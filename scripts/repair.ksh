#!/bin/bash
#--------------------------------------------------------#
# Script for Unix watchdog to dump trace memory/process when something is wrong
#--------------------------------------------------------#

# Param $1 = ENONUM = 12 = Out of memory
# Param $1 = -3 = Maximum average load (see watchdog man)

if [ "x$1" == "x" ]
then
        echo "Usage: repair.ksh param"
        exit 1
fi

# Check lock to be sure repair is executed only the first time the problem occurs
if [ -f /var/log/repair.lock ]
then
        echo "Repair locked by file /var/log/repair.lock"
        exit 2
fi
echo Lancement repair.ksh - $1 - $$ - $UID >/var/log/repair01_start$$.log

export databaseuser=`grep '^databaseuser=' /etc/sellyoursaas.conf | cut -d '=' -f 2`


# Report Mysql usage status
uptime > /var/log/repair02_uptime$$.log
cat /proc/meminfo > /var/log/repair02_meminfo$$.log
/usr/bin/mysqladmin -h localhost --verbose processlist > /var/log/repair02_mysqlprocesslist$$.log 2>&1
ps fauxww > /var/log/repair02_ps$$.log
iotop -P -b -n 2 > /var/log/repair02_iotop$$.log

/usr/sbin/apachectl fullstatus > /var/log/repair03_status$$.log 2>&1


# Report memory map
> /var/log/repair04_pmap$$.log
for pid in `ps faux | awk ' { if ($2 != "PID") print $2 } '`
do
        echo "pmap process $pid"

        echo pmap process $pid >> /var/log/repair04_pmap$$.log
        echo ----------------- >> /var/log/repair04_pmap$$.log
        pmap -d $pid >> /var/log/repair04_pmap$$.log
        echo >> /var/log/repair04_pmap$$.log

done;
# Pour lire:
# grep '\(^[0-9]*:\|mapped\)' pmap.log


> /var/log/repair05_stopapache$$.log
for fic in `pidof apache2`
do
        echo Kill $fic
#       kill $fic >> /var/log/repair05_stopapache$$.log
done
#/etc/init.d/apache2 stop > /var/log/repair05_stopapache$$.log 2>&1

#/etc/init.d/mysqld stop > /var/log/repair05_stop$$.log


# Report usage status
uptime > /var/log/repair06_uptime$$.log
cat /proc/meminfo > /var/log/repair06_meminfo$$.log
#/usr/bin/mysqladmin -h localhost --verbose processlist > /var/log/repair06_mysqlprocesslist$$.log 2>&1


#/etc/init.d/apache2 start >/var/log/repair07_start$$.log 2>&1


# Report usage status
uptime > /var/log/repair09_uptime$$.log
cat /proc/meminfo > /var/log/repair09_meminfo$$.log
/usr/bin/mysqladmin -h localhost --verbose processlist > /var/log/repair09_mysqlprocesslist$$.log 2>&1

#cp -p /var/log/mod_evasive/* /var/log/mod_evasive/archives 2>/dev/null
#rm -f /var/log/mod_evasive/* 2>/dev/null

touch /var/log/repair.lock

# Return 0 to avoid reboot
if [ "x$1" == "x12" ]
then
        exit 0
else
        exit $1
        #exit 0
fi
