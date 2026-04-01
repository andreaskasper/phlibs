<?php
/**
 * Unit tests for the phlibs\string2 class.
 *
 * @license FreeFoodLicense
 */

namespace phlibs\Test;

use phlibs\string2;
use PHPUnit\Framework\TestCase;

final class string2Test extends TestCase
{
    /**
     * Test vall() with US decimal format.
     */
    public function testVallUsFormat(): void
    {
        $this->assertEquals(123.45, string2::vall("123.45"));
    }

    /**
     * Test vall() with European comma decimal format.
     */
    public function testVallEuropeanFormat(): void
    {
        $this->assertEquals(123.45, string2::vall("123,45"));
    }

    /**
     * Test vall() with European format including thousands separator.
     */
    public function testVallEuropeanWithThousands(): void
    {
        $this->assertEquals(12345.67, string2::vall("12.345,67"));
    }

    /**
     * Test vall() with non-numeric input returns empty string.
     */
    public function testVallNonNumeric(): void
    {
        $this->assertEquals("", string2::vall("abc"));
    }

    /**
     * Test parse_phonenr() normalizes international numbers.
     */
    public function testParsePhoneNr(): void
    {
        $this->assertEquals("004917012345667", string2::parse_phonenr("+49 170 1234 5667"));
    }

    /**
     * Test parse_phonenr() strips non-digit characters.
     */
    public function testParsePhoneNrStripsChars(): void
    {
        $this->assertEquals("017012345678", string2::parse_phonenr("0170-123 456 78"));
    }

    /**
     * Test Abkuerzen() returns original text when short enough.
     */
    public function testAbkuerzenNoTruncation(): void
    {
        $this->assertEquals("Hello", string2::Abkuerzen("Hello", 10));
    }

    /**
     * Test Abkuerzen() truncates at word boundary with ellipsis.
     */
    public function testAbkuerzenTruncates(): void
    {
        $result = string2::Abkuerzen("Hello World this is a long text", 15);
        $this->assertStringEndsWith("…", $result);
        $this->assertLessThanOrEqual(16, strlen($result)); // 15 + ellipsis
    }
}
