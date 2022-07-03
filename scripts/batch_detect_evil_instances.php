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
 *      \file       sellyoursaas/scripts/batch_detect_evil_instances.php
 *		\ingroup    sellyoursaas
 *      \brief      Script to detect evils instances by scanning inside its data for blacklist content
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
include_once dol_buildpath("/sellyoursaas/class/blacklistcontent.class.php");
include_once dol_buildpath("/sellyoursaas/class/blacklistdir.class.php");


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
	print "Script to detect evils instances by scanning inside its data for blacklist content.\n";
	print "Usage on deployment servers: ".$script_file." (test|testemail|remove) [instancefilter]\n";
	print "\n";
	print "Options are:\n";
	print "- test          test scan\n";
	print "- testemail     test scan and send email\n";
	print "- remove        not yet available\n";
	exit(-1);
}
print '--- Start script with mode '.$argv[1]."\n";
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

$return_var = 0;

print "----- Go into dir /home/jail/home\n";
chdir('/home/jail/home/');

print "----- Loop for spam keys into index.php using blacklistcontent\n";

$tmpblacklistcontent = new Blacklistcontent($db);
$tmparray = $tmpblacklistcontent->fetchAll('', '', 1000, 0, array('status'=>1));
if (is_numeric($tmparray) && $tmparray < 0) {
	echo "Erreur: failed to get blacklistcontent elements.\n";
}

if (!empty($tmparray)) {
	foreach ($tmparray as $val) {
		$buffer = dol_sanitizePathName(trim($val->content));
		if ($buffer) {
			echo 'Scan if we found the string '.$buffer.' into osu*/dbn*/htdocs/index.php ';
			$command = "grep -l '".str_replace("'", ".", $buffer)."' osu*/dbn*/htdocs/index.php";
			$fullcommand=$command;
			$output=array();
			//echo $command."\n";
			exec($fullcommand, $output, $return_var);
			if ($return_var == 0) {		// grep -l returns 0 if something was found
				// We found an evil string
				print "- ALERT: the evil string '".$buffer."' was found into a file using the command: ".$command."\n";
			} else {
				print "- OK\n";
			}
		}
	}
}


print "----- Loop for spam dir using blacklistdir\n";

$tmpblacklistdir = new Blacklistdir($db);
$tmparray = $tmpblacklistdir->fetchAll('', '', 1000, 0, array('status'=>1));
if (is_numeric($tmparray) && $tmparray < 0) {
	echo "Erreur: failed to get blacklistdir elements.\n";
}

if (!empty($tmparray)) {
	foreach ($tmparray as $val) {
		$buffer = dol_sanitizePathName(trim($val->content));
		if ($buffer) {
			echo 'Scan if we found the blacklist dir '.$buffer.' in osu*/dbn*/htdocs/ ';
			$command = "find osu*/dbn*/htdocs/ -maxdepth 2 -type d | grep '".str_replace("'", ".", $buffer)."'";
			$fullcommand=$command;
			$output=array();
			//echo $command."\n";
			exec($fullcommand, $output, $return_var);
			if ($return_var == 0) {		// command returns 0 if something was found
				// We found an evil string
				print "- ALERT: the evil dir '".$buffer."' was found using the command: ".$command."\n";
			} else {
				print "- OK\n";
			}
		}
	}
}

/*
print "----- Loop for spam dir using whitelistdir\n";

$tmpblacklistdir = new Blacklistdir($db);
$tmparray = $tmpblacklistdir->fetchAll('', '', 1000, 0, array('status'=>1));
if (is_numeric($tmparray) && $tmparray < 0) {
	echo "Erreur: failed to get whitelistdir elements.\n";
}

if (!empty($tmparray)) {
	$buffer = '';
	foreach ($tmparray as $val) {
		$buffertmp = dol_sanitizePathName(trim($val->content));
		if ($buffertmp) {
			$buffer .= ($buffer ? '|' : '').$buffertmp;
		}
	}

	echo 'Scan if we found a non whitelistdir dir '.$buffer.' in osuSTAR/dbnSTAR/htdocs/ ';
	$command = "find osuSTAR/dbnSTAR/htdocs/ -maxdepth 1 -type d | grep -v '".str_replace("'", ".", $buffer)."'";
	$fullcommand=$command;
	$output=array();
	//echo $command."\n";
	exec($fullcommand, $output, $return_var);
	if ($return_var == 0) {		// command returns 0 if something was found
		// We found an evil string
		print "- ALERT: evil dirs '".(join(', ', $output))."' was/were found using the command: ".$command."\n";
	} else {
		print "- OK\n";
	}
}
*/

$dbmaster->close();	// Close database opened handler

print '--- end ERROR nb='.$nboferrors.' - '.strftime("%Y%m%d-%H%M%S")."\n";

if ($nboferrors) {
	if ($action == 'testemail') {
		$from = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;
		$to = $conf->global->SELLYOURSAAS_SUPERVISION_EMAIL;
		// Supervision tools are generic for all domain. No way to target a specific supervision email.

		$msg = 'Error in '.$script_file." ".$argv[1]." ".$argv[2]." (finished at ".strftime("%Y%m%d-%H%M%S").")\n\n".$out;

		include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		print 'Send email MAIN_MAIL_SENDMODE='.$conf->global->MAIN_MAIL_SENDMODE.' MAIN_MAIL_SMTP_SERVER='.$conf->global->MAIN_MAIL_SMTP_SERVER.' from='.$from.' to='.$to.' title=[Warning] Alert(s) in batch_detect_evil_instances - '.gethostname().' - '.dol_print_date(dol_now(), 'dayrfc')."\n";
		$cmail = new CMailFile('[Warning] Alert(s) in batch_detect_evil_instances - '.gethostname().' - '.dol_print_date(dol_now(), 'dayrfc'), $to, $from, $msg, array(), array(), array(), '', '', 0, 0, '', '', '', '', 'emailing');
		$result = $cmail->sendfile();
	}
}

exit($nboferrors);
