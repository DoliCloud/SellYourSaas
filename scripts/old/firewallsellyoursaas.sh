#!/bin/bash
### BEGIN INIT INFO
# Provides:          firewall
# Required-Start:    $local_fs $network $syslog
# Required-Stop:     $local_fs $network $syslog
# Default-Start:     2 3 4 5
# Default-Stop:      
# Short-Description: Launch firewall rules
# Description:       Launch firewall rules
### END INIT INFO
#
# Configuration Netfilter.
# Tout le fichier est parcouru jusqu'a ce qu'une regle accepte ou refuse.
# Si aucune regle trouvee, c'est la regle par defaut qui s'applique.
#
# Remarque 1:
# x.y.z.w/M correspond a une adresse si
# adresse & a.b.c.d = x.y.z.x
# avec a.b.c.d = adresse IP compose de M bits 1 a gauche.
# Exemple:
# 10.0.0.0/24 correspond a une adresse si
# adresse & 255.255.255.0 = 10.0.0.0
# Ce qui correspond a toutes les adresses 10.0.0.*
#
# Remarque 2:
# -i ethx est la carte qui recoit (pour INPUT, FORWARD, PREROUTING)
# -o ethx est la carte qui envoit (pour OUTPUT, FORWARD, POSTROUTING)
#
# Remarque 3:
# Pour forwarder, il peut etre necessaire de faire:
# echo 1 > /proc/sys/net/ipv4/ip_forward
# Pour eviter le spoofing:
# if [ -e /proc/sys/net/ipv4/conf/all/rp_filter ]
# then
#   for filtre in /proc/sys/net/ipv4/conf/*/rp_filter
#   do
#     echo 1 > $filtre
#   done
# fi
# Pour bloquer le ICMP:
# echo 1 > /proc/sys/net/ipv4/icmp_echo_ignore_all
# echo 1 > /proc/sys/net/ipv4/icmp_echo_ignore_broadcasts
#
# Remarque 4:
# En FTP actif, le client envoie la commande PORT numportdonnee, le server rappelle,
# depuis le port ftp-data, ce port.
# En FTP passif, le client envoie la commande PASV, le serveur retourne un port ephemeral
# et le client se connecte a ce port. La plage des ports ephemeral se trouve dans le
# fichier /proc/sys/net/ipv4/ip_local_port_range 
#
# Remarque 5:
# -j DNAT arrete la chaine PREROUTING
# -j SNAT arrete la chaine POSTROUTING
#

IP_SERVER=`ifconfig | sed -En 's/127.0.0.1//;s/.*inet (addr:)?(([0-9]*\.){3}[0-9]*).*/\2/p' | head -n 1`
IP_SERVER_V6=`ifconfig | grep -i global | sed -En 's/127.0.0.1//;s/.*inet6 (addr:)?\s?([^\s]+)/\2/p' | cut -d' ' -f1 | cut -d'/' -f1 `

IP_GITHUB=github.com
IPTABLES=/sbin/iptables
IP_WORMS=""		# x.y.z.a d.c.d.e

case $1 in
  start)
  # Verification de l'activite des interfaces reseau
  for INTERFACE in lo ens3; do
      ifconfig ${INTERFACE} >/dev/null 2>&1
      if [ "$?" -ne 0 ]; then
          echo "Interface ${INTERFACE} defaillante : Le firewall n'est pas active !"
          exit 1
      fi
  done


# Vide la table de config du firewall
#------------------------------------
${IPTABLES} -t filter -F
${IPTABLES} -t filter -X


# Definition d'une target pour loguer ce qu'on jette ou accept
#-------------------------------------------------------------
${IPTABLES} -N LOG_DROP
${IPTABLES} -N LOG_DROP_SUSPECT
${IPTABLES} -N LOG_DROP_SCAN
${IPTABLES} -N LOG_REJECT
${IPTABLES} -N LOG_ACCEPT

# Lignes avec --log-prefix vont en general dans /var/log/syslog
#${IPTABLES} -A LOG_DROP -j LOG --log-prefix '[IPTABLES DROP] : '
${IPTABLES} -A LOG_DROP -j DROP
#${IPTABLES} -A LOG_DROP_SUSPECT -j LOG --log-prefix '[IPTABLES DROP_SUSPECT] : '
${IPTABLES} -A LOG_DROP_SUSPECT -j DROP
${IPTABLES} -A LOG_DROP_SCAN -j LOG --log-prefix '[IPTABLES DROP_SCAN] : '
${IPTABLES} -A LOG_DROP_SCAN -j DROP
#${IPTABLES} -A LOG_REJECT -j LOG --log-prefix '[IPTABLES REJECT] : '
${IPTABLES} -A LOG_REJECT -j REJECT
#${IPTABLES} -A LOG_ACCEPT -j LOG --log-prefix '[IPTABLES ACCEPT] : '
${IPTABLES} -A LOG_ACCEPT -j ACCEPT


# Definit les regles par defaut
#------------------------------
${IPTABLES} -t filter -P INPUT     DROP
${IPTABLES} -t filter -P OUTPUT    DROP
${IPTABLES} -t filter -P FORWARD   DROP


# Blocage ip suspectes
#---------------------
# Si je met ca apres les regles ACCEPT, cela ne filtre rien !!!
if [ "x$IP_WORMS" != "x" ]
then
	for ip in $IP_WORMS; do
		${IPTABLES} -t filter -A INPUT -i ens3 -s $ip -j LOG_DROP_SUSPECT;
	done
fi

# Tout est permis sur la loopback
#--------------------------------
${IPTABLES} -t filter -A INPUT -i lo -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o lo -j ACCEPT


# Cas du ping
#------------
${IPTABLES} -t filter -A INPUT -i ens3 -p icmp -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -p icmp -j ACCEPT


# Ouverture de l'interieur vers exterieur
#----------------------------------------

# HTTP, HTTPS, SMTP, SMTP sendgrid, SMTPS, POP3, SSH, MYSQL (pour replication)
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 80 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 8080 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 443 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 25 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 2525 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 465 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 587 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 110 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 22 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 3306 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
# LDAP LDAPS
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 389 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 636 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
# IMAP, IMAPS
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 143 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 993 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
# Whois
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 43 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
# DCC
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 6277 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p udp --dport 6277 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
# RDATE
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p tcp --dport 37 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -p udp --dport 123 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT

# HTTP, HTTPS, SMTP, SMTP sendgrid, SMTPS, POP3, SSH, MYSQL (pour replication)
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 80 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 8080 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 443 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 25 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 2525 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 587 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 465 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 110 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 22 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 3306 -m state --state ESTABLISHED,RELATED -j ACCEPT
# LDAP LDAPS
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 389 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 636 -m state --state ESTABLISHED,RELATED -j ACCEPT
# IMAP, IMAPS
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 143 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 993 -m state --state ESTABLISHED,RELATED -j ACCEPT
# FTP, FTPS
#${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 21 -m state --state ESTABLISHED,RELATED -j ACCEPT
#${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 990 -m state --state ESTABLISHED,RELATED -j ACCEPT
# Whois
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 43 -m state --state ESTABLISHED,RELATED -j ACCEPT
# DCC (anti spam public services)
#${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 6277 -m state --state ESTABLISHED,RELATED -j ACCEPT
#${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p udp --sport 6277 -m state --state ESTABLISHED,RELATED -j ACCEPT
# RDATE
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p tcp --sport 37 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -d $IP_SERVER -p udp --sport 123 -m state --state ESTABLISHED,RELATED -j ACCEPT

# Ouverture a l'exterieur vers interieur (avec redirection sur serveur internet)
#-------------------------------------------------------------------------------
# SSH, HTTP, HTTPS, MySQL
${IPTABLES} -t filter -A INPUT -i ens3 -p tcp --dport 22 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -p tcp --dport 80 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -p tcp --dport 443 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -p tcp --dport 3306 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT

# SSH, HTTP, HTTPS, MySQL
${IPTABLES} -t filter -A OUTPUT -o ens3 -p tcp --sport 22 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -p tcp --sport 80 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -p tcp --sport 443 -m state --state ESTABLISHED,RELATED -j ACCEPT
${IPTABLES} -t filter -A OUTPUT -o ens3 -p tcp --sport 3306 -m state --state ESTABLISHED,RELATED -j ACCEPT


# Ouvertures vers autres serveurs
#--------------------------------
# Vers IP_BACKUP
if [ "x$IP_BACKUP" != "x" ]
then
	${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -d $IP_BACKUP -j ACCEPT
	${IPTABLES} -t filter -A INPUT -i ens3 -s $IP_BACKUP -d $IP_SERVER -j ACCEPT
fi
# Vers IP_GITHUB
${IPTABLES} -t filter -A OUTPUT -o ens3 -s $IP_SERVER -d $IP_GITHUB -j ACCEPT
${IPTABLES} -t filter -A INPUT -i ens3 -s $IP_GITHUB -d $IP_SERVER -j ACCEPT


# Rejet des acces exterieur a auth
#----------------------------------
# Rejet car les acces depuis exterieur a xinetd genere tentative sortie sur port 113 et cela evite timeout du drop
${IPTABLES} -t filter -A OUTPUT -o ens3 -p tcp --dport 113 -j LOG_REJECT


# Blocage Scan nmap
#------------------
# Bloquer les paquets XMAS (Scan special de nmap):
${IPTABLES} -t filter -A INPUT -p tcp --tcp-flags ALL ALL -j LOG_DROP_SCAN
# Bloquer les paquets NULL (scan special de nmap):
${IPTABLES} -t filter -A INPUT -p tcp --tcp-flags ALL NONE -j LOG_DROP_SCAN


# Drop les regles qui n'ont pas passees
#------------------------------------------------------------------
${IPTABLES} -t filter -A INPUT -j LOG_DROP
${IPTABLES} -t filter -A OUTPUT -j LOG_DROP
${IPTABLES} -t filter -A FORWARD -j LOG_DROP


echo "Firewall is running."
	;;

  stop)
    
    echo "Stopping firewall rules"

    # Vide/Efface tout
    #------------------------------------------------------------------

    ${IPTABLES} -t filter -P INPUT ACCEPT
    ${IPTABLES} -t filter -P OUTPUT ACCEPT
    ${IPTABLES} -t filter -P FORWARD ACCEPT

    ${IPTABLES} -t filter -F INPUT
    ${IPTABLES} -t filter -F OUTPUT
    ${IPTABLES} -t filter -F FORWARD
    ${IPTABLES} -t filter -F LOG_DROP
    ${IPTABLES} -t filter -F LOG_DROP_SUSPECT
    ${IPTABLES} -t filter -F LOG_DROP_SCAN
    ${IPTABLES} -t filter -F LOG_REJECT
    ${IPTABLES} -t filter -F LOG_ACCEPT

    /etc/init.d/fail2ban stop

    ${IPTABLES} -t filter -X

    exit 0
    ;;
  restart)
    $0 stop
    $0 start
    ;;
  status)
    ${IPTABLES} -L | grep anywhere 1>/dev/null 2>&1
    if [ "$?" == 0 ];
    then
        echo "Firewall is running : OK"
    else
        echo "Firewall is NOT running."
    fi
    ;;
  *)
    echo "Usage: $0 {start|stop|restart|status}"
    exit 2
esac
