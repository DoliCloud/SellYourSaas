#!/bin/bash

export vhostfile="~/git/sellyoursaas/scripts/templates/vhostHttps-dolibarr.template"
export targetdir="/home/test"

for i in {1..800}
do
	if [[ -d $targetdir/test_$i ]]
	then
		echo "Dir $targetdir/test_$i exists. We delete it."
		deluser test_$i
		rm -fr "$targetdir/test_$i"
	else
		echo "Dir $targetdir/test_$i does not exists."
	fi

	export apacheconf="/etc/apache2/sellyoursaas-available/test_$i.conf"
	if [[ -f $apacheconf ]]
	then
		echo "Apache conf for $apacheconf exists. We delete it."
		rm "$apacheconf"
	else
		echo "Apache conf $apacheconf does not exists."
	fi

done

echo "Finished"
