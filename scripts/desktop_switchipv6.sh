#!/bin/bash
#----------------------------------------------------------------
# This script allows you to enable/disable IP v6 on your desktop
#----------------------------------------------------------------

if [ "x$1" = "xdisable" ] ;
then
echo Disable IPV6
sysctl -w net.ipv6.conf.all.disable_ipv6=1
sysctl -w net.ipv6.conf.all.autoconf=0
sysctl -w net.ipv6.conf.default.disable_ipv6=1
sysctl -w net.ipv6.conf.default.autoconf=0
ifconfig
more /proc/net/if_inet6
fi

if [ "x$1" = "xenable" ] ;
then
echo Enable IPV6
sysctl -w net.ipv6.conf.default.autoconf=1
sysctl -w net.ipv6.conf.default.disable_ipv6=0
sysctl -w net.ipv6.conf.all.autoconf=1
sysctl -w net.ipv6.conf.all.disable_ipv6=0
ifconfig
more /proc/net/if_inet6
fi

echo You can try perf with
echo Orange FRANCE: ./speedtest -s 24215 -vvv
echo Lafibre.info: ./speedtest -s 21415 -vvv

