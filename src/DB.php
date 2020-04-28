<?php

/**
 * Klasse für PDO
 * @author Andreas Kasper <Andreas.Kasper@plabsi.com>
 * @package ASICMS
 * @version 0.1.20200428
 * LastChange: Erster Commit
 * 
 * Änderung am 28.04.2020 Commit
 * Nur noch ab php Version 7 möglich. Einige Optimierungen dafür.
 */
 
namespace phlibs;

class DB {
    private static $_cache = array();
    private $_connection_id = null;
    private $conn = null;
    private $_lastresult = null;
    
   

    public static function init(int $id, string $connectionstring, $user, $password) {
        self::$_cache[$id]["connectionstring"] = $connectionstring;
        self::$_cache[$id]["user"] = $user;
        self::$_cache[$id]["password"] = $password;
    }

    public function __construct(int $id) {
        $this->_connection_id = $id;
        $this->conn = new PDO(self::$_cache[$id]["connectionstring"],self::$_cache[$id]["user"],self::$_cache[$id]["password"]);
    }

    public function __get($name) {
        switch(strtolower($name)) {
            case "drivername": return $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            case "lastcmd": return $this->_lastresult->queryString;
        }
        trigger_error("Variable not found ".$name, E_USER_WARNING);
    }

    public function cmd(string $sql, Array $values = array()) {
        $this->_lastresult = $this->conn->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        if (!$this->_lastresult->execute($values)) trigger_error("Fehler beim ausführen von DB-Befehl (".$sql.")");
        return $this->_lastresult;
    }

    public function exec(string $sql) {
        return $this->conn->exec($sql);
    }

    public function cmdrow(string $sql, Array $values = array()) {
        $sth = $this->cmd($sql, $values);
        $row = $sth->fetch(PDO::FETCH_BOTH);
        return $row;
    }

    public function cmdrows(string $sql, Array $values = array(), $key = null) {
        $sth = $this->cmd($sql, $values);
        $rows = $sth->fetchAll(PDO::FETCH_BOTH);
        return $rows;
    }



}

