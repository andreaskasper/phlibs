<?php
/**
 *
 *
 *
 *
 * @license   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
namespace phlibs\Test;

use phlibs\SQL;
use PHPUnit\Framework\TestCase;
/**
 * PHPMailer - PHP email transport unit test class.
 */
final class SQLTest extends TestCase {
	
	public function testconvtxt() {
		$test = array('"' => '""');
		foreach ($test as $k => $v) {
			$this->assertEquals($v, SQL::convtxt($k));
		}
	}
	
}
