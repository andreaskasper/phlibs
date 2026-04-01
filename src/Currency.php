<?php
/**
 * Currency — Value object for ISO 4217 currency codes.
 *
 * Represents a currency with its symbol, display name, decimal behavior, and
 * symbol placement rules. Acts as the single source of truth for all currency
 * metadata in the phlibs library. Money delegates its symbol/name lookups here.
 *
 * Covers 115+ active ISO 4217 currencies with correct decimal places
 * (including 3-decimal currencies like KWD, BHD, OMR and 0-decimal like JPY, KRW).
 *
 * @author  Andreas Kasper <andreas.kasper@goo1.de>
 * @package phlibs
 * @version 2.1.0
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
     *
     * Covers all major world currencies. Uses Unicode escapes for non-ASCII symbols.
     */
    private const SYMBOLS = [
        // A
        'AED' => "\u{62F}.\u{625}",   // د.إ
        'AFN' => "\u{60B}",           // ؋
        'ALL' => 'L',
        'AMD' => "\u{058F}",          // ֏
        'ANG' => "\u{0192}",          // ƒ
        'AOA' => 'Kz',
        'ARS' => '$',
        'AUD' => 'A$',
        'AWG' => "\u{0192}",          // ƒ
        'AZN' => "\u{20BC}",          // ₼
        // B
        'BAM' => 'KM',
        'BBD' => 'Bds$',
        'BDT' => "\u{09F3}",          // ৳
        'BGN' => "\u{43B}\u{432}",    // лв
        'BHD' => 'BD',
        'BIF' => 'FBu',
        'BMD' => '$',
        'BND' => 'B$',
        'BOB' => 'Bs.',
        'BRL' => 'R$',
        'BSD' => '$',
        'BTN' => 'Nu.',
        'BWP' => 'P',
        'BYN' => 'Br',
        'BZD' => 'BZ$',
        // C
        'CAD' => 'CA$',
        'CDF' => 'FC',
        'CHF' => 'CHF',
        'CLP' => '$',
        'CNY' => "\u{00A5}",          // ¥
        'COP' => '$',
        'CRC' => "\u{20A1}",          // ₡
        'CUP' => '$',
        'CVE' => 'Esc',
        'CZK' => "K\u{10D}",          // Kč
        // D
        'DJF' => 'Fdj',
        'DKK' => 'kr',
        'DOP' => 'RD$',
        'DZD' => "\u{62F}.\u{62C}",   // د.ج
        // E
        'EGP' => "\u{00A3}",          // £
        'ERN' => 'Nfk',
        'ETB' => 'Br',
        'EUR' => "\u{20AC}",          // €
        // F
        'FJD' => 'FJ$',
        'FKP' => "\u{00A3}",          // £
        // G
        'GBP' => "\u{00A3}",          // £
        'GEL' => "\u{20BE}",          // ₾
        'GHS' => "GH\u{20B5}",        // GH₵
        'GIP' => "\u{00A3}",          // £
        'GMD' => 'D',
        'GNF' => 'FG',
        'GTQ' => 'Q',
        'GYD' => 'GY$',
        // H
        'HKD' => 'HK$',
        'HNL' => 'L',
        'HRK' => 'kn',
        'HTG' => 'G',
        'HUF' => 'Ft',
        // I
        'IDR' => 'Rp',
        'ILS' => "\u{20AA}",          // ₪
        'INR' => "\u{20B9}",          // ₹
        'IQD' => "\u{639}.\u{62F}",   // ع.د
        'IRR' => "\u{FDFC}",          // ﷼
        'ISK' => 'kr',
        // J
        'JMD' => 'J$',
        'JOD' => 'JD',
        'JPY' => "\u{00A5}",          // ¥
        // K
        'KES' => 'KSh',
        'KGS' => "\u{43B}\u{432}",    // лв
        'KHR' => "\u{17DB}",          // ៛
        'KMF' => 'CF',
        'KRW' => "\u{20A9}",          // ₩
        'KWD' => "\u{62F}.\u{643}",   // د.ك
        'KYD' => 'CI$',
        'KZT' => "\u{20B8}",          // ₸
        // L
        'LAK' => "\u{20AD}",          // ₭
        'LBP' => "\u{00A3}",          // £
        'LKR' => 'Rs',
        'LRD' => 'L$',
        'LSL' => 'L',
        'LYD' => 'LD',
        // M
        'MAD' => 'MAD',
        'MDL' => 'L',
        'MGA' => 'Ar',
        'MKD' => "\u{434}\u{435}\u{43D}", // ден
        'MMK' => 'K',
        'MNT' => "\u{20AE}",          // ₮
        'MOP' => 'MOP$',
        'MRU' => 'UM',
        'MUR' => "\u{20A8}",          // ₨
        'MVR' => 'Rf',
        'MWK' => 'MK',
        'MXN' => '$',
        'MYR' => 'RM',
        'MZN' => 'MT',
        // N
        'NAD' => 'N$',
        'NGN' => "\u{20A6}",          // ₦
        'NIO' => 'C$',
        'NOK' => 'kr',
        'NPR' => "\u{20A8}",          // ₨
        'NZD' => 'NZ$',
        // O
        'OMR' => "\u{FDFC}",          // ﷼
        // P
        'PAB' => 'B/.',
        'PEN' => 'S/',
        'PGK' => 'K',
        'PHP' => "\u{20B1}",          // ₱
        'PKR' => 'Rs',
        'PLN' => "z\u{142}",          // zł
        'PYG' => "\u{20B2}",          // ₲
        // Q
        'QAR' => 'QR',
        // R
        'RON' => 'lei',
        'RSD' => 'din.',
        'RUB' => "\u{20BD}",          // ₽
        'RWF' => 'RF',
        // S
        'SAR' => "\u{FDFC}",          // ﷼
        'SBD' => 'SI$',
        'SCR' => 'SRe',
        'SDG' => "\u{00A3}",          // £
        'SEK' => 'kr',
        'SGD' => 'S$',
        'SHP' => "\u{00A3}",          // £
        'SLE' => 'Le',
        'SOS' => 'Sh',
        'SRD' => '$',
        'SSP' => "\u{00A3}",          // £
        'STN' => 'Db',
        'SVC' => '$',
        'SYP' => "\u{00A3}",          // £
        'SZL' => 'E',
        // T
        'THB' => "\u{0E3F}",          // ฿
        'TJS' => 'SM',
        'TMT' => 'T',
        'TND' => 'DT',
        'TOP' => 'T$',
        'TRY' => "\u{20BA}",          // ₺
        'TTD' => 'TT$',
        'TWD' => 'NT$',
        'TZS' => 'TSh',
        // U
        'UAH' => "\u{20B4}",          // ₴
        'UGX' => 'USh',
        'USD' => '$',
        'UYU' => '$U',
        'UZS' => "so\u{02BB}m",       // soʻm
        // V
        'VES' => 'Bs.S',
        'VND' => "\u{20AB}",          // ₫
        'VUV' => 'VT',
        // W
        'WST' => 'WS$',
        // X
        'XAF' => 'FCFA',
        'XCD' => 'EC$',
        'XOF' => 'CFA',
        'XPF' => 'F',
        // Y
        'YER' => "\u{FDFC}",          // ﷼
        // Z
        'ZAR' => 'R',
        'ZMW' => 'ZK',
        'ZWL' => 'Z$',
    ];

    /**
     * @var array<string, string> Map of ISO 4217 code => human-readable name.
     */
    private const NAMES = [
        // A
        'AED' => 'UAE Dirham',
        'AFN' => 'Afghani',
        'ALL' => 'Lek',
        'AMD' => 'Armenian Dram',
        'ANG' => 'Netherlands Antillean Guilder',
        'AOA' => 'Kwanza',
        'ARS' => 'Argentine Peso',
        'AUD' => 'Australian Dollar',
        'AWG' => 'Aruban Florin',
        'AZN' => 'Azerbaijani Manat',
        // B
        'BAM' => 'Convertible Mark',
        'BBD' => 'Barbadian Dollar',
        'BDT' => 'Taka',
        'BGN' => 'Lev',
        'BHD' => 'Bahraini Dinar',
        'BIF' => 'Burundian Franc',
        'BMD' => 'Bermudian Dollar',
        'BND' => 'Brunei Dollar',
        'BOB' => 'Boliviano',
        'BRL' => 'Real',
        'BSD' => 'Bahamian Dollar',
        'BTN' => 'Ngultrum',
        'BWP' => 'Pula',
        'BYN' => 'Belarusian Ruble',
        'BZD' => 'Belize Dollar',
        // C
        'CAD' => 'Canadian Dollar',
        'CDF' => 'Congolese Franc',
        'CHF' => 'Swiss Franc',
        'CLP' => 'Chilean Peso',
        'CNY' => 'Yuan Renminbi',
        'COP' => 'Colombian Peso',
        'CRC' => 'Costa Rican Col\u{F3}n',
        'CUP' => 'Cuban Peso',
        'CVE' => 'Cape Verdean Escudo',
        'CZK' => 'Czech Koruna',
        // D
        'DJF' => 'Djiboutian Franc',
        'DKK' => 'Danish Krone',
        'DOP' => 'Dominican Peso',
        'DZD' => 'Algerian Dinar',
        // E
        'EGP' => 'Egyptian Pound',
        'ERN' => 'Nakfa',
        'ETB' => 'Ethiopian Birr',
        'EUR' => 'Euro',
        // F
        'FJD' => 'Fijian Dollar',
        'FKP' => 'Falkland Islands Pound',
        // G
        'GBP' => 'Pound Sterling',
        'GEL' => 'Georgian Lari',
        'GHS' => 'Ghanaian Cedi',
        'GIP' => 'Gibraltar Pound',
        'GMD' => 'Dalasi',
        'GNF' => 'Guinean Franc',
        'GTQ' => 'Quetzal',
        'GYD' => 'Guyanese Dollar',
        // H
        'HKD' => 'Hong Kong Dollar',
        'HNL' => 'Lempira',
        'HRK' => 'Kuna',
        'HTG' => 'Gourde',
        'HUF' => 'Forint',
        // I
        'IDR' => 'Rupiah',
        'ILS' => 'New Israeli Shekel',
        'INR' => 'Indian Rupee',
        'IQD' => 'Iraqi Dinar',
        'IRR' => 'Iranian Rial',
        'ISK' => 'Icelandic Kr\u{F3}na',
        // J
        'JMD' => 'Jamaican Dollar',
        'JOD' => 'Jordanian Dinar',
        'JPY' => 'Yen',
        // K
        'KES' => 'Kenyan Shilling',
        'KGS' => 'Som',
        'KHR' => 'Riel',
        'KMF' => 'Comorian Franc',
        'KRW' => 'Won',
        'KWD' => 'Kuwaiti Dinar',
        'KYD' => 'Cayman Islands Dollar',
        'KZT' => 'Tenge',
        // L
        'LAK' => 'Lao Kip',
        'LBP' => 'Lebanese Pound',
        'LKR' => 'Sri Lankan Rupee',
        'LRD' => 'Liberian Dollar',
        'LSL' => 'Loti',
        'LYD' => 'Libyan Dinar',
        // M
        'MAD' => 'Moroccan Dirham',
        'MDL' => 'Moldovan Leu',
        'MGA' => 'Malagasy Ariary',
        'MKD' => 'Denar',
        'MMK' => 'Kyat',
        'MNT' => 'Tugrik',
        'MOP' => 'Pataca',
        'MRU' => 'Ouguiya',
        'MUR' => 'Mauritian Rupee',
        'MVR' => 'Rufiyaa',
        'MWK' => 'Malawian Kwacha',
        'MXN' => 'Mexican Peso',
        'MYR' => 'Ringgit',
        'MZN' => 'Metical',
        // N
        'NAD' => 'Namibian Dollar',
        'NGN' => 'Naira',
        'NIO' => 'C\u{F3}rdoba',
        'NOK' => 'Norwegian Krone',
        'NPR' => 'Nepalese Rupee',
        'NZD' => 'New Zealand Dollar',
        // O
        'OMR' => 'Omani Rial',
        // P
        'PAB' => 'Balboa',
        'PEN' => 'Sol',
        'PGK' => 'Kina',
        'PHP' => 'Philippine Peso',
        'PKR' => 'Pakistani Rupee',
        'PLN' => 'Z\u{142}oty',
        'PYG' => 'Guaran\u{ED}',
        // Q
        'QAR' => 'Qatari Riyal',
        // R
        'RON' => 'Romanian Leu',
        'RSD' => 'Serbian Dinar',
        'RUB' => 'Russian Ruble',
        'RWF' => 'Rwandan Franc',
        // S
        'SAR' => 'Saudi Riyal',
        'SBD' => 'Solomon Islands Dollar',
        'SCR' => 'Seychellois Rupee',
        'SDG' => 'Sudanese Pound',
        'SEK' => 'Swedish Krona',
        'SGD' => 'Singapore Dollar',
        'SHP' => 'Saint Helena Pound',
        'SLE' => 'Leone',
        'SOS' => 'Somali Shilling',
        'SRD' => 'Surinamese Dollar',
        'SSP' => 'South Sudanese Pound',
        'STN' => 'Dobra',
        'SVC' => 'Salvadoran Col\u{F3}n',
        'SYP' => 'Syrian Pound',
        'SZL' => 'Lilangeni',
        // T
        'THB' => 'Baht',
        'TJS' => 'Somoni',
        'TMT' => 'Turkmenistan Manat',
        'TND' => 'Tunisian Dinar',
        'TOP' => 'Pa\u{2BB}anga',
        'TRY' => 'Turkish Lira',
        'TTD' => 'Trinidad and Tobago Dollar',
        'TWD' => 'New Taiwan Dollar',
        'TZS' => 'Tanzanian Shilling',
        // U
        'UAH' => 'Hryvnia',
        'UGX' => 'Ugandan Shilling',
        'USD' => 'US Dollar',
        'UYU' => 'Uruguayan Peso',
        'UZS' => 'Uzbekistani Som',
        // V
        'VES' => 'Bol\u{ED}var Soberano',
        'VND' => 'Dong',
        'VUV' => 'Vatu',
        // W
        'WST' => 'Samoan T\u{101}l\u{101}',
        // X
        'XAF' => 'Central African CFA Franc',
        'XCD' => 'East Caribbean Dollar',
        'XOF' => 'West African CFA Franc',
        'XPF' => 'CFP Franc',
        // Y
        'YER' => 'Yemeni Rial',
        // Z
        'ZAR' => 'South African Rand',
        'ZMW' => 'Zambian Kwacha',
        'ZWL' => 'Zimbabwean Dollar',
    ];

    /**
     * @var array<string> Currencies with 0 minor units (no decimal places).
     *
     * Per ISO 4217. Note: some sources list CZK/HUF as 2-decimal but in practice
     * these countries have eliminated minor-unit coins, so they are included here.
     */
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV',
        'XAF', 'XOF', 'XPF',
    ];

    /**
     * @var array<string> Currencies with 0 decimal places in practice
     * (officially 2, but minor units are not used in everyday commerce).
     */
    private const ZERO_DECIMAL_PRACTICE = [
        'CZK', 'HUF', 'IDR', 'IRR', 'LAK', 'MMK', 'MNT', 'TZS',
    ];

    /**
     * @var array<string> Currencies with 3 minor units (3 decimal places).
     *
     * Per ISO 4217. These are mostly Middle Eastern dinars.
     */
    private const THREE_DECIMAL = [
        'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND',
    ];

    /**
     * @var array<string> Currencies where the symbol is placed before the amount.
     */
    private const SYMBOL_BEFORE = [
        'AUD', 'BBD', 'BMD', 'BND', 'BRL', 'BSD', 'BZD', 'CAD',
        'CLP', 'COP', 'CRC', 'CUP', 'DOP', 'FJD', 'FKP', 'GBP',
        'GHS', 'GIP', 'GYD', 'HKD', 'JMD', 'KYD', 'LRD', 'MOP',
        'MXN', 'NAD', 'NGN', 'NIO', 'NZD', 'PHP', 'SBD', 'SGD',
        'SHP', 'SRD', 'SVC', 'TOP', 'TTD', 'TWD', 'USD', 'UYU',
        'WST', 'XCD', 'ZWL',
    ];

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
            $this->_id = strtoupper(trim($value));
        } else {
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
     * @return string Currency name (e.g. 'Euro', 'US Dollar').
     */
    public function name(): string {
        return self::NAMES[$this->_id] ?? $this->_id;
    }

    /**
     * Get the number of decimal places for this currency.
     *
     * Returns the correct value per ISO 4217:
     *   - 0 for JPY, KRW, VND, XAF, XOF, etc.
     *   - 3 for BHD, IQD, JOD, KWD, LYD, OMR, TND
     *   - 2 for everything else
     *
     * @param bool $practical If true, also returns 0 for currencies where minor
     *                        units exist on paper but are not used (CZK, HUF, IDR, etc.).
     * @return int Number of decimal places (0, 2, or 3).
     */
    public function decimals(bool $practical = false): int {
        if (in_array($this->_id, self::ZERO_DECIMAL)) return 0;
        if (in_array($this->_id, self::THREE_DECIMAL)) return 3;
        if ($practical && in_array($this->_id, self::ZERO_DECIMAL_PRACTICE)) return 0;
        return 2;
    }

    /**
     * Check whether this currency uses zero decimal places (ISO 4217).
     *
     * @return bool True for JPY, KRW, VND, XAF, XOF, etc.
     */
    public function isZeroDecimal(): bool {
        return in_array($this->_id, self::ZERO_DECIMAL);
    }

    /**
     * Check whether this currency uses three decimal places (ISO 4217).
     *
     * @return bool True for BHD, IQD, JOD, KWD, LYD, OMR, TND.
     */
    public function isThreeDecimal(): bool {
        return in_array($this->_id, self::THREE_DECIMAL);
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
     * Get the number of decimals for a currency code without creating an instance.
     *
     * @param string $code ISO 4217 currency code.
     * @return int Number of decimal places (0, 2, or 3).
     */
    public static function decimalsFor(string $code): int {
        return (new self($code))->decimals();
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
