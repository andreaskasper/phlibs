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
        $this->assertEquals('Dollar', (new Currency('USD'))->name());
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

    // ─── Decimals & Behavior ───────────────────────────────────────────

    public function testDecimals(): void
    {
        $this->assertEquals(2, (new Currency('EUR'))->decimals());
        $this->assertEquals(0, (new Currency('HUF'))->decimals());
        $this->assertEquals(0, (new Currency('JPY'))->decimals());
        $this->assertEquals(0, (new Currency('KRW'))->decimals());
    }

    public function testIsZeroDecimal(): void
    {
        $this->assertTrue((new Currency('JPY'))->isZeroDecimal());
        $this->assertTrue((new Currency('HUF'))->isZeroDecimal());
        $this->assertFalse((new Currency('EUR'))->isZeroDecimal());
        $this->assertFalse((new Currency('USD'))->isZeroDecimal());
    }

    public function testIsSymbolBefore(): void
    {
        $this->assertTrue((new Currency('USD'))->isSymbolBefore());
        $this->assertTrue((new Currency('GBP'))->isSymbolBefore());
        $this->assertTrue((new Currency('AUD'))->isSymbolBefore());
        $this->assertFalse((new Currency('EUR'))->isSymbolBefore());
        $this->assertFalse((new Currency('CHF'))->isSymbolBefore());
        $this->assertFalse((new Currency('PLN'))->isSymbolBefore());
    }

    public function testIsKnown(): void
    {
        $this->assertTrue((new Currency('EUR'))->isKnown());
        $this->assertTrue((new Currency('USD'))->isKnown());
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
        $this->assertEquals('Dollar', Currency::nameFor('USD'));
        $this->assertEquals('XYZ', Currency::nameFor('XYZ'));
    }

    public function testAllCodes(): void
    {
        $codes = Currency::allCodes();
        $this->assertContains('EUR', $codes);
        $this->assertContains('USD', $codes);
        $this->assertContains('JPY', $codes);
        $this->assertGreaterThan(40, count($codes));
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
}
