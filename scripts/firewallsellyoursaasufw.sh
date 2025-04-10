#!/bin/bash
# ---------------------------------
# firewallsellyoursaasufw.sh
# ---------------------------------


export RED='\033[0;31m'
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[0;33m'

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

webserver=`grep '^webserver=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

allowed_hosts=`grep '^allowed_hosts=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$allowed_hosts" == "x" && "x$instanceserver" != "x" && "x$instanceserver" != "x0" ]]; then
	echo Parameter allowed_host not found or empty. This is not possible when the server is an instanceserver.
	exit 4
fi

ipserverdeployment=`grep '^ipserverdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$ipserverdeployment" == "x" && "x$instanceserver" != "x" && "x$instanceserver" != "x0" ]]; then
	echo Parameter ipserverdeployment not found or empty. This is not possible when the server is an instanceserver.
	exit 4
fi

addloguidonufwblock=`grep '^addloguidonufwblock=' /etc/sellyoursaas.conf | cut -d '=' -f 2`


if [[ -s /etc/ssh/sshd_config.d/sellyoursaas.conf ]]; then
	port_ssh=`grep '^Port ' /etc/ssh/sshd_config.d/sellyoursaas.conf | cut -d ' ' -f 2`
fi
if [[ "x$port_ssh" == "x" ]]; then
	export port_ssh=22
fi


case $1 in
  start)


# From local to external target - Out
#------------------------------------

# SSH
ufw allow out $port_ssh/tcp	comment "SSH servers"
# HTTP
ufw allow out 80/tcp comment "HTTP servers"
ufw allow out 8080/tcp comment "HTTP alternative servers"
ufw allow out 443/tcp comment "HTTP servers (crypted)"
# Mysql/Mariadb
ufw allow out 3306/tcp comment "Mysql servers"
# Send Mail
ufw allow out log 25/tcp comment "SMTP servers"
ufw allow out log 2525/tcp comment "SMTP servers"
ufw allow out log 465/tcp comment "SMTP servers"
ufw allow out log 587/tcp comment "SMTP servers"
#ufw allow out log 1025/tcp
# LDAP LDAPS
ufw allow out 389/tcp comment "LDAP servers"
ufw allow out 636/tcp comment "LDAP servers (crypted)"
# POP
ufw allow out 110/tcp comment "POP servers"
# IMAP
ufw allow out 143/tcp comment "IMAP servers"
ufw allow out 993/tcp comment "IMAP servers (crypted)"
# Printers (ESC/POS port)
ufw allow out 9100/tcp comment "ESC-POST printers"
# DCC (anti spam public services)
#ufw allow out 6277/tcp
#ufw allow out 6277/udpvi
# Rdate / NTP
#ufw allow out 37/tcp deprecated
ufw allow out 123/udp comment "rdate/ntp servers"
# Whois
ufw allow out 43/tcp comment "Whois servers"
# DNS
ufw allow out 53/tcp comment "DNS servers"
ufw allow out 53/udp comment "DNS servers"
# NFS (only 2049/tcp is required for NFS)
ufw allow out 2049/tcp comment "NFS servers"
# DHCP
# TODO Allow DHCP client access ?
# ufw allow out from $ipserver port 68 to any port 67 proto udp

# From external source to local - In
#-----------------------------------


# SSH
export atleastoneipfound=0

if [[ "x$masterserver" == "x2" || "x$instanceserver" == "x2"  || "x$webserver" == "x2" ]]; then
	# If value is 2, we want a restriction per user found into a file (Value = 1 means access to everybody)
	for fic in `ls /etc/sellyoursaas.d/*-allowed-ip.conf /etc/sellyoursaas.d/*-allowed-ip-ssh.conf 2>/dev/null`
	do
		for line in `grep -v '^#' "$fic" | sed 's/\s*Require ip\s*//i' | grep '.*[\.:].*[\.:].*[\.:].*'`
		do
			export atleastoneipfound=1
		done
	done
	iptables -n --line-numbers -L OUTPUT | grep 'SELLYOURSAAS' > /dev/null
	if [[ "x$atleastoneipfound" == "x1" ]]; then
		echo Disallow existing In access for SSH for specific ip
		for num in `ufw status numbered |(grep ' 22/tcp'|grep -v 'Anywhere'|awk -F"[][]" '{print $2}') | sort -r`
		do
			echo delete rule number $num
			ufw --force delete $num
		done
		for num in `ufw status numbered |(grep ' $port_ssh/tcp'|grep -v 'Anywhere'|awk -F"[][]" '{print $2}') | sort -r`
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
			ufw allow from $line to any port $port_ssh proto tcp
		done
	done
	
	# Allow SSH to myself (for example this is required with Scaleway)
	if [[ "x$ipserverdeployment" != "x" && "x$instanceserver" != "x" && "x$instanceserver" != "x0" ]]; then
		echo Allow SSH to the restricted ip of deployment server $ipserverdeployment
		ufw allow from $ipserverdeployment to any port $port_ssh proto tcp
	fi
fi

if [[ "x$atleastoneipfound" == "x1" ]]; then
	echo Disallow In access for SSH to everybody
	ufw delete allow in $port_ssh/tcp
else 
	echo Allow In access with SSH to everybody
	ufw allow in $port_ssh/tcp comment "SSH Server"
fi

# MySQL
export atleastoneipfound=0

if [[ "x$masterserver" == "x2" || "x$instanceserver" == "x2" || "x$webserver" == "x2" ]]; then
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
			# Allow MySQL to the restricted ip $line
			echo Allow MySQL to the restricted ip $line
			ufw allow from $line to any port 3306 proto tcp
		done
	done
	
	# Allow MySQL to myself (for example this is required with Scaleway)
	if [[ "x$ipserverdeployment" != "x" && "x$instanceserver" != "x" && "x$instanceserver" != "x0" ]]; then
		echo Allow MySQL to the restricted ip $ipserverdeployment
		ufw allow from $ipserverdeployment to any port 3306 proto tcp
	fi
fi

if [[ "x$atleastoneipfound" == "x1" ]]; then
	echo Disallow In access for Mysql to everybody
	ufw delete allow in 3306/tcp
else 
	echo Allow In access with Mysql to everybody
	ufw allow in 3306/tcp comment "Mysql in access for everybody"
fi


echo "Allow In access to common port (http and dns) to everybody"
# HTTP
ufw allow in 80/tcp comment "HTTP Server"
ufw allow in 443/tcp comment "HTTP Server (crypted)"
# DNS
ufw allow in 53/tcp comment "DNS Server tcp"
ufw allow in 53/udp comment "DNS Server udp"
ufw allow in 953/tcp comment "DNS Server tcp (crypted)"
ufw allow in 953/udp comment "DNS Server upd (crypted)"

# To see master NFS server
if [[ "x$masterserver" != "x0" ]]; then
	echo Enable NFS entry to allow access to master from instance servers
	#ufw allow in 111/tcp
	#ufw allow in 111/udp
	ufw allow in 2049/tcp comment "NFS Server access from deployment servers"
	#ufw allow in 2049/udp
else
	ufw delete allow in 111/tcp
	ufw delete allow in 111/udp
	ufw delete allow in 2049/tcp
	ufw delete allow in 2049/udp
fi

# To accept remote action on port SSH, MySql and 8080
if [[ "x$allowed_hosts" != "x" ]]; then
	echo Process allowed_host=$allowed_hosts to accept remote call on 8080
  	#ufw delete allow in 8080/tcp # disabled to avoid deletion of rules for othe ports
    #ufw delete allow in 3306/tcp # 
	ufw delete allow in 8080/tcp
  
	for ipsrc in `echo $allowed_hosts | tr "," "\n"`
	do
		echo Process ip $ipsrc - Allow remote actions requests on port $port_ssh, 3306 and 8080 from this ip
		ufw allow from $ipsrc to any port $port_ssh proto tcp
		ufw allow from $ipsrc to any port 3306 proto tcp
		ufw allow from $ipsrc to any port 8080 proto tcp
	done
else
	echo No entry allowed_host found in /etc/sellyoursaas.conf, so no remote action can be requested to this server.
  	#ufw delete allow in 8080/tcp # disabled to avoid deletion of rules for othe ports
    #ufw delete allow in 3306/tcp # 	
    ufw delete allow in 8080/tcp
fi

# At end, after all allow
ufw default deny incoming
ufw default deny outgoing

ufw enable
ufw reload


# Note: to enabled including --log-uid, we must insert rule directly in iptables
# When you have log with UID inside, you can try to find process for this UID with
# ss -e -H -p -t state all '( sport = $smtpportcaller and dport = $smtpportcalled )  |  grep uid:XXX 
# ss -e -H -p -t state all '( sport = $smtpportcaller and dport = $smtpportcalled ) dst [$smtpipcalled]  |  grep uid:XXX 
# We add rule only if not already found
iptables -n --line-numbers -L OUTPUT | grep 'ALLOW SELLYOURSAAS' > /dev/null
if [ "x$?" == "x1" ]; then
	echo Add iptables rule for log uid
	iptables -I OUTPUT 1 -p tcp -m multiport --dports 25,2525,465,587 -m state --state NEW -j LOG --log-uid --log-prefix  "[UFW ALLOW SELLYOURSAAS] "
fi
ip6tables -n --line-numbers -L OUTPUT | grep 'ALLOW SELLYOURSAAS' > /dev/null
if [ "x$?" == "x1" ]; then
	echo Add ip6tables rule for log uid
	ip6tables -I OUTPUT 1 -p tcp -m multiport --dports 25,2525,465,587 -m state --state NEW -j LOG --log-uid --log-prefix  "[UFW ALLOW SELLYOURSAAS] "
fi

# Note: To enable log with uid on blocking access, you can enable this.
# When you have log with UID inside, you can try to find process for this UID with
# ss -e -H -p -t state all '( sport = $smtpportcaller and dport = $smtpportcalled )  |  grep uid:XXX 
# ss -e -H -p -t state all '( sport = $smtpportcaller and dport = $smtpportcalled ) dst [$smtpipcalled]  |  grep uid:XXX
if [ "x$addloguidonufwblock" == "x1" ]; then
	# TODO Disable log instructions added by ufw to avoid duplicate logs
	iptables -n --line-numbers -L | grep 'BLOCK SELLYOURSAAS' > /dev/null
	if [ "x$?" == "x1" ]; then
		echo Add iptables rule for log uid
		iptables -A ufw-after-logging-input -j LOG --log-uid --log-prefix "[UFW BLOCK SELLYOURSAAS] " -m limit --limit 3/min --limit-burst 10
		iptables -A ufw-after-logging-output -j LOG --log-uid --log-prefix "[UFW BLOCK SELLYOURSAAS] " -m limit --limit 3/min --limit-burst 10
		iptables -A ufw-after-logging-forward -j LOG --log-uid --log-prefix "[UFW BLOCK SELLYOURSAAS] " -m limit --limit 3/min --limit-burst 10
		iptables -A ufw-logging-deny -j LOG --log-uid --log-prefix "[UFW BLOCK SELLYOURSAAS] " -m limit --limit 3/min --limit-burst 10
	fi
	ip6tables -n --line-numbers -L | grep 'BLOCK SELLYOURSAAS' > /dev/null
	if [ "x$?" == "x1" ]; then
		echo Add ip6tables rule for log uid
		ip6tables -A ufw6-after-logging-input -j LOG --log-uid --log-prefix "[UFW BLOCK SELLYOURSAAS] " -m limit --limit 3/min --limit-burst 10
		ip6tables -A ufw6-after-logging-output -j LOG --log-uid --log-prefix "[UFW BLOCK SELLYOURSAAS] " -m limit --limit 3/min --limit-burst 10
		ip6tables -A ufw6-after-logging-forward -j LOG --log-uid --log-prefix "[UFW BLOCK SELLYOURSAAS] " -m limit --limit 3/min --limit-burst 10
		ip6tables -A ufw6-logging-deny -j LOG --log-uid --log-prefix "[UFW BLOCK SELLYOURSAAS] " -m limit --limit 3/min --limit-burst 10
	fi
fi

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
