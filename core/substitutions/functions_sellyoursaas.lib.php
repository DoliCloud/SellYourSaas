<?php
/* Copyright (C) 2011-2023 Laurent Destailleur         <eldy@users.sourceforge.net>
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
 * or see http://www.gnu.org/
 */

/**
 *	\file			htdocs/sellyoursaas/core/substitutions/functions_sellyoursaas.lib.php
 *	\brief			A set of functions for Dolibarr
 *					This file contains the functions for substitions of the plugin sellyoursaas.
 */


/**
 * 		Function called to complete substitution array (before generating on ODT, or a personalized email)
 * 		functions xxx_completesubstitutionarray are called by make_substitutions() if file
 * 		is inside directory htdocs/core/substitutions
 *
 *		@param	array		$substitutionarray	Array with substitution key=>val
 *		@param	Translate	$langs				Output langs
 *		@param	Object		$object				Object to use to get values
 *      @param  Mixed		$parameters       	Add more parameters (useful to pass product lines)
 * 		@return	void							The entry parameter $substitutionarray is modified
 */
function sellyoursaas_completesubstitutionarray(&$substitutionarray, $langs, $object, $parameters = null)
{
	global $conf,$db;

	include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
	include_once DOL_DOCUMENT_ROOT.'/societe/class/companypaymentmode.class.php';

	$langs->load("sellyoursaas@sellyoursaas");

	if (isset($parameters['needforkey'])) {
		$substitutionarray['BackupInstanceTabTitle'] = $langs->trans('BackupInstance').' | '.$langs->trans("RestoreInstance");
		if (!empty($object->array_options['options_latestbackup_status']) && $object->array_options['options_latestbackup_status'] == 'KO') {
			$substitutionarray['BackupInstanceTabTitle'] = $substitutionarray['BackupInstanceTabTitle'].img_warning($langs->trans("BackupError"));
		}
	}

	if ((! empty($parameters['mode'])) && $parameters['mode'] == 'formemail') {	// For exemple when called by FormMail::getAvailableSubstitKey()
		if (is_object($object) && get_class($object) == 'Societe') {
			$companypaymentmode = new CompanyPaymentMode($db);
			$result = $companypaymentmode->fetch(0, null, $object->id, 'card');
			if ($result >= 0) {
				$substitutionarray['__CARD_LAST4__']=($companypaymentmode->last_four ? $companypaymentmode->last_four : 'Not Defined');
			} else {
				dol_print_error($db);
			}
			$result = $companypaymentmode->fetch(0, null, $object->id, 'paypal');
			if ($result >= 0) {
				$substitutionarray['__PAYPAL_START_DATE__']=($companypaymentmode->starting_date ? dol_print_date($companypaymentmode->starting_date, 'dayrfc', 'gmt', $langs) : 'Not Defined');
				$substitutionarray['__PAYPAL_EXP_DATE__']=($companypaymentmode->ending_date ? dol_print_date($companypaymentmode->ending_date, 'dayrfc', 'gmt', $langs) : 'Not Defined');
			} else {
				dol_print_error($db);
			}
		}

		if (is_object($object) && get_class($object) == 'Contrat') {
			$hash = dol_hash(getDolGlobalString('SELLYOURSAAS_KEYFORHASH') . $object->thirdparty->email.dol_print_date(dol_now(), 'dayrfc'));
			$substitutionarray['__HASH__'] = $hash;

			include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
			foreach ($object->lines as $line) {
				if ($line->fk_product > 0) {
					$tmpproduct = new Product($db);
					$tmpproduct->fetch($line->fk_product);

					if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
						$initialapplogin = 'admin';

						dol_include_once('/sellyoursaas/class/packages.class.php');

						$tmppackageid = $tmpproduct->array_options['options_package'];
						$tmppackage = new Packages($db);
						$tmppackage->fetch($tmppackageid);

						$substitutionarray['__APPUSERNAME__'] = $initialapplogin;
						$substitutionarray['__APPUSERNAME_URLENCODED__'] = urlencode($initialapplogin);
						$substitutionarray['__PACKAGELABEL__'] = $tmppackage->label;
						$substitutionarray['__APPPASSWORD__']='';

						dol_syslog('Set substitution var for __EMAIL_FOOTER__ with $tmppackage->ref='.strtoupper($tmppackage->ref));
						$substitutionarray['__EMAIL_FOOTER__']='';
						if ($langs->trans("EMAIL_FOOTER_".strtoupper($tmppackage->ref)) != "EMAIL_FOOTER_".strtoupper($tmppackage->ref)) {
							$substitutionarray['__EMAIL_FOOTER__'] = $langs->trans("EMAIL_FOOTER_".strtoupper($tmppackage->ref));
						}

						break;
					}
				}
			}
		}
	}

	$tmpobject = $object;
	if (is_object($tmpobject) && is_object($object->thirdparty) && ! empty($object->thirdparty->array_options['options_domain_registration_page'])) {
		$tmpobject = $object->thirdparty;
	}

	// Force some values to another services
	// $tmpobject is now a thirdparty
	dol_syslog("sellyoursaas_completesubstitutionarray: tmpobject->array_options['options_domain_registration_page'] = ".(isset($tmpobject->array_options['options_domain_registration_page']) ? $tmpobject->array_options['options_domain_registration_page'] : '')." conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME = ".(!getDolGlobalString('SELLYOURSAAS_MAIN_DOMAIN_NAME') ? '' : $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME));
	if (is_object($tmpobject) && ! empty($tmpobject->array_options['options_domain_registration_page'])) {
		global $savconf;

		dol_syslog("savconf isset = ".isset($savconf));

		if (! isset($savconf)) {
			$savconf = dol_clone($conf, 0);
		}

		dol_syslog("sellyoursaas_completesubstitutionarray: savconf has currently savconf->global->SELLYOURSAAS_NAME = ".$savconf->global->SELLYOURSAAS_NAME." savconf->global->SELLYOURSAAS_MAIN_EMAIL = ".$savconf->global->SELLYOURSAAS_MAIN_EMAIL);

		// Force value to original conf in database
		$conf->global->SELLYOURSAAS_NAME = $savconf->global->SELLYOURSAAS_NAME;
		$conf->global->SELLYOURSAAS_ACCOUNT_URL = $savconf->global->SELLYOURSAAS_ACCOUNT_URL;
		$conf->global->SELLYOURSAAS_MAIN_EMAIL = $savconf->global->SELLYOURSAAS_MAIN_EMAIL;
		$conf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM = empty($savconf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM) ? '' : $savconf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM;
		$conf->global->SELLYOURSAAS_NOREPLY_EMAIL = $savconf->global->SELLYOURSAAS_NOREPLY_EMAIL;
		$conf->global->SELLYOURSAAS_SUPERVISION_EMAIL = $savconf->global->SELLYOURSAAS_SUPERVISION_EMAIL;

		// Check if thirdparty is for another service and force $conf with new values
		// This erase value of setup permanently
		$constforaltname = $tmpobject->array_options['options_domain_registration_page'];
		$newnamekey = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$constforaltname;
		if (getDolGlobalString($newnamekey)) {
			$conf->global->SELLYOURSAAS_NAME = getDolGlobalString($newnamekey);
		}

		$urlmyaccount = $savconf->global->SELLYOURSAAS_ACCOUNT_URL;
		if (! empty($tmpobject->array_options['options_domain_registration_page'])
			&& $tmpobject->array_options['options_domain_registration_page'] != $savconf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
			$constforaltname = $tmpobject->array_options['options_domain_registration_page'];
			$newurlkey = 'SELLYOURSAAS_ACCOUNT_URL-'.$constforaltname;
			if (getDolGlobalString($newurlkey)) {
				$urlmyaccount = getDolGlobalString($newurlkey);
			} else {
				$urlmyaccount = preg_replace('/'.$savconf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/', $tmpobject->array_options['options_domain_registration_page'], $urlmyaccount);
			}
		}

		if (empty($savconf->global->SELLYOURSAAS_MAIN_EMAIL)) {
			$savconf->global->SELLYOURSAAS_MAIN_EMAIL = '';
		}
		if (empty($savconf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM)) {
			$savconf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM = '';
		}
		if (empty($savconf->global->SELLYOURSAAS_NOREPLY_EMAIL)) {
			$savconf->global->SELLYOURSAAS_NOREPLY_EMAIL = '';
		}
		if (empty($savconf->global->SELLYOURSAAS_SUPERVISION_EMAIL)) {
			$savconf->global->SELLYOURSAAS_SUPERVISION_EMAIL = '';
		}

		$conf->global->SELLYOURSAAS_DOMAIN_REGISTRATION = $constforaltname;
		$conf->global->SELLYOURSAAS_ACCOUNT_URL        = $urlmyaccount;
		$conf->global->SELLYOURSAAS_MAIN_EMAIL         = preg_replace('/'.$savconf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/', $tmpobject->array_options['options_domain_registration_page'], $savconf->global->SELLYOURSAAS_MAIN_EMAIL);
		$conf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM = preg_replace('/'.$savconf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/', $tmpobject->array_options['options_domain_registration_page'], $savconf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM);
		$conf->global->SELLYOURSAAS_NOREPLY_EMAIL      = preg_replace('/'.$savconf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/', $tmpobject->array_options['options_domain_registration_page'], $savconf->global->SELLYOURSAAS_NOREPLY_EMAIL);
		$conf->global->SELLYOURSAAS_SUPERVISION_EMAIL  = preg_replace('/'.$savconf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/', $tmpobject->array_options['options_domain_registration_page'], $savconf->global->SELLYOURSAAS_SUPERVISION_EMAIL);

		dol_syslog("substitutionarray['__ONLINE_PAYMENT_URL__'] = ".(empty($substitutionarray['__ONLINE_PAYMENT_URL__']) ? '' : $substitutionarray['__ONLINE_PAYMENT_URL__']));

		// Replace url inside var $substitutionarray['__ONLINE_PAYMENT_URL__'] to use options_domain_registration_page instead of SELLYOURSAAS_MAIN_DOMAIN_NAME
		// A common value looks like  https://admin.mysellyoursaasdomain.com/public/payment/newpayment.php?source=invoice&ref=XXXX&securekey=zzzzzzz
		$newsubstiturl = preg_replace('/'.preg_quote($savconf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME, '/').'/', $tmpobject->array_options['options_domain_registration_page'], (empty($substitutionarray['__ONLINE_PAYMENT_URL__']) ? '' : $substitutionarray['__ONLINE_PAYMENT_URL__']));
		dol_syslog("newsubstiturl = ".$newsubstiturl);
		$substitutionarray['__ONLINE_PAYMENT_URL__'] = $newsubstiturl;

		dol_syslog("sellyoursaas_completesubstitutionarray: savconf has now savconf->global->SELLYOURSAAS_NAME = ".$savconf->global->SELLYOURSAAS_NAME." savconf->global->SELLYOURSAAS_MAIN_EMAIL = ".$savconf->global->SELLYOURSAAS_MAIN_EMAIL);
	}
}
