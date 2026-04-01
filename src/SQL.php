<?php
/**
 * SQL — MySQLi database wrapper with connection pooling and query helpers.
 *
 * Provides a full-featured abstraction over MySQLi with connection URI parsing,
 * automatic retry logic, placeholder-based escaping ({0}, {1}, …), query history,
 * timing, long-query callbacks, and convenience methods for common CRUD operations.
 *
 * @author  Andreas Kasper <andreas.kasper@goo1.de>
 * @package phlibs
 * @version 2.0.0
 * @license FreeFoodLicense
 *
 * Changelog:
 *   2019-01-20  Added processes property
 *   2019-01-21  Added success property
 *   2019-05-08  cmdrows accepts key parameter again
 *   2020-04-28  Result cache support
 *   2024-xx-xx  Connection retry logic, CreateUpdateArray, multicmd,
 *               setLongQueryCallback, affected_rows, max_allowed_packet
 */

namespace phlibs;

class SQL {

    /**
     * @var array<int, array> Static connection pool indexed by connection number.
     */
    private static $_connections = array();

    /**
     * @var string|null Last executed SQL command.
     */
    private $_lastcmd = null;

    /**
     * @var array<int, array{cmd: string, time: float}> Query history (last 100 queries).
     */
    private static $_history = array();

    /**
     * @var int Total number of queries executed across all instances.
     */
    private static $_counter = 0;

    /**
     * @var float Total time spent on queries in seconds.
     */
    private static $_timer = 0;

    /**
     * @var \mysqli|null Active MySQLi connection handle.
     */
    private $conn = null;

    /**
     * @var \mysqli_result|bool|null Result of the last query.
     */
    private $result = null;

    /**
     * @var callable|null Callback function for slow query logging.
     */
    private static $_logquerycallback = null;

    /**
     * @var int Threshold in seconds for triggering the slow query callback.
     */
    private static $_logquerycallbacktime = 999;

    /**
     * @var array<string, mixed> Result cache storage.
     */
    private static $_resultcache = array();

    /**
     * @var bool Whether the result cache is enabled.
     */
    private static $_resultcacheenabled = false;

    /**
     * Create a new SQL instance for the given connection number.
     *
     * @param int $connectionOrNumber Connection number (default: 0).
     * @throws \Exception If the argument is not an integer.
     */
    public function __construct($connectionOrNumber = 0) {
        if (is_integer($connectionOrNumber)) {
            $this->conn = $this->Verbindungsnr($connectionOrNumber);
        } else {
            throw new \Exception("Invalid argument: expected a connection number (integer).");
        }
    }

    /**
     * Magic getter for common properties.
     *
     * Supported properties:
     *   - lastcmd:          Last executed SQL string
     *   - lastid/lastkey/key/insertid: Last auto-increment ID
     *   - counter:          Total query count
     *   - history:          Query history array
     *   - error:            Last MySQLi error message
     *   - success:          Whether the last query succeeded
     *   - processes:        Active MySQL process count (excluding self)
     *   - processesmaxtime: Longest running process time
     *
     * @param  string $name Property name.
     * @return mixed
     */
    public function __get($name) {
        switch ($name) {
            case "lastcmd":
                return $this->_lastcmd;
            case "lastid":
            case "lastkey":
            case "key":
            case "insertid":
                return $this->conn->insert_id;
            case "counter":
                return self::$_counter;
            case "history":
                return self::$_history;
            case "error":
                return $this->conn->error;
            case "success":
                return (bool)($this->result);
            case "processes":
                return $this->cmdvalue('SELECT COUNT(*) FROM INFORMATION_SCHEMA.PROCESSLIST WHERE info IS NOT NULL') - 1;
            case "processesmaxtime":
                return $this->cmdvalue('SELECT MAX(TIME) FROM INFORMATION_SCHEMA.PROCESSLIST WHERE info IS NOT NULL');
        }
        trigger_error("Unknown property: " . $name, E_USER_WARNING);
        return null;
    }

    /**
     * Enable the internal result cache.
     *
     * @return void
     */
    public static function enableresultcache(): void {
        self::$_resultcacheenabled = true;
    }

    /**
     * Count active MySQL processes (excluding the current connection).
     *
     * @return int Number of active processes.
     */
    public function countprocesses(): int {
        return $this->cmdvalue('SELECT COUNT(*) FROM INFORMATION_SCHEMA.PROCESSLIST WHERE info IS NOT NULL') - 1;
    }

    /**
     * Get the maximum execution time of all active MySQL processes.
     *
     * @return int Maximum time in seconds.
     */
    public function processesmaxtime(): int {
        return $this->cmdvalue('SELECT MAX(TIME) FROM INFORMATION_SCHEMA.PROCESSLIST WHERE info IS NOT NULL');
    }

    /**
     * Register a callback for queries exceeding a time threshold.
     *
     * The callback receives an array with keys 'query' (string) and 'dauer' (float).
     *
     * @param int      $seconds  Threshold in seconds.
     * @param callable $callback Function to call for slow queries.
     * @return void
     */
    public static function setLongQueryCallback(int $seconds, callable $callback): void {
        self::$_logquerycallback = $callback;
        self::$_logquerycallbacktime = $seconds;
    }

    /**
     * Initialize a database connection from a URI string.
     *
     * URI format: mysql://user:password@host:port/database/prefix
     *
     * @param int    $ConnNr Connection number (slot in the connection pool).
     * @param string $DBuri  MySQL connection URI.
     * @return bool True on success.
     * @throws \Exception If the URI scheme is not 'mysql'.
     */
    public static function init(int $ConnNr, string $DBuri): bool {
        self::$_connections[$ConnNr]["conn"] = $DBuri;
        $a = parse_url($DBuri);
        $b = explode("/", $a["path"]);
        if ($a["scheme"] != "mysql") {
            throw new \Exception("Unsupported URI scheme: " . $a["scheme"] . " (expected 'mysql')");
        }
        self::$_connections[$ConnNr]["scheme"] = "mysql";
        if (!isset($a["port"])) $a["port"] = 3306;
        self::$_connections[$ConnNr]["host"]     = $a["host"];
        self::$_connections[$ConnNr]["port"]     = $a["port"];
        self::$_connections[$ConnNr]["user"]     = $a["user"];
        self::$_connections[$ConnNr]["password"] = $a["pass"] ?? "";
        self::$_connections[$ConnNr]["database"] = $b[1];
        self::$_connections[$ConnNr]["prefix"]   = $b[2] ?? '';
        return true;
    }

    /**
     * Establish or retrieve the MySQLi connection for a given connection number.
     *
     * Uses automatic retry logic: up to 50 attempts with 500ms intervals.
     * Falls back to the legacy mysql_connect() if MySQLi is unavailable.
     * Sets charset to UTF-8 on new connections.
     *
     * @param int $connection Connection number.
     * @return \mysqli The MySQLi connection handle.
     * @throws \Exception If no connection config exists or the connection fails.
     */
    public function Verbindungsnr(int $connection) {
        if (!isset(self::$_connections[$connection]["vnr"])) {
            // Auto-init from environment if available
            if (!isset(self::$_connections[$connection]) && isset($_ENV["config"]["db"][$connection]["conn"])) {
                self::init($connection, $_ENV["config"]["db"][$connection]["conn"]);
            }
            if (!isset(self::$_connections[$connection])) {
                throw new \Exception("No connection config for database #" . $connection);
            }
            if (function_exists("mysqli_connect")) {
                self::$_connections[$connection]["scheme"] = "mysql";

                $maxRetries    = 50;
                $retryDelayMs  = 500;
                $connected     = false;
                $lastError     = null;

                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    try {
                        self::$_connections[$connection]["vnr"] = mysqli_connect(
                            self::$_connections[$connection]["host"],
                            self::$_connections[$connection]["user"],
                            self::$_connections[$connection]["password"],
                            self::$_connections[$connection]["database"],
                            self::$_connections[$connection]["port"]
                        );
                        $connected = true;
                        break;
                    } catch (\mysqli_sql_exception $e) {
                        $lastError = $e->getMessage();
                        if ($attempt < $maxRetries) {
                            usleep($retryDelayMs * 1000);
                        }
                    }
                }

                if (!$connected) {
                    throw new \Exception(
                        "Database connection failed after {$maxRetries} attempts "
                        . "({$retryDelayMs}ms interval). Last error: " . $lastError
                    );
                }
            } elseif (function_exists("mysql_connect")) {
                /** @noinspection PhpDeprecationInspection Legacy fallback */
                self::$_connections[$connection]["vnr"] = mysql_connect(
                    self::$_connections[$connection]["host"],
                    self::$_connections[$connection]["user"],
                    self::$_connections[$connection]["password"]
                );
                if (self::$_connections[$connection]["vnr"]) {
                    mysql_select_db(self::$_connections[$connection]["database"], self::$_connections[$connection]["vnr"]);
                } else {
                    throw new \Exception("MySQL connection failed: " . mysql_error());
                }
            } else {
                throw new \Exception("Neither the MySQLi nor the MySQL extension is available.");
            }
            if (!self::$_connections[$connection]["vnr"]) {
                throw new \Exception("Could not connect to database server #" . $connection, 1);
            }
            self::$_connections[$connection]["vnr"]->query("SET NAMES 'utf8'");
            self::$_connections[$connection]["vnr"]->query("SET CHARACTER_SET_CLIENT='utf8'");
        }
        return self::$_connections[$connection]["vnr"];
    }

    /**
     * Execute a SQL query with placeholder escaping.
     *
     * Placeholders like {0}, {1}, {name} in the SQL string are replaced with
     * the corresponding escaped values from the $values array.
     *
     * @param string $sql    SQL query with optional placeholders.
     * @param array  $values Associative or indexed array of values to escape.
     * @return \mysqli_result|bool Query result or false on failure.
     */
    public function cmd(string $sql = "", array $values = array()) {
        foreach ($values as $k => $v) {
            $sql = str_replace("{" . $k . "}", $this->conn->real_escape_string($v ?? ""), $sql);
        }

        self::$_counter++;

        $dauer = microtime(true);
        $this->result = $this->conn->query($sql);
        $dauer = microtime(true) - $dauer;
        self::$_timer += $dauer;

        if ($dauer > self::$_logquerycallbacktime && !is_null(self::$_logquerycallback)) {
            call_user_func(self::$_logquerycallback, array("query" => $sql, "dauer" => $dauer));
        }

        $this->_lastcmd = $sql;
        SQL::$_history[] = array("cmd" => $sql, "time" => $dauer);
        unset(SQL::$_history[100]);
        return $this->result;
    }

    /**
     * Execute multiple SQL statements in a single call.
     *
     * @param string $sql   Semicolon-separated SQL statements.
     * @param bool   $quiet If true, discard all result sets immediately.
     * @return void
     */
    public function multicmd(string $sql, bool $quiet = true): void {
        $this->conn->multi_query($sql);
        do {
            if ($result = $this->conn->store_result()) {
                if ($quiet) {
                    $result->free();
                }
            }
        } while ($this->conn->more_results() && $this->conn->next_result());
    }

    /**
     * Check whether the last query was successful.
     *
     * @return bool True if the last query returned a truthy result.
     */
    public function success(): bool {
        return (bool)($this->result);
    }

    /**
     * Get the auto-increment ID from the last INSERT.
     *
     * @return int|string The insert ID.
     */
    public function insert_id() {
        return $this->conn->insert_id;
    }

    /**
     * Alias for insert_id().
     *
     * @return int|string The insert ID.
     */
    public function lastinsert_id() {
        return $this->conn->insert_id;
    }

    /**
     * Get the number of rows affected by the last UPDATE, DELETE, or INSERT.
     *
     * @return int Number of affected rows.
     */
    public function affected_rows(): int {
        return $this->conn->affected_rows;
    }

    /**
     * Execute a query and return all result rows as an array.
     *
     * @param string|null $sql    SQL query (returns empty array if null).
     * @param array|null  $values Placeholder values.
     * @param string|null $key    Optional column name to use as array key.
     * @param int|null    $style  MYSQLI_ASSOC, MYSQLI_NUM, or MYSQLI_BOTH (default: MYSQLI_ASSOC).
     * @return array Array of result rows.
     * @throws \RuntimeException If the query fails.
     */
    public function cmdrows(?string $sql = null, ?array $values = null, ?string $key = null, ?int $style = null): array {
        if (is_null($sql)) return array();
        if (is_null($values)) $values = array();
        if (is_null($style)) $style = MYSQLI_ASSOC;

        $result = $this->cmd($sql, $values);
        if (!$result) {
            throw new \RuntimeException("Invalid SQL command: (" . $this->_lastcmd . ")\n" . $this->conn->error, 602);
        }
        $out = array();
        if ($result->num_rows > 0) {
            if (is_null($key)) {
                while ($tmp = $result->fetch_array($style)) {
                    $out[] = $tmp;
                }
            } else {
                while ($tmp = $result->fetch_array($style)) {
                    $out[$tmp[$key]] = $tmp;
                }
            }
        }
        $result->free();
        return $out;
    }

    /**
     * Execute a query and return the first result row.
     *
     * @param string   $sql    SQL query.
     * @param array    $values Placeholder values.
     * @param int|null $style  MYSQLI_ASSOC, MYSQLI_NUM, or MYSQLI_BOTH (default: MYSQLI_ASSOC).
     * @return array The first row as an associative array, or empty array if no results.
     * @throws \RuntimeException If the query fails.
     */
    public function cmdrow(string $sql = "", array $values = array(), ?int $style = null): array {
        if (is_null($style)) $style = MYSQLI_ASSOC;
        $result = $this->cmd($sql, $values);
        if (!$result) {
            throw new \RuntimeException("Invalid SQL command: (" . $this->_lastcmd . ")\n" . $this->conn->error, 602);
        }
        $row = array();
        if ($result->num_rows > 0) {
            $row = $result->fetch_array($style);
            $result->free();
            $this->result = null;
        }
        return $row;
    }

    /**
     * Fetch the next row from the current result set.
     *
     * Call after cmd() to iterate through results manually.
     *
     * @param int|null $style MYSQLI_ASSOC, MYSQLI_NUM, or MYSQLI_BOTH (default: MYSQLI_ASSOC).
     * @return array The next row.
     */
    public function row(?int $style = null): array {
        if (is_null($style)) $style = MYSQLI_ASSOC;
        return $this->result->fetch_array($style);
    }

    /**
     * Execute a query and return a single scalar value (first column of first row).
     *
     * @param string $sql    SQL query.
     * @param array  $values Placeholder values.
     * @return mixed|null The scalar value, or null if no results.
     * @throws \RuntimeException If the query fails.
     */
    public function cmdvalue(string $sql = "", array $values = array()) {
        $result = $this->cmd($sql, $values);
        if (!$result) {
            throw new \RuntimeException("Invalid SQL command: (" . $this->_lastcmd . ")\n" . $this->conn->error, 602);
        }
        if ($result->num_rows > 0) {
            $row = $result->fetch_array(MYSQLI_NUM);
        }
        return isset($row) ? $row[0] : null;
    }

    /**
     * Update rows in a table.
     *
     * @param string $table       Table name.
     * @param array  $arr         Column => value pairs to SET.
     * @param array  $ids         Column name(s) to use in the WHERE clause (values taken from $arr).
     * @param int    $LimitAnzahl Optional LIMIT (default: -1 = no limit).
     * @return \mysqli_result|bool Query result.
     */
    public function Update(string $table = "", array $arr = array(), $ids = array(), int $LimitAnzahl = -1) {
        if (!is_array($ids)) $ids = array($ids);

        $fSet   = array();
        $fWhere = array();
        foreach ($arr as $key => $v) {
            $fSet[] = ' `' . $key . '` = "' . $this->conn->real_escape_string($v) . '" ';
        }
        foreach ($ids as $key) {
            $fWhere[] = ' (`' . $key . '` = "' . $this->conn->real_escape_string($arr[$key]) . '") ';
        }

        $sql = "UPDATE " . $table . " SET " . implode(",", $fSet) . " WHERE " . implode(" AND ", $fWhere);
        if ($LimitAnzahl > -1) $sql .= " LIMIT " . $LimitAnzahl;

        return $this->cmd($sql);
    }

    /**
     * Insert a new row (INSERT IGNORE).
     *
     * Duplicate key violations are silently ignored.
     *
     * @param string $table Table name.
     * @param array  $arr   Column => value pairs. NULL values produce SQL NULL.
     * @return \mysqli_result|bool Query result.
     */
    public function Create(string $table = "", array $arr = array()) {
        $fSet = array();
        foreach ($arr as $key => $v) {
            if (is_null($v)) {
                $fSet[] = ' `' . $key . '` = NULL ';
            } else {
                $fSet[] = ' `' . $key . '` = "' . $this->conn->real_escape_string($v) . '" ';
            }
        }
        $sql = "INSERT IGNORE INTO " . $table . " SET " . implode(",", $fSet);
        return $this->cmd($sql);
    }

    /**
     * Insert a row or update it on duplicate key (UPSERT).
     *
     * @param string            $table Table name.
     * @param array             $arr   Column => value pairs. NULL values produce SQL NULL.
     * @param string|array|null $modes Optional insert modes: 'delayed', 'low_priority'.
     * @return \mysqli_result|bool Query result.
     */
    public function CreateUpdate(string $table = "", array $arr = array(), $modes = null) {
        $fSet = array();
        foreach ($arr as $key => $v) {
            if (is_null($v)) {
                $fSet[] = ' `' . $key . '` = NULL ';
            } else {
                $fSet[] = ' `' . $key . '` = "' . $this->conn->real_escape_string($v) . '" ';
            }
        }

        $sql = "INSERT INTO " . $table . " SET " . implode(",", $fSet)
             . " ON DUPLICATE KEY UPDATE " . implode(",", $fSet);

        if (!empty($modes)) {
            if (is_string($modes)) $modes = array($modes);
            foreach ($modes as $mode) {
                switch ($mode) {
                    case "delayed":
                        $sql = "INSERT DELAYED " . substr($sql, 7);
                        break;
                    case "low_priority":
                        $sql = "INSERT LOW_PRIORITY " . substr($sql, 7);
                        break;
                }
            }
        }
        return $this->cmd($sql);
    }

    /**
     * Get the server's max_allowed_packet value.
     *
     * @return int Packet size limit in bytes.
     */
    public function max_allowed_packet(): int {
        return $this->cmdrow("SHOW VARIABLES LIKE 'max_allowed_packet'", array())['Value'] ?? 0;
    }

    /**
     * Bulk upsert multiple rows in a single INSERT ... ON DUPLICATE KEY UPDATE statement.
     *
     * Column order is derived from the first row. Missing keys in subsequent rows
     * default to NULL. Supports NULL, bool, int, float, string, and DateTimeInterface values.
     *
     * Note: Uses VALUES(col) syntax which is valid in MariaDB. MySQL 8.0.20+ deprecates
     * this in favor of alias syntax.
     *
     * @param string            $table Table name.
     * @param array             $rows  Array of associative arrays (rows to upsert).
     * @param string|array|null $modes Optional insert modes: 'delayed', 'low_priority'.
     * @return \mysqli_result|bool Query result.
     * @throws \Exception If $rows is empty or malformed.
     */
    public function CreateUpdateArray(string $table, array $rows = array(), $modes = null) {
        if (empty($rows) || !is_array($rows[0]) || empty($rows[0])) {
            throw new \Exception("No data provided for CreateUpdateArray.", 1);
        }

        $columns = array_keys($rows[0]);

        $quote = function ($v) {
            if (is_null($v)) return "NULL";
            if ($v instanceof \DateTimeInterface) return '"' . $this->conn->real_escape_string($v->format('Y-m-d H:i:s')) . '"';
            if (is_bool($v)) return $v ? "1" : "0";
            if (is_int($v) || is_float($v)) return (string)$v;
            return '"' . $this->conn->real_escape_string((string)$v) . '"';
        };

        $values = [];
        foreach ($rows as $row) {
            $tuple = [];
            foreach ($columns as $col) {
                $tuple[] = array_key_exists($col, $row) ? $quote($row[$col]) : "NULL";
            }
            $values[] = '(' . implode(',', $tuple) . ')';
        }

        $updates = [];
        foreach ($columns as $col) {
            $updates[] = '`' . $col . '` = VALUES(`' . $col . '`)';
        }

        $colsSql = '`' . implode('`,`', $columns) . '`';
        $sql = "INSERT INTO {$table} ({$colsSql}) VALUES " . implode(',', $values)
            . " ON DUPLICATE KEY UPDATE " . implode(',', $updates);

        if (!empty($modes)) {
            if (is_string($modes)) $modes = array($modes);
            foreach ($modes as $mode) {
                switch ($mode) {
                    case "delayed":
                        $sql = "INSERT DELAYED " . substr($sql, 7);
                        break;
                    case "low_priority":
                        $sql = "INSERT LOW_PRIORITY " . substr($sql, 7);
                        break;
                }
            }
        }

        return $this->cmd($sql);
    }

    /**
     * Escape a string for safe use in SQL queries.
     *
     * @param string $str String to escape.
     * @return string Escaped string.
     */
    public function convtxt($str): string {
        return $this->conn->real_escape_string($str);
    }

    /**
     * Escape a string for safe use in SQL queries (alias for convtxt).
     *
     * @param string $str String to escape.
     * @return string Escaped string.
     */
    public function real_escape_string($str): string {
        return $this->conn->real_escape_string($str);
    }
}
