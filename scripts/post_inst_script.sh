#!/usr/bin/env bash
#
# This script can be ran when installing a new server from an image as a post installation script
# so the new server does not executes scheduled task automatically (resulting of duplicated tasks
# with the tasks already executed by the original server). 
#

export now=`date +'%Y-%m-%d %H:%M:%S'`
export PID=${$}
export scriptdir=$(dirname $(realpath ${0}))

echo
echo "***** $0 *****"

if [ "$(id -u)" != "0" ]; then
	echo "This script must be run as root" 1>&2
	exit 100
fi

# Disable cron
echo "Stop cron begin"
echo "Stop cron begin" >>/var/log/post_inst_script.log

/etc/init.d/cron stop 2>&1 >>/var/log/post_inst_script.log
echo result = $? >>/var/log/post_inst_script.log

systemctl stop cron 2>&1 >>/var/log/post_inst_script.log
systemctl disable cron 2>&1 >>/var/log/post_inst_script.log

echo "Stop cron end"
echo "Stop cron end" >>/var/log/post_inst_script.log

# Disablepostfix
echo "Stop postfix begin"
echo "Stop postfix begin" >>/var/log/post_inst_script.log

/etc/init.d/postfix stop >>/var/log/post_inst_script.log
echo result = $? >>/var/log/post_inst_script.log

echo "Stop postfix end"
echo "Stop postfix end" >>/var/log/post_inst_script.log

# Disable ufw
echo "Stop ufw begin"
echo "Stop ufw begin" >>/var/log/post_inst_script.log

#/etc/init.d/ufw stop >>/var/log/post_inst_script.log
ufw disable >>/var/log/post_inst_script.log
echo result = $? >>/var/log/post_inst_script.log

echo "Stop ufw end"
echo "Stop ufw end" >>/var/log/post_inst_script.log

# Disable datadog
if [ -f /etc/init.d/datadog-agent ]; then
	sleep 1

	rm -f /etc/datadog-agent/datadog.yaml.disabled
	if [ -f /etc/datadog-agent/datadog.yaml ]; then
		mv /etc/datadog-agent/datadog.yaml  /etc/datadog-agent/datadog.yaml.disabled
	fi 
	echo "Stop datadog begin"
	echo "Stop datadog begin" >>/var/log/post_inst_script.log
	
	/etc/init.d/datadog-agent stop >>/var/log/post_inst_script.log
	echo result = $? >>/tmp/post_inst_script.log
	
	systemctl stop datadog-agent 2>&1 >>/var/log/post_inst_script.log
	systemctl disable datadog-agent 2>&1 >>/var/log/post_inst_script.log
	
	echo "Stop datadog end"
	echo "Stop datadog end" >>/var/log/post_inst_script.log
fi


exit 0
