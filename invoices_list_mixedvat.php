<?php
/* Copyright (C) 2026 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       invoices_list_mixedvat.php
 * \ingroup    sellyoursaas
 * \brief      List of invoices with lines using at least 2 different VAT rates
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

// Load traductions files requiredby by page
$langs->loadLangs(array("sellyoursaas@sellyoursaas", "bills", "companies"));

// Get parameters
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0) {
	$page = 0;
}
$offset = $limit * $page;
$sortorder = GETPOST('sortorder', 'aZ09');
$sortfield = GETPOST('sortfield', 'aZ09');

// Mode: '' = standard invoices, 'rec' = recurring invoice templates
$mode = GETPOST('mode', 'aZ09');
if ($mode != 'rec') {
	$mode = '';
}

// List of columns we can sort on
$sortfieldlist = array('ref' => ($mode == 'rec' ? 'f.titre' : 'f.ref'), 'socname' => 's.nom', 'datef' => ($mode == 'rec' ? 'f.date_when' : 'f.datef'), 'totalht' => 'f.total_ht', 'totalttc' => 'f.total_ttc');
if (! array_key_exists($sortfield, $sortfieldlist)) {
	$sortfield = 'datef';
}
$sortorder = (dol_strtolower($sortorder) == 'asc' ? 'asc' : 'desc');

// Tables and entity element, according to mode
$tablename = ($mode == 'rec' ? MAIN_DB_PREFIX."facture_rec" : MAIN_DB_PREFIX."facture");
$tablenamedet = ($mode == 'rec' ? MAIN_DB_PREFIX."facturedet_rec" : MAIN_DB_PREFIX."facturedet");
$elemententity = ($mode == 'rec' ? 'invoice' : 'facture');

$contextpage = 'invoiceslistmixedvat';
$hookmanager->initHooks(array($contextpage));

// Security check
if (!$user->hasRight('sellyoursaas', 'read') || !$user->hasRight('facture', 'lire')) {
	accessforbidden();
}


/*
 * View
 */

$sql = "SELECT f.rowid, f.fk_soc, f.total_ht, f.total_tva, f.total_ttc";
if ($mode == 'rec') {
	$sql .= ", f.titre as ref, f.date_when as datef, f.suspended, f.frequency, f.unit_frequency, f.date_last_gen";
} else {
	$sql .= ", f.ref, f.type, f.datef, f.fk_statut, f.paye";
}
$sql .= ", s.nom as socname, s.client as socclient";
$sql .= ", GROUP_CONCAT(DISTINCT fd.tva_tx ORDER BY fd.tva_tx SEPARATOR '|') as listofvat";
$sql .= " FROM ".$tablename." as f";
$sql .= " INNER JOIN ".$tablenamedet." as fd ON fd.fk_facture = f.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = f.fk_soc";
$sql .= " WHERE f.entity IN (".getEntity($elemententity).")";
if ($mode == 'rec') {
	$sql .= " GROUP BY f.rowid, f.fk_soc, f.total_ht, f.total_tva, f.total_ttc, f.titre, f.date_when, f.suspended, f.frequency, f.unit_frequency, f.date_last_gen, s.nom, s.client";
} else {
	$sql .= " GROUP BY f.rowid, f.fk_soc, f.total_ht, f.total_tva, f.total_ttc, f.ref, f.type, f.datef, f.fk_statut, f.paye, s.nom, s.client";
}
$sql .= " HAVING COUNT(DISTINCT fd.tva_tx) > 1";
$sql .= $db->order($sortfieldlist[$sortfield], $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$nbtotal = 0;
$sqlcount = "SELECT COUNT(*) as nb";
$sqlcount .= " FROM (";
$sqlcount .= " SELECT fd.fk_facture";
$sqlcount .= " FROM ".$tablename." as f";
$sqlcount .= " INNER JOIN ".$tablenamedet." as fd ON fd.fk_facture = f.rowid";
$sqlcount .= " WHERE f.entity IN (".getEntity($elemententity).")";
$sqlcount .= " GROUP BY fd.fk_facture";
$sqlcount .= " HAVING COUNT(DISTINCT fd.tva_tx) > 1";
$sqlcount .= " ) as tcount";

$resqlcount = $db->query($sqlcount);
if ($resqlcount) {
	$obj = $db->fetch_object($resqlcount);
	if ($obj) {
		$nbtotal = $obj->nb;
	}
	$db->free($resqlcount);
}

$resql = $db->query($sql);

$num = 0;
if ($resql) {
	$num = $db->num_rows($resql);
}

$param = '';
if ($mode == 'rec') {
	$param .= '&mode=rec';
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}

$title = $langs->trans($mode == 'rec' ? "RecurringInvoicesWithMixedVATRates" : "InvoicesWithMixedVATRates");

llxHeader('', $title);

print '<form method="POST" id="searchFormList" spellcheck="false" action="'.$_SERVER["PHP_SELF"].'">'."\n";

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotal, 'bill', 0, '', '', $limit, 0, 0, 1);

print '<div class="div-table-responsive">';
print '<table class="liste centpercent">';

// Line of titles
print '<tr class="liste_titre">';
print_liste_field_titre("Ref", $_SERVER["PHP_SELF"], "ref", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre("ThirdParty", $_SERVER["PHP_SELF"], "socname", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre(($mode == 'rec' ? "NextDateToExecution" : "DateInvoice"), $_SERVER["PHP_SELF"], "datef", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre("VATRatesUsed", $_SERVER["PHP_SELF"], "", "", $param);
print_liste_field_titre("AmountHT", $_SERVER["PHP_SELF"], "totalht", "", $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre("AmountTTC", $_SERVER["PHP_SELF"], "totalttc", "", $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre("Status", $_SERVER["PHP_SELF"], "", "", $param, 'class="right"', $sortfield, $sortorder);
print '</tr>';


if ($resql) {
	$i = 0;
	if ($num) {
		$invoice = ($mode == 'rec' ? new FactureRec($db) : new Facture($db));
		$thirdparty = new Societe($db);

		while ($i < min($num, $limit) && $obj = $db->fetch_object($resql)) {
			$invoice->id = $obj->rowid;
			$invoice->ref = $obj->ref;
			if ($mode == 'rec') {
				$invoice->titre = $obj->ref;
				$invoice->date_when = $db->jdate($obj->datef);
				$invoice->suspended = $obj->suspended;
				$invoice->frequency = $obj->frequency;
				$invoice->unit_frequency = $obj->unit_frequency;
				$invoice->date_last_gen = $db->jdate($obj->date_last_gen);
			} else {
				$invoice->type = $obj->type;
				$invoice->date = $db->jdate($obj->datef);
				$invoice->datef = $db->jdate($obj->datef);
				$invoice->fk_statut = $obj->fk_statut;
				$invoice->status = $obj->fk_statut;
				$invoice->paye = $obj->paye;
			}

			$thirdparty->id = $obj->fk_soc;
			$thirdparty->name = $obj->socname;
			$thirdparty->client = $obj->socclient;

			// Build the list of VAT rates of the invoice
			$vatlist = array();
			$tmparray = explode('|', $obj->listofvat);
			foreach ($tmparray as $vatrateused) {
				$vatlist[] = vatrate((float) $vatrateused, true);
			}

			print '<tr class="oddeven">';

			// Ref
			print '<td class="nowraponall">'.$invoice->getNomUrl(1).'</td>';

			// Thirdparty
			print '<td>';
			if ($obj->socname) {
				print $thirdparty->getNomUrl(1);
			} else {
				print '&nbsp;';
			}
			print '</td>';

			// Date
			print '<td class="nowraponall">'.dol_print_date($db->jdate($obj->datef), 'day').'</td>';

			// VAT rates
			print '<td>'.implode(' / ', $vatlist).'</td>';

			// Amount HT
			print '<td class="right nowraponall amount">'.price($obj->total_ht).'</td>';

			// Amount TTC
			print '<td class="right nowraponall amount">'.price($obj->total_ttc).'</td>';

			// Status
			if ($mode == 'rec') {
				if (!empty($obj->suspended)) {
					print '<td class="right nowrap"><span class="badge badge-status8">'.$langs->trans("RecurringInvoiceSuspended").'</span></td>';
				} else {
					print '<td class="right nowrap"><span class="badge badge-status4">'.$langs->trans("RecurringInvoiceActive").'</span></td>';
				}
			} else {
				print '<td class="right nowrap">'.$invoice->getLibStatut(3).'</td>';
			}

			print '</tr>';
			$i++;
		}
	} else {
		print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
	}
	$db->free($resql);
} else {
	print '<tr class="oddeven"><td colspan="7"><span class="error">'.($db->error() ? dol_escape_htmltag($db->error()) : $langs->trans("Error")).'</span></td></tr>';
}

print '</table>';
print '</div>';

print '</form>'."\n";

// End of page
llxFooter();
$db->close();
