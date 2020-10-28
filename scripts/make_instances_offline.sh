#!/bin/bash
#---------------------------------------------------------
# Script to make all instances offline or back online.
# Virtual hosts redirect to another URL.
#---------------------------------------------------------


echo "***** $0 *****"

export webSSLCertificateCRT=with.sellyoursaas.com.crt
export webSSLCertificateKEY=with.sellyoursaas.com.key
export webSSLCertificateIntermediate=with.sellyoursaas.com-intermediate.crt

if [ "x$2" == "x" ]; then
   echo "Usage:   $0  urlwhenoffline  test|offline|online"
   echo "Example: $0  https://myaccount.mydomain.com/offline.php  test"
   exit 1
fi

if [ "x$2" != "xtest" -a "x$2" != "xoffline" -a "x$2" != "xonline" ]; then
   echo "Parameter 2 must be test|offline|online"
   exit 1
fi

export scriptdir=$(dirname $(realpath ${0}))

# possibility to change the directory of vhostfile templates
templatesdir=`grep 'templatesdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$templatesdir" != "x" ]]; then
	export vhostfileoffline="$templatesdir/vhostHttps-sellyoursaas-offline.template"
else
	export vhostfileoffline="$scriptdir/templates/vhostHttps-sellyoursaas-offline.template"
fi

if [ "x$2" != "xonline" ]; then
	echo "Loop on each enabled virtual host of customer instances, create a new one and switch it"
	echo "Path for template is $scriptdir"
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
					sed 's!__webMyAccount__!'$1'!g' | \
			        sed 's!__webSSLCertificateCRT__!'$webSSLCertificateCRT'!g' | \
    	    	    sed 's!__webSSLCertificateKEY__!'$webSSLCertificateKEY'!g' | \
	            	sed 's!__webSSLCertificateIntermediate__!'$webSSLCertificateIntermediate'!g' | \
					sed 's!__VirtualHostHead__!'${virtualhosthead}'!g' | \
					sed 's!__AllowOverride__!'${allowoverride}'!g' \
					> /etc/apache2/sellyoursaas-offline/$fileshort
			else
		        rm -f /etc/apache2/sellyoursaas-offline/$domain.conf 2>/dev/null
				
				echo Create file /etc/apache2/sellyoursaas-offline/$fileshort for domain $domain
				cat $vhostfileoffline | \
					sed 's!__webAppDomain__!'${domain}'!g' | \
					sed 's!__webMyAccount__!'$1'!g' | \
		            sed 's!__webSSLCertificateCRT__!'$webSSLCertificateCRT'!g' | \
        		    sed 's!__webSSLCertificateKEY__!'$webSSLCertificateKEY'!g' | \
              		sed 's!__webSSLCertificateIntermediate__!'$webSSLCertificateIntermediate'!g' | \
					sed 's!__VirtualHostHead__!'${virtualhosthead}'!g' | \
					sed 's!__AllowOverride__!'${allowoverride}'!g' \
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
