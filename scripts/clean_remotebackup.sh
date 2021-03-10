#!/bin/bash
# Purge data.
# This script can be run on master or deployment servers.
#
# Put the following entry into your root cron
#40 4 4 * * /home/admin/wwwroot/dolibarr_sellyoursaas/scripts/clean.sh confirm

#set -e

export now=`date +%Y%m%d%H%M%S`

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
   exit 1
fi

# possibility to change the directory of instances are stored
export targetdir=`grep 'targetdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$targetdir" == "x" ]]; then
	export targetdir="/home/jail/home"
fi

export IPSERVERDEPLOYMENT=`grep '^ipserverdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export databasehost=`grep '^databasehost=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export database=`grep '^database=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export databaseuser=`grep '^databaseuser=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export databaseport=`grep '^databaseport=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$databaseport" == "x" ]]; then
	databaseport="3306"
fi

if [ "x$IPSERVERDEPLOYMENT" == "x" ]; then
   echo "Failed to find the IPSERVERDEPLOYMENT by reading entry 'ipserverdeployment=' into file /etc/sellyoursaas.conf" 1>&2
   exit 1
fi

if [ "x$database" == "x" ]; then
    echo "Failed to find the DATABASE by reading entry 'database=' into file /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} [test|confirm]"
	exit 1
fi
if [ "x$databasehost" == "x" ]; then
    echo "Failed to find the DATABASEHOST by reading entry 'databasehost=' into file /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} [test|confirm]"
	exit 1
fi
if [ "x$databaseuser" == "x" ]; then
    echo "Failed to find the DATABASEUSER by reading entry 'databaseuser=' into file /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} [test|confirm]"
	exit 1
fi
echo "Search sellyoursaas database credential in /etc/sellyoursaas.conf"
databasepass=`grep 'databasepass=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$databasepass" == "x" ]]; then
	echo Failed to get password for mysql user sellyoursaas 
	exit 1
fi

if [ "x$1" == "x" ]; then
	echo "Missing parameter - test|confirm" 1>&2
	echo "Usage: ${0} [test|confirm] (tempdirs)"
	exit 1
fi

echo "Search database server name and port for deployment server in /etc/sellyoursaas.conf"
export databasehostdeployment=`grep 'databasehostdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$databasehostdeployment" == "x" ]]; then
	databasehostdeployment="localhost"
fi 
export databaseportdeployment=`grep 'databaseportdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$databaseportdeployment" == "x" ]]; then
	databaseportdeployment="3306"
fi
echo "Search admin database credential for deployement server in /etc/sellyoursaas.conf"
export databaseuserdeployment=`grep 'databaseuserdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$databaseuserdeployment" == "x" ]]; then
	databaseuserdeployment=$databaseuser
fi
databasepassdeployment=`grep 'databasepassdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$databasepassdeployment" == "x" ]]; then
	databasepassdeployment=$databasepass
fi 

dnsserver=`grep 'dnsserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$dnsserver" == "x" ]]; then
	echo Failed to get dns server parameters 
	exit 1
fi

export testorconfirm=$1

# For debug
echo "database = $database"
echo "testorconfirm = $testorconfirm"


# Now clean miscellaneous files
echo "***** Now clean miscellaneous files"
rm /var/log/repair.lock > /dev/null 2>&1

# Now clean old journalctl files
echo "***** Now clean journal files older than 60 days"
echo "find '/var/log/journal/*/user-*.journal' -type f -path '/var/log/journal/*/user-*.journal' -mtime +60 -exec rm -f {} \;"
find "/var/log/journal/" -type f -path '/var/log/journal/*/user-*.journal' -mtime +60 -exec rm -f {} \;


# Clean archives 
if [ "x$2" == "xtempdirs" ]; then
	echo "Clean backup dir from not expected files (should not be required anymore)."
	echo "find '$backupdir' -type d -path '*/osu*/temp' -exec rm -fr {} \;"
	find "$backupdir" -type d -path '*/osu*/temp' -exec rm -fr {} \;
fi


echo
echo TODO Manually...

# Clean backup dir of instances that are now archived
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
			echo "# $fic - $noyoungfile - backup dir $backupdir/$osusername exists, we can remove backup" >> /tmp/deletedirs.sh
			echo "rm -fr "`dirname $fic` >> /tmp/deletedirs.sh
		fi
	else
        echo "# $fic - $noyoungfile - backup dir $dirtoscan exists with a very old last_mysqldump* file but was still active recently in backup. We must keep it." >> /tmp/deletedirs.sh
        echo "#rm -fr "`dirname $fic` >> /tmp/deletedirs.sh
	fi
done
if [ -s /tmp/deletedirs.sh ]; then
	echo "***** We should also clean backup of paying instances in $backupdir/osusername/ that are no more saved since a long time (last_mysqldump > 90days) and that are archived" 
	echo You can execute commands into file /tmp/deletedirs.sh
fi


exit 0
