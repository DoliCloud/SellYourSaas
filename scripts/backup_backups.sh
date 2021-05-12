#!/bin/bash
# Copy all backups on other locations (on a remote backup server)
#
# Put the following entry into your root cron
#40 4 4 * * /home/admin/wwwroot/dolibarr_sellyoursaas/scripts/backup_backups.sh confirm

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
export SERVPORTDESTI=`grep '^remotebackupserverport=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [ "x$SERVPORTDESTI" == "x" ]; then
	export SERVPORTDESTI="22"
fi
export USER=`grep '^remotebackupuser=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export DIRDESTI1="$remotebackupdir/home_"`hostname`;
export DIRDESTI2="$remotebackupdir/backup_"`hostname`;

export EMAILFROM=`grep '^emailfrom=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export EMAILTO=`grep '^emailsupervision=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [ "x$EMAILFROM" == "x" ]; then
	export EMAILFROM=support@$DOMAIN
fi
if [ "x$EMAILTO" == "x" ]; then
	export EMAILTO=supervision@$DOMAIN
fi

#export OPTIONS="-v -4 --stats -a --chmod=u=rwX --delete";
#export OPTIONS="-v -4 --stats -a --chmod=u=rwX --delete --delete-excluded";
export OPTIONS="-v -4 --stats -rlt --chmod=u=rwX --backup --suffix=.old";
if [ "x$DISTRIB_RELEASE" == "x20.10" ]; then
	# Version must be 20.10+ on both side !
	#export OPTIONS="$OPTIONS --open-noatime" 
	export OPTIONS="$OPTIONS"
else 
	export OPTIONS="$OPTIONS --noatime"
fi

if [ "x$USER" == "x" ]; then
	export USER="admin"
fi

echo "DOMAIN=$DOMAIN"
echo "DIRSOURCE1=$DIRSOURCE1"
echo "DIRSOURCE2=$DIRSOURCE2"
echo "SERVDESTI=$SERVDESTI"
echo "SERVPORTDESTI=$SERVPORTDESTI"
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

if [ "x$DOMAIN" == "x" ]; then
	echo "Value for domain seems to not be set into /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} (test|confirm) [osux]"
fi


export testorconfirm=$1

# For debug
echo "testorconfirm = $testorconfirm"

export errstring=""
export ret=0
export ret1=0
export ret2=0

# Loop on each target server
for SERVDESTICURSOR in `echo $SERVDESTI | sed -e 's/,/ /g'`
do
	echo `date +%Y%m%d%H%M%S`" Do rsync of $DIRSOURCE1 to $SERVDESTICURSOR..."
	export RSYNC_RSH="ssh -p $SERVPORTDESTI"
	export command="rsync -x --delete --delete-excluded --exclude-from=$scriptdir/backup_backups.exclude $OPTIONS $DIRSOURCE1/* $USER@$SERVDESTICURSOR:$DIRDESTI1";
	echo "$command";
	
	$command 2>&1
	export ret1=$?
	
	export ret2=0
	if [ "x$ret1" == "x0" ]; then
		echo
		echo `date +%Y%m%d%H%M%S`" Do rsync of customer directories to $SERVDESTICURSOR..."
	
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
					
					export RSYNC_RSH="ssh -p $SERVPORTDESTI"
			        export command="rsync -x --exclude-from=$scriptdir/backup_backups.exclude $OPTIONS $DIRSOURCE2/osu$i* $USER@$SERVDESTICURSOR:$DIRDESTI2";
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
		echo "Failed to make copy backup to remote backup server $SERVDESTICURSOR - End ret1=$ret1 ret2=$ret2 errstring=$errstring" | mail -aFrom:$EMAILFROM -s "[Warning] Backup of backup to remote server failed for "`hostname` $EMAILTO
		ret=$ret1
	elif [ "x$ret2" != "x0" ]; then
		echo "Send email to $EMAILTO to warn about backup error"
		echo "Failed to make copy backup to remote backup server $SERVDESTICURSOR - End ret1=$ret1 ret2=$ret2 errstring=$errstring" | mail -aFrom:$EMAILFROM -s "[Warning] Backup of backup to remote server failed for "`hostname` $EMAILTO
		ret=$ret2
	else
		echo "Send email to $EMAILTO to inform about backup success"
		echo "The backup of backup for "`hostname`" to remote backup server $SERVDESTICURSOR succeed - End ret1=$ret1 ret2=$ret2 errstring=$errstring" | mail -aFrom:$EMAILFROM -s "[Backup of Backup - "`hostname`"] Backup of backup to remote server succeed" $EMAILTO
	fi

echo

done


if [ "x$ret" != "x0" ]; then
	exit $ret
fi

exit 0
