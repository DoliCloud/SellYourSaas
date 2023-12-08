<?php
/* Copyright (C) 2007-2020 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *   	\file       htdocs/sellyoursaas/backoffice/index.php
 *		\ingroup    sellyoursaas
 *		\brief      Home page of DoliCloud service
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
require_once DOL_DOCUMENT_ROOT."/core/lib/company.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/dolgraph.class.php";
require_once DOL_DOCUMENT_ROOT."/core/class/doleditor.class.php";
dol_include_once("/sellyoursaas/backoffice/lib/refresh.lib.php");		// do not use dol_buildpath to keep global of var into refresh.lib.php working
dol_include_once("/sellyoursaas/backoffice/lib/backoffice.lib.php");		// do not use dol_buildpath to keep global of var into refresh.lib.php working


// Load traductions files requiredby by page
$langs->loadLangs(array("companies","other","sellyoursaas@sellyoursaas"));

// Get parameters
$id			= GETPOST('id', 'int');
$action		= GETPOST('action', 'alpha');
$mode		= GETPOST('mode', 'alpha');

$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST("sortfield", 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOST("page", 'int');
if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
if (! $sortorder) {
	$sortorder='ASC';
}
if (! $sortfield) {
	$sortfield='t.date_registration';
}
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;

$pageprev = $page - 1;
$pagenext = $page + 1;

// Security check
$result = restrictedArea($user, 'sellyoursaas', 0, '', '');

// Set serverprice with the param from $conf of the $dbmaster server.
$serverprice = empty($conf->global->SELLYOURSAAS_INFRA_COST) ? '100' : $conf->global->SELLYOURSAAS_INFRA_COST;



/*
 * Actions
 */

if ($action == 'update') {
	dolibarr_set_const($db, "NLTECHNO_NOTE", GETPOST("NLTECHNO_NOTE", 'none'), 'chaine', 0, '', $conf->entity);
}

if (GETPOST('saveannounce', 'alpha')) {
	dolibarr_set_const($db, "SELLYOURSAAS_ANNOUNCE", GETPOST("SELLYOURSAAS_ANNOUNCE", 'none'), 'chaine', 0, '', $conf->entity);
}

if ($action == 'setSELLYOURSAAS_DISABLE_NEW_INSTANCES') {
	if (GETPOST('value')) {
		dolibarr_set_const($db, 'SELLYOURSAAS_DISABLE_NEW_INSTANCES', 1, 'chaine', 0, '', $conf->entity);
	} else {
		dolibarr_set_const($db, 'SELLYOURSAAS_DISABLE_NEW_INSTANCES', 0, 'chaine', 0, '', $conf->entity);
	}
}
if ($action == 'setSELLYOURSAAS_ANNOUNCE_ON') {
	if (GETPOST('value')) {
		dolibarr_set_const($db, 'SELLYOURSAAS_ANNOUNCE_ON', 1, 'chaine', 0, '', $conf->entity);
	} else {
		dolibarr_set_const($db, 'SELLYOURSAAS_ANNOUNCE_ON', 0, 'chaine', 0, '', $conf->entity);
	}
}



/***************************************************
* VIEW
****************************************************/

$form=new Form($db);

llxHeader('', $langs->transnoentitiesnoconv('DoliCloudCustomers'), '');

//print_fiche_titre($langs->trans("DoliCloudArea"));

$head = sellYourSaasBackofficePrepareHead();


//$head = commande_prepare_head(null);
dol_fiche_head($head, 'notes', $langs->trans("DoliCloudArea"), -1, 'sellyoursaas@sellyoursaas');


$tmparray=dol_getdate(dol_now());
$endyear=$tmparray['year'];
$endmonth=$tmparray['mon'];
$datelastday=dol_get_last_day($endyear, $endmonth, 1);
$startyear=$endyear-2;


if ($action != 'edit') {
	print dol_htmlcleanlastbr($conf->global->NLTECHNO_NOTE);

	print '<div class="tabsAction">';

	print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Edit").'</a></div>';

	print '</div>';
} else {
	print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	$doleditor=new DolEditor('NLTECHNO_NOTE', $conf->global->NLTECHNO_NOTE, '', 480, 'dolibarr_mailings');
	print $doleditor->Create(1);
	print '<br>';
	print '<input class="button" type="submit" name="'.$langs->trans("Save").'">';
	print '</form>';
}


dol_fiche_end();


// End of page
llxFooter();

$db->close();
