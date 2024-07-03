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
 * BACKUP DATA FROM STRATUS5 TO LOCAL
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
	exit;
}

// Global variables
$version='1.0';
$error=0;

$login=isset($argv[1]) ? $argv[1] : '';
$password=isset($argv[2]) ? $argv[2] : '';
$loginbase=isset($argv[3]) ? $argv[3] : '';
$passwordbase=isset($argv[4]) ? $argv[4] : '';
$mode=isset($argv[5]) ? $argv[5] : '';

// Include Dolibarr environment
@set_time_limit(0);							// No timeout for this script
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);		// Set this define to 0 if you want to lock your script when dolibarr setup is "locked to admin user only".

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
if (! $res) {
	die("Include of master fails");
}
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

dol_include_once("/sellyoursaas/core/lib/sellyoursaas.lib.php");
include_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';



/*
 *	Main
 */

if (empty($login) || empty($password) || empty($mode)) {
	print "Usage:   $script_file login pass loginbase passbase (test|confirm|confirmsaasplex|confirmrm)\n";
	print "Example: $script_file laurent ************ dolicloud ************ confirm\n";
	print "Return code: 0 if success, <>0 if error\n";
	print "Warning, this script may take a long time.\n";
	exit(-1);
}


$sourcedir='/s5Home/laurent/';
$targetdir=(!getDolGlobalString('DOLICLOUD_BACKUP_PATH') ? '.' : $conf->global->DOLICLOUD_BACKUP_PATH).'/';
$server='www.on.dolicloud.com';

print "Backup of database from stratus5 to localhost\n";

$sftpconnectstring=$login.'@'.$server;
print 'SFTP connect string : '.$sftpconnectstring."\n";
//print 'SFTP password '.$password."\n";


// SFTP connect
if (! function_exists("ssh2_connect")) {
	dol_print_error('', 'ssh2_connect function does not exists');
	exit(1);
}

$server_port = getDolGlobalInt('SELLYOURSAAS_SSH_SERVER_PORT', 22);

$methods = array();
if (getDolGlobalString('SELLYOURSAAS_SSH2_HOSTKEYALGO')) {
	$methods['hostkey'] = getDolGlobalString('SELLYOURSAAS_SSH2_HOSTKEYALGO');
}
if (getDolGlobalString('SELLYOURSAAS_SSH2_KEXALGO')) {
	$methods['kex'] = getDolGlobalString('SELLYOURSAAS_SSH2_KEXALGO');
}
if (!empty($methods)) {
	$connection = ssh2_connect($server, $server_port, $methods);
} else {
	$connection = ssh2_connect($server, $server_port);
}

if ($connection) {
	if (! @ssh2_auth_password($connection, $login, $password)) {
		dol_syslog("Could not authenticate with username ".$login." . and password ".preg_replace('/./', '*', $password), LOG_ERR);
		exit(-5);
	} else {
		$filesys1='dump_sys1.sql';
		$filesys2='dump_sys2.sql';

		//$stream = ssh2_exec($connection, '/usr/bin/php -i');

		$mysqluser='debian-sys-maint';
		//$mysqlpassword='4k9Blxl2snq4FHXY';
		$mysqlpassword='EB7ostKIDJrBZeiF';

		print "Generate dump ".$filesys1.'.bz2'."\n";
		if ($mode == 'confirm' || $mode == 'confirmsaasplex') {
			$mysqldumpcommand="mysqldump -u $mysqluser -p$mysqlpassword -h 127.0.0.1 --single-transaction -K --tables -c -e --hex-blob --default-character-set=utf8 saasplex";
			echo $mysqldumpcommand."\n";
			$stream = ssh2_exec($connection, "$mysqldumpcommand | bzip2 -1 > ".$filesys1.'.bz2');
			stream_set_blocking($stream, true);
			// The command may not finish properly if the stream is not read to end
			$output = stream_get_contents($stream);
		}

		/*print "Generate dump ".$filesys2.'.bz2'."\n";
		if ($mode == 'confirm' || $mode == 'confirmrm')
		{
			$mysqldumpcommand="mysqldump -u $mysqluser -p$mysqlpassword -h 127.0.0.1 --single-transaction -K --tables -c -e --hex-blob --default-character-set=utf8 rm";
			echo $mysqldumpcommand."\n";
			$stream = ssh2_exec($connection, "$mysqldumpcommand | bzip2 -1 > ".$filesys2.'.bz2');
			stream_set_blocking($stream, true);
			// The command may not finish properly if the stream is not read to end
			$output = stream_get_contents($stream);
		}*/

		$sftp = ssh2_sftp($connection);

		print 'Get file '.$sourcedir.$filesys1.'.bz2 into '.$targetdir.$filesys1.'.bz2'."\n";
		if ($mode == 'confirm' || $mode == 'confirmsaasplex') {
			ssh2_scp_recv($connection, $sourcedir.$filesys1.'.bz2', $targetdir.$filesys1.'.bz2');
		}
		/*print 'Get file '.$sourcedir.$filesys2.'.bz2 into '.$targetdir.$filesys2.'.bz2'."\n";
		if ($mode == 'confirm' || $mode == 'confirmrm')
		{
			ssh2_scp_recv($connection, $sourcedir.$filesys2.'.bz2', $targetdir.$filesys2.'.bz2');
		}*/

		if ($mode == 'confirm' || $mode == 'confirmsaasplex') {
			dol_delete_file($targetdir.$filesys1);
		}
		$fullcommand="bzip2 -c -d ".$targetdir.$filesys1.".bz2 | mysql -u".$loginbase." -p".$passwordbase." -D dolicloud_saasplex";
		print "Load dump with ".$fullcommand."\n";
		if ($mode == 'confirm' || $mode == 'confirmsaasplex') {
			$output=array();
			$return_var=0;
			print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' '.$fullcommand."\n";
			exec($fullcommand, $output, $return_var);
			foreach ($output as $line) {
				print $line."\n";
			}
		}

		/*if ($mode == 'confirm' || $mode == 'confirmrm') dol_delete_file($targetdir.$filesys2);
		$fullcommand="bzip2 -c -d ".$targetdir.$filesys2.".bz2 | mysql -u".$loginbase." -p".$passwordbase." -D dolicloud_rm";
		print "Load dump with ".$fullcommand."\n";
		if ($mode == 'confirm' || $mode == 'confirmrm')
		{
			$output=array();
			$return_var=0;
			print dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt').' '.$fullcommand."\n";
			exec($fullcommand, $output, $return_var);
			foreach($output as $line) print $line."\n";
		}*/

		//ssh2_sftp_unlink($sftp, $fileinstalllock);
		//print $output;
	}
} else {
	print 'Failed to connect to ssh2 to '.$server;
	exit(-6);
}


exit(0);
