#!/bin/bash
# Purge data.
# This script can be run on master or deployment servers.
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

export masterserver=`grep '^masterserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export instanceserver=`grep '^instanceserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export IPSERVERDEPLOYMENT=`grep '^ipserverdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export databasehost=`grep '^databasehost=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export database=`grep '^database=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export databaseuser=`grep '^databaseuser=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
export databaseport=`grep '^databaseport=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$databaseport" == "x" ]]; then
	databaseport="3306"
fi

if [[ "x$instanceserver" != "x0" && "x$IPSERVERDEPLOYMENT" == "x" ]]; then
	echo "Failed to find the IPSERVERDEPLOYMENT by reading entry 'ipserverdeployment=' into file /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} [test|confirm]"
	exit 1
fi
if [[ "x$database" == "x" ]]; then
    echo "Failed to find the DATABASE by reading entry 'database=' into file /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} [test|confirm]"
	exit 29
fi
if [[ "x$databasehost" == "x" ]]; then
    echo "Failed to find the DATABASEHOST by reading entry 'databasehost=' into file /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} [test|confirm]"
	exit 30
fi
if [[ "x$databaseuser" == "x" ]]; then
    echo "Failed to find the DATABASEUSER by reading entry 'databaseuser=' into file /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} [test|confirm]"
	exit 4
fi
if [[ "x$archivedirtest" == "x" ]]; then
    echo "Failed to find the archivedirtest value by reading entry 'archivedirtest=' into file /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} [test|confirm]"
	exit 31
fi
if [[ "x$archivedirpaid" == "x" ]]; then
    echo "Failed to find the archivedirpaid value by reading entry 'archivedirpaid=' into file /etc/sellyoursaas.conf" 1>&2
	echo "Usage: ${0} [test|confirm]"
	exit 31
fi


echo "Search sellyoursaas database credential in /etc/sellyoursaas.conf"
databasepass=`grep '^databasepass=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$databasepass" == "x" ]]; then
	echo Failed to get password for mysql user sellyoursaas 
	exit 5
fi

if [ "x$1" == "x" ]; then
	echo "Missing parameter - test|confirm" 1>&2
	echo "Usage: ${0} [test|confirm] (oldtempinarchive)"
	echo "With mode test, the /temp/... files are not deleted at end of script" 
	exit 6
fi

echo "Search database server name and port for deployment server in /etc/sellyoursaas.conf"
export databasehostdeployment=`grep '^databasehostdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$databasehostdeployment" == "x" ]]; then
	databasehostdeployment="localhost"
fi 
export databaseportdeployment=`grep '^databaseportdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$databaseportdeployment" == "x" ]]; then
	databaseportdeployment="3306"
fi
echo "Search admin database credential for deployement server in /etc/sellyoursaas.conf"
export databaseuserdeployment=`grep '^databaseuserdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$databaseuserdeployment" == "x" ]]; then
	databaseuserdeployment=$databaseuser
fi
databasepassdeployment=`grep '^databasepassdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$databasepassdeployment" == "x" ]]; then
	databasepassdeployment=$databasepass
fi 

dnsserver=`grep '^dnsserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$dnsserver" == "x" ]]; then
	echo Failed to get dns server parameters 
	exit 7
fi

export usecompressformatforarchive=`grep '^usecompressformatforarchive=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

export testorconfirm=$1

# For debug
echo "database = $database"
echo "testorconfirm = $testorconfirm"


MYSQL=`which mysql`
MYSQLDUMP=`which mysqldump` 

if [ "x$IPSERVERDEPLOYMENT" != "x" ]; then
	if [[ ! -d $archivedirtest ]]; then
		echo Failed to find archive directory $archivedirtest
		exit 8
	fi
	if [[ ! -d $archivedirpaid ]]; then
		echo Failed to find archive directory $archivedirpaid
		exit 9
	fi
fi

echo "***** Clean temporary files"

echo rm -f /tmp/instancefound*
rm -f /tmp/instancefound*
if [ -f /tmp/instancefound-dbinsellyoursaas ]; then
	echo Failed to delete file /tmp/instancefound-dbinsellyoursaas
	exit 21
fi
if [ -f /tmp/instancefound-activedbinsellyoursaas ]; then
	echo Failed to delete file /tmp/instancefound-activedbinsellyoursaas
	exit 20
fi
if [ -f /tmp/instancefound-dbinmysqldic ]; then
	echo Failed to delete file /tmp/instancefound-dbinmysqldic
	exit 19
fi

echo rm -f /tmp/osutoclean*
rm -f /tmp/osutoclean*
if [ -f /tmp/osutoclean ]; then
	echo Failed to delete file /tmp/osutoclean
	exit 13
fi
if [ -f /tmp/osutoclean-oldundeployed ]; then
	echo Failed to delete file /tmp/osutoclean-oldundeployed
	exit 14
fi

echo rm -f /tmp/osusernamefound*
rm -f /tmp/osusernamefound*
if [ -f /tmp/osusernamefound ]; then
	echo Failed to delete file /tmp/osusernamefound
	exit 15
fi

echo "Nettoyage vieux fichiers log"
echo find /home/admin/wwwroot/dolibarr_documents -maxdepth 1 -name "dolibarr*.log*" -type f -mtime +2 -delete
find /home/admin/wwwroot/dolibarr_documents -maxdepth 1 -name "dolibarr*.log*" -type f -mtime +2 -delete

echo "Nettoyage vieux fichiers /tmp"
echo find /tmp -mtime +30 -name 'phpsendmail*.log' -delete
find /tmp -mtime +30 -name 'phpsendmail*.log' -delete

echo "Nettoyage vieux fichiers conf"
echo find /home/admin/wwwroot/dolibarr/htdocs/conf -mtime +10 -name '*~' -delete
find /home/admin/wwwroot/dolibarr/htdocs/conf -mtime +10 -name '*~' -delete


echo "***** Clean available virtualhost that are not enabled hosts (safe)"
for fic in `ls /etc/apache2/sellyoursaas-available/*.*.*.*.conf /etc/apache2/sellyoursaas-available/*.home.lan 2>/dev/null`
do
	basfic=`basename $fic` 
	if [ ! -L /etc/apache2/sellyoursaas-online/$basfic ]; then
		echo Remove file with rm /etc/apache2/sellyoursaas-available/$basfic
		if [[ $testorconfirm == "confirm" ]]; then
			rm /etc/apache2/sellyoursaas-available/$basfic
		fi
	else
		echo "Site $basfic is enabled, we keep it"
	fi
done

echo "***** Clean available fpm pool that are not enabled hosts (safe)"
if [ -d /etc/apache2/sellyoursaas-fpm-pool ]; then
	for fic in `ls /etc/apache2/sellyoursaas-fpm-pool/*.*.*.*.conf /etc/apache2/sellyoursaas-fpm-pool/*.home.lan 2>/dev/null`
	do
		basfic=`basename $fic` 
		if [ ! -L /etc/apache2/sellyoursaas-online/$basfic ]; then
			echo Remove file with rm /etc/apache2/sellyoursaas-available/$basfic
			if [[ $testorconfirm == "confirm" ]]; then
				rm /etc/apache2/sellyoursaas-available/$basfic
			fi
		else
			echo "Site $basfic is enabled, we keep it"
		fi
	done
fi


echo "***** Get list of databases of all instances and save it into /tmp/instancefound-dbinsellyoursaas"

echo "#url=ref_customer	username_os	database_db status" > /tmp/instancefound-dbinsellyoursaas

Q1="use $database; "
Q2="SELECT c.ref_customer, ce.username_os, ce.database_db, ce.deployment_status FROM llx_contrat as c, llx_contrat_extrafields as ce WHERE ce.fk_object = c.rowid AND ce.deployment_status IS NOT NULL";
SQL="${Q1}${Q2}"

echo "$MYSQL -h $databasehost -P $databaseport -u$databaseuser -pxxxxxx -e '$SQL' | grep -v 'ref_customer'"
$MYSQL -h $databasehost -P $databaseport -u$databaseuser -p$databasepass -e "$SQL" | grep -v 'ref_customer' >> /tmp/instancefound-dbinsellyoursaas
if [ "x$?" != "x0" ]; then
	echo "Failed to make first SQL request to get instances. Exit 1."
	exit 16
fi


echo "***** Get list of databases of known active instances and save it into /tmp/instancefound-activedbinsellyoursaas"

echo "#url=ref_customer	username_os	database_db status" > /tmp/instancefound-activedbinsellyoursaas

Q1="use $database; "
Q2="SELECT c.ref_customer, ce.username_os, ce.database_db, ce.deployment_status, ce.deployment_host FROM llx_contrat as c, llx_contrat_extrafields as ce WHERE ce.fk_object = c.rowid AND ce.deployment_status IN ('processing','done')";
SQL="${Q1}${Q2}"

echo "$MYSQL -h $databasehost -P $databaseport -u$databaseuser -pxxxxxx -e '$SQL' | grep -v 'ref_customer'"
$MYSQL -h $databasehost -P $databaseport -u$databaseuser -p$databasepass -e "$SQL" | grep -v 'ref_customer' >> /tmp/instancefound-activedbinsellyoursaas
if [ "x$?" != "x0" ]; then
	echo "Failed to make second SQL request to get instances. Exit 1."
	exit 17
fi


echo "***** Get list of databases available in mysql local and save it into /tmp/instancefound-dbinmysqldic (seems not used)"

Q1="use mysql; "
Q2="SHOW DATABASES; ";
SQL="${Q1}${Q2}"

echo "$MYSQL -h $databasehostdeployment -P $databaseportdeployment -u$databaseuserdeployment -pxxxxxx -e '$SQL' | grep 'dbn' "
$MYSQL -h $databasehostdeployment -P $databaseportdeployment -u$databaseuserdeployment -p$databasepassdeployment -e "$SQL" | grep 'dbn' | awk ' { print $1 } ' >> /tmp/instancefound-dbinmysqldic
if [ "x$?" != "x0" ]; then
	echo "Failed to make third SQL request to get instances. Exit 1."
	exit 18
fi


echo "***** Search osu unix account without home in $targetdir (should never happen)"
echo grep '^osu' /etc/passwd | cut -f 1 -d ':'
for osusername in `grep '^osu' /etc/passwd | cut -f 1 -d ':'`
do
	if [ ! -d $targetdir/$osusername ]; then
		echo User $osusername has no home. Should not happen.
		exit 12
	fi
done

echo "***** Search home in $targetdir without osu unix account (should never happen)"
echo "ls -d $targetdir/osu*";
for osusername in `ls -d $targetdir/osu* 2>/dev/null`
do
	export osusername=`basename $osusername`
	if ! grep "$osusername" /etc/passwd > /dev/null; then
		echo User $osusername has a home in $targetdir but is not inside /etc/passwd. Should not happen.
		exit 11
	fi
done

if [ "x$IPSERVERDEPLOYMENT" != "x" ]; then
	echo "***** Search from /tmp/instancefound-activedbinsellyoursaas of active databases (with known osusername) with a non existing unix user (should never happen)" 
	while read bidon osusername dbname deploymentstatus ipserverdeployment; do 
		if [[ "x$osusername" != "xusername_os" && "x$osusername" != "xunknown" && "x$osusername" != "xNULL" && "x$dbname" != "xNULL" ]]; then
			echo $ipserverdeployment | grep "$IPSERVERDEPLOYMENT" > /dev/null 2>&1
			notfoundip=$?
			#echo notfoundip=$notfoundip
	
			if [[ $notfoundip == 0 ]]; then
			    # The current line of instancefound-activedbinsellyoursaas is for an instance with files deployed on this server
		    	id $osusername >/dev/null 2>/dev/null
		    	if [[ "x$?" == "x1" ]]; then
					echo Line $bidon $osusername $dbname $deploymentstatus $ipserverdeployment is for a user on this server that does not exists. Should not happen.
					exit 10
		    	fi
		    fi
	    fi
	done < /tmp/instancefound-activedbinsellyoursaas
fi


if [ "x$IPSERVERDEPLOYMENT" != "x" ]; then
	echo "***** Search home in $targetdir without instance active (should never happen)"
	echo "ls -d $targetdir/osu*";
	for osusername in `ls -d $targetdir/osu* 2>/dev/null`
	do
		export osusername=`basename $osusername`
		if ! grep "$osusername" /tmp/instancefound-dbinsellyoursaas > /dev/null; then
			echo --- User $osusername has a home in $targetdir but is not inside /tmp/instancefound-dbinsellyoursaas. Should not happen.
			echo List of documents in dir /home/jail/home/$osusername/dbn*:
			ls /home/jail/home/$osusername/dbn*
			echo List of found virtualhost instance pointing to this user home:
			export vhfile=`grep -l $osusername /etc/apache2/sellyoursaas-available/*.conf`
			echo $vhfile
			export vhfile2=`grep -l $osusername /etc/apache2/sellyoursaas-enabled/*.conf`
			echo $vhfile2
			echo Check that there is no bug on script and you can delete user with
			if [ "x$vhfile" != "x" ]; then
				echo "rm $vhfile; "
			fi
			if [ "x$vhfile2" != "x" ]; then
				echo "rm $vhfile2; "
			fi
			echo "mkdir $archivedirtest/$osusername; deluser --remove-home --backup --backup-to $archivedirtest/$osusername $osusername; deluser --group $osusername"
			echo
			exit 9
		fi
	done
fi


# We disable this because when we undeploy, user is kept and we want to remove it only 1 month after undeployment date (processed by next point)
# TODO Build the file /tmp/instancefound-olduninstalleddbinsellyoursaas
#echo "***** Search from /tmp/instancefound-olduninstalleddbinsellyoursaas: osu unix account with record in /etc/passwd but not in instancefound-olduninstalleddbinsellyoursaas" 
#cat /tmp/instancefound-dbinsellyoursaas | awk '{ if ($2 != "username_os" && $2 != "unknown" && $2 != "NULL") print $2":" }' > /tmp/osusernamefound
#if [ -s /tmp/osusernamefound ]; then
#	for osusername in `grep -v /etc/passwd -f /tmp/osusernamefound | grep '^osu'`
#	do
#		tmpvar1=`echo $osusername | awk -F ":" ' { print $1 } '`
#		echo User $tmpvar1 is an ^osu user in /etc/passwd but has no active instance in /tmp/instancefound-olduninstalleddbinsellyoursaas
#		exit 9
#	done
#fi


if [ "x$IPSERVERDEPLOYMENT" != "x" ]; then
	echo "***** Search osu unix account for $IPSERVERDEPLOYMENT with very old undeployed database into /tmp/osutoclean-oldundeployed and search entries with existing home dir and without dbn* subdir, and save it into /tmp/osutoclean" 
	Q1="use $database; "
	Q2="SELECT ce.username_os FROM llx_contrat as c, llx_contrat_extrafields as ce WHERE c.rowid = ce.fk_object AND ce.deployment_host = '$IPSERVERDEPLOYMENT' AND c.rowid IN ";
	Q3=" (SELECT fk_contrat FROM llx_contratdet as cd, llx_contrat_extrafields as ce2 WHERE cd.fk_contrat = ce2.fk_object AND cd.STATUT = 5 AND ce2.deployment_status = 'undeployed' AND ce2.undeployment_date < ADDDATE(NOW(), INTERVAL -1 MONTH)); ";
	SQL="${Q1}${Q2}${Q3}"

	echo "$MYSQL -h $databasehost -P $databaseport -u$databaseuser -pxxxxxx -e $SQL"
	$MYSQL -h $databasehost -P $databaseport -u$databaseuser -p$databasepass -e "$SQL" | grep '^osu' >> /tmp/osutoclean-oldundeployed
	if [ -s /tmp/osutoclean-oldundeployed ]; then
		for osusername in `cat /tmp/osutoclean-oldundeployed`
		do
			tmpvar1=`echo $osusername | awk -F ":" ' { print $1 } '`
			if [ -d $targetdir/$osusername ]; then
				nbdbn=`ls $targetdir/$osusername/ | grep ^dbn | wc -w`
				if [[ "x$nbdbn" == "x0" ]]; then
					echo "User $tmpvar1 is an ^osu user in /tmp/osutoclean-oldundeployed but has still a home dir with no more dbn... into, so we will remove it"
					echo $tmpvar1 >> /tmp/osutoclean
				fi
			fi
		done
	fi
fi


echo "***** Loop on each user in /tmp/osutoclean to make a clean"
if [ -s /tmp/osutoclean ]; then

	export reloadapache=1
	
	cat /tmp/osutoclean | grep '^osu' | sort -u
	for osusername in `grep '^osu' /tmp/osutoclean | sort -u`
	do
		echo "***** Archive and delete qualified user $osusername found in /tmp/osutoclean"
		
		echo Try to find database and instance name from username $osusername
		export instancename=""
		export dbname=""
		export instancename=`grep $osusername /tmp/instancefound-dbinsellyoursaas | cut -f 1`
		export dbname=`grep $osusername /tmp/instancefound-dbinsellyoursaas | cut -f 3`
		
		echo For osusername=$osusername, dbname is $dbname, instancename is $instancename, databasehostdeployment is $databasehostdeployment
		
		# If dbname is known
		if [[ "x$dbname" != "x" ]]; then	
			if [[ "x$dbname" != "xNULL" ]]; then	
				echo "Do a dump of database $dbname - may fails if already removed"
				mkdir -p $archivedirtest/$osusername
				if [[ -x /usr/bin/zstd && "x$usecompressformatforarchive" == "xzstd" ]]; then
					echo "$MYSQLDUMP --no-tablespaces -h $databasehostdeployment -P $databaseportdeployment -u$databaseuserdeployment -pxxxxxx $dbname | zstd -z -9 -q > $archivedirtest/$osusername/dump.$dbname.$now.sql.zst"
					$MYSQLDUMP --no-tablespaces -h $databasehostdeployment -P $databaseportdeployment -u$databaseuserdeployment -p$databasepassdeployment $dbname | zstd -z -9 -q > "$archivedirtest/$osusername/dump.$dbname.$now.sql.zst"
				else
					echo "$MYSQLDUMP --no-tablespaces -h $databasehostdeployment -P $databaseportdeployment -u$databaseuserdeployment -pxxxxxx $dbname | gzip > $archivedirtest/$osusername/dump.$dbname.$now.sql.tgz"
					$MYSQLDUMP --no-tablespaces -h $databasehostdeployment -P $databaseportdeployment -u$databaseuserdeployment -p$databasepassdeployment $dbname | gzip > "$archivedirtest/$osusername/dump.$dbname.$now.sql.tgz"
				fi

				echo "Now drop the database"
				echo "echo 'DROP DATABASE $dbname;' | $MYSQL -h $databasehostdeployment -P $databaseportdeployment -u$databaseuserdeployment -pxxxxxx $dbname"
				if [[ $testorconfirm == "confirm" ]]; then
					echo "DROP DATABASE $dbname;" | $MYSQL -h $databasehostdeployment -P $databaseportdeployment -u$databaseuserdeployment -p$databasepassdeployment $dbname
				fi	
			fi
		fi


		# If osusername is known, remove user and archive dir (Note: archive with clean.sh is always done in test !!!!!!!)
		if [[ "x$osusername" != "x" ]]; then	
			if [[ "x$osusername" != "xNULL" ]]; then
				echo rm -f $targetdir/$osusername/$dbname/*.log
				rm -f $targetdir/$osusername/$dbname/*.log >/dev/null 2>&1 
				echo rm -f $targetdir/$osusername/$dbname/*.log.*
				rm -f $targetdir/$osusername/$dbname/*.log.* >/dev/null 2>&1 
				
				echo "clean $instancename (a clean means archive user dir and delete user, group and cron)" >> $archivedirtest/$osusername/clean-$instancename.txt
				
				echo crontab -r -u $osusername
				crontab -r -u $osusername
	
				echo deluser --remove-home --backup --backup-to $archivedirtest/$osusername $osusername
				if [[ $testorconfirm == "confirm" ]]; then
					deluser --remove-home --backup --backup-to $archivedirtest/$osusername $osusername
				fi
				
				echo deluser --group $osusername
				if [[ $testorconfirm == "confirm" ]]; then
					deluser --group $osusername
				fi
				
				# If dir still exists, we move it manually
				if [ -d "$targetdir/$osusername" ]; then
					echo The dir $targetdir/$osusername still exists when user does not exists anymore, we archive it manually
					echo mv -f $targetdir/$osusername $archivedirtest
					echo cp -pr $targetdir/$osusername $archivedirtest
					if [[ $testorconfirm == "confirm" ]]; then
						mv -f $targetdir/$osusername $archivedirtest 2>/dev/null
						cp -pr $targetdir/$osusername $archivedirtest
						rm -fr $targetdir/$osusername
						chown -R root $archivedirtest/$osusername
					fi
				fi
			fi
		fi
		
		export ZONENOHOST=`echo $instancename | cut -d . -f 2-`
		export ZONE="$ZONENOHOST.hosts" 
		export instancenameshort=`echo $instancename | cut -d . -f 1`
	
		# If instance name known
		if [ "x$instancenameshort" != "x" ]; then
			if [ "x$instancenameshort" != "xNULL" ]; then

				if [[ "$dnsserver" == "1" ]]; then
					if [ -f /etc/bind/${ZONE} ]; then
						echo "   ** Remove DNS entry for $instancenameshort from /etc/bind/${ZONE}"
						cat /etc/bind/${ZONE} | grep "^$instancenameshort " > /dev/null 2>&1
						notfound=$?
						echo notfound=$notfound
						
						if [[ $notfound == 0 ]]; then
				
							echo "cat /etc/bind/${ZONE} | grep -v '^$instancenameshort ' > /tmp/${ZONE}.$PID"
							cat /etc/bind/${ZONE} | grep -v "^$instancenameshort " > /tmp/${ZONE}.$PID
						
							# we're looking line containing this comment
							export DATE=`date +%y%m%d%H`
							export NEEDLE="serial number"
						    curr=$(/bin/grep -e "${NEEDLE}$" /tmp/${ZONE}.$PID | /bin/sed -n "s/^\s*\([0-9]*\)\s*;\s*${NEEDLE}\s*/\1/p")
						    # replace if current date is shorter (possibly using different format)
						    echo "Current bind counter is $curr"
						    if [ ${#curr} -lt ${#DATE} ]; then
						      serial="${DATE}00"
						    else
						      prefix=${curr::-2}
						      if [ "$DATE" -eq "$prefix" ]; then 	# same day
						        num=${curr: -2} # last two digits from serial number
						        num=$((10#$num + 1)) # force decimal representation, increment
						        serial="${DATE}$(printf '%02d' $num )" # format for 2 digits
						      else
						        serial="${DATE}00" # just update date
						      fi
						    fi
						    echo Replace serial in /tmp/${ZONE}.$PID with ${serial}
						    /bin/sed -i -e "s/^\(\s*\)[0-9]\{0,\}\(\s*;\s*${NEEDLE}\)$/\1${serial}\2/" /tmp/${ZONE}.$PID
						    
						    echo Test temporary file /tmp/${ZONE}.$PID
							named-checkzone ${ZONENOHOST} /tmp/${ZONE}.$PID
							if [[ "$?x" != "0x" ]]; then
								echo Error when editing the DNS file during clean.sh. File /tmp/${ZONE}.$PID is not valid 
								exit 22
							fi 
							
							echo "   ** Archive file with cp /etc/bind/${ZONE} /etc/bind/archives/${ZONE}-$now"
							cp /etc/bind/${ZONE} /etc/bind/archives/${ZONE}-$now
							
							echo "   ** Move new host file"
							echo mv -fu /tmp/${ZONE}.$PID /etc/bind/${ZONE}
							if [[ $testorconfirm == "confirm" ]]; then
								mv -fu /tmp/${ZONE}.$PID /etc/bind/${ZONE}
							fi
							
							echo "   ** Reload dns with rndc reload ${ZONENOHOST}"
							if [[ $testorconfirm == "confirm" ]]; then
								rndc reload ${ZONENOHOST}
								#/etc/init.d/bind9 reload
							fi
						fi
					fi
				fi
				
				apacheconf=/etc/apache2/sellyoursaas-online/$instancename.conf
				
				if [ -f $apacheconf ]; then
					echo "   ** Disable apache conf with rm"
					echo rm /etc/apache2/sellyoursaas-online/$instancename.conf
					echo rm /etc/apache2/sellyoursaas-online/$instancename.custom.conf
					if [[ $testorconfirm == "confirm" ]]; then
						rm /etc/apache2/sellyoursaas-online/$instancename.conf
						rm /etc/apache2/sellyoursaas-online/$instancename.custom.conf
					fi
				fi
	
				echo "   ** Remove apache conf /etc/apache2/sellyoursaas-available/$instancename.conf"
				if [[ -f /etc/apache2/sellyoursaas-available/$instancename.conf ]]; then
					echo rm /etc/apache2/sellyoursaas-available/$instancename.conf
					if [[ $testorconfirm == "confirm" ]]; then
						rm /etc/apache2/sellyoursaas-available/$instancename.conf
					fi
				else
					echo File /etc/apache2/sellyoursaas-available/$instancename.conf already deleted
				fi
				echo "   ** Remove apache conf /etc/apache2/sellyoursaas-online/$instancename.custom.conf"
				if [[ -f /etc/apache2/sellyoursaas-online/$instancename.custom.conf ]]; then
					echo rm /etc/apache2/sellyoursaas-online/$instancename.custom.conf
					if [[ $testorconfirm == "confirm" ]]; then
						rm /etc/apache2/sellyoursaas-online/$instancename.custom.conf
					fi
				else
					echo File /etc/apache2/sellyoursaas-available/$instancename.custom.conf already deleted
				fi
			
				/usr/sbin/apache2ctl configtest
				if [[ "x$?" != "x0" ]]; then
					echo Error when running apache2ctl configtest 
				else 
					echo "   ** Apache tasks finished with configtest ok"
				fi
			fi
		fi
		
	done

	# Restart apache
	echo service apache2 reload
	if [[ "x$reloadapache" == "x1" ]]; then
		if [[ $testorconfirm == "confirm" ]]; then
			service apache2 reload
			if [[ "x$?" != "x0" ]]; then
				echo "Error when running service apache2 reload. Exit 3"
				exit 3
			fi
		fi
	else
		echo "An error was found with apache2ctl configtest so no service apache2 reload was done. Exit 2"
		exit 2
	fi
fi

# Now clean orphaned crontabs files
echo "***** Now clean orphan crontabs files"
for fic in `ls /var/spool/cron/crontabs`;
do
	id $fic >/dev/null 2>/dev/null
	if [[ "x$?" == "x1" ]]; then
		echo Found a crontabs file for user $fic that does not exists. We clean crontabs file.
		mv /var/spool/cron/crontabs/$fic /var/spool/cron/crontabs.disabled 
	fi
done;

# Now clean also old dir in archives-test
echo "***** Now clean also old dir in $archivedirtest - 15 days after being archived"
cd $archivedirtest
# Note we can't use delete because search and delete is on dir and dir may contains some files
find $archivedirtest -maxdepth 1 -name 'osu*' -path '*archives*' -type d -mtime +15 -exec rm -fr {} \;

# Now clean also old dir in archives-paid
echo "***** Now clean also old dir in $archivedirpaid - 90 days after being archived"
cd $archivedirpaid
# Note we can't use delete because search and delete is on dir and dir may contains some files
find $archivedirpaid -maxdepth 1 -name 'osu*' -path '*archives*' -type d -mtime +90 -exec rm -fr {} \;

if [[ "$dnsserver" == "1" ]]; then
	# Now clean also old files in $archivedirbind
	echo "***** Now clean also old files in $archivedirbind - 15 days after being archived"
	cd $archivedirbind
	find $archivedirbind -maxdepth 1 -type f -path '*archives*' -mtime +15 -delete
fi

# Now clean also old files in $archivedircron
echo "***** Now clean also old files in $archivedircron - 15 days after being archived"
cd $archivedircron
find $archivedircron -maxdepth 1 -type f -path '*cron*' -mtime +15 -delete

# Now clean miscellaneous files
echo "***** Now clean miscellaneous files"
rm /var/log/repair.lock > /dev/null 2>&1

# Now clean old journalctl files
echo "***** Now clean journal files older than 60 days"
echo "find '/var/log/journal/*/user-*.journal' -type f -path '/var/log/journal/*/user-*.journal' -mtime +60 -delete"
find "/var/log/journal/" -type f -path '/var/log/journal/*/user-*.journal' -mtime +60 -delete

# Clean tmp files


# Now clean also old dir in archives-test
if [[ "x$masterserver" == "x1" ]]; then
	echo "***** We are on a master, so we clean sellyoursaas temp files" 
	echo "Clean sellyoursaas temp files"
	find "/home/admin/wwwroot/dolibarr_documents/sellyoursaas/temp/." ! -path "/home/admin/wwwroot/dolibarr_documents/sellyoursaas/temp/" -mtime +1 -delete
fi

# Clean log files
if [[ "x$instanceserver" != "x0" ]]; then
	echo "***** We are on a deployment server, so we clean log files and history files" 
	echo "Clean web server _error logs"
	for fic in `ls -art $targetdir/osu*/dbn*/*_error.log 2>/dev/null`; do > $fic; done
	echo "Clean applicative log files"
	for fic in `ls -art $targetdir/osu*/dbn*/documents/dolibarr*.log 2>/dev/null`; do > $fic; done
	for fic in `ls -art $targetdir/osu*/dbn*/htdocs/files/_log/*.log 2>/dev/null`; do > $fic; done
	for fic in `ls -art $targetdir/osu*/.mysql_history 2>/dev/null`; do rm $fic; done
fi


# Clean archives 
if [ "x$2" == "xoldtempinarchive" ]; then
	echo "Clean archives dir from not expected files (should not be required anymore). Archives are no more tree of files but an archive since 1st of july 2019".
	echo "find '$archivedirpaid' -type d -path '*/osu*/temp' -delete"
	find "$archivedirpaid" -type d -path '*/osu*/temp' -delete
	echo "find '$archivedirtest' -type d -path '*/osu*/temp' -delete"
	find "$archivedirtest" -type d -path '*/osu*/temp' -delete
fi

if [[ $testorconfirm == "confirm" ]]; then
	echo "***** Clean temporary files"
	
	echo rm -f /tmp/instancefound*
	rm -f /tmp/instancefound*
	echo rm -f /tmp/osutoclean*
	rm -f /tmp/osutoclean*
	echo rm -f /tmp/osusernamefound*
	rm -f /tmp/osusernamefound*
	echo rm -f /tmp/idlistofdb
	rm -f /tmp/idlistofdb
fi


echo
echo TODO Manually...

# Clean database users
echo "***** We should also clean mysql db and user table (used for permission) for deleted databases and deleted users"
SQL="use mysql; delete from db where Db NOT IN (SELECT schema_name FROM information_schema.schemata) and Db like 'dbn%';"
echo You can execute
echo "$MYSQL -h $databasehostdeployment -P $databaseportdeployment -u$databaseuserdeployment -e \"$SQL\" -pxxxxxx"
#$MYSQL -h $databasehostdeployment -P $databaseportdeployment -u$databaseuserdeployment -p$databasepassdeployment -e "$SQL"
SQL="use mysql; delete from user where User NOT IN (SELECT User from db) and User like 'dbu%';"
echo You can execute
echo "$MYSQL -h $databasehostdeployment -P $databaseportdeployment -u$databaseuserdeployment -e \"$SQL\" -pxxxxxx"
#$MYSQL -h $databasehostdeployment -P $databaseportdeployment -u$databaseuserdeployment -p$databasepassdeployment -e "$SQL"

if [[ $testorconfirm == "test" ]]; then
	echo "***** We can also list all databases that are present on disk but with status 'undeployed' so we can force to undeployed them correctly again"
	rm -f /tmp/idlistofdb
	>> /tmp/idlistofdb
	for fic in `ls -rt /var/lib/mysql /mnt/diskhome/mysql 2>/dev/null | grep dbn 2>/dev/null`; 
	do 
		echo -n " '"$fic"'," >> /tmp/idlistofdb
	done
	export idlistofdb=`cat /tmp/idlistofdb | sed -e 's/,$//' `
	if [[ "x$idlistofdb" != "x" ]]; then
		echo "echo 'DROP TABLE llx_contracttoupdate_tmp;' | $MYSQL -h $databasehost -P $databaseport -u$databaseuser -pxxxxxx $database"
		echo "DROP TABLE llx_contracttoupdate_tmp;" | $MYSQL -h $databasehost -P $databaseport -u$databaseuser -p$databasepass $database
		echo "echo 'CREATE TABLE llx_contracttoupdate_tmp AS SELECT s.nom, s.client, c.rowid, c.ref, c.ref_customer, ce.deployment_date_start, ce.undeployment_date FROM llx_contrat as c LEFT JOIN llx_societe as s ON s.rowid = c.fk_soc, llx_contrat_extrafields as ce WHERE c.rowid = ce.fk_object AND ce.database_db IN (0) AND ce.deployment_status = 'undeployed';' | $MYSQL -usellyoursaas -pxxxxxx -h $databasehost $database"
		echo "CREATE TABLE llx_contracttoupdate_tmp AS SELECT s.nom, s.client, c.rowid, c.ref, c.ref_customer, ce.deployment_date_start, ce.undeployment_date FROM llx_contrat as c LEFT JOIN llx_societe as s ON s.rowid = c.fk_soc, llx_contrat_extrafields as ce WHERE c.rowid = ce.fk_object AND ce.database_db IN ($idlistofdb) AND ce.deployment_status = 'undeployed';" | $MYSQL -usellyoursaas -p$databasepass -h $databasehost $database
		echo If there is some contracts not correctly undeployed, they are into llx_contracttoupdate_tmp of database master.
		echo You can execute "update llx_contrat_extrafields set deployment_status = 'done' where deployment_status = 'undeployed' AND fk_object in (select rowid from llx_contracttoupdate_tmp);"
	fi
fi

# Clean backup dir of instances that are now archived
> /tmp/deletedirs.sh
for fic in `find $backupdir/*/last_mysqldump*.txt -name "last_mysqldump*.txt" -mtime +90 | grep -v ".ok.txt"`
do
	noyoungfile=1
	dirtoscan=`dirname $fic`
	osusername=`basename $dirtoscan`
	for fic2 in `find $dirtoscan/last_mysqldump* -name "last_mysqldump*" -mtime -90`
	do
		noyoungfile=0
	done
	if [[ "x$noyoungfile" == "x1" ]]; then
		if [ -d "$archivedirpaid/$osusername" ]; then
			echo "# ----- $fic - $noyoungfile - archive dir $archivedirpaid/$osusername exists, we can remove backup" >> /tmp/deletedirs.sh
			echo "rm -fr "`dirname $fic` >> /tmp/deletedirs.sh
		else
			echo "# ----- $fic - $noyoungfile" >> /tmp/deletedirs.sh
			echo "# NOTE Dir $archivedirpaid/$osusername does not exists. It means instance was not archived !!! Do it with:" >> /tmp/deletedirs.sh
			echo "mv $backupdir/$osusername $archivedirpaid/$osusername; chown -R root:root $archivedirpaid/$osusername" >> /tmp/deletedirs.sh
		fi
	else
        echo "# ----- $fic - $noyoungfile - backup dir $dirtoscan exists with a very old last_mysqldump* file but was still active recently in backup. We must keep it." >> /tmp/deletedirs.sh
        echo "# ALERT This may happen when an instance is renamed so mysqldump of old name is old and the one of new name is new." >> /tmp/deletedirs.sh
		echo "#rm $fic" >> /tmp/deletedirs.sh
        echo "#rm -fr "`dirname $fic` >> /tmp/deletedirs.sh
	fi
done
if [ -s /tmp/deletedirs.sh ]; then
	echo "***** We should also clean backup of paying instances in $backupdir/osusername/ that are no more saved since a long time (last_mysqldump > 90days) and that are archived" 
	echo You can execute commands into file /tmp/deletedirs.sh
fi

echo 

exit 0
