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
echo "# arguments ------> ${@}"
echo "# parent path ----> ${0%/*}"
echo "# realname name --> $(basename $(realpath ${0}))"
echo "# realname dir ---> $(dirname $(realpath ${0}))"

export PID=${$}
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
	echo "Missing parameter 1 - mode (upgrade)" 1>&2
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
if [ "x$43" == "x" ]; then
        echo "Missing parameter 43 - dirforexampleforsources"
        exit 43
fi
if [ "x$44" == "x" ]; then
        echo "Missing parameter 44 - laststableupgradeversion"
        exit 44
fi
if [ "x$45" == "x" ]; then
        echo "Missing parameter 45 - lastversiondolibarrinstance"
        exit 45
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

export fileforconfig1=${10//£/ }
export targetfileforconfig1=${11//£/ }
export dirwithdumpfile=${12//£/ }
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

export dirforexampleforsources=${43}
export laststableupgradeversion=${44}
export lastversiondolibarrinstance=${45}

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

export usecompressformatforarchive=`grep '^usecompressformatforarchive=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

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
echo "templatesdir (from parameters) = $templatesdir"
echo "instancedir (from parameters) = $instancedir"
echo "fqn = $fqn"
echo "fqnold = $fqnold"
echo "CRONHEAD = $CRONHEAD"
echo "dirforexampleforsources = $dirforexampleforsources"
echo "laststableupgradeversion = $laststableupgradeversion"
echo "lastversiondolibarrinstance = $lastversiondolibarrinstance"


MYSQL=`which mysql`
MYSQLDUMP=`which mysqldump`


testorconfirm="confirm"


# Backup of database should have been done previously
# by calling the remote action 'backup'


# Upgrade

if [[ "$mode" == "upgrade" ]];then
	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** upgrade dolibarr instance"
	if [ $lastversiondolibarrinstance -lt 4 ]
	then
		echo "Version too old."
		exit 220
	fi
	if [ -d "$dirforexampleforsources" ]
	then
		echo "rsync -rlt -p -og --chmod=a+x,g-rwx,o-rwx --chown=$osusername:$osusername $dirforexampleforsources/* $instancedir/ --exclude test/ --exclude .buildpath --exclude .codeclimate.yml --exclude .editorconfig --exclude .git --exclude .github --exclude .gitignore --exclude .gitmessage --exclude .mailmap --exclude .settings --exclude .scrutinizer.yml --exclude .stickler.yml --exclude .project --exclude .travis.yml --exclude .tx --exclude phpstan.neon --exclude build/exe/ --exclude dev/ --exclude documents/ --include htdocs/modulebuilder/template/test/ --exclude test/ --exclude htdocs/conf/conf.php* --exclude htdocs/custom"
		rsync -rlt -p -og --chmod=a+x,g-rwx,o-rwx --chown=$osusername:$osusername $dirforexampleforsources/* $instancedir/ --exclude test/ --exclude .buildpath --exclude .codeclimate.yml --exclude .editorconfig --exclude .git --exclude .github --exclude .gitignore --exclude .gitmessage --exclude .mailmap --exclude .settings --exclude .scrutinizer.yml --exclude .stickler.yml --exclude .project --exclude .travis.yml --exclude .tx --exclude phpstan.neon --exclude build/exe/ --exclude dev/ --exclude documents/ --include htdocs/modulebuilder/template/test/ --exclude test/ --exclude htdocs/conf/conf.php* --exclude htdocs/custom

		if [ $? -eq 0 ]
		then
			echo "Successfully copied dolibarr folder"
		else
			echo "Error on copying dolibarr folder"
			exit 221
		fi

		echo `date +'%Y-%m-%d %H:%M:%S'`" cd $instancedir/"
        cd $instancedir/

		if [ ! -d "$instancedir/documents/admin/temp" ]
		then
			echo "mkdir -p $instancedir/documents/admin/temp"
			mkdir -p "$instancedir/documents/admin/temp"
			chown -R $osusername.$osusername "$instancedir/documents/admin/temp"
		fi

		echo `date +'%Y-%m-%d %H:%M:%S'`" cd $instancedir/htdocs/install/"
		cd "$instancedir/htdocs/install/"

		echo `date +'%Y-%m-%d %H:%M:%S'`" clean the output file $instancedir/documents/admin/temp/output.html"
		> "$instancedir/documents/admin/temp/output.html"
		chown $osusername.$osusername "$instancedir/documents/admin/temp/output.html"


		versionfrom=$lastversiondolibarrinstance
		versionto=$(( $versionfrom + 1 ))
		while [ $versionfrom -le $laststableupgradeversion ]
		do
			if [ -f "$instancedir/documents/install.lock" ]
			then
				echo `date +'%Y-%m-%d %H:%M:%S'`" rm $instancedir/documents/install.lock"
				rm "$instancedir/documents/install.lock"
			fi

			echo `date +'%Y-%m-%d %H:%M:%S'`" upgrade from version $versionfrom.0.0 to version $versionto.0.0 (or last minor version)"

			echo `date +'%Y-%m-%d %H:%M:%S'`" php upgrade.php $versionfrom.0.0 $versionto.0.0 >> $instancedir/documents/admin/temp/output.html"
			php upgrade.php $versionfrom.0.0 $versionto.0.0 >> "$instancedir/documents/admin/temp/output.html"
			echo >> "$instancedir/documents/admin/temp/output.html"

			if [ $? -eq 0 ]
			then
				echo `date +'%Y-%m-%d %H:%M:%S'`" php upgrade2.php $versionfrom.0.0 $versionto.0.0 >> $instancedir/documents/admin/temp/output.html"
				php upgrade2.php $versionfrom.0.0 $versionto.0.0 >> "$instancedir/documents/admin/temp/output.html"
				echo >> "$instancedir/documents/admin/temp/output.html"

				if [ $? -eq 0 ]
				then
					echo `date +'%Y-%m-%d %H:%M:%S'`" php step5.php $versionfrom.0.0 $versionto.0.0 >> $instancedir/admin/temp/output.html"
					php step5.php $versionfrom.0.0 $versionto.0.0 >> "$instancedir/documents/admin/temp/output.html"
					echo >> "$instancedir/documents/admin/temp/output.html"

					if [ $? -eq 0 ]
					then
						echo "Successfully upgraded to version $versionto"
					else
						echo "Error on step5.php"
						exit 224
					fi

				else
					echo "Error on upgrade2.php"
					exit 223
				fi
			else
				echo "Error on upgrade.php"
				exit 222
			fi
			versionfrom=$(( $versionfrom + 1 ))
			versionto=$(( $versionto + 1 ))
		done

		echo `date +'%Y-%m-%d %H:%M:%S'`" cd $instancedir/"
		cd $instancedir/

		if [ ! -f "documents/install.lock" ]
		then
			echo `date +'%Y-%m-%d %H:%M:%S'`" Recreate the lock file documents/install.lock"
			touch documents/install.lock
			chmod o-w documents/install.lock
			chown $osusername.$osusername documents/install.lock 
		fi
		
		# Restore user owner on all files into documents
		# because the upgrade/upgrade2/step5 may have created new files owned by root (because they were run with root).
		echo `date +'%Y-%m-%d %H:%M:%S'`" find $instancedir/documents ! -user $osusername -exec chown $osusername.$osusername {} \;"
		find "$instancedir/documents" ! -user $osusername -exec chown $osusername.$osusername {} \; 
	fi
fi

echo `date +'%Y-%m-%d %H:%M:%S'`" Process of action $mode of $instancename.$domainname for user $osusername finished with success"
sleep 1
echo `date +'%Y-%m-%d %H:%M:%S'`" return 0"
echo

exit 0
