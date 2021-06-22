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
require_once DOL_DOCUMENT_ROOT."/core/lib/contract.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/company.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/date.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formcompany.class.php";
dol_include_once("/sellyoursaas/core/lib/dolicloud.lib.php");

$langs->loadLangs(array("admin","companies","users","contracts","other","commercial","sellyoursaas@sellyoursaas"));

$action		= (GETPOST('action', 'alpha') ? GETPOST('action', 'alpha') : 'view');
$confirm	= GETPOST('confirm', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$id			= GETPOST('id', 'int');
$ref        = GETPOST('ref', 'alpha');
$error = 0; $errors = array();

if (!$sortorder) {
	$sortorder = "ASC";
}

if ($action != 'create') {
	$object = new Contrat($db);
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
$prefix_db   = $object->array_options['options_prefix_db'];
$port_db     = $object->array_options['options_port_db'];
$username_web = $object->array_options['options_username_os'];
$password_web = $object->array_options['options_password_os'];
$hostname_os = $object->array_options['options_hostname_os'];

if (empty($prefix_db)) $prefix_db = 'llx_';

// Security check
$result = restrictedArea($user, 'sellyoursaas', 0, '', '');


/*
 *	Actions
 */

$parameters=array('id'=>$id, 'objcanvas'=>$objcanvas);
$reshook=$hookmanager->executeHooks('doActions', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks

if (empty($reshook)) {
	// Cancel
	if (GETPOST('cancel', 'alpha') && ! empty($backtopage)) {
		header("Location: ".$backtopage);
		exit;
	}
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	if ($action == "createsupportuser") {
		$newdb=getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
		if (is_object($newdb)) {
			$savMAIN_SECURITY_HASH_ALGO = $conf->global->MAIN_SECURITY_HASH_ALGO;
			$savMAIN_SECURITY_SALT = $conf->global->MAIN_SECURITY_SALT;

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

			$password = $conf->global->SELLYOURSAAS_PASSWORD_FOR_SUPPORT;

			// Calculate hash with remote setup
			$password_crypted_for_remote = dol_hash($password);

			// Set language to use for notes on the user we will create.
			$newlangs = new Translate('', $conf);
			$newlangs->setDefaultLang('en_US');		// TODO Best is to used the language of customer.
			$newlangs->load("sellyoursaas@sellyoursaas");

			// Restore current setup
			$conf->global->MAIN_SECURITY_HASH_ALGO = $savMAIN_SECURITY_HASH_ALGO;
			$conf->global->MAIN_SECURITY_SALT = $savMAIN_SECURITY_SALT;
			$private_note = $newlangs->trans("NoteForSupportUser");
			$emailsupport = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
			$signature = '--<br>Support team';

			$sql = "INSERT INTO ".$prefix_db."user(login, lastname, admin, pass, pass_crypted, entity, datec, note, email, signature)";
			$sql .= " VALUES('".$conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT."', '".$newdb->escape($conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT)."', 1,";
			$sql .= " ".(empty($conf->global->SELLYOURSAAS_DEPRECATED_CLEAR_PASSWORD) ? 'null' : "'".$newdb->escape($conf->global->SELLYOURSAAS_PASSWORD_FOR_SUPPORT)."'").",";
			$sql .= " '".$newdb->escape($password_crypted_for_remote)."', ";
			$sql .= " 0, '".$newdb->idate(dol_now())."', '".$newdb->escape($private_note)."', '".$newdb->escape($emailsupport)."', '".$newdb->escape($signature)."')";
			$resql=$newdb->query($sql);
			if (! $resql) {
				if ($newdb->lasterrno() != 'DB_ERROR_RECORD_ALREADY_EXISTS') dol_print_error($newdb);
				else setEventMessages("ErrorRecordAlreadyExists", null, 'errors');
			}

			$idofcreateduser = $newdb->last_insert_id($prefix_db.'user');

			// Add all permissions on support user
			$edituser = new User($newdb);
			$edituser->id = $idofcreateduser;
			$edituser->entity = 0;

			$resaddright = $edituser->addrights(0, 'allmodules', '', 0, 1);
			if ($resaddright <= 0) {
				setEventMessages('Failed to set all permissions : '.$edituser->error, $edituser->errors, 'warnings');
			}
		}
	}
	if ($action == "deletesupportuser") {
		$newdb=getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
		if (is_object($newdb)) {
			$sql="DELETE FROM ".$prefix_db."user_rights where fk_user IN (SELECT rowid FROM llx_user WHERE login = '".$conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT."')";
			$resql=$newdb->query($sql);
			if (! $resql) dol_print_error($newdb);

			// Get user/pass of last admin user
			$sql="DELETE FROM ".$prefix_db."user WHERE login = '".$conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT."'";
			$resql=$newdb->query($sql);
			if (! $resql) dol_print_error($newdb);
		}
	}

	if ($action == "disableuser") {
		$newdb=getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
		if (is_object($newdb)) {
			// TODO Set definition to disable a user into the package
			$sql="UPDATE ".$prefix_db."user set statut=0 WHERE rowid = ".GETPOST('remoteid', 'int');
			if (preg_match('/glpi-network\.cloud/', $object->ref_customer)) {
				$sql="UPDATE ".$prefix_db."glpi_user set is_active=FALSE WHERE rowid = ".GETPOST('remoteid', 'int');
			}

			$resql=$newdb->query($sql);
			if (! $resql) dol_print_error($newdb);
			else setEventMessages("UserDisabled", null, 'mesgs');
		}
	}
	if ($action == "enableuser") {
		$newdb=getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
		if (is_object($newdb)) {
			// TODO Set definition to disable a user into the package
			$sql="UPDATE ".$prefix_db."user set statut=1 WHERE rowid = ".GETPOST('remoteid', 'int');
			if (preg_match('/glpi-network\.cloud/', $object->ref_customer)) {
				$sql="UPDATE ".$prefix_db."glpi_user set is_active=TRUE WHERE rowid = ".GETPOST('remoteid', 'int');
			}

			$resql=$newdb->query($sql);
			if (! $resql) dol_print_error($newdb);
			else setEventMessages("UserEnabled", null, 'mesgs');
		}
	}

	if ($action == "confirm_resetpassword") {
		$newdb=getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
		if (is_object($newdb)) {
			$password=GETPOST('newpassword', 'none');

			// TODO Use the encryption of remote instance.
			// Currently, we use admin setup or sellyoursaas setup if defined
			$savsalt = $conf->global->MAIN_SECURITY_SALT;
			$savalgo = $conf->global->MAIN_SECURITY_HASH_ALGO;
			if (! empty($conf->global->SELLYOURSAAS_SALTFORPASSWORDENCRYPTION)) {
				$conf->global->MAIN_SECURITY_SALT = $conf->global->SELLYOURSAAS_SALTFORPASSWORDENCRYPTION;
			}
			if (! empty($conf->global->SELLYOURSAAS_HASHALGOFORPASSWORD)) {
				$conf->global->MAIN_SECURITY_HASH_ALGO = $conf->global->SELLYOURSAAS_HASHALGOFORPASSWORD;
			}

			$password_crypted = dol_hash($password);

			$conf->global->MAIN_SECURITY_SALT = $savsalt;
			$conf->global->MAIN_SECURITY_HASH_ALGO = $savalgo;

			// TODO Set definition of algorithm to hash password into the package
			if (preg_match('/glpi-network\.cloud/', $object->ref_customer)) {
				if (!empty($conf->global->MAIN_SHOW_PASSWORD_INTO_LOG)) {
					dol_syslog("new password=".$password);
				}
				$password_crypted = md5($password);
			}

			// TODO Set definition to update password of a userinto the package
			$sql="UPDATE ".$prefix_db."user set pass='".$newdb->escape($password)."', pass_crypted = '".$newdb->escape($password_crypted)."' where rowid = ".((int) GETPOST('remoteid', 'int'));
			if (preg_match('/glpi-network\.cloud/', $object->ref_customer)) {
				$sql="UPDATE glpi_users set password='".$newdb->escape($password_crypted)."' WHERE id = ".((int) GETPOST('remoteid', 'int'));
			}


			$resql=$newdb->query($sql);
			if (! $resql) dol_print_error($newdb);
			else setEventMessages("PasswordModified", null, 'mesgs');
		}
	}

	if (! in_array($action, array('resetpassword', 'confirm_resetpassword', 'createsupportuser', 'deletesupportuser'))) {
		include 'refresh_action.inc.php';

		$action = 'view';
	}
}


/*
 *	View
 */

$help_url='';
llxHeader('', $langs->trans("Users"), $help_url);

$form = new Form($db);
$form2 = new Form($db2);
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
		// Get user/pass of last admin user
		$sql="SELECT login, pass FROM llx_user WHERE admin = 1 ORDER BY statut DESC, datelastlogin DESC LIMIT 1";
		// TODO Set definition to read users table into the package
		if (preg_match('/glpi-network\.cloud/', $object->ref_customer)) {
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
			setEventMessages('Failed to read remote customer instance: '.$newdb->lasterror(), '', 'warnings');
			$error++;
		}
	}
	//	else print 'Error, failed to connect';



	if (is_object($object->db2)) {
		$savdb=$object->db;
		$object->db=$object->db2;	// To have ->db to point to db2 for showrefnav function.  $db = master database
	}

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
	if (! empty($conf->projet->enabled)) {
		$langs->load("projects");
		$morehtmlref.='<br>'.$langs->trans('Project') . ' : ';
		if (0) {
			if ($action != 'classify')
				$morehtmlref.='<a class="editfielda" href="' . $_SERVER['PHP_SELF'] . '?action=classify&amp;id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetProject')) . '</a> : ';
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

	if (is_object($object->db2)) {
		$object->db=$savdb;
	}

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
$username_web = $object->array_options['options_username_os'];
$password_web = $object->array_options['options_password_os'];
$hostname_os = $object->array_options['options_hostname_os'];

$dbcustomerinstance=getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);

if (!$error && is_object($dbcustomerinstance) && $dbcustomerinstance->connected) {
	// Get user/pass of last admin user
	$sql="SELECT login, pass, pass_crypted FROM llx_user WHERE admin = 1 ORDER BY statut DESC, datelastlogin DESC LIMIT 1";
	// TODO Set definition of algorithm to hash password into the package
	if (preg_match('/glpi-network\.cloud/', $object->ref_customer)) {
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
	print '<table class="border" width="100%">';

	print_user_table($dbcustomerinstance, $object);

	print "</table><br>";
}
print '</form>'."\n";


// Application instance url
if (!$error) {
	if (empty($lastpassadmin)) {
		if (! empty($object->array_options['options_deployment_init_adminpass'])) {
			$url='https://'.$object->ref_customer.'?username='.$lastloginadmin.'&amp;password='.$object->array_options['options_deployment_init_adminpass'];
			$link='<a href="'.$url.'" target="_blank" id="dollink">'.$url.'</a>';
			$links.='Link to application (initial install pass) : ';
		} else {
			$url='https://'.$object->ref_customer.'?username='.$lastloginadmin;
			$link='<a href="'.$url.'" target="_blank" id="dollink">'.$url.'</a>';
			$links.='Link to application : ';
		}
	} else {
		$url='https://'.$object->ref_customer.'?username='.$lastloginadmin.'&amp;password='.$lastpassadmin;
		$link='<a href="'.$url.'" target="_blank" id="dollink">'.$url.'</a>';
		$links.='Link to application (last logged admin) : ';
	}
	print $links.$link;

	print '<br>';
}


// Barre d'actions
if (!$error && ! $user->societe_id) {
	print '<div class="tabsAction">';

	if ($user->rights->sellyoursaas->write) {
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=createsupportuser&amp;token='.newToken().'">'.$langs->trans('CreateSupportUser').'</a>';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=deletesupportuser&amp;token='.newToken().'">'.$langs->trans('DeleteSupportUser').'</a>';
	}

	print "</div><br>";
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
	global $langs;
	global $id;
	$form = new Form($db);
	$arrayfields = array(
		'rowid'=>array('label'=>$langs->trans("ID"), 'checked'=>1, 'position'=>10),
		'login'=>array('label'=>$langs->trans("Login"), 'checked'=>1, 'position'=>15),
		'lastname'=>array('label'=>$langs->trans("Lastname"), 'checked'=>1, 'position'=>20),
		'firstname'=>array('label'=>$langs->trans("Firstname"), 'checked'=>1, 'position'=>50),
		'admin'=>array('label'=>$langs->trans("Admin"), 'checked'=>1, 'position'=>22),
		'email'=>array('label'=>$langs->trans("Email"), 'checked'=>1, 'position'=>25),
		'pass'=>array('label'=>$langs->trans("Pass"), 'checked'=>1, 'position'=>27),
		'datec'=>array('label'=>$langs->trans("DateCreation"), 'checked'=>1, 'position'=>31),
		'datem'=>array('label'=>$langs->trans("DateModification"), 'checked'=>1, 'position'=>32),
		'datelastlogin'=>array('label'=>$langs->trans("DateLastLogin"), 'checked'=>1, 'position'=>35),
		'entity'=>array('label'=>$langs->trans("Entity"), 'checked'=>1, 'position'=>100),
		'fk_soc'=>array('label'=>$langs->trans("ParentsId"), 'checked'=>1, 'position'=>105),
		'statut'=>array('label'=>$langs->trans("Status"), 'checked'=>1, 'position'=>110),
	);
	print '<table class="noborder" width="100%">';

	// Nb of users
	print '<tr class="liste_titre">';
	print '<td>#</td>';
	foreach ($arrayfields as $key => $value) {
		if ($key == 'statut') {
			$cssforfield = ($cssforfield ? ' ' : '').'center';
		}else{
			$cssforfield = "";
		}
		if (!empty($arrayfields[$key]['checked'])) {
			print getTitleFieldOfList($arrayfields[$key]['label'], 0, $_SERVER['PHP_SELF'], $key, '', "", ($cssforfield ? 'class="'.$cssforfield.'"' : ''), "", "", ($cssforfield ? $cssforfield.' ' : ''))."\n";
		}
	}
	print '<td></td>';
	
	$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
	$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields
	print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', "", "", 'center maxwidthsearch ')."\n";	
	print '</tr>';

	if (is_object($newdb) && $newdb->connected) {
		// Get user/pass of all users in database
		$sql ="SELECT rowid, login, lastname, firstname, admin, email, pass, pass_crypted, datec, tms as datem, datelastlogin, fk_soc, fk_socpeople, fk_member, entity, statut";
		$sql.=" FROM llx_user ORDER BY statut DESC";

		// TODO Set definition of SQL to get list of all users into the package
		if (preg_match('/glpi-network\.cloud/', $object->ref_customer)) {
			$sql = "SELECT DISTINCT gu.id as rowid, gu.name as login, gu.realname as lastname, gu.firstname, gp.interface as admin, '' as pass, gu.password as pass_crypted, gu.date_creation as datec, gu.date_mod as datem, gu.last_login as datelastlogin, 0, 0, 0, gu.entities_id as entity, gu.is_active as statut,";
			//$sql = "SELECT DISTINCT gu.id as rowid, gu.name as login, gu.realname as lastname, gu.firstname, concat(gp.name, ' ', gp.interface) as admin, '' as pass, gu.password as pass_crypted, gu.date_creation as datec, gu.date_mod as datem, gu.last_login as datelastlogin, 0, 0, 0, gu.entities_id as entity, gu.is_active as statut,";
			$sql .= " glpi_useremails.email as email";
			$sql .= " FROM glpi_users as gu";
			$sql .= " LEFT JOIN glpi_useremails ON glpi_useremails.users_id = gu.id";
			$sql .= " LEFT JOIN glpi_profiles_users as gpu ON gpu.users_id = gu.id LEFT JOIN glpi_profiles as gp ON gpu.profiles_id = gp.id AND gp.interface = 'central'";
			$sql .= " WHERE gu.id not in (select gu2.id from glpi_users as gu2 where gu2.name = 'supportcloud' OR gu2.is_deleted = 1)";
			// TODO Limit payant uniquement
			$sql .= " ORDER BY gu.is_active DESC";
		}

		$resql=$newdb->query($sql);
		if (empty($resql)) {	// Alternative for Dolibarr 3.7-
			$sql ="SELECT rowid, login, lastname as lastname, firstname, admin, email, pass, pass_crypted, datec, tms as datem, datelastlogin, fk_societe, fk_socpeople, fk_member, entity, statut";
			$sql.=" FROM llx_user ORDER BY statut DESC";
			$resql=$newdb->query($sql);
			if (empty($resql)) {	// Alternative for Dolibarr 3.3-
				$sql ="SELECT rowid, login, nom as lastname, prenom as firstname, admin, email, pass, pass_crypted, datec, tms as datem, datelastlogin, fk_societe, fk_socpeople, fk_member, entity, statut";
				$sql.=" FROM llx_user ORDER BY statut DESC";
				$resql=$newdb->query($sql);
			}
		}

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
				print '<td>';
				print $obj->rowid;
				print '</td>';
				print '<td class="nowraponall">';
				print $obj->login;
				print ' <a target="_customerinstance" href="'.$url.'">'.img_object('', 'globe').'</a>';
				print '</td>';
				print '<td>'.$obj->lastname.'</td>';
				print '<td>'.$obj->firstname.'</td>';
				print '<td>'.$obj->admin.'</td>';
				print '<td>'.$obj->email.'</td>';
				$valtoshow = ($obj->pass ? $obj->pass.' (' : '').($obj->pass_crypted?$obj->pass_crypted:'NA').($obj->pass ? ')' : '');
				print '<td class="tdoverflowmax100" title="'.$valtoshow.'">'.$valtoshow.'</td>';
				print '<td>'.dol_print_date($newdb->jdate($obj->datec), 'dayhour').'</td>';
				print '<td>'.dol_print_date($newdb->jdate($obj->datem), 'dayhour').'</td>';
				print '<td>'.dol_print_date($newdb->jdate($obj->datelastlogin), 'dayhour').'</td>';
				print '<td>'.$obj->entity.'</td>';
				print '<td>';
				$txtparent='';
				if ($obj->fk_user > 0)      $txtparent.=($txtparent?'<br>':'').'Parent user: '.$obj->fk_user;
				if ($obj->fk_soc > 0)       $txtparent.=($txtparent?'<br>':'').'Parent thirdparty: '.$obj->fk_soc;
				if ($obj->fk_socpeople > 0) $txtparent.=($txtparent?'<br>':'').'Parent contact: '.$obj->fk_socpeople;
				if ($obj->fk_member > 0)    $txtparent.=($txtparent?'<br>':'').'Parent member: '.$obj->fk_member;
				print $txtparent;
				print '</td>';
				print '<td class="center">';
				if ($obj->statut) {
					print '<a href="'.$_SERVER["PHP_SELF"].'?action=disableuser&remoteid='.$obj->rowid.'&id='.$id.'"><span class="fa fa-toggle-on marginleftonly valignmiddle" style="font-size: 2em; color: #227722;" alt="Activated" title="Activated"></span></a>';
				} else {
					print '<a href="'.$_SERVER["PHP_SELF"].'?action=enableuser&remoteid='.$obj->rowid.'&id='.$id.'"><span class="fa fa-toggle-off marginleftonly valignmiddle" style="font-size: 2em; color: #888888;" alt="Disabled" title="Disabled"></span></a>';
				}
				print '</td>';
				print '<td align="right">';
				print '<a href="'.$_SERVER["PHP_SELF"].'?action=resetpassword&remoteid='.$obj->rowid.'&id='.$id.'">'.img_picto('ResetPassword', 'object_technic').'</a>';
				print '</td>';
				print '<td>';
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
}
