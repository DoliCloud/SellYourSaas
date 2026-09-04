#!/bin/bash
#
# One-time, per-deployment-server migration from mpm_itk (mod_php or php-fpm era) to
# mpm_event + HTTP/2. Requires phpfpm=1 and phpversion=<server default> already set in
# /etc/sellyoursaas.conf. Any vhost still on mod_php gets switched to that default PHP-FPM
# version first (via switch_instance_phpversion.sh, one instance at a time) before the MPM
# switch, so isolation (currently from mpm_itk's OS-user switching) is never dropped before
# each instance has its own php-fpm pool to fall back on.
#
# Why this is needed: mod_http2 refuses to load under mpm_prefork (which mpm_itk is built
# on), so HTTP/2 requires mpm_event. But mpm_event has no per-vhost UID switching, so Apache
# then runs every vhost as one shared user (www-data). PHP execution stays isolated per
# client via each php-fpm pool's own user (already the case since the php-fpm migration),
# but www-data still needs read+traverse on each instance's htdocs/ to serve static files
# and hand .php requests off to the fcgi socket - documents/ (private Dolibarr data) is
# never Aliased into any vhost and is only ever reached through an authenticated PHP script
# running under the instance's own pool user, so it must stay fully unreadable by www-data.
#
# Safe to re-run: every step here is idempotent.

set -e

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Checking prerequisites"

if [[ "$(id -u)" != "0" ]]; then
	echo "This script must be run as root" 1>&2
	exit 1
fi

phpfpm=$(grep '^phpfpm=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
if [[ "x$phpfpm" == "x" ]]; then
	echo "Error: phpfpm= is not set in /etc/sellyoursaas.conf. Migrate this server's instances to php-fpm before running this script." 1>&2
	exit 1
fi

defaultphpversion=$(grep '^phpversion=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
if [[ "x$defaultphpversion" == "x" ]]; then
	echo "Error: phpversion= is not set in /etc/sellyoursaas.conf (server default PHP-FPM version)" 1>&2
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
	echo "Error: SELLYOURSAAS_ENABLE_PHPVERSION_OVERRIDE is not enabled on the master server (Home - Setup - Other). Enable it before running this migration." 1>&2
	exit 1
fi

if ! apache2ctl -M 2>/dev/null | grep -q proxy_fcgi_module; then
	echo "Error: mod_proxy_fcgi is not enabled - needed before any mod_php instance can be switched to php-fpm." 1>&2
	echo "Run 'a2enmod proxy proxy_fcgi', then 'systemctl restart apache2' (a2enmod alone is not enough, this needs a full restart), then re-run this script." 1>&2
	exit 1
fi

scriptdir=$(dirname "$(realpath "$0")")
modphpvhosts=$(grep -L 'fpm-.*\.sock' /etc/apache2/sellyoursaas-enabled/*.conf 2>/dev/null || true)
if [[ "x$modphpvhosts" != "x" ]]; then
	echo "$(date +'%Y-%m-%d %H:%M:%S') ***** The following vhosts are still on mod_php, switching them to php-fpm $defaultphpversion (the server default) first:"
	echo "$modphpvhosts"
	while IFS= read -r vhost; do
		[ -n "$vhost" ] || continue
		fqn=$(basename "$vhost" .conf)
		documentroot=$(grep -oP '(?<=DocumentRoot )\S+' "$vhost" | head -1)
		documentroot=${documentroot%/htdocs}
		if [[ "x$documentroot" == "x" || ! -d "$documentroot" ]]; then
			echo "Error: could not find the instance directory for $fqn from $vhost's DocumentRoot" 1>&2
			exit 1
		fi
		osusername=$(stat -c '%U' "$documentroot")
		"$scriptdir/switch_instance_phpversion.sh" "$fqn" "$osusername" "$documentroot" "$defaultphpversion"
	done <<< "$modphpvhosts"
fi

echo "$(date +'%Y-%m-%d %H:%M:%S') All vhosts are now on php-fpm, continuing"

# Instances already on php-fpm before this script ever ran (e.g. deployed straight onto
# php-fpm, or switched manually) never went through switch_instance_phpversion.sh above,
# so their contract 'PHP version' field may never have been synced - visit them too, passing
# their own current version (a no-op on the server, but triggers the contract-field sync).
alreadyfpmvhosts=$(grep -l 'fpm-.*\.sock' /etc/apache2/sellyoursaas-enabled/*.conf 2>/dev/null | grep -v '\.custom[0-9]*\.conf$' || true)
if [[ "x$alreadyfpmvhosts" != "x" ]]; then
	echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Syncing the contract 'PHP version' field for vhosts already on php-fpm before this script ran:"
	while IFS= read -r vhost; do
		[ -n "$vhost" ] || continue
		fqn=$(basename "$vhost" .conf)
		currentphpversion=$(grep -oP "(?<=php)[0-9]+\.[0-9]+(?=-fpm-${fqn//./\\.}\.sock)" "$vhost" | head -1)
		[ -n "$currentphpversion" ] || continue
		documentroot=$(grep -oP '(?<=DocumentRoot )\S+' "$vhost" | head -1)
		documentroot=${documentroot%/htdocs}
		[[ "x$documentroot" != "x" && -d "$documentroot" ]] || continue
		osusername=$(stat -c '%U' "$documentroot")
		"$scriptdir/switch_instance_phpversion.sh" "$fqn" "$osusername" "$documentroot" "$currentphpversion"
	done <<< "$alreadyfpmvhosts"
fi

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Installing acl package"
if ! command -v setfacl >/dev/null 2>&1; then
	DEBIAN_FRONTEND=noninteractive apt-get install -y acl
fi

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Granting www-data read+traverse on htdocs for every existing instance (documents/ stays untouched)"
for d in /home/jail/home/*/*/htdocs; do
	[ -d "$d" ] || continue
	parent=$(dirname "$d")
	grandparent=$(dirname "$parent")
	echo "  $d"
	setfacl -m g:www-data:--x "$grandparent"
	setfacl -m g:www-data:--x "$parent"
	setfacl -R -m g:www-data:rX "$d"
	setfacl -d -m g:www-data:rX "$d"
done

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Verifying www-data can read htdocs but not documents/"
failed=0
for d in /home/jail/home/*/*/htdocs; do
	[ -d "$d" ] || continue
	f=$(find "$d" -maxdepth 1 -type f | head -1)
	if [ -n "$f" ] && ! sudo -u www-data test -r "$f"; then
		echo "  FAIL: www-data cannot read $f" 1>&2
		failed=1
	fi
done
for d in /home/jail/home/*/*/documents; do
	[ -d "$d" ] || continue
	if sudo -u www-data test -r "$d"; then
		echo "  FAIL: www-data can read $d (should stay blocked)" 1>&2
		failed=1
	fi
done
if [[ "$failed" != "0" ]]; then
	echo "Error: ACL verification failed, aborting before touching the MPM (nothing web-facing changed yet)" 1>&2
	exit 1
fi
echo "$(date +'%Y-%m-%d %H:%M:%S') ACL verification OK"

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Fixing up existing php-fpm pools created before listen.group was www-data"
for poolconf in /etc/php/*/fpm/pool.d/sellyoursaas/*.phpfpm.conf; do
	[ -f "$poolconf" ] || continue
	if grep -q '^listen.group = www-data$' "$poolconf"; then
		continue
	fi
	echo "  $poolconf"
	sed -i -E 's/^listen\.group = .*/listen.group = www-data/' "$poolconf"
	if ! grep -q '^listen.mode' "$poolconf"; then
		sed -i '/^listen.group = www-data$/a listen.mode = 0660' "$poolconf"
	fi
	phpversion=$(echo "$poolconf" | sed -n 's#/etc/php/\([0-9.]*\)/fpm/.*#\1#p')
	fqn=$(basename "$poolconf" .phpfpm.conf)
	systemctl restart "sellyoursaas-php${phpversion}-fpm-${fqn}.service"
done

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Neutralizing the now-invalid php_admin_value open_basedir line (mod_php will be disabled below; open_basedir is already enforced per-pool by php-fpm)"
for vhost in /etc/apache2/sellyoursaas-available/*.conf /etc/apache2/sellyoursaas-enabled/*.conf; do
	[ -f "$vhost" ] || continue
	[ -L "$vhost" ] && continue
	sed -i 's/^\([[:space:]]*\)php_admin_value open_basedir/\1#php_admin_value open_basedir/' "$vhost"
done

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Switching MPM from itk/prefork to event and enabling HTTP/2"
apache2ctl configtest
# a2query -m reports the a2enmod/a2dismod name (eg. "php7.4", "php8.3" - stable across Ubuntu
# 22.04/24.04, Debian package naming convention), NOT apache2ctl -M's internal module symbol
# (always "php7_module" for the whole 7.x branch, "php_module" for 8.x - never version-specific,
# so grepping for "php[0-9]+\.[0-9]+" in apache2ctl -M's output never matches anything there).
enabledphpmods=$(a2query -m 2>/dev/null | grep -oE '^php[0-9]+\.[0-9]+' || true)
for enabledphpmod in $enabledphpmods; do
	a2dismod "$enabledphpmod"
done
a2dismod mpm_itk 2>/dev/null || true
a2dismod mpm_prefork
rm -f /etc/apache2/mods-enabled/mpm_itk.conf
a2enmod mpm_event
a2enmod http2
apache2ctl configtest
systemctl restart apache2
systemctl is-active apache2

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Done. Verify with: curl -sk --http2 -A 'Mozilla/5.0' -o /dev/null -w 'Protocol: %{http_version}\n' https://<a-domain-on-this-server>/ --resolve <domain>:443:127.0.0.1"
