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

if (!defined('NOSESSION')) define('NOSESSION', '1');

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
if (! $res && file_exists($dolibarrdir."/htdocs/master.inc.php")) $res=@include $dolibarrdir."/htdocs/master.inc.php";
if (! $res) {
	print ("Include of master fails");
	exit(-1);
}


print 'Script to create missing payment after successful Stripe payment but no recording into database.';

/*
Scan "Successfuly charge card pm_.... for invoice ...."
and create payment for those invoices.
*/
