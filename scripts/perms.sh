#!/bin/bash
#--------------------------------------------------------#
# Script to force permission on expected default values
#--------------------------------------------------------#

#source /etc/lsb-release

export RED='\033[0;31m'
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[0;33m'


if [ "$(id -u)" != "0" ]; then
	echo "This script must be run as root" 1>&2
	exit 100
fi

# possibility to change the directory where instances are stored
export targetdir=`grep '^targetdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$targetdir" == "x" ]]; then
	export targetdir="/home/jail/home"
fi

# possibility to change the directory where backup instances are stored
export backupdir=`grep '^backupdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$backupdir" == "x" ]]; then
	export backupdir="/mnt/diskbackup/backup"
fi

echo "Search to know if we are a master server in /etc/sellyoursaas.conf"
masterserver=`grep '^masterserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
instanceserver=`grep '^instanceserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

export pathtospamdir=`grep '^pathtospamdir=' /etc/sellyoursaas-public.conf | cut -d '=' -f 2`
if [ "x$pathtospamdir" == "x" ]; then
	export pathtospamdir="/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam"
fi

# Go into a safe dir
cd /tmp

#echo "Remplacement user apache par www-data"
#find . -user apache -exec chown www-data {} \;

#echo "Remplacement group apache par www-data"
#find . -group apache -exec chgrp www-data {} \;

# Owner root on logs and backups dir
echo "Set owner and permission on logs and backup directory"
[ -d /home/admin/logs ] || mkdir /home/admin/logs;
[ -d /mnt/diskbackup ] || mkdir /mnt/diskbackup;
[ -d /home/admin/backup ] || mkdir /home/admin/backup;
[ -d /home/admin/backup/conf ] || mkdir /home/admin/backup/conf;
[ -d /home/admin/backup/mysql ] || mkdir /home/admin/backup/mysql;
[ -d /home/admin/wwwroot ] || mkdir /home/admin/wwwroot;
chown root:admin /home/admin/logs; chmod 770 /home/admin/logs; 
chown admin:admin /mnt/diskbackup; 
chown admin:admin /home/admin/backup; chown admin:admin /home/admin/backup/conf; chown admin:admin /home/admin/backup/mysql; 
chown admin:admin /home/admin/wwwroot

# Permissions on SSH config and private key files
echo "Set owner and permission on admin ssh files"
[ -s /home/admin/.ssh/config ] && chmod go-rwx /home/admin/.ssh/config && chown admin:admin /home/admin/.ssh/config
[ -s /home/admin/.ssh/id_rsa ] && chmod go-rwx /home/admin/.ssh/id_rsa && chown admin:admin /home/admin/.ssh/id_rsa
[ -s /home/admin/.ssh/id_rsa.pub ] && chmod go-wx /home/admin/.ssh/id_rsa.pub && chown admin:admin /home/admin/.ssh/id_rsa.pub
[ -s /home/admin/.ssh/id_rsa_sellyoursaas ] && chmod go-rwx /home/admin/.ssh/id_rsa_sellyoursaas && chown admin:admin /home/admin/.ssh/id_rsa_sellyoursaas 
[ -s /home/admin/.ssh/id_rsa_sellyoursaas.pub ] && chmod go-wx /home/admin/.ssh/id_rsa_sellyoursaas.pub && chown admin:admin /home/admin/.ssh/id_rsa_sellyoursaas.pub 


echo "Set owner and permission on /home/admin/wwwroot/dolibarr_documents/ (except sellyoursaas)"
chmod g+ws /home/admin/wwwroot/dolibarr_documents/
chown admin:www-data /home/admin/wwwroot/dolibarr_documents
for fic in `ls /home/admin/wwwroot/dolibarr_documents | grep -v sellyoursaas`; 
do 
	chown -R admin:www-data "/home/admin/wwwroot/dolibarr_documents/$fic"
	chmod -R ug+rw "/home/admin/wwwroot/dolibarr_documents/$fic"
	find "/home/admin/wwwroot/dolibarr_documents/$fic" -type d -exec chmod u+wx {} \;
	find "/home/admin/wwwroot/dolibarr_documents/$fic" -type d -exec chmod g+ws {} \;
done
if [ -d /home/admin/wwwroot/dolibarr_documents/users/temp/odtaspdf ]; then
	chown www-data:www-data /home/admin/wwwroot/dolibarr_documents/users/temp/odtaspdf
fi

if [[ "x$masterserver" == "x1" ]]; then
	echo We are on a master server, Set owner and permission on /home/admin/wwwroot/dolibarr_documents/sellyoursaas
	chown -R admin:www-data /home/admin/wwwroot/dolibarr_documents/sellyoursaas
	chmod -R ug+rw /home/admin/wwwroot/dolibarr_documents/sellyoursaas/git
	chmod -R ug+rw /home/admin/wwwroot/dolibarr_documents/sellyoursaas/packages
	chmod -R ug+rw /home/admin/wwwroot/dolibarr_documents/sellyoursaas/temp
	chmod -R ug+rw /home/admin/wwwroot/dolibarr_documents/sellyoursaas/crt
fi

echo Set owner and permission on /etc/sellyoursaas.conf
if [ ! -s /etc/sellyoursaas.conf ]; then
	echo > /etc/sellyoursaas.conf
fi
chown -R root:admin /etc/sellyoursaas.conf
chmod g-wx /etc/sellyoursaas.conf
chmod o-rwx /etc/sellyoursaas.conf

echo Set owner and permission on /etc/sellyoursaas-pubic.conf
if [ ! -s /etc/sellyoursaas-public.conf ]; then
	echo > /etc/sellyoursaas-public.conf
fi
chown -R root:admin /etc/sellyoursaas-public.conf
chmod a+r /etc/sellyoursaas-public.conf
chmod a-wx /etc/sellyoursaas-public.conf

echo Set owner and permission on /home/admin/wwwroot/dolibarr
chown -R admin:admin /home/admin/wwwroot/dolibarr
chmod -R a-w /home/admin/wwwroot/dolibarr
chmod -R u+w /home/admin/wwwroot/dolibarr/.git

if [ -d /home/admin/wwwroot/dolibarr_nltechno ]; then
	echo Set owner and permission on /home/admin/wwwroot/dolibarr_nltechno
	chmod -R a-w /home/admin/wwwroot/dolibarr_nltechno 2>/dev/null
	chmod -R u+w /home/admin/wwwroot/dolibarr_nltechno/.git 2>/dev/null
fi

if [ -d /home/admin/wwwroot/dolibarr_sellyoursaas ]; then
	echo Set owner and permission on /home/admin/wwwroot/dolibarr_sellyoursaas
	chmod -R a-w /home/admin/wwwroot/dolibarr_sellyoursaas 2>/dev/null
	chmod -R u+w /home/admin/wwwroot/dolibarr_sellyoursaas/.git 2>/dev/null
fi

echo Set owner and permission on /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php
if [ -f /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php ]; then
	chown www-data:admin /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php
	chmod o-rwx /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php
fi

echo Set owner and permission on SSL certificates /etc/apache2/*.key and /etc/lestencrypt
for fic in `ls /etc/apache2/ | grep '.key$'`; 
do 
	chown root:www-data /etc/apache2/$fic
	chmod ug+r /etc/apache2/$fic
	chmod o-rwx /etc/apache2/$fic
done
chmod go+x /etc/letsencrypt/archive
chmod go+x /etc/letsencrypt/live

if [[ "x$masterserver" == "x1" ]]; then
	echo We are on a master server, so we clean old temp files 
	find /home/admin/wwwroot/dolibarr_documents/sellyoursaas/temp -maxdepth 1 -name "*.tmp" -type f -mtime +3 -delete
fi

echo "Clean old log files in /home/admin/wwwroot/dolibarr_documents"
echo find /home/admin/wwwroot/dolibarr_documents -maxdepth 1 -name "dolibarr*.log*" -type f -mtime +3 -delete
find /home/admin/wwwroot/dolibarr_documents -maxdepth 1 -name "dolibarr*.log*" -type f -mtime +3 -delete

echo "Clean old files in /tmp"
echo find /tmp -mtime +30 -name 'phpsendmail*.*' -delete
find /tmp -mtime +30 -name 'phpsendmail*.*' -delete

echo "Check files for antispam system and create them if not found"
# Note: just after we redo it using $pathtospamdir variable
[ -d /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam ] || mkdir -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam;
[ ! -d /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam -o -s /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistmail ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas/spam/blacklistmail /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/;
[ ! -d /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam -o -s /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistip ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas/spam/blacklistip /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/;
[ ! -d /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam -o -s /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistfrom ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas/spam/blacklistfrom /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/;
[ ! -d /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam -o -s /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistcontent ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas/spam/blacklistcontent /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/;
chmod a+rwx /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam; chmod a+rw /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/* >/dev/null 2>&1;
chown admin:www-data /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/* >/dev/null 2>&1;
# If $pathtospamdir is default (/home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam) we do the same than previously
[ -d $pathtospamdir ] || mkdir $pathtospamdir;
[ -s /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistmail -o -s $pathtospamdir/blacklistmail ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistmail $pathtospamdir/;
[ -s /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistip -o -s $pathtospamdir/blacklistip ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistip $pathtospamdir/;
[ -s /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistfrom -o -s $pathtospamdir/blacklistfrom ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistfrom $pathtospamdir/;
[ -s /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistcontent -o -s $pathtospamdir/blacklistcontent ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistcontent $pathtospamdir/;
chmod a+rwx $pathtospamdir; chmod a+rw $pathtospamdir/* >/dev/null 2>&1
chown admin:www-data $pathtospamdir/* >/dev/null 2>&1

chown -R admin:www-data /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local;

# Special actions...

# Clean some files
if [ "x$instanceserver" != "x0" -a "x$instanceserver" != "x" ]; then
	IFS=$(echo -en "\n\b")
	echo We are on a deployment server, so we clean log files 
	echo "Clean web server logs $targetdir/osu*/dbn*/*_error.log"
	for fic in `ls -Adp $targetdir/osu*/dbn*/*_error.log 2>/dev/null | grep -v '/$'`; do > "$fic"; done
	echo "Clean applicative log files in $targetdir/osu*/dbn*/..."
	for fic in `ls -Adp $targetdir/osu*/dbn*/documents/dolibarr*.log 2>/dev/null | grep -v '/$'`; do > "$fic"; done
	for fic in `ls -Adp $targetdir/osu*/dbn*/htdocs/files/_log/*.log 2>/dev/null | grep -v '/$'`; do > "$fic"; done
	for fic in `ls -Adp $targetdir/osu*/dbn*/htdocs/files/_tmp/* 2>/dev/null | grep -v '/$'`; do rm "$fic"; done
	for fic in `ls -Adp $targetdir/osu*/dbn*/glpi_files/_tmp/* 2>/dev/null | grep -v '/$'`; do rm "$fic"; done
fi

# Disabled: We prefer --prune-empty-dirs
#if [ "x$instanceserver" != "x0" -a "x$instanceserver" != "x" ]; then
#	IFS=$(echo -en "\n\b")
#	echo "We are on a deployment server, so we try to delete empty dirs into backup directory under $backupdir/osu*"
#	find $backupdir/osu*/ -type d -empty -ls -delete > /var/log/find_delete_empty_dir.log 2>&1
#fi

# Create empty file it it does not exists
if [ ! -f  /var/log/apache2/other_vhosts_pid.log ]; then
	> /var/log/apache2/other_vhosts_pid.log
fi

# Create test files to test apparmor in web
[ -s /tmp/test.txt ] || echo "Test file" > /tmp/test.txt
chmod a+w /tmp/test.txt
echo "Content of tmp/test.txt file. This files should not be readable from web context except if owner of file is the same as the web server user." > /tmp/test.txt
chown www-data:www-data /tmp/test.txt
echo "Content of test.txt file. This files should not be readable from web context." > /test.txt 
chown root:root /test.txt

# TODO Try to change permission on this files to remove this ?
touch /var/log/phpmail.log
chown syslog:adm /var/log/phpmail.log
chmod a+rw /var/log/phpmail.log
touch /var/log/phpsendmail.log
chown syslog:adm /var/log/phpsendmail.log
chmod a+rw /var/log/phpsendmail.log

# Fix crontabs owners and permissions
echo "Fix crontabs owners and permissions"
echo find /var/spool/cron/crontabs/ -type f -name "osu*" -exec sh -c 'i="$1"; user=$(basename $i); chown "$user:$user" $i; chmod 600 $i' _ {} \;
find /var/spool/cron/crontabs/ -type f -name "osu*" -exec sh -c 'i="$1"; user=$(basename $i); chown "$user:$user" $i; chmod 600 $i' _ {} \;
