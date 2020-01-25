<?php
/* Copyright (C) 2007-2018 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *   	\file       htdocs/sellyoursaas/backoffice/customereports.php
 *		\ingroup    sellyoursaas
 *		\brief      Page to make custom reports
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include($_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php");
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include(substr($tmp, 0, ($i+1))."/main.inc.php");
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php");
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");

require_once(DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/dolgraph.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/doleditor.class.php");
require_once(DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php");

// Load traductions files requiredby by page
$langs->loadLangs(array("companies","other","sellyoursaas@sellyoursaas"));

// Get parameters
$mode		= GETPOST('mode','alpha') ? GETPOST('mode','alpha') : 'graph';

$search_filters = GETPOST('search_filters', 'array');
$search_measures = GETPOST('search_measures', 'array');
$search_xaxis = GETPOST('search_xaxis', 'array');
$search_yaxis = GETPOST('search_yaxis', 'array');

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
if (empty($page) || $page == -1) { $page = 0; }     // If $page is not defined, or '' or -1
$offset = $conf->liste_limit * $page;
if (! $sortorder) $sortorder='ASC';
if (! $sortfield) $sortfield='t.date_registration';
$limit = GETPOST('limit','int')?GETPOST('limit','int'):$conf->liste_limit;

$pageprev = $page - 1;
$pagenext = $page + 1;

// Protection if external user
if ($user->societe_id > 0)
{
	//accessforbidden();
}

$object = new Contrat($db);



/*
 * Actions
 */





/*
 * View
 */

$form=new Form($db);

llxHeader('',$langs->transnoentitiesnoconv('CustomReports'),'');

//print_fiche_titre($langs->trans("DoliCloudArea"));


$h = 0;
$head = array();

$head[$h][0] = 'index.php';
$head[$h][1] = $langs->trans("Home");
$head[$h][2] = 'home';
$h++;

$head[$h][0] = 'customreports.php';
$head[$h][1] = $langs->trans("CustomReports");
$head[$h][2] = 'customreports';
$h++;


//$head = commande_prepare_head(null);
dol_fiche_head($head, 'customreports', $langs->trans("DoliCloudArea"), -1, 'sellyoursaas@sellyoursaas');


$tmparray=dol_getdate(dol_now());
$endyear=$tmparray['year'];
$endmonth=$tmparray['mon'];
$datelastday=dol_get_last_day($endyear, $endmonth, 1);
$startyear=$endyear-2;

$param = '';

$arrayofmesures = array('count'=>'Count');
$arrayofxaxis = array();
$arrayofyaxis = array();

print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';

print '<div class="liste_titre liste_titre_bydiv centpercent">';
// Add Filter
print '<div class="divsearchfield">';
$arrayofstatus = array(Contrat::STATUS_DRAFT=>'Draft', Contrat::STATUS_VALIDATED=>'Validated', Contrat::STATUS_CLOSED=>'Closed');
print $langs->trans("Status").' '.$form->multiselectarray('search_status', $arrayofstatus, $search_status, 0, 0, 'minwidth100', 1);
print '</div>';
print '<div class="divsearchfield clearboth">';
foreach($object->fields as $key => $val) {
    if ($val['measure']) $arrayofmesures[$key] = $val['label'];
}
print $langs->trans("Measures").' '.$form->multiselectarray('search_measures', $arrayofmesures, $search_measures, 0, 0, 'minwidth100', 1);
print '</div>';
print '<div class="divsearchfield">';
foreach($object->fields as $key => $val) {
    if (! $val['measure']) {
        if (in_array($key, array('id', 'rowid', 'last_main_doc', 'extraparams'))) continue;
        if (in_array($val['type'], array('html', 'text'))) continue;
        $arrayofxaxis[$key] = $val['label'];
    }
}
print $langs->trans("XAxis").' '.$form->multiselectarray('search_xaxis', $arrayofxaxis, $search_xaxis, 0, 0, 'minwidth100', 1);
print '</div>';
if ($mode == 'grid') {
    print '<div class="divsearchfield">';
    foreach($object->fields as $key => $val) {
        if (! $val['measure']) $arrayofyaxis[$key] = $val['label'];
    }
    print $langs->trans("YAxis").' '.$form->multiselectarray('search_yaxis', $arrayofyaxis, $search_yaxis, 0, 0, 'minwidth100', 1);
    print '</div>';
}
print '<div class="divsearchfield">';
print '<input type="submit" class="button" value="'.$langs->trans("Refresh").'">';
print '</div>';
print '</div>';
print '</form>';

// Generate the SQL request
$sql = 'SELECT ';
foreach($search_xaxis as $key => $val) {
    $sql .= $val.' as x_'.$key.', ';
}
foreach($search_measures as $key => $val) {
    if ($key == 'count') $sql .= 'COUNT(rowid) as y_'.$key.', ';
    else $sql .= 'SUM(1 + '.$key.') as y_'.$key.', ';
}
$sql = preg_replace('/,\s*$/', '', $sql);
$sql .= ' FROM '.MAIN_DB_PREFIX.$object->table_element;
$sql .= ' WHERE 1 = 1';
$sql .= ' AND entity IN ('.getEntity($object->element).')';
foreach($search_filters as $key => $val) {

}
$sql .= ' GROUP BY ';
foreach($search_xaxis as $key => $val) {
    $sql .= $val.', ';
}
$sql = preg_replace('/,\s*$/', '', $sql);
$sql .= ' ORDER BY ';
foreach($search_xaxis as $key => $val) {
    $sql .= $val.', ';
}
$sql = preg_replace('/,\s*$/', '', $sql);

$legend=array();
foreach($search_measures as $key => $val) {
    $legend[]=$langs->trans($val);
}


// Execute the SQL request
$resql = $db->query($sql);
if (! $resql) {
    dol_print_error($db);
}

$data = array();
while($obj = $db->fetch_object($resql)) {
    // $this->data  = array(array(0=>'labelxA',1=>yA1,...,n=>yAn), array('labelxB',yB1,...yBn));   // or when there is n series to show for each x
    foreach($search_xaxis as $xkey => $xval) {
        $fieldforxkey = 'x_'.$xkey;
        $xarray = array(0 => $obj->$fieldforxkey);
        foreach($search_measures as $key => $val) {
            $fieldfory = 'y_'.$key;
            $xarray[] = $obj->$fieldfory;
        }
        $data[] = $xarray;
    }
}

// Show admin info
print info_admin($sql);


if ($mode == 'grid') {


}

if ($mode == 'graph') {
    $WIDTH = '80%';
    $HEIGHT = 200;

    // Show graph
    $px1 = new DolGraph();
    $mesg = $px1->isGraphKo();
    if (! $mesg)
    {
    	$px1->SetData($data);
    	unset($data);

    	$px1->SetLegend($legend);
    	$px1->SetMaxValue($px1->GetCeilMaxValue());
    	$px1->SetWidth($WIDTH);
    	$px1->SetHeight($HEIGHT);
    	$px1->SetYLabel($langs->trans("Y"));
    	$px1->SetShading(3);
    	$px1->SetHorizTickIncrement(1);
    	$px1->SetCssPrefix("cssboxes");
    	$px1->SetType(array('lines','lines','lines'));
    	$px1->mode='depth';
    	$px1->SetTitle('');

    	$dir=$conf->user->dir_temp;
    	dol_mkdir($dir);
    	$filenamenb = $dir.'/customreport_'.$object->element.'.png';
    	$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=user&file=customreport_'.$object->element.'.png';

    	$px1->draw($filenamenb, $fileurlnb);

    	print $px1->show();
    }
}


dol_fiche_end();


// End of page
llxFooter();

$db->close();
