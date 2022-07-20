#!/bin/bash
### BEGIN INIT INFO
# Provides:          apache_watchdog1
# Required-Start:    $local_fs $remote_fs $network $syslog $named
# Required-Stop:     $local_fs $remote_fs $network $syslog $named
# Default-Start:     2 3 4 5
# Default-Stop:      0 1 6
# Short-Description: Start/stop apache_watchdog1
# Description:       Start/stop apache_watchdog1, a watch dog for apache.
### END INIT INFO

#
# Script to launch apache watch dog.
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

if [ "x$1" == "x" ]; then
	echo "Usage: ${0##*/} start|stop|status"
fi

if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

if [ "x$1" == "xstart" ]; then

	pid=`ps ax | grep 'apache_watchdog_daemon1' | grep -v grep | awk ' { print $1 } '`
	if [ "x$pid" == "x" ]; then
		echo Switch on directory $scriptdir
		cd $scriptdir
		
		echo "apache_watchdog_daemon1 started"
		./apache_watchdog_daemon1.sh 2>&1 &
		
	else
		echo apache_watchdog_daemon1 is already running with PID $pid
	fi
fi

if [ "x$1" == "xstop" ]; then
		# Kill the tail process launched by the daemon
        pid=`ps ax | grep 'tail' | grep '/var/log/apache2/error.log' | grep -v grep | awk ' { print $1 } '`
        if [ "x$pid" == "x" ]; then
                echo apache_watchdog_daemon1 "tail" process not started
        else
                echo Launch kill to stop "tail" process with PID $pid
                kill $pid
        fi
        # Kill the process of daemon
        pid=`ps ax | grep 'apache_watchdog_daemon1' | grep -v grep | awk ' { print $1 } '`
        if [ "x$pid" == "x" ]; then
                echo apache_watchdog_daemon1 not started
        else
                echo Launch kill to stop apache_watchdog_daemon1 with PID $pid
                kill $pid
        fi
fi

if [ "x$1" == "xstatus" ]; then

	pid=`ps ax | grep 'apache_watchdog_daemon1' | grep -v grep | awk ' { print $1 } '`
	if [ "x$pid" == "x" ]; then
		echo apache_watchdog_daemon1 not started
	else
		echo apache_watchdog_daemon1 run with PID $pid
	fi
	
fi
