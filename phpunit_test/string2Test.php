<?php
/**
 *
 *
 *
 *
 * @license   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
namespace phlibs\Test;

use phlibs\string2;
use PHPUnit\Framework\TestCase;
/**
 * PHPMailer - PHP email transport unit test class.
 */
final class SQLTest extends TestCase {

	public function testvall() {
		$this->assertEquals(123.45, string2::vall("123.45"));
		$this->assertEquals(123.45, string2::vall("123,45"));
		$this->assertEquals(12345.67, string2::vall("12.345,67"));
	}

	
	
}