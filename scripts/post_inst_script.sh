#!/usr/bin/env bash
#
# This script can be ran when installing a new server from an image as a post installation script
# so the new server does not executes scheduled task automatically (resulting of a duplicate task
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

echo "Disable cron begin"
echo "Disable cron begin" >>/tmp/post_inst_script.log

/etc/init.d/cron stop 2>&1 >>/tmp/post_inst_script.log
echo result = $? >>/tmp/post_inst_script.log

systemctl stop cron 2>&1 >>/tmp/post_inst_script.log
systemctl disable cron 2>&1 >>/tmp/post_inst_script.log

echo "Disable cron end"
echo "Disable cron end" >>/tmp/post_inst_script.log

echo "Stop postfix begin"
echo "Stop postfix begin" >>/tmp/post_inst_script.log

/etc/init.d/postfix stop

echo "Stop postfix end"
echo "Stop postfix end" >>/tmp/post_inst_script.log

exit 0
