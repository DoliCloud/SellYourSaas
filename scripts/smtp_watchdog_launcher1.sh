#!/bin/bash
### BEGIN INIT INFO
# Provides:          smtp_watchdog1
# Required-Start:    $local_fs $remote_fs $network $syslog $named
# Required-Stop:     $local_fs $remote_fs $network $syslog $named
# Default-Start:     2 3 4 5
# Default-Stop:      0 1 6
# Short-Description: Start/stop smtp_watchdog1
# Description:       Start/stop smtp_watchdog1, a watch dog for smtp usage.
### END INIT INFO

#
# Script to launch smtp watch dog.
#

export now=`date +'%Y-%m-%d %H:%M:%S'`

echo
echo "**** ${0}"
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

export PID=${$}
export scriptdir=$(dirname $(realpath ${0}))
export dolibarrdir=`grep '^dolibarrdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

if [ "x$1" == "x" ]; then
	echo "Usage: ${0##*/} start|stop|status"
fi

if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

if [ "x$1" == "xstart" ]; then
	#iptables -I OUTPUT 1 -p tcp -m multiport --dports 25,2525,465,587 -m state --state NEW -j LOG --log-uid --log-prefix  "[UFW ALLOW SELLYOURSAAS] "
	#ip6tables -I OUTPUT 1 -p tcp -m multiport --dports 25,2525,465,587 -m state --state NEW -j LOG --log-uid --log-prefix  "[UFW ALLOW SELLYOURSAAS] "

	pid=`ps ax | grep 'smtp_watchdog_daemon1' | grep -v grep | awk ' { print $1 } '`
	if [ "x$pid" == "x" ]; then
		echo Switch on directory $scriptdir
		cd $scriptdir
		
		#./smtp_watchdog_daemon1.sh 2>&1 &
		if [ "x$dolibarrdir" != "x" ]; then
			echo "Launch $dolibarrdir/htdocs/custom/sellyoursaas/scripts/smtp_watchdog_daemon1.php"
			"${dolibarrdir}/htdocs/custom/sellyoursaas/scripts/smtp_watchdog_daemon1.php" 2>&1 &
		else
			echo Launch /home/admin/wwwroot/dolibarr/htdocs/custom/sellyoursaas/scripts/smtp_watchdog_daemon1.php
			/home/admin/wwwroot/dolibarr/htdocs/custom/sellyoursaas/scripts/smtp_watchdog_daemon1.php 2>&1 &
		fi
		echo "smtp_watchdog_daemon1 started"
	else
		echo smtp_watchdog_daemon1 is already running with PID $pid
	fi
fi

if [ "x$1" == "xstop" ]; then
		# Kill the tail process launched by the daemon
        pid=`ps ax | grep 'tail' | grep '/var/log/ufw.log' | grep -v grep | awk ' { print $1 } '`
        if [ "x$pid" == "x" ]; then
                echo smtp_watchdog_daemon1 "tail" process not started
        else
                echo Launch kill to stop "tail" process with PID $pid
                kill $pid
        fi
        # Kill the process of daemon
        pid=`ps ax | grep 'smtp_watchdog_daemon1' | grep -v grep | awk ' { print $1 } '`
        if [ "x$pid" == "x" ]; then
                echo smtp_watchdog_daemon1 not started
        else
                echo Launch kill to stop smtp_watchdog_daemon1 with PID $pid
                kill $pid
        fi
fi

if [ "x$1" == "xstatus" ]; then

	pid=`ps ax | grep 'smtp_watchdog_daemon1' | grep -v grep | awk ' { print $1 } '`
	if [ "x$pid" == "x" ]; then
		echo smtp_watchdog_daemon1 not started
	else
		echo smtp_watchdog_daemon1 run with PID $pid
	fi
	
fi
