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

tail -F $WDLOGFILE | grep --line-buffered 'UFW ALLOW' | 
while read -r line ; do
	export now=`date '+%Y-%m-%d %H:%M:%S'`
	echo "$now ----- start smtp_watchdog_daemon1" >> /var/log/phpsendmail.log 2>&1
	echo "Found a UFW ALLOW, in $WDLOGFILE, now try to find process owner..." >> /var/log/phpsendmail.log 2>&1
	echo "$line" >> /var/log/phpsendmail.log 2>&1

	export smtpipcaller=`echo $line | sed 's/.*SRC=//' | sed 's/\s.*//'`
	export smtpipcalled=`echo $line | sed 's/.*DST=//' | sed 's/\s.*//'`
	export smtpportcaller=`echo $line | sed 's/.*SPT=//' | sed 's/\s.*//'`
	export smtpportcalled=`echo $line | sed 's/.*DPT=//' | sed 's/\s.*//'`

	export processid="smtpsocket"
	export processownerid="xxxx"
	
	result=""
	if [ "x$smtpportcalled" != "x" ]; then
		if [ "x$smtpipcalled" != "x" ]; then
			export command="ss --oneline -e -H -p -t state all dport $smtpportcalled dst $smtpipcalled"
			echo "Execute command $command" >> /var/log/phpsendmail.log 2>&1
			result=`$command`
			if [ "x$result" != "x" ]; then
				echo "Extract processid from line" >> /var/log/phpsendmail.log 2>&1
				echo "$result" >> /var/log/phpsendmail.log 2>&1
				export processid=`echo "$result" | sed 's/.*pid=//' | sed 's/,.*//'`
				export processownerid=`echo "$result" | sed 's/.*uid://' | sed 's/\s.*//'`

				echo "We got processid=$processid" >> /var/log/phpsendmail.log 2>&1
				echo "We got processownerid=$processownerid" >> /var/log/phpsendmail.log 2>&1
				
				if [ "x$processownerid" != "x" ]; then
					if [[ $processownerid == ?(-)+([[:digit:]]) ]]; then
						# And now try to find the username of process id
						#export command="ps fauxwZ | grep $processid"
						#echo "Execute command $command" >> /var/log/phpsendmail.log 2>&1
						#ps fauxwZ | grep "$processid" | grep apache2 >> /var/log/phpsendmail.log 2>&1
						#ps fauxwZ >> /var/log/phpsendmail.log 2>&1
						
						export command="grep 'x:$processownerid:' /etc/passwd"
						export usernamestring=`$command`
					fi
				fi				
			fi
		fi
	fi

	# Build file for this email
	echo "Emails were sent using SMTP by process $processownerid" > "/tmp/phpsendmail-$processowner-$processid-smtpsocket.tmp"
	echo "SMTP server called by $smtpipcaller:$smtpportcaller is $smtpipcalled:$smtpportcalled" >> "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp"
	echo "$result" >> "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp"
	echo "$usernamestring" >> "/tmp/phpsendmail-$processownerid-$processid-smtpsocket.tmp"
	
	# Complete log /var/log/phpsendmail.log of all emails
	if [ "x$smtpportcalled" != "x" ]; then
		if [ "x$smtpipcalled" != "x" ]; then
			echo "$usernamestring" >> "/var/log/phpsendmail.log"
			#echo "smtp_watchdog_daemon1 has found an abusive smtp usage." | mail -aFrom:$EMAILFROM -s "[Warning] smtp_watchdog_daemon1 has found an abusive smtp usage on "`hostname`"." $EMAILTO
			#sleep 5
			export now=`date '+%Y%m%d%H%M%S'`
			
			# Test if we reached quota, if yes, ban SMTP port for IP
			echo "$now $smtpipcalled sellyoursaas rules ok" >> /var/log/phpsendmail.log
			#echo "$now $smtpipcalled sellyoursaas rules ko daily quota reached - we block SMTP ports. User has reached its daily quota of '.$MAXPERDAY >> /var/log/phpsendmail.log;
		fi
	fi
	
done

# This script never end
