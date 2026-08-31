#!/bin/bash
#----------------------------------------------------------------
# This script allows to deploy a module on instances of deployment servers
# using the ansible playbook launch_deployment_deploy_module.yml
#----------------------------------------------------------------


#set -e

source /etc/lsb-release

export RED='\033[0;31m'
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[0;33m'


if [ "x$7" == "x" ]; then
	echo "***** desktop_deployment_deploy_module.sh *****"
	echo "This script allows to deploy a module on instances of deployment servers"
	echo
	echo "Usage:   $0  hostsfile  target  command  productref  instancefilter  master_instance_id  [countrycode]"
	echo "         [hostsfile] is the ansible inventory file prefix (e.g. myhosts will use hosts-myhosts)"
	echo "         [target] is the host group or hostname to target"
	echo "         [command] is the command to execute (e.g. test, confirm)"
	echo "         [productref] is the module reference name"
	echo "         [instancefilter] is the instance filter (e.g. * for all instances)"
	echo "         [master_instance_id] is the master instance ID"
	echo "         [countrycode] is optional country code (e.g. FR)"
	echo
	echo "Example: $0  myhosts  deployment  test     REFMODULENAME  'abc*'  abc1234"
	echo "Example: $0  myhosts  deployment  confirm  REFMODULENAME  '*abc.mydomain.com'  abc1234  FR"
	echo
	exit 1
fi

hostsfile=$1
target=$2
command=$3
productref=$4
instancefilter=$5
master_instance_id=$6
countrycode=$7

if [ "x$countrycode" == "x" ]; then
	countrycode=""
fi

export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for inventory hosts-$hostsfile and target $target"
pwd

ansible_command="ansible-playbook -K launch_deployment_deploy_module.yml -i hosts-$hostsfile -e 'target=$target command=$command productref=$productref instancefilter=$instancefilter master_instance_id=$master_instance_id countrycode=$countrycode'"

echo "$ansible_command"
eval $ansible_command

echo "Finished."
