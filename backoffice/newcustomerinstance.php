<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *       \file       htdocs/sellyoursaas/backoffice/newcustomerinstance.php
 *       \ingroup    sellyoursaas
 *       \brief      Page to create a new SaaS customer or instance
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

require_once DOL_DOCUMENT_ROOT."/comm/action/class/actioncomm.class.php";
require_once DOL_DOCUMENT_ROOT."/contact/class/contact.class.php";
require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
require_once DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php";
require_once DOL_DOCUMENT_ROOT."/compta/facture/class/facture-rec.class.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/company.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/date.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formcompany.class.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php";
dol_include_once("/sellyoursaas/core/lib/sellyoursaas.lib.php");
dol_include_once("/sellyoursaas/backoffice/lib/refresh.lib.php");		// do not use dol_buildpath to keep global of var into refresh.lib.php working

$langs->loadLangs(array("admin", "companies", "users", "other", "commercial", "bills", "sellyoursaas@sellyoursaas"));

$mesg=''; $error=0; $errors=array();

$action		= (GETPOST('action', 'alpha') ? GETPOST('action', 'alpha') : 'view');
$confirm	= GETPOST('confirm', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$id			= GETPOST('id', 'int');
$instanceoldid = GETPOST('instanceoldid', 'alpha');
$instance   = GETPOST('instance', 'alpha');
$ref        = GETPOST('ref', 'alpha');
$refold     = GETPOST('refold', 'alpha');
$date_registration  = dol_mktime(0, 0, 0, GETPOST("date_registrationmonth", 'int'), GETPOST("date_registrationday", 'int'), GETPOST("date_registrationyear", 'int'), 1);
$date_endfreeperiod = dol_mktime(0, 0, 0, GETPOST("endfreeperiodmonth", 'int'), GETPOST("endfreeperiodday", 'int'), GETPOST("endfreeperiodyear", 'int'), 1);
if (empty($date_endfreeperiod) && ! empty($date_registration)) {
	$date_endfreeperiod=$date_registration+15*24*3600;
}

$emailtocreate=GETPOST('emailtocreate') ? GETPOST('emailtocreate') : '';
$instancetocreate=GETPOST('instancetocreate', 'alpha');

$error = 0; $errors = array();


// Security check
$user->rights->sellyoursaas->delete = $user->hasRight('sellyoursaas', 'write');
$result = restrictedArea($user, 'sellyoursaas', 0, '', '');

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array array
include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
$hookmanager=new HookManager($db);

$object=new Societe($db);

if (GETPOST('loadthirdparty')) {
	$action='create2';
}
if (GETPOST('add')) {
	$action='add';
}

// Security check
$result = restrictedArea($user, 'sellyoursaas', 0, '', '');

// Set serverprice with the param from $conf of the $dbmaster server.
$serverprice = !getDolGlobalString('SELLYOURSAAS_INFRA_COST') ? '100' : $conf->global->SELLYOURSAAS_INFRA_COST;


/*
 *	Actions
 */

$parameters=array('id'=>$id);
$reshook=$hookmanager->executeHooks('doActions', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Cancel
	if (GETPOST('cancel', 'alpha') && ! empty($backtopage)) {
		header("Location: ".$backtopage);
		exit;
	}
}


/*
 *	View
 */

$help_url='';
llxHeader('', $langs->trans("SellYourSaasInstance"), $help_url);

$form = new Form($db);
$formother = new FormOther($db);
$formcompany = new FormCompany($db);


print '<form mode="POST" action="'.$_SERVER["PHP_SELF"].'">';

print_fiche_titre($langs->trans("NewInstance"));

print '<br>';
print $langs->trans("ToCreateNewInstanceUseRegisterPageOrTheCustomerDashboard");
print '<br><br>';

print '</form>';

llxFooter();

$db->close();
