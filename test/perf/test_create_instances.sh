#!/bin/bash

export vhostfile="~/git/sellyoursaas/scripts/templates/vhostHttps-dolibarr.template"
export targetdir="/home/test"


if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi


for i in {1..800}
do
	if [[ -d $targetdir/test_$i/htdocs ]]
	then
		echo "Dir $targetdir/test_$i/htdocs already exists"
	else
		echo "Create dir $targetdir/test_$i/htdocs"
		useradd -m -d /home/test/test_$i test_$i
		mkdir -p "$targetdir/test_$i/htdocs"
		echo "HTML page for test_$i" > "$targetdir/test_$i/htdocs/index.html"
	fi

	if [[ ! -L "$targetdir/test_$i/htdocs/dolibarrtest" ]]
	then
		echo "Add link $targetdir/test_$i/htdocs/dolibarrtest to dolibarr files"
		ln -fs ~/git/dolibarr/htdocs "$targetdir/test_$i/htdocs/dolibarrtest"
	fi
		
	export apacheconf="/etc/apache2/sellyoursaas-available/test_$i.conf"
	if [[ -f $apacheconf ]]
	then
		echo "Apache conf $apacheconf already exists"
	else
		echo "Create apache conf $apacheconf from $vhostfile"
		cat $vhostfile | sed -e "s/@webAppDomain@/test_$i/g" | \
			  sed -e "s/@webAppAliases@/test_$i/g" | \
			  sed -e "s/@webAppLogName@/test_$i/g" | \
			  sed -e "s/@osUsername@/test_$i/g" | \
			  sed -e "s/@osGroupname@/test_$i/g" | \
			  sed -e "s/@webAppPath@/\/home\/test\/test_$i/" > $apacheconf
		#a2ensite test_$i.conf
		ln -fs /etc/apache2/sellyoursaas-available/test_$i.conf /etc/apache2/sellyoursaas-enabled
	fi
	
	if ! grep test_$i /etc/hosts >/dev/null; then
		echo Add name test_$i into /etc/hosts
		echo 127.0.0.1 test_$i >> /etc/hosts
	fi
	
done

echo "Finished. To launch apache: service apache2 reload"
