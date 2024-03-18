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
	exit(1);
}

?>
<!-- BEGIN PHP TEMPLATE dashboard.tpl.php -->
<?php

	print '
	<div class="page-content-wrapper">
			<div class="page-content">

	     <!-- BEGIN PAGE HEADER-->
	<!-- BEGIN PAGE HEAD -->
	<div class="page-head">
	  <!-- BEGIN PAGE TITLE -->
	<div class="page-title">
	  <h1>'.$langs->trans("Dashboard").'</h1>
	</div>
	<!-- END PAGE TITLE -->


	</div>
	<!-- END PAGE HEAD -->
	<!-- END PAGE HEADER-->


	    <div class="row">
	      <div class="col-md-6">

	        <div class="portlet light" id="planSection">

	          <div class="portlet-title">
	            <div class="caption">
	              <i class="fa fa-server font-green-sharp paddingright"></i><span class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("MyInstances").'</span>
	            </div>
	          </div>

	          <div class="portlet-body">

	            <div class="row">
	              <div class="col-md-9">
					'.$langs->trans("NbOfActiveInstances").'
	              </div>
	              <div class="col-md-3 right">
	                <h2>'.$nbofinstancesdone.'</h2>
	              </div>
	            </div> <!-- END ROW -->

				';
if ($nbofinstancessuspended) {
	print '
			            <div class="row">
			              <div class="col-md-9">
							'.$langs->trans("NbOfSuspendedInstances").'
			              </div>
			              <div class="col-md-3 right">
			                <h2 style="color:orange">'.$nbofinstancessuspended.'</h2>
			              </div>
			            </div> <!-- END ROW -->
					';
}

				print '
					<div class="row">
					<div class="center col-md-12">
						<br>
						<a class="wordbreak btn" href="'.$_SERVER["PHP_SELF"].'?mode=instances" class="btn default btn-xs green-stripe">'.$langs->trans("SeeDetailsAndOptions").'</a>
					</div></div>';

			print '
				</div>';		// end protlet-body

if ($mythirdpartyaccount->isareseller) {
	print '
				<div class="portlet-title">
				<div class="caption"><br><br>
				<i class="fa fa-server font-green-sharp paddingright"></i><span class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("InstancesOfMyCustomers").'</span>
				</div>
				</div>

				<div class="portlet-body">

				<div class="row">
				<div class="col-md-9">
				'.$langs->trans("NbOfActiveInstances").'
				</div>
				<div class="col-md-3 right">
				<h2>'.$nbofinstancesdonereseller.'</h2>
				</div>
				</div> <!-- END ROW -->

				';
	if ($nbofinstancessuspendedreseller) {
		print '
					<div class="row">
					<div class="col-md-9">
					'.$langs->trans("NbOfSuspendedInstances").'
					</div>
					<div class="col-md-3 right">
					<h2 style="color:orange">'.$nbofinstancessuspendedreseller.'</h2>
					</div>
					</div> <!-- END ROW -->
					';
	}

	print '
					<div class="row">
					<div class="center col-md-12">
						<br>
						<a class="wordbreak btn" href="'.$_SERVER["PHP_SELF"].'?mode=mycustomerinstances" class="btn default btn-xs green-stripe">'.$langs->trans("SeeDetailsAndOptionsOfMyCustomers").'</a>
					</div></div>';

	print '</div>';		// end protlet-body
}

			print '

	        </div> <!-- END PORTLET -->

	      </div> <!-- END COL -->

			<!-- My profile -->
	      <div class="col-md-6">
	        <div class="portlet light" id="myProfile">

	          <div class="portlet-title">
	            <div class="caption">
	              <i class="fa fa-user font-green-sharp paddingright"></i><span class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("MyAccount").'</span>
	            </div>
	          </div>

	          <div class="portlet-body">
				<div class="row">
				<div class="col-md-12">
	                ';
if (empty($welcomecid)) {		// If we just created an instance, we don't show warnings yet.
	$missing = 0;
	if (empty($mythirdpartyaccount->array_options['options_firstname'])) {
		$missing++;
	}
	if (empty($mythirdpartyaccount->array_options['options_lastname'])) {
		$missing++;
	}
	if ($mythirdpartyaccount->tva_assuj && empty($mythirdpartyaccount->tva_intra)) {
		$missing++;
	}

	if (! $missing) {
		print $langs->trans("ProfileIsComplete");
	} else {
		print $langs->trans("ProfileIsNotComplete", $missing, $_SERVER["PHP_SELF"].'?mode=myaccount');
		print ' '.img_warning();
	}
}
					print '
	            </div>
				</div>

				<div class="row">
				<div class="center col-md-12">
					<br>
					<a class="wordbreak btn" href="'.$_SERVER["PHP_SELF"].'?mode=myaccount" class="btn default btn-xs green-stripe">'.$langs->trans("SeeOrEditProfile").'</a>
				</div>
				</div>

	          </div> <!-- END PORTLET-BODY -->

	        </div> <!-- END PORTLET -->
	      </div><!-- END COL -->


	    </div> <!-- END ROW -->

	';

	print '
	    <div class="row">

			<!-- Box of payment balance -->
	      <div class="col-md-6">
	        <div class="portlet light" id="paymentBalance">

	          <div class="portlet-title">
	            <div class="caption">
	              <i class="fa fa-usd font-green-sharp paddingright"></i><span class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("PaymentBalance").'</span>
	            </div>
	          </div>';

				//var_dump($contract->linkedObjects['facture']);
				//dol_sort_array($contract->linkedObjects['facture'], 'date');
				$nbinvoicenotpayed = 0;
				$amountdue = 0;
foreach ($listofcontractid as $id => $contract) {
	$contract->fetchObjectLinked();
	if (isset($contract->linkedObjects['facture']) && is_array($contract->linkedObjects['facture'])) {
		foreach ($contract->linkedObjects['facture'] as $idinvoice => $invoice) {
			print '<!--';
			print dol_escape_htmltag($invoice->ref.'-'.$invoice->total_ht."-".$invoice->type."-status=".$invoice->statut."-paye=".$invoice->paye)."\n";
			print '-->';
			if ($invoice->statut == $invoice::STATUS_DRAFT) {
				continue;
			}
			if ($invoice->statut == $invoice::STATUS_VALIDATED) {
				$nbinvoicenotpayed++;
				$alreadypayed = $invoice->getSommePaiement();
				$amount_credit_notes_included = $invoice->getSumCreditNotesUsed();
				$amountdue += $invoice->total_ttc - $alreadypayed - $amount_credit_notes_included;
			}
		}
	}
}
				print '
	          <div class="portlet-body">

				<div class="row">
				<div class="col-md-9">
	            ';
if ($amountdue > 0 && $atleastonepaymentmode) {
	print $form->textwithpicto($langs->trans("UnpaidInvoices"), $langs->trans("PaymentWillBeProcessedSoon"));
} else {
	print $langs->trans("UnpaidInvoices");
}
				print '
                				</div>
                				<div class="col-md-3 right"><h2>';
if ($nbinvoicenotpayed > 0) {
	print '<font style="color: orange">';
}
				print $nbinvoicenotpayed;
if ($nbinvoicenotpayed) {
	print '</font>';
}
				print '<h2></div>
                	            </div>
                				<div class="row">
                				<div class="col-md-9">';
if ($amountdue > 0 && $atleastonepaymentmode) {
	print $form->textwithpicto($langs->trans("RemainderToPay"), $langs->trans("PaymentWillBeProcessedSoon"));
} else {
	print $langs->trans("RemainderToPay");
}
				print '</div>
                				<div class="col-md-3 right"><h2>';
if ($amountdue > 0) {
	print '<font style="color: orange; white-space: nowrap;">';
}
				print price($amountdue, 1, $langs, 0, -1, $conf->global->MAIN_MAX_DECIMALS_TOT, $conf->currency);
if ($amountdue > 0) {
	print '</font>';
}
				print '</h2></div>
	            </div>

				<div class="row">
				<div class="center col-md-12">
					<br>
					<a class="wordbreak btn" href="'.$_SERVER["PHP_SELF"].'?mode=billing" class="btn default btn-xs green-stripe">'.$langs->trans("SeeDetailsOfPayments").'</a>
				</div>
				</div>

	          </div> <!-- END PORTLET-BODY -->

	        </div> <!-- END PORTLET -->
	      </div><!-- END COL -->';

			$sellyoursaassupporturl = getDolGlobalString('SELLYOURSAAS_SUPPORT_URL');
if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
					&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
	$newnamekey = 'SELLYOURSAAS_SUPPORT_URL_'.strtoupper(str_replace('.', '_', $mythirdpartyaccount->array_options['options_domain_registration_page']));
	if (! empty($conf->global->$newnamekey)) {
		$sellyoursaassupporturl = $conf->global->$newnamekey;
	}
}

if (!$sellyoursaassupporturl) {
	$nboftickets = 0;
	$nbofopentickets = 0;

	print '
				<!-- Box of tickets -->
				<div class="col-md-6">
					<div class="portlet light" id="boxOfTickets">
						<div class="portlet-title">
							<div class="caption">
								<i class="fa fa-hands-helping font-green-sharp paddingright"></i><span class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("SupportTickets").'</span>
							</div>
						</div>
						<div class="portlet-body">
							<div class="row">
								<div class="col-md-9">
									'.$langs->trans("NbOfTickets").'
								</div>
								<div class="col-md-3 right">
									<h2>'.$nboftickets.'</h2>
								</div>
							</div> <!-- END ROW -->
							<div class="row">
								<div class="col-md-9">
									'.$langs->trans("NbOfOpenTickets").'
								</div>
								<div class="col-md-3 right">
									<h2>';
	if ($nbofopentickets > 0) {
		print '<font style="color: orange;">';
	}
	print $nbofopentickets;
	if ($nbofopentickets > 0) {
		print '</font>';
	}
	print '</h2>
								</div>
							</div> <!-- END ROW -->
							<div class="row">
								<div class="center col-md-12">
									<br />
									<a class="wordbreak btn" href="'.$_SERVER["PHP_SELF"].'?mode=support" class="btn default btn-xs green-stripe">'.$langs->trans("SeeDetailsOfTickets").'</a>
								</div>
							</div>
						</div> <!-- END PORTLET-BODY -->
					</div> <!-- END PORTLET boxOfTickets -->
				</div><!-- END COL -->';
}

	print '
				</div> <!-- END ROW -->
			</div>
		</div>
	</div>
	';
?>
<!-- END PHP TEMPLATE dashboard.tpl.php -->
