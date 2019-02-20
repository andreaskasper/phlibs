<?php
/**
 * Klasse zum behandeln von MySQL und MySQLi anfragen
 * @author Andreas Kasper <Andreas.Kasper@plabsi.com>
 * @package ASICMS
 * @version 0.1.2018113
 * LastChange: Umstellung auf beide Arten mysql/mysqli
 * 
 * Änderung am 20.01.2019 processes Variable
 * Änderung am 21.01.2019 success Variable
 */
 
class SQL {

    private static $_connections = array();
    private $_lastcmd = null;
    private static $_history = array();
    private static $_counter = 0;
    private static $_timer = 0;
    private $conn = null;
    private $result = null;

    function __construct($connectionOrNumber = 0) {
        if (is_integer($connectionOrNumber)) $this->conn = $this->Verbindungsnr($connectionOrNumber);
        else throw new \Exception("TODO: Keine Verbindungsnummer angegeben vielleicht über Connection...");
    }
    
    function __get($name) {
        switch ($name) {
            case "lastcmd": return $this->_lastcmd;
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
				$this->conn->error;
			case "success":
				return true;
			case "processes":
				return $this->cmdvalue('SELECT COUNT(*) FROM INFORMATION_SCHEMA.PROCESSLIST WHERE info IS NOT NULL')-1;
			case "processesmaxtime":
				return $this->cmdvalue('SELECT MAX(TIME) FROM INFORMATION_SCHEMA.PROCESSLIST WHERE info IS NOT NULL');
		}
		trigger_error("Unbekannte Variable ".$name, E_USER_WARNING);
        return null;
    }

    public static function init(int $ConnNr = 0, string $DBuri) {
		self::$_connections[$ConnNr]["conn"] = $DBuri;
		$a = parse_url($DBuri);
		$b = explode("/", $a["path"]);
        if ($a["scheme"] != "mysql") throw new \Exception("Kein MySQL-Schema der URI (".$a["scheme"].")");
        self::$_connections[$ConnNr]["scheme"] = "mysql";
		if (!isset($a["port"])) $a["port"] = 3306;
		self::$_connections[$ConnNr]["host"] = $a["host"];
		self::$_connections[$ConnNr]["port"] = $a["port"];
		self::$_connections[$ConnNr]["user"] = $a["user"];
		self::$_connections[$ConnNr]["password"] = $a["pass"] ?? "";
		self::$_connections[$ConnNr]["database"] = $b[1];
		self::$_connections[$ConnNr]["prefix"] = (isset($b[2])?$b[2]:'');
		return true;
	}

    public function Verbindungsnr(int $connection) {
		if (!isset(self::$_connections[$connection]["vnr"])) {
			if (!isset(self::$_connections[$connection]) && isset($_ENV["config"]["db"][$connection]["conn"])) self::init($connection, $_ENV["config"]["db"][$connection]["conn"]);
			if (!isset(self::$_connections[$connection])) {
				throw new \Exception("Kein Verbindungsschema für Datenbank Nummer".$connection."!");
				return null;
			}
			if (function_exists("mysqli_connect")) {
                self::$_connections[$connection]["scheme"] = "mysql";
                self::$_connections[$connection]["vnr"] = mysqli_connect(self::$_connections[$connection]["host"], self::$_connections[$connection]["user"], self::$_connections[$connection]["password"], self::$_connections[$connection]["database"], self::$_connections[$connection]["port"]);
            } elseif (function_exists("mysql_connect")) {
                self::$_connections[$connection]["vnr"] = mysql_connect(self::$_connections[$connection]["host"], self::$_connections[$connection]["user"], self::$_connections[$connection]["password"], self::$_connections[$connection]["database"], self::$_connections[$connection]["port"]);
            } else {
				throw new \Exception("Weder die MySQL- noch die MySQLi-Extension wurden auf dem Server gefunden.");
			}
			if (!self::$_connections[$connection]["vnr"]) throw new \Exception("Keine Verbindung zum Datenbank-Server ".$connection, 1);
			self::$_connections[$connection]["vnr"]->query("SET NAMES 'utf8'");
			self::$_connections[$connection]["vnr"]->query("SET CHARACTER_SET_CLIENT='utf8'");
		};
		return self::$_connections[$connection]["vnr"];
    }
    
    function cmd(string $sql = "", array $values = array()) {
		foreach ($values as $k=>$v) $sql = str_replace("{".$k."}", $this->conn->real_escape_string($v), $sql); 

		self::$_counter++;
		
		$dauer = microtime(true);
		$this->result = $this->conn->query($sql);
		$dauer = microtime(true)-$dauer;
		self::$_timer += $dauer;
		//echo($sql.'<br/>');
		$this->_lastcmd = $sql;
		SQL::$_history[] = array("cmd" => $sql, "time" => $dauer);
		unset(SQL::$_history[100]);
		return $this->result;
    }
    
    function cmdrows(string $sql = "", array $values = array(), string $key = null) {
		$result = $this->cmd($sql, $values);
		if (!$result) {
			throw new \SQLException("Ungültiger SQL-Befehl: (".$this->_lastcmd.")!\r\n".mysql_error(),602);
			return null;
		};
		$out = array();
		if ($result->num_rows > 0)
			for ($res = array(); $tmp = $result->fetch_array(MYSQLI_BOTH);) $out[] = $tmp;
		$result->free();
		return $out;
    }
    
    function cmdrow(string $sql = "", array $values = array()) {
		$result = $this->cmd($sql, $values);
		if (!$result) {
			throw new \SQLException("Ungültiger SQL-Befehl: (".$this->_lastcmd.")!\r\n".mysql_error(),602);
			return null;
		}
		$row = array();
		if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
		    $result->free();  //Natürlich geben wir den Speicher wieder frei
            $this->result = null;
        }
		return $row;
    }
    
    public function row() : array {
		return $this->result->fetch_assoc();
    }
    
    function cmdvalue(string $sql = "", array $values = array()) {
		$result = $this->cmd($sql, $values);
		if (!$result) {
			throw new \SQLException("Ungültiger SQL-Befehl: (".$this->_lastcmd.")!\r\n".mysql_error(),602);
			return null;
		}
		if ($result->num_rows > 0) {
            $row = $result->fetch_array(MYSQLI_NUM);
        }
		return isset($row) ? $row[0] : null;
    }
    
    function Update(string $table = "", array $arr = array(), $ids = array(), int $LimitAnzahl = -1) {
		if (!is_array($ids)) $ids = array($ids);
		
		$fSet = array();
		$fWhere = array();
		foreach ($arr as $key => $v) $fSet[] = ' `'.$key.'` = "'.$this->conn->real_escape_string($v).'" ';
		foreach ($ids as $key) $fWhere[] = ' (`'.$key.'` = "'.$this->conn->real_escape_string($arr[$key]).'") ';

		$sql = "UPDATE ".$table." SET ".implode(",",$fSet)." WHERE ".implode("AND",$fWhere)." ";
		if ($LimitAnzahl > -1) $sql .= " LIMIT ".$LimitAnzahl;

		return $this->cmd($sql);
    }
    
    function Create(string $table = "", array $arr = array()) {
        $fSet = array();
        foreach ($arr as $key => $v) $fSet[] = ' `'.$key.'` = "'.$this->conn->real_escape_string($v).'" ';

		$sql = "INSERT LOW_PRIORITY IGNORE INTO ".$table." SET ".implode(",",$fSet);
		return $this->cmd($sql);
    }
    
    function CreateUpdate(string $table = "", array $arr = array()) {
		$fSet = array();
        foreach ($arr as $key => $v) {
			if (is_null($v)) $fSet[] = ' `'.$key.'` = NULL ';
			else $fSet[] = ' `'.$key.'` = "'.$this->conn->real_escape_string($v).'" ';
		}

		$sql = "INSERT LOW_PRIORITY INTO ".$table." SET ".implode(",",$fSet)." ON DUPLICATE KEY UPDATE ".implode(",",$fSet);
		return $this->cmd($sql);
	}

	function convtxt($str) {
		return $this->conn->real_escape_string ($str);
	}

}
