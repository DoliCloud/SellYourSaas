#!/bin/bash
#
# One-time, per-deployment-server migration from mpm_itk (mod_php or php-fpm era) to
# mpm_event + HTTP/2. Run this AFTER every instance on the server has already been
# switched to php-fpm (phpfpm=1 in /etc/sellyoursaas.conf, and every vhost's SetHandler
# pointing at a php*-fpm-*.sock - check with:
#   grep -L 'fpm-.*\.sock' /etc/apache2/sellyoursaas-enabled/*.conf
# If that prints any file, that instance is still on mod_php: migrate it to php-fpm first
# (mpm_itk is what currently gives it OS-user isolation - dropping itk before its pool
# exists would run it, unisolated, as the shared Apache worker user).
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

modphpvhosts=$(grep -L 'fpm-.*\.sock' /etc/apache2/sellyoursaas-enabled/*.conf 2>/dev/null || true)
if [[ "x$modphpvhosts" != "x" ]]; then
	echo "Error: the following vhosts are still on mod_php (no php-fpm socket in their SetHandler):" 1>&2
	echo "$modphpvhosts" 1>&2
	echo "Switch them to php-fpm first (changephpversion from the contract, then check the vhost got a SetHandler proxy:unix:... block - see FIX needed in action_customurl_instance.sh for first-time mod_php->fpm migrations)." 1>&2
	exit 1
fi

echo "$(date +'%Y-%m-%d %H:%M:%S') All vhosts are already on php-fpm, continuing"

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

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Switching MPM from itk/prefork to event and enabling HTTP/2"
apache2ctl configtest
a2dismod mpm_itk mpm_prefork 2>/dev/null || true
a2enmod mpm_event
a2enmod http2
apache2ctl configtest
systemctl restart apache2
systemctl is-active apache2

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Done. Verify with: curl -sk --http2 -A 'Mozilla/5.0' -o /dev/null -w 'Protocol: %{http_version}\n' https://<a-domain-on-this-server>/ --resolve <domain>:443:127.0.0.1"
