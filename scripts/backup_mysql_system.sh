#!/bin/bash
# Purge data
#
# Put the following entry into your root cron
#40 4 4 * * /home/admin/wwwroot/dolibarr_sellyoursaas/scripts/backup_mysql_system.sh databasename confirm

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
export DOMAIN=`grep '^domain=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export targetdir="/home/admin/backup/mysql"				
export targetdir2="/home/admin/backup/conf"				

export DATABASE=`grep '^database=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export masterserver=`grep '^masterserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export webserver=`grep '^webserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

export EMAILFROM=`grep '^emailfrom=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export EMAILTO=`grep '^emailsupervision=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [ "x$EMAILFROM" == "x" ]; then
	export EMAILFROM=noreply@$DOMAIN
fi
if [ "x$EMAILTO" == "x" ]; then
	export EMAILTO=supervision@$DOMAIN
fi



if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 100
fi

if [ "x$1" == "x" ]; then
	echo "Missing parameter 1 - test|confirm" 1>&2
	echo "Usage: ${0} [test|confirm]"
fi
if [[ "x$1" == "x" ]]; then
	exit 1
fi


export testorconfirm=$1

# For debug
echo "testorconfirm = $testorconfirm"
echo "EMAILFROM=$EMAILFROM"
echo "EMAILTO=$EMAILTO"

export errstring=""
export usecompressformatforarchive=`grep '^usecompressformatforarchive=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

MYSQLDUMP=`which mysqldump`

if [[ ! -d $targetdir ]]; then
	echo Failed to find archive directory $targetdir
	exit 2
fi
if [[ ! -d $targetdir2 ]]; then
	echo Failed to find archive directory $targetdir2
	exit 3
fi

if [[ -x /usr/bin/zstd && "x$usecompressformatforarchive" == "xzstd" ]]; then
	echo "Do a tar of config files in conffiles.tar.zst"
	echo "tar -cv /home/*/.ssh /etc /var/spool/cron/crontabs | zstd -z -9 -q > $targetdir2/conffiles.tar.zst"
	tar -cv /home/*/.ssh /etc /var/spool/cron/crontabs | zstd -z -9 -q > $targetdir2/conffiles.tar.zst
	chown root.admin $targetdir2/conffiles.tar.zst
	chmod o-rwx $targetdir2/conffiles.tar.zst
	rm -f $targetdir2/conffiles.tar.gz
	rm -f $targetdir2/conffiles.tar.bz2

	export dbname="mysql" 
	rm "$targetdir/${dbname}.sql.zst"
	echo "Do a dump of database $dbname into $targetdir/${dbname}.sql.zst"
	echo "$MYSQLDUMP --no-tablespaces --quick --skip-extended-insert $dbname | zstd -z -9 -q > $targetdir/${dbname}.sql.zst"
	$MYSQLDUMP --no-tablespaces --quick --skip-extended-insert $dbname | zstd -z -9 -q > $targetdir/${dbname}.sql.zst
	FILESIZE=$(stat -c%s "$targetdir/${dbname}.sql.zst")
	if (( "$FILESIZE" < 50 )); then
		export errstring="$errstring\n"`date '+%Y-%m-%d %H:%M:%S'`" The backup of system for "`hostname`" localy failed - Command was $MYSQLDUMP"
	fi
	chown root.admin $targetdir/${dbname}.sql.zst
	chmod o-rwx $targetdir/${dbname}.sql.zst
	rm -f $targetdir/${dbname}.sql.gz
	rm -f $targetdir/${dbname}.sql.bz2
	
    export foundmasterdatabase=0;  
    if [ "x$DATABASE" != "x" -a "x$masterserver" == "x1" ]; then
            foundmasterdatabase=1;
    fi
    if [ "x$DATABASE" != "x" -a "x$webserver" == "x1" ]; then
            foundmasterdatabase=1;
    fi
    if [ "x$foundmasterdatabase" == "x1" ]; then	
		export dbname=$DATABASE 
		rm "$targetdir/${dbname}.sql.zst"
		echo "Do a dump of database $dbname into $targetdir/${dbname}.sql.zst"
		echo "$MYSQLDUMP --no-tablespaces $dbname | zstd -z -9 -q > $targetdir/${dbname}.sql.zst"
		$MYSQLDUMP --no-tablespaces $dbname | zstd -z -9 -q > $targetdir/${dbname}.sql.zst
		chown root.admin $targetdir/${dbname}.sql.zst
		chmod o-rwx $targetdir/${dbname}.sql.zst
		rm -f $targetdir/${dbname}.sql.gz
		rm -f $targetdir/${dbname}.sql.bz2
	else
		echo "No sellyoursaas master database found to backup (parameter in /etc/sellyoursaas.conf: database=$DATABASE, masterserver=$masterserver, webserver=$webserver)."
	fi
else
	echo "Do a tar of config filesin conffiles.tar.gz"
	echo "tar -cv /home/*/.ssh /etc /var/spool/cron/crontabs | gzip > $targetdir2/conffiles.tar.gz"
	tar -cv /home/*/.ssh /etc /var/spool/cron/crontabs | gzip > $targetdir2/conffiles.tar.gz
	chown root.admin $targetdir2/conffiles.tar.gz
	chmod o-rwx $targetdir2/conffiles.tar.gz
	rm -f $targetdir2/conffiles.tar.bz2
	
	export dbname="mysql"
	rm "$targetdir/${dbname}.sql.gz"
	echo "Do a dump of database $dbname into $targetdir/${dbname}.sql.gz"
	echo "$MYSQLDUMP --no-tablespaces --quick --skip-extended-insert $dbname | gzip > $targetdir/${dbname}.sql.gz"
	$MYSQLDUMP --no-tablespaces --quick --skip-extended-insert $dbname | gzip > $targetdir/${dbname}.sql.gz
	FILESIZE=$(stat -c%s "$targetdir/${dbname}.sql.gz")
	if (( "$FILESIZE" < 50 )); then
		export errstring="$errstring\n"`date '+%Y-%m-%d %H:%M:%S'`" The backup of system for "`hostname`" localy failed - Command was $MYSQLDUMP"
	fi
	chown root.admin $targetdir/${dbname}.sql.gz
	chmod o-rwx $targetdir/${dbname}.sql.gz
	rm -f $targetdir/${dbname}.sql.bz2
	rm -f $targetdir/${dbname}.sql.zst
	
    export foundmasterdatabase=0;  
    if [ "x$DATABASE" != "x" -a "x$masterserver" != "x" -a "x$masterserver" != "x0" ]; then
            foundmasterdatabase=1;
    fi
    if [ "x$DATABASE" != "x" -a "x$webserver" != "x" -a "x$webserver" != "x0" ]; then
            foundmasterdatabase=1;
    fi
    
    echo 
    
    if [ "x$foundmasterdatabase" == "x1" ]; then	
		export dbname=$DATABASE 
		rm "$targetdir/${dbname}.sql.gz"
		echo "Do a dump of database $dbname into $targetdir/${dbname}.sql.gz"
		echo "$MYSQLDUMP --no-tablespaces $dbname | gzip > $targetdir/${dbname}.sql.gz"
		$MYSQLDUMP --no-tablespaces $dbname | gzip > $targetdir/${dbname}.sql.gz
		chown root.admin $targetdir/${dbname}.sql.gz
		chmod o-rwx $targetdir/${dbname}.sql.gz
		rm -f $targetdir/${dbname}.sql.bz2
		rm -f $targetdir/${dbname}.sql.zst
	else
		echo "No sellyoursaas master database found to backup (parameter in /etc/sellyoursaas.conf: database=$DATABASE, masterserver=$masterserver, webserver=$webserver)."
	fi
fi

if [ "x$errstring" != "x" ]; then
	echo "Send email to $EMAILTO to inform about backup system error"
	echo -e "The local backup of system for "`hostname`" failed (started at $now).\nerrstring=$errstring" | mail -aFrom:$EMAILFROM -s "[Warning] Backup system of "`hostname`" failed" $EMAILTO
	echo
else
	echo "Send email to $EMAILTO to inform about backup system success"
	echo -e "The local backup of system for "`hostname`" succeed (started at $now)" | mail -aFrom:$EMAILFROM -s "[Backup system - "`hostname`"] Backup of system succeed" $EMAILTO
	echo
fi

exit 0
