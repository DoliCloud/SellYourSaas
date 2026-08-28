#!/bin/bash
#
# Create/update the php-fpm pool and systemd service for one instance, and point its
# Apache vhost's SetHandler at it - whether the instance is already on php-fpm (switching
# version) or still on mod_php (first-time migration, no existing SetHandler to replace).
#
# Used by both action_customurl_instance.sh (mode changephpversion, one instance at a time
# from a contract change) and migrate_server_http2.sh (looping over every remaining
# mod_php instance on a server before it can drop mpm_itk for HTTP/2).
#
# Usage: switch_instance_phpversion.sh <fqn> <osusername> <instancedir> <newphpversion>
#
# Safe to re-run: does nothing if the vhost is already pointed at <newphpversion>.

set -e

fqn=$1
osusername=$2
instancedir=$3
phpversion=$4

if [[ "x$fqn" == "x" || "x$osusername" == "x" || "x$instancedir" == "x" || "x$phpversion" == "x" ]]; then
	echo "Usage: $0 <fqn> <osusername> <instancedir> <newphpversion>" 1>&2
	exit 1
fi

templatesdir=$(grep '^templatesdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
if [[ "x$templatesdir" == "x" ]]; then
	templatesdir=$(dirname "$(realpath "$0")")/templates
fi
fpmpoolfiletemplate="$templatesdir/phppool-phpfpm.template"
fpmservicefiletemplate="$templatesdir/poolservice-phpfpm.template"

apacheconf="/etc/apache2/sellyoursaas-available/$fqn.conf"
if [ ! -f "$apacheconf" ]; then
	echo "Error: vhost $apacheconf not found, cannot switch PHP version for an instance that does not seem deployed" 1>&2
	exit 20
fi

# Always trust what the live vhost is actually pointing to (empty if still on mod_php,
# there's never been a SetHandler proxying to a php-fpm socket for this instance yet).
currentphpversioninvhost=$(grep -oP "(?<=php)[0-9]+\.[0-9]+(?=-fpm-${fqn//./\\.}\.sock)" "$apacheconf" | head -1)
echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Switching PHP version of $fqn from '${currentphpversioninvhost:-mod_php}' to '$phpversion'"

if [[ "x$currentphpversioninvhost" == "x$phpversion" ]]; then
	echo "Instance $fqn is already running PHP $phpversion, nothing to do"
	exit 0
fi

newphpfpmpooldir="/etc/php/$phpversion/fpm/pool.d/sellyoursaas"
newphpfpmconf="$newphpfpmpooldir/$fqn.phpfpm.conf"
newphpfpmservicename="sellyoursaas-php$phpversion-fpm-$fqn.service"
newphpfpmservice="/etc/systemd/system/$newphpfpmservicename"

mkdir -p "$newphpfpmpooldir"

echo "$(date +'%Y-%m-%d %H:%M:%S') Create php fpm pool conf $newphpfpmconf from $fpmpoolfiletemplate"
sed -e "s;__fqn__;$fqn;g" \
    -e "s;__phpversion__;$phpversion;g" \
    -e "s;__osUsername__;$osusername;g" \
    -e "s;__osGroupname__;$osusername;g" \
    -e "s;__webAppPath__;$instancedir;g" \
    "$fpmpoolfiletemplate" > "$newphpfpmconf"

echo "$(date +'%Y-%m-%d %H:%M:%S') Create php fpm service $newphpfpmservice from $fpmservicefiletemplate"
sed -e "s;__fqn__;$fqn;g" \
    -e "s;__phpversion__;$phpversion;g" \
    "$fpmservicefiletemplate" > "$newphpfpmservice"

systemctl daemon-reload
systemctl enable --now "$newphpfpmservicename"
sleep 1

newsocket="/run/php/php$phpversion-fpm-$fqn.sock"
if [ ! -S "$newsocket" ]; then
	echo "Error: expected socket $newsocket not found after starting $newphpfpmservicename" 1>&2
	exit 21
fi

cp -a "$apacheconf" "$apacheconf.bak-switchphpversion-$(date +%Y%m%d-%H%M%S)"

if [[ "x$currentphpversioninvhost" != "x" ]]; then
	# Already on php-fpm: just repoint the existing SetHandler at the new socket
	echo "$(date +'%Y-%m-%d %H:%M:%S') Update SetHandler in $apacheconf to point to the new socket"
	sed -i "s;php$currentphpversioninvhost-fpm-$fqn.sock;php$phpversion-fpm-$fqn.sock;g" "$apacheconf"
else
	# Still on mod_php: there is no SetHandler to replace, insert one fresh right after the
	# first </IfModule> (the mpm_itk AssignUserID block, present in every vhost regardless
	# of PHP mode), matching vhostHttps-phpfpm-sellyoursaas.template's own placement. Also
	# neutralize php_admin_value open_basedir: it's an Apache/mod_php-only directive, invalid
	# once mod_php is disabled, and redundant anyway since php-fpm enforces open_basedir
	# per-pool (see php_admin_value[open_basedir] in phppool-phpfpm.template).
	echo "$(date +'%Y-%m-%d %H:%M:%S') No existing php-fpm SetHandler in $apacheconf (instance was on mod_php), inserting one"
	awk -v socket="php$phpversion-fpm-$fqn.sock" '
		{ print }
		!done && /<\/IfModule>/ {
			print ""
			print "        # Indique a Apache d'"'"'utiliser le socket de PHP-FPM specifique"
			print "        <FilesMatch \\.php$>"
			print "            ProxyFCGIBackendType GENERIC"
			print "            SetHandler \"proxy:unix:/run/php/" socket "|fcgi://localhost/\""
			print "        </FilesMatch>"
			done = 1
		}
	' "$apacheconf" > "$apacheconf.tmp" && mv "$apacheconf.tmp" "$apacheconf"
	sed -i 's/^\([[:space:]]*\)php_admin_value open_basedir/\1#php_admin_value open_basedir/' "$apacheconf"
fi

# Grant www-data read+traverse on htdocs (needed if/when this server later drops mpm_itk
# for HTTP/2 - harmless additive ACL, itk already has full access via the socket owner).
if command -v setfacl >/dev/null 2>&1; then
	grandparent=$(dirname "$instancedir")
	setfacl -m g:www-data:--x "$grandparent" 2>/dev/null || true
	setfacl -m g:www-data:--x "$instancedir" 2>/dev/null || true
	setfacl -R -m g:www-data:rX "$instancedir/htdocs" 2>/dev/null || true
	setfacl -d -m g:www-data:rX "$instancedir/htdocs" 2>/dev/null || true
fi

apache2ctl configtest
service apache2 reload

if [[ "x$currentphpversioninvhost" != "x" ]]; then
	oldphpfpmservicename="sellyoursaas-php$currentphpversioninvhost-fpm-$fqn.service"
	echo "$(date +'%Y-%m-%d %H:%M:%S') Stop and remove old php fpm pool/service for previous version $currentphpversioninvhost"
	systemctl disable --now "$oldphpfpmservicename" 2>/dev/null
	rm -f "/etc/systemd/system/$oldphpfpmservicename"
	rm -f "/etc/php/$currentphpversioninvhost/fpm/pool.d/sellyoursaas/$fqn.phpfpm.conf"
	systemctl daemon-reload
fi

echo "$(date +'%Y-%m-%d %H:%M:%S') PHP version switch for $fqn to $phpversion done"
