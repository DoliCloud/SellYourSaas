#!/bin/bash

sudo ufw default deny incoming
sudo ufw default deny outgoing

sudo ufw allow icmp

sudo ufw allow out 22/tcp
sudo ufw allow out 80/tcp
sudo ufw allow out 8080/tcp
sudo ufw allow out 443/tcp
sudo ufw allow out 3306/tcp
sudo ufw allow out 25/tcp
sudo ufw allow out 2525/tcp
sudo ufw allow out 465/tcp
sudo ufw allow out 587/tcp
sudo ufw allow out 110/tcp
# LDAP LDAPS
sudo ufw allow out 389/tcp
sudo ufw allow out 636/tcp
# IMAP
sudo ufw allow out 143/tcp
sudo ufw allow out 993/tcp
# DCC
sudo ufw allow out 6227/tcp
sudo ufw allow out 6227/udp
# Rdate
sudo ufw allow out 37/tcp
sudo ufw allow out 123/udp
# Whois
sudo ufw allow out 43/tcp

sudo ufw allow in 22/tcp
sudo ufw allow in 80/tcp
sudo ufw allow in 8080/tcp
sudo ufw allow in 443/tcp

sudo ufw reload
