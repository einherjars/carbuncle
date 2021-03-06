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
use Einherjars\Carbuncle\Carbuncle;
use Einherjars\Carbuncle\Throttling\UserBannedException;
use Einherjars\Carbuncle\Throttling\UserSuspendedException;
use Einherjars\Carbuncle\Users\UserNotFoundException;
use PHPUnit_Framework_TestCase;

class CarbuncleTest extends PHPUnit_Framework_TestCase {

	protected $userProvider;

	protected $groupProvider;

	protected $throttleProvider;

	protected $hasher;

	protected $session;

	protected $cookie;

	protected $carbuncle;

	/**
	 * Setup resources and dependencies.
	 *
	 * @return void
	 */
	public function setUp()
	{
		$this->carbuncle = new Carbuncle(
			$this->userProvider     = m::mock('Einherjars\Carbuncle\Users\ProviderInterface'),
			$this->groupProvider    = m::mock('Einherjars\Carbuncle\Groups\ProviderInterface'),
			$this->throttleProvider = m::mock('Einherjars\Carbuncle\Throttling\ProviderInterface'),
			$this->session          = m::mock('Einherjars\Carbuncle\Sessions\SessionInterface'),
			$this->cookie           = m::mock('Einherjars\Carbuncle\Cookies\CookieInterface')
		);
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

	/**
	 * @expectedException Einherjars\Carbuncle\Users\UserNotActivatedException
	 */
	public function testLoggingInUnactivatedUser()
	{
		$user = m::mock('Einherjars\Carbuncle\Users\UserInterface');
		$user->shouldReceive('isActivated')->once()->andReturn(false);
		$user->shouldReceive('getLogin')->once()->andReturn('foo');

		$this->carbuncle->login($user);
	}

	public function testLoggingInUser()
	{
		$user = m::mock('Einherjars\Carbuncle\Users\UserInterface');
		$user->shouldReceive('isActivated')->once()->andReturn(true);
		$user->shouldReceive('getId')->once()->andReturn('foo');
		$user->shouldReceive('getPersistCode')->once()->andReturn('persist_code');
		$user->shouldReceive('recordLogin')->once();

		$this->session->shouldReceive('put')->with(array('foo', 'persist_code'))->once();

		$this->carbuncle->login($user);
	}

	public function testLoggingInAndRemembering()
	{
		$carbuncle = m::mock('Einherjars\Carbuncle\Carbuncle[login]', array(null, null, null, $this->session));
		$carbuncle->shouldReceive('login')->with($user = m::mock('Einherjars\Carbuncle\Users\UserInterface'), true)->once();
		$carbuncle->loginAndRemember($user);
	}

	/**
	 * @expectedException Einherjars\Carbuncle\Users\LoginRequiredException
	 */
	public function testAuthenticatingUserWhenLoginIsNotProvided()
	{
		$credentials = array();

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($user = m::mock('Einherjars\Carbuncle\Users\UserInterface'));
		$user->shouldReceive('getLoginName')->once()->andReturn('email');

		$this->carbuncle->authenticate($credentials);
	}

	/**
	 * @expectedException Einherjars\Carbuncle\Users\PasswordRequiredException
	 */
	public function testAuthenticatingUserWhenPasswordIsNotProvided()
	{
		$credentials = array(
			'email' => 'foo@bar.com',
		);

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($user = m::mock('Einherjars\Carbuncle\Users\UserInterface'));
		$user->shouldReceive('getLoginName')->once()->andReturn('email');

		$this->carbuncle->authenticate($credentials);
	}

	/**
	 * @expectedException Einherjars\Carbuncle\Users\UserNotFoundException
	 */
	public function testAuthenticatingUserWhereTheUserDoesNotExist()
	{
		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(false);

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($user = m::mock('Einherjars\Carbuncle\Users\UserInterface'));
		$user->shouldReceive('getLoginName')->once()->andReturn('email');

		$this->userProvider->shouldReceive('findByCredentials')->with($credentials)->once()->andThrow(new UserNotFoundException);

		$this->carbuncle->authenticate($credentials);
	}

	/**
	 * @expectedException Einherjars\Carbuncle\Throttling\UserBannedException
	 */
	public function testAuthenticatingWhenUserIsBanned()
	{
		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($emptyUser = m::mock('Einherjars\Carbuncle\Users\UserInterface'));
		$emptyUser->shouldReceive('getLoginName')->once()->andReturn('email');

		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(true);
		$this->throttleProvider->shouldReceive('findByUserLogin')->with('foo@bar.com', '0.0.0.0')->once()->andReturn($throttle = m::mock('Einherjars\Carbuncle\Throttling\ThrottleInterface'));

		$throttle->shouldReceive('check')->once()->andThrow(new UserBannedException);

		$this->carbuncle->authenticate($credentials);
	}

	/**
	 * @expectedException Einherjars\Carbuncle\Throttling\UserSuspendedException
	 */
	public function testAuthenticatingWhenUserIsSuspended()
	{
		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($emptyUser = m::mock('Einherjars\Carbuncle\Users\UserInterface'));
		$emptyUser->shouldReceive('getLoginName')->once()->andReturn('email');

		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(true);
		$this->throttleProvider->shouldReceive('findByUserLogin')->with('foo@bar.com', '0.0.0.0')->once()->andReturn($throttle = m::mock('Einherjars\Carbuncle\Throttling\ThrottleInterface'));

		$throttle->shouldReceive('check')->once()->andThrow(new UserSuspendedException);

		$this->carbuncle->authenticate($credentials);
	}

	/**
	 * @expectedException Einherjars\Carbuncle\Users\UserNotFoundException
	 */
	public function testAuthenticatingUserWhereTheUserDoesNotExistWithThrottling()
	{
		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($emptyUser = m::mock('Einherjars\Carbuncle\Users\UserInterface'));
		$emptyUser->shouldReceive('getLoginName')->once()->andReturn('email');

		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(true);
		$this->throttleProvider->shouldReceive('findByUserLogin')->with('foo@bar.com', '0.0.0.0')->once()->andReturn($throttle = m::mock('Einherjars\Carbuncle\Throttling\ThrottleInterface'));

		$throttle->shouldReceive('check')->once();

		$this->userProvider->shouldReceive('findByCredentials')->with($credentials)->once()->andThrow(new UserNotFoundException);

		// If we try find the user and they do not exist, we
		// add another login attempt to their throttle
		$throttle->shouldReceive('addLoginAttempt')->once();

		$this->carbuncle->authenticate($credentials);
	}

	public function testAuthenticatingUser()
	{
		$this->carbuncle = m::mock('Einherjars\Carbuncle\Carbuncle[login]', array($this->userProvider, $this->groupProvider, $this->throttleProvider, $this->session, $this->cookie));

		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(false);

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($user = m::mock('Einherjars\Carbuncle\Users\UserInterface'));
		$user->shouldReceive('getLoginName')->once()->andReturn('email');

		$this->userProvider->shouldReceive('findByCredentials')->with($credentials)->once()->andReturn($user = m::mock('Einherjars\Carbuncle\Users\UserInterface'));

		$user->shouldReceive('clearResetPassword')->once();

		$this->carbuncle->shouldReceive('login')->with($user, false)->once();
		$this->carbuncle->authenticate($credentials);
	}

	public function testAuthenticatingUserWithThrottling()
	{
		$this->carbuncle = m::mock('Einherjars\Carbuncle\Carbuncle[login]', array($this->userProvider, $this->groupProvider, $this->throttleProvider, $this->session, $this->cookie));

		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn($emptyUser = m::mock('Einherjars\Carbuncle\Users\UserInterface'));
		$emptyUser->shouldReceive('getLoginName')->once()->andReturn('email');

		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(true);
		$this->throttleProvider->shouldReceive('findByUserLogin')->with('foo@bar.com', '0.0.0.0')->once()->andReturn($throttle = m::mock('Einherjars\Carbuncle\Throttling\ThrottleInterface'));

		$throttle->shouldReceive('check')->once();

		$this->userProvider->shouldReceive('findByCredentials')->with($credentials)->once()->andReturn($user = m::mock('Einherjars\Carbuncle\Users\UserInterface'));

		// Upon successful login with throttling, the throttle
		// attempts are cleared
		$throttle->shouldReceive('clearLoginAttempts')->once();

		// We then clear any reset password attempts as the
		// login was successfully
		$user->shouldReceive('clearResetPassword')->once();

		// And we manually log in our user
		$this->carbuncle->shouldReceive('login')->with($user, false)->once();

		$this->carbuncle->authenticate($credentials);
	}

	public function testAuthenticatingUserAndRemembering()
	{
		$this->carbuncle = m::mock('Einherjars\Carbuncle\Carbuncle[authenticate]', array(null, null, null, $this->session));

		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'baz_bat',
		);

		$this->carbuncle->shouldReceive('authenticate')->with($credentials, true)->once();
		$this->carbuncle->authenticateAndRemember($credentials);
	}

	public function testCheckLoggingOut()
	{
		$this->carbuncle->setUser(m::mock('Einherjars\Carbuncle\Users\UserInterface'));
		$this->session->shouldReceive('get')->once();
		$this->session->shouldReceive('forget')->once();
		$this->cookie->shouldReceive('get')->once();
		$this->cookie->shouldReceive('forget')->once();

		$this->carbuncle->logout();
		$this->assertNull($this->carbuncle->getUser());
	}

	public function testCheckingUserWhenUserIsSetAndActivated()
	{
		$user = m::mock('Einherjars\Carbuncle\Users\UserInterface');
		$throttle = m::mock('Einherjars\Carbuncle\Throttling\ThrottleInterface');
		$throttle->shouldReceive('isBanned')->once()->andReturn(false);
		$throttle->shouldReceive('isSuspended')->once()->andReturn(false);

		$user->shouldReceive('isActivated')->once()->andReturn(true);

		$this->throttleProvider->shouldReceive('findByUser')->once()->andReturn($throttle);
		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(true);

		$this->carbuncle->setUser($user);
		$this->assertTrue($this->carbuncle->check());
	}

	public function testCheckingUserWhenUserIsSetAndSuspended()
	{
		$user     = m::mock('Einherjars\Carbuncle\Users\UserInterface');
		$throttle = m::mock('Einherjars\Carbuncle\Throttling\ThrottleInterface');
		$session  = m::mock('Einherjars\Carbuncle\Sessions\SessionInterface');
		$cookie   = m::mock('Einherjars\Carbuncle\Cookies\CookieInterface');

		$throttle->shouldReceive('isBanned')->once()->andReturn(false);
		$throttle->shouldReceive('isSuspended')->once()->andReturn(true);

		$session->shouldReceive('forget')->once();
		$cookie->shouldReceive('forget')->once();

		$user->shouldReceive('isActivated')->once()->andReturn(true);

		$this->throttleProvider->shouldReceive('findByUser')->once()->andReturn($throttle);
		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(true);

		$this->carbuncle->setSession($session);
		$this->carbuncle->setCookie($cookie);
		$this->carbuncle->setUser($user);
		$this->assertFalse($this->carbuncle->check());
	}

	public function testCheckingUserWhenUserIsSetAndBanned()
	{
		$user     = m::mock('Einherjars\Carbuncle\Users\UserInterface');
		$throttle = m::mock('Einherjars\Carbuncle\Throttling\ThrottleInterface');
		$session  = m::mock('Einherjars\Carbuncle\Sessions\SessionInterface');
		$cookie   = m::mock('Einherjars\Carbuncle\Cookies\CookieInterface');

		$throttle->shouldReceive('isBanned')->once()->andReturn(true);

		$session->shouldReceive('forget')->once();
		$cookie->shouldReceive('forget')->once();

		$user->shouldReceive('isActivated')->once()->andReturn(true);

		$this->throttleProvider->shouldReceive('findByUser')->once()->andReturn($throttle);
		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(true);

		$this->carbuncle->setSession($session);
		$this->carbuncle->setCookie($cookie);
		$this->carbuncle->setUser($user);
		$this->assertFalse($this->carbuncle->check());
	}

	public function testCheckingUserWhenUserIsSetAndNotActivated()
	{
		$user = m::mock('Einherjars\Carbuncle\Users\UserInterface');
		$user->shouldReceive('isActivated')->once()->andReturn(false);

		$this->carbuncle->setUser($user);
		$this->assertFalse($this->carbuncle->check());
	}

	public function testCheckingUserChecksSessionFirst()
	{
		$this->session->shouldReceive('get')->once()->andReturn(array('foo', 'persist_code'));
		$this->cookie->shouldReceive('get')->never();

		$throttle = m::mock('Einherjars\Carbuncle\Throttling\ThrottleInterface');
		$throttle->shouldReceive('isBanned')->once()->andReturn(false);
		$throttle->shouldReceive('isSuspended')->once()->andReturn(false);

		$this->throttleProvider->shouldReceive('findByUser')->once()->andReturn($throttle);
		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(true);

		$this->userProvider->shouldReceive('findById')->andReturn($user = m::mock('Einherjars\Carbuncle\Users\UserInterface'));

		$user->shouldReceive('checkPersistCode')->with('persist_code')->once()->andReturn(true);
		$user->shouldReceive('isActivated')->once()->andReturn(true);

		$this->assertTrue($this->carbuncle->check());
	}

	public function testCheckingUserChecksSessionFirstAndThenCookie()
	{
		$this->session->shouldReceive('get')->once();
		$this->cookie->shouldReceive('get')->once()->andReturn(array('foo', 'persist_code'));

		$throttle = m::mock('Einherjars\Carbuncle\Throttling\ThrottleInterface');
		$throttle->shouldReceive('isBanned')->once()->andReturn(false);
		$throttle->shouldReceive('isSuspended')->once()->andReturn(false);

		$this->userProvider->shouldReceive('findById')->andReturn($user = m::mock('Einherjars\Carbuncle\Users\UserInterface'));
		$this->throttleProvider->shouldReceive('findByUser')->once()->andReturn($throttle);
		$this->throttleProvider->shouldReceive('isEnabled')->once()->andReturn(true);

		$user->shouldReceive('checkPersistCode')->with('persist_code')->once()->andReturn(true);
		$user->shouldReceive('isActivated')->once()->andReturn(true);

		$this->assertTrue($this->carbuncle->check());
	}

	public function testCheckingUserReturnsFalseIfNoArrayIsReturned()
	{
		$this->session->shouldReceive('get')->once()->andReturn('we_should_never_return_a_string');

		$this->assertFalse($this->carbuncle->check());
	}

	public function testCheckingUserReturnsFalseIfIncorrectArrayIsReturned()
	{
		$this->session->shouldReceive('get')->once()->andReturn(array('we', 'should', 'never', 'have', 'more', 'than', 'two'));

		$this->assertFalse($this->carbuncle->check());
	}

	public function testCheckingUserWhenNothingIsFound()
	{
		$this->session->shouldReceive('get')->once()->andReturn(null);

		$this->cookie->shouldReceive('get')->once()->andReturn(null);

		$this->assertFalse($this->carbuncle->check());
	}

	public function testRegisteringUser()
	{
		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'sdf_sdf',
		);

		$user = m::mock('Einherjars\Carbuncle\Users\UserInterface');
		$user->shouldReceive('getActivationCode')->never();
		$user->shouldReceive('attemptActivation')->never();
		$user->shouldReceive('isActivated')->once()->andReturn(false);

		$this->userProvider->shouldReceive('create')->with($credentials)->once()->andReturn($user);

		$this->assertEquals($user, $registeredUser = $this->carbuncle->register($credentials));
		$this->assertFalse($registeredUser->isActivated());
	}

	public function testRegisteringUserWithActivationDone()
	{
		$credentials = array(
			'email'    => 'foo@bar.com',
			'password' => 'sdf_sdf',
		);

		$user = m::mock('Einherjars\Carbuncle\Users\UserInterface');
		$user->shouldReceive('getActivationCode')->once()->andReturn('activation_code_here');
		$user->shouldReceive('attemptActivation')->with('activation_code_here')->once();
		$user->shouldReceive('isActivated')->once()->andReturn(true);

		$this->userProvider->shouldReceive('create')->with($credentials)->once()->andReturn($user);

		$this->assertEquals($user, $registeredUser = $this->carbuncle->register($credentials, true));
		$this->assertTrue($registeredUser->isActivated());
	}

	public function testGetUserWithCheck()
	{
		$carbuncle = m::mock('Einherjars\Carbuncle\Carbuncle[check]', array(null, null, null, $this->session));
		$carbuncle->shouldReceive('check')->once();
		$carbuncle->getUser();
	}

    public function testFindGroupById()
    {
        $this->groupProvider->shouldReceive('findById')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findGroupByID(1));
    }

    public function testFindGroupByName()
    {
        $this->groupProvider->shouldReceive('findByName')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findGroupByName("name"));
    }

    public function testFindAllGroups()
    {
        $this->groupProvider->shouldReceive('findAll')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findAllGroups());
    }

    public function testCreateGroup()
    {
        $this->groupProvider->shouldReceive('create')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->createGroup(array()));
    }

    public function testFindUserByID()
    {
        $this->userProvider->shouldReceive('findById')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findUserById(1));
    }
    public function testFindUserByLogin()
    {
        $this->userProvider->shouldReceive('findByLogin')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findUserByLogin("login"));
    }

    public function testFindUserByCredentials()
    {
        $this->userProvider->shouldReceive('findByCredentials')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findUserByCredentials(array()));
    }

    public function testFindUserByActivationCode()
    {
        $this->userProvider->shouldReceive('findByActivationCode')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findUserByActivationCode("x"));
    }

    public function testFindUserByResetPasswordCode()
    {
        $this->userProvider->shouldReceive('findByResetPasswordCode')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findUserByResetPasswordCode("x"));
    }

    public function testFindAllUsers()
    {
        $this->userProvider->shouldReceive('findAll')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findAllUsers());
    }

    public function testFindAllUsersInGroup()
    {
        $group = m::mock('Einherjars\Carbuncle\Groups\GroupInterface');
        $this->userProvider->shouldReceive('findAllInGroup')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findAllUsersInGroup($group));
    }

    public function testFindAllUsersWithAccess()
    {
        $this->userProvider->shouldReceive('findAllWithAccess')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findAllUsersWithAccess(""));
    }

    public function testFindAllUsersWithAnyAccess()
    {
        $this->userProvider->shouldReceive('findAllWithAnyAccess')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findAllUsersWithAnyAccess(array()));
    }

    public function testCreateUser()
    {
        $this->userProvider->shouldReceive('create')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->createUser(array()));
    }

    public function testGetEmptyUser()
    {
        $this->userProvider->shouldReceive('getEmptyUser')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->getEmptyUser());
    }

    public function testFindThrottlerByUserID()
    {
        $this->throttleProvider->shouldReceive('findByUserId')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findThrottlerByUserId(1));
    }

    public function testFindThrottlerByUserLogin()
    {
        $this->throttleProvider->shouldReceive('findByUserLogin')->once()->andReturn(true);
        $this->assertTrue($this->carbuncle->findThrottlerByUserLogin("X"));
    }

}
