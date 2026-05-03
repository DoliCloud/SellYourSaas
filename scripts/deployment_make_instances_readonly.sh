#!/bin/bash
#---------------------------------------------------------
# Script to make instances in readonly mode.
#---------------------------------------------------------

source /etc/lsb-release

export RED='\033[0;31m'
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[0;33m'


echo "***** $0 $1 $2 $3 *****"

if [ "x$1" == "x" ]; then
   echo "Script to make one instances readonly."
   echo "Usage:   $0  instance  readonly|readwrite  test|confirm"
   echo
   exit 1
fi

if [ "x$2" != "xreadonly" -a "x$2" != "xreadwrite" ]; then
   echo "Parameter 2 must be readonly|readwrite"
   exit 2
fi
if [ "x$3" != "xtest" -a "x$3" != "xconfirm" ]; then
   echo "Parameter 3 must be test|confirm"
   exit 2
fi

# possibility to change the ssl certificates name
export webSSLCertificateCRT=`grep '^websslcertificatecrt=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$webSSLCertificateCRT" == "x" ]]; then
	export webSSLCertificateCRT=with.sellyoursaas.com.crt
fi
export webSSLCertificateKEY=`grep '^websslcertificatekey=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$webSSLCertificateKEY" == "x" ]]; then
	export webSSLCertificateKEY=with.sellyoursaas.com.key
fi
export webSSLCertificateIntermediate=`grep '^websslcertificateintermediate=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$webSSLCertificateIntermediate" == "x" ]]; then
	export webSSLCertificateIntermediate=with.sellyoursaas.com-intermediate.crt
fi
export domainmyaccount=`grep '^domain=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

export urlwhenoffline=$1
if [[ $urlwhenoffline != http* ]]; then
	export urlwhenoffline="https://myaccount.$domainmyaccount/$1"
fi


export scriptdir=$(dirname $(realpath ${0}))

# possibility to change the directory of vhostfile templates
templatesdir=`grep '^templatesdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$templatesdir" != "x" ]]; then
	export vhostfileoffline="$templatesdir/vhostHttps-sellyoursaas-offline.template"
else
	export vhostfileoffline="$scriptdir/templates/vhostHttps-sellyoursaas-offline.template"
fi

if [ "x$2" != "xreadonly" ]; then
	echo "Set instance in read only mode"
	# TODO
	echo --
fi

if [ "x$2" != "xreadwrite" ]; then
	echo "Set instance in read write mode"
	# TODO
	echo --
fi

echo "Finished."
