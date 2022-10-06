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
function cmp($a, $b)
{
	return strcmp($a->date, $b->date);
}

/**
 * To compare on date property (reverse)
 *
 * @param 	int 	$a		Date A
 * @param 	int 	$b		Date B
 * @return 	boolean			Result of comparison
 */
function cmpr_invoice_object_date_desc($a, $b)
{
	if ($a->date == $b->date) {
		return strcmp($b->id, $a->id);
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
	$sql.= " AND fk_soc = ".$thirdpartyidtotest;
	$sql.= " AND (type = 'ban' OR (type = 'card' AND status = ".$servicestatusstripe.") OR (type = 'paypal' AND status = ".$servicestatuspaypal."))";
	$sql.= " ORDER BY default_rib DESC, tms DESC";

	$resqltmp = $db->query($sql);
	if ($resqltmp) {
		$num_rows = $db->num_rows($resqltmp);
		if ($num_rows) {
			$i=0;
			while ($i < $num_rows) {
				$objtmp = $db->fetch_object($resqltmp);
				if ($objtmp) {
					if ($objtmp->default_rib != 1) continue;	// Keep the default payment mode only
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
 * @param	int		$mode				0=Test invoice or template invoice of contract, 1=Test only templates invoices
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

	if ($foundtemplate) return 1;

	if ($mode == 0) {
		$foundinvoice=0;
		if (!empty($contract->linkedObjectsIds['facture']) && is_array($contract->linkedObjectsIds['facture'])) {
			foreach ($contract->linkedObjectsIds['facture'] as $idelementelement => $invoiceid) {
				$foundinvoice++;
				break;
			}
		}

		if ($foundinvoice) return 1;
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
	// Note: we suppose that if a payment as correctly done after the invoice has also been closed so the invoice will not be reported here.
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

	/* old method
	$loadalsoobjects = 1;	// We nee the object 'facture' to test its status
	$contract->fetchObjectLinked(null, '', null, '', 'OR', 1, 'sourcetype', $loadalsoobjects);

	if (is_array($contract->linkedObjects['facture'])) {
		foreach ($contract->linkedObjects['facture'] as $rowidelementelement => $invoice) {
			if ($invoice->statut == Facture::STATUS_CLOSED) {
				continue;
			}

			// The invoice is not paid, we check if there is at least one payment issue
			// See also request into index.php
			$sql = "SELECT id FROM ".MAIN_DB_PREFIX."actioncomm";
			$sql .= " WHERE elementtype = 'invoice' AND fk_element = ".$invoice->id;
			$sql .= " AND (code LIKE 'AC_PAYMENT_%_KO' OR label = 'Cancellation of payment by the bank')";
			$sql .= ' ORDER BY datep DESC';

			$resql=$db->query($sql);
			if ($resql) {
				$num = $db->num_rows($resql);
				$db->free($resql);
				return $num;
			} else {
				dol_print_error($db);
			}
		}
	}
	*/

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
			if ($invoice->statut == Facture::STATUS_CLOSED) continue;
			if ($invoice->statut == Facture::STATUS_ABANDONED) continue;
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
	if (! isset($cachefortmpprod) || ! is_array($cachefortmpprod)) $cachefortmpprod = array();

	dol_syslog("sellyoursaasGetExpirationDate for contract id=".$contract->id." onlyexpirationdate=".$onlyexpirationdate);

	// Loop on each line to get lowest expiration date
	foreach ($contract->lines as $line) {
		if ($line->date_end) {	// Planned end date of service
			if ($expirationdate > 0) $expirationdate = min($expirationdate, $line->date_end);
			else $expirationdate = $line->date_end;
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
	global $db;

	if ($contract->nbofserviceswait > 0 || $contract->nbofservicesopened > 0 || $contract->nbofservicesexpired > 0) return false;
	if ($contract->nbofservicesclosed > 0) return true;

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
