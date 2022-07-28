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
 *     \file       htdocs/sellyoursaas/admin/setup.php
 *     \brief      Page administration module SellYourSaas
 */


if (! defined('NOSCANPOSTFORINJECTION')) define('NOSCANPOSTFORINJECTION', '1');		// Do not check anti CSRF attack test


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

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/files.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/images.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/geturl.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formticket.class.php";
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";
dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');

// Access control
if (! $user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$langs->loadLangs(array("admin", "errors", "install", "sellyoursaas@sellyoursaas"));

//exit;

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('sellyoursaas-setup'));

$tmpservices=array();
$tmpservicessub = explode(',', getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES'));
foreach ($tmpservicessub as $key => $tmpservicesub) {
	$tmpservicesub = preg_replace('/:.*$/', '', $tmpservicesub);
	if ($key > 0) $tmpservices[$tmpservicesub]=getDomainFromURL($tmpservicesub, 1);
	else $tmpservices['0']=getDomainFromURL($tmpservicesub, 1);
}
$arrayofsuffixfound = array();
foreach ($tmpservices as $key => $tmpservice) {
	$suffix = '';
	if ($key != '0') $suffix='_'.strtoupper(str_replace('.', '_', $tmpservice));

	if (in_array($suffix, $arrayofsuffixfound)) continue;
	$arrayofsuffixfound[$tmpservice] = $suffix;
}


/*
 * Actions
 */

if ($action == 'set') {
	$error=0;

	if (! $error) {
		dolibarr_set_const($db, 'SELLYOURSAAS_MAIN_FAQ_URL', GETPOST("SELLYOURSAAS_MAIN_FAQ_URL", 'custom', 0, FILTER_VALIDATE_URL), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_NAME", GETPOST("SELLYOURSAAS_NAME"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_MAIN_DOMAIN_NAME", GETPOST("SELLYOURSAAS_MAIN_DOMAIN_NAME"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_NAMES", GETPOST("SELLYOURSAAS_SUB_DOMAIN_NAMES"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_IP", GETPOST("SELLYOURSAAS_SUB_DOMAIN_IP"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_MAIN_EMAIL", GETPOST("SELLYOURSAAS_MAIN_EMAIL"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_MAIN_EMAIL_PREMIUM", GETPOST("SELLYOURSAAS_MAIN_EMAIL_PREMIUM"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SUPERVISION_EMAIL", GETPOST("SELLYOURSAAS_SUPERVISION_EMAIL"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NOREPLY_EMAIL", GETPOST("SELLYOURSAAS_NOREPLY_EMAIL"), 'chaine', 0, '', $conf->entity);

		$dir=GETPOST("DOLICLOUD_SCRIPTS_PATH");
		if (! dol_is_dir($dir)) setEventMessage($langs->trans("ErrorDirNotFound", $dir), 'warnings');
		dolibarr_set_const($db, "DOLICLOUD_SCRIPTS_PATH", GETPOST("DOLICLOUD_SCRIPTS_PATH"), 'chaine', 0, '', $conf->entity);

		foreach ($arrayofsuffixfound as $suffix) {
			dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_PRODUCT".$suffix, GETPOST("SELLYOURSAAS_DEFAULT_PRODUCT".$suffix), 'chaine', 0, '', $conf->entity);
		}
		//dolibarr_set_const($db,"SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS",GETPOST("SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS"),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_PRODUCT_CATEG", GETPOST("SELLYOURSAAS_DEFAULT_PRODUCT_CATEG"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG", GETPOST("SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG"), 'chaine', 0, '', $conf->entity);

		// Option for resellers
		dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_COMMISSION", GETPOST("SELLYOURSAAS_DEFAULT_COMMISSION"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_RESELLER_CATEG", GETPOST("SELLYOURSAAS_DEFAULT_RESELLER_CATEG"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_MINAMOUNT_TO_CLAIM", GETPOST("SELLYOURSAAS_MINAMOUNT_TO_CLAIM"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_EMAIL", GETPOST("SELLYOURSAAS_RESELLER_EMAIL"), 'chaine', 0, '', $conf->entity);
		if (GETPOSTISSET('SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE')) {
			dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE", GETPOST("SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE"), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION')) {
			dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION", GETPOST("SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION"), 'chaine', 0, '', $conf->entity);
		}

		dolibarr_set_const($db, "SELLYOURSAAS_REFS_URL", GETPOST("SELLYOURSAAS_REFS_URL"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_ACCOUNT_URL", GETPOST("SELLYOURSAAS_ACCOUNT_URL", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_PRICES_URL", GETPOST("SELLYOURSAAS_PRICES_URL", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_STATUS_URL", GETPOST("SELLYOURSAAS_STATUS_URL", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_URL", GETPOST("SELLYOURSAAS_RESELLER_URL", 'alpha'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_MYACCOUNT_FOOTER", GETPOST("SELLYOURSAAS_MYACCOUNT_FOOTER", 'none'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_CONVERSION_FOOTER", GETPOST("SELLYOURSAAS_CONVERSION_FOOTER", 'none'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_CSS", GETPOST("SELLYOURSAAS_CSS", 'none'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SECURITY_KEY", GETPOST("SELLYOURSAAS_SECURITY_KEY", 'none'), 'chaine', 0, '', $conf->entity);

	}

	if (! $error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}
}

if ($action == 'removelogo') {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$constname='SELLYOURSAAS_LOGO'.GETPOST('suffix', 'aZ09');
	$logofile=$conf->mycompany->dir_output.'/logos/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logofile);
	dolibarr_del_const($db, $constname, $conf->entity);

	$constname='SELLYOURSAAS_LOGO_SMALL'.GETPOST('suffix', 'aZ09');
	$logosmallfile=$conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logosmallfile);
	dolibarr_del_const($db, $constname, $conf->entity);

	$constname='SELLYOURSAAS_LOGO_MINI'.GETPOST('suffix', 'aZ09');
	$logominifile=$conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logominifile);
	dolibarr_del_const($db, $constname, $conf->entity);
}
if ($action == 'removelogoblack') {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$constname='SELLYOURSAAS_LOGO_BLACK'.GETPOST('suffix', 'aZ09');
	$logofile=$conf->mycompany->dir_output.'/logos/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logofile);
	dolibarr_del_const($db, "$constname", $conf->entity);

	$constname='SELLYOURSAAS_LOGO_SMALL_BLACK'.GETPOST('suffix', 'aZ09');
	$logosmallfile=$conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logosmallfile);
	dolibarr_del_const($db, $constname, $conf->entity);

	$constname='SELLYOURSAAS_LOGO_MINI_BLACK'.GETPOST('suffix', 'aZ09');
	$logominifile=$conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logominifile);
	dolibarr_del_const($db, $constname, $conf->entity);
}


/*
 * View
 */

$formother=new FormOther($db);
$form=new Form($db);
$formticket = new FormTicket($db);

$help_url="";
llxHeader("", $langs->trans("SellYouSaasSetup"), $help_url);

$linkback='<a href="'.($backtopage?$backtopage:DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans('SellYouSaasSetup'), $linkback, 'setup');

print '<span class="opacitymedium">'.$langs->trans("Prerequisites")."</span><br>\n";
print '<br>';

print 'Function <b>idn_to_ascii</b> available: '.(function_exists('idn_to_ascii') ? img_picto('', 'tick', 'class="paddingrightonly"').yn(1) : img_picto('', 'warning', 'class="paddingrightonly"').yn(0)).'<br>';
print 'Function <b>checkdnsrr</b> available: '.(function_exists('checkdnsrr') ? img_picto('', 'tick', 'class="paddingrightonly"').yn(1) : img_picto('', 'warning', 'class="paddingrightonly"').yn(0)).'<br>';
print 'Parameter <b>allow_url_fopen</b> is on: '.(ini_get('allow_url_fopen') ? img_picto('', 'tick', 'class="paddingrightonly"').yn(1) : img_picto('', 'warning', 'class="paddingrightonly"').yn(0)).'<br>';
$arrayoffunctionsdisabled = explode(',', ini_get('disable_functions'));
if (in_array('exec', $arrayoffunctionsdisabled)) {
	print "Parameter <b>disable_functions</b>: Bad. Must not contain 'exec'<br>";
} else {
	print 'Parameter <b>disable_functions</b>: '.img_picto('', 'tick', 'class="paddingrightonly"').' does not contains: exec<br>';
}
print "<br>\n";

//$head=array();
//dol_fiche_head($head, 'serversetup', $langs->trans("SellYourSaas"), -1);

print '<span class="opacitymedium">'.$langs->trans("SellYouSaasDesc")."</span><br>\n";
print "<br>\n";

$error=0;
$head = sellyoursaas_admin_prepare_head();
print dol_get_fiche_head($head,"setup", "SellYouSaasSetup", -1,"sellyoursaas@sellyoursaas");

print '<form enctype="multipart/form-data" method="POST" action="'.$_SERVER["PHP_SELF"].'" name="form_index">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td class="titlefield">'.$langs->trans("ParametersOnMasterServer").'</td><td>'.$langs->trans("Value").'</td>';
print '<td class="titlefield"><div class="float">'.$langs->trans("Examples").'</div><div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
print "</tr>\n";

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_FORCE_STRIPE_TEST").'</td>';
print '<td>';
print ajax_constantonoff('SELLYOURSAAS_FORCE_STRIPE_TEST', array(), $conf->entity, 0, 0, 1);
print '</td>';
print '<td><span class="opacitymedium small">1</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasName").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_NAME" value="'.getDolGlobalString('SELLYOURSAAS_NAME').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">My SaaS service</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasMainDomain").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_MAIN_DOMAIN_NAME" value="'.getDolGlobalString('SELLYOURSAAS_MAIN_DOMAIN_NAME').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">mysaasdomainname.com</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$form->textwithpicto($langs->trans("SellYourSaasSubDomains"), $langs->trans("SellYourSaasSubDomainsHelp")).'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SUB_DOMAIN_NAMES" value="'.getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">with.mysaasdomainname.com,with.mysaas2.com:mysaas2.com...</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasSubDomainsIP").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SUB_DOMAIN_IP" value="'.getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_IP').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">192.168.0.1,123.456.789.012...</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasMainEmail").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_MAIN_EMAIL" value="'.getDolGlobalString('SELLYOURSAAS_MAIN_EMAIL').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">contact@mysaasdomainname.com</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasMainEmail").' (Premium)</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_MAIN_EMAIL_PREMIUM" value="'.getDolGlobalString('SELLYOURSAAS_MAIN_EMAIL_PREMIUM').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">contact+premium@mysaasdomainname.com</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasSupervisionEmail").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SUPERVISION_EMAIL" value="'.getDolGlobalString('SELLYOURSAAS_SUPERVISION_EMAIL').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">supervision@mysaasdomainname.com</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasNoReplyEmail").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_NOREPLY_EMAIL" value="'.getDolGlobalString('SELLYOURSAAS_NOREPLY_EMAIL').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">noreply@mysaasdomainname.com</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("DirForScriptPath").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="DOLICLOUD_SCRIPTS_PATH" value="'.getDolGlobalString('DOLICLOUD_SCRIPTS_PATH').'">';
print '</td>';
print '<td><span class="opacitymedium small">'.dol_buildpath('sellyoursaas/scripts').'</span></td>';
print '</tr>';

foreach ($arrayofsuffixfound as $service => $suffix) {
	print '<!-- suffix = '.$suffix.' -->'."\n";

	print '<tr class="oddeven"><td>'.($service ? $service.' - ' : '').$langs->trans("DefaultProductForInstances").'</td>';
	print '<td>';
	$constname = 'SELLYOURSAAS_DEFAULT_PRODUCT'.$suffix;
	print '<!-- constname = '.$constname.' -->';
	$defaultproductid = getDolGlobalString($constname);
	print $form->select_produits($defaultproductid, 'SELLYOURSAAS_DEFAULT_PRODUCT'.$suffix, '', 0, 0, 1, 2, '', 0, array(), 0, '1', 0, 'maxwidth500');
	print '</td>';
	print '<td><span class="opacitymedium small">My SaaS service for instance</span></td>';
	print '</tr>';
}

/*print '<tr class="oddeven"><td>'.$langs->trans("DefaultProductForUsers").'</td>';
print '<td>';
$defaultproductforusersid=getDolGlobalString('SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS');
print $form->select_produits($defaultproductforusersid, 'SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS');
print '</td>';
print '<td>My SaaS service for users</td>';
print '</tr>';
*/

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("DefaultCategoryForSaaSServices").'</td>';
print '<td>';
$defaultproductcategid=getDolGlobalString('SELLYOURSAAS_DEFAULT_PRODUCT_CATEG');
print $formother->select_categories(Categorie::TYPE_PRODUCT, $defaultproductcategid, 'SELLYOURSAAS_DEFAULT_PRODUCT_CATEG', 0, 1, 'miwidth300');
print '</td>';
print '<td><span class="opacitymedium small">SaaS Products</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("DefaultCategoryForSaaSCustomers").'</td>';
print '<td>';
$defaultcustomercategid=getDolGlobalString('SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG');
print $formother->select_categories(Categorie::TYPE_CUSTOMER, $defaultcustomercategid, 'SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG', 0, 1, 'miwidth300');
print '</td>';
print '<td><span class="opacitymedium small">SaaS Customers</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ALLOW_RESELLER_PROGRAM").'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_ALLOW_RESELLER_PROGRAM', array(), null, 0, 0, 1);
} else {
	if (empty($conf->global->SELLYOURSAAS_ALLOW_RESELLER_PROGRAM)) {
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
	print '<input class="width50" type="text" name="SELLYOURSAAS_MINAMOUNT_TO_CLAIM" value="'.getDolGlobalString('SELLYOURSAAS_MINAMOUNT_TO_CLAIM').'">';
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
		if (empty($conf->global->SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE)) {
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

print '<tr class="oddeven"><td>'.$langs->trans("RefsUrl", DOL_DOCUMENT_ROOT.'/sellyoursaas/git');
print '</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_REFS_URL" value="'.getDolGlobalString('SELLYOURSAAS_REFS_URL').'">';
print '</td>';
print '<td><span class="opacitymedium small">https://admin.mysaasdomainname.com/git</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasAccountUrl").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_ACCOUNT_URL" value="'.getDolGlobalString('SELLYOURSAAS_ACCOUNT_URL').'">';
print '</td>';
print '<td><span class="opacitymedium small">https://myaccount.mysaasdomainname.com<br>Note: Virtual hosts for such domains must link to <strong>'.dol_buildpath('sellyoursaas/myaccount').'</strong></span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasPricesUrl").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_PRICES_URL" value="'.getDolGlobalString('SELLYOURSAAS_PRICES_URL').'">';
print '</td>';
print '<td><span class="opacitymedium small">https://myaccount.mysaasdomainname.com/prices.html</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasStatusUrl").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_STATUS_URL" value="'.getDolGlobalString('SELLYOURSAAS_STATUS_URL').'">';
print '</td>';
print '<td><span class="opacitymedium small">https://status.mysaasdomainname.com</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $form->textwithpicto($langs->trans("SELLYOURSAAS_MAIN_FAQ_URL"), $langs->trans("SELLYOURSAAS_MAIN_FAQ_URLHelp"));
print '</td>';
print '<td colspan="2">';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_MAIN_FAQ_URL" value="'.getDolGlobalString('SELLYOURSAAS_MAIN_FAQ_URL').'">';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("FooterContent").'</td>';
print '<td>';
print '<textarea name="SELLYOURSAAS_MYACCOUNT_FOOTER" class="quatrevingtpercent" rows="3">'.getDolGlobalString('SELLYOURSAAS_MYACCOUNT_FOOTER').'</textarea>';
print '</td>';
print '<td><span class="opacitymedium small">&lt;script&gt;Your google analytics code&lt;/script&gt;</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("ConversionContent").'</td>';
print '<td>';
print '<textarea name="SELLYOURSAAS_CONVERSION_FOOTER" class="quatrevingtpercent" rows="3">'.getDolGlobalString('SELLYOURSAAS_CONVERSION_FOOTER').'</textarea>';
print '</td>';
print '<td><span class="opacitymedium small">&lt;script&gt;Your conversion trackers&lt;/script&gt;</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("CSSForCustomerAndRegisterPages").'</td>';
print '<td>';
print '<textarea name="SELLYOURSAAS_CSS" class="quatrevingtpercent" rows="3">'.getDolGlobalString('SELLYOURSAAS_CSS').'</textarea>';
print '</td>';
print '<td></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SecurityKeyForPublicPages").' <span class="opacitymedium">(To protect the URL for Spam reporting webhooks)</spam></td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_SECURITY_KEY" value="'.getDolGlobalString('SELLYOURSAAS_SECURITY_KEY').'">';
print '</td>';
print '<td><span class="opacitymedium small">123456abcdef</span></td>';
print '</tr>';

print '</table>';
print '</div>';

print "</form>\n";


print "<br>";
print '<br>';

// Define $urlwithroot
$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT, '/').'$/i', '', trim($dolibarr_main_url_root));
$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
//$urlwithroot=DOL_MAIN_URL_ROOT;						// This is to use same domain name than current. For Paypal payment, we can use internal URL like localhost.

/*
var_dump(DOL_URL_ROOT);
var_dump(dol_buildpath('/sellyoursaas/public/spamreport.php', 1));
var_dump(DOL_MAIN_URL_ROOT);
*/

$message = '';
$url = '<a href="'.dol_buildpath('/sellyoursaas/public/spamreport.php', 3).'?key='.urlencode(getDolGlobalString('SELLYOURSAAS_SECURITY_KEY', 'KEYNOTDEFINED')).'&mode=test" target="_blank" rel="noopener">'.dol_buildpath('/sellyoursaas/public/spamreport.php', 3).'?key='.urlencode(getDolGlobalString('SELLYOURSAAS_SECURITY_KEY', 'KEYNOTDEFINED')).'[&mode=test]</a>';
$message .= img_picto('', 'object_globe.png').' '.$langs->trans("EndPointFor", "SpamReport", '{s1}');
$message = str_replace('{s1}', $url, $message);
print $message;

print '<br>';

/*
$message='';
$url='<a href="'.dol_buildpath('/sellyoursaas/myaccount/public/test.php', 3).'?key='.($conf->global->SELLYOURSAAS_SECURITY_KEY?urlencode($conf->global->SELLYOURSAAS_SECURITY_KEY):'...').'" target="_blank">'.dol_buildpath('/sellyoursaas/public/test.php', 3).'?key='.($conf->global->SELLYOURSAAS_SECURITY_KEY?urlencode($conf->global->SELLYOURSAAS_SECURITY_KEY):'KEYNOTDEFINED').'</a>';
$message.=img_picto('', 'object_globe.png').' '.$langs->trans("EndPointFor", "Test", '{s1}');
$message = str_replace('{s1}', $url, $message);
print $message;

print "<br>";
*/

//dol_fiche_end();


llxfooter();

$db->close();
