#!/bin/bash
#----------------------------------------------------------------
# This script allows to edit crontab on remote servers using the change_crontab_server.yml playbook.
#----------------------------------------------------------------


#set -e

source /etc/lsb-release

export RED='\033[0;31m'
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[0;33m'


if [ "x$3" == "x" ]; then
   echo "***** Get/Edit banned IP on a remote servers *****"
   echo "Usage:   $0  hostfile  [hostgrouporname] list|unban|ban  IP  [jail]]"
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

jail=$5

# Check if 'askvault' was passed as argument to use --ask-vault-pass instead of -K for become password
askvault=""
for arg in "$@"; do
	if [ "x$arg" == "xaskvault" ] || [ "x$arg" == "xaskvault=1" ]; then
		askvault="1"
	fi
done

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
if [ "x$action" == "xban" ]; then
	command='ansible-playbook -K bannedip_ban.yml -i hosts-'$1' -e "target='$target' ip='$ip' jail='$jail'"' 
fi

if [ "x$askvault" == "x1" ]; then
	command=${command/"-K"/"--ask-vault-pass"}
fi
echo "$command"
eval $command

echo "Finished."


