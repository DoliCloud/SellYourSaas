#!/bin/bash
#---------------------------------------------------------
# Script to run remotely the git_update_sellyour_saas.sh
#
# /pathto/git_update_sellyoursaas_remote.sh hostfile [hostgrouporname]
#---------------------------------------------------------

source /etc/lsb-release

if [ "x$1" == "x" ]; then
   echo "Usage:   $0  hostfile  [hostgrouporname]"
   echo "         [hostgrouporname] can be 'master', 'deployment', 'web', 'remotebackup', or list separated with comma like 'master,deployment' (default)"
   echo "Example: $0  myhostfile  master,deployment"
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

#command="ansible-playbook -K launch_git_update_sellyoursaas.yml -i hosts-$1 -e 'target="$target"' --limit=*.mydomain.com"
command='ansible-playbook -K launch_git_update_sellyoursaas.yml -i hosts-'$1' -e "target='$target'"'
echo "$command"
eval $command

echo "Finished."
