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
echo "${0} ${1} ... (other parameters not shown, they contain passwords)"
echo "# user id --------> $(id -u)"
echo "# now ------------> $now"
echo "# PID ------------> ${$}"
echo "# PWD ------------> $PWD"
#echo "# arguments ------> ${@}"
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
if [[ ! "$laststableupgradeversion" =~ ^[0-9]+$ || ! "$lastversiondolibarrinstance" =~ ^[0-9]+$ ]]; then
	echo "Invalid parameter 44 or 45 - versions must be integers" 1>&2
	exit 45
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

export usecompressformatforarchive=`grep '^usecompressformatforarchive=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

# possibility to change the path of sellyoursass directory
olddoldataroot="/home/admin/wwwroot/dolibarr_documents"
newdoldataroot=`grep '^doldataroot=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$newdoldataroot" != "x" ]]; then
	fileforconfig1=${fileforconfig1/$olddoldataroot/$newdoldataroot}
	dirwithdumpfile=${dirwithdumpfile/$olddoldataroot/$newdoldataroot}
	dirwithsources1=${dirwithsources1/$olddoldataroot/$newdoldataroot}
	dirwithsources2=${dirwithsources2/$olddoldataroot/$newdoldataroot}
	dirwithsources3=${dirwithsources3/$olddoldataroot/$newdoldataroot}
	cronfile=${cronfile/$olddoldataroot/$newdoldataroot}
	cliafter=${cliafter/$olddoldataroot/$newdoldataroot}
fi

export archivenotfounddirwithsources=0

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
	echo `date +'%Y-%m-%d %H:%M:%S'`" ***** upgrade instance"
	if [ $lastversiondolibarrinstance -lt 4 ]
	then
		echo "Version too old."
		exit 220
	fi

	# The instance user owns $targetdir/$osusername, so $dbname may have been replaced by a symbolic link to redirect
	# actions done as root. So we go into the instance dir and check it is the real one before working on it.
	realinstancedir="$(realpath "$targetdir")/$osusername/$dbname"
	function cdrealinstancedir() {
		cd "$instancedir/" || exit 225
		if [ "$(pwd -P)" != "$realinstancedir" ]; then
			echo "Error: $instancedir is not the real directory $realinstancedir (symbolic link ?)"
			exit 225
		fi
	}
	echo `date +'%Y-%m-%d %H:%M:%S'`" cd $instancedir/ and check it is a real directory"
	cdrealinstancedir
	if [ -d "$dirforexampleforsources" ]
	then
		# First try with sourcefile.tar.zst/.tgz that is into tmp cache dir /tmp/cache/home/admin/wwwroot/dolibarr_documents/sellyoursaas/git/xxx.tar.zst/.tgz
		# If cache file does not exists, use the archive file into NFS dir $dirforexampleforsources/ with tar -I zstd -xf sourcefile.tar.zst --directory $targetdirwithsources1/
		# If archive file not found, do the rsync.

		# First try to find the cache file
		if [ -d $dirwithsources1 ]; then
			if [[ "x$targetdirwithsources1" != "x" ]]; then
				mkdir -p $targetdirwithsources1
				if [[ -f /tmp/cache$dirwithsources1.tar.zst ]]; then
					echo `date +'%Y-%m-%d %H:%M:%S'`" Local zst cache found for src 1. We use it with: tar -I zstd -xf /tmp/cache$dirwithsources1.tar.zst --directory $targetdirwithsources1/"
					tar -I zstd -xf /tmp/cache$dirwithsources1.tar.zst --directory $targetdirwithsources1/
				else
					echo "No cache file /tmp/cache$dirwithsources1.tar.zst found";
					if [ -f /tmp/cache$dirwithsources1.tgz ]; then
						echo `date +'%Y-%m-%d %H:%M:%S'`" Local tgz cache found for src 1. We use it with: tar -xzf /tmp/cache$dirwithsources1.tgz --directory $targetdirwithsources1/"
						tar -xzf /tmp/cache$dirwithsources1.tgz --directory $targetdirwithsources1/
					else
						echo "No cache file /tmp/cache$dirwithsources1.tgz found";
						# If local cache does not exists
						if [ -f $dirwithsources1.tar.zst ]; then
							echo `date +'%Y-%m-%d %H:%M:%S'`" Local cache not found for src 1. We try with with remote archive with: tar -I zstd -xf $dirwithsources1.tat.zst --directory $targetdirwithsources1/"
							tar -I zstd -xf $dirwithsources1.tar.zst --directory $targetdirwithsources1/
						else
							echo `date +'%Y-%m-%d %H:%M:%S'`" Remote archive not found for dirwithsources1 (".$dirwithsources1.tar.zst." not found)."
							archivenotfounddirwithsources=1
						fi
					fi
				fi
			else
				archivenotfounddirwithsources=1
			fi
		else
			archivenotfounddirwithsources=1
		fi

		if [ ! $archivenotfounddirwithsources -eq 1 ] && [ -d $dirwithsources2 ]; then
			if [[ "x$targetdirwithsources2" != "x" ]]; then
				mkdir -p $targetdirwithsources2
				if [[ -f /tmp/cache$dirwithsources2.tar.zst ]]; then
					echo `date +'%Y-%m-%d %H:%M:%S'`" Local zst cache found for src 2. We use it with: tar -I zstd -xf /tmp/cache$dirwithsources2.tar.zst --directory $targetdirwithsources2/"
					tar -I zstd -xf /tmp/cache$dirwithsources2.tar.zst --directory $targetdirwithsources2/
				else
					echo "No cache file /tmp/cache$dirwithsources2.tar.zst found";
					if [ -f /tmp/cache$dirwithsources2.tgz ]; then
						echo `date +'%Y-%m-%d %H:%M:%S'`" Local tgz cache found for src 2. We use it with: tar -xzf /tmp/cache$dirwithsources2.tgz --directory $targetdirwithsources2/"
						tar -xzf /tmp/cache$dirwithsources2.tgz --directory $targetdirwithsources2/
					else
						echo "No cache file /tmp/cache$dirwithsources2.tgz found";
						# If local cache does not exists
						if [ -f $dirwithsources2.tar.zst ]; then
							echo `date +'%Y-%m-%d %H:%M:%S'`" Local cache not found for src 2. We try with with remote archive with: tar -I zstd -xf $dirwithsources2.tar.zst --directory $targetdirwithsources2/"
							tar -I zstd -xf $dirwithsources2.tar.zst --directory $targetdirwithsources2/
						else
							echo `date +'%Y-%m-%d %H:%M:%S'`" Remote archive not found for dirwithsources2 (".$dirwithsources2.tar.zst." not found)."
							archivenotfounddirwithsources=1
						fi
					fi
				fi
			else
				archivenotfounddirwithsources=1
			fi
		fi

		if [ ! $archivenotfounddirwithsources -eq 1 ] && [ -d $dirwithsources3 ]; then
			if [[ "x$targetdirwithsources3" != "x" ]]; then
				mkdir -p $targetdirwithsources3
				if [[ -f /tmp/cache$dirwithsources3.tar.zst ]]; then
					echo `date +'%Y-%m-%d %H:%M:%S'`" Local zst cache found for src 3. We use it with: tar -I zstd -xf /tmp/cache$dirwithsources3.tar.zst --directory $targetdirwithsources3/"
					tar -I zstd -xf /tmp/cache$dirwithsources3.tar.zst --directory $targetdirwithsources3/
				else
					echo "No cache file /tmp/cache$dirwithsources3.tar.zst found";
					if [ -f /tmp/cache$dirwithsources3.tgz ]; then
						echo `date +'%Y-%m-%d %H:%M:%S'`" Local tgz cache found for src 3. We use it with: tar -xzf /tmp/cache$dirwithsources3.tgz --directory $targetdirwithsources3/"
						tar -xzf /tmp/cache$dirwithsources3.tgz --directory $targetdirwithsources3/
					else
						echo "No cache file /tmp/cache$dirwithsources3.tgz found";
						# If local cache does not exists
						if [ -f $dirwithsources3.tar.zst ]; then
							echo `date +'%Y-%m-%d %H:%M:%S'`" Local cache not found for src 3. We try with with remote archive with: tar -I zstd -xf $dirwithsources3.tar.zst --directory $targetdirwithsources3/"
							tar -I zstd -xf $dirwithsources3.tar.zst --directory $targetdirwithsources3/
						else
							echo `date +'%Y-%m-%d %H:%M:%S'`" Remote archive not found for dirwithsources3 (".$dirwithsources3.tar.zst." not found)."
							archivenotfounddirwithsources=1
						fi
					fi
				fi
			else
				archivenotfounddirwithsources=1
			fi
		fi

		# If some cache file not found,
		if [ $archivenotfounddirwithsources -eq 1 ]; then
			echo `date +'%Y-%m-%d %H:%M:%S'`" Local cache and remote archive not found for at least 1 dirwithsource."
			echo `date +'%Y-%m-%d %H:%M:%S'`" We try with $dirforexampleforsources with : rsync -rlt -p -og --chmod=a+x,g-rwx,o-rwx --chown=$osusername:$osusername $dirforexampleforsources/* $instancedir/ --exclude test/ --exclude .buildpath --exclude .codeclimate.yml --exclude .editorconfig --exclude .git --exclude .github --exclude .gitignore --exclude .gitmessage --exclude .mailmap --exclude .settings --exclude .scrutinizer.yml --exclude .stickler.yml --exclude .project --exclude .travis.yml --exclude .tx --exclude phpstan.neon --exclude build/exe/ --exclude dev/ --exclude documents/ --include htdocs/modulebuilder/template/test/ --exclude test/ --exclude htdocs/conf/conf.php* --exclude htdocs/custom"
			rsync -rlt -p -og --chmod=a+x,g-rwx,o-rwx --chown=$osusername:$osusername $dirforexampleforsources/* $instancedir/ --exclude test/ --exclude .buildpath --exclude .codeclimate.yml --exclude .editorconfig --exclude .git --exclude .github --exclude .gitignore --exclude .gitmessage --exclude .mailmap --exclude .settings --exclude .scrutinizer.yml --exclude .stickler.yml --exclude .project --exclude .travis.yml --exclude .tx --exclude phpstan.neon --exclude build/exe/ --exclude dev/ --exclude documents/ --include htdocs/modulebuilder/template/test/ --exclude test/ --exclude htdocs/conf/conf.php* --exclude htdocs/custom
			if [ $? -eq 0 ];then
				archivenotfounddirwithsources=0
			fi
		fi

		if [ $archivenotfounddirwithsources -eq 0 ]
		then
			echo "Successfully copied files of new version"
		else
			echo "Error on copying files of new version"
			exit 221
		fi

		echo `date +'%Y-%m-%d %H:%M:%S'`" Force permissions and owner on $targetdir/$osusername/$dbname"
		chown $osusername:$osusername $targetdir/$osusername
		echo `date +'%Y-%m-%d %H:%M:%S'`" chown -R $osusername:$osusername $targetdir/$osusername/$dbname"
		# Current dir is still the checked instance dir, so we use "." (chmod -R follows a symbolic link given as argument)
		chown -R $osusername:$osusername .
		echo `date +'%Y-%m-%d %H:%M:%S'`" chmod -R go-rwxs $targetdir/$osusername/$dbname"
		chmod -R go-rwxs .

		echo `date +'%Y-%m-%d %H:%M:%S'`" cd $instancedir/"
        cdrealinstancedir

		if [ ! -d "$instancedir/documents/admin/temp" ]
		then
			echo `date +'%Y-%m-%d %H:%M:%S'`" mkdir -p $instancedir/documents/admin/temp"
			# Created by the instance user (never by root) because this directory is controlled by the instance user
			runuser -u "$osusername" -- mkdir -p "$instancedir/documents/admin/temp"
		fi

		echo `date +'%Y-%m-%d %H:%M:%S'`" cd $instancedir/htdocs/install/"
		cd "$instancedir/htdocs/install/"

		fileoutputhtml="output-"`date +'%Y%m%d%H%M%S'`".html"

		echo `date +'%Y-%m-%d %H:%M:%S'`" clean the output file $instancedir/documents/admin/temp/$fileoutputhtml"
		# Created by the instance user (never by root) because this directory is controlled by the instance user
		runuser -u "$osusername" -- truncate -s 0 "$instancedir/documents/admin/temp/$fileoutputhtml"


		# Note: if target is version x, we must start with migration from x-1 to x, so
		# we manage the case the instance has a minor version lower.
		versionfrom=$(( $lastversiondolibarrinstance - 1 ))
		versionto=$(( $versionfrom + 1 ))
		echo `date +'%Y-%m-%d %H:%M:%S'`" versionfrom=$versionfrom versionto=$versionto"

		# Run the first pass of upgrade
		upgradeerror=0
		while [ $versionto -le $laststableupgradeversion ]
		do
			if [ -f "$instancedir/documents/install.lock" ]
			then
				echo `date +'%Y-%m-%d %H:%M:%S'`" rm $instancedir/documents/install.lock"
				runuser -u "$osusername" -- rm "$instancedir/documents/install.lock"
			fi

			echo `date +'%Y-%m-%d %H:%M:%S'`" upgrade from version $versionfrom.0.0 to version $versionto.0.0 (or last minor version)"

			# Run php scripts and write their output as the instance user (never as root): they load files owned by this user (conf.php...)
			echo `date +'%Y-%m-%d %H:%M:%S'`" runuser -u $osusername -- php upgrade.php $versionfrom.0.0 $versionto.0.0 >> $instancedir/documents/admin/temp/$fileoutputhtml"
			runuser -u "$osusername" -- sh -c 'php upgrade.php "$1" "$2" >> "$3"; ret=$?; echo >> "$3"; exit $ret' _ "$versionfrom.0.0" "$versionto.0.0" "$instancedir/documents/admin/temp/$fileoutputhtml"

			if [ $? -eq 0 ]
			then
				echo `date +'%Y-%m-%d %H:%M:%S'`" runuser -u $osusername -- php upgrade2.php $versionfrom.0.0 $versionto.0.0 >> $instancedir/documents/admin/temp/$fileoutputhtml"
				runuser -u "$osusername" -- sh -c 'php upgrade2.php "$1" "$2" >> "$3"; ret=$?; echo >> "$3"; exit $ret' _ "$versionfrom.0.0" "$versionto.0.0" "$instancedir/documents/admin/temp/$fileoutputhtml"

				if [ $? -eq 0 ]
				then
					echo `date +'%Y-%m-%d %H:%M:%S'`" runuser -u $osusername -- php step5.php $versionfrom.0.0 $versionto.0.0 >> $instancedir/documents/admin/temp/$fileoutputhtml"
					runuser -u "$osusername" -- sh -c 'php step5.php "$1" "$2" >> "$3"; ret=$?; echo >> "$3"; exit $ret' _ "$versionfrom.0.0" "$versionto.0.0" "$instancedir/documents/admin/temp/$fileoutputhtml"

					if [ $? -eq 0 ]
					then
						echo "Successfully upgraded to version $versionto"
					else
						echo "Error on step5.php"
						upgradeerror=224
					fi

				else
					echo "Error on upgrade2.php"
					upgradeerror=223
				fi
			else
				echo "Error on upgrade.php"
				upgradeerror=222
			fi

			# On error, stop the upgrade but exit only after the install.lock file has been recreated
			if [ $upgradeerror -ne 0 ]; then
				break
			fi

			versionfrom=$(( $versionfrom + 1 ))
			versionto=$(( $versionto + 1 ))
		done

		echo `date +'%Y-%m-%d %H:%M:%S'`" cd $instancedir/"
		cdrealinstancedir

		if [ ! -f "documents/install.lock" ]
		then
			echo `date +'%Y-%m-%d %H:%M:%S'`" Recreate the lock file documents/install.lock"
			# Created by the instance user (never by root) so a symbolic link set by this user can't be followed as root
			runuser -u "$osusername" -- sh -c 'touch documents/install.lock && chmod o-w documents/install.lock'
		fi

		# Restore user owner on all files into documents
		# because the upgrade/upgrade2/step5 may have created new files owned by root (because they were run with root).
		# This may be very long (from 1s to 10mn) so we execute it in background
		echo `date +'%Y-%m-%d %H:%M:%S'`" find $instancedir/documents ! -user $osusername -exec chown -h $osusername:$osusername {} +"
		find documents ! -user $osusername -exec chown -h $osusername:$osusername {} + &

		if [ $upgradeerror -ne 0 ]; then
			echo `date +'%Y-%m-%d %H:%M:%S'`" Upgrade failed, see $instancedir/documents/admin/temp/$fileoutputhtml - exit $upgradeerror"
			exit $upgradeerror
		fi
	fi
fi

echo `date +'%Y-%m-%d %H:%M:%S'`" Process of action $mode of $instancename.$domainname for user $osusername finished with success"
sleep 1
echo `date +'%Y-%m-%d %H:%M:%S'`" return 0"
echo

exit 0
