<?php
/* Copyright (C) 2007-2021 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *   	\file       htdocs/sellyoursaas/backoffice/setup_antispam.php
 *		\ingroup    sellyoursaas
 *		\brief      Home to setup antispam
 */

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
require_once DOL_DOCUMENT_ROOT."/core/lib/company.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/dolgraph.class.php";
require_once DOL_DOCUMENT_ROOT."/core/class/doleditor.class.php";
dol_include_once("/sellyoursaas/backoffice/lib/refresh.lib.php");		// do not use dol_buildpath to keep global of var into refresh.lib.php working
dol_include_once("/sellyoursaas/backoffice/lib/backoffice.lib.php");		// do not use dol_buildpath to keep global of var into refresh.lib.php working



// Load traductions files requiredby by page
$langs->loadLangs(array("admin", "companies", "other", "sellyoursaas@sellyoursaas"));

// Get parameters
$id			= GETPOST('id', 'int');
$action		= GETPOST('action', 'alpha');
$mode		= GETPOST('mode', 'alpha');

$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST("sortfield", 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOST("page", 'int');
if (empty($page) || $page == -1) { $page = 0; }     // If $page is not defined, or '' or -1
$offset = $limit * $page;
if (! $sortorder) $sortorder='ASC';
if (! $sortfield) $sortfield='t.date_registration';
$limit = GETPOST('limit', 'int')?GETPOST('limit', 'int'):$conf->liste_limit;

$pageprev = $page - 1;
$pagenext = $page + 1;

// Security check
$result = restrictedArea($user, 'sellyoursaas', 0, '', '');

$keytodesactivate	= GETPOST('key', 'alpha');
$value	= GETPOST('value', 'alpha');


/*
 *	Actions
 */

// None


/*
 *	View
 */

$form=new Form($db);

llxHeader('', $langs->transnoentitiesnoconv('AntiSpam'), '');

//print_fiche_titre($langs->trans("DoliCloudArea"));

$head = sellYourSaasBackofficePrepareHead();


//$head = commande_prepare_head(null);
dol_fiche_head($head, 'antispam', $langs->trans("AntiSpam"), -1, 'sellyoursaas@sellyoursaas');


$tmparray=dol_getdate(dol_now());
$endyear=$tmparray['year'];
$endmonth=$tmparray['mon'];
$datelastday=dol_get_last_day($endyear, $endmonth, 1);
$startyear=$endyear-2;


print '<div class="fichecenter">';

print "\n";
print "<!-- section of deployment servers -->\n";
print '<div class="div-table-responsive-no-min">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="noborder nohover centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Options').'</td>';
print '<td></td>';
print '</tr>';

//$dirforspam = DOL_DATA_ROOT.'/sellyoursaas_local/spam';
$dirforspam = getDolGlobalString('SELLYOURSAAS_DIR_FOR_SPAM', '/tmp/spam');

print '<tr class="oddeven nohover">';
print '<td>';
$filemaster = $dirforspam.'/blacklistfrom';
$file = $filemaster;
$htmltext = $langs->trans("ExampleContentOfFileOnMaster", $file).'<br>';
if (dol_is_file($filemaster)) {
	$htmltext .= dol_htmlentitiesbr(file_get_contents($filemaster));
}
print $form->textwithpicto($langs->trans("FileFor", 'blacklistfrom').' <span class="opacitymedium">('.$langs->trans("FileToEditedManually").')</span>', '', 1, 'help', '', 0, 3, 'blacklistfrom');
print '</td>';
print '<td>';
print $form->textwithpicto($file, $htmltext, 1, 'help', '', 0, 3, 'blacklistfrom');
print '</td>';
print '</tr>';

print '<tr class="oddeven nohover">';
print '<td>';
$filemaster = $dirforspam.'/blacklistcontent';
$file = $filemaster;
$htmltext = $langs->trans("ExampleContentOfFileOnMaster", $file).'<br>';
if (dol_is_file($filemaster)) {
	$htmltext .= dol_htmlentitiesbr(file_get_contents($filemaster));
}
print $form->textwithpicto($langs->trans("FileFor", 'blacklistcontent').' <span class="opacitymedium">('.$langs->trans("FileToEditedManually").')</span>', '', 1, 'help', '', 0, 3, 'blacklistcontent');
print '</td>';
print '<td>';
print $form->textwithpicto($file, $htmltext, 1, 'help', '', 0, 3, 'blacklistcontent');
print '</td>';
print '</tr>';

print '<tr class="oddeven nohover">';
print '<td>';
$filemaster = $dirforspam.'/blacklistip';
$file = $filemaster;
$htmltext = $langs->trans("ExampleContentOfFileOnMaster", $file).'<br>';
if (dol_is_file($filemaster)) {
	$htmltext .= dol_htmlentitiesbr(file_get_contents($filemaster));
}
print $form->textwithpicto($langs->trans("FileFor", 'blacklistip'), $langs->trans("FileEditedAutomaticallyByMailWrapperOnAbuseDetection"), 1, 'help', '', 0, 3, 'blacklistip');
print '</td>';
print '<td>';
print $form->textwithpicto($file, $htmltext, 1, 'help', '', 0, 3, 'blacklistip');
print '</td>';
print '</tr>';

print '<tr class="oddeven nohover">';
print '<td>';
print $langs->trans("EnableAlertEmailOnWebhookSpamReport");
print '</td>';
print '<td>';
print ajax_constantonoff('SELLYOURSAAS_SPAMREPORT_EMAIL_DISABLED', array(), null, 1);
print '</td>';
print '</tr>';

print '<tr class="oddeven nohover">';
print '<td>';
print $langs->trans("EnableAlertDatadogOnWebhookSpamReport");
print '</td>';
print '<td>';
print ajax_constantonoff('SELLYOURSAAS_SPAMREPORT_DATADOG_DISABLED', array(), null, 1);
print '</td>';
print '</tr>';

print "</table>";
print '</div>';

print "</div>";

dol_fiche_end();


print '<br>';

$message='';
$url='<a href="'.dol_buildpath('/sellyoursaas/public/spamreport.php', 3).'?key='.(getDolGlobalString('SELLYOURSAAS_SECURITY_KEY')?urlencode(getDolGlobalString('SELLYOURSAAS_SECURITY_KEY')):'...').'&mode=test" target="_blank">'.dol_buildpath('/sellyoursaas/public/spamreport.php', 3).'?key='.(getDolGlobalString('SELLYOURSAAS_SECURITY_KEY')?urlencode(getDolGlobalString('SELLYOURSAAS_SECURITY_KEY')):'KEYNOTDEFINED').'[&mode=test]</a>';
$message.=img_picto('', 'object_globe.png').' '.$langs->trans("EndPointFor", "WebHook SpamReport", '{s1}');
$message = str_replace('{s1}', $url, $message);
print $message;

/*
print '<br>';

$message='';
$url='<a href="'.dol_buildpath('/sellyoursaas/myaccount/public/test.php', 3).'?key='.($conf->global->SELLYOURSAAS_SECURITY_KEY?urlencode($conf->global->SELLYOURSAAS_SECURITY_KEY):'...').'" target="_blank">'.dol_buildpath('/sellyoursaas/public/test.php', 3).'?key='.($conf->global->SELLYOURSAAS_SECURITY_KEY?urlencode($conf->global->SELLYOURSAAS_SECURITY_KEY):'KEYNOTDEFINED').'</a>';
$message.=img_picto('', 'object_globe.png').' '.$langs->trans("EndPointFor", "Test", '{s1}');
$message = str_replace('{s1}', $url, $message);
print $message;
*/

print '<br>';



// End of page
llxFooter();

$db->close();
