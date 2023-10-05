#!/usr/bin/php
<?php
/* Copyright (C) 2012-2019 Laurent Destailleur	<eldy@users.sourceforge.net>
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
 * FEATURE
 *
 * Make a backup of files (rsync) or database (mysqdump) of a deployed instance.
 * There is no report/tracking done into any database. This must be done by a parent script.
 * This script is run from the deployment servers.
 *
 * Note:
 * ssh keys must be authorized to have testrsync and confirmrsync working.
 * remote access to database must be granted for option 'testdatabase' or 'confirmdatabase'.
 */

if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');					// Do not create database handler $db
if (!defined('NOSESSION')) define('NOSESSION', '1');
if (!defined('NOREQUIREVIRTUALURL')) define('NOREQUIREVIRTUALURL', '1');

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

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
$RSYNCDELETE=0;
$NOTRANS=0;
$QUICK=0;
$NOSTATS=0;
$FORCERSYNC=0;
$FORCEDUMP=0;

$instance=isset($argv[1])?$argv[1]:'';
$dirroot=isset($argv[2])?$argv[2]:'';
$mode=isset($argv[3])?$argv[3]:'';

$keystocheck = array(4, 5, 6, 7, 8);
foreach ($keystocheck as $keytocheck) {
	if (isset($argv[$keytocheck])) {
		if ($argv[$keytocheck] == '--delete') {
			$RSYNCDELETE=1;
		} elseif ($argv[$keytocheck] == '--notransaction') {
			$NOTRANS=1;
		} elseif ($argv[$keytocheck] == '--quick') {
			$QUICK=1;
		} elseif ($argv[$keytocheck] == '--nostats') {
			$NOSTATS=1;
		} elseif ($argv[$keytocheck] == '--forcersync') {
			$FORCERSYNC=1;
		} elseif ($argv[$keytocheck] == '--forcedump') {
			$FORCEDUMP=1;
		}
	}
}


@set_time_limit(0);							// No timeout for this script
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);		// Set this define to 0 if you want to lock your script when dolibarr setup is "locked to admin user only".

// Read /etc/sellyoursaas.conf file just for $dolibarrdir
$dolibarrdir='';
$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
		if ($tmpline[0] == 'dolibarrdir') {
			$dolibarrdir = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $tmpline[1]);
		}
	}
}
if (empty($dolibarrdir)) {
	print "Failed to find 'dolibarrdir' entry into /etc/sellyoursaas.conf file\n";
	exit(-1);
}

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
if (! $res && file_exists(__DIR__."/../../master.inc.php")) $res=@include __DIR__."/../../../master.inc.php";
if (! $res && file_exists(__DIR__."/../../../master.inc.php")) $res=@include __DIR__."/../../../master.inc.php";
if (! $res && file_exists($dolibarrdir."/htdocs/master.inc.php")) $res=@include $dolibarrdir."/htdocs/master.inc.php";
if (! $res) {
	print ("Include of master fails");
	exit(-1);
}

include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once("/sellyoursaas/core/lib/dolicloud.lib.php");

// Read /etc/sellyoursaas.conf file
$databasehost='localhost';
$databaseport='3306';
$database='';
$databaseuser='sellyoursaas';
$databasepass='';
$dolibarrdir='';
$usecompressformatforarchive='gzip';
$backupignoretables='';
$backupcompressionalgorithms='';	// can be '' or 'zstd'
$backuprsyncdayfrequency=1;	// Default value is an rsync every 1 day.
$backupdumpdayfrequency=1;	// Default value is a sql dump every 1 day.
$master_unique_id = '';
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
		if ($tmpline[0] == 'backupignoretables') {
			$backupignoretables = $tmpline[1];
		}
		if ($tmpline[0] == 'backupcompressionalgorithms') {
			$backupcompressionalgorithms = preg_replace('/[^a-z]/', '', $tmpline[1]);
		}
		if ($tmpline[0] == 'backuprsyncdayfrequency') {
			$backuprsyncdayfrequency = $tmpline[1];
		}
		if ($tmpline[0] == 'backupdumpdayfrequency') {
			$backupdumpdayfrequency = $tmpline[1];
		}
		if ($tmpline[0] == 'master_unique_id') {
			$master_unique_id = dol_string_nospecial($tmpline[1]);
		}
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit(-1);
}

if (empty($dolibarrdir)) {
	print "Failed to find 'dolibarrdir' entry into /etc/sellyoursaas.conf file\n";
	exit(-1);
}
if (empty($backuprsyncdayfrequency)) {
	print "Bad value for 'backuprsyncdayfrequency'. Must contains the number of days between each rsync.\n";
	exit(-1);
}
if (empty($backupdumpdayfrequency)) {
	print "Bad value for 'backupdumpdayfrequency'. Must contains the number of days between each sql dump.\n";
	exit(-1);
}

$return_varother = 0;
$return_var = 0;
$return_varmysql = 0;
$return_outputmysql = 0;


/*
 *	Main
 */

if (0 == posix_getuid()) {
	echo "Script must not be ran with root (but with the 'admin' sellyoursaas account).\n";
	exit(-1);
}
if (empty($instanceserver)) {
	echo "This server seems to not be a server for the deployment of instances (this should be defined in sellyoursaas.conf file).\n";
	print "Press ENTER to continue or CTL+C to cancel...";
	$input = trim(fgets(STDIN));
}

$dbmaster=getDoliDBInstance('mysqli', $databasehost, $databaseuser, $databasepass, $database, $databaseport);
if ($dbmaster->error) {
	dol_print_error($dbmaster, "host=".$databasehost.", port=".$databaseport.", user=".$databaseuser.", databasename=".$database.", ".$dbmaster->error);
	exit(-1);
}
if ($dbmaster) {
	$conf->setValues($dbmaster);
}
if (empty($db)) {
	$db = $dbmaster;
}

if (empty($dirroot) || empty($instance) || empty($mode)) {
	print "This script must be ran as 'admin' user.\n";
	print "Usage:   $script_file  instance    backup_dir  (testrsync|testdatabase|test|confirmrsync|confirmdatabase|confirm) [--delete] [--notransaction] [--quick] [--forcersync] [--forcedump] [--nostats]\n";
	print "Example: $script_file  myinstance  ".$conf->global->DOLICLOUD_BACKUP_PATH."  testrsync\n";
	print "Note:    ssh keys must be authorized to have rsync (test and confirm) working\n";
	print "         remote access to database must be granted for testdatabase or confirmdatabase.\n";
	print "         the parameter --delete run the rsync with the --delete option\n";
	print "         the parameter --notransaction run the mysqldump without the --single-transaction\n";
	print "         the parameter --quick run the mysqldump with the --quick option (recommended)\n";
	print "         the parameter --forcersync to force rsync even if date is too young\n";
	print "         the parameter --forcedump to force sql dump even if date is too young\n";
	print "         the parameter --nostats disable send of statistics to the external supervision platform\n";
	print "Return code: 0 if success, <>0 if error\n";
	exit(-1);
}

// Forge complete name of instance
if (! empty($instance) && ! preg_match('/\./', $instance) && ! preg_match('/\.home\.lan$/', $instance)) {
	if (empty(getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION'))) {
		$tmparray = explode(',', getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES'));
	} else {
		dol_include_once('sellyoursaas/class/deploymentserver.class.php');
		$staticdeploymentserver = new Deploymentserver($db);
		$tmparray = $staticdeploymentserver->fetchAllDomains();
	}
	$tmpstring = preg_replace('/:.*$/', '', $tmparray[0]);
	$instance = $instance.".".$tmpstring;   // Automatically concat first domain name
}


$idofinstancefound = 0;

$sql = "SELECT c.rowid, c.ref, c.ref_customer as instance, c.statut";
$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object";
$sql.= "  WHERE c.entity IN (".getEntity('contract').")";
$sql.= " AND c.ref_customer = '".$dbmaster->escape($instance)."'";
$sql.= " AND ce.deployment_status = 'done'";

$resql = $dbmaster->query($sql);
if (! $resql) {
	dol_print_error($resql);
	exit(-2);
}
$num_rows = $dbmaster->num_rows($resql);
if ($num_rows > 1) {
	print 'Error: several instance '.$instance.' found.'."\n";
	exit(-2);
} else {
	$obj = $dbmaster->fetch_object($resql);
	if ($obj) {
		$idofinstancefound = $obj->rowid;
	}
}

dol_include_once('/sellyoursaas/class/sellyoursaascontract.class.php');

$object = new SellYourSaasContract($dbmaster);

if (empty($conf->file->unique_instance_id)) {
	$conf->file->unique_instance_id = empty($master_unique_id) ? '' : $master_unique_id;
}

$result=0;
if ($idofinstancefound) {
	$result = $object->fetch($idofinstancefound);
}


if ($result <= 0) {
	print "Error: instance ".$instance." not found.\n";
	exit(-2);
}

$object->instance        = $object->ref_customer;
$object->username_os     = $object->array_options['options_username_os'];
$object->password_os     = $object->array_options['options_password_os'];
$object->hostname_db     = $object->array_options['options_hostname_db'];
$object->port_db         = $object->array_options['options_port_db'];
$object->username_db     = $object->array_options['options_username_db'];
$object->password_db     = $object->array_options['options_password_db'];
$object->database_db     = $object->array_options['options_database_db'];
$object->deployment_host = $object->array_options['options_deployment_host'];
$object->latestbackup_date_ok = $object->array_options['options_latestbackup_date_ok'];
$object->backup_frequency = $object->array_options['options_backup_frequency'];

if (empty($object->instance) && empty($object->username_os) && empty($object->password_os) && empty($object->database_db)) {
	print "Error: properties for instance ".$instance." was not registered into database.\n";
	exit(-3);
}
if (! is_dir($dirroot)) {
	print "Error: Target directory ".$dirroot." to store backup does not exist.\n";
	exit(-4);
}

$dirdb = preg_replace('/_([a-zA-Z0-9]+)/', '', $object->database_db);
$login = $object->username_os;

$sourcedir=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$login.'/'.$dirdb;
$server=($object->deployment_host ? $object->deployment_host : $object->array_options['options_hostname_os']);

if (empty($login) || empty($dirdb)) {
	print "Error: properties for instance ".$instance." are not registered completely (missing at least login or database name).\n";
	exit(-5);
}

//$fromserver = (in_array($server, array('127.0.0.1','localhost')) ? $server : $login.'@'.$server.":");
$fromserver = $login.'@'.$server.":";
print 'Backup instance '.$instance.' from '.$fromserver.' to '.$dirroot.'/'.$login." (mode=".$mode.")\n";

//$listofdir=array($dirroot.'/'.$login, $dirroot.'/'.$login.'/documents', $dirroot.'/'.$login.'/system', $dirroot.'/'.$login.'/htdocs', $dirroot.'/'.$login.'/scripts');
if ($mode == 'confirm' || $mode == 'confirmrsync' || $mode == 'confirmdatabase') {
	$listofdir=array();
	$listofdir[]=$dirroot.'/'.$login;
	/*if ($mode == 'confirm' || $mode == 'confirmdatabase')
	{
		$listofdir[]=$dirroot.'/'.$login.'/documents';
		$listofdir[]=$dirroot.'/'.$login.'/documents/admin';
		$listofdir[]=$dirroot.'/'.$login.'/documents/admin/backup';
	}*/
	foreach ($listofdir as $dirtocreate) {
		if (! is_dir($dirtocreate)) {
			$res=@mkdir($dirtocreate);
			if (! $res) {
				print 'Failed to create dir '.$dirtocreate."\n";
				$mode = 'disabled';
				$return_varother = 1;
			}
		}
	}
}

// Backup files
if ($mode == 'testrsync' || $mode == 'test' || $mode == 'confirmrsync' || $mode == 'confirm') {
	$result = dol_mkdir($dirroot.'/'.$login);	// $result will be 0 if it already exists
	if ($result < 0) {
		print "ERROR failed to create target dir ".$dirroot.'/'.$login."\n";
		exit(-1);
	}

	// Get frequency of rsync for the instance
	/*if (!empty($object->backupfrequency)) {
		// use $object->backupfrequency for $backuprsyncdayfrequency
	}*/

	// Test last date of rsync
	$txtfile = $dirroot.'/'.$login.'/last_rsync_'.$instance.'.ok.txt';
	$txtfiledate = dol_filemtime($txtfile);
	$datetriggerrsync = dol_now('gmt') - ($backuprsyncdayfrequency * 24 * 3600) + (12 * 3600);
	print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' Test date of file '.$txtfile."\n";
	if (!dol_is_file($txtfile) || $txtfiledate <= $datetriggerrsync || $FORCERSYNC) {	// We add 3600 as security for date comparison
		// Instance is qualified for rsync backup
		$command="rsync";
		$param=array();
		if ($mode != 'confirm' && $mode != 'confirmrsync') $param[]="-n";
		//$param[]="-a";
		$param[]="-4";
		$param[]="--prune-empty-dirs";
		$param[]="-rlt";
		//$param[]="-vv";
		$param[]="-v";
		//$param[]="--noatime";				// launching server must be lower then 20.10
		//$param[]="--open-noatime";		// version must be 20.10 on both side
		//$param[]="--exclude-from --exclude-from=$scriptdir/backup_backups.exclude";

		//$param[]="--cvs-exclude";
		$param[]="--exclude .git";
		$param[]="--exclude .gitignore";

		$param[]="--exclude .buildpath";
		$param[]="--exclude .settings";
		$param[]="--exclude .project";
		$param[]="--exclude '*.com*SSL'";
		$param[]="--exclude '*.log'";
		$param[]="--exclude '*.pdf_preview*.png'";
		$param[]="--exclude '(PROV*)'";
		//$param[]="--exclude '*/doc/images/'";	    // To keep files into htdocs/core/module/xxx/doc/ dir
		//$param[]="--exclude '*/doc/install/'";	// To keep files into htdocs/core/module/xxx/doc/ dir
		//$param[]="--exclude '*/doc/user/'";		// To keep files into htdocs/core/module/xxx/doc/ dir
		$param[]="--exclude '*/thumbs/'";
		$param[]="--exclude '*/temp/'";
		// Excludes for Dolibarr
		$param[]="--exclude '*/documents/admin/backup/'";		// Exclude backup of database
		$param[]="--exclude '*/documents/admin/documents/'";	// Exclude backup of documents directory
		$param[]="--exclude '*/documents/*/admin/backup/'";		// Exclude backup of database
		$param[]="--exclude '*/documents/*/admin/documents/'";	// Exclude backup of documents directory
		$param[]="--exclude '*/documents/installmodules.lock'";	// Exclude backup of installmodules.lock
		$param[]="--exclude '*/htdocs/install/filelist-*.xml*'";
		$param[]="--exclude '*/htdocs/includes/tecnickcom/tcpdf/font/ae_fonts_*'";
		$param[]="--exclude '*/htdocs/includes/tecnickcom/tcpdf/font/dejavu-fonts-ttf-*'";
		$param[]="--exclude '*/htdocs/includes/tecnickcom/tcpdf/font/freefont-*'";
		// Excludes for GLPI
		$param[]="--exclude '*/_cache/*'";
		$param[]="--exclude '*/_cron/*'";
		$param[]="--exclude '*/_dumps/*'";
		$param[]="--exclude '*/_graph/*'";
		$param[]="--exclude '*/_lock/*'";
		$param[]="--exclude '*/_log/*'";
		$param[]="--exclude '*/_rss/*'";
		$param[]="--exclude '*/_sessions/*'";
		$param[]="--exclude '*/_uploads/*'";
		$param[]="--exclude '*/_tmp/*'";
		$param[]="--exclude '*/_plugins/fusioninventory/xml/*'";
		// Excludes for other
		$param[]="--exclude '*/_source/*'";
		$param[]="--exclude '*/__MACOSX/*'";

		//$param[]="--backup --suffix=.old";
		if ($RSYNCDELETE) {
			$param[]=" --delete --delete-excluded";
		}
		$param[]="--stats";
		$param[]="-e 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -o PasswordAuthentication=no'";

		//var_dump($param);
		//$param[] = (in_array($server, array('127.0.0.1','localhost')) ? '' : $login.'@'.$server.":") . $sourcedir;
		$param[] = $login.'@'.$server.":" . $sourcedir;
		$param[] = $dirroot.'/'.$login;
		$fullcommand=$command." ".join(" ", $param);
		$output=array();
		$datebeforersync = dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt');
		print $datebeforersync.' '.$fullcommand."\n";
		exec($fullcommand, $output, $return_var);
		$dateafterrsync = dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt');
		print $dateafterrsync.' rsync done (return='.$return_var.')'."\n";

		// Output result
		foreach ($output as $outputline) {
			print $outputline."\n";
		}

		// Add file tag
		if ($mode == 'confirm' || $mode == 'confirmrsync') {
			$handle=fopen($dirroot.'/'.$login.'/last_rsync_'.$instance.'.txt', 'w');
			if ($handle) {
				fwrite($handle, 'File created after rsync of '.$instance.". datebeforersync=".$datebeforersync." dateafterrsync=".$dateafterrsync." return_var=".$return_var."\n");
				fwrite($handle, 'fullcommand = '.$fullcommand."\n");
				fclose($handle);
			} else {
				print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' Warning: Failed to create file last_rsync_'.$instance.'.txt'."\n";
			}
			if ($return_var == 0) {
				$handle=fopen($dirroot.'/'.$login.'/last_rsync_'.$instance.'.ok.txt', 'w');
				if ($handle) {
					fwrite($handle, 'File created after rsync of '.$instance.". datebeforersync=".$datebeforersync." dateafterrsync=".$dateafterrsync." return_var=".$return_var."\n");
					fwrite($handle, 'fullcommand = '.$fullcommand."\n");
					fclose($handle);
				} else {
					print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' Warning: Failed to create file last_rsync_'.$instance.'.ok.txt'."\n";
				}
			}
		}
	} else {
		print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' According to file '.$txtfile.', last rsync was done the '.dol_print_date($txtfiledate, 'standard', 'gmt') ." GMT so after the trigger date  ".dol_print_date($datetriggerrsync, 'standard', 'gmt')." GMT, so rsync is discarded.\n";
	}
}

// Backup database
if ($mode == 'testdatabase' || $mode == 'test' || $mode == 'confirmdatabase' || $mode == 'confirm') {
	include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	// Get frequency of sql dump for the instance
	/*if (!empty($object->backupfrequency)) {
		// use $object->backupfrequency for $backupdumpdayfrequency
	} */

	// Test last date of sql dump
	$txtfile = $dirroot.'/'.$login.'/last_mysqldump_'.$instance.'.ok.txt';
	$txtfiledate = dol_filemtime($txtfile);
	$datetriggerrsync = dol_now('gmt') - ($backupdumpdayfrequency * 24 * 3600) + (12 * 3600);
	print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' Test date of file '.$txtfile."\n";
	if (!dol_is_file($txtfile) || $txtfiledate <= ($datetriggerrsync + 3600) || $FORCEDUMP) {	// We add 3600 as security for date comparison
		// Instance is qualified for dump backup
		$serverdb = $server;
		if (filter_var($object->hostname_db, FILTER_VALIDATE_IP) !== false) {
			print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' hostname_db value is an IP, so we use it in priority instead of ip of deployment server'."\n";
			$serverdb = $object->hostname_db;
		}

		$command="mysqldump";
		$param=array();
		$param[]=$object->database_db;
		$param[]="-h";
		$param[]=$serverdb;
		$param[]="-P";
		$param[]=(! empty($object->port_db) ? $object->port_db : "3306");
		$param[]="-u";
		$param[]=$object->username_db;
		$param[]='-p"'.str_replace(array('"','`'), array('\"','\`'), $object->password_db).'"';
		if ($backupcompressionalgorithms) {
			$param[]="--compression-algorithms=".$backupcompressionalgorithms;
		} else {
			$param[]="--compress";
		}
		$param[]="-l";
		if (empty($NOTRANS)) {
			$param[]="--single-transaction";
		}
		$param[]="-K";
		$param[]="--tables";
		$param[]="--no-tablespaces";
		$param[]="-c";
		$param[]="-e";
		if (!empty($QUICK)) {
			$param[]="-q";
		}
		$param[]="--hex-blob";
		$param[]="--default-character-set=utf8";

		if ($backupignoretables) {
			$listofignoretables = dolExplodeIntoArray($backupignoretables, ',', ':');
			if (array_key_exists($object->instance, $listofignoretables)) {
				$listofignoretablesforinstance = explode('+', $listofignoretables[$object->instance]);
				foreach ($listofignoretablesforinstance as $key => $val) {
					$param[]='--ignore-table='.$object->database_db.'.'.$val;
				}
			}
			if (array_key_exists('all', $listofignoretables)) {
				$listofignoretablesforinstance = explode('+', $listofignoretables['all']);
				foreach ($listofignoretablesforinstance as $key => $val) {
					$param[]='--ignore-table='.$object->database_db.'.'.$val;
				}
			}
		}

		$prefixdumptemp = 'temp';

		$fullcommand=$command." ".join(" ", $param);
		if (command_exists("zstd") && "x$usecompressformatforarchive" == "xzstd") {
			if ($mode != 'confirm' && $mode != 'confirmdatabase') $fullcommand.=' 2>'.$dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.err | zstd -z -9 -q > /dev/null';
			else $fullcommand.=' 2>'.$dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.err | zstd -z -9 -q > '.$dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.sql.zst';
			// Delete file with same name and other extensions (if other option was enabled in past)
			dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.dol_print_date(dol_now('gmt'), '%d', 'gmt').'.sql.bz2');
			dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.dol_print_date(dol_now('gmt'), '%d', 'gmt').'.sql.gz');
			dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_ok.sql.bz2');
			dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_ok.sql.gz');
		} else {
			if ($mode != 'confirm' && $mode != 'confirmdatabase') $fullcommand.=' 2>'.$dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.err | gzip '.(empty($conf->global->SELLYOURSAAS_DUMP_DATABASE_GZIP_OPTIONS)?'':$conf->global->SELLYOURSAAS_DUMP_DATABASE_GZIP_OPTIONS).' > /dev/null';
			else $fullcommand.=' 2>'.$dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.err | gzip '.(empty($conf->global->SELLYOURSAAS_DUMP_DATABASE_GZIP_OPTIONS)?'':$conf->global->SELLYOURSAAS_DUMP_DATABASE_GZIP_OPTIONS).' > '.$dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.sql.gz';
			// Delete file with same name and other extensions (if other option was enabled in past)
			dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.dol_print_date(dol_now('gmt'), '%d', 'gmt').'.sql.bz2');
			dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.dol_print_date(dol_now('gmt'), '%d', 'gmt').'.sql.zst');
			dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.sql.bz2');
			dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.sql.zst');
			dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_ok.sql.bz2');
			dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_ok.sql.zst');
		}

		// Execute command
		$output=array();
		$return_outputmysql=0;
		$datebeforemysqldump = dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt');
		print $datebeforemysqldump.' '.$fullcommand."\n";
		exec($fullcommand, $output, $return_varmysql);
		$dateaftermysqldump = dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt');

		$outputerr = file_get_contents($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.err');
		print $outputerr;

		$return_outputmysql = (count(file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.err')) - 1);	// If there is more than 1 line in .err, this is an error in dump.
		if (empty($return_outputmysql)) {	// If no error detected with the number of lines, we try also to detect by searching ' Error ' into .err content
			$return_outputmysql = strpos($outputerr, ' Error ');
		}
		if (empty($return_outputmysql)) {	// If no error detected previously, we try also to detect by getting size file
			if ($mode == 'testdatabase' || $mode == 'test') {
				$return_outputmysql = 0;
			} else {
				if (command_exists("zstd") && "x$usecompressformatforarchive" == "xzstd") {
					$filesizeofsql = filesize($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.sql.zst');
				} else {
					$filesizeofsql = filesize($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.sql.gz');
				}
				if ($filesizeofsql < 100) {
					$return_outputmysql = 1;
				}
			}
		}

		if ($return_outputmysql > 0) {
			print $dateaftermysqldump.' mysqldump found string error in output err file or into dump filesize.'."\n";
		} else {
			$return_outputmysql = 0;

			// Delete temporary file once backup is done when file is empty
			dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.err');

			// Rename dump file with a constant name file
			if (command_exists("zstd") && "x$usecompressformatforarchive" == "xzstd") {
				dol_move($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.sql.zst', $dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_ok.sql.zst');
				// Delete file with same extensions but using old version name
				dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.dol_print_date(dol_now('gmt'), '%d', 'gmt').'.sql.zst');
			} else {
				dol_move($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.$prefixdumptemp.'.sql.gz', $dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_ok.sql.gz');
				// Delete file with same extensions but using old version name
				dol_delete_file($dirroot.'/'.$login.'/mysqldump_'.$object->database_db.'_'.dol_print_date(dol_now('gmt'), '%d', 'gmt').'.sql.gz');
			}
		}

		print $dateaftermysqldump.' mysqldump done (return='.$return_varmysql.', error in output='.$return_outputmysql.')'."\n";

		// Output result
		foreach ($output as $outputline) {
			print $outputline."\n";
		}

		// Add file tag
		if ($mode == 'confirm' || $mode == 'confirmdatabase') {
			$handle=fopen($dirroot.'/'.$login.'/last_mysqldump_'.$instance.'.txt', 'w');
			if ($handle) {
				fwrite($handle, 'File created after mysqldump of '.$instance.". datebeforemysqldump=".$datebeforemysqldump." dateaftermysqldump=".$dateaftermysqldump." return_varmysql=".$return_varmysql."\n");
				fwrite($handle, 'fullcommand = '.preg_replace('/\s\-p"[^"]+"/', ' -phidden', $fullcommand)."\n");
				fclose($handle);
			} else {
				print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' Warning: Failed to create file last_mysqldump_'.$instance.'.txt'."\n";
			}
			if ($return_varmysql == 0) {
				$handle=fopen($dirroot.'/'.$login.'/last_mysqldump_'.$instance.'.ok.txt', 'w');
				if ($handle) {
					fwrite($handle, 'File created after mysqldump of '.$instance.". datebeforemysqldump=".$datebeforemysqldump." dateaftermysqldump=".$dateaftermysqldump." return_varmysql=".$return_varmysql."\n");
					fwrite($handle, 'fullcommand = '.preg_replace('/\s\-p"[^"]+"/', ' -phidden', $fullcommand)."\n");
					fclose($handle);
				} else {
					print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' Warning: Failed to create file last_mysqldump_'.$instance.'.ok.txt'."\n";
				}
			}
		}
	} else {
		print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' According to file '.$txtfile.', last sql dump was done the '.dol_print_date($txtfiledate, 'standard', 'gmt') ." GMT so after the trigger date ".dol_print_date($datetriggerrsync, 'standard', 'gmt')." GMT, so sql dump is discarded.\n";
	}
}

$now=dol_now();

// Update database
if (empty($return_varother) && empty($return_var) && empty($return_varmysql) && empty($return_outputmysql)) {
	print "RESULT into backup process of rsync: ".$return_var."\n";
	print "RESULT into backup process of mysqldump: ".$return_varmysql." + ".$return_outputmysql."\n";

	if ($mode == 'confirm') {
		print 'Update date of full backup (rsync+dump) for instance '.$object->instance.' to '.$now."\n";

		// Update database
		$object->array_options['options_latestbackup_date'] = $now;	// date latest files and database rsync backup try
		$object->array_options['options_latestbackup_date_ok'] = $now;	// date latest files and database rsync backup try
		$object->array_options['options_latestbackup_status'] = 'OK';
		$object->array_options['options_latestbackup_message'] = dol_trunc('', 8000);

		$object->update($user, 1);

		// Send to DataDog (metric + event)
		if (!empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED) && empty($NOSTATS)) {
			try {
				print "Send result of backup ok to DataDog\n";
				dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

				$arrayconfig=array();
				if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY)) {
					$arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
				}

				$statsd = new DataDog\DogStatsd($arrayconfig);

				$arraytags=array('result'=>'ok');
				$statsd->increment('sellyoursaas.backup', 1, $arraytags);
			} catch (Exception $e) {
				print "Error when sending data to DataDog\n";
			}
		}
	}
} else {
	if (! empty($return_varother)) print "ERROR into backup process init: ".$return_varother."\n";
	if (! empty($return_var))      print "ERROR into backup process of rsync: ".$return_var."\n";
	if (! empty($return_varmysql) || ! empty($return_outputmysql)) print "ERROR into backup process of mysqldump: ".$return_varmysql." + ".$return_outputmysql."\n";

	if ($mode == 'confirm' || $mode == 'disabled') {
		// Update database
		$object->array_options['options_latestbackup_date'] = $now;	// date latest files and database rsync backup try
		$object->array_options['options_latestbackup_status'] = 'KO';
		$object->array_options['options_latestbackup_message'] = dol_trunc('', 8000);

		$object->update($user, 1);

		// Send to DataDog (metric + event)
		if (!empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED) && empty($NOSTATS)) {
			try {
				print "Send result of backup ko to DataDog\n";
				dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

				$arrayconfig=array();
				if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY)) {
					$arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
				}

				$statsd = new DataDog\DogStatsd($arrayconfig);

				$arraytags=array('result'=>'ko');
				$statsd->increment('sellyoursaas.backup', 1, $arraytags);
			} catch (Exception $e) {
				print "Error when sending data to DataDog\n";
			}
		}
	}

	exit(1);
}

exit(0);
