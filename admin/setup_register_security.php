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
		if (GETPOSTISSET("SELLYOURSAAS_DISABLE_NEW_INSTANCES_EXCEPT_IP")) {
			dolibarr_set_const($db, "SELLYOURSAAS_DISABLE_NEW_INSTANCES_EXCEPT_IP", GETPOST("SELLYOURSAAS_DISABLE_NEW_INSTANCES_EXCEPT_IP", 'alpha'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET("SELLYOURSAAS_ONLY_NON_PROFIT_ORGA")) {
			dolibarr_set_const($db, "SELLYOURSAAS_ONLY_NON_PROFIT_ORGA", GETPOST("SELLYOURSAAS_ONLY_NON_PROFIT_ORGA", 'alpha'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET("SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_MAX_MEMBERS")) {
			dolibarr_set_const($db, "SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_MAX_MEMBERS", GETPOST("SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_MAX_MEMBERS", 'alpha'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET("SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_MAX_EMPLOYEES")) {
			dolibarr_set_const($db, "SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_MAX_EMPLOYEES", GETPOST("SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_MAX_EMPLOYEES", 'alpha'), 'chaine', 0, '', $conf->entity);
		}

		if (GETPOSTISSET("SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED")) {
			dolibarr_set_const($db, "SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED", GETPOST("SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED", 'alpha'), 'chaine', 0, '', $conf->entity);
		}


		// Google recaptcha
		if (GETPOSTISSET("SELLYOURSAAS_GOOGLE_RECAPTCHA_ON")) {
			dolibarr_set_const($db, "SELLYOURSAAS_GOOGLE_RECAPTCHA_ON", GETPOST("SELLYOURSAAS_GOOGLE_RECAPTCHA_ON", 'alpha'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET("SELLYOURSAAS_GOOGLE_RECAPTCHA_SITE_KEY")) {
			dolibarr_set_const($db, "SELLYOURSAAS_GOOGLE_RECAPTCHA_SITE_KEY", GETPOST("SELLYOURSAAS_GOOGLE_RECAPTCHA_SITE_KEY", 'alpha'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET("SELLYOURSAAS_GOOGLE_RECAPTCHA_SECRET_KEY")) {
			dolibarr_set_const($db, "SELLYOURSAAS_GOOGLE_RECAPTCHA_SECRET_KEY", GETPOST("SELLYOURSAAS_GOOGLE_RECAPTCHA_SECRET_KEY", 'alpha'), 'chaine', 0, '', $conf->entity);
		}

		// IP Intel
		if (GETPOSTISSET("SELLYOURSAAS_GETIPINTEL_ON")) {
			dolibarr_set_const($db, "SELLYOURSAAS_GETIPINTEL_ON", GETPOST("SELLYOURSAAS_GETIPINTEL_ON", 'alpha'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET("SELLYOURSAAS_GETIPINTEL_EMAIL")) {
			dolibarr_set_const($db, "SELLYOURSAAS_GETIPINTEL_EMAIL", GETPOST("SELLYOURSAAS_GETIPINTEL_EMAIL", 'alpha'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET("SELLYOURSAAS_IPQUALITY_ON")) {
			dolibarr_set_const($db, "SELLYOURSAAS_IPQUALITY_ON", GETPOST("SELLYOURSAAS_IPQUALITY_ON", 'alpha'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET("SELLYOURSAAS_IPQUALITY_KEY")) {
			dolibarr_set_const($db, "SELLYOURSAAS_IPQUALITY_KEY", GETPOST("SELLYOURSAAS_IPQUALITY_KEY", 'alpha'), 'chaine', 0, '', $conf->entity);
		}

		dolibarr_set_const($db, 'SELLYOURSAAS_MAXDEPLOYMENTPERIP', GETPOST("SELLYOURSAAS_MAXDEPLOYMENTPERIP", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SELLYOURSAAS_MAXDEPLOYMENTPERIPVPN', GETPOST("SELLYOURSAAS_MAXDEPLOYMENTPERIPVPN", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR', GETPOST("SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT', GETPOST("SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SELLYOURSAAS_MAXDEPLOYMENTPARALLEL', GETPOST("SELLYOURSAAS_MAXDEPLOYMENTPARALLEL", 'int'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, 'SELLYOURSAAS_VPN_PROBA_REFUSED', GETPOST("SELLYOURSAAS_VPN_PROBA_REFUSED", 'alphanohtml'), 'chaine', 0, '', $conf->entity);


		dolibarr_set_const($db, "SELLYOURSAAS_NAME_RESERVED", GETPOST("SELLYOURSAAS_NAME_RESERVED"), 'chaine', 0, '', $conf->entity);
		if (getDolGlobalInt('SELLYOURSAAS_EMAIL_ADDRESSES_BANNED_ENABLED')) {
			dolibarr_set_const($db, "SELLYOURSAAS_EMAIL_ADDRESSES_BANNED", GETPOST("SELLYOURSAAS_EMAIL_ADDRESSES_BANNED"), 'chaine', 0, '', $conf->entity);
		}


		dolibarr_set_const($db, "SELLYOURSAAS_HASHALGOFORPASSWORD", GETPOST("SELLYOURSAAS_HASHALGOFORPASSWORD", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SALTFORPASSWORDENCRYPTION", GETPOST("SELLYOURSAAS_SALTFORPASSWORDENCRYPTION", 'alpha'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_REMOTE_ACTION_SIGNATURE_KEY", GETPOST("SELLYOURSAAS_REMOTE_ACTION_SIGNATURE_KEY", 'alpha'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_SSH2_HOSTKEYALGO", GETPOST("SELLYOURSAAS_SSH2_HOSTKEYALGO", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SSH2_KEXALGO", GETPOST("SELLYOURSAAS_SSH2_KEXALGO", 'alpha'), 'chaine', 0, '', $conf->entity);

		foreach ($arrayofsuffixfound as $suffix) {
			dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_PRODUCT".$suffix, GETPOST("SELLYOURSAAS_DEFAULT_PRODUCT".$suffix), 'chaine', 0, '', $conf->entity);
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
print dol_get_fiche_head($head, "setup_register_security", "SellYouSaasSetup", -1, "sellyoursaas@sellyoursaas");

print '<form enctype="multipart/form-data" method="POST" action="'.$_SERVER["PHP_SELF"].'" name="form_index">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td><td></td>';
print '<td><div class="float">'.$langs->trans("Examples").'</div><div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
print "</tr>\n";

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_DISABLE_NEW_INSTANCES_EXCEPT_IP").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_DISABLE_NEW_INSTANCES_EXCEPT_IP" spellcheck="false" value="'.getDolGlobalString('SELLYOURSAAS_DISABLE_NEW_INSTANCES_EXCEPT_IP').'">';
print '</td>';
print '<td><span class="opacitymedium small">1.2.3.4,...</span></td>';
print '</tr>';

// Option to say that only non profit organisation can register. The checkbox become mandatory
print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ONLY_NON_PROFIT_ORGA").'</td>';
print '<td>';
$array = array(
	'0' => 'No',
	'1' => 'NonProfitOrganization',
	'2' => 'NonProfitOrganizationAndCaritative',
	'3' => $langs->trans('NonProfitOrganizationAndSmall', getDolGlobalInt('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_NB_MEMBERS', 100), getDolGlobalInt('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_NB_SALARIES', 2))
);
print $form->selectarray('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA', $array, getDolGlobalString('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA'), 0, 0, 0, '', 1, 0, 0, '', 'maxwidth250');
print '</td>';
print '<td><span class="opacitymedium small">Set to a value if you want to restrict registration to some non-profit organizations only</span></td>';
print '</tr>';

if (getDolGlobalString('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA') == 3) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_MAX_MEMBERS").'</td>';
	print '<td>';
	print '<input class="width75" type="number" name="SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_MAX_MEMBERS" value="'.getDolGlobalInt('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_MAX_MEMBERS', 100).'">';
	print '</td>';
	print '<td><span class="opacitymedium small">100</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_MAX_EMPLOYEES").'</td>';
	print '<td>';
	print '<input class="width75" type="number" name="SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_MAX_EMPLOYEES" value="'.getDolGlobalString('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_MAX_EMPLOYEES', 2).'">';
	print '</td>';
	print '<td><span class="opacitymedium small">2</span></td>';
	print '</tr>';
}

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NAME_RESERVED").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_NAME_RESERVED" value="'.getDolGlobalString('SELLYOURSAAS_NAME_RESERVED').'">';
print '</td>';
print '<td><span class="opacitymedium small">^mycompany[0-9]*\.</span></td>';
print '</tr>';

// Option to disable the random autoselect of server
print '<tr class="oddeven"><td>';
print $form->textwithpicto($langs->trans("SELLYOURSAAS_FORCE_NO_SELECTION_IF_SEVERAL"), $langs->trans("SELLYOURSAAS_FORCE_NO_SELECTION_IF_SEVERALHelp"));
print '</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_FORCE_NO_SELECTION_IF_SEVERAL', array(), null, 0, 0, 1);
} else {
	if (!getDolGlobalString('SELLYOURSAAS_FORCE_NO_SELECTION_IF_SEVERAL')) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=setSELLYOURSAAS_FORCE_NO_SELECTION_IF_SEVERAL">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delSELLYOURSAAS_FORCE_NO_SELECTION_IF_SEVERAL">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td></td>';
print '</tr>';

// Option to allow the selection of the service
/* To do this, just use several servive keys as parameter plan= of the register URL
print '<tr class="oddeven"><td>';
print $form->textwithpicto($langs->trans("SELLYOURSAAS_ALLOW_SELECTION_OF_SERVICE"), $langs->trans("SELLYOURSAAS_ALLOW_SELECTION_OF_SERVICEHelp"));
print '</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_ALLOW_SELECTION_OF_SERVICE', array(), null, 0, 0, 1);
} else {
	if (!getDolGlobalString('SELLYOURSAAS_ALLOW_SELECTION_OF_SERVICE')) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=setSELLYOURSAAS_ALLOW_SELECTION_OF_SERVICE">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delSELLYOURSAAS_ALLOW_SELECTION_OF_SERVICE">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td></td>';
print '</tr>';
*/

// Set the default product to use when registering a new instance
foreach ($arrayofsuffixfound as $service => $suffix) {
	print '<!-- suffix = '.$suffix.' -->'."\n";

	print '<tr class="oddeven"><td>'.($service ? $service.' - ' : '').$langs->trans("DefaultProductForInstances").'</td>';
	print '<td class="nowraponall">';
	$constname = 'SELLYOURSAAS_DEFAULT_PRODUCT'.$suffix;
	print '<!-- constname = '.$constname.' -->';
	$defaultproductid = getDolGlobalString($constname);
	print img_picto('', 'product', 'class="pictofixedwidth"');
	print $form->select_produits($defaultproductid, 'SELLYOURSAAS_DEFAULT_PRODUCT'.$suffix, '', 0, 0, 1, 2, '', 0, array(), 0, '1', 0, 'minwidth175 maxwidth500 widthcentpercentminusx');
	print '</td>';
	print '<td><span class="opacitymedium small">My SaaS service for instance</span></td>';
	print '</tr>';
}

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAXDEPLOYMENTPERIP").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAXDEPLOYMENTPERIP" value="'.getDolGlobalInt('SELLYOURSAAS_MAXDEPLOYMENTPERIP', 20).'">';
print '</td>';
print '<td><span class="opacitymedium small">20</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR" value="'.getDolGlobalInt('SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR', 5).'">';
print '</td>';
print '<td><span class="opacitymedium small">5</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("SELLYOURSAAS_MAXDEPLOYMENTPARALLEL"), $langs->trans("SELLYOURSAAS_MAXDEPLOYMENTPARALLELDesc")).'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAXDEPLOYMENTPARALLEL" value="'.getDolGlobalInt('SELLYOURSAAS_MAXDEPLOYMENTPARALLEL', 4).'">';
print '</td>';
print '<td><span class="opacitymedium small">4</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT" value="'.getDolGlobalInt('SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT', 4).'">';
print '</td>';
print '<td><span class="opacitymedium small">4</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_HASHALGOFORPASSWORD").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_HASHALGOFORPASSWORD" value="'.getDolGlobalString('SELLYOURSAAS_HASHALGOFORPASSWORD').'">';
print '</td>';
print '<td><span class="opacitymedium small">\'sha1md5\', \'sha256\', \'password_hash\', ...<br>Useless if you don\'t use the substitution key __APPPASSWORD0__ in package definition (for example if you used __APPPASSWORDMD5__ or __APPPASSWORDSHA256__ or __APPPASSWORDPASSWORD_HASH__ instead)</span></td>';
print '</tr>';

if (!getDolGlobalString('SELLYOURSAAS_HASHALGOFORPASSWORD') || getDolGlobalString('SELLYOURSAAS_HASHALGOFORPASSWORD') != 'password_hash') {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_SALTFORPASSWORDENCRYPTION").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_SALTFORPASSWORDENCRYPTION" value="'.getDolGlobalString('SELLYOURSAAS_SALTFORPASSWORDENCRYPTION').'">';
	print '</td>';
	print '<td><span class="opacitymedium small"></span></td>';
	print '</tr>';
}



// Google recaptcha
print '<tr class="liste_titre">';
print '<td>Google ReCaptcha</td>';
print '<td></td>';
print '<td></td>';
print "</tr>\n";

print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("SELLYOURSAAS_GOOGLE_RECAPTCHA_ON"), 'This is a usefull component to fight against spam instances').'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_GOOGLE_RECAPTCHA_ON', array(), null, 0, 0, 1);
} else {
	if (!getDolGlobalString('SELLYOURSAAS_GETIPINTEL_ON')) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=setSELLYOURSAAS_GOOGLE_RECAPTCHA_ON">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delSELLYOURSAAS_GOOGLE_RECAPTCHA_ON">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td></td>';
print '</tr>';

if (getDolGlobalInt('SELLYOURSAAS_GOOGLE_RECAPTCHA_ON')) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_GOOGLE_RECAPTCHA_SITE_KEY");
	print ' &nbsp; <a href="https://www.google.com/recaptcha/admin/create" target="_blank">(Admin...)</a>';
	print '</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_GOOGLE_RECAPTCHA_SITE_KEY" value="'.getDolGlobalString('SELLYOURSAAS_GOOGLE_RECAPTCHA_SITE_KEY').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">abc123...</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_GOOGLE_RECAPTCHA_SECRET_KEY").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_GOOGLE_RECAPTCHA_SECRET_KEY" value="'.getDolGlobalString('SELLYOURSAAS_GOOGLE_RECAPTCHA_SECRET_KEY').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">abc123...</span></td>';
	print '</tr>';
}

if (getDolGlobalInt('SELLYOURSAAS_EMAIL_ADDRESSES_BANNED_ENABLED')) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_EMAIL_ADDRESSES_BANNED").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_EMAIL_ADDRESSES_BANNED" value="'.getDolGlobalString('SELLYOURSAAS_EMAIL_ADDRESSES_BANNED').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">yopmail.com,hotmail.com,spammer@gmail.com</span></td>';
	print '</tr>';
}


// Enable DisposableEmail service
print '<tr class="liste_titre">';
print '<td>DisposableEmail (free)</td>';
print '<td></td>';
print '<td></td>';
print "</tr>\n";

print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED"), 'This is a usefull component to fight against spam instances').'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED', array(), null, 0, 0, 1);
} else {
	if (!getDolGlobalString('SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED')) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=setSELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delSELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td></td>';
print '</tr>';

if (getDolGlobalString('SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED')) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_API_KEY", "DispoableEmail").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_API_KEY" value="'.getDolGlobalString('SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_API_KEY').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">1234567890123456</span></td>';
	print '</tr>';
}


// Enable GetIPIntel
print '<tr class="liste_titre">';
print '<td>GetIPIntel (free)</td>';
print '<td></td>';
print '<td></td>';
print "</tr>\n";

print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("SELLYOURSAAS_GETIPINTEL_ON"), 'This is a usefull component to fight against spam instances').'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_GETIPINTEL_ON', array(), null, 0, 0, 1);
} else {
	if (!getDolGlobalString('SELLYOURSAAS_GETIPINTEL_ON')) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=setSELLYOURSAAS_GETIPINTEL_ON">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delSELLYOURSAAS_GETIPINTEL_ON">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td></td>';
print '</tr>';

if (getDolGlobalString('SELLYOURSAAS_GETIPINTEL_ON')) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_GETIPINTEL_EMAIL").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_GETIPINTEL_EMAIL" value="'.getDolGlobalString('SELLYOURSAAS_GETIPINTEL_EMAIL').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">myemail@email.com</span></td>';
	print '</tr>';
}


// Enable IPQualityScore
print '<tr class="liste_titre">';
print '<td>IPQuality</td>';
print '<td></td>';
print '<td></td>';
print "</tr>\n";

print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("SELLYOURSAAS_IPQUALITY_ON"), 'This is a very important component to fight against spam instances').'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_IPQUALITY_ON', array(), null, 0, 0, 1);
} else {
	if (!getDolGlobalString('SELLYOURSAAS_IPQUALITY_ON')) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=setSELLYOURSAAS_IPQUALITY_ON">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delSELLYOURSAAS_IPQUALITY_ON">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td></td>';
print '</tr>';

if (getDolGlobalString('SELLYOURSAAS_IPQUALITY_ON')) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_API_KEY", "IPQualityScore").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_IPQUALITY_KEY" value="'.getDolGlobalString('SELLYOURSAAS_IPQUALITY_KEY').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">1234567890123456</span></td>';
	print '</tr>';
}

if (getDolGlobalString('SELLYOURSAAS_GETIPINTEL_ON') || getDolGlobalString('SELLYOURSAAS_IPQUALITY_ON')) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_VPN_PROBA_REFUSED").'</td>';
	print '<td>';
	print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_VPN_PROBA_REFUSED" value="'.getDolGlobalString('SELLYOURSAAS_VPN_PROBA_REFUSED').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">0.9, 1, Keep empty for no filter on VPN probability</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAXDEPLOYMENTPERIP").' (VPN)</td>';
	print '<td>';
	print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAXDEPLOYMENTPERIPVPN" value="'.getDolGlobalInt('SELLYOURSAAS_MAXDEPLOYMENTPERIPVPN', 2).'">';
	print '</td>';
	print '<td><span class="opacitymedium small">2</span></td>';
	print '</tr>';
}

// Key to sign message on each deployment servers
print '<tr class="liste_titre"><td colspan="3">Security for communication from master to deployment servers (Deprecated. Use instead a different key on each deployment server)</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_REMOTE_ACTION_SIGNATURE_KEY").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_REMOTE_ACTION_SIGNATURE_KEY" id="SELLYOURSAAS_REMOTE_ACTION_SIGNATURE_KEY" value="'.getDolGlobalString('SELLYOURSAAS_REMOTE_ACTION_SIGNATURE_KEY').'">';
if (!empty($conf->use_javascript_ajax)) {
	print '&nbsp;'.img_picto($langs->trans('Generate'), 'refresh', 'id="generate_token" class="linkobject"');
	// Add button to autosuggest a key
	include_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
	print dolJSToSetRandomPassword("SELLYOURSAAS_REMOTE_ACTION_SIGNATURE_KEY", "generate_token");
}
print '</td>';
print '<td><span class="opacitymedium small">Define a value to add a security signature of messages. This key must also be added into all deployment servers into file /etc/sellyoursaas.conf on key "signature_key=..."</span></td>';
print '</tr>';

// SSH2 for master to deployment connection
print '<tr class="liste_titre"><td colspan="3">SSH2 (for master to deployment connection)</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_SSH2_HOSTKEYALGO").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SSH2_HOSTKEYALGO" value="'.getDolGlobalString('SELLYOURSAAS_SSH2_HOSTKEYALGO').'">';
print '</td>';
print '<td><span class="opacitymedium small">Depends on libssl version. Example: ssh-rsa, ssh-dss, ...</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_SSH2_KEXALGO").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SSH2_KEXALGO" value="'.getDolGlobalString('SELLYOURSAAS_SSH2_KEXALGO').'">';
print '</td>';
print '<td><span class="opacitymedium small">Depends on libssl version. Example: diffie-hellman-group-exchange-sha256, diffie-hellman-group1-sha1, diffie-hellman-group14-sha1, diffie-hellman-group-exchange-sha1, ...</span></td>';
print '</tr>';

print '</table>';
print '</div>';

print "</form>\n";


llxfooter();

$db->close();
