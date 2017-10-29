<?php

namespace phlibs;

class string2 {

	public static function vall($value) {
		if (strpos($value, ",") !== FALSE) {
			$value = str_replace(".", "", $value);
			$value = str_replace(",", ".", $value);
		}
		if ($value + 0 != $value) return "";
		return $value + 0;
	}
	
	public static function parse_phonenr($txt) {
		if (substr($txt,0,1) == "+") $txt = "00".substr($txt, 1, 999);
		$txt = preg_replace("@[^0-9]@", "", $txt);
		return $txt;
	}
	
	public static function Abkuerzen($txt, $length = 160) {
		if (strlen($txt) <= $length) return $txt;
		$p = strrpos(substr($txt,0,161), " ");
		return substr($txt, 0, $p)."…";
	}
}