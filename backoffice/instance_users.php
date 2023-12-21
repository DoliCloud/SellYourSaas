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
 *       \file       htdocs/sellyoursaas/backoffice/instance_users.php
 *       \ingroup    societe
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
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT."/core/lib/contract.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/company.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/date.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formcompany.class.php";
dol_include_once("/sellyoursaas/core/lib/sellyoursaas.lib.php");
dol_include_once("/sellyoursaas/class/sellyoursaascontract.class.php");
dol_include_once('/sellyoursaas/class/packages.class.php');

$langs->loadLangs(array("admin","companies","users","contracts","other","commercial","sellyoursaas@sellyoursaas"));

$action		= (GETPOST('action', 'alpha') ? GETPOST('action', 'alpha') : 'view');
$confirm	= GETPOST('confirm', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$contextpage = 'instance_user';
$optioncss  = GETPOST('optioncss', 'alpha');

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');

$id			= GETPOST('id', 'int');
$ref        = GETPOST('ref', 'alpha');

$error = 0;

if (!$sortorder) {
	$sortorder = "ASC";
}

if ($action != 'create') {
	$object = new SellYourSaasContract($db);
}

// Initialize array of search criterias
$search_all = trim(GETPOST('search_all', 'alphanohtml'));
$search = array();
$arrayoffields = array('rowid', 'login', 'firstname', 'lastname', 'admin', 'email');
foreach ($arrayoffields as $key) {
	if (GETPOST('search_'.$key, 'alpha') !== '') {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
	if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
		$search[$key.'_dtstart'] = dol_mktime(0, 0, 0, GETPOST('search_'.$key.'_dtstartmonth', 'int'), GETPOST('search_'.$key.'_dtstartday', 'int'), GETPOST('search_'.$key.'_dtstartyear', 'int'));
		$search[$key.'_dtend'] = dol_mktime(23, 59, 59, GETPOST('search_'.$key.'_dtendmonth', 'int'), GETPOST('search_'.$key.'_dtendday', 'int'), GETPOST('search_'.$key.'_dtendyear', 'int'));
	}
}


// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array array
include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
$hookmanager=new HookManager($db);
$hookmanager->initHooks(array('contractcard'));


if ($id > 0 || $ref) {
	$result=$object->fetch($id, $ref);
	if ($result < 0) {
		setEventMessages('Failed to read remote customer instance: '.$object->error, null, 'warnings');
		$error++;
	}
	$id = $object->id;
}

$backupstring = getDolGlobalString('DOLICLOUD_SCRIPTS_PATH').'/backup_instance.php '.$object->instance.' ' . getDolGlobalString('DOLICLOUD_INSTANCES_PATH');


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

if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array('id'=>$id);
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
	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		foreach ($arrayoffields as $key) {
			$search[$key] = '';
			if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
				$search[$key.'_dtstart'] = '';
				$search[$key.'_dtend'] = '';
			}
		}
		$toselect = array();
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	if ($action == "createsupportuser") {
		$newdb = getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
		$newdb->prefix_db = $prefix_db;

		if (is_object($newdb)) {
			// Get login and password for support
			$loginforsupport = getDolGlobalString('SELLYOURSAAS_LOGIN_FOR_SUPPORT');

			$password = getDolGlobalString('SELLYOURSAAS_SUPPORT_DEFAULT_PASSWORD');
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
					if ($obj) {
						$conf->global->MAIN_SECURITY_HASH_ALGO = $obj->value;
					}
				} else {
					setEventMessages("Failed to get remote MAIN_SECURITY_HASH_ALGO", null, 'warnings');
				}
				$sql="SELECT value FROM ".$prefix_db."const WHERE name = 'MAIN_SECURITY_SALT' ORDER BY entity LIMIT 1";
				$resql=$newdb->query($sql);
				if ($resql) {
					$obj = $newdb->fetch_object($resql);
					if ($obj) {
						$conf->global->MAIN_SECURITY_SALT = $obj->value;
					}
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
				$sql .= " '".$newdb->escape(dolEncrypt($password, '', '', 'dolibarr'))."')";
				$resql=$newdb->query($sql);
				if (! $resql) {
					if ($newdb->lasterrno() != 'DB_ERROR_RECORD_ALREADY_EXISTS') {
						dol_print_error($newdb);
					} else {
						setEventMessages("ErrorRecordAlreadyExists", null, 'errors');
					}
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
					if ($newdb->lasterrno() != 'DB_ERROR_RECORD_ALREADY_EXISTS') {
						dol_print_error($newdb);
					} else {
						setEventMessages("ErrorRecordAlreadyExists", null, 'errors');
					}
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
				if (! $resql) {
					dol_print_error($newdb);
				}

				$sql="DELETE FROM ".$prefix_db."user WHERE login = '".$newdb->escape($conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT)."'";
				$resql=$newdb->query($sql);
				if (! $resql) {
					dol_print_error($newdb);
				}
			} elseif ($forglpi) {
				$sql="DELETE FROM glpi_profiles_users WHERE users_id = (SELECT id FROM glpi_users WHERE name = '".$newdb->escape($conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT)."')";
				$resql=$newdb->query($sql);
				if (! $resql) {
					dol_print_error($newdb);
				}

				$sql="DELETE FROM glpi_users WHERE name = '".$newdb->escape($conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT)."'";
				$resql=$newdb->query($sql);
				if (! $resql) {
					dol_print_error($newdb);
				}
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
			if (! $resql) {
				dol_print_error($newdb);
			} else {
				setEventMessages("UserDisabled", null, 'mesgs');
			}
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
			if (! $resql) {
				dol_print_error($newdb);
			} else {
				setEventMessages("UserEnabled", null, 'mesgs');
			}
		}
	}

	if ($action == "confirm_resetpassword" && GETPOST('confirm') != 'no') {
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
					if ($obj) {
						$conf->global->MAIN_SECURITY_HASH_ALGO = $obj->value;
					}
				} else {
					setEventMessages("Failed to get remote MAIN_SECURITY_HASH_ALGO", null, 'warnings');
				}
				$sql="SELECT value FROM ".$prefix_db."const WHERE name = 'MAIN_SECURITY_SALT' ORDER BY entity LIMIT 1";
				$resql=$newdb->query($sql);
				if ($resql) {
					$obj = $newdb->fetch_object($resql);
					if ($obj) {
						$conf->global->MAIN_SECURITY_SALT = $obj->value;
					}
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

$form = new Form($db);
$formcompany = new FormCompany($db);

$title = $langs->trans("Users");
$help_url='';

// Output page
// --------------------------------------------------------------------

llxHeader('', $title, $help_url);

$param = '';
if (!empty($mode)) {
	$param .= '&mode='.urlencode($mode);
}
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($id > 0) {
	$param .= '&id='.((int) $id);
}
/*if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}*/
if ($optioncss != '') {
	$param .= '&optioncss='.urlencode($optioncss);
}
foreach ($search as $key => $val) {
	if (is_array($search[$key])) {
		foreach ($search[$key] as $skey) {
			if ($skey != '') {
				$param .= '&search_'.$key.'[]='.urlencode($skey);
			}
		}
	} elseif (preg_match('/(_dtstart|_dtend)$/', $key) && !empty($val)) {
		$param .= '&search_'.$key.'month='.((int) GETPOST('search_'.$key.'month', 'int'));
		$param .= '&search_'.$key.'day='.((int) GETPOST('search_'.$key.'day', 'int'));
		$param .= '&search_'.$key.'year='.((int) GETPOST('search_'.$key.'year', 'int'));
	} elseif ($search[$key] != '') {
		$param .= '&search_'.$key.'='.urlencode($search[$key]);
	}
}
// Add $param from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';
// Add $param from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSearchParam', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
$param .= $hookmanager->resPrint;

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

	if ($object->array_options['options_deployment_status'] !== 'undeployed') {
		$newdb=getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
	}

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

if ($object->array_options['options_deployment_status'] !== 'undeployed') {
	$dbcustomerinstance=getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
}

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

	if ($user->hasRight('sellyoursaas', 'write')) {
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=createsupportuser&token='.newToken().($sortfield ? '&sortfield='.$sortfield.'&sortorder='.$sortorder : '').'">'.$langs->trans('CreateSupportUser').'</a>';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deletesupportuser&token='.newToken().($sortfield ? '&sortfield='.$sortfield.'&sortorder='.$sortorder : '').'">'.$langs->trans('DeleteSupportUser').'</a>';
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
 * @param   string      			$newdb          New db
 * @param   SellYourSaasContract    $object         Object contract
 * @return  void
 */
function print_user_table($newdb, $object)
{
	global $db, $langs, $form, $hookmanager;
	global $search, $id, $contextpage, $param;

	$sortfield = GETPOST('sortfield', 'aZ09comma');
	$sortorder = GETPOST('sortorder', 'aZ09comma');

	$arrayfields = array(
		'rowid'=>array('label'=>"ID", 'checked'=>1, 'position'=>10, 'csslist'=>'maxwidth50'),
		'login'=>array('label'=>"Login", 'checked'=>1, 'position'=>15),
		'lastname'=>array('label'=>"Lastname", 'checked'=>1, 'position'=>20, 'csslist'=>'tdoverflowmax150'),
		'firstname'=>array('label'=>"Firstname", 'checked'=>1, 'position'=>50, 'csslist'=>'tdoverflowmax150'),
		'admin'=>array('label'=>"Admin", 'checked'=>1, 'position'=>22, 'csslist'=>'nowraponall'),
		'email'=>array('label'=>"Email", 'checked'=>1, 'position'=>25),
		'pass'=>array('label'=>"Pass", 'checked'=>-1, 'position'=>27),
		'datec'=>array('label'=>"DateCreation", 'checked'=>1, 'position'=>31),
		'datem'=>array('label'=>"DateModification", 'checked'=>-1, 'position'=>32),
		'datelastlogin'=>array('label'=>"DateLastLogin", 'checked'=>1, 'position'=>35),
		'iplastlogin'=>array('label'=>"IPLastLogin", 'checked'=>0, 'position'=>36),
		'datepreviouslogin'=>array('label'=>"DatePreviousLogin", 'checked'=>0, 'position'=>37),
		'ippreviouslogin'=>array('label'=>"IPPreviousLogin", 'checked'=>0, 'position'=>38),
		'entity'=>array('label'=>"Entity", 'checked'=>1, 'position'=>100),
		'fk_soc'=>array('label'=>"ParentsId", 'checked'=>-1, 'position'=>105),
		'statut'=>array('label'=>"Status", 'checked'=>1, 'position'=>110),
	);
	if (!$sortfield) {
		$sortfield = key($arrayfields); // Set here default search field. By default 1st field in definition.
	}
	if (!$sortorder) {
		$sortorder = "ASC";
	}

	$arrayofmassactions = array();
	$moreforfilter = '';

	$prefix_db   = (empty($object->array_options['options_prefix_db']) ? 'llx_' : $object->array_options['options_prefix_db']);

	$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
	$selectedfields = ($mode != 'kanban' ? $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN', '')) : ''); // This also change content of $arrayfields
	$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

	print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
	print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";


	$cssforfield = '';

	// Fields title search
	// --------------------------------------------------------------------
	print '<tr class="liste_titre_filter">';
	// Action column
	if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<td class="liste_titre center maxwidthsearch">';
		$searchpicto = $form->showFilterButtons('left');
		print $searchpicto;
		print '</td>';
	}
	// Num
	print '<td></td>';
	foreach ($arrayfields as $key => $value) {
		if ($key == 'statut') {
			$cssforfield = ($cssforfield ? ' ' : '').'center';
		} else {
			$cssforfield = (empty($value['csslist']) ? '' : $value['csslist']);
		}
		if (!empty($arrayfields[$key]['checked'])) {
			if (in_array($key, array('rowid', 'admin'))) {
				print '<td class="liste_titre'.($cssforfield ? ' '.$cssforfield : '').($key == 'status' ? ' parentonrightofpage' : '').'">';
				print '<input type="text" class="flat maxwidth50" name="search_'.$key.'" value="'.dol_escape_htmltag(isset($search[$key]) ? $search[$key] : '').'">';
				print '</td>';
			} elseif (in_array($key, array('login', 'lastname', 'firstname', 'email'))) {
				//print getTitleFieldOfList($arrayfields[$key]['label'], 0, $_SERVER['PHP_SELF'], $key, '', "&id=".$id, ($cssforfield ? 'class="'.$cssforfield.'"' : ''),      $sortfield, $sortorder, ($cssforfield ? $cssforfield.' ' : ''))."\n";
				print '<td class="liste_titre'.($cssforfield ? ' '.$cssforfield : '').($key == 'status' ? ' parentonrightofpage' : '').'">';
				print '<input type="text" class="flat maxwidth75" name="search_'.$key.'" value="'.dol_escape_htmltag(isset($search[$key]) ? $search[$key] : '').'">';
				print '</td>';
			} else {
				print '<td class="liste_titre"></td>';
			}
		}
	}
	// Extra fields
	//include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_input.tpl.php';

	// Fields from hook
	$parameters = array('arrayfields'=>$arrayfields);
	$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	/*if (!empty($arrayfields['anotherfield']['checked'])) {
	 print '<td class="liste_titre"></td>';
	 }*/
	// Action column
	if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<td class="liste_titre center maxwidthsearch">';
		$searchpicto = $form->showFilterButtons();
		print $searchpicto;
		print '</td>';
	}
	print '</tr>'."\n";

	$totalarray = array();
	$totalarray['nbfield'] = 0;

	// Fields title label
	// --------------------------------------------------------------------
	print '<tr class="liste_titre">';
	// Action column
	if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
		$totalarray['nbfield']++;
	}
	// Numero
	print '<td>#</td>';
	$totalarray['nbfield']++;
	foreach ($arrayfields as $key => $value) {
		$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
		if ($key == 'statut') {
			$cssforfield .= ($cssforfield ? ' ' : '').'center';
		} elseif (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
			$cssforfield .= ($cssforfield ? ' ' : '').'center';
		} elseif (in_array($val['type'], array('timestamp'))) {
			$cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
		} elseif (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && !in_array($key, array('id', 'rowid', 'ref', 'status')) && $val['label'] != 'TechnicalID' && empty($val['arrayofkeyval'])) {
			$cssforfield .= ($cssforfield ? ' ' : '').'right';
		}
		$cssforfield = preg_replace('/small\s*/', '', $cssforfield);	// the 'small' css must not be used for the title label
		if (!empty($arrayfields[$key]['checked'])) {
			print getTitleFieldOfList($arrayfields[$key]['label'], 0, $_SERVER['PHP_SELF'], $key, '', $param, ($cssforfield ? 'class="'.$cssforfield.'"' : ''), $sortfield, $sortorder, ($cssforfield ? $cssforfield.' ' : ''), 0, (empty($val['helplist']) ? '' : $val['helplist']))."\n";
			$totalarray['nbfield']++;
		}
	}
	// Extra fields
	//include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';
	// Hook fields
	$parameters = array('arrayfields'=>$arrayfields, 'param'=>$param, 'sortfield'=>$sortfield, 'sortorder'=>$sortorder, 'totalarray'=>&$totalarray);
	$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	/*if (!empty($arrayfields['anotherfield']['checked'])) {
	 print '<th class="liste_titre right">'.$langs->trans("AnotherField").'</th>';
	 $totalarray['nbfield']++;
	 }*/
	// Action column
	if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
		$totalarray['nbfield']++;
	}
	print '</tr>'."\n";


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
			$sql .= " WHERE 1 = 1";
			$key = 'rowid';
			if ($search[$key]) {
				$sql .= natural_search($key, $search[$key], 1);
			}
			$key = 'login';
			if ($search[$key]) {
				$sql .= natural_search($key, $search[$key], 0);
			}
			$key = 'lastname';
			if ($search[$key]) {
				$sql .= natural_search($key, $search[$key], 0);
			}
			$key = 'firstname';
			if ($search[$key]) {
				$sql .= natural_search($key, $search[$key], 0);
			}
			$key = 'admin';
			if ($search[$key]) {
				$sql .= natural_search($key, $search[$key], 0);
			}
			$key = 'email';
			if ($search[$key]) {
				$sql .= natural_search($key, $search[$key], 0);
			}
			$sql .= $newdb->order($sortfield, $sortorder);

			$resql=$newdb->query($sql);
			if (empty($resql)) {	// Alternative for Dolibarr 3.7-
				$sql = "SELECT rowid, login, lastname as lastname, firstname, admin, email, pass, pass_crypted, datec, tms as datem, datelastlogin, fk_societe, fk_socpeople, fk_member, entity, statut";
				$sql .= " FROM ".$prefix_db."user as t ";
				$sql .= $newdb->order($sortfield, $sortorder);
				$resql = $newdb->query($sql);
				if (empty($resql)) {	// Alternative for Dolibarr 3.3-
					$sql = "SELECT rowid, login, nom as lastname, prenom as firstname, admin, email, pass, pass_crypted, datec, tms as datem, datelastlogin, fk_societe, fk_socpeople, fk_member, entity, statut";
					$sql .= " FROM ".$prefix_db."user as t";
					$sql .= $newdb->order($sortfield, $sortorder);
					$resql = $newdb->query($sql);
				}
			}
		} elseif ($forglpi) {
			$sql .= "SELECT gu.id AS rowid, gu.name as login, gu.realname as lastname, gu.firstname,";
			$sql .= " GROUP_CONCAT(DISTINCT IF(gp.interface = 'central', 'central', NULL)) AS admin,";
			$sql .= " GROUP_CONCAT(DISTINCT gue.email) AS email,";
			$sql .= " '' as pass, gu.password as pass_crypted, gu.date_creation as datec, gu.date_mod as datem, gu.last_login AS datelastlogin, 0 as nu1, 0 as nu2, 0 as nu3, gu.entities_id as entity, gu.is_active as statut";
			$sql .= " FROM glpi_users as gu LEFT JOIN glpi_profiles_users AS gpu ON gu.id = gpu.users_id";
			$sql .= " LEFT JOIN glpi_profiles as gp ON gpu.profiles_id = gp.id LEFT JOIN glpi_useremails AS gue ON gue.users_id = gu.id";
			$sql .= " WHERE gu.is_deleted = 0 AND gu.name NOT IN ('supportcloud')";
			//$sql .= " GROUP BY rowid, login, realname, firstname, pass, pass_crypted, datec, datem, datelastlogin, nu1, nu2, nu3, entity, statut, admin;";
			/*
			$sql = "SELECT DISTINCT gu.id as rowid, gu.name as login, gu.realname as lastname, gu.firstname,";
			$sql .= " gp.interface as admin, '' as pass, gu.password as pass_crypted, gu.date_creation as datec, gu.date_mod as datem, gu.last_login as datelastlogin, 0 as nu1, 0 as nu2, 0 as nu3, gu.entities_id as entity, gu.is_active as statut";
			//$sql = "SELECT gu.id as rowid, gu.name as login, gu.realname as lastname, gu.firstname, CONCAT(gp.interface, ' (', gp.name,')') as admin, '' as pass, gu.password as pass_crypted, gu.date_creation as datec, gu.date_mod as datem, gu.last_login as datelastlogin, 0 as nu1, 0 as nu2, 0 as nu3, gu.entities_id as entity, gu.is_active as statut";
			//$sql .= ", glpi_useremails.email as email";
			$sql .= ", GROUP_CONCAT(glpi_useremails.email) as email";
			$sql .= " FROM glpi_users as gu";
			$sql .= " LEFT JOIN glpi_profiles_users as gpu ON gpu.users_id = gu.id";
			$sql .= " LEFT JOIN glpi_useremails ON glpi_useremails.users_id = gu.id";
			$sql .= " LEFT JOIN glpi_profiles as gp ON gpu.profiles_id = gp.id AND gp.interface = 'central'";
			$sql .= " WHERE gu.id not in (select gu2.id from glpi_users as gu2 where gu2.is_deleted = 1)";
			*/
			$key = 'rowid';
			if ($search[$key]) {
				$sql .= natural_search($key, $search[$key], 1);
			}
			$key = 'login';
			if ($search[$key]) {
				$sql .= natural_search($key, $search[$key], 0);
			}
			$key = 'lastname';
			if ($search[$key]) {
				$sql .= natural_search('realname', $search[$key], 0);
			}
			$key = 'firstname';
			if ($search[$key]) {
				$sql .= natural_search($key, $search[$key], 0);
			}
			$key = 'admin';
			if ($search[$key]) {
				//$sql .= natural_search('gp.interface', $search[$key], 0);
				$sql .= natural_search('gp.interface', $search[$key], 0);
			}
			$key = 'email';
			if ($search[$key]) {
				$sql .= natural_search($key, $search[$key], 0);
			}
			//$sql .= " GROUP BY gu.id, gu.name, gu.realname, gu.firstname, admin, gp.interface, pass, gu.password, gu.date_creation, gu.date_mod, gu.last_login, nu1, nu2, nu3, gu.entities_id, gu.is_active";
			$sql .= " GROUP BY rowid, login, realname, firstname, pass, pass_crypted, datec, datem, datelastlogin, nu1, nu2, nu3, entity, statut";

			$sql .= $newdb->order($sortfield, $sortorder);
		} else {
			// Generic case
		}

		$resql=$newdb->query($sql);

		if ($resql) {
			$num=$newdb->num_rows($resql);
			$i=0;
			while ($i < $num) {
				$obj = $newdb->fetch_object($resql);

				global $object;
				$url='https://'.$object->ref_customer.'?username='.urlencode($obj->login).'&amp;password='.urlencode($obj->pass);
				print '<tr class="oddeven">';
				// Action column
				if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
					print '<td align="center">';
					print '<a href="'.$_SERVER["PHP_SELF"].'?action=resetpassword&token='.newToken().'&remoteid='.((int) $obj->rowid).'&id='.((int) $id).'">'.img_picto($langs->trans('ResetPassword'), 'object_technic').'</a>';
					print '</td>';
				}
				print '<td>';
				print($i+1);
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
							$valtoshow = ($obj->pass ? $obj->pass.' (' : '').($obj->pass_crypted ? $obj->pass_crypted : 'NA').($obj->pass ? ')' : '');
							print '<td class="tdoverflowmax100" title="'.dol_escape_htmltag($valtoshow).'">'.dol_escape_htmltag($valtoshow).'</td>';
						} elseif ($key == 'login') {
							print '<td class="nowraponall tdoverflowmax150" title="'.dol_escape_htmltag($obj->$key).'">';
							print $obj->$key;
							print ' <a target="_customerinstance" href="'.$url.'">'.img_object('', 'globe').'</a>';
							print '</td>';
						} elseif ($key == 'email') {
							$tmparray = explode(',', $obj->$key);
							print '<td class="tdoverflowmax200" title="'.dol_escape_htmltag(join(', ', array_unique($tmparray))).'">';
							$tmparray = explode(',', $obj->$key);
							$cacheforthisline = array();
							foreach ($tmparray as $tmpemail) {
								if (!empty($cacheforthisline[$tmpemail])) {
									continue;
								}
								$cacheforthisline[$tmpemail] = 1;
								print dol_print_email($tmpemail, (empty($obj->fk_socpeople) ? 0 : $obj->fk_socpeople), (empty($obj->fk_soc) ? 0 : $obj->fk_soc), 1)." ";
							}
							print '</td>';
						} elseif ($key == 'datec' || $key == 'datem' || $key == 'datelastlogin') {
							print '<td class="nowraponall">'.dol_print_date($newdb->jdate($obj->$key), 'dayhour', 'tzuserrel').'</td>';
						} else {
							print '<td'.($cssforfield ? ' class="'.$cssforfield.'"' : '').' title="'.dol_escape_htmltag(empty($obj->$key) ? '' : $obj->$key).'">';
							print dol_escape_htmltag(empty($obj->$key) ? '' : $obj->$key);
							print '</td>';
						}
					}
				}
				// Action column
				if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
					print '<td align="center">';
					print '<a href="'.$_SERVER["PHP_SELF"].'?action=resetpassword&token='.newToken().'&remoteid='.((int) $obj->rowid).'&id='.((int) $id).'">'.img_picto($langs->trans('ResetPassword'), 'object_technic').'</a>';
					print '</td>';
				}
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
