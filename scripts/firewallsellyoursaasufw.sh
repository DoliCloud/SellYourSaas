#!/bin/bash

IPTABLES=iptables


case $1 in
  start)

ufw enable

# From local to external target - Out
ufw allow out 22/tcp
ufw allow out 80/tcp
ufw allow out 8080/tcp
ufw allow out 443/tcp
ufw allow out 3306/tcp
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
# DCC
ufw allow out 6227/tcp
ufw allow out 6227/udp
# Rdate
ufw allow out 37/tcp
ufw allow out 123/udp
# Whois
ufw allow out 43/tcp
# DNS
ufw allow out 53/tcp

# From external source to local - In
ufw allow in 22/tcp
ufw allow in 80/tcp
ufw allow in 8080/tcp
ufw allow in 443/tcp
# DNS
ufw allow in 53/tcp

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
    exit 1
esac
