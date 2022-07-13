#!/bin/bash

#
# smtp watch dog daemon 1.
#

export now=`date '+%Y-%m-%d %H:%M:%S'`
export WDLOGFILE='/var/log/ufw.log'

export DOMAIN=`grep '^domain=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

export MAXPERDAY=`grep '^maxemailperday=' /etc/sellyoursaas-public.conf | cut -d '=' -f 2`
if [ "x$MAXPERDAY" == "x" ]; then
	export MAXPERDAY=500
fi


echo >> /var/log/smtp_watchdog1.log
echo "**** ${0} started" >> /var/log/smtp_watchdog1.log
echo $now" Try to detect a smtp connexion from $WDLOGFILE and log who do it" >> /var/log/smtp_watchdog1.log 

#echo "${0} ${@}"
#echo "# User id --------> $(id -u)"
#echo "# Now ------------> $now"
#echo "# PID ------------> ${$}"
#echo "# PWD ------------> $PWD" 
#echo "# arguments ------> ${@}"
#echo "# path to me -----> ${0}"
#echo "# parent path ----> ${0%/*}"
#echo "# my name --------> ${0##*/}"
#echo "# realname -------> $(realpath ${0})"
#echo "# realname name --> $(basename $(realpath ${0}))"
#echo "# realname dir ---> $(dirname $(realpath ${0}))"

export EMAILFROM=`grep '^emailfrom=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export EMAILTO=`grep '^emailsupervision=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [ "x$EMAILFROM" == "x" ]; then
	export EMAILFROM=noreply@$DOMAIN
fi
if [ "x$EMAILTO" == "x" ]; then
	export EMAILTO=supervision@$DOMAIN
fi

export PID=${$}
export scriptdir=$(dirname $(realpath ${0}))

echo "DOMAIN=$DOMAIN" >> /var/log/smtp_watchdog1.log
echo "EMAILFROM=$EMAILFROM" >> /var/log/smtp_watchdog1.log
echo "EMAILTO=$EMAILTO" >> /var/log/smtp_watchdog1.log
echo "PID=$PID" >> /var/log/smtp_watchdog1.log
echo "Now event captured will be logged into /var/log/phpsendmail.log" >> /var/log/smtp_watchdog1.log

re='^[0-9]+$'

tail -F $WDLOGFILE | grep --line-buffered 'UFW ALLOW' | 
while read -r line ; do
	export now=`date '+%Y-%m-%d %H:%M:%S'`
	echo "$now ----- start smtp_watchdog_daemon1.sh" >> /var/log/phpsendmail.log 2>&1
	echo "$now Found a UFW ALLOW, in $WDLOGFILE, now try to find process IP and ports..." >> /var/log/phpsendmail.log 2>&1
	echo "$line" >> /var/log/phpsendmail.log 2>&1

	export remoteip='unknown'
	export smtpipcaller=`echo $line | sed 's/.*SRC=//' | sed 's/\s.*//'`
	export smtpipcalled=`echo $line | sed 's/.*DST=//' | sed 's/\s.*//'`
	export smtpportcaller=`echo $line | sed 's/.*SPT=//' | sed 's/\s.*//'`
	export smtpportcalled=`echo $line | sed 's/.*DPT=//' | sed 's/\s.*//'`

	export processid="smtpsocket"
	export processownerid="xxxx"
	
	result=""
	if [ "x$smtpportcalled" != "x" ]; then
		if [ "x$smtpipcalled" != "x" ]; then
			export command="ss --oneline -e -H -p -t state all dport $smtpportcalled dst [$smtpipcalled]"
			echo "Execute command $command" >> /var/log/phpsendmail.log 2>&1
			result=`$command`
			if [ "x$result" != "x" ]; then
				echo "Extract processid from line" >> /var/log/phpsendmail.log 2>&1
				echo "$result" >> /var/log/phpsendmail.log 2>&1
				export processid=`echo "$result" | grep ESTAB | sed 's/.*pid=//' | sed 's/,.*//'`
				export processownerid=`echo "$result" | grep ESTAB | sed 's/.*uid://' | sed 's/\s.*//'`

				export now=`date '+%Y-%m-%d %H:%M:%S'`
				echo "$now We got processid=${processid}, processownerid=${processownerid}" >> /var/log/phpsendmail.log 2>&1
				#ps fauxw >> /var/log/phpsendmail.log 2>&1
				
				if [ "x$processid" != "x" ]; then
					if [[ $processownerid =~ $re ]] ; then
						echo "We got the processownerid from the ss command, surely a web access" >> /var/log/phpsendmail.log 2>&1
					else
						echo "We did not get the processownerid from the ss command. We try to get the processownerid using the processid from ps" >> /var/log/phpsendmail.log 2>&1

						export processownerid=`ps -faxW -o "uid,pid,cpu,start,time,cmd" | grep --color=never " $processid " | grep -n "color=never" | awk '{ print $1 }'`
						echo "processownerid=$processownerid" >> "/var/log/phpsendmail.log"
					fi
										
					if [[ $processownerid =~ $re ]] ; then
						echo "We try to get the usernamestring from processownerid" >> /var/log/phpsendmail.log 2>&1
				
						export usernamestring=`grep "x:$processownerid:" /etc/passwd | cut -f1 -d:`
						echo "usernamestring=$usernamestring" >> "/var/log/phpsendmail.log"
					else
						echo "processownerid not valid, we can't find $usernamestring" >> "/var/log/phpsendmail.log"
					fi
						
					if [[ $processid =~ $re ]] ; then
						echo "We try to get the apache process info" >> /var/log/phpsendmail.log 2>&1
						#echo "/usr/bin/lynx -dump -width 500 http://127.0.0.1/server-status | grep \" $processid \"" >> /var/log/phpsendmail.log 2>&1
						echo "tail -n 200 /var/log/apache2/other_vhosts_pid.log | grep -m 1 \" $processid \"" >> /var/log/phpsendmail.log 2>&1
						
						#export apachestring=`/usr/bin/lynx -dump -width 500 http://127.0.0.1/server-status | grep -m 1 " $processid "`
						export apachestring=`tail -n 200 /var/log/apache2/other_vhosts_pid.log | grep -m 1 " $processid "`
                        echo "apachestring=$apachestring" >> "/var/log/phpsendmail.log"

                        # Try to guess remoteip
                        if [[ "x$apachestring" != "x" ]] ; then
                        	export remoteip=`echo $apachestring | awk '{print $2}'`
                            echo "remoteip=$remoteip" >> "/var/log/phpsendmail.log"
                        fi
                    else 
                    	echo "processid not valid, we can't find apache and remoteip data" >> "/var/log/phpsendmail.log"
					fi
				fi
			fi
		fi
	fi

	# Build file for this email
	> "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp"
	if [ "x$processid" == "x" ]; then
		chmod a+rw "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp"
	else
		> "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp"
	fi

	echo "Emails were sent using SMTP by process $processownerid" >> "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp"
	echo "SMTP connection from $smtpipcaller:$smtpportcaller -> $smtpipcalled:$smtpportcalled" >> "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp"
	echo "$result" >> "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp"
	echo "usernamestring=$usernamestring" >> "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp"
	echo "apachestring=$apachestring" >> "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp"
	echo "remoteip=$remoteip" >> "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp"
	if [[ "x$usernamestring" =~ ^xosu.* ]]; then
		chown $usernamestring.$usernamestring "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp" 2>&1
	fi
	
	# Complete log /var/log/phpsendmail.log of all emails
	if [[ $processownerid =~ $re ]] ; then
		export now=`date '+%Y%m%d%H%M%S'`
	
		# Test if we reached quota, if yes, discard the email and log it for fail2ban
		export resexec=`find /tmp/phpsendmail-$processownerid-* -mtime -1 | wc -l`
		echo "$now nb of process found with find /tmp/phpsendmail-$processownerid-* -mtime -1 | wc -l = $resexec (we accept $MAXPERDAY)" >> /var/log/phpsendmail.log
		
		if [ "x$remoteip" == "x" ]; then
			remoteip="unknown"
		fi
		if [[ $resexec -gt $MAXPERDAY ]]; then
			echo "$now $remoteip sellyoursaas rules ko daily quota reached. User has reached its daily quota of $MAXPERDAY" >> /var/log/phpsendmail.log
		else
			echo "$now $remoteip sellyoursaas rules ok" >> /var/log/phpsendmail.log
		fi
	fi

	#echo "smtp_watchdog_daemon1 has found an abusive smtp usage." | mail -aFrom:$EMAILFROM -s "[Warning] smtp_watchdog_daemon1 has found an abusive smtp usage on "`hostname`"." $EMAILTO

done

# This script never end
