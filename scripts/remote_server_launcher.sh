#!/bin/bash
### BEGIN INIT INFO
# Provides:          remote_server_launcher
# Required-Start:    $local_fs $remote_fs $network $syslog $named
# Required-Stop:     $local_fs $remote_fs $network $syslog $named
# Default-Start:     2 3 4 5
# Default-Stop:      0 1 6
# Short-Description: Start/stop remote_server_launcher
# Description:       Start/stop remote_server_launcher, a daemon agent for
#                    sellyoursaas.
### END INIT INFO

#
# Script to launch SellyourSaas httpd daemon agent.
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

remoteserverlistenip=`grep '^remoteserverlistenip=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$remoteserverlistenip" == "x" ]]; then
	remoteserverlistenip="0.0.0.0"
fi
remoteserverlistenport=`grep '^remoteserverlistenport=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$remoteserverlistenport" == "x" ]]; then
	remoteserverlistenport="8080"
fi

if [ "x$1" == "xstart" ]; then
	#echo "socat TCP4-LISTEN:$remoteserverlistenport,fork EXEC:$scriptdir/remote_server.sh > /var/log/remote_server.log"
	#socat TCP4-LISTEN:$remoteserverlistenport,fork EXEC:$scriptdir/remote_server.sh & > /var/log/remote_server.log

	pid=$(ps ax | grep "php -S $remoteserverlistenip" | grep -v grep | awk ' { print $1 } ')
	if [ "x$pid" == "x" ]; then
		echo Switch on directory $scriptdir
		cd $scriptdir
		
		export phpversion=`php -v | head -n 1 | cut -c 5-7`
		export abc="remote_server/index.php"
		if [[ "x$phpversion" == "x7.0" ]]; then 
			export abc="index.php"
		fi
		
		php -S $remoteserverlistenip:$remoteserverlistenport -t remote_server $abc 2>&1 &
		echo "Server started with php -S $remoteserverlistenip:$remoteserverlistenport -t remote_server $abc"
		
		echo "Logs of server will be in /var/log/remote_server.log"
	else
		echo Server is already running with PID $pid
	fi
fi

if [ "x$1" == "xstop" ]; then
	#killall socat
	
	pid=$(ps ax | grep "php -S $remoteserverlistenip" | grep -v grep | awk ' { print $1 } ')
	if [ "x$pid" == "x" ]; then
		echo Server not started
	else
		echo Launch kill to stop server with PID $pid
		kill $pid
	fi
fi

if [ "x$1" == "xstatus" ]; then
	#killall socat
	
	pid=$(ps ax | grep "php -S $remoteserverlistenip" | grep -v grep | awk ' { print $1 } ')
	if [ "x$pid" == "x" ]; then
		echo Server not started
	else
		echo Server run with PID $pid
	fi
fi
