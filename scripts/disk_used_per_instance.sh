#!/bin/bash
# Purge data.
# This script can be run on master or deployment servers.
#
# Put the following entry into your root cron
#40 4 4 * * /home/admin/wwwroot/dolibarr_sellyoursaas/scripts/clean.sh confirm

#set -e

source /etc/lsb-release

export now=`date +'%Y-%m-%d %H:%M:%S'`

#echo
#echo "**** ${0}"
#echo "${0} ${@}"
#echo "# user id --------> $(id -u)"
#echo "# now ------------> $now"
#echo "# PID ------------> ${$}"
#echo "# PWD ------------> $PWD" 
#echo "# arguments ------> ${@}"
#echo "# path to me -----> ${0}"
#echo "# parent path ----> ${0%/*}"
#echo "# my name --------> ${0##*/}"
#echo "# realname -------> $(realpath ${0})"
#echo "# realname name --> $(basename $(realpath ${0}))"
#echo "# realname dir ---> $(dirname $(realpath ${0}))"

export PID=${$}
export scriptdir=$(dirname $(realpath ${0}))				
export backupdir=`grep '^backupdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export archivedirtest=`grep '^archivedirtest=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export archivedirpaid=`grep '^archivedirpaid=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export archivedirbind="/etc/bind/archives"
export archivedircron="/var/spool/cron/crontabs.disabled"



if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 100
fi

# possibility to change the directory of instances are stored
export targetdir=`grep '^targetdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$targetdir" == "x" ]]; then
	export targetdir="/home/jail/home"
fi

cd $targetdir;

#if [[ "x$archivedirtest" == "x" ]]; then
    #echo "Failed to find the archivedirtest value by reading entry 'archivedirtest=' into file /etc/sellyoursaas.conf" 1>&2
	#echo "Usage: ${0} [test|confirm]"
	#exit 31
#fi
#if [[ "x$archivedirpaid" == "x" ]]; then
    #echo "Failed to find the archivedirpaid value by reading entry 'archivedirpaid=' into file /etc/sellyoursaas.conf" 1>&2
	#echo "Usage: ${0} [test|confirm]"
	#exit 31
#fi

#if [ "x$1" == "x" ]; then
	#echo "Missing parameter - test|confirm" 1>&2
	#echo "Usage: ${0} [test|confirm] (oldtempinarchive)"
	#echo "With mode test, the /temp/... files are not deleted at end of script" 
	#exit 6
#fi

echo "***** Report disk used per instance"

> /tmp/disk_used.tmp

for fic in `ls -A`; do duc info -b -a -d $fic/.duc.db >> /tmp/disk_used.tmp; done

cat /tmp/disk_used.tmp | grep -v "Date" | awk ' { print $5" "$6; } ' | sort -n -r -k 1

echo 

exit 0
