<?php
/**
 * Unit tests for the phlibs\Currency class.
 *
 * @license FreeFoodLicense
 */

namespace phlibs\Test;

use phlibs\Currency;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    // ─── Construction ───────────────────────────────────────────────────

    public function testConstructorModern(): void
    {
        $c = new Currency('EUR');
        $this->assertEquals('EUR', $c->id());
    }

    public function testConstructorLegacy(): void
    {
        $c = new Currency('id', 'USD');
        $this->assertEquals('USD', $c->id());
    }

    public function testConstructorUppercases(): void
    {
        $c = new Currency('eur');
        $this->assertEquals('EUR', $c->id());
    }

    public function testConstructorTrims(): void
    {
        $c = new Currency(' GBP ');
        $this->assertEquals('GBP', $c->id());
    }

    public function testStaticFactory(): void
    {
        $c = Currency::get('CHF');
        $this->assertEquals('CHF', $c->id());
    }

    public function testEmptyCodeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Currency('');
    }

    // ─── Properties ─────────────────────────────────────────────────────

    public function testSymbol(): void
    {
        $this->assertEquals("\u{20AC}", (new Currency('EUR'))->symbol());
        $this->assertEquals('$', (new Currency('USD'))->symbol());
        $this->assertEquals("\u{00A3}", (new Currency('GBP'))->symbol());
        $this->assertEquals("z\u{142}", (new Currency('PLN'))->symbol());
        $this->assertEquals('Ft', (new Currency('HUF'))->symbol());
    }

    public function testName(): void
    {
        $this->assertEquals('Euro', (new Currency('EUR'))->name());
        $this->assertEquals('US Dollar', (new Currency('USD'))->name());
        $this->assertEquals('Forint', (new Currency('HUF'))->name());
        $this->assertEquals("Z\u{142}oty", (new Currency('PLN'))->name());
    }

    public function testUnknownCurrencyFallback(): void
    {
        $c = new Currency('XYZ');
        $this->assertEquals('XYZ', $c->symbol());
        $this->assertEquals('XYZ', $c->name());
    }

    public function testMagicGet(): void
    {
        $c = new Currency('EUR');
        $this->assertEquals('EUR', $c->id);
        $this->assertEquals("\u{20AC}", $c->symbol);
        $this->assertEquals('Euro', $c->name);
        $this->assertEquals(2, $c->decimals);
    }

    public function testToString(): void
    {
        $c = new Currency('EUR');
        $this->assertEquals('EUR', (string)$c);
    }

    // ─── Decimals ───────────────────────────────────────────────────────

    public function testDecimalsStandard(): void
    {
        $this->assertEquals(2, (new Currency('EUR'))->decimals());
        $this->assertEquals(2, (new Currency('USD'))->decimals());
        $this->assertEquals(2, (new Currency('GBP'))->decimals());
    }

    public function testDecimalsZero(): void
    {
        $this->assertEquals(0, (new Currency('JPY'))->decimals());
        $this->assertEquals(0, (new Currency('KRW'))->decimals());
        $this->assertEquals(0, (new Currency('VND'))->decimals());
        $this->assertEquals(0, (new Currency('XAF'))->decimals());
        $this->assertEquals(0, (new Currency('XOF'))->decimals());
        $this->assertEquals(0, (new Currency('BIF'))->decimals());
        $this->assertEquals(0, (new Currency('CLP'))->decimals());
        $this->assertEquals(0, (new Currency('PYG'))->decimals());
        $this->assertEquals(0, (new Currency('RWF'))->decimals());
        $this->assertEquals(0, (new Currency('VUV'))->decimals());
    }

    public function testDecimalsThree(): void
    {
        $this->assertEquals(3, (new Currency('BHD'))->decimals());
        $this->assertEquals(3, (new Currency('KWD'))->decimals());
        $this->assertEquals(3, (new Currency('OMR'))->decimals());
        $this->assertEquals(3, (new Currency('JOD'))->decimals());
        $this->assertEquals(3, (new Currency('IQD'))->decimals());
        $this->assertEquals(3, (new Currency('LYD'))->decimals());
        $this->assertEquals(3, (new Currency('TND'))->decimals());
    }

    public function testDecimalsPractical(): void
    {
        // HUF officially has 2 decimals but in practice uses 0
        $this->assertEquals(2, (new Currency('HUF'))->decimals(false));
        $this->assertEquals(0, (new Currency('HUF'))->decimals(true));
        // CZK same
        $this->assertEquals(2, (new Currency('CZK'))->decimals(false));
        $this->assertEquals(0, (new Currency('CZK'))->decimals(true));
        // EUR should not be affected by practical mode
        $this->assertEquals(2, (new Currency('EUR'))->decimals(true));
    }

    public function testIsZeroDecimal(): void
    {
        $this->assertTrue((new Currency('JPY'))->isZeroDecimal());
        $this->assertFalse((new Currency('EUR'))->isZeroDecimal());
        $this->assertFalse((new Currency('KWD'))->isZeroDecimal());
    }

    public function testIsThreeDecimal(): void
    {
        $this->assertTrue((new Currency('KWD'))->isThreeDecimal());
        $this->assertTrue((new Currency('BHD'))->isThreeDecimal());
        $this->assertFalse((new Currency('EUR'))->isThreeDecimal());
        $this->assertFalse((new Currency('JPY'))->isThreeDecimal());
    }

    // ─── Symbol Placement ───────────────────────────────────────────────

    public function testIsSymbolBefore(): void
    {
        $this->assertTrue((new Currency('USD'))->isSymbolBefore());
        $this->assertTrue((new Currency('GBP'))->isSymbolBefore());
        $this->assertTrue((new Currency('AUD'))->isSymbolBefore());
        $this->assertTrue((new Currency('BRL'))->isSymbolBefore());
        $this->assertFalse((new Currency('EUR'))->isSymbolBefore());
        $this->assertFalse((new Currency('CHF'))->isSymbolBefore());
        $this->assertFalse((new Currency('PLN'))->isSymbolBefore());
    }

    public function testIsKnown(): void
    {
        $this->assertTrue((new Currency('EUR'))->isKnown());
        $this->assertTrue((new Currency('KWD'))->isKnown());
        $this->assertTrue((new Currency('ZMW'))->isKnown());
        $this->assertFalse((new Currency('XYZ'))->isKnown());
    }

    // ─── Comparison ─────────────────────────────────────────────────────

    public function testEquals(): void
    {
        $this->assertTrue((new Currency('EUR'))->equals(new Currency('EUR')));
        $this->assertFalse((new Currency('EUR'))->equals(new Currency('USD')));
    }

    // ─── Static Helpers ─────────────────────────────────────────────────

    public function testSymbolFor(): void
    {
        $this->assertEquals("\u{20AC}", Currency::symbolFor('EUR'));
        $this->assertEquals('$', Currency::symbolFor('USD'));
        $this->assertEquals('XYZ', Currency::symbolFor('XYZ'));
    }

    public function testNameFor(): void
    {
        $this->assertEquals('Euro', Currency::nameFor('EUR'));
        $this->assertEquals('US Dollar', Currency::nameFor('USD'));
        $this->assertEquals('XYZ', Currency::nameFor('XYZ'));
    }

    public function testDecimalsFor(): void
    {
        $this->assertEquals(2, Currency::decimalsFor('EUR'));
        $this->assertEquals(0, Currency::decimalsFor('JPY'));
        $this->assertEquals(3, Currency::decimalsFor('KWD'));
    }

    public function testAllCodes(): void
    {
        $codes = Currency::allCodes();
        $this->assertContains('EUR', $codes);
        $this->assertContains('USD', $codes);
        $this->assertContains('JPY', $codes);
        $this->assertContains('KWD', $codes);
        $this->assertContains('ZMW', $codes);
        $this->assertGreaterThan(110, count($codes));
        // Should be sorted
        $sorted = $codes;
        sort($sorted);
        $this->assertEquals($sorted, $codes);
    }

    // ─── Formatting ─────────────────────────────────────────────────────

    public function testFormatWithAmount(): void
    {
        $eur = new Currency('EUR');
        $formatted = $eur->format(19.99, 'de');
        $this->assertStringContainsString('19,99', $formatted);
        $this->assertStringContainsString("\u{20AC}", $formatted);
    }

    public function testFormatWithoutAmount(): void
    {
        $eur = new Currency('EUR');
        $this->assertEquals("\u{20AC}", $eur->format());
    }

    // ─── Integration with Money ─────────────────────────────────────────

    public function testMoneyCurrencyObject(): void
    {
        $m = new \phlibs\Money(100, 'EUR');
        $c = $m->currencyObject();
        $this->assertInstanceOf(Currency::class, $c);
        $this->assertEquals('EUR', $c->id());
    }

    public function testMoneyDelegatesToCurrency(): void
    {
        $m = new \phlibs\Money(100, 'PLN');
        $c = new Currency('PLN');
        $this->assertEquals($c->symbol(), $m->symbol());
        $this->assertEquals($c->name(), $m->name());
        $this->assertEquals($c->decimals(), $m->decimals());
    }

    public function testThreeDecimalCurrencyFormatting(): void
    {
        $m = new \phlibs\Money(1234.567, 'KWD');
        $formatted = $m->format('en');
        $this->assertStringContainsString('1,234.567', $formatted);
    }
}
