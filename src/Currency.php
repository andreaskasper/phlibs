<?php
/**
 * Currency — Value object for ISO 4217 currency codes.
 *
 * Represents a currency with its symbol, display name, decimal behavior, and
 * symbol placement rules. Acts as the single source of truth for all currency
 * metadata in the phlibs library. Money delegates its symbol/name lookups here.
 *
 * @author  Andreas Kasper <andreas.kasper@goo1.de>
 * @package phlibs
 * @version 2.0.0
 * @license FreeFoodLicense
 */

namespace phlibs;

class Currency {

    /**
     * @var string ISO 4217 currency code (uppercase, e.g. 'EUR', 'USD').
     */
    private string $_id;

    /**
     * @var array<string, string> Map of ISO 4217 code => currency symbol.
     */
    private const SYMBOLS = [
        'AED' => "\u{62F}.\u{625}",
        'ARS' => '$',
        'AUD' => 'A$',
        'BGN' => "\u{43B}\u{432}",
        'BRL' => 'R$',
        'CAD' => 'CA$',
        'CHF' => 'CHF',
        'CLP' => '$',
        'CNY' => "\u{00A5}",
        'COP' => '$',
        'CZK' => "K\u{10D}",
        'DKK' => 'kr',
        'EGP' => "\u{00A3}",
        'EUR' => "\u{20AC}",
        'GBP' => "\u{00A3}",
        'HKD' => 'HK$',
        'HRK' => 'kn',
        'HUF' => 'Ft',
        'IDR' => 'Rp',
        'ILS' => "\u{20AA}",
        'INR' => "\u{20B9}",
        'ISK' => 'kr',
        'JPY' => "\u{00A5}",
        'KRW' => "\u{20A9}",
        'MAD' => 'MAD',
        'MXN' => '$',
        'MYR' => 'RM',
        'NOK' => 'kr',
        'NZD' => 'NZ$',
        'PEN' => 'S/',
        'PHP' => "\u{20B1}",
        'PKR' => 'Rs',
        'PLN' => "z\u{142}",
        'QAR' => 'QR',
        'RON' => 'lei',
        'RUB' => "\u{20BD}",
        'SAR' => "\u{FDFC}",
        'SEK' => 'kr',
        'SGD' => 'S$',
        'THB' => "\u{0E3F}",
        'TRY' => "\u{20BA}",
        'TWD' => 'NT$',
        'UAH' => "\u{20B4}",
        'USD' => '$',
        'VND' => "\u{20AB}",
        'ZAR' => 'R',
    ];

    /**
     * @var array<string, string> Map of ISO 4217 code => human-readable name.
     */
    private const NAMES = [
        'AED' => 'Dirham',
        'ARS' => 'Argentine Peso',
        'AUD' => 'Australian Dollar',
        'BGN' => 'Lev',
        'BRL' => 'Real',
        'CAD' => 'Canadian Dollar',
        'CHF' => 'Franc',
        'CLP' => 'Chilean Peso',
        'CNY' => 'Yuan',
        'COP' => 'Colombian Peso',
        'CZK' => 'Koruna',
        'DKK' => 'Krone',
        'EGP' => 'Egyptian Pound',
        'EUR' => 'Euro',
        'GBP' => 'Pound',
        'HKD' => 'Hong Kong Dollar',
        'HRK' => 'Kuna',
        'HUF' => 'Forint',
        'IDR' => 'Rupiah',
        'ILS' => 'Shekel',
        'INR' => 'Rupee',
        'ISK' => "Icelandic Kr\u{F3}na",
        'JPY' => 'Yen',
        'KRW' => 'Won',
        'MAD' => 'Moroccan Dirham',
        'MXN' => 'Peso',
        'MYR' => 'Ringgit',
        'NOK' => 'Norwegian Krone',
        'NZD' => 'New Zealand Dollar',
        'PEN' => 'Peruvian Sol',
        'PHP' => 'Peso',
        'PKR' => 'Pakistani Rupee',
        'PLN' => "Z\u{142}oty",
        'QAR' => 'Qatari Riyal',
        'RON' => 'Leu',
        'RUB' => 'Ruble',
        'SAR' => 'Riyal',
        'SEK' => 'Krona',
        'SGD' => 'Singapore Dollar',
        'THB' => 'Baht',
        'TRY' => 'Lira',
        'TWD' => 'Taiwan Dollar',
        'UAH' => 'Ukrainian Hryvnia',
        'USD' => 'Dollar',
        'VND' => 'Vietnamese Dong',
        'ZAR' => 'Rand',
    ];

    /**
     * @var array<string> Currencies that have no minor units (no decimal places).
     */
    private const ZERO_DECIMAL = ['CLP', 'CZK', 'HUF', 'IDR', 'ISK', 'JPY', 'KRW', 'VND'];

    /**
     * @var array<string> Currencies where the symbol is placed before the amount.
     */
    private const SYMBOL_BEFORE = ['AUD', 'BRL', 'CAD', 'GBP', 'HKD', 'MXN', 'NZD', 'SGD', 'USD', 'TWD'];

    // ─── Constructors ───────────────────────────────────────────────────

    /**
     * Create a new Currency instance.
     *
     * Accepts either a plain ISO 4217 code or the legacy format ("id", code).
     *
     * @param string      $idOrType ISO 4217 currency code, or "id" for legacy construction.
     * @param string|null $value    Currency code when using legacy format.
     * @throws \InvalidArgumentException If the currency code is empty.
     */
    public function __construct(string $idOrType, ?string $value = null) {
        if ($idOrType === 'id' && $value !== null) {
            // Legacy support: new Currency("id", "EUR")
            $this->_id = strtoupper(trim($value));
        } else {
            // Modern: new Currency("EUR")
            $this->_id = strtoupper(trim($idOrType));
        }
        if (empty($this->_id)) {
            throw new \InvalidArgumentException("Currency code must not be empty.");
        }
    }

    /**
     * Static factory for convenience.
     *
     * @param string $code ISO 4217 currency code.
     * @return self
     */
    public static function get(string $code): self {
        return new self($code);
    }

    // ─── Magic Methods ──────────────────────────────────────────────────

    /**
     * Magic getter for common properties.
     *
     * Supported: id, symbol, name, decimals
     *
     * @param  string $name Property name.
     * @return mixed
     */
    public function __get(string $name) {
        switch ($name) {
            case 'id':       return $this->_id;
            case 'symbol':   return $this->symbol();
            case 'name':     return $this->name();
            case 'decimals': return $this->decimals();
        }
        trigger_error("Unknown property: Currency::" . $name, E_USER_WARNING);
        return null;
    }

    /**
     * String representation returns the ISO 4217 code.
     *
     * @return string
     */
    public function __toString(): string {
        return $this->_id;
    }

    // ─── Getters ────────────────────────────────────────────────────────

    /**
     * Get the ISO 4217 currency code.
     *
     * @return string Uppercase currency code (e.g. 'EUR').
     */
    public function id(): string {
        return $this->_id;
    }

    /**
     * Get the currency symbol.
     *
     * Returns the code itself if no symbol is registered.
     *
     * @return string Currency symbol (e.g. '€', '$', '£').
     */
    public function symbol(): string {
        return self::SYMBOLS[$this->_id] ?? $this->_id;
    }

    /**
     * Get the human-readable currency name.
     *
     * Returns the code itself if no name is registered.
     *
     * @return string Currency name (e.g. 'Euro', 'Dollar').
     */
    public function name(): string {
        return self::NAMES[$this->_id] ?? $this->_id;
    }

    /**
     * Get the number of decimal places for this currency.
     *
     * @return int 0 for zero-decimal currencies, 2 for all others.
     */
    public function decimals(): int {
        return in_array($this->_id, self::ZERO_DECIMAL) ? 0 : 2;
    }

    /**
     * Check whether this currency uses zero decimal places.
     *
     * @return bool True for currencies like HUF, JPY, KRW.
     */
    public function isZeroDecimal(): bool {
        return in_array($this->_id, self::ZERO_DECIMAL);
    }

    /**
     * Check whether the symbol is placed before the amount.
     *
     * @return bool True for USD, GBP, AUD, CAD, etc.
     */
    public function isSymbolBefore(): bool {
        return in_array($this->_id, self::SYMBOL_BEFORE);
    }

    /**
     * Check whether this currency code is known (has a registered symbol or name).
     *
     * @return bool True if the currency is in the built-in list.
     */
    public function isKnown(): bool {
        return isset(self::SYMBOLS[$this->_id]) || isset(self::NAMES[$this->_id]);
    }

    // ─── Comparison ─────────────────────────────────────────────────────

    /**
     * Check whether two Currency instances represent the same currency.
     *
     * @param self $other Currency to compare with.
     * @return bool
     */
    public function equals(self $other): bool {
        return $this->_id === $other->_id;
    }

    // ─── Formatting ─────────────────────────────────────────────────────

    /**
     * Format an amount in this currency.
     *
     * Shorthand for creating a Money instance and calling format().
     * If no amount is given, returns just the symbol.
     *
     * @param float|null  $amount Amount to format (null returns symbol only).
     * @param string|null $lang   Language code for locale-aware formatting.
     * @return string Formatted money string or currency symbol.
     */
    public function format(?float $amount = null, ?string $lang = null): string {
        if (is_null($amount)) {
            return $this->symbol();
        }
        return (new Money($amount, $this->_id))->format($lang);
    }

    // ─── Static Helpers ─────────────────────────────────────────────────

    /**
     * Get the symbol for a currency code without creating an instance.
     *
     * @param string $code ISO 4217 currency code.
     * @return string Symbol or the code itself.
     */
    public static function symbolFor(string $code): string {
        $code = strtoupper(trim($code));
        return self::SYMBOLS[$code] ?? $code;
    }

    /**
     * Get the name for a currency code without creating an instance.
     *
     * @param string $code ISO 4217 currency code.
     * @return string Name or the code itself.
     */
    public static function nameFor(string $code): string {
        $code = strtoupper(trim($code));
        return self::NAMES[$code] ?? $code;
    }

    /**
     * Get all known currency codes.
     *
     * @return array<string> Sorted list of ISO 4217 currency codes.
     */
    public static function allCodes(): array {
        $codes = array_unique(array_merge(array_keys(self::SYMBOLS), array_keys(self::NAMES)));
        sort($codes);
        return $codes;
    }
}
