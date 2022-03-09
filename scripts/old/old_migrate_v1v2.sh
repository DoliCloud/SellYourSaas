#!/bin/bash

export now=`date +'%Y-%m-%d %H:%M:%S'`

echo
#echo "####################################### ${0} ${1}"
#echo "${0} ${@}"
#echo "# user id --------> $(id -u)"
#echo "# now ------------> $now"
#echo "# PID ------------> ${$}"
#echo "# PWD ------------> $PWD" 
#echo "# arguments ------> ${@}"
#echo "# path to me -----> ${0}"
#echo "# parent path ----> ${0%/*}"
#echo "# my name --------> ${0##*/}"
#echo "# realname -------> $(realpath ${0})"
#echo "# realname name --> $(basename $(realpath ${0}))"
#echo "# realname dir ---> $(dirname $(realpath ${0}))"

export PID=${$}
export scriptdir=$(dirname $(realpath ${0}))
export ZONE="on.dolicloud.com.hosts" 
export REMOTEIP='79.137.96.15'


echo "***** $0 *****"

if [ "$(id -u)" != "0" ]; then
	echo "This script must be run as root" 1>&2
	exit 1
fi

if [ "x$1" == "x" ]; then
	echo "You must select instances first with this and run then with option confirm:"
	sql="SELECT name as '#name' from app_instance where status = 'DEPLOYED' AND access_enabled = true AND customer_id IN (select customer.id from customer, address where manual_collection = false and customer.address_id = address.id and address.country = 'XX') ORDER BY deployed_date LIMIT 5" 
	echo "echo \"$sql\" | mysql -Dsaasplex -uxxx -pyyy -hzzz > $scriptdir/filetomigrate.txt"
	#echo "where xxx can be found with:  sudo cat /etc/mysql/debian.cnf |grep password | sort -u | awk ' { print \$3; }'"
	echo
	echo There is currently `cat $scriptdir/filetomigrate.txt | grep -v '#' | wc -l` records in filetomigrate.txt
	echo
	echo "Usage: $0 confirm|migrate|dns|disablev1"
	exit 1
fi

if [[ ! -f $scriptdir/filetomigrate.txt ]]; then
	echo Error failed to find file $scriptdir/filetomigrate.txt with list of instances to migrate.
	echo
	echo "You must select instances first with this and run then with option confirm:"
	sql="SELECT name as '#name' from app_instance where status = 'DEPLOYED' AND access_enabled = true AND customer_id IN (select customer.id from customer, address where manual_collection = false and customer.address_id = address.id and address.country = 'XX') ORDER BY deployed_date LIMIT 5" 
	echo "echo \"$sql\" | mysql -Dsaasplex -uxxx -pyyy -hzzz > $scriptdir/filetomigrate.txt"
	#echo "where xxx can be found with:  sudo cat /etc/mysql/debian.cnf |grep password | sort -u | awk ' { print \$3; }'"
	echo
	echo There is currently `cat $scriptdir/filetomigrate.txt | grep -v '#' | wc -l` records in filetomigrate.txt
	echo
	echo "Usage: $0 confirm"
	exit 1
fi

if [ "x$1" == "xconfirm" -o "x$1" == "xmigrate" ]; then
	echo There is currently `cat $scriptdir/filetomigrate.txt | grep -v '#' | wc -l` records in filetomigrate.txt
	echo
	echo "Purge filetomigrate.ok|ko"
	> $scriptdir/filetomigrate.ok
	> $scriptdir/filetomigrate.ko
fi

# Make migration
if [ "x$1" == "xconfirm" -o "x$1" == "xmigrate" ]; then
	echo "----- Make migration. Loop on $scriptdir/filetomigrate.txt"
	export i=0
	for instancename in `cat $scriptdir/filetomigrate.txt | sed -e 's!.on.dolicloud.com!!g' | grep -v '#'`
	do
		if [[ "x$instancename" != "x" ]]; then
		    export i=$(($i + 1))
			echo Try $i to migrate $instance with php old_migrate_v1v2.php $instancename $instancename confirm
			php old_migrate_v1v2.php $instancename $instancename confirm
			result=$?
			if [[ "x$result" == "x0" ]]; then
				echo $instancename >> $scriptdir/filetomigrate.ok
			else
				echo $instancename >> $scriptdir/filetomigrate.ko
			fi
			echo Result = $result
		fi
	done
fi

# Fix DNS
if [ "x$1" == "xconfirm" -o "x$1" == "xdns" ]; then
	echo "----- Change DNS for entries into $scriptdir/filetomigrate.ok"
	for instancename in `cat $scriptdir/filetomigrate.ok | sed -e 's!.on.dolicloud.com!!g' | grep -v '#'`
	do
		echo `date +'%Y-%m-%d %H:%M:%S'`" **** Archive file with cp /etc/bind/${ZONE} /etc/bind/archives/${ZONE}-$now"
		cp /etc/bind/${ZONE} /etc/bind/archives/${ZONE}-$now
	
		if [[ "x$instancename" != "x" ]]; then
			
			echo Remove and add DNS for $instancename
			
			echo "cat /etc/bind/${ZONE} | grep -v '^$instancename ' > /tmp/${ZONE}.$PID"
			cat /etc/bind/${ZONE} | grep -v "^$instancename " > /tmp/${ZONE}.$PID
	
			echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Add $instancename A $REMOTEIP into tmp host file /tmp/${ZONE}.$PID"
			echo $instancename A $REMOTEIP >> /tmp/${ZONE}.$PID  
	
			echo `date +'%Y-%m-%d %H:%M:%S'`" **** Move new host file with mv -fu /tmp/${ZONE}.$PID /etc/bind/${ZONE}"
			mv -fu /tmp/${ZONE}.$PID /etc/bind/${ZONE}
	
		fi
	done
fi

# Disable V1
if [ "x$1" == "xconfirm" -o "x$1" == "xdisablev1" ]; then
	echo "----- Disable V1 by switching to manual collection"
	sql="UPDATE customer set manual_collection = true where id IN (SELECT customer_id FROM app_instance WHERE name IN ("
	for instancename in `cat $scriptdir/filetomigrate.ok | sed -e 's!.on.dolicloud.com!!g' | grep -v '#'`
	do
		if [[ "x$instancename" != "x" ]]; then
			sql="$sql'$instancename.on.dolicloud.com'," 
		fi
	done
	sql="$sql'bidon'));"
	echo "WARNING !!! Now you must flag all successfuly migrated instances as migrated on source with:"
	echo "echo \"$sql\" | mysql -Dsaasplex -uxxx -pyyy -hzzz"
	echo "WARNING !!! You must increase serial of DNS file /etc/bind/on.dolicloud.com.hosts and restart with:"
	echo "rndc reload on.dolicloud.com"
fi

