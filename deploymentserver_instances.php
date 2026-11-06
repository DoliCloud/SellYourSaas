<?php
/* Copyright (C) 2007-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *  \file       htdocs/sellyoursaas/deploymentserver_instances.php
 *  \ingroup    sellyoursaas
 *  \brief      Tab for instances of deploymentserver
 */


// General defined Options
//if (! defined('CSRFCHECK_WITH_TOKEN'))     define('CSRFCHECK_WITH_TOKEN', '1');						// Force use of CSRF protection with tokens even for GET
//if (! defined('MAIN_AUTHENTICATION_MODE')) define('MAIN_AUTHENTICATION_MODE', 'aloginmodule');	// Force authentication handler
//if (! defined('MAIN_LANG_DEFAULT'))        define('MAIN_LANG_DEFAULT', 'auto');						// Force LANG (language) to a particular value
//if (! defined('MAIN_SECURITY_FORCECSP'))   define('MAIN_SECURITY_FORCECSP', 'none');					// Disable all Content Security Policies
//if (! defined('NOBROWSERNOTIF'))    	  define('NOBROWSERNOTIF', '1');									// Disable browser notification
//if (! defined('NOIPCHECK'))                define('NOIPCHECK', '1');										// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined('NOLOGIN'))                  define('NOLOGIN', '1');										// Do not use login - if this page is public (can be called outside logged session). This includes the NOIPCHECK too.
//if (! defined('NOREQUIREAJAX'))            define('NOREQUIREAJAX', '1');      	  // Do not load ajax.lib.php library
//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');										// Do not create database handler $db
//if (! defined('NOREQUIREHTML'))            define('NOREQUIREHTML', '1');										// Do not load html.form.class.php
//if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');										// Do not load and show top and left menu
//if (! defined('NOREQUIRESOC'))             define('NOREQUIRESOC', '1');										// Do not load object $mysoc
//if (! defined('NOREQUIRETRAN'))            define('NOREQUIRETRAN', '1');										// Do not load object $langs
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');										// Do not load object $user
//if (! defined('NOSCANGETFORINJECTION'))   define('NOSCANGETFORINJECTION', '1');						// Do not check injection attack on GET parameters
//if (! defined('NOSCANPOSTFORINJECTION'))  define('NOSCANPOSTFORINJECTION', '1');					// Do not check injection attack on POST parameters
//if (! defined('NOSTYLECHECK'))             define('NOSTYLECHECK', '1');										// Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL'))           define('NOTOKENRENEWAL', '1');										// Do not roll the Anti CSRF token (used if MAIN_SECURITY_CSRF_WITH_TOKEN is on)


// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
/**
 * The main.inc.php has been included so the following variable are now defined:
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
dol_include_once('/sellyoursaas/class/deploymentserver.class.php');
dol_include_once('/sellyoursaas/lib/sellyoursaas_deploymentserver.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("sellyoursaas@sellyoursaas", "companies"));

// Get parameters
$id = GETPOSTINT('id');
$ref        = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : str_replace('_', '', basename(dirname(__FILE__)).basename(__FILE__, '.php')); // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$object = new Deploymentserver($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array($object->element.'instances', 'globalcard'));  // Note that conf->hooks_modules contains array
// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once  // Must be include, not include_once. Include fetch and fetch_thirdparty but not fetch_optionals


// There is several ways to check permission.
// Set $enablepermissioncheck to 1 to enable a minimum low level of checks
$enablepermissioncheck = 1;
if ($enablepermissioncheck) {
	$permissiontoread = $user->hasRight('sellyoursaas', 'read');
} else {
	$permissiontoread = 1;
}

// Security check (enable the most restrictive one)
// Reject external users and the internal user shared by all customers of the myaccount dashboard
if ($user->socid > 0 || $user->id == getDolGlobalInt('SELLYOURSAAS_ANONYMOUSUSER')) {
	accessforbidden();
}
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (($object->status == $object::STATUS_DRAFT) ? 1 : 0);
//restrictedArea($user, $object->module, $object->id, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);
if (!isModEnabled("sellyoursaas")) {
	accessforbidden();
}
if (!$permissiontoread) {
	accessforbidden();
}


/*
 * Actions
 */

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}


/*
 * View
 */

$form = new Form($db);

$title = $langs->trans('deploymentserver').' - '.$langs->trans("Instances");
//$title = $object->ref." - ".$langs->trans("Instances");
$help_url = '';
//$help_url='EN:Customers_Orders|FR:Commandes_Clients|ES:Pedidos de clientes';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-sellyoursaas page-card_instances');

if ($id > 0 || !empty($ref)) {
	$head = deploymentserverPrepareHead($object);

	print dol_get_fiche_head($head, 'instances', $langs->trans("deploymentserver"), -1, $object->picto);

	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="'.dol_buildpath('/sellyoursaas/deploymentserver_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno"></div>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);


	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	// Url of the list of contracts filtered on the instances deployed on this deployment server
	$urllist = DOL_URL_ROOT.'/contrat/list.php?leftmenu=contracts&contextpage=sellyoursaasinstances';
	$urllist .= '&search_product_category='.urlencode(getDolGlobalString('SELLYOURSAAS_DEFAULT_PRODUCT_CATEG'));
	$urllist .= '&search_ref_customer='.urlencode($object->ref);

	print '<div class="marginleftonly marginrightonly centpercent center">';

	print '<br><br>';

	print '<a class="butAction" href="'.$urllist.'">'.img_picto('', 'contract', 'class="pictofixedwidth"').$langs->trans("SeeFilteredInstancesList").'</a>';

	print '</div>';

	print '</div>';

	print dol_get_fiche_end();
}

// End of page
llxFooter();
$db->close();
