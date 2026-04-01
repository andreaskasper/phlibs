<?php
/**
 * Money — Immutable value object for monetary amounts.
 *
 * Represents a monetary amount with a currency code (ISO 4217). All arithmetic
 * operations return new Money instances (immutable pattern). Supports locale-aware
 * formatting, currency exchange via pluggable rate providers or live API lookup,
 * and common comparisons.
 *
 * Delegates currency metadata (symbol, name, decimals) to the Currency class.
 *
 * Note: Internally stores amounts as float. For applications requiring exact decimal
 * arithmetic (e.g. banking), consider using bcmath or an integer-based (cents) approach.
 *
 * @author  Andreas Kasper <andreas.kasper@goo1.de>
 * @package phlibs
 * @version 2.2.0
 * @license FreeFoodLicense
 */

namespace phlibs;

class Money {

    /**
     * @var float The monetary amount.
     */
    private float $_amount;

    /**
     * @var string ISO 4217 currency code (e.g. 'EUR', 'USD', 'CHF').
     */
    private string $_currency;

    /**
     * @var callable|null Custom exchange rate provider callback.
     *                    Signature: function(string $from, string $to): ?float
     */
    private static $_exchangeRateProvider = null;

    // ─── Live Exchange Rate Configuration ──────────────────────────────

    /**
     * @var bool Whether live exchange rate lookups are enabled.
     */
    private static bool $_liveExchangeEnabled = false;

    /**
     * @var string Base URL for the exchange rate API.
     *             Default: frankfurter.app (free, no API key, ECB data).
     *             Must support: GET {url}/latest?base={FROM}
     *             Response: { "rates": { "USD": 1.08, ... } }
     */
    private static string $_exchangeApiUrl = 'https://api.frankfurter.dev';

    /**
     * @var int Cache TTL in seconds (default: 3600 = 1 hour).
     */
    private static int $_exchangeCacheTtl = 3600;

    /**
     * @var array<string, array{rates: array<string, float>, fetched_at: int}>
     *      Static in-memory cache of exchange rates, keyed by base currency.
     *      Each entry contains the rates array and a Unix timestamp.
     */
    private static array $_exchangeCache = [];

    /**
     * @var int HTTP timeout in seconds for API requests.
     */
    private static int $_exchangeApiTimeout = 5;

    // ─── Constructors ───────────────────────────────────────────────────

    /**
     * Create a new Money instance.
     *
     * @param float|int|string $amount   The monetary amount.
     * @param string           $currency ISO 4217 currency code.
     */
    public function __construct($amount, string $currency) {
        $this->_amount   = (float)$amount;
        $this->_currency = strtoupper(trim($currency));
    }

    /**
     * Create a Money instance from a European/German formatted string.
     *
     * Handles comma as decimal separator and dot as thousands separator.
     *
     * @param string $currency ISO 4217 currency code.
     * @param string $value    Formatted amount string (e.g. '1.234,56').
     * @return self
     */
    public static function fromString(string $currency, string $value): self {
        $parsed = string2::vall($value);
        return new self((float)$parsed, $currency);
    }

    /**
     * Convenience factory: Money::EUR(19.99)
     *
     * Supports any 3-letter currency code as a static method name.
     *
     * @param string $name      Currency code (called as static method).
     * @param array  $arguments First argument is the amount.
     * @return self
     * @throws \InvalidArgumentException If no amount is provided.
     */
    public static function __callStatic(string $name, array $arguments): self {
        if (empty($arguments)) {
            throw new \InvalidArgumentException("Amount is required: Money::{$name}(amount)");
        }
        return new self($arguments[0], $name);
    }

    // ─── Magic Methods ──────────────────────────────────────────────────

    /**
     * Magic getter for common properties.
     *
     * Supported: amount, currency, symbol, name, decimals
     *
     * @param  string $name Property name.
     * @return mixed
     */
    public function __get(string $name) {
        switch ($name) {
            case 'amount':   return $this->_amount;
            case 'currency': return $this->_currency;
            case 'symbol':   return $this->symbol();
            case 'name':     return $this->name();
            case 'decimals': return $this->decimals();
        }
        trigger_error("Unknown property: Money::" . $name, E_USER_WARNING);
        return null;
    }

    /**
     * String representation using default formatting.
     *
     * @return string Formatted money string.
     */
    public function __toString(): string {
        return $this->format();
    }

    // ─── Getters ────────────────────────────────────────────────────────

    /**
     * Get the numeric amount.
     *
     * @return float
     */
    public function amount(): float {
        return $this->_amount;
    }

    /**
     * Get the ISO 4217 currency code.
     *
     * @return string
     */
    public function currency(): string {
        return $this->_currency;
    }

    /**
     * Get the Currency value object for this money's currency.
     *
     * @return Currency
     */
    public function currencyObject(): Currency {
        return new Currency($this->_currency);
    }

    /**
     * Get the currency symbol (delegated to Currency).
     *
     * @return string Symbol (e.g. '€', '$') or the currency code if unknown.
     */
    public function symbol(): string {
        return Currency::symbolFor($this->_currency);
    }

    /**
     * Get the currency display name (delegated to Currency).
     *
     * @return string Human-readable name (e.g. 'Euro', 'Dollar') or the code if unknown.
     */
    public function name(): string {
        return Currency::nameFor($this->_currency);
    }

    /**
     * Get the number of decimal places for this currency (delegated to Currency).
     *
     * @return int 0 for zero-decimal currencies (HUF, JPY, …), 2 for all others.
     */
    public function decimals(): int {
        return $this->currencyObject()->decimals();
    }

    // ─── Arithmetic (immutable — always returns new instance) ────────────

    /**
     * Add an amount or another Money instance.
     *
     * @param float|int|self $value Amount or Money object (must have same currency).
     * @return self New Money instance with the sum.
     * @throws \InvalidArgumentException On currency mismatch or invalid type.
     */
    public function add($value): self {
        if ($value instanceof self) {
            $this->assertSameCurrency($value);
            return new self($this->_amount + $value->_amount, $this->_currency);
        }
        if (is_numeric($value)) {
            return new self($this->_amount + $value, $this->_currency);
        }
        throw new \InvalidArgumentException("Cannot add: expected numeric value or Money instance.");
    }

    /**
     * Subtract an amount or another Money instance.
     *
     * @param float|int|self $value Amount or Money object (must have same currency).
     * @return self New Money instance with the difference.
     * @throws \InvalidArgumentException On currency mismatch or invalid type.
     */
    public function subtract($value): self {
        if ($value instanceof self) {
            $this->assertSameCurrency($value);
            return new self($this->_amount - $value->_amount, $this->_currency);
        }
        if (is_numeric($value)) {
            return new self($this->_amount - $value, $this->_currency);
        }
        throw new \InvalidArgumentException("Cannot subtract: expected numeric value or Money instance.");
    }

    /**
     * Multiply the amount by a factor.
     *
     * @param float|int $factor Multiplication factor.
     * @return self New Money instance with the product.
     */
    public function multiply($factor): self {
        return new self($this->_amount * (float)$factor, $this->_currency);
    }

    /**
     * Divide the amount by a divisor.
     *
     * @param float|int $divisor Division factor (must not be zero).
     * @return self New Money instance with the quotient.
     * @throws \DivisionByZeroError If divisor is zero.
     */
    public function divide($divisor): self {
        if ((float)$divisor == 0.0) {
            throw new \DivisionByZeroError("Cannot divide money by zero.");
        }
        return new self($this->_amount / (float)$divisor, $this->_currency);
    }

    /**
     * Return the absolute value.
     *
     * @return self New Money instance with abs(amount).
     */
    public function abs(): self {
        return new self(abs($this->_amount), $this->_currency);
    }

    /**
     * Negate the amount.
     *
     * @return self New Money instance with negated amount.
     */
    public function negate(): self {
        return new self(-$this->_amount, $this->_currency);
    }

    // ─── Comparisons ────────────────────────────────────────────────────

    /**
     * Check if this Money equals another (same amount and currency).
     *
     * @param self $other Money instance to compare.
     * @return bool
     */
    public function equals(self $other): bool {
        return $this->_currency === $other->_currency
            && abs($this->_amount - $other->_amount) < 0.00001;
    }

    /**
     * Compare two Money instances.
     *
     * @param self $other Money instance to compare.
     * @return int -1, 0, or 1 (like spaceship operator).
     * @throws \InvalidArgumentException On currency mismatch.
     */
    public function compareTo(self $other): int {
        $this->assertSameCurrency($other);
        return $this->_amount <=> $other->_amount;
    }

    /**
     * @return bool True if the amount is zero.
     */
    public function isZero(): bool {
        return abs($this->_amount) < 0.00001;
    }

    /**
     * @return bool True if the amount is greater than zero.
     */
    public function isPositive(): bool {
        return $this->_amount > 0.00001;
    }

    /**
     * @return bool True if the amount is less than zero.
     */
    public function isNegative(): bool {
        return $this->_amount < -0.00001;
    }

    // ─── Formatting ─────────────────────────────────────────────────────

    /**
     * Format the money value for display.
     *
     * Applies locale-aware formatting rules:
     *   - German ("de"): 1.234,56€
     *   - English/default: 1,234.56€
     *   - USD/GBP: $1,234.56 (symbol before amount)
     *   - HUF/JPY: No decimal places
     *
     * @param string|null $lang Language code (e.g. 'de', 'en'). Falls back to
     *                          $_ENV['lang'] or 'en'.
     * @return string Formatted money string with symbol.
     */
    public function format(?string $lang = null): string {
        $lang = $lang ?? $_ENV["lang"] ?? "en";
        $lang2 = substr($lang, 0, 2);
        $cur = $this->currencyObject();
        $dec = $cur->decimals();
        $sym = $cur->symbol();
        $symbolBefore = $cur->isSymbolBefore();

        $european = in_array($lang2, ['de', 'fr', 'es', 'it', 'nl', 'pt', 'pl', 'hu', 'cs', 'ro', 'bg', 'hr']);

        if ($european) {
            $formatted = number_format($this->_amount, $dec, ',', '.');
        } else {
            $formatted = number_format($this->_amount, $dec, '.', ',');
        }

        if ($symbolBefore) {
            return $sym . $formatted;
        }
        return $formatted . $sym;
    }

    /**
     * Format without currency symbol (plain number).
     *
     * @param string|null $lang Language code for locale-aware separators.
     * @return string Formatted number string.
     */
    public function formatPlain(?string $lang = null): string {
        $lang = $lang ?? $_ENV["lang"] ?? "en";
        $lang2 = substr($lang, 0, 2);
        $dec = $this->currencyObject()->decimals();

        $european = in_array($lang2, ['de', 'fr', 'es', 'it', 'nl', 'pt', 'pl', 'hu']);

        if ($european) {
            return number_format($this->_amount, $dec, ',', '.');
        }
        return number_format($this->_amount, $dec, '.', ',');
    }

    // ─── Exchange Rate Configuration ───────────────────────────────────

    /**
     * Register a custom exchange rate provider.
     *
     * The callback receives two currency codes (from, to) and should return
     * the exchange rate as a float, or null if unknown.
     *
     * @param callable $provider Signature: function(string $from, string $to): ?float
     * @return void
     */
    public static function setExchangeRateProvider(callable $provider): void {
        self::$_exchangeRateProvider = $provider;
    }

    /**
     * Enable or disable live exchange rate lookups via HTTP API.
     *
     * When enabled, exchangeRate() and exchangeTo() will automatically fetch
     * current rates from the configured API (default: frankfurter.app, ECB data).
     * Rates are cached in-memory per base currency for the configured TTL.
     *
     * @param bool        $enabled Whether to enable live lookups (default: true).
     * @param string|null $apiUrl  Custom API base URL (must support GET /latest?base=XXX).
     * @param int|null    $ttl     Cache TTL in seconds (default: 3600 = 1 hour).
     * @param int|null    $timeout HTTP request timeout in seconds (default: 5).
     * @return void
     */
    public static function enableLiveExchange(
        bool    $enabled = true,
        ?string $apiUrl = null,
        ?int    $ttl = null,
        ?int    $timeout = null
    ): void {
        self::$_liveExchangeEnabled = $enabled;
        if ($apiUrl !== null)  self::$_exchangeApiUrl     = rtrim($apiUrl, '/');
        if ($ttl !== null)     self::$_exchangeCacheTtl   = $ttl;
        if ($timeout !== null) self::$_exchangeApiTimeout = $timeout;
    }

    /**
     * Check whether live exchange rate lookups are enabled.
     *
     * @return bool
     */
    public static function isLiveExchangeEnabled(): bool {
        return self::$_liveExchangeEnabled;
    }

    /**
     * Clear the in-memory exchange rate cache.
     *
     * Call this to force fresh rates on the next lookup.
     *
     * @return void
     */
    public static function clearExchangeCache(): void {
        self::$_exchangeCache = [];
    }

    /**
     * Get the current cache state for debugging.
     *
     * @return array<string, array{rates: array<string, float>, fetched_at: int, age_seconds: int}>
     */
    public static function getExchangeCacheInfo(): array {
        $info = [];
        foreach (self::$_exchangeCache as $base => $entry) {
            $info[$base] = [
                'rates'       => $entry['rates'],
                'fetched_at'  => $entry['fetched_at'],
                'age_seconds' => time() - $entry['fetched_at'],
            ];
        }
        return $info;
    }

    // ─── Exchange Rate Lookup ───────────────────────────────────────────

    /**
     * Get the exchange rate between two currencies.
     *
     * Lookup order:
     *   1. Custom provider (if registered via setExchangeRateProvider)
     *   2. Live API (if enabled via enableLiveExchange)
     *   3. Hardcoded fallback rates
     *
     * @param string $from Source currency code.
     * @param string $to   Target currency code.
     * @return float|null Exchange rate, or null if unknown.
     */
    public static function exchangeRate(string $from, string $to): ?float {
        $from = strtoupper($from);
        $to   = strtoupper($to);
        if ($from === $to) return 1.0;

        // 1. Try custom provider
        if (self::$_exchangeRateProvider !== null) {
            $rate = call_user_func(self::$_exchangeRateProvider, $from, $to);
            if ($rate !== null) return (float)$rate;
        }

        // 2. Try live API
        if (self::$_liveExchangeEnabled) {
            $rate = self::fetchLiveRate($from, $to);
            if ($rate !== null) return $rate;
        }

        // 3. Fallback hardcoded rates
        $rates = [
            'EUR-PLN' => 4.0,
            'PLN-EUR' => 0.25,
        ];
        return $rates["{$from}-{$to}"] ?? null;
    }

    /**
     * Fetch a live exchange rate from the configured API.
     *
     * Rates are cached in-memory per base currency. When a cache entry exists
     * and hasn't expired, the cached rate is returned without an HTTP call.
     * A single API call fetches ALL rates for a base currency, so subsequent
     * lookups from the same base are instant.
     *
     * @param string $from Source currency code.
     * @param string $to   Target currency code.
     * @return float|null Exchange rate, or null on API failure.
     */
    private static function fetchLiveRate(string $from, string $to): ?float {
        // Check cache
        if (isset(self::$_exchangeCache[$from])) {
            $entry = self::$_exchangeCache[$from];
            if ((time() - $entry['fetched_at']) < self::$_exchangeCacheTtl) {
                return $entry['rates'][$to] ?? null;
            }
            // Expired — remove stale entry
            unset(self::$_exchangeCache[$from]);
        }

        // Fetch from API
        $url = self::$_exchangeApiUrl . '/latest?base=' . urlencode($from);

        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => self::$_exchangeApiTimeout,
                'header'  => "Accept: application/json\r\n"
                           . "User-Agent: phlibs/Money (github.com/andreaskasper/phlibs)\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $json = @file_get_contents($url, false, $context);
        if ($json === false) {
            return null;
        }

        $data = @json_decode($json, true);
        if (!is_array($data) || !isset($data['rates']) || !is_array($data['rates'])) {
            return null;
        }

        // Store ALL rates for this base currency in cache
        self::$_exchangeCache[$from] = [
            'rates'      => $data['rates'],
            'fetched_at' => time(),
        ];

        return $data['rates'][$to] ?? null;
    }

    /**
     * Prefetch exchange rates for one or more base currencies.
     *
     * Useful for warming the cache before processing multiple conversions.
     * Silently ignores API failures.
     *
     * @param string|array $baseCurrencies One or more ISO 4217 base currency codes.
     * @return void
     */
    public static function prefetchRates($baseCurrencies): void {
        if (!self::$_liveExchangeEnabled) return;
        if (is_string($baseCurrencies)) $baseCurrencies = [$baseCurrencies];

        foreach ($baseCurrencies as $base) {
            $base = strtoupper(trim($base));
            // Skip if already cached and not expired
            if (isset(self::$_exchangeCache[$base])) {
                if ((time() - self::$_exchangeCache[$base]['fetched_at']) < self::$_exchangeCacheTtl) {
                    continue;
                }
            }
            // Fetch (will populate cache as side-effect)
            self::fetchLiveRate($base, 'USD'); // Target doesn't matter, all rates are cached
        }
    }

    // ─── Currency Conversion ───────────────────────────────────────────

    /**
     * Convert this Money to another currency.
     *
     * If no rate is provided, attempts to look up the rate via:
     *   1. Custom provider
     *   2. Live API (if enabled)
     *   3. Hardcoded fallback rates
     *
     * @param string     $targetCurrency ISO 4217 currency code.
     * @param float|null $rate           Exchange rate (optional, auto-lookup if null).
     * @return self New Money instance in the target currency.
     * @throws \RuntimeException If no exchange rate is available.
     */
    public function exchangeTo(string $targetCurrency, ?float $rate = null): self {
        $targetCurrency = strtoupper(trim($targetCurrency));
        if ($this->_currency === $targetCurrency) {
            return new self($this->_amount, $targetCurrency);
        }
        if ($rate === null) {
            $rate = self::exchangeRate($this->_currency, $targetCurrency);
        }
        if ($rate === null) {
            $hint = self::$_liveExchangeEnabled
                ? "Live API returned no rate."
                : "Enable live rates with Money::enableLiveExchange() or register a provider.";
            throw new \RuntimeException(
                "No exchange rate available for {$this->_currency} -> {$targetCurrency}. {$hint}"
            );
        }
        return new self($this->_amount * $rate, $targetCurrency);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * Assert that another Money instance has the same currency.
     *
     * @param self $other Money instance to check.
     * @throws \InvalidArgumentException If currencies don't match.
     */
    private function assertSameCurrency(self $other): void {
        if ($this->_currency !== $other->_currency) {
            throw new \InvalidArgumentException(
                "Currency mismatch: cannot operate on {$this->_currency} and {$other->_currency}."
            );
        }
    }

    /**
     * Return an array representation of this Money instance.
     *
     * @return array{amount: float, currency: string, formatted: string}
     */
    public function toArray(): array {
        return [
            'amount'    => $this->_amount,
            'currency'  => $this->_currency,
            'formatted' => $this->format(),
        ];
    }

    /**
     * Return a JSON-serializable representation.
     *
     * @return array{amount: float, currency: string}
     */
    public function jsonSerialize(): array {
        return [
            'amount'   => $this->_amount,
            'currency' => $this->_currency,
        ];
    }
}
