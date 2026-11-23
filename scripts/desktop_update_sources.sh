#!/bin/bash
#---------------------------------------------------------
# Script to run remotely the master_git_update_sources.sh, to update the git sources of the packages
# found into dolibarr_documents/sellyoursaas/git of the master and to rebuild their archives.
#
# /pathto/desktop_update_sources.sh hostfile [hostgrouporname] [subdir|all]
#---------------------------------------------------------

source /etc/lsb-release

export RED='\033[0;31m'
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[0;33m'


if [ "x$1" == "x" ]; then
   echo "Usage:   $0  hostfile  [hostgrouporname]  [subdir|all]"
   echo "         [hostgrouporname] must be 'master' (default), no other value is accepted"
   echo "         [subdir|all] is the name of the directory into dolibarr_documents/sellyoursaas/git to update, or 'all' (default)"
   echo "Example: $0  myhostfile  master"
   echo "Example: $0  myhostfile  master  dolibarr_x.y"
   exit 1
fi

target=$2
if [ "x$target" == "x" ]; then
	target="master"
fi
if [ "x$target" != "xmaster" ]; then
	echo -e "${RED}Error: the target must be 'master' (value '$target' is not allowed).\033[0m" 1>&2
	exit 1
fi

subdir=$3
if [ "x$subdir" == "x" ]; then
	subdir="all"
fi

export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for host group=$1 and targets=$target, subdir=$subdir"
pwd

command='ansible-playbook -K launch_git_update_sources.yml -i hosts-'$1' -e "target='$target' subdir='$subdir'"'
echo "$command"
eval $command

echo "Finished."
