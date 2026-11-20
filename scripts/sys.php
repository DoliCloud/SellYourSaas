#!/usr/bin/env php
<?php
/* Copyright (C) 2026 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 * or see http://www.gnu.org/
 */

/**
 *      \file       sellyoursaas/scripts/sys.php
 *      \ingroup    sellyoursaas
 *      \brief      Text UI (Midnight Commander like) to choose a SellYourSaas desktop tool (scripts/desktop_*),
 *                  fill its parameters (ansible inventories and hosts are proposed) and get the command line to launch.
 *                  This tool never executes the command, it only builds it so you can copy/paste it.
 */

if (php_sapi_name() != 'cli') {
	echo "Error: This script must be run with PHP CLI.\n";
	exit(1);
}

$scriptdir = __DIR__;
$ansibledir = $scriptdir.'/ansible';
$selfname = basename(__FILE__);

if (isset($argv[1]) && in_array($argv[1], array('-h', '--help'))) {
	print "Usage: ".$selfname."\n";
	print "Text UI to choose a SellYourSaas desktop tool (scripts/desktop_*), fill its parameters and get the command\n";
	print "line to launch. The command is never executed: copy it (key c or F5) or quit (F10) to get it printed.\n";
	exit(0);
}
if (!function_exists('posix_isatty') || !posix_isatty(STDIN) || !posix_isatty(STDOUT)) {
	if (function_exists('posix_isatty')) {
		fwrite(STDERR, "Error: This script must be run in an interactive terminal.\n");
		exit(1);
	}
}

// Colors (Midnight Commander like)
define('C_RESET', "\033[0m");
define('C_TEXT', "\033[0;37;44m");
define('C_BOLD', "\033[1;37;44m");
define('C_HIGHLIGHT', "\033[1;33;44m");
define('C_DIM', "\033[0;36;44m");
define('C_WARN', "\033[1;33;41m");
define('C_OK', "\033[1;38;5;46;44m");		// 256 colors green, not changed by the palette of the terminal theme
define('C_BORDER', "\033[0;37;44m");
define('C_BORDER_FOCUS', "\033[1;37;44m");
define('C_TITLE', "\033[0;37;44m");
define('C_TITLE_FOCUS', "\033[0;30;46m");
define('C_CURSOR', "\033[0;30;46m");
define('C_CURSOR_NOFOCUS', "\033[0;30;47m");
define('C_BAR', "\033[0;30;46m");
define('C_KEY', "\033[0;37;40m");


/**
 * Return the definitions of the known desktop tools.
 * Param types: hostfile, target, choice, text, kv (named key=value), cwd (directory where to run), raw.
 *
 * @return	array<string,array<string,mixed>>	Definitions indexed by script file name
 */
function getToolDefinitions()
{
	$targethelp = "Group(s) or server name(s) of the inventory. Space to select several.";
	$hostfilehelp = "Ansible inventory: 'xxx' means the file scripts/ansible/hosts-xxx.";

	return array(
		'desktop_apt_upgrade.sh' => array(
			'summary' => 'apt update/upgrade on remote servers',
			'desc' => "Launch an apt update and apt upgrade on the remote servers (playbook launch_apt_upgrade.yml).\nOn deployment servers, all instances are switched in maintenance mode during the upgrade and switched back online at the end. Optionally reboot the servers.\nThe script waits for a key press before starting.",
			'usage' => 'hostfile target [reboot]',
			'notes' => array("All instances of the targeted servers are offline during the run. If interrupted, restore them with deployment_make_instances_offlineonline.sh maintenance.php online.", "Check afterwards that the web server is still alive."),
			'params' => array(
				array('name' => 'hostfile', 'type' => 'hostfile', 'required' => 1, 'help' => $hostfilehelp),
				array('name' => 'target', 'type' => 'target', 'required' => 1, 'default' => 'master', 'help' => $targethelp),
				array('name' => 'reboot', 'type' => 'choice', 'values' => array('' => 'no reboot', 'reboot' => 'reboot servers after upgrade'), 'help' => 'Reboot the servers after the upgrade.'),
			),
		),
		'desktop_clean.sh' => array(
			'summary' => 'Run clean.sh on remote servers',
			'desc' => "Launch the script scripts/clean.sh on the remote servers (playbook launch_clean.yml) to clean old files, temporary files and archives of removed instances.",
			'usage' => 'hostfile target [confirm|test]',
			'notes' => array("Use the mode 'test' first for a dry run."),
			'params' => array(
				array('name' => 'hostfile', 'type' => 'hostfile', 'required' => 1, 'help' => $hostfilehelp),
				array('name' => 'target', 'type' => 'target', 'required' => 1, 'default' => 'deployment', 'help' => $targethelp),
				array('name' => 'mode', 'type' => 'choice', 'default' => 'test', 'values' => array('test' => 'dry run, nothing is deleted', 'confirm' => 'really clean'), 'help' => "Use 'test' for a dry run, 'confirm' to really clean."),
			),
		),
		'desktop_update_sellyoursaas.sh' => array(
			'summary' => 'Git update of SellYourSaas on servers',
			'desc' => "Launch scripts/git_update_sellyoursaas.sh /home/admin/wwwroot/ on the remote servers (playbook launch_git_update_sellyoursaas.yml) to update the sources of Dolibarr and SellYourSaas.\nWith 'unlock', the file dolibarr_documents/upgrade.unlock is also created.",
			'usage' => 'hostfile [target] [unlock]',
			'params' => array(
				array('name' => 'hostfile', 'type' => 'hostfile', 'required' => 1, 'help' => $hostfilehelp),
				array('name' => 'target', 'type' => 'target', 'default' => 'master,deployment', 'help' => $targethelp),
				array('name' => 'unlock', 'type' => 'choice', 'values' => array('' => 'do not create upgrade.unlock', 'unlock' => 'create dolibarr_documents/upgrade.unlock'), 'help' => 'Create the upgrade.unlock file after the update.'),
			),
		),
		'desktop_config_dolibarr.sh' => array(
			'summary' => 'Change a parameter of Dolibarr conf.php',
			'desc' => "Set \$param = value into the Dolibarr config file /home/admin/wwwroot/dolibarr/htdocs/conf/conf.php of the remote servers (playbook change_config_dolibarr.yml).\nNot for the conf files of deployed instances.",
			'usage' => 'hostfile param value [target]',
			'params' => array(
				array('name' => 'hostfile', 'type' => 'hostfile', 'required' => 1, 'help' => $hostfilehelp),
				array('name' => 'param', 'type' => 'text', 'required' => 1, 'values' => array('dolibarr_main_data_root' => '', 'dolibarr_main_url_root' => '', 'dolibarr_main_document_root' => '', 'dolibarr_main_db_host' => '', 'dolibarr_main_db_port' => '', 'dolibarr_main_prod' => '', 'dolibarr_main_force_https' => '', 'dolibarr_mailing_limit_sendbyweb' => '', 'dolibarr_mailing_limit_sendbycli' => ''), 'help' => 'Name of the variable in conf.php (without $). Type to filter or enter another name.'),
				array('name' => 'value', 'type' => 'text', 'required' => 1, 'help' => 'New value of the variable. Type the value then Enter.'),
				array('name' => 'target', 'type' => 'target', 'default' => 'deployment', 'help' => $targethelp),
			),
		),
		'desktop_config_sellyoursaas.sh' => array(
			'summary' => 'Change a parameter of /etc/sellyoursaas.conf',
			'desc' => "Set param=value into the SellYourSaas config file /etc/sellyoursaas.conf (default) or /etc/sellyoursaas-public.conf of the remote servers (playbook change_config_sellyoursaas.yml).",
			'usage' => 'hostfile target param value [sellyoursaas|sellyoursaas-public]',
			'notes' => array('The argument order differs from desktop_config_dolibarr.sh (target is the 2nd argument).'),
			'params' => array(
				array('name' => 'hostfile', 'type' => 'hostfile', 'required' => 1, 'help' => $hostfilehelp),
				array('name' => 'target', 'type' => 'target', 'required' => 1, 'default' => 'master,deployment', 'help' => $targethelp),
				array('name' => 'param', 'type' => 'text', 'required' => 1, 'values' => array('usecompressformatforarchive' => '', 'remotebackupserverport' => '', 'maxemailperday' => '', 'maxemailperdaypaid' => '', 'instanceserver' => '', 'ipserverdeployment' => '', 'masterserver' => '', 'webserver' => '', 'backupdir' => '', 'remotebackupdir' => ''), 'help' => 'Name of the parameter in the conf file. Type to filter or enter another name.'),
				array('name' => 'value', 'type' => 'text', 'required' => 1, 'help' => 'New value of the parameter. Type the value then Enter.'),
				array('name' => 'conffile', 'type' => 'choice', 'values' => array('' => '/etc/sellyoursaas.conf (default)', 'sellyoursaas' => '/etc/sellyoursaas.conf', 'sellyoursaas-public' => '/etc/sellyoursaas-public.conf'), 'help' => 'Config file to change.'),
			),
		),
		'desktop_crontab_server.sh' => array(
			'summary' => 'Fix the crontab of remote servers',
			'desc' => "Edit the crontab /var/spool/cron/crontabs/<username> of the remote servers (playbook change_crontab_server.yml) to fix known lines (backup_backup.sh, perms.ksh -> perms.sh).",
			'usage' => 'hostfile target root|osusername',
			'params' => array(
				array('name' => 'hostfile', 'type' => 'hostfile', 'required' => 1, 'help' => $hostfilehelp),
				array('name' => 'target', 'type' => 'target', 'required' => 1, 'help' => $targethelp),
				array('name' => 'username', 'type' => 'text', 'required' => 1, 'values' => array('root' => '', 'admin' => ''), 'help' => 'Unix user owning the crontab to fix.'),
			),
		),
		'desktop_bannedip.sh' => array(
			'summary' => 'List/ban/unban IPs with fail2ban',
			'desc' => "Manage fail2ban on the remote servers:\n- list: show the whitelisted (ignoreip) and banned IPs of each jail,\n- unban: remove an IP from all jails,\n- ban: ban an IP into a given jail.",
			'usage' => 'hostfile target list|unban|ban [IP] [jail]',
			'params' => array(
				array('name' => 'hostfile', 'type' => 'hostfile', 'required' => 1, 'help' => $hostfilehelp),
				array('name' => 'target', 'type' => 'target', 'required' => 1, 'help' => $targethelp),
				array('name' => 'action', 'type' => 'choice', 'required' => 1, 'default' => 'list', 'values' => array('list' => 'list whitelisted and banned IPs', 'unban' => 'unban an IP from all jails', 'ban' => 'ban an IP into a jail'), 'help' => 'Action to do.'),
				array('name' => 'ip', 'type' => 'text', 'requiredif' => array('action' => array('ban', 'unban')), 'help' => 'IP to ban or unban.'),
				array('name' => 'jail', 'type' => 'text', 'requiredif' => array('action' => array('ban')), 'values' => array('sshd' => '', 'apache-shellshock' => '', 'web-dol-bruteforce' => '', 'web-accesslog-limit403' => '', 'email-dol-blacklist' => '', 'email-dol-perso' => '', 'email-dol-perso-perday' => ''), 'help' => 'Jail where to ban the IP (for action ban only).'),
			),
		),
		'desktop_info_servers.sh' => array(
			'summary' => 'Get OS, PHP and sudo users of servers',
			'desc' => "Get information on the remote servers, read only (playbook info_servers.yml):\n- OS and precise version (and kernel),\n- PHP version (CLI, installed branches and PHP used by Apache),\n- users allowed to sudo as root, with their sudo rule,\n- users allowed to sudo as admin (sudo su - admin), with their sudo rule.",
			'usage' => 'hostfile [target]',
			'params' => array(
				array('name' => 'hostfile', 'type' => 'hostfile', 'required' => 1, 'help' => $hostfilehelp),
				array('name' => 'target', 'type' => 'target', 'default' => 'all', 'help' => $targethelp." 'all' for all servers of the inventory."),
			),
		),
		'desktop_install_check.sh' => array(
			'summary' => 'Check/fix the setup of servers',
			'desc' => "Check and fix the setup of master and deployment servers (playbook launch_install_check.yml): OS accounts (admin groups), PAM password policy, apt packages, and optionally apache or a given PHP version ('all' = apache + php74).",
			'usage' => 'hostfile [target] [apache|php70..php84|all]',
			'notes' => array("Targets 'web' and 'remotebackup' are refused."),
			'params' => array(
				array('name' => 'hostfile', 'type' => 'hostfile', 'required' => 1, 'help' => $hostfilehelp),
				array('name' => 'target', 'type' => 'target', 'default' => 'master', 'groups' => array('master', 'deployment'), 'help' => "Group(s) master, deployment or server name(s). Space to select several."),
				array('name' => 'option', 'type' => 'choice', 'values' => array('' => 'base checks only', 'apache' => 'also check apache', 'php70' => '', 'php72' => '', 'php74' => '', 'php80' => '', 'php81' => '', 'php82' => '', 'php83' => '', 'php84' => '', 'all' => 'apache + php74'), 'help' => 'Optional additional component to check.'),
			),
		),
		'desktop_deploy_module.sh' => array(
			'summary' => 'Deploy/update a module on instances',
			'desc' => "Run scripts/deployment_deploy_module.php on the deployment servers (playbook launch_deployment_deploy_module.yml) to deploy or update the package of the service 'productref' (e.g. CLOUD-MOD-EInvoicing) on the instances matching 'instancefilter' (trials included).\n'master_instance_id' is the value of \$dolibarr_main_instance_unique_id in conf.php of the master server.",
			'usage' => 'hostsfile target test|confirm productref instancefilter master_instance_id [countrycode] [nocache]',
			'notes' => array("Use 'test' first.", 'The master_instance_id is a secret: it will be visible in your shell history and in the process list of the servers.', 'Add nocache after an update of the module, otherwise the cached archive is deployed again.'),
			'atleastone' => array('countrycode', 'nocache'),
			'params' => array(
				array('name' => 'hostsfile', 'type' => 'hostfile', 'required' => 1, 'help' => $hostfilehelp),
				array('name' => 'target', 'type' => 'target', 'required' => 1, 'default' => 'deployment', 'groups' => array('deployment'), 'help' => "Group deployment or name(s) of deployment server(s). Space to select several."),
				array('name' => 'command', 'type' => 'choice', 'required' => 1, 'default' => 'test', 'values' => array('test' => 'dry run', 'confirm' => 'really deploy'), 'help' => "Use 'test' first, then 'confirm'."),
				array('name' => 'productref', 'type' => 'text', 'required' => 1, 'values' => array('CLOUD-MOD-EInvoicing' => ''), 'help' => "Ref of the service (option with a package) on the master, e.g. CLOUD-MOD-EInvoicing."),
				array('name' => 'instancefilter', 'type' => 'text', 'required' => 1, 'values' => array('*' => 'all instances', 'abc*' => 'instances starting with abc'), 'help' => "Filter on instance names, e.g. 'abc*' (it will be quoted)."),
				array('name' => 'master_instance_id', 'type' => 'text', 'required' => 1, 'help' => 'Value of $dolibarr_main_instance_unique_id of the master server (secret).'),
				array('name' => 'countrycode', 'type' => 'text', 'values' => array('FR' => '', 'BE' => '', 'CH' => '', 'ES' => '', 'IT' => '', 'DE' => ''), 'help' => 'Country code. countrycode or nocache must be provided.'),
				array('name' => 'nocache', 'type' => 'choice', 'values' => array('' => 'use the cached archive', 'nocache' => 'rebuild the cached archive'), 'help' => 'Force the rebuild of the cached archive of the package.'),
			),
		),
		'desktop_admin_users.php' => array(
			'summary' => 'Manage unix sysadmin users on servers',
			'desc' => "Create, update, deactivate, reactivate or remove a personal unix sysadmin account on all the servers (playbooks user_create.yml, user_deactivate.yml, user_reactivate.yml, user_remove.yml).\nThe password is hashed locally (SHA-512) before being sent to Ansible.",
			'usage' => 'hostfile target create|update|deactivate|reactivate|remove login [loginforansible] [userroot=0|1] [userip=...] [userpublickey="..."] [userpassword="..."]',
			'notes' => array('create requires userroot. userroot=1 also requires userpassword.', 'A password given here is visible in your shell history.'),
			'params' => array(
				array('name' => 'hostfile', 'type' => 'hostfile', 'required' => 1, 'help' => $hostfilehelp),
				array('name' => 'target', 'type' => 'target', 'required' => 1, 'default' => 'master,deployment,web', 'help' => $targethelp),
				array('name' => 'action', 'type' => 'choice', 'required' => 1, 'values' => array('create' => '', 'update' => '', 'deactivate' => '', 'reactivate' => '', 'remove' => ''), 'help' => 'Action on the user account.'),
				array('name' => 'login', 'type' => 'text', 'required' => 1, 'help' => 'Login of the unix user to manage.'),
				array('name' => 'loginforansible', 'type' => 'text', 'help' => 'Optional SSH login used by Ansible (default: your current login).'),
				array('name' => 'userroot', 'type' => 'kv', 'requiredif' => array('action' => array('create')), 'values' => array('0' => 'no root access', '1' => 'root access allowed (requires userpassword)'), 'help' => 'Allow the user to become root.'),
				array('name' => 'userip', 'type' => 'kv', 'help' => 'IP allowed to connect with this user.'),
				array('name' => 'userpublickey', 'type' => 'kv', 'help' => 'SSH public key of the user (e.g. ssh-ed25519 AAAA...).'),
				array('name' => 'userpassword', 'type' => 'kv', 'requiredif' => array('userroot' => array('1')), 'help' => 'Password of the user (mandatory if userroot=1).'),
			),
		),
		'desktop_switchipv6.sh' => array(
			'summary' => 'Enable/disable IPv6 on the desktop',
			'desc' => "Local tool (no Ansible): enable or disable IPv6 on your desktop with sysctl, then show the network interfaces. Needs root.",
			'usage' => 'sudo desktop_switchipv6.sh enable|disable',
			'prefix' => 'sudo ',
			'params' => array(
				array('name' => 'action', 'type' => 'choice', 'required' => 1, 'values' => array('enable' => 'enable IPv6', 'disable' => 'disable IPv6'), 'help' => 'Enable or disable IPv6.'),
			),
		),
		'desktop_fix_unzip.php' => array(
			'summary' => 'Fix a badly unzipped archive',
			'desc' => "Local tool (no Ansible): fix the content of a directory where an archive was badly unzipped (e.g. a Windows zip giving files named dir\\sub\\file). It recreates the sub-directories, moves the files into them, then removes empty directories.\nIt works on the current directory.",
			'usage' => 'cd dirtofix && desktop_fix_unzip.php',
			'params' => array(
				array('name' => 'directory', 'type' => 'cwd', 'required' => 1, 'help' => 'Directory to fix (the script is run from it). Type the path then Enter.'),
			),
		),
	);
}

/**
 * Return the list of desktop tools found in the scripts directory, with their definition.
 *
 * @param	string	$scriptdir	Directory of scripts
 * @param	string	$selfname	Name of this script (excluded)
 * @return	array<int,array<string,mixed>>
 */
function getTools($scriptdir, $selfname)
{
	$definitions = getToolDefinitions();
	$tools = array();
	$files = glob($scriptdir.'/desktop_*');
	sort($files);
	foreach ($files as $file) {
		$name = basename($file);
		if ($name == $selfname || !is_file($file) || preg_match('/(~|\.bak|\.orig)$/', $name)) {
			continue;
		}
		if (isset($definitions[$name])) {
			$tool = $definitions[$name];
		} else {
			// Unknown tool: use the header comment of the script as description
			$tool = array('summary' => '', 'desc' => getHeaderComment($file), 'usage' => '', 'params' => array(
				array('name' => 'arguments', 'type' => 'raw', 'help' => 'Arguments added as is to the command line (no description available for this tool).'),
			));
		}
		$tool['name'] = $name;
		$tool['file'] = $file;
		$tools[] = $tool;
	}
	return $tools;
}

/**
 * Get the header comment of a shell or PHP script.
 *
 * @param	string	$file	Path of script
 * @return	string			Description
 */
function getHeaderComment($file)
{
	$lines = @file($file);
	$desc = array();
	if (is_array($lines)) {
		foreach (array_slice($lines, 1, 30) as $line) {
			$line = trim($line);
			if (preg_match('/^(#|\*|\/\*)\s*(\\\\brief)?\s*(.*)$/', $line, $reg)) {
				$text = trim($reg[3]);
				if ($text === '' || preg_match('/^[-=*\/]+$/', $text) || preg_match('/(copyright|license|gnu|warranty|program|\\\\file|\\\\ingroup)/i', $text)) {
					continue;
				}
				$desc[] = $text;
			} elseif (count($desc)) {
				break;
			}
		}
	}
	return count($desc) ? implode("\n", $desc) : 'No description available.';
}

/**
 * Return the list of ansible inventory suffixes (scripts/ansible/hosts-xxx => xxx).
 *
 * @param	string	$ansibledir		Ansible directory
 * @return	array<string,string>	Suffix => label
 */
function getHostfiles($ansibledir)
{
	$result = array();
	$files = glob($ansibledir.'/hosts-*');
	sort($files);
	foreach ($files as $file) {
		$suffix = preg_replace('/^hosts-/', '', basename($file));
		if (!is_file($file) || preg_match('/(~|\.bak|\.orig|\.swp)$/', $suffix)) {
			continue;
		}
		$inventory = parseInventory($ansibledir, $suffix);
		$nb = 0;
		foreach ($inventory as $hosts) {
			$nb += count($hosts);
		}
		$result[$suffix] = 'hosts-'.$suffix.' ('.count($inventory).' groups, '.$nb.' hosts)';
	}
	return $result;
}

/**
 * Parse an ansible inventory file (ini format).
 *
 * @param	string	$ansibledir		Ansible directory
 * @param	string	$suffix			Suffix of inventory file
 * @return	array<string,string[]>	Group => list of hosts
 */
function parseInventory($ansibledir, $suffix)
{
	static $cache = array();
	if (isset($cache[$suffix])) {
		return $cache[$suffix];
	}
	$groups = array();
	$file = $ansibledir.'/hosts-'.$suffix;
	$lines = (preg_match('/^[a-zA-Z0-9_.-]+$/', $suffix) && is_file($file)) ? @file($file) : false;
	if (is_array($lines)) {
		$current = '';
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '' || $line[0] == '#' || $line[0] == ';') {
				continue;
			}
			if (preg_match('/^\[([^\]]+)\]/', $line, $reg)) {
				$current = $reg[1];
				if (strpos($current, ':vars') !== false) {
					$current = '';		// Variables section, not hosts
				} else {
					$current = preg_replace('/:children$/', '', $current);
					if (!isset($groups[$current])) {
						$groups[$current] = array();
					}
				}
				continue;
			}
			if ($current !== '') {
				$tmp = preg_split('/\s+/', $line);
				$groups[$current][] = $tmp[0];
			}
		}
	}
	$cache[$suffix] = $groups;
	return $groups;
}


/*
 * Text helpers
 */

/**
 * Visible width of a string
 *
 * @param	string	$s	String
 * @return	int
 */
function strWidth($s)
{
	return function_exists('mb_strwidth') ? mb_strwidth($s, 'UTF-8') : strlen($s);
}

/**
 * Truncate a string to a visible width
 *
 * @param	string	$s	String
 * @param	int		$w	Width
 * @return	string
 */
function truncText($s, $w)
{
	if ($w <= 0) {
		return '';
	}
	if (strWidth($s) <= $w) {
		return $s;
	}
	return function_exists('mb_strimwidth') ? mb_strimwidth($s, 0, $w, '', 'UTF-8') : substr($s, 0, $w);
}

/**
 * Truncate then pad a string to an exact visible width
 *
 * @param	string	$s	String
 * @param	int		$w	Width
 * @return	string
 */
function fitText($s, $w)
{
	$s = truncText($s, $w);
	return $s.str_repeat(' ', max(0, $w - strWidth($s)));
}

/**
 * Word wrap a text (multi lines) to a visible width
 *
 * @param	string	$text	Text
 * @param	int		$w		Width
 * @return	string[]
 */
function wrapText($text, $w)
{
	$result = array();
	foreach (explode("\n", $text) as $paragraph) {
		$line = '';
		$indent = preg_match('/^(-\s+)/', $paragraph, $reg) ? str_repeat(' ', strlen($reg[1])) : '';
		foreach (preg_split('/ +/', $paragraph) as $word) {
			if ($line !== '' && strWidth($line.' '.$word) > $w) {
				$result[] = $line;
				$line = $indent.$word;
			} else {
				$line = ($line === '' ? $word : $line.' '.$word);
			}
			while (strWidth($line) > $w) {
				$result[] = truncText($line, $w);
				$line = function_exists('mb_strimwidth') ? mb_substr($line, mb_strlen(truncText($line, $w), 'UTF-8'), null, 'UTF-8') : substr($line, $w);
			}
		}
		$result[] = $line;
	}
	return $result;
}

/**
 * Hard wrap a string (no word boundaries), used for the command line
 *
 * @param	string	$s	String
 * @param	int		$w	Width
 * @return	string[]
 */
function hardWrap($s, $w)
{
	$result = array();
	if ($w <= 0) {
		return $result;
	}
	while (strWidth($s) > $w) {
		$part = truncText($s, $w);
		$result[] = $part;
		$s = substr($s, strlen($part));
	}
	$result[] = $s;
	return $result;
}

/**
 * Quote a shell argument only when needed
 *
 * @param	string	$s	Argument
 * @return	string
 */
function shellQuote($s)
{
	if ($s === '') {
		return "''";
	}
	if (preg_match('/^[a-zA-Z0-9_.,:\/=@%+-]+$/', $s)) {
		return $s;
	}
	return escapeshellarg($s);
}


/*
 * Command line building
 */

/**
 * Return if a parameter is mandatory, given the other values
 *
 * @param	array<string,mixed>		$param	Parameter definition
 * @param	array<string,string>	$vals	Current values
 * @return	bool
 */
function isParamRequired($param, $vals)
{
	if (!empty($param['required'])) {
		return true;
	}
	if (!empty($param['requiredif'])) {
		foreach ($param['requiredif'] as $otherparam => $othervalues) {
			if (isset($vals[$otherparam]) && in_array($vals[$otherparam], $othervalues, true)) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Return the path of a script to show in the command line (relative to the current directory when possible)
 *
 * @param	string	$file		Absolute path of script
 * @param	bool	$absolute	Force absolute path
 * @return	string
 */
function getScriptPathForCommand($file, $absolute = false)
{
	$cwd = getcwd();
	if (!$absolute && $cwd && strpos($file, $cwd.'/') === 0) {
		return './'.substr($file, strlen($cwd) + 1);
	}
	return shellQuote($file);
}

/**
 * Build the command line of a tool
 *
 * @param	array<string,mixed>		$tool		Tool definition
 * @param	array<string,string>	$vals		Values of parameters
 * @param	string[]				$warnings	Returned list of warnings
 * @return	string							Command line
 */
function buildCommand($tool, $vals, &$warnings)
{
	$warnings = array();
	$args = array();
	$kvs = array();
	$cwd = null;
	foreach ($tool['params'] as $param) {
		$v = isset($vals[$param['name']]) ? $vals[$param['name']] : '';
		$required = isParamRequired($param, $vals);
		if ($v === '' && $required) {
			$warnings[] = 'Missing mandatory parameter: '.$param['name'];
		}
		if ($param['type'] == 'kv') {
			if ($v !== '') {
				$kvs[] = shellQuote($param['name'].'='.$v);
			}
		} elseif ($param['type'] == 'cwd') {
			$cwd = $v;
		} else {
			$args[] = array('value' => $v, 'param' => $param, 'required' => $required);
		}
	}
	if (!empty($tool['atleastone'])) {
		$found = false;
		foreach ($tool['atleastone'] as $name) {
			if (isset($vals[$name]) && $vals[$name] !== '') {
				$found = true;
			}
		}
		if (!$found) {
			$warnings[] = 'At least one of these parameters must be set: '.implode(', ', $tool['atleastone']);
		}
	}

	// Remove trailing empty optional arguments
	while (count($args) && $args[count($args) - 1]['value'] === '' && !$args[count($args) - 1]['required']) {
		array_pop($args);
	}
	$parts = array();
	foreach ($args as $arg) {
		if ($arg['value'] === '') {
			if ($arg['required']) {
				$parts[] = '<'.$arg['param']['name'].'>';
			} elseif (isset($arg['param']['default']) && $arg['param']['default'] !== '') {
				$parts[] = shellQuote($arg['param']['default']);	// An empty positional argument is not possible
			}
			continue;
		}
		$parts[] = ($arg['param']['type'] == 'raw') ? $arg['value'] : shellQuote($arg['value']);
	}
	$parts = array_merge($parts, $kvs);

	$prefix = empty($tool['prefix']) ? '' : $tool['prefix'];
	if ($cwd !== null) {
		$command = 'cd '.($cwd === '' ? '<directory>' : shellQuote($cwd)).' && '.$prefix.getScriptPathForCommand($tool['file'], true);
	} else {
		$command = $prefix.getScriptPathForCommand($tool['file']);
	}
	if (count($parts)) {
		$command .= ' '.implode(' ', $parts);
	}
	return $command;
}


/*
 * Terminal
 */

/**
 * Class to manage the text UI
 */
class DesktopMenu
{
	/** @var array<int,array<string,mixed>> */
	public $tools = array();
	/** @var string */
	public $ansibledir;
	/** @var array<string,array<string,string>> */
	public $values = array();
	/** @var string tools|params|values|cmd|desc */
	public $focus = 'tools';
	/** @var int */
	public $toolIdx = 0;
	/** @var int */
	public $toolOffset = 0;
	/** @var int */
	public $paramIdx = 0;
	/** @var int */
	public $valueIdx = 0;
	/** @var int */
	public $valueOffset = 0;
	/** @var int */
	public $paramOffset = 0;
	/** @var int */
	public $descOffset = 0;
	/** @var string */
	public $filter = '';
	/** @var string */
	public $status = '';
	/** @var string */
	public $lastHostfile = '';
	/** @var int */
	public $rows = 24;
	/** @var int */
	public $cols = 80;
	/** @var string */
	private $sttySaved = '';
	/** @var bool */
	private $resized = false;
	/** @var bool True when values were toggled with Space (Enter then validates the list) */
	public $multiTouched = false;
	/** @var string Input bytes not yet parsed */
	private $inbuf = '';

	/**
	 * Constructor
	 *
	 * @param	array<int,array<string,mixed>>	$tools		Tools
	 * @param	string							$ansibledir	Ansible dir
	 */
	public function __construct($tools, $ansibledir)
	{
		$this->tools = $tools;
		$this->ansibledir = $ansibledir;
		$hostfiles = getHostfiles($ansibledir);
		if (count($hostfiles) == 1) {
			$this->lastHostfile = (string) key($hostfiles);
		}
		$this->initToolValues();
	}

	/**
	 * Init the values of the current tool (defaults, last used hostfile)
	 *
	 * @return void
	 */
	public function initToolValues()
	{
		if (!count($this->tools)) {
			return;
		}
		$tool = $this->tools[$this->toolIdx];
		if (!isset($this->values[$tool['name']])) {
			$this->values[$tool['name']] = array();
			foreach ($tool['params'] as $param) {
				$this->values[$tool['name']][$param['name']] = isset($param['default']) ? $param['default'] : '';
			}
		}
		foreach ($tool['params'] as $param) {
			if ($param['type'] == 'hostfile' && $this->values[$tool['name']][$param['name']] === '') {
				$this->values[$tool['name']][$param['name']] = $this->lastHostfile;
			}
		}
		$this->paramIdx = 0;
		$this->paramOffset = 0;
		$this->resetValueList();
	}

	/**
	 * Reset the list of proposed values
	 *
	 * @return void
	 */
	public function resetValueList()
	{
		$this->filter = '';
		$this->valueIdx = 0;
		$this->valueOffset = 0;
		$this->multiTouched = false;
	}

	/**
	 * Current tool
	 *
	 * @return array<string,mixed>
	 */
	public function currentTool()
	{
		return $this->tools[$this->toolIdx];
	}

	/**
	 * Current parameter or null
	 *
	 * @return array<string,mixed>|null
	 */
	public function currentParam()
	{
		$tool = $this->currentTool();
		return isset($tool['params'][$this->paramIdx]) ? $tool['params'][$this->paramIdx] : null;
	}

	/**
	 * Get the value of a parameter of current tool
	 *
	 * @param	string	$name	Param name
	 * @return	string
	 */
	public function getValue($name)
	{
		$tool = $this->currentTool();
		return isset($this->values[$tool['name']][$name]) ? $this->values[$tool['name']][$name] : '';
	}

	/**
	 * Set the value of a parameter of current tool
	 *
	 * @param	string	$name	Param name
	 * @param	string	$value	Value
	 * @return	void
	 */
	public function setValue($name, $value)
	{
		$tool = $this->currentTool();
		$this->values[$tool['name']][$name] = $value;
		foreach ($tool['params'] as $param) {
			if ($param['name'] == $name && $param['type'] == 'hostfile') {
				$this->lastHostfile = $value;
			}
		}
	}

	/**
	 * Build the list of proposed values for the current parameter
	 *
	 * @return array<int,array<string,mixed>>	List of items (value, label, selectable)
	 */
	public function getValueItems()
	{
		$param = $this->currentParam();
		$items = array();
		if (!$param) {
			return $items;
		}
		$filter = strtolower($this->filter);
		$candidates = array();
		if ($param['type'] == 'hostfile') {
			foreach (getHostfiles($this->ansibledir) as $suffix => $label) {
				$candidates[] = array('value' => $suffix, 'label' => $label, 'search' => $suffix);
			}
			if (!count($candidates)) {
				$items[] = array('value' => '', 'label' => '(no file scripts/ansible/hosts-xxx found, see hosts.example)', 'selectable' => false);
			}
		} elseif ($param['type'] == 'target') {
			$hostfile = '';
			foreach ($this->currentTool()['params'] as $p) {
				if ($p['type'] == 'hostfile') {
					$hostfile = $this->getValue($p['name']);
				}
			}
			$inventory = ($hostfile !== '') ? parseInventory($this->ansibledir, $hostfile) : array();
			if (!count($inventory)) {
				$items[] = array('value' => '', 'label' => ($hostfile === '' ? '(choose a hostfile first to see its servers)' : '(inventory hosts-'.$hostfile.' not found or empty)'), 'selectable' => false);
				foreach (array('master', 'deployment', 'web', 'remotebackup') as $group) {
					$inventory[$group] = array();
				}
			}
			$allowedgroups = empty($param['groups']) ? null : $param['groups'];
			// Ansible pattern 'all' (all hosts of the inventory), only when the groups are not restricted
			if ($allowedgroups === null && !isset($inventory['all'])) {
				$allhosts = array();
				foreach ($inventory as $hosts) {
					$allhosts = array_merge($allhosts, $hosts);
				}
				$nball = count(array_unique($allhosts));
				$candidates[] = array('value' => 'all', 'label' => 'all  [all servers of the inventory, '.$nball.' host'.($nball > 1 ? 's' : '').']', 'search' => 'all');
			}
			foreach ($inventory as $group => $hosts) {
				if ($allowedgroups !== null && !in_array($group, $allowedgroups)) {
					continue;
				}
				$candidates[] = array('value' => $group, 'label' => $group.'  [group, '.count($hosts).' host'.(count($hosts) > 1 ? 's' : '').']', 'search' => $group.' '.implode(' ', $hosts));
			}
			foreach ($inventory as $group => $hosts) {
				if ($allowedgroups !== null && !in_array($group, $allowedgroups)) {
					continue;
				}
				foreach ($hosts as $host) {
					$candidates[] = array('value' => $host, 'label' => $host.'  ('.$group.')', 'search' => $host.' '.$group);
				}
			}
		} elseif (!empty($param['values'])) {
			foreach ($param['values'] as $value => $hint) {
				$value = (string) $value;
				$label = ($value === '' ? '(empty)' : $value).($hint !== '' ? '  - '.$hint : '');
				$candidates[] = array('value' => $value, 'label' => $label, 'search' => $value.' '.$hint);
			}
		}

		// Custom value typed by the user
		if ($this->filter !== '' && !in_array($param['type'], array('hostfile', 'choice'))) {
			$items[] = array('value' => $this->filter, 'label' => '» Use typed value: '.$this->filter, 'selectable' => true);
		}
		$seen = array();
		foreach ($candidates as $candidate) {
			if (isset($seen[$candidate['value']])) {
				continue;
			}
			$seen[$candidate['value']] = 1;
			if ($filter !== '' && strpos(strtolower($candidate['search']), $filter) === false) {
				continue;
			}
			$candidate['selectable'] = true;
			$items[] = $candidate;
		}
		if (!count($items)) {
			if (in_array($param['type'], array('text', 'kv', 'cwd', 'raw', 'target'))) {
				$items[] = array('value' => '', 'label' => '(type a value then Enter)', 'selectable' => false);
			} else {
				$items[] = array('value' => '', 'label' => '(no matching value)', 'selectable' => false);
			}
		}
		return $items;
	}


	/*
	 * Terminal management
	 */

	/**
	 * Enter the full screen mode
	 *
	 * @return void
	 */
	public function start()
	{
		$this->sttySaved = trim((string) shell_exec('stty -g < /dev/tty 2>/dev/null'));
		shell_exec('stty -icanon -echo -isig -ixon min 1 time 0 < /dev/tty 2>/dev/null');
		stream_set_blocking(STDIN, false);
		if (function_exists('pcntl_async_signals') && defined('SIGWINCH')) {
			pcntl_async_signals(true);
			pcntl_signal(SIGWINCH, function () {
				$this->resized = true;
			});
		}
		register_shutdown_function(array($this, 'stop'));
		fwrite(STDOUT, "\033[?1049h\033[?25l");
		$this->updateSize();
	}

	/**
	 * Restore the terminal
	 *
	 * @return void
	 */
	public function stop()
	{
		if ($this->sttySaved === null) {
			return;
		}
		fwrite(STDOUT, C_RESET."\033[?25h\033[?1049l");
		if ($this->sttySaved !== '') {
			shell_exec('stty '.escapeshellarg($this->sttySaved).' < /dev/tty 2>/dev/null');
		} else {
			shell_exec('stty sane < /dev/tty 2>/dev/null');
		}
		stream_set_blocking(STDIN, true);
		$this->sttySaved = null;
	}

	/**
	 * Read the terminal size
	 *
	 * @return bool		True if size has changed
	 */
	public function updateSize()
	{
		$size = trim((string) shell_exec('stty size < /dev/tty 2>/dev/null'));
		$rows = 24;
		$cols = 80;
		if (preg_match('/^(\d+)\s+(\d+)$/', $size, $reg) && (int) $reg[1] > 0 && (int) $reg[2] > 0) {
			$rows = (int) $reg[1];
			$cols = (int) $reg[2];
		}
		$changed = ($rows != $this->rows || $cols != $this->cols);
		$this->rows = $rows;
		$this->cols = $cols;
		return $changed;
	}

	/**
	 * Wait and read a key. Return null on timeout.
	 *
	 * @param	float	$timeout	Timeout in seconds
	 * @return	string|null			Key name (UP, DOWN, ENTER...) or the typed character
	 */
	public function readKey($timeout = 1.0)
	{
		$write = null;
		$except = null;
		if ($this->inbuf === '') {
			$read = array(STDIN);
			$nb = @stream_select($read, $write, $except, (int) $timeout, (int) (($timeout - (int) $timeout) * 1000000));
			if (!$nb) {
				return null;
			}
			$this->inbuf .= (string) fread(STDIN, 1024);
			if ($this->inbuf === '') {
				return null;
			}
		}
		if ($this->inbuf === "\033") {
			// Wait a little to know if it is the Esc key or the start of a sequence
			$read = array(STDIN);
			if (@stream_select($read, $write, $except, 0, 50000)) {
				$this->inbuf .= (string) fread(STDIN, 1024);
			}
		}
		$c = $this->inbuf[0];
		if ($c == "\033") {
			if (preg_match('/^\033(\[[0-9;]*[~A-Za-z]|O[A-Za-z])/', $this->inbuf, $reg)) {
				$seq = $reg[1];
				$this->inbuf = (string) substr($this->inbuf, strlen($reg[0]));
			} else {
				$seq = '';
				$this->inbuf = (string) substr($this->inbuf, ($this->inbuf === "\033\033" ? 2 : 1));
			}
			$map = array(
				'[A' => 'UP', '[B' => 'DOWN', '[C' => 'RIGHT', '[D' => 'LEFT', 'OA' => 'UP', 'OB' => 'DOWN', 'OC' => 'RIGHT', 'OD' => 'LEFT',
				'[5~' => 'PGUP', '[6~' => 'PGDN', '[H' => 'HOME', '[F' => 'END', 'OH' => 'HOME', 'OF' => 'END', '[1~' => 'HOME', '[4~' => 'END',
				'[3~' => 'DEL', '[Z' => 'BTAB', '[15~' => 'F5', '[21~' => 'F10', '' => 'ESC',
			);
			return isset($map[$seq]) ? $map[$seq] : 'UNKNOWN';
		}
		$o = ord($c);
		if ($o >= 0xC0) {
			// UTF-8 multi bytes char
			$len = ($o >= 0xF0) ? 4 : (($o >= 0xE0) ? 3 : 2);
			$c = (string) substr($this->inbuf, 0, $len);
			$this->inbuf = (string) substr($this->inbuf, $len);
			return $c;
		}
		$this->inbuf = (string) substr($this->inbuf, 1);
		$map = array("\t" => 'TAB', "\n" => 'ENTER', "\r" => 'ENTER', "\x7f" => 'BACKSPACE', "\x08" => 'BACKSPACE', "\x03" => 'CTRLC', "\x15" => 'CTRLU', ' ' => 'SPACE', "\x0c" => 'CTRLL');
		if (isset($map[$c])) {
			return $map[$c];
		}
		if ($o < 32) {
			return 'UNKNOWN';
		}
		return $c;
	}


	/*
	 * Rendering
	 */

	/**
	 * Render a box with a title and content lines
	 *
	 * @param	string								$title		Title
	 * @param	array<int,array<string,mixed>>		$lines		Lines: array('text'=>, 'style'=>) or array('parts'=>array(array(style, text))) or array('sep'=>1)
	 * @param	int									$w			Width
	 * @param	int									$h			Height
	 * @param	bool								$focused	Focused
	 * @return	string[]										Rendered lines
	 */
	public function renderBox($title, $lines, $w, $h, $focused)
	{
		$b = $focused ? C_BORDER_FOCUS : C_BORDER;
		$inner = $w - 2;
		$out = array();
		$t = truncText(' '.$title.' ', max(0, $inner - 2));
		$out[] = $b.'┌─'.($focused ? C_TITLE_FOCUS : C_TITLE).$t.$b.str_repeat('─', max(0, $inner - 1 - strWidth($t))).'┐';
		for ($i = 0; $i < $h - 2; $i++) {
			$line = isset($lines[$i]) ? $lines[$i] : array('text' => '');
			if (!empty($line['sep'])) {
				$out[] = $b.'├'.str_repeat('─', $inner).'┤';
				continue;
			}
			if (isset($line['parts'])) {
				$content = '';
				$remain = $inner;
				foreach ($line['parts'] as $part) {
					$txt = truncText($part[1], $remain);
					$content .= $part[0].$txt;
					$remain -= strWidth($txt);
				}
				$content .= C_TEXT.str_repeat(' ', max(0, $remain));
			} else {
				$content = (isset($line['style']) ? $line['style'] : C_TEXT).fitText($line['text'], $inner);
			}
			$out[] = $b.'│'.$content.$b.'│';
		}
		$out[] = $b.'└'.str_repeat('─', $inner).'┘';
		return $out;
	}

	/**
	 * Compute the scroll offset so that the cursor is visible
	 *
	 * @param	int		$cursor		Cursor
	 * @param	int		$offset		Current offset
	 * @param	int		$visible	Number of visible lines
	 * @return	int					New offset
	 */
	public static function scrollOffset($cursor, $offset, $visible)
	{
		if ($visible <= 0) {
			return 0;
		}
		if ($cursor < $offset) {
			$offset = $cursor;
		}
		if ($cursor >= $offset + $visible) {
			$offset = $cursor - $visible + 1;
		}
		return max(0, $offset);
	}

	/**
	 * Return the layout: left width and top height
	 *
	 * @return int[]	array(leftwidth, topheight, bottomheight)
	 */
	public function layout()
	{
		$mid = $this->rows - 2;
		$lw = max(30, min(48, (int) floor($this->cols * 0.42)));
		$th = max(7, min(count($this->tools) + 2, (int) floor($mid * 0.4)));
		$bh = $mid - $th;
		return array($lw, $th, $bh);
	}

	/**
	 * Draw the whole screen
	 *
	 * @return void
	 */
	public function render()
	{
		if ($this->rows < 16 || $this->cols < 60) {
			fwrite(STDOUT, C_RESET."\033[2J\033[H".'Terminal too small (min 60x16). Resize it or press F10 to quit.');
			return;
		}
		list($lw, $th, $bh) = $this->layout();
		$rw = $this->cols - $lw;
		$tool = $this->currentTool();

		// Top left: tools
		$visible = $th - 2;
		$this->toolOffset = self::scrollOffset($this->toolIdx, $this->toolOffset, $visible);
		$lines = array();
		for ($i = $this->toolOffset; $i < min(count($this->tools), $this->toolOffset + $visible); $i++) {
			$style = C_TEXT;
			if ($i == $this->toolIdx) {
				$style = ($this->focus == 'tools') ? C_CURSOR : C_CURSOR_NOFOCUS;
			}
			$lines[] = array('text' => ' '.$this->tools[$i]['name'], 'style' => $style);
		}
		$boxTools = $this->renderBox('Tools ('.count($this->tools).')', $lines, $lw, $th, $this->focus == 'tools');

		// Top right: description
		$inner = $rw - 2;
		$lines = array();
		$lines[] = array('text' => $tool['name'].(empty($tool['summary']) ? '' : ' - '.$tool['summary']), 'style' => C_HIGHLIGHT);
		$lines[] = array('text' => '');
		foreach (wrapText($tool['desc'], $inner - 1) as $l) {
			$lines[] = array('text' => ' '.$l);
		}
		if (!empty($tool['usage'])) {
			$lines[] = array('text' => '');
			foreach (wrapText('Usage: '.(strpos($tool['usage'], 'desktop_') === false ? $tool['name'].' ' : '').$tool['usage'], $inner - 1) as $l) {
				$lines[] = array('text' => ' '.$l, 'style' => C_BOLD);
			}
		}
		if (!empty($tool['notes'])) {
			$lines[] = array('text' => '');
			foreach ($tool['notes'] as $note) {
				foreach (wrapText('! '.$note, $inner - 1) as $j => $l) {
					$lines[] = array('text' => ' '.($j ? '  ' : '').$l, 'style' => C_HIGHLIGHT);
				}
			}
		}
		$visible = $th - 2;
		$this->descOffset = max(0, min($this->descOffset, count($lines) - $visible));
		$more = count($lines) > $visible ? ' ['.($this->descOffset + 1).'-'.min(count($lines), $this->descOffset + $visible).'/'.count($lines).']' : '';
		$boxDesc = $this->renderBox('Description'.$more, array_slice($lines, $this->descOffset), $rw, $th, $this->focus == 'desc');

		// Bottom left: parameters and proposed values
		$lines = array();
		$namewidth = 4;
		foreach ($tool['params'] as $param) {
			$namewidth = max($namewidth, strWidth($param['name']) + 2);
		}
		$namewidth = min($namewidth, (int) floor(($lw - 2) / 2));
		$vals = $this->values[$tool['name']];
		$param = $this->currentParam();
		$items = $param ? $this->getValueItems() : array();
		$helplines = $param ? array_slice(wrapText($param['help'], $lw - 3), 0, 2) : array();
		// Keep room for the proposed values: the list of parameters scrolls when it is too long
		$nbparams = count($tool['params']);
		$minvalues = max(3, min(count($items), 6));
		$paramrows = max(2, min($nbparams, $bh - 2 - ($param ? 2 + count($helplines) + $minvalues : 0)));
		$this->paramOffset = self::scrollOffset($this->paramIdx, $this->paramOffset, $paramrows);
		$this->paramOffset = min($this->paramOffset, max(0, $nbparams - $paramrows));
		foreach ($tool['params'] as $i => $param) {
			if ($i < $this->paramOffset || $i >= $this->paramOffset + $paramrows) {
				continue;
			}
			$required = isParamRequired($param, $vals);
			$value = $this->getValue($param['name']);
			$display = ($value === '') ? (isset($param['default']) && $param['default'] !== '' ? '(default '.$param['default'].')' : ($required ? '<required>' : '(none)')) : $value;
			if ($i == $this->paramIdx && in_array($this->focus, array('params', 'values'))) {
				$st = ($this->focus == 'params') ? C_CURSOR : C_CURSOR_NOFOCUS;
				$lines[] = array('parts' => array(array($st, fitText(($required ? '*' : ' ').$param['name'], $namewidth)), array($st, ' '.$display.str_repeat(' ', $lw))));
			} else {
				$lines[] = array('parts' => array(array($required ? C_BOLD : C_TEXT, fitText(($required ? '*' : ' ').$param['name'], $namewidth)), array($value === '' ? C_DIM : C_HIGHLIGHT, ' '.$display)));
			}
		}
		$param = $this->currentParam();
		if ($param) {
			$lines[] = array('sep' => 1);
			foreach ($helplines as $l) {
				$lines[] = array('text' => ' '.$l, 'style' => C_DIM);
			}
			$multi = ($param['type'] == 'target');
			if ($this->focus == 'values' && $multi && $this->multiTouched) {
				$lines[] = array('parts' => array(array(C_BOLD, ' Filter: '), array(C_HIGHLIGHT, $this->filter), array(C_CURSOR, ' '), array(C_DIM, '  Enter: validate selection')));
			} elseif ($this->focus == 'values') {
				$lines[] = array('parts' => array(array(C_BOLD, ' Filter/type: '), array(C_HIGHLIGHT, $this->filter), array(C_CURSOR, ' ')));
			} else {
				$lines[] = array('text' => ' Enter: choose a value'.($multi ? ', Space: multi-select' : ''), 'style' => C_DIM);
			}
			$this->valueIdx = max(0, min($this->valueIdx, count($items) - 1));
			$visible = max(1, $bh - 2 - count($lines));
			$this->valueOffset = self::scrollOffset($this->valueIdx, $this->valueOffset, $visible);
			$current = $this->getValue($param['name']);
			$selected = $multi ? explode(',', $current) : array($current);
			for ($i = $this->valueOffset; $i < min(count($items), $this->valueOffset + $visible); $i++) {
				$item = $items[$i];
				$mark = '  ';
				if ($item['selectable'] && in_array($item['value'], $selected, true) && !preg_match('/^» /', $item['label'])) {
					$mark = $multi ? '✓ ' : '• ';
				}
				$style = $item['selectable'] ? C_TEXT : C_DIM;
				if ($i == $this->valueIdx && $this->focus == 'values') {
					$style = C_CURSOR;
				}
				$lines[] = array('text' => ' '.$mark.$item['label'], 'style' => $style);
			}
		}
		$more = ($paramrows < $nbparams) ? ' ['.($this->paramOffset + 1).'-'.min($nbparams, $this->paramOffset + $paramrows).'/'.$nbparams.']' : '';
		$boxParams = $this->renderBox('Parameters'.$more, $lines, $lw, $bh, in_array($this->focus, array('params', 'values')));

		// Bottom right: command line
		$warnings = array();
		$command = buildCommand($tool, $vals, $warnings);
		$lines = array();
		$lines[] = array('text' => 'Command line to launch (it is not executed here):', 'style' => C_BOLD);
		$lines[] = array('text' => '');
		foreach (hardWrap($command, $inner) as $l) {
			$lines[] = array('text' => $l, 'style' => C_HIGHLIGHT);
		}
		$lines[] = array('text' => '');
		foreach ($warnings as $warning) {
			foreach (wrapText('! '.$warning, $inner) as $l) {
				$lines[] = array('text' => $l, 'style' => C_WARN);
			}
		}
		if (!count($warnings)) {
			$lines[] = array('text' => 'All mandatory parameters are set.', 'style' => C_OK);
		}
		$lines[] = array('text' => '');
		foreach (wrapText('F5 (or c): copy to clipboard. F10 (or q, or Enter here): quit and print the command.', $inner) as $l) {
			$lines[] = array('text' => $l, 'style' => C_DIM);
		}
		if ($this->status !== '') {
			$lines[] = array('text' => '');
			foreach (wrapText($this->status, $inner) as $l) {
				$lines[] = array('text' => $l, 'style' => C_OK);
			}
		}
		$boxCmd = $this->renderBox('Command', $lines, $rw, $bh, $this->focus == 'cmd');

		// Compose screen
		$screen = array();
		$title = ' SellYourSaas CLI tools - '.getcwd();
		$screen[] = C_BAR.fitText($title, $this->cols);
		for ($i = 0; $i < $th; $i++) {
			$screen[] = $boxTools[$i].$boxDesc[$i];
		}
		for ($i = 0; $i < $bh; $i++) {
			$screen[] = $boxParams[$i].$boxCmd[$i];
		}
		$keys = array('Tab' => 'Panel', '↑↓' => 'Move', 'Enter' => 'Select', 'Del' => 'Clear', 'Esc' => 'Back', 'F5' => 'Copy', 'F10' => 'Quit');
		$bar = '';
		$barwidth = 0;
		foreach ($keys as $key => $label) {
			$piece = strWidth($key) + strWidth($label) + 2;
			if ($barwidth + $piece > $this->cols) {
				break;
			}
			$bar .= C_KEY.$key.C_BAR.$label.' '.C_KEY.' ';
			$barwidth += $piece;
		}
		$bar .= C_KEY.str_repeat(' ', max(0, $this->cols - $barwidth));
		$screen[] = $bar;

		$out = '';
		foreach ($screen as $i => $line) {
			$out .= "\033[".($i + 1).";1H".$line.C_RESET;
		}
		fwrite(STDOUT, $out);
	}

	/**
	 * Copy a text into the clipboard (OSC 52 escape sequence + wl-copy, xclip or xsel when available)
	 *
	 * @param	string	$text	Text
	 * @return	string			Status message
	 */
	public function copyToClipboard($text)
	{
		fwrite(STDOUT, "\033]52;c;".base64_encode($text)."\007");
		$candidates = array();
		if (getenv('WAYLAND_DISPLAY')) {
			$candidates[] = array('wl-copy');
		}
		if (getenv('DISPLAY')) {
			$candidates[] = array('xclip', '-selection', 'clipboard');
			$candidates[] = array('xsel', '--clipboard', '--input');
		}
		foreach ($candidates as $cmd) {
			$path = trim((string) shell_exec('command -v '.escapeshellarg($cmd[0]).' 2>/dev/null'));
			if ($path === '') {
				continue;
			}
			$cmd[0] = $path;
			$proc = proc_open(implode(' ', array_map('escapeshellarg', $cmd)), array(0 => array('pipe', 'r'), 1 => array('file', '/dev/null', 'w'), 2 => array('file', '/dev/null', 'w')), $pipes);
			if (is_resource($proc)) {
				fwrite($pipes[0], $text);
				fclose($pipes[0]);
				if (proc_close($proc) == 0) {
					return 'Command copied into the clipboard (with '.basename($path).').';
				}
			}
		}
		return 'Command sent to the clipboard with the terminal OSC 52 sequence (if your terminal supports it). Otherwise quit with F10 to get it printed.';
	}


	/*
	 * Main loop and key handling
	 */

	/**
	 * Main loop
	 *
	 * @return string	Command line of the selected tool when quitting
	 */
	public function run()
	{
		$this->start();
		$this->render();
		while (true) {
			$key = $this->readKey(1.0);
			if ($key === null) {
				if ($this->resized || !function_exists('pcntl_async_signals')) {
					$this->resized = false;
					if ($this->updateSize()) {
						fwrite(STDOUT, "\033[2J");
						$this->render();
					}
				}
				continue;
			}
			if (!$this->handleKey($key)) {
				break;
			}
			$this->render();
		}
		$warnings = array();
		$tool = $this->currentTool();
		$command = buildCommand($tool, $this->values[$tool['name']], $warnings);
		$this->stop();
		return $command;
	}

	/**
	 * Move a cursor in a list
	 *
	 * @param	string	$key	Key
	 * @param	int		$cursor	Cursor
	 * @param	int		$nb		Number of items
	 * @param	int		$page	Page size
	 * @return	int				New cursor
	 */
	public static function moveCursor($key, $cursor, $nb, $page)
	{
		switch ($key) {
			case 'UP':
				$cursor--;
				break;
			case 'DOWN':
				$cursor++;
				break;
			case 'PGUP':
				$cursor -= max(1, $page);
				break;
			case 'PGDN':
				$cursor += max(1, $page);
				break;
			case 'HOME':
				$cursor = 0;
				break;
			case 'END':
				$cursor = $nb - 1;
				break;
		}
		return max(0, min($nb - 1, $cursor));
	}

	/**
	 * Handle a key
	 *
	 * @param	string	$key	Key
	 * @return	bool			False to quit
	 */
	public function handleKey($key)
	{
		$tool = $this->currentTool();
		$param = $this->currentParam();
		list($lw, $th, $bh) = $this->layout();
		$movekeys = array('UP', 'DOWN', 'PGUP', 'PGDN', 'HOME', 'END');
		$this->status = '';

		// Global keys
		if ($key == 'F10' || $key == 'CTRLC' || ($key == 'q' && $this->focus != 'values')) {
			return false;
		}
		if ($key == 'CTRLL') {
			fwrite(STDOUT, "\033[2J");
			$this->updateSize();
			return true;
		}
		if ($key == 'F5' || ($key == 'c' && $this->focus != 'values')) {
			$warnings = array();
			$this->status = $this->copyToClipboard(buildCommand($tool, $this->values[$tool['name']], $warnings));
			return true;
		}
		if ($key == 'TAB' || $key == 'BTAB') {
			$cycle = array('tools', 'params', 'cmd', 'desc');
			$current = ($this->focus == 'values') ? 'params' : $this->focus;
			$pos = array_search($current, $cycle);
			$pos = ($pos + ($key == 'TAB' ? 1 : count($cycle) - 1)) % count($cycle);
			$this->focus = $cycle[$pos];
			$this->resetValueList();
			return true;
		}

		switch ($this->focus) {
			case 'tools':
				if (in_array($key, $movekeys)) {
					$new = self::moveCursor($key, $this->toolIdx, count($this->tools), $th - 2);
					if ($new != $this->toolIdx) {
						$this->toolIdx = $new;
						$this->descOffset = 0;
						$this->initToolValues();
					}
				} elseif ($key == 'ENTER' || $key == 'RIGHT') {
					$this->focus = 'params';
					$this->paramIdx = 0;
					$this->resetValueList();
				}
				break;

			case 'desc':
				if (in_array($key, $movekeys)) {
					$this->descOffset = self::moveCursor($key, $this->descOffset, 1000, $th - 2);
				} elseif ($key == 'ESC') {
					$this->focus = 'tools';
				}
				break;

			case 'cmd':
				if ($key == 'ENTER') {
					return false;
				} elseif ($key == 'ESC') {
					$this->focus = 'params';
				}
				break;

			case 'params':
				if (in_array($key, $movekeys)) {
					$new = self::moveCursor($key, $this->paramIdx, count($tool['params']), $bh - 2);
					if ($new != $this->paramIdx) {
						$this->paramIdx = $new;
						$this->resetValueList();
					}
				} elseif (($key == 'ENTER' || $key == 'RIGHT') && $param) {
					$this->enterValues();
				} elseif (($key == 'DEL' || $key == 'BACKSPACE') && $param) {
					$this->setValue($param['name'], '');
				} elseif ($key == 'LEFT' || $key == 'ESC') {
					$this->focus = 'tools';
				} elseif ($key == 'SPACE' && $param) {
					$this->enterValues();
				} elseif ($param && $key != 'UNKNOWN' && strWidth($key) == 1 && $param['type'] != 'choice' && $param['type'] != 'hostfile') {
					// Typing directly on a parameter starts to fill its value
					$this->focus = 'values';
					$this->filter = $key;
					$this->valueIdx = 0;
				}
				break;

			case 'values':
				$items = $this->getValueItems();
				if (in_array($key, $movekeys)) {
					$this->valueIdx = self::moveCursor($key, $this->valueIdx, count($items), $bh - 6);
				} elseif ($key == 'ESC' || $key == 'LEFT') {
					$this->focus = 'params';
					$this->resetValueList();
				} elseif ($key == 'ENTER') {
					$item = isset($items[$this->valueIdx]) ? $items[$this->valueIdx] : null;
					if ($this->multiTouched || ($item && $item['selectable'])) {
						if (!$this->multiTouched) {
							$this->setValue($param['name'], $item['value']);
						}
						// Go to next parameter
						$this->focus = 'params';
						if ($this->paramIdx < count($tool['params']) - 1) {
							$this->paramIdx++;
						}
						$this->resetValueList();
					} elseif ($this->filter === '' && in_array($param['type'], array('text', 'kv', 'cwd', 'raw'))) {
						$this->setValue($param['name'], '');
						$this->focus = 'params';
						$this->resetValueList();
					}
				} elseif ($key == 'SPACE' && $param['type'] == 'target') {
					$item = isset($items[$this->valueIdx]) ? $items[$this->valueIdx] : null;
					if ($item && $item['selectable']) {
						$current = $this->getValue($param['name']);
						$list = ($current === '') ? array() : explode(',', $current);
						$pos = array_search($item['value'], $list, true);
						if ($pos === false) {
							// 'all' already includes every server, so it is exclusive with other values
							if ($item['value'] == 'all') {
								$list = array();
							} else {
								$list = array_diff($list, array('all'));
							}
							$list[] = $item['value'];
						} else {
							unset($list[$pos]);
						}
						$this->setValue($param['name'], implode(',', $list));
						$this->multiTouched = true;
						$this->valueIdx = min(count($items) - 1, $this->valueIdx + 1);
					}
				} elseif ($key == 'BACKSPACE') {
					$this->filter = function_exists('mb_substr') ? mb_substr($this->filter, 0, -1, 'UTF-8') : substr($this->filter, 0, -1);
					$this->valueIdx = 0;
				} elseif ($key == 'CTRLU' || $key == 'DEL') {
					$this->filter = '';
					$this->valueIdx = 0;
				} elseif ($key == 'SPACE') {
					$this->filter .= ' ';
					$this->valueIdx = 0;
				} elseif ($key != 'UNKNOWN' && $key != 'RIGHT' && $key != 'BTAB' && strWidth($key) == 1) {
					$this->filter .= $key;
					$this->valueIdx = 0;
				}
				break;
		}
		return true;
	}

	/**
	 * Enter the list of values of the current parameter, with the cursor on the current value
	 *
	 * @return void
	 */
	public function enterValues()
	{
		$param = $this->currentParam();
		$this->focus = 'values';
		$this->resetValueList();
		$current = $this->getValue($param['name']);
		if (in_array($param['type'], array('text', 'kv', 'cwd', 'raw')) && $current !== '') {
			// Prefill the typed text with the current value so it can be edited
			$this->filter = $current;
			return;
		}
		foreach ($this->getValueItems() as $i => $item) {
			if ($item['selectable'] && $item['value'] === $current) {
				$this->valueIdx = $i;
				break;
			}
		}
	}
}


/*
 * Main
 */

$tools = getTools($scriptdir, $selfname);
if (!count($tools)) {
	fwrite(STDERR, "Error: No desktop_* tool found into ".$scriptdir."\n");
	exit(1);
}

$menu = new DesktopMenu($tools, $ansibledir);
$command = $menu->run();

print "Command line (not executed):\n\n".$command."\n\n";
exit(0);
