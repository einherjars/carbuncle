<?php namespace Einherjars\Carbuncle\Tests;
/**
 * Part of the Carbuncle package.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the 3-clause BSD License.
 *
 * This source file is subject to the 3-clause BSD License that is
 * bundled with this package in the LICENSE file.  It is also available at
 * the following URL: http://www.opensource.org/licenses/BSD-3-Clause
 *
 * @package    Carbuncle
 * @version    2.0.0
 * @author     Einherjars LLC
 * @license    BSD License (3-clause)
 * @copyright  (c) 2011 - 2013, Einherjars LLC
 * @link       http://einherjars.com
 */

use Mockery as m;
use Einherjars\Carbuncle\Cookies\FuelPHPCookie;
use PHPUnit_Framework_TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class FuelPHPCookieTest extends PHPUnit_Framework_TestCase {

	/**
	 * Setup resources and dependencies.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass()
	{
		require_once __DIR__.'/stubs/fuelphp/Cookie.php';
	}

	/**
	 * Close mockery.
	 *
	 * @return void
	 */
	public function tearDown()
	{
		m::close();
	}

	public function testOverridingKey()
	{
		$cookie = new FuelPHPCookie('bar');
		$this->assertEquals('bar', $cookie->getKey());
	}

	public function testPut()
	{
		$cookie = new FuelPHPCookie('foo');
		$cookie->put('bar', 120);

		$this->assertTrue(isset($_SERVER['__cookie.set']));
		$result = $_SERVER['__cookie.set'];
		$this->assertEquals('foo', $result[0]);
		$this->assertEquals(json_encode('bar'), $result[1]);
		$this->assertEquals(120, $result[2]);
		unset($_SERVER['__cookie.set']);
	}

	public function testForever()
	{
		$cookie = m::mock('Einherjars\Carbuncle\Cookies\FuelPHPCookie[put]');

		$me = $this;
		$cookie->shouldReceive('put')->with('bar', m::on(function($value) use ($me)
		{
			// Value must be at least 5 years in advance
			// to satisfy being "forever". We're using
			// PHPUnit assertions here so that an Exception
			// is thrown which is more meaningful to the user
			$me->assertGreaterThanOrEqual(2628000, $value, 'The expiry date must be at least 5 years (2628000 seconds) in advance.');

			// We never get here if the above assertion
			// was false, save to proceed.
			return true;

		}))->once();

		$cookie->forever('bar');
	}

	public function testGet()
	{
		$cookie = new FuelPHPCookie('foo');
		$this->assertEquals('baz', $cookie->get());
	}

	public function testForget()
	{
		$cookie = new FuelPHPCookie('foo');
		$this->assertFalse(isset($_SERVER['__cookie.delete']));
		$cookie->forget();
		$this->assertTrue($_SERVER['__cookie.delete']);
		unset($_SERVER['__cookie.delete']);
	}

}