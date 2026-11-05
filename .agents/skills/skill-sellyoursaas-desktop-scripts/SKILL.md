---
name: skill-sellyoursaas-desktop-scripts
description: "Use when asked how to use, run, debug or modify the SellYourSaas admin desktop tools scripts/desktop_*.sh and scripts/desktop_*.php (remote apt upgrade, clean, git update, config change, crontab, fail2ban banned IPs, unix users admin, module deployment, install check) and their Ansible playbooks in scripts/ansible/. The agent only gives the command line to launch and never runs these tools itself."
license: MIT
user-invocable: true
allowed-tools:
 - read_file
 - write_file
 - bash
 - grep
---

# SellYourSaas desktop tools (`scripts/desktop_*`)

## Purpose

The `scripts/desktop_*` files are run **from the sysadmin's desktop** (not on the servers). Except the two local
utilities (`desktop_switchipv6.sh`, `desktop_fix_unzip.php`), they are thin wrappers that build and run an
`ansible-playbook` command against the servers of a SellYourSaas infrastructure.

## Golden rule: never run these tools

- The agent must **NEVER execute** any `scripts/desktop_*` script, nor the `ansible-playbook` commands they build,
  even if the user explicitly asks to run them. They act on production servers.
- For any request to use one of these tools, answer with the **exact command line to launch** (with the arguments
  filled from the request, and a safe variant first when available, e.g. `test` mode or a single host target),
  and let the user run it.
- The user is usually at the root of the sellyoursaas project: always write the commands relative to it, starting
  with `./scripts/desktop_...` (never `cd` into `scripts/` first). The scripts `cd` into `scripts/ansible/`
  themselves, so they work from any directory.
- Reading the scripts and playbooks, or editing them when asked, is allowed.

## Common mechanism (all Ansible wrappers)

1. The script `cd` into `scripts/ansible/` (path relative to the script, so it can be launched from anywhere).
2. It runs `ansible-playbook -K <playbook>.yml -i hosts-<hostfile> -e "target=<target> ..."`.
   - `<hostfile>` (1st argument) is a **suffix**: `myhosts` means the inventory file `scripts/ansible/hosts-myhosts`.
     See `scripts/ansible/hosts.example` for the format (groups `[master]`, `[web]`, `[deployment]`, `[remotebackup]`).
   - `target` is passed to the playbook as `hosts: '{{target}}'`: a group name (`master`, `deployment`, `web`,
     `remotebackup`), a comma separated list of groups (`master,deployment`) or a single hostname
     (`withX.mysellyoursaasdomain.com`).
   - In particular, instead of the group `deployment`, you can give the name of one deployment server (or several
     separated with a comma). The list of available names is the content of the `[deployment]` section of the
     inventory file `scripts/ansible/hosts-<hostfile>` (read it to propose the right server name).
   - `-K` makes Ansible ask for the **sudo (become) password** of the remote user. The SSH user is the current desktop
     login unless overridden (only `desktop_admin_users.php` has a `loginforansible` option).
3. The full command is printed, then executed with `eval` (or `exec()` in PHP), then `Finished.` is printed.
4. Running a script without the mandatory arguments prints its usage and exits with code 1.

Prerequisites on the desktop: `ansible` installed, an SSH access (key) to the servers with a user allowed to sudo,
and `/etc/lsb-release` present (sourced by every `.sh` script).

## Reference of the tools

| Script | Usage | Playbook | What is done on the remote servers |
|---|---|---|---|
| `desktop_apt_upgrade.sh` | `hostfile target [reboot]` (default target `master`) | `launch_apt_upgrade.yml` | Switches all instances offline (`deployment_make_instances_offlineonline.sh maintenance.php offline`), runs apt update/upgrade, optionally reboots, then switches instances back online. Asks a key press before starting. Check afterward that the web server is still alive. |
| `desktop_clean.sh` | `hostfile target [confirm\|test]` (default `confirm`) | `launch_clean.yml` | Runs `scripts/clean.sh <command>` on servers. Use `test` for a dry run. |
| `desktop_update_sellyoursaas.sh` | `hostfile [target] [unlock]` (default `master,deployment`) | `launch_git_update_sellyoursaas.yml` | Runs `scripts/git_update_sellyoursaas.sh /home/admin/wwwroot/`. With `unlock`, also creates `dolibarr_documents/upgrade.unlock`. |
| `desktop_config_dolibarr.sh` | `hostfile param value [target]` (default `deployment`) | `change_config_dolibarr.yml` | Sets `$param = value` in `/home/admin/wwwroot/dolibarr/htdocs/conf/conf.php`. Not for the conf files of deployed instances. |
| `desktop_config_sellyoursaas.sh` | `hostfile target param value [sellyoursaas\|sellyoursaas-public]` | `change_config_sellyoursaas.yml` | Sets `param=value` in `/etc/sellyoursaas.conf` (default) or `/etc/sellyoursaas-public.conf`. Note: argument order differs from `desktop_config_dolibarr.sh` (target is 2nd). |
| `desktop_crontab_server.sh` | `hostfile target root\|osusername` | `change_crontab_server.yml` | Edits `/var/spool/cron/crontabs/<username>` (fixes known lines: `backup_backup.sh`, `perms.ksh` → `perms.sh`). |
| `desktop_bannedip.sh` | `hostfile target list\|unban\|ban [IP] [jail]` | `bannedip_list.yml`, `bannedip_unban.yml`, `bannedip_ban.yml` | Fail2ban: `list` shows whitelisted (`ignoreip`) and banned IPs per jail, `unban` removes an IP from all jails, `ban` bans an IP in a given jail. |
| `desktop_install_check.sh` | `hostfile [target] [apache\|php70..php84\|all]` (default `master`) | `launch_install_check.yml` | Checks/fixes the setup of master and deployment servers: OS accounts (admin groups), PAM password policy, apt packages, and optionally apache or a given PHP version (`all` = apache + php74). Refuses `web` and `remotebackup` targets. |
| `desktop_deploy_module.sh` | `hostsfile target test\|confirm productref instancefilter master_instance_id [countrycode] [nocache]` | `launch_deployment_deploy_module.yml` | Runs `scripts/deployment_deploy_module.php` on deployment servers to deploy the package `productref` on instances matching `instancefilter` (e.g. `'abc*'`, quote it), trials included. `nocache` forces the rebuild of the cached archive. |
| `desktop_admin_users.php` | `hostfile target create\|update\|deactivate\|reactivate\|remove login [loginforansible] [userroot=0\|1] [userip=...] [userpublickey="..."] [userpassword="..."]` (default target `master,deployment,web`) | `user_create.yml` (create and update), `user_deactivate.yml`, `user_reactivate.yml`, `user_remove.yml` | Manages personal unix sysadmin accounts on all servers. `create` requires `userroot`; `userroot=1` also requires `userpassword`. The password is hashed locally (SHA-512 `crypt`, `$6$salt$...`) before being sent to Ansible. The 5th argument is taken as `loginforansible` (`--user=` for SSH) only if it contains no `=`. Runs with `ansible-playbook -v`. |

Local tools (no Ansible):

| Script | Usage | What it does |
|---|---|---|
| `desktop_switchipv6.sh` | `sudo desktop_switchipv6.sh enable\|disable` | Enables/disables IPv6 on the desktop with `sysctl` (needs root), then shows interfaces. |
| `desktop_fix_unzip.php` | run inside the directory to fix, no argument | Fixes an archive badly unzipped (e.g. a Windows zip giving files named `dir\sub\file`): recreates the real sub-directories, moves files into them, then removes empty directories. Works on the current directory. |

## Examples

```bash
# From the root of the sellyoursaas project
./scripts/desktop_clean.sh myhosts deployment test
./scripts/desktop_update_sellyoursaas.sh myhosts master,deployment
./scripts/desktop_config_sellyoursaas.sh myhosts master,deployment usecompressformatforarchive zstd
./scripts/desktop_bannedip.sh myhosts withX.mysellyoursaasdomain.com unban 1.2.3.4
./scripts/desktop_deploy_module.sh myhosts deployment test REFMODULENAME 'abc*' abc1234 FR
./scripts/desktop_admin_users.php myhosts master,deployment,web create jdoe userroot=0 userip=1.2.3.4 userpublickey="ssh-ed25519 AAAA..." userpassword=...
```

## Pitfalls to know

- Always try first on a single host (`target=withX.mysellyoursaasdomain.com`) or with a `test` mode when available
  (`desktop_clean.sh`, `desktop_deploy_module.sh`).
- `desktop_apt_upgrade.sh` puts **all instances of the target servers in maintenance** during the run. If the run is
  interrupted, restore them manually with `deployment_make_instances_offlineonline.sh maintenance.php online`.
- `desktop_deploy_module.sh` exits with the usage if the 7th argument is missing, so in practice `countrycode` (or
  `nocache`) must be provided even if the usage says it is optional.
- Arguments are concatenated into a shell command executed with `eval`: quote values containing spaces, `*` or
  special characters, and never pass untrusted input to these scripts.
- Remote paths are hardcoded: `/home/admin/wwwroot/dolibarr_sellyoursaas/scripts/` for the scripts and
  `/home/admin/wwwroot/dolibarr/htdocs/conf/conf.php` for the Dolibarr config.

## When modifying these scripts

- Keep the same pattern (usage block when arguments are missing, `cd $currentpath/ansible`, print then run the
  command, `Finished.`) and add/update the matching playbook in `scripts/ansible/`.
- New playbooks must use `hosts: '{{target}}'` so they work with group names, lists and single hostnames.
- Check shell scripts with `shellcheck` and PHP scripts with `php -l`.
