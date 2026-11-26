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


if [ "x$6" == "x" ]; then
	echo "***** desktop_deployment_deploy_module.sh *****"
	echo "This script allows to deploy a module on instances of deployment servers"
	echo
	echo "Usage:   $0  hostsfile  target  command  productref  instancefilter  master_instance_unique_id  [countrycode] [nocache]"
	echo "         [hostsfile] is the ansible inventory file prefix (e.g. myhosts will use hosts-myhosts)"
	echo "         [target] is the host group or hostname to target"
	echo "         [command] is the command to execute (e.g. test, confirm)"
	echo "         [productref] is the module reference name"
	echo "         [instancefilter] is the instance filter (e.g. * for all instances)"
	echo "         [master_instance_id] is the master instance ID. Use - to read it from the env var SELLYOURSAAS_MASTER_INSTANCE_ID"
	echo "                              or, if not defined, to be prompted for it (recommended: the secret is then not visible"
	echo "                              in the shell history nor in the process list)"
	echo "         [countrycode] is optional country code (e.g. FR)"
	echo "         [nocache] is optional. It forces the rebuild of the local archive used as the cached package to deploy"
	echo
	echo "Example: $0  myhosts  deployment  test     REFMODULENAME  'abc*'  -  FR"
	echo "Example: $0  myhosts  deployment  confirm  REFMODULENAME  '*abc.mydomain.com'  -  FR  nocache"
	echo
	exit 1
fi

hostsfile=$1
target=$2
command=$3
productref=$4
instancefilter=$5
master_instance_id=$6
countrycode=""
if [ "x$7" != "x" ]; then
	if [ "x$7" == "xnocache" ]; then
		nocache="nocache"
	else
		countrycode=$7
	fi
fi
if [ "x$8" != "x" ]; then
	if [ "x$8" == "xnocache" ]; then
		nocache="nocache"
	else
		countrycode=$8
	fi
fi

# Get the secret master_instance_id from env or prompt, so it is not visible in shell history nor in process list
if [ "x$master_instance_id" == "x-" ]; then
	if [ "x$SELLYOURSAAS_MASTER_INSTANCE_ID" != "x" ]; then
		master_instance_id=$SELLYOURSAAS_MASTER_INSTANCE_ID
	else
		read -r -s -p "Enter the master instance unique id (\$dolibarr_main_instance_unique_id of the master): " master_instance_id
		echo
	fi
else
	echo -e "${YELLOW}Warning: the master_instance_id given on command line is visible in your shell history. Use - instead.\033[0m"
fi
if [ "x$master_instance_id" == "x" ]; then
	echo "Error: master_instance_id is empty."
	exit 1
fi

# Pass the secret to ansible with a temporary vars file (mode 600) instead of the command line
secretvarsfile=$(mktemp)
trap 'rm -f "$secretvarsfile"' EXIT
printf "master_instance_id: '%s'\n" "${master_instance_id//\'/\'\'}" > "$secretvarsfile"

# Check if 'askvault' was passed as argument to use --ask-vault-pass instead of -K for become password
askvault=""
for arg in "$@"; do
	if [ "x$arg" == "xaskvault" ] || [ "x$arg" == "xaskvault=1" ]; then
		askvault="1"
	fi
done

export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for inventory hosts-$hostsfile and target $target"
pwd

ansible_command="ansible-playbook -K launch_deployment_deploy_module.yml -i hosts-$hostsfile -e 'target=$target command=$command productref=$productref instancefilter=$instancefilter countrycode=$countrycode nocache=$nocache' -e '@$secretvarsfile'"

if [ "x$askvault" == "x1" ]; then
	ansible_command=${ansible_command/"-K"/"--ask-vault-pass"}
fi
echo "$ansible_command"
eval $ansible_command

echo "Finished."
