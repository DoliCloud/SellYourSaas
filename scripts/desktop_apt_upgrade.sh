#!/bin/bash
#----------------------------------------------------------------
# This script allows to launch an apt update / upgrade on remote servers
#----------------------------------------------------------------


#set -e

source /etc/lsb-release

export RED='\033[0;31m'
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[0;33m'


if [ "x$2" == "x" ]; then
   echo "***** Execute an apt upgrade on remote servers. This switch all instances in maintenancemode. *****"
   echo "Usage:   $0  hostfile  [hostgrouporname]  (reboot)"
   echo "         [hostgrouporname] can be 'master', 'deployment', 'web', 'remotebackup', or list separated with comma like 'master,deployment' (default)"
   echo "Example: $0  myhostfile  master,deployment"
   echo "Example: $0  myhostfile  withX.mysellyoursaasdomain.com  reboot"
   exit 1
fi

target=$2
if [ "x$target" == "x" ]; then
	target="master"
fi

reboot=$3

export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for host group $1 and targets $target"
pwd

echo
echo "This script will switch all instances in maintenance mode and restore them after !"
echo "Press any key to start (or CTRL+C to cancel)..."
read -r answer

#command="ansible-playbook -K launch_apt_upgrade.yml -i hosts-$1 -e 'target="$target"' --limit=*.mydomain.com"

if [ "x$reboot" == "x" ]; then
	command='ansible-playbook -K launch_apt_upgrade.yml -i hosts-'$1' -e "target='$target'"'
else
	command='ansible-playbook -K launch_apt_upgrade.yml -i hosts-'$1' -e "target='$target' reboot='1'"'
fi
echo "$command"
eval $command


echo "Finished."
echo "Warning: Check that the web server process is still alive..."
echo

