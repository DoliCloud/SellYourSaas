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
<!-- BEGIN PHP TEMPLATE billing.tpl.php -->
<?php

// Instantiate hooks of myaccount only if not already define
$hookmanager->initHooks(array('sellyoursaas-myaccountbilling'));

print '
	<div class="page-content-wrapper">
			<div class="page-content">


    <!-- BEGIN PAGE HEADER-->
	<!-- BEGIN PAGE HEAD -->
	<div class="page-head">
	  <!-- BEGIN PAGE TITLE -->
	<div class="page-title">
	  <h1>'.$langs->trans("Billing").' <small>'.$langs->trans("BillingDesc").'</small></h1>
	</div>
	<!-- END PAGE TITLE -->
	</div>
	<!-- END PAGE HEAD -->
	<!-- END PAGE HEADER-->

	    <div class="row">
	      <div class="'.($mythirdpartyaccount->array_options['options_checkboxnonprofitorga'] == 'nonprofit' && getDolGlobalInt("SELLYOURSAAS_ENABLE_FREE_PAYMENT_MODE") ? 'col-md-12' : 'col-md-9').'">

	        <div class="portlet light" id="planSection">

	          <div class="portlet-title">
	            <div class="caption">
	              <span class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("MyInvoices").'</span>
	            </div>
	          </div>
';

if (getDolGlobalString('SELLYOURSAAS_DOLICLOUD_ON') && $mythirdpartyaccount->array_options['options_source'] == 'MIGRATIONV1') {
	$sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
	if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
		$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
		if (! empty($conf->global->$newnamekey)) {
			$sellyoursaasemail = $conf->global->$newnamekey;
		}
	}

	print $langs->trans('InvoiceBeforeAreAvailableOnDemandAt', dol_print_date($mythirdpartyaccount->array_options['options_date_registration'], 'day'), $sellyoursaasemail);
	print '<br>';
}

if (count($listofcontractid) > 0) {
	foreach ($listofcontractid as $id => $contract) {
		$planref = $contract->array_options['options_plan'];
		$statuslabel = $contract->array_options['options_deployment_status'];
		$instancename = preg_replace('/\..*$/', '', $contract->ref_customer);

		// Get info about PLAN of Contract
		$planlabel = $planref;

		$color = "green";
		if ($statuslabel == 'processing') {
			$color = 'orange';
		}
		if ($statuslabel == 'suspended') {
			$color = 'orange';
		}

		$dbprefix = $contract->array_options['options_prefix_db'];
		if (empty($dbprefix)) {
			$dbprefix = 'llx_';
		}

		print '
				<br>
		        <div class="portlet-body">

		            <div class="row" style="border-bottom: 1px solid #ddd;">

		              <div class="col-md-4">
				          <a href="https://'.$contract->ref_customer.'" class="caption-subject bold uppercase font-green-sharp" title="'.$langs->trans("Contract").' '.$contract->ref.'" target="_blankinstance">'.$instancename.img_picto('', 'globe', 'class="paddingleft"').'</a><br>
						  <span class="opacitymedium small">'.$langs->trans("ID").' : </span><span class="font-green-sharp small">'.$contract->ref.'</span>
				          <span class="caption-helper"><!-- - '.$planlabel.'--></span>	<!-- This is service -->
		              </div><!-- END COL -->
		              <div class="col-md-2 hideonsmartphone">
		                '.$langs->trans("Date").'
		              </div>
		              <div class="col-md-2 hideonsmartphone">
		                '.$langs->trans("Amount").'
		              </div>
		              <div class="col-md-2 hideonsmartphone">

		              </div>
		              <div class="col-md-2 hideonsmartphone">
		                '.$langs->trans("Status").'
		              </div>
		            </div> <!-- END ROW -->
				';

		$contract->fetchObjectLinked();
		$freqlabel = array('d'=>$langs->trans('Day'), 'm'=>$langs->trans('Month'), 'y'=>$langs->trans('Year'));
		if (!empty($contract->linkedObjects['facture']) && is_array($contract->linkedObjects['facture']) && count($contract->linkedObjects['facture']) > 0) {
			// Sort on ascending date
			usort($contract->linkedObjects['facture'], "sellyoursaasCmpDateDesc");	// function "cmp" to sort on ->date is inside sellyoursaas.lib.php

			//var_dump($contract->linkedObjects['facture']);
			//dol_sort_array($contract->linkedObjects['facture'], 'date');
			foreach ($contract->linkedObjects['facture'] as $idinvoice => $invoice) {
				/* @var Facture $invoice */
				if ($invoice->statut == Facture::STATUS_DRAFT) {
					continue;
				}

				print '
					            <div class="row" style="margin-top:20px">

					              <div class="col-md-4 nowraponall">
									';

				// Execute hook getLastMainDocLink
				$parameters=array('invoice' => $invoice, 'contract' => $contract);
				$reshook = $hookmanager->executeHooks('getLastMainDocLink', $parameters);    // Note that $action and $object may have been modified by some hooks.
				if ($reshook > 0) {
					print $hookmanager->resPrint;
				} else {
					$url = $invoice->getLastMainDocLink($invoice->element, 0, 1);
					if ($url) {
						print '<a href="'.DOL_URL_ROOT.'/'.$url.'">'.$invoice->ref.img_mime($invoice->ref.'.pdf', $langs->trans("File").': '.$invoice->ref.'.pdf', 'paddingleft').'</a>';
					} else {
						print $invoice->ref.' <span class="small opacitymedium">('.$langs->trans("InvoicePDFNotYetAvailable").')</span>';
					}
				}

				print '</div>

								  <!-- Date -->
					              <div class="col-md-2">
									'.dol_print_date($invoice->date, 'dayrfc', $langs).'
					              </div>

								  <!-- Price -->
					              <div class="col-md-2">
									'.price(price2num($invoice->total_ttc), 1, $langs, 0, 0, $conf->global->MAIN_MAX_DECIMALS_TOT, $conf->currency).'
					              </div>

								  <!-- Payment mode -->
								  <div class="col-md-2" title="'.dol_escape_htmltag($invoice->mode_reglement).'">
									'.dol_escape_htmltag($invoice->mode_reglement).'
					              </div>

					              <div class="col-md-2 nowrap">
									';
				$alreadypayed = $invoice->getSommePaiement();
				$amount_credit_notes_included = $invoice->getSumCreditNotesUsed();
				$paymentinerroronthisinvoice = 0;

				// Test if there is a payment error (if last event is payment error). If yes, ask to fix payment data
				$sql = 'SELECT f.rowid, ee.code, ee.label, ee.extraparams, ee.datep  FROM '.MAIN_DB_PREFIX.'facture as f';
				$sql.= ' INNER JOIN '.MAIN_DB_PREFIX."actioncomm as ee ON ee.fk_element = f.rowid AND ee.elementtype = 'invoice'";
				$sql.= " AND (ee.code LIKE 'AC_PAYMENT_%_KO' OR ee.label = 'Cancellation of payment by the bank')";
				$sql.= ' WHERE f.fk_soc = '.((int) $mythirdpartyaccount->id).' AND f.paye = 0 AND f.rowid = '.((int) $invoice->id);
				$sql.= ' ORDER BY ee.datep DESC';
				$sql.= ' LIMIT 1';

				$resql = $db->query($sql);
				if ($resql) {
					$num_rows = $db->num_rows($resql);
					$i=0;
					if ($num_rows) {
						$paymentinerroronthisinvoice++;
						$obj = $db->fetch_object($resql);

						// There is at least one payment error
						$lasttrystring = $langs->trans("LastTry").': '.dol_print_date($db->jdate($obj->datep));
						if ($obj->label == 'Cancellation of payment by the bank') {
							print '<span title="'.dol_escape_htmltag($langs->trans("PaymentChargedButReversedByBank").' - '.$lasttrystring).'">';
							print dolGetStatus($langs->transnoentitiesnoconv("PaymentError"), $langs->transnoentitiesnoconv("PaymentError"), '', 'status8', 2);
							//print '<img src="'.DOL_URL_ROOT.'/theme/eldy/img/statut8.png"> '.$langs->trans("PaymentError").'</span>';
						} elseif ($obj->extraparams == 'PAYMENT_ERROR_INSUFICIENT_FUNDS') {
							print '<span title="'.dol_escape_htmltag($obj->extraparams.($obj->extraparams ? ' - ' : '').$lasttrystring).'">';
							print dolGetStatus($langs->transnoentitiesnoconv("PaymentError").' Insuficient funds', $langs->transnoentitiesnoconv("PaymentError"), '', 'status8', 2);
							//print '<img src="'.DOL_URL_ROOT.'/theme/eldy/img/statut8.png" alt="Insuficient funds"> '.$langs->trans("PaymentError").'</span>';
						} else {
							print '<span title="'.dol_escape_htmltag($obj->extraparams.($obj->extraparams ? ' - ' : '').$lasttrystring).'">';
							print dolGetStatus($langs->transnoentitiesnoconv("PaymentError"), $langs->transnoentitiesnoconv("PaymentError"), '', 'status8', 2);
							//print '<img src="'.DOL_URL_ROOT.'/theme/eldy/img/statut8.png"> '.$langs->trans("PaymentError").'</span>';
						}
					}
				}
				if (! $paymentinerroronthisinvoice) {
					$s = $invoice->getLibStatut(2, $alreadypayed + $amount_credit_notes_included);
					$s = preg_replace('/'.$langs->transnoentitiesnoconv("BillStatusPaidBackOrConverted").'/', $langs->trans("Refunded"), $s);
					$s = preg_replace('/'.$langs->transnoentitiesnoconv("BillShortStatusPaidBackOrConverted").'/', $langs->trans("Refunded"), $s);
					// Test if there is a direct debit order linked to invoice
					$sql = "SELECT dp.rowid, pb.rowid  FROM ".MAIN_DB_PREFIX."prelevement_demande as dp";
					$sql .= " JOIN ".MAIN_DB_PREFIX."prelevement_bons as pb ON pb.rowid = dp.fk_prelevement_bons";
					$sql .= " WHERE dp.fk_facture = ".((int) $invoice->id)." AND dp.date_traite IS NOT NULL";
					$sql .= " AND pb.statut = 1 AND pb.date_credit IS NULL";
					$resql = $db->query($sql);
					if ($resql) {
						$num_rows = $db->num_rows($resql);
						if ($num_rows >= 1) {
							$s = preg_replace('/'.$langs->transnoentitiesnoconv("BillStatusNotPaid").'/', $langs->trans("StatusTrans"), $s);
							$s = preg_replace('/'.$langs->trans("BillShortStatusNotPaid").'/', $langs->trans("SepaSentToBank"), $s);
						}
					}
					print $s;
					// TODO Add details of payments
					//$htmltext = 'Soon here: Details of payment...';
					//print $form->textwithpicto('', $htmltext);
				}
				print '
					              </div>

					            </div>
							';
			}
		} else {
			print '
					            <div class="row" style="margin-top:20px">

					              <div class="col-md-12">
								<span class="opacitymedium">'.$langs->trans("NoInvoice").'</span>
								  </div>
								</div>
						';
		}

		print '
		          </div> <!-- END PORTLET-BODY -->
				<br><br>
				';
	}
} else {
	print '
					            <div class="row" style="margin-top:20px">

					              <div class="col-md-12">
								<span class="opacitymedium">'.$langs->trans("NoInvoice").'</span>
								  </div>
								</div>
						';
}

print '

	        </div> <!-- END PORTLET -->



	      </div> <!-- END COL -->';
if ($mythirdpartyaccount->array_options['options_checkboxnonprofitorga'] != 'nonprofit' || !getDolGlobalInt("SELLYOURSAAS_ENABLE_FREE_PAYMENT_MODE")) {
	print'			<!-- Box of payment modes -->
	      <div class="col-md-3">
	        <div class="portlet light" id="paymentMethodSection">

	          <div class="portlet-title">
	            <div class="caption">
	              <i class="icon-credit-card font-green-sharp"></i>
	              <span class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("PaymentMode").'</span>
	            </div>
	          </div>

	          <div class="portlet-body">
	            <p>';

	$urltoenterpaymentmode = $_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode);

	if ($nbpaymentmodeok > 0) {
		print '<table class="centpercent">';
		print '<!-- '.$companypaymentmodetemp->id.' -->';

		$i = 0;
		foreach ($arrayofcompanypaymentmode as $companypaymentmodetemp) {
			if ($i > 0) {
				print '<tr><td colspan="3"><br></td></tr>';
			}
			if ($companypaymentmodetemp->type == 'card') {
				print '<tr>';
				print '<td colspan="3" class="wordbreak">';
				print '<!-- '.$companypaymentmodetemp->id.' -->';
				print img_credit_card($companypaymentmodetemp->type_card);
				print $langs->trans("CreditCard");
				print '</td>';
				print '</tr>';
				print '<tr>';
				print '<td>';
				print '....'.dol_escape_htmltag($companypaymentmodetemp->last_four);
				print '</td>';
				print '<td></td>';
				print '<td>';
				print sprintf("%02d", $companypaymentmodetemp->exp_date_month).'/'.$companypaymentmodetemp->exp_date_year;
				print '</td>';
				print '</tr>';
				// Warning if expiring
				if ($companypaymentmodetemp->exp_date_year < $nowyear ||
				($companypaymentmodetemp->exp_date_year == $nowyear && $companypaymentmodetemp->exp_date_month <= $nowmonth)) {
					print '<tr><td colspan="3" style="color: orange">';
					print img_warning().' '.$langs->trans("YourPaymentModeWillExpireFixItSoon", $urltoenterpaymentmode);
					print '</td></tr>';
				}
				if (GETPOST('debug', 'int')) {
					include_once DOL_DOCUMENT_ROOT.'/stripe/class/stripe.class.php';
					$stripe = new Stripe($db);
					$stripeacc = $stripe->getStripeAccount($service);								// Get Stripe OAuth connect account if it exists (no remote access to Stripe here)
					$customer = $stripe->customerStripe($mythirdpartyaccount, $stripeacc, $servicestatusstripe, 0);

					print '<tr><td>';
					print 'Stripe customer: '.$customer->id;
					print '</td><td colspan="2">';
					print 'Stripe card: '.dol_escape_htmltag($companypaymentmodetemp->stripe_card_ref);
					print '</td></tr>';
				}
			} elseif ($companypaymentmodetemp->type == 'paypal') {
				print '<tr>';
				print '<td colspan="3" class="wordbreak">';
				print '<!-- '.$companypaymentmodetemp->id.' -->';
				print img_picto('', 'paypal');
				print $langs->trans("Paypal");
				print '</td>';
				print '</tr>';
				print '<tr>';
				print '<td>';
				print dol_escape_htmltag($companypaymentmodetemp->email);
				print '<br>Preaproval key: '.$companypaymentmodetemp->preapproval_key;
				print '</td>';
				print '<td>';
				print dol_print_date($companypaymentmodetemp->starting_date, 'day').'/'.dol_print_date($companypaymentmodetemp->ending_date, 'day');
				print '</td>';
				print '</tr>';
				// Warning if expiring
				if (dol_time_plus_duree($companypaymentmodetemp->ending_date, -1, 'm') < $nowyear) {
					print '<tr><td colspan="3" style="color: orange">';
					print img_warning().' '.$langs->trans("YourPaymentModeWillExpireFixItSoon", $urltoenterpaymentmode);
					print '</td></tr>';
				}
			} elseif ($companypaymentmodetemp->type == 'ban') {
				print '<tr>';
				print '<td colspan="3" class="wordbreak">';
				print img_picto('', 'bank', '', false, 0, 0, '', 'fa-2x');
				print $langs->trans("PaymentTypeShortPRE");
				print '</td>';
				print '</tr>';

				print '<tr><td colspan="3">';
				print $langs->trans("IBAN").': <span class="small">'.$companypaymentmodetemp->iban_prefix.'</span><br>';
				if ($companypaymentmodetemp->rum) {
					print $langs->trans("RUM").': <span class="small">'.$companypaymentmodetemp->rum.'</span>';
				}
				print '</td></tr>';
			} else {
				print '<tr>';
				print '<td>';
				print dol_escape_htmltag($companypaymentmodetemp->type);
				print '</td>';
				print '<td>';
				print dol_escape_htmltag($companypaymentmodetemp->label);
				print '</td>';
				print '<td>';
				print '</td>';
				print '</tr>';
			}

			$i++;
		}

		print '</table>';
	} else {
		print '<span class="opacitymedium">'.$langs->trans("NoPaymentMethodOnFile").'</span>';
		if (!empty($nbofinstancessuspended) || !empty($ispaid) || !empty($atleastonecontractwithtrialended)) {
			print ' '.img_warning($langs->trans("NoPaymentMethodOnFile"));
		}
	}

	print '
	                <br><br>
	                <center><a href="'.$urltoenterpaymentmode.'" class="wordbreak btn default green-stripe">';
	if ($nbpaymentmodeok) {
		print $langs->trans("ModifyPaymentMode").'...';
	} else {
		print $langs->trans("AddAPaymentMode").'...';
	}
	print '</a></center>

	            </p>
	          </div> <!-- END PORTLET-BODY -->

	        </div> <!-- END PORTLET -->
	      </div><!-- END COL -->';
}
print '  </div> <!-- END ROW -->


	    </div>
		</div>
	';
?>
<!-- END PHP TEMPLATE billing.tpl.php -->
