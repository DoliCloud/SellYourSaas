#!/bin/bash
#----------------------------------------------------------------
# This script allows to update or fix the SellYourSaas config files
# /etc/sellyoursaas.conf OR the /etc/sellyoursaas-public.conf
#----------------------------------------------------------------


#set -e

source /etc/lsb-release

if [ "x$3" == "x" ]; then
   echo "Usage:   $0  hostsfile  param  value  [hostgrouporname]  [sellyoursaas|sellyoursaas-public]"
   echo "         [hostgrouporname] can be 'master', 'deployment', 'web', 'remotebackup', or list separated with comma like 'master,deployment' (default)"
   echo "         [sellyoursaasfile] can be 'sellyoursaas' (default) or 'sellyoursaas-public'"
   echo "Example: $0  myhostsfile  usecompressformatforarchive  zstd  master,deployment"
   echo "Example: $0  myhostsfile  remotebackupserverport  22  master,deployment"
   echo "Example: $0  myhostsfile  maxemailperday  500  withX.mysellyoursaasdomain.com  sellyoursaas-public"
   exit 1
fi

param=$2
value=$3
target=$4
nameofconf=$5

if [ "x$target" == "x" ]; then
	target="master"
fi
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


