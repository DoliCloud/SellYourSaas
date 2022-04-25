#!/bin/bash
# Copy all backups on other locations (on a remote backup server)
#
# Put the following entry into your root cron
#40 4 4 * * /home/admin/wwwroot/dolibarr_sellyoursaas/scripts/backup_backups.sh confirm [m|w] [osuX]

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
export backupdir=`grep '^backupdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export remotebackupdir=`grep '^remotebackupdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

export testorconfirm=$1
export HISTODIR=`date +%d`
if [ "x$2" == "xw" ]; then
	HISTODIR=`date +%u`
fi

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
	export EMAILFROM=noreply@$DOMAIN
fi
if [ "x$EMAILTO" == "x" ]; then
	export EMAILTO=supervision@$DOMAIN
fi

export DISTRIB_RELEASE=`lsb_release -r -s`

#export OPTIONS="-v -4 --stats -a --chmod=u=rwX --delete";
#export OPTIONS="-v -4 --stats -a --chmod=u=rwX --delete --delete-excluded";
export OPTIONS="-4 --stats -rlt --chmod=u=rwX";
if [ "x$DISTRIB_RELEASE" == "x20.10" ]; then
	# Version must be 20.10+ on both side !
	#export OPTIONS="$OPTIONS --open-noatime" 
	export OPTIONS="$OPTIONS"
else 
	export OPTIONS="$OPTIONS --noatime"
fi
if [ "x$2" == "x--delete" ]; then
	export OPTIONS="$OPTIONS --delete"
fi
if [ "x$3" == "x--delete" ]; then
	export OPTIONS="$OPTIONS --delete"
fi
if [ "x$4" == "x--delete" ]; then
	export OPTIONS="$OPTIONS --delete"
fi



if [ "x$USER" == "x" ]; then
	export USER="admin"
fi

instanceserver=`grep '^instanceserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

export TESTN=""
if [ "x$testorconfirm" != "xconfirm" ]; then
	TESTN="-n"
fi

echo "DOMAIN=$DOMAIN"
echo "DIRSOURCE1=$DIRSOURCE1"
echo "DIRSOURCE2=$DIRSOURCE2"
echo "SERVDESTI=$SERVDESTI"
echo "SERVPORTDESTI=$SERVPORTDESTI"
echo "EMAILFROM=$EMAILFROM"
echo "EMAILTO=$EMAILTO"
echo "PID=$PID"
echo "instanceserver=$instanceserver"
echo "backupdir=$backupdir"
echo "remotebackupdir=$remotebackupdir"
echo "HISTODIR=$HISTODIR"
echo "OPTIONS=$OPTIONS"
echo "TESTN=$TESTN"

echo "**** ${0} started"
echo `date +'%Y-%m-%d %H:%M:%S'`" Start to copy backups on a remote server" 

if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 100
fi

if [ "x$1" == "x" ]; then
	echo
	echo "Usage: ${0} (test|confirm) [m|w] [osuX] [--delete]"
	echo "Where m (default) is to keep 1 month of backup, and w is to keep 1 week of backup"
	echo "You can also set a group of 4 first letter on username to backup the backup of a limited number of users."
	exit 1
fi

if [ "x$SERVDESTI" == "x" ]; then
	echo "Can't find name of remote backup server (remotebackupserver=) in /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} (test|confirm) [osuX]"
fi

if [ "x$DOMAIN" == "x" ]; then
	echo "Value for domain seems to not be set into /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} (test|confirm) [osuX]"
fi



# For debug
echo "testorconfirm = $testorconfirm"

export errstring=""
export ret=0
declare -A ret1
declare -A ret2


# the following line clears the last weeks incremental directory
[ -d $HOME/emptydir ] || mkdir $HOME/emptydir


# Loop on each target server
for SERVDESTICURSOR in `echo $SERVDESTI | sed -e 's/,/ /g'`
do
	ret1[$SERVDESTICURSOR]=0
	ret2[$SERVDESTICURSOR]=0
done

# Loop on each target server to make backup of SOURCE1
for SERVDESTICURSOR in `echo $SERVDESTI | sed -e 's/,/ /g'`
do
	#echo `date +'%Y-%m-%d %H:%M:%S'`" Do rsync of emptydir to $SERVDESTICURSOR:$DIRDESTI1/backupold_$HISTODIR/..."
	#rsync $TESTN -a $HOME/emptydir/ $USER@$SERVDESTICURSOR:$DIRDESTI1/backupold_$HISTODIR/

	echo `date +'%Y-%m-%d %H:%M:%S'`" Do rsync of $DIRSOURCE1 to $USER@$SERVDESTICURSOR:$DIRDESTI1..."
	export RSYNC_RSH="ssh -p $SERVPORTDESTI"
	export command="rsync $TESTN -x --exclude-from=$scriptdir/backup_backups.exclude $OPTIONS --backup --backup-dir=$DIRDESTI1/backupold_$HISTODIR $DIRSOURCE1/* $USER@$SERVDESTICURSOR:$DIRDESTI1";
	echo "$command";
	
	
	$command 2>&1
   	# WARNING: The set of rescommand must be just after the $command. No echo between.
	rescommand=$?
    if [ "x$rescommand" != "x0" ]; then
		ret1[$SERVDESTICURSOR]=$rescommand
    	echo "ERROR Failed to make rsync for $DIRSOURCE1 to $SERVDESTICURSOR. ret=${ret1[$SERVDESTICURSOR]}."
    	echo "Command was: $command"
    	export errstring="$errstring\n"`date '+%Y-%m-%d %H:%M:%S'`" Dir $DIRSOURCE1 to $SERVDESTICURSOR. ret=${ret1[$SERVDESTICURSOR]}. Command was: $command\n"
    fi
done

	
# Loop on each target server to make backup of SOURCE2 (if no error during backup of SOURCE1)
if [[ "x$instanceserver" != "x0" ]]; then
	echo
	echo `date +'%Y-%m-%d %H:%M:%S'`" Do rsync of customer directories $DIRSOURCE2/osu to $SERVDESTI..."

	#for SERVDESTICURSOR in `echo $SERVDESTI | sed -e 's/,/ /g'`
	#do
	#	echo `date +'%Y-%m-%d %H:%M:%S'`" Do rsync of emptydir to $SERVDESTICURSOR:$DIRDESTI2/backupold_$HISTODIR/..."
	#	rsync $TESTN -a $HOME/emptydir/ $USER@$SERVDESTICURSOR:$DIRDESTI2/backupold_$HISTODIR/
	#done

	for i in 'a' 'b' 'c' 'd' 'e' 'f' 'g' 'h' 'i' 'j' 'k' 'l' 'm' 'n' 'o' 'p' 'q' 'r' 's' 't' 'u' 'v' 'w' 'x' 'y' 'z' '0' '1' '2' '3' '4' '5' '6' '7' '8' '9' ; do
		echo
		echo `date +'%Y-%m-%d %H:%M:%S'`" ----- Process directory $backupdir/osu$i"
		nbofdir=`ls -d $backupdir/osu$i* 2>/dev/null | wc -l`
		if [ "x$nbofdir" != "x0" ]; then
			# Test if we force backup on a given dir
			if [ "x$3" != "x" ]; then
				if [ "x$3" != "xosu$i" ]; then
					echo "Ignored."
					continue
				fi
			fi

			for SERVDESTICURSOR in `echo $SERVDESTI | sed -e 's/,/ /g'`
			do
				if [ "x${ret1[$SERVDESTICURSOR]}" == "x0" ]; then
					export RSYNC_RSH="ssh -p $SERVPORTDESTI"
			        export command="rsync $TESTN -x --exclude-from=$scriptdir/backup_backups.exclude $OPTIONS --backup --backup-dir=$DIRDESTI2/backupold_$HISTODIR $DIRSOURCE2/osu$i* $USER@$SERVDESTICURSOR:$DIRDESTI2";
		        	echo `date +'%Y-%m-%d %H:%M:%S'`" $command";

			        $command 2>&1
				   	# WARNING: The set of rescommand must be just after the $command. No echo between.
					rescommand=$?
			        if [ "x$rescommand" != "x0" ]; then
			        	ret2[$SERVDESTICURSOR]=$((${ret2[$SERVDESTICURSOR]} + 1));
			        	echo "ERROR Failed to make rsync for $DIRSOURCE2/osu$i to $SERVDESTICURSOR. ret=${ret2[$SERVDESTICURSOR]}."
					   	echo "Command was: $command"
			        	export errstring="$errstring\n"`date '+%Y-%m-%d %H:%M:%S'`" Dir osu$i to $SERVDESTICURSOR. ret=${ret2[$SERVDESTICURSOR]}. Command was: $command\n"
			        fi
				else
					echo "Canceled. An error occured in backup of DIRSOURCE2=$DIRSOURCE2/osu$i"
					export errstring="$errstring\nCanceled. An error occured in backup of DIRSOURCE2=$DIRSOURCE2/osu$i"
				fi
			done
	    else
	    	echo No directory found starting with name $backupdir/osu$i
			export errstring="$errstring\nNo directory found starting with name $backupdir/osu$i"
	    fi
	done
fi

echo
echo `date +'%Y-%m-%d %H:%M:%S'`" End with errstring=$errstring"
echo


# Loop on each target server
for SERVDESTICURSOR in `echo $SERVDESTI | sed -e 's/,/ /g'`
do
	echo `date +'%Y-%m-%d %H:%M:%S'`" End for $SERVDESTICURSOR ret1[$SERVDESTICURSOR]=${ret1[$SERVDESTICURSOR]} ret2[$SERVDESTICURSOR]=${ret2[$SERVDESTICURSOR]}"

	if [ "x${ret1[$SERVDESTICURSOR]}" != "x0" ]; then
		echo "Send email to $EMAILTO to warn about backup error"
		echo -e "Failed to make copy backup to remote backup server $SERVDESTICURSOR - End ret1=${ret1[$SERVDESTICURSOR]} ret2=${ret2[$SERVDESTICURSOR]} errstring=\n$errstring" | mail -aFrom:$EMAILFROM -s "[Warning] Backup of backup to remote server failed for "`hostname` $EMAILTO
		ret=${ret1[$SERVDESTICURSOR]}
	elif [ "x${ret2[$SERVDESTICURSOR]}" != "x0" ]; then
		echo "Send email to $EMAILTO to warn about backup error"
		echo -e "Failed to make copy backup to remote backup server $SERVDESTICURSOR - End ret1=${ret1[$SERVDESTICURSOR]} ret2=${ret2[$SERVDESTICURSOR]} errstring=\n$errstring" | mail -aFrom:$EMAILFROM -s "[Warning] Backup of backup to remote server failed for "`hostname` $EMAILTO
		ret=${ret2[$SERVDESTICURSOR]}
	fi

	echo
done


# Delete temporary emptydir
rmdir $HOME/emptydir


if [ "x$ret" != "x0" ]; then
	exit $ret
fi

if [ "x$3" != "x" ]; then
	echo Script was called for only one given instance. No email or supervision event sent in such situation
else
	echo "Send email to $EMAILTO to inform about backup success"
	echo -e "The backup of backup for "`hostname`" to remote backup server $SERVDESTI succeed - End ret1=0 ret2=0\n$errstring" | mail -aFrom:$EMAILFROM -s "[Backup of Backup - "`hostname`"] Backup of backup to remote server succeed" $EMAILTO
fi
echo

exit 0
