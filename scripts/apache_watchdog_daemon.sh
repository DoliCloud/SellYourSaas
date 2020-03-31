#!/bin/bash

#
# apache watch dog daemon.
#

export now=`date '+%Y-%m-%d %H:%M:%S'`
export WDLOGFILE='/var/log/apache2/error.log'

export DOMAIN=`grep '^domain=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

echo >> /var/log/apache_watchdog.log
echo "**** ${0} started" >> /var/log/apache_watchdog.log
#echo $now" Try to detect an apache crash file in /var/crash" >> /var/log/apache_watchdog.log
echo $now" Try to detect lines 'AH00060: seg fault or similar nasty error detected in the parent process' into $WDLOGFILE" >> /var/log/apache_watchdog.log 

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

export EMAILFROM=support@$DOMAIN
export EMAILTO=supervision@$DOMAIN
export PID=${$}
export scriptdir=$(dirname $(realpath ${0}))

echo "DOMAIN=$DOMAIN" >> /var/log/apache_watchdog.log
echo "EMAILFROM=$EMAILFROM" >> /var/log/apache_watchdog.log
echo "EMAILTO=$EMAILTO" >> /var/log/apache_watchdog.log
echo "PID=$PID" >> /var/log/apache_watchdog.log

#while [ 1 ] ; do
#sleep 30
#if [ -f /var/crash/_usr_sbin_apache2.0.crash ] ; then
tail -F $WDLOGFILE | grep --line-buffered 'AH00060: seg fault or similar nasty error detected in the parent process' | 
while read ; do
    sleep 5
	export now=`date '+%Y-%m-%d %H:%M:%S'`
	echo "$now ----- Found a segfault, now kicking apache..." >> /var/log/apache_watchdog.log 2>&1
    sleep 5
	/etc/init.d/apache2 stop >> /var/log/apache_watchdog.log 2>&1
	sleep 5
	killall -9 apache2 >> /var/log/apache_watchdog.log 2>&1
	sleep 5
	export now=`date '+%Y-%m-%d %H:%M:%S'`
	echo "$now Now restart apache..." >> /var/log/apache_watchdog.log 2>&1
	/etc/init.d/apache2 start >> /var/log/apache_watchdog.log 2>&1
	
	echo "Apache seg fault detected. Apache was killed and started." | mail -aFrom:$EMAILFROM -s "[Alert] Apache seg fault detected on "`hostname`". Apache was killed and started." $EMAILTO
	sleep 5
	export now=`date '+%Y%m%d%H%M%S'`
	mv /var/crash/_usr_sbin_apache2.0.crash /var/crash/_usr_sbin_apache2.0.crash."$now" >> /var/log/apache_watchdog.log 2>&1
#fi
done

# This script never end
