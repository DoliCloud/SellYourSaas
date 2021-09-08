#!/bin/bash
# Purge data.
# This script must be run on a remote backup server.
#
# Put the following entry into your root cron
#40 4 4 * * /home/admin/wwwroot/dolibarr_sellyoursaas/scripts/clean.sh confirm

#set -e

source /etc/lsb-release

export now=`date +'%Y-%m-%d %H:%M:%S'`

echo
echo "**** ${0}"
echo "${0} ${@}"
echo "# user id --------> $(id -u)"
echo "# now ------------> $now"
echo "# PID ------------> ${$}"
echo "# PWD ------------> $PWD" 
echo "# arguments ------> ${@}"
echo "# path to me -----> ${0}"
echo "# parent path ----> ${0%/*}"
echo "# my name --------> ${0##*/}"
echo "# realname -------> $(realpath ${0})"
echo "# realname name --> $(basename $(realpath ${0}))"
echo "# realname dir ---> $(dirname $(realpath ${0}))"


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

export IPSERVERDEPLOYMENT=`grep '^ipserverdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

if [ "x$IPSERVERDEPLOYMENT" == "x" ]; then
   echo "Failed to find the IPSERVERDEPLOYMENT by reading entry 'ipserverdeployment=' into file /etc/sellyoursaas.conf" 1>&2
   exit 1
fi

if [ "x$1" == "x" ]; then
	echo "Missing parameter - test|confirm" 1>&2
	echo "Usage: ${0} [test|confirm] (tempdirs)"
	exit 2
fi



export usecompressformatforarchive=`grep '^usecompressformatforarchive=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

export testorconfirm=$1

# For debug
echo "testorconfirm = $testorconfirm"


# Now clean miscellaneous files
echo "***** Now clean miscellaneous files"
rm /var/log/repair.lock > /dev/null 2>&1

# Now clean old journalctl files
echo "***** Now clean journal files older than 60 days"
echo "find '/var/log/journal/*/user-*.journal' -type f -path '/var/log/journal/*/user-*.journal' -mtime +60 -exec rm -f {} \;"
find "/var/log/journal/" -type f -path '/var/log/journal/*/user-*.journal' -mtime +60 -exec rm -f {} \;


# Clean temporary dirs into backups 
if [ "x$2" == "xtempdirs" ]; then
	echo "Clean backup dir from not expected files (should not be required anymore)."
	echo "find '$backupdir' -type d -path '*/osu*/temp' -exec rm -fr {} \;"
	find "$backupdir" -type d -path '*/osu*/temp' -exec rm -fr {} \;
fi


echo

# Clean backup dir of instances that are now archived so no more backuped
> /tmp/deletedirs.sh
for fic in `find $backupdir/*/last_mysqldump* -name "last_mysqldump*" -mtime +90`
do
	noyoungfile=1
	dirtoscan=`dirname $fic`
	osusername=`basename $dirtoscan`
	for fic2 in `find $dirtoscan/last_mysqldump* -name "last_mysqldump*" -mtime -90`
	do
		noyoungfile=0
	done
	if [[ "x$noyoungfile" == "x1" ]]; then
		if [ -d "$backupdir/$osusername" ]; then
			echo "# ----- $fic - $noyoungfile - backup dir $backupdir/$osusername exists with only very old files, we can remove backup" >> /tmp/deletedirs.sh
			echo "rm -fr "`dirname $fic` >> /tmp/deletedirs.sh
		fi
	else
        echo "# ----- $fic - $noyoungfile - backup dir $dirtoscan exists with a very old last_mysqldump* file but was still active recently in backup. We must keep it." >> /tmp/deletedirs.sh
        echo "#rm -fr "`dirname $fic` >> /tmp/deletedirs.sh
	fi
done
if [ -s /tmp/deletedirs.sh ]; then
	echo TODO Manually...
	echo "***** We should also clean backup of paying instances in $backupdir/osusername/ that are no more saved since a long time (last_mysqldump > 90days) and that are archived" 
	echo You can execute commands into file /tmp/deletedirs.sh
	
	echo
fi


exit 0
