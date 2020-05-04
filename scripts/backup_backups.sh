#!/bin/bash
# Copy all backups on other locations (on a remote backup server)
#
# Put the following entry into your root cron
#40 4 4 * * /home/admin/wwwroot/dolibarr_sellyoursaas/scripts/backup_backups.sh confirm

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

export DOMAIN=`grep '^domain=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

# Source
export DIRSOURCE1="/home";
export DIRSOURCE2=`grep 'backupdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

# Target
export SERVDESTI=`grep 'remotebackupserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export USER="admin";
export DIRDESTI1="/mnt/diskbackup/home_"`hostname`;
export DIRDESTI2="/mnt/diskbackup/backup_"`hostname`;

export EMAILFROM=support@$DOMAIN
export EMAILTO=supervision@$DOMAIN
#export OPTIONS="-v --stats -a --delete";
#export OPTIONS="-v --stats -a --delete --delete-excluded";
export OPTIONS="-v --stats -rlt --noatime --backup --suffix=.old";

echo >> /var/log/backup_backups.log

echo "DOMAIN=$DOMAIN" >> /var/log/backup_backups.log
echo "DIRSOURCE1=$DIRSOURCE1" >> /var/log/backup_backups.log
echo "DIRSOURCE2=$DIRSOURCE2" >> /var/log/backup_backups.log
echo "SERVDESTI=$SERVDESTI" >> /var/log/backup_backups.log
echo "EMAILFROM=$EMAILFROM" >> /var/log/backup_backups.log
echo "EMAILTO=$EMAILTO" >> /var/log/backup_backups.log
echo "PID=$PID" >> /var/log/backup_backups.log

echo "DOMAIN=$DOMAIN"
echo "DIRSOURCE1=$DIRSOURCE1"
echo "DIRSOURCE2=$DIRSOURCE2"
echo "SERVDESTI=$SERVDESTI"
echo "EMAILFROM=$EMAILFROM"
echo "EMAILTO=$EMAILTO"
echo "PID=$PID"


echo "**** ${0} started" >> /var/log/backup_backups.log
echo $now" Start to copy backups on a remote server" >> /var/log/backup_backups.log 

if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 1
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


echo "Do a rsync first part"
export command="rsync -x --delete --delete-excluded --exclude '*_log' --exclude '*.log' --exclude '*log.*.gz' --exclude '_sessions/*' --exclude '_log/*' --exclude '_tmp/*' -e ssh $OPTIONS $DIRSOURCE1/* $USER\@$SERVDESTI:$DIRDESTI1";
echo "$command\n";

$command >>/var/log/backup_backups.log
export ret1=$?

echo "Do a rsync for second part"

for i in {'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'}; do
        export command="rsync -x --exclude '*_log' --exclude '*.log' --exclude '*log.*.gz' --exclude '_sessions/*' --exclude '_log/*' --exclude '_tmp/*' -e ssh $OPTIONS $DIRSOURCE2/osu$i* $USER\@$SERVDESTI:$DIRDESTI2";
        echo "$command\n";

        $command >>/var/log/backup_backups.log
        export ret2=$ret2+($? ? 1 : 0);
done

if [ "x$ret1" != "x0" ]; then
	echo "Failed to make copy backup on remote backup" | mail -aFrom:$EMAILFROM -s "[Alert] Backup on remote failed for "`hostname` $EMAILTO
	exit $ret1
fi 
if [ "x$ret2" != "x0" ]; then
	echo "Failed to make copy backup on remote backup" | mail -aFrom:$EMAILFROM -s "[Alert] Backup on remote failed for "`hostname` $EMAILTO
	exit $ret2
fi 

echo $now" End ret1=$ret1 ret2=$ret2" >> /var/log/backup_backups.log 

exit 0
