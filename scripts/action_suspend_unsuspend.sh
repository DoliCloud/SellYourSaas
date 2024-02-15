#!/bin/bash

# To use this script with remote ssh (not required when using the remote agent):
# Create a symbolic link to this file .../action_suspend_unsuspend.sh into /usr/bin
# Grant adequate permissions (550 mean root and group www-data can read and execute, nobody can write)
# sudo chown root:www-data /usr/bin/action_suspend_unsuspend.sh
# sudo chmod 550 /usr/bin/action_suspend_unsuspend.sh
# And allow apache to sudo on this script by doing visudo to add line:
#www-data        ALL=(ALL) NOPASSWD: /usr/bin/action_suspend_unsuspend.sh


export now=`date +'%Y-%m-%d %H:%M:%S'`
export nowlog=`date +'%Y%m%d-%H%M%S'`

echo
echo
echo "####################################### ${0} ${1}"
echo "${0} ${@}"
echo "# user id --------> $(id -u)"
echo "# now ------------> $now"
echo "# PID ------------> ${$}"
echo "# PWD ------------> $PWD" 
#echo "# arguments ------> ${@}"
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
# domainname. Example: withX.mysaasdomain.com
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
# CERTIFFORCUSTOMDOMAIN. Example: withY.mysaasdomain.com, myowndomain.com 
export CERTIFFORCUSTOMDOMAIN=${30}
if [ "x$CERTIFFORCUSTOMDOMAIN" == "x-" ]; then
	CERTIFFORCUSTOMDOMAIN=""
fi
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

export CUSTOMDOMAIN=${46}



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
echo "webSSLCertificateCRT = $webSSLCertificateCRT"
echo "webSSLCertificateKEY = $webSSLCertificateKEY"
echo "webSSLCertificateIntermediate = $webSSLCertificateIntermediate"
echo "fqn = $fqn"
echo "fqnold = $fqnold"
echo "CRONHEAD = $CRONHEAD"


testorconfirm="confirm"



# Rename

if [[ "$mode" == "rename" ]]; then

	if [[ "$fqnold" != "-.-" ]] && [[ "$fqn" != "$fqnold" ]]; then
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** For instance in $targetdir/$osusername/$dbname, check if new virtual host $fqn exists"

		export apacheconf="/etc/apache2/sellyoursaas-online/$fqn.conf"
		if [ -f $apacheconf ]; then
			echo "Error failed to rename. New name is already used (found file /etc/apache2/sellyoursaas-online/$fqn.conf)." 
			exit 80
		fi
	fi
	
	
	# TODO - Add DNS entry for $fqn ? Still required with wildcard DNS ?


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
	
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** For instance in $targetdir/$osusername/$dbname, we will create a new custom virtual name $fqn.custom"

		export pathforcertifmaster="/home/admin/wwwroot/dolibarr_documents/sellyoursaas/crt"
		export pathforcertiflocal="/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt"
	
		echo `date +'%Y-%m-%d %H:%M:%S'`" Check that SSL files for $fqn.custom exists and create them if not"
		if [[ "x$CERTIFFORCUSTOMDOMAIN" != "x" ]]; then
			# If a name for a custom CERTIF stored on master was forced, we use this one as SSL certiticate
			export webCustomSSLCertificateCRT=$CERTIFFORCUSTOMDOMAIN.crt
			export webCustomSSLCertificateKEY=$CERTIFFORCUSTOMDOMAIN.key
			export webCustomSSLCertificateIntermediate=$CERTIFFORCUSTOMDOMAIN-intermediate.crt
		
			if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateCRT ]]; then
				# If file or link does not exist
				echo `date +'%Y-%m-%d %H:%M:%S'`" Copy file $pathforcertifmaster/$webCustomSSLCertificateCRT to $pathforcertiflocal/$webCustomSSLCertificateCRT"
				cp -pn $pathforcertifmaster/$webCustomSSLCertificateCRT $pathforcertiflocal/$webCustomSSLCertificateCRT
				# It is better to link to a bad certificate than linking to non existing file, so
				if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateCRT ]]; then
					echo "Previous cp not valid, so we create it from /etc/apache2/$webSSLCertificateCRT"
					echo "ln -fs /etc/apache2/$webSSLCertificateCRT $pathforcertiflocal/$webCustomSSLCertificateCRT"
					ln -fs /etc/apache2/$webSSLCertificateCRT $pathforcertiflocal/$webCustomSSLCertificateCRT
				fi
			fi
			if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateKEY ]]; then
				# If file or link does not exist
				echo `date +'%Y-%m-%d %H:%M:%S'`" Copy file $pathforcertifmaster/$webCustomSSLCertificateKEY to $pathforcertiflocal/$webCustomSSLCertificateKEY"
				cp -pn $pathforcertifmaster/$webCustomSSLCertificateKEY $pathforcertiflocal/$webCustomSSLCertificateKEY
				# It is better to link to a bad certificate than linking to non existing file, so
				if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateKEY ]]; then
					echo "Previous cp not valid, so we create it from /etc/apache2/$webSSLCertificateKEY"
					echo "ln -fs /etc/apache2/$webSSLCertificateKEY $pathforcertiflocal/$webCustomSSLCertificateKEY"
					ln -fs /etc/apache2/$webSSLCertificateKEY $pathforcertiflocal/$webCustomSSLCertificateKEY
				fi
			fi
			if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateIntermediate ]]; then
				# If file or link does not exist
				echo `date +'%Y-%m-%d %H:%M:%S'`" Copy file $pathforcertifmaster/$webCustomSSLCertificateIntermediate to $pathforcertiflocal/$webCustomSSLCertificateIntermediate"
				cp -pn $pathforcertifmaster/$webCustomSSLCertificateIntermediate $pathforcertiflocal/$webCustomSSLCertificateIntermediate
				# It is better to link to a bad certificate than linking to non existing file, so
				if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateIntermediate ]]; then
					echo "Previous cp not valid, so we recreate it from /etc/apache2/$webSSLCertificateIntermediate"
					echo "ln -fs /etc/apache2/$webSSLCertificateIntermediate $pathforcertiflocal/$webCustomSSLCertificateIntermediate"
					ln -fs /etc/apache2/$webSSLCertificateIntermediate $pathforcertiflocal/$webCustomSSLCertificateIntermediate
				fi
			fi
		else 
			# No $CERTIFFORCUSTOMDOMAIN forced (no cert file was created initially), so we will generate one
			export domainnameorcustomurl=`echo $customurl | cut -d "." -f 1`
			# We must create it using letsencrypt if not yet created
			if [[ ! -e /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-$customurl.crt ]]; then
				# When we rename, it may be because we change abc.with... into def.with..., or
				# because we added a custom url

				if [[ ! -d $instancedir/htdocs/.well-known ]]; then
                	echo "mkdir $instancedir/htdocs/.well-known"
                	mkdir $instancedir/htdocs/.well-known
                	echo "chown $osusername:$osusername $instancedir/htdocs/.well-known"
					chown $osusername:$osusername $instancedir/htdocs/.well-known
				fi

				if [[ ! -d $instancedir/htdocs/.well-known/acme-challenge ]]; then
                	echo "mkdir $instancedir/htdocs/.well-known/acme-challenge"
                	mkdir $instancedir/htdocs/.well-known/acme-challenge
                	echo "chown $osusername:$osusername $instancedir/htdocs/.well-known/acme-challenge"
					chown $osusername:$osusername $instancedir/htdocs/.well-known/acme-challenge
				fi

				# Generate the letsencrypt certificate
                echo "certbot certonly --webroot -w $instancedir/htdocs -d $customurl"
                certbot certonly --webroot -w $instancedir/htdocs -d $customurl

                # create links
				if [[ -e /etc/letsencrypt/live/$customurl/cert.pem ]]; then
					echo `date +'%Y-%m-%d %H:%M:%S'`" Link of generated cert file for custom url"
					echo "Link certificate for virtualhost with
						ln -fs /etc/letsencrypt/live/$customurl/privkey.pem /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-$customurl.key
						ln -fs /etc/letsencrypt/live/$customurl/cert.pem /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-$customurl.crt
						ln -fs /etc/letsencrypt/live/$customurl/fullchain.pem /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-$customurl-intermediate.crt
					"
					ln -fs /etc/letsencrypt/live/$customurl/privkey.pem /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-$customurl.key
					ln -fs /etc/letsencrypt/live/$customurl/cert.pem /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-$customurl.crt
					ln -fs /etc/letsencrypt/live/$customurl/fullchain.pem /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-$customurl-intermediate.crt
				fi
			fi
			
			# If custom cert not found, we fallback on the wildcard one for server (will generate a warning, but it will works !)
			if [[ ! -e /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-$customurl.crt ]]; then
				export webCustomSSLCertificateCRT=$webSSLCertificateCRT
				export webCustomSSLCertificateKEY=$webSSLCertificateKEY
				export webCustomSSLCertificateIntermediate=$webSSLCertificateIntermediate
				export CERTIFFORCUSTOMDOMAIN="with.sellyoursaas.com"
			else
				export webCustomSSLCertificateCRT=/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-$customurl.crt
				export webCustomSSLCertificateKEY=/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-$customurl.key
				export webCustomSSLCertificateIntermediate=/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-$customurl-intermediate.key
				export CERTIFFORCUSTOMDOMAIN="$fqn-$customurl"
			fi
		fi
		
		
		# If the certificate file is not found, we disable SSL
		if [[ ! -e /etc/apache2/$webCustomSSLCertificateCRT ]]; then
			SSLON="Off"
		else
			SSLON="On"
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


	echo mkdir -p $targetdir/$osusername/$dbname to be sure apache can create its error log file
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
	if [[ "$fqnold" != "-.-" ]] && [[ "$fqn" != "$fqnold" ]]; then
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
# 'suspend' = change virtual host with a page with a message 'Suspended' and disable cron
# 'suspendmaintenance' = change virtual host with a page with a message 'Offline' and keep cron alive
# 'suspendredirect' = change virtual host with a page with a message 'Offline', will alos update the DNS entry

if [[ "$mode" == "suspend" || $mode == "suspendmaintenance" || $mode == "suspendredirect" ]]; then
	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Suspend instance in $targetdir/$osusername/$dbname"

	# Will use the virtual host template file vhostHttps-sellyoursaas-suspended.template
	export vhostfiletouse=$vhostfilesuspended;
	if [[ $mode == "suspendmaintenance" || $mode == "suspendredirect" ]]; then
		# Will use the virtual host template file vhostHttps-sellyoursaas-maintenance.template that call the page maintenance.php
		# This page will make a redirect instea dof showing a maintenance message if the message start with http...
		export vhostfiletouse=$vhostfilemaintenance;
	fi	
	
	export apacheconf="/etc/apache2/sellyoursaas-available/$fqn.conf"
	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Create a new suspended apache conf $apacheconf from $vhostfiletouse"

	if [[ -s $apacheconf ]]
	then
		echo "Apache conf $apacheconf already exists, we delete it since it may be a file from an old instance with same name"
		rm -f $apacheconf
	fi

	# Create virtual host for standard url (custom url may be created later)
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


	# Enable conf with ln
	echo Enable conf with ln -fs /etc/apache2/sellyoursaas-available/$fqn.conf /etc/apache2/sellyoursaas-online
	ln -fs /etc/apache2/sellyoursaas-available/$fqn.conf /etc/apache2/sellyoursaas-online
	
	# We create the virtual host for the custom url
	if [[ "x$customurl" != "x" ]]; then
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** For instance in $targetdir/$osusername/$dbname, we will create a new custom virtual name $fqn.custom"

        export pathforcertifmaster="/home/admin/wwwroot/dolibarr_documents/sellyoursaas/crt"
        export pathforcertiflocal="/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt"

		echo `date +'%Y-%m-%d %H:%M:%S'`" Check that SSL files for $fqn.custom exists to reuse them"

        if [[ "x$CERTIFFORCUSTOMDOMAIN" != "x" ]]; then
                # If a name for a custom CERTIF stored on master was forced, we use this one as SSL certiticate
                export webCustomSSLCertificateCRT=$CERTIFFORCUSTOMDOMAIN.crt
                export webCustomSSLCertificateKEY=$CERTIFFORCUSTOMDOMAIN.key
                export webCustomSSLCertificateIntermediate=$CERTIFFORCUSTOMDOMAIN-intermediate.crt

                if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateCRT ]]; then
                        # If file or link does not exist
                        echo `date +'%Y-%m-%d %H:%M:%S'`" cp -pn file $pathforcertifmaster/$webCustomSSLCertificateCRT to $pathforcertiflocal/$webCustomSSLCertificateCRT"
                        cp -pn $pathforcertifmaster/$webCustomSSLCertificateCRT $pathforcertiflocal/$webCustomSSLCertificateCRT
                        # It is better to link to a bad certificate than linking to non existing file, so
                        if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateCRT ]]; then
                                echo "Previous cp not valid, so we create it from /etc/apache2/$webSSLCertificateCRT"
                                echo "ln -fs /etc/apache2/$webSSLCertificateCRT $pathforcertiflocal/$webCustomSSLCertificateCRT"
                                ln -fs /etc/apache2/$webSSLCertificateCRT $pathforcertiflocal/$webCustomSSLCertificateCRT
                        fi
                fi
                if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateKEY ]]; then
                        # If file or link does not exist
                        echo `date +'%Y-%m-%d %H:%M:%S'`" cp -pn file $pathforcertifmaster/$webCustomSSLCertificateKEY to $pathforcertiflocal/$webCustomSSLCertificateKEY"
                        cp -pn $pathforcertifmaster/$webCustomSSLCertificateKEY $pathforcertiflocal/$webCustomSSLCertificateKEY
                        # It is better to link to a bad certificate than linking to non existing file, so
                        if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateKEY ]]; then
                                echo "Previous cp not valid, so we create it from /etc/apache2/$webSSLCertificateKEY"
                                echo "ln -fs /etc/apache2/$webSSLCertificateKEY $pathforcertiflocal/$webCustomSSLCertificateKEY"
                                ln -fs /etc/apache2/$webSSLCertificateKEY $pathforcertiflocal/$webCustomSSLCertificateKEY
                        fi
                fi
                if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateIntermediate ]]; then
                        # If file or link does not exist
                        echo `date +'%Y-%m-%d %H:%M:%S'`" cp -pn file $pathforcertifmaster/$webCustomSSLCertificateIntermediate to $pathforcertiflocal/$webCustomSSLCertificateIntermediate"
                        cp -pn $pathforcertifmaster/$webCustomSSLCertificateIntermediate $pathforcertiflocal/$webCustomSSLCertificateIntermediate
                        # It is better to link to a bad certificate than linking to non existing file, so
                        if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateIntermediate ]]; then
                                echo "Previous cp not valid, so we recreate it from /etc/apache2/$webSSLCertificateIntermediate"
                                echo "ln -fs /etc/apache2/$webSSLCertificateIntermediate $pathforcertiflocal/$webCustomSSLCertificateIntermediate"
                                ln -fs /etc/apache2/$webSSLCertificateIntermediate $pathforcertiflocal/$webCustomSSLCertificateIntermediate
                        fi
                fi
		else
			# No $CERTIFFORCUSTOMDOMAIN forced (no cert file was created initially), so we will use existing one or generic one
            export domainnameorcustomurl=`echo $customurl | cut -d "." -f 1`

			# When we suspend, there is no need to generate the cert for the custom URL. Cert should already exists if a custom url has been defined.

            # But if custom cert not found, we fallback on the wildcard one for server (will generate a warning, but it will works !)
			if [[ -e /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-custom.crt ]]; then
	            export webCustomSSLCertificateCRT=$webSSLCertificateCRT
    	        export webCustomSSLCertificateKEY=$webSSLCertificateKEY
        	    export webCustomSSLCertificateIntermediate=$webSSLCertificateIntermediate
            	export CERTIFFORCUSTOMDOMAIN="with.sellyoursaas.com"
			else
				export webCustomSSLCertificateCRT=/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-custom.crt
				export webCustomSSLCertificateKEY=/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-custom.key
				export webCustomSSLCertificateIntermediate=/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-custom-intermediate.key
				export CERTIFFORCUSTOMDOMAIN="$fqn-custom"
			fi
		fi	
	
		
        # If the certificate file is not found, we disable SSL
        if [[ ! -e /etc/apache2/$webCustomSSLCertificateCRT ]]; then
			SSLON="Off"
        else
			SSLON="On"
        fi

        export apacheconf="/etc/apache2/sellyoursaas-available/$fqn.custom.conf"
        echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Create a new suspended apache conf $apacheconf from $vhostfiletouse"
	
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

	
	echo /usr/sbin/apache2ctl configtest
	/usr/sbin/apache2ctl configtest
	if [[ "x$?" != "x0" ]]; then
		echo Error when running apache2ctl configtest. We remove the new created virtual host /etc/apache2/sellyoursaas-online/$fqn...conf to hope to restore configtest ok.
		rm -f /etc/apache2/sellyoursaas-online/$fqn.conf
		rm -f /etc/apache2/sellyoursaas-online/$fqn.custom.conf
		#rm -f /etc/apache2/sellyoursaas-online/$fqn.website*.conf	# Not modified previously and removed just after
		echo "Failed to suspend instance $instancename.$domainname with: Error when running apache2ctl configtest" | mail -aFrom:$EMAILFROM -s "[Warning] Pb when suspending $instancename.$domainname" $EMAILTO 
		sleep 1
		exit 5
	fi 

	# Remove virtual host for public web sites by deleting links into sellyoursaas-enabled
	echo "Remove virtual host for possible virtual host for web sites"
	for fic in `ls /etc/apache2/sellyoursaas-online/$fqn.website-*.conf`
	do
		echo Delete conf with rm -f /etc/apache2/sellyoursaas-online/$fqn.website-*.conf
		rm -f /etc/apache2/sellyoursaas-online/$fqn.website-*.conf
	done
	
	
	if [[ "x$apachereload" != "xnoapachereload" ]]; then
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Apache tasks finished. service apache2 reload."
		service apache2 reload
		if [[ "x$?" != "x0" ]]; then
			echo Error when running service apache2 reload
			echo "Failed to suspend instance $instancename.$domainname with: Error when running service apache2 reload" | mail -aFrom:$EMAILFROM -s "[Warning] Pb when suspending $instancename.$domainname" $EMAILTO
			sleep 1 
			exit 6
		#else
		#   A sleep is already done at end of script
		#	sleep 1			
		fi
	else
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Apache tasks finished. But we do not reload apache2 now to reduce reloading."
	fi
fi


# Unsuspend. Can also be used to force recreation of Virtual host.

if [[ "$mode" == "unsuspend" ]]; then
	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Unsuspend instance in $targetdir/$osusername/$dbname"

	export vhostfiletouse=$vhostfile;

	export apacheconf="/etc/apache2/sellyoursaas-available/$fqn.conf"
	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** Create a new apache conf $apacheconf from $vhostfiletouse"

	if [[ -s $apacheconf ]]
	then
		echo "Apache conf $apacheconf already exists, we delete it since it may be a file from an old instance with same name"
		rm -f $apacheconf
	fi

	# Create virtual host for standard url (custom url may be created later)
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
			  sed -e "s;__webMyAccount__;$SELLYOURSAAS_ACCOUNT_URL;g" | \
			  sed -e "s;__webAppPath__;$instancedir;g" > $apacheconf


	# Enable conf with ln
	echo Enable conf with ln -fs /etc/apache2/sellyoursaas-available/$fqn.conf /etc/apache2/sellyoursaas-online
	ln -fs /etc/apache2/sellyoursaas-available/$fqn.conf /etc/apache2/sellyoursaas-online
	
	# We create the virtual host for the custom url
	if [[ "x$customurl" != "x" ]]; then
		echo `date +'%Y-%m-%d %H:%M:%S'`" ***** For instance in $targetdir/$osusername/$dbname, we will create a new custom virtual name $fqn.custom"

        export pathforcertifmaster="/home/admin/wwwroot/dolibarr_documents/sellyoursaas/crt"
        export pathforcertiflocal="/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt"

		echo `date +'%Y-%m-%d %H:%M:%S'`" Check that SSL files for $fqn.custom exists to reuse them"
	
        if [[ "x$CERTIFFORCUSTOMDOMAIN" != "x" ]]; then
                # If a name for a custom CERTIF stored on master was forced, we use this one as SSL certiticate
                export webCustomSSLCertificateCRT=$CERTIFFORCUSTOMDOMAIN.crt
                export webCustomSSLCertificateKEY=$CERTIFFORCUSTOMDOMAIN.key
                export webCustomSSLCertificateIntermediate=$CERTIFFORCUSTOMDOMAIN-intermediate.crt

                if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateCRT ]]; then
                        # If file or link does not exist
                        echo `date +'%Y-%m-%d %H:%M:%S'`" cp -pn file $pathforcertifmaster/$webCustomSSLCertificateCRT to $pathforcertiflocal/$webCustomSSLCertificateCRT"
                        cp -pn $pathforcertifmaster/$webCustomSSLCertificateCRT $pathforcertiflocal/$webCustomSSLCertificateCRT
                        # It is better to link to a bad certificate than linking to non existing file, so
                        if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateCRT ]]; then
                                echo "Previous cp not valid, so we create it from /etc/apache2/$webSSLCertificateCRT"
                                echo "ln -fs /etc/apache2/$webSSLCertificateCRT $pathforcertiflocal/$webCustomSSLCertificateCRT"
                                ln -fs /etc/apache2/$webSSLCertificateCRT $pathforcertiflocal/$webCustomSSLCertificateCRT
                        fi
                fi
                if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateKEY ]]; then
                        # If file or link does not exist
                        echo `date +'%Y-%m-%d %H:%M:%S'`" cp -pn file $pathforcertifmaster/$webCustomSSLCertificateKEY to $pathforcertiflocal/$webCustomSSLCertificateKEY"
                        cp -pn $pathforcertifmaster/$webCustomSSLCertificateKEY $pathforcertiflocal/$webCustomSSLCertificateKEY
                        # It is better to link to a bad certificate than linking to non existing file, so
                        if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateKEY ]]; then
                                echo "Previous cp not valid, so we create it from /etc/apache2/$webSSLCertificateKEY"
                                echo "ln -fs /etc/apache2/$webSSLCertificateKEY $pathforcertiflocal/$webCustomSSLCertificateKEY"
                                ln -fs /etc/apache2/$webSSLCertificateKEY $pathforcertiflocal/$webCustomSSLCertificateKEY
                        fi
                fi
                if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateIntermediate ]]; then
                        # If file or link does not exist
                        echo `date +'%Y-%m-%d %H:%M:%S'`" cp -pn file $pathforcertifmaster/$webCustomSSLCertificateIntermediate to $pathforcertiflocal/$webCustomSSLCertificateIntermediate"
                        cp -pn $pathforcertifmaster/$webCustomSSLCertificateIntermediate $pathforcertiflocal/$webCustomSSLCertificateIntermediate
                        # It is better to link to a bad certificate than linking to non existing file, so
                        if [[ ! -e $pathforcertiflocal/$webCustomSSLCertificateIntermediate ]]; then
                                echo "Previous cp not valid, so we recreate it from /etc/apache2/$webSSLCertificateIntermediate"
                                echo "ln -fs /etc/apache2/$webSSLCertificateIntermediate $pathforcertiflocal/$webCustomSSLCertificateIntermediate"
                                ln -fs /etc/apache2/$webSSLCertificateIntermediate $pathforcertiflocal/$webCustomSSLCertificateIntermediate
                        fi
                fi
        else
			# No $CERTIFFORCUSTOMDOMAIN forced (no cert file was created initially), so we will use existing one or generic one
            export domainnameorcustomurl=`echo $customurl | cut -d "." -f 1`

			# When we unsuspend, there is no need to generate the cert for the custom URL. Cert should already exists if a custom url has been defined.

			# If custom cert not found, we fallback on the wildcard one for server (will generate a warning, but it will works !)
			if [[ -e /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-custom.crt ]]; then
	            export webCustomSSLCertificateCRT=$webSSLCertificateCRT
    	        export webCustomSSLCertificateKEY=$webSSLCertificateKEY
        	    export webCustomSSLCertificateIntermediate=$webSSLCertificateIntermediate
            	export CERTIFFORCUSTOMDOMAIN="with.sellyoursaas.com"
			else
				export webCustomSSLCertificateCRT=/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-custom.crt
				export webCustomSSLCertificateKEY=/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-custom.key
				export webCustomSSLCertificateIntermediate=/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/$fqn-custom-intermediate.key
				export CERTIFFORCUSTOMDOMAIN="$fqn-custom"
			fi
		fi	
	
		
        # If the certificate file is not found, we disable SSL
        if [[ ! -e /etc/apache2/$webCustomSSLCertificateCRT ]]; then
			SSLON="Off"
        else
			SSLON="On"
        fi
        	
		export apacheconf="/etc/apache2/sellyoursaas-available/$fqn.custom.conf"
		echo "Create a new apache conf $apacheconf from $vhostfiletouse"
	
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


if [[ "$mode" == "suspend" || "$mode" == "suspendredirect" ]]; then

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


if [[ "$mode" == "suspendredirect" ]]; then

	export ZONE="$domainname.hosts" 

	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** If IP for $instancename in DNS files /etc/bind/${ZONE} has changed and is not $REMOTEIP, we must also change the DNS entry."

	echo "Entry $instancename not found into host /etc/bind/${ZONE}, we must add it with: /home/admin/wwwroot/dolibarr/scripts/deployment_update_dnszone.php set ${ZONE} $instancename $REMOTEIP"

	/home/admin/wwwroot/dolibarr_sellyoursaas/scripts/deployment_update_dnszone.php set "$domainname" "$instancename" "$REMOTEIP"

	if [[ "$?x" != "0x" ]]; then
		echo error: Failed to update the DNS file.
		exit 15
	fi
fi


echo `date +'%Y-%m-%d %H:%M:%S'`" Process of action $mode of $instancename.$domainname for user $osusername finished"
sleep 1
echo `date +'%Y-%m-%d %H:%M:%S'`" return 0"
echo

exit 0
