#!/bin/bash
#---------------------------------------------------------
# Script to run remotely the script clean.sh
#
# /pathto/desktop_clean.sh hostfile [hostgrouporname]
#---------------------------------------------------------

#set -e

source /etc/lsb-release

export RED='\033[0;31m'
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[0;33m'


if [ "x$2" == "x" ]; then
   echo "***** Launch the script clean.sh on remote servers *****"
   echo "Usage:   $0  hostfile  [hostgrouporname]  [confirm|test]"
   echo "         [hostgrouporname] can be 'master', 'deployment', 'web', 'remotebackup', or list separated with comma like 'master,deployment'"
   echo "Example: $0  myhostfile  deployment"
   echo "Example: $0  myhostfile  withX.mysellyoursaasdomain.com  test"
   exit 1
fi

target=$2
if [ "x$target" == "x" ]; then
	target="master,deployment"
fi

test=$3

export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for host group $1 and targets $target"
pwd


#command="ansible-playbook -K launch_clean.yml -i hosts-$1 -e 'target="$target"' --limit=*.mydomain.com"
if [ "x$test" == "x" ]; then
	command='ansible-playbook -K launch_clean.yml -i hosts-'$1' -e "target='$target' command='confirm'"'
else
	command='ansible-playbook -K launch_clean.yml -i hosts-'$1' -e "target='$target' command='$test'"'
fi
echo "$command"
eval $command

echo "Finished."
