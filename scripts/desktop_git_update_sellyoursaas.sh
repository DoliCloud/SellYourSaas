#!/bin/bash
#---------------------------------------------------------
# Script to run remotely the git_update_sellyour_saas.sh
#
# /pathto/git_update_sellyoursaas_remote.sh hostgroup [target]
#---------------------------------------------------------

source /etc/lsb-release

if [ "x$1" == "x" ]; then
   echo "Usage:   $0  hostgroup  [target]"
   echo "         [target] can be 'master', 'deployment', 'web', 'backup', or list separated with comma like 'master,deployment' (default)"
   echo "Example: $0  mygroup  master,deployment"
   echo "Example: $0  mygroup  withX.mysellyoursaasdomain.com"
   exit 1
fi

target=$2
if [ "x$target" == "x" ]; then
	target="master,deployment"
fi

export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for host group $1 and targets $2"
pwd

#command="ansible-playbook -K launch_git_update_sellyoursaas.yml -i hosts-$1 -e 'target="$target"' --limit=*.mydomain.com"
command='ansible-playbook -K launch_git_update_sellyoursaas.yml -i hosts-'$1' -e "target='$target'"'
echo "$command"
eval $command

echo "Finished."
