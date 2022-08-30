#!/usr/bin/php
<?php
//
// smtp watch dog daemon 1.
//

$logfile = '/var/log/smtp_watchdog1.log';
$logphpsendmail = '/var/log/phpsendmail.log';
$WDLOGFILE='/var/log/ufw.log';


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

$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
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

file_put_contents($logfile, date('Y-m-d H:i:s') . "**** ${0} started");
file_put_contents($logfile, date('Y-m-d H:i:s') . " Try to detect a smtp connexion from $WDLOGFILE and log who do it");

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

file_put_contents($logfile, date('Y-m-d H:i:s') . "DOMAIN=$DOMAIN");
file_put_contents($logfile, date('Y-m-d H:i:s') . "EMAILFROM=$EMAILFROM");
file_put_contents($logfile, date('Y-m-d H:i:s') . "EMAILTO=$EMAILTO");
file_put_contents($logfile, date('Y-m-d H:i:s') . "PID=$PID");
file_put_contents($logfile, date('Y-m-d H:i:s') . "Now event captured will be logged into /var/log/phpsendmail.log");

$re='^[0-9]+$';


/**
 * getInstancesOfUser()
 *
 * @param	string	$pathtospamdir		Spam dir
 * @return	array						Array of instances in mailquota (so paid instances)
 */
function getInstancesOfUser($pathtospamdir)
{
	$instanceofuser = array();
	// Loop on each line of $pathtospamdir/mailquota
	$fp = @fopen($pathtospamdir."/mailquota", "r");
	if ($fp) {
		while (($buffer = fgets($fp, 1024)) !== false) {
			$reg = array();
			if (preg_match('\sid=(\d+)\sref=([^\s]+)\sosu=([^\s]+)\smailquota=(\d+)', $buffer, $reg)) {
				$instanceofuser[$reg[1]] = array('id'=>$reg[1], 'ref='=>$reg[2], 'osu'=>$reg[3]);
			}
		}
		if (!feof($fp)) {
			echo "Erreur: fgets() a échoué\n";
		}
		fclose($fp);
	}
	return $instanceofuser;
}


// Load $instanceofuser
$instanceofuser = getInstancesOfUser($pathtospamdir);


$handle = popen("tail -F ".$WDLOGFILE." | grep --line-buffered 'UFW ALLOW'", 'r');
while (!feof($handle)) {
	$line = fgets($handle);
	flush();

	$remoteip='unknown';
	$usernamestring="";
	$processownerid="";
	$processid="";
	$apachestring="";

	file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ----- start smtp_watchdog_daemon1.php");
	file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " Found a UFW ALLOW, in $WDLOGFILE, now try to find the process IPs and ports...");
	file_put_contents($logphpsendmail, date('Y-m-d H:i:s') . " ".$line);

	$smtpipcaller=preg_replace('/\s.*/', '', preg_replace('/.*SRC=/', '', $line));
	$smtpipcalled=preg_replace('/\s.*/', '', preg_replace('/.*DST=/', '', $line));
	$smtpportcaller=preg_replace('/\s.*/', '', preg_replace('/.*SPT=/', '', $line));
	$smtpportcalled=preg_replace('/\s.*/', '', preg_replace('/.*DPT=/', '', $line));

	$result = "";

	// TODO Call this sometimes only
	//$instanceofuser = getInstancesOfUser();
}
pclose($handle);


?>
	if [ "x$smtpportcalled" != "x" ]; then
		if [ "x$smtpipcalled" != "x" ]; then
			export command="ss --oneline -e -H -p -t state all dport $smtpportcalled dst [$smtpipcalled]"
			echo "$now Execute command $command" >> /var/log/phpsendmail.log 2>&1
			result=`$command`
			if [ "x$result" != "x" ]; then
				export now=`date '+%Y-%m-%d %H:%M:%S'`
				echo "$now Extract processid from line" >> /var/log/phpsendmail.log 2>&1
				echo "$now $result" >> /var/log/phpsendmail.log 2>&1
				export processownerid=`echo "$result" | grep -m 1 'ESTAB\|SYN-SENT' | grep 'uid:' | sed 's/.*uid://' | sed 's/\s.*//'`
				export processid=`echo "$result" | grep -m 1 'ESTAB\|SYN-SENT' | grep 'pid=' | sed 's/.*pid=//' | sed 's/,.*//'`

				export now=`date '+%Y-%m-%d %H:%M:%S'`
				echo "$now We got processid=${processid}, processownerid=${processownerid}" >> /var/log/phpsendmail.log 2>&1
				#ps fauxw >> /var/log/phpsendmail.log 2>&1

				if [ "x$processid" != "x" ]; then
					if [[ $processownerid =~ $re ]] ; then
						echo "$now We got the processownerid from the ss command, surely an email sent from a web page" >> /var/log/phpsendmail.log 2>&1
					else
						echo "$now We did not get the processownerid from the ss command. We try to get the processownerid using the processid from ps" >> /var/log/phpsendmail.log 2>&1

						export processownerid=`ps -faxW -o "uid,pid,cpu,start,time,cmd" | grep --color=never " $processid " | grep -n "color=never" | awk '{ print $1 }'`
						echo "$now processownerid=$processownerid" >> "/var/log/phpsendmail.log"
					fi

					if [[ $processid =~ $re ]] ; then
						echo "$now We try to get the apache process info" >> /var/log/phpsendmail.log 2>&1
						#echo "/usr/bin/lynx -dump -width 500 http://127.0.0.1/server-status | grep \" $processid \"" >> /var/log/phpsendmail.log 2>&1

						# Add a sleep to increase hope to have line "... client ip - pid ..." written into other_vhosts_pid.log file
						sleep 1
						echo "$now tail -n 200 /var/log/apache2/other_vhosts_pid.log | grep -m 1 \" $processid \"" >> /var/log/phpsendmail.log 2>&1

						#export apachestring=`/usr/bin/lynx -dump -width 500 http://127.0.0.1/server-status | grep -m 1 " $processid "`
						export apachestring=`tail -n 200 /var/log/apache2/other_vhosts_pid.log | grep -m 1 " $processid "`
						export now=`date '+%Y-%m-%d %H:%M:%S'`
						echo "$now apachestring=$apachestring" >> "/var/log/phpsendmail.log"

						# Try to guess remoteip
						if [[ "x$apachestring" != "x" ]] ; then
							export remoteip=`echo $apachestring | awk '{print $2}'`
							echo "$now remoteip=$remoteip" >> "/var/log/phpsendmail.log"

							# Test IP
							# If ipquality shows it is tor ip (has tor or active_tor on), we refuse and we add ip into blacklistip
							# If ipquality shows it is a vpn (vpn or active_vpn on), if fraud_score > SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED, we refuse and we add into blacklist
							#echo "$new $remoteip sellyoursaas rules ko blacklist - IP found into blacklistip of IPQualityScore" >> /var/log/phpsendmail.log
							#
						fi
					else
						echo "$now processid not valid, we can't find apache and remoteip data" >> "/var/log/phpsendmail.log"
					fi
				fi

				if [[ $processownerid =~ $re ]] ; then
					echo "$now We try to get the usernamestring from processownerid" >> /var/log/phpsendmail.log 2>&1

					export usernamestring=`grep "x:$processownerid:" /etc/passwd | cut -f1 -d:`
					echo "$now usernamestring=$usernamestring" >> "/var/log/phpsendmail.log"

					#TODO Get quota of emails MAXPERDAY for the UID $processownerid / $usernamestring


				else
					echo "$now processownerid not valid, we can't find $usernamestring" >> "/var/log/phpsendmail.log"
				fi
			fi
		fi
	fi

	# Build file /tmp/phpsendmail-... for this email
	if [ "x$processid" == "x" ]; then
		rm "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp" >/dev/null 2>&1
		> "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp"
		chmod ug+rw "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp"
	else
		rm "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp" >/dev/null 2>&1
		> "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp"
	fi

	echo "Emails were sent using SMTP by processid=$processid processownerid=$processownerid smtpportcaller=$smtpportcaller" >> "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp"
	echo "SMTP connection from $smtpipcaller:$smtpportcaller -> $smtpipcalled:$smtpportcalled" >> "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp"
	echo "$result" >> "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp"
	echo "usernamestring=$usernamestring" >> "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp"
	echo "apachestring=$apachestring" >> "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp"
	echo "remoteip=$remoteip" >> "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp"

	export now=`date '+%Y-%m-%d %H:%M:%S'`

	if [[ "x$usernamestring" =~ ^xosu.* ]]; then
		chown $usernamestring.$usernamestring "/tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp" 2>&1
	fi

	echo "$now The file /tmp/phpsendmail-$processownerid-$processid-$smtpportcaller-smtpsocket.tmp has been generated" >> "/var/log/phpsendmail.log"

	# Complete log /var/log/phpsendmail.log for this smtp email
	if [[ $processownerid =~ $re ]] ; then

		# Test if we reached quota, if yes, discard the email and log it for fail2ban
		export resexec=`find /tmp/phpsendmail-$processownerid-* -mtime -1 | wc -l`

		export now=`date '+%Y-%m-%d %H:%M:%S'`
		echo "$now nb of process found with find /tmp/phpsendmail-$processownerid-* -mtime -1 | wc -l = $resexec (we accept $MAXPERDAY)" >> /var/log/phpsendmail.log

		if [ "x$remoteip" == "x" ]; then
			remoteip="unknown"
		fi

		if [ "x$remoteip" != "xunknown" ]; then
			# TODO Make a check of IP and URL from
			# $remoteip, $usernamestring, $smtpportcalled, $smtpipcalled, $apachestring

			export blacklistipfile="$pathtospamdir/blacklistip"
			if [ -s $blacklistipfile ]; then
				# If this looks an IP, we check if it is in blacklist
				export resexec2=`grep -m 1 "^$remoteip\$" $blacklistipfile`
				if [[ "x$resexec2" == "x$remoteip" ]]; then
					# We found the ip into the blacklistip
					echo "$now We found the IP $remoteip into blacklistip file $blacklistipfile" >> /var/log/phpsendmail.log
					echo "$new $remoteip sellyoursaas rules ko blacklist - IP found into blacklistip file $blacklistipfile" >> /var/log/phpsendmail.log
				else
					echo "$now IP $remoteip not found into blacklistip file $blacklistipfile" >> /var/log/phpsendmail.log
				fi
			fi
		fi

		if [[ $resexec -gt $MAXPERDAY ]]; then
			echo "$now $remoteip sellyoursaas rules ko daily quota reached. User has reached its daily quota of $MAXPERDAY" >> /var/log/phpsendmail.log
		else
			echo "$now $remoteip sellyoursaas rules ok" >> /var/log/phpsendmail.log
		fi
	fi

	#echo "smtp_watchdog_daemon1 has found an abusive smtp usage." | mail -aFrom:$EMAILFROM -s "[Warning] smtp_watchdog_daemon1 has found an abusive smtp usage on "`hostname`"." $EMAILTO



# This script never end
