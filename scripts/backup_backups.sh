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
export backupdir=`grep '^backupdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export remotebackupdir=`grep '^remotebackupdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

if [ "x$remotebackupdir" == "x" ]; then
	export remotebackupdir=/mnt/diskbackup
fi

# Source
export DIRSOURCE1="/home";
export DIRSOURCE2=`grep '^backupdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

# Target
export SERVDESTI=`grep '^remotebackupserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export USER=`grep '^remotebackupuser=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export DIRDESTI1="$remotebackupdir/home_"`hostname`;
export DIRDESTI2="$remotebackupdir/backup_"`hostname`;

export EMAILFROM=support@$DOMAIN
export EMAILTO=supervision@$DOMAIN
#export OPTIONS="-v -4 --stats -a --chmod=u=rwX --delete";
#export OPTIONS="-v -4 --stats -a --chmod=u=rwX --delete --delete-excluded";
export OPTIONS="-v -4 --stats -rlt --noatime --chmod=u=rwX --backup --suffix=.old";

if [ "x$USER" == "x" ]; then
	export USER="admin"
fi

echo "DOMAIN=$DOMAIN"
echo "DIRSOURCE1=$DIRSOURCE1"
echo "DIRSOURCE2=$DIRSOURCE2"
echo "SERVDESTI=$SERVDESTI"
echo "EMAILFROM=$EMAILFROM"
echo "EMAILTO=$EMAILTO"
echo "PID=$PID"
echo "backupdir=$backupdir"
echo "remotebackupdir=$remotebackupdir"


echo "**** ${0} started"
echo `date +%Y%m%d%H%M%S`" Start to copy backups on a remote server" 

if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

if [ "x$1" == "x" ]; then
	echo "Missing parameter 1 - test|confirm" 1>&2
	echo "Usage: ${0} (test|confirm)"
fi
if [[ "x$1" == "x" ]]; then
	exit 1
fi

if [ "x$SERVDESTI" == "x" ]; then
	echo "Can't find name of remote backup server (remotebackupserver=) in /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} (test|confirm) [osux]"
fi


export testorconfirm=$1

# For debug
echo "testorconfirm = $testorconfirm"

export errstring=""

echo `date +%Y%m%d%H%M%S`" Do rsync - first part..."
export command="rsync -x --delete --delete-excluded --exclude '*_log' --exclude '*.log' --exclude '*.log.old' --exclude '*log.*.gz' --exclude '*log.*.gz.old' --exclude '_sessions/*' --exclude '_log/*' --exclude '_tmp/*' -e ssh $OPTIONS $DIRSOURCE1/* $USER@$SERVDESTI:$DIRDESTI1";
echo "$command";

$command 2>&1
export ret1=$?

export ret2=0
if [ "x$ret1" == "x0" ]; then
	echo
	echo `date +%Y%m%d%H%M%S`" Do rsync - second part..."

	for i in 'a' 'b' 'c' 'd' 'e' 'f' 'g' 'h' 'i' 'j' 'k' 'l' 'm' 'n' 'o' 'p' 'q' 'r' 's' 't' 'u' 'v' 'w' 'x' 'y' 'z' '0' '1' '2' '3' '4' '5' '6' '7' '8' '9' ; do
			echo `date +%Y%m%d%H%M%S`" Process directory $backupdir/osu$i"
			nbofdir=`ls -d $backupdir/osu$i* | wc -l`
			if [ "x$nbofdir" != "x0" ]; then
				# Test if we force backup on a given dir
				if [ "x$2" != "x" ]; then
					if [ "x$2" != "xosu$i" ]; then
						break
					fi
				fi
				
		        export command="rsync -x --exclude '*_log' --exclude '*.log' --exclude '*log.*.gz' --exclude '_sessions/*' --exclude '_log/*' --exclude '_tmp/*' -e ssh $OPTIONS $DIRSOURCE2/osu$i* $USER@$SERVDESTI:$DIRDESTI2";
	        	echo "$command";
	        	
		        $command 2>&1
		        if [ "x$?" != "x0" ]; then
		        	echo "ERROR Failed to make rsync for $DIRSOURCE2/osu$i"
		        	export ret2=$(($ret2 + 1));
		        	export errstring="$errstring Dir osu$i "`date '+%Y-%m-%d %H:%M:%S'`
		        fi
		    else
		    	echo No directory found starting with name $backupdir/osu$i
		    fi
			echo
	done
else
	export errstring="ERROR Failed to make $command"
fi

echo `date +%Y%m%d%H%M%S`" End ret1=$ret1 ret2=$ret2 errstring=$errstring"

if [ "x$ret1" != "x0" ]; then
	echo "Send email to $EMAILTO to warn about backup error"
	echo "Failed to make copy backup on remote backup - End ret1=$ret1 ret2=$ret2 errstring=$errstring" | mail -aFrom:$EMAILFROM -s "[Warning] Backup on remote failed for "`hostname` $EMAILTO
	exit $ret1
else
	if [ "x$ret2" != "x0" ]; then
		echo "Send email to $EMAILTO to warn about backup error"
		echo "Failed to make copy backup on remote backup - End ret1=$ret1 ret2=$ret2 errstring=$errstring" | mail -aFrom:$EMAILFROM -s "[Warning] Backup on remote failed for "`hostname` $EMAILTO
		exit $ret2
	fi 
fi


exit 0
