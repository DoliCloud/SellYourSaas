#!/bin/bash

IPTABLES=iptables

masterserver=`grep '^masterserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$masterserver" == "x" ]]; then
	echo Failed to get masterserver parameter
	exit 1
fi

dnsserver=`grep '^dnsserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$dnsserver" == "x" ]]; then
	echo Failed to get dnsserver parameter 
	exit 2
fi

instanceserver=`grep '^instanceserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$instanceserver" == "x" ]]; then
	echo Failed to get instanceserver parameter
	exit 3
fi




case $1 in
  start)

ufw enable

# From local to external target - Out
# SSH
ufw allow out 22/tcp
# HTTP
ufw allow out 80/tcp
ufw allow out 8080/tcp
ufw allow out 443/tcp
# Mysql/Mariadb
ufw allow out 3306/tcp
# Mail
ufw allow out 25/tcp
ufw allow out 2525/tcp
ufw allow out 465/tcp
ufw allow out 587/tcp
ufw allow out 110/tcp
# LDAP LDAPS
ufw allow out 389/tcp
ufw allow out 636/tcp
# IMAP
ufw allow out 143/tcp
ufw allow out 993/tcp
# DCC (anti spam public services)
#ufw allow out 6277/tcp
#ufw allow out 6277/udp
# Rdate
ufw allow out 37/tcp
ufw allow out 123/udp
# Whois
ufw allow out 43/tcp
# DNS
ufw allow out 53/tcp
ufw allow out 53/udp
# NFS
ufw allow out 2049/tcp
ufw allow out 2049/udp

# From external source to local - In
# SSH
atleastoneipfound=0
for fic in `ls /etc/sellyoursaas-allowed-ip.d/*.conf`
do
	echo Process file $fic
	cat "$fic" | grep -v '^#' | sed 's/Require ip//i' | grep '.*\..*\..*\..*' | grep 'while read line
	do
		# Allow ssh to the ip
		ufw allow from $line to any port 22 proto tcp

		atleastoneipfound=1	
	done
done
if [ "x$atleastoneipfound" == "x1"]; then
	ufw delete allow in 22/tcp
else 
	ufw allow in 22/tcp
fi


# HTTP
ufw allow in 80/tcp
ufw allow in 8080/tcp
ufw allow in 443/tcp
# DNS
ufw allow in 53/tcp
ufw allow in 53/udp
# Mysql/Mariadb
ufw allow in 3306/tcp

# For master server
ufw allow in 111/udp
ufw allow in 111/tcp
ufw allow in 2049/tcp
ufw allow in 2049/udp



ufw default deny incoming
ufw default deny outgoing

ufw reload

$0 status
	;;

  stop)
    
    echo "Stopping firewall rules"

ufw disable 	

    exit 0
    ;;
  restart)
    $0 stop
    $0 start
    ;;
  status)
    ${IPTABLES} -L | grep anywhere | grep ESTABLISHED 1>/dev/null 2>&1
    if [ "$?" == 0 ];
    then
        echo "Firewall is running : OK"
    else
        echo "Firewall is NOT running."
    fi
    ;;
  *)
    echo "Usage: $0 {start|stop|restart|status}"
    exit 4
esac
