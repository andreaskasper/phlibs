<?php
/**
 * Klasse zum behandeln von MySQL anfragen
 * @author Andreas Kasper <Andreas.Kasper@plabsi.com>
 * @package ASICMS
 * @version 0.1.20160408
 * LastChange: Umstellung auf mysqli
 */
 
 namespace phlibs;
 
class SQL {

	/**
	 * In dieser Variablen werden die Informationen zur Verbindung gespeichert.
	 * @var $_conn
	 */
	private static $_connections = array();

	/**
	 * Das letzte MySQL-Result
	 * @var mysqlresult
	 */
	private $result = null;

	private $conn = null;

	/**
	 * Sollen Fehler ausgegeben werden?
	 */
	var $showerror = false;
	
	

	/**
	 * Letzter ausgeführter Befehl
	 * @var string
	 */
	var $lastcmd = "";
	var $lasterrornr = 0;
	var $lasterror = "";

	/**
	 * Die Historie der ausgeführten Befehle.
	 * @var array
	 */
	var $history = array();

	/**
	 * Einstellungswert der festlegt, ob eine Befehlshistorie geführt werden soll
	 * @var boolean
	 */
	//var $savehistory = false;
	public static $counter = 0;
	public static $timer = 0;

	/**
	 * Konstruktor für die SQL-Klasse
	 * @param integer $defaultconnection Die Verbindnungsnummer die Standardmäßig verwendet werden soll. Wenn zusätzlich die DBuri angegeben ist, wird diese überschrieben.
	 * @param uri $DBuri URI der MySQL-Datenbank. (optional)
	 */
	function __construct($connectionOrNumber = 0) {
		if (is_integer($connectionOrNumber)) $this->conn = $this->Verbindungsnr($connectionOrNumber);
	}
	
	public static function init($ConnNr = 0, $DBuri) {
		self::$_connections[$ConnNr]["conn"] = $DBuri;
		$a = parse_url($DBuri);
		$b = explode("/", $a["path"]);
		if ($a["scheme"] != "mysql") trigger_error("Kein MySQL-Schema");
		if (!isset($a["port"])) $a["port"] = 3306;
		self::$_connections[$ConnNr]["host"] = $a["host"];
		self::$_connections[$ConnNr]["port"] = $a["port"];
		self::$_connections[$ConnNr]["user"] = $a["user"];
		self::$_connections[$ConnNr]["password"] = (isset($a["pass"])?$a["pass"]:"");
		self::$_connections[$ConnNr]["database"] = $b[1];
		self::$_connections[$ConnNr]["prefix"] = $b[2];
		return true;
	}

	/** Führt einen SQL Befehl aus.
	 *
	 * @param integer $connection Verweis auf die Verbindnungsnummer in der Konfigurationsdatei
	 * @param string $sql SQL-Befehl
	 * @param boolean $silent Schnellere ausführung aber keine Antwort
	 * @param array $values Die Werte, die im Command ersetzt werden sollen.
	 * @result mysqlresult
	 */
	function cmd($sql = "", $values = array()) {
		foreach ($values as $k=>$v) $sql = str_replace("{".$k."}", $this->conn->real_escape_string($v), $sql); 
		//Exploit?
/*
		if (strpos($sql, "---") !== FALSE) {
			trigger_error("SQL-Script injection per ---", E_USER_ERROR);
			return 0;
		}
*/
		$this->lasterrornr = 0;
		$this->lasterror = "";
		self::$counter++;
		
		$dauer = microtime(true);
		$this->result = $this->conn->query($sql);
		$dauer = microtime(true)-$dauer;
		self::$timer += $dauer;
		//echo($sql.'<br/>');
		$this->lastcmd = $sql;
		$this->history[] = array("cmd" => $sql, "time" => $dauer);
		unset($this->history[100]);
		return $this->result;
	}
	
	function cmdBackground($sql = "", $values = array()) {
		return $this->cmd($sql, $value);
	}

	/** Zählt die Anzahl der Zeilen der vorangegangenen Abfrage.
	 *
	 * @result integer
	 */
	function countrows() {
		return $this->result->num_rows;
	}
	
	/**
	* Diese muss den Parameter SQL_CALC_FOUND_ROWS haben...
	**/
	function countallrows($connection = -1) {
		return $this->cmdvalue($connection, "SELECT FOUND_ROWS()");
	}

	/** Ermittelt die Verbindungsnummer aus einer definierten Connectionnummer aus den Daten der Konfigurationsdatei
	 *
	 * @param integer $connection Verweis auf die Verbindnungsnummer in der Konfigurationsdatei
	 * @result mysqlresult
	 */
	public function Verbindungsnr($connection) {
		if (!isset(self::$_connections[$connection]["vnr"])) {
			if (!isset(self::$_connections[$connection]) && isset($_ENV["config"]["db"][$connection]["conn"])) self::init($connection, $_ENV["config"]["db"][$connection]["conn"]);
			if (!isset(self::$_connections[$connection])) {
				throw new \Exception("Kein Verbindungsschema für Datenbank Nummer".$connection."!");
				exit(1);
			}
			if (!function_exists("mysqli_connect")) {
				throw new \Exception("Die MySQLi-Extension wurde nicht auf dem Server installiert!");
				exit();
			}
			self::$_connections[$connection]["vnr"] = mysqli_connect(self::$_connections[$connection]["host"], self::$_connections[$connection]["user"], self::$_connections[$connection]["password"], self::$_connections[$connection]["database"], self::$_connections[$connection]["port"]);
			if (!self::$_connections[$connection]["vnr"]) throw new SQLException("Keine Verbindung zum Datenbank-Server ".$connection, 1);
			self::$_connections[$connection]["vnr"]->query("SET NAMES 'utf8'");
			self::$_connections[$connection]["vnr"]->query("SET CHARACTER_SET_CLIENT='utf8'");
		};
		return self::$_connections[$connection]["vnr"];
	}
	
	public function getTableName($tablename = "", $connection = -1) {
		if ($connection == -1) $connection = $this->defaultconnection;
		return self::$_conn[$connection]["dbprefix"].$tablename;
	}

	/** Ermittelt alle Ergebniszeilen einer MySQL-Abfrage
	 *
	 * @param integer $connection Verweis auf die Verbindnungsnummer in der Konfigurationsdatei
	 * @param string $sql SQL-Befehl
	 * @result array
	 */
	function cmdrows($sql = "", $values = array(), $key = null) {
		$result = $this->cmd($sql, $values);
		if (!$result) {
			throw new SQLException("Ungueltiger SQL-Befehl: (".$sql.")!\r\n".mysql_error(),602);
			exit(1);
		};
		$out = array();
		if ($result->num_rows > 0) {
			if ($key == null) for ($res = array(); $tmp = $result->fetch_array(MYSQLI_BOTH);) $out[] = $tmp;
			else for ($res = array(); $tmp = $result->fetch_array(MYSQLI_BOTH);) $out[$tmp[$key]] = $tmp;
		}
		$result->free();
		return $out;
	}

	/** Ermittelt eine/die erste Ergebniszeile aus einer MySQL-Abfrage.
	 *
	 * @param integer $connection Verweis auf die Verbindnungsnummer in der Konfigurationsdatei
	 * @param string $sql SQL-Befehl
	 * @result array
	 */
	function cmdrow($sql = "", $values = array()) {
		$result = $this->cmd($sql, $values);
		if (!$result) {
			//die("Ungueltiger SQL-Befehl: (".$sql.")!\r\n".mysql_error($this->Verbindungsnr($connection)));
				throw new SQLException("Ungültiger SQL-Befehl: (".$sql.")!\r\n\r\n".$this->conn->error, 602);
				exit(1);
		}
		$row = array();
		if ($result->num_rows > 0)
		$row = $result->fetch_assoc();
		$result->free();  //Natürlich geben wir den Speicher wieder frei
		$this->result = null;
		return $row;
	}
	
	public function row() {
		return $this->result->fetch_assoc();
	}

	function cmdvalue($sql = "", $values = array()) {
		$result = $this->cmd($sql, $values);
		if (!$result) {
			throw new SQLException("Ungueltiger SQL-Befehl: (".$sql.")!\r\n".mysql_error(), 602);
		}
		if ($result->num_rows > 0)
		$row = $result->fetch_array(MYSQL_NUM);
		return isset($row) ? $row[0] : null;
	}

	/** Führt einen Datenbankenupdate aus
	 *
	 * @param integer $conn Verweis auf die Verbindnungsnummer in der Konfigurationsdatei
	 * @param string $table Name der MySQL-Tabelle.
	 * @param array $arr Ein Array aus Werten, wobei der Schlüssel die Feldbezeichnung ist.
	 * @param string/array $ids Array oder String mit Schlüsselwerten der Tabelle.
	 * @param integer $LimitAnzahl Anzahl der Zeilen, die Upgedated werden dürfen.
	 * @result mysqlresult
	 */
	function Update($table = "", $arr = array(), $ids = "", $LimitAnzahl = -1) {
		if (is_string($ids)) $ids = array($ids);
		if (strpos($table, "`"))
		$table = self::BQStable($conn, $table);
		
		$fSet = array();
		$fWhere = array();
		foreach ($arr as $key => $v) $fSet[] = ' `'.$key.'` = "'.mysql_real_escape_string($v,$this->Verbindungsnr($conn)).'"';
		foreach ($ids as $key) $fWhere[] = ' (`'.$key.'` = "'.mysql_real_escape_string($arr[$key],$this->Verbindungsnr($conn)).'")';

		$sql = "UPDATE ".$table." SET ".implode(",",$fSet)." WHERE ".implode("AND",$fWhere)." ";
		if ($LimitAnzahl != -1) $sql .= " LIMIT ".$LimitAnzahl;

		return $this->cmd($conn, $sql);
	}

	function BQSset($arr) {
		foreach ($arr as $key => $value) $fSet2[] = ' `'.$key.'` = "'.$this->conn->real_escape_string($value).'" ';
		return implode(",", $fSet2);
	}

	function BQStable($str) {
		$a = 0;
		$j = false;
		$g = array("","");
		for ($i = 0; $i < strlen($str); $i++) {
			$z=substr($str, $i, 1);
			if ($z == "`") { $j = !$j; $g[$a] .= $z;}
			elseif (($z == ".") AND (!$j)) $a++;
			else $g[$a] .= $z;
		}
		if ($g[1] == "") {
			$g[1] = $g[0];
			$a = parse_url(self::$_connections[$conn]["conn"]);
			$b = explode("/", $a["path"]);
			$g[0] = $b[1];
		}
		if (strpos($g[0], "`") === FALSE) $g[0] = "`".$g[0]."`";
		if (strpos($g[1], "`") === FALSE) $g[1] = "`".$g[1]."`";
			
		return $g[0].'.'.$g[1];
	}

	/**
	 * Ermittelt die PRIMARY-Felder einer Tabelle
	 * @param integer $conn Verbindungsnummer
	 * @param string $table NAme der Tabelle
	 * @return array
	 */
	function GetTableIDs($conn = -1, $table = "") {
		if ($conn == -1) $conn = $this->defaultconnection;
		$out =array();
		$rows = $this->cmdrows($conn, "SHOW COLUMNS FROM ".$table);
		foreach ($rows as $row) {
			if ($row["Key"] == "PRI") $out[] = $row["Field"];
		}
		return $out;
	}

	/** Erstellt oder updated einen MySQL Datensatz
	 *
	 * @param integer $conn Verweis auf die Verbindnungsnummer in der Konfigurationsdatei
	 * @param string $table Name der MySQL-Tabelle.
	 * @param array $arr Ein Array aus Werten, wobei der Schlüssel die Feldbezeichnung ist.
	 * @param string/array $ids Array oder String mit Schlüsselwerten der Tabelle.
	 * @result mysqlresult
	 */
	function Create($table = "", $arr = array()) {
		$sql = "INSERT LOW_PRIORITY IGNORE INTO ".$table." SET ".$this->BQSset($arr);
		return $this->cmd($sql);
	}


	/** Erstellt oder updated einen MySQL Datensatz
	 *
	 * @param integer $conn Verweis auf die Verbindnungsnummer in der Konfigurationsdatei
	 * @param string $table Name der MySQL-Tabelle.
	 * @param array $arr Ein Array aus Werten, wobei der Schlüssel die Feldbezeichnung ist.
	 * @param string/array $ids Array oder String mit Schlüsselwerten der Tabelle.
	 * @result mysqlresult
	 */
	function CreateUpdate($table = "", $arr = array()) {
		$fset = self::BQSset($arr);

		$sql = "INSERT LOW_PRIORITY INTO ".$table." SET ".$fset." ON DUPLICATE KEY UPDATE ".$fset;
		return $this->cmd($sql);
	}

	function CreateUpdateOld( $conn = -1, $table = "", $arr = array(), $ids = "") {
		if ($conn == -1) $conn = $this->defaultconnection;
		if (strpos($table,".") === FALSE) {
			$table = $_ENV["config"]["mysql"][$conn]["database"].".".$table;
		}
		if ($ids == "") {
			$ids = $this->GetTableIDs($conn, $table);
		}
		if (is_string($ids)) $ids = array($ids);
		if (strpos($table, "`"))
		$table = "`".implode("`.`", explode(".", $table))."`";

		foreach ($arr as $key => $v) $fSet[] = ' `'.$key.'` = "'.mysql_real_escape_string($v, $this->Verbindungsnr($conn)).'"';
		foreach ($ids as $key) $fWhere[] = ' (`'.$key.'` = "'.mysql_real_escape_string($arr[$key], $this->Verbindungsnr($conn)).'")';
		foreach ($ids as $key) $fSet2[] = ' `'.$key.'` = "'.mysql_real_escape_string($arr[$key], $this->Verbindungsnr($conn)).'" ';

		$sql = "INSERT LOW_PRIORITY IGNORE INTO ".$table." SET ".implode(",",$fSet2);
		$this->cmd($conn, $sql, true);
		$sql = "UPDATE LOW_PRIORITY ".$table." SET ".implode(",",$fSet)." WHERE ".implode("AND",$fWhere)." ";
		return $this->cmd($conn, $sql, true);
	}

	/** Führt einen Datenbankenselect aus
	 *
	 * @param integer $conn Verweis auf die Verbindungsnummer in der Konfigurationsdatei
	 * @param string $table Name der MySQL-Tabelle.
	 * @param string $where Bestandteil der WHERE Anfrage
	 * @param array $arrFields Welche Felder sollen ausgegeben werden?
	 * @result mysqlresult
	 */
	function Select($conn = -1, $fields = "", $WhereData = array(), $WhereStructure = "1") {
		if ($conn == -1) $conn = $this->defaultconnection;
		if (is_string($fields)) $fields = array($fields);

		foreach ($fields as $key => $Itm) {
			if (substr_count($Itm) == 0) $Itm = $_ENV["config"]["mysql"][$connection]["table"].".".$Itm;
			if (substr_count($Itm) == 1) $Itm = $_ENV["config"]["mysql"][$connection]["database"].".".$Itm;
			//$Itm = str_replace("`", "", $Itm);
			//$Itm = "`".str_replace(".", "`.`", $Itm)."`";
			$arrf[$key] = $Itm;
			$usedfields[$Itm] = $Itm;
			$usedtables[substr($Itm,0, strrpos($Itm, "."))] = substr($Itm,0, strrpos($Itm, "."));
		}

		//FIXME: Vervollständigen

			
		$sql = "SELECT ".implode(",",$arrFields)." FROM ".$table." WHERE ".$Where;
		return $this->cmd($conn, $sql);
	}
	
	function Simple($conn = -1, $command = "", $data = array()) {
		$g = string::ZwischenstringArray($command, "{", "}");
		foreach($g as $a) {
			$command = str_replace("{".$a."}", str_replace(chr(39), chr(39).chr(39), $data[$a]), $command);
			}
		unset($g);
	return $this->cmdrows($conn, $command);
	}
	
	function LastInsertKey($conn = -1) {
		return $this->conn->insert_id;
		}
	
	/**
	 * Splittet einen Connectionstring in seine Bestandteile auf und fügt Sie in die Konfiguration ein.
	 * Bei erfolg wird die ConnectionID zurückgegeben. Bei Misserfolg FALSE.
	 * 
	 * @param integer $id ConnectionID
	 * @param string $aurl Connectionstring
	 * @return bool/integer
	 */
	function ConnectionString($id = -1, $aurl = "") {
		if ($id == -1) $id = count($_ENV["config"]["mysql"]);
		$b = parse_url($aurl);
		if ($b["scheme"] != "mysql") return FALSE;
		$_ENV["config"]["mysql"][$id]["host"] = $b["host"];
		if ($b["port"] + 0 == 0) $b["port"] = 3306;
		$_ENV["config"]["mysql"][$id]["port"] = $b["port"];
		$_ENV["config"]["mysql"][$id]["user"] = $b["user"];
		$_ENV["config"]["mysql"][$id]["password"] = $b["pass"];
		$g = explode("/", $b["path"]);
		$_ENV["config"]["mysql"][$id]["database"] = $g[0];
		$_ENV["config"]["mysql"][$id]["table"] = $g[1];
		return $id;
	}

	public static function convtxt($txt) {
		$txt = str_replace(chr(34), chr(34).chr(34), $txt);
		return $txt;
	}
	
	function export($conn = -1, $database = "", $filename = "") {
		$doc = new DomDocument("1.0", "UTF-8");
		$doc->formatOutput = true;
		$root = $doc->appendChild($doc->createElement("MySQL"));
		$root->setAttributeNS(XMLNS_NAMESPACE, "xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
		$root->setAttributeNS("http://www.w3.org/2001/XMLSchema-instance", "xsi:noNamespaceSchemaLocation", "http://asicms.sourceforge.net/SQLExport.xsd");
		
		$root->setAttribute("version", "1.0");

		$rootdb = $root->appendChild($doc->createElement("database"));
		$rootdb->setAttribute("name" , $database);
		$trows = $this->cmdrows($conn, "SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA = '".$database."'");
		foreach ($trows as $trow) {
			$roott = $rootdb->appendChild($doc->createElement("table"));
			$roott->setAttribute("name" , $trow["TABLE_NAME"]);
			$roott->setAttribute("engine" , $trow["ENGINE"]);

			$crows = $this->cmdrows($conn, "SELECT * FROM information_schema.COLUMNS WHERE (`TABLE_SCHEMA` = '".$database."') AND (`TABLE_NAME`='".$trow["TABLE_NAME"]."') ORDER BY ORDINAL_POSITION");
			foreach ($crows as $crow) {
				$rootc = $roott->appendChild($doc->createElement("field"));
				$rootc->setAttribute("name" , $crow["COLUMN_NAME"]);
				$rootc->setAttribute("type" , $crow["COLUMN_TYPE"]);
				$rootc->setAttribute("collation" , $crow["COLLATION_NAME"]);
				if ($crow["COLUMN_KEY"] == "PRI")
					$rootc->setAttribute("primary" , true);
				if ($crow["EXTRA"] != "")
					$rootc->setAttribute("extra" , $crow["EXTRA"]);
				}
			
			$crows = $this->cmdrows($conn, "SELECT * FROM information_schema.TABLE_CONSTRAINTS WHERE (`CONSTRAINT_SCHEMA` = '".$database."') AND (`TABLE_NAME`='".$trow["TABLE_NAME"]."')");
			$rooti = $roott->appendChild($doc->createElement("indexes"));
			foreach ($crows as $crow) {
				$rootc = $rooti->appendChild($doc->createElement("index"));
				$rootc->setAttribute("name" , $crow["CONSTRAINT_NAME"]);
				$rootc->setAttribute("type" , $crow["CONSTRAINT_TYPE"]);
				$rootc->appendChild($doc->createTextNode($crow["CONSTRAINT_TYPE"]));
				}
				
			}
		
		if ($filename == "") echo($doc->saveXML());
		}
		
	function import($conn = -1, $filename = "") {
		$doc = new DomDocument("1.0", "UTF-8");
		$doc = loadXML($filename);
		//TODO: Muss fertiggestellt werden!
		}
}

class SQLException extends \Exception {

}
