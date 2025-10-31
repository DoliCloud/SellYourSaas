<?php
/* Copyright (C) 2012-2022 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 * or see https://www.gnu.org/
 */

/**
 *     \file       htdocs/sellyoursaas/admin/setup_deploy_dolibarr.php
 *     \brief      Page administration module SellYourSaas (tab Deploy dolibarr)
 */


if (! defined('NOSCANPOSTFORINJECTION')) {
	define('NOSCANPOSTFORINJECTION', '1');		// Do not check anti CSRF attack test
}

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
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
/**
 * The main.inc.php has been included so the following variable are now defined:
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/files.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/images.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/geturl.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formticket.class.php";
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";
dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');
dol_include_once('sellyoursaas/class/deploymentserver.class.php');

// Access control
if (! $user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$langs->loadLangs(array("admin", "errors", "install", "sellyoursaas@sellyoursaas"));

//exit;

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('sellyoursaas-setup'));

$tmpservices=array();
$staticdeploymentserver = new Deploymentserver($db);
// Build array $tmpservicessub
// array('0' => 'mysaasdomain.com', 'with2.mysaasdomainalt.com' => 'mysaasdomainalt.com', , 'with3.mysaasdomainalt.com' => 'mysaasdomainalt.com', ...)
if (!getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION')) {
	$tmpservicessub = explode(',', getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES'));	// old way to get list of domain names
} else {
	$tmpservicessub = $staticdeploymentserver->fetchAllDomains();	// Get list of domain names foun dinto table sellyoursaas_deploymentserver
}
foreach ($tmpservicessub as $key => $tmpservicesub) {
	$tmpservicesub = preg_replace('/:.*$/', '', $tmpservicesub);
	if ($key > 0) {
		$tmpdomain = getDomainFromURL($tmpservicesub, 1);
		$tmpservices[$tmpservicesub] = $tmpdomain;
	} else {
		$tmpservices['0'] = getDomainFromURL($tmpservicesub, 1);
	}
}
// Now we duplicate domain to keep first one and alternative one that differs
$arrayofsuffixfound = array();
foreach ($tmpservices as $key => $tmpservice) {
	$suffix = '';
	if ($key != '0') {
		if ($tmpservice == $tmpservices['0']) {
			continue;
		}
		$suffix='_'.strtoupper(str_replace('.', '_', $tmpservice));
	}
	if (in_array($suffix, $arrayofsuffixfound)) {
		continue;
	}
	$arrayofsuffixfound[$tmpservice] = $suffix;
}
if (empty($arrayofsuffixfound)) {
	$arrayofsuffixfound[] = '';
}
// $arrayofsuffixfound should be now array('mysaasdomain'=>'', mysaasdomainalt'=>'_MYSAASDOMAINALT_COM', ...)
//var_dump($arrayofsuffixfound);


/*
 * Actions
 */

if ($action == 'set') {
	$error=0;

	if (! $error) {
		dolibarr_set_const($db, "SELLYOURSAAS_AUTOMIGRATION_CODE", GETPOST("SELLYOURSAAS_AUTOMIGRATION_CODE", 'alphanohtml'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_AUTOUPGRADE_CODE", GETPOST("SELLYOURSAAS_AUTOUPGRADE_CODE", 'alphanohtml'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_LAST_STABLE_VERSION_DOLIBARR", GETPOST("SELLYOURSAAS_LAST_STABLE_VERSION_DOLIBARR", 'alphanohtml'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_IGNORE_MODULES_FOR_AUTOUPGRADE", GETPOST("SELLYOURSAAS_IGNORE_MODULES_FOR_AUTOUPGRADE", 'alphanohtml'), 'chaine', 0, '', $conf->entity);

		if (GETPOSTISSET('SELLYOURSAAS_ENABLE_CUSTOMURL')) {
			dolibarr_set_const($db, 'SELLYOURSAAS_ENABLE_CUSTOMURL', GETPOST("SELLYOURSAAS_ENABLE_CUSTOMURL", 'int'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_ENABLE_CUSTOMURL_FOR_THIRDPARTYID')) {
			dolibarr_set_const($db, 'SELLYOURSAAS_ENABLE_CUSTOMURL_FOR_THIRDPARTYID', GETPOST("SELLYOURSAAS_ENABLE_CUSTOMURL_FOR_THIRDPARTYID", 'intcomma'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_ENABLE_WEBSITES')) {
			dolibarr_set_const($db, 'SELLYOURSAAS_ENABLE_WEBSITES', GETPOST("SELLYOURSAAS_ENABLE_WEBSITES", 'int'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES_FOR_THIRDPARTYID')) {
			dolibarr_set_const($db, 'SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES_FOR_THIRDPARTYID', GETPOST("SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES_FOR_THIRDPARTYID", 'intcomma'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_PRODUCT_ID_FOR_WEBSITE_DEPLOYMENT')) {
			dolibarr_set_const($db, "SELLYOURSAAS_PRODUCT_ID_FOR_WEBSITE_DEPLOYMENT", GETPOST("SELLYOURSAAS_PRODUCT_ID_FOR_WEBSITE_DEPLOYMENT", 'int'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_PRODUCT_ID_FOR_CUSTOM_URL')) {
			dolibarr_set_const($db, "SELLYOURSAAS_PRODUCT_ID_FOR_CUSTOM_URL", GETPOST("SELLYOURSAAS_PRODUCT_ID_FOR_CUSTOM_URL", 'int'), 'chaine', 0, '', $conf->entity);
		}
	}

	if (! $error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}
}


/*
 * View
 */

$form = new Form($db);
$formticket = new FormTicket($db);

$help_url="";
llxHeader("", $langs->trans("SellYouSaasSetup"), $help_url);

$linkback='<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans('SellYouSaasSetup'), $linkback, 'setup');

$error=0;

$head = sellyoursaas_admin_prepare_head();
print dol_get_fiche_head($head, "setup_deploy_dolibarr", "SellYouSaasSetup", -1, "sellyoursaas@sellyoursaas");

print '<form enctype="multipart/form-data" method="POST" action="'.$_SERVER["PHP_SELF"].'" name="form_index">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print $form->textwithpicto($langs->trans("SELLYOURSAAS_ALLOW_DOLIBARR_SPECIFIC"), $langs->transnoentities('SELLYOURSAAS_ALLOW_DOLIBARR_SPECIFICHelp'));
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_ALLOW_DOLIBARR_SPECIFIC', array(), null, 0, 0, 1);
} else {
	if (!getDolGlobalString('SELLYOURSAAS_ALLOW_DOLIBARR_SPECIFIC')) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=set_SELLYOURSAAS_ALLOW_DOLIBARR_SPECIFIC">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=del_SELLYOURSAAS_ALLOW_DOLIBARR_SPECIFIC">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '<br><br>';


$allowdolibarrspecific = getDolGlobalInt('SELLYOURSAAS_ALLOW_DOLIBARR_SPECIFIC');
if ($allowdolibarrspecific) {
	print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td class="titlefieldmiddle">'.$langs->trans("Parameters").'</td>';
	print '<td></td>';
	print '<td><div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
	print "</tr>\n";

	// Auto migrate
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_AUTOMIGRATION_CODE").'</td>';
	print '<td class="nowraponall" colspan="2">';
	print $formticket->selectGroupTickets(getDolGlobalString('SELLYOURSAAS_AUTOMIGRATION_CODE'), 'SELLYOURSAAS_AUTOMIGRATION_CODE', '', 2, 1, 0, 0, 'maxwidth400 widthcentpercentminusx');
	print '</td>';
	print '</tr>';

	// Auto upgrade
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_AUTOUPGRADE_CODE").'</td>';
	print '<td class="nowraponall" colspan="2">';
	print $formticket->selectGroupTickets(getDolGlobalString('SELLYOURSAAS_AUTOUPGRADE_CODE'), 'SELLYOURSAAS_AUTOUPGRADE_CODE', '', 2, 1, 0, 0, 'maxwidth400 widthcentpercentminusx');
	print ' &nbsp; ';
	print '<input class="minwidth50 maxwidth75" type="text" name="SELLYOURSAAS_LAST_STABLE_VERSION_DOLIBARR" value="'.getDolGlobalString('SELLYOURSAAS_LAST_STABLE_VERSION_DOLIBARR', '').'" placeholder="'.$langs->trans("Version").'" spellcheck="false">';
	print ' &nbsp; ';
	print '<input class="minwidth150 maxwidth300" type="text" name="SELLYOURSAAS_IGNORE_MODULES_FOR_AUTOUPGRADE" value="'.getDolGlobalString('SELLYOURSAAS_IGNORE_MODULES_FOR_AUTOUPGRADE', '').'" placeholder="'.$langs->trans("ModulesToIgnoreForAutoUpgrade").'" spellcheck="false">';
	print '</td>';
	print '</tr>';


	// Allow Custom URL
	print '<tr class="oddeven trforbreakperms trforbreaknobg"><td class="tdforbreakperms">'.$langs->trans("SELLYOURSAAS_ENABLE_CUSTOMURL").'</td>';
	print '<td class="tdforbreakperms">';
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('SELLYOURSAAS_ENABLE_CUSTOMURL', array(), null, 0, 0, 1);
	} else {
		if (!getDolGlobalString('SELLYOURSAAS_ENABLE_CUSTOMURL')) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=SELLYOURSAAS_ENABLE_CUSTOMURL">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
		} else {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=SELLYOURSAAS_ENABLE_CUSTOMURL">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
		}
	}
	print '</td>';
	print '<td class="tdforbreakperms"><span class="opacitymedium small">Set to yes to allow customer to set a custom URL.</td>';
	print '</tr>';

	// Allow Custom URL for specific thirdparty ID ?
	if (getDolGlobalString('SELLYOURSAAS_ENABLE_CUSTOMURL')) {
		print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ENABLE_CUSTOMURL_FOR_THIRDPARTYID").'</td>';
		print '<td>';
		print '<input class="maxwidth200" type="text" name="SELLYOURSAAS_ENABLE_CUSTOMURL_FOR_THIRDPARTYID" value="'.getDolGlobalString('SELLYOURSAAS_ENABLE_CUSTOMURL_FOR_THIRDPARTYID', '').'">';
		print '</td>';
		print '<td><span class="opacitymedium small">12345,12346,... (keep empty to allow for everybody)</span></td>';
		print '</tr>';
	}

	// Product ID for custom URL
	if (getDolGlobalString('SELLYOURSAAS_ENABLE_CUSTOMURL')) {
		print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_PRODUCT_ID_FOR_CUSTOM_URL").'</td>';
		print '<td>';
		print img_picto('', 'product', 'class="pictofixedwidth"');
		print $form->select_produits(getDolGlobalString('SELLYOURSAAS_PRODUCT_ID_FOR_CUSTOM_URL'), "SELLYOURSAAS_PRODUCT_ID_FOR_CUSTOM_URL", '', 0, 0, 1, 2, '', 0, array(), 0, '1', 0, 'maxwidth500 widthcentpercentminusx');
		print '</td>';
		print '<td><span class="opacitymedium small"></span></td>';
		print '</tr>';
	}

	// Allow deployment of Dolibarr website
	print '<tr class="oddeven trforbreakperms trforbreaknobg"><td class="tdforbreakperms">'.$langs->trans("SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES").'</td>';
	print '<td class="tdforbreakperms">';
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES', array(), null, 0, 0, 1);
	} else {
		if (!getDolGlobalString('SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES')) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
		} else {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
		}
	}
	print '</td>';
	print '<td class="tdforbreakperms"><span class="opacitymedium small">Set to yes to allow customer to set a website online.</td>';
	print '</tr>';


	// Allow deployment of Dolibarr website for specific thirdparty ID ?
	if (getDolGlobalString('SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES')) {
		print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES_FOR_THIRDPARTYID").'</td>';
		print '<td>';
		print '<input class="maxwidth200" type="text" name="SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES_FOR_THIRDPARTYID" value="'.getDolGlobalString('SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES_FOR_THIRDPARTYID', '').'">';
		print '</td>';
		print '<td><span class="opacitymedium small">12345,12346,... (keep empty to allow for everybody)</span></td>';
		print '</tr>';
	}

	// Product ID for website deployment
	if (getDolGlobalString('SELLYOURSAAS_ENABLE_DOLIBARR_WEBSITES')) {
		print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_PRODUCT_ID_FOR_WEBSITE_DEPLOYMENT").'</td>';
		print '<td>';
		print img_picto('', 'product', 'class="pictofixedwidth"');
		print $form->select_produits(getDolGlobalString('SELLYOURSAAS_PRODUCT_ID_FOR_WEBSITE_DEPLOYMENT'), "SELLYOURSAAS_PRODUCT_ID_FOR_WEBSITE_DEPLOYMENT", '', 0, 0, 1, 2, '', 0, array(), 0, '1', 0, 'maxwidth500 widthcentpercentminusx');
		print '</td>';
		print '<td><span class="opacitymedium small"></span></td>';
		print '</tr>';
	}

	print '</table>';
	print '</div>';
}

print "</form>\n";


llxfooter();

$db->close();
