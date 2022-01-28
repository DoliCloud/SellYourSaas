#!/bin/bash
#----------------------------------------------------------------
# This script allows to launch an apt update / upgrade on remote servers
#----------------------------------------------------------------


#set -e

source /etc/lsb-release

if [ "x$2" == "x" ]; then
   echo "Usage:   $0  hostfile  [hostgrouporname]"
   echo "         [hostgrouporname] can be 'master', 'deployment', 'web', 'remotebackup', or list separated with comma like 'master,deployment' (default)"
   echo "Example: $0  myhostfile  master,deployment"
   echo "Example: $0  myhostfile  withXmysellyoursaasdomain.com"
   exit 1
fi

target=$2
if [ "x$target" == "x" ]; then
	target="master"
fi

export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for host group $1 and targets $2"
pwd


#command="ansible-playbook -K launch_apt_upgrade.yml -i hosts-$1 -e 'target="$target"' --limit=*.mydomain.com"
command='ansible-playbook -K launch_apt_upgrade.yml -i hosts-'$1' -e "target='$target' command='confirm'"'
echo "$command"
eval $command

echo "Finished."


