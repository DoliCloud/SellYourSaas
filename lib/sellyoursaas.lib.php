<?php
/* Copyright (C) 2018	Laurent Destailleur	<eldy@users.sourceforge.net>
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
 * \file    lib/sellyoursaas.lib.php
 * \ingroup sellyoursaas
 * \brief   Library files with common functions for SellYourSaas module
 */

if (!function_exists('getDolGlobalString')) {
	/**
	 * Return dolibarr global constant string value
	 * @param string $key key to return value, return '' if not set
	 * @param string $default value to return
	 * @return string
	 */
	function getDolGlobalString($key, $default = '')
	{
		global $conf;
		// return $conf->global->$key ?? $default;
		return (string) (isset($conf->global->$key) ? $conf->global->$key : $default);
	}
}

if (!function_exists('getDolGlobalInt')) {
	/**
	 * Return dolibarr global constant int value
	 * @param string $key key to return value, return 0 if not set
	 * @param int $default value to return
	 * @return int
	 */
	function getDolGlobalInt($key, $default = 0)
	{
		global $conf;
		// return $conf->global->$key ?? $default;
		return (int) (isset($conf->global->$key) ? $conf->global->$key : $default);
	}
}

/**
 * To compare on date property
 *
 * @param 	int 	$a		Date A
 * @param 	int 	$b		Date B
 * @return 	boolean			Result of comparison
 */
function sellyoursaasCmpDate($a, $b)
{
	if ($a->date == $b->date) {
		return strcmp((string) $a->id, (string) $b->id);
	}
	return strcmp($a->date, $b->date);
}

/**
 * To compare on date property (reverse)
 *
 * @param 	int 	$a		Date A
 * @param 	int 	$b		Date B
 * @return 	boolean			Result of comparison
 */
function sellyoursaasCmpDateDesc($a, $b)
{
	if ($a->date == $b->date) {
		return strcmp((string) $b->id, (string) $a->id);
	}
	return strcmp($b->date, $a->date);
}

/**
 * Return if a thirdparty has a payment mode set as a default payment mode.
 *
 * @param 	int	$thirdpartyidtotest		Third party id
 * @return 	int							>0 if there is at least one payment mode, 0 if no payment mode
 */
function sellyoursaasThirdpartyHasPaymentMode($thirdpartyidtotest)
{
	global $conf, $db, $user;

	$atleastonepaymentmode = 0;

	// Define environment of payment modes
	$servicestatusstripe = 0;
	if (! empty($conf->stripe->enabled)) {
		$service = 'StripeTest';
		$servicestatusstripe = 0;
		if (getDolGlobalString('STRIPE_LIVE') && ! GETPOST('forcesandbox', 'alpha') && !getDolGlobalString('SELLYOURSAAS_FORCE_STRIPE_TEST')) {
			$service = 'StripeLive';
			$servicestatusstripe = 1;
		}
	}
	$servicestatuspaypal = 0;
	if (! empty($conf->paypal->enabled)) {
		$servicestatuspaypal = 0;
		if (getDolGlobalString('PAYPAL_LIVE') && ! GETPOST('forcesandbox', 'alpha') && !getDolGlobalString('SELLYOURSAAS_FORCE_PAYPAL_TEST')) {
			$servicestatuspaypal = 1;
		}
	}


	// Fill array of company payment modes
	$sql = 'SELECT rowid, default_rib FROM '.MAIN_DB_PREFIX."societe_rib";
	$sql.= " WHERE type in ('ban', 'card', 'paypal')";
	$sql.= " AND fk_soc = ".((int) $thirdpartyidtotest);
	$sql.= " AND (";
	$sql.= "(type = 'ban') OR";												// sepa		TODO Add filter on ext_payment_site
	$sql.= "(type = 'card' AND status = ".$servicestatusstripe.") OR";		// stripe	TODO Add filter on ext_payment_site
	$sql.= "(type = 'paypal' AND status = ".$servicestatuspaypal.")";		// paypal	TODO Add filter on ext_payment_site
	$sql.= ")";
	$sql.= " ORDER BY default_rib DESC, tms DESC";

	$resqltmp = $db->query($sql);
	if ($resqltmp) {
		$num_rows = $db->num_rows($resqltmp);
		if ($num_rows) {
			$i=0;
			while ($i < $num_rows) {
				$objtmp = $db->fetch_object($resqltmp);
				if ($objtmp) {
					if ($objtmp->default_rib != 1) {
						continue;
					}	// Keep the default payment mode only
					$atleastonepaymentmode++;
					break;
				}
				$i++;
			}
		}
	}

	return $atleastonepaymentmode;
}

/**
 * Return if instance is a paid instance or not
 * Check if there is an invoice or template invoice (it was a paying customer) or just a template invoice (it is a current paying customer)
 *
 * @param 	Contrat $contract			Object contract
 * @param	int		$mode				0=Test invoice or template invoice linked to the contract, 1=Test only templates invoices
 * @param	int		$loadalsoobjects	Load also array this->linkedObjects (Use 0 to increase performances)
 * @return	int							>0 if this is a paid contract
 */
function sellyoursaasIsPaidInstance($contract, $mode = 0, $loadalsoobjects = 0)
{
	$contract->fetchObjectLinked(null, '', null, '', 'OR', 1, 'sourcetype', $loadalsoobjects);

	/*var_dump($contract->linkedObjectsIds);
	var_dump($contract->linkedObjects);*/

	$foundtemplate=0;
	if (!empty($contract->linkedObjectsIds['facturerec']) && is_array($contract->linkedObjectsIds['facturerec'])) {
		foreach ($contract->linkedObjectsIds['facturerec'] as $idelementelement => $templateinvoiceid) {
			$foundtemplate++;
			break;
		}
	}

	if ($foundtemplate) {
		return 1;
	}

	if ($mode == 0) {
		$foundinvoice=0;
		if (!empty($contract->linkedObjectsIds['facture']) && is_array($contract->linkedObjectsIds['facture'])) {
			foreach ($contract->linkedObjectsIds['facture'] as $idelementelement => $invoiceid) {
				$foundinvoice++;
				break;
			}
		}

		if ($foundinvoice) {
			return 1;
		}
	}

	return 0;
}


/**
 * Return if instance has a last payment in error or not
 *
 * @param 	Contrat 	$contract			Object contract
 * @return	int								>0 if this is a contract with at least one link to an open invoice with a payment error
 */
function sellyoursaasIsPaymentKo($contract)
{
	global $db;

	$paymenterror=0;

	// Return number of invoice open with an event payment error on it
	// Note: we suppose that if a payment as been correctly saved after the invoice has also been closed, then the
	// invoice will be excluded by the fk_statut = 1, so we can count record with a payment failure with invoice not closed
	$sql = "SELECT DISTINCT ee.fk_target as invoiceid";
	$sql .= " FROM llx_element_element as ee";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."facture as f ON ee.fk_target = f.rowid AND f.fk_statut = 1";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."actioncomm as a ON ee.fk_target = a.fk_element AND a.elementtype = 'invoice' AND (a.code LIKE 'AC_PAYMENT_%_KO' OR a.label = 'Cancellation of payment by the bank')";
	$sql .= " WHERE (ee.fk_source = ".((int) $contract->id)." AND ee.sourcetype = 'contrat' AND ee.targettype = 'facture')";
	$sql .= " UNION ";
	$sql .= "SELECT DISTINCT ee.fk_source as invoiceid";
	$sql .= " FROM llx_element_element as ee";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."facture as f ON ee.fk_source = f.rowid AND f.fk_statut = 1";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."actioncomm as a ON ee.fk_source = a.fk_element AND a.elementtype = 'invoice' AND (a.code LIKE 'AC_PAYMENT_%_KO' OR a.label = 'Cancellation of payment by the bank')";
	$sql .= " WHERE (ee.fk_target = ".((int) $contract->id)." AND ee.targettype = 'contrat' AND ee.sourcetype = 'facture')";

	$resql=$db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$db->free($resql);
		return $num;
	} else {
		dol_print_error($db);
	}

	return $paymenterror;
}

/**
 * Return if instance has some unpaid invoices
 *
 * @param 	Contrat $contract		Object contract
 * @return	int						>0 if this is a contract with current payment error
 */
function sellyoursaasHasOpenInvoices($contract)
{
	global $db;

	$contract->fetchObjectLinked();
	$atleastoneopeninvoice=0;

	if (isset($contract->linkedObjects['facture']) && is_array($contract->linkedObjects['facture'])) {
		foreach ($contract->linkedObjects['facture'] as $rowidelementelement => $invoice) {
			if ($invoice->statut == Facture::STATUS_CLOSED) {
				continue;
			}
			if ($invoice->statut == Facture::STATUS_ABANDONED) {
				continue;
			}
			if (empty($invoice->paid)) {
				$atleastoneopeninvoice++;
			}
		}
	}

	return $atleastoneopeninvoice;
}


/**
 * Return date of expiration. Can also return other information on instance (status, nb of users, id of product of application, ...)
 * For expiration date, it takes the lowest planed end date for services (whatever is service status)
 *
 * @param 	Contrat $contract				Object contract
 * @param	int		$onlyexpirationdate		1=Return only property 'expiration_date' (no need to load each product line properties to also set the 'nbusers', 'nbofgbs', 'status', 'duration_value', ...)
 * @return	array							Array of data array(
 * 												'expirationdate'=>Timestamp of expiration date, or 0 if error or not found,
 * 												'status'=>Status of line of package app,
 * 												'duration_value', 'duration_unit', 'nbusers', 'nbofgbs', 'appproductid'
 * 											)
 */
function sellyoursaasGetExpirationDate($contract, $onlyexpirationdate = 0)
{
	global $db;

	$expirationdate = 0;
	$statusofappline = 0;
	$duration_value = 0;
	$duration_unit = '';
	$nbofusers = 0;
	$nbofgbs = 0;
	$appproductid = 0;

	include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

	global $cachefortmpprod;
	if (! isset($cachefortmpprod) || ! is_array($cachefortmpprod)) {
		$cachefortmpprod = array();
	}

	dol_syslog("sellyoursaasGetExpirationDate for contract id=".$contract->id." onlyexpirationdate=".$onlyexpirationdate);

	// Loop on each line to get lowest expiration date
	foreach ($contract->lines as $line) {
		if ($line->date_end) {	// Planned end date of service
			if ($expirationdate > 0) {
				$expirationdate = min($expirationdate, $line->date_end);
			} else {
				$expirationdate = $line->date_end;
			}
		}

		if (empty($onlyexpirationdate) && $line->fk_product > 0) {
			if (empty($cachefortmpprod[$line->fk_product])) {	// if product not already loaded into the cache
				$tmpprod = new Product($db);
				$result = $tmpprod->fetch($line->fk_product, '', '', '', 1, 1, 1);
				if ($result > 0) {
					$cachefortmpprod[$line->fk_product] = $tmpprod;
				} else {
					dol_syslog("Error, failed to fetch product with ID ".$line->fk_product, LOG_ERR);
				}
			}
			$prodforline = $cachefortmpprod[$line->fk_product];

			// Get data depending on type of line (status, duration_xxx, appproductid, nbofusers, nbofgbs)
			if ($prodforline->array_options['options_app_or_option'] == 'app') {
				$duration_value = $prodforline->duration_value;
				$duration_unit = $prodforline->duration_unit;
				$appproductid = $prodforline->id;

				$statusofappline = $line->statut;

				if (empty($duration_value) || empty($duration_unit)) {
					dol_syslog("Error, the definition of duration for product ID ".$prodforline->id." is uncomplete.", LOG_ERR);
				}
			}
			if ($prodforline->array_options['options_app_or_option'] == 'system') {
				if ($prodforline->array_options['options_resource_label'] == 'User'
				|| preg_match('/user/i', $prodforline->ref)) {
					$nbofusers += $line->qty;
				}
				if ($prodforline->array_options['options_resource_label'] == 'Gb'
				|| preg_match('/\sgb\s/i', $prodforline->ref)) {
					$nbofgbs = $line->qty;
				}
			}
		}
	}

	return array('expirationdate'=>$expirationdate, 'status'=>$statusofappline, 'duration_value'=>$duration_value, 'duration_unit'=>$duration_unit, 'nbusers'=>$nbofusers, 'nbofgbs'=>$nbofgbs, 'appproductid'=>$appproductid);
}



/**
 * Return if contract is suspenced/close
 * Take lowest planed end date for services (whatever is service status)
 *
 * @param 	Contrat $contract		Object contract
 * @return	boolean					Return if a contract is suspended or not
 */
function sellyoursaasIsSuspended($contract)
{
	if ($contract->nbofserviceswait > 0 || $contract->nbofservicesopened > 0 || $contract->nbofservicesexpired > 0) {
		return false;
	}
	if ($contract->nbofservicesclosed > 0) {
		return true;
	}

	return false;
}

/**
 * Return URL of customer account. Try to guess using an object.
 *
 * @param   Object      $object         Object Product or Object Packages or Object Contract
 * @return  string                      URL
 */
function getRootUrlForAccount($object)
{
	global $db, $conf;

	$tmpret = explode(',', getDolGlobalString('SELLYOURSAAS_ACCOUNT_URL'));     // By default
	$ret = $tmpret[0];

	$newobject = $object;

	// If $object is a contract, we take ref_customer
	if ($newobject && get_class($newobject) == 'Contrat') {
		include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
		$ret = 'https://myaccount.'.getDomainFromURL($newobject->ref_customer, 1);
	}

	// If $object is a product, we take package
	if ($newobject && get_class($newobject) == 'Product') {
		dol_include_once('/sellyoursaas/class/packages.class.php');

		$newobject->fetch_optionals();

		$tmppackage = new Packages($db);
		$tmppackage->fetch($newobject->array_options['options_package']);
		$newobject = $tmppackage;
	}

	// If $object is a package, we take first restrict and add account.
	if ($newobject && get_class($newobject) == 'Packages') {
		$tmparray = explode(',', $newobject->restrict_domains);
		if (is_array($tmparray)) {
			foreach ($tmparray as $key => $val) {
				if ($val) {
					include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
					$ret = 'https://myaccount.'.getDomainFromURL($val, 1);
					break;
				}
			}
		}
	}

	return $ret;
}

/**
 * Prepare array with list of tabs
 *
 * @return  array				Array of tabs to show
 */
function sellyoursaas_admin_prepare_head()
{
	global $langs, $conf, $user;

	$langs->load("sellyoursaas");
	$h = 0;
	$head = array();

	$head[$h][0] = "setup.php";
	$head[$h][1] = $langs->trans("ParametersOnMasterServer");
	$head[$h][2] = "setup";
	$h++;

	$head[$h][0] = "setup_deploy_server.php";
	$head[$h][1] = $langs->trans("ParametersOnDeploymentServers");
	$head[$h][2] = "setup_deploy_server";
	$h++;

	$head[$h][0] = "setup_register_security.php";
	$head[$h][1] = $langs->trans("SecurityOfRegistrations");
	$head[$h][2] = "setup_register_security";
	$h++;

	$head[$h][0] = "setup_automation.php";
	$head[$h][1] = $langs->trans("Automation");
	$head[$h][2] = "setup_automation";
	$h++;

	$head[$h][0] = "setup_reseller.php";
	$head[$h][1] = $langs->trans("ResellerProgram");
	$head[$h][2] = "setup_reseller";
	$h++;

	$head[$h][0] = "setup_endpoints.php";
	$head[$h][1] = $langs->trans("Endpoints");
	$head[$h][2] = "setup_endpoints";
	$h++;

	$head[$h][0] = "setup_other.php";
	$head[$h][1] = $langs->trans("Other");
	$head[$h][2] = "setup_other";
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	// $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
	// $this->tabs = array('entity:-tabname);   												to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'useradmin');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'useradmin', 'remove');

	return $head;
}


/**
 * Function with remote API call to check registration data quality. Can check also captcha.
 *
 * @param	string		$remoteip		User remote IP
 * @param	boolean		$whitelisted	Is the IP or email white listed ?
 * @param	string		$email			User remote email
 * @param	int			$checkcaptcha	Check also captcha (check will not be done when deploying from customer dashboard for exemple, where user is already logged)
 * @return 	string[]					Array with ipquality (string with different scores), emailquality (string with different scores), vpnproba, abusetest, fraudscoreip, fraudscoreemail
 */
function getRemoteCheck($remoteip, $whitelisted, $email, $checkcaptcha = 1)
{
	global $conf, $db;

	$vpnproba = '';
	$ipquality = '';
	$emailquality = '';
	$fraudscoreip = 0;
	$fraudscoreemail = 0;
	$abusetest = 0;

	$errormessage = '';

	require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

	dol_syslog("getRemoteCheck remoteip=".$remoteip." email=".$email." whitelisted=".$whitelisted, LOG_INFO);

	// Check the captcha
	if ($checkcaptcha && getDolGlobalString('SELLYOURSAAS_GOOGLE_RECAPTCHA_ON') && $remoteip != '127.0.0.1') {
		dol_syslog("getRemoteCheck Check using Google Recaptcha", LOG_DEBUG);

		$grecaptcharesponse = GETPOST('g-recaptcha-response', 'alphanohtml');

		$message= "secret=".getDolGlobalString('SELLYOURSAAS_GOOGLE_RECAPTCHA_SECRET_KEY').'&response='.urlencode($grecaptcharesponse)."&remoteip=".urlencode(getUserRemoteIP());
		$urltocall = 'https://www.google.com/recaptcha/api/siteverify';

		// Check validation of the captcha
		$resurl = getURLContent($urltocall, 'POST', $message);

		if (empty($resurl['curl_error_no']) && !empty($resurl['http_code']) && $resurl['http_code'] == 200) {
			$jsonresult = json_decode($resurl['content']);
			$keyforerrorcode = 'error-codes';
			$errorcodes = $jsonresult->$keyforerrorcode;

			if (! $jsonresult->success) {
				// Output the key "Instance creation blocked for"
				dol_syslog("InstanceCreationBlockedForSecurityPurpose: Instance creation blocked for remoteip ".$remoteip.", bad validation of captcha", LOG_WARNING);

				$abusetest = 10;
				$errormessage = "Error in captcha validation. ".implode(', ', $errorcodes);
			} else {
				// TODO Set a min score different according to country
				$mingrecaptcha = (float) getDolGlobalString('SELLYOURSAAS_GOOGLE_RECAPTCHA_MIN_SCORE', 0);

				dol_syslog("getRemoteCheck Google Recaptcha score=".$jsonresult->score." min=".$mingrecaptcha, LOG_DEBUG);

				$ipquality .= 'grecaptcha-score='.$jsonresult->score.';';

				if ($jsonresult->score <= $mingrecaptcha) {
					// Output the key "Instance creation blocked for"
					dol_syslog("InstanceCreationBlockedForSecurityPurpose: Instance creation blocked for remoteip ".$remoteip.", score ".$jsonresult->score." is lower than ".$mingrecaptcha, LOG_DEBUG);
					$abusetest = 10;
				}
			}
		} else {
			dol_syslog("getRemoteCheck Captcha validation fails.", LOG_ERR);
			// We do not stop
		}
	}

	// Check email with disposablemail
	if (empty($abusetest) && getDolGlobalInt('SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED') && getDolGlobalString('SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_API_KEY')) {
		dol_syslog("getRemoteCheck Check using DisposableEmail", LOG_DEBUG);

		$allowed = false;
		$disposable = false;
		$allowedemail = (getDolGlobalString('SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ALLOWED') ? json_decode($conf->global->SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ALLOWED, true) : array());
		$bannedemail = (getDolGlobalString('SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_BANNED') ? json_decode($conf->global->SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_BANNED, true) : array());
		$parts = explode("@", $email);
		$domaintocheck = $parts[1];

		// Check cache of domain already check and allowed
		if (! empty($allowedemail)) {
			foreach ($allowedemail as $alloweddomainname) {
				if ($alloweddomainname == $domaintocheck) {
					$allowed = true;
					break;
				}
			}
		}

		// If not found in allowed database
		if ($allowed === false) {
			// Check cache of domain already check and banned
			if (! empty($bannedemail)) {
				foreach ($bannedemail as $banneddomainname) {
					if ($banneddomainname == $domaintocheck) {
						$disposable = true;
						break;
					}
				}
			}

			// Check in API Block Disposable E-mail database
			if ($disposable === false) {
				$emailtowarn = getDolGlobalString('SELLYOURSAAS_MAIN_EMAIL', getDolGlobalString('MAIN_INFO_SOCIETE_MAIL'));
				$apikey = getDolGlobalString('SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_API_KEY');

				// Check if API account and credit are ok
				$request = "https://status.block-disposable-email.com/status/?apikey=".$apikey;
				$resulturl = getURLContent($request);
				$result = $resulturl['content'];
				$resultData = json_decode($result, true);

				if ($resultData["request_status"] == "ok" && $resultData["apikeystatus"] == "active" && $resultData["credits"] > "0") {
					$request = 'https://api.block-disposable-email.com/easyapi/json/'.$apikey.'/'.$domaintocheck;
					$resulturl = getURLContent($request);
					$result = $resulturl['content'];
					$resultData = json_decode($result, true);

					if ($resultData["request_status"] == "success") {
						require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

						// domain is allowed
						if ($resultData["domain_status"] == "ok") {
							array_push($allowedemail, $domaintocheck);
							dolibarr_set_const($db, 'SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ALLOWED', json_encode($allowedemail), 'chaine', 0, '', $conf->entity);
						} elseif ($resultData["domain_status"] == "block") {
							array_push($bannedemail, $domaintocheck);
							dolibarr_set_const($db, 'SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_BANNED', json_encode($bannedemail), 'chaine', 0, '', $conf->entity);

							// Output the key "Instance creation blocked for"
							dol_syslog("ErrorEMailAddressBannedForSecurityReasons Instance creation blocked for email ".$emailtowarn);
							$abusetest = 50; // blocked email with api
						} else {
							// Output the key "Instance creation blocked for"
							dol_syslog("ErrorTechnicalErrorOccurredPleaseContactUsByEmail Instance creation blocked for email ".$emailtowarn);
							$abusetest = 99; // technical error
						}
					} else {
						// Output the key "Instance creation blocked for"
						dol_syslog("ErrorTechnicalErrorOccurredPleaseContactUsByEmail Instance creation blocked for email ".$emailtowarn);
						$abusetest = 98; // technical error
					}
				} else {
					// Output the key "Instance creation blocked for"
					dol_syslog("ErrorTechnicalErrorOccurredPleaseContactUsByEmail Instance creation blocked for email ".$emailtowarn);
					$abusetest = 97; // customer api access error
				}
			} else {
				// Output the key "Instance creation blocked for"
				dol_syslog("ErrorEMailAddressBannedForSecurityReasons Instance creation blocked for email ".$emailtowarn);
				$abusetest = 55; // blocked email in cache
			}
		}
	}

	// Evaluate VPN probability with GetIPIntel
	if (empty($abusetest) && getDolGlobalString('SELLYOURSAAS_GETIPINTEL_ON')) {
		dol_syslog("getRemoteCheck Check using GETIPIntel", LOG_DEBUG);

		$emailforvpncheck='contact+checkcustomer@mysaasdomainname.com';
		if (getDolGlobalString('SELLYOURSAAS_GETIPINTEL_EMAIL')) {
			$emailforvpncheck = getDolGlobalString('SELLYOURSAAS_GETIPINTEL_EMAIL');
		}
		$url = 'http://check.getipintel.net/check.php?ip='.urlencode($remoteip).'&contact='.urlencode($emailforvpncheck).'&flag=f';
		$result = getURLContent($url, 'GET', '', 1, array(), array('http', 'https'), 0);
		/* The proxy check system will return negative values on error. For standard format (non-json), an additional HTTP 400 status code is returned
		 -1 Invalid no input
		 -2 Invalid IP address
		 -3 Unroutable address / private address
		 -4 Unable to reach database, most likely the database is being updated. Keep an eye on twitter for more information.
		 -5 Your connecting IP has been banned from the system or you do not have permission to access a particular service. Did you exceed your query limits? Did you use an invalid email address? If you want more information, please use the contact links below.
		 -6 You did not provide any contact information with your query or the contact information is invalid.
		 If you exceed the number of allowed queries, you'll receive a HTTP 429 error.
		 */
		if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
			$vpnproba = (float) price2num($result['content'], 2, 1);
			$vpnproba = round($vpnproba, 2);
			$ipquality .= 'geti-vpn='.$vpnproba.';';
		} else {
			$vpnproba = '';
			$ipquality .= 'geti-check failed. http_code = '.dol_trunc($result['http_code'], 100).';';
		}

		// Refused if VPN probability from GetIP is too high
		if (!$whitelisted && empty($abusetest) && getDolGlobalString('SELLYOURSAAS_VPN_PROBA_REFUSED')) {
			$conf->global->SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED = 85;

			if (!getDolGlobalString('SELLYOURSAAS_IPQUALITY_ON')) {
				// If not other check, we get default $fraudscoreip = 99
				$fraudscoreip = 99;		// getintel is very important because we did not do other tests with IPQuality
			} else {
				$fraudscoreip = 1;		// getintel is not very important, we set a fraudscore always lower than the threshold
			}

			// We test if fraudscore is accepted or not
			if (is_numeric($vpnproba) && $vpnproba >= (float) getDolGlobalString('SELLYOURSAAS_VPN_PROBA_REFUSED') && ($fraudscoreip >= (float) getDolGlobalString('SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED'))) {
				// Output the key "Instance creation blocked for"
				dol_syslog("Instance creation blocked for ".$remoteip." - VPN probability ".$vpnproba." is higher or equal than " . getDolGlobalString('SELLYOURSAAS_VPN_PROBA_REFUSED').' with a fraudscore '.$fraudscoreip.' >= ' . getDolGlobalString('SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED'));
				$abusetest = 1;
			}
		}
	}

	// Evaluate VPN probability with IPQualityScore but also TOR or bad networks and email
	if (getDolGlobalString('SELLYOURSAAS_IPQUALITY_ON') && empty($abusetest) && getDolGlobalString('SELLYOURSAAS_IPQUALITY_KEY')) {
		dol_syslog("getRemoteCheck Check using IP Quality", LOG_DEBUG);

		// Retrieve additional (optional) data points which help us enhance fraud scores.
		$user_agent = (empty($_SERVER["HTTP_USER_AGENT"]) ? '' : $_SERVER["HTTP_USER_AGENT"]);
		$user_language = (empty($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? '' : $_SERVER["HTTP_ACCEPT_LANGUAGE"]);

		// Set the strictness for this query. (0 (least strict) - 3 (most strict))
		$strictness = 1;

		// You may want to allow public access points like coffee shops, schools, corporations, etc...
		$allow_public_access_points = 'true';

		// Reduce scoring penalties for mixed quality IP addresses shared by good and bad users.
		$lighter_penalties = 'true';

		// Create parameters array.
		$parameters = array(
			'user_agent' => $user_agent,
			'user_language' => $user_language,
			'strictness' => $strictness,
			'allow_public_access_points' => $allow_public_access_points,
			'lighter_penalties' => $lighter_penalties
		);

		/* User & Transaction Scoring
		 * Score additional information from a user, order, or transaction for risk analysis
		 * Please see the documentation and example code to include this feature in your scoring:
		 * https://www.ipqualityscore.com/documentation/proxy-detection/transaction-scoring
		 * This feature requires a Premium plan or greater
		 */
		$transaction_parameters = array();

		// Format Parameters
		if (is_array($transaction_parameters) && count($transaction_parameters)) {
			$formatted_parameters = http_build_query(array_merge($parameters, $transaction_parameters));
		} else {
			$formatted_parameters = http_build_query($parameters);
		}

		// Create API URL for IP Check
		$url = sprintf(
			'https://www.ipqualityscore.com/api/json/ip/%s/%s?%s',
			getDolGlobalString('SELLYOURSAAS_IPQUALITY_KEY'),
			urlencode($remoteip),
			$formatted_parameters
		);

		$result = getURLContent($url);
		if (is_array($result) && $result['http_code'] == 200 && !empty($result['content'])) {
			try {
				dol_syslog("Result of call of ipqualityscore: ".$result['content'], LOG_DEBUG);
				$jsonreponse = json_decode($result['content'], true);
				dol_syslog("For ip ".$remoteip.": fraud_score=".$jsonreponse['fraud_score']." - is_crawler=".$jsonreponse['is_crawler']." - vpn=".$jsonreponse['vpn']." - recent_abuse=".$jsonreponse['recent_abuse']." - tor=".($jsonreponse['tor'] || $jsonreponse['active_tor']));
				if ($jsonreponse['success']) {
					if ($jsonreponse['recent_abuse'] && getDolGlobalString('SELLYOURSAAS_IPQUALITY_BLOCK_ABUSING_IP')) {	// Not recommanded if users are using shared IP
						dol_syslog("Instance creation blocked for ".$remoteip." - This is an IP with recent abuse reported");
						$abusetest = 2;
					}
					if ($jsonreponse['tor'] || $jsonreponse['active_tor']) {
						// So recommanded that is it enabled always, no option to disable this
						dol_syslog("Instance creation blocked for ".$remoteip." - This is a TOR or evil IP - host=".$jsonreponse['host']);
						$abusetest = 3;
					}
					$ipquality .= 'ipq-tor='.(($jsonreponse['tor'] || $jsonreponse['active_tor']) ? 1 : 0).';';
					$ipquality .= 'ipq-vpn='.(($jsonreponse['vpn'] || $jsonreponse['active_vpn']) ? 1 : 0).';';
					$ipquality .= 'ipq-recent_abuse='.($jsonreponse['recent_abuse'] ? 1 : 0).';';
					$ipquality .= 'ipq-fraud_score='.$jsonreponse['fraud_score'].';';
					$ipquality .= 'ipq-host='.$jsonreponse['host'].';';
					$fraudscoreip = (int) $jsonreponse['fraud_score'];

					if ($vpnproba === '') {
						// If vpn proba was not found with getip, we use the one found from ipqualityscore
						$vpnproba = (($jsonreponse['vpn'] || $jsonreponse['active_vpn']) ? 1 : 0);
						$vpnproba = round($vpnproba, 2);
					}
				} else {
					$ipquality .= 'ipq-check failed. Success property not found. '.dol_trunc($result['content'], 100).';';
				}
			} catch (Exception $e) {
				$ipquality .= 'ipq-check failed. Exception '.dol_trunc($e->getMessage(), 100).';';
			}
		} else {
			$ipquality .= 'ipq-check failed. http_code = '.dol_trunc($result['http_code'], 100).';';
		}

		// Refused if VPN probability from IPQuality is too high
		if (!$whitelisted && empty($abusetest) && getDolGlobalString('SELLYOURSAAS_VPN_PROBA_REFUSED')) {
			$conf->global->SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED = 85;

			if (is_numeric($vpnproba) && $vpnproba >= (float) getDolGlobalString('SELLYOURSAAS_VPN_PROBA_REFUSED') && ($fraudscoreip >= (float) getDolGlobalString('SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED'))) {
				// Output the key "Instance creation blocked for"
				dol_syslog("Instance creation blocked for ".$remoteip." - IPQuality VPN probability ".$vpnproba." is higher or equal than " . getDolGlobalString('SELLYOURSAAS_VPN_PROBA_REFUSED').' with a fraudscore '.$fraudscoreip.' >= ' . getDolGlobalString('SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED'));
				$abusetest = 1;
			}
		}


		// Create API URL for Email Check
		$url = sprintf(
			'https://www.ipqualityscore.com/api/json/email/%s/%s?%s',
			getDolGlobalString('SELLYOURSAAS_IPQUALITY_KEY'),
			urlencode($email),
			$formatted_parameters
		);

		$result = getURLContent($url);
		if (is_array($result) && $result['http_code'] == 200 && !empty($result['content'])) {
			try {
				dol_syslog("Result of call of ipqualityscore: ".$result['content'], LOG_DEBUG);
				$jsonreponse = json_decode($result['content'], true);
				dol_syslog("For email ".$email.": valid=".$jsonreponse['valid']." - disposable=".$jsonreponse['disposable']." - dns_valid=".$jsonreponse['dns_valid']." - timed_out=".$jsonreponse['timed_out']);
				if ($jsonreponse['success']) {
					$emailquality .= 'ipq-valid='.$jsonreponse['valid'].';';
					$emailquality .= 'ipq-disposable='.$jsonreponse['disposable'].';';
					$emailquality .= 'ipq-dns_valid='.$jsonreponse['dns_valid'].';';
					$emailquality .= 'ipq-timed_out='.$jsonreponse['timed_out'].';';
					$emailquality .= 'ipq-recent_abuse='.$jsonreponse['recent_abuse'].';';
				} else {
					$emailquality .= 'ipq-check failed. Success property not found. '.dol_trunc($result['content'], 100).';';
				}
			} catch (Exception $e) {
				$emailquality .= 'ipq-check failed. Exception '.dol_trunc($e->getMessage(), 100).';';
			}
		} else {
			$emailquality .= 'ipq-check failed. http_code = '.dol_trunc($result['http_code'], 100).';';
		}

		// Refused if Email fraud probability is too high
		if (!$whitelisted && empty($abusetest)) {
			if ($jsonreponse['recent_abuse'] === false && ($jsonreponse['valid'] === true || ($jsonreponse['timed_out'] === true && $jsonreponse['disposable'] === false && $jsonreponse['dns_valid'] === true))) {
				// Email valid
			} else {
				$maxfraudscoreemail = getDolGlobalInt('SELLYOURSAAS_EMAIL_FRAUDSCORE_REFUSED', 100);
				if ($fraudscoreemail >= $maxfraudscoreemail) {
					// Output the key "Instance creation blocked for"
					dol_syslog("Instance creation blocked for email ".$email." - Email fraudscoreemail ".$fraudscoreemail." is higher or equal than " . $maxfraudscoreemail);
					// TODO Enabled this
					//$abusetest = 6;
				}
			}
		}
	}


	// SELLYOURSAAS_BLACKLIST_IP_MASKS and SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN are hidden constants.
	// Deprecated. Check instead into the list of blacklist ips in database. This is done at begin of page.

	// Block for some IPs
	if (!$whitelisted && empty($abusetest) && getDolGlobalString('SELLYOURSAAS_BLACKLIST_IP_MASKS')) {
		dol_syslog("getRemoteCheck Check using SELLYOURSAAS_BLACKLIST_IP_MASKS", LOG_DEBUG);

		$arrayofblacklistips = explode(',', getDolGlobalString('SELLYOURSAAS_BLACKLIST_IP_MASKS'));
		foreach ($arrayofblacklistips as $blacklistip) {
			if ($remoteip == $blacklistip) {
				// Output the key "Instance creation blocked for"
				dol_syslog("Instance creation blocked for ".$remoteip." - This IP is in blacklist SELLYOURSAAS_BLACKLIST_IP_MASKS");
				$abusetest = 4;
			}
		}
	}

	// Block for some IPs if VPN proba is higher that a threshold
	if (!$whitelisted && empty($abusetest) && getDolGlobalString('SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN')) {
		dol_syslog("getRemoteCheck Check using SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN", LOG_DEBUG);

		if (is_numeric($vpnproba) && $vpnproba >= (float) getDolGlobalString('SELLYOURSAAS_VPN_PROBA_FOR_BLACKLIST', 1)) {
			$arrayofblacklistips = explode(',', getDolGlobalString('SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN'));
			foreach ($arrayofblacklistips as $blacklistip) {
				if ($remoteip == $blacklistip) {
					// Output the key "Instance creation blocked for"
					dol_syslog("Instance creation blocked for ".$remoteip." - This IP is in blacklist SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN");
					$abusetest = 5;
				}
			}
		}
	}

	return array('ipquality'=>$ipquality, 'emailquality'=>$emailquality, 'vpnproba'=>$vpnproba, 'abusetest'=>$abusetest, 'fraudscoreip'=>$fraudscoreip, 'fraudscoreemail'=>$fraudscoreemail, 'error'=>$errormessage);
}

/**
 * Function to get nb of users for a certain contract
 *
 * @param	string|Contrat		$contractref			Ref of contract for user count or contract
 * @param	ContratLigne		$contractline			Contract line
 * @param	string				$codeextrafieldqtymin	Code of extrafield to find minimum qty of users
 * @param	string				$sqltoexecute			SQL to execute to get nb of users in customer instance
 * @return 	int											<0 if error or Number of users for contract
 */
function sellyoursaasGetNbUsersContract($contractref, $contractline, $codeextrafieldqtymin, $sqltoexecute)
{
	global $db;

	if (is_object($contractref)) {
		$contract = $contractref;
	} else {
		require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
		$contract = new Contrat($db);
		$result = $contract->fetch(0, $contractref);
		if ($result <= 0) {
			setEventMessages($contract->error, $contract->errors, 'errors');
			return -1;
		}
	}

	$server = $contract->ref_customer;
	if (empty($server)) {
		$server = $contract->array_options['options_hostname_db'];
	}
	$port_db = $contract->port_db;
	if (empty($port_db)) {
		$port_db = (! empty($contract->array_options['options_port_db']) ? $contract->array_options['options_port_db'] : 3306);
	}
	$username_db = $contract->username_db;
	if (empty($username_db)) {
		$username_db = $contract->array_options['options_username_db'];
	}
	$password_db = $contract->password_db;
	if (empty($password_db)) {
		$password_db = $contract->array_options['options_password_db'];
	}
	$database_db = $contract->database_db;
	if (empty($database_db)) {
		$database_db = $contract->array_options['options_database_db'];
	}

	$newdb = getDoliDBInstance('mysqli', $server, $username_db, $password_db, $database_db, $port_db);
	if (!$newdb->connected) {
		dol_print_error($newdb);
		return -1;
	}

	$nbusersql = 0;
	$nbuserextrafield = 0;
	$qtyuserline = 0;

	// Note: this sql request should contains the correct SQL with the correct prefix on table
	$sqltoexecute = trim($sqltoexecute);

	// Set vars so we can use same code than into sellyoursaasutils.class.php
	$sqlformula = $sqltoexecute;
	$dbinstance = $newdb;
	$newqty = null;	// If $newqty remains null, we won't change/record value.
	$newcommentonqty = '';
	$error = 0;

	dol_syslog("Execute sql=".$sqltoexecute);

	$resql=$newdb->query($sqltoexecute);
	if ($resql) {
		if (preg_match('/^select count/i', $sqlformula)) {
			// If request is a simple SELECT COUNT
			$objsql = $dbinstance->fetch_object($resql);
			if ($objsql) {
				$newqty = $objsql->nb;
				$newcommentonqty .= '';
			} else {
				$error++;
				dol_syslog('sellyoursaasGetNbUsersContract: SQL to get resources returns error for '.$object->ref.' - '.$producttmp->ref.' - '.$sqlformula);
				//$this->error = 'sellyoursaasRemoteAction: SQL to get resources returns error for '.$object->ref.' - '.$producttmp->ref.' - '.$sqlformula;
				//$this->errors[] = $this->error;
			}
		} else {
			// If request is a SELECT nb, fieldlogin as comment
			$num = $dbinstance->num_rows($resql);
			if ($num > 0) {
				$itmp = 0;
				$arrayofcomment = array();
				while ($itmp < $num) {
					// If request is a list to count
					$objsql = $dbinstance->fetch_object($resql);
					if ($objsql) {
						if (empty($newqty)) {
							$newqty = 0;	// To have $newqty not null and allow addition just after
						}
						$newqty += (isset($objsql->nb) ? $objsql->nb : 1);
						if (isset($objsql->comment)) {
							$arrayofcomment[] = $objsql->comment;
						}
					}
					$itmp++;
				}
				//$newcommentonqty .= 'Qty '.$producttmp->ref.' = '.$newqty."\n";
				$newcommentonqty .= 'User Accounts ('.$newqty.') : '.join(', ', $arrayofcomment)."\n";
			} else {
				$error++;
				dol_syslog('sellyoursaasGetNbUsersContract: SQL to get resource list returns empty list for '.$object->ref.' - '.$producttmp->ref.' - '.$sqlformula);
				//$this->error = 'sellyoursaasRemoteAction: SQL to get resource list returns empty list for '.$object->ref.' - '.$producttmp->ref.' - '.$sqlformula;
				//$this->errors[] = $this->error;
			}
			if ($newqty) {
				$nbusersql = $newqty;
			}
		}
	} else {
		$nbusersql = -1;	// Error
	}

	if (is_object($newdb) && $newdb->connected) {
		$newdb->close();
	}

	if (!empty($contractline->array_options["options_".$codeextrafieldqtymin])) {
		$nbuserextrafield = $contractline->array_options["options_".$codeextrafieldqtymin]; // Get qty min of user contract line
	}

	if ($error) {
		return -1;
	}

	// Return the max qty of all the qty get
	$ret = max($nbusersql, $nbuserextrafield);
	dol_syslog("sellyoursaasGetNbUsersContract ret=".$ret);

	return $ret;
}

/**
 * Function to know if we are in trial, free mode or paid mode
 * @param	Contrat		$contract				Contract
 * @param	Societe		$mythirdpartyaccount	Thirdparty
 * @return 	int									0 if trial mode, 1 if paid mode, 2 free mode
 */
function sellyoursaasGetModeStatusInstance($contract, $mythirdpartyaccount){
	$modeinstancestatus = 0;
	$ispaid = sellyoursaasIsPaidInstance($contract);
	if ($ispaid) {
		if ((empty($mythirdpartyaccount->array_options['options_checkboxnonprofitorga']) || $mythirdpartyaccount->array_options['options_checkboxnonprofitorga'] == 'nonprofit') && getDolGlobalInt("SELLYOURSAAS_ENABLE_FREE_PAYMENT_MODE")) {
			$modeinstancestatus = 2;
		} else {
			$modeinstancestatus = 1;
		}
	}
	return $modeinstancestatus;
}
