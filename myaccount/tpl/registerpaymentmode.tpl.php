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

$langs->load("banks");

?>
<!-- BEGIN PHP TEMPLATE registerpaymentmode.tpl.php -->
<?php

print '<!-- mode = registerpaymentmode -->
	<div class="page-content-wrapper">
		<div class="page-content">


		<!-- BEGIN PAGE HEADER-->
		<!-- BEGIN PAGE HEAD -->
		<div class="page-head">
		  <!-- BEGIN PAGE TITLE -->
		<div class="page-title">
		  <h1>'.$langs->trans("PaymentMode").'<br><small>'.$langs->trans("SetANewPaymentMode").'</small></h1>
		</div>
		<!-- END PAGE TITLE -->
		</div>
		<!-- END PAGE HEAD -->
		<!-- END PAGE HEADER-->


	    <div class="row">
		<div class="col-md-12 center">
		<div class="portlet light">

		<div class="portlet-body">';


print '<!-- Form payment-form STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION = '.$conf->global->STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION.' STRIPE_USE_NEW_CHECKOUT = '.$conf->global->STRIPE_USE_NEW_CHECKOUT.' -->'."\n";
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST" id="payment-form">'."\n";

print '<input type="hidden" name="token" value="'.newToken().'">'."\n";
print '<input type="hidden" name="action" value="createpaymentmode">'."\n";
print '<input type="hidden" name="mode" value="registerpaymentmode">'."\n";
print '<input type="hidden" name="backtourl" value="'.$backtourl.'">';
//print '<input type="hidden" name="thirdparty_id" value="'.$mythirdpartyaccount->id.'">';

$tmp = $mythirdpartyaccount->getOutstandingBills('customer');
$outstandingTotalIncTax = $tmp['opened'];
$outstandingRefs = $tmp['refsopened'];
$totalInvoiced = $tmp['total_ttc'];

// If thirdparty is not yet a customer (no payment never done), we show him the amount to pay in its first invoice.
if ($totalInvoiced == 0) {
	// Loop on contracts
	$amounttopayasfirstinvoice = 0;
	$amounttopayasfirstinvoicetinstances = array();
	foreach ($listofcontractid as $contract) {
		if ($contract->array_options['options_deployment_status'] == 'done') {
			$sellyoursaasutils = new SellYourSaasUtils($db);

			$comment = 'Refresh contract '.$contract->ref.' on the payment page to be able to show the correct amount to pay';
			// First launch update of resources:
			// This update status of install.lock+authorized key (but does not recreate them) and update qty of contract lines + qty into linked template invoice
			$result = $sellyoursaasutils->sellyoursaasRemoteAction('refresh', $contract, 'admin', '', '', '0', $comment);
			$contract->fetch($contract->id);   // Reload to get new values after refresh

			$amounttopayasfirstinvoice += $contract->total_ttc;
			$amounttopayasfirstinvoicetinstances[$contract->ref_customer] = $contract;
		}
	}

	$defaultdiscountcode = GETPOST('discountcode', 'aZ09');
	$acceptdiscountcode = ($conf->global->SELLYOURSAAS_ACCEPT_DISCOUNTCODE ? 1 : 0);

	// We are not yet a customer
	if ($amounttopayasfirstinvoice) {
		print '<div class="opacitymedium firstpaymentmessage"><small>'.$langs->trans("AFirstInvoiceOfWillBeDone", price($amounttopayasfirstinvoice, 0, $langs, 1, -1, -1, $conf->currency));
		if (count($amounttopayasfirstinvoicetinstances) >= 2) {    // If 2 instances
			print ' (';
			$i = 0;
			foreach ($amounttopayasfirstinvoicetinstances as $key => $tmpcontracttopay) {
				if ($i) print ', ';
				print '<strong>'.$key.'</strong>: '.price($tmpcontracttopay->total_ttc, 0, $langs, 1, -1, -1, $conf->currency);
				$i++;
			}
			print ')';
		} else {
			$parenthesisopen = 0;
			if (count($amounttopayasfirstinvoicetinstances) == 1) {   // If 1 instance to pay (the most common case)
				foreach ($amounttopayasfirstinvoicetinstances as $key => $tmpcontracttopay) {
					$parenthesisopen = 1;
					print ' ('.$langs->trans("Instance").': <strong>'.$key.'</strong>';
					// If there is only one contract waiting for payment, we can get the discount code of it, if there is one and if a value is not already provided in POST.
					if (! GETPOSTISSET('discountcode')) {
						$defaultdiscountcode = $tmpcontracttopay->array_options['options_discountcode'];
					}
				}
			}

			$urlforplanprices = $conf->global->SELLYOURSAAS_PRICES_URL;
			if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
				&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
				$newnamekey = 'SELLYOURSAAS_PRICES_URL_'.strtoupper(str_replace('.', '_', $mythirdpartyaccount->array_options['options_domain_registration_page']));
				$urlforplanprices = $conf->global->$newnamekey;
			}

			if ($urlforplanprices) {
				print ' - ';
				print $langs->trans("SeeOurPrices", $urlforplanprices);
			}

			if ($parenthesisopen) {
				print ')';
			} else {
				print '.';
			}
		}
		print '</small></div>';
		print '<br>';

		// Show input text for the discount code
		if ($acceptdiscountcode) {
			print '<br>';
			print $langs->trans("DiscountCode").': <input type="text" name="discountcode" id="discountcode" value="'.$defaultdiscountcode.'" class="maxwidth200"><br>';
			print '<div class="discountcodetext margintoponly" id="discountcodetext" autocomplete="off"></div>';
			//var_dump($listofcontractid);
			print '<script type="text/javascript" language="javascript">'."\n";
			print '
						jQuery(document).ready(function() {
	        	    		jQuery("#discountcode").keyup(function() {
	        	    			console.log("Discount code modified, we update the text section");
								if (jQuery("#discountcode").val()) {
									var text = jQuery("#discountcode").val();
									if (text.length >= 3) {
										var url = "ajax/ajaxdiscount.php";
										$.getJSON( url, {
										    contractids: "'.join(',', array_keys($listofcontractid)).'",
										    discountcode: text,
										    format: "json"
										})
									    .done(function( data ) {
											console.log(data.discountcodetext);
											jQuery("#discountcodetext").html(data.discountcodetext);
										});
									}
								} else {
									jQuery("#discountcodetext").html("");
								}
	        	    		});
	        	    	});';

			print '</script>';
			print '<hr>';
		}
		print '<br>';
	} else {
		if (count($amounttopayasfirstinvoicetinstances) > 0) {
			// The amount to pay is 0 but there is at least one instance, it means all deployed instances are free.
			print '<div class="opacitymedium firstpaymentmessage"><small>'.$langs->trans("NoPayableInstanceYetOnlyFree").'</small></div>';
		} else {
			// There is no instance at all
			print '<div class="opacitymedium firstpaymentmessage"><small>'.$langs->trans("NoInstanceYet").'</small></div>';
		}
		print '<br><br>';
	}
} else {	// There is already some invoices. This is already a customer.
	if ($outstandingTotalIncTax) {
		print '<div class="opacitymedium firstpaymentmessage"><small>'.$langs->trans("ThePaymentModeWillBeUseToPayYourDueAmount", join(', ', $outstandingRefs), price($outstandingTotalIncTax, 0, $langs, 1, -1, -1, $conf->currency));
		print '</small></div>';
		print '<br>';
	}
}

print '
		<div class="radio-list">
		<label class="radio-inline" style="margin-right: 0px" id="linkcard">
		<div class="radio inline-block"><span class="checked">'.$langs->trans("CreditOrDebitCard").'<input type="radio" name="type" value="card" checked></span></div><br>
		<img src="/img/mastercard.png" width="50" height="31">
		<img src="/img/visa.png" width="50" height="31">
		<img src="/img/american_express.png" width="50" height="31">
		</label>
		<!--
		<label class="radio-inline" id="linkpaypal" style="margin-left: 40px;">
		<div class="radio inline-block"><span>'.$langs->trans("PayPal").'<input type="radio" name="type" value="PayPal"></span></div><br>
		<img src="/img/paypal.png" width="50" height="31">
		</label>
		-->
		<label class="radio-inline" id="linksepa" style="margin-left: 30px;">
		<div class="radio inline-block"><span>'.$langs->trans("SEPAMandate").'<input type="radio" name="type" value="SepaMandate"></span></div><br>
		<img src="/img/sepa.png" width="50" height="31">
		</label>
		</div>

		<br>

		<div class="linkcard">';


$foundcard=0;
// Check if there is already a payment
foreach ($arrayofcompanypaymentmode as $companypaymentmodetemp) {
	if ($companypaymentmodetemp->type == 'card') {
		$foundcard++;
		print '<hr>';
		print '<div class="marginbottomonly">'.img_credit_card($companypaymentmodetemp->type_card, 'marginrightonlyimp');
		print '<span class="opacitymedium">'.$langs->trans("CurrentCreditOrDebitCard").'</span></div>';
		print '<!-- companypaymentmode id = '.$companypaymentmodetemp->id.' -->';
		print '....'.$companypaymentmodetemp->last_four;
		print ' - ';
		print sprintf("%02d", $companypaymentmodetemp->exp_date_month).'/'.$companypaymentmodetemp->exp_date_year;
		// Warning if expiring
		if ($companypaymentmodetemp->exp_date_year < $nowyear ||
		($companypaymentmodetemp->exp_date_year == $nowyear && $companypaymentmodetemp->exp_date_month <= $nowmonth)) {
			print '<br>';
			print img_warning().' '.$langs->trans("YourPaymentModeWillExpireFixItSoon", $urltoenterpaymentmode);
		}
	}
}
if ($foundcard) {
	print '<hr>';
	print '<div class="marginbottomonly">'.img_credit_card($companypaymentmodetemp->type_card, 'marginrightonlyimp');
	print '<span class="opacitymedium">'.$langs->trans("NewCreditOrDebitCard").'</span></div>';
}


if (! empty($conf->global->STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION)) {	// Use a SCA ready method
	$fulltag='CUS='.$mythirdpartyaccount->id;
	$fulltag=dol_string_unaccent($fulltag);

	require_once DOL_DOCUMENT_ROOT.'/stripe/class/stripe.class.php';

	$service = 'StripeLive';
	$servicestatus = 1;

	if (empty($conf->global->STRIPE_LIVE) || GETPOST('forcesandbox', 'alpha')) {
		$service = 'StripeTest';
		$servicestatus = 0;
	}
	$stripe = new Stripe($db);
	$stripeacc = $stripe->getStripeAccount($service);
	$stripecu = null;
	$stripecu = $stripe->customerStripe($mythirdpartyaccount, $stripeacc, $servicestatus, 1); // will use $stripearrayofkeysbyenv to know which env to search into

	if (! empty($conf->global->STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION)) {
		$setupintent=$stripe->getSetupIntent('Stripe setupintent '.$fulltag, $mythirdpartyaccount, $stripecu, $stripeacc, $servicestatus);
		if ($stripe->error) {
			setEventMessages($stripe->error, null, 'errors');

			$emailforerror = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
			if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
				&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
				$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
				$emailforerror = $conf->global->$newnamekey;
			}

			setEventMessages($langs->trans("ErrorContactEMail", $emailforerror, 'StripeCusNotFound'), null, 'errors');
		}
	}
}


print '<div class="row"><div class="col-md-12"><label class="valignmiddle" style="margin-bottom: 20px">'.$langs->trans("NameOnCard").':</label> ';
print '<input id="cardholder-name" class="minwidth200 valignmiddle" style="margin-bottom: 15px" type="text" name="proprio" value="'.GETPOST('proprio', 'alpha').'" autocomplete="off" autofocus>';
print '</div></div>';

require_once DOL_DOCUMENT_ROOT.'/stripe/config.php';
// Reforce the $stripearrayofkeys because content may have been changed by the include of config.php
if (empty($conf->global->STRIPE_LIVE) || GETPOST('forcesandbox', 'alpha') || ! empty($conf->global->SELLYOURSAAS_FORCE_STRIPE_TEST)) {
	$stripearrayofkeys = $stripearrayofkeysbyenv[0];	// Test
} else {
	$stripearrayofkeys = $stripearrayofkeysbyenv[1];	// Live
}

print '	<center><div class="form-row" style="max-width: 320px">

		<div id="card-element">
		<!-- A Stripe Element will be inserted here. -->
		</div>

		<!-- Used to display form errors. -->
		<div id="card-errors" role="alert"></div>

		</div></center>
        ';

print '<br>';

if (! empty($conf->global->STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION) && is_object($setupintent)) {
	print '<input type="hidden" name="setupintentid" value="'.$setupintent->id.'">'."\n";
	print '<button class="btn btn-info btn-circle" id="buttontopay" data-secret="'.$setupintent->client_secret.'">'.$langs->trans("Save").'</button>';
} else {
	print '<button class="btn btn-info btn-circle" id="buttontopay">'.$langs->trans("Save").'</button>';
}

print '<img id="hourglasstopay" class="hidden" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/working.gif">';
print ' ';
print '<a id="buttontocancel" href="'.($backtourl ? $backtourl : $_SERVER["PHP_SELF"]).'" class="btn green-haze btn-circle">'.$langs->trans("Cancel").'</a>';

if (! empty($conf->global->STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION) && is_object($setupintent)) {
	// TODO Enable this legal mention for SCA
	/*$urlfortermofuse = '';
	 if ($conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME == 'dolicloud.com')
	 {
	 $urlfortermofuse = 'https://www.'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/en-terms-and-conditions.php';
	 if (preg_match('/^fr/i', $langs->defaultlang)) $urlfortermofuse = 'https://www.'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/fr-conditions-utilisations.php';
	 if (preg_match('/^es/i', $langs->defaultlang)) $urlfortermofuse = 'https://www.'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/es-terminos-y-condiciones.php';
	 }
	 if ($urlfortermofuse)
	 {
	 print '<br><br><span class="opacitymedium"><small>';
	 print $langs->trans('By entering my credit card number, I authorise to send instructions to the financial institution that issued my card to take payments from my card account for my subscription, in accordance with the terms of the <a href="'.$urlfortermofuse.'" target="_blank">General Terms of Service (GTS)</a>');
	 print '</small></span><br>';
	 }*/
}

print '<script src="https://js.stripe.com/v3/"></script>'."\n";

// Code to ask the credit card. This use the default "API version". No way to force API version when using JS code.
print '<script type="text/javascript" language="javascript">'."\n";

if (! empty($conf->global->STRIPE_USE_NEW_CHECKOUT)) {
	// Not implemented
} elseif (! empty($conf->global->STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION)) {
	?>
			// Code for payment with option STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION set

			// Create a Stripe client.
			var stripe = Stripe('<?php echo $stripearrayofkeys['publishable_key']; // Defined into config.php ?>');

			// Create an instance of Elements
			var elements = stripe.elements();

			// Custom styling can be passed to options when creating an Element.
			// (Note that this demo uses a wider set of styles than the guide below.)
			var style = {
			  base: {
				color: '#32325d',
				lineHeight: '24px',
				fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
				fontSmoothing: 'antialiased',
				fontSize: '16px',
				'::placeholder': {
				  color: '#aab7c4'
				}
			  },
			  invalid: {
				color: '#fa755a',
				iconColor: '#fa755a'
			  }
			};

			var cardElement = elements.create('card', {style: style});

			// Add an instance of the card Element into the `card-element` <div>
			cardElement.mount('#card-element');

			// Handle real-time validation errors from the card Element.
			cardElement.addEventListener('change', function(event) {
				var displayError = document.getElementById('card-errors');
				  if (event.error) {
					  console.log("Show event error (like 'Incorrect card number', ...)");
					displayError.textContent = event.error.message;
				  } else {
					  console.log("Reset error message");
					displayError.textContent = '';
				  }
			});

			// Handle form submission
			var cardholderName = document.getElementById('cardholder-name');
			var cardButton = document.getElementById('buttontopay');
			var clientSecret = cardButton.dataset.secret;

			cardButton.addEventListener('click', function(event) {
				console.log("We click on buttontopay");
				event.preventDefault();

				if (cardholderName.value == '')
				{
					console.log("Field Card holder is empty");
					var displayError = document.getElementById('card-errors');
					displayError.textContent = '<?php print dol_escape_js($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("NameOnCard"))); ?>';
				}
				else
				{
				  stripe.handleCardSetup(
					clientSecret, cardElement, {
						payment_method_data: {
							billing_details: {
								name: cardholderName.value
								<?php if (GETPOST('email', 'alpha') || ! empty($mythirdpartyaccount->email)) { ?>, email: '<?php echo dol_escape_js(GETPOST('email', 'alpha') ? GETPOST('email', 'alpha') : $mythirdpartyaccount->email); ?>'<?php } ?>
								<?php if (! empty($mythirdpartyaccount->phone)) { ?>, phone: '<?php echo dol_escape_js($mythirdpartyaccount->phone); ?>'<?php } ?>
								<?php if (is_object($mythirdpartyaccount)) { ?>, address: {
									city: '<?php echo dol_escape_js($mythirdpartyaccount->town); ?>',
									country: '<?php echo dol_escape_js($mythirdpartyaccount->country_code); ?>',
									line1: '<?php echo dol_escape_js($mythirdpartyaccount->address); ?>',
									postal_code: '<?php echo dol_escape_js($mythirdpartyaccount->zip); ?>'}<?php } ?>
							}
						  }
					}
				  ).then(function(result) {
						console.log(result);
					  if (result.error) {
						  console.log("Error on result of handleCardPayment");
						  jQuery('#buttontopay').show();
						  jQuery('#hourglasstopay').hide();
						  // Inform the user if there was an error
						  var errorElement = document.getElementById('card-errors');
						  errorElement.textContent = result.error.message;
					  } else {
							// The payment has succeeded. Display a success message.
						  console.log("No error on result of handleCardPayment, so we submit the form");
						  // Submit the form
						  jQuery('#buttontopay').hide();
						  jQuery('#buttontocancel').hide();
						  jQuery('#hourglasstopay').show();
						  jQuery('#hourglasstopay').removeClass('hidden');
						  // Send form (action=createpaymentmode)
						  jQuery('#payment-form').submit();
					  }
				  });
				}
			});

		<?php
} else { // Old method (not SCA ready)
	print "
            	// Old code for payment with option STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION off and STRIPE_USE_NEW_CHECKOUT off

    			// Create a Stripe client.
    			var stripe = Stripe('".$stripearrayofkeys['publishable_key']."');		/* Defined into config.php */

    			// Create an instance of Elements.
    			var elements = stripe.elements();

    			// Custom styling can be passed to options when creating an Element.
    			// (Note that this demo uses a wider set of styles than the guide below.)
    			var style = {
    			  base: {
    			    color: '#32325d',
    			    lineHeight: '18px',
    			    fontFamily: '\"Helvetica Neue\", Helvetica, sans-serif',
    			    fontSmoothing: 'antialiased',
    			    fontSize: '16px',
    			    '::placeholder': {
    			      color: '#aab7c4'
    			    }
    			  },
    			  invalid: {
    			    color: '#fa755a',
    			    iconColor: '#fa755a'
    			  }
    			};

    			// Create an instance of the card Element.
    			var card = elements.create('card', {style: style});

    			// Add an instance of the card Element into the `card-element` <div>.
    			card.mount('#card-element');

    			// Handle real-time validation errors from the card Element.
    			card.addEventListener('change', function(event) {
    			  var displayError = document.getElementById('card-errors');
    			  if (event.error) {
    			    displayError.textContent = event.error.message;
    			  } else {
    			    displayError.textContent = '';
    			  }
    			});

    			// Handle form submission.
    			var form = document.getElementById('payment-form');
    			form.addEventListener('submit', function(event) {
    			  event.preventDefault();";
	if (empty($conf->global->STRIPE_USE_3DSECURE)) {	// Ask credit card directly, no 3DS test
		?>
						/* Use token */
						stripe.createToken(card).then(function(result) {
							if (result.error) {
							  // Inform the user if there was an error
							  var errorElement = document.getElementById('card-errors');
							  errorElement.textContent = result.error.message;
							} else {
							  // Send the token to your server
							  stripeTokenHandler(result.token);
							}
						});
		<?php
	} else { // Ask credit card with 3DS test
		?>
						/* Use 3DS source */
						stripe.createSource(card).then(function(result) {
							if (result.error) {
							  // Inform the user if there was an error
							  var errorElement = document.getElementById('card-errors');
							  errorElement.textContent = result.error.message;
							} else {
							  // Send the source to your server
							  stripeSourceHandler(result.source);
							}
						});
		<?php
	}
	print "
    			});


    			/* Insert the Token into the form so it gets submitted to the server */
    		    function stripeTokenHandler(token) {
    		      // Insert the token ID into the form so it gets submitted to the server
    		      var form = document.getElementById('payment-form');

    		      var hiddenInput = document.createElement('input');
    		      hiddenInput.setAttribute('type', 'hidden');
    		      hiddenInput.setAttribute('name', 'stripeToken');
    		      hiddenInput.setAttribute('value', token.id);
    		      form.appendChild(hiddenInput);

    			  var hiddenInput2 = document.createElement('input');
    			  hiddenInput2.setAttribute('type', 'hidden');
    			  hiddenInput2.setAttribute('name', 'token');
                  hiddenInput2.setAttribute('value', '".$_SESSION["newtoken"]."');
    			  form.appendChild(hiddenInput2);

    		      // Submit the form
    		      jQuery('#buttontopay').hide();
    		      jQuery('#buttontocancel').hide();
    		      jQuery('#hourglasstopay').show();
    		      console.log('submit token');
    		      form.submit();
    		    }

    			/* Insert the Source into the form so it gets submitted to the server */
    			function stripeSourceHandler(source) {
    			  // Insert the source ID into the form so it gets submitted to the server
    			  var form = document.getElementById('payment-form');

    			  var hiddenInput = document.createElement('input');
    			  hiddenInput.setAttribute('type', 'hidden');
    			  hiddenInput.setAttribute('name', 'stripeSource');
    			  hiddenInput.setAttribute('value', source.id);
    			  form.appendChild(hiddenInput);

    			  var hiddenInput2 = document.createElement('input');
    			  hiddenInput2.setAttribute('type', 'hidden');
    			  hiddenInput2.setAttribute('name', 'token');
                  hiddenInput2.setAttribute('value', '".$_SESSION["newtoken"]."');
    			  form.appendChild(hiddenInput2);

    			  // Submit the form
    		      jQuery('#buttontopay').hide();
    		      jQuery('#buttontocancel').hide();
    		      jQuery('#hourglasstopay').show();
    		      console.log('submit form with source');
    			  form.submit();
    			}

    			";
}

print '</script>';


print '
	</div>


	<div class="linkpaypal" style="display: none;">';
print '<br>';
//print $langs->trans("PaypalPaymentModeAvailableForYealySubscriptionOnly");
print $langs->trans("PaypalPaymentModeNotYetAvailable");
print '<br><br>';
//print '<input type="submit" name="submitpaypal" value="'.$langs->trans("Continue").'" class="btn btn-info btn-circle">';
print ' ';
print '<input type="submit" name="cancel" value="'.$langs->trans("Cancel").'" class="btn green-haze btn-circle">';

print '
	</div>';


// SEPA Payment mode

print '
	<!-- SEPA Payment mode -->
	<div class="linksepa" style="display: none;">
		<div class="center quatrevingtpercent center">';
if ($mythirdpartyaccount->isInEEC()) {
	$foundban=0;

	// Check if there is already a payment
	foreach ($arrayofcompanypaymentmode as $companypaymentmodetemp) {
		if ($companypaymentmodetemp->type == 'ban') {
			/*print img_picto('', 'bank', '',  false, 0, 0, '', 'fa-2x');
			print '<span class="wordbreak" style="word-break: break-word" colspan="2">';
			print $langs->trans("WithdrawalReceipt");
			print '</span>';
			print '<br>';*/

			print '<hr>';
			print '<div class="marginbottomonly">'.img_picto('', 'bank_account', 'class="marginrightonlyimp"');
			print '<span class="opacitymedium">'.$langs->trans("CurrentBAN").'</span></div>';
			print '<!-- companypaymentmode id = '.$companypaymentmodetemp->id.' -->';
			print '<b>'.$langs->trans("IBAN").'</b>: '.$companypaymentmodetemp->iban.'<br>';
			print '<b>'.$langs->trans("BIC").'</b>: '.$companypaymentmodetemp->bic.'<br>';
			if ($companypaymentmodetemp->rum) {
				print '<b>'.$langs->trans("RUM").'</b>: '.$companypaymentmodetemp->rum;
			}
			$foundban++;

			//print $langs->trans("FindYourSEPAMandate");

			$companybankaccounttemp = new CompanyBankAccount($db);

			include_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
			$ecmfile = new EcmFiles($db);
			$result = $ecmfile->fetch(0, '', '', '', '', $companybankaccounttemp->table_element, $companypaymentmodetemp->id);
			if ($result > 0) {
				$companybankaccounttemp->last_main_doc = $ecmfile->filepath.'/'.$ecmfile->filename;
				print '<br><!-- Link to download main doc -->'."\n";
				$publicurltodownload = $companybankaccounttemp->getLastMainDocLink($object->element, 0, 1);

				$sellyoursaasaccounturl = $conf->global->SELLYOURSAAS_ACCOUNT_URL;
				include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
				$sellyoursaasaccounturl = preg_replace('/'.preg_quote(getDomainFromURL($conf->global->SELLYOURSAAS_ACCOUNT_URL, 1), '/').'/', getDomainFromURL($_SERVER["SERVER_NAME"], 1), $sellyoursaasaccounturl);

				$urltouse=$sellyoursaasaccounturl.'/'.(DOL_URL_ROOT?DOL_URL_ROOT.'/':'').$publicurltodownload;
				//print img_mime('sepa.pdf').'  <a href="'.$urltouse.'" target="_download">'.$langs->trans("DownloadTheSEPAMandate").'</a><br>';
			}

			print '<hr>';
		}
	}

	$enabledformtoentersepa = getDolGlobalString('SELLYOURSAAS_ENABLE_SEPA');
	$enabledformtoentersepaforids = explode(',', getDolGlobalString('SELLYOURSAAS_ENABLE_SEPA_FOR_THIRDPARTYID'));	// To test by enabling only on a given thirdparty, use SELLYOURSAAS_ENABLE_SEPA_FOR_THIRDPARTYID = id of thirparty.
	//$enabledformtoentersepa = 1;

	if ($enabledformtoentersepa || in_array($mythirdpartyaccount->id, $enabledformtoentersepaforids)) {
		// Form to enter SEPA
		print '<br>';
		print '<div class="marginbottomonly">'.img_picto('', 'bank_account', 'class="marginrightonlyimp"');
		print '<span class="opacitymedium">'.$langs->trans("NewBAN").'</div>';
		print '<table class="center">';
		print '<tr><td class="minwidth100 valignmiddle start bold">'.$langs->trans("BankName").' </td><td class="valignmiddle start"><input type="text" class="maxwidth150onsmartphone" name="bankname" id="bankname" value="'.dol_escape_htmltag($bankname).'"></td></tr>';
		print '<tr><td class="minwidth100 valignmiddle start bold">'.$langs->trans("IBAN").' </td><td class="valignmiddle start"><input type="text" class="maxwidth150onsmartphone width250" name="iban" id="iban" value="'.dol_escape_htmltag($iban).'"></td></tr>';
		print '<tr><td class="minwidth100 valignmiddle start bold">'.$langs->trans("BIC").' </td><td class="valignmiddle start"><input type="text" name="bic" id="bic" value="'.dol_escape_htmltag($bic).'" class="maxwidth150"></td></tr>';
		print '</table>';

		print '<br>';

		print '<div class="opacitymedium small justify">'.$langs->trans("SEPALegalText", $mysoc->name, $mysoc->name).'</div>';

		print '<br><br>';
		print '<input type="submit" name="submitsepa" value="'.$langs->trans("Save").'" class="btn btn-info btn-circle">';
		print ' ';
		print '<a id="buttontocancel" href="'.($backtourl ? $backtourl : $_SERVER["PHP_SELF"]).'" class="btn green-haze btn-circle">'.$langs->trans("Cancel").'</a>';
	} else {
		if (! $foundban) {
			print '<br>';
			//print $langs->trans("SEPAPaymentModeAvailableForYealyAndCeeSubscriptionOnly");
			print $langs->trans("SEPAPaymentModeAvailableNotYetAvailable");
		}

		print '<br><br>';
		//print '<input type="submit" name="submitsepa" value="'.$langs->trans("Continue").'" class="btn btn-info btn-circle">';
		print ' ';
		//print '<input type="submit" name="cancel" value="'.$langs->trans("Cancel").'" class="btn green-haze btn-circle">';
		print '<a id="buttontocancel" href="'.($backtourl ? $backtourl : $_SERVER["PHP_SELF"]).'" class="btn green-haze btn-circle">'.$langs->trans("Cancel").'</a>';
	}
} else {
	print '<br>';
	print $langs->trans("SEPAPaymentModeAvailableForCeeOnly", $mythirdpartyaccount->country);
	print '<br><br>';
	print ' ';
	//print '<input type="submit" name="cancel" value="'.$langs->trans("Cancel").'" class="btn green-haze btn-circle">';
	print '<a id="buttontocancel" href="'.($backtourl ? $backtourl : $_SERVER["PHP_SELF"]).'" class="btn green-haze btn-circle">'.$langs->trans("Cancel").'</a>';
}

print '
		</div>
		</div>	<!-- end of div class="linksepa" -->

		</form>
		</div>

		</div></div></div>

	    </div>
		</div>
	';

	print '<script type="text/javascript" language="javascript">
		jQuery(document).ready(function() {
			jQuery("#linkcard").click(function() {
				console.log("Click on linkcard");
				jQuery(".linkcard").show();
				jQuery(".linkpaypal").hide();
				jQuery(".linksepa").hide();
				jQuery("#cardholder-name").focus();
			});
			jQuery("#linkpaypal").click(function() {
				console.log("Click on linkpaypal");
				jQuery(".linkcard").hide();
				jQuery(".linkpaypal").show();
				jQuery(".linksepa").hide();
			});
			jQuery("#linksepa").click(function() {
				console.log("Click on linksepa");
				jQuery(".linkcard").hide();
				jQuery(".linkpaypal").hide();
				jQuery(".linksepa").show();
				jQuery("#bankname").focus();
			});';
if (GETPOST('type', 'aZ09') == 'SepaMandate') {
	print 'jQuery("#linksepa").trigger("click");';
}
	print '
		});
		</script>';

?>
<!-- END PHP TEMPLATE registerpaymentmode.tpl.php -->
