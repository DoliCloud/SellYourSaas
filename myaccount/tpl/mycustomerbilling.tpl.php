<?php
/* Copyright (C) 2011-2018 Laurent Destailleur <eldy@users.sourceforge.net>
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

// Protection to avoid direct call of template
if (empty($conf) || ! is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}

?>
<!-- BEGIN PHP TEMPLATE mycustomerbilling.tpl.php -->
<?php

// Instantiate hooks of myaccount only if not already define
$hookmanager->initHooks(array('sellyoursaas-mycustomerbilling'));

// TODO separate select 2 (commission earned) and select 1 (commissions received)
$page2 = $page;
$offset2 = $offset;
$sortfield2 = $sortfield;
$sortorder2 = $sortorder;
$limit2 = $limit;

print '
	<div class="page-content-wrapper">
			<div class="page-content">



	     <!-- BEGIN PAGE HEADER-->
	<!-- BEGIN PAGE HEAD -->
	<div class="page-head">
	  <!-- BEGIN PAGE TITLE -->
	<div class="page-title">
	  <h1>'.$langs->trans("MyCustomersBilling").'</h1>
	</div>
	<!-- END PAGE TITLE -->
	</div>
	<!-- END PAGE HEAD -->
	<!-- END PAGE HEADER-->



	    <div class="row">
	      <div class="col-md-12">
			<!-- my commissions received -->
			<div class="portlet light">
	          <div class="portlet-title">
	            <div class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("MyCommissionsReceived").' ('.$conf->currency.')</div>
	          </div>';


		print '
			<div class="div-table-responsive-no-min">
				<table class="noborder centpercent tablecommission">
				<tr class="liste_titre">

	              <td>
	                '.$langs->trans("Invoice").'
	              </td>
	              <td style="min-width: 100px">
	                '.$langs->trans("Date").'
	              </td>
	              <td align="right">
	                '.$langs->trans("AmountHT").'
	              </td>
	              <td align="right">
	                '.$langs->trans("AmountTTC").'
	              </td>
	              <td>
	                '.$langs->trans("Status").'
	              </td>

				</tr>
				';

				$sortfield = 'f.datef';
				$sortorder = 'DESC';

				$sql ='SELECT f.rowid, f.ref as ref, f.ref_supplier, f.fk_soc, f.datef, f.total_ht, f.total_ttc, f.paye, f.fk_statut';
				$sql.=' FROM '.MAIN_DB_PREFIX.'facture_fourn as f';
				$sql.=' WHERE f.fk_soc = '.$mythirdpartyaccount->id;

				$sql.=$db->order($sortfield, $sortorder);

				// Count total nb of records
				$nbtotalofrecords = '';
				$resql = $db->query($sql);
				$nbtotalofrecords = $db->num_rows($resql);

				// if total resultset is smaller then paging size (filtering), goto and load page 0
if (($page * $limit) > $nbtotalofrecords) {
	$page = 0;
	$offset = 0;
}
				// if total resultset is smaller than the limit, no need to do paging.
if (is_numeric($nbtotalofrecords) && $limit > $nbtotalofrecords) {
	$num = $nbtotalofrecords;
} else {
	$sql.= $db->plimit($limit+1, $offset);

	$resql=$db->query($sql);
	if (! $resql) {
		dol_print_error($db);
		exit;
	}

	$num = $db->num_rows($resql);
}

				include_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';

				$tmpthirdparty = new Societe($db);
				$tmpinvoice = new FactureFournisseur($db);
				$ecmfile = new EcmFiles($db);


				// Loop on record
				// --------------------------------------------------------------------
				$i=0; $totalpaidht = 0;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) break;		// Should not happen

	$tmpthirdparty->fetch($obj->fk_soc);	// To get current default commission of this customer
	$tmpinvoice->fetch($obj->rowid);

	if ($tmpinvoice->statut == FactureFournisseur::STATUS_DRAFT) continue;

	$titleinvoice = $obj->ref.($obj->ref_supplier ? ' ('.$obj->ref_supplier.')' : '');

	print '
							<tr>
			              <td class="nowraponall">
			                '.($obj->ref_supplier ? $obj->ref_supplier : $obj->ref);
	$publicurltodownload = $tmpinvoice->getLastMainDocLink($tmpinvoice->element, 0, 1);

	$sellyoursaasaccounturl = $conf->global->SELLYOURSAAS_ACCOUNT_URL;
	include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
	$sellyoursaasaccounturl = preg_replace('/'.preg_quote(getDomainFromURL($conf->global->SELLYOURSAAS_ACCOUNT_URL, 1), '/').'/', getDomainFromURL($_SERVER["SERVER_NAME"], 1), $sellyoursaasaccounturl);

	$urltouse=$sellyoursaasaccounturl.'/'.(DOL_URL_ROOT?DOL_URL_ROOT.'/':'').$publicurltodownload;
	//print '<br><a href="'.$urltouse.'" target="_download">'.$langs->trans("Download").'</a>';

	$totalpaidht += $obj->total_ht;

	print img_mime('pdf.pdf', $titleinvoice, 'paddingleft');

	print '
			              </td>
			              <td>
			                '.dol_print_date($obj->datef, 'dayrfc', $langs).'
			              </td>
			              <td align="right">
			                '.price(price2num($obj->total_ht), 1, $langs, 0, 0, $conf->global->MAIN_MAX_DECIMALS_TOT, $conf->currency).'
			              </td>
			              <td align="right">
			                '.price(price2num($obj->total_ttc), 1, $langs, 0, 0, $conf->global->MAIN_MAX_DECIMALS_TOT, $conf->currency).'
			              </td>
			              <td>
			                ';
	//$s = $tmpinvoice->getLibStatut(2, $alreadypayed + $amount_credit_notes_included);
	$s = $tmpinvoice->getLibStatut(2, -1);
	//$s = preg_replace('/'.$langs->trans("BillShortStatusPaidBackOrConverted").'/', $langs->trans("Refunded"), $s);
	print $s;
	print '
					    </tr>
				        ';

	$i++;
}

				//print '<tr class="liste_titre"><td colspan="7">'.$langs->trans("Total").'</td>';
				//print '<td align="right"><strong>'.price($commoldystem + $totalamountcommission).'</strong></td>';
				//print '</tr>';

				print '</table></div>';


		print '
	        </div>
		  </div>
	    </div> <!-- END ROW -->


	    <div class="row">
	      <div class="col-md-12">

			<!-- my commissions earned -->
	        <div class="portlet light">
	          <div class="portlet-title">
	            <div class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("MyCommissionsEarned").' ('.$conf->currency.')</div>
        ';

		print '
			<div class="div-table-responsive-no-min">
				<table class="noborder centpercent tablecommission">
				<tr class="liste_titre">

	              <td style="min-width: 150px">
			         '.$langs->trans("Customer").'
	              </td>
	              <td>
	                '.$langs->trans("Invoice").'
	              </td>
	              <td style="min-width: 100px">
	                '.$langs->trans("Date").'
	              </td>
	              <td align="right">
	                '.$langs->trans("AmountHT").'
	              </td>
	              <td>
	                '.$langs->trans("Status").'
	              </td>
	              <td align="right">
	                '.$langs->trans("Commission").' (%)
	              </td>
	              <td align="right">
	                '.$langs->trans("Commission").'<br>('.$langs->trans("AmountHT").')
	              </td>

				</tr>
		';

if (preg_match('/Commissions old system = ([a-zA-Z0-9\.\,]+)/i', $mythirdpartyaccount->note_private, $reg)) {
	$commoldystem = price2num($reg[1]);
	print '<tr>';
	print '<td colspan="2">'.$langs->trans("CommissionsOnOldSystem").'</td>';
	print '<td></td>';
	print '<td></td>';
	print '<td></td>';
	print '<td></td>';
	print '<td align="right">'.price($commoldystem).'</td>';
	print '</tr>';
}

		$sortfield2 = 'f.datef,f.rowid';
		$sortorder2 = 'DESC';

		$sql ='SELECT f.rowid, f.ref as ref, f.fk_soc, f.datef, total_ht, total_ttc, f.paye, f.fk_statut, fe.commission';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'facture as f LEFT JOIN '.MAIN_DB_PREFIX.'facture_extrafields as fe ON fe.fk_object = f.rowid';
		$sql.=' WHERE fe.reseller = '.$mythirdpartyaccount->id;

		$sql.=$db->order($sortfield2, $sortorder2);

		// Count total nb of records
		$nbtotalofrecords = '';
		$resql = $db->query($sql);
		$nbtotalofrecords = $db->num_rows($resql);

		// if total resultset is smaller then paging size (filtering), goto and load page 0
if (($page2 * $limit2) > $nbtotalofrecords) {
	$page2 = 0;
	$offset2 = 0;
}
		// if total resultset is smaller than the limit, no need to do paging.
if (is_numeric($nbtotalofrecords) && $limit2 > $nbtotalofrecords) {
	$num = $nbtotalofrecords;
} else {
	$sql.= $db->plimit($limit2+1, $offset2);

	$resql=$db->query($sql);
	if (! $resql) {
		dol_print_error($db);
		exit;
	}

	$num = $db->num_rows($resql);
}

		include_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';

		$tmpthirdparty = new Societe($db);
		$tmpinvoice = new Facture($db);
		$ecmfile = new EcmFiles($db);


		// Loop on record
		// --------------------------------------------------------------------
		$i=0;
while ($i < min($num, $limit2)) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) break;		// Should not happen

	$tmpthirdparty->fetch($obj->fk_soc);	// To get current default commission of this customer
	$tmpinvoice->fetch($obj->rowid);

	if ($tmpinvoice->statut == Facture::STATUS_DRAFT) continue;

	$currentcommissionpercent = $tmpthirdparty->array_options['options_commission'];
	$commissionpercent = $obj->commission;
	if ($obj->paye) $commission = price2num($obj->total_ht * $commissionpercent / 100, 'MT');
	else $commission = 0;

	print '
						<tr>
		              <td title="'.dol_escape_htmltag($tmpthirdparty->name).'" class="tdoverflowmax200">
				         ';
	print $tmpthirdparty->name;
	//.' '.$form->textwithpicto('', $langs->trans("CurrentCommission").': '.($commissionpercent?$commissionpercent:0).'%', 1).'
	print '</td>
		                      <td class="nowraponall">
		                        ';
	$titleinvoice = $tmpinvoice->ref.($tmpinvoice->ref_supplier ? ' ('.$tmpinvoice->ref_supplier.')' : '');

	$sellyoursaasaccounturl = $conf->global->SELLYOURSAAS_ACCOUNT_URL;
	include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
	$sellyoursaasaccounturl = preg_replace('/'.preg_quote(getDomainFromURL($conf->global->SELLYOURSAAS_ACCOUNT_URL, 1), '/').'/', getDomainFromURL($_SERVER["SERVER_NAME"], 1), $sellyoursaasaccounturl);

	$parameters=array('invoice' => $tmpinvoice, 'thirdparty' => $tmpthirdparty, 'sellyoursaasaccounturl' => $sellyoursaasaccounturl);
	$reshook = $hookmanager->executeHooks('getLastMainDocLink', $parameters);    // Note that $action and $object may have been modified by some hooks.
	if ($reshook > 0) {
		print $hookmanager->resPrint;
	} else {
		$publicurltodownload = $tmpinvoice->getLastMainDocLink($tmpinvoice->element, 0, 1);
		$urltouse=$sellyoursaasaccounturl.'/'.(DOL_URL_ROOT?DOL_URL_ROOT.'/':'').$publicurltodownload;
		print '<a href="'.$urltouse.'" target="_download">'.$tmpinvoice->ref.img_mime('pdf.pdf', $titleinvoice, 'paddingleft').'</a>';
	}

	print '
		              </td>
		                      <td>
		                        '.dol_print_date($obj->datef, 'dayrfc', $langs).'
		                      </td>
		              <td align="right">
		                '.price(price2num($obj->total_ht), 1, $langs, 0, 0, $conf->global->MAIN_MAX_DECIMALS_TOT, $conf->currency).'
		              </td>
		              <td>
		                ';
	//$s = $tmpinvoice->getLibStatut(2, $alreadypayed + $amount_credit_notes_included);
	$s = $tmpinvoice->getLibStatut(2, -1);
	$s = preg_replace('/'.$langs->trans("BillShortStatusPaidBackOrConverted").'/', $langs->trans("Refunded"), $s);
	$s = preg_replace('/'.$langs->trans("BillStatusPaidBackOrConverted").'/', $langs->trans("Refunded"), $s);

	print $s;
	print '
		              </td>
		              <td align="right">
		                '.($commissionpercent?$commissionpercent:0).'
		              </td>
		              <td align="right">
		                '.price($commission).'
		              </td>
				    </tr>
			        ';

	$i++;
}

if ($nbtotalofrecords > $limit2) {
	// Show navigation previous - next
	print '<tr><td colspan="6" class="center">';
	if ($page2 > 0) print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?mode='.$mode.'&limit='.$limit2.'&page='.($page2-1).'">'.$langs->trans("Previous").'</a>';
	if ($page2 > 0 && (($page2 + 1) * $limit2) <= $nbtotalofrecords) print ' &nbsp; ... &nbsp; ';
	if ((($page2 + 1) * $limit2) <= $nbtotalofrecords) print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?mode='.$mode.'&limit='.$limit2.'&page='.($page2+1).'">'.$langs->trans("Next").'</a>';
	print '<br><br>';
	print '</td>';
	print '<td class="right">...<br><br></td>';
	print '</tr>';
}

		// Get total of commissions earned
		$totalamountcommission='ERROR';

		$sql ='SELECT SUM(fe.commission * f.total_ht / 100) as total';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'facture as f LEFT JOIN '.MAIN_DB_PREFIX.'facture_extrafields as fe ON fe.fk_object = f.rowid';
		//$sql.=' WHERE fe.reseller IN ('.join(',', $listofcustomeridreseller).')';
		$sql.=' WHERE fe.reseller = '.((int) $mythirdpartyaccount->id);
		$sql.=' AND fk_statut <> '.Facture::STATUS_DRAFT;
		$sql.=' AND paye = 1';

		$resql = $db->query($sql);
if ($resql) {
	$obj = $db->fetch_object($resql);
	$totalamountcommission = $obj->total;
} else {
	dol_print_error($db);
}

		print '<!-- Total of commissions earned -->';
		print '<tr class="liste_titre"><td colspan="6">'.$langs->trans("Total").'</td>';
		print '<td align="right"><strong>'.price($commoldystem + $totalamountcommission).'</strong></td>';
		print '</tr>';

if ($totalpaidht) {
	print '<tr style="background-color: #f0f0F0;">';
	print '<td colspan="2">'.$langs->trans("AlreadyPaid").'</td>';
	print '<td></td>';
	print '<td></td>';
	print '<td></td>';
	print '<td></td>';
	print '<td align="right">'.price($totalpaidht).'</td>';
	print '</tr>';
}

		print '<tr style="background-color: #f0f0F0;">';
		print '<td colspan="2">'.$langs->trans("RemainderToBill").'</td>';
		print '<td></td>';
		print '<td></td>';
		print '<td></td>';
		print '<td></td>';
		print '<td align="right">'.price(price2num($commoldystem + $totalamountcommission - $totalpaidht, 'MT')).'</td>';
		print '</tr>';

		print '</table>';

		print '<br>';
		print $langs->trans("YouCanClainAmountWhen", price(getDolGlobalInt('SELLYOURSAAS_MINAMOUNT_TO_CLAIM') ? getDolGlobalInt('SELLYOURSAAS_MINAMOUNT_TO_CLAIM') : 100, 1, $langs, 1, -1, -1, $conf->currency)).'<br>';
		$labelforcompany = $mysoc->name. ' ('.$langs->transnoentitiesnoconv("VATIntra").': '.$mysoc->tva_intra.', '.$langs->trans("Country").': '.$langs->trans("Country".$mysoc->country_code).')';

		$emailforresellerinvoice = getDolGlobalString('SELLYOURSAAS_RESELLER_EMAIL');
if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
			&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != getDolGlobalString('SELLYOURSAAS_MAIN_DOMAIN_NAME')) {
		$newnamekey = 'SELLYOURSAAS_RESELLER_EMAIL-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
	if (getDolGlobalString($newnamekey)) {
			$emailforresellerinvoice = getDolGlobalString($newnamekey);
	}
}

if (empty($emailforresellerinvoice)) {
	$emailforresellerinvoice = getDolGlobalString('SELLYOURSAAS_MAIN_EMAIL');
}
if (empty($emailforresellerinvoice)) {
	$emailforresellerinvoice = $mysoc->email;
}
		print $langs->trans("SendYourInvoiceTo", $labelforcompany, $emailforresellerinvoice);
		print '</div>';

		print '
        </div></div>
                    </div>
                  </div>
        		';

		print '



	    </div>
		</div>
	';

?>
<!-- END PHP TEMPLATE mycustomerbilling.tpl.php -->
