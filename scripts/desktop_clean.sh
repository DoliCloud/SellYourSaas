#!/bin/bash
#---------------------------------------------------------
# Script to run remotely the script clean.sh
#
# /pathto/desktop_clean.sh hostfile [hostgrouporname]
#---------------------------------------------------------

#set -e

source /etc/lsb-release

if [ "x$2" == "x" ]; then
   echo "***** Launch the script clean.sh on remote servers *****"
   echo "Usage:   $0  hostfile  [hostgrouporname]"
   echo "         [hostgrouporname] can be 'master', 'deployment', 'web', 'remotebackup', or list separated with comma like 'master,deployment'"
   echo "Example: $0  myhostfile  deployment"
   echo "Example: $0  myhostfile  withX.mysellyoursaasdomain.com"
   exit 1
fi

target=$2
if [ "x$target" == "x" ]; then
	target="master,deployment"
fi

export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for host group $1 and targets $target"
pwd


#command="ansible-playbook -K launch_clean.yml -i hosts-$1 -e 'target="$target"' --limit=*.mydomain.com"
command='ansible-playbook -K launch_clean.yml -i hosts-'$1' -e "target='$target' command='confirm'"'
echo "$command"
eval $command

echo "Finished."
