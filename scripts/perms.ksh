#!/bin/bash
#--------------------------------------------------------#

cd /home

echo "Positionnement droit et permissions"

#echo "Remplacement user apache par www-data"
#find . -user apache -exec chown www-data {} \;

#echo "Remplacement group apache par www-data"
#find . -group apache -exec chgrp www-data {} \;


# Owner root
chown root.adm /home/admin/logs/

# dolibarr.mysaasdomainname.com
chmod g+ws /home/admin/wwwroot/dolibarr_documents/
chown admin.www-data /home/admin/wwwroot/dolibarr_documents
chown -R admin.www-data /home/admin/wwwroot/dolibarr_documents
chmod -R ug+w /home/admin/wwwroot/dolibarr_documents

chown -R admin.admin /home/admin/wwwroot/dolibarr
chmod -R a-w /home/admin/wwwroot/dolibarr
chmod -R u+w /home/admin/wwwroot/dolibarr/.git
chmod -R a-w /home/admin/wwwroot/dolibarr_nltechno 2>/dev/null
chmod -R u+w /home/admin/wwwroot/dolibarr_nltechno/.git 2>/dev/null
chmod -R a-w /home/admin/wwwroot/dolibarr_sellyoursaas
chmod -R u+w /home/admin/wwwroot/dolibarr_sellyoursaas/.git
chown www-data.admin /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php
chmod o-rwx /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php


echo "Nettoyage fichier logs error"
for fic in `ls -art /home/jail/home/osu*/dbn*/*_error.log`; do > $fic; done
echo "Nettoyage fichier logs dolibarr"
for fic in `ls -art /home/jail/home/osu*/dbn*/documents/dolibarr*.log`; do > $fic; done

echo "Nettoyage vieux fichiers tmp"
find /home/admin/wwwroot/dolibarr_documents/sellyoursaas/temp -maxdepth 1 -name "*.tmp" -type f -mtime +10 -exec rm {} \;

echo "Nettoyage vieux fichiers log"
find /home/admin/wwwroot/dolibarr_documents -maxdepth 1 -name "dolibarr*.log*" -type f -mtime +10 -exec rm {} \;
