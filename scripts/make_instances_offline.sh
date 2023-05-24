#!/bin/bash
#---------------------------------------------------------
# Script to make all instances offline or back online.
# Virtual hosts redirect to another URL.
#---------------------------------------------------------


echo "***** $0 *****"

if [ "x$2" == "x" ]; then
   echo "Usage:   $0  urlwhenoffline  test|offline|online"
   echo "Example: $0  offline.php  test"
   echo "Example: $0  maintenance.php  test"
   echo "Example: $0  https://myaccount.mydomain.com/offline.php  test       (old syntax)"
   echo "Example: $0  https://myaccount.mydomain.com/maintenance.php  test   (old syntax)"
   exit 1
fi

if [ "x$2" != "xtest" -a "x$2" != "xoffline" -a "x$2" != "xonline" ]; then
   echo "Parameter 2 must be test|offline|online"
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
echo "Url to use for __webMyAccount__ is $urlwhenoffline"


export scriptdir=$(dirname $(realpath ${0}))

# possibility to change the directory of vhostfile templates
templatesdir=`grep '^templatesdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$templatesdir" != "x" ]]; then
	export vhostfileoffline="$templatesdir/vhostHttps-sellyoursaas-offline.template"
else
	export vhostfileoffline="$scriptdir/templates/vhostHttps-sellyoursaas-offline.template"
fi

if [ "x$2" != "xonline" ]; then
	echo "Loop on each enabled virtual host of customer instances, create a new one and switch it"
	if [[ "x$templatesdir" != "x" ]]; then
		echo "Path for template is $templatesdir"
	else
		echo "Path for template is $scriptdir"
	fi
	mkdir /etc/apache2/sellyoursaas-offline 2>/dev/null

	for file in `ls /etc/apache2/sellyoursaas-online/*`
	do
	        echo -- Process file $file to create its offline virtual host
			export fileshort=`basename $file`
			export domain=$(echo $fileshort | /bin/sed 's/\.conf$//g' | /bin/sed 's/\.custom$//g')
			#echo fileshort=$fileshort domain=$domain 
			
			if [[ $fileshort == *".custom."* ]]; then
		        rm -f /etc/apache2/sellyoursaas-offline/$domain.custom.conf 2>/dev/null
				export domain=$(cat /etc/apache2/sellyoursaas-online/$domain.custom.conf | grep ServerName | sed -s 's/^ *//' | cut --delimiter=' '  -f2)
	        
				echo Create file /etc/apache2/sellyoursaas-offline/$fileshort for domain $domain
				cat $vhostfileoffline | \
					sed 's!__webAppDomain__!'${domain}'!g' | \
					sed 's!__webMyAccount__!'${urlwhenoffline}'!g' | \
			        sed 's!__webSSLCertificateCRT__!'$webSSLCertificateCRT'!g' | \
    	    	    sed 's!__webSSLCertificateKEY__!'$webSSLCertificateKEY'!g' | \
	            	sed 's!__webSSLCertificateIntermediate__!'$webSSLCertificateIntermediate'!g' | \
					sed 's!__VirtualHostHead__!'${virtualhosthead}'!g' | \
					sed 's!__AllowOverride__!'${allowoverride}'!g' | \
					sed 's!__IncludeFromContract__!'${includefromcontract}'!g' \
					> /etc/apache2/sellyoursaas-offline/$fileshort
			else
		        rm -f /etc/apache2/sellyoursaas-offline/$domain.conf 2>/dev/null
				
				echo Create file /etc/apache2/sellyoursaas-offline/$fileshort for domain $domain
				cat $vhostfileoffline | \
					sed 's!__webAppDomain__!'${domain}'!g' | \
					sed 's!__webMyAccount__!'${urlwhenoffline}'!g' | \
		            sed 's!__webSSLCertificateCRT__!'$webSSLCertificateCRT'!g' | \
        		    sed 's!__webSSLCertificateKEY__!'$webSSLCertificateKEY'!g' | \
              		sed 's!__webSSLCertificateIntermediate__!'$webSSLCertificateIntermediate'!g' | \
					sed 's!__VirtualHostHead__!'${virtualhosthead}'!g' | \
					sed 's!__AllowOverride__!'${allowoverride}'!g' | \
					sed 's!__IncludeFromContract__!'${includefromcontract}'!g' \
					> /etc/apache2/sellyoursaas-offline/$fileshort
			fi
	done
	echo --
fi

if [ "x$2" = "xoffline" ]; then
	rm /etc/apache2/sellyoursaas-enabled
	echo Create link /etc/apache2/sellyoursaas-enabled pointing to /etc/apache2/sellyoursaas-offline
	ln -fs /etc/apache2/sellyoursaas-offline /etc/apache2/sellyoursaas-enabled
	
	echo Reload Apache
	/etc/init.d/apache2 reload 
fi

if [ "x$2" = "xonline" ]; then
	rm /etc/apache2/sellyoursaas-enabled
	echo Create link /etc/apache2/sellyoursaas-enabled pointing to /etc/apache2/sellyoursaas-online
	ln -fs /etc/apache2/sellyoursaas-online /etc/apache2/sellyoursaas-enabled
	
	echo Reload Apache
	/etc/init.d/apache2 reload 
fi

if [ "x$2" != "xoffline" -a "x$2" != "xonline" ]; then
	echo Nothing more done. We are in test mode.
fi

echo "Finished."
