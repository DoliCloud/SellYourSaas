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
		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT", GETPOST("SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT", GETPOST("SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NBHOURSBETWEENTRIES", GETPOST("SELLYOURSAAS_NBHOURSBETWEENTRIES", 'none'), 'chaine', 0, 'Nb hours minium between each invoice payment try', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES", GETPOST("SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES", 'none'), 'chaine', 0, 'Nb days before stopping invoice payment try', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_ANONYMOUSUSER", GETPOST("SELLYOURSAAS_ANONYMOUSUSER", 'alpha'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND", GETPOST("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND", GETPOST("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT", GETPOST("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT", GETPOST("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT", 'int'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_HASHALGOFORPASSWORD", GETPOST("SELLYOURSAAS_HASHALGOFORPASSWORD", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SALTFORPASSWORDENCRYPTION", GETPOST("SELLYOURSAAS_SALTFORPASSWORDENCRYPTION", 'alpha'), 'chaine', 0, '', $conf->entity);
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
print dol_get_fiche_head($head,"setup_automation", "SellYouSaasSetup", -1,"sellyoursaas@sellyoursaas");

print '<form enctype="multipart/form-data" method="POST" action="'.$_SERVER["PHP_SELF"].'" name="form_index">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td class="titlefield">'.$langs->trans("Automation").'</td><td>'.$langs->trans("Value").'</td>';
print '<td class="titlefield"><div class="float">'.$langs->trans("Examples").'</div><div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
print "</tr>\n";

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("AnonymousUser").'</td>';
print '<td>';
print $form->select_dolusers(getDolGlobalString('SELLYOURSAAS_ANONYMOUSUSER'), 'SELLYOURSAAS_ANONYMOUSUSER', 1);
print '</td>';
print '<td><span class="opacitymedium small">User used for all anonymous action (registering, actions from customer dashboard, ...)</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_HASHALGOFORPASSWORD").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_HASHALGOFORPASSWORD" value="'.getDolGlobalString('SELLYOURSAAS_HASHALGOFORPASSWORD').'">';
print '</td>';
print '<td><span class="opacitymedium small">\'sha1md5\', \'sha256\', \'password_hash\', ...<br>Useless if you don\'t use the substitution key __APPPASSWORD0__ in package definition (for example if you used __APPPASSWORDMD5__ or APPPASSWORDSHA256__ or __APPPASSWORDPASSWORD_HASH__ instead)</span></td>';
print '</tr>';

if (empty($conf->global->SELLYOURSAAS_HASHALGOFORPASSWORD) || $conf->global->SELLYOURSAAS_HASHALGOFORPASSWORD != 'password_hash') {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_SALTFORPASSWORDENCRYPTION").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_SALTFORPASSWORDENCRYPTION" value="'.getDolGlobalString('SELLYOURSAAS_SALTFORPASSWORDENCRYPTION').'">';
	print '</td>';
	print '<td><span class="opacitymedium small"></span></td>';
	print '</tr>';
}

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT').'">';
print '</td>';
print '<td><span class="opacitymedium small">7</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT').'">';
print '</td>';
print '<td><span class="opacitymedium small">1</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND').'">';
print '</td>';
print '<td><span class="opacitymedium small">2</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND').'">';
print '</td>';
print '<td><span class="opacitymedium small">12</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT').'">';
print '</td>';
print '<td><span class="opacitymedium small">30</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBHOURSBETWEENTRIES").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBHOURSBETWEENTRIES" value="'.getDolGlobalString('SELLYOURSAAS_NBHOURSBETWEENTRIES').'">';
print '</td>';
print '<td><span class="opacitymedium small">49</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES').'">';
print '</td>';
print '<td><span class="opacitymedium small">35</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT').'">';
print '</td>';
print '<td><span class="opacitymedium small">120</span></td>';
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
