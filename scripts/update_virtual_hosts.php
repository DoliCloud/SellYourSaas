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
 * Script to update virtual hosts on customer instances.
 */

if (!defined('NOSESSION')) define('NOSESSION', '1');
if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');				// Do not create database handler $db

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

$mode=isset($argv[1])?$argv[1]:'';
$option=isset($argv[2])?$argv[2]:'';
$stringtoadd=isset($argv[3])?$argv[3]:'';

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
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/master.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/master.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/master.inc.php")) $res=@include dirname(substr($tmp, 0, ($i+1)))."/master.inc.php";
// Try master.inc.php using relative path
if (! $res && file_exists("../master.inc.php")) $res=@include "../master.inc.php";
if (! $res && file_exists("../../master.inc.php")) $res=@include "../../master.inc.php";
if (! $res && file_exists("../../../master.inc.php")) $res=@include "../../../master.inc.php";
if (! $res && file_exists("../../../../master.inc.php")) $res=@include "../../../../master.inc.php";
if (! $res && file_exists(__DIR__."/../../master.inc.php")) $res=@include __DIR__."/../../master.inc.php";
if (! $res && file_exists(__DIR__."/../../../master.inc.php")) $res=@include __DIR__."/../../../master.inc.php";
if (! $res && file_exists($dolibarrdir."/htdocs/master.inc.php")) $res=@include $dolibarrdir."/htdocs/master.inc.php";
if (! $res) {
	print ("Include of master fails");
	exit(-1);
}
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
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit;
}


/*
 *	Main
 */

print "***** ".$script_file." *****\n";

if (0 != posix_getuid()) {
	echo "Script must be ran with root user. Try to launch it using sudo.\n";
	exit(-1);
}

if (empty($instanceserver) || empty($mode) || empty($option) || empty($stringtoadd)) {
	print "Update all virtual hosts files of instances by adding a line if not already found.\n";
	print "Script must be ran from each deployment server with login root.\n";
	print "\n";
	print "Usage:   ".$script_file."  test|confirm  discardiffound|addiffound|replace  'line to add'\n";
	print "Example: ".$script_file."  test  replace  'php_admin_value open_basedir /tmp/:/home/admin/wwwroot/dolibarr_sellyoursaas/scripts/:__InstanceRoot__'\n";
	print "Return code: 0 if success, <>0 if error\n";
	exit(-1);
}

if (!in_array($option, array('discardiffound', 'addiffound', 'replace'))) {
	print "Usage: ".$script_file."  test|confirm  discardiffound|addiffound|replace  'line to add'\n";
	print "Bad value for second parameter option '".$option."'\n";
	exit(-1);
}

$dirtoscan = '/etc/apache2/sellyoursaas-available';

print "Process each virtual host into directory ".$dirtoscan." to add ".$stringtoadd."\n";

$listofvirtualhost = dol_dir_list($dirtoscan, 'files', 0, '\.conf$');
foreach ($listofvirtualhost as $virtualhostconfarray) {
	$virtualhostconf = $virtualhostconfarray['fullname'];
	print "***** Process file ".$virtualhostconf."\n";
	$handle = fopen($virtualhostconf, 'r');
	if ($handle) {
		$found = 0;
		$addnewstring = 1;
		$totallineoriginal = 0;
		$apachedocumentroot = '';
		$arrayoflines = array();
		while ($s = fgets($handle, 4096)) {
			$totallineoriginal++;
			$reg = array();
			if (preg_match('/^\s*DocumentRoot\s*(.*)$/', $s, $reg)) {
				$apachedocumentroot = preg_replace('/htdocs\/?$/', '', $reg[1]);
				print "  We found the value ".$apachedocumentroot." for InstanceRoot.\n";
			}
			$stringtosearch = str_replace('__InstanceRoot__', '.*', preg_quote($stringtoadd, '/'));
			if ($stringtosearch && preg_match('/'.$stringtosearch.'/', $s)) {
				print "  A string similar to the one to add already found".($option == 'replace' ? ' (lines will be replaced)' : ($option == 'discardiffound' ? ' (no change on file will be done)' : ' (file will be completed)')).": ".$s;
				$found++;
				// Do we have to keep it or discard line ?
				if ($option == 'discardiffound') {
					$addnewstring = 0;
				}
				if ($option == 'addiffound') {
					$arrayoflines[] = $s;
				}
			} else {
				$arrayoflines[] = $s;
			}
		}
		fclose($handle);

		$rewritefile = 1;
		if ($found && $option == 'discardiffound') {
			$rewritefile = 0;
			print "  -> We discard file ".$virtualhostconf.".\n";
		}
		//php_admin_value open_basedir /tmp/:/home/admin/wwwroot/dolibarr_sellyoursaas/scripts/:/home/jail/home/osur3s2ffyep/dbnzCo96O2J/

		if ($rewritefile) {
			print "  -> We will rewrite the file ".$virtualhostconf." after adding the string (old file will be renamed into .beforeupdate).\n";

			if ($mode == 'confirm') {
				dol_move($virtualhostconf, $virtualhostconf.'.beforeupdate', 0, 1, 0, 0);
				$handlew = fopen($virtualhostconf, 'w');
			} else {
				$handlew = fopen('/dev/null', 'w');
			}
			if (! $handlew) {
				print "  ERROR: Failed to open file ".$virtualhostconf." for writing.\n";
				fclose($handlew);
				continue;
			} else {
				$linenb = 0;
				print "  We have ".count($arrayoflines)." old lines on ".$totallineoriginal." to rewrite.\n";
				foreach ($arrayoflines as $line) {
					fwrite($handlew, $line);
					$linenb++;

					// We add the string
					if ($linenb == 1 && $addnewstring) {
						$newstringtoadd = $stringtoadd;
						if ($apachedocumentroot) {
							$newstringtoadd = str_replace('__InstanceRoot__', $apachedocumentroot, $newstringtoadd);
						}
						print "  We also add this new string after line ".$linenb.": ".$newstringtoadd."\n";
						fwrite($handlew, $newstringtoadd."\n");
					}
				}
			}

			fclose($handlew);
			if ($mode == 'confirm') {
				print "  File has been rewritten.\n";
			} else {
				print "  File has NOT been rewritten (test mode).\n";
			}
		}
	}
}

exit(0);
