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
 *      \file       sellyoursaas/scripts/ansible/info_users.php
 *		\ingroup    sellyoursaas
 *      \brief      Script used by an ansible script executed remotely to get information about a user.
 */

if (!defined('NOSESSION')) define('NOSESSION', '1');
if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');				// Do not create database handler $db
if (!defined('NOREQUIREVIRTUALURL')) define('NOREQUIREVIRTUALURL', '1');

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname($_SERVER['PHP_SELF']).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	fwrite(STDERR, "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n");
	exit(-1);
}

// Global variables
$version='1.0';
$error=0;


$mode = empty($argv[1]) ? '' : $argv[1];


// -------------------- START OF YOUR CODE HERE --------------------
@set_time_limit(0);							// No timeout for this script
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);		// Set this define to 0 if you want to lock your script when dolibarr setup is "locked to admin user only".


// Read /etc/sellyoursaas.conf file
$dolibarrdir='';
$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
		if ($tmpline[0] == 'dolibarrdir') {
			$dolibarrdir = $tmpline[1];
		}
	}
} else {
	fwrite(STDERR, "Failed to open /etc/sellyoursaas.conf file\n");
	exit(-1);
}

if (empty($dolibarrdir)) {
	fwrite(STDERR, "Failed to find 'dolibarrdir' entry into /etc/sellyoursaas.conf file\n");
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
if (! $res && file_exists("./master.inc.php")) $res=@include "./master.inc.php";
if (! $res && file_exists("../master.inc.php")) $res=@include "../master.inc.php";
if (! $res && file_exists("../../master.inc.php")) $res=@include "../../master.inc.php";
if (! $res && file_exists("../../../master.inc.php")) $res=@include "../../../master.inc.php";
if (! $res && file_exists("../../../../master.inc.php")) $res=@include "../../../../master.inc.php";
if (! $res && file_exists(__DIR__."/../../master.inc.php")) $res=@include __DIR__."/../../../master.inc.php";
if (! $res && file_exists(__DIR__."/../../../master.inc.php")) $res=@include __DIR__."/../../../master.inc.php";
if (! $res && file_exists($dolibarrdir."/htdocs/master.inc.php")) $res=@include $dolibarrdir."/htdocs/master.inc.php";
if (! $res) die("Include of master fails");
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

include_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/utils.class.php';

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
	}
} else {
	fwrite(STDERR, "Failed to open /etc/sellyoursaas.conf file\n");
	exit(-1);
}


/*
 *	Main
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


// Load user and its permissions
//$result=$user->fetch('','admin');	// Load user for login 'admin'. Comment line to run as anonymous user.
//if (! $result > 0) { dol_print_error('',$user->error); exit; }
//$user->getrights();

if ($mode == 'test') {
	print "***** ".$script_file." (".$version.") - ".dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt')." *****\n";
}
if (! isset($argv[2])) {	// Check parameters
	print 'Tool to return some information for a user.'."\n";
	print "\n";
	print "Usage: ".$script_file." (test|ip|public_key) login\n";
	print "\n";
	exit;
}
if ($mode == 'test') {
	print '--- start'."\n";
	print 'Argument 1='.$argv[1]."\n";
	print 'Argument 2='.$argv[2]."\n";
}

$login = $argv[2];

// Get list of instance
$sql = "SELECT u.rowid as id, u.login as login, ue.rsapublicmain, ue.ippublicmain";
$sql.= " FROM ".MAIN_DB_PREFIX."user as u LEFT JOIN ".MAIN_DB_PREFIX."user_extrafields as ue ON u.rowid = ue.fk_object";
$sql.= " WHERE u.login = '".$dbmaster->escape($login)."'";
//$sql.= " AND u.active = 1";

dol_syslog($script_file, LOG_DEBUG);

$resql = $dbmaster->query($sql);
if ($resql) {
	$num_rows = $dbmaster->num_rows($resql);
	if ($num_rows == 1) {
		$obj = $dbmaster->fetch_object($resql);
		if ($obj) {
			if ($mode == 'test') {
				print 'Found ip='.$obj->ippublicmain." key=".$obj->rsapublicmain."\n";
			} else {
				if ($mode == 'ip') {
					print $obj->ippublicmain;
				}
				if ($mode == 'public_key') {
					print $obj->rsapublicmain;
				}
			}
		}
	} elseif ($num_rows == 0) {
		fwrite(STDERR, 'Login '.$login.' not found in Dolibarr Master.'."\n");
		exit(0);
	} else {
		fwrite(STDERR, 'Bad number of record found when searching the login in Dolibarr Master'."\n");
		exit(1);
	}
} else {
	print $dbmaster->lasterror();
}


$db->close();	// Close database opened handler

if ($mode == 'test') {
	print '--- end'."\n";
}
exit(0);
