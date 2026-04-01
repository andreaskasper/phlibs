<?php

class Currency {

    private $_id = null;

    public function __construct($type, $value) {
        switch ($type) {
            case "id":
                $this->_id = $value;
                return $this;

        }    
        throw new \Exception("unbekannter Constructor");
    }

    public function __get($name) {
        switch ($name) {
            case "symbol": return $this->symbol();
            case "name": return $this->name();
            case "id": return $this->id();
        }
    }

    /**
     * Get the ID of the currency.
     *
     * @return string The ID of the currency.
     */
    public function id() : string { return $this->_id; }
    
    /**
     * Get the currency symbol.
     *
     * @return string The currency symbol.
     */
    public function symbol() : string {
        $currencySymbols = [
            "CHF" => "Fr",
            "EUR" => "€",
            "GBP" => "£",
            "HUF" => "Ft",
            "ILS" => "₪",
            "NOK" => "kr",
            "PLN" => "zł",
            "SEK" => "kr",
            "USD" => "$",
            "CAD" => '$CA',
            "JPY" => "¥",
            "AUD" => "A$",
            "INR" => "₹",
            "CNY" => "¥",
            "RUB" => "₽",
            "BRL" => "R$",
            "MXN" => "$",
            "ZAR" => "R",
            "KRW" => "₩",
            "SGD" => "S$",
            "NZD" => "NZ$",
            "HKD" => "HK$",
            "MYR" => "RM",
            "THB" => "฿",
            "TRY" => "₺",
            "PHP" => "₱",
            "DKK" => "kr",
            "CZK" => "Kč",
            "RON" => "lei",
            "HRK" => "kn",
            "BGN" => "лв",
            "ISK" => "kr",
            "IDR" => "Rp",
            "SAR" => "﷼",
            "AED" => "د.إ"
        ];

        return $currencySymbols[$this->_id] ?? $this->_id;
    }

    /**
     * Get the name of the currency.
     *
     * @return string The name of the currency.
     */
    public function name(): string {
        $currencyNames = [
            "CHF" => "Franc",
            "EUR" => "Euro",
            "GBP" => "Pound",
            "HUF" => "Forint",
            "NOK" => "Norwegian krone",
            "PLN" => "Złoty",
            "ILS" => "Shekel",
            "CAD" => "Canadian Dollar",
            "USD" => "Dollar",
            "JPY" => "Yen",
            "AUD" => "Australian Dollar",
            "INR" => "Rupee",
            "CNY" => "Yuan",
            "RUB" => "Ruble",
            "BRL" => "Real",
            "MXN" => "Peso",
            "ZAR" => "Rand",
            "KRW" => "Won",
            "SGD" => "Singapore Dollar",
            "NZD" => "New Zealand Dollar",
            "HKD" => "Hong Kong Dollar",
            "MYR" => "Ringgit",
            "THB" => "Baht",
            "TRY" => "Lira",
            "PHP" => "Peso",
            "DKK" => "Krone",
            "CZK" => "Koruna",
            "RON" => "Leu",
            "HRK" => "Kuna",
            "BGN" => "Lev",
            "ISK" => "Icelandic Króna",
            "IDR" => "Rupiah",
            "SAR" => "Riyal",
            "AED" => "Dirham",
            "ARS" => "Argentine Peso",
            "CLP" => "Chilean Peso",
            "COP" => "Colombian Peso",
            "EGP" => "Egyptian Pound",
            "MAD" => "Moroccan Dirham",
            "PEN" => "Peruvian Sol",
            "PKR" => "Pakistani Rupee",
            "QAR" => "Qatari Riyal",
            "TWD" => "Taiwan Dollar",
            "UAH" => "Ukrainian Hryvnia",
            "VND" => "Vietnamese Dong"
        ];

        return $currencyNames[$this->_id] ?? $this->_id;
    }

    /**
     * Formats the given amount according to the specified language.
     *
     * @param float|null $amount The amount to be formatted. Default is null.
     * @param string|null $lang The language code for formatting. Default is null.
     * @return string The formatted amount.
     */
    public function format(?float $amount = null, ?string $lang = null) {
        if (is_null($amount)) return $this->symbol();
        return (new Money("ac", $amount, $this))->format($lang);
    }

    public static function exchange(string $from, string $to) {
        if ($from == $to) return 1;
        switch ($from."-".$to) {
            case "EUR-PLN": return 4;
            case "PLN-EUR": return 0.25;
        }

    }

}
