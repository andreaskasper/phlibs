<?php
/**
 * Unit tests for the phlibs\PageCache class.
 *
 * @license FreeFoodLicense
 */

namespace phlibs\Test;

use phlibs\PageCache;
use PHPUnit\Framework\TestCase;

final class PageCacheTest extends TestCase
{
    /**
     * Test that the PageCache class exists and is loadable.
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(PageCache::class));
    }

    /**
     * Test that ttl() stores and returns the TTL value.
     */
    public function testTtl(): void
    {
        PageCache::ttl(3600);
        $this->assertEquals(3600, PageCache::$ttl);
    }

    /**
     * Test that lastchange() tracks the most recent timestamp.
     */
    public function testLastchange(): void
    {
        // Reset
        $reflection = new \ReflectionClass(PageCache::class);
        $prop = $reflection->getProperty('lastchange');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        PageCache::lastchange(1000);
        PageCache::lastchange(2000);
        PageCache::lastchange(1500);

        $this->assertEquals(2000, PageCache::lastchange());
    }
}
