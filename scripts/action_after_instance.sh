#!/bin/bash

# To use this script with remote ssh (not required when using the remote agent):
# Create a symbolic link to this file .../action_after_instance.sh into /usr/bin
# Grant adequate permissions (550 mean root and group www-data can read and execute, nobody can write)
# sudo chown root:www-data /usr/bin/action_after_instance_instance.sh
# sudo chmod 550 /usr/bin/action_after_instance.sh
# And allow apache to sudo on this script by doing visudo to add line:
#www-data        ALL=(ALL) NOPASSWD: /usr/bin/action_website_instance.sh


export now=`date +'%Y-%m-%d %H:%M:%S'`

echo
echo
echo "####################################### ${0} ${1}"
echo "${0} ${@}"
echo "# user id --------> $(id -u)"
echo "# now ------------> $now"
echo "# PID ------------> ${$}"
echo "# PWD ------------> $PWD" 
echo "# arguments ------> ${@}"
echo "# parent path ----> ${0%/*}"
echo "# realname name --> $(basename $(realpath ${0}))"
echo "# realname dir ---> $(dirname $(realpath ${0}))"

export PID=${$}
export ZONES_PATH="/etc/bind/zones"
export scriptdir=$(dirname $(realpath ${0}))

if [ "$(id -u)" != "0" ]; then
	echo "This script must be run as root" 1>&2
	exit 100
fi

if [ "x$1" == "x" ]; then
	echo "Missing parameter 1 - mode (addwebsite|removewebsite)" 1>&2
	exit 1
fi
if [ "x$2" == "x" ]; then
	echo "Missing parameter 2 - osusername" 1>&2
	exit 2
fi
if [ "x$3" == "x" ]; then
	echo "Missing parameter 3 - ospassword" 1>&2
	exit 3
fi
if [ "x$4" == "x" ]; then
	echo "Missing parameter 4 - instancename" 1>&2
	exit 4
fi
if [ "x$5" == "x" ]; then
	echo "Missing parameter 5 - domainname" 1>&2
	exit 5
fi
if [ "x$6" == "x" ]; then
	echo "Missing parameter 6 - dbname" 1>&2
	exit 6
fi
if [ "x$7" == "x" ]; then
	echo "Missing parameter 7 - dbport" 1>&2
	exit 7
fi
if [ "x${23}" == "x" ]; then
	echo "Missing parameter 23 - REMOTEIP" 1>&2
	exit 23
fi


export mode=$1
export osusername=$2
export ospassword=$3
export instancename=$4
export domainname=$5

export dbname=$6
export dbport=$7
export dbusername=$8
export dbpassword=$9

export fileforconfig1=${10}
export targetfileforconfig1=${11}
export dirwithdumpfile=${12}
export dirwithsources1=${13}
export targetdirwithsources1=${14}
export dirwithsources2=${15}
export targetdirwithsources2=${16}
export dirwithsources3=${17}
export targetdirwithsources3=${18}
export cronfile=${19}
export cliafter=${20}
export targetdir=${21}
export EMAILTO=${22}
export REMOTEIP=${23}
export SELLYOURSAAS_ACCOUNT_URL=${24}
export instancenameold=${25}
export domainnameold=${26}
export customurl=${27//£/ }
if [ "x$customurl" == "x-" ]; then
	customurl=""
fi
export contractlineid=${28}
export EMAILFROM=${29}
export CERTIFFORCUSTOMDOMAIN=${30}
export archivedir=${31}
export SSLON=${32}
export apachereload=${33}
export ALLOWOVERRIDE=${34//£/ }
if [ "x$ALLOWOVERRIDE" == "x-" ]; then
	ALLOWOVERRIDE=""
fi
export VIRTUALHOSTHEAD=${35//£/ }
if [ "x$VIRTUALHOSTHEAD" == "x-" ]; then
	VIRTUALHOSTHEAD=""
fi
export ispaidinstance=${36}
export SELLYOURSAAS_LOGIN_FOR_SUPPORT=${37}
export directaccess=${38}
export sshaccesstype=${39}

export cliafterpaid=${48}

export ErrorLog='#ErrorLog'

export instancedir=$targetdir/$osusername/$dbname
export fqn=$instancename.$domainname
export fqnold=$instancenameold.$domainnameold

# possibility to change the path of sellyoursass directory
olddoldataroot=`grep '^olddoldataroot=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
newdoldataroot=`grep '^newdoldataroot=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$olddoldataroot" != "x" && "x$newdoldataroot" != "x" ]]; then
	fileforconfig1=${fileforconfig1/$olddoldataroot/$newdoldataroot}
	dirwithdumpfile=${dirwithdumpfile/$olddoldataroot/$newdoldataroot}
	dirwithsources1=${dirwithsources1/$olddoldataroot/$newdoldataroot}
	dirwithsources2=${dirwithsources2/$olddoldataroot/$newdoldataroot}
	dirwithsources3=${dirwithsources3/$olddoldataroot/$newdoldataroot}
	cronfile=${cronfile/$olddoldataroot/$newdoldataroot}
	cliafter=${cliafter/$olddoldataroot/$newdoldataroot}
	cliafterpaid=${cliafterpaid/$olddoldataroot/$newdoldataroot}
fi

# For debug
echo `date +'%Y-%m-%d %H:%M:%S'`" input params for $0:"
echo "mode = $mode"
echo "osusername = $osusername"
echo "ospassword = XXXXXX"
echo "instancename = $instancename"
echo "domainname = $domainname"
echo "fileforconfig1 = $fileforconfig1"
echo "targetdir = $targetdir"
echo "EMAILTO = $EMAILTO"
echo "REMOTEIP = $REMOTEIP"
echo "SELLYOURSAAS_ACCOUNT_URL = $SELLYOURSAAS_ACCOUNT_URL" 
echo "instancenameold = $instancenameold" 
echo "domainnameold = $domainnameold"
echo "customurl = $customurl"
echo "contractlineid = $contractlineid" 
echo "EMAILFROM = $EMAILFROM"
echo "CERTIFFORCUSTOMDOMAIN = $CERTIFFORCUSTOMDOMAIN"
echo "archivedir = $archivedir"
echo "SSLON = $SSLON"
echo "apachereload = $apachereload"
echo "ALLOWOVERRIDE = $ALLOWOVERRIDE"
echo "VIRTUALHOSTHEAD = $VIRTUALHOSTHEAD"
echo "ispaidinstance = $ispaidinstance"
echo "SELLYOURSAAS_LOGIN_FOR_SUPPORT = $SELLYOURSAAS_LOGIN_FOR_SUPPORT"
echo "directaccess = $directaccess"
echo "sshaccesstype = $sshaccesstype"
echo "CUSTOMDOMAIN = $CUSTOMDOMAIN"
echo "WEBSITENAME = $WEBSITENAME"
echo "ErrorLog = $ErrorLog"

echo `date +'%Y-%m-%d %H:%M:%S'`" calculated params:"
echo "instancedir = $instancedir"



testorconfirm="confirm"

if [[ "$mode" == "actionafterpaid" ]]; then
	if [[ "x$cliafterpaid" != "x" ]]; then
		if [ -f $cliafterpaid ]; then
			echo `date +'%Y-%m-%d %H:%M:%S'`" Execute script with . $cliafterpaid"
			. $cliafterpaid
			if [[ "x$?" != "x0" ]]; then
				echo Error when running the CLI script $cliafterpaid 
				echo "Error when running the CLI script $cliafterpaid" | mail -aFrom:$EMAILFROM -s "[Alert] Pb in deployment" $EMAILTO
				exit 26
			fi
		fi
	fi
fi


echo `date +'%Y-%m-%d %H:%M:%S'`" Process of action $mode of $instancename.$domainname for user $osusername finished"
sleep 1
echo `date +'%Y-%m-%d %H:%M:%S'`" return 0"
echo

exit 0
