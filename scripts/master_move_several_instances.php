#!/usr/bin/php
<?php
/* Copyright (C) 2020-2021 Laurent Destailleur	<eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 *
 * Migrate an old instance on a new server.
 * Script must be ran with admin from master server.
 */

/**
 *      \file       sellyoursaas/scripts/master_move_several_instances.php
 *		\ingroup    sellyoursaas
 *      \brief      Script to run from the master server to move several instances from a server to another one.
 */

if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}

// Global variables
$version='1.0';
$error=0;

// Include Dolibarr environment
@set_time_limit(0);							// No timeout for this script
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);		// Set this define to 0 if you want to lock your script when dolibarr setup is "locked to admin user only".
//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');                                  // Do not create database handler $db
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');                                // Do not load object $user


// Read /etc/sellyoursaas.conf file
$masterserver='';
$instanceserver='';
$databasehost='localhost';
$databaseport='3306';
$database='';
$databaseuser='sellyoursaas';
$databasepass='';
$dolibarrdir='';
$usecompressformatforarchive='gzip';
$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
		if ($tmpline[0] == 'masterserver') {
			$masterserver = $tmpline[1];
		}
		if ($tmpline[0] == 'instanceserver') {
			$instanceserver = $tmpline[1];
		}
		if ($tmpline[0] == 'databasehost') {
			$databasehost = $tmpline[1];
		}
		if ($tmpline[0] == 'databaseport') {
			$databaseport = $tmpline[1];
		}
		if ($tmpline[0] == 'database') {
			$database = $tmpline[1];
		}
		if ($tmpline[0] == 'databaseuser') {
			$databaseuser = $tmpline[1];
		}
		if ($tmpline[0] == 'databasepass') {
			$databasepass = $tmpline[1];
		}
		if ($tmpline[0] == 'dolibarrdir') {
			$dolibarrdir = $tmpline[1];
		}
		if ($tmpline[0] == 'usecompressformatforarchive') {
			$usecompressformatforarchive = $tmpline[1];
		}
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	print "\n";
	exit(-1);
}

if (empty($dolibarrdir)) {
	print "Failed to find 'dolibarrdir' entry into /etc/sellyoursaas.conf file\n";
	print "\n";
	exit(-1);
}
if (empty($masterserver)) {
	print "Failed to find 'masterserver' entry into /etc/sellyoursaas.conf file. This script must be run on master server.\n";
	print "\n";
	exit(-1);
}

// Load Dolibarr environment
$res=0;
// Try master.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) {
	$i--;
	$j--;
}
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/master.inc.php")) {
	$res=@include substr($tmp, 0, ($i+1))."/master.inc.php";
}
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/master.inc.php")) {
	$res=@include dirname(substr($tmp, 0, ($i+1)))."/master.inc.php";
}
// Try master.inc.php using relative path
if (! $res && file_exists("../master.inc.php")) {
	$res=@include "../master.inc.php";
}
if (! $res && file_exists("../../master.inc.php")) {
	$res=@include "../../master.inc.php";
}
if (! $res && file_exists("../../../master.inc.php")) {
	$res=@include "../../../master.inc.php";
}
if (! $res && file_exists(__DIR__."/../../master.inc.php")) {
	$res=@include __DIR__."/../../master.inc.php";
}
if (! $res && file_exists(__DIR__."/../../../master.inc.php")) {
	$res=@include __DIR__."/../../../master.inc.php";
}
if (! $res && file_exists($dolibarrdir."/htdocs/master.inc.php")) {
	$res=@include $dolibarrdir."/htdocs/master.inc.php";
}
if (! $res) {
	print("Include of master fails");
	exit(-1);
}
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

dol_include_once("/sellyoursaas/core/lib/sellyoursaas.lib.php");
dol_include_once('/sellyoursaas/class/packages.class.php');
include_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/utils.class.php';
//include_once(DOL_DOCUMENT_ROOT.'/user/class/user.class.php');

$langs->loadLangs(array("main", "errors"));

$oldinstance=isset($argv[1]) ? $argv[1] : '';

$newinstance=isset($argv[2]) ? strtolower($argv[2]) : '';

$mode=isset($argv[3]) ? $argv[3] : '';

$langsen = new Translate('', $conf);
$langsen->setDefaultLang($mysoc->default_lang);
$langsen->loadLangs(array("main", "errors"));

$user->fetch(getDolGlobalString('SELLYOURSAAS_ANONYMOUSUSER'));


/*
 *	Main
 */

$utils = new Utils($db);

print "***** ".$script_file." ".$version." *****\n";

if (empty($newinstance) || empty($mode)) {
	print "Move existing instance from one server to another one (with target instances not existing yet).\n";
	print "Script must be ran from the master server with login admin.\n";
	print "\n";
	print "Usage: ".$script_file." *.withX.mysaasdomainname.com withY.mysaasdomainname.com (test|confirm|confirmmaintenance|confirmredirect)\n";
	print "Mode is: test                test mode (nothing is done).\n";
	print "         confirm             real move of the instance (deprecated, use confirmmaintenance or confirmredirect).\n";
	print "         confirmmaintenance  real move and replace old instance with a definitive message 'Suspended. Instance has been moved.'.\n";
	print "         confirmredirect     real move with a mesage 'Move in progress' during transfer, and then, switch old instance into a redirect instance.\n";
	print "Return code: 0 if success, <>0 if error\n";
	print "\n";
	exit(-1);
}

/*
	if (0 != posix_getuid()) {
		echo "Script must be ran with root.\n";
		exit(-1);
	}
} else {*/
if (0 == posix_getuid()) {
	echo "Script must not be ran with root (but with the 'admin' sellyoursaas account).\n";
	print "\n";
	exit(-1);
}

if (getDomainFromURL($oldinstance, 2) == getDomainFromURL($newinstance, 2)) {
	echo "The domain of old instance (".getDomainFromURL($oldinstance, 2).") must differs from domain of new instance (".getDomainFromURL($newinstance, 2)."). ";
	echo "If you need to change the name only staying on same server, just make a rename on instance from interface.\n";
	print "\n";
	exit(-1);
}

//$dbmaster=getDoliDBInstance('mysqli', $databasehost, $databaseuser, $databasepass, $database, $databaseport);
$dbmaster = $db;
if ($dbmaster->error) {
	dol_print_error($dbmaster, "host=".$databasehost.", port=".$databaseport.", user=".$databaseuser.", databasename=".$database.", ".$dbmaster->error);
	exit;
}
if ($dbmaster) {
	$conf->setValues($dbmaster);
}
if (empty($db)) {
	$db=$dbmaster;
}


//$user = new User();
//$user->fetch($conf->global->SELLYOURSAAS_ANONYMOUSUSER);

print "Select all instances with deploy status 'done' and matching name ".$oldinstance." and that are not redirect.\n";

// Nb of deployed instances
$nbofinstancedeployed=0;
// Nb of errors
$nboferrors=0;
// List of instances
$instances=array();				// array of paid instances
$instancestrial=array();		// array of trial instances
$instancespaidsuspended=array();
$instancespaidnotsuspendedpaymenterror=array();

$instancefilter=$oldinstance;
$instancefiltercomplete=$instancefilter;

// Forge complete name of instance
if (! empty($instancefiltercomplete) && ! preg_match('/\./', $instancefiltercomplete) && ! preg_match('/\.home\.lan$/', $instancefiltercomplete)) {
	if (!getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION')) {
		$tmparray = explode(',', getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES'));
	} else {
		dol_include_once('sellyoursaas/class/deploymentserver.class.php');
		$staticdeploymentserver = new Deploymentserver($db);
		$tmparray = $staticdeploymentserver->fetchAllDomains();
	}
	$tmpstring = preg_replace('/:.*$/', '', $tmparray[0]);
	$instancefiltercomplete = $instancefiltercomplete.".".$tmpstring;   // Automatically concat first domain name
}

include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
$object=new Contrat($dbmaster);

// Get list of instance
$sql = "SELECT c.rowid as id, c.ref, c.ref_customer as instance,";
$sql.= " ce.deployment_status as instance_status, ce.latestbackup_date_ok, ce.backup_frequency";
$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object";
$sql.= " WHERE c.ref_customer <> '' AND c.ref_customer IS NOT NULL";
$sql.= " AND c.ref_customer LIKE '".str_replace('*', '%', $instancefiltercomplete)."'";	// $instancefiltercomplete can contains % chars.
$sql.= " AND ce.deployment_status = 'done'";		// Get 'deployed' only, but only if we don't request a specific instance
$sql.= " AND ce.deployment_status IS NOT NULL";
$sql.= " AND (ce.suspendmaintenance_message IS NULL OR ce.suspendmaintenance_message NOT LIKE 'http%')";	// Exclude instance of type redirect

$dbtousetosearch = $dbmaster;

print $sql."\n";                                    // To have this into the ouput of cron job

dol_syslog($script_file, LOG_DEBUG);

$resql=$dbtousetosearch->query($sql);
if ($resql) {
	$num = $dbtousetosearch->num_rows($resql);
	$i = 0;
	if ($num) {
		// Loop on each deployed instance/contract
		while ($i < $num) {
			$obj = $dbtousetosearch->fetch_object($resql);
			if ($obj) {
				// We process the instance
				$instance = $obj->instance;

				dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');

				unset($object->linkedObjects);
				unset($object->linkedObjectsIds);

				// Load data of instance and set $instance_status (PROCESSING, DEPLOYED, SUSPENDED, UNDEPLOYED)
				$instance_status = 'UNKNOWN';
				$result = $object->fetch($obj->id);
				if ($result <= 0) {
					$i++;
					dol_print_error($dbmaster, $object->error, $object->errors);
					continue;
				} else {
					if ($object->array_options['options_deployment_status'] == 'processing') {
						$instance_status = 'PROCESSING';
					} elseif ($object->array_options['options_deployment_status'] == 'undeployed') {
						$instance_status = 'UNDEPLOYED';
					} elseif ($object->array_options['options_deployment_status'] == 'done') {
						// should be here due to test into SQL request
						$instance_status = 'DEPLOYED';
						$nbofinstancedeployed++;
					}
				}

				if ($instance_status == 'DEPLOYED') {
					$issuspended = sellyoursaasIsSuspended($object);
					if ($issuspended) {
						$instance_status = 'SUSPENDED';
					}
					// Note: to check that all non deployed instance has line with status that is 5 (close), you can run
					// select * from llx_contrat as c, llx_contrat_extrafields as ce, llx_contratdet as cd WHERE ce.fk_object = c.rowid
					// AND cd.fk_contrat = c.rowid AND ce.deployment_status <> 'done' AND cd.statut <> 5;
					// You should get nothing.
				}

				// Set $payment_status ('TRIAL', 'PAID' or 'FAILURE')
				$payment_status='PAID';
				$ispaid = sellyoursaasIsPaidInstance($object);	// This load linkedObjectsIds
				if (! $ispaid) {
					$payment_status='TRIAL';
				} else {
					$ispaymentko = sellyoursaasIsPaymentKo($object);
					if ($ispaymentko) {
						$payment_status='FAILURE';
					}
				}

				print "Analyze".($instancefiltercomplete ? '' : ' deployed')." instance ".($i+1)." ".$instance.": instance_status=".$instance_status." payment_status=".$payment_status.(empty($object->array_options['options_suspendmaintenance_message']) ? "" : " (maintenance/redirect: ".($object->array_options['options_suspendmaintenance_message']).")")."\n";

				// Count
				if (! in_array($payment_status, array('TRIAL'))) {
					// We analyze all non trial deployed instances
					if (in_array($instance_status, array('SUSPENDED'))) {
						$instancespaidsuspended[$obj->id] = $obj->ref.' ('.$instance.')';
						if (in_array($payment_status, array('FAILURE'))) {
							$instancespaidsuspendedandpaymenterror[$obj->id] = $obj->ref.' ('.$instance.')';
						}
					} else {
						$instancespaidnotsuspended[$obj->id] = $obj->ref.' ('.$instance.')';
						if (in_array($payment_status, array('FAILURE'))) {
							$instancespaidnotsuspendedpaymenterror[$obj->id] = $obj->ref.' ('.$instance.')';
						}
					}

					$instances[$obj->id] = array('id'=>$obj->id, 'ref'=>$obj->ref, 'instance'=>$instance, 'latestbackup_date_ok'=>$dbtousetosearch->jdate($obj->latestbackup_date_ok), 'backup_frequency'=>$obj->backup_frequency);
					print "Qualify instance ".$instance." with instance_status=".$instance_status." payment_status=".$payment_status."\n";
				} elseif ($instancefiltercomplete) {
					$instances[$obj->id] = array('id'=>$obj->id, 'ref'=>$obj->ref, 'instance'=>$instance, 'latestbackup_date_ok'=>$dbtousetosearch->jdate($obj->latestbackup_date_ok), 'backup_frequency'=>$obj->backup_frequency);
					print "Qualify instance ".$instance." with instance_status=".$instance_status." payment_status=".$payment_status."\n";
				} else {
					$instancestrial[$obj->id] = array('id'=>$obj->id, 'ref'=>$obj->ref, 'instance'=>$instance, 'latestbackup_date_ok'=>$dbtousetosearch->jdate($obj->latestbackup_date_ok), 'backup_frequency'=>$obj->backup_frequency);
					//print "Found instance ".$instance." with instance_status=".$instance_status." instance_status_bis=".$instance_status_bis." payment_status=".$payment_status."\n";
				}
			}
			$i++;
		}
	}
} else {
	$error++;
	$nboferrors++;
	dol_print_error($dbtousetosearch);
}
print "We found ".count($instancestrial)." deployed trial + ".count($instances)." deployed paid/confirmed instances, including ".count($instancespaidsuspended)." suspended + ".count($instancespaidnotsuspendedpaymenterror)." active with payment ko\n";


$listofinstances = $instances;

print "Found ".count($listofinstances)." instance(s) to move.\n";

$nbofmoveok = 0;
$nbofmoveko = 0;
$i = 0;

foreach ($listofinstances as $oldinstancecursor) {
	// Process instance
	$oldinstancecursorname = $oldinstancecursor['instance'];
	$tmparray = explode('.', $oldinstancecursorname);
	$newinstancecursorname = $tmparray[0].'.'.getDomainFromURL($newinstance, 2);
	$i++;

	print "\n";
	print "Move instance #".$i." ".$oldinstancecursorname." into ".$newinstancecursorname.".\n";

	$command='php '.DOL_DOCUMENT_ROOT."/custom/sellyoursaas/scripts/master_move_instance.php ".escapeshellarg($oldinstancecursorname)." ".escapeshellarg($newinstancecursorname);
	$command .= " ".$mode;
	$command .= " -y";
	print $command."\n";

	$return_val = 0;

	$outputfile = $conf->admin->dir_temp.'/out.tmp';
	//$resultarray = $utils->executeCLI($command, $outputfile, 0);
	system($command, $return_val);

	//$return_val = $resultarray['result'];
	//$content_grabbed = $resultarray['output'];

	print "Result: ".$return_val."\n";

	/*
	if (!empty($resultarray['error'])) {
		print "Output: ".$content_grabbed."\n";
		print "Error: ".$resultarray['error']."\n";
	}
	*/

	if ($return_val != 0) {
		$nbofmoveko++;
		$error++;
		break;
	} else {
		$nbofmoveok++;
	}
}

// Return
if (count($listofinstances)) {
	print "\n";
	if (! $error) {
		if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
			print 'Move of old instances matching name '.$oldinstance." done.\n";
		} else {
			print 'Move of old instances matching name '.$oldinstance." canceled (test mode)\n";
		}
	} else {
		print 'Failed to move last instance'."\n";
		print "\n";
		exit(-1);
	}
}

print "\n";
print "Finished (nb ok=".$nbofmoveok.", nb ko=".$nbofmoveko.").\n";

print "\n";

exit(0);
