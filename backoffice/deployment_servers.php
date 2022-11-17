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
 *   	\file       htdocs/sellyoursaas/backoffice/deployment_servers.php
 *		\ingroup    sellyoursaas
 *		\brief      Home page of DoliCloud service
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
if (empty($page) || $page == -1) { $page = 0; }     // If $page is not defined, or '' or -1
$offset = $limit * $page;
if (! $sortorder) $sortorder='ASC';
if (! $sortfield) $sortfield='t.date_registration';
$limit = GETPOST('limit', 'int')?GETPOST('limit', 'int'):$conf->liste_limit;

$pageprev = $page - 1;
$pagenext = $page + 1;

// Security check
$result = restrictedArea($user, 'sellyoursaas', 0, '', '');

$keyforaction	= GETPOST('key', 'alpha');
$value	= GETPOST('value', 'alpha');
if (!GETPOST('cancel', 'alpha')) {
	$domainnamenew = GETPOST("domainname", "alpha");
	$ipaddressnew = GETPOST("ipaddress", "alpha");
	$statusnew = GETPOST("openedclosedinstance", "alpha");
}

// Set serverprice with the param from $conf of the $dbmaster server.
$serverprice = empty($conf->global->SELLYOURSAAS_INFRA_COST)?'100':$conf->global->SELLYOURSAAS_INFRA_COST;
$error = 0;

if (GETPOST('addinstance', 'alpha')) {
	$action = 'addinstance';
}
if (GETPOST('saveannounce', 'alpha')) {
	$action = 'setSELLYOURSAAS_ANNOUNCE';
}
if (GETPOST('editinstance', 'alpha')) {
	$action = 'editinstance';
}

/*
 *	Actions
 */

if ($action == 'setSELLYOURSAAS_DISABLE_INSTANCE') {
	$listofdomains = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
	// move the x part in domain:x into domain:closed:x
	$tmpdomainkey = explode(':', $listofdomains[$keyforaction]);
	if ($value == 0) {
		if (!empty($tmpdomainkey[1])) {
			$tmpdomainkey[2]=$tmpdomainkey[1];
		}
		$tmpdomainkey[1] = 'closed';
		$listofdomains[$keyforaction] = implode(':', $tmpdomainkey);
		$listofdomains = implode(',', $listofdomains);
		dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_NAMES", $listofdomains, 'chaine', 0, '', $conf->entity);
	} elseif ($value == 1) {
		// move the domain:closed:x into domain:x
		if (!empty($tmpdomainkey[2])) {
			$tmpdomainkey[1]=$tmpdomainkey[2];
			unset($tmpdomainkey[2]);
		} else {
			unset($tmpdomainkey[1]);
		}
		$listofdomains[$keyforaction] = implode(':', $tmpdomainkey);
		$listofdomains = implode(',', $listofdomains);
		dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_NAMES", $listofdomains, 'chaine', 0, '', $conf->entity);
	}
}

// Enable the annouce for the server $keyforaction
if ($action == 'setSELLYOURSAAS_ANNOUNCE_ON') {
	$keyforparam = 'SELLYOURSAAS_ANNOUNCE_ON_'.$keyforaction;
	if ($value) {
		dolibarr_set_const($db, $keyforparam, 1, 'chaine', 0, '', $conf->entity);
	} else {
		dolibarr_del_const($db, $keyforparam, $conf->entity);
	}
}

// Set text for announce
if ($action == 'setSELLYOURSAAS_ANNOUNCE') {
	foreach ($_POST as $key => $val) {	// Loop on each entry to search the o
		$reg = array();
		if (preg_match('/^saveannounce_(.*)$/', $key, $reg)) {
			$keyforparam = 'SELLYOURSAAS_ANNOUNCE_'.str_replace('_', '.', $reg[1]);
			$value = GETPOST('value_'.$reg[1]);
			if ($value) {
				dolibarr_del_const($db, $keyforparam, $conf->entity);	// Delete old text
				dolibarr_set_const($db, $keyforparam, $value, 'chaine', 0, '', $conf->entity);
			} else {
				dolibarr_del_const($db, $keyforparam, $conf->entity);
			}
		}
	}
}

if ($action == 'addinstance') {
	if ($user->hasRight('sellyoursaas', 'write')) {
		if (empty($domainnamenew)) {
			setEventMessages($langs->transnoentities("ErrorFieldRequired", $langs->trans("Domain")), null, 'errors');
			$error++;
		}
		if (empty($ipaddressnew)) {
			setEventMessages($langs->transnoentities("ErrorFieldRequired", $langs->trans("IP")), null, 'errors');
			$error++;
		}
		if ($statusnew == -1) {
			setEventMessages($langs->transnoentities("ErrorFieldRequired", $langs->trans("Closed").'|'.$langs->trans("Opened")), null, 'errors');
			$error++;
		}

		if (!$error) {
			$listofdomains = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
			$listofips = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_IP);
			$tmpdomainkey = array();
			$res = 0;

			if ($statusnew == 0) {
				$tmpdomainkey[0] = $domainnamenew;
				$tmpdomainkey[1] = 'closed';
				$listofdomains[] = implode(':', $tmpdomainkey);
				$listofdomains = implode(',', $listofdomains);
				dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_NAMES", $listofdomains, 'chaine', 0, '', $conf->entity);
			} elseif ($statusnew == 1) {
				$tmpdomainkey[0] = $domainnamenew;
				$listofdomains[] = implode(':', $tmpdomainkey);
				$listofdomains = implode(',', $listofdomains);
				dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_NAMES", $listofdomains, 'chaine', 0, '', $conf->entity);
			} else {
				setEventMessages($langs->transnoentities("ErrorInvalidValue", $langs->trans("Closed").'|'.$langs->trans("Opened")), null, 'errors');
				$error++;
			}

			if (!$error) {
				$listofips[] = $ipaddressnew;
				$listofips = implode(',', $listofips);
				dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_IP", $listofips, 'chaine', 0, '', $conf->entity);
				unset($domainnamenew);
				unset($ipaddressnew);
				unset($statusnew);
			}
		}
	} else {
		setEventMessages($langs->trans("NotEnoughPermissions"), null, 'errors');
	}
}

if ($action == 'editinstance') {
	if ($user->hasRight('sellyoursaas', 'write')) {
		if (empty($domainnamenew)) {
			setEventMessages($langs->transnoentities("ErrorFieldRequired", $langs->trans("Domain")), null, 'errors');
			$error++;
		}
		if (empty($ipaddressnew)) {
			setEventMessages($langs->transnoentities("ErrorFieldRequired", $langs->trans("IP")), null, 'errors');
			$error++;
		}
		if ($statusnew == -1) {
			setEventMessages($langs->transnoentities("ErrorFieldRequired", $langs->trans("Closed").'|'.$langs->trans("Opened")), null, 'errors');
			$error++;
		}

		if (!$error) {
			$listofdomains = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
			$tmpdomainkey = explode(':', $listofdomains[$keyforaction]);
			$tmpdomainnew = explode(':', $domainnamenew);
			$listofips = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_IP);
			$res = 0;

			if ($statusnew == 0) {
				$tmpdomainkey[0] = $tmpdomainnew[0];
				$tmpdomainkey[1] = 'closed';
				if (!empty($tmpdomainnew[1])) {
					$tmpdomainkey[2] = $tmpdomainnew[1];
				} else {
					unset($tmpdomainkey[2]);
				}
				$listofdomains[$keyforaction] = implode(':', $tmpdomainkey);
				$listofdomains = implode(',', $listofdomains);
				dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_NAMES", $listofdomains, 'chaine', 0, '', $conf->entity);
			} elseif ($statusnew == 1) {
				$tmpdomainkey[0] = $tmpdomainnew[0];
				if (!empty($tmpdomainnew[1])) {
					$tmpdomainkey[1] = $tmpdomainnew[1];
				} else {
					unset($tmpdomainkey[1]);
				}
				if (!empty($tmpdomainkey[2])) {
					unset($tmpdomainkey[2]);
				}
				$listofdomains[$keyforaction] = implode(':', $tmpdomainkey);
				$listofdomains = implode(',', $listofdomains);
				dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_NAMES", $listofdomains, 'chaine', 0, '', $conf->entity);
			} else {
				setEventMessages($langs->transnoentities("ErrorInvalidValue", $langs->trans("Closed").'|'.$langs->trans("Opened")), null, 'errors');
				$error++;
			}

			if (!$error) {
				$listofips[$keyforaction] = $ipaddressnew;
				$listofips = implode(',', $listofips);
				dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_IP", $listofips, 'chaine', 0, '', $conf->entity);
				unset($domainnamenew);
				unset($ipaddressnew);
				unset($statusnew);
			}
		}
	} else {
		setEventMessages($langs->trans("NotEnoughPermissions"), null, 'errors');
	}
}

if ($action == 'confirm_delete') {
	if ($user->hasRight("sellyoursaas", "delete")) {
		$listofdomains = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
		$listofips = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_IP);
		unset($listofdomains[$keyforaction]);
		unset($listofips[$keyforaction]);
		$listofdomains = implode(',', $listofdomains);
		dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_NAMES", $listofdomains, 'chaine', 0, '', $conf->entity);
		$listofips = implode(',', $listofips);
		dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_IP", $listofips, 'chaine', 0, '', $conf->entity);
	} else {
		setEventMessages($langs->trans("NotEnoughPermissions"), null, 'errors');
	}
	$action = '';
}
/*
 *	View
 */

$form=new Form($db);

llxHeader('', $langs->transnoentitiesnoconv('DoliCloudCustomers'), '');

//print_fiche_titre($langs->trans("DoliCloudArea"));

$head = sellYourSaasBackofficePrepareHead();


//$head = commande_prepare_head(null);
dol_fiche_head($head, 'deploymentservers', $langs->trans("DoliCloudArea"), -1, 'sellyoursaas@sellyoursaas');


$tmparray=dol_getdate(dol_now());
$endyear=$tmparray['year'];
$endmonth=$tmparray['mon'];
$datelastday=dol_get_last_day($endyear, $endmonth, 1);
$startyear=$endyear-2;


if (empty($conf->global->SELLYOURSAAS_SUB_DOMAIN_IP)) {
	$langs->load("errors");
	print $langs->trans("ErrorModuleSetupNotComplete", "SellYourSaas");
} else {
	$formconfirm = '';
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?key='.$keyforaction, $langs->trans('DeleteDeploymentServerFromList'), $langs->trans('ConfirmDeleteDeploymentServerFromList'), 'confirm_delete', '', 0, 1);
	}
	print $formconfirm;

	print '<div class="fichecenter">';

	// List of deployment servers

	$param = '';
	$listofipwithinstances=array();
	$sql="SELECT DISTINCT deployment_host FROM ".MAIN_DB_PREFIX."contrat_extrafields WHERE deployment_host IS NOT NULL AND deployment_status IN ('done', 'processing')";
	$resql=$db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$listofipwithinstances[]=$obj->deployment_host;
		}
		$db->free($resql);
	} else dol_print_error($db);

	print "\n";
	print "<!-- section of deployment servers -->\n";
	print '<div class="div-table-responsive-no-min">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
	print '<table class="noborder nohover centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('DeploymentServers').'</td></tr>';
	print '<tr class="oddeven nohover">';
	print '<td>'.$langs->trans('SellYourSaasSubDomainsIPDeployed').': <strong>'.join(', ', $listofipwithinstances).'</strong></td>';
	print '</tr>';

	print '</td>';
	print '</tr>';
	print "</table>";
	print '</div>';

	$listofips = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_IP);
	$listofdomains = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
	$helptooltip = 'SELLYOURSAAS_SUB_DOMAIN_NAMES = '.join(', ', $listofdomains).'<br><br>SELLYOURSAAS_SUB_DOMAIN_IP = '.join(', ', $listofips);

	print_fiche_titre($form->textwithpicto($langs->trans('SellYourSaasSubDomainsIP'), $helptooltip)).'<br>';

	print '<div class="div-table-responsive-no-min">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<table class="noborder nohover centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Domain").'</td>';
	print '<td>'.$langs->trans("IP").'</td>';
	print '<td class="center">';
	$helptooltip = img_warning('', '').' '.$langs->trans("EvenIfDomainIsOpenTo");
	print $form->textwithpicto($langs->trans("Closed").'|'.$langs->trans("Opened"), $helptooltip);
	print '</td>';
	// Nb of instances
	print '<td class="center">';
	print $form->textwithpicto($langs->trans("Instances"), $langs->trans("NbOfOpenInstances"));
	print '</td>';
	print '<td></td>';
	print '<td></td>';
	print '<td colspan="2">';
	print $form->textwithpicto($langs->trans("AnnounceOnCustomerDashboard"), $langs->trans("Example").':<br>(AnnounceMajorOutage)<br>(AnnounceMinorOutage)<br>(AnnounceMaintenanceInProgress)<br>Any custom text...</span>', 1, 'help', '', 1, 3, 'tooltipfortext');
	print '</td>';
	if ($user->hasRight('sellyoursaas', 'write') || $user->hasRight('sellyoursaas', 'delete')) {
		print '<td></td>';
	}
	print '</tr>';

	// Get nb of open instances per ip
	$openinstances = array();
	$sql = "SELECT ce.deployment_host, COUNT(rowid) as nb FROM ".$db->prefix()."contrat_extrafields as ce";
	$sql .= " WHERE deployment_status in ('processing', 'done')";
	$sql .= " GROUP BY ce.deployment_host";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$openinstances[$obj->deployment_host] = (int) $obj->nb;
		}
	} else {
		dol_print_error($db);
	}

	// Row to add deployment server
	if ($user->hasRight("sellyoursaas", "write")) {
		print '<tr>';
		// Domain
		print '<td>';
		$inputdomainname = '<input type="text" name="domainname" id="domainnameadd" class="widthcentpercentminusx" value="'.($action != "edit" ? $domainnamenew : '').'" '.($action == "edit" ? 'disabled' : '').'>';
		print $form->textwithpicto($inputdomainname, $langs->trans("SellYourSaasSubDomainsDeployPageHelp"));
		print '</td>';

		// IP
		print '<td>';
		print '<input class="maxwidth100" type="text" name="ipaddress" id="ipaddressadd" value="'.($action != "edit" ? $ipaddressnew : '').'" '.($action == "edit" ? 'disabled' : '').'>';
		print '</td>';

		// Open / Closed
		print '<td class="center">';
		print '<select name="openedclosedinstance" id="openedclosedinstancenew" '.($action == "edit" ? 'disabled' : '').'>';
		print '<option value="-1"></option>';
		print '<option '.($statusnew == "0" && $action != "edit" ?'selected=""' : '').' value="0">'.$langs->trans('Closed').'</option>';
		print '<option '.($statusnew == "1" && $action != "edit" ?'selected=""' : '').' value="1">'.$langs->trans('Opened').'</option>';
		print '</select>';
		print '</td>';
		print ajax_combobox("openedclosedinstancenew");

		print '<td></td>';
		print '<td></td>';
		print '<td></td>';
		print '<td></td>';

		print '<td colspan="2" class="right">';
		print '<input type="submit" name="addinstance" class="button smallpaddingimp" value="'.$langs->trans("Add").'" '.($action == "edit" ? 'disabled' : '').'>';
		print '</td>';
		print '</tr>';
	}

	// Loop on each deployment server
	foreach ($listofips as $key => $val) {
		$tmparraydomain = explode(':', $listofdomains[$key]);
		print '<tr class="oddeven">';
		if ($action == "edit" && $key == $keyforaction) {
			print '<input type="hidden" name="key" value="'.$key.'">';
			// Domain
			if (in_array($tmparraydomain[1], array('bidon', 'hidden', 'closed'))) {
				print '<td>';
				$inputdomainname = '<input type="text" name="domainname" id="domainnameadd" value="'.$tmparraydomain[0].(!empty($tmparraydomain[2]) ? ':'.$tmparraydomain[2] : '').'">';
				print $form->textwithpicto($inputdomainname, $langs->trans("SellYourSaasSubDomainsDeployPageHelp"));
				print '</td>';
			} else {
				print '<td>';
				$inputdomainname = '<input type="text" name="domainname" id="domainnameadd" value="'.$listofdomains[$key].'">';
				print $form->textwithpicto($inputdomainname, $langs->trans("SellYourSaasSubDomainsDeployPageHelp"));
				print '</td>';
			}

			// IP
			print '<td>';
			print '<input class="maxwidth100" type="text" name="ipaddress" id="ipaddressadd" value="'.$val.'">';
			print '</td>';

			// Open / Closed
			print '<td class="center">';
			print '<select name="openedclosedinstance" id="openedclosedinstanceedit">';
			print '<option value="-1"></option>';
			if (in_array($tmparraydomain[1], array('bidon', 'hidden', 'closed'))) {
				print '<option selected="" value="0">'.$langs->trans('Closed').'</option>';
				print '<option value="1">'.$langs->trans('Opened').'</option>';
			} else {
				print '<option value="0">'.$langs->trans('Closed').'</option>';
				print '<option selected="" value="1">'.$langs->trans('Opened').'</option>';
			}
			print '</select>';
			print '</td>';
			print ajax_combobox("openedclosedinstanceedit");

			print '<td></td>';
			print '<td></td>';
			print '<td></td>';
			print '<td></td>';

			print '<td colspan="2" class="right">';
			print '<input type="submit" name="editinstance" class="button smallpaddingimp" value="'.$langs->trans("Save").'">';
			print '<input type="submit" name="cancel" class="button smallpaddingimp" value="'.$langs->trans("Cancel").'">';
			print '</td>';
		} else {
			// Domain
			print '<td>'.$tmparraydomain[0].'</td>';

			// IP
			print '<td>'.$val.'</td>';
			/*print '<td>';
			if (! empty($tmparraydomain[1])) {
				if (in_array($tmparraydomain[1], array('bidon', 'hidden', 'closed'))) {
					print $langs->trans("Closed");
				} else {
					print img_picto($langs->trans("Open"), 'check', '', false, 0, 0, '', 'paddingright', 0).$langs->trans("OnDomainOnly", $tmparraydomain[1]);
				}
			} else {
				print img_picto($langs->trans("Open"), 'check', '', false, 0, 0, '', 'paddingright', 0).$langs->trans("Open");
			}
			print '</td>';*/

			// Open / Closed
			print '<td class="center">';
			if (! empty($tmparraydomain[1])) {
				if (in_array($tmparraydomain[1], array('bidon', 'hidden', 'closed'))) {
					// Button off, click to enable
					$enabledisablehtml='<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setSELLYOURSAAS_DISABLE_INSTANCE&value=1&key='.urlencode($key).'&token='.newToken().'">';
					$enabledisablehtml.=img_picto($langs->trans("Disabled"), 'switch_off', '', false, 0, 0, '', 'valignmiddle paddingright');
					$enabledisablehtml.='</a>';
				} else {
					// Button on, click to disable
					$enabledisablehtml='<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setSELLYOURSAAS_DISABLE_INSTANCE&value=0&key='.urlencode($key).'&token='.newToken().'">';
					$enabledisablehtml.=img_picto($langs->trans("Activated"), 'switch_on', '', false, 0, 0, '', 'valignmiddle paddingright');
					$enabledisablehtml.='</a><br>';
					$enabledisablehtml.='<span class="small opacitymedium">'.$langs->trans("OnDomainOnly", $tmparraydomain[1]).'</span>';
				}
			} else {
				// Button on, click to disable
				$enabledisablehtml='<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setSELLYOURSAAS_DISABLE_INSTANCE&value=0&key='.urlencode($key).'&token='.newToken().'">';
				$enabledisablehtml.=img_picto($langs->trans("Activated"), 'switch_on', '', false, 0, 0, '', 'valignmiddle paddingright');
				$enabledisablehtml.='</a>';
			}
			print $enabledisablehtml;
			print '</td>';

			print '<td class="center">';
			if (empty($openinstances[$val])) {
				print '0';
			} else {
				print dol_escape_htmltag($openinstances[$val]);
			}
			print '</td>';

			// Commands
			print '<td class="small">';
			$commandstartstop = 'sudo '.$conf->global->DOLICLOUD_SCRIPTS_PATH.'/remote_server_launcher.sh start|status|stop';
			print $form->textwithpicto($langs->trans("StartStopAgent"), $langs->trans("CommandToManageRemoteDeploymentAgent").':<br><br>'.$commandstartstop, 1, 'help', '', 0, 3, 'startstop'.$key).'<br>';
			print '</td>';
			print '<td class="small">';
			$commandstartstop = 'sudo '.$conf->global->DOLICLOUD_SCRIPTS_PATH.'/make_instances_offline.sh '.$conf->global->SELLYOURSAAS_ACCOUNT_URL.'/offline.php test|offline|online';
			print $form->textwithpicto($langs->trans("OnlineOffline"), $langs->trans("CommandToPutInstancesOnOffline").':<br><br>'.$commandstartstop, 1, 'help', '', 0, 3, 'onoff'.$key).'<br>';
			print '</td>';

			// Announce
			print '<td>';
			$keyforparam = 'SELLYOURSAAS_ANNOUNCE_ON_'.$tmparraydomain[0];
			if (empty($conf->global->$keyforparam)) {
				// Button off, click to enable
				$enabledisablehtml='<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setSELLYOURSAAS_ANNOUNCE_ON&value=1&key='.urlencode($tmparraydomain[0]).'&token='.newToken().'">';
				$enabledisablehtml.=img_picto($langs->trans("Disabled"), 'switch_off', '', false, 0, 0, '', 'valignmiddle paddingright');
				$enabledisablehtml.='</a>';
			} else {
				// Button on, click to disable
				$enabledisablehtml='<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setSELLYOURSAAS_ANNOUNCE_ON&value=0&key='.urlencode($tmparraydomain[0]).'&token='.newToken().'">';
				$enabledisablehtml.=img_picto($langs->trans("Activated"), 'switch_on', '', false, 0, 0, '', 'warning valignmiddle paddingright');
				$enabledisablehtml.='</a> ';
			}
			print $enabledisablehtml;
			print '</td>';

			print '<td class="nowraponall">';
			$keyforparam2 = 'SELLYOURSAAS_ANNOUNCE_'.$tmparraydomain[0];
			print '<input type="hidden" name="action" value="setSELLYOURSAAS_ANNOUNCE">';
			print '<input type="hidden" name="key" value="'.$tmparraydomain[0].'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<textarea class="flat valignmiddle inputsearch inline-block maxwidth200" type="text" name="value_'.$tmparraydomain[0].'" rows="'.ROWS_2.'">';
			print $conf->global->$keyforparam2;
			print '</textarea>';
			print '<div class="center valignmiddle inline-block">';
			print '<input type="submit" id="saveannounce_'.$tmparraydomain[0].'" name="saveannounce_'.$tmparraydomain[0].'" class="button smallpaddingimp" value="'.$langs->trans("Save").'">';
			print '</div>';
			print '</td>';
			if ($user->hasRight('sellyoursaas', 'write') || $user->hasRight('sellyoursaas', 'delete')) {
				// code...
				print '<td class="minwidth50">';
				if ($user->hasRight('sellyoursaas', 'write')) {
					print '<a href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'&key='.$key.'">'.img_picto($langs->trans("Edit"), 'edit', 'class="marginleftonly"').'</a>';
				}
				if ($user->hasRight('sellyoursaas', 'delete')) {
					print '<a href="'.$_SERVER["PHP_SELF"].'?action=delete&token='.newToken().'&key='.$key.'">'.img_delete('', 'class="marginleftonly valigntextbottom"').'</a>';
				}
				print '</td>';
			}
		}

		print '</tr>';
		/*
		 print '<tr class="oddeven"><td>';
		 print $langs->trans("CommandToManageRemoteDeploymentAgent").'<br>';
		 print '<textarea class="flat inputsearch centpercent" type="text" name="SELLYOURSAAS_ANNOUNCE">';
		 print 'sudo '.$conf->global->DOLICLOUD_SCRIPTS_PATH.'/remote_server_launcher.sh start|status|stop';
		 print '</textarea>';
		 print '</td></tr>';
		 print '<tr class="oddeven"><td>';
		 print $langs->trans("CommandToPutInstancesOnOffline").'<br>';
		 print '<textarea class="flat inputsearch centpercent" type="text" name="SELLYOURSAAS_ANNOUNCE">';
		 print 'sudo '.$conf->global->DOLICLOUD_SCRIPTS_PATH.'/make_instances_offline.sh '.$conf->global->SELLYOURSAAS_ACCOUNT_URL.'/offline.php test|offline|online';
		 print '</textarea>';
		 print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=makeoffline&token='.newToken().'">'.$langs->trans("PutAllInstancesOffLine").'</a>';
		 print ' &nbsp; - &nbsp; ';
		 print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=makeonline&token='.newToken().'">'.$langs->trans("PutAllInstancesOnLine").'</a>';
		 print '</td></tr>';
		 */
	}
	print '</table>';
	print '</form>';

	print '</div>';

	print "</div>";
}

dol_fiche_end();


// End of page
llxFooter();

$db->close();
