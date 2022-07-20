#!/bin/bash

IPTABLES=iptables

masterserver=`grep '^masterserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$masterserver" == "x" ]]; then
	echo Failed to get masterserver parameter.
	exit 1
fi

dnsserver=`grep '^dnsserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$dnsserver" == "x" ]]; then
	echo Failed to get dnsserver parameter.
	exit 2
fi

instanceserver=`grep '^instanceserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$instanceserver" == "x" ]]; then
	echo Failed to get instanceserver parameter.
	exit 3
fi

allowed_hosts=`grep '^allowed_hosts=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$allowed_hosts" == "x" && "x$instanceserver" != "x" && "x$instanceserver" != "x0" ]]; then
	echo Parameter allowed_host not found or empty. This is not possible when the server is an instanceserver.
	exit 4
fi


case $1 in
  start)


# From local to external target - Out
#------------------------------------

# SSH
ufw allow out 22/tcp
# HTTP
ufw allow out 80/tcp
ufw allow out 8080/tcp
ufw allow out 443/tcp
# Mysql/Mariadb
ufw allow out 3306/tcp
# Send Mail
ufw allow out log 25/tcp
ufw allow out log 2525/tcp
ufw allow out log 465/tcp
ufw allow out log 587/tcp
# LDAP LDAPS
ufw allow out 389/tcp
ufw allow out 636/tcp
# POP
ufw allow out 110/tcp
# IMAP
ufw allow out 143/tcp
ufw allow out 993/tcp
# DCC (anti spam public services)
#ufw allow out 6277/tcp
#ufw allow out 6277/udpvi /etc/
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
#Required ?
#ufw allow out 111/tcp
#ufw allow out 111/udp


# From external source to local - In
#-----------------------------------


# SSH
export atleastoneipfound=0

if [[ "x$masterserver" == "x2" || "x$instanceserver" == "x2" ]]; then
	# If value is 2, we want a restriction per user found into a file (Value = 1 means access to everybody)
	for fic in `ls /etc/sellyoursaas.d/*-allowed-ip.conf /etc/sellyoursaas.d/*-allowed-ip-ssh.conf 2>/dev/null`
	do
		for line in `grep -v '^#' "$fic" | sed 's/\s*Require ip\s*//i' | grep '.*[\.:].*[\.:].*[\.:].*'`
		do
			export atleastoneipfound=1
		done
	done

	if [[ "x$atleastoneipfound" == "x1" ]]; then
		echo Disallow existing In access for SSH for specific ip
		for num in `ufw status numbered |(grep ' 22/tcp'|grep -v 'Anywhere'|awk -F"[][]" '{print $2}') | sort -r`
		do
			echo delete rule number $num
			ufw --force delete $num
		done
	fi

	for fic in `ls /etc/sellyoursaas.d/*-allowed-ip.conf /etc/sellyoursaas.d/*-allowed-ip-ssh.conf 2>/dev/null`
	do
		echo Process file $fic
		for line in `grep -v '^#' "$fic" | sed 's/\s*Require ip\s*//i' | grep '.*[\.:].*[\.:].*[\.:].*'`
		do
			# Allow SSH to the restricted ip $line
			echo Allow SSH to the restricted ip $line
			ufw allow from $line to any port 22 proto tcp
		done
	done
fi

if [[ "x$atleastoneipfound" == "x1" ]]; then
	echo Disallow In access for SSH to everybody
	ufw delete allow in 22/tcp
else 
	echo Allow In access with SSH to everybody
	ufw allow in 22/tcp
fi

# MYSQL
export atleastoneipfound=0

if [[ "x$masterserver" == "x2" || "x$instanceserver" == "x2" ]]; then
	# If value is 2, we want a restriction per user found into a file (Value = 1 means access to everybody)
	for fic in `ls /etc/sellyoursaas.d/*-allowed-ip.conf /etc/sellyoursaas.d/*-allowed-ip-mysql.conf 2>/dev/null`
	do
		for line in `grep -v '^#' "$fic" | sed 's/\s*Require ip\s*//i' | grep '.*[\.:].*[\.:].*[\.:].*'`
		do
			export atleastoneipfound=1
		done
	done

	if [[ "x$atleastoneipfound" == "x1" ]]; then
		echo Disallow existing In access for Mysql for specific ip
		for num in `ufw status numbered |(grep ' 3306/tcp'|grep -v 'Anywhere'|awk -F"[][]" '{print $2}') | sort -r`
		do
			echo delete rule number $num
			ufw --force delete $num
		done
	fi

	# MySQL
	for fic in `ls /etc/sellyoursaas.d/*-allowed-ip.conf /etc/sellyoursaas.d/*-allowed-ip-mysql.conf 2>/dev/null`
	do
		echo Process file $fic
		for line in `grep -v '^#' "$fic" | sed 's/\s*Require ip\s*//i' | grep '.*[\.:].*[\.:].*[\.:].*'`
		do
			# Allow Mysql to the restricted ip $line
			echo Allow Mysql to the restricted ip $line
			ufw allow from $line to any port 3306 proto tcp
		done
	done
fi

if [[ "x$atleastoneipfound" == "x1" ]]; then
	echo Disallow In access for Mysql to everybody
	ufw delete allow in 3306/tcp
else 
	echo Allow In access with Mysql to everybody
	ufw allow in 3306/tcp
fi

# Seems not required
#ufw allow from 127.0.0.0/8 to any port 22 proto tcp
#ufw allow from 192.168.0.0/16 to any port 22 proto tcp
#ufw allow from 127.0.0.0/8 to any port 3306 proto tcp
#ufw allow from 192.168.0.0/16 to any port 3306 proto tcp

echo "Allow In access to common port (http and dns) to everybody"
# HTTP
ufw allow in 80/tcp
ufw allow in 443/tcp
# DNS
ufw allow in 53/tcp
ufw allow in 53/udp
ufw allow in 953/tcp
ufw allow in 953/udp

# To see master NFS server
if [[ "x$masterserver" != "x0" ]]; then
	echo Enable NFS entry from instance servers
	ufw allow in 111/tcp
	ufw allow in 111/udp
	ufw allow in 2049/tcp
	ufw allow in 2049/udp
else
	ufw delete allow in 111/tcp
	ufw delete allow in 111/udp
	ufw delete allow in 2049/tcp
	ufw delete allow in 2049/udp
fi

# To accept remote action on port 8080
if [[ "x$allowed_hosts" != "x" ]]; then
	echo Process allowed_host=$allowed_hosts to accept remote call on 22, 3306 and 8080
	ufw delete allow in 8080/tcp
	for ipsrc in `echo $allowed_hosts | tr "," "\n"`
	do
		echo Process ip $ipsrc - Allow remote actions requests on port 22, 3306 and 8080 from this ip
		ufw allow from $ipsrc to any port 22 proto tcp
		ufw allow from $ipsrc to any port 3306 proto tcp
		ufw allow from $ipsrc to any port 8080 proto tcp
	done
else
	echo No entry allowed_host found in /etc/sellyoursaas.conf, so no remote action can be requested to this server.
	ufw delete allow in 8080/tcp
fi

ufw default deny incoming
ufw default deny outgoing

ufw enable
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
