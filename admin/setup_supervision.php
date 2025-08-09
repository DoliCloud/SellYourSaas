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
 *     \file       htdocs/sellyoursaas/admin/setup_other.php
 *     \brief      Page administration module SellYourSaas (tab Other)
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
// $arrayofsuffixfound should be now array('mysaasdomain'=>'', mysaasdomainalt'=>'_MYSAASDOMAINALT_COM', ...)
//var_dump($arrayofsuffixfound);



/*
 * Actions
 */

if ($action == 'set') {
	$error=0;

	if (! $error) {
		dolibarr_set_const($db, "SELLYOURSAAS_DATADOG_ENABLED", GETPOST("SELLYOURSAAS_DATADOG_ENABLED", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_DATADOG_APIKEY", GETPOST("SELLYOURSAAS_DATADOG_APIKEY", 'alphanohtml'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_DATADOG_APPKEY", GETPOST("SELLYOURSAAS_DATADOG_APPKEY", 'alphanohtml'), 'chaine', 0, '', $conf->entity);
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
print dol_get_fiche_head($head, "setup_supervision", "SellYouSaasSetup", -1, "sellyoursaas@sellyoursaas");

print '<form enctype="multipart/form-data" method="POST" action="'.$_SERVER["PHP_SELF"].'" name="form_index">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="titlefieldmiddle">'.$langs->trans("Parameters").'</td><td></td>';
print '<td><div class="float">'.$langs->trans("Examples").'</div><div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
print "</tr>\n";

// SELLYOURSAAS_DATADOG_ENABLED
print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_DATADOG_ENABLED").'</td>';
print '<td>';
$array = array('0' => array('label' => 'No'), '1' => array('label' => 'Yes'), '2' => array('label' => 'Yes with detail of remote action errors', 'data-html' => 'Yes with detail of remote action errors <span class="opacitymedium">(May contain sensitive data)</span>'));
print $form->selectarray('SELLYOURSAAS_DATADOG_ENABLED', $array, getDolGlobalString('SELLYOURSAAS_DATADOG_ENABLED'), 0);
print '</td>';
print '<td><span class="opacitymedium small">If a datadog agent is running on each of your server, enable this option so SellyourSaas will send metrics sellyoursaas.* to Datadog.</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_DATADOG_APPKEY").'</td>';
print '<td>';
print '<input class="maxwidth200" type="text" name="SELLYOURSAAS_DATADOG_APPKEY" value="'.getDolGlobalString('SELLYOURSAAS_DATADOG_APPKEY', '').'">';
print '</td>';
print '<td><span class="opacitymedium small">MyApp</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_DATADOG_APIKEY").'</td>';
print '<td>';
print '<input class="maxwidth200" type="text" name="SELLYOURSAAS_DATADOG_APIKEY" value="'.getDolGlobalString('SELLYOURSAAS_DATADOG_APIKEY', '').'">';
print '</td>';
print '<td><span class="opacitymedium small">45fdf4sds54fdf</span></td>';
print '</tr>';

print '</table>';
print '</div>';

print '</table>';
print '</div>';

print "</form>\n";


llxfooter();

$db->close();
