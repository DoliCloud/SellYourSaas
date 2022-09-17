#!/usr/bin/php
<?php
/* Copyright (C) 2007-2022 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *      \file       sellyoursaas/scripts/remotely_admin_users.php
 *		\ingroup    sellyoursaas
 *      \brief      Script to run from a remote computer to admin unix users of a SellYourSaas infrastructure.
 */

if (!defined('NOSESSION')) define('NOSESSION', '1');
if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');				// Do not create database handler $db

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

/*

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
*/


// Load user and its permissions
//$result=$user->fetch('','admin');	// Load user for login 'admin'. Comment line to run as anonymous user.
//if (! $result > 0) { dol_print_error('',$user->error); exit; }
//$user->getrights();


print "***** ".$script_file." (".$version.") - ".strftime("%Y%m%d-%H%M%S")." *****\n";
if (! isset($argv[3])) {	// Check parameters
	print 'Administer unix users of a SellYourSaas infrastructure remotely.'."\n";
	print "This script must be ran remotely from an allowed desktop.\n";
	print "\n";
	print "Usage:\n".$script_file." (create|deactivate|reactivate|remove) login hostfile target [userip=userip] [userpublickey=\"userpublickey\"]\n";
	print "\n";
	exit(-1);
}
print '--- start'."\n";
//print 'Argument 1='.$argv[1]."\n";
//print 'Argument 2='.$argv[2]."\n";



/*
 * Main
 */

$now = time();

// mandatory params
$action = isset($argv[1]) ? $argv[1] : '';
$login = isset($argv[2]) ? $argv[2] : '';
$hostfile = isset($argv[3]) ? $argv[3] : '';

// optional params
$userip = '';
$userpublickey = '';
$target = empty($argv[4]) ? '' : $argv[4];
for ($i = 4; $i <= 6; $i++) {
	$moreparam = empty($argv[$i]) ? '' : $argv[$i];
	//print $moreparam."\n";
	if ($moreparam) {
		$arrayparam = explode('=', $moreparam, 2);
		if (count($arrayparam) == 2) {
			if ($arrayparam[0] == 'userip') {
				$userip = $arrayparam[1];
			}
			if ($arrayparam[0] == 'userpublickey') {
				$userpublickey = $arrayparam[1];
			}
		}
	}
}

$scriptyaml = '';
if ($action == 'create') {
	$scriptyaml = 'create_user.yml';
} elseif ($action == 'allowroot' || $action == 'disallowroot' || $action == 'reactivate') {
	$scriptyaml = 'reactivate_user.yml';
} elseif ($action == 'deactivate') {
	$scriptyaml = 'deactivate_user.yml';
} elseif ($action == 'delete') {
	$scriptyaml = 'delete_user.yml';
} elseif ($action == 'remove') {
	$scriptyaml = 'remove_user.yml';
} else {
	echo "Error: Bad parameter action. Must be (create|allowroot|disallowroot|deactivate|reactivate|remove).\n";
	exit(-1);
}

if (empty($target)) {
	$target = "master,deployment,web";
}

$currentdir = getcwd();

chdir($path.'/ansible');

$command = "ansible-playbook -v -K ".$scriptyaml." -i hosts-".$hostfile." -e 'target=".$target." login=".$login;
if ($userip) {
	$command .= " userip=\"".$userip."\"";
}
if ($userpublickey) {
	$command .= " userpublickey=\"".$userpublickey."\"";
}
if ($action == 'allowroot') {
	$command .= " allowroot=1";
}
if ($action == 'disallowroot') {
	$command .= " disallowroot=1";
}
$command .= "'";

$ret = 0;
$resarray = array();

print $command."\n";
$result = exec($command, $resarray, $ret);

foreach ($resarray as $line) {
	print $line."\n";
}

chdir($currentdir);

exit(0);
