#!/bin/bash
# Catch backups from a remote backup server into a local computer, like a NAS.
#
# Put the following entry into the cron of a user that can rsync to the remote server with its public key.
# /home/admin/wwwroot/dolibarr_sellyoursaas/scripts/backup_backup_backups.php  remotelogin  (test|confirm)  [remotebackupserversrc]  [localdirtarget]  >/.../backup_backup_backups.log
#
# On a NAS (Synology, ...), the file can be stored into
# ~/backup_pull_backups.sh
#


#set -e

#source /etc/lsb-release

export now=`date +'%Y-%m-%d %H:%M:%S'`

echo
echo "**** ${0} started"
echo

if [ "x$1" == "x" ]; then
	echo "Usage:   ${0}  remotelogin  (test|confirm|confirmdelete)"
	echo "Example: ${0}  admin        test"
	echo "Example: ${0}  admin        test     mysellyoursaasbackupserver.com:22/mydir  /volume2/NASBACKUPMYDIR"
	echo "Note:    The user running the script must have its public key declared on the backup server to pull"
	exit
fi

echo `date +'%Y-%m-%d %H:%M:%S'`" Start script ${0} to pull backups on local server"

export PID=${$}
export realpath=$(realpath "${0}")
export scriptdir=$(dirname "$realpath")
export script=${0##*/}

echo "${0} ${@}"
echo "# user id --------> $(id -u)"
echo "# now ------------> $now"
echo "# PID ------------> ${$}"
echo "# PWD ------------> $PWD" 
echo "# arguments ------> ${@}"
echo "# path to me -----> ${0}"
echo "# parent path ----> ${0%/*}"
echo "# my name --------> ${0##*/}"
echo "# scriptdir-------> $scriptdir"

export USER=$1
export testorconfirm=$2

if [ "x$3" != "x" ]; then
	export remotebackupserver=`echo $3 | cut -d ':' -f 1`
	export remotebackupserverport=`echo $3 | cut -d ':' -f 2 | cut -d '/' -f 1`
	export remotebackupdir=`echo $3 | sed -s 's/^[^\/]*//'`
	
	export backupdir=$4
else
	export remotebackupserver=`grep '^remotebackupserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
	export remotebackupserverport=`grep '^remotebackupserverport=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
	export remotebackupdir=`grep '^remotebackupdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
	
	export backupdir=`grep '^backupdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
fi

export DOMAIN=`grep '^domain=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export EMAILFROM=`grep '^emailfrom=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export EMAILTO=`grep '^emailsupervision=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [ "x$EMAILFROM" == "x" ]; then
	export EMAILFROM=noreply@$DOMAIN
fi
if [ "x$EMAILTO" == "x" ]; then
	export EMAILTO=supervision@$DOMAIN
fi

#export OPTIONS=" -4 --prune-empty-dirs --stats -rlt --chmod=u=rwX";
export OPTIONS=" -4 --prune-empty-dirs --stats -rlt --no-specials";
if [ "x$testorconfirm" == "xconfirmdelete" ]; then
	export OPTIONS="$OPTIONS --delete --delete-excluded"
fi

#export DISTRIB_RELEASE=`lsb_release -r -s`
#if [ "x$DISTRIB_RELEASE" == "x20.10" ]; then
#	# Version must be 20.10+ on both side !
#	#export OPTIONS="$OPTIONS --open-noatime" 
#	export OPTIONS="$OPTIONS"
#else 
#	export OPTIONS="$OPTIONS --noatime"
#fi

if [ "x$testorconfirm" != "xconfirm" ]; then
	export OPTIONS="-n $OPTIONS"
fi
if [ "x$USER" == "x" ]; then
	export USER="admin"
fi
if [ "x$3" == "x--delete" ]; then
	export OPTIONS="$OPTIONS --delete"
fi

if [ "x$remotebackupdir" == "x" ]; then
	export remotebackupdir=/mnt/diskbackup
fi
if [ "x$backupdir" == "x" ]; then
	export backupdir=/mnt/diskbackup/backup
fi
if [ "x$remotebackupserverport" == "x" ]; then
	export remotebackupserverport="22"
fi

echo "USER=$USER"
echo "testorconfirm=$testorconfirm"
echo "remotebackupserver=$remotebackupserver"
echo "remotebackupserverport=$remotebackupserverport"
echo "remotebackupdir=$remotebackupdir"
echo "backupdir=$backupdir"
echo "EMAILFROM=$EMAILFROM"
echo "EMAILTO=$EMAILTO"


if [ "x$USER" == "x" -o "x$testorconfirm" == "x" -o "x$remotebackupserver" == "x" -o "x$remotebackupdir" == "x" ]; then
	echo "Usage:   ${0}  remotelogin  (test|confirm)"
	echo "Example: ${0}  admin        test"
	echo "Example: ${0}  admin        test     mysellyoursaasbackupserver.com:22/media/admin/HDDATA1_LD/  /volumeX/NASBACKUPMYDIR"
	echo "Note:    The user running the script must have its public key declared on the backup server to pull"
	exit
fi


export errstring=""
export ret=0
export ret1=0

cd "$scriptdir"



if [ "x$3" != "x" -a "x$4" != "x" ]; then
	# Generic usage
	echo `date +'%Y-%m-%d %H:%M:%S'`" Start execution in generic mode" 

	>/var/log/$script.generic.log 
	if [ ! -w /var/log/$script.generic.log ]; then
		echo User has no permission to write into log file /var/log/$script.generic.log
		export ret1=1
	fi
	> /var/log/$script.generic.err
	if [ ! -w /var/log/$script.generic.err ]; then
		echo User has no permission to write into err file /var/log/$script.generic.err
		export ret1=1
	fi
	
	# Source
	export SERVSOURCE=$remotebackupserver
	export SERVPORTSOURCE=$remotebackupserverport
	export DIRSOURCE="$remotebackupdir";
	
	# Target
	export DIRDESTI="$backupdir";

	export RSYNC_RSH="ssh -p $SERVPORTSOURCE"
	export OPTIONS="$OPTIONS --exclude=.debris --exclude=*.qcow --exclude=*.qcow2 --exclude=*.fsa --exclude=VirtualBox*VMs/"
	
	# Note: for pulling a backup, we do not exclude backup_backups.exclude, so image is like the backup server.
	export command="rsync -x $OPTIONS $USER@$SERVSOURCE:$DIRSOURCE/* $DIRDESTI1";
	echo "$command";

	> /var/log/$script.generic.err
	$command >/var/log/$script.generic.log 2>/var/log/$script.generic.err
	if [ "x$?" != "x0" ]; then
        nberror=`cat /var/log/$script.generic.err | grep -v "Broken pipe" | grep -v "No such file or directory" | grep -v "some files/attrs were not transferred" | wc -l`
	    cat /var/log/$script.generic.err
		if [ "x$nberror" != "x0" ]; then
		  	echo "ERROR Failed to make rsync for $DIRSOURCE"
	  		echo
	   		export ret1=$(($ret1 + 1));
	   		export errstring="${0} $1 $2 $3 $4 -> Dir $DIRSOURCE "`date '+%Y-%m-%d %H:%M:%S'`
	   	else
	   	    export errstring="$3 $4 -> No files found"
            echo "${0} $1 $2 $3 $4 -> No files found"
            echo
	   	fi
	else
	    export errstring="${0} $1 $2 $3 $4 -> OK"
		echo "OK"
		echo
	fi
else
	# Usage for sellyoursaas
	echo `date +'%Y-%m-%d %H:%M:%S'`" Start execution in SellYourSaas mode (using parameters from /etc/sellyoursaas.conf)" 

	>/var/log/$script.log 
	if [ ! -w /var/log/$script.log ]; then
		echo User has no permission to write into log file /var/log/$script.log
		export ret1=1
	fi
	> /var/log/$script.err
	if [ ! -w /var/log/$script.err ]; then
		echo User has no permission to write into err file /var/log/$script.err
		export ret1=1
	fi
	
	# Source
	export SERVSOURCE=$remotebackupserver
	export SERVPORTSOURCE=$remotebackupserverport
	export DIRSOURCE="$remotebackupdir";
	
	# Target
	export DIRDESTI="$backupdir";
		
	
	# Loop on each target server
	for SERVSOURCECURSOR in `echo $SERVSOURCE | sed -e 's/,/ /g'`
	do
		# First get the list of backup directories
		export command="rsync --list-only $USER@$SERVSOURCECURSOR:$DIRSOURCE/"
		echo "$command > /tmp/backup_list_dirs"
		echo "$command > /tmp/backup_list_dirs" >>/var/log/$script.log
		$command > /tmp/backup_list_dirs
	
		# Now loop on each backup directory
		for fic in `cat /tmp/backup_list_dirs | awk ' $1 ~ /^d/ && $5 !~ /^\./ { print $5 } '`; do 
	
			# Case of /mnt/diskbackup/backup*x
			echo `date +'%Y-%m-%d %H:%M:%S'`" Do rsync of customer directories on $SERVSOURCECURSOR:$DIRSOURCE/$fic to $DIRDESTI/$fic ..."
			echo `date +'%Y-%m-%d %H:%M:%S'`" Do rsync of customer directories on $SERVSOURCECURSOR:$DIRSOURCE/$fic to $DIRDESTI/$fic ..." >>/var/log/$script.log

			# Now get the list of sub directories
			export command="rsync --list-only $USER@$SERVSOURCECURSOR:$DIRSOURCE/$fic/"
			echo "$command > /tmp/backup_list_dirs_$fic"
			echo "$command > /tmp/backup_list_dirs_$fic" >>/var/log/$script.log
			$command > /tmp/backup_list_dirs_$fic

			# Now loop on each backup sub directory
			for fic2 in `cat /tmp/backup_list_dirs_$fic | awk ' $1 ~ /^d/ && $5 !~ /^\./ { print $5 } '`; do
					echo >>/var/log/$script.log
					echo `date +'%Y-%m-%d %H:%M:%S'`" Process directory $SERVSOURCECURSOR:$DIRSOURCE/$fic/$fic2/"
					echo `date +'%Y-%m-%d %H:%M:%S'`" Process directory $SERVSOURCECURSOR:$DIRSOURCE/$fic/$fic2/" >>/var/log/$script.log
					
					# Test if we force backup on a given dir
					#if [ "x$2" != "x" ]; then
					#	if [ "x$2" != "xosu$i" ]; then
					#		break
					#	fi
					#fi

					export RSYNC_RSH="ssh -p $SERVPORTSOURCE"

					# Note for pulling a backup, we do not exclude backup_backups.exclude, so image is like the backup server.
			        export command="rsync -x $OPTIONS $USER@$SERVSOURCECURSOR:$DIRSOURCE/$fic/$fic2 $DIRDESTI/$fic";
		        	echo "$command"
		        	echo "$command" >>/var/log/$script.log

					> /var/log/$script.err
			        $command >>/var/log/$script.log 2>/var/log/$script.err
			        if [ "x$?" != "x0" ]; then
				        nberror=`cat /var/log/$script.err | grep -v "[Receiver] write error: Broken pipe" | grep -v "No such file or directory" | grep -v "some files/attrs were not transferred" | wc -l`
    	    			cat /var/log/$script.err
						if [ "x$nberror" != "x0" ]; then
				        	echo "ERROR Failed to make rsync for $DIRSOURCE/$fic/$fic2"
				        	echo
				        	export ret1=$(($ret1 + 1));
			    	    	export errstring="$errstring<br>Dir $SERVSOURCECURSOR:$DIRSOURCE/$fic/$fic2 "`date '+%Y-%m-%d %H:%M:%S'`" $command"
			    	    else
			                echo "WARNING Command returned an error. May be because no files were found. See /var/log/$script.log|.err"
			                echo
			    	    fi
					else
						echo "OK"
						echo
			        fi
			done

			sleep 2
			
			echo End of copy of dir /mnt/diskbackup/$fic
		done
		
		echo
		echo -e `date +'%Y-%m-%d %H:%M:%S'`" End ret1=$ret1 errstring=$errstring"
		echo
	
	done
fi


# Now we can also clean very old data
echo "Delete very old directories (older than 2 years with no change)"
cd $backupdir
#find "$backupdir/" -maxdepth 2 -path "*backup_*/osu*" -type d -mtime +730
find "$backupdir/" -maxdepth 2 -path "*backup_*/osu*" -type d -mtime +730 -print0 | xargs -0 rm -rf


if [ "x$ret1" != "x0" ]; then
	echo "Send email to $EMAILTO to inform about backup error ret1=$ret1"
	
	#echo -e "Backup pulled of a backup for "`hostname`" failed - End ret1=$ret1\n$errstring" | mail -aFrom:$EMAILFROM -s "[Warning] Backup pulled of a backup - "`hostname`" failed" $EMAILTO

	export body="Backup pulled from a backup by "`hostname`" failed - End ret1=$ret1<br>$errstring"
	export subject="[Warning] Backup pulled from a backup - "`hostname`" failed" 
	export headers='From: '${EMAILFROM}$'\nContent-type: text/html;charset=UTF-8'; 
	# Run php with -r (no need of ? tag)
	/usr/bin/php -r "mail('$EMAILTO', '$subject', '$body', '$headers');"; 
	
	#if [ -s /usr/syno/bin/synodsmnotify ]; then
		#/usr/syno/bin/synodsmnotify "@administrators" "System Event" "$subject $body"; 
	#fi
	
	echo

	exit 1
else 
	echo "Send email to $EMAILTO to inform about backup success"

	#echo -e "Backup pulled of a backup for "`hostname`" succeed - End ret1=0\n$errstring" | mail -aFrom:$EMAILFROM -s "[Backup pulled of a Backup - "`hostname`"] Backup pulled of a backup succeed" $EMAILTO

	export body="Backup pulled from a backup by "`hostname`" succeed - End ret1=$ret1<br>$errstring"
	export subject="[Backup pulled from a Backup - "`hostname`"] Backup pulled from a backup succeed" 
	export headers='From: '${EMAILFROM}$'\nContent-type: text/html;charset=UTF-8'; 
	# Run php with -r (no need of ? tag)
	/usr/bin/php -r "mail('$EMAILTO', '$subject', '$body', '$headers');"; 

	#if [ -s /usr/syno/bin/synodsmnotify ]; then
		#/usr/syno/bin/synodsmnotify "@administrators" "System Event" "$subject $body";
	#fi
	
	echo
fi


exit 0
