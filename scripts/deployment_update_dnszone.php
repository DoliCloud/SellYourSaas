#!/usr/bin/php
<?php
/* Copyright (C) 2012 Laurent Destailleur	<eldy@users.sourceforge.net>
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
 * Script to update dns zone files on customer instances.
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

// Global variables
$version='1.0';
$error=0;

// Include Dolibarr environment
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
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit(-1);
}

if (empty($dolibarrdir)) {
	print "Failed to find 'dolibarrdir' entry into /etc/sellyoursaas.conf file\n";
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
if (! $res) {
	print("Include of master fails");
	exit(-1);
}
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

include_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/utils.class.php';

$quiet = 0;
$test = 0;

$i = 0;
while ($i < $argc) {
	if (!empty($argv[$i])) {
		if ($argv[$i] == '-q') {
			$quiet = 1;
			unset($argv[$i]);
		} elseif ($argv[$i] == '-t') {
			$test = 1;
			unset($argv[$i]);
		}
	}
	$i++;
}
$argv = array_values($argv);	// We reindex parameters

$mode=isset($argv[1]) ? $argv[1] : '';
$dnszonefile=isset($argv[2]) ? $argv[2] : '';
$entry=isset($argv[3]) ? $argv[3] : '';
$ip=isset($argv[4]) ? $argv[4] : '';

/*
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
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit;
}
*/


/*
 *	Main
 */

if (!$quiet) {
	print "***** ".$script_file." (".$version.") *****\n";
}

if (0 != posix_getuid()) {
	echo "Script must be ran with root user. Try to launch it using sudo.\n";
	exit(-1);
}

if (in_array($dnszonefile, array('-q'))) {
	$dnszonefile = '';
}
if (in_array($dnszonefile, array('-q'))) {
	$dnszonefile = '';
}

if (empty($mode) || empty($dnszonefile)) {
	print "Script must be ran from a deployment server with login root.\n";
	print "\n";
	print "Usage:  ".$script_file."  get  zonefile  entry\n";
	print "Usage:  ".$script_file."  set  zonefile  entry  ip\n";
	//print "Usage:   ".$script_file."  refreshonly  zonefile\n";
	print "Option: -q for quiet mode\n";
	print "Option: -t for test mode\n";
	print "Return code: 0 if success, <>0 if error\n";
	exit(-1);
}
if (!in_array($mode, array('get', 'set', 'refresh'))) {
	print "Bad value for first parameter option '".$dnszonefile."'\n";
	exit(-1);
}
if (in_array($mode, array('get', 'set')) && empty($entry)) {
	print "Bad value for third parameter with action ".$mode.".\n";
	exit(-1);
}
if (in_array($mode, array('set')) && empty($ip)) {
	print "Bad value for fourth parameter with action ".$mode.". Must be the new IP.\n";
	exit(-1);
}

$fullpathdnszonefile = "/etc/bind/".$dnszonefile.".hosts";

if (!$quiet) {
	print "Process dns zone file ".$fullpathdnszonefile." with action ".$mode."\n";
}

if (!dol_is_file($fullpathdnszonefile)) {
	print "Bad value for second parameter dnszonefile '".$dnszonefile."'. File ".$fullpathdnszonefile." can't be found.\n";
	exit(-1);
}

$PID = getmypid();
$tmpfile = "/tmp/".$dnszonefile.".".$PID;
$fqn = $entry.".".$dnszonefile;
$NEEDLE="serial";
$DATE=dol_print_date(dol_now("gmt"), "%y%m%d%H", "gmt");	// YYMMDDHH

$found = 0;

$handle = fopen($fullpathdnszonefile, 'r');
if ($handle) {
	if ($mode == 'set') {
		$handlenew = fopen($tmpfile, 'w');
	}
	$arrayoflines = array();
	while ($s = fgets($handle, 4096)) {
		if ($mode == 'get') {
			$reg = array();
			if (preg_match('/^('.preg_quote($entry).')\s+A\s+(.*)$/', $s, $reg)) {
				if ($reg[1] == $entry) {
					print $reg[2];
					if (!$quiet) {
						print "\n";
					}
					break;
				}
			}
		}
		if ($mode == 'set') {
			// If line serial is detected
			$reg = array();
			if (preg_match('/^\s*(\d\d\d\d\d\d\d\d)(\d\d)*\s*;\s*'.preg_quote($NEEDLE, '/').'\s*$/', $s, $reg)) {
				// We found line for the serial number
				$oldserial = (int) ($reg[1].$reg[2]);
				$NEWDATE = (int) ($DATE."00");
				if (!$quiet) {
					print "We found serial ".$oldserial." to replace with ".$NEWDATE."\n";
				}
				if ($NEWDATE > $oldserial) {
					if (!$quiet) {
						print "New value is higher than old, so we can use it\n";
					}
				} else {
					if (!$quiet) {
						print "New value lower or equal than old, so we increase until we found a higher value\n";
					}
					while ($NEWDATE <= $oldserial) {
						$NEWDATE++;
					}
				}
				if (!$quiet) {
					print "Final replacement to do is to replace ".$oldserial." with ".$NEWDATE."\n";
				}
				fwrite($handlenew, preg_replace('/'.$oldserial.'/', $NEWDATE, $s));
				continue;
			}

			$reg = array();
			if (preg_match('/^('.preg_quote($entry).')\s+A\s+(.*)$/', $s, $reg)) {
				if ($reg[1] == $entry) {
					$found = 1;
					if (!$quiet) {
						print "IP for ".$entry." is modified from ".$reg[2]." to ".$ip."\n";
					}
					if ($reg[2] != $ip) {
						fwrite($handlenew, $entry." A ".$ip."\n");
					} else {
						fwrite($handlenew, $s);
					}
				} else {
					fwrite($handlenew, $s);
				}
			} else {
				fwrite($handlenew, $s);
			}
		}

		$arrayoflines[] = $s;
	}

	if ($mode == 'set' && $handlenew) {
		if (!$found) {
			if (!$quiet) {
				print "Record for ".$entry." was not found, so we add the line: ".$entry." A ".$ip."\n";
			}
			fwrite($handlenew, $entry." A ".$ip."\n");
		}

		fclose($handlenew);
	}
	fclose($handle);
}


if ($mode == 'set') {
	$utils = new Utils($db);

	// Test new file
	if (!$quiet) {
		print "Test the new DNS zone file ".$fullpathdnszonefile."\n";
	}
	$command = "named-checkzone ".$dnszonefile." ".$tmpfile;
	$outputfile = "/tmp/nslookup.tmp";
	$arrayresult = $utils->executeCLI($command, $outputfile);
	if ($arrayresult['result'] != 0 || !empty($arrayresult['error'])) {
		print "Error when editing the DNS file. File ".$tmpfile." is not valid.\n";
		exit(-2);
	} else {
		if (!$quiet) {
			print "New DNS host file ".$tmpfile." is valid.\n";
		}
	}

	// Archive old file
	$nowlog = dol_print_date(dol_now("gmt"), "%Y%m%d-%H%M%S", "gmt");
	$archivefile = "/etc/bind/archives/".$dnszonefile."-".$nowlog;
	if (!$quiet) {
		print "Copy the old DNS zone file ".$fullpathdnszonefile." into ".$archivefile."\n";
	}
	dol_copy($fullpathdnszonefile, $archivefile, 0, 1);

	// Replace old file with new one
	if (!$quiet) {
		if (!$test) {
			print "Move the new file ".$tmpfile." into ".$fullpathdnszonefile."\n";
		} else {
			print "We are in test mode so we don't move the new file ".$tmpfile." into ".$fullpathdnszonefile."\n";
		}
	}
	if (!$test) {
		dol_move($tmpfile, $fullpathdnszonefile, 0, 1, 0, 0);
	}

	// Restart DNS server
	$command = "rndc reload ".$dnszonefile;
	if (!$quiet) {
		print "Reload the DNS server with command: ".$command."\n";
	}
	$outputfile = "/tmp/nslookup.tmp";
	$arrayresult = $utils->executeCLI($command, $outputfile);
	if ($arrayresult['result'] != 0 || !empty($arrayresult['error'])) {
		print "Failed to restart DNS server\n";
		exit(-3);
	} else {
		if (!$quiet) {
			print "Reload is OK\n";
		}
	}

	$command = "nslookup ".$fqn." 127.0.0.1";
	if (!$quiet) {
		print "Now test the DNS with: ".$command."\n";
	}
	$outputfile = "/tmp/nslookup.tmp";
	$arrayresult = $utils->executeCLI($command, $outputfile);
	if ($arrayresult['result'] != 0) {
		print "Error after reloading DNS. nslookup of $fqn fails on first try. We wait a little bit to make another try.\n";
		sleep(3);
		print "Now test again the DNS with: ".$command."\n";
		$arrayresult = $utils->executeCLI($command, $outputfile);
		if ($arrayresult['result'] != 0 || !empty($arrayresult['error'])) {
			print "Error after reloading DNS. nslookup of $fqn fails on second try too.\n";
			exit(-4);
		}
	}
	if (!$quiet) {
		print "Resolution seems ok with result: ".$arrayresult['output']."\n";
	}
}

exit(0);
