#!/bin/bash

# To use this script with remote ssh (not required when using the remote agent):
# Create a symbolic link to this file .../action_suspend_unsuspend.sh into /usr/bin
# Grant adequate permissions (550 mean root and group www-data can read and execute, nobody can write)
# sudo chown root:www-data /usr/bin/action_suspend_unsuspend.sh
# sudo chmod 550 /usr/bin/action_suspend_unsuspend.sh
# And allow apache to sudo on this script by doing visudo to add line:
#www-data        ALL=(ALL) NOPASSWD: /usr/bin/action_suspend_unsuspend.sh


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

# possibility to change the directory of vhostfile templates
templatesdir=`grep '^templatesdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$templatesdir" != "x" ]]; then
	export vhostfile="$templatesdir/vhostHttps-sellyoursaas.template"
	export vhostfilesuspended="$templatesdir/vhostHttps-sellyoursaas-suspended.template"
	export vhostfilemaintenance="$templatesdir/vhostHttps-sellyoursaas-maintenance.template"
else
	export vhostfile="$scriptdir/templates/vhostHttps-sellyoursaas.template"
	export vhostfilesuspended="$scriptdir/templates/vhostHttps-sellyoursaas-suspended.template"
	export vhostfilemaintenance="$scriptdir/templates/vhostHttps-sellyoursaas-maintenance.template"
fi

if [ "$(id -u)" != "0" ]; then
	echo "This script must be run as root" 1>&2
	exit 100
fi

if [ "x$1" == "x" ]; then
	echo "Missing parameter 1 - mode (suspend|suspendmaintenance|unsuspend)" 1>&2
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
if [ "x${22}" == "x" ]; then
	echo "Missing parameter 22 - EMAILFROM" 1>&2
	exit 22
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
export INCLUDEFROMCONTRACT=${40//£/ }
if [ "x$INCLUDEFROMCONTRACT" == "x-" ]; then
	INCLUDEFROMCONTRACT=""
fi

export ErrorLog='#ErrorLog'


export instancedir=$targetdir/$osusername/$dbname
export fqn=$instancename.$domainname
export fqnold=$instancenameold.$domainnameold
export CRONHEAD=${VIRTUALHOSTHEAD/php_value date.timezone /TZ=}

# possibility to change the ssl certificates name
export webSSLCertificateCRT=`grep '^websslcertificatecrt=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$webSSLCertificateCRT" == "x" ]]; then
	export webSSLCertificateCRT=with.sellyoursaas.com.crt
fi
export webSSLCertificateKEY=`grep '^websslcertificatekey=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$webSSLCertificateKEY" == "x" ]]; then
	export webSSLCertificateKEY=with.sellyoursaas.com.key
fi
export webSSLCertificateIntermediate=`grep '^websslcertificateintermediate=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$webSSLCertificateIntermediate" == "x" ]]; then
	export webSSLCertificateIntermediate=with.sellyoursaas.com-intermediate.crt
fi

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
echo "ErrorLog = $ErrorLog"

echo `date +'%Y-%m-%d %H:%M:%S'`" calculated params:"
echo "instancedir = $instancedir"
echo "fqn = $fqn"
echo "fqnold = $fqnold"
echo "CRONHEAD = $CRONHEAD"


testorconfirm="confirm"



# Rename

if [[ "$mode" == "rename" ]]; then

	if [[ "$fqn" != "$fqnold" ]]; then
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** For instance in $targetdir/$osusername/$dbname, check if new virtual host $fqn exists"

		export apacheconf="/etc/apache2/sellyoursaas-online/$fqn.conf"
		if [ -f $apacheconf ]; then
			echo "Error failed to rename. New name is already used (found file /etc/apache2/sellyoursaas-online/$fqn.conf)." 
			exit 80
		fi
	fi
	
	# TODO
	# Add DNS entry for $fqn


	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** For instance in $targetdir/$osusername/$dbname, create a new virtual name $fqn"

	export apacheconf="/etc/apache2/sellyoursaas-available/$fqn.conf"
	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Create a new apache conf $apacheconf from $vhostfile"

	if [[ -s $apacheconf ]]
	then
		echo "Apache conf $apacheconf already exists, we delete it since it may be a file from an old instance with same name"
		rm -f $apacheconf
	fi

	# Create virtual host for standard name (custom url may be created later)
	echo "cat $vhostfile | sed -e 's/__webAppDomain__/$instancename.$domainname/g' | \
			  sed -e 's/__webAppAliases__/$instancename.$domainname/g' | \
			  sed -e 's/__webAppLogName__/$instancename/g' | \
              sed -e 's/__webSSLCertificateCRT__/$webSSLCertificateCRT/g' | \
              sed -e 's/__webSSLCertificateKEY__/$webSSLCertificateKEY/g' | \
              sed -e 's/__webSSLCertificateIntermediate__/$webSSLCertificateIntermediate/g' | \
			  sed -e 's/__webAdminEmail__/$EMAILFROM/g' | \
			  sed -e 's/__osUsername__/$osusername/g' | \
			  sed -e 's/__osGroupname__/$osusername/g' | \
			  sed -e 's;__osUserPath__;$targetdir/$osusername/$dbname;g' | \
			  sed -e 's;__VirtualHostHead__;$VIRTUALHOSTHEAD;g' | \
			  sed -e 's;__AllowOverride__;$ALLOWOVERRIDE;g' | \
			  sed -e 's;__IncludeFromContract__;$INCLUDEFROMCONTRACT;g' | \
			  sed -e 's;__SELLYOURSAAS_LOGIN_FOR_SUPPORT__;$SELLYOURSAAS_LOGIN_FOR_SUPPORT;g' | \
			  sed -e 's;#ErrorLog;$ErrorLog;g' | \
			  sed -e 's;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g' | \
			  sed -e 's;__webAppPath__;$instancedir;g' > $apacheconf"
	cat $vhostfile | sed -e "s/__webAppDomain__/$instancename.$domainname/g" | \
			  sed -e "s/__webAppAliases__/$instancename.$domainname/g" | \
			  sed -e "s/__webAppLogName__/$instancename/g" | \
              sed -e "s/__webSSLCertificateCRT__/$webSSLCertificateCRT/g" | \
              sed -e "s/__webSSLCertificateKEY__/$webSSLCertificateKEY/g" | \
              sed -e "s/__webSSLCertificateIntermediate__/$webSSLCertificateIntermediate/g" | \
			  sed -e "s/__webAdminEmail__/$EMAILFROM/g" | \
			  sed -e "s/__osUsername__/$osusername/g" | \
			  sed -e "s/__osGroupname__/$osusername/g" | \
			  sed -e "s;__osUserPath__;$targetdir/$osusername/$dbname;g" | \
			  sed -e "s;__VirtualHostHead__;$VIRTUALHOSTHEAD;g" | \
			  sed -e "s;__AllowOverride__;$ALLOWOVERRIDE;g" | \
			  sed -e "s;__IncludeFromContract__;$INCLUDEFROMCONTRACT;g" | \
			  sed -e "s;__SELLYOURSAAS_LOGIN_FOR_SUPPORT__;$SELLYOURSAAS_LOGIN_FOR_SUPPORT;g" | \
			  sed -e "s;#ErrorLog;$ErrorLog;g" | \
			  sed -e "s;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g" | \
			  sed -e "s;__webAppPath__;$instancedir;g" > $apacheconf


	#echo Enable conf with a2ensite $fqn.conf
	#a2ensite $fqn.conf
	echo Enable conf with ln -fs /etc/apache2/sellyoursaas-available/$fqn.conf /etc/apache2/sellyoursaas-online
	ln -fs /etc/apache2/sellyoursaas-available/$fqn.conf /etc/apache2/sellyoursaas-online


	# Remove and recreate customurl
	rm -f /etc/apache2/sellyoursaas-available/$fqn.custom.conf
	rm -f /etc/apache2/sellyoursaas-online/$fqn.custom.conf
	if [[ "x$customurl" != "x" ]]; then
	
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** For instance in $targetdir/$osusername/$dbname, create a new custom virtual name $fqn.custom"
	
		echo "Check that SSL files for $fqn.custom exists and create link to generic certificate files if not"
		if [[ "x$CERTIFFORCUSTOMDOMAIN" != "x" ]]; then
			export pathforcertif=`dirname $fileforconfig1`
			export pathforcertif=`dirname $pathforcertif`
			export webCustomSSLCertificateCRT=$CERTIFFORCUSTOMDOMAIN.crt
			export webCustomSSLCertificateKEY=$CERTIFFORCUSTOMDOMAIN.key
			export webCustomSSLCertificateIntermediate=$CERTIFFORCUSTOMDOMAIN-intermediate.crt
		
			if [[ ! -e /etc/apache2/$webCustomSSLCertificateCRT ]]; then
				echo "Create link /etc/apache2/$webCustomSSLCertificateCRT to $pathforcertif/crt/$webCustomSSLCertificateCRT"
				ln -fs $pathforcertif/crt/$webCustomSSLCertificateCRT /etc/apache2/$webCustomSSLCertificateCRT
				# It is better to link to a bad certificate than linking to non existing file
				if [[ ! -e /etc/apache2/$webCustomSSLCertificateCRT ]]; then
					echo "Previous link not valid, so we create it to /etc/apache2/$webSSLCertificateCRT"
					echo "ln -fs /etc/apache2/$webSSLCertificateCRT /etc/apache2/$webCustomSSLCertificateCRT"
					ln -fs /etc/apache2/$webSSLCertificateCRT /etc/apache2/$webCustomSSLCertificateCRT
				fi
			fi
			if [[ ! -e /etc/apache2/$webCustomSSLCertificateKEY ]]; then
				echo "Create link /etc/apache2/$webCustomSSLCertificateKEY to $pathforcertif/crt/$webCustomSSLCertificateKEY"
				ln -fs $pathforcertif/crt/$webCustomSSLCertificateKEY /etc/apache2/$webCustomSSLCertificateKEY
				# It is better to link to a bad certificate than linking to non existing file
				if [[ ! -e /etc/apache2/$webCustomSSLCertificateKEY ]]; then
					echo "Previous link not valid, so we create it to /etc/apache2/$webSSLCertificateKEY"
					echo "ln -fs /etc/apache2/$webSSLCertificateKEY /etc/apache2/$webCustomSSLCertificateKEY"
					ln -fs /etc/apache2/$webSSLCertificateKEY /etc/apache2/$webCustomSSLCertificateKEY
				fi
			fi
			if [[ ! -e /etc/apache2/$webCustomSSLCertificateIntermediate ]]; then
				echo "Create link /etc/apache2/$webCustomSSLCertificateIntermediate to $pathforcertif/crt/$webCustomSSLCertificateIntermediate"
				ln -fs $pathforcertif/crt/$webCustomSSLCertificateIntermediate /etc/apache2/$webCustomSSLCertificateIntermediate
				# It is better to link to a bad certificate than linking to non existing file
				if [[ ! -e /etc/apache2/$webCustomSSLCertificateIntermediate ]]; then
					echo "Previous link not valid, so we recreate it to /etc/apache2/$webSSLCertificateIntermediate"
					echo "ln -fs /etc/apache2/$webSSLCertificateIntermediate /etc/apache2/$webCustomSSLCertificateIntermediate"
					ln -fs /etc/apache2/$webSSLCertificateIntermediate /etc/apache2/$webCustomSSLCertificateIntermediate
				fi
			fi
		fi
		
		export apacheconf="/etc/apache2/sellyoursaas-available/$fqn.custom.conf"
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Create a new apache conf $apacheconf from $vhostfile"
	
		if [[ -s $apacheconf ]]
		then
			echo "Apache conf $apacheconf already exists, we delete it since it may be a file from an old instance with same name"
			rm -f $apacheconf
		fi
	
		echo "cat $vhostfile | sed -e 's/__webAppDomain__/$customurl/g' | \
				  sed -e 's/__webAppAliases__/$customurl/g' | \
				  sed -e 's/__webAppLogName__/$instancename/g' | \
                  sed -e 's/__webSSLCertificateCRT__/$webCustomSSLCertificateCRT/g' | \
                  sed -e 's/__webSSLCertificateKEY__/$webCustomSSLCertificateKEY/g' | \
                  sed -e 's/__webSSLCertificateIntermediate__/$webCustomSSLCertificateIntermediate/g' | \
				  sed -e 's/__webAdminEmail__/$EMAILFROM/g' | \
				  sed -e 's/__osUsername__/$osusername/g' | \
				  sed -e 's/__osGroupname__/$osusername/g' | \
				  sed -e 's;__osUserPath__;$targetdir/$osusername/$dbname;g' | \
				  sed -e 's;__VirtualHostHead__;$VIRTUALHOSTHEAD;g' | \
				  sed -e 's;__AllowOverride__;$ALLOWOVERRIDE;g' | \
				  sed -e 's;__IncludeFromContract__;$INCLUDEFROMCONTRACT;g' | \
				  sed -e 's;__SELLYOURSAAS_LOGIN_FOR_SUPPORT__;$SELLYOURSAAS_LOGIN_FOR_SUPPORT;g' | \
				  sed -e 's;#ErrorLog;$ErrorLog;g' | \
				  sed -e 's;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g' | \
				  sed -e 's;__webAppPath__;$instancedir;g' | \
				  sed -e 's/with\.sellyoursaas\.com/$CERTIFFORCUSTOMDOMAIN/g' > $apacheconf"
		cat $vhostfile | sed -e "s/__webAppDomain__/$customurl/g" | \
				  sed -e "s/__webAppAliases__/$customurl/g" | \
				  sed -e "s/__webAppLogName__/$instancename/g" | \
                  sed -e "s/__webSSLCertificateCRT__/$webCustomSSLCertificateCRT/g" | \
                  sed -e "s/__webSSLCertificateKEY__/$webCustomSSLCertificateKEY/g" | \
                  sed -e "s/__webSSLCertificateIntermediate__/$webCustomSSLCertificateIntermediate/g" | \
				  sed -e "s/__webAdminEmail__/$EMAILFROM/g" | \
				  sed -e "s/__osUsername__/$osusername/g" | \
				  sed -e "s/__osGroupname__/$osusername/g" | \
				  sed -e "s/SSLEngine on/SSLEngine $SSLON/ig" | \
				  sed -e "s/SSLEngine off/SSLEngine $SSLON/ig" | \
				  sed -e "s/RewriteEngine on/RewriteEngine $SSLON/ig" | \
				  sed -e "s/RewriteEngine off/RewriteEngine $SSLON/ig" | \
				  sed -e "s;__osUserPath__;$targetdir/$osusername/$dbname;g" | \
				  sed -e "s;__VirtualHostHead__;$VIRTUALHOSTHEAD;g" | \
				  sed -e "s;__AllowOverride__;$ALLOWOVERRIDE;g" | \
				  sed -e "s;__IncludeFromContract__;$INCLUDEFROMCONTRACT;g" | \
				  sed -e "s;__SELLYOURSAAS_LOGIN_FOR_SUPPORT__;$SELLYOURSAAS_LOGIN_FOR_SUPPORT;g" | \
				  sed -e "s;#ErrorLog;$ErrorLog;g" | \
				  sed -e "s;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g" | \
				  sed -e "s;__webAppPath__;$instancedir;g" | \
				  sed -e "s/with\.sellyoursaas\.com/$CERTIFFORCUSTOMDOMAIN/g" > $apacheconf
	
	
		#echo Enable conf with a2ensite $fqn.custom.conf
		#a2ensite $fqn.custom.conf
		echo Enable conf with ln -fs /etc/apache2/sellyoursaas-available/$fqn.custom.conf /etc/apache2/sellyoursaas-online
		ln -fs /etc/apache2/sellyoursaas-available/$fqn.custom.conf /etc/apache2/sellyoursaas-online
	
	fi 


	echo mkdir $targetdir/$osusername/$dbname to be sure apache can create its error log file
	mkdir -p $targetdir/$osusername/$dbname

	
	echo /usr/sbin/apache2ctl configtest
	/usr/sbin/apache2ctl configtest
	if [[ "x$?" != "x0" ]]; then
		echo Error when running apache2ctl configtest 
		echo "Failed to unsuspend instance $instancename.$domainname with: Error when running apache2ctl configtest" | mail -aFrom:$EMAILFROM -s "[Alert] Pb in suspend" $EMAILTO 
		sleep 1
		exit 9
	fi 

	if [[ "x$apachereload" != "xnoapachereload" ]]; then
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Apache tasks finished. service apache2 reload."
		service apache2 reload
		if [[ "x$?" != "x0" ]]; then
			echo Error when running service apache2 reload
			echo "Failed to unsuspend instance $instancename.$domainname with: Error when running service apache2 reload" | mail -aFrom:$EMAILFROM -s "[Alert] Pb in suspend" $EMAILTO 
			sleep 1
			exit 20
		else
			sleep 1
		fi
	else
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Apache tasks finished. But we do not reload apache2 now to reduce reloading."
	fi


	# If we rename instance
	if [[ "$fqn" != "$fqnold" ]]; then
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** For instance in $targetdir/$osusername/$dbname, delete old virtual name $fqnold"

		export apacheconf="/etc/apache2/sellyoursaas-online/$fqnold.conf"
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Remove apache conf $apacheconf"

		if [ -f $apacheconf ]; then
		
			echo Disable conf with a2dissite $fqnold.conf
			#a2dissite $fqn.conf
			rm -f /etc/apache2/sellyoursaas-online/$fqnold.conf
			rm -f /etc/apache2/sellyoursaas-online/$fqnold.custom.conf
			
			/usr/sbin/apache2ctl configtest
			if [[ "x$?" != "x0" ]]; then
				echo Error when running apache2ctl configtest 
				echo "Failed to delete virtual host with old name instance $instancenameold.$domainnameold with: Error when running apache2ctl configtest" | mail -aFrom:$EMAILFROM -s "[Alert] Pb in rename" $EMAILTO
				sleep 1
				exit 3
			fi
			
			echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Apache tasks finished. service apache2 reload"
			service apache2 reload
			if [[ "x$?" != "x0" ]]; then
				echo Error when running service apache2 reload 
				echo "Failed to delete virtual host with old name instance $instancenameold.$domainnameold with: Error when running service apache2 reload" | mail -aFrom:$EMAILFROM -s "[Alert] Pb in rename" $EMAILTO
				sleep 1
				exit 4
			#else
			#   A sleep is already don at end of script
			#	sleep 1			
			fi
		else
			echo "Virtual host $apacheconf seems already disabled"
		fi

		# TODO
		# Remove DNS entry for $fqnold
	fi

fi


# Suspend

if [[ "$mode" == "suspend" || $mode == "suspendmaintenance" ]]; then
	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Suspend instance in $targetdir/$osusername/$dbname"

	export vhostfiletouse=$vhostfilesuspended;
	if [[ $mode == "suspendmaintenance" ]]; then
		export vhostfiletouse=$vhostfilemaintenance;
	fi	
	
	export apacheconf="/etc/apache2/sellyoursaas-available/$fqn.conf"
	echo "Create a suspended apache conf $apacheconf from $vhostfiletouse"

	if [[ -s $apacheconf ]]
	then
		echo "Apache conf $apacheconf already exists, we delete it since it may be a file from an old instance with same name"
		rm -f $apacheconf
	fi

	echo "cat $vhostfiletouse | sed -e 's/__webAppDomain__/$instancename.$domainname/g' | \
			  sed -e 's/__webAppAliases__/$instancename.$domainname/g' | \
			  sed -e 's/__webAppLogName__/$instancename/g' | \
              sed -e 's/__webSSLCertificateCRT__/$webSSLCertificateCRT/g' | \
              sed -e 's/__webSSLCertificateKEY__/$webSSLCertificateKEY/g' | \
              sed -e 's/__webSSLCertificateIntermediate__/$webSSLCertificateIntermediate/g' | \
			  sed -e 's/__webAdminEmail__/$EMAILFROM/g' | \
			  sed -e 's/__osUsername__/$osusername/g' | \
			  sed -e 's/__osGroupname__/$osusername/g' | \
			  sed -e 's;__osUserPath__;$targetdir/$osusername/$dbname;g' | \
			  sed -e 's;__VirtualHostHead__;$VIRTUALHOSTHEAD;g' | \
			  sed -e 's;__AllowOverride__;$ALLOWOVERRIDE;g' | \
			  sed -e 's;__IncludeFromContract__;$INCLUDEFROMCONTRACT;g' | \
			  sed -e 's;__SELLYOURSAAS_LOGIN_FOR_SUPPORT__;$SELLYOURSAAS_LOGIN_FOR_SUPPORT;g' | \
			  sed -e 's;#ErrorLog;$ErrorLog;g' | \
			  sed -e 's;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g' | \
			  sed -e 's;__webAppPath__;$instancedir;g' > $apacheconf"
	cat $vhostfiletouse | sed -e "s/__webAppDomain__/$instancename.$domainname/g" | \
			  sed -e "s/__webAppAliases__/$instancename.$domainname/g" | \
			  sed -e "s/__webAppLogName__/$instancename/g" | \
              sed -e "s/__webSSLCertificateCRT__/$webSSLCertificateCRT/g" | \
              sed -e "s/__webSSLCertificateKEY__/$webSSLCertificateKEY/g" | \
              sed -e "s/__webSSLCertificateIntermediate__/$webSSLCertificateIntermediate/g" | \
			  sed -e "s/__webAdminEmail__/$EMAILFROM/g" | \
			  sed -e "s/__osUsername__/$osusername/g" | \
			  sed -e "s/__osGroupname__/$osusername/g" | \
			  sed -e "s;__osUserPath__;$targetdir/$osusername/$dbname;g" | \
			  sed -e "s;__VirtualHostHead__;$VIRTUALHOSTHEAD;g" | \
			  sed -e "s;__AllowOverride__;$ALLOWOVERRIDE;g" | \
			  sed -e "s;__IncludeFromContract__;$INCLUDEFROMCONTRACT;g" | \
			  sed -e "s;__SELLYOURSAAS_LOGIN_FOR_SUPPORT__;$SELLYOURSAAS_LOGIN_FOR_SUPPORT;g" | \
			  sed -e "s;#ErrorLog;$ErrorLog;g" | \
			  sed -e "s;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g" | \
			  sed -e "s;__webAppPath__;$instancedir;g" > $apacheconf


	#echo Enable conf with a2ensite $fqn.conf
	#a2ensite $fqn.conf
	echo Enable conf with ln -fs /etc/apache2/sellyoursaas-available/$fqn.conf /etc/apache2/sellyoursaas-online
	ln -fs /etc/apache2/sellyoursaas-available/$fqn.conf /etc/apache2/sellyoursaas-online
	
	
	if [[ "x$customurl" != "x" ]]; then
	
		export apacheconf="/etc/apache2/sellyoursaas-available/$fqn.custom.conf"
		echo "Create a suspended apache conf $apacheconf from $vhostfiletouse"
	
		if [[ -s $apacheconf ]]
		then
			echo "Apache conf $apacheconf already exists, we delete it since it may be a file from an old instance with same name"
			rm -f $apacheconf
		fi
	
		echo "cat $vhostfiletouse | sed -e 's/__webAppDomain__/$customurl/g' | \
				  sed -e 's/__webAppAliases__/$customurl/g' | \
				  sed -e 's/__webAppLogName__/$instancename/g' | \
                  sed -e 's/__webSSLCertificateCRT__/$webSSLCertificateCRT/g' | \
                  sed -e 's/__webSSLCertificateKEY__/$webSSLCertificateKEY/g' | \
                  sed -e 's/__webSSLCertificateIntermediate__/$webSSLCertificateIntermediate/g' | \
				  sed -e 's/__webAdminEmail__/$EMAILFROM/g' | \
				  sed -e 's/__osUsername__/$osusername/g' | \
				  sed -e 's/__osGroupname__/$osusername/g' | \
				  sed -e 's;__osUserPath__;$targetdir/$osusername/$dbname;g' | \
				  sed -e 's;__VirtualHostHead__;$VIRTUALHOSTHEAD;g' | \
				  sed -e 's;__AllowOverride__;$ALLOWOVERRIDE;g' | \
				  sed -e 's;__IncludeFromContract__;$INCLUDEFROMCONTRACT;g' | \
				  sed -e 's;__SELLYOURSAAS_LOGIN_FOR_SUPPORT__;$SELLYOURSAAS_LOGIN_FOR_SUPPORT;g' | \
				  sed -e 's;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g' | \
				  sed -e 's;__webAppPath__;$instancedir;g' | \
				  sed -e 's/with\.sellyoursaas\.com/$CERTIFFORCUSTOMDOMAIN/g' > $apacheconf"
		cat $vhostfiletouse | sed -e "s/__webAppDomain__/$customurl/g" | \
				  sed -e "s/__webAppAliases__/$customurl/g" | \
				  sed -e "s/__webAppLogName__/$instancename/g" | \
                  sed -e "s/__webSSLCertificateCRT__/$webSSLCertificateCRT/g" | \
                  sed -e "s/__webSSLCertificateKEY__/$webSSLCertificateKEY/g" | \
                  sed -e "s/__webSSLCertificateIntermediate__/$webSSLCertificateIntermediate/g" | \
				  sed -e "s/__webAdminEmail__/$EMAILFROM/g" | \
				  sed -e "s/__osUsername__/$osusername/g" | \
				  sed -e "s/__osGroupname__/$osusername/g" | \
				  sed -e "s;__osUserPath__;$targetdir/$osusername/$dbname;g" | \
				  sed -e "s;__VirtualHostHead__;$VIRTUALHOSTHEAD;g" | \
			  	  sed -e "s;__AllowOverride__;$ALLOWOVERRIDE;g" | \
			  	  sed -e "s;__IncludeFromContract__;$INCLUDEFROMCONTRACT;g" | \
			  	  sed -e "s;__SELLYOURSAAS_LOGIN_FOR_SUPPORT__;$SELLYOURSAAS_LOGIN_FOR_SUPPORT;g" | \
				  sed -e "s;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g" | \
				  sed -e "s;__webAppPath__;$instancedir;g" | \
				  sed -e "s/with\.sellyoursaas\.com/$CERTIFFORCUSTOMDOMAIN/g" > $apacheconf
	
	
		#echo Enable conf with a2ensite $fqn.custom.conf
		#a2ensite $fqn.custom.conf
		echo Enable conf with ln -fs /etc/apache2/sellyoursaas-available/$fqn.custom.conf /etc/apache2/sellyoursaas-online
		ln -fs /etc/apache2/sellyoursaas-available/$fqn.custom.conf /etc/apache2/sellyoursaas-online
	
	fi

	
	# remove virtual host for public web sites by deleting links into sellyoursaas-enabled
	echo "Remove virtual host for possible virtual host for web sites"
	for fic in `ls /etc/apache2/sellyoursaas-online/$fqn.website-*.conf`
	do
		echo Delete conf with rm -f /etc/apache2/sellyoursaas-online/$fqn.website-*.conf
		rm -f /etc/apache2/sellyoursaas-online/$fqn.website-*.conf
	done
	
	
	echo /usr/sbin/apache2ctl configtest
	/usr/sbin/apache2ctl configtest
	if [[ "x$?" != "x0" ]]; then
		echo Error when running apache2ctl configtest. We remove the new created virtual host /etc/apache2/sellyoursaas-online/$fqn.conf to hope to restore configtest ok.
		rm -f /etc/apache2/sellyoursaas-online/$fqn.conf
		rm -f /etc/apache2/sellyoursaas-online/$fqn.custom.conf
		echo "Failed to suspend instance $instancename.$domainname with: Error when running apache2ctl configtest" | mail -aFrom:$EMAILFROM -s "[Warning] Pb when suspending $instancename.$domainname" $EMAILTO 
		sleep 1
		exit 5
	fi 
	
	if [[ "x$apachereload" != "xnoapachereload" ]]; then
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Apache tasks finished. service apache2 reload."
		service apache2 reload
		if [[ "x$?" != "x0" ]]; then
			echo Error when running service apache2 reload
			echo "Failed to suspend instance $instancename.$domainname with: Error when running service apache2 reload" | mail -aFrom:$EMAILFROM -s "[Warning] Pb when suspending $instancename.$domainname" $EMAILTO
			sleep 1 
			exit 6
		#else
		#   A sleep is already don at end of script
		#	sleep 1			
		fi
	else
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Apache tasks finished. But we do not reload apache2 now to reduce reloading."
	fi
fi


# Unsuspend. Can also be used to force recreation of Virtual host.

if [[ "$mode" == "unsuspend" ]]; then
	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Unsuspend instance in $targetdir/$osusername/$dbname"

	export apacheconf="/etc/apache2/sellyoursaas-available/$fqn.conf"
	echo "Create a new apache conf $apacheconf from $vhostfile"

	if [[ -s $apacheconf ]]
	then
		echo "Apache conf $apacheconf already exists, we delete it since it may be a file from an old instance with same name"
		rm -f $apacheconf
	fi

	echo "cat $vhostfile | sed -e 's/__webAppDomain__/$instancename.$domainname/g' | \
			  sed -e 's/__webAppAliases__/$instancename.$domainname/g' | \
			  sed -e 's/__webAppLogName__/$instancename/g' | \
              sed -e 's/__webSSLCertificateCRT__/$webSSLCertificateCRT/g' | \
              sed -e 's/__webSSLCertificateKEY__/$webSSLCertificateKEY/g' | \
              sed -e 's/__webSSLCertificateIntermediate__/$webSSLCertificateIntermediate/g' | \
			  sed -e 's/__webAdminEmail__/$EMAILFROM/g' | \
			  sed -e 's/__osUsername__/$osusername/g' | \
			  sed -e 's/__osGroupname__/$osusername/g' | \
			  sed -e 's;__osUserPath__;$targetdir/$osusername/$dbname;g' | \
			  sed -e 's;__VirtualHostHead__;$VIRTUALHOSTHEAD;g' | \
			  sed -e 's;__AllowOverride__;$ALLOWOVERRIDE;g' | \
			  sed -e 's;__IncludeFromContract__;$INCLUDEFROMCONTRACT;g' | \
			  sed -e 's;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g' | \
			  sed -e 's;__webAppPath__;$instancedir;g' > $apacheconf"
	cat $vhostfile | sed -e "s/__webAppDomain__/$instancename.$domainname/g" | \
			  sed -e "s/__webAppAliases__/$instancename.$domainname/g" | \
			  sed -e "s/__webAppLogName__/$instancename/g" | \
              sed -e "s/__webSSLCertificateCRT__/$webSSLCertificateCRT/g" | \
              sed -e "s/__webSSLCertificateKEY__/$webSSLCertificateKEY/g" | \
              sed -e "s/__webSSLCertificateIntermediate__/$webSSLCertificateIntermediate/g" | \
			  sed -e "s/__webAdminEmail__/$EMAILFROM/g" | \
			  sed -e "s/__osUsername__/$osusername/g" | \
			  sed -e "s/__osGroupname__/$osusername/g" | \
			  sed -e "s;__osUserPath__;$targetdir/$osusername/$dbname;g" | \
			  sed -e "s;__VirtualHostHead__;$VIRTUALHOSTHEAD;g" | \
			  sed -e "s;__AllowOverride__;$ALLOWOVERRIDE;g" | \
			  sed -e "s;__IncludeFromContract__;$INCLUDEFROMCONTRACT;g" | \
			  sed -e "s;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g" | \
			  sed -e "s;__webAppPath__;$instancedir;g" > $apacheconf


	#echo Enable conf with a2ensite $fqn.conf
	#a2ensite $fqn.conf
	echo Enable conf with ln -fs /etc/apache2/sellyoursaas-available/$fqn.conf /etc/apache2/sellyoursaas-online
	ln -fs /etc/apache2/sellyoursaas-available/$fqn.conf /etc/apache2/sellyoursaas-online
	
	
	if [[ "x$customurl" != "x" ]]; then
	
		export apacheconf="/etc/apache2/sellyoursaas-available/$fqn.custom.conf"
		echo "Create a new apache conf $apacheconf from $vhostfile"
	
		if [[ -s $apacheconf ]]
		then
			echo "Apache conf $apacheconf already exists, we delete it since it may be a file from an old instance with same name"
			rm -f $apacheconf
		fi
	
		echo "cat $vhostfile | sed -e 's/__webAppDomain__/$customurl/g' | \
				  sed -e 's/__webAppAliases__/$customurl/g' | \
				  sed -e 's/__webAppLogName__/$instancename/g' | \
                  sed -e 's/__webSSLCertificateCRT__/$webSSLCertificateCRT/g' | \
                  sed -e 's/__webSSLCertificateKEY__/$webSSLCertificateKEY/g' | \
                  sed -e 's/__webSSLCertificateIntermediate__/$webSSLCertificateIntermediate/g' | \
				  sed -e 's/__webAdminEmail__/$EMAILFROM/g' | \
				  sed -e 's/__osUsername__/$osusername/g' | \
				  sed -e 's/__osGroupname__/$osusername/g' | \
				  sed -e 's;__osUserPath__;$targetdir/$osusername/$dbname;g' | \
				  sed -e 's;__VirtualHostHead__;$VIRTUALHOSTHEAD;g' | \
				  sed -e 's;__AllowOverride__;$ALLOWOVERRIDE;g' | \
				  sed -e 's;__IncludeFromContract__;$INCLUDEFROMCONTRACT;g' | \
				  sed -e 's;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g' | \
				  sed -e 's;__webAppPath__;$instancedir;g' | \
				  sed -e 's/with\.sellyoursaas\.com/$CERTIFFORCUSTOMDOMAIN/g' > $apacheconf"
		cat $vhostfile | sed -e "s/__webAppDomain__/$customurl/g" | \
				  sed -e "s/__webAppAliases__/$customurl/g" | \
				  sed -e "s/__webAppLogName__/$instancename/g" | \
                  sed -e "s/__webSSLCertificateCRT__/$webSSLCertificateCRT/g" | \
                  sed -e "s/__webSSLCertificateKEY__/$webSSLCertificateKEY/g" | \
                  sed -e "s/__webSSLCertificateIntermediate__/$webSSLCertificateIntermediate/g" | \
				  sed -e "s/__webAdminEmail__/$EMAILFROM/g" | \
				  sed -e "s/__osUsername__/$osusername/g" | \
				  sed -e "s/__osGroupname__/$osusername/g" | \
				  sed -e "s;__osUserPath__;$targetdir/$osusername/$dbname;g" | \
				  sed -e "s;__VirtualHostHead__;$VIRTUALHOSTHEAD;g" | \
				  sed -e "s;__AllowOverride__;$ALLOWOVERRIDE;g" | \
				  sed -e "s;__IncludeFromContract__;$INCLUDEFROMCONTRACT;g" | \
				  sed -e "s;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g" | \
				  sed -e "s;__webAppPath__;$instancedir;g" | \
				  sed -e "s/with\.sellyoursaas\.com/$CERTIFFORCUSTOMDOMAIN/g" > $apacheconf
	
	
		#echo Enable conf with a2ensite $fqn.custom.conf
		#a2ensite $fqn.custom.conf
		echo Enable conf with ln -fs /etc/apache2/sellyoursaas-available/$fqn.custom.conf /etc/apache2/sellyoursaas-online
		ln -fs /etc/apache2/sellyoursaas-available/$fqn.custom.conf /etc/apache2/sellyoursaas-online
	
	fi


	# restore virtual host for public web sites by deleting links into sellyoursaas-enabled
	echo "Check if we have to enable virtual host for web sites"
	for fic in `ls /etc/apache2/sellyoursaas-available/$fqn.website-*.conf`
	do
		echo Enable conf with ln -fs /etc/apache2/sellyoursaas-available/$fqn.website-*.conf /etc/apache2/sellyoursaas-online
		ln -fs /etc/apache2/sellyoursaas-available/$fqn.website-*.conf /etc/apache2/sellyoursaas-online
	done


	echo /usr/sbin/apache2ctl configtest
	/usr/sbin/apache2ctl configtest
	if [[ "x$?" != "x0" ]]; then
		echo Error when running apache2ctl configtest 
		echo "Failed to unsuspend instance $instancename.$domainname with: Error when running apache2ctl configtest" | mail -aFrom:$EMAILFROM -s "[Alert] Pb in suspend" $EMAILTO 
		sleep 1
		exit 7
	fi 

	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Apache tasks finished. service apache2 reload"
	service apache2 reload
	if [[ "x$?" != "x0" ]]; then
		echo Error when running service apache2 reload
		echo "Failed to unsuspend instance $instancename.$domainname with: Error when running service apache2 reload" | mail -aFrom:$EMAILFROM -s "[Alert] Pb in suspend" $EMAILTO 
		sleep 1
		exit 8
	#else
	#   A sleep is already don at end of script
	#	sleep 1			
	fi

fi


# Cron

if [[ "$mode" == "unsuspend" ]]; then

	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Reinstall cron file $cronfile"
	if [[ -f /var/spool/cron/crontabs/$osusername ]]; then
		echo merge existing $cronfile with existing /var/spool/cron/crontabs/$osusername
		# We remove the line that contains the dbname, TZ and comment into the tmp file
		echo "cat /var/spool/cron/crontabs/$osusername | grep -v $dbname | grep -v 'TZ=' | grep -v '^#' > /tmp/$dbname.tmp"
		cat /var/spool/cron/crontabs/$osusername | grep -v "$dbname" | grep -v "TZ=" | grep -v "^#" > /tmp/$dbname.tmp
		# Now we add the lines to use for this instance into the tmp file
		echo "cat $cronfile >> /tmp/$dbname.tmp"
		cat $cronfile >> /tmp/$dbname.tmp
		# Then we add an empty line (otherwise the last line is ignored)
		#echo "echo >> /tmp/$dbname.tmp"
		#echo >> /tmp/$dbname.tmp
		echo cp /tmp/$dbname.tmp /var/spool/cron/crontabs/$osusername
		cp /tmp/$dbname.tmp /var/spool/cron/crontabs/$osusername
		echo rm -f /tmp/$dbname.tmp
		rm -f /tmp/$dbname.tmp
	else
		echo cron file /var/spool/cron/crontabs/$osusername does not exists yet
		echo cp $cronfile /var/spool/cron/crontabs/$osusername
		cp $cronfile /var/spool/cron/crontabs/$osusername
	fi

	chown $osusername.$osusername /var/spool/cron/crontabs/$osusername
	chmod 600 /var/spool/cron/crontabs/$osusername
fi

if [[ "$mode" == "suspend" ]]; then

	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Remove cron file /var/spool/cron/crontabs/$osusername"
	if [ -s /var/spool/cron/crontabs/$osusername ]; then
		mkdir -p /var/spool/cron/crontabs.disabled
		rm -f /var/spool/cron/crontabs.disabled/$osusername
		echo cp /var/spool/cron/crontabs/$osusername /var/spool/cron/crontabs.disabled/$osusername
		cp /var/spool/cron/crontabs/$osusername /var/spool/cron/crontabs.disabled/$osusername
		# We remove the line that contains the dbname
		echo "cat /var/spool/cron/crontabs/$osusername | grep -v $dbname > /tmp/$dbname.tmp"
		cat /var/spool/cron/crontabs/$osusername | grep -v $dbname > /tmp/$dbname.tmp
		# Copy the file without the dbname line
		echo cp /tmp/$dbname.tmp /var/spool/cron/crontabs/$osusername
		cp /tmp/$dbname.tmp /var/spool/cron/crontabs/$osusername
		echo rm -f /tmp/$dbname.tmp
		rm -f /tmp/$dbname.tmp
	else
		echo cron file /var/spool/cron/crontabs/$osusername already removed or empty
	fi 

fi


echo `date +'%Y-%m-%d %H:%M:%S'`" Process of action $mode of $instancename.$domainname for user $osusername finished"
sleep 1
echo `date +'%Y-%m-%d %H:%M:%S'`" return 0"
echo

exit 0
