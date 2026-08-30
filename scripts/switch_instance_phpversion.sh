#!/bin/bash
#
# Create/update the php-fpm pool and systemd service for one instance, and point every one
# of its Apache vhosts' SetHandler at it - the main one plus any extra custom-URL vhost
# ("<fqn>.custom.conf", "<fqn>.custom2.conf"...: one <VirtualHost> per custom domain, since
# each needs its own SSL cert) - whether the instance is already on php-fpm (switching
# version) or still on mod_php (first-time migration, no existing SetHandler to replace).
#
# Used by both action_customurl_instance.sh (mode changephpversion, one instance at a time
# from a contract change) and migrate_server_http2.sh (looping over every remaining
# mod_php instance on a server before it can drop mpm_itk for HTTP/2).
#
# Usage: switch_instance_phpversion.sh <fqn> <osusername> <instancedir> <newphpversion>
#
# Safe to re-run: does nothing if every vhost is already pointed at <newphpversion>.

set -e

fqn=$1
osusername=$2
instancedir=$3
phpversion=$4

if [[ "x$fqn" == "x" || "x$osusername" == "x" || "x$instancedir" == "x" || "x$phpversion" == "x" ]]; then
	echo "Usage: $0 <fqn> <osusername> <instancedir> <newphpversion>" 1>&2
	exit 1
fi

masterdbhost=$(grep '^databasehost=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
masterdbport=$(grep '^databaseport=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
masterdbuser=$(grep '^databaseuser=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
masterdbpass=$(grep '^databasepass=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
masterdbname=$(grep '^database=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
if [[ "x$masterdbhost" == "x" || "x$masterdbname" == "x" ]]; then
	echo "Error: could not read the master database connection details from /etc/sellyoursaas.conf" 1>&2
	exit 1
fi
mastermysql() {
	mysql -h "$masterdbhost" -P "$masterdbport" -u "$masterdbuser" -p"$masterdbpass" "$masterdbname" --default-character-set=utf8 -N -e "$1"
}

# The Dolibarr table prefix defaults to llx_ but is configurable per install (dolibarr_main_db_prefix
# in conf.php); read the local Dolibarr's conf.php to get the prefix actually used by this master database.
dolibarrdir=$(grep '^dolibarrdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
dbprefix=llx_
if [[ "x$dolibarrdir" != "x" && -f "$dolibarrdir/htdocs/conf/conf.php" ]]; then
	confprefix=$(grep -vE '^\s*//' "$dolibarrdir/htdocs/conf/conf.php" 2>/dev/null | grep -oP "dolibarr_main_db_prefix\s*=\s*[\"']\K[^\"']+" || true)
	if [[ "x$confprefix" != "x" ]]; then
		dbprefix=$confprefix
	fi
fi

# Nothing enforces that this locally-read prefix actually matches the master database's real
# table prefix (the rest of the module - batch_customers.php and friends - makes the exact same
# unchecked assumption); fail clearly here instead of a confusing "table doesn't exist" further down.
mastermysqlerror=$(mastermysql "SELECT 1 FROM ${dbprefix}const LIMIT 1" 2>&1 >/dev/null)
if [[ $? -ne 0 ]]; then
	echo "Error: could not query ${dbprefix}const on the master database ($masterdbname@$masterdbhost) - check the master database credentials in /etc/sellyoursaas.conf, and that '$dbprefix' (from $dolibarrdir/htdocs/conf/conf.php, or the llx_ default if that file wasn't found) actually matches the table prefix used on the master database." 1>&2
	echo "$mastermysqlerror" 1>&2
	exit 1
fi

enableoverride=$(mastermysql "SELECT value FROM ${dbprefix}const WHERE name='SELLYOURSAAS_ENABLE_PHPVERSION_OVERRIDE' AND entity=1" 2>/dev/null)
if [[ "x$enableoverride" != "x1" ]]; then
	echo "Error: SELLYOURSAAS_ENABLE_PHPVERSION_OVERRIDE is not enabled on the master server (Home - Setup - Other). Enable it before switching any instance's PHP version." 1>&2
	exit 1
fi

templatesdir=$(grep '^templatesdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
if [[ "x$templatesdir" == "x" ]]; then
	templatesdir=$(dirname "$(realpath "$0")")/templates
fi
fpmpoolfiletemplate="$templatesdir/phppool-phpfpm.template"
fpmservicefiletemplate="$templatesdir/poolservice-phpfpm.template"

# php-fpm's open_basedir needs read access to the sellyoursaas module's own scripts/ directory
# (eg. for phpsendmail.php/phpsendmailprepend.php) - same variable as action_deploy_undeploy.sh,
# otherwise __sellyoursaasScriptsPath__ is left unsubstituted in the generated pool conf.
sellyoursaasdir=$(grep '^sellyoursaasdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
if [[ "x$sellyoursaasdir" == "x" ]]; then
	sellyoursaasdir="/home/admin/wwwroot/dolibarr_sellyoursaas"
fi
sellyoursaasscriptsdir="$sellyoursaasdir/scripts"

if ! apache2ctl -M 2>/dev/null | grep -q proxy_fcgi_module; then
	echo "Error: mod_proxy_fcgi is not enabled - the SetHandler this script writes into the vhost needs it to reach php-fpm." 1>&2
	echo "Run 'a2enmod proxy proxy_fcgi', then 'systemctl restart apache2' (a2enmod alone is not enough, this needs a full restart), before retrying." 1>&2
	exit 22
fi

apacheconf="/etc/apache2/sellyoursaas-available/$fqn.conf"
if [ ! -f "$apacheconf" ]; then
	echo "Error: vhost $apacheconf not found, cannot switch PHP version for an instance that does not seem deployed" 1>&2
	exit 20
fi

# An instance can have extra custom-URL vhosts (one SSL cert per domain means one full
# <VirtualHost> per domain, not just a ServerAlias on the main one) - only one of them is
# ever known to Dolibarr (contract custom_url field, "<fqn>.custom.conf"); others may have
# been added by hand ("<fqn>.custom2.conf", "<fqn>.custom3.conf"...), multi-URL support
# being deferred for now. All of them proxy to the same php-fpm socket as the main vhost,
# so every one of them needs the same SetHandler update, or it silently keeps serving the
# old PHP version - or breaks outright once the old pool/service is torn down below.
apacheconfs=$(
	{
		echo "$apacheconf"
		ls /etc/apache2/sellyoursaas-available/"$fqn".custom*.conf 2>/dev/null
		ls /etc/apache2/sellyoursaas-enabled/"$fqn".custom*.conf 2>/dev/null
	} | xargs -r -I{} realpath {} | sort -u
)
echo "$(date +'%Y-%m-%d %H:%M:%S') Vhost(s) for $fqn:"
echo "$apacheconfs" | sed 's/^/  /'

# Always trust what the live main vhost is actually pointing to (empty if still on mod_php,
# there's never been a SetHandler proxying to a php-fpm socket for this instance yet).
currentphpversioninvhost=$(grep -oP "(?<=php)[0-9]+\.[0-9]+(?=-fpm-${fqn//./\\.}\.sock)" "$apacheconf" | head -1)
echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Switching PHP version of $fqn from '${currentphpversioninvhost:-mod_php}' to '$phpversion'"

# Only skip everything (including the vhost loop below) if EVERY vhost already points at the
# target version - a custom-URL vhost added or re-pointed after the last switch could still
# be lagging behind even when the main one already matches.
allvhostsalreadyonversion=1
while IFS= read -r onevhost; do
	[ -n "$onevhost" ] || continue
	if ! grep -q "php$phpversion-fpm-$fqn.sock" "$onevhost"; then
		allvhostsalreadyonversion=0
		break
	fi
done <<< "$apacheconfs"
if [[ "$allvhostsalreadyonversion" == "1" ]]; then
	echo "Instance $fqn is already running PHP $phpversion on every vhost, nothing to do on the server"
	# Still sync the contract field even on a no-op: an instance already on php-fpm before
	# this script existed (or before SELLYOURSAAS_ENABLE_PHPVERSION_OVERRIDE was fixed) may
	# never have had its 'PHP version' field written, and would otherwise never get fixed
	# since it never goes through the switch logic below.
	echo "$(date +'%Y-%m-%d %H:%M:%S') Syncing the 'PHP version' contract field on the master server, just in case it wasn't already"
	mastermysql "UPDATE ${dbprefix}contrat_extrafields ce JOIN ${dbprefix}contrat c ON c.rowid = ce.fk_object SET ce.phpversion = '$phpversion' WHERE c.ref_customer = '$fqn' AND (ce.phpversion IS NULL OR ce.phpversion != '$phpversion')"
	exit 0
fi

newphpfpmpooldir="/etc/php/$phpversion/fpm/pool.d/sellyoursaas"
newphpfpmconf="$newphpfpmpooldir/$fqn.phpfpm.conf"
newphpfpmservicename="sellyoursaas-php$phpversion-fpm-$fqn.service"
newphpfpmservice="/etc/systemd/system/$newphpfpmservicename"

mkdir -p "$newphpfpmpooldir"

# Per-instance temp dir the pool conf points env TMP/TMPDIR/TEMP, sys_temp_dir and
# upload_tmp_dir at instead of the shared system /tmp - may not exist yet for an instance
# switching to php-fpm for the first time (action_deploy_undeploy.sh only creates it on deploy).
# Owner needs fixing here (unlike on deploy) since this script never chowns the rest of
# $instancedir - it already belongs to $osusername from the original deploy.
mkdir -p "$instancedir/tmp"
chown "$osusername:$osusername" "$instancedir/tmp"
chmod go-rwxs "$instancedir/tmp"

echo "$(date +'%Y-%m-%d %H:%M:%S') Create php fpm pool conf $newphpfpmconf from $fpmpoolfiletemplate"
sed -e "s;__fqn__;$fqn;g" \
    -e "s;__phpversion__;$phpversion;g" \
    -e "s;__osUsername__;$osusername;g" \
    -e "s;__osGroupname__;$osusername;g" \
    -e "s;__webAppPath__;$instancedir;g" \
    -e "s;__sellyoursaasScriptsPath__;$sellyoursaasscriptsdir;g" \
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

while IFS= read -r onevhost; do
	[ -n "$onevhost" ] || continue
	cp -a "$onevhost" "$onevhost.bak-switchphpversion-$(date +%Y%m%d-%H%M%S)"

	oneversioninvhost=$(grep -oP "(?<=php)[0-9]+\.[0-9]+(?=-fpm-${fqn//./\\.}\.sock)" "$onevhost" | head -1)
	if [[ "x$oneversioninvhost" != "x" ]]; then
		# Already on php-fpm: just repoint the existing SetHandler at the new socket
		echo "$(date +'%Y-%m-%d %H:%M:%S') Update SetHandler in $onevhost to point to the new socket"
		sed -i "s;php$oneversioninvhost-fpm-$fqn.sock;php$phpversion-fpm-$fqn.sock;g" "$onevhost"
	else
		# Still on mod_php: there is no SetHandler to replace, insert one fresh right after the
		# first </IfModule> (the mpm_itk AssignUserID block, present in every vhost regardless
		# of PHP mode), matching vhostHttps-phpfpm-sellyoursaas.template's own placement. Also
		# neutralize php_admin_value open_basedir: it's an Apache/mod_php-only directive, invalid
		# once mod_php is disabled, and redundant anyway since php-fpm enforces open_basedir
		# per-pool (see php_admin_value[open_basedir] in phppool-phpfpm.template).
		echo "$(date +'%Y-%m-%d %H:%M:%S') No existing php-fpm SetHandler in $onevhost (instance was on mod_php), inserting one"
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
		' "$onevhost" > "$onevhost.tmp" && mv "$onevhost.tmp" "$onevhost"
		sed -i 's/^\([[:space:]]*\)php_admin_value open_basedir/\1#php_admin_value open_basedir/' "$onevhost"
	fi
done <<< "$apacheconfs"

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

echo "$(date +'%Y-%m-%d %H:%M:%S') Updating the 'PHP version' contract field on the master server to match"
mastermysql "UPDATE ${dbprefix}contrat_extrafields ce JOIN ${dbprefix}contrat c ON c.rowid = ce.fk_object SET ce.phpversion = '$phpversion' WHERE c.ref_customer = '$fqn'"

echo "$(date +'%Y-%m-%d %H:%M:%S') PHP version switch for $fqn to $phpversion done"
