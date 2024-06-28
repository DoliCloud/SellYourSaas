<?php
/* Copyright (C) 2004-2023 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/sellyoursaas/backoffice/instance_backup.php
 *       \ingroup    sellyoursaas
 *       \brief      Card of a contact
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) {
	$i--;
	$j--;
}
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) {
	$res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
}
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) {
	$res=@include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) {
	$res=@include "../../main.inc.php";
}
if (! $res && file_exists("../../../main.inc.php")) {
	$res=@include "../../../main.inc.php";
}
if (! $res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT."/comm/action/class/actioncomm.class.php";
require_once DOL_DOCUMENT_ROOT."/contact/class/contact.class.php";
require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
require_once DOL_DOCUMENT_ROOT."/projet/class/project.class.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/contract.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/company.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/date.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/geturl.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formcompany.class.php";
dol_include_once("/sellyoursaas/core/lib/sellyoursaas.lib.php");
dol_include_once("/sellyoursaas/class/sellyoursaasutils.class.php");

$langs->loadLangs(array("admin", "companies", "users", "other", "contracts", "commercial", "sellyoursaas@sellyoursaas"));

$action		= (GETPOST('action', 'alpha') ? GETPOST('action', 'alpha') : 'view');
$confirm	= GETPOST('confirm', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$id			= GETPOST('id', 'int');
$instanceoldid = GETPOST('instanceoldid', 'int');
$ref        = GETPOST('ref', 'alpha');
$refold     = GETPOST('refold', 'alpha');

$mesg = '';

$error = 0;
$errors = array();


if ($action != 'create') {
	$object = new Contrat($db);
}

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('contractcard','globalcard'));


if ($id > 0 || $ref) {
	$result = $object->fetch($id ? $id : $instanceoldid, $ref ? $ref : $refold);
	if ($result < 0) {
		setEventMessages('Failed to read remote customer instance: '.$object->error, null, 'warnings');
		$error++;
	}
	$id = $object->id;
}

$backupstring=getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/backup_instance.php '.$object->ref_customer.' ' . getDolGlobalString('DOLICLOUD_BACKUP_PATH');

$restorestringfrombackup = '';
$restorestringfromarchive = '';
$restorestringpretoshow = '';
$restorestringposttoshow = '';
$moveinstancestringtoshow = '';

$ispaid = sellyoursaasIsPaidInstance($object);
if ($ispaid) {
	if ($object->array_options['options_deployment_status'] != 'undeployed') {
		//$restorestringpretoshow = 'sudo chown -R admin '.$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "cd " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os']."\n";
		// If there is an old dir used by a previous extract, we remove it
		$restorestringpretoshow .= "sudo rm -fr " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= "sudo tar -xvf " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.gz'."\n";
		$restorestringpretoshow .= "or\n";
		$restorestringpretoshow .= "sudo tar -I zstd -xvf " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.zst'."\n";

		$restorestringpretoshow .= "sudo mv " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/' . getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' ' . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= "\n";
		$restorestringpretoshow .= "sudo chown -R admin:root " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "sudo chmod -R a+rx " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "su - admin\n";

		$restorestringfrombackupshort = getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/restore_instance.php ' . getDolGlobalString('DOLICLOUD_BACKUP_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan';
		$restorestringfrombackup = getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/restore_instance.php ' . getDolGlobalString('DOLICLOUD_BACKUP_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan '.$object->ref_customer.' (test|confirm)';

		$restorestringfromarchiveshort = getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/restore_instance.php ' . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan';
		$restorestringfromarchive = getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/restore_instance.php ' . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan '.$object->ref_customer.' (test|confirm)';

		$restorestringfromremotebackup = getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/restore_instance.php remotebackup:/mnt/diskbackup/.snapshots/diskbackup-xxx/backup_yyy/'.$object->array_options['options_username_os'].' autoscan '.$object->ref_customer.' (test|confirm)';
		$restorestringfromremotebackup .= "\n".$langs->trans("or")."\n";
		$restorestringfromremotebackup .= getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/restore_instance.php remotebackup:/mnt/diskbackup/backup_yyy/'.$object->array_options['options_username_os'].' autoscan '.$object->ref_customer.' (test|confirm)';
	} else {
		//$restorestringpretoshow = 'sudo chown -R admin '.$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "cd " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os']."\n";
		// If there is an old dir used by a previous extract, we remove it
		$restorestringpretoshow .= "sudo rm -fr " . getDolGlobalString('DOLICLOUD_INSTANCES_PATH')."/".$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."; sudo rm -fr " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= "sudo tar -xvf " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.gz'."\n";
		$restorestringpretoshow .= "or\n";
		$restorestringpretoshow .= "sudo tar -I zstd -xvf " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.zst'."\n";

		$restorestringpretoshow .= "sudo mv " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/' . getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' ' . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= 'sudo mkdir ' . getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].'; sudo chown '.$object->array_options['options_username_os'].':'.$object->array_options['options_username_os'].' ' . getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "\n";
		$restorestringpretoshow .= "sudo chown -R admin:root " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "sudo chmod -R a+rx " . getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "su - admin\n";

		$restorestringposttoshow .= "# Then restore the conf .undeployed file into new conf file.\n";

		$restorestringfrombackupshort = 'Not possible. Redeploy the instance first.';
		$restorestringfrombackup = 'Not possible. Redeploy the instance first.';

		$restorestringfromarchiveshort = 'Not possible. Redeploy the instance first.';
		$restorestringfromarchive = 'Not possible. Redeploy the instance first.';

		$restorestringfromremotebackup = 'Not possible. Redeploy the instance first.';
	}
} else {
	if ($object->array_options['options_deployment_status'] != 'undeployed') {
		//$restorestringpretoshow = 'sudo chown -R admin '.$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "cd " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os']."\n";
		// If there is an old dir used by a previous extract, we remove it
		$restorestringpretoshow .= "sudo rm -fr " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= "sudo rm -fr " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/home'."\n";
		$restorestringpretoshow .= "sudo tar -xvf " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.gz'."\n";
		$restorestringpretoshow .= "or\n";
		$restorestringpretoshow .= "sudo tar -I zstd -xvf " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.zst'."\n";

		$restorestringpretoshow .= "sudo mv " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/' . getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' ' . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";

		$restorestringpretoshow .= "\n";
		$restorestringpretoshow .= "sudo chown -R admin:root " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "sudo chmod -R a+rx " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "su - admin\n";

		$restorestringfrombackupshort = getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/restore_instance.php ' . getDolGlobalString('DOLICLOUD_BACKUP_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan';
		$restorestringfrombackup = getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/restore_instance.php ' . getDolGlobalString('DOLICLOUD_BACKUP_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan '.$object->ref_customer.' (test|confirm)';

		$restorestringfromarchiveshort = getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/restore_instance.php ' . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan ';
		$restorestringfromarchive = getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/restore_instance.php ' . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan '.$object->ref_customer.' (test|confirm)';

		$restorestringfromremotebackup = getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/restore_instance.php remotebackup:/mnt/diskbackup/.snapshots/diskbackup-xxx/backup_yyy/'.$object->array_options['options_username_os'].' autoscan '.$object->ref_customer.' (test|confirm)';
		$restorestringfromremotebackup .= "\n".$langs->trans("or")."\n";
		$restorestringfromremotebackup .= getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/restore_instance.php remotebackup:/mnt/diskbackup/backup_yyy/'.$object->array_options['options_username_os'].' autoscan '.$object->ref_customer.' (test|confirm)';
	} else {
		$restorestringpretoshow .= "cd " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os']."\n";
		// If there is an old dir used by a previous extract, we remove it
		$restorestringpretoshow .= 'sudo rm -fr ' . getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].'; sudo rm -fr ' . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= 'sudo rm -fr ' . getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'/'.$object->array_options['options_username_os'].'/home; sudo rm -fr ' . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/home'."\n";
		$restorestringpretoshow .= "sudo tar -xvf " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.gz'."\n";
		$restorestringpretoshow .= "or\n";
		$restorestringpretoshow .= "sudo tar -I zstd -xvf " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.zst'."\n";

		$restorestringpretoshow .= "sudo mv " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/' . getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' ' . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= 'sudo mkdir ' . getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."; sudo chown ".$object->array_options['options_username_os'].".".$object->array_options['options_username_os'].' ' . getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'/'.$object->array_options['options_username_os']."\n";

		$restorestringpretoshow .= "\n";
		$restorestringpretoshow .= "sudo chown -R admin:root " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "sudo chmod -R a+rx " . getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "su - admin\n";

		$restorestringposttoshow .= "# Then restore the conf .undeployed file into new conf file.\n";

		//$restorestringfrombackupshort = 'Not possible. Redeploy the instance first. Also backup may not have been done as it was not a validated/paid instance.';
		//$restorestringfrombackup = 'Not possible. Redeploy the instance first. Also backup may not have been done as it was not a validated/paid instance.';

		$restorestringfromarchiveshort = 'Not possible. Redeploy the instance first.';
		$restorestringfromarchive = 'Not possible. Redeploy the instance first.';

		$restorestringfromremotebackup = 'Not possible. Redeploy the instance first.';
	}
}

$tmparray = explode('.', $object->ref_customer);

$moveinstancestringtoshow .= "# First, check that the master server can connect with ssh and user admin on the source instance server with:\n";
$moveinstancestringtoshow .= "# ssh admin@".getDomainFromURL($object->ref_customer, 2)." wc /etc/apache2/with.sellyoursaas.com*.*\n";
$moveinstancestringtoshow .= "# If ssh connect fails, do this on ".getDomainFromURL($object->ref_customer, 2).":\n";
$moveinstancestringtoshow .= "# cp /etc/skel/.ssh/authorized_keys_support /home/admin/.ssh/authorized_keys_support; chown admin:admin /home/admin/.ssh/authorized_keys_support\n";
//$moveinstancestringtoshow .= "# - If some cert files read is denied, do this on ".getDomainFromURL($object->ref_customer, 2).":\n";
//$moveinstancestringtoshow .= "#   gpasswd -a admin www-data\n";
$moveinstancestringtoshow .= "su - admin\n";
$moveinstancestringtoshow .= getDolGlobalString('DOLICLOUD_SCRIPTS_PATH') . '/master_move_instance.php '.$object->ref_customer.' '.$tmparray[0].'.withNEW.'.getDomainFromURL($object->ref_customer, 1).' (test|confirm|confirmredirect|confirmmaintenance)'."\n";
// Remove read in certif file.
//$moveinstancestringtoshow .= "# On src server: gpasswd -d admin www-data\n";

// Increase limit of time. Works only if we are not in safe mode
$ExecTimeLimit = 1800; // 30mn
if (!empty($ExecTimeLimit)) {
	$err = error_reporting();
	error_reporting(0); // Disable all errors
	//error_reporting(E_ALL);
	@set_time_limit($ExecTimeLimit); // Need more than 240 on Windows 7/64
	error_reporting($err);
}

// Security check
$result = restrictedArea($user, 'sellyoursaas', 0, '', '');


/*
 *	Actions
 */

$parameters=array('id'=>$id);
$reshook=$hookmanager->executeHooks('doActions', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Cancel
	if (GETPOST('cancel', 'alpha') && ! empty($backtopage)) {
		header("Location: ".$backtopage);
		exit;
	}

	//include 'refresh_action.inc.php';

	if ($action == 'backupinstance') {
		// Launch the remote action backup
		$sellyoursaasutils = new SellYourSaasUtils($db);

		dol_syslog("Launch the remote action backup for ".$object->ref);

		$db->begin();

		$errorforlocaltransaction = 0;

		$comment = 'Launch backup from backoffice on contract '.$object->ref;
		// First launch update of resources: This update status of install.lock+authorized key and update qty of contract lines + linked template invoice
		$result = $sellyoursaasutils->sellyoursaasRemoteAction('backup', $object, 'admin', '', '', '0', $comment);	// This include add of event if qty has changed

		if ($result <= 0) {
			$error++;
			$errorforlocaltransaction++;
			setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
		} else {
			setEventMessages('BackupOK', null, 'mesgs');
		}

		if (! $errorforlocaltransaction) {
			$db->commit();

			// Reload object to get updated values
			$result = $object->fetch($object->id);
		} else {
			$db->rollback();
		}
	}

	$action = 'view';
}


/*
 *	View
 */

$title = $langs->trans("SellYourSaasInstance");
$help_url='';
llxHeader('', $title, $help_url);

$form = new Form($db);
$formcompany = new FormCompany($db);

$countrynotdefined=$langs->trans("ErrorSetACountryFirst").' ('.$langs->trans("SeeAbove").')';

if ($action != 'create') {
	// Show tabs
	$head = contract_prepare_head($object);

	$title = $langs->trans("Contract");
	dol_fiche_head($head, 'backup', $title, -1, 'contract');
}


if (($id > 0 || $instanceoldid > 0) && $action != 'edit' && $action != 'create') {
	/*
	 * Fiche en mode visualisation
	*/

	$instance = 'xxxx';
	$type_db = $conf->db->type;

	$instance = $object->ref_customer;

	$object->fetch_thirdparty();

	$hostname_db = $object->array_options['options_hostname_db'];
	$username_db = $object->array_options['options_username_db'];
	$password_db = $object->array_options['options_password_db'];
	$database_db = $object->array_options['options_database_db'];
	$port_db      = (!empty($object->array_options['options_port_db']) ? $object->array_options['options_port_db'] : 3306);
	$prefix_db    = (!empty($object->array_options['options_prefix_db']) ? $object->array_options['options_prefix_db'] : 'llx_');
	$hostname_os  = $object->array_options['options_hostname_os'];
	$username_os = $object->array_options['options_username_os'];
	$password_os = $object->array_options['options_password_os'];
	$username_web = $object->thirdparty->email;
	$password_web = $object->thirdparty->array_options['options_password'];

	//$newdb = getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);

	/*if (is_object($object->db2)) {
		$savdb = $object->db;
		$object->db = $object->db2;	// To have ->db to point to db2 for showrefnav function.  $db = master database
	}*/


	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="'.DOL_URL_ROOT.'/contrat/list.php?restore_lastsearch_values=1'.(! empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';
	// Ref customer
	$morehtmlref.=$form->editfieldkey("RefCustomer", 'ref_customer', $object->ref_customer, $object, 0, 'string', '', 0, 1);
	$morehtmlref.=$form->editfieldval("RefCustomer", 'ref_customer', $object->ref_customer, $object, 0, 'string', '', null, null, '', 1, 'getFormatedCustomerRef');
	// Ref supplier
	$morehtmlref.='<br>';
	$morehtmlref.=$form->editfieldkey("RefSupplier", 'ref_supplier', $object->ref_supplier, $object, 0, 'string', '', 0, 1);
	$morehtmlref.=$form->editfieldval("RefSupplier", 'ref_supplier', $object->ref_supplier, $object, 0, 'string'.(isset($conf->global->THIRDPARTY_REF_INPUT_SIZE) ? ':' . getDolGlobalString('THIRDPARTY_REF_INPUT_SIZE') : ''), '', null, null, '', 1, 'getFormatedSupplierRef');
	// Thirdparty
	$morehtmlref .= '<br>'.$object->thirdparty->getNomUrl(1, 'customer');
	if (empty($conf->global->MAIN_DISABLE_OTHER_LINK) && $object->thirdparty->id > 0) {
		$morehtmlref .= ' (<a href="'.DOL_URL_ROOT.'/contrat/list.php?socid='.$object->thirdparty->id.'&search_societe='.urlencode($object->thirdparty->name).'">'.$langs->trans("OtherContracts").'</a>)';
	}
	// Project
	if (isModEnabled('project')) {
		$langs->load("projects");
		$morehtmlref .= '<br>';
		if (0) {
			$morehtmlref .= img_picto($langs->trans("Project"), 'project', 'class="pictofixedwidth"');
			if ($action != 'classify') {
				$morehtmlref .= '<a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?action=classify&token='.newToken().'&id='.$object->id.'">'.img_edit($langs->transnoentitiesnoconv('SetProject')).'</a> ';
			}
			$morehtmlref .= $form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id, $object->socid, $object->fk_project, ($action == 'classify' ? 'projectid' : 'none'), 0, 0, 0, 1, '', 'maxwidth300');
		} else {
			if (!empty($object->fk_project)) {
				$proj = new Project($db);
				$proj->fetch($object->fk_project);
				$morehtmlref .= $proj->getNomUrl(1);
				if ($proj->title) {
					$morehtmlref .= '<span class="opacitymedium"> - '.dol_escape_htmltag($proj->title).'</span>';
				}
			}
		}
	}
	$morehtmlref.='</div>';

	//dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'none', $morehtmlref);

	$nodbprefix=0;

	dol_banner_tab($object, ($instanceoldid ? 'refold' : 'ref'), $linkback, 1, ($instanceoldid ? 'name' : 'ref'), 'ref', $morehtmlref, '', $nodbprefix, '', '', 1);
}

if ($id > 0 || $instanceoldid > 0) {
	dol_fiche_end();
}

if ($id > 0 && $action != 'edit' && $action != 'create') {
	/*if (is_object($object->db2)) {
		$object->db = $savdb;
	}*/


	print '<div class="fichecenter">';

	$backupdir = getDolGlobalString('DOLICLOUD_BACKUP_PATH');
	$backupdirremote = '/mnt/diskbackup/backup_yyy';
	$backupdirremote2 = '/mnt/diskbackup/.snapshots/diskbackup-xxx/backup_yyy';

	$login = $username_os;
	$password = $password_os;
	$server = $object->ref_customer;

	// ----- Backup instance -----
	//print '<strong>INSTANCE BACKUP</strong><br>';

	//print '<div class="underbanner clearboth"></div>';

	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent tableforfield">';

	// Backup dir
	print '<tr class="oddeven">';
	print '<td class="titlefieldmiddle">'.$langs->trans("DeploymentHost").'</td>';
	print '<td>'.$object->array_options['options_deployment_host'].'</td>';
	print '</tr>';

	// ----- Backup result

	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("LocalBackup").'</td>';
	print '<td>';
	print '</td>';
	print '</tr>';

	// Backup dir
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("BackupDir").'</td>';
	print '<td>'.$backupdir.'/'.$login.'</td>';
	print '</tr>';

	// Last backup date try
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("DateLastBackup").'</td>';
	print '<td>';
	if ($object->array_options['options_latestbackup_date']) {
		print dol_print_date($object->array_options['options_latestbackup_date'], 'dayhour', 'tzuser');
	}
	print '</td>';
	print '</tr>';

	// Last backup date success
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("DateLastBackupOK").'</td>';
	print '<td>';
	if ($object->array_options['options_latestbackup_date_ok']) {
		print dol_print_date($object->array_options['options_latestbackup_date_ok'], 'dayhour', 'tzuser');
	}
	print '</td>';
	print '</tr>';

	// Latest backup status
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("CurrentBackupStatus").'</td>';
	print '<td>';
	print($object->array_options['options_latestbackup_status'] == 'KO' ? '<span class="error">' : '');
	print $object->array_options['options_latestbackup_status'];
	print($object->array_options['options_latestbackup_status'] == 'KO' ? '</span>' : '');
	print '</td>';
	print '</tr>';

	// Latest backup message
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("LatestBackupMessage").'</td>';
	print '<td class="classfortooltip" title="'.dolPrintHTMLForAttribute($object->array_options['options_latestbackup_message']).'">';
	print dolGetFirstLineOfText(dolPrintHTML($object->array_options['options_latestbackup_message']), 5);
	print '</td>';
	print '</tr>';

	// ----- Remote backup

	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("RemoteBackup").'</td>';
	print '<td></td>';
	print '</td>';
	print '</tr>';

	// Backup dir
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("BackupDir").'</td>';
	print '<td>'.$backupdirremote.'/'.$login.' or '.$backupdirremote2.'/'.$login.'</td>';
	print '</tr>';

	// Last remote backup date try
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("LatestBackupRemoteDate").'</td>';
	print '<td>';
	if ($object->array_options['options_latestbackupremote_date']) {
		print dol_print_date($object->array_options['options_latestbackupremote_date'], 'dayhour', 'tzuser');
	}
	print '</td>';
	print '</tr>';

	// Last remote backup date success
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("LatestBackupRemoteDateOK").'</td>';
	print '<td>';
	if ($object->array_options['options_latestbackupremote_date_ok']) {
		print dol_print_date($object->array_options['options_latestbackupremote_date_ok'], 'dayhour', 'tzuser');
	}
	print '</td>';
	print '</tr>';

	// Latest remote backup status
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("LatestBackupRemoteStatus").'</td>';
	print '<td>';
	print($object->array_options['options_latestbackupremote_status'] == 'KO' ? '<span class="error">' : '');
	print $object->array_options['options_latestbackupremote_status'];
	print($object->array_options['options_latestbackupremote_status'] == 'KO' ? '</span>' : '');
	print '</td>';
	print '</tr>';


	print "</table>";
	print '</div>';

	print "</div>";


	// Barre d'actions
	if (! $user->socid) {
		print '<div class="tabsAction">';

		if ($user->hasRight('sellyoursaas', 'write') && $object->array_options['options_deployment_status'] !== 'undeployed') {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=backupinstance&token='.newToken().'">'.$langs->trans('BackupNow').'</a>';
		}

		print "</div>";
	}
}


// Backup command line
$backupstringtoshow=$backupstring.' confirm --nostats --forcersync --forcedump';
$backupstringtoshow2=$backupstring.' confirm';
print '<span class="fa fa-database secondary"></span> -> <span class="fa fa-file paddingright"></span> Backup command line string <span class="opacitymedium">(to run by admin from the deployment server where to store the backup, or from root from the master server)</span><br>';
print '<input type="text" name="backupstring" id="backupstring" value="'.$backupstringtoshow.'" class="quatrevingtpercent"><br>';
print ajax_autoselect('backupstring');

print '<br>';

// Restore command line from backup
if ($restorestringfrombackup) {
	$restorestringtoshow=$restorestringfrombackup;
	print '<span class="fa fa-file paddingright"></span> -> <span class="fa fa-database secondary paddingright"></span> Restore command line string from local Backup <span class="opacitymedium">(to run by admin from the server hosting the backup)</span><br>';
	print '<input type="text" name="restorestring" id="restorestring" value="'.$restorestringtoshow.'" class="quatrevingtpercent"><br>';
	print ajax_autoselect('restorestring');

	print '<br>';
}

// Restore commands from remote backup
if ($restorestringfromremotebackup) {
	$restorestringtoshow=$restorestringfromremotebackup;
	print '<span class="fa fa-file paddingright"></span> -> <span class="fa fa-database secondary paddingright"></span> Restore command line string from remote Backup <span class="opacitymedium">(to run by root from the deployment server)</span><br>';
	print '<textarea name="restorestringfromremotebackup" id="restorestringfromremotebackup" class="quatrevingtpercent" rows="'.ROWS_3.'">';
	print $restorestringtoshow;
	print '</textarea>';
	print '<br>';
	print ajax_autoselect('restorestringfromremotebackup');

	print '<br>';
}

// Restore commands from archive
if ($restorestringfromarchive) {
	$restorestringtoshow=$restorestringfromarchive;
	print '<span class="fa fa-file-archive paddingright"></span> -> <span class="fa fa-database secondary paddingright"></span> Restore command line string from local Archive <span class="opacitymedium">(to run by admin from the server hosting the archives)</span><br>';
	print '<textarea name="restorestringfromarchive" id="restorestringfromarchive" class="centpercent" rows="'.ROWS_9.'">';
	print $restorestringpretoshow."\n";
	print $restorestringtoshow."\n";
	print $restorestringposttoshow;
	print '</textarea>';
	//print ajax_autoselect('restorestringfromarchive');

	print '<br>';
	print '<br>';
}


// Duplicate an instance into another instance (already existing instance)
if ($restorestringfrombackupshort) {
	$restorestringtoshow=$restorestringfrombackupshort.' nameoftargetinstance (test|confirm)';
	print '<span class="fa fa-database secondary"></span><span class="fa fa-database"></span> -> <span class="fa fa-database secondary"></span><span class="fa fa-database secondary paddingright"></span> Duplicate an instance into another instance (already existing instance) <span class="opacitymedium">(to run by admin on master server)</span><br>';
	print '<textarea name="restorestringfromarchive" id="restorestringfromarchive" class="centpercent" rows="'.ROWS_2.'">';
	print $backupstringtoshow."\n";
	print $restorestringtoshow;
	print '</textarea>';

	print '<br>';
	print '<br>';
}

// Move instance into another server (non existing target instance)
if ($moveinstancestringtoshow) {
	//$restorestringtoshow=$restorestringfrombackupshort.' nameoftargetinstance (test|confirm)';
	print '<span class="fa fa-database secondary"></span> -> <span class="fa fa-database opacitymedium"></span><span class="fa fa-database secondary paddingright"></span> Move an instance into another server (non existing target instance) <span class="opacitymedium">(to run by admin on master server)</span><br>';
	print '<textarea name="moveinstancestring" id="moveinstancestring" class="centpercent" rows="'.ROWS_8.'">';
	print $moveinstancestringtoshow;
	print '</textarea>';

	print '<br>';
}



if (! empty($mesg)) {
	print '<br>';
	print $mesg;
}

llxFooter();

$db->close();
