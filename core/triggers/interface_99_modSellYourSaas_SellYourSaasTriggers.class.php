<?php
/* Copyright (C) 2018 Laurent Destailleur <eldy@users.sourceforge.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modSellYourSaas_SellYourSaasTriggers.class.php
 * \ingroup sellyoursaas
 * \brief   Trigger for sellyoursaas module.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';


/**
 *  Class of triggers for SellYourSaas module
 */
class InterfaceSellYourSaasTriggers extends DolibarrTriggers
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "sellyoursaas";
		$this->description = "SellYourSaas triggers.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = 1.0;
		$this->picto = 'sellyoursaas@sellyoursaas';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}


	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		global $mysoc;

		if (!isModEnabled('sellyoursaas')) {
			return 0;     // Module not active, we do nothing
		}

		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action

		$error = 0;
		$remoteaction = '';

		switch ($action) {
			case 'CATEGORY_LINK':
				// Test if this is a partner. If yes, send an email
				include_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

				if ($object->type === Categorie::TYPE_SUPPLIER || Categorie::$MAP_ID_TO_CODE[$object->type] == Categorie::TYPE_SUPPLIER) {
					// We link a supplier categorie to a thirdparty
					if ($object->id == $conf->global->SELLYOURSAAS_DEFAULT_RESELLER_CATEG) {
						$reseller = $object->context['linkto'];

						// $object->context['linkto'] is Societe object
						/*if (empty($reseller->name_alias))	// Used to generate the partnerkey
						{
							$this->errors[] = $langs->trans("CompanyAliasIsRequiredWhenWeSetResellerTag");
							return -1;
						}*/
						if (empty($reseller->array_options['options_commission']) && $reseller->array_options['options_commission'] != '0') {
							$this->errors[] = $langs->trans("CommissionIsRequiredWhenWeSetResellerTag");
							return -1;
						}

						// If password not set yet, we set it
						if (empty($reseller->array_options['options_password'])) {
							$password = dol_string_nospecial(dol_string_unaccent(strtolower($reseller->name)));

							$reseller->oldcopy = dol_clone($reseller);

							$reseller->array_options['options_password']=dol_hash($password);

							$reseller->update($reseller->id, $user, 0);
						}

						// No email, can be done manually.
						/*
						// Send deployment email
						include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
						include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
						$formmail=new FormMail($db);

						$arraydefaultmessage=$formmail->getEMailTemplate($db, 'thirdparty', $user, $langs, 0, 1, '(ChannelPartnerCreated)');

						$substitutionarray=getCommonSubstitutionArray($langs, 0, null, $contract);
						complete_substitutions_array($substitutionarray, $langs, $contract);

						$subject = make_substitutions($arraydefaultmessage->topic, $substitutionarray, $langs);
						$msg     = make_substitutions($arraydefaultmessage->content, $substitutionarray, $langs);
						$from = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;
						$to = $contract->thirdparty->email;

						$cmail = new CMailFile($subject, $to, $from, $msg, array(), array(), array(), '', '', 0, 1);
						$result = $cmail->sendfile();
						if (! $result)
						{
							$error++;
							$this->errors += $cmail->errors;
						}
						*/
					}
				}
				break;
			case 'LINECONTRACT_ACTIVATE':
				if (empty($object->context['deployallwasjustdone'])) {
					dol_syslog("Trigger LINECONTRACT_ACTIVATE is ran and context 'deployallwasjustdone' is not 1, so we will launch the unsuspend remote actions if line has type 'app'");
					$object->fetch_product();
					if ($object->product->array_options['options_app_or_option'] == 'app') {
						$contract = new Contrat($this->db);
						$contract->fetch($object->fk_contrat);
						if ($contract->array_options['options_deployment_status'] == 'undeployed') {
							setEventMessages("CantActivateContractWhenUndeployed", null, 'warnings');
						} else {
							$remoteaction = 'unsuspend';
						}
					}
				} else {
					dol_syslog("Trigger LINECONTRACT_ACTIVATE is ran but context 'deployallwasjustdone' is 1, so we do NOT launch the unsuspend remote actions");
				}
				break;
			case 'LINECONTRACT_CLOSE':
				dol_syslog("Trigger LINECONTRACT_CLOSE is ran");

				$object->fetch_product();
				if (!empty($object->product->array_options['options_app_or_option']) && $object->product->array_options['options_app_or_option'] == 'app') {
					$contract = new Contrat($this->db);
					$contract->fetch($object->fk_contrat);
					if ($contract->array_options['options_deployment_status'] == 'undeployed') {
						setEventMessages("CantDisableContractWhenUndeployed", null, 'warnings');
					} else {
						$remoteaction = 'suspend';
					}
				}
				break;
			case 'CONTRACT_DELETE':
				$remoteaction = 'undeployall';
				break;
			case 'CONTRACT_MODIFY':
				/*var_dump($object->oldcopy->array_options['options_date_endfreeperiod']);
				var_dump($object->array_options['options_date_endfreeperiod']);
				var_dump($object->lines);*/

				// Do we rename instance name ?
				if (isset($object->oldcopy)
				&& ($object->oldcopy->ref_customer != $object->ref_customer
				|| $object->oldcopy->array_options['options_custom_url'] != $object->array_options['options_custom_url'])) {
					$testok = 1;

					if (preg_match('/\.with\./', $object->array_options['options_custom_url'])) {
						$this->errors[]="Value of URL including .with. is not allowed as custom URL";
						return -1;
					}

					// Clean new value
					$nametotest = $object->ref_customer;
					// Sanitize $sldAndSubdomain. Remove start and end -
					$nametotest = preg_replace('/^\-+/', '', $nametotest);
					$nametotest = preg_replace('/\-+$/', '', $nametotest);
					// Avoid uppercase letters
					$nametotest = strtolower($nametotest);

					// Test new name syntax
					if ($nametotest != $object->ref_customer) {
						$this->errors[]="Bad value for the new name of URL (must be lowercase, without spaces, not starting with '-')";
						return -1;
					}

					// Test new name syntax 2
					if (! preg_match('/^[a-zA-Z0-9\-\.]+$/', $object->ref_customer)) {	// Same control than in register_instance but we add . because we test FQDN and not only first part.
						$this->errors[]="Bad value for the new name of URL (special characters are not allowed)";
						return -1;
					}

					// Test that new name is not already used
					$nametotest = $object->ref_customer;
					// @TODO

					// Test that custom url is not already used
					$nametotest = $object->array_options['options_custom_url'];
					// @TODO

					if ($testok) {
						if ($object->oldcopy->array_options['options_deployment_status'] != 'undeployed') {
							dol_syslog("We found a change in ref_customer or into custom url for a not undeployed instance, so we will call the remote action rename");
							$remoteaction='rename';
						}

						// Change hostname OS and hostname DB
						if ($object->oldcopy->ref_customer != $object->ref_customer) {
							$object->array_options['options_hostname_os'] = $object->ref_customer;
							$object->updateExtraField('hostname_os', null, $user);
							$object->array_options['options_hostname_db'] = $object->ref_customer;
							$object->updateExtraField('hostname_db', null, $user);
						}
					} else {
						$this->errors[]="Name already used";
						return -1;
					}
				}

				// Do we change end of trial ?
				if (isset($object->oldcopy) && $object->oldcopy->array_options['options_date_endfreeperiod'] != $object->array_options['options_date_endfreeperiod']) {
					dol_syslog("We found a change in date of end of trial, so we check if you can and, if yes, we make the update of contract");

					if ($object->oldcopy->array_options['options_date_endfreeperiod'] && ($object->oldcopy->array_options['options_date_endfreeperiod'] < $object->array_options['options_date_endfreeperiod'])) {
						// Check there is no recurring invoice. If yes, we refuse to increase value.
						$object->fetchObjectLinked();
						//var_dump($object->linkedObjects);
						if (is_array($object->linkedObjects['facturerec'])) {
							if (count($object->linkedObjects['facturerec']) > 0) {
								$this->errors[]="ATemplateInvoiceExistsNoWayToChangeTrial";
								return -1;
							}
						}
					}

					foreach ($object->lines as $line) {
						if ($line->date_end < $object->array_options['options_date_endfreeperiod']) {
							$line->oldcopy = dol_clone($line);

							$line->date_end = $object->array_options['options_date_endfreeperiod'];
							$line->date_fin_validite = $object->array_options['options_date_endfreeperiod'];	// deprecated

							$line->update($user);
							break;	// No need to loop on all, the constant CONTRACT_SYNC_PLANNED_DATE_OF_SERVICES should be enabled by module SELLYOURSAAS
									// so the line->update() will update also all other lines when we update one line.
						}
					}
				}
				break;

			case 'BILL_VALIDATE':
				dol_syslog("Trigger BILL_VALIDATE is ran");

				$reseller = new Societe($this->db);
				$reseller->fetch($object->thirdparty->parent);
				if ($reseller->id > 0) {
					$object->array_options['options_commission']=$reseller->array_options['options_commission'];
					$object->array_options['options_reseller']=$reseller->id;
					$object->insertExtraFields('', $user);
				}
				break;
			case 'BILL_CANCEL':
				break;
			case 'BILL_PAYED':
				dol_syslog("Trigger BILL_PAYED is ran");

				$object->fetchObjectLinked(null, '', null, '', 'OR', 0, 'sourcetype', 0);

				if ($object->type != Facture::TYPE_CREDIT_NOTE  && ! empty($object->linkedObjectsIds['contrat'])) {
					// Get the first contract of the paid invoice
					$contractid = reset($object->linkedObjectsIds['contrat']);
					dol_syslog("The cancel/paid invoice ".$object->ref." is linked to contract id ".$contractid.", we check if we have to unsuspend it.");

					include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
					$contract = new Contrat($this->db);
					$contract->fetch($contractid);

					// TODO Check there is no other unpaid invoices for the case of several pending invoices on same contract
					// (in such a case, we may decide to no activate the linked contract)
					// $contract->fetchObjectLinked();

					if ($contract->array_options['options_deployment_status'] == 'done') {
						$result = $contract->activateAll($user);		// This will activate line if not already activated and set status of contrat to 1 if not already set
						if ($result < 0) {
							$error++;
							$this->error = $contract->error;
							$this->errors = $contract->errors;
						}
						dol_syslog("Contract lines have been activated");
					}
				} else {
					dol_syslog("The cancel/paid invoice ".$object->ref." is a credit note, or has no linked contract to check to unsuspend.");
				}
				break;

			case 'PAYMENT_CUSTOMER_CREATE':
				// $object is a Payment

				dol_syslog("We trap trigger PAYMENT_CUSTOMER_CREATE for id = ".$object->id);

				// Send to DataDog (metric + event)
				if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED) && preg_match('/SellYourSaas/i', ($object->note ? $object->note : $object->note_public))) {
					$totalamount = 0;
					foreach ($object->amounts as $key => $amount) {
						$totalamount+=$amount;
					}
					$totalamount=price2num($totalamount);

					try {
						dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

						$arrayconfig=array();
						if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY)) {
							$arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
						}

						$statsd = new DataDog\DogStatsd($arrayconfig);

						$arraytags=null;
						$statsd->increment('sellyoursaas.payment', 1, $arraytags, $totalamount);
						$statsd->increment('sellyoursaas.paymentdone', 1, $arraytags);
					} catch (Exception $e) {
					}
				}
				break;

			case 'PAYMENT_CUSTOMER_DELETE':
				dol_syslog("We trap trigger PAYMENT_CUSTOMER_DELETE for id = ".$object->id);

				// Send to DataDog (metric + event)
				if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED) && preg_match('/SellYourSaas/i', ($object->note ? $object->note : $object->note_public))) {
					try {
						dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

						$arrayconfig=array();
						if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY)) {
							$arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
						}

						$statsd = new DataDog\DogStatsd($arrayconfig);

						$totalamount=price2num(-1 * $object->amount);

						$arraytags=null;
						$statsd->increment('sellyoursaas.payment', 1, $arraytags, $totalamount);   // total amount is negative
						//$statsd->increment('sellyoursaas.paymentdone', 1, $arraytags);
					} catch (Exception $e) {
					}
				}
				break;

			case 'COMPANY_MODIFY':
				/*var_dump($object->oldcopy->array_options['options_date_endfreeperiod']);
				 var_dump($object->array_options['options_date_endfreeperiod']);
				 var_dump($object->lines);*/

				if (isset($object->oldcopy)	&&
				(($object->oldcopy->tva_intra != $object->tva_intra)
				|| ($object->oldcopy->tva_assuj != $object->tva_assuj)
				|| ($object->oldcopy->country_id != $object->country_id))) {
					include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
					include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

					dol_syslog("We change intra VAT information or country id, so we must also change VAT into contract and into template invoices");

					$object->country_code = getCountry($object->country_id, 2);

					$sql ="SELECT c.rowid FROM ".MAIN_DB_PREFIX."contrat as c, ".MAIN_DB_PREFIX."contrat_extrafields as ce";
					$sql.=" WHERE ce.fk_object = c.rowid AND ce.deployment_status IS NOT NULL";
					$sql.=" AND c.fk_soc = ".$object->id;
					$resql = $this->db->query($sql);
					if ($resql) {
						$num = $this->db->num_rows($resql);
						$i=0;
						while ($i < $num) {
							$obj = $this->db->fetch_object($resql);

							$contract = new Contrat($this->db);
							$contract->fetch($obj->rowid);

							foreach ($contract->lines as $line) {
								//$newvatrate = get_default_tva($mysoc, $object, $line->fk_product);
								$newvatrate = get_default_tva($mysoc, $object, 0);
								if ($newvatrate != $line->tva_tx) {
									$line->tva_tx = $newvatrate;
									$line->update($user, 1);
								}
							}

							// Test if there is template invoice linked to contract
							$contract->fetchObjectLinked();

							if (is_array($contract->linkedObjects['facturerec']) && count($contract->linkedObjects['facturerec']) > 0) {
								foreach ($contract->linkedObjects['facturerec'] as $invoice) {
									$sqlsearchline = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'facturedet_rec WHERE fk_facture = '.$invoice->id;
									$resqlsearchline = $this->db->query($sqlsearchline);
									if ($resqlsearchline) {
										$num_search_line = $this->db->num_rows($resqlsearchline);
										$j=0;
										while ($j < $num_search_line) {
											$objsearchline = $this->db->fetch_object($resqlsearchline);
											if ($objsearchline) {	// If empty, it means, template invoice has no line corresponding to contract line
												// Update qty
												$invoicerecline = new FactureLigneRec($this->db);
												$invoicerecline->fetch($objsearchline->rowid);

												$invoicerecline->tva_tx = get_default_tva($mysoc, $object, $invoicerecline->fk_product);

												$tabprice = calcul_price_total($invoicerecline->qty, $invoicerecline->subprice, $invoicerecline->remise_percent, $invoicerecline->tva_tx, $invoicerecline->localtax1_tx, $invoicerecline->txlocaltax2, 0, 'HT', $invoicerecline->info_bits, $invoicerecline->product_type, $mysoc, array(), 100);

												$invoicerecline->total_ht  = $tabprice[0];
												$invoicerecline->total_tva = $tabprice[1];
												$invoicerecline->total_ttc = $tabprice[2];
												$invoicerecline->total_localtax1 = $tabprice[9];
												$invoicerecline->total_localtax2 = $tabprice[10];

												$result = $invoicerecline->update($user, 1);
											}

											$j++;
										}

										$result = $invoice->update_price();
									} else {
										$error++;
										$this->error = $this->db->lasterror();
									}
								}
							}

							$i++;
						}
					} else dol_print_error($this->db);
				}
				break;
		}

		if ($remoteaction) {     // Note that remoteaction is on for line of contract if line has type 'app' only.
			$okforremoteaction = 1;
			$contract = null;
			if (get_class($object) == 'Contrat') {	// object is contract
				$contract = $object;
			} else { // object is a line of contract for type 'app'
				$contract = new Contrat($this->db);
				$contract->fetch($object->fk_contrat);
			}

			// No remote action required or this is not a sellyoursaas instance
			if (in_array($remoteaction, array('suspend','unsuspend','undeploy','undeployall')) && empty($contract->array_options['options_deployment_status'])) {
				$okforremoteaction=0;
			}

			if (! $error && $okforremoteaction && $contract) {
				if ($remoteaction == 'deploy' || $remoteaction == 'unsuspend') {		// when remoteaction = 'deploy' or 'unsuspend'
					// If there is some template invoices linked to contract, we make sure the template invoices are also enabled
					$contract->fetchObjectLinked();
					//var_dump($contract->linkedObjects);
					if (is_array($contract->linkedObjects['facturerec'])) {
						foreach ($contract->linkedObjects['facturerec'] as $templateinvoice) {
							if ($templateinvoice->suspended == FactureRec::STATUS_SUSPENDED) {
								$templateinvoice->setValueFrom('suspended', FactureRec::STATUS_NOTSUSPENDED);
							}
						}
					}
				}

				if ($remoteaction == 'undeploy') {
					// If there is some template invoices linked to contract, we make sure template invoice are disabled
					$contract->fetchObjectLinked();
					//var_dump($contract->linkedObjects);
					if (is_array($contract->linkedObjects['facturerec'])) {
						foreach ($contract->linkedObjects['facturerec'] as $templateinvoice) {
							if ($templateinvoice->suspended == FactureRec::STATUS_NOTSUSPENDED) {
								$templateinvoice->setValueFrom('suspended', FactureRec::STATUS_SUSPENDED);
							}
						}
					}
				}
			}
			if (! $error && $okforremoteaction) {
				dol_include_once('/sellyoursaas/class/sellyoursaasutils.class.php');
				$sellyoursaasutils = new SellYourSaasUtils($this->db);
				// Param '0' means an event is added for some remot action only, '-1' means never add remote action
				$forceaddevent = '0';
				$result = $sellyoursaasutils->sellyoursaasRemoteAction($remoteaction, $object, 'admin', '', '', $forceaddevent, 'Remote action '.$remoteaction.' executed from trigger '.$action, 300);
				if ($result <= 0) {
					$error++;
					$this->error=$sellyoursaasutils->error;
					$this->errors=$sellyoursaasutils->errors;
				} else {
					if (! preg_match('/sellyoursaas/', session_name())) {	// No popup message after trigger if we are not into the backoffice
						if ($remoteaction == 'suspend') setEventMessage($langs->trans("InstanceWasSuspended", $contract->ref_customer.' ('.$contract->ref.')'));
						elseif ($remoteaction == 'unsuspend') setEventMessage($langs->trans("InstanceWasUnsuspended", $contract->ref_customer.' ('.$contract->ref.')'));
						elseif ($remoteaction == 'deploy') setEventMessage($langs->trans("InstanceWasDeployed", $contract->ref_customer.' ('.$contract->ref.')'));
						elseif ($remoteaction == 'undeploy') setEventMessage($langs->trans("InstanceWasUndeployed", $contract->ref_customer.' ('.$contract->ref.')'));
						elseif ($remoteaction == 'deployall') setEventMessage($langs->trans("InstanceWasDeployed", $contract->ref_customer.' ('.$contract->ref.')').' (deployall)');
						elseif ($remoteaction == 'undeployall') setEventMessage($langs->trans("InstanceWasUndeployed", $contract->ref_customer.' ('.$contract->ref.')').' (undeployall)');
						elseif ($remoteaction == 'rename') setEventMessage($langs->trans("InstanceWasRenamed", $contract->ref_customer.' '.$contract->array_options['options_custom_url'].' ('.$contract->ref.')'));
					}
				}
			}

			dol_syslog("Trigger ".$action." ends with error=".$error);
		}

		if ($error) {
			return -1;
		} else {
			return 0;
		}
	}
}
