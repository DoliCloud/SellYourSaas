<?php
/* Copyright (C) 2007-2022 Laurent Destailleur  <eldy@users.sourceforge.net>
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

if ($mode == 'refreshstats') {
	ini_set('max_execution_time', '300'); //300 seconds = 5 minutes
}

// Set serverprice with the param from $conf of the $dbmaster server.
$serverprice = empty($conf->global->SELLYOURSAAS_INFRA_COST) ? '100' : $conf->global->SELLYOURSAAS_INFRA_COST;


/*
 *	Actions
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


/*
 *	View
 */

$form=new Form($db);

llxHeader('', $langs->transnoentitiesnoconv('DoliCloudCustomers'), '');

//print_fiche_titre($langs->trans("DoliCloudArea"));

$head = sellYourSaasBackofficePrepareHead();

//$head = commande_prepare_head(null);
dol_fiche_head($head, 'home', $langs->trans("DoliCloudArea"), -1, 'sellyoursaas@sellyoursaas');


$tmparray=dol_getdate(dol_now());
$endyear=$tmparray['year'];
$endmonth=$tmparray['mon'];
$datelastday=dol_get_last_day($endyear, $endmonth, 1);
$datefirstday=dol_get_first_day($endyear, $endmonth, 1);
$nbyears = (GETPOSTISSET('nbyears') ? ((int) GETPOST('nbyears', 'int')) : getDolGlobalInt('SELLYOURSAAS_NB_YEARS', 2));
$startyear=$endyear - $nbyears;


$total=0;
$totalcommissions=0;
$totalnewinstances=0;
$totallostinstances=0;
$totalusers=0;
$totalinstances=0;
$totalinstancespaying=0;
$totalresellers=0;
$newinstances=0;
$lostinstances=0;

$suppliercateg = getDolGlobalInt('SELLYOURSAAS_DEFAULT_RESELLER_CATEG');

$sql = 'SELECT COUNT(*) as nb FROM '.MAIN_DB_PREFIX.'societe as s, '.MAIN_DB_PREFIX.'categorie_fournisseur as c';
$sql.= ' WHERE c.fk_soc = s.rowid AND s.status = 1 AND c.fk_categorie = '.((int) $suppliercateg);
$resql = $db->query($sql);
if ($resql) {
	$obj = $db->fetch_object($resql);
	if ($obj) {
		$totalresellers = $obj->nb;
	}
}

if ($mode == 'refreshstats') {
	$rep = sellyoursaas_calculate_stats($db, null, $datefirstday);

	$total=$rep['total'];
	$totalcommissions=$rep['totalcommissions'];
	$totalnewinstances=$rep['totalnewinstances'];
	$totallostinstances=$rep['totallostinstances'];
	$totalinstancespaying=$rep['totalinstancespaying'];
	$totalinstancespayingall=$rep['totalinstancespayingall'];
	$totalinstancessuspendedfree=$rep['totalinstancessuspendedfree'];
	$totalinstancessuspendedpaying=$rep['totalinstancessuspendedpaying'];
	$totalinstancesexpiredfree=$rep['totalinstancesexpiredfree'];
	$totalinstancesexpiredpaying=$rep['totalinstancesexpiredpaying'];
	$totalinstances=$rep['totalinstances'];
	$totalusers=$rep['totalusers'];
	$newinstances=count($rep['listofnewinstances']);
	$lostinstances=count($rep['listoflostinstances']);

	$_SESSION['stats_total']=$total;
	$_SESSION['stats_totalcommissions']=$totalcommissions;
	$_SESSION['stats_totalnewinstances']=$totalnewinstances;
	$_SESSION['stats_totallostinstances']=$totallostinstances;
	$_SESSION['stats_totalinstancespaying']=$totalinstancespaying;
	$_SESSION['stats_totalinstancespayingall']=$totalinstancespayingall;
	$_SESSION['stats_totalinstancessuspendedfree']=$totalinstancessuspendedfree;
	$_SESSION['stats_totalinstancesexpiredfree']=$totalinstancesexpiredfree;
	$_SESSION['stats_totalinstancessuspendedpaying']=$totalinstancessuspendedpaying;
	$_SESSION['stats_totalinstancesexpiredpaying']=$totalinstancesexpiredpaying;
	$_SESSION['stats_totalinstances']=$totalinstances;
	$_SESSION['stats_totalusers']=$totalusers;
	$_SESSION['stats_newinstances']=$newinstances;
	$_SESSION['stats_lostinstances']=$lostinstances;
} else {
	$total = isset($_SESSION['stats_total']) ? $_SESSION['stats_total'] : '';
	$totalcommissions = isset($_SESSION['stats_totalcommissions']) ? $_SESSION['stats_totalcommissions'] : '';
	$totalnewinstances = isset($_SESSION['stats_totalnewinstances']) ? $_SESSION['stats_totalnewinstances'] : '';
	$totallostinstances = isset($_SESSION['stats_totallostinstances']) ? $_SESSION['stats_totallostinstances'] : '';
	$totalinstancespaying = isset($_SESSION['stats_totalinstancespaying']) ? $_SESSION['stats_totalinstancespaying'] : '';
	$totalinstancespayingall = isset($_SESSION['stats_totalinstancespayingall']) ? $_SESSION['stats_totalinstancespayingall'] : '';
	$totalinstancessuspendedfree = isset($_SESSION['stats_totalinstancessuspendedfree']) ? $_SESSION['stats_totalinstancessuspendedfree'] : '';
	$totalinstancesexpiredfree = isset($_SESSION['stats_totalinstancesexpiredfree']) ? $_SESSION['stats_totalinstancesexpiredfree'] : '';
	$totalinstancessuspendedpaying = isset($_SESSION['stats_totalinstancessuspendedpaying']) ? $_SESSION['stats_totalinstancessuspendedpaying'] : '';
	$totalinstancesexpiredpaying = isset($_SESSION['stats_totalinstancesexpiredpaying']) ? $_SESSION['stats_totalinstancesexpiredpaying'] : '';
	$totalinstances = isset($_SESSION['stats_totalinstances']) ? $_SESSION['stats_totalinstances'] : '';
	$totalusers = isset($_SESSION['stats_totalusers']) ? $_SESSION['stats_totalusers'] : '';
	$newinstances = isset($_SESSION['stats_newinstances']) ? $_SESSION['stats_newinstances'] : 0;
	$lostinstances = isset($_SESSION['stats_lostinstances']) ? $_SESSION['stats_lostinstances'] : 0;
}

$total = price2num($total, 'MT');
$totalcommissions = price2num($totalcommissions, 'MT');
$part = (float) getDolGlobalString('SELLYOURSAAS_PERCENTAGE_FEE', '0');
$benefit=price2num(($total * (1 - $part) - $serverprice - $totalcommissions), 'MT');


print '<div class="fichecenter"><div class="fichethirdleft">';


/*
 * Announce
 */

$param = '';

print '<form method="post" action="'.dol_buildpath('/sellyoursaas/backoffice/index.php', 1).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="noborder nohover centpercent">';
print '<tr class="liste_titre">';
print '<td>';
print $langs->trans('Website').' & '.$langs->trans('CustomerAccountArea');
print '</td></tr>';
print '<tr class="oddeven"><td>';
$enabledisablehtml='';
if (getDolGlobalString('SELLYOURSAAS_DISABLE_NEW_INSTANCES')) {
	// Button off, click to enable
	$enabledisablehtml .= '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setSELLYOURSAAS_DISABLE_NEW_INSTANCES&token='.newToken().'&value=0'.$param.'">';
	$enabledisablehtml .= img_picto($langs->trans("Disabled"), 'switch_off', '', false, 0, 0, '', 'error valignmiddle paddingright');
	$enabledisablehtml .= '</a>';
} else {
	// Button on, click to disable
	$enabledisablehtml .= '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setSELLYOURSAAS_DISABLE_NEW_INSTANCES&token='.newToken().'&value=1'.$param.'">';
	$enabledisablehtml .= img_picto($langs->trans("Activated"), 'switch_on', '', false, 0, 0, '', 'valignmiddle paddingright');
	$enabledisablehtml .= '</a>';
}
print $enabledisablehtml;
print $langs->trans("EnableNewInstance");

if (getDolGlobalString('SELLYOURSAAS_DISABLE_NEW_INSTANCES')) {
	if (getDolGlobalString('SELLYOURSAAS_DISABLE_NEW_INSTANCES_EXCEPT_IP')) {
		print '<br><span class="opacitymedium">'.$langs->trans("AllowedToIP").': '.getDolGlobalString('SELLYOURSAAS_DISABLE_NEW_INSTANCES_EXCEPT_IP').'</span>';
	}
	if (getDolGlobalString('SELLYOURSAAS_DISABLE_NEW_INSTANCES_MESSAGE')) {
		print '<br><span class="opacitymedium">'.getDolGlobalString('SELLYOURSAAS_DISABLE_NEW_INSTANCES_MESSAGE').'</span>';
	}
}

print '</td></tr>';
print '<tr class="oddeven"><td>';
$enabledisableannounce='';
if (!getDolGlobalString('SELLYOURSAAS_ANNOUNCE_ON')) {
	// Button off, click to enable
	$enabledisableannounce.='<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setSELLYOURSAAS_ANNOUNCE_ON&token='.newToken().'&value=1'.$param.'">';
	$enabledisableannounce.=img_picto($langs->trans("Disabled"), 'switch_off', '', false, 0, 0, '', 'valignmiddle paddingright');
	$enabledisableannounce.='</a>';
} else {
	// Button on, click to disable
	$enabledisableannounce.='<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setSELLYOURSAAS_ANNOUNCE_ON&token='.newToken().'&value=0'.$param.'">';
	$enabledisableannounce.=img_picto($langs->trans('MessageOn'), 'switch_on', '', false, 0, 0, '', 'warning valignmiddle paddingright');
	$enabledisableannounce.='</a>';
}
print $enabledisableannounce;
print $form->textwithpicto($langs->trans("AnnounceOnCustomerDashboard"), $langs->trans("Example").':<br>(AnnounceMajorOutage)<br>(AnnounceMinorOutage)<br>(AnnounceMaintenanceInProgress)<br>Any custom text...</span>', 1, 'help', '', 1, 3, 'tooltipfortext');
print '<br>';
print '<textarea class="flat inputsearch  inline-block" type="text" name="SELLYOURSAAS_ANNOUNCE" rows="'.ROWS_5.'"'.(empty($conf->global->SELLYOURSAAS_ANNOUNCE_ON) ? ' disabled="disabled"' : '').'>';
print getDolGlobalString('SELLYOURSAAS_ANNOUNCE');
print '</textarea>';
print '<div class="center valigntop inline-block"><input type="submit" name="saveannounce" class="button smallpaddingimp" value="'.$langs->trans("Save").'"></div>';
print '</td></tr>';
print "</table></form><br>";

$listofipwithinstances=array();
$sql="SELECT DISTINCT deployment_host FROM ".MAIN_DB_PREFIX."contrat_extrafields WHERE deployment_host IS NOT NULL AND deployment_status IN ('done', 'processing')";
$resql=$db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$listofipwithinstances[] = $obj->deployment_host;
	}
	$db->free($resql);
} else {
	dol_print_error($db);
}

print "\n";



// Show variation
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="wordwrap wordbreak"><span class="valignmiddle">'.$langs->trans("VariationOfCurrentMonth").'</span>';
print '</td>';
print '<td class="right">';
print '<a href="'.$_SERVER["PHP_SELF"].'?mode=refreshstats">'.img_picto('', 'refresh', '', false, 0, 0, '', 'valignmiddle').'</a>';
print '</td>';
print '</tr>';
print '<tr class="oddeven"><td class="wordwrap wordbreak">';
print $form->textwithpicto($langs->trans("NewInstances"), $langs->trans("NonRedirectContractWithInvoiceRecCreateDuringMonth"));
print '</td><td align="right">';
print '<font size="+2">'.$newinstances.' | <span class="amount">'.price($totalnewinstances, 1, $langs, 1, -1, 'MT', $conf->currency).'</span><span class="opacitymedium">'.($newinstances ? ' | '.price(price2num($totalnewinstances/$newinstances, 'MT'), 1, $langs, 1, -1, -1, $conf->currency).' / '.$langs->trans("instances") : '').'</span></font>';
print '</td></tr>';
print '<tr class="oddeven"><td class="wordwrap wordbreak">';
print $langs->trans("LostInstances");
print '</td><td align="right">';
print '<font size="+2">'.$lostinstances.' | <span class="amount">'.price($totallostinstances, 1, $langs, 1, -1, 'MT', $conf->currency).'</span><span class="opacitymedium">'.($lostinstances ? ' | '.price(price2num($totallostinstances/$lostinstances, 'MT'), 1, $langs, 1, -1, -1, $conf->currency).' / '.$langs->trans("instances") : '').'</span></font>';
print '</td></tr>';
print '</table>';



print '</div><div class="fichetwothirdright"><div class="ficheaddleft">';


// Show totals
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="wordwrap wordbreak"><span class="valignmiddle">'.$langs->trans("Statistics").'</span>';
print '</td>';
print '<td class="right">';
print '<a href="'.$_SERVER["PHP_SELF"].'?mode=refreshstats">'.img_picto('', 'refresh', '', false, 0, 0, '', 'valignmiddle').'</a>';
print '</td>';
print '</tr>';
print '<tr class="oddeven"><td class="wordwrap wordbreak">';
print $langs->trans("NbOfResellers");
print '</td><td align="right">';
print '<font size="+2">'.$totalresellers.'</font>';
print '</td></tr>';
print '<tr class="oddeven"><td class="wordwrap wordbreak">';
$texthelp = $langs->trans("NbOfInstancesActivePayingDesc");
$stringlistofinstancespayingwithoutrecinvoice = '';
$nboflistofinstancespayingwithoutrecinvoice = 0;
if (!empty($rep) && is_array($rep['listofinstancespayingwithoutrecinvoice'])) {
	$nboflistofinstancespayingwithoutrecinvoice = count($rep['listofinstancespayingwithoutrecinvoice']);
	$rep['listofinstancespayingwithoutrecinvoice'] = dol_sort_array($rep['listofinstancespayingwithoutrecinvoice'], 'thirdparty_name');
	foreach ($rep['listofinstancespayingwithoutrecinvoice'] as $arrayofcontract) {
		$stringlistofinstancespayingwithoutrecinvoice .= ($stringlistofinstancespayingwithoutrecinvoice ? ', ' : '').$arrayofcontract['thirdparty_name'].' - '.$arrayofcontract['contract_ref']."\n";
	}
}
print $form->textwithpicto($langs->trans("NbOfInstancesActivePaying"), $texthelp);
$texthelp = $langs->trans("NbOfInstancesActivePayingWithoutRecInvoice", $nboflistofinstancespayingwithoutrecinvoice);
if ($stringlistofinstancespayingwithoutrecinvoice) {
	$texthelp.=' ('.$stringlistofinstancespayingwithoutrecinvoice.')';
}
print ' | '.$form->textwithpicto($langs->trans("NbOfInstancesActivePayingAll"), $texthelp).' | '.$langs->trans("NbOfActiveInstances").' ';
print '</td><td align="right">';
if (! empty($_SESSION['stats_totalusers'])) {
	print '<font size="+2">'.$totalinstancespaying.' | '.$totalinstancespayingall.' | '.$totalinstances.'</font>';
} else {
	print '<span class="opacitymedium">'.$langs->trans("ClickToRefresh").'</span>';
}
print '<!-- List of instances : '."\n";
if (!empty($rep) && is_array($rep['listofinstancespaying'])) {
	$rep['listofinstancespaying'] = dol_sort_array($rep['listofinstancespaying'], 'thirdparty_name');
	foreach ($rep['listofinstancespaying'] as $arrayofcontract) {
		print $arrayofcontract['thirdparty_name'].' - '.$arrayofcontract['contract_ref']."\n";
	}
}
print "\n".'-->';
print '</td></tr>';
print '<tr class="oddeven"><td class="wordwrap wordbreak">';
print $langs->trans("NbOfSuspendedInstances").' '.$langs->trans("payed").' | '.$langs->trans("test");
print '</td><td align="right">';
if (! empty($_SESSION['stats_totalusers'])) {
	print '<font size="+2">'.$totalinstancessuspendedpaying.' | '.$totalinstancessuspendedfree.'</font>';
} else {
	print '<span class="opacitymedium">'.$langs->trans("ClickToRefresh").'</span>';
}
print '</td></tr>';
print '<tr class="oddeven"><td>';
print $langs->trans("NbOfUsers").' ';
print '</td><td align="right" class="wordwrap wordbreak">';
if (! empty($_SESSION['stats_totalusers'])) {
	print '<font size="+2">'.$totalusers.'</font>';
} else {
	print '<span class="opacitymedium">'.$langs->trans("ClickToRefresh").'</span>';
}
print '</td></tr>';
print '<tr class="oddeven"><td class="wordwrap wordbreak">';
print $langs->trans("AverageRevenuePerInstance");
print '</td><td align="right">';
if (! empty($_SESSION['stats_totalusers'])) {
	if ($totalinstancespayingall) {
		print '<font size="+2"><span class="amount">'.($totalinstancespaying ? price(price2num($total/$totalinstancespayingall, 'MT'), 1, $langs, 1, -1, -1, $conf->currency) : '0').'</span></font>';
	}
} else {
	print '<span class="opacitymedium">'.$langs->trans("ClickToRefresh").'</span>';
}
print '</td></tr>';
print '<tr class="oddeven"><td class="wordwrap wordbreak">';
print $langs->trans("RevenuePerMonth").' ('.$langs->trans("HT").')';
print '</td><td align="right">';
if (! empty($_SESSION['stats_totalusers'])) {
	print '<font size="+2"><span class="amount">'.price($total, 1, $langs, 1, -1, -1, $conf->currency).'</span></font>';
} else {
	print '<span class="opacitymedium">'.$langs->trans("ClickToRefresh").'</span>';
}
print '</td></tr>';
print '<tr class="oddeven"><td class="wordwrap wordbreak">';
print $langs->trans("CommissionPerMonth").' ('.$langs->trans("HT").')';
print '</td><td align="right">';
print '<font size="+2"><span class="amount">'.price($totalcommissions, 1, $langs, 1, -1, -1, $conf->currency).'</span></font>';
print '</td></tr>';
print '<tr class="oddeven"><td class="wordwrap wordbreak">';
print $langs->trans("ChargePerMonth").' ('.$langs->trans("HT").')';
print '</td><td align="right">';
print '<font size="+2"><span class="amount">'.price($serverprice, 1, $langs, 1, -1, -1, $conf->currency).'</span></font>';
print '</td></tr>';
print '<tr class="liste_total"><td class="wrapimp wordwrap wordbreak">';
print $langs->trans("BenefitDoliCloud");
print '<br>(';
print price($total, 1).' - '.($part ? ($part*100).'% - ' : '').price($serverprice, 1).' - '.price($totalcommissions, 1).' = '.price($total * (1 - $part), 1).' - '.price($serverprice, 1).' - '.price($totalcommissions, 1);
print ')</td><td align="right">';
if (! empty($_SESSION['stats_totalusers'])) {
	print '<font size="+2">'.price($benefit, 1, $langs, 1, -1, -1, $conf->currency).' </font>';
} else {
	print '<span class="opacitymedium">'.$langs->trans("ClickToRefresh").'</span>';
}
print '</td></tr>';
print '</table>';


print '</div></div></div>';

//$servicetouse='old';
$servicetouse=strtolower($conf->global->SELLYOURSAAS_NAME);
$regs = array();

// array(array(0=>'labelxA',1=>yA1,...,n=>yAn), array('labelxB',yB1,...yBn))
$data1 = array();
$sql ='SELECT name, x, y FROM '.MAIN_DB_PREFIX.'sellyoursaas_stats';
$sql.=" WHERE service = '".$db->escape($servicetouse)."' AND name IN ('total', 'totalcommissions')";
$sql.=" ORDER BY x, name";
$resql=$db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	$i=0;

	$oldx='';
	$absice=array();
	while ($i < $num) {
		$obj=$db->fetch_object($resql);
		if ($obj->x < $startyear."01") {
			$i++;
			continue;
		}
		if ($obj->x > $endyear."12") {
			$i++;
			continue;
		}

		if ($oldx && $oldx != $obj->x) {
			// break
			preg_match('/^([0-9]{4})+([0-9]{2})+$/', $oldx, $regs);
			$absice[0]=$regs[1].'-'.$regs[2]; // to show yyyy-mm (international format)
			$benefit=price2num($absice[1] * (1 - $part) - $serverprice - $absice[2], 'MT');
			$absice[3]=$benefit;
			ksort($absice);
			$data1[]=$absice;
			$absice=array();
		}

		$oldx=$obj->x;

		if ($obj->name == 'total') {
			$absice[1]=$obj->y;
		}
		if ($obj->name == 'totalcommissions') {
			$absice[2]=$obj->y;
		}

		$i++;
	}

	if ($oldx) {
		preg_match('/^([0-9]{4})+([0-9]{2})+$/', $oldx, $regs);
		$absice[0]=$regs[1].'-'.$regs[2]; // to show yyyy-mm (international format)
		$benefit=price2num($absice[1] * (1 - $part) - $serverprice - $absice[2], 'MT');
		$absice[3]=$benefit;
		ksort($absice);
		$data1[]=$absice;
	}
} else {
	dol_print_error($db);
}

$data2 = array();
$sql ='SELECT name, x, y FROM '.MAIN_DB_PREFIX.'sellyoursaas_stats';
$sql.=" WHERE service = '".$db->escape($servicetouse)."' AND name IN ('totalinstancespaying', 'totalinstancespayingall', 'totalinstances', 'totalusers')";
$sql.=" ORDER BY x, name";
$resql=$db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	$i=0;

	$oldx='';
	$absice=array();
	while ($i < $num) {
		$obj=$db->fetch_object($resql);

		if ($obj->x < $startyear."01") {
			$i++;
			continue;
		}
		if ($obj->x > $endyear."12") {
			$i++;
			continue;
		}

		if ($oldx && $oldx != $obj->x) {
			// break
			preg_match('/^([0-9]{4})+([0-9]{2})+$/', $oldx, $regs);
			$absice[0]=$regs[1].'-'.$regs[2]; // to show yyyy-mm (international format)
			ksort($absice);
			$data2[]=$absice;
			$absice=array();
		}

		$oldx=$obj->x;

		if ($obj->name == 'totalinstancespaying') {
			$absice[1]=$obj->y;
		}
		if ($obj->name == 'totalinstancespayingall') {
			$absice[2]=$obj->y;
		}
		if ($obj->name == 'totalinstances') {
			$absice[3]=$obj->y;
		}
		if ($obj->name == 'totalusers') {
			$absice[4]=$obj->y;
		}

		$i++;
	}

	if ($oldx) {
		preg_match('/^([0-9]{4})+([0-9]{2})+$/', $oldx, $regs);
		$absice[0]=$regs[1].'-'.$regs[2]; // to show yyyy-mm (international format)
		ksort($absice);
		$data2[]=$absice;
	}
} else {
	dol_print_error($db);
}

// array(array(0=>'labelxA',1=>yA1,...,n=>yAn), array('labelxB',yB1,...yBn))
$data3 = array();
$sql ='SELECT name, x, y FROM '.MAIN_DB_PREFIX.'sellyoursaas_stats';
$sql.=" WHERE service = '".$db->escape($servicetouse)."' AND name IN ('total', 'totalinstancespayingall')";
$sql.=" ORDER BY x, name";
$resql=$db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	$i=0;

	$oldx='';
	$absice=array();
	while ($i < $num) {
		$obj=$db->fetch_object($resql);
		if ($obj->x < $startyear."01") {
			$i++;
			continue;
		}
		if ($obj->x > $endyear."12") {
			$i++;
			continue;
		}

		if ($oldx && $oldx != $obj->x) {
			// break
			preg_match('/^([0-9]{4})+([0-9]{2})+$/', $oldx, $regs);
			$absice[0]=$regs[1].'-'.$regs[2]; // to show yyyy-mm (international format)
			$averagebasket=(empty($absice[2]) ? 0 : price2num($absice[1] / $absice[2], 'MT'));
			$absice[1]=$averagebasket;
			unset($absice[2]);
			ksort($absice);
			$data3[]=$absice;
			$absice=array();
		}

		$oldx=$obj->x;

		if ($obj->name == 'total') {
			$absice[1]=$obj->y;
		}
		if ($obj->name == 'totalinstancespayingall') {
			$absice[2]=$obj->y;
		}

		$i++;
	}

	if ($oldx) {
		preg_match('/^([0-9]{4})+([0-9]{2})+$/', $oldx, $regs);
		$absice[0]=$regs[1].'-'.$regs[2]; // to show yyyy-mm (international format)
		$averagebasket=(empty($absice[2]) ? 0 : price2num($absice[1] / $absice[2], 'MT'));
		$absice[1]=$averagebasket;
		unset($absice[2]);
		ksort($absice);
		$data3[]=$absice;
		$absice=array();
	}
} else {
	dol_print_error($db);
}

// array(array(0=>'labelxA',1=>yA1,...,n=>yAn), array('labelxB',yB1,...yBn))
$data4 = array();
$sql ='SELECT name, x, y FROM '.MAIN_DB_PREFIX.'sellyoursaas_stats';
$sql.=" WHERE service = '".$db->escape($servicetouse)."' AND name IN ('totalnewinstances', 'totallostinstances', 'newinstances', 'lostinstances')";
$sql.=" ORDER BY x, name";
$resql=$db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	$i=0;

	$oldx='';
	$absice=array();
	while ($i < $num) {
		$obj=$db->fetch_object($resql);
		if ($obj->x < $startyear."01") {
			$i++;
			continue;
		}
		if ($obj->x > $endyear."12") {
			$i++;
			continue;
		}

		if ($oldx && $oldx != $obj->x) {
			// break
			preg_match('/^([0-9]{4})+([0-9]{2})+$/', $oldx, $regs);
			$absice[0]=$regs[1].'-'.$regs[2]; // to show yyyy-mm (international format)
			ksort($absice);
			$data4[]=$absice;
			$absice=array();
		}

		$oldx=$obj->x;

		if ($obj->name == 'newinstances') {
			$absice[1]=$obj->y;
		}
		if ($obj->name == 'lostinstances') {
			$absice[2]=$obj->y;
		}
		if ($obj->name == 'totalnewinstances') {
			$absice[3]=$obj->y;
		}
		if ($obj->name == 'totallostinstances') {
			$absice[4]=$obj->y;
		}

		$i++;
	}

	if ($oldx) {
		preg_match('/^([0-9]{4})+([0-9]{2})+$/', $oldx, $regs);
		$absice[0]=$regs[1].'-'.$regs[2]; // to show yyyy-mm (international format)
		ksort($absice);
		$data4[]=$absice;
	}
} else {
	dol_print_error($db);
}



if (empty($conf->dol_optimize_smallscreen)) {
	$WIDTH=600;
	$HEIGHT=260;
} else {
	$WIDTH=DolGraph::getDefaultGraphSizeForStats('width');
	$HEIGHT=DolGraph::getDefaultGraphSizeForStats('height');
}

$fileurlnb = '';

// Set graph
$px1 = new DolGraph();
$mesg = $px1->isGraphKo();
if (! $mesg) {
	$px1->SetData($data1);
	$data1 = null;

	$legend=array();
	$legend[0]=$langs->trans("RevenuePerMonth").' ('.$langs->trans("HT").')';
	$legend[1]=$langs->trans("CommissionPerMonth").' ('.$langs->trans("HT").')';
	$legend[2]=$langs->trans("BenefitDoliCloud");
	if (!empty($conf->dol_optimize_smallscreen)) {
		foreach ($legend as $key => $value) {
			$legend[$key] = dol_trunc($legend[$key], 20);
		}
	}

	$px1->SetLegend($legend);
	$px1->setShowPointValue(2);
	$px1->SetMaxValue($px1->GetCeilMaxValue());
	$px1->SetWidth($WIDTH);
	$px1->SetHeight($HEIGHT);
	$px1->SetYLabel($langs->trans("Nb"));
	$px1->SetShading(3);
	$px1->SetHorizTickIncrement(1);
	$px1->SetCssPrefix("cssboxes");
	$px1->SetType(array('lines','lines','lines'));
	$px1->mode='depth';
	$px1->SetTitle($langs->trans("Profits"));

	$px1->draw('dolicloudamount.png', $fileurlnb);
}

$px2 = new DolGraph();
$mesg = $px2->isGraphKo();
if (! $mesg) {
	$px2->SetData($data2);
	$data2 = null;

	$legend=array();
	$legend[0]=$langs->trans("NbOfInstancesActivePaying");
	$legend[1]=$langs->trans("NbOfInstancesActivePayingAll");
	$legend[2]=$langs->trans("NbOfActiveInstances");
	$legend[3]=$langs->trans("NbOfUsers");
	if (!empty($conf->dol_optimize_smallscreen)) {
		foreach ($legend as $key => $value) {
			$legend[$key] = dol_trunc($legend[$key], 20);
		}
	}

	$px2->SetLegend($legend);
	$px2->setShowPointValue(2);
	$px2->SetMaxValue($px2->GetCeilMaxValue());
	$px2->SetWidth($WIDTH);
	$px2->SetHeight($HEIGHT);
	$px2->SetYLabel($langs->trans("Nb"));
	$px2->SetShading(3);
	$px2->SetHorizTickIncrement(1);
	$px2->SetCssPrefix("cssboxes");
	$px2->SetType(array('lines','lines','lines','lines'));
	$px2->mode='depth';
	$px2->SetTitle($langs->trans("Instances").'/'.$langs->trans("Users"));

	$px2->draw('dolicloudcustomersusers.png', $fileurlnb);
}

$px3 = new DolGraph();
$mesg = $px3->isGraphKo();
if (! $mesg) {
	$px3->SetData($data3);
	$data3 = null;

	$legend=array();
	$legend[0]=$langs->trans("AverageRevenuePerInstance").' ('.$langs->trans("HT").')';

	$px3->SetLegend($legend);
	$px3->setShowPointValue(2);
	$px3->SetMaxValue($px3->GetCeilMaxValue());
	$px3->SetWidth($WIDTH);
	$px3->SetHeight($HEIGHT);
	$px3->SetYLabel($langs->trans("Amount"));
	$px3->SetShading(3);
	$px3->SetHorizTickIncrement(1);
	$px3->SetCssPrefix("cssboxes");
	$px3->SetType(array('lines'));
	$px3->mode='depth';
	$px3->SetTitle($langs->trans("AverageRevenuePerInstance"));

	$px3->draw('dolicloudaveragebasket.png', $fileurlnb);
}

$px4 = new DolGraph();
$mesg = $px4->isGraphKo();
if (! $mesg) {
	$px4->SetData($data4);
	$data4 = null;
	$legend=array();
	$legend[0]=$langs->trans("NbNewInstances");
	$legend[1]=$langs->trans("NbLostInstances");
	$legend[2]=$langs->trans("AmountNewInstances").' ('.$langs->trans("HT").')';
	$legend[3]=$langs->trans("AmountLostInstances").' ('.$langs->trans("HT").')';

	$px4->SetLegend($legend);
	$px4->setShowPointValue(2);
	$px4->SetMaxValue($px4->GetCeilMaxValue());
	$px4->SetWidth($WIDTH);
	$px4->SetHeight($HEIGHT);
	$px4->SetYLabel($langs->trans("Amount"));
	$px4->SetShading(3);
	$px4->SetHorizTickIncrement(1);
	$px4->SetCssPrefix("cssboxes");
	$px4->SetType(array('lines'));
	$px4->mode='depth';
	$px4->SetTitle($langs->trans("NewAndLostInstances"));

	$px4->draw('dolicloudnewlostinstances.png', $fileurlnb);
}

print '<div class="fichecenter"><br></div>';

//print '<hr>';
//print '<div class="fichecenter liste_titre" style="height: 20px;">'.$langs->trans("Graphics").'</div>';

print '<div class="fichecenter center"><center>';
print '<div class="inline-block nohover">';
print $px2->show();
print '</div>';
print '<div class="inline-block nohover">';
print $px4->show();
print '</div>';
print '<div class="inline-block nohover">';
print $px1->show();
print '</div>';
print '<div class="inline-block nohover">';
print $px3->show();
print '</div>';
print '</center></div>';


dol_fiche_end();


// End of page
llxFooter();

$db->close();
