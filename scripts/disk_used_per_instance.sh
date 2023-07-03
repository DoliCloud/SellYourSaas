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
export homedir=`grep '^homedir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
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

if [ "x$homedir" == "x" ]; then
	export homedir=/mnt/diskhome/home
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

echo "***** Disk used per instance (scan home dir duc.db file, containing the analysis of the content of backup dir)"

if [ "x$1" == "x" ]; then
	echo "Missing parameter - list|delete" 1>&2
	echo "Usage:   "`basename ${0}`" (list|update|delete) [osu...]"
	echo "Example: "`basename ${0}`" list"
	echo "         "`basename ${0}`" list osu123456"
	exit 1
fi

> /tmp/disk_used.tmp

for fic in `ls -A`; do
    if [ "x$2" != "x" ]; then
    	if [ "x$fic" != "x$2" ]; then
    		continue
    	fi
    fi
	if [ "x$1" == "xlist" ]; then
		duc info -b -a -d "$fic/.duc.db" >>/tmp/disk_used.tmp 2>&1;
	fi 
	if [ "x$1" == "xupdate" ]; then
		echo Update duc for $homedir/$fic
		duc index $homedir/$fic -x -m 3 -d "$homedir/$fic/.duc.db"
		chown $fic.$fic "$homedir/$fic/.duc.db"
	fi 
	if [ "x$1" == "xdelete" ]; then
		rm "$fic/.duc.db"
	fi
done

cat /tmp/disk_used.tmp | sed -e 's/Error opening:/YYYY-MM-DD HH:MM:SS 0 0 0/g' | grep -v "Date" | awk ' { if ($6) { print $5" octets "$6; } } ' | sort -n -r -k 1

echo 

exit 0
