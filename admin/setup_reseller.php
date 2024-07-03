<?php
/* Copyright (C) 2012-2020 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *     \file       htdocs/sellyoursaas/admin/setup_reseller.php
 *     \brief      Page administration module SellYourSaas
 */


if (! defined('NOSCANPOSTFORINJECTION')) {
	define('NOSCANPOSTFORINJECTION', '1');
}		// Do not check anti CSRF attack test


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

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/files.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/images.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/geturl.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formticket.class.php";
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";
dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');
dol_include_once('/sellyoursaas/class/deploymentserver.class.php');

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
if (!getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION')) {
	$tmpservicessub = explode(',', getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES'));
} else {
	$tmpservicessub = $staticdeploymentserver->fetchAllDomains();
}
foreach ($tmpservicessub as $key => $tmpservicesub) {
	$tmpservicesub = preg_replace('/:.*$/', '', $tmpservicesub);
	if ($key > 0) {
		$tmpservices[$tmpservicesub]=getDomainFromURL($tmpservicesub, 1);
	} else {
		$tmpservices['0']=getDomainFromURL($tmpservicesub, 1);
	}
}
$arrayofsuffixfound = array();
foreach ($tmpservices as $key => $tmpservice) {
	$suffix = '';
	if ($key != '0') {
		$suffix='_'.strtoupper(str_replace('.', '_', $tmpservice));
	}

	if (in_array($suffix, $arrayofsuffixfound)) {
		continue;
	}
	$arrayofsuffixfound[$tmpservice] = $suffix;
}


/*
 * Actions
 */

if ($action == 'set') {
	$error=0;

	if (! $error) {
		// Option for resellers
		if (GETPOSTISSET('SELLYOURSAAS_DEFAULT_COMMISSION')) {
			dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_COMMISSION", GETPOST("SELLYOURSAAS_DEFAULT_COMMISSION"), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_DEFAULT_RESELLER_CATEG')) {
			dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_RESELLER_CATEG", GETPOST("SELLYOURSAAS_DEFAULT_RESELLER_CATEG"), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_RESELLER_URL')) {
			dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_URL", GETPOST("SELLYOURSAAS_RESELLER_URL", 'alpha'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_MINAMOUNT_TO_CLAIM')) {
			dolibarr_set_const($db, "SELLYOURSAAS_MINAMOUNT_TO_CLAIM", GETPOST("SELLYOURSAAS_MINAMOUNT_TO_CLAIM"), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_RESELLER_EMAIL')) {
			dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_EMAIL", GETPOST("SELLYOURSAAS_RESELLER_EMAIL"), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE')) {
			dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE", GETPOST("SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE"), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION')) {
			dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION", GETPOST("SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION"), 'chaine', 0, '', $conf->entity);
		}
	}

	if (! $error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}
}


/*
 * View
 */

$formother=new FormOther($db);
$form=new Form($db);
$formticket = new FormTicket($db);

$help_url="";
llxHeader("", $langs->trans("SellYouSaasSetup"), $help_url);

$linkback='<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans('SellYouSaasSetup'), $linkback, 'setup');

$error=0;

$head = sellyoursaas_admin_prepare_head();
print dol_get_fiche_head($head, "setup_reseller", "SellYouSaasSetup", -1, "sellyoursaas@sellyoursaas");

print '<form enctype="multipart/form-data" method="POST" action="'.$_SERVER["PHP_SELF"].'" name="form_index">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td><td>'.$langs->trans("Value").'</td>';
print '<td><div class="float">'.$langs->trans("Examples").'</div><div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
print "</tr>\n";

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ALLOW_RESELLER_PROGRAM").'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_ALLOW_RESELLER_PROGRAM', array(), null, 0, 0, 1);
} else {
	if (!getDolGlobalString('SELLYOURSAAS_ALLOW_RESELLER_PROGRAM')) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=set_SELLYOURSAAS_ALLOW_RESELLER_PROGRAM">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=del_SELLYOURSAAS_ALLOW_RESELLER_PROGRAM">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
//print $form->selectyesno('SELLYOURSAAS_ALLOW_RESELLER_PROGRAM', $allowresellerprogram, 1);
print '</td>';
print '<td><span class="opacitymedium small">Set to yes if you want your customers being able to apply to become resellers</span></td>';
print '</tr>';

$allowresellerprogram = getDolGlobalInt('SELLYOURSAAS_ALLOW_RESELLER_PROGRAM');
if ($allowresellerprogram) {
	print '<tr class="oddeven"><td>'.$langs->trans("DefaultCommission");
	print '</td>';
	print '<td>';
	print '<input class="width50 right" type="text" name="SELLYOURSAAS_DEFAULT_COMMISSION" value="'.getDolGlobalString('SELLYOURSAAS_DEFAULT_COMMISSION').'"> %';
	print '</td>';
	print '<td><span class="opacitymedium small">25%</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("DefaultCategoryForSaaSResellers").'</td>';
	print '<td>';
	$defaultcustomercategid = getDolGlobalString('SELLYOURSAAS_DEFAULT_RESELLER_CATEG');
	print $formother->select_categories(Categorie::TYPE_SUPPLIER, $defaultcustomercategid, 'SELLYOURSAAS_DEFAULT_RESELLER_CATEG', 0, 1, 'miwidth300');
	print '</td>';
	print '<td><span class="opacitymedium small">SaaS Resellers</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasResellerUrl").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_RESELLER_URL" value="'.getDolGlobalString('SELLYOURSAAS_RESELLER_URL').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">https://www.mysaasdomainname.com/en-become-a-dolicloud-reseller.php</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MINAMOUNT_TO_CLAIM").'</td>';
	print '<td>';
	print '<input class="width50 right" type="text" name="SELLYOURSAAS_MINAMOUNT_TO_CLAIM" value="'.getDolGlobalString('SELLYOURSAAS_MINAMOUNT_TO_CLAIM').'">';
	print ' '.$langs->getCurrencySymbol($conf->currency);
	print '</td>';
	print '<td><span class="opacitymedium small">100</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_RESELLER_EMAIL").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_RESELLER_EMAIL" value="'.getDolGlobalString('SELLYOURSAAS_RESELLER_EMAIL').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">partner@mysaasdomainname.com</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE").'</td>';
	print '<td>';
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE', array(), null, 0, 0, 1);
	} else {
		if (!getDolGlobalString('SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE')) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=set_SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
		} else {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=del_SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
		}
	}
	//print $form->selectyesno('SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE', $allowresellerprogram, 1);
	print '</td>';
	print '<td></td>';
	print '</tr>';

	// If option to allow reseller custome prices is on, we can set a maximum for discount
	if (getDolGlobalInt('SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE')) {
		print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION").'</td>';
		print '<td>';
		print '<input class="maxwidth50 right" type="text" name="SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION" value="'.getDolGlobalInt('SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION', 0).'"> %';
		print '</td>';
		print '<td><span class="opacitymedium small">30 %</span></td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

print "</form>\n";


llxfooter();

$db->close();
