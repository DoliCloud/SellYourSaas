<?php
/* Copyright (C) 2023 Laurent Destailleur <eldy@users.sourceforge.net>
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

// $listofcontractid must be defined
// $error must be defined
// $paymentmode must be defined to 'card' or 'ban'
// $backurl
// $thirdpartyhadalreadyapaymentmode
// $langscompany

dol_include_once('/sellyoursaas/class/sellyoursaasutils.class.php');
if (!is_object($sellyoursaasutils)) {
	$sellyoursaasutils = new SellYourSaasUtils($db);
}

// Create a recurring invoice (+real invoice + contract renewal) for all contracts of the customer, if there is no recurring invoice yet
if (! $error) {
	foreach ($listofcontractid as $contract) {
		dol_syslog("--- Create recurring invoice on contract contract_id = ".$contract->id." if it does not have yet.", LOG_DEBUG, 0);

		if (preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
			dol_syslog("--- This contract is a redirection, we discard this contract", LOG_DEBUG, 0);
			continue;							// If contract is a redirection, we discard check and creation of any recurring invoice
		}
		if ($contract->array_options['options_deployment_status'] != 'done') {
			dol_syslog("--- Deployment status is not 'done', we discard this contract", LOG_DEBUG, 0);
			continue;							// This is a not valid contract (undeployed or not yet completely deployed), so we discard this contract to avoid to create template not expected
		}
		if ($contract->total_ht == 0) {
			dol_syslog("--- Amount is null, we discard this contract", LOG_DEBUG, 0);
			continue;							// Amount is null, so we do not create recurring invoice for that. Note: This should not happen.
		}

		// Make a test to pass loop if there is already a template invoice
		$result = $contract->fetchObjectLinked();
		if ($result < 0) {
			dol_syslog("--- Error during fetchObjectLinked, we discard this contract", LOG_ERR, 0);
			continue;							// There is an error, so we discard this contract to avoid to create template twice
		}
		if (! empty($contract->linkedObjectsIds['facturerec'])) {
			$templateinvoice = reset($contract->linkedObjectsIds['facturerec']);
			if ($templateinvoice > 0) {			// There is already a template invoice, so we discard this contract to avoid to create template twice
				dol_syslog("--- There is already a recurring invoice on the contract contract_id = ".$contract->id, LOG_DEBUG, 0);
				continue;
			}
		}

		dol_syslog("--- No template invoice found linked to the contract contract_id = ".$contract->id." that is NOT null, so we refresh contract before creating template invoice + creating invoice (if template invoice date is already in past) + making contract renewal.", LOG_DEBUG, 0);

		$comment = 'Refresh contract '.$contract->ref.' after entering a payment mode on dashboard, because we need to create a template invoice (case of STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION set)';
		// First launch update of resources:
		// This update qty of contract lines + qty into linked template invoice.
		$result = $sellyoursaasutils->sellyoursaasRemoteAction('refreshmetrics', $contract, 'admin', '', '', '0', $comment);

		dol_syslog("--- No template invoice found linked to the contract contract_id = ".$contract->id.", so we create it then we create real invoice (if template invoice date is already in past) then make contract renewal.", LOG_DEBUG, 0);

		// Now create invoice draft
		$dateinvoice = $contract->array_options['options_date_endfreeperiod'];
		if ($dateinvoice < $now) {
			$dateinvoice = $now;
		}

		$invoice_draft = new Facture($db);
		$tmpproduct = new Product($db);

		// Create empty invoice
		if (! $error) {
			$invoice_draft->socid				= $contract->socid;
			$invoice_draft->type				= Facture::TYPE_STANDARD;
			$invoice_draft->number				= '';
			$invoice_draft->date				= $dateinvoice;

			$invoice_draft->note_private		= 'Template invoice created after adding a payment mode for card/stripe';
			if ($paymentmode == 'ban') {
				$invoice_draft->mode_reglement_id	= dol_getIdFromCode($db, 'PRE', 'c_paiement', 'code', 'id', 1);
			} else {
				$invoice_draft->mode_reglement_id	= dol_getIdFromCode($db, 'CB', 'c_paiement', 'code', 'id', 1);
			}
			$invoice_draft->cond_reglement_id	= dol_getIdFromCode($db, 'RECEP', 'c_payment_term', 'code', 'rowid', 1);
			$invoice_draft->fk_account          = getDolGlobalInt('STRIPE_BANK_ACCOUNT_FOR_PAYMENTS');	// stripe

			$invoice_draft->fetch_thirdparty();

			$origin = 'contrat';
			$originid = $contract->id;

			$invoice_draft->origin = $origin;
			$invoice_draft->origin_id = $originid;

			// Possibility to add external linked objects with hooks
			$invoice_draft->linked_objects[$invoice_draft->origin] = $invoice_draft->origin_id;

			$idinvoice = $invoice_draft->create($user);      // This include class to add_object_linked() and add add_contact()
			if (! ($idinvoice > 0)) {
				setEventMessages($invoice_draft->error, $invoice_draft->errors, 'errors');
				$error++;
			}
		}

		$frequency=1;
		$frequency_unit='m';
		$discountcode = strtoupper(trim(GETPOST('discountcode', 'aZ09')));	// If a discount code was prodived on page
		/* If a discount code exists on contract level, it was used to prefill the payment page, so it is received into the GETPOST('discountcode', 'int').
		if (empty($discountcode) && ! empty($contract->array_options['options_discountcode'])) {    // If no discount code provided, but we find one on contract, we use this one
			$discountcode = $contract->array_options['options_discountcode'];
		}*/

		$discounttype = '';
		$discountval = 0;
		$validdiscountcodearray = array();
		$nbofproductapp = 0;

		// Add lines on invoice
		if (! $error) {
			// Add lines of contract to template invoice
			$srcobject = $contract;

			$lines = $srcobject->lines;
			if (empty($lines) && method_exists($srcobject, 'fetch_lines')) {
				$srcobject->fetch_lines();
				$lines = $srcobject->lines;
			}

			$date_start = false;
			$fk_parent_line = 0;
			$num = count($lines);
			for ($i=0; $i < $num; $i++) {
				$label=(! empty($lines[$i]->label) ? $lines[$i]->label : '');
				$desc=(! empty($lines[$i]->desc) ? $lines[$i]->desc : $lines[$i]->libelle);
				if ($invoice_draft->situation_counter == 1) {
					$lines[$i]->situation_percent =  0;
				}

				// Positive line
				$product_type = ($lines[$i]->product_type ? $lines[$i]->product_type : 0);

				// Date start
				$date_start = false;
				if ($lines[$i]->date_debut_prevue) {
					$date_start = $lines[$i]->date_debut_prevue;
				}
				if ($lines[$i]->date_debut_reel) {
					$date_start = $lines[$i]->date_debut_reel;
				}
				if ($lines[$i]->date_start) {
					$date_start = $lines[$i]->date_start;
				}

				// Date end
				$date_end = false;
				if ($lines[$i]->date_fin_prevue) {
					$date_end = $lines[$i]->date_fin_prevue;
				}
				if ($lines[$i]->date_fin_reel) {
					$date_end = $lines[$i]->date_fin_reel;
				}
				if ($lines[$i]->date_end) {
					$date_end = $lines[$i]->date_end;
				}

				// If date start is in past, we set it to now
				$now = dol_now();
				if ($date_start < $now) {
					dol_syslog("Date start is in past, so we take current date as date start and update also end date of contract", LOG_DEBUG, 0);
					$tmparray = sellyoursaasGetExpirationDate($srcobject, 0);
					$duration_value = $tmparray['duration_value'];
					$duration_unit = $tmparray['duration_unit'];

					$date_start = $now;
					$date_end = dol_time_plus_duree($now, $duration_value, $duration_unit) - 1;

					// Because we update the end date planned of contract too
					$sqltoupdateenddate = 'UPDATE '.MAIN_DB_PREFIX."contratdet SET date_fin_validite = '".$db->idate($date_end)."' WHERE fk_contrat = ".$srcobject->id;
					$resqltoupdateenddate = $db->query($sqltoupdateenddate);
				}

				// Reset fk_parent_line for no child products and special product
				if (($lines[$i]->product_type != 9 && empty($lines[$i]->fk_parent_line)) || $lines[$i]->product_type == 9) {
					$fk_parent_line = 0;
				}

				// Discount
				$discount = $lines[$i]->remise_percent;

				// Extrafields
				if (empty($conf->global->MAIN_EXTRAFIELDS_DISABLED) && method_exists($lines[$i], 'fetch_optionals')) {
					$lines[$i]->fetch_optionals($lines[$i]->rowid);
					$array_options = $lines[$i]->array_options;
				}

				$tva_tx = $lines[$i]->tva_tx;
				if (! empty($lines[$i]->vat_src_code) && ! preg_match('/\(/', $tva_tx)) {
					$tva_tx .= ' ('.$lines[$i]->vat_src_code.')';
				}

				// View third's localtaxes for NOW and do not use value from origin.
				$localtax1_tx = get_localtax($tva_tx, 1, $invoice_draft->thirdparty);
				$localtax2_tx = get_localtax($tva_tx, 2, $invoice_draft->thirdparty);

				//$price_invoice_template_line = $lines[$i]->subprice * GETPOST('frequency_multiple','int');
				$price_invoice_template_line = $lines[$i]->subprice;


				// Get data from product (frequency, discount type and val)
				$tmpproduct->fetch($lines[$i]->fk_product);

				dol_syslog("Read frequency for product id=".$tmpproduct->id, LOG_DEBUG, 0);
				if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
					// Protection to avoid to validate contract with several 'app' products.
					$nbofproductapp++;
					if ($nbofproductapp > 1) {
						dol_syslog("Error: Bad definition of contract. There is more than 1 service with type 'app'", LOG_ERR);
						$error++;
						break;
					}
					$frequency = $tmpproduct->duration_value;
					$frequency_unit = $tmpproduct->duration_unit;

					if ($tmpproduct->array_options['options_register_discountcode']) {
						$tmpvaliddiscountcodearray = explode(',', $tmpproduct->array_options['options_register_discountcode']);
						foreach ($tmpvaliddiscountcodearray as $valdiscount) {
							$valdiscountarray = explode(':', $valdiscount);
							$tmpcode = strtoupper(trim($valdiscountarray[0]));
							$tmpval = str_replace('%', '', trim($valdiscountarray[1]));
							if (is_numeric($tmpval)) {
								$validdiscountcodearray[$tmpcode] = array('code'=>$tmpcode, 'type'=>'percent', 'value'=>$tmpval);
							} else {
								dol_syslog("Error: Bad definition of discount for product id = ".$tmpproduct->id." with value ".$tmpproduct->array_options['options_register_discountcode'], LOG_ERR);
							}
						}
						// If we entered a discountcode or get it from contract
						if (! empty($validdiscountcodearray[$discountcode])) {
							$discounttype = $validdiscountcodearray[$discountcode]['type'];
							$discountval = $validdiscountcodearray[$discountcode]['value'];
						} else {
							$discountcode = '';
						}
						//var_dump($validdiscountcodearray); var_dump($discountcode); var_dump($discounttype); var_dump($discountval); exit;
						if ($discounttype == 'percent') {
							if ($discountval > $discount) {
								$discount = $discountval;		// If discount with coupon code is higher than the one defined into contract.
							}
						}
					}
				}

				// Insert the line
				$result = $invoice_draft->addline($desc, $price_invoice_template_line, $lines[$i]->qty, $tva_tx, $localtax1_tx, $localtax2_tx, $lines[$i]->fk_product, $discount, $date_start, $date_end, 0, $lines[$i]->info_bits, $lines[$i]->fk_remise_except, 'HT', 0, $product_type, $lines[$i]->rang, $lines[$i]->special_code, $invoice_draft->origin, $lines[$i]->rowid, $fk_parent_line, $lines[$i]->fk_fournprice, $lines[$i]->pa_ht, $label, $array_options, $lines[$i]->situation_percent, $lines[$i]->fk_prev_id, $lines[$i]->fk_unit);

				if ($result > 0) {
					$lineid = $result;
				} else {
					$lineid = 0;
					$error++;
					break;
				}

				// Defined the new fk_parent_line
				if ($result > 0 && $lines[$i]->product_type == 9) {
					$fk_parent_line = $result;
				}
			}
		}

		// Now we convert invoice into a template
		if (! $error) {
			dol_syslog("--- Now we convert invoice into recuring invoice");

			//var_dump($invoice_draft->lines);
			//var_dump(dol_print_date($date_start,'dayhour'));
			//exit;

			//$frequency=1;
			//$frequency_unit='m';
			$frequency = (! empty($frequency) ? $frequency : 1);	// read frequency of product app
			$frequency_unit = (! empty($frequency_unit) ? $frequency_unit : 'm');	// read frequency_unit of product app
			$tmp = dol_getdate($date_start ? $date_start : $now);
			$reyear = $tmp['year'];
			$remonth = $tmp['mon'];
			$reday = $tmp['mday'];
			$rehour = $tmp['hours'];
			$remin = $tmp['minutes'];
			$nb_gen_max=0;
			//print dol_print_date($date_start,'dayhour');
			//var_dump($remonth);

			$invoice_rec = new FactureRec($db);

			$invoice_rec->title = 'Template invoice for '.$contract->ref.' '.$contract->ref_customer;
			$invoice_rec->titre = $invoice_rec->title;
			$invoice_rec->note_private = $contract->note_private;
			//$invoice_rec->note_public  = dol_concatdesc($contract->note_public, '__(Period)__ : __INVOICE_DATE_NEXT_INVOICE_BEFORE_GEN__ - __INVOICE_DATE_NEXT_INVOICE_AFTER_GEN__');
			$invoice_rec->note_public  = $contract->note_public;
			$invoice_rec->mode_reglement_id = $invoice_draft->mode_reglement_id;
			$invoice_rec->cond_reglement_id = $invoice_draft->cond_reglement_id;

			$invoice_rec->usenewprice = 0;

			$invoice_rec->frequency = $frequency;
			$invoice_rec->unit_frequency = $frequency_unit;
			$invoice_rec->nb_gen_max = $nb_gen_max;
			$invoice_rec->auto_validate = 0;

			$invoice_rec->fk_project = 0;

			$date_next_execution = dol_mktime($rehour, $remin, 0, $remonth, $reday, $reyear);
			$invoice_rec->date_when = $date_next_execution;

			// Add discount into the template invoice (it was already added into lines)
			if ($discountcode) {
				$invoice_rec->array_options['options_discountcode'] = $discountcode;
			}

			// Get first contract linked to invoice used to generate template
			if ($invoice_draft->id > 0) {
				$srcObject = $invoice_draft;

				$srcObject->fetchObjectLinked();

				if (! empty($srcObject->linkedObjectsIds['contrat'])) {
					$contractidid = reset($srcObject->linkedObjectsIds['contrat']);

					$invoice_rec->origin = 'contrat';
					$invoice_rec->origin_id = $contractidid;
					$invoice_rec->linked_objects[$invoice_draft->origin] = $invoice_draft->origin_id;
				}
			}

			$oldinvoice = new Facture($db);
			$oldinvoice->fetch($invoice_draft->id);

			$invoicerecid = $invoice_rec->create($user, $oldinvoice->id);
			if ($invoicerecid > 0) {
				$sql = 'UPDATE '.MAIN_DB_PREFIX.'facturedet_rec SET date_start_fill = 1, date_end_fill = 1 WHERE fk_facture = '.$invoice_rec->id;
				$result = $db->query($sql);
				if (! $error && $result < 0) {
					$error++;
					setEventMessages($db->lasterror(), null, 'errors');
				}

				$result=$oldinvoice->delete($user, 1);
				if (! $error && $result < 0) {
					$error++;
					setEventMessages($oldinvoice->error, $oldinvoice->errors, 'errors');
				}
			} else {
				$error++;
				setEventMessages($invoice_rec->error, $invoice_rec->errors, 'errors');
			}

			// A template invoice was just created, we run generation of invoice if template invoice date is already in past
			if (! $error) {
				dol_syslog("--- A template invoice was generated with id ".$invoicerecid.", now we run createRecurringInvoices to build real invoice", LOG_DEBUG, 0);
				$facturerec = new FactureRec($db);

				$savperm1 = $user->hasRight('facture', 'creer');
				$savperm2 = $user->hasRight('facture', 'invoice_advance', 'validate');

				if (!isset($user->rights->facture)) {
					$user->rights->facture = new stdClass();
				}
				$user->rights->facture->creer = 1;	// Force permission to user to validate invoices because code may be executed by anonymous user
				if (!$user->hasRight('facture', 'invoice_advance')) {
					$user->rights->facture->invoice_advance = new stdClass();
				}
				$user->rights->facture->invoice_advance->validate = 1;

				$result = $facturerec->createRecurringInvoices($invoicerecid, 1);		// Generate real invoice from pending recurring invoices
				if ($result != 0) {
					$error++;
					setEventMessages($facturerec->error, $facturerec->errors, 'errors');
				}

				$user->rights->facture->creer = $savperm1;
				$user->rights->facture->invoice_advance->validate = $savperm2;
			}

			// Now try to take the payment if payment is OK and payment mode is not a differed payment mode
			if (! $error && $paymentmode != 'ban') {
				if (empty($paymentmode)) {
					$paymentmode = 'card';
				}

				dol_syslog("--- Now we try to take payment with mode '".$paymentmode."' for thirdpartyid = ".$mythirdpartyaccount->id, LOG_DEBUG, 0);	// Unsuspend if it was suspended (done by trigger BILL_CANCEL or BILL_PAYED).


				$sellyoursaasutils = new SellYourSaasUtils($db);
				$result = $sellyoursaasutils->doTakePaymentStripeForThirdparty($service, $servicestatusstripe, $mythirdpartyaccount->id, $companypaymentmode, null, 0, 1, 0, 1, $paymentmode);

				if ($result != 0) {
					$error++;
					setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
					dol_syslog("--- Error when taking payment (paymentmode=".$paymentmode.", STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION on) ".$sellyoursaasutils->error, LOG_DEBUG, 0);
				} else {
					dol_syslog("--- Success to take payment (paymenmode=".$paymentmonde.", STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION on)", LOG_DEBUG, 0);
				}

				// If some payment was really done, we force commit to be sure to validate invoices payment done by stripe, whatever is global result of doTakePaymentStripeForThirdparty
				if ($sellyoursaasutils->stripechargedone > 0) {
					dol_syslog("--- Force commit to validate payments recorded after real Stripe charges", LOG_DEBUG, 0);

					$rescommit = $db->commit();

					dol_syslog("--- rescommit = ".$rescommit." transaction_opened is now ".$db->transaction_opened, LOG_DEBUG, 0);

					$db->begin();
				}
			}

			// Make renewals on contracts of customer if payment is OK and payment mode is not a differed payment mode
			if (! $error && $paymentmode != 'ban') {
				dol_syslog("--- Now we make renewal of contracts for thirdpartyid=".$mythirdpartyaccount->id." if payments were ok and contract are not unsuspended", LOG_DEBUG, 0);

				$sellyoursaasutils = new SellYourSaasUtils($db);

				$result = $sellyoursaasutils->doRenewalContracts($mythirdpartyaccount->id);		// A refresh is also done if renewal is done
				if ($result != 0) {
					$error++;
					setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
				}
			}
		}
		if (! $error && !$thirdpartyhadalreadyapaymentmode) {
			$comment = 'Execute remote script on '.$contract->ref.' after the creation of a first payment method';
			$sellyoursaasutils = new SellYourSaasUtils($db);
			$result = $sellyoursaasutils->sellyoursaasRemoteAction('actionafterpaid', $contract, 'admin', '', '', 0, $comment);
			if ($result <= 0) {
				dol_syslog("Call to remoteaction actionafterpaid has failed with result=".$result.". Check remote_server.log file.", LOG_WARNING);
				// No error test on this. Not a problem if it fails.
				//$error++;
				//setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
			}
		}
	}
}


if (! $error) {
	// Payment mode successfully recorded
	setEventMessages($langs->trans("PaymentModeRecorded"), null, 'mesgs');

	$db->commit();

	$url=$_SERVER["PHP_SELF"];
	if ($backurl) {
		$url=$backurl;
	}

	if ($thirdpartyhadalreadyapaymentmode > 0) {
		dol_syslog("PaymentModeHasBeenModified");

		// Set flag 'showconversiontracker' in session to output the js tracker by llxFooter function of customer dashboard.
		$_SESSION['showconversiontracker']='paymentmodified';

		$url.=(preg_match('/\?/', $url) ? '&' : '?').'paymentmodified=1';

		// Send to DataDog (metric + event)
		if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED)) {
			try {
				dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

				$arrayconfig=array();
				if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY)) {
					$arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
				}

				$statsd = new DataDog\DogStatsd($arrayconfig);

				$arraytags=null;
				$statsd->increment('sellyoursaas.paymentmodemodified', 1, $arraytags);
			} catch (Exception $e) {
			}
		}
	} else {
		dol_syslog("PaymentModeHasBeenAdded");

		// Set flag 'showconversiontracker' in session to output the js tracker by llxFooter function of customer dashboard.
		$_SESSION['showconversiontracker']='paymentrecorded';

		$url.=(preg_match('/\?/', $url) ? '&' : '?').'paymentrecorded=1';

		// Send to DataDog (metric + event)
		if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED)) {
			try {
				dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

				$arrayconfig=array();
				if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY)) {
					$arrayconfig=array('apiKey' => $conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
				}

				$statsd = new DataDog\DogStatsd($arrayconfig);

				$arraytags=null;
				$statsd->increment('sellyoursaas.paymentmodeadded', 1, $arraytags);

				global $dolibarr_main_url_root;
				$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT, '/').'$/i', '', trim($dolibarr_main_url_root));
				$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
				//$urlwithroot=DOL_MAIN_URL_ROOT;					// This is to use same domain name than current

				$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;
				if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
					&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
					$newnamekey = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
					if (! empty($conf->global->$newnamekey)) {
						$sellyoursaasname = $conf->global->$newnamekey;
					}
				}

				$titleofevent = dol_trunc($sellyoursaasname.' - '.gethostname().' - '.$langscompany->trans("NewCustomer").': '.$mythirdpartyaccount->name, 90);
				$messageofevent = ' - '.$langscompany->trans("PaymentModeAddedFrom").' '.getUserRemoteIP()."\n";
				$messageofevent.= $langscompany->trans("Customer").': '.$mythirdpartyaccount->name.' ['.$langscompany->trans("SeeOnBackoffice").']('.preg_replace('/https:\/\/myaccount\./', 'https://admin.', $urlwithouturlroot).'/societe/card.php?socid='.$mythirdpartyaccount->id.')'."\n".$langscompany->trans("SourceURLOfEvent").": ".$url;

				// See https://docs.datadoghq.com/api/?lang=python#post-an-event
				$statsd->event(
					$titleofevent,
					array(
							'text'       =>  "%%% \n ".$titleofevent.$messageofevent." \n %%%",      // Markdown text
							'alert_type' => 'info',
							'source_type_name' => 'API',
							'host'       => gethostname()
						)
				);
			} catch (Exception $e) {
				// Nothing done
			}
		}
	}

	header('Location: '.$url);
	exit;
} else {
	$db->rollback();
}
