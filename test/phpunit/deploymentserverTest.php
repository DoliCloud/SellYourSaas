<?php
/* Copyright (C) 2007-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2022 SuperAdmin <test@dolibarr.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    test/phpunit/DeploymentserverTest.php
 * \ingroup sellyoursaas
 * \brief   PHPUnit test for Deploymentserver class.
 */

global $conf, $user, $langs, $db;
//define('TEST_DB_FORCE_TYPE','mysql');	// This is to force using mysql driver

//require_once 'PHPUnit/Autoload.php';
require_once dirname(__FILE__).'/../../../dolibarr/htdocs/master.inc.php';
dol_include_once('/sellyoursaas/class/deploymentserver.class.php');


if (empty($user->id)) {
	print "Load permissions for admin user nb 1\n";
	$user->fetch(1);
	$user->getrights();
}
$conf->global->MAIN_DISABLE_ALL_MAILS = 1;

$langs->load("main");


/**
 * Class DeploymentserverTest
 *
 * @backupGlobals disabled
 * @backupStaticAttributes enabled
 * @remarks	backupGlobals must be disabled to have db,conf,user and lang not erased.
 */
class DeploymentserverTest extends PHPUnit\Framework\TestCase
{
	protected $savconf;
	protected $savuser;
	protected $savlangs;
	protected $savdb;

	/**
	 * Constructor
	 * We save global variables into local variables
	 *
	 * @return DeploymentserverTest
	 */
	public function __construct()
	{
		parent::__construct();

		//$this->sharedFixture
		global $conf, $user, $langs, $db;
		$this->savconf = $conf;
		$this->savuser = $user;
		$this->savlangs = $langs;
		$this->savdb = $db;

		print __METHOD__." db->type=".$db->type." user->id=".$user->id;
		//print " - db ".$db->db;
		print "\n";
	}

	/**
	 * Global test setup
	 *
	 * @return void
	 */
	public static function setUpBeforeClass():void
	{
		global $conf, $user, $langs, $db;
		$db->begin(); // This is to have all actions inside a transaction even if test launched without suite.

		print __METHOD__."\n";
	}

	/**
	 * Unit test setup
	 *
	 * @return void
	 */
	protected function setUp():void
	{
		global $conf, $user, $langs, $db;
		$conf = $this->savconf;
		$user = $this->savuser;
		$langs = $this->savlangs;
		$db = $this->savdb;

		print __METHOD__."\n";
	}

	/**
	 * Unit test teardown
	 *
	 * @return void
	 */
	protected function tearDown():void
	{
		print __METHOD__."\n";
	}

	/**
	 * Global test teardown
	 *
	 * @return void
	 */
	public static function tearDownAfterClass():void
	{
		global $conf, $user, $langs, $db;
		$db->rollback();

		print __METHOD__."\n";
	}


	/**
	 * A sample test
	 *
	 * @return bool
	 */
	public function testSomething()
	{
		global $conf, $user, $langs, $db;
		$conf = $this->savconf;
		$user = $this->savuser;
		$langs = $this->savlangs;
		$db = $this->savdb;

		$result = true;

		print __METHOD__." result=".$result."\n";
		$this->assertTrue($result);

		return $result;
	}

	/**
	 * testDeploymentserverCreate
	 *
	 * @return int
	 */
	public function testDeploymentserverCreate()
	{
		global $conf, $user, $langs, $db;
		$conf = $this->savconf;
		$user = $this->savuser;
		$langs = $this->savlangs;
		$db = $this->savdb;

		$localobject = new Deploymentserver($this->savdb);
		$localobject->initAsSpecimen();
		$result = $localobject->create($user);

		print __METHOD__." result=".$result."\n";
		$this->assertLessThan($result, 0);

		return $result;
	}

	/**
	 * testDeploymentserverDelete
	 *
	 * @param	int		$id		Id of object
	 * @return	int
	 *
	 * @depends	testDeploymentserverCreate
	 * The depends says test is run only if previous is ok
	 */
	public function testDeploymentserverDelete($id)
	{
		global $conf, $user, $langs, $db;
		$conf = $this->savconf;
		$user = $this->savuser;
		$langs = $this->savlangs;
		$db = $this->savdb;

		$localobject = new Deploymentserver($this->savdb);
		$result = $localobject->fetch($id);
		$result = $localobject->delete($user);

		print __METHOD__." id=".$id." result=".$result."\n";
		$this->assertLessThan($result, 0);
		return $result;
	}
}
