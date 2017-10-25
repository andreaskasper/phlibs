<?php

/**
 * 
 * @author Andreas Kasper <djassi@users.sourceforge.net>
 * @category ASICMS
 * @copyright 2012-2017 by Andreas Kasper
 * @name WebCache
 * @link http://www.plabsi.com Plabsi Weblabor
 * @license FastFoodLicense
 * @version 0.1.151229
 */
 
 namespace phlibs;

class WebCache {
	
	/**
	 * Zähler für die Webanfragen
	 * @static
	 * @var integer
	 */
	private static $WebRequestCounter = 0;

	/**
	 * Macht eine Webanfrage und gibt den Wert zurück. Wenn keine Daten geladen werden können, kommt NULL.
	 * @param string $url Webadresse
	 * @param integer $sec Cachelaufzeit in Sekunden
	 * @param string|mixed $needle Array oder String der Werte, die in der Antwort vorkommen müssen
	 * @return string|null Quellcode der Webseite oder NULL
	 * @static
	 */
	public static function get($url, $sec = 86400, $needle = "") {
		$id = md5($url)."@webcache";
		$db = new SQL(0);
		$row = $db->cmdrow('SELECT * FROM main.cache WHERE id="{0}" LIMIT 0,1', array($id));
		if (!isset($row["dt_created"]) OR ($row["dt_created"]+rand($sec/2, $sec*(self::$WebRequestCounter+1)) < time())) {
			self::$WebRequestCounter++;
			$html = @file_get_contents($url);
			$j = true;
			if (is_string($needle) and ($needle != "")) $j = (strpos($html, $needle) !== FALSE);
			if (is_array($needle)) foreach ($needle as $a) if ($j AND (strpos($html, $a) === FALSE)) $j = false;;
			if ($j) {
				$row = array();
				$row["id"] = $id;
				$row["value"] = $html;
				$row["dt_created"] = time();
				$row["dt_timeout"] = time()+365*86400;
				$db->CreateUpdate("main.cache", $row);
			}
		}
		if (!isset($row["value"])) return null;
		return $row["value"];
	}
	
	public static function getJSON($url, $sec = 86400, $needle = "") {
		return json_decode(self::get($url, $sec, $needle), true);
	}
	
	public static function getObject($url, $sec = 86400, $needle = "") {
		$out = array("created" => null,"data" => null, "from_cache" => true);
		$id = md5($url);
		$local = "/var/tmp/".substr($id,0,1)."/".substr($id,0,3)."/".$id.".webcache";
		@mkdir("/var/tmp/".substr($id,0,1)."/".substr($id,0,3)."/",0777,true);
		if (!file_exists($local) OR (filemtime($local)+rand($sec/2, $sec*(self::$WebRequestCounter+1)) < time())) {
			self::$WebRequestCounter++;
			$html = @file_get_contents($url);
			$j = true;
			if (is_string($needle) and ($needle != "")) $j = (strpos($html, $needle) !== FALSE);
			if (is_array($needle)) foreach ($needle as $a) if ($j AND (strpos($html, $a) === FALSE)) $j = false;;
			if ($j) { file_put_contents($local, $html); $out["from_cache"] = false; }
		}
		if (!file_exists($local)) return null;
		$out["created"] = filemtime($local);
		$out["data"] = @file_get_contents($local);
		return $out;
	}
}
