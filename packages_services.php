<?php
/* Copyright (C) 2017 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *  \file       packages_services.php
 *  \ingroup    sellyoursaas
 *  \brief      Page of Packages services
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
if (! $res && file_exists("../main.inc.php")) {
	$res=@include "../main.inc.php";
}
if (! $res && file_exists("../../main.inc.php")) {
	$res=@include "../../main.inc.php";
}
if (! $res && file_exists("../../../main.inc.php")) {
	$res=@include "../../../main.inc.php";
}
if (! $res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
dol_include_once('/sellyoursaas/class/packages.class.php');
dol_include_once('/sellyoursaas/lib/packages.lib.php');


// Load traductions files requiredby by page
$langs->loadLangs(array("sellyoursaas@sellyoursaas","other"));

// Get parameters
$id			= GETPOST('id', 'int');
$ref        = GETPOST('ref', 'alpha');
$action		= GETPOST('action', 'alpha');
$cancel     = GETPOST('cancel', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

if (GETPOST('actioncode', 'array')) {
	$actioncode=GETPOST('actioncode', 'array', 3);
	if (! count($actioncode)) {
		$actioncode='0';
	}
} else {
	$actioncode=GETPOST("actioncode", "alpha", 3) ? GETPOST("actioncode", "alpha", 3) : (GETPOST("actioncode")=='0' ? '0' : (empty($conf->global->AGENDA_DEFAULT_FILTER_TYPE_FOR_OBJECT) ? '' : $conf->global->AGENDA_DEFAULT_FILTER_TYPE_FOR_OBJECT));
}

// Security check - Protection if external user
//if ($user->societe_id > 0) accessforbidden();
//if ($user->societe_id > 0) $socid = $user->societe_id;
//$result = restrictedArea($user, 'sellyoursaas', $id);

$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST("sortfield", 'alpha');
$sortorder = GETPOST("sortorder", 'alpha');
$page = GETPOST("page", 'int');
if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield) {
	$sortfield='a.datep,a.id';
}
if (! $sortorder) {
	$sortorder='DESC';
}

// Initialize technical objects
$object=new Packages($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction=$conf->sellyoursaas->dir_output . '/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('packagesagenda'));     // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label('packages');

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';  // Must be include, not include_once  // Must be include, not include_once. Include fetch and fetch_thirdparty but not fetch_optionals
if ($id > 0 || ! empty($ref)) {
	$upload_dir = $conf->sellyoursaas->multidir_output[$object->entity] . "/" . $object->id;
}

$permissiontoread = $user->hasRight('sellyoursaas', 'read');
$permissiontoadd = $user->hasRight('sellyoursaas', 'write');
$permissiontodelete = $user->hasRight('sellyoursaas', 'delete') || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_DRAFT);
$permissionnote = $user->hasRight('sellyoursaas', 'write');


/*
 *	Actions
 */

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Cancel
	if ($cancel && ! empty($backtopage)) {
		header("Location: ".$backtopage);
		exit;
	}

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		$actioncode='';
	}
}



/*
 *	View
 */

$form = new Form($db);

if ($object->id > 0) {
	$title=$langs->trans("Services");
	//if (! empty($conf->global->MAIN_HTML_TITLE) && preg_match('/thirdpartynameonly/',$conf->global->MAIN_HTML_TITLE) && $object->name) $title=$object->name." - ".$title;
	$help_url = '';
	llxHeader('', $title, $help_url);

	if (! empty($conf->notification->enabled)) {
		$langs->load("mails");
	}
	$head = packagesPrepareHead($object);


	dol_fiche_head($head, 'services', $langs->trans("Packages"), -1, 'label');

	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="' .dol_buildpath('/sellyoursaas/packages_list.php', 1) . '?restore_lastsearch_values=1' . (! empty($socid) ? '&socid=' . $socid : '') . '">' . $langs->trans("BackToList") . '</a>';

	$morehtmlref='<div class="refidno">';
	/*
	 // Ref customer
	 $morehtmlref.=$form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', 0, 1);
	 $morehtmlref.=$form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', null, null, '', 1);
	 // Thirdparty
	 $morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1);
	 // Project
	 if (! empty($conf->project->enabled))
	 {
	 $langs->load("projects");
	 $morehtmlref.='<br>'.$langs->trans('Project') . ' ';
	 if ($user->rights->sellyoursaas->creer)
	 {
	 if ($action != 'classify')
		 //$morehtmlref.='<a class="editfielda" href="' . $_SERVER['PHP_SELF'] . '?action=classify&amp;id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetProject')) . '</a> : ';
		 $morehtmlref.=' : ';
		 if ($action == 'classify') {
		 //$morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'projectid', 0, 0, 1, 1);
		 $morehtmlref.='<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		 $morehtmlref.='<input type="hidden" name="action" value="classin">';
		 $morehtmlref.='<input type="hidden" name="token" value="'.newToken().'">';
		 $morehtmlref.=$formproject->select_projects($object->socid, $object->fk_project, 'projectid', $maxlength, 0, 1, 0, 1, 0, 0, '', 1);
		 $morehtmlref.='<input type="submit" class="button valignmiddle" value="'.$langs->trans("Modify").'">';
		 $morehtmlref.='</form>';
		 } else {
		 $morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'none', 0, 0, 0, 1);
		 }
		 } else {
		 if (! empty($object->fk_project)) {
		 $proj = new Project($db);
		 $proj->fetch($object->fk_project);
		 $morehtmlref.='<a href="'.DOL_URL_ROOT.'/projet/card.php?id=' . $object->fk_project . '" title="' . $langs->trans('ShowProject') . '">';
		 $morehtmlref.=$proj->ref;
		 $morehtmlref.='</a>';
		 } else {
		 $morehtmlref.='';
		 }
		 }
		 }*/
	$morehtmlref.='</div>';


	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	$object->info($object->id);
	print dol_print_object_info($object, 1);

	print '</div>';

	dol_fiche_end();



	// Actions buttons

	$out='';
	$permok=$user->rights->service->creer;
	$out.=(! empty($objcon->id) ? '&packageid='.$id : '').'&backtopage=1';

	print '<div class="tabsAction">';

	if (! empty($user->rights->service->creer)) {
		print '<a class="butAction" href="'.DOL_URL_ROOT.'/product/card.php?action=create&type=1'.$out.'">'.$langs->trans("AddService").'</a>';
	} else {
		print '<a class="butActionRefused" href="#">'.$langs->trans("AddService").'</a>';
	}

	print '</div>';


	// List of services
	if (! empty($user->rights->service->lire)) {
		$param='&packageid='.$id;
		if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
			$param.='&contextpage='.$contextpage;
		}
		if ($limit > 0 && $limit != $conf->liste_limit) {
			$param.='&limit='.$limit;
		}


		print load_fiche_titre($langs->trans("ServicesUsingThisPackage"), '', '');

		$tmpproduct = new Product($db);

		$sql ="SELECT p.rowid FROM ".MAIN_DB_PREFIX."product as p, ".MAIN_DB_PREFIX."product_extrafields as pe";
		$sql.=" WHERE p.rowid = pe.fk_object AND p.fk_product_type = 1 AND pe.package = '".$db->escape($object->id)."'";

		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Service").'</td>';
		print '<td class="right">'.$langs->trans("Status").'</td>';
		print '</tr>';

		$resql = $db->query($sql);
		if ($resql) {
			$num_rows = $db->num_rows($resql);
			$i=0;
			while ($i < $num_rows) {
				$obj = $db->fetch_object($resql);
				if ($obj) {
					$tmpproduct->fetch($obj->rowid);

					print '<tr class="oddeven">';
					print '<td>'.$tmpproduct->getNomUrl(1).'</td>';
					print '<td class="right">'.$tmpproduct->getLibStatut(5).'</td>';
					print '</tr>';
				}
				$i++;
			}
		} else {
			dol_print_error($db);
		}

		print '</table>';
		print '</div>';
	}
}


llxFooter();

$db->close();
