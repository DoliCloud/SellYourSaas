#!/usr/bin/php
<?php
/* Copyright (C) 2007-2018 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 * or see http://www.gnu.org/*
 */

/**
 *      \file       sellyoursaas/scripts/batch_scan.php
 *		\ingroup    sellyoursaas
 *      \brief      Script to detect bad instance
 */

if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');					// Do not create database handler $db
if (!defined('NOSESSION')) define('NOSESSION', '1');
if (!defined('NOREQUIREVIRTUALURL')) define('NOREQUIREVIRTUALURL', '1');

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname($_SERVER['PHP_SELF']).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}

// Global variables
$version='1.0';
$error=0;


// -------------------- START OF YOUR CODE HERE --------------------
@set_time_limit(0);							// No timeout for this script
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);		// Set this define to 0 if you want to lock your script when dolibarr setup is "locked to admin user only".

// Load Dolibarr environment
$res=0;
// Try master.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/master.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/master.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/master.inc.php")) $res=@include dirname(substr($tmp, 0, ($i+1)))."/master.inc.php";
// Try master.inc.php using relative path
if (! $res && file_exists("../master.inc.php")) $res=@include "../master.inc.php";
if (! $res && file_exists("../../master.inc.php")) $res=@include "../../master.inc.php";
if (! $res && file_exists("../../../master.inc.php")) $res=@include "../../../master.inc.php";
if (! $res && file_exists(__DIR__."/../../master.inc.php")) $res=@include __DIR__."/../../master.inc.php";
if (! $res && file_exists(__DIR__."/../../../master.inc.php")) $res=@include __DIR__."/../../../master.inc.php";
if (! $res) {
	print "Include of master fails";
	exit(-1);
}
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/utils.class.php';
include_once dol_buildpath("/sellyoursaas/backoffice/lib/refresh.lib.php");		// This set $serverprice


// Read /etc/sellyoursaas.conf file
$databasehost='localhost';
$databaseport='3306';
$database='';
$databaseuser='sellyoursaas';
$databasepass='';
$ipserverdeployment='';
$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
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
		if ($tmpline[0] == 'ipserverdeployment') {
			$ipserverdeployment = $tmpline[1];
		}
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit(-1);
}



/*
 * Main
 */

$dbmaster=getDoliDBInstance('mysqli', $databasehost, $databaseuser, $databasepass, $database, $databaseport);
if ($dbmaster->error) {
	dol_print_error($dbmaster, "host=".$databasehost.", port=".$databaseport.", user=".$databaseuser.", databasename=".$database.", ".$dbmaster->error);
	exit(-1);
}
if ($dbmaster) {
	$conf->setValues($dbmaster);
}
if (empty($db)) $db=$dbmaster;

//$langs->setDefaultLang('en_US'); 	// To change default language of $langs
$langs->load("main");				// To load language file for default language
@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
//$result=$user->fetch('','admin');	// Load user for login 'admin'. Comment line to run as anonymous user.
//if (! $result > 0) { dol_print_error('',$user->error); exit(-1); }
//$user->getrights();


print "***** ".$script_file." (".$version.") - ".strftime("%Y%m%d-%H%M%S")." *****\n";
if (! isset($argv[1])) {	// Check parameters
	print "Usage on deployment servers: ".$script_file." (test) [instancefilter]\n";
	print "\n";
	print "- test          test scan\n";
	exit(-1);
}
print '--- start script with mode '.$argv[1]."\n";
//print 'Argument 1='.$argv[1]."\n";
//print 'Argument 2='.$argv[2]."\n";

$now = dol_now();

$action=$argv[1];
$nbofok=0;
// Nb of deployed instances
$nbofinstancedeployed=0;
// Nb of paying instance
$nboferrors=0;
$instances=array();
$instancespaidsuspended=array();
$instancespaidsuspendedandpaymenterror=array();
$instancespaidnotsuspended=array();
$instancespaidnotsuspendedpaymenterror=array();
$instancesbackuperror=array();
$instancesupdateerror=array();
$instancesbackupsuccess=array();


$instancefilter=(isset($argv[2])?$argv[2]:'');
$instancefiltercomplete=$instancefilter;

// Forge complete name of instance
if (! empty($instancefiltercomplete) && ! preg_match('/\./', $instancefiltercomplete) && ! preg_match('/\.home\.lan$/', $instancefiltercomplete)) {
	$tmparray = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
	$tmpstring = preg_replace('/:.*$/', '', $tmparray[0]);
	$instancefiltercomplete = $instancefiltercomplete.".".$tmpstring;   // Automatically concat first domain name
}


/*
include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
$object=new Contrat($dbmaster);

// Get list of instance
$sql = "SELECT c.rowid as id, c.ref, c.ref_customer as instance,";
$sql.= " ce.deployment_status as instance_status";
$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object";
$sql.= " WHERE c.ref_customer <> '' AND c.ref_customer IS NOT NULL";
if ($instancefiltercomplete) {
	$stringforsearch = '';
	$tmparray = explode(',', $instancefiltercomplete);
	foreach ($tmparray as $instancefiltecompletevalue) {
		if (! empty($stringforsearch)) $stringforsearch.=", ";
		$stringforsearch.="'".trim($instancefiltecompletevalue)."'";
	}
	$sql.= " AND c.ref_customer IN (".$stringforsearch.")";
} else {
	$sql.= " AND ce.deployment_status = 'done'";		// Get 'deployed' only, but only if we don't request a specific instance
}
$sql.= " AND ce.deployment_status IS NOT NULL";
$sql.= " AND (ce.suspendmaintenance_message IS NULL OR ce.suspendmaintenance_message NOT LIKE 'http%')";	// Exclude instance of type redirect
// Add filter on deployment server
if ($action == 'backup' || $action == 'backupdelete' ||$action == 'backuprsync' || $action == 'backupdatabase' || $action == 'backuptestrsync' || $action == 'backuptestdatabase') {
	$sql.=" AND ce.deployment_host = '".$dbmaster->escape($ipserverdeployment)."'";
}

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

					$instances[$obj->id]=$instance;
					print "Qualify instance ".$instance." with instance_status=".$instance_status." payment_status=".$payment_status."\n";
				} elseif ($instancefiltercomplete) {
					$instances[$obj->id]=$instance;
					print "Qualify instance ".$instance." with instance_status=".$instance_status." payment_status=".$payment_status."\n";
				} else {
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
print "We found ".count($instances)." qualified instances including ".count($instancespaidsuspended)." suspended + ".count($instancespaidnotsuspendedpaymenterror)." active with payment ko\n";
*/

//print "----- Start loop for backup_instance\n";

$fp = @fopen("/tmp/spam/blacklistcontent", "r");
if ($fp) {
	while (($buffer = fgets($fp, 4096)) !== false) {
		echo 'Scan if if found the string '.$buffer.' into /home/jails/home/osu*/dbn*/htdocs/index.php'."\n";
	}
	if (!feof($fp)) {
		echo "Erreur: fgets() a échoué\n";
	}
	fclose($fp);
}


$dbmaster->close();	// Close database opened handler

exit($nboferrors);
