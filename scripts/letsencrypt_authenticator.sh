#!/bin/bash

verbose=true
export subdomain=`grep '^subdomain=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
zone_file="/etc/bind/${subdomain}.hosts"
#current_certificates="/etc/letsencrypt/live/with1.doliasso.org/*pem"

#LET'S ENCRYPT VARIABLES
#
#CERTBOT_DOMAIN: The domain being authenticated
#CERTBOT_VALIDATION: The validation string
#CERTBOT_TOKEN: Resource name part of the HTTP-01 challenge (HTTP-01 only)
#CERTBOT_REMAINING_CHALLENGES: Number of challenges remaining after the current challenge
#CERTBOT_ALL_DOMAINS: A comma-separated list of all domains challenged for the current certificate

if [ -z "$CERTBOT_DOMAIN" ] || [ -z "$CERTBOT_VALIDATION" ]
then
	echo "EMPTY DOMAIN OR VALIDATION : LET'S ENCRYPT ENV VARIABLES NOT SET"
	exit 2
fi

if [ ! -f "$zone_file" ] || [ ! -w "$zone_file" ]
then
	echo "ZONE FILE DOESN'T EXIST OR ISN'T WRITABLE: $zone_file"
	exit 3
fi


#current_checksums=$(md5sum $current_certificates)
#$verbose && echo -e "current certificates md5sums :\n$current_checksums"

serial=$(grep serial $zone_file |awk '{print $1}')
$verbose && echo "old serial : $serial"
new_serial=$((serial+1))
$verbose && echo "new serial : $new_serial"
challenge_line="_acme-challenge IN        TXT      $CERTBOT_VALIDATION"
$verbose && echo "challenge line : $challenge_line"
sed_command='$a'
sed_command+="$challenge_line"
sed -i.auto.bck -e "$sed_command" $zone_file
sed -i.auto.bck2 -e "s/$serial/$new_serial/" $zone_file
systemctl stop bind9
sleep 5
systemctl start bind9
sleep 10

