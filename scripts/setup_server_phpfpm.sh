#!/bin/bash
#
# One-time preparation of a deployment server for PHP-FPM with one or more PHP
# versions, before any instance is switched and before scripts/migrate_server_http2.sh
# can run. Idempotent: safe to re-run, eg. to add one more PHP version later.
#
# Usage: setup_server_phpfpm.sh <default_version> <version> [version...]
# Example: setup_server_phpfpm.sh 8.3 8.1 8.2 8.3 8.4
#   - installs php-fpm + a standard extension set for every <version> listed
#   - makes <default_version> (must be one of the versions listed) the one
#     whose own distro php<version>-fpm.service stays enabled; the others are masked
#   - sets phpfpm=1 and phpversion=<default_version> in /etc/sellyoursaas.conf
#   - enables mod_proxy_fcgi and restarts Apache
#   - refreshes the jailkit common jail AND every existing private jail (sshaccesstype=2
#     instances each got their own full copy at deploy time, they don't share commonjail)
#     so SSH/SFTP users get a matching php<version> CLI binary for every version above,
#     plus whatever PHP version mod_php is currently serving (if any - existing instances
#     keep using it until migrated). Skipped entirely if this server isn't using jailkit.
#
# What this script does NOT do: touch any existing vhost, or run
# scripts/migrate_server_http2.sh's MPM switch. Existing instances keep running
# under mod_php until migrated individually (contract PHP version field, which
# calls scripts/switch_instance_phpversion.sh) or in bulk later.

set -e

if [[ "$(id -u)" != "0" ]]; then
	echo "This script must be run as root" 1>&2
	exit 1
fi

if [[ $# -lt 2 ]]; then
	echo "Usage: $0 <default_version> <version> [version...]" 1>&2
	echo "Example: $0 8.3 8.1 8.2 8.3 8.4" 1>&2
	exit 1
fi

defaultphpversion=$1
shift
versions="$*"

case " $versions " in
	*" $defaultphpversion "*) ;;
	*)
		echo "Error: default version $defaultphpversion must be one of the versions listed: $versions" 1>&2
		exit 1
		;;
esac

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
	echo "Error: SELLYOURSAAS_ENABLE_PHPVERSION_OVERRIDE is not enabled on the master server (Home - Setup - Other). Enable it before setting up PHP-FPM on this server." 1>&2
	exit 1
fi

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Checking Sury PPA support for this OS"
codename=$(lsb_release -cs 2>/dev/null || . /etc/os-release && echo "$VERSION_CODENAME")
if ! curl -fsSL "https://packages.sury.org/php/dists/" 2>/dev/null | grep -q ">$codename/<"; then
	echo "Error: Sury's PHP PPA has no '$codename' distribution - this OS is too old (or too new) to install extra PHP versions this way." 1>&2
	echo "Check https://packages.sury.org/php/dists/ for supported distributions. Upgrading the OS is the usual fix (see do-release-upgrade)." 1>&2
	exit 1
fi
echo "$(date +'%Y-%m-%d %H:%M:%S') Sury supports $codename, continuing"

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Adding the Sury PHP PPA"
if [ ! -f /etc/apt/sources.list.d/php-sury.list ]; then
	apt-get install -y ca-certificates apt-transport-https curl
	curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/deb.sury.org-php.gpg
	echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $codename main" > /etc/apt/sources.list.d/php-sury.list
fi
apt-get update

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Installing PHP-FPM and extensions for: $versions"
exts="apcu bz2 cli common curl fpm gd igbinary imagick imap intl ldap mbstring memcached msgpack mysql opcache readline ssh2 xdebug xml zip"
for v in $versions; do
	echo "$(date +'%Y-%m-%d %H:%M:%S') --- php$v ---"
	pkgs=""
	for e in $exts; do
		pkg="php$v-$e"
		if apt-cache show "$pkg" >/dev/null 2>&1; then
			pkgs="$pkgs $pkg"
		else
			echo "  skipping $pkg (not available for PHP $v on this distribution)"
		fi
	done
	DEBIAN_FRONTEND=noninteractive apt-get install -y $pkgs
done

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Masking the default php-fpm service for every non-default version"
for v in $versions; do
	if [[ "$v" == "$defaultphpversion" ]]; then
		systemctl unmask "php$v-fpm" 2>/dev/null || true
		systemctl enable --now "php$v-fpm"
	else
		systemctl disable --now "php$v-fpm" 2>/dev/null || true
		systemctl mask "php$v-fpm"
	fi
done

# A handful of extensions (imagick, ssh2, xdebug at least) are not built per PHP version by
# Sury - installing any of them pulls in a version-independent package that registers itself
# against every PHP ABI already present on the system via a packaging trigger, which can
# silently apt-get install (and systemd auto-enables) a whole extra php<version>-fpm this
# script never asked for and knows nothing about. Mask any php-fpm service on disk that isn't
# one of the versions actually requested, whatever pulled it in.
for unit in /usr/lib/systemd/system/php*-fpm.service /lib/systemd/system/php*-fpm.service; do
	[ -f "$unit" ] || continue
	v=$(basename "$unit" | sed -n 's/^php\([0-9.]*\)-fpm\.service$/\1/p')
	[[ "x$v" != "x" ]] || continue
	case " $versions " in
		*" $v "*) ;;
		*)
			echo "  php$v-fpm was not requested (pulled in as a side dependency) - masking it"
			systemctl disable --now "php$v-fpm" 2>/dev/null || true
			systemctl mask "php$v-fpm"
			;;
	esac
done

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Creating pool.d/sellyoursaas for every version"
for v in $versions; do
	mkdir -p "/etc/php/$v/fpm/pool.d/sellyoursaas"
	chown root:root "/etc/php/$v/fpm/pool.d/sellyoursaas"
	chmod 755 "/etc/php/$v/fpm/pool.d/sellyoursaas"
done

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Deploying the php-fpm AppArmor local override"
imagickdir=$(basename "$(ls -d /etc/ImageMagick-* 2>/dev/null | head -1)")
if [[ "x$imagickdir" == "x" ]]; then
	echo "Warning: no /etc/ImageMagick-* found, skipping the ImageMagick allowance in the AppArmor override (add it by hand if ImageMagick gets installed later)."
	imagickdir="ImageMagick-6"
fi
cat > /etc/apparmor.d/local/php-fpm << EOF
owner /run/systemd/notify w,
# added by claude migration - allow ImageMagick policy read
/etc/$imagickdir/*.xml r,
/usr/share/$imagickdir/*.xml r,
/etc/$imagickdir/*.xml.dpkg-new r,
# added by claude migration - allow per-instance fpm socket/pid naming
/run/php/php*-fpm-*.sock rwlk,
/run/php/php*-fpm-*.pid rw,
# added by claude migration - allow reading/writing instance webroots (isolation enforced by open_basedir per pool)
/home/jail/home/** rwk,
EOF
chown root:root /etc/apparmor.d/local/php-fpm
chmod 644 /etc/apparmor.d/local/php-fpm
apparmor_parser -r /etc/apparmor.d/php-fpm

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Setting phpfpm=1 and phpversion=$defaultphpversion in /etc/sellyoursaas.conf"
if grep -q '^phpfpm=' /etc/sellyoursaas.conf; then
	sed -i "s/^phpfpm=.*/phpfpm=1/" /etc/sellyoursaas.conf
else
	printf '\nphpfpm=1\n' >> /etc/sellyoursaas.conf
fi
if grep -q '^phpversion=' /etc/sellyoursaas.conf; then
	sed -i "s/^phpversion=.*/phpversion=$defaultphpversion/" /etc/sellyoursaas.conf
else
	printf 'phpversion=%s\n' "$defaultphpversion" >> /etc/sellyoursaas.conf
fi

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Enabling mod_proxy_fcgi (needs a full Apache restart to take effect)"
a2enmod proxy proxy_fcgi
apache2ctl configtest
systemctl restart apache2
systemctl is-active apache2

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Refreshing the jailkit common jail"
chrootdir=$(grep '^chrootdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
if ! command -v jk_init >/dev/null 2>&1; then
	echo "jailkit is not installed on this server (SSH access is not jailed here), skipping the jail refresh."
elif [[ "x$chrootdir" == "x" ]]; then
	echo "chrootdir= is not set in /etc/sellyoursaas.conf, this server is not using jailkit for SellYourSaaS, skipping the jail refresh."
elif ! grep -q '^\[php\]' /etc/jailkit/jk_init.ini; then
	echo "Warning: jailkit is installed and chrootdir= is set, but /etc/jailkit/jk_init.ini has no [php] section yet - this server's jail was never set up per the Jailkit section of the doc. Skipping the jail refresh (add the [php]/[env]/[mysqlclient] sections by hand first, then re-run this script)."
else
	# a2query -m reports the a2enmod/a2dismod name (eg. "php7.4", "php8.3" - stable across Ubuntu
	# 22.04/24.04, Debian package naming convention), NOT apache2ctl -M's internal module symbol
	# (always "php7_module" for the whole 7.x branch, "php_module" for 8.x - never version-specific,
	# so grepping for "php[0-9]+\.[0-9]+" in apache2ctl -M's output never matches anything there).
	currentmodphp=$(a2query -m 2>/dev/null | grep -oE '^php[0-9]+\.[0-9]+' | head -1 || true)
	jailversions="$versions"
	if [[ "x$currentmodphp" != "x" ]]; then
		case " $jailversions " in
			*" ${currentmodphp#php} "*) ;;
			*) jailversions="$jailversions ${currentmodphp#php}" ;;
		esac
	fi
	execs="/usr/bin/php"
	for v in $jailversions; do
		if [ -x "/usr/bin/php$v" ]; then
			execs="$execs, /usr/bin/php$v"
		fi
	done
	sed -i "s#^executables = /usr/bin/php.*#executables = $execs#" /etc/jailkit/jk_init.ini
	echo "  jk_init.ini [php] executables now: $execs"

	rm -rf /home/jail/chroot/template
	mkdir /home/jail/chroot/template
	jk_init -c /etc/jailkit/jk_init.ini -j /home/jail/chroot/template extendedshell limitedshell groups sftp rsync editors git php mysqlclient
	mkdir -p /home/jail/chroot/template/home /home/jail/chroot/template/tmp
	chmod 1777 /home/jail/chroot/template/tmp

	templatesdir=$(grep '^templatesdir=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
	if [[ "x$templatesdir" != "x" ]]; then
		cd /home/jail/chroot
		tar --zstd -cf commonjail.tar.zst template
		tar --zstd -cf privatejail.tar.zst template
		backupdir="$templatesdir/old-jail-backup-$(date +%Y%m%d-%H%M%S)"
		mkdir -p "$backupdir"
		[ -f "$templatesdir/commonjail.tar.zst" ] && mv "$templatesdir/commonjail.tar.zst" "$backupdir/"
		[ -f "$templatesdir/privatejail.tar.zst" ] && mv "$templatesdir/privatejail.tar.zst" "$backupdir/"
		cp commonjail.tar.zst privatejail.tar.zst "$templatesdir/"
		chown nobody:nogroup "$templatesdir/commonjail.tar.zst" "$templatesdir/privatejail.tar.zst" 2>/dev/null || true
	fi

	# /etc/passwd, /etc/group (and their -/shadow variants) are NOT part of the software
	# refreshed here - jk_init only ever seeds them with root/system accounts, and every real
	# jailed user then gets added to the LIVE jail's own copy by jk_jailuser at deploy time
	# (see action_deploy_undeploy.sh). Syncing the freshly rebuilt template's minimal versions
	# over them would wipe out every already-deployed user, breaking their SSH/SFTP access and
	# any cron (eg. backups) that logs in as them - found and fixed the hard way on this exact
	# set of servers, see the commit history.
	jailidentityexcludes=(--exclude=/etc/passwd --exclude=/etc/passwd- --exclude=/etc/group --exclude=/etc/group- --exclude=/etc/shadow --exclude=/etc/shadow- --exclude=/etc/gshadow --exclude=/etc/gshadow-)

	if [ -d /home/jail/chroot/commonjail ]; then
		rsync -a "${jailidentityexcludes[@]}" /home/jail/chroot/template/ /home/jail/chroot/commonjail/
		echo "  synced into the live commonjail"
	fi

	# Instances with sshaccesstype=2 (PrivateUserJail) each got their own full copy of this
	# same template at deploy time (extracted from privatejail.tar.zst, then renamed to
	# $chrootdir/$osusername) instead of sharing commonjail - refresh every one of them too,
	# or they silently keep whatever PHP versions were available on the day they were deployed.
	if [[ "x$chrootdir" != "x" && -d "$chrootdir" ]]; then
		for onejail in "$chrootdir"/*/; do
			onejail=${onejail%/}
			base=$(basename "$onejail")
			case "$base" in
				commonjail|template) continue ;;
			esac
			[ -d "$onejail/usr/bin" ] || continue
			rsync -a "${jailidentityexcludes[@]}" /home/jail/chroot/template/ "$onejail/"
			echo "  synced into the private jail $onejail"
		done
	fi
fi

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Updating this server's Deployment Server card on the master server"
ownip=$(grep '^ipserverdeployment=' /etc/sellyoursaas.conf | cut -d '=' -f 2)
if [[ "x$ownip" == "x" ]]; then
	echo "Warning: ipserverdeployment= is not set in /etc/sellyoursaas.conf, skipping the Deployment Server card update - set phpversiondefault/phpversionsavailable/phpversionoverride by hand, or add ipserverdeployment= and re-run this script." 1>&2
else
	# Same detection command as the "Detect PHP versions" button (action "listphpversions"
	# in scripts/remote_server/index.php), so the card ends up exactly as if that button had
	# been clicked - this script is the single source of truth for this server's PHP-FPM setup.
	phpversionsfound=$(ls -1 /usr/sbin/php-fpm* 2>/dev/null | grep -oP 'php-fpm\K[0-9]+\.[0-9]+$' | sort -u | paste -sd, -)
	mastermysql "UPDATE ${dbprefix}sellyoursaas_deploymentserver SET phpversionsavailable='$phpversionsfound', phpversiondefault='$defaultphpversion', phpversionoverride=1 WHERE ipaddress='$ownip'"
	echo "  phpversionsavailable=$phpversionsfound, phpversiondefault=$defaultphpversion, phpversionoverride=1 (ipaddress=$ownip)"
fi

echo "$(date +'%Y-%m-%d %H:%M:%S') ***** Done. This server is now ready: switch existing instances individually"
echo "(contract PHP version field, or scripts/migrate_server_http2.sh once all of them are on PHP-FPM)."
