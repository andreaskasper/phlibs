<?php
/**
 * PageCache — HTTP cache header manager.
 *
 * Static utility class for managing HTTP cache headers including Cache-Control,
 * Last-Modified, Expires, and 304 Not Modified responses. Also provides HTML
 * meta tags for browser-side cache hints.
 *
 * @author    Andreas Kasper <andreas.kasper@goo1.de>
 * @package   phlibs
 * @version   1.1.0
 * @license   FreeFoodLicense
 * @copyright 2012-2024 by Andreas Kasper
 */

namespace phlibs;

class PageCache {

    /**
     * @var int|null Unix timestamp of the last content modification.
     */
    private static $lastchange = null;

    /**
     * @var int|null Cache TTL in seconds (Time-To-Live for client-side cache).
     */
    public static $ttl = null;

    /**
     * @var int|null Server-side cache TTL in seconds (s-maxage).
     */
    public static $ttl_server = null;

    /**
     * @var int|null Stale-while-revalidate time in seconds.
     */
    public static $ttl_revalidate = null;

    /**
     * @var int|null Unix timestamp until when the cache is valid.
     */
    public static $until = null;

    /**
     * @var int|null Unix timestamp until when the server cache is valid.
     */
    public static $until_server = null;

    /**
     * @var string|null Cache pragma directive ('public' or 'private').
     */
    public static $pragma = null;

    /**
     * Set or get the cache TTL values.
     *
     * @param int|null $ttl              Client-side TTL in seconds.
     * @param int|null $ttl_server       Server-side TTL in seconds (s-maxage).
     * @param int|null $ttl_revalidate   Stale-while-revalidate time in seconds.
     * @return int|null Current TTL value.
     */
    public static function ttl($ttl = null, $ttl_server = null, $ttl_revalidate = null) {
        if ($ttl != null) self::$ttl = $ttl;
        if ($ttl_server != null) self::$ttl_server = $ttl_server;
        if ($ttl_revalidate != null) self::$ttl_revalidate = $ttl_revalidate;
        return self::$ttl;
    }

    /**
     * Set or get the last modification timestamp.
     *
     * Tracks the most recent modification across multiple calls (keeps the latest).
     *
     * @param int|string|null $timestamp Unix timestamp or date string.
     * @return int|null Current last-change timestamp.
     */
    public static function lastchange($timestamp = null) {
        if ($timestamp == null) return self::$lastchange;
        if (is_numeric($timestamp)) $timestamp = (int)$timestamp;
        else $timestamp = strtotime($timestamp);
        if (self::$lastchange == null) self::$lastchange = $timestamp;
        else self::$lastchange = max(self::$lastchange, $timestamp);
        return self::$lastchange;
    }

    /**
     * Check for conditional request and send 304 Not Modified if appropriate.
     *
     * Compares the If-Modified-Since request header against the stored lastchange
     * timestamp. Terminates the script with a 304 response if the content hasn't changed.
     *
     * @return void
     */
    public static function check304(): void {
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && self::$lastchange != null) {
            if (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= self::$lastchange) {
                header($_SERVER["SERVER_PROTOCOL"] . " 304 Not Modified", true, 304);
                exit(1);
            }
        }
    }

    /**
     * Send HTTP cache headers based on the current configuration.
     *
     * Sends appropriate Cache-Control, Last-Modified, Expires, and Pragma headers.
     * If no caching is configured (no TTL/until and no pragma), sends no-cache headers.
     *
     * @return void
     */
    public static function header(): void {
        if (self::$lastchange != null) {
            header("Last-Modified: " . gmdate('D, d M Y H:i:s ', self::$lastchange) . "GMT");
        }

        // Caching fully disabled
        if ((self::$until == null && self::$ttl == null) || !in_array(self::$pragma, array("private", "public"))) {
            header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
            header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            header('Pragma: no-cache');
            return;
        }

        // TTL-based caching
        if (self::$ttl != null) {
            $cc = array();
            $cc[] = "max-age=" . self::$ttl;
            if (self::$ttl_server != null) {
                $cc[] = "s-maxage=" . self::$ttl_server;
            } else {
                $cc[] = "s-maxage=" . self::$ttl;
            }
            if (self::$ttl_revalidate != null) {
                $cc[] = "stale-while-revalidate=" . self::$ttl_revalidate;
            } else {
                $cc[] = "stale-while-revalidate=" . floor(self::$ttl / 2);
            }
            $cc[] = self::$pragma;
            header("Cache-Control: " . implode(", ", $cc));
            return;
        }

        // Until-based caching
        if (self::$until != null) {
            header("Cache-Control: " . self::$pragma
                . ", s-maxage=" . (self::$until_server - time())
                . ", max-age=" . (self::$until - time()));
            return;
        }
    }

    /**
     * Generate HTML meta tags for cache control.
     *
     * @param bool $echo Whether to echo the output (default: true).
     * @return string The generated HTML meta tags.
     */
    public static function meta($echo = true): string {
        $out = "";

        if (self::$ttl != null) {
            $out .= '<meta http-equiv="Cache-control" content="public">' . PHP_EOL;
            $out .= '<meta http-equiv="expires" content="' . gmdate('D, d M Y H:i:s ', time() + self::$ttl) . 'GMT">' . PHP_EOL;
        } elseif (self::$until != null) {
            $out .= '<meta http-equiv="Cache-control" content="public">' . PHP_EOL;
            $out .= '<meta http-equiv="expires" content="' . gmdate('D, d M Y H:i:s ', self::$until) . 'GMT">' . PHP_EOL;
        } else {
            $out .= '<meta http-equiv="expires" content="0">' . PHP_EOL;
        }

        $lastchange = self::$lastchange ?? time();
        $out .= '<meta http-equiv="last-modified" content="' . gmdate('D, d M Y H:i:s ', $lastchange) . 'GMT" />' . PHP_EOL;
        $out .= '<meta name="cachecheck" content="' . date("d.m.Y H:i:s") . '"/>' . PHP_EOL;

        if ($echo) echo($out);
        return $out;
    }

    /**
     * Force a 304 Not Modified response regardless of timestamps.
     *
     * Use when you know the content hasn't changed and want to short-circuit.
     *
     * @return never Terminates the script.
     */
    public static function CacheWins(): void {
        if (isset($_SERVER["HTTP_IF_MODIFIED_SINCE"])) {
            header($_SERVER["SERVER_PROTOCOL"] . " 304 Not Modified", true, 304);
            exit(1);
        }
    }
}
