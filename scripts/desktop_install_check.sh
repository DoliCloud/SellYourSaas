#!/bin/bash
#----------------------------------------------------------------
# This script allows to launch an apt update / upgrade on remote servers
#----------------------------------------------------------------


#set -e

source /etc/lsb-release

if [ "x$1" == "x" ]; then
   echo "Usage:   $0  hostfile  [hostgrouporname]  (php74)"
   echo "         [hostgrouporname] can be 'master', 'deployment', 'web', 'remotebackup', or list separated with comma like 'master,deployment' (default)"
   echo "Example: $0  myhostfile  master,deployment"
   echo "Example: $0  myhostfile  withX.mysellyoursaasdomain.com"
   exit 1
fi

target=$2
if [ "x$target" == "x" ]; then
	target="master"
fi

if [ "x$3" == "xphp74" ]; then
	php74=1
fi


export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for host group $1 and targets $target"
pwd


#command="ansible-playbook -K launch_install_check.yml -i hosts-$1 -e 'target="$target"' --limit=*.mydomain.com"

if [ "x$php74" == "x" ]; then
	command='ansible-playbook -K launch_install_check.yml -i hosts-'$1' -e "target='$target'"'
else
	command='ansible-playbook -K launch_install_check.yml -i hosts-'$1' -e "target='$target' php74='1'"'
fi
echo "$command"
eval $command

echo "Finished."
