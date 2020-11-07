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


/**
 * To compare on date property
 *
 * @param date $a		Date A
 * @param date $b		Date B
 * @return boolean		Result of comparison
 */
function cmp($a, $b)
{
	return strcmp($a->date, $b->date);
}

/**
 * Return if a thirdparty has a payment mode
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
	if (! empty($conf->stripe->enabled))
	{
		$service = 'StripeTest';
		$servicestatusstripe = 0;
		if (! empty($conf->global->STRIPE_LIVE) && ! GETPOST('forcesandbox','alpha') && empty($conf->global->SELLYOURSAAS_FORCE_STRIPE_TEST))
		{
			$service = 'StripeLive';
			$servicestatusstripe = 1;
		}
	}
	$servicestatuspaypal = 0;
	if (! empty($conf->paypal->enabled))
	{
		$servicestatuspaypal = 0;
		if (! empty($conf->global->PAYPAL_LIVE) && ! GETPOST('forcesandbox','alpha') && empty($conf->global->SELLYOURSAAS_FORCE_PAYPAL_TEST))
		{
			$servicestatuspaypal = 1;
		}
	}


	// Fill array of company payment modes
	$sql = 'SELECT rowid, default_rib FROM '.MAIN_DB_PREFIX."societe_rib";
	$sql.= " WHERE type in ('ban', 'card', 'paypal')";
	$sql.= " AND fk_soc = ".$thirdpartyidtotest;
	$sql.= " AND (type = 'ban' OR (type='card' AND status = ".$servicestatusstripe.") OR (type='paypal' AND status = ".$servicestatuspaypal."))";
	$sql.= " ORDER BY default_rib DESC, tms DESC";

	$resqltmp = $db->query($sql);
	if ($resqltmp)
	{
		$num_rows = $db->num_rows($resqltmp);
		if ($num_rows)
		{
			$i=0;
			while ($i < $num_rows)
			{
				$objtmp = $db->fetch_object($resqltmp);
				if ($objtmp)
				{
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
 * @param 	Contrat $contract		Object contract
 * @param	int		$mode			0=Test invoice or template invoice of contract, 1=Test only templates invoices
 * @return	int						>0 if this is a paid contract
 */
function sellyoursaasIsPaidInstance($contract, $mode=0)
{
	$contract->fetchObjectLinked();

	$foundtemplate=0;
	if (is_array($contract->linkedObjects['facturerec']))
	{
		foreach($contract->linkedObjects['facturerec'] as $idtemplateinvoice => $templateinvoice)
		{
			$foundtemplate++;
			break;
		}
	}

	if ($foundtemplate) return 1;

	if ($mode == 0)
	{
		$foundinvoice=0;
		if (is_array($contract->linkedObjects['facture']))
		{
			foreach($contract->linkedObjects['facture'] as $idinvoice => $invoice)
			{
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
 * @param 	Contrat $contract		Object contract
 * @return	int						>0 if this is a contract with current payment error
 */
function sellyoursaasIsPaymentKo($contract)
{
	global $db;

	$contract->fetchObjectLinked();
	$paymenterror=0;

	if (is_array($contract->linkedObjects['facture']))
	{
		foreach($contract->linkedObjects['facture'] as $idinvoice => $invoice)
		{
			if ($invoice->statut == Facture::STATUS_CLOSED) continue;

			// The invoice is not paid, we check if there is at least one payment issue
			$sql=' SELECT id FROM '.MAIN_DB_PREFIX."actioncomm WHERE elementtype = 'invoice' AND fk_element = ".$invoice->id." AND code='INVOICE_PAYMENT_ERROR'";
			$resql=$db->query($sql);
			if ($resql)
			{
				$num=$db->num_rows($resql);
				$db->free($resql);
				return $num;
			}
			else dol_print_error($db);
		}
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

	if (is_array($contract->linkedObjects['facture']))
	{
		foreach($contract->linkedObjects['facture'] as $idinvoice => $invoice)
		{
			if ($invoice->statut == Facture::STATUS_CLOSED) continue;
			if ($invoice->statut == Facture::STATUS_ABANDONED) continue;
			if (empty($invoice->paid))
			{
				$atleastoneopeninvoice++;
			}
		}
	}

	return $atleastoneopeninvoice;
}


/**
 * Return date of expiration
 * Take lowest planed end date for services (whatever is service status)
 *
 * @param 	Contrat $contract		Object contract
 * @return	array					Array of data array('expirationdate'=>Timestamp of expiration date, or 0 if error or not found)
 */
function sellyoursaasGetExpirationDate($contract)
{
	global $db;

	$expirationdate = 0;
	$duration_value = 0;
	$duration_unit = '';
	$appproductid = 0;
	$status = 0;
	$nbofusers = 0;

	include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

	global $cachefortmpprod;
	if (! isset($cachefortmpprod) || ! is_array($cachefortmpprod)) $cachefortmpprod = array();

	// Loop on each line to get lowest expiration date
	foreach($contract->lines as $line)
	{
		if ($line->date_end)	// Planned end date of service
		{
			if ($expirationdate > 0) $expirationdate = min($expirationdate, $line->date_end);
			else $expirationdate = $line->date_end;
		}

		if ($line->fk_product > 0)
		{
			if (empty($cachefortmpprod[$line->fk_product]))
			{
				$tmpprod = new Product($db);
				$result = $tmpprod->fetch($line->fk_product);
				if ($result > 0)
				{
				    $cachefortmpprod[$line->fk_product] = $tmpprod;
				}
				else
				{
				    dol_syslog("Error, failed to fetch product with ID ".$line->fk_product, LOG_ERR);
				}
			}
			$prodforline = $cachefortmpprod[$line->fk_product];

			if ($prodforline->array_options['options_app_or_option'] == 'app')
			{
				$duration_value = $prodforline->duration_value;
				$duration_unit = $prodforline->duration_unit;
				$appproductid = $prodforline->id;

				$status = $line->statut;

				if (empty($duration_value) || empty($duration_unit))
				{
				    dol_syslog("Error, the definition of duration for product ID ".$prodforline->id." is uncomplete.", LOG_ERR);
				}
			}
			if ($prodforline->array_options['options_app_or_option'] == 'system')
			{
			    if ($prodforline->array_options['options_resource_label'] == 'User'
			    || preg_match('/user/i', $prodforline->ref)) {
			        $nbofusers = $line->qty;
			    }
			    if ($prodforline->array_options['options_resource_label'] == 'Gb'
			    || preg_match('/\sgb\s/i', $prodforline->ref)) {
			        $nbofgbs = $line->qty;
			    }
			}
		}
	}

	return array('status'=>$status, 'expirationdate'=>$expirationdate, 'duration_value'=>$duration_value, 'duration_unit'=>$duration_unit, 'nbusers'=>$nbofusers, 'nbofgbs'=>$nbofgbs, 'appproductid'=>$appproductid);
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

    // If $object is a contract, we take ref_c
    if (get_class($newobject) == 'Contrat')
    {
        include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
        $ret = 'https://myaccount.'.getDomainFromURL($newobject->ref_customer, 1);
    }

    // If $object is a product, we take package
    if (get_class($newobject) == 'Product')
    {
        dol_include_once('/sellyoursaas/class/packages.class.php');

        $newobject->fetch_optionals();

        $tmppackage = new Packages($db);
        $tmppackage->fetch($newobject->array_options['options_package']);
        $newobject = $tmppackage;
    }

    // If $object is a package, we take first restrict and add account.
    if (get_class($newobject) == 'Packages')
    {
        $tmparray = explode(',', $newobject->restrict_domains);
        if (is_array($tmparray))
        {
            foreach($tmparray as $key => $val)
            {
                if ($val)
                {
                    include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
                    $ret = 'https://myaccount.'.getDomainFromURL($val, 1);
                    break;
                }
            }
        }
    }

    return $ret;
}
