#!/bin/bash
# Copy all backup of backups from other locations (on a remote backup server)
#
# Put the following entry into your root cron
# /home/admin/wwwroot/dolibarr_sellyoursaas/scripts/backup_backup_backups.sh test|confirm /mnt/diskbackup remotebackupserver1,remotebackupserver2,...

#set -e

#source /etc/lsb-release

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

export remotebackupdir=$2
export SERVSOURCE=$3

# Source
export SERVPORTSOURCE="22"
export USER="backup"
export DIRSOURCE1="$remotebackupdir/home_";
export DIRSOURCE2="$remotebackupdir/backup_";

# Target
export DIRDESTI1="/home_";
export DIRDESTI2="/backup_";


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


echo "remotebackupdir=$remotebackupdir"
echo "SERVSOURCE=$SERVSOURCE"
echo "DIRSOURCE1=$DIRSOURCE1"
echo "DIRSOURCE2=$DIRSOURCE2"
echo "DIRDESTI1=$DIRDESTI1"
echo "DIRDESTI2=$DIRDESTI2"
echo "PID=$PID"


echo "**** ${0} started"
echo `date +%Y%m%d%H%M%S`" Start to copy backups of backup on local server" 

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



export testorconfirm=$1

# For debug
echo "testorconfirm = $testorconfirm"

export errstring=""
export ret=0
export ret1=0
export ret2=0

# Loop on each target server
for SERVSOURCECURSOR in `echo $SERVSOURCE | sed -e 's/,/ /g'`
do
	echo `date +%Y%m%d%H%M%S`" Do rsync of $DIRSOURCE1 to $SERVDESTICURSOR..."
	export RSYNC_RSH="ssh -p $SERVPORTSOURCE"
	export command="rsync -x --delete --delete-excluded --exclude-from=$scriptdir/backup_backups.exclude $OPTIONS $USER@$SERVSOURCECURSOR:$DIRSOURCE1/* $DIRDESTI1";
	echo "$command";
	
	$command 2>&1
	export ret1=$?
	
	export ret2=0
	if [ "x$ret1" == "x0" ]; then
		echo
		echo `date +%Y%m%d%H%M%S`" Do rsync of customer directories to $DIRDESTI2..."
	
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
					
					export RSYNC_RSH="ssh -p $SERVPORTSOURCE"
			        export command="rsync -x --exclude-from=$scriptdir/backup_backups.exclude $OPTIONS $USER@$SERVSOURCECURSOR:$DIRSOURCE2/osu$i* $DIRDESTI2";
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
	fi
	
	echo `date +%Y%m%d%H%M%S`" End ret1=$ret1 ret2=$ret2 errstring=$errstring"
	echo

done


if [ "x$ret" != "x0" ]; then
	exit $ret
fi

exit 0
