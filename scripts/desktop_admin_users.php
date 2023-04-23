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

$date = new DateTime();
print "***** ".$script_file." (".$version.") - ".$date->format('Ymd-H:i:s')." *****\n";
if (! isset($argv[4])) {	// Check parameters
	print 'Administer unix users of a SellYourSaas infrastructure remotely.'."\n";
	print "This script must be ran remotely from an allowed desktop.\n";
	print "\n";
	print "Usage:\n".$script_file." (create|deactivate|reactivate|remove) logintoupdate hostfile (master,deployment,web) [loginforansible] [userroot=0|1] [userip=userip] [userpublickey=\"userpublickey\"] [userpassword=\"userpassword\"]\n";
	print "\n";
	print "Example:\n";
	print "To create a first admin user (root allowed): ".$script_file.' create logintocreate hostfile master,deployment,web ubuntu userroot=1 userpassword=... userpublickey="ABC..."'."\n";
	print "To add an admin user (root not allowed): ".$script_file.' create logintocreate hostfile master,deployment,web userroot=0 userip=ipofuser userpassword=... userpublickey="ABC..."'."\n";
	print "To update root access of a user: ".$script_file.' create logintoupdate hostfile withX.sellyoursaasdomain.com userroot=X'."\n";
	print "To remove a user: ".$script_file.' remove logintodelete hostfile master,deployment,web'."\n";
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
$target = empty($argv[4]) ? '' : $argv[4];

$loginforansible = isset($argv[5]) ? $argv[5] : '';
if (strpos($loginforansible, '=') !== false) {
	$loginforansible = "";
}

// optional params
$userip = '';
$userpublickey = '';
$userpassword = '';
$userroot = '';
for ($i = 5; $i <= 10; $i++) {
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
			if ($arrayparam[0] == 'userpassword') {
				$userpassword = $arrayparam[1];
			}
			if ($arrayparam[0] == 'userroot') {
				$userroot = $arrayparam[1];
			}
		}
	}
}

if ($action == 'create' && ($userroot === '' || empty($userpublickey))) {
	echo "Error: To create a personal user login, the parameter userroot and userpublickey are mandatory.\n";
	exit(-1);
}
if ($userroot && empty($userpassword)) {
	echo "Error: To create a personal user login allowed to get root access (userroot=1), the parameter userpassword is mandatory.\n";
	exit(-1);
}

$scriptyaml = '';
if ($action == 'create') {
	$scriptyaml = 'create_user.yml';
} elseif ($action == 'reactivate') {
	$scriptyaml = 'reactivate_user.yml';
} elseif ($action == 'deactivate') {
	$scriptyaml = 'deactivate_user.yml';
} elseif ($action == 'delete') {
	$scriptyaml = 'delete_user.yml';
} elseif ($action == 'remove') {
	$scriptyaml = 'remove_user.yml';
} else {
	echo "Error: Bad parameter action. Must be (create|deactivate|reactivate|remove).\n";
	echo "\n";
	print "Usage:\n".$script_file." (create|deactivate|reactivate|remove) logintoupdate hostfile (master,deployment,web) [loginforansible] [userip=userip] [userroot=0|1] [userpublickey=\"userpublickey\"] [userpassword=\"userpassword\"]\n";
	exit(-1);
}

if (empty($target)) {
	$target = "master,deployment,web";
}

$currentdir = getcwd();

chdir($path.'/ansible');

$command = "ansible-playbook -v -K ".$scriptyaml." -i hosts-".$hostfile;
if ($loginforansible) {
	$command .= " --user=".$loginforansible;
}
$command .= " -e 'target=".$target." login=".$login;
if ($userip) {
	$command .= " userip=\"".$userip."\"";
}
if ($userroot) {
	$command .= " userroot=\"".$userroot."\"";
}
if ($userpublickey) {
	$command .= " userpublickey=\"".$userpublickey."\"";
}
if ($userpassword) {
	$salt = generateSalt(16); // génère un sel aléatoire de 16 caractères
	//$hash = '$6$' . $salt . '$' . hash('sha512', $salt . $userpassword, false); // utilise la fonction hash() pour hasher le mot de passe avec le sel et encode le résultat dans le format du fichier /etc/shadow
	$hash = crypt($userpassword, "$6$".$salt); // utilise la fonction crypt() pour hasher le mot de passe avec le sel

	$command .= " userpassword=\"".$hash."\"";
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



/**
 * Generate a salt
 *
 * @param	int		$length		Length of salt
 * @return	string				Salt
 */
function generateSalt($length)
{
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
	$salt = '';
	for ($i = 0; $i < $length; $i++) {
		$salt .= $chars[rand(0, strlen($chars) - 1)];
	}
	return $salt;
}
