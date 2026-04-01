<?php
/**
 * Money — Immutable value object for monetary amounts.
 *
 * Represents a monetary amount with a currency code (ISO 4217). All arithmetic
 * operations return new Money instances (immutable pattern). Supports locale-aware
 * formatting, currency exchange via pluggable rate providers, and common comparisons.
 *
 * Note: Internally stores amounts as float. For applications requiring exact decimal
 * arithmetic (e.g. banking), consider using bcmath or an integer-based (cents) approach.
 *
 * @author  Andreas Kasper <andreas.kasper@goo1.de>
 * @package phlibs
 * @version 2.0.0
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

    /**
     * @var array<string, string> Map of currency code => display name.
     */
    private const CURRENCY_NAMES = [
        'AUD' => 'Australian Dollar',
        'CAD' => 'Canadian Dollar',
        'CHF' => 'Franc',
        'CNY' => 'Yuan',
        'CZK' => 'Koruna',
        'DKK' => 'Krone',
        'EUR' => 'Euro',
        'GBP' => 'Pound',
        'HUF' => 'Forint',
        'JPY' => 'Yen',
        'NOK' => 'Krone',
        'PLN' => 'Złoty',
        'RON' => 'Leu',
        'SEK' => 'Krona',
        'TRY' => 'Lira',
        'USD' => 'Dollar',
    ];

    /**
     * @var array<string, string> Map of currency code => symbol.
     */
    private const CURRENCY_SYMBOLS = [
        'AUD' => 'A$',
        'CAD' => 'C$',
        'CHF' => 'CHF',
        'CNY' => '¥',
        'CZK' => 'Kč',
        'DKK' => 'kr',
        'EUR' => '€',
        'GBP' => '£',
        'HUF' => 'Ft',
        'JPY' => '¥',
        'NOK' => 'kr',
        'PLN' => 'zł',
        'RON' => 'lei',
        'SEK' => 'kr',
        'TRY' => '₺',
        'USD' => '$',
    ];

    /**
     * @var array<string> Currencies that typically display without decimal places.
     */
    private const ZERO_DECIMAL_CURRENCIES = ['HUF', 'JPY', 'CZK'];

    /**
     * @var array<string> Currencies where the symbol is placed before the amount.
     */
    private const SYMBOL_BEFORE = ['USD', 'GBP', 'AUD', 'CAD'];

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
     * Get the currency symbol.
     *
     * @return string Symbol (e.g. '€', '$') or the currency code if unknown.
     */
    public function symbol(): string {
        return self::CURRENCY_SYMBOLS[$this->_currency] ?? $this->_currency;
    }

    /**
     * Get the currency display name.
     *
     * @return string Human-readable name (e.g. 'Euro', 'Dollar') or the code if unknown.
     */
    public function name(): string {
        return self::CURRENCY_NAMES[$this->_currency] ?? $this->_currency;
    }

    /**
     * Get the number of decimal places for this currency.
     *
     * @return int 0 for zero-decimal currencies (HUF, JPY, …), 2 for all others.
     */
    public function decimals(): int {
        return in_array($this->_currency, self::ZERO_DECIMAL_CURRENCIES) ? 0 : 2;
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
        $dec = $this->decimals();
        $sym = $this->symbol();
        $symbolBefore = in_array($this->_currency, self::SYMBOL_BEFORE);

        // Determine separators based on locale
        if ($lang2 === 'de' || $lang2 === 'fr' || $lang2 === 'es' || $lang2 === 'it' || $lang2 === 'nl' || $lang2 === 'pt' || $lang2 === 'pl' || $lang2 === 'hu') {
            // European format: 1.234,56
            $formatted = number_format($this->_amount, $dec, ',', '.');
        } else {
            // English format: 1,234.56
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
        $dec = $this->decimals();

        if ($lang2 === 'de' || $lang2 === 'fr' || $lang2 === 'es' || $lang2 === 'it') {
            return number_format($this->_amount, $dec, ',', '.');
        }
        return number_format($this->_amount, $dec, '.', ',');
    }

    // ─── Currency Exchange ──────────────────────────────────────────────

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
     * Get the exchange rate between two currencies.
     *
     * Uses the registered provider if available, otherwise falls back to
     * a built-in table of common rates.
     *
     * @param string $from Source currency code.
     * @param string $to   Target currency code.
     * @return float|null Exchange rate, or null if unknown.
     */
    public static function exchangeRate(string $from, string $to): ?float {
        $from = strtoupper($from);
        $to   = strtoupper($to);
        if ($from === $to) return 1.0;

        // Try custom provider first
        if (self::$_exchangeRateProvider !== null) {
            $rate = call_user_func(self::$_exchangeRateProvider, $from, $to);
            if ($rate !== null) return (float)$rate;
        }

        // Fallback: hardcoded rates (override with setExchangeRateProvider)
        $rates = [
            'EUR-PLN' => 4.0,
            'PLN-EUR' => 0.25,
        ];
        return $rates["{$from}-{$to}"] ?? null;
    }

    /**
     * Convert this Money to another currency using an exchange rate.
     *
     * If no rate is provided, attempts to look up the rate via the registered
     * provider or the built-in rate table.
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
            throw new \RuntimeException(
                "No exchange rate available for {$this->_currency} -> {$targetCurrency}. "
                . "Register a provider via Money::setExchangeRateProvider()."
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
