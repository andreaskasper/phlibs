<?php

class Money {

    private $_amount = null;
    private $_currency = null;

    public function __construct($type, $value1, $value2) {
        switch ($type) {
            case "ac":
            default:
                $this->_amount = $value1;
                if (is_string($value2)) $this->_currency = new Currency("id", $value2);
                else $this->_currency = $value2;
                return $this;

        }    
        throw new \Exception("unbekannter Constructor");
    }

    public function __get($name) {
        switch ($name) {
            case "amount":
                return $this->_amount+0;
            case "currency":
                return $this->currency();
            case "symbol":
                return $this->symbol();
            case "name":
                switch ($this->_currency) {
                    case "CHF": return "Franc";
                    case "EUR": return "Euro";
                    case "HUF": return "Forint";
                    case "PLN": return "Złoty";
                    case "USD": return "Dollar";
                    default: return $this->_currency;
                }
            //case "id": return $this->_id;
        }
    }

    public function __string() { return $this->format(); }
    public function amount() : float { return $this->_amount+0; }
    public function currency() : Currency { return $this->_currency; }
    public function add($k) { 
        if (is_numeric($k)) $this->_amount += $k; 
        elseif (is_object($k) AND get_class($k) == "Money") $this->_amount += $k->amount();
        else throw new Exception("Wrong Format");
        
    }
    public function multiply(float $k) : Money { return new Money("ac", $this->_amount*$k, $this->_currency); }
    public function symbol() : string { return $this->currency()->symbol(); }

    

    /**
     * Formats the money value according to the specified language.
     *
     * @param string|null $lang The language code to format the money value. If null, the default language will be used.
     * @return string The formatted money value as a string.
     */
    public function format(?string $lang = null) : string {
        $cur = $this->currency()->id();
        $has_decimal = (is_numeric($this->_amount) && floor($this->_amount) != $this->_amount);
        $lang = $lang ?? $_ENV["lang"] ?? "en";

        switch ($cur) {
            case "PLN":
            case "HUF":
                return number_format($this->_amount, 0, ",", ".") . $this->symbol();
            case "USD":
                return $this->symbol() . number_format($this->_amount, $has_decimal ? 2 : 0, ".", ",");
            default:
                return substr($lang, 0, 2) == "de" 
                    ? number_format($this->_amount, 2, ".", ",") . $this->symbol() 
                    : number_format($this->_amount, 2, ",", ".") . $this->symbol();
        }
    }

    public static function exchange(string $from, string $to) {
        if ($from == $to) return 1;
        switch ($from."-".$to) {
            case "EUR-PLN": return 4;
            case "PLN-EUR": return 0.25;
        }
    }

    public function exchangerate(float $multiplier, Currency $currency) : Money {
        return new Money("ac", $this->amount()*$multiplier, $currency);
    }


}
