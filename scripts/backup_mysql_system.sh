#!/bin/bash
# Purge data
#
# Put the following entry into your root cron
#40 4 4 * * /home/admin/wwwroot/dolibarr_sellyoursaas/scripts/backup_mysql_system.sh databasename confirm

#set -e

source /etc/lsb-release

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
export targetdir="/home/admin/backup/mysql"				
export targetdir2="/home/admin/backup/conf"				


if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 100
fi

export DATABASE=`grep '^database=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export masterserver=`grep '^masterserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

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
	echo "Do a tar of config files"
	echo "tar -cv /home/*/.ssh /etc /var/spool/cron/crontabs | zstd -z -9 -q > $targetdir2/conffiles.tar.zst"
	tar -cv /home/*/.ssh /etc /var/spool/cron/crontabs | zstd -z -9 -q > $targetdir2/conffiles.tar.zst
	chown root.admin $targetdir2/conffiles.tar.zst
	chmod o-rwx $targetdir2/conffiles.tar.zst
	rm -f $targetdir2/conffiles.tar.gz
	rm -f $targetdir2/conffiles.tar.bz2

	export dbname="mysql" 
	echo "Do a dump of database $dbname"
	echo "$MYSQLDUMP --quick --skip-extended-insert $dbname | zstd -z -9 -q > $targetdir/${dbname}_"`date +%d`".sql.zst"
	$MYSQLDUMP --quick --skip-extended-insert $dbname | zstd -z -9 -q > $targetdir/${dbname}_`date +%d`.sql.zst
	chown root.admin $targetdir/${dbname}_`date +%d`.sql.zst
	chmod o-rwx $targetdir/${dbname}_`date +%d`.sql.zst
	rm -f $targetdir/${dbname}_`date +%d`.sql.gz
	rm -f $targetdir/${dbname}_`date +%d`.sql.bz2
	
	if [ "x$DATABASE" != "x" -a "x$masterserver" == "x1" ]; then
		export dbname=$DATABASE 
		echo "Do a dump of database $dbname"
		echo "$MYSQLDUMP $dbname | zstd -z -9 -q > $targetdir/${dbname}_"`date +%d`".sql.zst"
		$MYSQLDUMP $dbname | zstd -z -9 -q > $targetdir/${dbname}_`date +%d`.sql.zst
		chown root.admin $targetdir/${dbname}_`date +%d`.sql.zst
		chmod o-rwx $targetdir/${dbname}_`date +%d`.sql.zst
		rm -f $targetdir/${dbname}_`date +%d`.sql.gz
		rm -f $targetdir/${dbname}_`date +%d`.sql.bz2
	else
		echo "No sellyoursaas master database found to backup."
	fi
else
	echo "Do a tar of config files"
	echo "tar -cv /home/*/.ssh /etc /var/spool/cron/crontabs | gzip > $targetdir2/conffiles.tar.gz"
	tar -cv /home/*/.ssh /etc /var/spool/cron/crontabs | gzip > $targetdir2/conffiles.tar.gz
	chown root.admin $targetdir2/conffiles.tar.gz
	chmod o-rwx $targetdir2/conffiles.tar.gz
	rm -f $targetdir2/conffiles.tar.bz2
	
	export dbname="mysql" 
	echo "Do a dump of database $dbname"
	echo "$MYSQLDUMP --quick --skip-extended-insert $dbname | gzip > $targetdir/${dbname}_"`date +%d`".sql.gz"
	$MYSQLDUMP --quick --skip-extended-insert $dbname | gzip > $targetdir/${dbname}_`date +%d`.sql.gz
	chown root.admin $targetdir/${dbname}_`date +%d`.sql.gz
	chmod o-rwx $targetdir/${dbname}_`date +%d`.sql.gz
	rm -f $targetdir/${dbname}_`date +%d`.sql.bz2
	
	if [ "x$DATABASE" != "x" -a "x$masterserver" == "x1" ]; then
		export dbname=$DATABASE 
		echo "Do a dump of database $dbname"
		echo "$MYSQLDUMP $dbname | gzip > $targetdir/${dbname}_"`date +%d`".sql.gz"
		$MYSQLDUMP $dbname | gzip > $targetdir/${dbname}_`date +%d`.sql.gz
		chown root.admin $targetdir/${dbname}_`date +%d`.sql.gz
		chmod o-rwx $targetdir/${dbname}_`date +%d`.sql.gz
		rm -f $targetdir/${dbname}_`date +%d`.sql.bz2
	else
		echo "No sellyoursaas master database found to backup."
	fi
fi

exit 0
