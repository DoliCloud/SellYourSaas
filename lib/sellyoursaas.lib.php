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
		return (string) (empty($conf->global->$key) ? $default : $conf->global->$key);
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
		return (int) (empty($conf->global->$key) ? $default : $conf->global->$key);
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
		if (! empty($conf->global->STRIPE_LIVE) && ! GETPOST('forcesandbox', 'alpha') && empty($conf->global->SELLYOURSAAS_FORCE_STRIPE_TEST)) {
			$service = 'StripeLive';
			$servicestatusstripe = 1;
		}
	}
	$servicestatuspaypal = 0;
	if (! empty($conf->paypal->enabled)) {
		$servicestatuspaypal = 0;
		if (! empty($conf->global->PAYPAL_LIVE) && ! GETPOST('forcesandbox', 'alpha') && empty($conf->global->SELLYOURSAAS_FORCE_PAYPAL_TEST)) {
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

	$tmpret = explode(',', $conf->global->SELLYOURSAAS_ACCOUNT_URL);     // By default
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
 * Function with remote API call to check registration data quality
 *
 * @param	string		$remoteip		User remote IP
 * @param	boolean		$whitelisted	true or flase
 * @param	string		$email			User remote email
 * @return 	string[]					Array with ipquality, emailquality, vpnproba, abusetest, fraudscoreip, fraudscoreemail
 */
function getRemoteCheck($remoteip, $whitelisted, $email)
{
	global $conf;

	$vpnproba = '';
	$ipquality = '';
	$emailquality = '';
	$fraudscoreip = 0;
	$fraudscoreemail = 0;
	$abusetest = 0;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

	dol_syslog("getRemoteCheck");

	// TODO Insert evaluation by disposablemail here


	// Evaluate VPN probability with Getintel
	if (!empty($conf->global->SELLYOURSAAS_GETIPINTEL_ON)) {
		$emailforvpncheck='contact+checkcustomer@mysaasdomainname.com';
		if (!empty($conf->global->SELLYOURSAAS_GETIPINTEL_EMAIL)) {
			$emailforvpncheck = $conf->global->SELLYOURSAAS_GETIPINTEL_EMAIL;
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
		if (!$whitelisted && empty($abusetest) && !empty($conf->global->SELLYOURSAAS_VPN_PROBA_REFUSED)) {
			$conf->global->SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED = 85;

			if (empty($conf->global->SELLYOURSAAS_IPQUALITY_ON)) {
				// If not other check, we get default $fraudscoreip = 99
				$fraudscoreip = 99;		// getintel is very important because we did not do other tests with IPQuality
			} else {
				$fraudscoreip = 1;		// getintel is not very important, we set a fraudscore always lower than the threshold
			}

			// We test if fraudscore is accepted or not
			if (is_numeric($vpnproba) && $vpnproba >= (float) $conf->global->SELLYOURSAAS_VPN_PROBA_REFUSED && ($fraudscoreip >= $conf->global->SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED)) {
				dol_syslog("Instance creation blocked for ".$remoteip." - VPN probability ".$vpnproba." is higher or equal than " . getDolGlobalString('SELLYOURSAAS_VPN_PROBA_REFUSED').' with a fraudscore '.$fraudscoreip.' >= ' . getDolGlobalString('SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED'));
				$abusetest = 1;
			}
		}
	}

	// Evaluate VPN probability with IPQualityScore but also TOR or bad networks and email
	if (!empty($conf->global->SELLYOURSAAS_IPQUALITY_ON) && empty($abusetest) && !empty($conf->global->SELLYOURSAAS_IPQUALITY_KEY)) {
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
			$conf->global->SELLYOURSAAS_IPQUALITY_KEY,
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
					if ($jsonreponse['recent_abuse'] && !empty($conf->global->SELLYOURSAAS_IPQUALITY_BLOCK_ABUSING_IP)) {	// Not recommanded if users are using shared IP
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
		if (!$whitelisted && empty($abusetest) && !empty($conf->global->SELLYOURSAAS_VPN_PROBA_REFUSED)) {
			$conf->global->SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED = 85;

			if (is_numeric($vpnproba) && $vpnproba >= (float) $conf->global->SELLYOURSAAS_VPN_PROBA_REFUSED && ($fraudscoreip >= $conf->global->SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED)) {
				dol_syslog("Instance creation blocked for ".$remoteip." - IPQuality VPN probability ".$vpnproba." is higher or equal than " . getDolGlobalString('SELLYOURSAAS_VPN_PROBA_REFUSED').' with a fraudscore '.$fraudscoreip.' >= ' . getDolGlobalString('SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED'));
				$abusetest = 1;
			}
		}


		// Create API URL for Email Check
		$url = sprintf(
			'https://www.ipqualityscore.com/api/json/email/%s/%s?%s',
			$conf->global->SELLYOURSAAS_IPQUALITY_KEY,
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
				dol_syslog("Instance creation blocked for email ".$email." - Email fraud probability ".$fraudscoreemail." is higher or equal than " . getDolGlobalString('SELLYOURSAAS_EMAIL_FRAUDSCORE_REFUSED'));
				// TODO Enable this
				// $abusetest = 6;
			}
		}
	}


	// SELLYOURSAAS_BLACKLIST_IP_MASKS and SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN are hidden constants.
	// Deprecated. Use instead the List of blacklist ips into menu. This is done a begin of page

	// Block for some IPs
	if (!$whitelisted && empty($abusetest) && !empty($conf->global->SELLYOURSAAS_BLACKLIST_IP_MASKS)) {
		$arrayofblacklistips = explode(',', $conf->global->SELLYOURSAAS_BLACKLIST_IP_MASKS);
		foreach ($arrayofblacklistips as $blacklistip) {
			if ($remoteip == $blacklistip) {
				dol_syslog("Instance creation blocked for ".$remoteip." - This IP is in blacklist SELLYOURSAAS_BLACKLIST_IP_MASKS");
				$abusetest = 4;
			}
		}
	}

	// Block for some IPs if VPN proba is higher that a threshold
	if (!$whitelisted && empty($abusetest) && !empty($conf->global->SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN)) {
		if (is_numeric($vpnproba) && $vpnproba >= (empty($conf->global->SELLYOURSAAS_VPN_PROBA_FOR_BLACKLIST) ? 1 : (float) $conf->global->SELLYOURSAAS_VPN_PROBA_FOR_BLACKLIST)) {
			$arrayofblacklistips = explode(',', $conf->global->SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN);
			foreach ($arrayofblacklistips as $blacklistip) {
				if ($remoteip == $blacklistip) {
					dol_syslog("Instance creation blocked for ".$remoteip." - This IP is in blacklist SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN");
					$abusetest = 5;
				}
			}
		}
	}

	return array('ipquality'=>$ipquality, 'emailquality'=>$emailquality, 'vpnproba'=>$vpnproba, 'abusetest'=>$abusetest, 'fraudscoreip'=>$fraudscoreip, 'fraudscoreemail'=>$fraudscoreemail);
}

/**
 * Function to get nb of users for a certain contract
 *
 * @param	int			$userproductid	Id of product for user count
 * @return 	int							<0 if error or Number of users for contract
 */
function sellyoursaasGetNbUsersContract($userproductid = 0) {
	global $db, $object;

	$dbprefix = $object->array_options['options_prefix_db'];
	if (empty($dbprefix)) {
		$dbprefix = MAIN_DB_PREFIX;
	}
	$loginforsupport  = getDolGlobalString("SELLYOURSAAS_LOGIN_FOR_SUPPORT");

	$server = $object->ref_customer;
	if (empty($hostname_db)) {
		$hostname_db = $object->array_options['options_hostname_db'];
	}
	$port_db = $object->port_db;
	if (empty($port_db)) {
		$port_db = (! empty($object->array_options['options_port_db']) ? $object->array_options['options_port_db'] : 3306);
	}
	$username_db = $object->username_db;
	if (empty($username_db)) {
		$username_db = $object->array_options['options_username_db'];
	}
	$password_db = $object->password_db;
	if (empty($password_db)) {
		$password_db = $object->array_options['options_password_db'];
	}
	$database_db = $object->database_db;
	if (empty($database_db)) {
		$database_db = $object->array_options['options_database_db'];
	}

	$newdb = getDoliDBInstance('mysqli', $server, $username_db, $password_db, $database_db, $port_db);
	if (!$newdb->connected) {
		dol_print_error($newdb);
		return -1;
	}

	$nbusersql = 0;
	$nbuserextrafield = 0;
	$qtyuserline = 0;

	$sql = "SELECT count(rowid) as nb";
	$sql .= " FROM ".$dbprefix."user";
	$sql .= " WHERE statut = 1";
	$sql .= " AND login != '".$loginforsupport."'";
	$sql .= " AND (fk_socpeople IS NULL OR fk_socpeople = 0)";
	$resql=$newdb->query($sql);
	if ($resql) {
		$obj = $newdb->fetch_object($resql);
		$nbusersql = $obj->nb;
	} else {
		dol_print_error($db);
	}

	$contractlines = $object->lines;

	foreach ($contractlines as $contractline) {
		if (empty($userproductid) || $contractline->fk_product == $userproductid) {
			$contractline->fetch_optionals();
			if (!empty($contractline->array_options["options_qtymin"])) {
				$qtyuserline = $contractline->qty; //Get qty of user contract line
				$nbuserextrafield = $contractline->array_options["options_qtymin"]; //Get qty min of user contract line
			}
		}
	}

	// Return the max qty off all the qty get
	return max($nbusersql, $nbuserextrafield, $qtyuserline);
}