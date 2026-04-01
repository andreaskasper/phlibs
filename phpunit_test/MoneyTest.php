<?php
/**
 * Unit tests for the phlibs\Money class.
 *
 * @license FreeFoodLicense
 */

namespace phlibs\Test;

use phlibs\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    // ─── Construction ───────────────────────────────────────────────────

    public function testConstructor(): void
    {
        $m = new Money(19.99, 'EUR');
        $this->assertEquals(19.99, $m->amount());
        $this->assertEquals('EUR', $m->currency());
    }

    public function testStaticFactory(): void
    {
        $m = Money::EUR(42.50);
        $this->assertEquals(42.50, $m->amount());
        $this->assertEquals('EUR', $m->currency());
    }

    public function testStaticFactoryUppercase(): void
    {
        $m = Money::usd(100);
        $this->assertEquals('USD', $m->currency());
    }

    public function testStaticFactoryNoAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::EUR();
    }

    // ─── Properties ─────────────────────────────────────────────────────

    public function testSymbol(): void
    {
        $this->assertEquals('€', Money::EUR(1)->symbol());
        $this->assertEquals('$', Money::USD(1)->symbol());
        $this->assertEquals('£', Money::GBP(1)->symbol());
        $this->assertEquals('zł', Money::PLN(1)->symbol());
    }

    public function testName(): void
    {
        $this->assertEquals('Euro', Money::EUR(1)->name());
        $this->assertEquals('Dollar', Money::USD(1)->name());
        $this->assertEquals('Forint', Money::HUF(1)->name());
    }

    public function testUnknownCurrencyFallback(): void
    {
        $m = Money::XYZ(100);
        $this->assertEquals('XYZ', $m->symbol());
        $this->assertEquals('XYZ', $m->name());
    }

    public function testMagicGetAmount(): void
    {
        $m = Money::EUR(19.99);
        $this->assertEquals(19.99, $m->amount);
        $this->assertEquals('EUR', $m->currency);
        $this->assertEquals('€', $m->symbol);
        $this->assertEquals('Euro', $m->name);
    }

    public function testDecimals(): void
    {
        $this->assertEquals(2, Money::EUR(1)->decimals());
        $this->assertEquals(0, Money::HUF(1)->decimals());
        $this->assertEquals(0, Money::JPY(1)->decimals());
    }

    // ─── Arithmetic ─────────────────────────────────────────────────────

    public function testAddNumeric(): void
    {
        $m = Money::EUR(10)->add(5);
        $this->assertEquals(15.0, $m->amount());
        $this->assertEquals('EUR', $m->currency());
    }

    public function testAddMoney(): void
    {
        $m = Money::EUR(10)->add(Money::EUR(5));
        $this->assertEquals(15.0, $m->amount());
    }

    public function testAddCurrencyMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::EUR(10)->add(Money::USD(5));
    }

    public function testAddInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::EUR(10)->add('invalid');
    }

    public function testSubtract(): void
    {
        $m = Money::EUR(10)->subtract(3);
        $this->assertEquals(7.0, $m->amount());
    }

    public function testSubtractMoney(): void
    {
        $m = Money::EUR(10)->subtract(Money::EUR(3));
        $this->assertEquals(7.0, $m->amount());
    }

    public function testMultiply(): void
    {
        $m = Money::EUR(10)->multiply(2.5);
        $this->assertEquals(25.0, $m->amount());
    }

    public function testDivide(): void
    {
        $m = Money::EUR(10)->divide(4);
        $this->assertEquals(2.5, $m->amount());
    }

    public function testDivideByZero(): void
    {
        $this->expectException(\DivisionByZeroError::class);
        Money::EUR(10)->divide(0);
    }

    public function testAbs(): void
    {
        $m = (new Money(-50, 'EUR'))->abs();
        $this->assertEquals(50.0, $m->amount());
    }

    public function testNegate(): void
    {
        $m = Money::EUR(50)->negate();
        $this->assertEquals(-50.0, $m->amount());
    }

    public function testImmutability(): void
    {
        $a = Money::EUR(10);
        $b = $a->add(5);
        $this->assertEquals(10.0, $a->amount()); // Original unchanged
        $this->assertEquals(15.0, $b->amount());
    }

    // ─── Comparisons ────────────────────────────────────────────────────

    public function testIsZero(): void
    {
        $this->assertTrue(Money::EUR(0)->isZero());
        $this->assertFalse(Money::EUR(1)->isZero());
    }

    public function testIsPositive(): void
    {
        $this->assertTrue(Money::EUR(1)->isPositive());
        $this->assertFalse(Money::EUR(0)->isPositive());
        $this->assertFalse(Money::EUR(-1)->isPositive());
    }

    public function testIsNegative(): void
    {
        $this->assertTrue(Money::EUR(-1)->isNegative());
        $this->assertFalse(Money::EUR(0)->isNegative());
        $this->assertFalse(Money::EUR(1)->isNegative());
    }

    public function testEquals(): void
    {
        $this->assertTrue(Money::EUR(10)->equals(Money::EUR(10)));
        $this->assertFalse(Money::EUR(10)->equals(Money::EUR(11)));
        $this->assertFalse(Money::EUR(10)->equals(Money::USD(10)));
    }

    public function testCompareTo(): void
    {
        $this->assertEquals(0, Money::EUR(10)->compareTo(Money::EUR(10)));
        $this->assertEquals(-1, Money::EUR(5)->compareTo(Money::EUR(10)));
        $this->assertEquals(1, Money::EUR(10)->compareTo(Money::EUR(5)));
    }

    public function testCompareToCurrencyMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::EUR(10)->compareTo(Money::USD(10));
    }

    // ─── Formatting ─────────────────────────────────────────────────────

    public function testFormatGerman(): void
    {
        $m = new Money(1234.56, 'EUR');
        $this->assertEquals('1.234,56€', $m->format('de'));
    }

    public function testFormatEnglish(): void
    {
        $m = new Money(1234.56, 'EUR');
        $this->assertEquals('1,234.56€', $m->format('en'));
    }

    public function testFormatUsdSymbolBefore(): void
    {
        $m = Money::USD(1234.56);
        $this->assertEquals('$1,234.56', $m->format('en'));
    }

    public function testFormatHufNoDecimals(): void
    {
        $m = Money::HUF(1500);
        $formatted = $m->format('en');
        $this->assertEquals('1,500Ft', $formatted);
    }

    public function testFormatPlain(): void
    {
        $m = new Money(1234.56, 'EUR');
        $this->assertEquals('1.234,56', $m->formatPlain('de'));
        $this->assertEquals('1,234.56', $m->formatPlain('en'));
    }

    public function testToString(): void
    {
        $m = Money::EUR(19.99);
        $str = (string)$m;
        $this->assertIsString($str);
        $this->assertNotEmpty($str);
    }

    // ─── Exchange ───────────────────────────────────────────────────────

    public function testExchangeRateSameCurrency(): void
    {
        $this->assertEquals(1.0, Money::exchangeRate('EUR', 'EUR'));
    }

    public function testExchangeRateBuiltIn(): void
    {
        $this->assertEquals(4.0, Money::exchangeRate('EUR', 'PLN'));
        $this->assertEquals(0.25, Money::exchangeRate('PLN', 'EUR'));
    }

    public function testExchangeRateUnknown(): void
    {
        $this->assertNull(Money::exchangeRate('EUR', 'JPY'));
    }

    public function testExchangeToWithRate(): void
    {
        $m = Money::EUR(100)->exchangeTo('USD', 1.08);
        $this->assertEquals('USD', $m->currency());
        $this->assertEqualsWithDelta(108.0, $m->amount(), 0.01);
    }

    public function testExchangeToAutoRate(): void
    {
        $m = Money::EUR(100)->exchangeTo('PLN');
        $this->assertEquals('PLN', $m->currency());
        $this->assertEqualsWithDelta(400.0, $m->amount(), 0.01);
    }

    public function testExchangeToNoRate(): void
    {
        $this->expectException(\RuntimeException::class);
        Money::EUR(100)->exchangeTo('JPY');
    }

    public function testCustomExchangeRateProvider(): void
    {
        Money::setExchangeRateProvider(function($from, $to) {
            if ($from === 'EUR' && $to === 'GBP') return 0.85;
            return null;
        });
        $m = Money::EUR(100)->exchangeTo('GBP');
        $this->assertEqualsWithDelta(85.0, $m->amount(), 0.01);
        $this->assertEquals('GBP', $m->currency());

        // Cleanup
        Money::setExchangeRateProvider(function() { return null; });
    }

    // ─── Serialization ──────────────────────────────────────────────────

    public function testToArray(): void
    {
        $m = Money::EUR(19.99);
        $arr = $m->toArray();
        $this->assertArrayHasKey('amount', $arr);
        $this->assertArrayHasKey('currency', $arr);
        $this->assertArrayHasKey('formatted', $arr);
        $this->assertEquals(19.99, $arr['amount']);
        $this->assertEquals('EUR', $arr['currency']);
    }

    public function testJsonSerialize(): void
    {
        $m = Money::EUR(19.99);
        $json = $m->jsonSerialize();
        $this->assertEquals(['amount' => 19.99, 'currency' => 'EUR'], $json);
    }
}
