<?php
/**
 * Unit tests for the phlibs\WebCache class.
 *
 * @license FreeFoodLicense
 */

namespace phlibs\Test;

use phlibs\WebCache;
use PHPUnit\Framework\TestCase;

final class WebCacheTest extends TestCase
{
    /**
     * Test that the WebCache class exists and is loadable.
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(WebCache::class));
    }
}
