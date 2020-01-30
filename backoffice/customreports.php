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

// Load traductions files requiredby by page
$langs->loadLangs(array("companies", "contracts", "bills", "other", "exports", "sellyoursaas@sellyoursaas"));

// Get parameters
$mode		= GETPOST('mode','alpha') ? GETPOST('mode','alpha') : 'graph';

$objecttype = GETPOST('objecttype', 'alpha');
if (empty($objecttype)) $objecttype = 'contract';

$search_filters = GETPOST('search_filters', 'array');
$search_measures = GETPOST('search_measures', 'array');
$search_xaxis = GETPOST('search_xaxis', 'array');
$search_yaxis = GETPOST('search_yaxis', 'array');
$search_graph = GETPOST('search_graph');

// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOST('page', 'int');
if (empty($page) || $page == -1 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha') || (empty($toselect) && $massaction === '0')) { $page = 0; }     // If $page is not defined, or '' or -1 or if we click on clear filters or if we select empty mass action
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Protection if external user
if ($user->societe_id > 0)
{
	//accessforbidden();
}

if ($objecttype == 'contract') {
    require_once(DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php");
    $object = new Contrat($db);
}
elseif ($objecttype == 'invoice') {
    require_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
    $object = new Facture($db);
}
elseif ($objecttype == 'invoice_template') {
    require_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture-rec.class.php");
    $object = new FactureRec($db);
}
else {
    dol_print_error('', 'Bad value for objecttype');
    exit;
}

$extrafields = new ExtraFields($db);

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);
//$extrafields->fetch_name_optionals_label($object->table_element_line);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

$search_component_params=array('');


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

$arrayofmesures = array('t.count'=>'Count');
$arrayofxaxis = array();
$arrayofyaxis = array();

print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';

print '<div class="liste_titre liste_titre_bydiv centpercent">';

// Select object
print '<div class="divsearchfield">';
$arrayoftype = array('contract' => 'Contracts', 'invoice' => 'Invoices', 'invoice_template'=>'PredefinedInvoices');
print '<div class="width150 inline-block">'.$langs->trans("Object").'</div> ';
print $form->selectarray('objecttype', $arrayoftype, $objecttype, 0, 0, 0, '', 1);
print '</div><div class="clearboth"></div>';


// Add Filter
print '<div class="divsearchfield quatrevingtpercent">';
print $form->searchComponent(array($object->element => $object->fields), $search_component_params);
print '</div>';
print '<div class="divsearchfield clearboth">';
foreach($object->fields as $key => $val) {
    if ($val['isameasure']) {
        $arrayofmesures['t.'.$key.'-sum'] = $val['label'].' <span class="opacitymedium">('.$langs->trans("Sum").')</span>';
        $arrayofmesures['t.'.$key.'-average'] = $val['label'].' <span class="opacitymedium">(('.$langs->trans("Average").')</span>';
    }
}
// Add measure from extrafields
if ($object->isextrafieldmanaged) {
    foreach($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
        if (! empty($extrafields->attributes[$object->table_element]['totalizable'][$key])) {
            $arrayofmesures['te.'.$key.'-sum'] = $langs->trans($extrafields->attributes[$object->table_element]['label'][$key]).' <span class="opacitymedium">('.$langs->trans("Sum").')</span>';
            $arrayofmesures['te.'.$key.'-average'] = $langs->trans($extrafields->attributes[$object->table_element]['label'][$key]).' <span class="opacitymedium">('.$langs->trans("Average").')</span>';
        }
    }
}
print '<div class="inline-block opacitymedium"><span class="fas fa-chart-line paddingright" title="Filter"></span>'.$langs->trans("Measures").'</div> ';
print $form->multiselectarray('search_measures', $arrayofmesures, $search_measures, 0, 0, 'minwidth500', 1);
print '</div>';
// Measures
print '<div class="divsearchfield">';
foreach($object->fields as $key => $val) {
    if (! $val['measure']) {
        if (in_array($key, array('id', 'ref_int', 'ref_ext', 'rowid', 'entity', 'last_main_doc', 'extraparams'))) continue;
        if (preg_match('/^fk_/', $key)) continue;
        if (in_array($val['type'], array('html', 'text'))) continue;
        if (in_array($val['type'], array('timestamp', 'date', 'datetime'))) {
            $arrayofxaxis['t.'.$key.'-year'] = array('label' => $langs->trans($val['label']).' ('.$langs->trans("Year").')', 'position' => $val['position']);
            $arrayofxaxis['t.'.$key.'-month'] = array('label' => $langs->trans($val['label']).' ('.$langs->trans("Month").')', 'position' => $val['position']);
            $arrayofxaxis['t.'.$key.'-day'] = array('label' => $langs->trans($val['label']).' ('.$langs->trans("Day").')', 'position' => $val['position']);
        } else {
            $arrayofxaxis['t.'.$key] = array('label' => $val['label'], 'position' => (int) $val['position']);
        }
    }
    // Add measure from extrafields
    if ($object->isextrafieldmanaged) {
        foreach($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
            if (! empty($extrafields->attributes[$object->table_element]['totalizable'][$key])) {
                $arrayofxaxis['te.'.$key] = array('label' => $extrafields->attributes[$object->table_element]['label'][$key], 'position' => (int) $extrafields->attributes[$object->table_element]['pos'][$key]);
            }
        }
    }
}
$arrayofxaxis = dol_sort_array($arrayofxaxis, 'position');
$arrayofxaxislabel = array();
foreach($arrayofxaxis as $key => $val) {
    $arrayofxaxislabel[$key] = $val['label'];
}
print '<div class="inline-block opacitymedium"><span class="fas fa-long-arrow-alt-right paddingright" title="Filter"></span>'.$langs->trans("XAxis").'</div> ';
print $form->multiselectarray('search_xaxis', $arrayofxaxislabel, $search_xaxis, 0, 0, 'minwidth500', 1);
print '</div>';

if ($mode == 'grid') {
    print '<div class="divsearchfield">';
    foreach($object->fields as $key => $val) {
        if (! $val['measure']) {
            if (in_array($key, array('id', 'rowid', 'entity', 'last_main_doc', 'extraparams'))) continue;
            if (preg_match('/^fk_/', $key)) continue;
            if (in_array($val['type'], array('html', 'text'))) continue;
            if (in_array($val['type'], array('timestamp', 'date', 'datetime'))) {
                $arrayofyaxis['t.'.$key.'-year'] = array('label' => $langs->trans($val['label']).' ('.$langs->trans("Year").')', 'position' => $val['position']);
                $arrayofyaxis['t.'.$key.'-month'] = array('label' => $langs->trans($val['label']).' ('.$langs->trans("Month").')', 'position' => $val['position']);
                $arrayofyaxis['t.'.$key.'-day'] = array('label' => $langs->trans($val['label']).' ('.$langs->trans("Day").')', 'position' => $val['position']);
            } else {
                $arrayofyaxis['t.'.$key] = array('label' => $val['label'], 'position' => (int) $val['position']);
            }
        }
        // Add measure from extrafields
        if ($object->isextrafieldmanaged) {
            foreach($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
                if (! empty($extrafields->attributes[$object->table_element]['totalizable'][$key])) {
                    $arrayofyaxis['te.'.$key] = array('label' => $extrafields->attributes[$object->table_element]['label'][$key], 'position' => (int) $extrafields->attributes[$object->table_element]['pos'][$key]);
                }
            }
        }
    }
    $arrayofyaxis = dol_sort_array($arrayofyaxis, 'position');
    $arrayofyaxislabel = array();
    foreach($arrayofyaxis as $key => $val) {
        $arrayofyaxislabel[$key] = $val['label'];
    }
    print '<div class="inline-block opacitymedium"><span class="fas fa-sort-numeric-up-alt paddingright" title="Filter"></span>'.$langs->trans("YAxis").'</div> ';
    print $form->multiselectarray('search_yaxis', $arrayofyaxislabel, $search_yaxis, 0, 0, 'minwidth100', 1);
    print '</div>';
}

if ($mode == 'graph') {
    print '<div class="divsearchfield">';
    $arrayofgraphs = array('line', 'bars'); // also 'pies'
    print '<div class="inline-block opacitymedium"><span class="fas fa-chart-area paddingright" title="Filter"></span>'.$langs->trans("Graph").'</div> ';
    print $form->selectarray('search_graph', $arrayofgraphs, $search_graph, 0, 0, 'minwidth100', 1);
    print '</div>';
}
print '<div class="divsearchfield">';
print '<input type="submit" class="button" value="'.$langs->trans("Refresh").'">';
print '</div>';
print '</div>';
print '</form>';


// Generate the SQL request
$sql = '';
if (! empty($search_measures) && ! empty($search_xaxis))
{
    $fieldid = 'rowid';

    $sql = 'SELECT ';
    foreach($search_xaxis as $key => $val) {
        if (preg_match('/\-year$/', $val)) {
            $tmpval = preg_replace('/\-year$/', '', $val);
            $sql .= 'DATE_FORMAT('.$tmpval.", '%Y') as x_".$key.', ';
        } elseif (preg_match('/\-month$/', $val)) {
            $tmpval = preg_replace('/\-month$/', '', $val);
            $sql .= 'DATE_FORMAT('.$tmpval.", '%Y-%m') as x_".$key.', ';
        } elseif (preg_match('/\-day$/', $val)) {
            $tmpval = preg_replace('/\-day$/', '', $val);
            $sql .= 'DATE_FORMAT('.$tmpval.", '%Y-%m-%f') as x_".$key.', ';
        }
        else $sql .= $val.' as x_'.$key.', ';
    }
    foreach($search_measures as $key => $val) {
        if ($val == 't.count') $sql .= 'COUNT(t.'.$fieldid.') as y_'.$key.', ';
        elseif (preg_match('/\-sum$/', $val)) {
            $tmpval  = preg_replace('/\-sum$/', '', $val);
            $sql .= 'SUM('.$db->ifsql($tmpval.' IS NULL', '0', $tmpval).') as y_'.$key.', ';
        }
        elseif (preg_match('/\-average$/', $val)) {
            $tmpval  = preg_replace('/\-average$/', '', $val);
            $sql .= 'AVG('.$db->ifsql($tmpval.' IS NULL', '0', $tmpval).') as y_'.$key.', ';
        }
    }
    $sql = preg_replace('/,\s*$/', '', $sql);
    $sql .= ' FROM '.MAIN_DB_PREFIX.$object->table_element.' as t';
    // Add measure from extrafields
    if ($object->isextrafieldmanaged) {
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.$object->table_element.'_extrafields as te ON te.fk_object = t.'.$fieldid;
    }
    $sql .= ' WHERE 1 = 1';
    $sql .= ' AND entity IN ('.getEntity($object->element).')';
    foreach($search_filters as $key => $val) {

    }
    $sql .= ' GROUP BY ';
    foreach($search_xaxis as $key => $val) {
        if (preg_match('/\-year$/', $val)) {
            $tmpval = preg_replace('/\-year$/', '', $val);
            $sql .= 'DATE_FORMAT('.$tmpval.", '%Y')";
        } elseif (preg_match('/\-month$/', $val)) {
            $tmpval = preg_replace('/\-month$/', '', $val);
            $sql .= 'DATE_FORMAT('.$tmpval.", '%Y-%m')";
        } elseif (preg_match('/\-day$/', $val)) {
            $tmpval = preg_replace('/\-day$/', '', $val);
            $sql .= 'DATE_FORMAT('.$tmpval.", '%Y-%m-%f')";
        }
        else $sql .= $val.', ';
    }
    $sql = preg_replace('/,\s*$/', '', $sql);
    $sql .= ' ORDER BY ';
    foreach($search_xaxis as $key => $val) {
        if (preg_match('/\-year$/', $val)) {
            $tmpval = preg_replace('/\-year$/', '', $val);
            $sql .= 'DATE_FORMAT('.$tmpval.", '%Y'), ";
        } elseif (preg_match('/\-month$/', $val)) {
            $tmpval = preg_replace('/\-month$/', '', $val);
            $sql .= 'DATE_FORMAT('.$tmpval.", '%Y-%m'), ";
        } elseif (preg_match('/\-day$/', $val)) {
            $tmpval = preg_replace('/\-day$/', '', $val);
            $sql .= 'DATE_FORMAT('.$tmpval.", '%Y-%m-%f'), ";
        }
        else $sql .= $val.', ';
    }
    $sql = preg_replace('/,\s*$/', '', $sql);
}


$legend=array();
foreach($search_measures as $key => $val) {
    $legend[] = $langs->trans($arrayofmesures[$val]);
}

// Execute the SQL request
$totalnbofrecord = 0;
$data = array();
if ($sql) {
    $resql = $db->query($sql);
    if (! $resql) {
        dol_print_error($db);
    }

    while($obj = $db->fetch_object($resql)) {
        // $this->data  = array(array(0=>'labelxA',1=>yA1,...,n=>yAn), array('labelxB',yB1,...yBn));   // or when there is n series to show for each x
        foreach($search_xaxis as $xkey => $xval) {
            $fieldforxkey = 'x_'.$xkey;
            $xlabel = $obj->$fieldforxkey;
            $xvalwithoutprefix = preg_replace('/^[a-z]+\./', '', $xval);
            if (! empty($object->fields[$xvalwithoutprefix]['arrayofkeyval'])) {
                $xlabel = $object->fields[$xvalwithoutprefix]['arrayofkeyval'][$obj->$fieldforxkey];
            }
            $xarray = array(0 => ($xlabel ? dol_trunc($xlabel, 20, 'middle') : $langs->trans("NotDefined")));
            foreach($search_measures as $key => $val) {
                $fieldfory = 'y_'.$key;
                $xarray[] = $obj->$fieldfory;
            }
            $data[] = $xarray;
        }
    }

    $totalnbofrecord = count($data);
}


print '<div class="customreportsoutput'.($totalnbofrecord?'':' customreportsoutputnotdata').'">';


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

    	$arrayoftypes = array();
    	foreach($search_measures as $key => $val) {
    	    $arrayoftypes[] = $search_graph;
    	}

    	$px1->SetLegend($legend);
    	$px1->SetMinValue($px1->GetFloorMinValue());
    	$px1->SetMaxValue($px1->GetCeilMaxValue());
    	$px1->SetWidth($WIDTH);
    	$px1->SetHeight($HEIGHT);
    	$px1->SetYLabel($langs->trans("Y"));
    	$px1->SetShading(3);
    	$px1->SetHorizTickIncrement(1);
    	$px1->SetCssPrefix("cssboxes");
    	$px1->SetType($arrayoftypes);
    	$px1->mode='depth';
    	$px1->SetTitle('');

    	$dir=$conf->user->dir_temp;
    	dol_mkdir($dir);
    	$filenamenb = $dir.'/customreport_'.$object->element.'.png';
    	$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=user&file=customreport_'.$object->element.'.png';

    	$px1->draw($filenamenb, $fileurlnb);

    	print $px1->show($totalnbofrecord ? 0 : $langs->trans("SelectYourGraphOptionsFirst"));
    }
}

if ($sql) {
    // Show admin info
    print '<br>'.info_admin($langs->trans("SQLUsedForExport").':<br> '.$sql);
}

print '<div>';

dol_fiche_end();


// End of page
llxFooter();

$db->close();
