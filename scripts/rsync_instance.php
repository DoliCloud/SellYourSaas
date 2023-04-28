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
 * Update an instance on stratus5 server with new ref version.
 */

/**
 *      \file       sellyoursaas/scripts/rsync_instance.php
 *		\ingroup    sellyoursaas
 *      \brief      Script to upgrade an instant
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
	exit;
}

// Global variables
$version='1.0';
$error=0;

$dirroot=isset($argv[1])?$argv[1]:'';
$instance=isset($argv[2])?$argv[2]:'';
$mode=isset($argv[3])?$argv[3]:'';

// Include Dolibarr environment
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
if (! $res) die("Include of master fails");
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

dol_include_once("/sellyoursaas/core/lib/dolicloud.lib.php");
include_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';


// Read /etc/sellyoursaas.conf file
$databasehost='localhost';
$databaseport='3306';
$database='';
$databaseuser='sellyoursaas';
$databasepass='';
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
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit;
}



/*
 *	Main
 */

$dbmaster=getDoliDBInstance('mysqli', $databasehost, $databaseuser, $databasepass, $database, $databaseport);
if ($dbmaster->error) {
	dol_print_error($dbmaster, "host=".$databasehost.", port=".$databaseport.", user=".$databaseuser.", databasename=".$database.", ".$dbmaster->error);
	exit;
}
if ($dbmaster) {
	$conf->setValues($dbmaster);
}
if (empty($db)) $db=$dbmaster;

if (empty($dirroot) || empty($instance) || empty($mode)) {
	print "Update an instance on remote server with new ref version.\n";
	print "Usage: $script_file source_root_dir sellyoursaas_instance (test|confirm|confirmunlock|diff|diffadd|diffchange|testclean|confirmclean|confirmwithtestdir)\n";
	print "Return code: 0 if success, <>0 if error\n";
	exit(-1);
}

if (0 == posix_getuid() && empty($conf->global->SELLYOURSAAS_SCRIPT_BYPASS_ROOT_RESTRICTION)) {
	echo "Script must not be ran with root (but with the 'admin' sellyoursaas account).\n";
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

include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
$object = new Contrat($db);
$result=$object->fetch('', '', $instance);
$result=$object->fetch_thirdparty();

if ($result <= 0) {
	print "Error: instance ".$instance." not found or duplicate found. Are you on the correct SellYourSaas master server ?\n";
	exit(-2);
}

$object->instance = $object->ref_customer;
$object->username_os = $object->array_options['options_username_os'];
$object->password_os = $object->array_options['options_password_os'];
$object->username_db = $object->array_options['options_username_db'];
$object->password_db = $object->array_options['options_password_db'];
$object->database_db = $object->array_options['options_database_db'];


if (empty($object->instance) || empty($object->username_os) || empty($object->password_os) || empty($object->database_db)) {
	print "Error: Some properties for instance ".$instance." was not registered into database.\n";
	exit(-3);
}
if (! is_file($dirroot.'/README.md')) {
	print "Error: Source directory to synchronize must contains a README.md file (not found into ".$dirroot.")\n";
	exit(-4);
}

$dirdb = preg_replace('/_([a-zA-Z0-9]+)/', '', $object->database_db);
$login = $object->username_os;
$password = $object->password_os;

$targetdir = $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$login.'/'.$dirdb;
$server = $object->array_options['options_hostname_os'];
$server_port = (empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? 22 : $conf->global->SELLYOURSAAS_SSH_SERVER_PORT);

if (empty($login) || empty($dirdb)) {
	print "Error: properties for instance ".$instance." are not registered completely (missing at least login or database name).\n";
	exit(-5);
}

$sftpconnectstring=$object->username_os.'@'.$server.':'.$targetdir;

print 'Synchro of files '.$dirroot.' to '.$targetdir."\n";
print 'SFTP connect string : '.$sftpconnectstring."\n";
//print 'SFTP password '.$object->password_os."\n";

$command="rsync";
$param=array();
if (! in_array($mode, array('confirm','confirmunlock','confirmwithtestdir','confirmclean'))) $param[]="-n";
//$param[]="-a";
if (! in_array($mode, array('diff','diffadd','diffchange'))) $param[]="-rlt";
else { $param[]="-rlD"; $param[]="--modify-window=1000000000"; $param[]="--delete -n"; }
$param[]="-v";
//$param[]="--noatime";				// launching server must be lower then 20.10
//$param[]="--open-noatime";		// version must be 20.10 on both side
$param[]="--exclude .buildpath";
$param[]="--exclude .codeclimate.yml";
$param[]="--exclude .editorconfig";
$param[]="--exclude .git";
$param[]="--exclude .github";
$param[]="--exclude .gitignore";
$param[]="--exclude .gitmessage";
$param[]="--exclude .mailmap";
$param[]="--exclude .settings";
$param[]="--exclude .scrutinizer.yml";
$param[]="--exclude .stickler.yml";
$param[]="--exclude .project";
$param[]="--exclude .travis.yml";
$param[]="--exclude .tx";
$param[]="--exclude phpstan.neon";
$param[]="--exclude build/exe/";
//$param[]="--exclude doc/";	// To keep files into htdocs/core/module/xxx/doc dir
$param[]="--exclude dev/";
$param[]="--exclude documents/";
$param[]="--include htdocs/modulebuilder/template/test/";
if ($mode != 'confirmwithtestdir') {
	$param[]="--exclude test/";
}
$param[]="--exclude htdocs/conf/conf.php*";
$param[]="--exclude glpi_config/config_db.php*";
$param[]="--exclude htdocs/inc/downstream.php*";
$param[]="--exclude htdocs/custom";
if (! in_array($mode, array('diff','diffadd','diffchange'))) $param[]="--stats";
if (in_array($mode, array('testclean','confirmclean'))) $param[]="--delete";
$param[]="-e 'ssh -p ".$server_port." -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -o PasswordAuthentication=no'";

$param[]=$dirroot.'/';
$param[]=$login.'@'.$server.":".$targetdir;

//var_dump($param);
$fullcommand=$command." ".join(" ", $param);
$output=array();
$return_var=0;
print $fullcommand."\n";
exec($fullcommand, $output, $return_var);

// Output result
foreach ($output as $outputline) {
	print $outputline."\n";
}

// Remove install.lock and create upgrade.unlock file if mode confirmunlock
if ($mode == 'confirmunlock') {
	// SFTP connect
	if (! function_exists("ssh2_connect")) { dol_print_error('', 'ssh2_connect function does not exists'); exit(1); }

	$connection = ssh2_connect($server, $server_port);
	if ($connection) {
		//print $object->instance." ".$object->username_os." ".$object->password_os."<br>\n";
		if (! @ssh2_auth_password($connection, $object->username_os, $object->password_os)) {
			dol_syslog("Could not authenticate with username ".$username." . and password ".preg_replace('/./', '*', $password), LOG_ERR);
			exit(-5);
		} else {
			$sftp = ssh2_sftp($connection);

			// Remove install.lock
			$dir=preg_replace('/_([a-zA-Z0-9]+)$/', '', $object->database_db);
			$fileinstalllock=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->username_os.'/'.$dir.'/documents/install.lock';

			print 'Remove file '.$fileinstalllock."\n";

			ssh2_sftp_unlink($sftp, $fileinstalllock);

			// Create upgrade.unlock
			$fileupgradeunlock="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->username_os.'/'.$dir.'/documents/upgrade.unlock';

			print 'Create file '.$fileupgradeunlock."\n";

			$stream = fopen($fileupgradeunlock, 'w+');
			if ($stream) {
				//var_dump($stream);exit;
				fwrite($stream, "// File to allow upgrade.\n");
				fclose($stream);
			}
		}
	} else {
		print 'Failed to connect to ssh2 to '.$server;
		exit(-6);
	}
}

if ($mode != 'test') {
	print "Create event into database\n";
	dol_syslog("Add event into database");

	$user = new User($db);
	$user->fetch($conf->global->SELLYOURSAAS_ANONYMOUSUSER);

	if ($user->id > 0) {
		$actioncomm=new ActionComm($db);
		if (is_object($object->thirdparty)) $actioncomm->socid=$object->thirdparty->id;
		$actioncomm->datep = dol_now('tzserver');
		$actioncomm->percentage = 100;
		$actioncomm->label = 'Upgrade from CLI rsync_instance.php, instance='.$instance.' dirroot='.$dirroot.' mode='.$mode;
		$actioncomm->note_private = $actioncomm->label;
		$actioncomm->fk_element = $object->id;
		$actioncomm->elementtype = 'contract';
		$actioncomm->type_code = 'AC_OTH_AUTO';
		$actioncomm->userassigned[$user->id] = array('id'=>$user->id);
		$actioncomm->userownerid = $user->id;
		$actioncomm->create($user);
	}
}

exit($return_var);
