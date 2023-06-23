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
 *      \file       sellyoursaas/scripts/update_total.php
 *		\ingroup    sellyoursaas
 *
 *      This script call the update_total on all recurring invoice into tmp_tmp table.
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
if (0 == posix_getuid()) {
	echo "Script must NOT be ran with root (but with the 'admin' sellyoursaas account).\n";
	print "\n";
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
if (! $res && file_exists("../../../../master.inc.php")) $res=@include "../../../../master.inc.php";
if (! $res && file_exists(__DIR__."/../../master.inc.php")) $res=@include __DIR__."/../../master.inc.php";
if (! $res && file_exists(__DIR__."/../../../master.inc.php")) $res=@include __DIR__."/../../../master.inc.php";
if (! $res && file_exists(__DIR__."/../../../../master.inc.php")) $res=@include __DIR__."/../../../../master.inc.php";
if (! $res) {
	print "Include of master fails";
	exit(-1);
}
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/utils.class.php';
include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
include_once dol_buildpath("/sellyoursaas/backoffice/lib/refresh.lib.php");		// This set $serverprice

// Read /etc/sellyoursaas.conf file
$domain='';
$databasehost='localhost';
$databaseport='3306';
$database='';
$databaseuser='sellyoursaas';
$databasepass='';
$ipserverdeployment='';
$emailfrom='';
$emailsupervision='';
$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
		if ($tmpline[0] == 'domain') {
			$domain = dol_string_nospecial($tmpline[1]);
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
		if ($tmpline[0] == 'ipserverdeployment') {
			$ipserverdeployment = $tmpline[1];
		}
		if ($tmpline[0] == 'emailfrom') {
			$emailfrom = $tmpline[1];
		}
		if ($tmpline[0] == 'emailsupervision') {
			$emailsupervision = $tmpline[1];
		}
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit(-1);
}
if (empty($emailfrom)) {
	$emailfrom="noreply@".$domain;
}
if (empty($emailsupervision)) {
	$emailsupervision="supervision@".$domain;
}

$sendcontext = 'emailing';
//$sendcontext = 'standard';



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

// Set serverprice with the param from $conf of the $dbmaster server.
$serverprice = empty($conf->global->SELLYOURSAAS_INFRA_COST)?'100':$conf->global->SELLYOURSAAS_INFRA_COST;

//$langs->setDefaultLang('en_US'); 	// To change default language of $langs
$langs->load("main");				// To load language file for default language
@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
//$result=$user->fetch('','admin');	// Load user for login 'admin'. Comment line to run as anonymous user.
//if (! $result > 0) { dol_print_error('',$user->error); exit(-1); }
//$user->getrights();


print "***** ".$script_file." (".$version.") - ".dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt')." *****\n";
//print 'Argument 1='.$argv[1]."\n";
//print 'Argument 2='.$argv[2]."\n";

$now = dol_now();

$action=empty($argv[1]) ? '' : $argv[1];
$nbofok=0;
$nbofokdiscarded=0;

if ($action != 'confirm' && $action != 'test') {
	print "Usage: ".$script_file." test|confirm\n";
	exit(-1);
}


// Initialize the array $instances*


// Nb of deployed instances
$nbofinstancedeployed=0;
// Nb of errors
$nboferrors=0;
// List of instances
$instances=array();				// array of paid instances
$instancestrial=array();		// array of trial instances
$instancespaidsuspended=array();
$instancespaidsuspendedandpaymenterror=array();
$instancespaidnotsuspended=array();
$instancespaidnotsuspendedpaymenterror=array();
$instancesbackuperror=array();
$instancesupdateerror=array();
$instancesbackupsuccess=array();
$instancesbackupsuccessdiscarded=array();


$instancefilter=(isset($argv[2])?$argv[2]:'');
$instancefiltercomplete=$instancefilter;

// Forge complete name of instance
if (! empty($instancefiltercomplete) && ! preg_match('/\./', $instancefiltercomplete) && ! preg_match('/\.home\.lan$/', $instancefiltercomplete)) {
	if (empty(getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION'))) {
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

// Get list of recurring invoices
$sql = "SELECT frid, cid FROM tmp_tmp";

$dbtousetosearch = $dbmaster;

print $sql."\n";                                    // To have this into the ouput of cron job

dol_syslog($script_file, LOG_DEBUG);

$dbtousetosearch->begin();

$i = 0;
$resql=$dbtousetosearch->query($sql);
if ($resql) {
	$num = $dbtousetosearch->num_rows($resql);
	if ($num) {
		// Loop on each deployed instance/contract
		while ($i < $num) {
			$obj = $dbtousetosearch->fetch_object($resql);
			if ($obj) {
				// We process the invoice
				print "Update invoice rec id = ".$obj->frid."\n";
				$tmpinvoicerec = new FactureRec($db);
				$result = $tmpinvoicerec->fetch($obj->frid);
				if ($result) {
					$tmpinvoicerec->update_price();
				}

				// We process the contract
				/*print "Update contract = ".$obj->cid."\n";
				$tmpcontract = new Contrat($db);
				$result = $tmpcontract->fetch($obj->cid);
				if ($result) {
					$tmpcontract->update_price();
				}*/
			}
			$i++;
		}
	}
} else {
	$error++;
	$nboferrors++;
	dol_print_error($dbtousetosearch);
}

print "We found ".$i." lines processed\n";

if (!$error && $action == 'confirm') {
	$dbtousetosearch->commit();
} else {
	$dbtousetosearch->rollback();
}

$dbmaster->close();	// Close database opened handler

exit($nboferrors);
