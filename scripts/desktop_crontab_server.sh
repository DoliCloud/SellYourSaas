#!/bin/bash
#----------------------------------------------------------------
# This script allows to edit crontab on remote servers using the change_crontab_server.yml playbook.
#----------------------------------------------------------------


#set -e

source /etc/lsb-release

if [ "x$2" == "x" ]; then
   echo "***** Edit of crontab on remote servers *****"
   echo "Usage:   $0  hostfile  [hostgrouporname]"
   echo "         [hostgrouporname] can be 'master', 'deployment', 'web', 'remotebackup', or list separated with comma like 'master,deployment' (default)"
   echo "Example: $0  myhostfile  master,deployment"
   echo "Example: $0  myhostfile  withX.mysellyoursaasdomain.com  reboot"
   exit 1
fi

target=$2
if [ "x$target" == "x" ]; then
	target="master"
fi

username=$3
if [ "x$username" == "x" ]; then
	username="root"
fi

reboot=$4

export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for host group $1 and targets $target"
pwd


command='ansible-playbook -K change_crontab_server.yml -i hosts-'$1' -e "target='$target' username='$username'"' 

echo "$command"
eval $command

echo "Finished."


