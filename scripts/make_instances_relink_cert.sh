#!/bin/bash
#---------------------------------------------------------
# Relink local certificates found to link to specific files
#---------------------------------------------------------

source /etc/lsb-release

export RED='\033[0;31m'
export GREEN='\033[0;32m'
export BLUE='\033[0;34m'
export YELLOW='\033[0;33m'


echo "***** $0 *****"

if [ "x$2" == "x" ]; then
   echo "Relink local certificates found into /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt to link to specific cert files"
   echo
   echo "Usage:   $0  root_of_cert_to_link_to  regex_of_files_to_replace  test|confirm"
   echo "Example: $0  /etc/apache2/all.with.dolicloud.com  xxx.dolicloud.com  test|confirm"
   echo
   exit 1
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

echo "Search local cert files to relink with: ls /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/*.key | grep $2" 
for fic in `ls /home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/crt/*.key | grep $2`
do
	newfic="${fic%.key}"
	echo "* Process files $newfic.(key|crt|-intermediate.crt).."
	#echo "ls -l $newfic.key | grep $1"
	islink=`ls -l $newfic.key | grep $1 | cut -c1`
	#echo "islink=$islink"
	
	if [ "x$islink" == "xl" ]; then
		echo "File $newfic.key is already a link, we ignore it"
	else
		echo "ln -fs $1.key $newfic.key"
		echo "ln -fs $1.crt $newfic.crt"
		echo "ln -fs $1-intermediate.crt $newfic-intermediate.crt"
		if [ "x$3" == "xconfirm" ]; then
			ln -fs $1.key $newfic.key
			ln -fs $1.crt $newfic.crt
			ln -fs $1-intermediate.crt $newfic-intermediate.crt
		else
			echo "Test mode: nothing done"
		fi
	fi
done

echo "Finished."
