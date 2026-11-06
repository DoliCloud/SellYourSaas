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
 * \file    test/unit/SellYourSaasLibTest.php
 * \ingroup sellyoursaas
 * \brief   PHPUnit test for sellyoursaas.lib.php functions.
 */


global $conf,$user,$langs,$db;
//define('TEST_DB_FORCE_TYPE','mysql');	// This is to force using mysql driver
//require_once 'PHPUnit/Autoload.php';
require_once dirname(__FILE__).'/../../../dolibarr/htdocs/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');

if (empty($user->id)) {
	print "Load permissions for admin user nb 1\n";
	$user->fetch(1);
	$user->loadRights();
}
$conf->global->MAIN_DISABLE_ALL_MAILS=1;


/**
 * Class SellYourSaasLibTest
 *
 * @backupGlobals disabled
 * @backupStaticAttributes enabled
 * @remarks	backupGlobals must be disabled to have db,conf,user and lang not erased.
 */
class SellYourSaasLibTest extends PHPUnit\Framework\TestCase
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
	 * testSellyoursaasIsPaymentKo
	 *
	 * @return  int
	 */
	public function testSellyoursaasIsPaymentKo()
	{
		global $conf,$user,$langs,$db;
		$conf=$this->savconf;
		$user=$this->savuser;
		$langs=$this->savlangs;
		$db=$this->savdb;

		$result = 0;

		$contract = new Contrat($db);

		// TODO Create contract and invoice linked and enter 2 event payment error on invoice.
		$contract->fetch(22);




		$result = sellyoursaasIsPaymentKo($contract);

		print __METHOD__." result=".$result."\n";
		$this->assertEquals(2, $result);

		return $result;
	}

	/**
	 * testSellyoursaasCheckCustomUrlAlreadyUsed
	 * Check that a custom url matching the ref of a deployment server is refused,
	 * and that an unused custom url is accepted.
	 *
	 * @return  int
	 */
	public function testSellyoursaasCheckCustomUrlAlreadyUsed()
	{
		global $conf,$user,$langs,$db;
		$conf=$this->savconf;
		$user=$this->savuser;
		$langs=$this->savlangs;
		$db=$this->savdb;

		$testref = 'zztestsellyoursaas.withX.unusedtestdomain.org';

		// Insert a deployment server with ref = $testref
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."sellyoursaas_deploymentserver (ref, entity, ipaddress, status, date_creation)";
		$sql .= " VALUES ('".$db->escape($testref)."', ".((int) $conf->entity).", '127.0.0.1', 0, '".$db->idate(dol_now())."')";
		$resql = $db->query($sql);
		$this->assertTrue((bool) $resql, 'Failed to insert test deployment server');
		$serverid = $db->last_insert_id(MAIN_DB_PREFIX."sellyoursaas_deploymentserver");

		// Check a custom url that matches the ref of the deployment server
		$result = sellyoursaasCheckCustomUrlAlreadyUsed($db, $testref);
		print __METHOD__." result with url matching deployment server ref=".$result."\n";
		$this->assertEquals($serverid, $result);

		// Check a custom url that matches the ref of the deployment server with www prefix
		$result = sellyoursaasCheckCustomUrlAlreadyUsed($db, 'www.'.$testref);
		print __METHOD__." result with www prefix url matching deployment server ref=".$result."\n";
		$this->assertEquals($serverid, $result);

		// Check a custom url that matches no record
		$result = sellyoursaasCheckCustomUrlAlreadyUsed($db, 'zztestsellyoursaas.withX.notuseddomain.org');
		print __METHOD__." result with unused url=".$result."\n";
		$this->assertEquals(0, $result);

		// Clean test data
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."sellyoursaas_deploymentserver WHERE rowid = ".((int) $serverid);
		$resql = $db->query($sql);
		$this->assertTrue((bool) $resql, 'Failed to delete test deployment server');

		return $result;
	}
}
