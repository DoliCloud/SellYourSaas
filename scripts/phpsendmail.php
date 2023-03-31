#!/usr/bin/php
<?php
/**
  This script is a sendmail wrapper for php to log calls of the php mail() function.
  Author: Till Brehm, www.ispconfig.org
  (Hopefully) secured by David Goodwin <david @ _palepurple_.co.uk>

  Modify your php.ini file to add:
  sendmail_path = /usr/local/bin/phpsendmail.php
*/

//setlocale(LC_CTYPE, "en_US.UTF-8");

$sendmail_bin = '/usr/sbin/sendmail';
$logfile = '/var/log/phpsendmail.log';

// The directory $pathtospamdir must have permission rwxrwxrwx and not rwxrwxrwt
//$pathtospamdir = '/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam';
$pathtospamdir = '/tmp/spam';

//* Get the email content
$mail='';
$toline = ''; $ccline = ''; $bccline = '';
$nbto = 0; $nbcc = 0; $nbbcc = 0;
$fromline = '';
$referenceline = '';
$messageidline = '';
$emailfrom = '';


// Rules
$MAXOK = 10;
$MAXPERDAY = 250;	// By default, will be overwritten with sellyoursaas-public.conf

file_put_contents($logfile, date('Y-m-d H:i:s') . " ----- start phpsendmail.php\n", FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . " SERVER_NAME = ".(empty($_SERVER['SERVER_NAME']) ? '' : $_SERVER['SERVER_NAME'])."\n", FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . " DOCUMENT_ROOT = ".(empty($_SERVER['DOCUMENT_ROOT']) ? '' : $_SERVER['DOCUMENT_ROOT'])."\n", FILE_APPEND);

$fp = @fopen('/etc/sellyoursaas-public.conf', 'r');
// Get $maxemailperday
$maxemailperday = 0;
$maxemailperdaypaid = 0;
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas-public.conf')));
	fclose($fp);
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
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
if (is_numeric($maxemailperday) && $maxemailperday > 0) {
	$MAXPERDAY = (int) $maxemailperday;
}
if (is_numeric($maxemailperdaypaid) && $maxemailperdaypaid > 0) {
	$MAXPERDAYPAID = (int) $maxemailperdaypaid;
}

$processownerid = posix_getuid();
$tmparray = posix_getpwuid($processownerid);
$usernamestring = $tmparray['name'];
file_put_contents($logfile, date('Y-m-d H:i:s') . " processownerid=".$processownerid." usernamestring=".$usernamestring."\n", FILE_APPEND);

if (empty($MAXPERDAY)) {
	$MAXPERDAY=1000;
}
if (empty($MAXPERDAYPAID)) {
	$MAXPERDAYPAID=1000;
}

// Main

file_put_contents($logfile, date('Y-m-d H:i:s') . " php.ini file loaded is ".php_ini_loaded_file()."\n", FILE_APPEND);

if (function_exists('shell_exec')) {
	file_put_contents($logfile, date('Y-m-d H:i:s') . " shell_exec is available. ok\n", FILE_APPEND);
} else {
	file_put_contents($logfile, date('Y-m-d H:i:s') . " The function shell_exec is not available in shell context - exit 1\n", FILE_APPEND);
	exit(1);
}
/*$disabledfunction = ini_get('disable_functions');
if ($disabledfunction) {
	if (strpos($disabledfunction, 'shell_exec') === false) {
		file_put_contents($logfile, date('Y-m-d H:i:s') . " shell_exec is not disabled. ok\n", FILE_APPEND);
	} else {
		file_put_contents($logfile, date('Y-m-d H:i:s') . " The function shell_exec is disabled by disable_functions - exit 1\n", FILE_APPEND);
		exit(1);
	}
} else {
	file_put_contents($logfile, date('Y-m-d H:i:s') . " failed to get disable_functions.\n", FILE_APPEND);
}
*/


// Load $instanceofuser
$instanceofuser = getInstancesOfUser($pathtospamdir);

// Load $blacklistips
$blacklistips = getBlackListIps($pathtospamdir);



// TODO Check quota of email for the UID $processownerid / $usernamestring
//export usernamestring=`grep "x:$processownerid:" /etc/passwd | cut -f1 -d:`
//echo "$now usernamestring=$usernamestring" >> "/var/log/phpsendmail.log"



$pointer = fopen('php://stdin', 'r');

$headerend = 0;
while ($line = fgets($pointer)) {
	if (empty($headerend)) {
		if (preg_match('/^to:\s/i', $line)) {
			$toline .= trim($line)."\n";
			$linetmp = preg_replace('/^to:\s*/i', '', trim($line));
			$tmpto=preg_split("/[\s,;]+/", $linetmp);
			$nbto+=count($tmpto);
		} elseif (preg_match('/^cc:\s/i', $line)) {
			$ccline .= trim($line)."\n";
			$linetmp = preg_replace('/^cc:\s*/i', '', trim($line));
			$tmpcc=preg_split("/[\s,;]+/", $linetmp);
			$nbcc+=count($tmpcc);
		} elseif (preg_match('/^bcc:\s/i', $line)) {
			$bccline .= trim($line)."\n";
			$linetmp = preg_replace('/^bcc:\s*/i', '', trim($line));
			$tmpbcc=preg_split("/[\s,;]+/", $linetmp);
			$nbbcc+=count($tmpbcc);
		}

		$reg = array();
		if (preg_match('/^from:\s.*<(.*)>/i', $line, $reg)) {
			$fromline .= trim($line)."\n";
			$emailfrom = $reg[1];
		} elseif (preg_match('/^from:\s+([^\s]*)/i', $line, $reg)) {
			$fromline .= trim($line)."\n";
			$emailfrom = trim($reg[1]);
		}

		if (preg_match('/^message-id:\s/i', $line)) {
			$messageidline .= trim($line)."\n";
		}

		if (preg_match('/^references:\s/i', $line)) {
			$referenceline .= trim($line)."\n";
		}

		if (preg_match('/^\-\-/', $line)) {
			// We found a symbol for a multipart section, so header is finished now, we can stop header analysis
			$headerend = 1;
		}
	}

	$mail .= $line;
}

$tmpfile='/tmp/phpsendmail-'.posix_getuid().'-'.getmypid().'.tmp';
@unlink($tmpfile);
file_put_contents($tmpfile, $mail);
chmod($tmpfile, 0660);

//* compose the sendmail command
// $command = 'echo ' . escapeshellarg($mail) . ' | '.$sendmail_bin.' -t -i ';
$command = 'cat '.$tmpfile.' | '.$sendmail_bin.' -t -i ';
$optionffound=0;
for ($i = 1; $i < $_SERVER['argc']; $i++) {
	if (preg_match('/-f/', $_SERVER['argv'][$i])) $optionffound++;
		$command .= escapeshellarg($_SERVER['argv'][$i]).' ';
}

if (! $optionffound) {
	file_put_contents($logfile, date('Y-m-d H:i:s') . ' option -f not found. Args are '.join(' ', $_SERVER['argv']).'. We get if from the header'."\n", FILE_APPEND);
	$command .= "'-f".$emailfrom."'";
}

$ip = empty($_SERVER["REMOTE_ADDR"]) ? '' : $_SERVER["REMOTE_ADDR"];
if (empty($ip)) {
	file_put_contents($logfile, date('Y-m-d H:i:s') . ' ip unknown. See tmp file '.$tmpfile."\n", FILE_APPEND);
	// exit(7);		// We do not exit, this can occurs sometime
}

// Count other existing file starting with '/tmp/phpsendmail-'.posix_getuid()
// and return error if nb is higher than 500
$commandcheck = 'find /tmp/phpsendmail-'.posix_getuid().'-* -mtime -1 | wc -l';

// Execute the command
// We need 'shell_exec' here that return all the result as string and not only first line like 'exec'
//$resexec = shell_exec("id");
//file_put_contents($logfile, date('Y-m-d H:i:s')." id = ".$resexec."\n", FILE_APPEND);
$resexec = shell_exec($commandcheck);
$resexec = (int) (empty($resexec) ? 0 : trim($resexec));

$MAXALLOWED = $MAXPERDAYPAID;
if ($usernamestring && $usernamestring != 'admin') {
	if (in_array($usernamestring, array_keys($instanceofuser))) {
		$MAXALLOWED = $instanceofuser[$usernamestring]['mailquota'];
	} else {
		$MAXALLOWED = $MAXPERDAY;
	}
}

file_put_contents($logfile, date('Y-m-d H:i:s')." Nb of processes found with ".$commandcheck." = ".$resexec." (we accept ".$MAXALLOWED.")\n", FILE_APPEND);

if ($resexec > $MAXALLOWED) {
	file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $ip . ' sellyoursaas rules ko quota reached - exit 6. User has reached its quota of '.$MAXALLOWED.".\n", FILE_APPEND);
	exit(6);
}


// Write the log
//file_put_contents($logfile, var_export($_SERVER, true)."\n", FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $toline, FILE_APPEND);
if ($ccline)  file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $ccline, FILE_APPEND);
if ($bccline) file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $bccline, FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $fromline, FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . ' Email detected into From: '. $emailfrom."\n", FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $messageidline, FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $referenceline, FILE_APPEND);
file_put_contents($logfile, date('Y-m-d H:i:s') . ' PWD=' . (empty($_ENV['PWD'])?(empty($_SERVER["PWD"])?'':$_SERVER["PWD"]):$_ENV['PWD'])." - REQUEST_URI=".(empty($_SERVER["REQUEST_URI"])?'':$_SERVER["REQUEST_URI"])."\n", FILE_APPEND);


// Check if IP is in blacklist
if (is_array($blacklistips) && in_array($ip, $blacklistips)) {
	file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $ip . ' sellyoursaas rules ko blacklist - exit 2. Blacklisted ip '.$ip." found into file ".$pathtospamdir."/blacklistip\n", FILE_APPEND);
	exit(3);
}


$blacklistoffroms = @file_get_contents($pathtospamdir.'/blacklistfrom');
if ($blacklistoffroms === false) {
	file_put_contents($logfile, date('Y-m-d H:i:s') . " ERROR $pathtospamdir/blacklistfrom can't be read.\n", FILE_APPEND);
} elseif (! empty($emailfrom)) {
	$blacklistoffromsarray = explode("\n", $blacklistoffroms);
	if (is_array($blacklistoffromsarray) && in_array($emailfrom, $blacklistoffromsarray)) {
		file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $ip . ' sellyoursaas rules ko blacklist - exit 3. Blacklisted from '.$emailfrom." found into file blacklistfrom\n", FILE_APPEND);
		exit(4);
	}
}

$blacklistofdirs = @file_get_contents($pathtospamdir.'/blacklistdir');
if ($blacklistofdirs === false) {
	file_put_contents($logfile, date('Y-m-d H:i:s') . " ERROR $pathtospamdir/blacklistdir can't be read.\n", FILE_APPEND);
} elseif (! empty($_SERVER["REQUEST_URI"])) {
	$blacklistofdirsarray = explode("\n", $blacklistofdirs);
	if (is_array($blacklistofdirsarray)) {
		foreach ($blacklistofdirsarray as $blacklistofdir) {
			if ($blacklistofdir && preg_match('/'.preg_quote(trim($blacklistofdir), '/').'/', $_SERVER["REQUEST_URI"])) {
				file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $ip . ' sellyoursaas rules ko blacklist - exit 7. Blacklisted dir '.$_SERVER["REQUEST_URI"]." contains blacklistdir key ".$blacklistofdir."\n", FILE_APPEND);
				exit(7);
			}
		}
	}
}

$blacklistofcontents = @file_get_contents($pathtospamdir.'/blacklistcontent');
if ($blacklistofcontents === false) {
	file_put_contents($logfile, date('Y-m-d H:i:s') . " ERROR $pathtospamdir/blacklistcontent can't be read.\n", FILE_APPEND);
} elseif (! empty($mail)) {
	//file_put_contents($logfile, date('Y-m-d H:i:s') . " blacklistofcontents = ".$blacklistofcontents."\n", FILE_APPEND);
	$blacklistofcontentsarray = explode("\n", $blacklistofcontents);
	foreach ($blacklistofcontentsarray as $blackcontent) {
		if (trim($blackcontent) && preg_match('/'.preg_quote(trim($blackcontent), '/').'/ims', $mail)) {
			file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $ip . ' sellyoursaas rules ko blacklist - exit 4. Blacklisted content has the key '.trim($blackcontent)." found into file blacklistcontent\n", FILE_APPEND);
			// Save spam mail content and ip
			file_put_contents($pathtospamdir.'/blacklistmail', $mail."\n", FILE_APPEND);
			chmod($pathtospamdir."/blacklistmail", 0666);

			// Add ip to blacklistip
			if (! empty($ip)) {
				file_put_contents($pathtospamdir.'/blacklistip', $ip."\n", FILE_APPEND);
				chmod($pathtospamdir."/blacklistip", 0666);
			}
			exit(5);
		}
	}
}

if (empty($fromline) && empty($emailfrom)) {
	file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $ip . ' cant send email - exit 1. From not provided. See tmp file '.$tmpfile."\n", FILE_APPEND);
	exit(1);
} elseif (($nbto + $nbcc + $nbbcc) > $MAXOK) {
	file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $ip . ' sellyoursaas rules ko toomanyrecipient - exit 2. ( >'.$MAXOK.' : ' . $nbto . ' ' . $nbcc . ' ' . $nbbcc . ' ) ' . (empty($_ENV['PWD'])?'':$_ENV['PWD'])."\n", FILE_APPEND);
	exit(2);
} else {
	file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $ip . ' sellyoursaas rules ok ( <'.$MAXOK.' : ' . $nbto . ' ' . $nbcc . ' ' . $nbbcc . ' - '.(empty($_SERVER["REQUEST_URI"])?'':$_SERVER["REQUEST_URI"]).' ) ' . (empty($_ENV['PWD'])?'':$_ENV['PWD'])."\n", FILE_APPEND);
}



file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' .$command."\n", FILE_APPEND);

// Execute the command to send email
// We need 'shell_exec' here that return all the result as string and not only first line like 'exec'
$resexec =  shell_exec($command);

if (empty($ip)) file_put_contents($logfile, "--- no ip detected ---", FILE_APPEND);
if (empty($ip)) file_put_contents($logfile, var_export($_SERVER, true), FILE_APPEND);
if (empty($ip)) file_put_contents($logfile, var_export($_ENV, true), FILE_APPEND);

time_nanosleep(0, 200000000);	// Add a delay to reduce effect of successfull spamming

return $resexec;




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
