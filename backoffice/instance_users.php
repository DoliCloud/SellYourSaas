<?php
/* Copyright (C) 2004-2013 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *       \file       htdocs/sellyoursaas/backoffice/instance_users.php
 *       \ingroup    societe
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
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT."/core/lib/contract.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/company.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/date.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formcompany.class.php";
dol_include_once("/sellyoursaas/core/lib/dolicloud.lib.php");
dol_include_once("/sellyoursaas/class/sellyoursaascontract.class.php");
dol_include_once('/sellyoursaas/class/packages.class.php');

$langs->loadLangs(array("admin","companies","users","contracts","other","commercial","sellyoursaas@sellyoursaas"));

$action		= (GETPOST('action', 'alpha') ? GETPOST('action', 'alpha') : 'view');
$confirm	= GETPOST('confirm', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');

$id			= GETPOST('id', 'int');
$ref        = GETPOST('ref', 'alpha');

$error = 0; $errors = array();

if (!$sortorder) {
	$sortorder = "ASC";
}

if ($action != 'create') {
	$object = new SellYourSaasContract($db);
}

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array array
include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
$hookmanager=new HookManager($db);
$hookmanager->initHooks(array('contractcard'));


if ($id > 0 || $ref) {
	$result=$object->fetch($id, $ref);
	if ($result < 0) dol_print_error($db, $object->error);
	$id = $object->id;
}

$backupstring=$conf->global->DOLICLOUD_SCRIPTS_PATH.'/backup_instance.php '.$object->instance.' '.$conf->global->DOLICLOUD_INSTANCES_PATH;


$instance = 'xxxx';
$type_db = $conf->db->type;
$instance = $object->ref_customer;
$hostname_db = $object->array_options['options_hostname_db'];
$username_db = $object->array_options['options_username_db'];
$password_db = $object->array_options['options_password_db'];
$database_db = $object->array_options['options_database_db'];
$prefix_db   = (empty($object->array_options['options_prefix_db']) ? 'llx_' : $object->array_options['options_prefix_db']);
$port_db     = (!empty($object->array_options['options_port_db']) ? $object->array_options['options_port_db'] : 3306);
$username_os = $object->array_options['options_username_os'];
$password_os = $object->array_options['options_password_os'];
$hostname_os = $object->array_options['options_hostname_os'];

if (empty($prefix_db)) {
	$prefix_db = 'llx_';
}

// Security check
$result = restrictedArea($user, 'sellyoursaas', 0, '', '');
// Get tmppackage
$tmppackage = new Packages($db);
foreach ($object->lines as $keyline => $line) {
	$tmpproduct = new Product($db);
	if ($line->fk_product > 0) {
		$tmpproduct->fetch($line->fk_product, '', '', '', 1, 1, 1);
		if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
			if ($tmpproduct->array_options['options_package'] > 0) {
				$tmppackage->fetch($tmpproduct->array_options['options_package']);
				break;
			} else {
				dol_syslog("Error: ID of package not defined on productwith ID ".$line->fk_product);
			}
		}
	}
}

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
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	if ($action == "createsupportuser") {
		$newdb = getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
		$newdb->prefix_db = $prefix_db;

		if (is_object($newdb)) {
			// Get login and password for support
			$loginforsupport = $conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT;

			$password = $conf->global->SELLYOURSAAS_SUPPORT_DEFAULT_PASSWORD;
			if (empty($password)) {
				require_once DOL_DOCUMENT_ROOT."/core/lib/security2.lib.php";
				$password = getRandomPassword(false);
			}
			$password_crypted_for_remote = '';


			$fordolibarr = 1;
			if (preg_match('/glpi.*\.cloud/', $object->ref_customer)) {
				$fordolibarr = 0;
				$forglpi = 1;
			}


			if ($fordolibarr) {
				// Save setup and init env to have dol_hash ok for target instance
				$savMAIN_SECURITY_HASH_ALGO = getDolGlobalString('MAIN_SECURITY_HASH_ALGO');
				$savMAIN_SECURITY_SALT = getDolGlobalString('MAIN_SECURITY_SALT');

				// Get setup of remote
				$sql="SELECT value FROM ".$prefix_db."const WHERE name = 'MAIN_SECURITY_HASH_ALGO' ORDER BY entity LIMIT 1";
				$resql=$newdb->query($sql);
				if ($resql) {
					$obj = $newdb->fetch_object($resql);
					if ($obj) $conf->global->MAIN_SECURITY_HASH_ALGO = $obj->value;
				} else {
					setEventMessages("Failed to get remote MAIN_SECURITY_HASH_ALGO", null, 'warnings');
				}
				$sql="SELECT value FROM ".$prefix_db."const WHERE name = 'MAIN_SECURITY_SALT' ORDER BY entity LIMIT 1";
				$resql=$newdb->query($sql);
				if ($resql) {
					$obj = $newdb->fetch_object($resql);
					if ($obj) $conf->global->MAIN_SECURITY_SALT = $obj->value;
				} else {
					setEventMessages("Failed to get remote MAIN_SECURITY_SALT", null, 'warnings');
				}

				// Calculate hash using remote setup
				$password_crypted_for_remote = dol_hash($password);

				// Restore current setup
				$conf->global->MAIN_SECURITY_HASH_ALGO = $savMAIN_SECURITY_HASH_ALGO;
				$conf->global->MAIN_SECURITY_SALT = $savMAIN_SECURITY_SALT;
			}


			// Set language to use for notes on the user we will create.
			$newlangs = new Translate('', $conf);
			$newlangs->setDefaultLang('en_US');		// TODO Best is to used the language of customer.
			$newlangs->load("sellyoursaas@sellyoursaas");

			$private_note = $newlangs->trans("NoteForSupportUser");
			$emailsupport = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
			$signature = '--<br>Support team';

			if ($fordolibarr) {
				$sql = "INSERT INTO ".$prefix_db."user(login, lastname, admin, pass, pass_crypted, entity, datec, email, signature, api_key)";
				$sql .= " VALUES('".$newdb->escape($loginforsupport)."', '".$newdb->escape($loginforsupport)."', 1,";
				$sql .= " null,";
				$sql .= " '".$newdb->escape($password_crypted_for_remote)."', ";
				$sql .= " 0, '".$newdb->idate(dol_now())."', '".$newdb->escape($emailsupport)."', '".$newdb->escape($signature)."', ";
				$sql .= " '".$newdb->escape(dolEncrypt($password))."')";
				$resql=$newdb->query($sql);
				if (! $resql) {
					if ($newdb->lasterrno() != 'DB_ERROR_RECORD_ALREADY_EXISTS') dol_print_error($newdb);
					else setEventMessages("ErrorRecordAlreadyExists", null, 'errors');
				}

				$idofcreateduser = $newdb->last_insert_id($prefix_db.'user');
			} elseif ($forglpi) {
				$sql = "INSERT INTO glpi_users(name, password, authtype, date_mod, password_last_update, is_active)";
				$sql .= " VALUES('".$newdb->escape($loginforsupport)."',";
				$sql .= " MD5('".$newdb->escape($password)."'),";
				//$sql .= " '".$newdb->escape($password_crypted_for_remote)."', ";
				$sql .= " 1,";
				$sql .= " '".$newdb->idate(dol_now())."', ";
				$sql .= " '".$newdb->idate(dol_now())."', ";
				$sql .= " 1";
				$sql .= ")";
				$resql=$newdb->query($sql);
				if (! $resql) {
					if ($newdb->lasterrno() != 'DB_ERROR_RECORD_ALREADY_EXISTS') dol_print_error($newdb);
					else setEventMessages("ErrorRecordAlreadyExists", null, 'errors');
				} else {
					$insertedid = $newdb->last_insert_id('glpi_users', 'id');
					if ($insertedid > 0) {
						//$sql = "insert into glpi_profiles_users(users_id, profiles_id, entities_id, is_recursive) SELECT ".((int) $insertedid).", id, 0, 1 from glpi_profiles where interface = 'central'";
						$sql = "insert into glpi_profiles_users(users_id, profiles_id, entities_id, is_recursive) VALUES(".((int) $insertedid).", 4, 0, 1)";
						$resql=$newdb->query($sql);
					}
				}


				$idofcreateduser = $newdb->last_insert_id($prefix_db.'user');
			} else {
				// TODO
			}


			// Add all permissions on support user
			if ($fordolibarr) {
				$edituser = new User($newdb);
				$edituser->id = $idofcreateduser;
				$edituser->entity = 0;

				$resaddright = $edituser->addrights(0, 'allmodules', '', 0, 1);
				if ($resaddright <= 0) {
					setEventMessages('Failed to set all permissions : '.$edituser->error, $edituser->errors, 'warnings');
				}
			}

			setEventMessages('Password for user <b>'.$loginforsupport.'</b> set to <b>'.$password.'</b>', null, 'warnings');
		}
	}
	if ($action == "deletesupportuser") {
		$newdb = getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
		if (is_object($newdb)) {
			$fordolibarr = 1;
			if (preg_match('/glpi.*\.cloud/', $object->ref_customer)) {
				$fordolibarr = 0;
				$forglpi = 1;
			}

			if ($fordolibarr) {
				$sql="DELETE FROM ".$prefix_db."user_rights where fk_user IN (SELECT rowid FROM ".$prefix_db."user WHERE login = '".$newdb->escape($conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT)."')";
				$resql=$newdb->query($sql);
				if (! $resql) dol_print_error($newdb);

				$sql="DELETE FROM ".$prefix_db."user WHERE login = '".$newdb->escape($conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT)."'";
				$resql=$newdb->query($sql);
				if (! $resql) dol_print_error($newdb);
			} elseif ($forglpi) {
				$sql="DELETE FROM glpi_profiles_users WHERE users_id = (SELECT id FROM glpi_users WHERE name = '".$newdb->escape($conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT)."')";
				$resql=$newdb->query($sql);
				if (! $resql) dol_print_error($newdb);

				$sql="DELETE FROM glpi_users WHERE name = '".$newdb->escape($conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT)."'";
				$resql=$newdb->query($sql);
				if (! $resql) dol_print_error($newdb);
			}
		}
	}

	if ($action == "disableuser") {
		$newdb = getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
		if (is_object($newdb)) {
			// TODO Set definition to disable a user into the package
			$fordolibarr = 1;
			if (preg_match('/glpi.*\.cloud/', $object->ref_customer)) {
				$fordolibarr = 0;
				$forglpi = 1;
			}

			if ($fordolibarr) {
				$sql="UPDATE ".$prefix_db."user set statut=0 WHERE rowid = ".GETPOST('remoteid', 'int');
			} elseif ($forglpi) {
				$sql="UPDATE glpi_users set is_active = 0 WHERE rowid = ".GETPOST('remoteid', 'int');
			}

			$resql=$newdb->query($sql);
			if (! $resql) dol_print_error($newdb);
			else setEventMessages("UserDisabled", null, 'mesgs');
		}
	}
	if ($action == "enableuser") {
		$newdb = getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
		if (is_object($newdb)) {
			// TODO Set definition to disable a user into the package
			$fordolibarr = 1;
			if (preg_match('/glpi.*\.cloud/', $object->ref_customer)) {
				$fordolibarr = 0;
				$forglpi = 1;
			}

			if ($fordolibarr) {
				$sql="UPDATE ".$prefix_db."user set statut=1 WHERE rowid = ".GETPOST('remoteid', 'int');
			} elseif ($forglpi) {
				$sql="UPDATE glpi_users set is_active = 1 WHERE rowid = ".GETPOST('remoteid', 'int');
			}

			$resql=$newdb->query($sql);
			if (! $resql) dol_print_error($newdb);
			else setEventMessages("UserEnabled", null, 'mesgs');
		}
	}

	if ($action == "confirm_resetpassword") {
		$newdb = getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
		if (is_object($newdb)) {
			$password=GETPOST('newpassword', 'none');

			$fordolibarr = 1;
			if (preg_match('/glpi.*\.cloud/', $object->ref_customer)) {
				$fordolibarr = 0;
				$forglpi = 1;
			}


			if ($fordolibarr) {
				// Save setup and init env to have dol_hash ok for target instance
				$savMAIN_SECURITY_HASH_ALGO = getDolGlobalString('MAIN_SECURITY_HASH_ALGO');
				$savMAIN_SECURITY_SALT = getDolGlobalString('MAIN_SECURITY_SALT');

				// Get setup of remote
				$sql="SELECT value FROM ".$prefix_db."const WHERE name = 'MAIN_SECURITY_HASH_ALGO' ORDER BY entity LIMIT 1";
				$resql=$newdb->query($sql);
				if ($resql) {
					$obj = $newdb->fetch_object($resql);
					if ($obj) $conf->global->MAIN_SECURITY_HASH_ALGO = $obj->value;
				} else {
					setEventMessages("Failed to get remote MAIN_SECURITY_HASH_ALGO", null, 'warnings');
				}
				$sql="SELECT value FROM ".$prefix_db."const WHERE name = 'MAIN_SECURITY_SALT' ORDER BY entity LIMIT 1";
				$resql=$newdb->query($sql);
				if ($resql) {
					$obj = $newdb->fetch_object($resql);
					if ($obj) $conf->global->MAIN_SECURITY_SALT = $obj->value;
				} else {
					setEventMessages("Failed to get remote MAIN_SECURITY_SALT", null, 'warnings');
				}

				// Calculate hash using remote setup
				$password_crypted_for_remote = dol_hash($password);

				// Restore current setup
				$conf->global->MAIN_SECURITY_HASH_ALGO = $savMAIN_SECURITY_HASH_ALGO;
				$conf->global->MAIN_SECURITY_SALT = $savMAIN_SECURITY_SALT;
			}

			// TODO Set definition to update password of a userinto the package
			if (!empty($tmppackage->sqlpasswordreset)) {
				$substitutionarray = array(
					'__NEWUSERPASSWORD__' => $newdb->escape($password),
					'__NEWUSERPASSWORDCRYPTED__' => $newdb->escape($password_crypted_for_remote),
					'__REMOTEUSERID__' =>(int) GETPOST('remoteid', 'int')
				);
				$sql = make_substitutions($tmppackage->sqlpasswordreset, $substitutionarray);
			} else {
				if ($fordolibarr) {
					$sql="UPDATE ".$prefix_db."user set pass='".$newdb->escape($password)."', pass_crypted = '".$newdb->escape($password_crypted_for_remote)."' where rowid = ".((int) GETPOST('remoteid', 'int'));
				} elseif ($forglpi) {
					$sql="UPDATE glpi_users set password = MD5('".$newdb->escape($password)."') WHERE id = ".((int) GETPOST('remoteid', 'int'));
				}
			}

			$resql=$newdb->query($sql);
			if (! $resql) {
				dol_print_error($newdb);
			} else {
				setEventMessages("PasswordModified", null, 'mesgs');
			}
		}
	}

	if (! in_array($action, array('resetpassword', 'confirm_resetpassword', 'createsupportuser', 'deletesupportuser'))) {
		//include 'refresh_action.inc.php';

		$action = 'view';
	}
}


/*
 *	View
 */

$help_url='';
llxHeader('', $langs->trans("Users"), $help_url);

$form = new Form($db);
$formcompany = new FormCompany($db);

$countrynotdefined=$langs->trans("ErrorSetACountryFirst").' ('.$langs->trans("SeeAbove").')';

if ($action != 'create') {
	// Show tabs
	$head = contract_prepare_head($object);

	$title = $langs->trans("Contract");
	dol_fiche_head($head, 'users', $title, -1, 'contract');
}

if ($id > 0 && $action != 'edit' && $action != 'create') {
	/*
	 * Fiche en mode visualisation
	 */

	$newdb=getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);

	if (is_object($newdb) && $newdb->connected) {
		$fordolibarr = 1;
		if (preg_match('/glpi.*\.cloud/', $object->ref_customer)) {
			$fordolibarr = 0;
			$forglpi = 1;
		}

		// Get user/pass of last admin user
		if ($fordolibarr) {
			$sql="SELECT login, pass FROM ".$prefix_db."user WHERE admin = 1 ORDER BY statut DESC, datelastlogin DESC LIMIT 1";
		} elseif ($forglpi) {
			$sql="SELECT name as login, '' as pass FROM glpi_users WHERE 1 = 1 ORDER BY is_active DESC, last_login DESC LIMIT 1";
		}

		$resql=$newdb->query($sql);
		if ($resql) {
			$obj = $newdb->fetch_object($resql);
			$object->lastlogin_admin=$obj->login;
			$object->lastpass_admin=$obj->pass;
			$lastloginadmin=$object->lastlogin_admin;
			$lastpassadmin=$object->lastpass_admin;
		} else {
			setEventMessages('Failed to read remote customer instance: '.$newdb->lasterror(), null, 'warnings');
			$error++;
		}
	}
	//	else print 'Error, failed to connect';



	/*if (is_object($object->db2)) {
		$savdb=$object->db;
		$object->db=$object->db2;	// To have ->db to point to db2 for showrefnav function.  $db = master database
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
	$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1);
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

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', $nodbprefix, '', '', 1);

	/*if (is_object($object->db)) {
		$object->db = $savdb;
	}*/

	print '<div class="fichecenter">';
	print '</div>';
}

if ($id > 0) {
	dol_fiche_end();
}



$instance = 'xxxx';
$type_db = $conf->db->type;

$hostname_db = $object->array_options['options_hostname_db'];
$username_db = $object->array_options['options_username_db'];
$password_db = $object->array_options['options_password_db'];
$database_db = $object->array_options['options_database_db'];
$port_db     = $object->array_options['options_port_db'];
$prefix_db   = (empty($object->array_options['options_prefix_db']) ? 'llx_' : $object->array_options['options_prefix_db']);
$username_os = $object->array_options['options_username_os'];
$password_os = $object->array_options['options_password_os'];
$hostname_os = $object->array_options['options_hostname_os'];

$dbcustomerinstance=getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);

if (!$error && is_object($dbcustomerinstance) && $dbcustomerinstance->connected) {
	// Get user/pass of last admin user
	$sql="SELECT login, pass, pass_crypted FROM ".$prefix_db."user WHERE admin = 1 ORDER BY statut DESC, datelastlogin DESC LIMIT 1";
	// TODO Set definition of algorithm to hash password into the package
	if (preg_match('/glpi.*\.cloud/', $object->ref_customer)) {
		$sql="SELECT name as login, '' as pass, password as pass_crypted FROM glpi_users WHERE 1 = 1 ORDER BY is_active DESC, last_login DESC LIMIT 1";
	}

	$resql=$dbcustomerinstance->query($sql);
	if ($resql) {
		$obj = $dbcustomerinstance->fetch_object($resql);
		$object->lastlogin_admin=$obj->login;
		$object->lastpass_admin=$obj->pass;
		$lastloginadmin=$object->lastlogin_admin;
		$lastpassadmin=$object->lastpass_admin;
	} else {
		dol_print_error($dbcustomerinstance);
	}
}


if ($action == 'resetpassword') {
	include_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';

	$newpasswordforcustomerinstance = getRandomPassword(false);	// TODO Use setup of customer instance instead of backoffice instance

	$formquestion=array();
	$formquestion[] = array('type' => 'text','name' => 'newpassword','label' => $langs->trans("NewPassword"),'value' => $newpasswordforcustomerinstance);

	print $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&remoteid=' . GETPOST('remoteid', 'int'), $langs->trans('ResetPassword'), $langs->trans('ConfirmResetPassword'), 'confirm_resetpassword', $formquestion, 0, 1);
}

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
if (!$error) {
	print '<input type="hidden" name="action" value="list">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="id" value="'.$id.'">';
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';

	print_user_table($dbcustomerinstance, $object);

	print "<br>";
}
print '</form>'."\n";


// Barre d'actions
if (!$error && ! $user->socid) {
	print '<div class="tabsAction">';

	if ($user->rights->sellyoursaas->write) {
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=createsupportuser&token='.newToken().'">'.$langs->trans('CreateSupportUser').'</a>';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deletesupportuser&token='.newToken().'">'.$langs->trans('DeleteSupportUser').'</a>';
	}

	print "</div><br>";
}

if (is_object($newdb) && $newdb->connected) {
	$newdb->close();
}

llxFooter();

$db->close();


/**
 * Print list of users
 *
 * @param   string      $newdb          New db
 * @param   Contrat     $object         Object contract
 * @return  void
 */
function print_user_table($newdb, $object)
{
	global $db, $langs;
	global $id;

	$sortfield = GETPOST('sortfield', 'aZ09comma');
	$sortorder = GETPOST('sortorder', 'aZ09comma');

	$form = new Form($newdb);
	$arrayfields = array(
		'rowid'=>array('label'=>"ID", 'checked'=>1, 'position'=>10),
		'login'=>array('label'=>"Login", 'checked'=>1, 'position'=>15),
		'lastname'=>array('label'=>"Lastname", 'checked'=>1, 'position'=>20, 'csslist'=>'tdoverflowmax150'),
		'firstname'=>array('label'=>"Firstname", 'checked'=>1, 'position'=>50, 'csslist'=>'tdoverflowmax150'),
		'admin'=>array('label'=>"Admin", 'checked'=>1, 'position'=>22),
		'email'=>array('label'=>"Email", 'checked'=>1, 'position'=>25),
		'pass'=>array('label'=>"Pass", 'checked'=>1, 'position'=>27),
		'datec'=>array('label'=>"DateCreation", 'checked'=>1, 'position'=>31),
		'datem'=>array('label'=>"DateModification", 'checked'=>1, 'position'=>32),
		'datelastlogin'=>array('label'=>"DateLastLogin", 'checked'=>1, 'position'=>35),
		'iplastlogin'=>array('label'=>"IPLastLogin", 'checked'=>0, 'position'=>36),
		'datepreviouslogin'=>array('label'=>"DatePreviousLogin", 'checked'=>0, 'position'=>37),
		'ippreviouslogin'=>array('label'=>"IPPreviousLogin", 'checked'=>0, 'position'=>38),
		'entity'=>array('label'=>"Entity", 'checked'=>1, 'position'=>100),
		'fk_soc'=>array('label'=>"ParentsId", 'checked'=>1, 'position'=>105),
		'statut'=>array('label'=>"Status", 'checked'=>1, 'position'=>110),
	);
	if (!$sortfield) {
		$sortfield = key($arrayfields); // Set here default search field. By default 1st field in definition.
	}
	if (!$sortorder) {
		$sortorder = "ASC";
	}

	$prefix_db   = (empty($object->array_options['options_prefix_db']) ? 'llx_' : $object->array_options['options_prefix_db']);

	$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
	$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields

	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';

	$cssforfield = '';
	// Nb of users
	print '<tr class="liste_titre">';
	print '<td>#</td>';
	foreach ($arrayfields as $key => $value) {
		if ($key == 'statut') {
			$cssforfield = ($cssforfield ? ' ' : '').'center';
		} else {
			$cssforfield = (empty($value['csslist']) ? '' : $value['csslist']);
		}
		if (!empty($arrayfields[$key]['checked'])) {
			print getTitleFieldOfList($arrayfields[$key]['label'], 0, $_SERVER['PHP_SELF'], $key, '', "&id=".$id, ($cssforfield ? 'class="'.$cssforfield.'"' : ''), $sortfield, $sortorder, ($cssforfield ? $cssforfield.' ' : ''))."\n";
		}
	}
	print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', "", "", 'center maxwidthsearch ')."\n";
	print '</tr>';

	if (is_object($newdb) && $newdb->connected) {
		$fordolibarr = 1;
		if (preg_match('/glpi.*\.cloud/', $object->ref_customer)) {
			$fordolibarr = 0;
			$forglpi = 1;
		}

		$sql = '';

		// Get user/pass of all users in database
		if ($fordolibarr) {
			$sql = "SELECT rowid, login, lastname, firstname, admin, email, pass, pass_crypted, datec, tms as datem, datelastlogin, fk_soc, fk_socpeople, fk_member, entity, statut";
			$sql .= " FROM ".$prefix_db."user";
			$sql .= $newdb->order($sortfield, $sortorder);

			$resql=$newdb->query($sql);
			if (empty($resql)) {	// Alternative for Dolibarr 3.7-
				$sql = "SELECT rowid, login, lastname as lastname, firstname, admin, email, pass, pass_crypted, datec, tms as datem, datelastlogin, fk_societe, fk_socpeople, fk_member, entity, statut";
				$sql .= " FROM ".$prefix_db."user";
				$sql .= $newdb->order($sortfield, $sortorder);
				$resql = $newdb->query($sql);
				if (empty($resql)) {	// Alternative for Dolibarr 3.3-
					$sql = "SELECT rowid, login, nom as lastname, prenom as firstname, admin, email, pass, pass_crypted, datec, tms as datem, datelastlogin, fk_societe, fk_socpeople, fk_member, entity, statut";
					$sql .= " FROM ".$prefix_db."user";
					$sql .= $newdb->order($sortfield, $sortorder);
				}
			}
		} elseif ($forglpi) {
			$sql = "SELECT DISTINCT gu.id as rowid, gu.name as login, gu.realname as lastname, gu.firstname, gp.interface as admin, '' as pass, gu.password as pass_crypted, gu.date_creation as datec, gu.date_mod as datem, gu.last_login as datelastlogin, 0, 0, 0, gu.entities_id as entity, gu.is_active as statut,";
			$sql .= " glpi_useremails.email as email";
			$sql .= " FROM glpi_users as gu";
			$sql .= " LEFT JOIN glpi_useremails ON glpi_useremails.users_id = gu.id";
			$sql .= " LEFT JOIN glpi_profiles_users as gpu ON gpu.users_id = gu.id";
			$sql .= " LEFT JOIN glpi_profiles as gp ON gpu.profiles_id = gp.id AND gp.interface = 'central'";
			$sql .= " WHERE gu.id not in (select gu2.id from glpi_users as gu2 where gu2.is_deleted = 1)";
			$sql .= " ORDER BY gu.is_active DESC";
		}

		$resql=$newdb->query($sql);

		if ($resql) {
			$num=$newdb->num_rows($resql);
			$i=0;
			while ($i < $num) {
				$obj = $newdb->fetch_object($resql);

				global $object;
				$url='https://'.$object->ref_customer.'?username='.$obj->login.'&amp;password='.$obj->pass;
				print '<tr class="oddeven">';
				print '<td>';
				print ($i+1);
				print '</td>';
				foreach ($arrayfields as $key => $value) {
					$cssforfield = (empty($value['csslist']) ? '' : $value['csslist']);

					if (! empty($arrayfields[$key]['checked'])) {
						if ($key == 'statut') {
							if ($obj->statut) {
								print '<td class="center">';
								print '<a href="'.$_SERVER["PHP_SELF"].'?action=disableuser&token='.newToken().'&remoteid='.$obj->rowid.'&id='.$id.'"><span class="fa fa-toggle-on marginleftonly valignmiddle" style="font-size: 2em; color: #227722;" alt="Activated" title="Activated"></span></a>';
								print '</td>';
							} else {
								print '<td class="center">';
								print '<a href="'.$_SERVER["PHP_SELF"].'?action=enableuser&token='.newToken().'&remoteid='.$obj->rowid.'&id='.$id.'"><span class="fa fa-toggle-off marginleftonly valignmiddle" style="font-size: 2em; color: #888888;" alt="Disabled" title="Disabled"></span></a>';
								print '</td>';
							}
						} elseif ($key == 'pass') {
							$valtoshow = ($obj->pass ? $obj->pass.' (' : '').($obj->pass_crypted?$obj->pass_crypted:'NA').($obj->pass ? ')' : '');
							print '<td class="tdoverflowmax100" title="'.$valtoshow.'">'.$valtoshow.'</td>';
						} elseif ($key == 'login') {
							print '<td class="nowraponall">';
							print $obj->$key;
							print ' <a target="_customerinstance" href="'.$url.'">'.img_object('', 'globe').'</a>';
							print '</td>';
						} elseif ($key == 'email') {
							print '<td>'.dol_print_email($obj->$key, (empty($obj->fk_socpeople) ? 0 : $obj->fk_socpeople), (empty($obj->fk_soc) ? 0 : $obj->fk_soc), 1).'</td>';
						} elseif ($key == 'datec' || $key == 'datem' || $key == 'datelastlogin') {
							print '<td>'.dol_print_date($newdb->jdate($obj->$key), 'dayhour', 'tzuserrel').'</td>';
						} else {
							print '<td'.($cssforfield ? ' class="'.$cssforfield.'"' : '').' title="'.$db->escape((empty($obj->$key) ? '' : $obj->$key)).'">';
							print (empty($obj->$key) ? '' : $obj->$key);
							print '</td>';
						}
					}
				}
				print '<td align="center">';
				print '<a href="'.$_SERVER["PHP_SELF"].'?action=resetpassword&token='.newToken().'&remoteid='.$obj->rowid.'&id='.$id.'">'.img_picto('ResetPassword', 'object_technic').'</a>';
				print '</td>';
				print '</tr>';
				$i++;
			}
		} else {
			dol_print_error($newdb);
		}
	} else {
		print '<tr><td class="opacitymedium" colspan="15">'.$langs->trans("FailedToConnectMayBeOldInstance").'</td></tr>';
	}

	print "</table>";
	print "</div>";
}
