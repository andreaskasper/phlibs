<?php
/**
 * DB — PDO database wrapper with connection pooling.
 *
 * Provides a lightweight abstraction over PDO with the same cmd/cmdrow/cmdrows/cmdvalue
 * API as the SQL class. Supports any PDO-compatible database driver.
 *
 * @author  Andreas Kasper <andreas.kasper@goo1.de>
 * @package phlibs
 * @version 1.0.0
 * @license FreeFoodLicense
 *
 * Changelog:
 *   2020-04-28  Initial commit
 */

namespace phlibs;

class DB {

    /**
     * @var array<int, array> Static connection pool indexed by connection ID.
     */
    private static $_cache = array();

    /**
     * @var int|null Active connection ID.
     */
    private $_connection_id = null;

    /**
     * @var \PDO|null Active PDO connection handle.
     */
    private $conn = null;

    /**
     * @var \PDOStatement|null Result of the last query.
     */
    private $_lastresult = null;

    /**
     * Initialize a PDO connection configuration.
     *
     * Does not connect immediately — the connection is established lazily
     * when a DB instance is created.
     *
     * @param int    $id               Connection ID (slot in the pool).
     * @param string $connectionstring PDO DSN (e.g. 'mysql:host=localhost;dbname=mydb').
     * @param string $user             Database username.
     * @param string $password         Database password.
     * @return void
     */
    public static function init(int $id, string $connectionstring, $user, $password): void {
        self::$_cache[$id]["connectionstring"] = $connectionstring;
        self::$_cache[$id]["user"]             = $user;
        self::$_cache[$id]["password"]         = $password;
        self::$_cache[$id]["conn"]             = null;
    }

    /**
     * Create a new DB instance and establish the PDO connection if needed.
     *
     * @param int $id Connection ID (must be previously initialized via init()).
     * @throws \PDOException If the connection fails.
     */
    public function __construct(int $id) {
        $this->_connection_id = $id;
        if (is_null(self::$_cache[$id]["conn"])) {
            self::$_cache[$id]["conn"] = new \PDO(
                self::$_cache[$id]["connectionstring"],
                self::$_cache[$id]["user"],
                self::$_cache[$id]["password"]
            );
            self::$_cache[$id]["conn"]->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
        $this->conn = self::$_cache[$id]["conn"];
    }

    /**
     * Magic getter for common properties.
     *
     * Supported properties:
     *   - drivername: PDO driver name (e.g. 'mysql', 'sqlite')
     *   - lastcmd:    The SQL string of the last executed query
     *
     * @param  string $name Property name.
     * @return mixed
     */
    public function __get($name) {
        switch (strtolower($name)) {
            case "drivername":
                return $this->conn->getAttribute(\PDO::ATTR_DRIVER_NAME);
            case "lastcmd":
                return $this->_lastresult->queryString;
        }
        trigger_error("Unknown property: " . $name, E_USER_WARNING);
        return null;
    }

    /**
     * Execute a SQL query with placeholder escaping.
     *
     * Placeholders like {0}, {1} are replaced with the corresponding values.
     * Note: Values are NOT escaped via PDO prepared statements — use with caution.
     *
     * @param string $sql    SQL query with optional {n} placeholders.
     * @param array  $values Values to substitute into placeholders.
     * @return \PDOStatement The query result.
     */
    public function cmd(string $sql, array $values = array()): \PDOStatement {
        $keys = array();
        foreach ($values as $k => $v) {
            $keys[] = "{" . $k . "}";
        }
        $sql = str_replace($keys, $values, $sql);

        $this->_lastresult = $this->conn->query($sql);
        return $this->_lastresult;
    }

    /**
     * Execute a non-query SQL statement (INSERT, UPDATE, DELETE, DDL).
     *
     * @param string $sql SQL statement.
     * @return int|false Number of affected rows, or false on failure.
     */
    public function exec(string $sql) {
        return $this->conn->exec($sql);
    }

    /**
     * Execute a query and return the first result row.
     *
     * @param string $sql    SQL query.
     * @param array  $values Placeholder values.
     * @return array|false The first row, or false if no results.
     */
    public function cmdrow(string $sql, array $values = array()) {
        $sth = $this->cmd($sql, $values);
        return $sth->fetch(\PDO::FETCH_BOTH);
    }

    /**
     * Execute a query and return all result rows.
     *
     * @param string      $sql    SQL query.
     * @param array       $values Placeholder values.
     * @param string|null $key    (Reserved) Column to use as array key.
     * @return array Array of rows.
     */
    public function cmdrows(string $sql, array $values = array(), $key = null): array {
        $sth = $this->cmd($sql, $values);
        return $sth->fetchAll(\PDO::FETCH_BOTH);
    }

    /**
     * Execute a query and return a single scalar value (first column of first row).
     *
     * @param string $sql    SQL query.
     * @param array  $values Placeholder values.
     * @return mixed|null The scalar value.
     */
    public function cmdvalue(string $sql, array $values = array()) {
        $sth = $this->cmd($sql, $values);
        $row = $sth->fetch(\PDO::FETCH_NUM);
        return $row[0] ?? null;
    }
}
