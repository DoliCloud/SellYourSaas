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
$sortfield = GETPOST("sortfield", 'alpha');
$sortorder = GETPOST("sortorder", 'alpha');
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
if ($action == 'setSELLYOURSAAS_DISABLE_INSTANCE') {
	$listofdomains = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
	$tmpdomainkey = explode(':',$listofdomains[$keytodesactivate]);
	if ($value == 0) {
		if (!empty($tmpdomainkey[1])) {
			$tmpdomainkey[2]=$tmpdomainkey[1];
		}
		$tmpdomainkey[1] = 'closed';
		$listofdomains[$keytodesactivate] = implode(':',$tmpdomainkey);
		$listofdomains = implode(',',$listofdomains);
		dolibarr_set_const($db,"SELLYOURSAAS_SUB_DOMAIN_NAMES",$listofdomains, 'chaine', 0, '', $conf->entity);
	}
	else if ($value == 1) {
		if (!empty($tmpdomainkey[2])) {
			$tmpdomainkey[1]=$tmpdomainkey[2];
			unset($tmpdomainkey[2]);
		}else{
			unset($tmpdomainkey[1]);
		}
		$listofdomains[$keytodesactivate] = implode(':',$tmpdomainkey);
		$listofdomains = implode(',',$listofdomains);
		dolibarr_set_const($db,"SELLYOURSAAS_SUB_DOMAIN_NAMES",$listofdomains, 'chaine', 0, '', $conf->entity);
	}
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
	print '<tr class="">';
	print '<td>';
	$helptooltip = "SELLYOURSAAS_SUB_DOMAIN_IP = ".$conf->global->SELLYOURSAAS_SUB_DOMAIN_IP.'<br><br>SELLYOURSAAS_SUB_DOMAIN_NAMES = '.$conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES;
	print $form->textwithpicto($langs->trans('SellYourSaasSubDomainsIP'), $helptooltip).':<br>';
	print '<table class="noborder">';
	print '<tr class="liste_titre_bidon"><td>'.$langs->trans("IP").'</td><td>'.$langs->trans("Domain").'</td><td>';
	$helptooltip = img_warning('', '').' '.$langs->trans("EvenIfDomainIsOpenTo");
	print $form->textwithpicto($langs->trans("Registration"), $helptooltip);
	print '<td class="center">'.$langs->trans("Closed").'|'.$langs->trans("Open").'</td>';
	print '</td><td></td><td></td></tr>';
	$listofips = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_IP);
	$listofdomains = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
	foreach ($listofips as $key => $val) {
		$tmparraydomain = explode(':', $listofdomains[$key]);
		print '<tr class="oddeven"><td>'.$val.'</td><td>'.$tmparraydomain[0].'</td><td>';
		if (! empty($tmparraydomain[1])) {
			if (in_array($tmparraydomain[1], array('bidon', 'hidden', 'closed'))) {
				print $langs->trans("Closed");
			} else {
				print img_picto($langs->trans("Open"), 'check', '', false, 0, 0, '', 'paddingright', 0).$langs->trans("OnDomainOnly", $tmparraydomain[1]);
			}
		} else {
			print img_picto($langs->trans("Open"), 'check', '', false, 0, 0, '', 'paddingright', 0).$langs->trans("Open");
		}
		print '</td>';
		print '<td class="center">';
		if (in_array($tmparraydomain[1], array('bidon', 'hidden', 'closed'))){
			// Button off, click to enable
			$enabledisablehtml='<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setSELLYOURSAAS_DISABLE_INSTANCE&value=1&key='.$key.'">';
			$enabledisablehtml.=img_picto($langs->trans("Disabled"), 'switch_off', '', false, 0, 0, '', 'error valignmiddle paddingright');
			$enabledisablehtml.='</a>';
		} else {
			// Button on, click to disable
			$enabledisablehtml='<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setSELLYOURSAAS_DISABLE_INSTANCE&value=0&key='.$key.'">';
			$enabledisablehtml.=img_picto($langs->trans("Activated"), 'switch_on', '', false, 0, 0, '', 'valignmiddle paddingright');
			$enabledisablehtml.='</a>';
		}
		print $enabledisablehtml;
		print '</td>';
		print '<td>';
		$commandstartstop = 'sudo '.$conf->global->DOLICLOUD_SCRIPTS_PATH.'/remote_server_launcher.sh start|status|stop';
		print $form->textwithpicto($langs->trans("StartStopAgent"), $langs->trans("CommandToManageRemoteDeploymentAgent").':<br><br>'.$commandstartstop, 1, 'help', '', 0, 3, 'startstop'.$key).'<br>';
		print '</td>';
		print '<td>';
		$commandstartstop = 'sudo '.$conf->global->DOLICLOUD_SCRIPTS_PATH.'/make_instances_offline.sh '.$conf->global->SELLYOURSAAS_ACCOUNT_URL.'/offline.php test|offline|online';
		print $form->textwithpicto($langs->trans("OnlineOffline"), $langs->trans("CommandToPutInstancesOnOffline").':<br><br>'.$commandstartstop, 1, 'help', '', 0, 3, 'onoff'.$key).'<br>';
		print '</td>';
		print '</tr>';
	}
	print '</table>';
	print '</td>';
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
	print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=makeoffline">'.$langs->trans("PutAllInstancesOffLine").'</a>';
	print ' &nbsp; - &nbsp; ';
	print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=makeonline">'.$langs->trans("PutAllInstancesOnLine").'</a>';
	print '</td></tr>';
	*/
	print "</table>";
	print '</div>';

	print "</div>";
}

dol_fiche_end();


// End of page
llxFooter();

$db->close();
