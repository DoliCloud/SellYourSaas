#!/bin/bash
#----------------------------------------------------------------
# This script gets information on remote servers (read only) using the info_servers.yml playbook:
# OS and precise version, PHP version, users allowed to sudo as root and users allowed to sudo su - admin.
#----------------------------------------------------------------


#set -e

source /etc/lsb-release

export RED='\033[0;31m'
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[0;33m'


if [ "x$1" == "x" ]; then
   echo "***** Get information (OS, PHP, sudo users) on remote servers *****"
   echo "Usage:   $0  hostfile  [hostgrouporname]"
   echo "         [hostgrouporname] can be 'all' (default), 'master', 'deployment', 'web', 'remotebackup', or list separated with comma like 'master,deployment'"
   echo "Example: $0  myhostfile  master,deployment"
   echo "Example: $0  myhostfile  withX.mysellyoursaasdomain.com"
   exit 1
fi

target=$2
if [ "x$target" == "x" ]; then
	target="all"
fi

export currentpath=$(dirname "$0")

cd "$currentpath/ansible" || exit 1

echo "Execute ansible for host group $1 and targets $target"
pwd

command='ansible-playbook -K info_servers.yml -i hosts-'$1' -e "target='$target'"'

echo "$command"
eval $command

echo "Finished."
