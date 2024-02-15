#!/bin/bash
#----------------------------------------------------------------
# This script allows to edit crontab on remote servers using the change_crontab_server.yml playbook.
#----------------------------------------------------------------


#set -e

source /etc/lsb-release

if [ "x$3" == "x" ]; then
   echo "***** Get/Edit banned IP on a remote servers *****"
   echo "Usage:   $0  hostfile  [hostgrouporname] list|unban  IP"
   echo "         [hostgrouporname] can be 'master', 'deployment', 'web', 'remotebackup', or list separated with comma like 'master,deployment' (default)"
   echo "Example: $0  myhostfile  master,deployment"
   echo "Example: $0  myhostfile  withX.mysellyoursaasdomain.com"
   exit 1
fi

target=$2
if [ "x$target" == "x" ]; then
	target="master"
fi

action=$3

ip=$4

export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for host group $1 and targets $target"
pwd

if [ "x$action" == "xlist" ]; then
	command='ansible-playbook -K bannedip_list.yml -i hosts-'$1' -e "target='$target'"' 
fi
if [ "x$action" == "xunban" ]; then
	command='ansible-playbook -K bannedip_unban.yml -i hosts-'$1' -e "target='$target' ip='$ip'"' 
fi

echo "$command"
eval $command

echo "Finished."


