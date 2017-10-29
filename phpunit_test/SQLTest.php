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
	
	public function testinit() {
		try {
			SQL::init(0, "mysql://root@localhost/test/");
		} catch (Exception $ex) {
				$this->fail("Failed at SQL init");
		}		
	}
	
	public function testcmd() {
		try {
			$db = new SQL(0);
			$db->cmd('CREATE TABLE `testtable` (
 `id` bigint(10) NOT NULL AUTO_INCREMENT,
 `txt` text,
 `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8');

			$db->CreateUpdate("testtable", array("txt" => "Hallo"));
			
			$db->cmdrow('SELECT * FROM testtable WHERE 1');
			
			$db->cmdrows('SELECT * FROM testtable WHERE 1');
			
			
		} catch (Exception $ex) {
				$this->fail("Failed at SQL init");
		}		
	}
	
	
	public function testconvtxt() {
		$test = array('"' => '""');
		foreach ($test as $k => $v) {
			$this->assertEquals($v, SQL::convtxt($k));
		}
	}
	
}
