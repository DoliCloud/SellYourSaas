#!/bin/bash
#----------------------------------------------------------------
# This script allows to update or fix the SellYourSaas config files saved as:
# /etc/sellyoursaas.conf OR the /etc/sellyoursaas-public.conf
#----------------------------------------------------------------


#set -e

source /etc/lsb-release

export RED='\033[0;31m'
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[0;33m'


if [ "x$3" == "x" -o "x$4" == "x" ]; then
	echo "***** desktop_config_sellyoursaas.sh *****"	
	echo "This script allows to update or fix the SellYourSaas config file /etc/sellyoursaas[-public].conf"
	echo
	echo "Usage:   $0  hostsfile  hostgrouporname  param  value  [sellyoursaas|sellyoursaas-public]"
	echo "         [hostgrouporname] can be 'master', 'deployment', 'web', 'remotebackup', or list separated with comma like 'master,deployment' (default)"
	echo "         [sellyoursaasfile] can be 'sellyoursaas' (default) or 'sellyoursaas-public'"
	echo
	echo "Example: $0  myhostsfile  master,deployment  usecompressformatforarchive  zstd"
	echo "Example: $0  myhostsfile  master,deployment  remotebackupserverport  22"
	echo "Example: $0  myhostsfile  withX.mysellyoursaasdomain.com  maxemailperday  500  sellyoursaas-public"
	echo
	exit 1
fi

target=$2
param=$3
value=$4
nameofconf=$5

if [ "x$nameofconf" == "x" ]; then
	nameofconf="sellyoursaas"
fi


export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for host group $1 and targets $target"
pwd


#command="ansible-playbook -K change_config_sellyoursaas.yml -i hosts-$1 -e 'nameofconf=\"$nameofconf\" target=\"$target\" option=\"$param\" value=\"$value\"' --limit=*.mydomain.com"
command="ansible-playbook -K change_config_sellyoursaas.yml -i hosts-$1 -e 'nameofconf=\"$nameofconf\" target=\"$target\" option=\"$param\" value=\"$value\"'"

echo "$command"
eval $command

echo "Finished."


