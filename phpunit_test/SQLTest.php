<?php
/**
 * Unit tests for the phlibs\SQL class.
 *
 * @license FreeFoodLicense
 */

namespace phlibs\Test;

use phlibs\SQL;
use PHPUnit\Framework\TestCase;

final class SQLTest extends TestCase
{
    /**
     * Test that SQL::init() parses a MySQL URI without throwing.
     */
    public function testInit(): void
    {
        $this->expectNotToPerformAssertions();
        SQL::init(99, "mysql://root@localhost/test/");
    }

    /**
     * Test that SQL::init() rejects non-mysql URIs.
     */
    public function testInitRejectsInvalidScheme(): void
    {
        $this->expectException(\Exception::class);
        SQL::init(98, "postgres://root@localhost/test/");
    }

    /**
     * Test that convtxt() is an instance method that escapes strings.
     * Requires a live database connection.
     *
     * @group database
     */
    public function testConvtxt(): void
    {
        try {
            SQL::init(0, "mysql://root@127.0.0.1:3306/test/");
            $db = new SQL(0);
            // Basic escaping: double quotes should be escaped
            $escaped = $db->convtxt('test"value');
            $this->assertIsString($escaped);
            $this->assertStringContainsString('\\', $escaped);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    /**
     * Test that setLongQueryCallback registers without errors.
     */
    public function testSetLongQueryCallback(): void
    {
        $this->expectNotToPerformAssertions();
        SQL::setLongQueryCallback(5, function ($info) {
            // no-op
        });
    }
}
