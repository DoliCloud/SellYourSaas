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
 *      \file       sellyoursaas/scripts/master_move_instance.php
 *		\ingroup    sellyoursaas
 *      \brief      Script to run from the master server to move an instance from a deployment server to another deployment server.
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
$nointeractive = 0;

$i = 0;
while ($i < $argc) {
	if (!empty($argv[$i])) {
		if ($argv[$i] == '-y') {
			$nointeractive = 1;
			unset($argv[$i]);
		}
	}
	$i++;
}


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
	print "Move an existing instance from an old server to a new server (with target instance not existing yet).\n";
	print "Script must be ran from the master server with login admin.\n";
	print "\n";
	print "Usage: ".$script_file." oldinstance.withX.mysaasdomainname.com newinstance.withY.mysaasdomainname.com (test|confirm|confirmmaintenance|confirmredirect) [MYPRODUCTREF] [-y]\n";
	print "Mode is: test                test mode (nothing is done).\n";
	print "         confirm             real move of the instance (deprecated, use confirmmaintenance or confirmredirect).\n";
	print "         confirmmaintenance  real move and replace old instance with a definitive message 'Suspended. Instance has been moved.'.\n";
	print "         confirmredirect     real move with a mesage 'Move in progress' during transfer, and then, switch old instance into a redirect instance.\n";
	print "MYPRODUCTREF can be set to force a new hosting application service.\n";
	print "Option -y can be added to automatically answer yes to questions.\n";
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


// Forge complete name of instance
if (! empty($newinstance) && ! preg_match('/\./', $newinstance) && ! preg_match('/\.home\.lan$/', $newinstance)) {
	if (!getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION')) {
		$tmparray = explode(',', getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES'));
	} else {
		dol_include_once('sellyoursaas/class/deploymentserver.class.php');
		$staticdeploymentserver = new Deploymentserver($db);
		$tmparray = $staticdeploymentserver->fetchAllDomains();
	}
	$tmpstring = preg_replace('/:.*$/', '', $tmparray[0]);
	$newinstance = $newinstance.".".$tmpstring;   // Automatically concat first domain name
}


$tmppackage = new Packages($dbmaster);

dol_include_once('/sellyoursaas/class/sellyoursaascontract.class.php');

// Get data of old instance
$oldobject = new SellYourSaasContract($dbmaster);
$result=$oldobject->fetch('', '', $oldinstance);
$oldobject->fetch_thirdparty();

if (empty($oldinstance) || $result <= 0 || $oldobject->statut == 0 || $oldobject->array_options['options_deployment_status'] != 'done') {
	print "Error: the old instance to move with full name '".$oldinstance."' and a deployment status = 'done' was not found.\n";
	print "\n";
	exit(-1);
}

$oldoshost=$oldobject->array_options['options_hostname_os'];
$oldosuser=$oldobject->array_options['options_username_os'];
$oldospass=$oldobject->array_options['options_password_os'];
//$oldosdir=$oldobject->array_options['dir'];

$olddbhost=$oldobject->array_options['options_hostname_db'];
$olddbname=$oldobject->array_options['options_database_db'];
$olddbport=($oldobject->array_options['options_port_db'] ? $oldobject->array_options['options_port_db'] : 3306);
$olddbuser=$oldobject->array_options['options_username_db'];
$olddbpass=$oldobject->array_options['options_password_db'];

/*$db2=getDoliDBInstance('mysqli', $olddbhost, $olddbuser, $olddbpass, $olddbname, $olddbport);
if ($db2->error)
{
	dol_print_error($db2,"host=".$olddbhost.", port=".$olddbport.", user=".$olddbuser.", databasename=".$olddbname.", ".$db2->error);
	exit(-1);
}*/

$productref = '';
$forceproductref = '';
if (isset($argv[4]) && $argv[4] != '-y') {
	$productref = $argv[4];
	$forceproductref = $argv[4];
}
if (empty($productref)) {
	// Get tmppackage
	foreach ($oldobject->lines as $keyline => $line) {
		$tmpproduct = new Product($dbmaster);
		if ($line->fk_product > 0) {
			$tmpproduct->fetch($line->fk_product);
			if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
				if ($tmpproduct->array_options['options_package'] > 0) {
					$productref = $tmpproduct->ref;
					$tmppackage->fetch($tmpproduct->array_options['options_package']);
					$freeperioddays = $tmpproduct->array_options['options_freeperioddays'];
					break;
				} else {
					dol_syslog("Error: ID of package not defined on productwith ID ".$line->fk_product);
				}
			}
		}
	}
}
if (empty($productref)) {
	print "Error: Failed to get product ref of instance '".$oldinstance."'\n";
	print "\n";
	exit(-1);
}

$createthirdandinstance = 0;

dol_include_once("/sellyoursaas/class/sellyoursaascontract.class.php");

$newobject = new SellYourSaasContract($dbmaster);
$result=$newobject->fetch('', '', $newinstance);
if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {	// In test mode, we accept to load into existing instance because new one will NOT be created.
	if ($result > 0 && ($newobject->statut > 0 || $newobject->array_options['options_deployment_status'] != 'processing')) {
		print "Error: An existing instance called '".$newinstance."' (with deployment status != 'processing') already exists.\n";
		print "\n";
		exit(-1);
	}
}

$newobject->instance = $newinstance;
$newobject->username_os = $oldobject->array_options['options_username_os'];
$newobject->password_os = $oldobject->array_options['options_password_os'];
$newobject->hostname_os = $oldobject->array_options['options_hostname_os'];
$newobject->username_db = $oldobject->array_options['options_username_db'];
$newobject->password_db = $oldobject->array_options['options_password_db'];
$newobject->database_db = $oldobject->array_options['options_database_db'];

if (empty($newobject->instance) || empty($newobject->username_os) || empty($newobject->password_os) || empty($newobject->database_db)) {
	print "Error: Some properties for instance ".$newinstance." could not be retreived from old instance (missing instance, username_os, password_os or database_db).\n";
	print "\n";
	exit(-3);
}

$olddirdb = preg_replace('/_([a-zA-Z0-9]+)/', '', $oldobject->array_options['options_database_db']);
$sourcedir = getDolGlobalString('DOLICLOUD_INSTANCES_PATH') . '/'.$oldobject->array_options['options_username_os'].'/'.$olddirdb;

$oldsftpconnectstring=$oldosuser.'@'.$oldoshost.':'.$sourcedir;
$adminoldsftpconnectstring='admin@'.$oldoshost;

$tmparray = explode('.', $oldinstance);
$oldshortname = $tmparray[0];
unset($tmparray[0]);
$oldwilddomain = join('.', $tmparray);


// Switch old instance in maintenance mode
if ($mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
	dol_include_once('sellyoursaas/class/sellyoursaasutils.class.php');
	$sellyoursaasutils = new SellYourSaasUtils($db);

	$comment = 'Suspended. A move of the instance into another server is in progress or has been completed.';
	print '--- Switch old instance in maintenance mode'."\n";

	$result = $sellyoursaasutils->sellyoursaasRemoteAction('suspendmaintenance', $oldobject, 'admin', '', '', '0', $comment, 300);
	if ($result <= 0) {
		print "Error calling sellyoursaasRemoteAction: ".$sellyoursaasutils->error."\n";
		print "\n";
		exit(-1);
	}

	$oldobject->array_options['options_suspendmaintenance_message'] = $comment;
	$result = $oldobject->update($user);
	if ($result < 0) {
		print "Error updating contract with redirect url: ".$oldobject->error."\n";
		print "\n";
		exit(-1);
	}
}


// Share certificate of old instance by copying them into the common crt dir (they should already be into this directory)
// If the certificate of the source instance are not into crt directory, we must copy them into the sellyoursaas master crt directory with read permission to admin user.
$CERTIFFORCUSTOMDOMAIN = $oldinstance;
if ($CERTIFFORCUSTOMDOMAIN) {
	print '--- Check/copy the certificate files (.key, .crt and -intermediate.crt) of instance (generic and custom) into the sellyoursaas master crt directory (to reuse them on the new instance for backward compatibility).'."\n";
	foreach (array('', '-custom') as $ext) {
		foreach (array('.key', '.crt', '-intermediate.crt') as $ext2) {
			$srcfile = '/etc/apache2/with.sellyoursaas.com'.$ext.$ext2;
			$srcfilecustom = '/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/'.$CERTIFFORCUSTOMDOMAIN.$ext.$ext2;
			$destfile = $conf->sellyoursaas->dir_output.'/crt/'.$CERTIFFORCUSTOMDOMAIN.$ext.$ext2;
			if (dol_is_file($destfile)) {
				print ' Certificate file '.$destfile.' already found into crt directory. Step discarded.'."\n";
			} else {
				// Try to get certificate files from the old deployment server
				print ' Rsync from admin@'.$oldoshost.' to get file '.basename($srcfilecustom)."\n";

				// May need to make chmod go+x live archive before

				$command="rsync";
				$param=array();
				$param[]="-prt";
				$param[]="-L";	// Convert symlinks into real files/dir
				$param[]="-e 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no'";
				$param[]=$adminoldsftpconnectstring.':'.$srcfilecustom;
				$param[]=$destfile;
				$fullcommand=$command." ".join(" ", $param);

				print " ".$fullcommand."\n";

				$outputfile = $conf->admin->dir_temp.'/out.tmp';
				$resultarray = $utils->executeCLI($fullcommand, $outputfile, 0);

				$return_var = $resultarray['result'];
				$content_grabbed = $resultarray['output'];

				if ($return_var) {
					if (preg_match('/No such file or directory/', $content_grabbed)) {
						// We retry with with.sellyoursaas.com.crt file
						print ' Files not found so we try to rsync from admin@'.$oldoshost.' to get file '.basename($srcfile)."\n";

						$command="rsync";
						$param=array();
						$param[]="-prt";
						$param[]="-L";	// Convert symlinks into real files/dir
						$param[]="-e 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no'";
						$param[]=$adminoldsftpconnectstring.':'.$srcfile;
						$param[]=$destfile;
						$fullcommand=$command." ".join(" ", $param);

						print " ".$fullcommand."\n";

						$outputfile = $conf->admin->dir_temp.'/out.tmp';
						$resultarray = $utils->executeCLI($fullcommand, $outputfile, 0);

						$return_var = $resultarray['result'];
						$content_grabbed = $resultarray['output'];

						if ($return_var) {
							if ($ext && preg_match('/No such file or directory/', $content_grabbed)) {
								print "  No files for ext ".$ext.$ext2." found\n";
							} else {
								// This error cas should not happen
								print " -> Error during rsync. Failed to get file.\n";
								print $content_grabbed;
								exit(-1);
							}
						} else {
							print "  File for ext ".$ext.$ext2." was sync\n";
						}
					} else {
						print " -> Error during rsync\n";
						print $content_grabbed;
					}
				} else {
					print "  File for ext ".$ext.$ext2." was sync\n";
				}
			}
		}
	}
}


print '--- Check/copy the certificate files (.key, .crt and -intermediate.crt) for websites into the sellyoursaas master crt directory (to reuse them on the new instance for backward compatibility).'."\n";
// TODO


print '--- Create new container for new instance (need sql create/write access on master database with master database user)'."\n";

$newpass = $oldobject->array_options['options_deployment_initial_password'];
if (empty($newpass)) {
	$newpass = getRandomPassword(true, array('I'), 16);
}

$command='php '.DOL_DOCUMENT_ROOT."/custom/sellyoursaas/myaccount/register_instance.php ".escapeshellarg($productref)." ".escapeshellarg($newinstance)." ".escapeshellarg($newpass)." ".escapeshellarg($oldobject->thirdparty->id);
$commandnopass='php '.DOL_DOCUMENT_ROOT."/custom/sellyoursaas/myaccount/register_instance.php ".escapeshellarg($productref)." ".escapeshellarg($newinstance)." --a-new-password-- ".escapeshellarg($oldobject->thirdparty->id);
$command.=" ".escapeshellarg($oldinstance);
echo $commandnopass."\n";

$return_val = 0;
if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
	$outputfile = $conf->admin->dir_temp.'/out.tmp';
	$resultarray = $utils->executeCLI($command, $outputfile, 0);

	$return_val = $resultarray['result'];
	$content_grabbed = $resultarray['output'];

	echo "Result: ".$return_val."\n";
	if (!empty($resultarray['error'])) {
		echo "Output: ".$content_grabbed."\n";
		echo "Error: ".$resultarray['error']."\n";
	}
}

if ($return_val != 0) {
	$error++;
}

// Return
if (! $error) {
	if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
		print '-> Creation of a new instance with name '.$newinstance." done.\n";
	} else {
		print '-> Creation of a new instance with name '.$newinstance." canceled (test mode)\n";
	}
} else {
	print '-> Failed to create a new instance with name '.$newinstance."\n";
	print "\n";
	exit(-1);
}

// Reload contract to get all values up to date
$newobject = new SellYourSaasContract($dbmaster);
$result=$newobject->fetch('', '', $newinstance);

$newserver=$newobject->array_options['options_hostname_os'];
$newlogin=$newobject->array_options['options_username_os'];
$newpassword=$newobject->array_options['options_password_os'];
$newserverbase=$newobject->array_options['options_hostname_db'];
$newloginbase=$newobject->array_options['options_username_db'];
$newpasswordbase=$newobject->array_options['options_password_db'];
$newdatabasedb=$newobject->array_options['options_database_db'];


if ($result <= 0 || empty($newlogin) || empty($newdatabasedb)) {
	print "Error: Failed to find target instance '".$newinstance."'";
	if ($mode == 'test') {
		print " (it should have been created by this script but, in test mode, the instance can't be created).\n";
	}
	print "\n";
	exit(-1);
}

// Set the custom url on new object with the one of the old one
if (! empty($oldobject->array_options['options_custom_url'])) {
	print "Update new instance to set the custom url to ".$oldobject->array_options['options_custom_url']."\n";
	$newobject->array_options['options_custom_url'] = $oldobject->array_options['options_custom_url'];
	if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
		$newobject->update($user, 1);
	}
}

// Set the date of end of period with same value than the source
$dateendperiod = 0;
$oldpricesperproduct = array();
foreach ($oldobject->lines as $line) {
	$oldpricesperproduct[$line->fk_product] = array('price_ht' => $line->price_ht, 'discount' => $line->remise_percent, 'qty' => $line->qty);
	if ($line->date_end && (empty($dateendperiod) || $line->date_end < $dateendperiod)) {
		$dateendperiod = $line->date_end;
	}
}
print "Lowest date of end of validity of services of old contract is ".dol_print_date($dateendperiod, 'standard').".\n";
if ($dateendperiod > 0) {
	$sql = 'UPDATE '.MAIN_DB_PREFIX."contratdet set date_fin_validite = '".$db->idate($dateendperiod)."'";
	$sql .= " WHERE fk_contrat = ".((int) $newobject->id);
	print $sql."\n";
	if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
		$resql = $db->query($sql);
		if (!$resql) {
			print 'Failed to set lowest date of end of validity'."\n";
			exit(-1);
		}
	}
}
print "Set end date of trial on new contract to the same value than the old contract.\n";
$sql = 'UPDATE '.MAIN_DB_PREFIX."contrat_extrafields set date_endfreeperiod = '".$db->idate($oldobject->array_options['options_date_endfreeperiod'])."'";
$sql .= " WHERE fk_object = ".((int) $newobject->id);
print $sql."\n";
if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
	$resql = $db->query($sql);
	if (!$resql) {
		print 'Failed to set end date of trial period'."\n";
		exit(-1);
	}
}

if (empty($forceproductref)) {
	print "Update price, discount and qty of the new contract lines to match the one on the source.\n";

	foreach ($oldpricesperproduct as $productid => $pricesperproduct) {
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX."contratdet";
		$sql .= " WHERE fk_contrat = ".((int) $newobject->id);
		$sql .= " AND fk_product = ".((int) $productid);
		print $sql."\n";
		$resqlselect = $db->query($sql);
		$objselect = $db->fetch_object($resqlselect);
		if ($objselect && $objselect->rowid > 0) {
			$contractline = new ContratLigne($db);
			$contractline->fetch($objselect->rowid);

			$contractline->qty = $pricesperproduct['qty'];
			$contractline->remise_percent = $pricesperproduct['discount'];
			$contractline->subprice = $pricesperproduct['price_ht'];
			$contractline->price_ht = $pricesperproduct['price_ht'];	// deprecated

			print "We update line ".$objselect->rowid." with price_ht = ".$pricesperproduct['price_ht']." discount = ".$pricesperproduct['discount']."\n";
			if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
				$result = $contractline->update($user);
				if ($result < 0) {
					print 'Failed to set same prices, discount and qty than original contract'."\n";
					exit(-1);
				}
			}
		}
	}
} else {
	print "A new product ref was forced, so we do not align prices on old contract.\n";
}


$newsftpconnectstring=$newlogin.'@'.$newserver.':' . getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'/'.$newlogin.'/'.preg_replace('/_([a-zA-Z0-9]+)$/', '', $newdatabasedb);

$createthirdandinstance=1;


// Now we will sync files from source to target in 2 steps

$tmptargetdir='/tmp/'.$newlogin.'/'.$newdatabasedb;
$countdeleted = 0;
dol_delete_dir_recursive($tmptargetdir, 0, 0, 0, $countdeleted, 0, 1);
dol_mkdir($tmptargetdir);


print '--- Synchro of files '.$oldsftpconnectstring.' to '.$tmptargetdir."\n";
print 'SFTP connect string : '.$oldsftpconnectstring."\n";
//print 'SFTP old password '.$oldospass."\n";


// First we get the files of the source to move
$command="rsync";
$param=array();
//if (! in_array($mode, array('confirm', 'confirmredirect', 'confirmmaintenance'))) $param[]="-n";
//$param[]="-a";
if (! in_array($mode, array('diff','diffadd','diffchange'))) {
	$param[]="-rlt";
} else {
	$param[]="-rlD";
	$param[]="--modify-window=1000000000";
	$param[]="--delete -n";
}
//$param[]="-v";
if (empty($createthirdandinstance)) {
	$param[]="-u";
}		// If we have just created instance, we overwrite file during rsync
$param[]="--exclude .buildpath";
$param[]="--exclude .git";
$param[]="--exclude .gitignore";
$param[]="--exclude .settings";
$param[]="--exclude .project";
$param[]="--exclude *.pdf_preview.png";
$param[]="--exclude htdocs/conf/conf.php";
$param[]="--exclude glpi_config/config_db.php";
$param[]="--exclude htdocs/inc/downstream.php";
$param[]="-e 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no'";

$param[]=$oldosuser.'@'.$oldoshost.":".$sourcedir.'/*';
$param[]=$tmptargetdir;

//var_dump($param);
$fullcommand=$command." ".join(" ", $param);
$output=array();
$return_var=0;

if (empty($nointeractive)) {
	print "Press ENTER to continue by running the rsync command to get files of old instance...";
	$input = trim(fgets(STDIN));
}

print $fullcommand."\n";

$outputfile = $conf->admin->dir_temp.'/out.tmp';
$resultarray = $utils->executeCLI($fullcommand, $outputfile, 0);

$return_var = $resultarray['result'];
$content_grabbed = $resultarray['output'];

if ($return_var) {
	print "-> Error during rsync from source instance to ".$tmptargetdir."\n";
	print $content_grabbed;
	exit(-1);
} else {
	print "-> Files were sync from source instance to ".$tmptargetdir."\n";
}

// Output result
print $content_grabbed."\n";


// Now we copy files on the target directory
$sourcedir = $tmptargetdir;
$targetdir = getDolGlobalString('DOLICLOUD_INSTANCES_PATH') . '/'.$newlogin.'/'.$newdatabasedb;

print '--- Synchro of files '.$sourcedir.' to '.$newsftpconnectstring."\n";
print 'SFTP connect string : '.$newsftpconnectstring."\n";
//print 'SFTP new password '.$newpassword."\n";

$command="rsync";
$param=array();
if (! in_array($mode, array('confirm', 'confirmredirect', 'confirmmaintenance'))) {
	$param[]="-n";
}
//$param[]="-a";
if (! in_array($mode, array('diff','diffadd','diffchange'))) {
	$param[]="-rlt";
} else {
	$param[]="-rlD";
	$param[]="--modify-window=1000000000";
	$param[]="--delete -n";
}
//$param[]="-v";
if (empty($createthirdandinstance)) {
	$param[]="-u";
}		// If we have just created instance, we overwrite file during rsync
$param[]="--exclude .buildpath";
$param[]="--exclude .git";
$param[]="--exclude .gitignore";
$param[]="--exclude .settings";
$param[]="--exclude .project";
$param[]="--exclude htdocs/conf/conf.php";
$param[]="--exclude glpi_config/config_db.php";
$param[]="--exclude htdocs/inc/downstream.php";
$param[]="-e 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no'";

$param[]=$sourcedir.'/';
$param[]=$newlogin.'@'.$newserver.":".$targetdir;

//var_dump($param);
$fullcommand=$command." ".join(" ", $param);
$output=array();
$return_var=0;

if (empty($nointeractive)) {
	print "Press ENTER to continue by running the rsync command to push files on remote target host...\n";
	$input = trim(fgets(STDIN));
}

print $fullcommand."\n";

$outputfile = $conf->admin->dir_temp.'/out.tmp';
$resultarray = $utils->executeCLI($fullcommand, $outputfile, 0);

$return_var = $resultarray['result'];
$content_grabbed = $resultarray['output'];

//exec($fullcommand, $output, $return_var);
if ($return_var) {
	print "-> Error during rsync from local dir ".$sourcedir." to target instance\n";
	print $content_grabbed;
	exit(-1);
} else {
	print "-> Files were sync from local dir ".$sourcedir." to target instance\n";
}
print "\n";

// Output result
print $content_grabbed."\n";


// Now we copy database from source to target

print '--- Dump database '.$olddbname.' into '.$tmptargetdir.'/mysqldump_'.$olddbname.'_'.dol_print_date(dol_now('gmt'), "%d", 'gmt').".sql\n";


// First we backup the source database
$command="mysqldump";
$param=array();
$param[]=$olddbname;
$param[]="-h";
$param[]=$olddbhost;
$param[]="-u";
$param[]=$olddbuser;
$param[]='-p"'.str_replace(array('"','`'), array('\"','\`'), $olddbpass).'"';
$param[]="--compress";
$param[]="-l";
$param[]="--single-transaction";
$param[]="-K";
$param[]="--tables";
$param[]="--no-tablespaces";
$param[]="-c";
$param[]="-e";
$param[]="--hex-blob";
$param[]="--default-character-set=utf8";

$fullcommand = $command." ".join(" ", $param);
$fullcommandredirectionfile = $tmptargetdir.'/mysqldump_'.$olddbname.'_'.dol_print_date(dol_now('gmt'), "%d", 'gmt').'.sql';
$output = array();
$return_varmysql = 0;
print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' '.$fullcommand." > ".$fullcommandredirectionfile."\n";

$outputfile = $conf->admin->dir_temp.'/out.tmp';
$resultarray = $utils->executeCLI($fullcommand, $outputfile, 0, $fullcommandredirectionfile);

$return_varmysql = $resultarray['result'];
$content_grabbed = $resultarray['output'];

print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' mysqldump done (return='.$return_varmysql.')'."\n";

// Output result
print $content_grabbed."\n";

if ($return_var) {
	print "-> Error during mysql dump of instance ".$oldobject->ref_customer."\n";
	exit(-1);
}


// We load the backup on target database
print '--- Load database '.$newdatabasedb.' from '.$tmptargetdir.'/mysqldump_'.$olddbname.'_'.dol_print_date(dol_now('gmt'), "%d", 'gmt').".sql\n";
//print "If the mysql fails, try to run mysql -u".$newloginbase." -p".$newpasswordbase." -D ".$newobject->database_db."\n";

$fullcommanddropa='echo "drop table llx_accounting_account;" | mysql -A -h '.$newserverbase.' -u '.$newloginbase.' -p'.$newpasswordbase.' -D '.$newdatabasedb;
$output=array();
$return_var=0;
print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' Drop table to prevent load error with '.$fullcommanddropa."\n";
if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
	$outputfile = $conf->admin->dir_temp.'/out.tmp';
	$resultarray = $utils->executeCLI($fullcommanddropa, $outputfile, 0, null, 1);

	$return_var = $resultarray['result'];
	$content_grabbed = $resultarray['output'];

	print $content_grabbed."\n";
	// If table already not exist, return_var is 1
	// If technical error, return_var is also 1, so we disable this test
	/*if ($return_var) {
		print "Error on droping table into the new instance\n";
		exit(-2);
	}*/
}

$fullcommanddropb='echo "drop table llx_accounting_system;" | mysql -A -h '.$newserverbase.' -u '.$newloginbase.' -p'.$newpasswordbase.' -D '.$newdatabasedb;
$output=array();
$return_var=0;
print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' Drop table to prevent load error with '.$fullcommanddropb."\n";
if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
	$outputfile = $conf->admin->dir_temp.'/out.tmp';
	$resultarray = $utils->executeCLI($fullcommanddropb, $outputfile, 0, null, 1);

	$return_var = $resultarray['result'];
	$content_grabbed = $resultarray['output'];

	print $content_grabbed."\n";
	// If table already not exist, return_var is 1
	// If technical error, return_var is also 1, so we disable this test
	/*if ($return_var) {
		print "Error on droping table into the new instance\n";
		exit(-2);
	}*/
}

$fullcommand="cat ".$tmptargetdir."/mysqldump_".$olddbname.'_'.dol_print_date(dol_now('gmt'), "%d", 'gmt').".sql | mysql -A -h ".$newserverbase." -u ".$newloginbase." -p".$newpasswordbase." -D ".$newdatabasedb;
print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt')." Load dump with ".$fullcommand."\n";
if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
	$output=array();
	$return_var=0;
	print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' '.$fullcommand."\n";

	$outputfile = $conf->admin->dir_temp.'/out.tmp';
	$resultarray = $utils->executeCLI($fullcommand, $outputfile, 0, null, 1);

	$return_var = $resultarray['result'];
	$content_grabbed = $resultarray['output'];

	print $content_grabbed."\n";
}


// Prepare SQL commands to execute after the load
$sqla = 'UPDATE '.MAIN_DB_PREFIX."facture_rec SET titre='".$dbmaster->escape('Template invoice for '.$newobject->ref.' '.$newobject->ref_customer)."'";
$sqla .= ' WHERE rowid = (SELECT fk_target FROM '.MAIN_DB_PREFIX.'element_element';
$sqla .= ' WHERE fk_source = '.((int) $oldobject->id)." AND sourcetype = 'contrat' AND targettype = 'facturerec')";

$sqlb = 'UPDATE '.MAIN_DB_PREFIX.'element_element SET fk_source = '.((int) $newobject->id);
$sqlb.= ' WHERE fk_source = '.((int) $oldobject->id)." AND sourcetype = 'contrat' AND (targettype = 'facturerec' OR targettype = 'facture')";

$sqlc = 'UPDATE '.MAIN_DB_PREFIX.'element_element SET fk_target = '.((int) $newobject->id);
$sqlc.= ' WHERE fk_target = '.((int) $oldobject->id)." AND targettype = 'contrat' AND (sourcetype = 'facturerec' OR sourcetype = 'facture')";

$sqld = 'UPDATE '.MAIN_DB_PREFIX."contrat_extrafields SET suspendmaintenance_message = '".$dbmaster->escape('https://'.$newobject->ref_customer)."'";
$sqld.= ' WHERE fk_object = '.((int) $oldobject->id);

if ($return_var) {
	print "-> Error during mysql load of instance ".$newobject->ref_customer."\n";
	print "FIX LOAD OF DUMP THEN RUN THIS MANUALLY:\n";
	print $sqla."\n";
	print $sqlb."\n";
	print $sqlc."\n";
	if ($mode == 'confirmredirect') {
		print $sqld."\n";
	}
	exit(-1);
}


print "\n";

if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
	print '-> Dump loaded into database '.$newdatabasedb.'. You can test instance on URL https://'.$newobject->ref_customer."\n";
	print "Finished.\n";
} else {
	print '-> Dump NOT loaded (test mode) into database '.$newdatabasedb.'. You can test instance on URL https://'.$newobject->ref_customer."\n";
}

print "\n";

print "Move recurring invoice from old to new instance:\n";
print $sqla."\n";
if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
	$resql = $dbmaster->query($sqla);
	if (!$resql) {
		print 'ERROR '.$dbmaster->lasterror();
	}
}

print $sqlb."\n";
if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
	$resql = $dbmaster->query($sqlb);
	if (!$resql) {
		print 'ERROR '.$dbmaster->lasterror();
	}
}

print $sqlc."\n";
if ($mode == 'confirm' || $mode == 'confirmredirect' || $mode == 'confirmmaintenance') {
	$resql = $dbmaster->query($sqlc);
	if (!$resql) {
		print 'ERROR '.$dbmaster->lasterror();
	}
}

print "Note: To revert the move of the recurring invoice, you can do:\n";

$sql = 'UPDATE '.MAIN_DB_PREFIX.'element_element SET fk_target = '.((int) $oldobject->id);
$sql.= ' WHERE fk_target = '.((int) $newobject->id)." AND targettype = 'contrat' AND (sourcetype = 'facturerec' OR sourcetype = 'facture')";
print $sql."\n";

$sql = 'UPDATE '.MAIN_DB_PREFIX.'element_element SET fk_source = '.((int) $oldobject->id);
$sql.= ' WHERE fk_source = '.((int) $newobject->id)." AND sourcetype = 'contrat' AND (targettype = 'facturerec' OR targettype = 'facture')";
print $sql."\n";

$sql = 'UPDATE '.MAIN_DB_PREFIX."facture_rec SET titre='".$dbmaster->escape('Template invoice for '.$oldobject->ref.' '.$oldobject->ref_customer)."'";
$sql.= ' WHERE rowid = (SELECT fk_target FROM '.MAIN_DB_PREFIX.'element_element';
$sql.= ' WHERE fk_source = '.((int) $oldobject->id)." AND sourcetype = 'contrat' AND targettype = 'facturerec'";
print $sql."\n";

print "\n";


$dnschangedone = 0;
if ($mode != 'confirmredirect' && $mode != 'confirmmaintenance') {
	print "DON'T FORGET TO REDIRECT INSTANCE ON OLD SYSTEM BY SETTING THE MAINTENANCE MODE WITH THE MESSAGE\n";
	print "https://".$newobject->ref_customer."\n";
	print "\n";
} else {
	// Switch old instance in redirect mode
	if ($mode == 'confirmredirect') {
		dol_include_once('sellyoursaas/class/sellyoursaasutils.class.php');
		$sellyoursaasutils = new SellYourSaasUtils($db);

		$suspendmessage = 'https://'.$newinstance;
		$newip = $newobject->array_options['options_deployment_host'];
		$comment = 'Move instance keeping a redirect to '.$suspendmessage.', we also set the new IP '.$newip.' into the old DNS file.';
		print '--- Switch old instance in redirect maintenance mode by calling remote action suspendredirect on the old server (set redirect to '.$suspendmessage.', and set the new ip to '.$newip." in DNS)\n";

		$result = $sellyoursaasutils->sellyoursaasRemoteAction('suspendredirect', $oldobject, 'admin', '', '', '0', $comment, 300, $newip);
		if ($result <= 0) {
			print "Error calling sellyoursaasRemoteAction: ".$sellyoursaasutils->error."\n";
			print "Try to call the service manually then update the instance to set the redirect to ".$suspendmessage."\n";
			print "\n";
			exit(-1);
		} else {
			$dnschangedone = 1;
		}

		$oldobject->array_options['options_suspendmaintenance_message'] = $suspendmessage;
		$result = $oldobject->update($user);
		if ($result < 0) {
			print "Error updating contract with redirect url: ".$oldobject->error."\n";
			print "\n";
			exit(-1);
		}
	}
}

if (!$dnschangedone) {
	print "NOTE: TO GET A REDIRECT WORKING AT THE DNS LEVEL, YOU CAN FIX THE DNS FILE /etc/bind/".$oldwilddomain.".hosts ON OLD SERVER TO SET THE LINE:\n";
	print $oldshortname." A ".$newobject->array_options['options_deployment_host']."\n";
	print "THEN RELOAD DNS WITH rndc reload ".$oldwilddomain."\n";
}
print "\n";
print "Finished.\n";
print "\n";

exit($return_var + $return_varmysql);
