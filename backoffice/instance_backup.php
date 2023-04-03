<?php
/* Copyright (C) 2004-2022 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/sellyoursaas/backoffice/instance_backup.php
 *       \ingroup    sellyoursaas
 *       \brief      Card of a contact
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT."/comm/action/class/actioncomm.class.php";
require_once DOL_DOCUMENT_ROOT."/contact/class/contact.class.php";
require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
require_once DOL_DOCUMENT_ROOT."/projet/class/project.class.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/contract.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/company.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/date.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/geturl.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formcompany.class.php";
dol_include_once("/sellyoursaas/core/lib/dolicloud.lib.php");
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

$error=0; $errors=array();


if ($action != 'create') {
	$object = new Contrat($db);
}

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('contractcard','globalcard'));


if ($id > 0 || $ref) {
	$result = $object->fetch($id?$id:$instanceoldid, $ref?$ref:$refold);
	if ($result < 0) dol_print_error($db, $object->error);
	$id = $object->id;
}

$backupstring=$conf->global->DOLICLOUD_SCRIPTS_PATH.'/backup_instance.php '.$object->ref_customer.' '.$conf->global->DOLICLOUD_BACKUP_PATH;

$restorestringfrombackup = '';
$restorestringfromarchive = '';
$restorestringpretoshow = '';
$restorestringposttoshow = '';
$moveinstancestringtoshow = '';

$ispaid = sellyoursaasIsPaidInstance($object);
if ($ispaid) {
	if ($object->array_options['options_deployment_status'] != 'undeployed') {
		//$restorestringpretoshow = 'sudo chown -R admin '.$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "cd ".$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		// If there is an old dir used by a previous extract, we remove it
		$restorestringpretoshow .= "sudo rm -fr ".$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= "sudo tar -xvf ".$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.gz'."\n";
		$restorestringpretoshow .= "sudo mv ".$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' '.$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= "\n";
		$restorestringpretoshow .= "sudo chown -R admin.root ".$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "sudo chmod -R a+rx ".$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "su - admin\n";

		$restorestringfrombackupshort = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->DOLICLOUD_BACKUP_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan';
		$restorestringfrombackup = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->DOLICLOUD_BACKUP_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan '.$object->ref_customer;

		$restorestringfromarchiveshort = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan';
		$restorestringfromarchive = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan '.$object->ref_customer;
	} else {
		//$restorestringpretoshow = 'sudo chown -R admin '.$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "cd ".$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		// If there is an old dir used by a previous extract, we remove it
		$restorestringpretoshow .= "sudo rm -fr ".$conf->global->DOLICLOUD_INSTANCES_PATH."/".$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."; sudo rm -fr ".$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= "sudo tar -xvf ".$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.gz'."\n";
		$restorestringpretoshow .= "sudo mv ".$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' '.$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= 'sudo mkdir '.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].'; sudo chown '.$object->array_options['options_username_os'].':'.$object->array_options['options_username_os'].' '.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "\n";
		$restorestringpretoshow .= "sudo chown -R admin.root ".$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "sudo chmod -R a+rx ".$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "su - admin\n";

		$restorestringposttoshow .= "# Then restore the conf .undeployed file into new conf file.\n";

		$restorestringfrombackupshort = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->DOLICLOUD_BACKUP_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan';
		$restorestringfrombackup = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->DOLICLOUD_BACKUP_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan '.$object->ref_customer;

		$restorestringfromarchiveshort = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan';
		$restorestringfromarchive = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan '.$object->ref_customer;
	}
} else {
	if ($object->array_options['options_deployment_status'] != 'undeployed') {
		//$restorestringpretoshow = 'sudo chown -R admin '.$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "cd ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		// If there is an old dir used by a previous extract, we remove it
		$restorestringpretoshow .= "sudo rm -fr ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= "sudo rm -fr ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/home'."\n";
		$restorestringpretoshow .= "sudo tar -xvf ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.gz'."\n";
		$restorestringpretoshow .= "or\n";
		$restorestringpretoshow .= "sudo tar -I zstd -xvf ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.zst'."\n";
		$restorestringpretoshow .= "sudo mv ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' '.$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= "\n";
		$restorestringpretoshow .= "sudo chown -R admin.root ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "sudo chmod -R a+rx ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "su - admin\n";

		$restorestringfrombackupshort = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->DOLICLOUD_BACKUP_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan';
		$restorestringfrombackup = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->DOLICLOUD_BACKUP_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan '.$object->ref_customer;

		$restorestringfromarchiveshort = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan ';
		$restorestringfromarchive = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan '.$object->ref_customer;
	} else {
		$restorestringpretoshow .= "cd ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		// If there is an old dir used by a previous extract, we remove it
		$restorestringpretoshow .= 'sudo rm -fr '.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].'; sudo rm -fr '.$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= 'sudo rm -fr '.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/home; sudo rm -fr '.$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/home'."\n";
		$restorestringpretoshow .= "sudo tar -xvf ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.gz'."\n";
		$restorestringpretoshow .= "or\n";
		$restorestringpretoshow .= "sudo tar -I zstd -xvf ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_username_os'].'.tar.zst'."\n";
		$restorestringpretoshow .= "sudo mv ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' '.$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."\n";
		$restorestringpretoshow .= 'sudo mkdir '.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db']."; sudo chown ".$object->array_options['options_username_os'].".".$object->array_options['options_username_os'].' '.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os']."\n";

		$restorestringpretoshow .= "\n";
		$restorestringpretoshow .= "sudo chown -R admin.root ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "sudo chmod -R a+rx ".$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os']."\n";
		$restorestringpretoshow .= "su - admin\n";

		$restorestringposttoshow .= "# Then restore the conf .undeployed file into new conf file.\n";

		$restorestringfromarchiveshort = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan ';
		$restorestringfromarchive = $conf->global->DOLICLOUD_SCRIPTS_PATH.'/restore_instance.php '.$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->array_options['options_username_os'].'/'.$object->array_options['options_database_db'].' autoscan '.$object->ref_customer;
	}
}

$tmparray = explode('.', $object->ref_customer);

$moveinstancestringtoshow .= "su - admin\n";
$moveinstancestringtoshow .= $conf->global->DOLICLOUD_SCRIPTS_PATH.'/master_move_instance.php '.$object->ref_customer.' '.$tmparray[0].'.withNEW.'.getDomainFromURL($object->ref_customer, 1).' (test|confirm|confirmredirect|confirmmaintenance)'."\n";
// Remove read in certif file.
$moveinstancestringtoshow .= "chmod o-r /etc/apache2/".getDomainFromURL($object->ref_customer, 2).".key\n";


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

$help_url='';
llxHeader('', $langs->trans("DoliCloudInstances"), $help_url);

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
	$hostname_db = $object->array_options['options_hostname_db'];
	$username_db = $object->array_options['options_username_db'];
	$password_db = $object->array_options['options_password_db'];
	$database_db = $object->array_options['options_database_db'];
	$port_db     = $object->array_options['options_port_db'];
	$username_os = $object->array_options['options_username_os'];
	$password_os = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	//$newdb = getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);

	/*if (is_object($object->db2)) {
		$savdb = $object->db;
		$object->db = $object->db2;	// To have ->db to point to db2 for showrefnav function.  $db = master database
	}*/



	$object->fetch_thirdparty();

	//$object->email = $object->thirdparty->email;

	// Contract card

	$linkback = '<a href="'.DOL_URL_ROOT.'/contrat/list.php?restore_lastsearch_values=1'.(! empty($socid)?'&socid='.$socid:'').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref='';

	$morehtmlref.='<div class="refidno">';
	// Ref customer
	$morehtmlref.=$form->editfieldkey("RefCustomer", 'ref_customer', $object->ref_customer, $object, 0, 'string', '', 0, 1);
	$morehtmlref.=$form->editfieldval("RefCustomer", 'ref_customer', $object->ref_customer, $object, 0, 'string', '', null, null, '', 1, 'getFormatedCustomerRef');
	// Ref supplier
	$morehtmlref.='<br>';
	$morehtmlref.=$form->editfieldkey("RefSupplier", 'ref_supplier', $object->ref_supplier, $object, 0, 'string', '', 0, 1);
	$morehtmlref.=$form->editfieldval("RefSupplier", 'ref_supplier', $object->ref_supplier, $object, 0, 'string', '', null, null, '', 1, 'getFormatedSupplierRef');
	// Thirdparty
	$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . ($object->thirdparty ? $object->thirdparty->getNomUrl(1) : '');
	// Project
	if (! empty($conf->project->enabled)) {
		$langs->load("projects");
		$morehtmlref.='<br>'.$langs->trans('Project') . ' : ';
		if (0) {
			if ($action != 'classify')
				$morehtmlref.='<a class="editfielda" href="' . $_SERVER['PHP_SELF'] . '?action=classify&token='.newToken().'&id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetProject')) . '</a> : ';
			if ($action == 'classify') {
				//$morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'projectid', 0, 0, 1, 1);
				$morehtmlref.='<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
				$morehtmlref.='<input type="hidden" name="action" value="classin">';
				$morehtmlref.='<input type="hidden" name="token" value="'.newToken().'">';
				$morehtmlref.=$formproject->select_projects($object->thirdparty->id, $object->fk_project, 'projectid', $maxlength, 0, 1, 0, 1, 0, 0, '', 1);
				$morehtmlref.='<input type="submit" class="button valignmiddle" value="'.$langs->trans("Modify").'">';
				$morehtmlref.='</form>';
			} else {
				$morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->thirdparty->id, $object->fk_project, 'none', 0, 0, 0, 1);
			}
		} else {
			if (! empty($object->fk_project)) {
				$proj = new Project($db);
				$proj->fetch($object->fk_project);
				$morehtmlref.='<a href="'.DOL_URL_ROOT.'/projet/card.php?id=' . $object->fk_project . '" title="' . $langs->trans('ShowProject') . '">';
				$morehtmlref.=$proj->ref;
				$morehtmlref.='</a>';
			} else {
				$morehtmlref.='';
			}
		}
	}
	$morehtmlref.='</div>';

	//dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'none', $morehtmlref);

	$nodbprefix=0;

	dol_banner_tab($object, ($instanceoldid?'refold':'ref'), $linkback, 1, ($instanceoldid?'name':'ref'), 'ref', $morehtmlref, '', $nodbprefix, '', '', 1);
}

if ($id > 0 || $instanceoldid > 0) {
	dol_fiche_end();
}

if ($id > 0 && $action != 'edit' && $action != 'create') {
	/*if (is_object($object->db2)) {
		$object->db = $savdb;
	}*/


	print '<div class="fichecenter">';

	$backupdir = $conf->global->DOLICLOUD_BACKUP_PATH;

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

	// Backup dir
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("BackupDir").'</td>';
	print '<td>'.$backupdir.'/'.$login.'</td>';
	print '</tr>';

	// Last backup date try
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("DateLastBackup").'</td>';
	print '<td>';
	if ($object->array_options['options_latestbackup_date']) print dol_print_date($object->array_options['options_latestbackup_date'], 'dayhour', 'tzuser');
	print '</td>';
	print '</tr>';

	// Last backup date success
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("DateLastBackupOK").'</td>';
	print '<td>';
	if ($object->array_options['options_latestbackup_date_ok']) print dol_print_date($object->array_options['options_latestbackup_date_ok'], 'dayhour', 'tzuser');
	print '</td>';
	print '</tr>';

	// Current backup status
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("CurrentBackupStatus").'</td>';
	print '<td>';
	print ($object->array_options['options_latestbackup_status'] == 'KO' ? '<span class="error">' : '');
	print $object->array_options['options_latestbackup_status'];
	print ($object->array_options['options_latestbackup_status'] == 'KO' ? '</span>' : '');
	print '</td>';
	print '</tr>';

	// Current backup status
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("LatestBackupMessage").'</td>';
	print '<td>';
	print $object->array_options['options_latestbackup_message'];
	print '</td>';
	print '</tr>';

	print "</table>";
	print '</div>';

	print "</div>";


	// Barre d'actions
	if (! $user->socid) {
		print '<div class="tabsAction">';

		if ($user->rights->sellyoursaas->write) {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=backupinstance&token='.newToken().'">'.$langs->trans('BackupNow').'</a>';
		}

		print "</div>";
	}
}


// Backup command line
$backupstringtoshow=$backupstring.' confirm --nostats --forcersync --forcedump';
$backupstringtoshow2=$backupstring.' confirm';
print '<span class="fa fa-database secondary"></span> -> <span class="fa fa-file paddingright"></span> Backup command line string <span class="opacitymedium">(to run on the server where to store the backup)</span><br>';
print '<input type="text" name="backupstring" id="backupstring" value="'.$backupstringtoshow.'" class="quatrevingtpercent"><br>';
print ajax_autoselect('backupstring');

print '<br>';

// Restore command line from backup
if ($restorestringfrombackup) {
	$restorestringtoshow=$restorestringfrombackup.' (test|confirm)';
	print '<span class="fa fa-file paddingright"></span> -> <span class="fa fa-database secondary paddingright"></span> Restore command line string from Backup <span class="opacitymedium">(to run from the server hosting the backup)</span><br>';
	print '<input type="text" name="restorestring" id="restorestring" value="'.$restorestringtoshow.'" class="quatrevingtpercent"><br>';
	print ajax_autoselect('restorestring');

	print '<br>';
}

// Restore commands from archive
if ($restorestringfromarchive) {
	$restorestringtoshow=$restorestringfromarchive.' (test|confirm)';
	print '<span class="fa fa-file-archive paddingright"></span> -> <span class="fa fa-database secondary paddingright"></span> Restore command line string from Archive <span class="opacitymedium">(to run from the server hosting the archives)</span><br>';
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
	print '<span class="fa fa-database secondary"></span><span class="fa fa-database"></span> -> <span class="fa fa-database secondary"></span><span class="fa fa-database secondary paddingright"></span> Duplicate an instance into another instance (already existing instance) <span class="opacitymedium">(can be run on master, source OR taget server. recommended: source server)</span><br>';
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
	print '<span class="fa fa-database secondary"></span> -> <span class="fa fa-database opacitymedium"></span><span class="fa fa-database secondary paddingright"></span> Move an instance into another server (non existing target instance) <span class="opacitymedium">(to run on master server)</span><br>';
	print '<textarea name="moveinstancestring" id="moveinstancestring" class="centpercent" rows="'.ROWS_3.'">';
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
