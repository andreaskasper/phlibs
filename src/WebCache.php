<?php
/**
 * WebCache — HTTP content cache with database and filesystem backends.
 *
 * Caches remote web content to reduce external HTTP calls. Supports two storage
 * backends: a MySQL/MariaDB table (main.cache) via the SQL class, and local
 * filesystem storage (/var/tmp/). Includes content validation via needle matching.
 *
 * @author    Andreas Kasper <andreas.kasper@goo1.de>
 * @package   phlibs
 * @version   1.0.0
 * @license   FreeFoodLicense
 * @copyright 2012-2024 by Andreas Kasper
 */

namespace phlibs;

class WebCache {

    /**
     * @var int Counter for web requests made during this process.
     */
    private static $WebRequestCounter = 0;

    /**
     * Fetch and cache a URL's content using the database backend.
     *
     * Stores cached responses in the `main.cache` table. Cache expiry is randomized
     * between 50%-100% of the specified TTL to prevent thundering herd effects.
     * The request counter influences the TTL multiplier to reduce load under high traffic.
     *
     * @param string       $url    URL to fetch.
     * @param int          $sec    Cache TTL in seconds (default: 86400 = 24 hours).
     * @param string|array $needle Required string(s) that must appear in the response
     *                             for it to be considered valid and cached.
     * @return string|null Response body, or null if not available.
     */
    public static function get($url, $sec = 86400, $needle = "") {
        $id = md5($url) . "@webcache";
        $db = new SQL(0);
        $row = $db->cmdrow('SELECT * FROM main.cache WHERE id="{0}" LIMIT 0,1', array($id));
        if (!isset($row["dt_created"]) || ($row["dt_created"] + rand($sec / 2, $sec * (self::$WebRequestCounter + 1)) < time())) {
            self::$WebRequestCounter++;
            $html = @file_get_contents($url);
            $j = true;
            if (is_string($needle) && ($needle != "")) {
                $j = (strpos($html, $needle) !== false);
            }
            if (is_array($needle)) {
                foreach ($needle as $a) {
                    if ($j && (strpos($html, $a) === false)) $j = false;
                }
            }
            if ($j) {
                $row = array();
                $row["id"]         = $id;
                $row["value"]      = $html;
                $row["dt_created"] = time();
                $row["dt_timeout"] = time() + 365 * 86400;
                $db->CreateUpdate("main.cache", $row);
            }
        }
        if (!isset($row["value"])) return null;
        return $row["value"];
    }

    /**
     * Fetch and cache a URL's content, returned as decoded JSON.
     *
     * @param string       $url    URL to fetch.
     * @param int          $sec    Cache TTL in seconds (default: 86400).
     * @param string|array $needle Required string(s) for response validation.
     * @return array|null Decoded JSON data, or null if not available.
     */
    public static function getJSON($url, $sec = 86400, $needle = "") {
        return json_decode(self::get($url, $sec, $needle), true);
    }

    /**
     * Fetch and cache a URL's content using the filesystem backend.
     *
     * Stores cached responses as files under /var/tmp/ with a hashed directory structure.
     * Returns metadata about the cached object including creation time and cache status.
     *
     * @param string       $url    URL to fetch.
     * @param int          $sec    Cache TTL in seconds (default: 86400).
     * @param string|array $needle Required string(s) for response validation.
     * @return array|null Array with keys 'created' (timestamp), 'data' (content),
     *                    'from_cache' (bool), or null if not available.
     */
    public static function getObject($url, $sec = 86400, $needle = "") {
        $out = array("created" => null, "data" => null, "from_cache" => true);
        $id = md5($url);
        $local = "/var/tmp/" . substr($id, 0, 1) . "/" . substr($id, 0, 3) . "/" . $id . ".webcache";
        @mkdir("/var/tmp/" . substr($id, 0, 1) . "/" . substr($id, 0, 3) . "/", 0777, true);
        if (!file_exists($local) || (filemtime($local) + rand($sec / 2, $sec * (self::$WebRequestCounter + 1)) < time())) {
            self::$WebRequestCounter++;
            $html = @file_get_contents($url);
            $j = true;
            if (is_string($needle) && ($needle != "")) {
                $j = (strpos($html, $needle) !== false);
            }
            if (is_array($needle)) {
                foreach ($needle as $a) {
                    if ($j && (strpos($html, $a) === false)) $j = false;
                }
            }
            if ($j) {
                file_put_contents($local, $html);
                $out["from_cache"] = false;
            }
        }
        if (!file_exists($local)) return null;
        $out["created"] = filemtime($local);
        $out["data"]    = @file_get_contents($local);
        return $out;
    }
}
