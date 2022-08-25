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
		dolibarr_set_const($db, "SELLYOURSAAS_PUBLIC_KEY", GETPOST("SELLYOURSAAS_PUBLIC_KEY", 'none'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_LOGIN_FOR_SUPPORT", GETPOST("SELLYOURSAAS_LOGIN_FOR_SUPPORT", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_PASSWORD_FOR_SUPPORT", GETPOST("SELLYOURSAAS_PASSWORD_FOR_SUPPORT", 'none'), 'chaine', 0, '', $conf->entity);

		$dir=GETPOST("DOLICLOUD_INSTANCES_PATH");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db, "DOLICLOUD_INSTANCES_PATH", GETPOST("DOLICLOUD_INSTANCES_PATH"), 'chaine', 0, '', $conf->entity);

		$dir=GETPOST("DOLICLOUD_BACKUP_PATH");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db, "DOLICLOUD_BACKUP_PATH", GETPOST("DOLICLOUD_BACKUP_PATH"), 'chaine', 0, '', $conf->entity);

		$dir=GETPOST("SELLYOURSAAS_TEST_ARCHIVES_PATH");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db, "SELLYOURSAAS_TEST_ARCHIVES_PATH", GETPOST("SELLYOURSAAS_TEST_ARCHIVES_PATH"), 'chaine', 0, '', $conf->entity);

		$dir=GETPOST("SELLYOURSAAS_PAID_ARCHIVES_PATH");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db, "SELLYOURSAAS_PAID_ARCHIVES_PATH", GETPOST("SELLYOURSAAS_PAID_ARCHIVES_PATH"), 'chaine', 0, '', $conf->entity);
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


$error=0;

$head = sellyoursaas_admin_prepare_head();
print dol_get_fiche_head($head, "setup_deploy_server", "SellYouSaasSetup", -1, "sellyoursaas@sellyoursaas");

print '<form enctype="multipart/form-data" method="POST" action="'.$_SERVER["PHP_SELF"].'" name="form_index">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td class="titlefield">'.$langs->trans("Parameters").'</td><td>'.$langs->trans("Value").'</td>';
print '<td class="titlefield"><div class="float">'.$langs->trans("Examples").'</div><div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
print "</tr>\n";

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("DirForDoliCloudInstances").'</td>';
print '<td>';
print '<input class="minwidth200" type="text" name="DOLICLOUD_INSTANCES_PATH" value="'.getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'">';
print '</td>';
print '<td><span class="opacitymedium small">/home/jail/home</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">';
print $form->textwithpicto($langs->trans("DirForBackupTestInstances"), '').'</td>';
print '<td>';
//print '<input class="minwidth200" type="text" name="DOLICLOUD_TEST_BACKUP_PATH" value="'.getDolGlobalString('DOLICLOUD_TEST_BACKUP_PATH').'">';
print '<span class="opacitymedium">'.$langs->trans("FeatureNotYetAvailable").'</span>';
print '</td>';
print '<td>';
//print '/home/jail/backup, /mnt/diskbackup/backup';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">';
print $form->textwithpicto($langs->trans("DirForBackupInstances"), '').'</td>';
print '<td>';
print '<input class="minwidth200" type="text" name="DOLICLOUD_BACKUP_PATH" value="'.getDolGlobalString('DOLICLOUD_BACKUP_PATH').'">';
print '</td>';
print '<td><span class="opacitymedium small">/home/jail/backup, /mnt/diskbackup/backup</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">';
print $form->textwithpicto($langs->trans("SELLYOURSAAS_TEST_ARCHIVES_PATH"), $langs->trans("ArchiveInstanceDesc").'<br><br>'.$langs->trans("ArchiveTestInstanceDesc")).'</td>';
print '<td>';
print '<input class="minwidth200" type="text" name="SELLYOURSAAS_TEST_ARCHIVES_PATH" value="'.getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'">';
print '</td>';
print '<td><span class="opacitymedium small">/home/jail/archives-test, /mnt/diskbackup/archives-test</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">';
print $form->textwithpicto($langs->trans("SELLYOURSAAS_PAID_ARCHIVES_PATH"), $langs->trans("ArchiveInstanceDesc")).'</td>';
print '<td>';
print '<input class="minwidth200" type="text" name="SELLYOURSAAS_PAID_ARCHIVES_PATH" value="'.getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'">';
print '</td>';
print '<td><span class="opacitymedium small">/home/jail/archives-paid, /mnt/diskbackup/archives-paid</span></td>';
print '</tr>';

// SSH public keys to deploy on authized_public file.
print '<tr class="oddeven"><td>'.$langs->trans("SSHPublicKey").'</td>';
print '<td>';
print '<textarea name="SELLYOURSAAS_PUBLIC_KEY" class="quatrevingtpercent" rows="3">'.getDolGlobalString('SELLYOURSAAS_PUBLIC_KEY').'</textarea>';
print '</td>';
print '<td><span class="opacitymedium small">'.$langs->trans("SSHPublicKeyDesc").'</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("LoginForSupport").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_LOGIN_FOR_SUPPORT" value="'.getDolGlobalString('SELLYOURSAAS_LOGIN_FOR_SUPPORT').'">';
print '</td>';
print '<td><span class="opacitymedium small">'.$langs->trans("LoginSupportHelp").'</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("PasswordForSupport").'</td>';
print '<td>';
print '<input type="password" name="SELLYOURSAAS_PASSWORD_FOR_SUPPORT" value="'.getDolGlobalString('SELLYOURSAAS_PASSWORD_FOR_SUPPORT').'">';
if (getDolGlobalString('SELLYOURSAAS_PASSWORD_FOR_SUPPORT')) {
	print showValueWithClipboardCPButton(getDolGlobalString('SELLYOURSAAS_PASSWORD_FOR_SUPPORT'), 0, 'none');
}
print '</td>';
print '<td><span class="opacitymedium small">Password to use to create a support user account on customer instances</span></td>';
print '</tr>';

print '</table>';
print '</div>';

print "</form>\n";


llxfooter();

$db->close();
