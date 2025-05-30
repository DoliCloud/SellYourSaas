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
		dolibarr_set_const($db, "SELLYOURSAAS_NAME", GETPOST("SELLYOURSAAS_NAME"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_MAIN_DOMAIN_NAME", GETPOST("SELLYOURSAAS_MAIN_DOMAIN_NAME"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_NAMES", GETPOST("SELLYOURSAAS_SUB_DOMAIN_NAMES"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_IP", GETPOST("SELLYOURSAAS_SUB_DOMAIN_IP"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_MAIN_EMAIL", GETPOST("SELLYOURSAAS_MAIN_EMAIL"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_MAIN_EMAIL_PREMIUM", GETPOST("SELLYOURSAAS_MAIN_EMAIL_PREMIUM"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SUPERVISION_EMAIL", GETPOST("SELLYOURSAAS_SUPERVISION_EMAIL"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NOREPLY_EMAIL", GETPOST("SELLYOURSAAS_NOREPLY_EMAIL"), 'chaine', 0, '', $conf->entity);

		$dir = GETPOST("DOLICLOUD_SCRIPTS_PATH");
		if ($dir && ! dol_is_dir($dir)) {
			setEventMessage($langs->trans("ErrorDirNotFound", $dir), 'warnings');
		}
		dolibarr_set_const($db, "DOLICLOUD_SCRIPTS_PATH", GETPOST("DOLICLOUD_SCRIPTS_PATH"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_PRODUCT_CATEG", GETPOST("SELLYOURSAAS_DEFAULT_PRODUCT_CATEG"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG", GETPOST("SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG"), 'chaine', 0, '', $conf->entity);

		//dolibarr_set_const($db, "SELLYOURSAAS_REFS_URL", GETPOST("SELLYOURSAAS_REFS_URL"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_ACCOUNT_URL", GETPOST("SELLYOURSAAS_ACCOUNT_URL", 'alpha'), 'chaine', 0, '', $conf->entity);
		foreach ($arrayofsuffixfound as $suffix) {
			dolibarr_set_const($db, "SELLYOURSAAS_PRICES_URL".$suffix, GETPOST("SELLYOURSAAS_PRICES_URL".$suffix), 'chaine', 0, '', $conf->entity);
		}

		dolibarr_set_const($db, "SELLYOURSAAS_MYACCOUNT_FOOTER", GETPOST("SELLYOURSAAS_MYACCOUNT_FOOTER", 'none'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_CONVERSION_FOOTER", GETPOST("SELLYOURSAAS_CONVERSION_FOOTER", 'none'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_ANONYMOUSUSER", GETPOST("SELLYOURSAAS_ANONYMOUSUSER", 'alpha'), 'chaine', 0, '', $conf->entity);
	}

	if (! $error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}
}

if ($action == 'removelogo') {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$constname='SELLYOURSAAS_LOGO'.GETPOST('suffix', 'aZ09');
	$logofile=$conf->mycompany->dir_output.'/logos/'.getDolGlobalString($constname);
	if (getDolGlobalString($constname) != '') {
		dol_delete_file($logofile);
	}
	dolibarr_del_const($db, $constname, $conf->entity);

	$constname='SELLYOURSAAS_LOGO_SMALL'.GETPOST('suffix', 'aZ09');
	$logosmallfile=$conf->mycompany->dir_output.'/logos/thumbs/'.getDolGlobalString($constname);
	if (getDolGlobalString($constname) != '') {
		dol_delete_file($logosmallfile);
	}
	dolibarr_del_const($db, $constname, $conf->entity);

	$constname='SELLYOURSAAS_LOGO_MINI'.GETPOST('suffix', 'aZ09');
	$logominifile=$conf->mycompany->dir_output.'/logos/thumbs/'.getDolGlobalString($constname);
	if (getDolGlobalString($constname) != '') {
		dol_delete_file($logominifile);
	}
	dolibarr_del_const($db, $constname, $conf->entity);
}
if ($action == 'removelogoblack') {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$constname='SELLYOURSAAS_LOGO_BLACK'.GETPOST('suffix', 'aZ09');
	$logofile=$conf->mycompany->dir_output.'/logos/'.getDolGlobalString($constname);
	if (getDolGlobalString($constname) != '') {
		dol_delete_file($logofile);
	}
	dolibarr_del_const($db, "$constname", $conf->entity);

	$constname='SELLYOURSAAS_LOGO_SMALL_BLACK'.GETPOST('suffix', 'aZ09');
	$logosmallfile=$conf->mycompany->dir_output.'/logos/thumbs/'.getDolGlobalString($constname);
	if (getDolGlobalString($constname) != '') {
		dol_delete_file($logosmallfile);
	}
	dolibarr_del_const($db, $constname, $conf->entity);

	$constname='SELLYOURSAAS_LOGO_MINI_BLACK'.GETPOST('suffix', 'aZ09');
	$logominifile=$conf->mycompany->dir_output.'/logos/thumbs/'.getDolGlobalString($constname);
	if (getDolGlobalString($constname) != '') {
		dol_delete_file($logominifile);
	}
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

$linkback='<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans('SellYouSaasSetup'), $linkback, 'setup');

$error=0;

$head = sellyoursaas_admin_prepare_head();
print dol_get_fiche_head($head, "setup", "SellYouSaasSetup", -1, "sellyoursaas@sellyoursaas");

print '<span class="opacitymedium">'.$langs->trans("Prerequisites")." :</span><br>\n";
print 'Function <b>idn_to_ascii</b> available: '.(function_exists('idn_to_ascii') ? img_picto('', 'tick', 'class="paddingrightonly"').yn(1) : img_picto('', 'warning', 'class="paddingrightonly"').yn(0)).'<br>';
print 'Function <b>checkdnsrr</b> available: '.(function_exists('checkdnsrr') ? img_picto('', 'tick', 'class="paddingrightonly"').yn(1) : img_picto('', 'warning', 'class="paddingrightonly"').yn(0)).'<br>';
print 'Parameter <b>allow_url_fopen</b> is on: '.(ini_get('allow_url_fopen') ? img_picto('', 'tick', 'class="paddingrightonly"').yn(1) : img_picto('', 'warning', 'class="paddingrightonly"').yn(0)).'<br>';
$arrayoffunctionsdisabled = explode(',', ini_get('disable_functions'));
if (in_array('exec', $arrayoffunctionsdisabled)) {
	print "Parameter <b>disable_functions</b>: ".img_picto('', 'error', 'class="paddingrightonly"')." Bad. Must not contain 'exec'<br>";
} else {
	print 'Parameter <b>disable_functions</b>: '.img_picto('', 'tick', 'class="paddingrightonly"').' does not contains: exec<br>';
}
if (in_array('popen', $arrayoffunctionsdisabled)) {
	print "Parameter <b>disable_functions</b>: ".img_picto('', 'error', 'class="paddingrightonly"')." Bad. Must not contain 'popen'<br>";
} else {
	print 'Parameter <b>disable_functions</b>: '.img_picto('', 'tick', 'class="paddingrightonly"').' does not contains: popen (used by /etc/init.d/smtp_watchdog_daemon1.php)<br>';
}
if (in_array('shell_exec', $arrayoffunctionsdisabled)) {
	print "Parameter <b>disable_functions</b>: ".img_picto('', 'error', 'class="paddingrightonly"')." Bad. Must not contain 'shell_exec'<br>";
} else {
	print 'Parameter <b>disable_functions</b>: '.img_picto('', 'tick', 'class="paddingrightonly"').' does not contains: shell_exec (used by /usr/local/bin/phpsendmail.php)<br>';
}
print "<br>\n";

print '<form enctype="multipart/form-data" method="POST" action="'.$_SERVER["PHP_SELF"].'" name="form_index">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td><td></td>';
print '<td><div class="float">'.$langs->trans("Examples").'</div><div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
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

if (!getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION')) {
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
}

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
print '<td><span class="opacitymedium small wordbreak">'.dol_buildpath('sellyoursaas/scripts').'</span></td>';
print '</tr>';

/*print '<tr class="oddeven"><td>'.$langs->trans("DefaultProductForUsers").'</td>';
print '<td>';
$defaultproductforusersid=getDolGlobalString('SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS');
print $form->select_produits($defaultproductforusersid, 'SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS');
print '</td>';
print '<td>My SaaS service for users</td>';
print '</tr>';
*/

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("DefaultCategoryForSaaSServices").'</td>';
print '<td class="width200">';	// We force width so the picto+combo can calculate width and the combo size can be adjusted according to size of cell
$defaultproductcategid=getDolGlobalString('SELLYOURSAAS_DEFAULT_PRODUCT_CATEG');
print img_picto('', 'category', 'class="pictofixedwidth"');
print $formother->select_categories(Categorie::TYPE_PRODUCT, $defaultproductcategid, 'SELLYOURSAAS_DEFAULT_PRODUCT_CATEG', 0, 1, 'minwidth100 maxwidth400 widthcentpercentminusx');
print '</td>';
print '<td><span class="opacitymedium small">SaaS Products</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("DefaultCategoryForSaaSCustomers").'</td>';
print '<td class="width200">';	// We force width so the picto+combo can calculate width and the combo size can be adjusted according to size of cell
$defaultcustomercategid=getDolGlobalString('SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG');
print img_picto('', 'category', 'class="pictofixedwidth"');
print $formother->select_categories(Categorie::TYPE_CUSTOMER, $defaultcustomercategid, 'SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG', 0, 1, 'minwidth100 maxwidth400 widthcentpercentminusx');
print '</td>';
print '<td><span class="opacitymedium small">SaaS Customers</span></td>';
print '</tr>';

/*
print '<tr class="oddeven"><td>'.$langs->trans("RefsUrl", DOL_DOCUMENT_ROOT.'/sellyoursaas/git');
print '</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_REFS_URL" value="'.getDolGlobalString('SELLYOURSAAS_REFS_URL').'">';
print '</td>';
print '<td><span class="opacitymedium small wordbreak">https://admin.mysaasdomainname.com/git</span></td>';
print '</tr>';
*/

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasAccountUrl").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_ACCOUNT_URL" value="'.getDolGlobalString('SELLYOURSAAS_ACCOUNT_URL').'">';
print '</td>';
print '<td><span class="opacitymedium small wordbreak">https://myaccount.mysaasdomainname.com<br>Note: The virtual host for this domain must point to <strong>'.dol_buildpath('sellyoursaas/myaccount').'</strong></span></td>';
print '</tr>';

foreach ($arrayofsuffixfound as $service => $suffix) {
	print '<!-- suffix = '.$suffix.' -->'."\n";

	print '<tr class="oddeven"><td>'.($service ? $service.' - ' : '').$langs->trans("SellYourSaasPricesUrl").'</td>';
	print '<td>';
	$constname = 'SELLYOURSAAS_PRICES_URL'.$suffix;
	print '<!-- constname = '.$constname.' -->';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_PRICES_URL'.$suffix.'" value="'.getDolGlobalString('SELLYOURSAAS_PRICES_URL'.$suffix).'">';
	print '</td>';
	print '<td><span class="opacitymedium small">https://myaccount.mysaasdomainname.com/prices.html</span></td>';
	print '</tr>';
}

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

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("AnonymousUser").'</td>';
print '<td>';
print img_picto('', 'user', 'class="pictofixedwidth"');
print $form->select_dolusers(getDolGlobalString('SELLYOURSAAS_ANONYMOUSUSER'), 'SELLYOURSAAS_ANONYMOUSUSER', 1);
print '</td>';
print '<td><span class="opacitymedium small">User used for all anonymous action (registering, actions from customer dashboard, ...)</span></td>';
print '</tr>';

print '</table>';
print '</div>';

print "</form>\n";


llxfooter();

$db->close();
