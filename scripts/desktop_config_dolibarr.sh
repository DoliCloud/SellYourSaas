#!/bin/bash
#----------------------------------------------------------------
# This script allows to update or fix the Dolibarr config file
# /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php
#----------------------------------------------------------------


#set -e

source /etc/lsb-release

export RED='\033[0;31m'
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[0;33m'


if [ "x$3" == "x" ]; then
	echo "***** desktop_config_dolibarr.sh *****"	
	echo "This script allows to update or fix the Dolibarr config file /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php"
	echo
	echo "Usage:   $0  hostfile  param  value  [hostgrouporname]"
	echo "         [hostgrouporname] can be 'master', 'deployment', 'web', 'remotebackup', or list separated with comma like 'master,deployment'"
	echo
	echo "Example: $0  myhostfile  dolibarr_main_data_root  '/home/admin/wwwroot/dolibarr_documents'   deployment"
	echo "Example: $0  myhostfile  dolibarr_main_data_root  '/home/admin/wwwroot/dolibarr_documents'   withX.mysellyoursaasdomain.com"
	echo
	exit 1
fi

param=$2
value=$3
target=$4

if [ "x$target" == "x" ]; then
	target="deployment"
fi


export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for host group $1 and targets $target"
pwd


#command="ansible-playbook -K change_config_dolibarr.yml -i hosts-$1 -e 'target=\"$target\" option=\"$param\" value=\"$value\"' --limit=*.mydomain.com"
command="ansible-playbook -K change_config_dolibarr.yml -i hosts-$1 -e 'target=\"$target\" option=\"$param\" value=\"$value\"'"

echo "$command"
eval $command

echo "Finished."


