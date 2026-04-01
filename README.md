# 📚 phlibs — PHP Class Library

A lightweight PHP utility library extracted from the ASICMS framework. Provides database abstraction (MySQL/MariaDB via MySQLi and PDO), monetary value objects with currency support, HTTP page caching, web content caching, and string helpers.

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-FreeFoodLicense-green.svg)](https://packagist.org/packages/andreaskasper/phlibs)
[![Latest Stable Version](https://poser.pugx.org/andreaskasper/phlibs/v/stable.svg)](https://packagist.org/packages/andreaskasper/phlibs)
[![Total Downloads](https://poser.pugx.org/andreaskasper/phlibs/downloads)](https://packagist.org/packages/andreaskasper/phlibs)
[![GitHub Issues](https://img.shields.io/github/issues/andreaskasper/phlibs.svg)](https://github.com/andreaskasper/phlibs/issues)

---

## Installation

```bash
composer require andreaskasper/phlibs
```

Or add to your `composer.json`:

```json
{
    "require": {
        "andreaskasper/phlibs": "*"
    }
}
```

---

## Classes

### SQL — MySQLi Database Wrapper

Full-featured MySQLi wrapper with connection pooling, query history, placeholder-based escaping (`{0}`, `{1}`, …), and convenience methods for common operations.

```php
use phlibs\SQL;

// Initialize connection via URI
SQL::init(0, "mysql://user:password@localhost:3306/mydb/");

$db = new SQL(0);

// Query with placeholder escaping
$rows = $db->cmdrows('SELECT * FROM users WHERE status = "{0}"', ['active']);
$row  = $db->cmdrow('SELECT * FROM users WHERE id = "{0}"', [42]);
$count = $db->cmdvalue('SELECT COUNT(*) FROM users');

// Insert / Upsert
$db->Create('users', ['name' => 'Alice', 'email' => 'alice@example.com']);
$db->CreateUpdate('users', ['id' => 1, 'name' => 'Alice', 'visits' => 5]);

// Bulk upsert
$db->CreateUpdateArray('users', [
    ['id' => 1, 'name' => 'Alice'],
    ['id' => 2, 'name' => 'Bob'],
]);

// Update with WHERE keys
$db->Update('users', ['id' => 1, 'name' => 'Updated'], ['id']);

// Long query monitoring
SQL::setLongQueryCallback(5, function($info) {
    error_log('Slow query: ' . $info['query'] . ' (' . $info['dauer'] . 's)');
});
```

**Features:**
- Connection URI parsing (`mysql://user:pass@host:port/database/prefix`)
- Automatic connection retry (50 attempts, 500ms interval)
- `{n}` placeholder escaping via `real_escape_string`
- Query counter, timer, and history (last 100 queries)
- Long-running query callback
- Multi-query support (`multicmd`)
- `max_allowed_packet()` helper
- Magic properties: `$db->lastcmd`, `$db->insertid`, `$db->counter`, `$db->error`, `$db->success`

---

### DB — PDO Database Wrapper

Lightweight PDO wrapper with the same `cmd` / `cmdrow` / `cmdrows` / `cmdvalue` API as the SQL class. Supports any PDO-compatible database (MySQL, PostgreSQL, SQLite, …).

```php
use phlibs\DB;

DB::init(0, 'mysql:host=localhost;dbname=mydb', 'user', 'password');

$db = new DB(0);
$row = $db->cmdrow('SELECT * FROM users WHERE id = {0}', [42]);
```

---

### Money — Immutable Monetary Value Object

Represents monetary amounts with currency awareness. Fully immutable — all arithmetic operations return new instances. Supports locale-aware formatting and pluggable exchange rates.

```php
use phlibs\Money;

// Create
$price = new Money(19.99, 'EUR');
$price = Money::EUR(19.99);                         // Static factory
$price = Money::fromString('EUR', '1.234,56');       // German format

// Arithmetic (all return new instances)
$total    = $price->add(5.00);                       // 24.99 EUR
$diff     = $price->subtract(Money::EUR(10));         // 9.99 EUR
$doubled  = $price->multiply(2);                      // 39.98 EUR
$split    = $price->divide(3);                        // 6.66 EUR
$absolute = $price->negate()->abs();                  // 19.99 EUR

// Formatting
echo $price->format('de');     // "19,99€"
echo $price->format('en');     // "19.99€"
echo Money::USD(42)->format(); // "$42.00"
echo (string) $price;          // Uses default locale

// Properties
$price->amount;    // 19.99
$price->currency;  // 'EUR'
$price->symbol;    // '€'
$price->name;      // 'Euro'

// Comparisons
$price->isPositive();              // true
$price->isZero();                  // false
$price->equals(Money::EUR(19.99)); // true
$price->compareTo($other);        // -1, 0, or 1

// Currency exchange
Money::setExchangeRateProvider(function($from, $to) {
    return MyRateService::getRate($from, $to);
});
$inPln = $price->exchangeTo('PLN');        // Uses provider
$inUsd = $price->exchangeTo('USD', 1.08);  // Explicit rate
```

**Features:**
- Immutable value object pattern
- Static factory methods (`Money::EUR(amount)`, `Money::USD(amount)`, …)
- Locale-aware formatting (German, English, European conventions)
- Zero-decimal currencies (HUF, JPY, CZK)
- Symbol placement (before for USD/GBP, after for EUR/CHF)
- Pluggable exchange rate provider
- Comparison methods (`equals`, `compareTo`, `isZero`, `isPositive`, `isNegative`)
- `toArray()` and `jsonSerialize()` for serialization

---

### Currency — Currency Value Object

Represents an ISO 4217 currency with its metadata. Acts as the single source of truth for symbols, names, decimal behavior, and symbol placement across the library. Covers 45+ currencies.

```php
use phlibs\Currency;

// Create
$eur = new Currency('EUR');
$usd = Currency::get('USD');

// Properties
$eur->id;          // 'EUR'
$eur->symbol;      // '€'
$eur->name;        // 'Euro'
$eur->decimals;    // 2
(string) $eur;     // 'EUR'

// Query behavior
$eur->isZeroDecimal();   // false
$eur->isSymbolBefore();  // false
$eur->isKnown();         // true
$eur->equals($usd);      // false

// Static helpers (no instance needed)
Currency::symbolFor('GBP');  // '£'
Currency::nameFor('JPY');    // 'Yen'
Currency::allCodes();        // ['AED', 'ARS', 'AUD', ...]

// Format amounts directly
$eur->format(19.99, 'de');   // '19,99€'
$eur->format();              // '€' (symbol only)
```

**Supported currencies:** AED, ARS, AUD, BGN, BRL, CAD, CHF, CLP, CNY, COP, CZK, DKK, EGP, EUR, GBP, HKD, HRK, HUF, IDR, ILS, INR, ISK, JPY, KRW, MAD, MXN, MYR, NOK, NZD, PEN, PHP, PKR, PLN, QAR, RON, RUB, SAR, SEK, SGD, THB, TRY, TWD, UAH, USD, VND, ZAR

---

### PageCache — HTTP Cache Header Manager

Static helper for HTTP caching: sends `Cache-Control`, `Last-Modified`, and `304 Not Modified` responses.

```php
use phlibs\PageCache;

PageCache::ttl(3600);              // Cache for 1 hour
PageCache::lastchange(filemtime(__FILE__));
PageCache::$pragma = 'public';
PageCache::check304();             // Send 304 if not modified
PageCache::header();               // Send cache headers
```

---

### WebCache — Web Content Cache

Caches HTTP responses either in a database table (`main.cache`) or on the local filesystem (`/var/tmp/`).

```php
use phlibs\WebCache;

$html = WebCache::get('https://example.com/api/data', 3600);
$json = WebCache::getJSON('https://example.com/api/data.json', 3600);
$obj  = WebCache::getObject('https://example.com/page', 86400);
```

---

### string2 — String Utilities

Helper class for number parsing (German/European format) and string manipulation.

```php
use phlibs\string2;

string2::vall('12.345,67');        // 12345.67 (German format)
string2::vall('123.45');           // 123.45 (US format)
string2::parse_phonenr('+49 170 1234567'); // '004917012345667'
string2::Abkuerzen('Long text...', 50);    // Truncated at word boundary
```

---

## Requirements

- PHP >= 7.4
- MySQLi extension (for `SQL` class)
- PDO extension (for `DB` class)

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests run automatically via GitHub Actions on PHP 7.4, 8.0, 8.1, 8.2, 8.3, and 8.4.

---

## Support

[![donate via Patreon](https://img.shields.io/badge/Donate-Patreon-green.svg)](https://www.patreon.com/AndreasKasper)
[![donate via PayPal](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.me/AndreasKasper)
[![donate via Ko-fi](https://img.shields.io/badge/Donate-Ko--fi-green.svg)](https://ko-fi.com/andreaskasper)
[![Sponsors](https://img.shields.io/github/sponsors/andreaskasper)](https://github.com/sponsors/andreaskasper)
