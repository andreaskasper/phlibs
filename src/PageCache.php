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
 
class PageCache {

	private static $lastchange = null;
	public static $ttl = null;
	public static $ttl_server = null;
	public static $ttl_revalidate = null;
	public static $until = null;
	public static $until_server = null;
	public static $pragma = null;

	public static function ttl($ttl = null, $ttl_server = null, $ttl_revalidate = null) {
		if ($ttl != null) self::$ttl = $ttl;
		if ($ttl_server != null) self::$ttl_server = $ttl_server;
		if ($ttl_revalidate != null) self::$ttl_revalidate = $ttl_revalidate;
		return self::$ttl;
	}
	
	public static function lastchange($timestamp = null) {
		if ($timestamp == null) return self::$lastchange;
		if (is_numeric($timestamp)) $timestamp = strtotime($timestamp);
		if (self::$lastchange == null) self::$lastchange = $timestamp;
		else self::$lastchange = max(self::$lastchange,$timestamp);
	}
	
	 
	/*Ausgaben*/
	
	public static function check304() {
		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) AND self::$lastchange != null) {
			if (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= self::$lastchange) {
				header($_SERVER["SERVER_PROTOCOL"]." 304 nimm den Cache", true, 304); 
				exit(1); 
			}
		}
	}
	
	public static function header() {
		if (self::$lastchange != null) header("Last-Modified: ".gmdate('D, d M Y H:i:s ', self::$lastchange)."GMT");
		
	
		/*Caching komplett deaktiviert*/
		if ((self::$until == null AND self::$ttl == null) OR !in_array(self::$pragma, array("private","public"))) {
			header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
			header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
			header('Pragma: no-cache');
			return;
		}
		
		if (self::$ttl != null) {
			$cc = array();
			$cc[] = "max-age=".self::$ttl;
			if (self::$ttl != null AND self::$ttl_server != null) $cc[] = "s-maxage=".self:$ttl_server; else $cc[] = "s-maxage=".self:$ttl;
			if (self::$ttl_revalidate != null) $cc[] = "stale-while-revalidate=".self::$ttl_revalidate; else $cc[] = "stale-while-revalidate=".floor(self:$ttl/2);
			$cc[] = self::$pragma;
			
			//header("Expires: ".gmdate('D, d M Y H:i:s ', time()+self::$ttl)."GMT");
			header("Cache-Control: ".implode(", ", $cc));
			return;
		}
		if (self::$until != null) {
			//header("Expires: ".gmdate('D, d M Y H:i:s ', self::$until)."GMT");
			header("Cache-Control: ".self::$pragma.", s-maxage=".(self::$until_server-time()).", max-age=".(self::$until-time()));
			return;
		}
		
	}
	
	public static function meta($echo = true) {
		$out = "";
		
		if (self::$ttl != null) {
			$out .= '<meta http-equiv="Cache-control" content="public">'.PHP_EOL;
			$out .= '<meta http-equiv="expires" content="'.gmdate('D, d M Y H:i:s ', time()+self::$ttl).'GMT">'.PHP_EOL;
			
		}
		elseif (self::$until != null) {
			$out .= '<meta http-equiv="Cache-control" content="public">'.PHP_EOL;
			$out .= '<meta http-equiv="expires" content="'.gmdate('D, d M Y H:i:s ', self::$until).'GMT">'.PHP_EOL;
			
		} else {
			$out .= '<meta http-equiv="expires" content="0">'.PHP_EOL;
		}
		
		if (self::$lastchange != null) $lastchange = self::$lastchange; else $lastchange = time();
		 $out .= '<meta http-equiv="last-modified" content="'.gmdate('D, d M Y H:i:s ', $lastchange).'GMT" />'.PHP_EOL;
		$out .= '<meta name="cachecheck" content="'.date("d.m.Y H:i:s").'"/>'.PHP_EOL;
		//$out .= ''.var_export($_SERVER,true).'';
		if ($echo) echo($out); 
		return $out;
	}
	
	public static function CacheWins() {
		if (isset($_SERVER["HTTP_IF_MODIFIED_SINCE"])) {
			header($_SERVER["SERVER_PROTOCOL"]." 304 nimm den Cache", true, 304); 
			exit(1); 
		}
	}
	 
	 
}
