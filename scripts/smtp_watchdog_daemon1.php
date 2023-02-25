#!/usr/bin/php
<?php
/* Copyright (C) 2022 Laurent Destailleur	<eldy@users.sourceforge.net>
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

$logfile = '/var/log/smtp_watchdog1.log';
$logphpsendmail = '/var/log/phpsendmail.log';
$WDLOGFILE='/var/log/ufw.log';
$outputfile='/tmp/outputforexecutecli.out';


$fp = @fopen('/etc/sellyoursaas-public.conf', 'r');
// Get $maxemailperday
$maxemailperday = 0;
$maxemailperdaypaid = 0;
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas-public.conf')));
	fclose($fp);
	foreach ($array as $val) {
		$tmpline = explode("=", $val);
		if ($tmpline[0] == 'maxemailperday') {
			$maxemailperday = $tmpline[1];
		}
		if ($tmpline[0] == 'maxemailperdaypaid') {
			$maxemailperdaypaid = $tmpline[1];
		}
		if ($tmpline[0] == 'pathtospamdir') {
			$pathtospamdir = $tmpline[1];
		}
	}
} else {
	file_put_contents($logfile, date('Y-m-d H:i:s') . " ERROR Failed to open /etc/sellyoursaas-public.conf file\n", FILE_APPEND);
	//exit(-1);
}

// Get the default limit in setup
if (is_numeric($maxemailperday) && $maxemailperday > 0) {
	$MAXPERDAY = (int) $maxemailperday;
}
if (is_numeric($maxemailperdaypaid) && $maxemailperdaypaid > 0) {
	$MAXPERDAYPAID = (int) $maxemailperdaypaid;
}
// The limit for the instance
$MAXALLOWED = 0;

$dolibarrdir='';
if ($tmpline[0] == 'dolibarrdir') {
	$dolibarrdir = $tmpline[1];
}

$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline = explode("=", $val);
		if ($tmpline[0] == 'domain') {
			$DOMAIN = $tmpline[1];
		}
		if ($tmpline[0] == 'emailfrom') {
			$EMAILFROM = $tmpline[1];
		}
		if ($tmpline[0] == 'emailsupervision') {
			$EMAILTO = $tmpline[1];
		}
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit(-1);
}
if (empty($EMAILFROM)) {
	$EMAILFROM="noreply@".$DOMAIN;
}
if (empty($EMAILTO)) {
	$EMAILTO="supervision@".$DOMAIN;
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
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/utils.class.php';



file_put_contents($logfile, date('Y-m-d H:i:s') . " **** $script_file started\n", FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . " Try to detect a smtp connexion from $WDLOGFILE and log who do it\n", FILE_APPEND);

if (empty($MAXPERDAY)) {
	$MAXPERDAY=1000;
}
if (empty($MAXPERDAYPAID)) {
	$MAXPERDAYPAID=1000;
}
if (empty($EMAILFROM)) {
	$EMAILFROM="noreply@".$DOMAIN;
}
if (empty($EMAILTO)) {
	$EMAILTO='supervision@'.$DOMAIN;
}
if (empty($pathtospamdir)) {
	$pathtospamdir="/tmp/spam";
}

$PID=getmypid();
$scriptdir=dirname(__FILE__);

file_put_contents($logfile, date('Y-m-d H:i:s') . " DOMAIN=$DOMAIN\n", FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . " EMAILFROM=$EMAILFROM\n", FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . " EMAILTO=$EMAILTO\n", FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . " PID=$PID\n", FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . " Now event captured will be logged into ".$logphpsendmail."\n", FILE_APPEND);

// Load $instanceofuser
$instanceofuser = getInstancesOfUser($pathtospamdir);

// Load $blacklistips
$blacklistips = getBlackListIps($pathtospamdir);

$datelastload = dol_now();


//$LOGPREFIX='UFW ALLOW';
// Note: to enable including --log-uid, we must insert rule directly in iptables (they are launched by launcher smtp_watchdog_launcher1.sh)
//iptables -I OUTPUT 1 -p tcp -m multiport --dports 25,2525,465,587 -m state --state NEW -j LOG --log-uid --log-prefix  "[UFW ALLOW SELLYOURSAAS] "
//ip6tables -I OUTPUT 1 -p tcp -m multiport --dports 25,2525,465,587 -m state --state NEW -j LOG --log-uid --log-prefix  "[UFW ALLOW SELLYOURSAAS] "
$LOGPREFIX='UFW ALLOW SELLYOURSAAS';

$handle = popen("tail -F ".$WDLOGFILE." | grep --line-buffered '".$LOGPREFIX."'", 'r');
while (!feof($handle)) {
	$line = fgets($handle);
	flush();

	$ok = 1;
	$remoteip = 'unknown';
	$usernamestring = '';
	$processownerid = '';
	$processid = '';
	$apachestring = '';

	file_put_contents($logfile, date('Y-m-d H:i:s') . " a new line appears into ".$WDLOGFILE." -> process it and write it into ".$logphpsendmail."\n", FILE_APPEND);

	// Write into smtp_watchdog1.log
	file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ----- start smtp_watchdog_daemon1.php\n", FILE_APPEND);
	file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " Found log prefix ".$LOGPREFIX.", in $WDLOGFILE, now try to find the process IPs and ports...\n", FILE_APPEND);
	file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ".trim($line)."\n", FILE_APPEND);

	$smtpipcaller=preg_replace('/\s.*/', '', preg_replace('/.*\sSRC=/', '', $line));
	$smtpipcalled=preg_replace('/\s.*/', '', preg_replace('/.*\sDST=/', '', $line));
	$smtpportcaller=preg_replace('/\s.*/', '', preg_replace('/.*\sSPT=/', '', $line));
	$smtpportcalled=preg_replace('/\s.*/', '', preg_replace('/.*\sDPT=/', '', $line));

	if (preg_match('/\sUID=/', $line)) {
		$processownerid=preg_replace('/\s.*/', '', preg_replace('/.*\sUID=/', '', $line));
		file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " We got processownerid=${processownerid} from iptable log ".$WDLOGFILE."\n", FILE_APPEND);
	}


	$result = '';


	if ($datelastload < (dol_now() - 3600)) {
		file_put_contents($logfile, date('Y-m-d H:i:s') . " reload cached files\n", FILE_APPEND);

		// Call this sometimes to refresh list of paid instances
		$instanceofuser = getInstancesOfUser($pathtospamdir);

		// Call this sometimes to refresh list of black listed IPs
		$blacklistips = getBlackListIps($pathtospamdir);

		$datelastload = dol_now();
	}


	if (!empty($smtpportcalled) && !empty($smtpipcalled)) {
		// -H option available only on recent ubuntu
		$command = "ss -e -H -p -t state all '( dport = $smtpportcalled )' dst [$smtpipcalled] ";
		if (!empty($smtpportcaller)) {
			$command = "ss -e -H -p -t state all '( sport = $smtpportcaller and dport = $smtpportcalled )' dst [$smtpipcalled]";
		}
		file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " Execute command $command\n", FILE_APPEND);

		include_once DOL_DOCUMENT_ROOT.'/core/class/utils.class.php';
		$utils = new Utils($db);
		$result = $utils->executeCLI($command, $outputfile);

		if (empty($result['result'])) {
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " Extract processid from line...\n", FILE_APPEND);
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ".trim($result['output'])."\n", FILE_APPEND);

			$reg = array();
			if (preg_match('/(ESTAB|SYN-SENT).*uid:(\d+)/', $result['output'], $reg)) {	// Often
				$processownerid = $reg[2];
			}
			if (preg_match('/(ESTAB|SYN-SENT).*pid=(\d+)/', $result['output'], $reg)) {	// Not always
				$processid = $reg[2];
			}

			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " We got processid=${processid}, processownerid=${processownerid} from log or ss command\n", FILE_APPEND);
		} else {
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ERROR ".$result['error']." ".$result['output']."\n", FILE_APPEND);
		}

		/*
		if (empty($processid)) {
			// Try another method with server-status
			$commandlynx = '/usr/bin/lynx -dump -width 500 http://127.0.0.1/server-status';
			// echo "/usr/bin/lynx -dump -width 500 http://127.0.0.1/server-status | grep \" $processid \"" >> /var/log/phpsendmail.log 2>&1
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " commandlynx=".$commandlynx."\n", FILE_APPEND);

			$resultlynx = $utils->executeCLI($commandlynx, $outputfile, 0, null, 1);
			if (empty($resultlynx['result'])) {
				$xxx = trim($resultlynx['output']);
				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ".$xxx."\n", FILE_APPEND);
			} else {
				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ERROR ".$resultlynx['error']." ".$resultlynx['output']."\n", FILE_APPEND);
			}
		}
		*/

		if (empty($processid)) {
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " The processid is still unknown, we try with netstat\n", FILE_APPEND);

			// Try another method with netstat
			$commandns = 'netstat -npt | grep \':25\s\|:2525\s\|:465\s\|:587\s\'';	// -napt show also the LISTEN processes
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " commandns=".$commandns."\n", FILE_APPEND);

			$resultns = $utils->executeCLI($commandns, $outputfile, 0, null, 1);
			if (empty($resultns['result'])) {
				$xxx = trim($resultns['output']);
				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ".$xxx."\n", FILE_APPEND);

				$reg = array();
				if (preg_match('/:(25|2525|465|587)\sESTABLISHED\s(\d+)/', $result['output'], $reg)) {
					$processid = $reg[2];
				}
			} else {
				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ERROR ".$resultns['error']." ".$resultns['result']." ".$resultns['output']."\n", FILE_APPEND);
			}
		}

		if (!empty($processid)) {
			if (preg_match('/^[0-9]+$/', $processownerid)) {
				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " We got the processownerid from the log or the ss command, surely an email sent from a web page\n", FILE_APPEND);
			} else {
				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " We did not get the processownerid from the log neither ss command. We try to find the processownerid using the processid and ps\n", FILE_APPEND);

				$commandps = 'ps -f -a -x -o "uid,pid,cpu,start,time,cmd" | grep --color=never " '.$processid.' " | grep -v "color=never" | awk \'{ print $1 }\'';

				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " commandps=".$commandps."\n", FILE_APPEND);

				$resultps = $utils->executeCLI($commandps, $outputfile, 0, null, 1);
				if (empty($resultps['result'])) {
					$processownerid = trim($resultps['output']);
				} else {
					file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ERROR ".$resultps['error']." ".$resultps['output']."\n", FILE_APPEND);
				}
				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " processownerid=$processownerid\n", FILE_APPEND);
			}

			// We try to get the apache process info
			if (preg_match('/^[0-9]+$/', $processid)) {
				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " We got the processid, now we try to get the apache process info to get remoteip\n", FILE_APPEND);
				// echo "/usr/bin/lynx -dump -width 500 http://127.0.0.1/server-status | grep \" $processid \"" >> /var/log/phpsendmail.log 2>&1
				// wget http://127.0.0.1/server-status -O -

				// Add a sleep to increase hope to have line "... client ip - pid ..." written into other_vhosts_pid.log file
				sleep(1);

				// export apachestring=`/usr/bin/lynx -dump -width 500 http://127.0.0.1/server-status | grep -m 1 " $processid "`
				$commandapachestring = 'tail -n 200 /var/log/apache2/other_vhosts_pid.log | grep -m 1 " '.$processid.' "';

				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " commandapachestring = ".$commandapachestring."\n", FILE_APPEND);

				$resultapachestring = $utils->executeCLI($commandapachestring, $outputfile, 0, null, 1);
				$apachestring = '';
				if (empty($resultapachestring['result'])) {
					$apachestring = trim($resultapachestring['output']);
					file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " apachestring=".$apachestring."\n", FILE_APPEND);

					// Try to guess remoteip
					if (!empty($apachestring)) {
						$arrayapachestring = preg_split('/\s+/', $apachestring);
						$remoteip = $arrayapachestring[1];
						file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " remoteip=".$remoteip."\n", FILE_APPEND);
					}
				} else {
					// If not found, result may be 1. Not an error to report.
					//file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ERROR ".$resultapachestring['error']." ".$resultapachestring['output']."\n", FILE_APPEND);
					file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " no entry found into other_vhosts_pid.log so apachestring=".$apachestring." remoteip=".$remoteip."\n", FILE_APPEND);
				}
			} else {
				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " processid not valid, we can't find apache and remoteip data\n", FILE_APPEND);
			}
		}

		// We try to get the usernamestring from processownerid
		if (preg_match('/^[0-9]+$/', $processownerid)) {
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " We try to get the usernamestring from processownerid\n", FILE_APPEND);

			$commandpasswd = 'grep "x:'.$processownerid.':" /etc/passwd | cut -f1 -d:';

			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " commandpasswd = ".$commandpasswd."\n", FILE_APPEND);

			$resultcommandpasswd = $utils->executeCLI($commandpasswd, $outputfile, 0, null, 1);
			$usernamestring = '';
			if (empty($resultcommandpasswd['result'])) {
				$usernamestring = trim($resultcommandpasswd['output']);
			} else {
				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ERROR ".$resultcommandpasswd['error']." ".$resultcommandpasswd['output']."\n", FILE_APPEND);
			}

			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " usernamestring=".$usernamestring."\n", FILE_APPEND);
		} else {
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " processownerid not valid, we can't find usernamestring\n", FILE_APPEND);
		}
	}

	// Build file /tmp/phpsendmail-... for this email
	$phpsendmailtmp = "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp";
	if (empty($processid)) {
		dol_delete_file($phpsendmailtmp);
		@chmod($phpsendmailtmp, 0770);
	} else {
		dol_delete_file($phpsendmailtmp);
		@chmod($phpsendmailtmp, 0770);
	}

	if (empty($remoteip)) {
		$remoteip="unknown";
	}

	file_put_contents($phpsendmailtmp, "Emails were sent using SMTP by processid=$processid processownerid=$processownerid smtpportcaller=$smtpportcaller\n", FILE_APPEND);
	file_put_contents($phpsendmailtmp, "SMTP connection from $smtpipcaller:$smtpportcaller -> $smtpipcalled:$smtpportcalled\n", FILE_APPEND);
	file_put_contents($phpsendmailtmp, (empty($result['result']) ? '' : $result['result']).' '.(empty($result['output']) ? '' : $result['output'])."\n", FILE_APPEND);
	file_put_contents($phpsendmailtmp, "usernamestring=$usernamestring\n", FILE_APPEND);
	file_put_contents($phpsendmailtmp, "apachestring=$apachestring\n", FILE_APPEND);
	file_put_contents($phpsendmailtmp, "remoteip=$remoteip\n", FILE_APPEND);

	flush();

	if (preg_match('/^osu/', $usernamestring)) {
		@chown($usernamestring, $phpsendmailtmp);
		@chgrp($usernamestring, $phpsendmailtmp);
	}

	file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " The file ".$phpsendmailtmp." has been generated\n", FILE_APPEND);


	// Check if case is in blacklist
	if ($ok && $remoteip != "unknown") {
		// Make a check of IP and URL from the array loaded from database
		// $remoteip, $usernamestring, $smtpportcalled, $smtpipcalled, $apachestring
		if (in_array($remoteip, $blacklistips)) {
			// We found the ip into the blacklistip
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " We found the IP $remoteip into ".$pathtospamdir."/blacklistip file\n", FILE_APPEND);
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " $remoteip sellyoursaas rules ko blacklist - IP found into blacklistip file\n", FILE_APPEND);
			$ok = 0;
		} else {
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " IP $remoteip not found into ".$pathtospamdir."/blacklistip file\n", FILE_APPEND);
		}
	}

	// Check quota
	if ($ok) {
		if (is_numeric($processownerid) && $processownerid >= 65535) {
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " We try to check the quota for processownerid=".$processownerid."\n", FILE_APPEND);

			// Test if we reached quota, if yes, discard the email and log it for fail2ban
			//dol_dir_list('/tmp', 'files', 0, '^phpsendmail\-'.$processownerid.'\-');
			$commandresexec="find /tmp/phpsendmail-$processownerid-* -mtime -1 | wc -l";

			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " commandresexec = ".$commandresexec."\n", FILE_APPEND);

			$resultresexec = $utils->executeCLI($commandresexec, $outputfile, 0, null, 1);
			$resexec = -1;
			if (empty($resultresexec['result'])) {
				$resexec = trim($resultresexec['output']);	// nb of email already sent in last 24h

				$MAXALLOWED = $MAXPERDAYPAID;
				if ($usernamestring) {
					if (in_array($usernamestring, array_keys($instanceofuser))) {
						$MAXALLOWED = $instanceofuser[$usernamestring]['mailquota'];
					} else {
						$MAXALLOWED = $MAXPERDAY;
					}
				}

				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " Nb of processes found with ".$commandresexec." = ".$resexec." (we accept ".$MAXALLOWED.")\n", FILE_APPEND);

				if ($resexec > $MAXALLOWED) {
					file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " $remoteip sellyoursaas rules ko quota reached. User has reached its quota of ".$MAXALLOWED."\n", FILE_APPEND);
					$ok = 0;
					//echo "smtp_watchdog_daemon1 has found an abusive smtp usage of over quota." | mail -aFrom:$EMAILFROM -s "[Warning] smtp_watchdog_daemon1 has found an abusive smtp usage on "`hostname`"." $EMAILTO
				}
			} else {
				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ERROR ".$resultresexec['error']." ".$resultresexec['output']."\n", FILE_APPEND);
			}
		} else {
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " processownerid ".$processownerid." is undefined or lower than 65535 so we do not check quota.\n", FILE_APPEND);
		}
	}

	// Check IP quality
	if ($ok && $remoteip != "unknown") {
		if (!is_numeric($processownerid) || $processownerid >= 65535) {
			// Test IP quality
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " We check the quality of the remoteip=".$remoteip."\n", FILE_APPEND);

			$evil = 0;

			// If ipquality shows it is tor ip (has tor or active_tor on), we refuse and we add ip into blacklistip
			// If ipquality shows it is a vpn (vpn or active_vpn on), if fraud_score > SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED, we refuse and we add into blacklist
			//file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " $remoteip sellyoursaas rules ko blacklist - IP found into blacklistip of IPQualityScore\n", FILE_APPEND);
			//$ok=0;
			//echo "smtp_watchdog_daemon1 has found an abusive smtp usage using a VPN to send emails." | mail -aFrom:$EMAILFROM -s "[Warning] smtp_watchdog_daemon1 has found an abusive smtp usage on "`hostname`"." $EMAILTO
			//else
			//file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " IP $remoteip is good according to IPQualityScore\n", FILE_APPEND);
			// TODO

			if ($evil) {
				file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " $remoteip sellyoursaas rules ko blacklist - IP found into blacklistip of IPQualityScore\n", FILE_APPEND);

				// Add IP to blacklistip
				file_put_contents($pathtospamdir.'/blacklistip', $remoteip."\n", FILE_APPEND);
				chmod($pathtospamdir."/blacklistip", 0666);
			}
		} else {
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " Process owner id=".$processownerid." is lower than 65535 so we do not check quota.\n", FILE_APPEND);
		}
	}

	if ($ok) {
		if ($MAXALLOWED > $MAXPERDAYPAID) {
			// If user has a specific limit, we set a log that will not be viewed by fail2ban (not "sellyoursaas rules ok")
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " $remoteip sellyoursaas rules is good with a custom limit\n", FILE_APPEND);
		} else {
			// If user has a common limit
			file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " $remoteip sellyoursaas rules ok\n", FILE_APPEND);
		}
	}
}
// This script never end

file_put_contents($logfile, date('Y-m-d H:i:s') . " exit of loop in script ".$script_file.". This should not happen except if script process was killed.\n", FILE_APPEND);

pclose($handle);



/**
 * getInstancesOfUser()
 *
 * @param	string	$pathtospamdir		Spam directory
 * @return	array						Array of instances in mailquota (so paid instances). Key is osu... user. Value is an array.
 */
function getInstancesOfUser($pathtospamdir)
{
	$instanceofuser = array();
	// Loop on each line of $pathtospamdir/mailquota
	$fp = @fopen($pathtospamdir."/mailquota", "r");
	if ($fp) {
		while (($buffer = fgets($fp, 1024)) !== false) {
			$reg = array();
			if (preg_match('/\sid=(\d+)\sref=([^\s]+)\sosu=([^\s]+)\smailquota=(\d+)/', $buffer, $reg)) {
				$instanceofuser[$reg[3]] = array('id'=>$reg[1], 'ref='=>$reg[2], 'osu'=>$reg[3], 'mailquota'=>$reg[4]);
			}
		}
		if (!feof($fp)) {
			echo "Erreur: fgets() a échoué\n";
		}
		fclose($fp);
	}
	return $instanceofuser;
}

/**
 * getBlackListIps()
 *
 * @param	string	$pathtospamdir		Spam directory
 * @return	array						Array of blacklisted IPs. Key and value are the IP.
 */
function getBlackListIps($pathtospamdir)
{
	$blacklistips = array();
	// Loop on each line of $pathtospamdir/mailquota
	$fp = @fopen($pathtospamdir."/blacklistip", "r");
	if ($fp) {
		while (($buffer = fgets($fp, 1024)) !== false) {
			//$reg = array();
			//if (preg_match('(.*)', $buffer, $reg)) {
			//	$blacklistips[$reg[1]] = $reg[1];
			//}
			$buffer = trim($buffer);
			$blacklistips[$buffer] = $buffer;
		}
		if (!feof($fp)) {
			echo "Erreur: fgets() a échoué\n";
		}
		fclose($fp);
	}
	return $blacklistips;
}
