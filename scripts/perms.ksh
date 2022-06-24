#!/bin/bash
#--------------------------------------------------------#
# Script to force permission on expected default values
#--------------------------------------------------------#

if [ "$(id -u)" != "0" ]; then
	echo "This script must be run as root" 1>&2
	exit 100
fi

# possibility to change the directory of instances are stored
export targetdir=`grep '^targetdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$targetdir" == "x" ]]; then
	export targetdir="/home/jail/home"
fi

echo "Search to know if we are a master server in /etc/sellyoursaas.conf"
masterserver=`grep '^masterserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
instanceserver=`grep '^instanceserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

# Go into a safe dir
cd /tmp


#echo "Remplacement user apache par www-data"
#find . -user apache -exec chown www-data {} \;

#echo "Remplacement group apache par www-data"
#find . -group apache -exec chgrp www-data {} \;

# Owner root on logs and backups dir
echo "Set owner and permission on logs and backup directory"
chown root.adm /home/admin/logs/
[ -d /home/admin/logs ] || mkdir /home/admin/logs;
[ -d /mnt/diskbackup ] || mkdir /mnt/diskbackup;
[ -d /home/admin/backup ] || mkdir /home/admin/backup;
[ -d /home/admin/backup/conf ] || mkdir /home/admin/backup/conf;
[ -d /home/admin/backup/mysql ] || mkdir /home/admin/backup/mysql;
[ -d /home/admin/wwwroot ] || mkdir /home/admin/wwwroot;
chown root.adm /home/admin/logs; chmod 770 /home/admin/logs; 
chown admin.admin /mnt/diskbackup; 
chown admin.admin /home/admin/backup; chown admin.admin /home/admin/backup/conf; chown admin.admin /home/admin/backup/mysql; 
chown admin.admin /home/admin/wwwroot


echo "Set owner and permission on /home/admin/wwwroot/dolibarr_documents/ (except sellyoursaas)"
chmod g+ws /home/admin/wwwroot/dolibarr_documents/
chown admin.www-data /home/admin/wwwroot/dolibarr_documents
for fic in `ls /home/admin/wwwroot/dolibarr_documents | grep -v sellyoursaas`; 
do 
	chown -R admin.www-data /home/admin/wwwroot/dolibarr_documents/$fic
	chmod -R ug+w /home/admin/wwwroot/dolibarr_documents/$fic
done
if [ -d /home/admin/wwwroot/dolibarr_documents/users/temp/odtaspdf ]; then
	chown www-data.www-data /home/admin/wwwroot/dolibarr_documents/users/temp/odtaspdf
fi

if [[ "x$masterserver" == "x1" ]]; then
	echo We are on a master server, Set owner and permission on /home/admin/wwwroot/dolibarr_documents/sellyoursaas
	chown -R admin.www-data /home/admin/wwwroot/dolibarr_documents/sellyoursaas
	chmod -R ug+rw /home/admin/wwwroot/dolibarr_documents/sellyoursaas/git
	chmod -R ug+rw /home/admin/wwwroot/dolibarr_documents/sellyoursaas/packages
	chmod -R ug+rw /home/admin/wwwroot/dolibarr_documents/sellyoursaas/temp
	chmod -R ug+rw /home/admin/wwwroot/dolibarr_documents/sellyoursaas/crt
fi

echo Set owner and permission on /etc/sellyoursaas.conf
if [ ! -s /etc/sellyoursaas.conf ]; then
	echo > /etc/sellyoursaas.conf
fi
chown -R root.admin /etc/sellyoursaas.conf
chmod g-wx /etc/sellyoursaas.conf
chmod o-rwx /etc/sellyoursaas.conf

echo Set owner and permission on /etc/sellyoursaas-pubic.conf
if [ ! -s /etc/sellyoursaas-public.conf ]; then
	echo > /etc/sellyoursaas-public.conf
fi
chown -R root.admin /etc/sellyoursaas-public.conf
chmod a+r /etc/sellyoursaas-public.conf
chmod a-wx /etc/sellyoursaas-public.conf

echo Set owner and permission on /home/admin/wwwroot/dolibarr
chown -R admin.admin /home/admin/wwwroot/dolibarr
chmod -R a-w /home/admin/wwwroot/dolibarr
chmod -R u+w /home/admin/wwwroot/dolibarr/.git

echo Set owner and permission on /home/admin/wwwroot/dolibarr_nltechno
chmod -R a-w /home/admin/wwwroot/dolibarr_nltechno 2>/dev/null
chmod -R u+w /home/admin/wwwroot/dolibarr_nltechno/.git 2>/dev/null

echo Set owner and permission on /home/admin/wwwroot/dolibarr_sellyoursaas
chmod -R a-w /home/admin/wwwroot/dolibarr_sellyoursaas
chmod -R u+w /home/admin/wwwroot/dolibarr_sellyoursaas/.git

echo Set owner and permission on /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php
if [ -f /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php ]; then
	chown www-data.admin /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php
	chmod o-rwx /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php
fi

echo Set owner and permission on SSL certificates /etc/apache2/*.key
for fic in `ls /etc/apache2/ | grep '.key$'`; 
do 
	chown root.www-data /etc/apache2/$fic
	chmod ug+r /etc/apache2/$fic
	chmod o-rwx /etc/apache2/$fic
done

if [[ "x$instanceserver" != "x0" ]]; then
	IFS=$(echo -en "\n\b")
	echo We are on a deployment server, so we clean log files 
	echo "Clean web server _error logs"
	for fic in `ls -Adp $targetdir/osu*/dbn*/*_error.log 2>/dev/null | grep -v '/$'`; do > "$fic"; done
	echo "Clean applicative log files"
	for fic in `ls -Adp $targetdir/osu*/dbn*/documents/dolibarr*.log 2>/dev/null | grep -v '/$'`; do > "$fic"; done
	for fic in `ls -Adp $targetdir/osu*/dbn*/htdocs/files/_log/*.log 2>/dev/null | grep -v '/$'`; do > "$fic"; done
	for fic in `ls -Adp $targetdir/osu*/dbn*/htdocs/files/_tmp/* 2>/dev/null | grep -v '/$'`; do rm "$fic"; done
	for fic in `ls -Adp $targetdir/osu*/dbn*/glpi_files/_tmp/* 2>/dev/null | grep -v '/$'`; do rm "$fic"; done
fi

if [[ "x$masterserver" == "x1" ]]; then
	echo We are on a master server, so we clean old temp files 
	find /home/admin/wwwroot/dolibarr_documents/sellyoursaas/temp -maxdepth 1 -name "*.tmp" -type f -mtime +2 -exec rm {} \;
fi

echo "Nettoyage vieux fichiers log"
echo find /home/admin/wwwroot/dolibarr_documents -maxdepth 1 -name "dolibarr*.log*" -type f -mtime +2 -exec rm {} \;
find /home/admin/wwwroot/dolibarr_documents -maxdepth 1 -name "dolibarr*.log*" -type f -mtime +2 -exec rm {} \;

echo "Nettoyage vieux /tmp"
echo find /tmp -mtime +30 -name 'phpsendmail*.*' -exec rm {} \;
find /tmp -mtime +30 -name 'phpsendmail*.*' -exec rm {} \;

echo "Check files for antispam system and create them if not found"
[ -d /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam ] || mkdir -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam;
[ -s /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistmail ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas/spam/blacklistmail /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/;
[ -s /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistip ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas/spam/blacklistip /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/;
[ -s /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistfrom ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas/spam/blacklistfrom /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/;
[ -s /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistcontent ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas/spam/blacklistcontent /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/;
chmod a+rwx /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam; chmod a+rw /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/*;
chown -R admin.www-data /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local;

[ -d /tmp/spam ] || mkdir /tmp/spam;
[ -s /tmp/spam/blacklistmail ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistmail /tmp/spam/;
[ -s /tmp/spam/blacklistip ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistip /tmp/spam/;
[ -s /tmp/spam/blacklistfrom ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistfrom /tmp/spam/;
[ -s /tmp/spam/blacklistcontent ] || cp -p /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam/blacklistcontent /tmp/spam/;
chmod a+rwx /tmp/spam; chmod a+rw /tmp/spam/*

