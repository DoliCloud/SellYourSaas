<?php
/* Copyright (C) 2007-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) ---Put here your own copyright and developer email---
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    test/unit/SellYourSaasUtilsTest.php
 * \ingroup sellyoursaas
 * \brief   PHPUnit test for sellyoursaas.lib.php functions.
 */


global $conf,$user,$langs,$db;
//define('TEST_DB_FORCE_TYPE','mysql');	// This is to force using mysql driver
//require_once 'PHPUnit/Autoload.php';
require_once dirname(__FILE__).'/../../../dolibarr/htdocs/master.inc.php';
dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');
dol_include_once('/sellyoursaas/class/sellyoursaasutils.class.php');

if (empty($user->id)) {
	print "Load permissions for admin user nb 1\n";
	$user->fetch(1);
	$user->getrights();
}
$conf->global->MAIN_DISABLE_ALL_MAILS=1;


/**
 * Class SellYourSaasUtilsTest
 *
 * @backupGlobals disabled
 * @backupStaticAttributes enabled
 * @remarks	backupGlobals must be disabled to have db,conf,user and lang not erased.
 */
class SellYourSaasUtilsTest extends PHPUnit\Framework\TestCase
{
	protected $savconf;
	protected $savuser;
	protected $savlangs;
	protected $savdb;

	/**
	 * Constructor
	 * We save global variables into local variables
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();

		//$this->sharedFixture
		global $conf,$user,$langs,$db;
		$this->savconf=$conf;
		$this->savuser=$user;
		$this->savlangs=$langs;
		$this->savdb=$db;

		print __METHOD__." db->type=".$db->type." user->id=".$user->id;
		//print " - db ".$db->db;
		print "\n";
	}

	/**
	 * Global test setup
	 *
	 * @return void
	 */
	public static function setUpBeforeClass()
	{
		fwrite(STDOUT, __METHOD__ . "\n");
	}

	/**
	 * Unit test setup
	 *
	 * @return void
	 */
	protected function setUp()
	{
		fwrite(STDOUT, __METHOD__ . "\n");
	}

	/**
	 * Verify pre conditions
	 *
	 * @return void
	 */
	protected function assertPreConditions()
	{
		fwrite(STDOUT, __METHOD__ . "\n");
	}

	/**
	 * A sample test
	 *
	 * @return void
	 */
	public function testSomething()
	{
		fwrite(STDOUT, __METHOD__ . "\n");

		$this->assertTrue(true);
	}

	/**
	 * Verify post conditions
	 *
	 * @return void
	 */
	protected function assertPostConditions()
	{
		fwrite(STDOUT, __METHOD__ . "\n");
	}

	/**
	 * Unit test teardown
	 *
	 * @return void
	 */
	protected function tearDown()
	{
		fwrite(STDOUT, __METHOD__ . "\n");
	}

	/**
	 * Global test teardown
	 *
	 * @return void
	 */
	public static function tearDownAfterClass()
	{
		fwrite(STDOUT, __METHOD__ . "\n");
	}

	/**
	 * testGetRemoteServerDeploymentIp
	 *
	 * @return  string		IP or '' if error
	 */
	public function testGetRemoteServerDeploymentIp()
	{
		global $conf,$user,$langs,$db;
		$conf=$this->savconf;
		$user=$this->savuser;
		$langs=$this->savlangs;
		$db=$this->savdb;

		$result = 0;

		$sellyoursaasutils = new SellYourSaasUtils($db);

		$conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES = 'withX.mysellyoursaasdomain.com,withY.mysellyoursaasdomain.com:closed,...';
		$conf->global->SELLYOURSAAS_SUB_DOMAIN_IP = '1.2.3.4,5.6.7.8,...';

		$result = $sellyoursaasutils->getRemoteServerDeploymentIp('withX.mysellyoursaasdomain.com');
		print __METHOD__." result=".$result."\n";
		$this->assertEquals('1.2.3.4', $result);

		$result = $sellyoursaasutils->getRemoteServerDeploymentIp('withY.mysellyoursaasdomain.com');
		print __METHOD__." result=".$result."\n";
		$this->assertEquals('5.6.7.8', $result);

		$result = $sellyoursaasutils->getRemoteServerDeploymentIp('withY.mysellyoursaasdomain.com', 1);
		print __METHOD__." result=".$result."\n";
		$this->assertEquals('', $result);

		return $result;
	}
}
