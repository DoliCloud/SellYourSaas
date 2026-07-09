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
 * Script must be ran with admin from deployment server.
 */

/**
 *      \file       sellyoursaas/scripts/deployment_deploy_module.php
 *		\ingroup    sellyoursaas
 *      \brief      Script to run from the deployment server to deploy module on several instances.
 */

if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}
if (!defined('NOREQUIREDB')) {
	define('NOREQUIREDB', '1');
}				// Do not create database handler $db
if (!defined('NOREQUIREVIRTUALURL')) {
	define('NOREQUIREVIRTUALURL', '1');
}


$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}

$version='1.0';
$nbdeployok = 0;
$nbdeployko = 0;
$nbdeploynothingdone = 0;

// Include Dolibarr environment
@set_time_limit(0);							// No timeout for this script
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);		// Set this define to 0 if you want to lock your script when dolibarr setup is "locked to admin user only".

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
$subdomain='';

$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
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
		if ($tmpline[0] == 'subdomain') {
			$subdomain = $tmpline[1];
		}
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	print "\n";
	exit(-1);
}

if (empty($dolibarrdir)) {
	print "Failed to find 'dolibarrdir' entry into /etc/sellyoursaas.conf file\n";
	exit(-1);
}
if (empty($instanceserver)) {
	print "Failed to find 'instanceserver' entry into /etc/sellyoursaas.conf file. This script must be run on deployment server.\n";
	print "\n";
	exit(-1);
}

// Load Dolibarr environment
$res=0;
// Try master.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
if (! $res && file_exists("../../../../master.inc.php")) {
	$res=@include "../../../../master.inc.php";
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

$mode=isset($argv[1]) ? $argv[1] : '';
$productref=isset($argv[2]) ? $argv[2] : '';
$instancefilter = isset($argv[3]) ? $argv[3] : '';

dol_include_once("sellyoursaas/core/lib/sellyoursaas.lib.php");
dol_include_once("sellyoursaas/class/sellyoursaascontract.class.php");
dol_include_once("sellyoursaas/class/sellyoursaasutils.class.php");
dol_include_once('sellyoursaas/class/packages.class.php');
include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';


print "***** ".$script_file." ".$version." *****\n";

if (0 != posix_getuid()) {
	echo "Script must be ran with root user. Try to launch it using sudo.\n";
	exit(-1);
}

if (empty($productref)) {
	print "Deploy a specific module for all deployed instances.\n";
	print "Script must be ran from each deployment server with login root.\n";
	print "\n";
	print "Usage:   ".$script_file." test|confirm productref\n";
	print "Example: ".$script_file." test TESTMODULE\n";
	print "Return code: 0 if success, <> 0 if error\n";
	exit(-1);
}

$dbmaster = getDoliDBInstance('mysqli', $databasehost, $databaseuser, $databasepass, $database, $databaseport);
if ($dbmaster) {
	$conf->setValues($dbmaster);
}
$db = $dbmaster;
$mysoc = new Societe($db);
$mysoc->setMysoc($conf);

// Try to find module to deploy
$product = new Product($dbmaster);
$res = $product->fetch('', $productref);
if ($res <= 0) {
	print "Bad value for productid with action ".$mode.".\n";
	exit(-1);
}

$instancefiltercomplete = $instancefilter; //TODO: escape filter
if (empty($instancefilter)) {
	$instancefiltercomplete = "*";
}
$instancefiltercomplete .= ".".$subdomain;

include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
$object=new Contrat($dbmaster);

$sql = "SELECT c.rowid as id, c.ref, c.ref_customer as instance,";
$sql.= " ce.deployment_status as instance_status, ce.latestbackup_date_ok, ce.backup_frequency";
$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object";
$sql.= " WHERE c.ref_customer <> '' AND c.ref_customer IS NOT NULL";
$sql.= " AND c.ref_customer LIKE '".str_replace('*', '%', $instancefiltercomplete)."'";	// $instancefiltercomplete can contains % chars.
$sql.= " AND ce.deployment_status = 'done'";		// Get 'deployed' only, but only if we don't request a specific instance
$sql.= " AND ce.deployment_status IS NOT NULL";
$sql.= " AND (ce.suspendmaintenance_message IS NULL OR ce.suspendmaintenance_message NOT LIKE 'http%')";	// Exclude instance of type redirect
$sql.= " ORDER BY instance ASC";

dol_syslog($script_file, LOG_DEBUG);

$resql=$dbmaster->query($sql);
if ($resql) {
	$num = $dbmaster->num_rows($resql);
	$i = 0;
	if ($num) {
		// Loop on each deployed instance/contract
		while ($i < $num) {
			$dbmaster->begin();
			$error=0;
			$obj = $dbmaster->fetch_object($resql);
			if ($obj) {
				$instance = $obj->instance;
				print("Deploying module for instance ".$instance."\n");
				$contractid = $obj->id;

				unset($object->linkedObjects);
				unset($object->linkedObjectsIds);
				$result = $object->fetch($contractid);
				if ($result <= 0) {
					$i++;
					$nbdeployko++;
					dol_print_error($dbmaster, $object->error, $object->errors);
					continue;
				}

				$contractlines = $object->getLinesArray();
				$productlinefound = false;
				$date_end = dol_now();
				foreach ($contractlines as $tmpline) {
					if ($tmpline->fk_product == $product->id) {
						$productlinefound =true;
					}
					$date_end = $tmpline->date_end;
				}
				if ($productlinefound) {
					$i++;
					$nbdeploynothingdone++;
					print("Warning: Module ".$product->ref." already deployed for instance ".$instance."\n");
					continue;
				}

				// Create service line(s) in contract
				$expirationarray = sellyoursaasGetExpirationDate($object, 0);
				$duration_value = $expirationarray['duration_value'];
				$duration_unit = $expirationarray['duration_unit'];
				$date_start = dol_now();
				if ($date_end < $date_start) {
					$date_end = $date_start;
				}
				$idlinecontract = $object->addline($product->description, $product->price, 1, $product->tva_tx, $product->localtax1_tx, $product->localtax2_tx, $product->id, 0, $date_start, $date_end);
				if ($idlinecontract <= 0) {
					dol_print_error($dbmaster, $object->error, $object->errors);
					$nbdeployko++;
					$error++;
				}

				// Activate service line(s) in contract
				if (!$error) {
					$object->fetch($contractid);
					$result = $object->active_line($user, $idlinecontract, $date_start, '', 'Activation after option deployment');
					if (!$result) {
						dol_print_error($dbmaster, $object->error, $object->errors);
						$nbdeployko++;
						$error ++;
					}
				}

				// create service line in recurring invoice
				if (!$error) {
					$object->fetchObjectLinked();
					if (!empty($object->linkedObjects["facturerec"])) {
						$arrayfacturerec = array_values($object->linkedObjects["facturerec"]);

						if (count($arrayfacturerec) != 1) {
							print("Error: Too many recurring invoices were found for instance ".$instance."\n");
							$nbdeployko++;
							$error ++;
						} else {
							$facturerec = $arrayfacturerec[0];
							$result = $facturerec->addLine($product->description, $product->price, 1, $product->tva_tx, $product->localtax1_tx, $product->localtax2_tx, $product->id, 0, 'HT', 0, '', 0, 0, -1, 0, '', null, 0, 1, 1);
							if (!$result) {
								dol_print_error($dbmaster, $facturerec->error, $facturerec->errors);
								$nbdeployko++;
								$error ++;
							}
						}
					}
				}

				//TODO: Deploy module archive
			}
			if (!$error) {
				$dbmaster->commit();
			} else {
				$dbmaster->rollback();
			}
			$i++;
		}
	}
} else {
	$error++;
	$nboferrors++;
	dol_print_error($dbmaster);
}

print("Deployment ended with ".$nbdeployok." contract without error, ".$nbdeployko." contract with error and ".$nbdeploynothingdone." contract where nothing was done\n");