<?php
/* Copyright (C) 2013 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 * or see http://www.gnu.org/
 */

/**
 *	    \file       htdocs/sellyoursaas/admin/about.php
 *      \ingroup    sellyoursaas
 *      \brief      Page about
 */

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
dol_include_once("/sellyoursaas/lib/sellyoursaas.lib.php");

// Access control
if (! $user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');


$langs->load("admin");
$langs->load("other");
$langs->load("sellyoursaas@sellyoursaas");
$langs->load("sms");


/**
 * View
 */

$help_url='';
llxHeader('', '', $help_url);

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("Setup"), $linkback, 'setup');

$head=sellyoursaasadmin_prepare_head();

dol_fiche_head($head, 'tababout', $langs->trans("sellyoursaas"));

dol_include_once('/sellyoursaas/core/modules/modSellYourSaas.class.php');
$tmpmodule = new modSellYourSaas($db);
if (method_exists($tmpmodule, 'getDescLong')) {
	print $tmpmodule->getDescLong();
}

print '<br><hr><br>';

print $langs->trans("AboutInfo").'<br>';

print $langs->trans("MoreModules").'<br>';
print '&nbsp; &nbsp; &nbsp; '.$langs->trans("MoreModulesLink").'<br>';
$url='https://www.dolistore.com/search.php?search_query=nltechno';
print '<a href="'.$url.'" target="_blank"><img border="0" width="180" src="'.DOL_URL_ROOT.'/theme/dolistore_logo.png"></a><br><br><br>';

print '<br>';
print $langs->trans("MoreCloudHosting").'<br>';
print '&nbsp; &nbsp; &nbsp; '.$langs->trans("MoreCloudHostingLink").'<br>';
$url='https://www.dolicloud.com';
print '<a href="'.$url.'" target="_blank"><img border="0" width="180" src="../img/dolicloud_logo.png"></a><br><br><br>';

print '<br>';
print $langs->trans("CompatibleWithDoliDroid").'<br>';
$url='https://play.google.com/store/apps/details?id=com.nltechno.dolidroidpro';
print '<a href="'.$url.'" target="_blank"><img border="0" width="180" src="../img/dolidroid_512x512_en.png"></a><br><br>';

dol_fiche_end();


llxFooter();

$db->close();
