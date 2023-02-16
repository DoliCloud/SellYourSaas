#!/usr/bin/php
<?php
/* Copyright (C) 2007-2019 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *      \file       sellyoursaas/scripts/master_deploy_sshkey.php
 *		\ingroup    sellyoursaas
 *      \brief      Script to run from master server to redeploy the public keys found into setup to the authorized_keys_support file of all customers
 *                  on deployment servers. Deployment is done from master using the dolicloud_files_refresh() method (so using login/pass of accounts).
 *                  This script erases the old version of authorized_keys_support files.
 */

if (!defined('NOSESSION')) define('NOSESSION', '1');

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname($_SERVER['PHP_SELF']).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit;
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
if (! $res && file_exists("./master.inc.php")) $res=@include "./master.inc.php";
if (! $res && file_exists("../master.inc.php")) $res=@include "../master.inc.php";
if (! $res && file_exists("../../master.inc.php")) $res=@include "../../master.inc.php";
if (! $res && file_exists("../../../master.inc.php")) $res=@include "../../../master.inc.php";
if (! $res) die("Include of master fails");
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

include_once dol_buildpath("/sellyoursaas/backoffice/lib/refresh.lib.php");

// Set serverprice with the param from $conf of the $dbmaster server.
$serverprice = empty($conf->global->SELLYOURSAAS_INFRA_COST)?'100':$conf->global->SELLYOURSAAS_INFRA_COST;

//$langs->setDefaultLang('en_US'); 	// To change default language of $langs
$langs->load("main");				// To load language file for default language
@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
//$result=$user->fetch('','admin');	// Load user for login 'admin'. Comment line to run as anonymous user.
//if (! $result > 0) { dol_print_error('',$user->error); exit; }
//$user->getrights();


print "***** ".$script_file." (".$version.") - ".dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt')." *****\n";
if (! isset($argv[1])) {	// Check parameters
	print 'Redeploy the public keys found into the setup (SELLYOURSAAS_PUBLIC_KEY) to the authorized_keys_support file of all customers on 1 or all deployment servers.'."\n";
	print 'Deployment is done from master using the dolicloud_files_refresh() method (so using login/pass of accounts).'."\n";
	print "This script must be ran from the master server.\n";
	print "\n";
	print "Usage: ".$script_file." (test|confirm) [instancefilter]\n";
	print "\n";
	print "- test     test deployment of the public keys into authorized_keys_support (nothing is done)\n";
	print "- confirm  deploy the public key into authorized_keys_support (it erases old verions)\n";
	exit;
}
print '--- start'."\n";
//print 'Argument 1='.$argv[1]."\n";
//print 'Argument 2='.$argv[2]."\n";



/*
 * Main
 */

$now = dol_now();

$action=$argv[1];
$nbofko=0;
$nbofok=0;
$nbofactive=0;
$nbofactivesusp=0;
$nbofactivepaymentko=0;
$nbofalltime=0;
$nboferrors=0;
$instancefilter=(isset($argv[2])?$argv[2]:'');
$instancefiltercomplete=$instancefilter;

$instances=array();
$instanceserror=array();

$dbtousetosearch = $db;

include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
$object=new Contrat($db);


// Get list of instance that we must deploy to.
$sql = "SELECT c.rowid as id, c.ref_customer as instance,";
$sql.= " ce.deployment_status as instance_status";
$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object";
$sql.= " WHERE c.ref_customer <> '' AND c.ref_customer IS NOT NULL";
if ($instancefiltercomplete) {
	$sql.= " AND c.ref_customer LIKE '".$dbtousetosearch->escape($instancefiltercomplete).".%'";
}
$sql.= " AND ce.deployment_status IS NOT NULL";


dol_syslog($script_file, LOG_DEBUG);

$resql=$dbtousetosearch->query($sql);
if ($resql) {
	$num = $dbtousetosearch->num_rows($resql);
	$i = 0;
	if ($num) {
		while ($i < $num) {
			$obj = $dbtousetosearch->fetch_object($resql);
			if ($obj) {
				$instance = $obj->instance;
				$payment_status='PAID';
				$found = true;

				dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');
				$object = new Contrat($db);

				$instance_status = '';
				$result = $object->fetch($obj->id);
				if ($result <= 0) $found=false;
				else {
					if ($object->array_options['options_deployment_status'] == 'processing') {
						$instance_status = 'PROCESSING';
					} elseif ($object->array_options['options_deployment_status'] == 'undeployed') {
						$instance_status = 'UNDEPLOYED';
					} elseif ($object->array_options['options_deployment_status'] == 'done') {
						$instance_status = 'DEPLOYED';
					} else {
						$instance_status = 'UNKNOWN';
					}
				}

				$ispaid = sellyoursaasIsPaidInstance($object);
				if (! $ispaid) {
					$payment_status='TRIAL';
				} else {
					$ispaymentko = sellyoursaasIsPaymentKo($object);
					if ($ispaymentko) $payment_status='FAILURE';
				}

				print "Analyze instance ".($i+1)." ".$instance." status=".$instance_status." instance_status=".$instance_status." payment_status=".$payment_status."\n";

				// Count
				if ($found) {
					if ($instancefiltercomplete) {
						$instances[$obj->id]=$object;
						print "Qualify instance ".($i+1)." ".$instance." with instance_status=".$instance_status." instance_status_bis=".$instance_status." payment_status=".$payment_status." subscription_status(not used)=".$obj->subscription_status."\n";
					} else {
						$nbofalltime++;
						if (! in_array($instance_status, array('PROCESSING')) && ! in_array($instance_status, array('UNDEPLOYED'))) {		// Nb of active
							$nbofactive++;

							if (in_array($instance_status, array('SUSPENDED'))) $nbofactivesusp++;
							else $nbofactiveok++; // not suspended, not close request

							$instances[$obj->id]=$object;
							print "Qualify instance ".($i+1)." ".$instance." with instance_status=".$instance_status." instance_status_bis=".$instance_status." payment_status=".$payment_status." subscription_status(not used)=".$obj->subscription_status."\n";
						} else {
							//print "Found instance ".$instance." with instance_status=".$instance_status." instance_status_bis=".$instance_status_bis." payment_status=".$payment_status." subscription_status(not used)=".$obj->subscription_status."\n";
						}
					}
				} else {
					//print "Found instance ".$instance." with instance_status=".$instance_status." instance_status_bis=".$instance_status_bis." payment_status=".$payment_status." subscription_status(not used)=".$obj->subscription_status."\n";
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
print "Found ".count($instances)." instances including ".$nbofactivesusp." suspended\n";


//print "----- Start loop on each instance\n";
if ($action == 'test' || $action == 'confirm') {
	// Loop on each instance
	if (! $error) {
		$i = 0;
		foreach ($instances as $key => $tmpobject) {
			$instance = ($tmpobject->instance ? $tmpobject->instance : $tmpobject->ref_customer);

			$now=dol_now();

			$return_val=0; $error=0; $errors=array();	// No error by default into each loop

			// Run backup
			print "--- Process deploy of public key to instance ".($i+1)." (id ".$key.") ".$instance.' - '.dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt')."\n";

			$errors=array();

			if ($action == 'confirm') {
				$return_val = dolicloud_files_refresh($conf, $db, $tmpobject, $errors, 1, 2);

				echo "Result: ".$return_val."\n";
				echo "Output: ".join(',', $errors)."\n";
			} else {
				echo "Test mode, nothing done\n";
			}

			if (! empty($errors)) $error++;

			//
			if (! $error) {
				$nbofok++;
				print 'Process success for '.$instance."\n";
			} else {
				$nboferrors++;
				$instanceserror[]=$instance;
				print 'Process fails for '.$instance."\n";
			}

			$i++;
		}
	}
}


// Result
print "Nb of instances deployment ok: ".$nbofok."\n";
print "Nb of instances deployment ko: ".$nboferrors;
print (count($instanceserror)?", error for deploy public key on ".join(',', $instanceserror):"");
print "\n";
if (! $nboferrors) {
	print '--- end ok - '.dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt')."\n";
} else {
	print '--- end error code='.$nboferrors.' - '.dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt')."\n";
}

$db->close();	// Close database opened handler

exit($nboferrors);
