<?php
/* Copyright (C) 2007-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
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

/**
 *  \file       sellyoursaas/class/sellyoursaasutils.class.php
 *  \ingroup    sellyoursaas
 *  \brief      Class with utilities
 */

// Put here all includes required by your class file
//require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
//require_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
//require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('sellyoursaas/lib/sellyoursaas.lib.php');


/**
 *	Class with cron tasks of SellYourSaas module
 */
class SellYourSaasUtils
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)

	public $stripechargedone;
	public $stripechargeerror;


    /**
     *  Constructor
     *
     *  @param	DoliDb		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;
        return 1;
    }


    /**
     * Action executed by scheduler for job SellYourSaasValidateDraftInvoices
     * Search draft invoices on sellyoursaas customers and check they are linked to a not closed contract. Validate it if not, do nothing if closed.
     * CAN BE A CRON TASK
     *
     * @param	int		$restrictonthirdpartyid		0=All qualified draft invoices, >0 = Restrict on qualified draft invoice of thirdparty.
     * @return	int									0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doValidateDraftInvoices($restrictonthirdpartyid=0)
    {
    	global $conf, $langs, $user;

    	$langs->load("agenda");

		include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$invoice = new Facture($this->db);

		$savlog = $conf->global->SYSLOG_FILE;
		$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doValidateDraftInvoices.log';

		$now = dol_now();

		dol_syslog(__METHOD__." search and validate draft invoices. langs->defaultlang=".$langs->defaultlang, LOG_DEBUG);

    	$error = 0;
    	$this->output = '';
    	$this->error='';

    	$draftinvoiceprocessed = array();

    	$this->db->begin();

		$sql = 'SELECT f.rowid FROM '.MAIN_DB_PREFIX.'facture as f,';
		$sql.= ' '.MAIN_DB_PREFIX.'societe_extrafields as se';
		$sql.= ' WHERE f.fk_statut = '.Facture::STATUS_DRAFT;
		$sql.= " AND se.fk_object = f.fk_soc AND se.dolicloud = 'yesv2'";
		if ($restrictonthirdpartyid > 0) $sql.=" AND f.fk_soc = ".$restrictonthirdpartyid;
		$sql.= " ORDER BY f.datef, f.rowid";

		$resql = $this->db->query($sql);
		if ($resql)
		{
			$num_rows = $this->db->num_rows($resql);
			$i= 0;
			while($i < $num_rows)
			{
				$obj = $this->db->fetch_object($resql);
				if ($obj && $invoice->fetch($obj->rowid) > 0)
				{
				    dol_syslog("* Process invoice id=".$invoice->id." ref=".$invoice->ref." restrictonthirdpartyid=".$restrictonthirdpartyid);

					$invoice->fetch_thirdparty();

					if ($invoice->thirdparty->array_options['manualcollection'])
					{
						dol_syslog("This thirdparty has manual collection on, so we don't validate invoice");
					}
					else
					{
					    $tmparray = $invoice->thirdparty->getOutstandingBills('customer');
                        if ($tmparray['opened'] > 0)
                        {
                            dol_syslog("This thirdparty has already open invoices, so we don't validate any other invoices");     // So only 1 invoice is validated per thirdparty and pass
                        }

					    // Search contract linked to invoice
						$invoice->fetchObjectLinked();

						if (is_array($invoice->linkedObjects['contrat']) && count($invoice->linkedObjects['contrat']) > 0)
						{
							//dol_sort_array($object->linkedObjects['facture'], 'date');
							foreach($invoice->linkedObjects['contrat'] as $idcontract => $contract)
							{
								if (! empty($draftinvoiceprocessed[$invoice->id])) continue;	// If already processed because of a previous contract line, do nothing more

								// We ignore $contract->nbofserviceswait +  and $contract->nbofservicesclosed
								$nbservice = $contract->nbofservicesopened + $contract->nbofservicesexpired;
								// If contract not undeployed and not suspended ?
								// Note: If suspended, when unsuspened, the remaining draft invoice will be generated
								// Note: if undeployed, this should not happen, because templates invoice should be disabled when an instance is undeployed
								if ($nbservice && $contract->array_options['options_deployment_status'] != 'undeployed')
								{
									if (! empty($conf->global->SELLYOURSAAS_INVOICE_FORCE_DATE_VALIDATION)) {
										$conf->global->FAC_FORCE_DATE_VALIDATION = 1;
									}

									$result = $invoice->validate($user);
									if ($result > 0)
									{
										$draftinvoiceprocessed[$invoice->id]=$invoice->ref;

										// Now we build the invoice
										$hidedetails = (GETPOST('hidedetails', 'int') ? GETPOST('hidedetails', 'int') : (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0));
										$hidedesc = (GETPOST('hidedesc', 'int') ? GETPOST('hidedesc', 'int') : (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0));
										$hideref = (GETPOST('hideref', 'int') ? GETPOST('hideref', 'int') : (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0));

										// Define output language
										$outputlangs = $langs;
										$newlang = '';
										if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id','aZ09')) $newlang = GETPOST('lang_id','aZ09');
										if ($conf->global->MAIN_MULTILANGS && empty($newlang))	$newlang = $invoice->thirdparty->default_lang;
										if (! empty($newlang)) {
											$outputlangs = new Translate("", $conf);
											$outputlangs->setDefaultLang($newlang);
											$outputlangs->loadLangs(array('main','bills','products'));
										}
										$model_pdf = ($invoice->model_pdf ? $invoice->model_pdf : $invoice->modelpdf);
										$ret = $invoice->fetch($id); // Reload to get new records

										dol_syslog("GETPOST('lang_id','aZ09')=".GETPOST('lang_id','aZ09')." invoice->thirdparty->default_lang=".(is_object($invoice->thirdparty)?$invoice->thirdparty->default_lang:'invoice->thirdparty not defined')." newlang=".$newlang." outputlangs->defaultlang=".$outputlangs->defaultlang);

										$result = $invoice->generateDocument($model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
									}
									else
									{
										$error++;
										$this->error = $invoice->error;
										$this->errors = $invoice->errors;
										break;
									}
								}
								else
								{
									// Do nothing
									dol_syslog("Number of open services (".$nbservice.") is zero or contract is undeployed, so we do nothing.");
								}
							}
						}
						else
						{
							dol_syslog("No linked contract found on this invoice");
						}
					}
				}
				else
				{
					$error++;
					$this->errors[] = 'Failed to get invoice with id '.$obj->rowid;
				}

				$i++;
			}
		}
		else
		{
			$error++;
			$this->error = $this->db->lasterror();
		}

		$this->output = count($draftinvoiceprocessed).' invoice(s) validated on '.$num_rows.' draft invoice found'.(count($draftinvoiceprocessed)>0 ? ' : '.join(',', $draftinvoiceprocessed) : '').' (search done on invoices of SellYourSaas customers only)';

		$this->db->commit();

		$conf->global->SYSLOG_FILE = $savlog;

		return ($error ? 1: 0);
    }

    /**
     * Action executed by scheduler for job SellYourSaasAlertSoftEndTrial
     * Search contracts of sellyoursaas customers that are deployed + with open lines + about to expired (= date between (end date - SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT) and (end date - SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT + 7)) + not yet already warned (date_softalert_endfreeperiod is null), then send email remind
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doAlertSoftEndTrial()
    {
    	global $conf, $langs, $user;

    	$langs->load("agenda");

    	$mode = 'test';

    	$savlog = $conf->global->SYSLOG_FILE;
    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doAlertSoftEndTrial.log';

    	$contractprocessed = array();
    	$contractok = array();
    	$contractko = array();
        $contractpayingupdated = array();

    	$now = dol_now();

    	$error = 0;
    	$this->output = '';
    	$this->error='';

    	$delayindaysshort = $conf->global->SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT;
    	$delayindayshard = $conf->global->SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT;
    	if ($delayindaysshort <= 0 || $delayindayshard <= 0)
    	{
    		$this->error='BadValueForDelayBeforeTrialEndForAlert';

    		$conf->global->SYSLOG_FILE = $savlog;

    		return -1;
    	}
    	dol_syslog(__METHOD__." we send email warning on contract that will expire in ".$delayindaysshort." days or before and not yet reminded", LOG_DEBUG, 1);

    	$this->db->begin();

    	$date_limit_expiration = dol_time_plus_duree($now, abs($delayindaysshort), 'd');

    	$sql = 'SELECT DISTINCT c.rowid, c.ref_customer';
    	$sql.= ' FROM '.MAIN_DB_PREFIX.'contrat as c, '.MAIN_DB_PREFIX.'contratdet as cd, '.MAIN_DB_PREFIX.'contrat_extrafields as ce,';
    	$sql.= ' '.MAIN_DB_PREFIX.'societe_extrafields as se';
    	$sql.= ' WHERE cd.fk_contrat = c.rowid AND ce.fk_object = c.rowid';
    	$sql.= " AND ce.deployment_status = 'done'";
    	$sql.= " AND ce.date_softalert_endfreeperiod IS NULL";
    	$sql.= " AND cd.date_fin_validite <= '".$this->db->idate($date_limit_expiration)."'";      // Expired contracts
    	$sql.= " AND cd.date_fin_validite >= '".$this->db->idate($date_limit_expiration - 7 * 24 * 3600)."'";	// Protection: We dont' go higher than 7 days late to avoid to resend too much warnings when update of date_softalert_endfreeperiod has failed
    	$sql.= " AND cd.statut = 4";	// 4 = ContratLigne::STATUS_OPEN
    	$sql.= " AND se.fk_object = c.fk_soc AND se.dolicloud = 'yesv2'";
    	$sql.= " ORDER BY c.rowid DESC";
    	//print $sql;

    	$resql = $this->db->query($sql);
    	if ($resql)
    	{
    		$num = $this->db->num_rows($resql);

    		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
    		include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
    		$formmail=new FormMail($this->db);

    		$MAXPERCALL=10;
    		$nbsending = 0;

    		$i=0;
    		while ($i < $num)
    		{
    			$obj = $this->db->fetch_object($resql);
    			if ($obj)
    			{
    				if (! empty($contractprocessed[$obj->rowid])) continue;

    				if ($nbsending >= $MAXPERCALL)
    				{
    				    dol_syslog("We reach the limit of ".$MAXPERCALL." contract processed per batch, so we quit loop for the batch doAlertSoftTrial to avoid to reach email quota.", LOG_WARNING);
    				    break;
    				}

    				// Test if this is a paid or not instance
    				$object = new Contrat($this->db);
    				$result = $object->fetch($obj->rowid);
    				if ($result <= 0)
    				{
    					$error++;
    					$this->errors[] = 'Failed to load contract with id='.$obj->rowid;
    					continue;
    				}

    				dol_syslog("* Process contract id=".$object->id." ref=".$object->ref." ref_customer=".$object->ref_customer);

    				dol_syslog('Call sellyoursaasIsPaidInstance', LOG_DEBUG, 1);
    				$isAPayingContract = sellyoursaasIsPaidInstance($object);
    				dol_syslog('', 0, -1);
    				if ($mode == 'test' && $isAPayingContract)          // Discard if this is a paid instance when we are in test mode
    				{
    				    $contractpayingupdated[$object->id]=$object->ref;

    				    $sqlupdatedate = 'UPDATE '.MAIN_DB_PREFIX."contrat_extrafields SET date_softalert_endfreeperiod = date_endfreeperiod WHERE fk_object = ".$object->id;
    				    $resqlupdatedate = $this->db->query($sqlupdatedate);
    				    if (! $resqlupdatedate)
    				    {
    				        dol_syslog('Failed to update date_softalert_endfreeperiod with date_endfreeperiod for object id = '.$object->id, LOG_ERR);
    				    }
    				    continue;
    				}
    				//if ($mode == 'paid' && ! $isAPayingContract) continue;										// Discard if this is a test instance when we are in paid mode

    				// Suspend instance
    				dol_syslog('Call sellyoursaasGetExpirationDate', LOG_DEBUG, 1);
    				$tmparray = sellyoursaasGetExpirationDate($object);
    				dol_syslog('', 0, -1);
    				$expirationdate = $tmparray['expirationdate'];

    				if ($expirationdate && $expirationdate < $date_limit_expiration)
    				{
    					$nbsending++;

					    // Load third party
					    $object->fetch_thirdparty();

					    $outputlangs = new Translate('', $conf);
					    $outputlangs->setDefaultLang($object->thirdparty->default_lang);
					    $outputlangs->loadLangs(array('main'));

					    // @TODO Save in cache $arraydefaultmessage for each $object->thirdparty->default_lang and reuse it to avoid getEMailTemplate called each time
					    dol_syslog("We will call getEMailTemplate for type 'contract', label 'GentleTrialExpiringReminder', outputlangs->defaultlang=".$outputlangs->defaultlang);
					    $arraydefaultmessage=$formmail->getEMailTemplate($this->db, 'contract', $user, $outputlangs, 0, 1, 'GentleTrialExpiringReminder');

    					$substitutionarray=getCommonSubstitutionArray($outputlangs, 0, null, $object);
    					$substitutionarray['__SELLYOURSAAS_EXPIRY_DATE__']=dol_print_date($expirationdate, 'day', 'tzserver', $outputlangs);
    					complete_substitutions_array($substitutionarray, $outputlangs, $object);

    					$subject = make_substitutions($arraydefaultmessage->topic, $substitutionarray);
    					$msg     = make_substitutions($arraydefaultmessage->content, $substitutionarray);

    					$from = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;
    					$to = $object->thirdparty->email;
    					$trackid = 'thi'.$object->thirdparty->id;

    					$cmail = new CMailFile($subject, $to, $from, $msg, array(), array(), array(), '', '', 0, 1, '', '', $trackid);
    					$result = $cmail->sendfile();
    					if (! $result)
    					{
    						$error++;
    						$this->error = $cmail->error;
    						$this->errors = $cmail->errors;
    						dol_syslog("Failed to send email to ".$to." ".$this->error, LOG_WARNING);
    						$contractko[$object->id]=$object->ref;
    					}
    					else
    					{
    						dol_syslog("Email sent to ".$to, LOG_DEBUG);
    						$contractok[$object->id]=$object->ref;

    						$sqlupdatedate = 'UPDATE '.MAIN_DB_PREFIX."contrat_extrafields SET date_softalert_endfreeperiod = '".$this->db->idate($now)."' WHERE fk_object = ".$object->id;
    						$resqlupdatedate = $this->db->query($sqlupdatedate);
    						if (! $resqlupdatedate)
    						{
    						    dol_syslog("Failed to update date_softalert_endfreeperiod with '".$this->db->idate($now)."' for object id = ".$object->id, LOG_ERR);
    						}
    					}

    					$contractprocessed[$object->id]=$object->ref;
    				}
    				else
    				{

    				}
    			}
    			$i++;
    		}
    	}
    	else
    	{
    		$error++;
    		$this->error = $this->db->lasterror();
    	}

    	$this->output = count($contractprocessed).' contract(s) qualified (search done on contracts of SellYourSaas prospects/customers only).';
    	if (count($contractpayingupdated)>0)
    	{
    	    $this->output .= ' '.count($contractpayingupdated).' contract(s) seems paying so we updated date_softalert_endfreeperiod to date_endfreeperiod for '.join(',', $contractpayingupdated).'.';
    	}
    	if (count($contractok)>0)
    	{
    		$this->output .= ' '.count($contractok).' email(s) sent for '.join(',', $contractok).'.';
    	}
    	if (count($contractko)>0)
    	{
    		$this->output .= ' '.count($contractko).' email(s) in error for '.join(',', $contractko).'.';
    	}

    	$this->db->commit();

    	dol_syslog(__METHOD__." ".$this->output, LOG_DEBUG, -1);

    	$conf->global->SYSLOG_FILE = $savlog;

    	return ($error ? 1: 0);
    }


    /**
     * Action executed by scheduler. To run every day.
     * Send warning when credit card will expire to sellyoursaas customers.
     * CAN BE A CRON TASK
     *
     * @param	int			$day1	Day1 in month to launch warnings (1st)
     * @param	int			$day2	Day2 in month to launch warnings (20th)
     * @return	int					0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doAlertCreditCardExpiration($day1='',$day2='')
    {
    	global $conf, $langs, $user;

    	$langs->load("agenda");

    	$savlog = $conf->global->SYSLOG_FILE;
    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doAlertCreditCardExpiration.log';

    	$now = dol_now();

    	$error = 0;
    	$this->output = '';
    	$this->error='';

    	dol_syslog(__METHOD__.' - Search card that expire at end of month and send remind. Test is done the day '.$day1.' and '.$day2.' of month', LOG_DEBUG);

    	if (empty($day1) ||empty($day2))
    	{
    		$this->error = 'Bad value for parameter day1 and day2. Set param to "1, 20" for example';
    		$error++;

    		$conf->global->SYSLOG_FILE = $savlog;

    		return 1;
    	}

    	$servicestatus = 0;
    	if (! empty($conf->stripe->enabled))
    	{
    		$service = 'StripeTest';
    		$servicestatus = 0;
    		if (! empty($conf->global->STRIPE_LIVE) && ! GETPOST('forcesandbox','alpha') && empty($conf->global->SELLYOURSAAS_FORCE_STRIPE_TEST))
    		{
    			$service = 'StripeLive';
    			$servicestatus = 1;
    		}
    	}

    	$currentdate = dol_getdate($now);
    	$currentday = $currentdate['mday'];
    	$currentmonth = $currentdate['mon'];
    	$currentyear = $currentdate['year'];

    	if ($currentday != $day1 && $currentday != $day2) {
    		$this->output = 'Nothing to do. We are not the day '.$day1.', neither the day '.$day2.' of the month';

    		$conf->global->SYSLOG_FILE = $savlog;

    		return 0;
    	}

    	$this->db->begin();

    	// Get warning email template
    	include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
    	include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
    	include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
    	$formmail=new FormMail($db);

    	$nextyear = $currentyear;
    	$nextmonth = $currentmonth + 1;
    	if ($nextmonth > 12) { $nextmonth = 1; $nextyear++; }

    	// Search payment modes on companies that has an active invoice template
    	$sql = 'SELECT DISTINCT sr.rowid, sr.fk_soc, sr.exp_date_month, sr.exp_date_year, sr.last_four, sr.status';
    	$sql.= ' FROM '.MAIN_DB_PREFIX.'societe_rib as sr, '.MAIN_DB_PREFIX.'societe_extrafields as se,';
    	$sql.= ' '.MAIN_DB_PREFIX.'facture_rec as fr';
    	$sql.= " WHERE sr.fk_soc = fr.fk_soc AND sr.default_rib = 1 AND sr.type = 'card' AND sr.status = ".$servicestatus;
    	$sql.= " AND se.fk_object = fr.fk_soc AND se.dolicloud = 'yesv2'";
    	$sql.= " AND sr.exp_date_month = ".$currentmonth." AND sr.exp_date_year = ".$currentyear;
    	$sql.= " AND fr.suspended = ".FactureRec::STATUS_NOTSUSPENDED;
    	$sql.= " AND fr.frequency > 0";

    	$resql = $this->db->query($sql);
    	if ($resql)
    	{
    		$num_rows = $this->db->num_rows($resql);
    		$i = 0;
    		while ($i < $num_rows)
    		{
    			$obj = $this->db->fetch_object($resql);

    			$thirdparty = new Societe($this->db);
    			$thirdparty->fetch($obj->fk_soc);

    			if ($thirdparty->id > 0)
    			{
    				dol_syslog("* Process thirdparty id=".$thirdparty->id." name=".$thirdparty->name);

    				$langstouse = new Translate('', $conf);
    				$langstouse->setDefaultLang($thirdparty->default_lang ? $thirdparty->default_lang : $langs->defaultlang);
    				$langstouse->loadLangs(array('main'));

    				$arraydefaultmessage=$formmail->getEMailTemplate($this->db, 'thirdparty', $user, $langstouse, -2, 1, '(AlertCreditCardExpiration)');		// Templates are init into data.sql

    				if (is_object($arraydefaultmessage) && ! empty($arraydefaultmessage->topic))
    				{
    					$substitutionarray=getCommonSubstitutionArray($langstouse, 0, null, $thirdparty);
    					$substitutionarray['__CARD_EXP_DATE_MONTH__']=$obj->exp_date_month;
    					$substitutionarray['__CARD_EXP_DATE_YEAR__']=$obj->exp_date_year;
    					$substitutionarray['__CARD_LAST4__']=$obj->last_four;

    					complete_substitutions_array($substitutionarray, $langstouse, $thirdparty);

    					$subject = make_substitutions($arraydefaultmessage->topic, $substitutionarray, $langstouse);
    					$msg     = make_substitutions($arraydefaultmessage->content, $substitutionarray, $langstouse);
    					$from = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;


    					$to = $thirdparty->email;

    					$cmail = new CMailFile($subject, $to, $from, $msg, array(), array(), array(), '', '', 0, 1);
    					$result = $cmail->sendfile();
    					if (! $result)
    					{
    						$error++;
    						$this->error = 'Failed to send email to thirdparty id = '.$thirdparty->id.' : '.$cmail->error;
    						$this->errors[] = 'Failed to send email to thirdparty id = '.$thirdparty->id.' : '.$cmail->error;
    					}
    				}
    				else
    				{
    					$error++;
    					$this->error = 'Failed to get email a valid template (AlertCreditCardExpiration)';
    					$this->errors[] = 'Failed to get email a valid template (AlertCreditCardExpiration)';
    				}
    			}

    			$i++;
    		}
    	}
    	else
    	{
    		$error++;
    		$this->error = $this->db->lasterror();
    	}

    	if (! $error)
    	{
    		$this->output = 'Found '.$num_rows.' payment mode for credit card that will expire soon (ran in mode '.$service.') (search done on SellYourSaas customers with active template invoice only)';
    	}

    	$this->db->commit();

    	$conf->global->SYSLOG_FILE = $savlog;

    	return $error;
    }


    /**
     * Action executed by scheduler.
     * Send warning when paypal preapproval will expire to sellyoursaas customers.
     * CAN BE A CRON TASK
     *
     * @param	int			$day1	Day1 in month to launch warnings (1st)
     * @param	int			$day2	Day2 in month to launch warnings (20th)
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doAlertPaypalExpiration($day1='', $day2='')
    {
    	global $conf, $langs, $user;

    	$langs->load("agenda");

    	$savlog = $conf->global->SYSLOG_FILE;
    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doAlertPaypalExpiration.log';

    	$now = dol_now();

    	$error = 0;
    	$this->output = '';
    	$this->error='';

    	dol_syslog(__METHOD__.' - Search paypal approval that expire at end of month and send remind. Test is done the day '.$day1.' and '.$day2.' of month', LOG_DEBUG);

    	if (empty($day1) ||empty($day2))
    	{
    		$this->error = 'Bad value for parameter day1 and day2. Set param to "1, 20" for example';
    		$error++;

    		$conf->global->SYSLOG_FILE = $savlog;

    		return 1;
    	}

    	$servicestatus = 1;
    	if (! empty($conf->paypal->enabled))
    	{
    		//$service = 'PaypalTest';
    		$servicestatus = 0;
    		if (! empty($conf->global->PAYPAL_LIVE) && ! GETPOST('forcesandbox','alpha') && empty($conf->global->SELLYOURSAAS_FORCE_PAYPAL_TEST))
    		{
    			//$service = 'PaypalLive';
    			$servicestatus = 1;
    		}
    	}

    	$currentdate = dol_getdate($now);
    	$currentday = $currentdate['mday'];
    	$currentmonth = $currentdate['mon'];
    	$currentyear = $currentdate['year'];

    	if ($currentday != $day1 && $currentday != $day2) {
    		$this->output = 'Nothing to do. We are not the day '.$day1.', neither the day '.$day2.' of the month';

    		$conf->global->SYSLOG_FILE = $savlog;

    		return 0;
    	}

    	$this->db->begin();

    	// Get warning email template
    	include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
    	include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
    	$formmail=new FormMail($db);

    	$nextyear = $currentyear;
    	$nextmonth = $currentmonth + 1;
    	if ($nextmonth > 12) { $nextmonth = 1; $nextyear++; }
    	$timelessonemonth = dol_time_plus_duree($now, -1, 'm');

    	if ($timelessonemonth)
    	{
    		// Search payment modes on companies that has an active invoice template
    		$sql = 'SELECT DISTINCT sr.rowid, sr.fk_soc, sr.exp_date_month, sr.exp_date_year, sr.last_four, sr.status';
    		$sql.= ' FROM '.MAIN_DB_PREFIX.'societe_rib as sr, '.MAIN_DB_PREFIX.'societe_extrafields as se,';
    		$sql.= ' '.MAIN_DB_PREFIX.'facture_rec as fr';
    		$sql.= " WHERE sr.fk_soc = fr.fk_soc AND sr.default_rib = 1 AND sr.type = 'paypal' AND sr.status = ".$servicestatus;
    		$sql.= " AND sr.exp_date_month = ".$currentmonth." AND sr.exp_date_year = ".$currentyear;
    		$sql.= " AND se.fk_object = fr.fk_soc AND se.dolicloud = 'yesv2'";
    		$sql.= " AND fr.suspended = ".FactureRec::STATUS_NOTSUSPENDED;
    		$sql.= " AND fr.frequency > 0";

	    	$resql = $this->db->query($sql);
	    	if ($resql)
	    	{
	    		$num_rows = $this->db->num_rows($resql);
	    		$i = 0;
	    		while ($i < $num_rows)
	    		{
	    			$obj = $this->db->fetch_object($resql);

	    			$thirdparty = new Societe($this->db);
	    			$thirdparty->fetch($obj->fk_soc);
	    			if ($thirdparty->id > 0)
	    			{
	    				dol_syslog("* Process thirdparty id=".$thirdparty->id." name=".$thirdparty->nom);

	    				$langstouse = new Translate('', $conf);
	    				$langstouse->setDefaultLang($thirdparty->default_lang ? $thirdparty->default_lang : $langs->defaultlang);
	    				$langstouse->loadLangs(array('main'));

	    				$arraydefaultmessage=$formmail->getEMailTemplate($this->db, 'thirdparty', $user, $langstouse, -2, 1, 'AlertPaypalApprovalExpiration');		// Templates are init into data.sql

	    				if (is_object($arraydefaultmessage) && ! empty($arraydefaultmessage->topic))
	    				{
	    					$substitutionarray=getCommonSubstitutionArray($langstouse, 0, null, $thirdparty);
	    					$substitutionarray['__PAYPAL_EXP_DATE__']=dol_print_date($obj->ending_date, 'day', 'tzserver', $langstouse);

	    					complete_substitutions_array($substitutionarray, $langstouse, $thirdparty);

	    					$subject = make_substitutions($arraydefaultmessage->topic, $substitutionarray, $langstouse);
	    					$msg     = make_substitutions($arraydefaultmessage->content, $substitutionarray, $langstouse);
	    					$from = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;


	    					$to = $thirdparty->email;

	    					$cmail = new CMailFile($subject, $to, $from, $msg, array(), array(), array(), '', '', 0, 1);
	    					$result = $cmail->sendfile();
	    					if (! $result)
	    					{
	    						$error++;
	    						$this->error = 'Failed to send email to thirdparty id = '.$thirdparty->id.' : '.$cmail->error;
	    						$this->errors[] = 'Failed to send email to thirdparty id = '.$thirdparty->id.' : '.$cmail->error;
	    					}
	    				}
	    				else
	    				{
	    					$error++;
	    					$this->error = 'Failed to get email a valid template AlertPaypalApprovalExpiration';
	    					$this->errors[] = 'Failed to get email a valid template AlertPaypalApprovalExpiration';
	    				}
	    			}

	    			$i++;
	    		}
	    	}
	    	else
	    	{
	    		$error++;
	    		$this->error = $this->db->lasterror();
	    	}
    	}

    	if (! $error)
    	{
    		$this->output = 'Found '.$num_rows.' record with paypal approval that will expire soon (ran in mode '.$servicestatus.')';
    	}

    	$this->db->commit();

    	$conf->global->SYSLOG_FILE = $savlog;

    	return $error;
    }


    /**
     * Action executed by scheduler
     * Loop on invoice for customer with default payment mode Stripe and take payment/send email. Unsuspend if it was suspended (done by trigger BILL_CANCEL or BILL_PAYED).
     * CAN BE A CRON TASK
     *
     * @param	int		$maxnbofinvoicetotry    		Max number of payment to do (0 = No max)
     * @param	int		$noemailtocustomeriferror		1=No email sent to customer if there is a payment error (can be used when error is already reported on screen)
     * @return	int			                    		0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doTakePaymentStripe($maxnbofinvoicetotry = 0, $noemailtocustomeriferror = 0)
    {
    	global $conf, $langs, $mysoc;

    	$langs->load("agenda");

    	$savlog = $conf->global->SYSLOG_FILE;
    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doTakePaymentStripe.log';

    	include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
    	include_once DOL_DOCUMENT_ROOT.'/societe/class/companypaymentmode.class.php';

    	$error = 0;
    	$this->output = '';
    	$this->error='';

    	$invoiceprocessed = array();
    	$invoiceprocessedok = array();
    	$invoiceprocessedko = array();

    	if (empty($conf->stripe->enabled))
    	{
    		$this->error='Error, stripe module not enabled';

    		$conf->global->SYSLOG_FILE = $savlog;

    		return 1;
    	}

   		$service = 'StripeTest';
   		$servicestatus = 0;
   		if (! empty($conf->global->STRIPE_LIVE) && ! GETPOST('forcesandbox','alpha') && empty($conf->global->SELLYOURSAAS_FORCE_STRIPE_TEST))
   		{
   			$service = 'StripeLive';
   			$servicestatus = 1;
    	}

    	dol_syslog(__METHOD__." maxnbofinvoicetotry=".$maxnbofinvoicetotry." noemailtocustomeriferror=".$noemailtocustomeriferror, LOG_DEBUG);

    	$this->db->begin();

    	$sql = 'SELECT f.rowid, se.fk_object as socid, sr.rowid as companypaymentmodeid';
    	$sql.= ' FROM '.MAIN_DB_PREFIX.'facture as f, '.MAIN_DB_PREFIX.'societe_extrafields as se, '.MAIN_DB_PREFIX.'societe_rib as sr';
    	$sql.= ' WHERE sr.fk_soc = f.fk_soc';
    	$sql.= " AND f.paye = 0 AND f.type = 0 AND f.fk_statut = ".Facture::STATUS_VALIDATED;
    	$sql.= " AND sr.status = ".$servicestatus;
    	$sql.= " AND f.fk_soc = se.fk_object AND se.dolicloud = 'yesv2'";
    	$sql.= " ORDER BY f.datef ASC, f.rowid ASC, sr.default_rib DESC, sr.tms DESC";		// Lines may be duplicated. Never mind, we will exclude duplicated invoice later.
    	//print $sql;exit;

    	$resql = $this->db->query($sql);
    	if ($resql)
    	{
    		$num = $this->db->num_rows($resql);

    		$i=0;
    		while ($i < $num)
    		{
    			$obj = $this->db->fetch_object($resql);
    			if ($obj)
    			{
    				if (! empty($invoiceprocessed[$obj->rowid])) continue;		// Invoice already processed

    				$invoice = new Facture($this->db);
    				$result1 = $invoice->fetch($obj->rowid);

    				$companypaymentmode = new CompanyPaymentMode($this->db);
    				$result2 = $companypaymentmode->fetch($obj->companypaymentmodeid);

	    			if ($result1 <= 0 || $result2 <= 0)
	    			{
	    				$error++;
	    				dol_syslog('Failed to get invoice id = '.$obj->rowid.' or companypaymentmode id ='.$obj->companypaymentmodeid, LOG_ERR);
	    				$this->errors[] = 'Failed to get invoice id = '.$obj->rowid.' or companypaymentmode id ='.$obj->companypaymentmodeid;
	    			}
    				else
    				{
    					dol_syslog("* Process invoice id=".$invoice->id." ref=".$invoice->ref);

    					$result = $this->doTakePaymentStripeForThirdparty($service, $servicestatus, $obj->socid, $companypaymentmode, $invoice, 0, $noemailtocustomeriferror);
						if ($result == 0)	// No error
						{
							$invoiceprocessedok[$obj->rowid]=$invoice->ref;
						}
						else
						{
							$invoiceprocessedko[$obj->rowid]=$invoice->ref;
						}
    				}

    				$invoiceprocessed[$obj->rowid]=$invoice->ref;
    			}

    			$i++;

    			if ($maxnbofinvoicetotry && $i >= $maxnbofinvoicetotry) {
    			    break;
    			}
    		}
    	}
    	else
    	{
    		$error++;
    		$this->error = $this->db->lasterror();
    	}

    	$this->output = count($invoiceprocessedok).' invoice(s) paid among '.count($invoiceprocessed).' qualified invoice(s) with a valid default payment mode processed'.(count($invoiceprocessedok)>0 ? ' : '.join(',', $invoiceprocessedok) : '').' (ran in mode '.$servicestatus.') (search done on SellYourSaas customers only)';
    	$this->output .= ' - '.count($invoiceprocessedko).' discarded (missing stripe customer/card id, payment error or other reason)'.(count($invoiceprocessedko)>0 ? ' : '.join(',', $invoiceprocessedko) : '');

    	$this->db->commit();

    	$conf->global->SYSLOG_FILE = $savlog;

    	return $error;
    }


    /**
     * doTakePaymentStripeForThirdparty
     * Take payment/send email. Unsuspend if it was suspended (done by trigger BILL_CANCEL or BILL_PAYED).
     *
     * @param	int		             $service					'StripeTest' or 'StripeLive'
     * @param	int		             $servicestatus				Service 0 or 1
     * @param	int		             $thirdparty_id				Thirdparty id
     * @param	CompanyPaymentMode	 $companypaymentmode		Company payment mode id
     * @param	int		             $invoice					null=All invoices of thirdparty, Invoice=Only this invoice
     * @param	int		             $includedraft				Include draft invoices
     * @param	int		             $noemailtocustomeriferror	1=No email sent to customer if there is a payment error (can be used when error is already reported on screen)
     * @param	int		             $nocancelifpaymenterror	1=Do not cancel payment if there is a recent payment error AC_PAYMENT_STRIPE_KO (used to charge from user console)
     * @param   int                  $calledinmyaccountcontext  1=The payment is called in a myaccount GUI context. So we can ignore control on delayed payments.
     * @return	int					                 			0 if no error, >0 if error
     */
    function doTakePaymentStripeForThirdparty($service, $servicestatus, $thirdparty_id, $companypaymentmode, $invoice=null, $includedraft=0, $noemailtocustomeriferror=0, $nocancelifpaymenterror=0, $calledinmyaccountcontext=0)
    {
    	global $conf, $mysoc, $user, $langs;

    	$error = 0;

    	$langs->load("agenda");

    	dol_syslog("doTakePaymentStripeForThirdparty service=".$service." servicestatus=".$servicestatus." thirdparty_id=".$thirdparty_id." companypaymentmode=".$companypaymentmode->id." noemailtocustomeriferror=".$noemailtocustomeriferror." nocancelifpaymenterror=".$nocancelifpaymenterror." calledinmyaccountcontext=".$calledinmyaccountcontext);

    	$this->stripechargedone = 0;
    	$this->stripechargeerror = 0;
    	$now = dol_now();

    	// Check parameters
    	if (empty($thirdparty_id))
    	{
    		$this->errors[]='Empty parameter thirdparty_id when calling doTakePaymentStripeForThirdparty';
    		return 1;
    	}

    	$currency = $conf->currency;

    	// Get list of pending invoices (may also validate pending draft if $includedraft is set)
    	$invoices=array();
    	if (empty($invoice))	// If all invoices of thirdparty
    	{
    		$sql = 'SELECT f.rowid, f.fk_statut';
    		$sql.= ' FROM '.MAIN_DB_PREFIX.'facture as f, '.MAIN_DB_PREFIX.'societe as s';
    		$sql.= ' WHERE f.fk_soc = s.rowid';
    		$sql.= " AND f.paye = 0 AND f.type = 0";
    		if ($includedraft)
    		{
    			$sql.= " AND f.fk_statut in (".Facture::STATUS_DRAFT.", ".Facture::STATUS_VALIDATED.")";
    		}
    		else
    		{
    			$sql.= " AND f.fk_statut = ".Facture::STATUS_VALIDATED;
    		}
    		$sql.= " AND s.rowid = ".$thirdparty_id;
    		$sql.= " ORDER BY f.datef ASC, f.rowid ASC";
    		//print $sql;

    		$resql = $this->db->query($sql);
    		if ($resql)
    		{
    			$num = $this->db->num_rows($resql);

    			$i=0;
    			while ($i < $num)		// Loop on each invoice to pay and validate them if they are draft
    			{
    				$obj = $this->db->fetch_object($resql);
    				if ($obj)
    				{
    					$invoice = new Facture($this->db);
    					$result = $invoice->fetch($obj->rowid);
    					if ($result > 0)
    					{
    						if ($invoice->statut == Facture::STATUS_DRAFT)
    						{
    							$user->rights->facture->creer = 1;		// Force permission to user to validate invoices
    							$user->rights->facture->invoice_advance->validate = 1;

    							if (! empty($conf->global->SELLYOURSAAS_INVOICE_FORCE_DATE_VALIDATION)) {
    							    $conf->global->FAC_FORCE_DATE_VALIDATION = 1;
    							}

    							$result = $invoice->validate($user);

    							// We do not create PDF here, it will be done when the payment->create is called
    						}
    					}
    					else
    					{
   							$error++;
   							$this->errors[] = 'Failed to load invoice with id='.$obj->rowid;
    					}
    					if ($result > 0)
    					{
    						$invoices[] = $invoice;
    					}
    				}
    				$i++;
    			}
    		}
    	}
    	else
    	{
    		$invoices[] = $invoice;
    	}
		if (count($invoices) == 0)
		{
			dol_syslog("No qualified invoices found for thirdparty_id = ".$thirdparty_id);
		}

		dol_syslog("We found ".count($invoices).' qualified invoices to process payment on (ran in mode '.$servicestatus.').');

		global $stripearrayofkeysbyenv;
		global $savstripearrayofkeysbyenv;

		// Loop on each invoice to pay
		foreach($invoices as $invoice)
		{
		    $errorforinvoice = 0;     // We reset the $errorforinvoice at each invoice loop

			$invoice->fetch_thirdparty();

			dol_syslog("--- Process invoice thirdparty_id=".$thirdparty_id.", thirdparty_name=".$invoice->thirdparty->name." id=".$invoice->id.", ref=".$invoice->ref.", datef=".dol_print_date($invoice->date, 'dayhourlog'), LOG_DEBUG);

			$alreadypayed = $invoice->getSommePaiement();
    		$amount_credit_notes_included = $invoice->getSumCreditNotesUsed();
    		$amounttopay = $invoice->total_ttc - $alreadypayed - $amount_credit_notes_included;

    		// Correct the amount according to unit of currency
    		// See https://support.stripe.com/questions/which-zero-decimal-currencies-does-stripe-support
    		$arrayzerounitcurrency=array('BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF');
    		$amountstripe=$amounttopay;
    		if (! in_array($currency, $arrayzerounitcurrency)) $amountstripe=$amountstripe * 100;

    		if ($amountstripe > 0)
    		{
    			try {
    			    //var_dump($companypaymentmode);
    				dol_syslog("We will try to pay with companypaymentmodeid=".$companypaymentmode->id." stripe_card_ref=".$companypaymentmode->stripe_card_ref." mode=".$companypaymentmode->status, LOG_DEBUG);

    				$thirdparty = new Societe($this->db);
    				$resultthirdparty = $thirdparty->fetch($thirdparty_id);

    				include_once DOL_DOCUMENT_ROOT.'/stripe/class/stripe.class.php';        // This include the include of htdocs/stripe/config.php
                                                                            				// So it inits or erases the $stripearrayofkeysbyenv
    				$stripe = new Stripe($this->db);

    				if (empty($savstripearrayofkeysbyenv)) $savstripearrayofkeysbyenv = $stripearrayofkeysbyenv;
    				dol_syslog("Current Stripe environment is ".$stripearrayofkeysbyenv[$servicestatus]['publishable_key']);
    				dol_syslog("Current Saved Stripe environment is ".$savstripearrayofkeysbyenv[$servicestatus]['publishable_key']);

    				$foundalternativestripeaccount = '';

    				// Force stripe to another value (by default this value is empty)
    				if (! empty($thirdparty->array_options['options_stripeaccount']))
    				{
    				    dol_syslog("The thirdparty id=".$thirdparty->id." has a dedicated Stripe Account, so we switch to it.");

    				    $tmparray = explode('@', $thirdparty->array_options['options_stripeaccount']);
    				    if (! empty($tmparray[1]))
    				    {
        				    $tmparray2 = explode(':', $tmparray[1]);
        				    if (! empty($tmparray2[3]))
        				    {
        				        $stripearrayofkeysbyenv = array(
            				        0=>array(
            				            "publishable_key" => $tmparray2[0],
            				            "secret_key"      => $tmparray2[1]
            				        ),
            				        1=>array(
            				            "publishable_key" => $tmparray2[2],
            				            "secret_key"      => $tmparray2[3]
            				        )
            				    );

           				        $stripearrayofkeys = $stripearrayofkeysbyenv[$servicestatus];
            				    \Stripe\Stripe::setApiKey($stripearrayofkeys['secret_key']);

            				    $foundalternativestripeaccount = $tmparray[0];    // Store the customer id

            				    dol_syslog("We use now customer=".$foundalternativestripeaccount." publishable_key=".$stripearrayofkeys['publishable_key'], LOG_DEBUG);
        				    }
    				    }

    				    if (! $foundalternativestripeaccount)
    				    {
    				        $stripearrayofkeysbyenv = $savstripearrayofkeysbyenv;

    				        $stripearrayofkeys = $savstripearrayofkeysbyenv[$servicestatus];
    				        \Stripe\Stripe::setApiKey($stripearrayofkeys['secret_key']);
    				        dol_syslog("We found a bad value for Stripe Account for thirdparty id=".$thirdparty->id.", so we ignore it and keep using the global one, so ".$stripearrayofkeys['publishable_key'], LOG_WARNING);
    				    }
    				}
    				else {
    				    $stripearrayofkeysbyenv = $savstripearrayofkeysbyenv;

    				    $stripearrayofkeys = $savstripearrayofkeysbyenv[$servicestatus];
    				    \Stripe\Stripe::setApiKey($stripearrayofkeys['secret_key']);
    				    dol_syslog("The thirdparty id=".$thirdparty->id." has no dedicated Stripe Account, so we use global one, so ".$stripearrayofkeys['publishable_key'], LOG_DEBUG);
    				}

    				$stripeacc = $stripe->getStripeAccount($service);								// Get Stripe OAuth connect account if it exists (no network access here)

    				if ($foundalternativestripeaccount) {
    				    if (empty($stripeacc)) {				// If the Stripe connect account not set, we use common API usage
    				        $customer = \Stripe\Customer::retrieve("$foundalternativestripeaccount");
    				    } else {
    				        $customer = \Stripe\Customer::retrieve("$foundalternativestripeaccount", array("stripe_account" => $stripeacc));
    				    }
    				} else {
        				$customer = $stripe->customerStripe($thirdparty, $stripeacc, $servicestatus, 0);
        				if (empty($customer) && ! empty($stripe->error))
    					{
    						$this->errors[] = $stripe->error;
    					}
    				}

    				$nbhoursbetweentries    = (empty($conf->global->SELLYOURSAAS_NBHOURSBETWEENTRIES) ? 49 : $conf->global->SELLYOURSAAS_NBHOURSBETWEENTRIES);				// Must have more that 48 hours + 1 between each try (so 1 try every 3 daily batch)
    				$nbdaysbeforeendoftries = (empty($conf->global->SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES) ? 35 : $conf->global->SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES);

    				if ($resultthirdparty > 0 && ! empty($customer))
    				{
    					// Test if last AC_PAYMENT_STRIPE_KO event is an old error lower than $nbhoursbetweentries hours.
    					$recentfailedpayment = false;
    					$sqlonevents = 'SELECT COUNT(*) as nb FROM '.MAIN_DB_PREFIX.'actioncomm WHERE fk_soc = '.$thirdparty->id." AND code ='AC_PAYMENT_STRIPE_KO' AND datep > '".$this->db->idate($now - ($nbhoursbetweentries * 3600))."'";
						$resqlonevents = $this->db->query($sqlonevents);
						if ($resqlonevents)
						{
							$obj = $this->db->fetch_object($resqlonevents);
							if ($obj && $obj->nb > 0) $recentfailedpayment = true;
						}
						if ($recentfailedpayment && empty($nocancelifpaymenterror))	// If we are not in a mode that ask to avoid cancelation, we cancel payment.
						{
							$errmsg='Payment try was canceled (recent payment, in last '.$nbhoursbetweentries.' hours, with error AC_PAYMENT_STRIPE_KO for this customer)';
							dol_syslog($errmsg, LOG_DEBUG);

							$error++;
							$errorforinvoice++;
							$this->errors[]=$errmsg;
						}
						elseif (! empty($invoice->array_options['options_delayautopayment']) && $invoice->array_options['options_delayautopayment'] > $now && empty($calledinmyaccountcontext)) {
						    $errmsg='Payment try was canceled (invoice qualified by the automatic payment was delayed after the '.dol_print_date($invoice->array_options['options_delayautopayment'], 'day').')';
						    dol_syslog($errmsg, LOG_DEBUG);

						    $error++;
						    $errorforinvoice++;
						    $this->errors[]=$errmsg;
						}
						elseif (
						    ($invoice->date < ($now - ($nbdaysbeforeendoftries * 24 * 3600)))                                 // We try until we reach $nbdaysbeforeendoftries
						    && ($invoice->date < ($now - (62 * 24 * 3600)) || $invoice->date > ($now - (60 * 24 * 3600)))     // or when we have 60 days
						    && ($invoice->date < ($now - (92 * 24 * 3600)) || $invoice->date > ($now - (90 * 24 * 3600)))     // or when we have 90 days
						    && empty($nocancelifpaymenterror))
						{
							$errmsg='Payment try was canceled (invoice date is older than '.$nbdaysbeforeendoftries.' days and not 60 days old and not 90 days old) - You can still take payment from backoffice.';
							dol_syslog($errmsg, LOG_DEBUG);

							$error++;
							$errorforinvoice++;
							$this->errors[]=$errmsg;
						}
						else
						{
	    					$stripecard = $stripe->cardStripe($customer, $companypaymentmode, $stripeacc, $servicestatus, 0);
	    					if ($stripecard)  // Can be card_... (old mode) or pm_... (new mode)
	    					{
	    						$FULLTAG='INV='.$invoice->id.'-CUS='.$thirdparty->id;
	    						$description='Stripe payment from doTakePaymentStripeForThirdparty: '.$FULLTAG.' ref='.$invoice->ref;

	    						$stripefailurecode='';
	    						$stripefailuremessage='';
	    						$stripefailuredeclinecode='';

	    						if (preg_match('/^card_/', $stripecard->id)) // Using old method
	    						{
    	    						dol_syslog("* Create charge on card ".$stripecard->id.", amountstripe=".$amountstripe.", FULLTAG=".$FULLTAG, LOG_DEBUG);

    	    						$charge = null;		// Force reset of $charge, so, if already set from a previous fetch, it will be empty even if there is an exception at next step
    	    						try {
    		    						$charge = \Stripe\Charge::create(array(
    			    						'amount'   => price2num($amountstripe, 'MU'),
    			    						'currency' => $currency,
    			    						'capture'  => true,							// Charge immediatly
    			    						'description' => $description,
    			    						'metadata' => array("FULLTAG" => $FULLTAG, 'Recipient' => $mysoc->name, 'dol_version'=>DOL_VERSION, 'dol_entity'=>$conf->entity, 'ipaddress'=>(empty($_SERVER['REMOTE_ADDR'])?'':$_SERVER['REMOTE_ADDR'])),
    		    							'customer' => $customer->id,
    		    							//'customer' => 'bidon_to_force_error',		// To use to force a stripe error
    			    						'source' => $stripecard,
    		    						    'statement_descriptor' => dol_trunc('INV='.$invoice->id, 10, 'right', 'UTF-8', 1),     // 22 chars that appears on bank receipt (company + description)
    		    						));
    	    						}
    	    						catch(\Stripe\Error\Card $e) {
    	    							// Since it's a decline, Stripe_CardError will be caught
    	    							$body = $e->getJsonBody();
    	    							$err  = $body['error'];

    	    							$stripefailurecode = $err['code'];
    	    							$stripefailuremessage = $err['message'];
    	    							$stripefailuredeclinecode = $err['decline_code'];
    	    						}
    	    						catch(Exception $e)
    	    						{
    	    							$stripefailurecode='UnknownChargeError';
    	    							$stripefailuremessage=$e->getMessage();
    	    						}
	    						}
	    						else                                                                     // Using new SCA method
	    						{
	    						    dol_syslog("* Create payment on card ".$stripecard->id.", amounttopay=".$amounttopay.", amountstripe=".$amountstripe.", FULLTAG=".$FULLTAG, LOG_DEBUG);

	    						    // Create payment intent and charge payment (confirmnow = true)
	    						    $paymentintent = $stripe->getPaymentIntent($amounttopay, $currency, $FULLTAG, $description, $invoice, $customer->id, $stripeacc, $servicestatus, 0, 'automatic', true, $stripecard->id, 1);

	    						    $charge = new stdClass();
	    						    if ($paymentintent->status == 'succeeded')
	    						    {
	    						        $charge->status = 'ok';
	    						        $charge->id = $paymentintent->id;
	    						        $charge->customer = $customer->id;
	    						    }
	    						    else
	    						    {
	    						        $charge->status = 'failed';
	    						        $charge->customer = $customer->id;
                                        $charge->failure_code = $stripe->code;
                                        $charge->failure_message = $stripe->error;
                                        $charge->failure_declinecode = $stripe->declinecode;
                                        $stripefailurecode = $stripe->code;
                                        $stripefailuremessage = $stripe->error;
                                        $stripefailuredeclinecode = $stripe->declinecode;
	    						    }

	    						    //var_dump("stripefailurecode=".$stripefailurecode." stripefailuremessage=".$stripefailuremessage." stripefailuredeclinecode=".$stripefailuredeclinecode);
	    						    //exit;
	    						}

	    						$postactionmessages=array();

	    						// Return $charge = array('id'=>'ch_XXXX', 'status'=>'succeeded|pending|failed', 'failure_code'=>, 'failure_message'=>...)
	    						if (empty($charge) || $charge->status == 'failed')
	    						{
	    							dol_syslog('Failed to charge card or payment mode '.$stripecard->id.' stripefailurecode='.$stripefailurecode.' stripefailuremessage='.$stripefailuremessage.' stripefailuredeclinecode='.$stripefailuredeclinecode, LOG_WARNING);

	    							// Save a stripe payment was in error
	    							$this->stripechargeerror++;

	    							$error++;
	    							$errorforinvoice++;
	    							$errmsg=$langs->trans("FailedToChargeCard");
	    							if (! empty($charge))
	    							{
	    							    if ($stripefailuredeclinecode == 'authentication_required')
	    							    {
	    							        $errauthenticationmessage=$langs->trans("ErrSCAAuthentication");
	    							        $errmsg=$errauthenticationmessage;
	    							    }
	    							    elseif (in_array($stripefailuredeclinecode, array('insufficient_funds', 'generic_decline')))
	    							    {
	    							        $errmsg.=': '.$charge->failure_code;
	    							        $errmsg.=($charge->failure_message?' - ':'').' '.$charge->failure_message;
	    							        if (empty($stripefailurecode))    $stripefailurecode = $charge->failure_code;
	    							        if (empty($stripefailuremessage)) $stripefailuremessage = $charge->failure_message;
	    							    }
	    							    else
	    							    {
    	    								$errmsg.=': failure_code='.$charge->failure_code;
    	    								$errmsg.=($charge->failure_message?' - ':'').' failure_message='.$charge->failure_message;
    	    								if (empty($stripefailurecode))    $stripefailurecode = $charge->failure_code;
    	    								if (empty($stripefailuremessage)) $stripefailuremessage = $charge->failure_message;
	    							    }
	    							}
	    							else
	    							{
	    								$errmsg.=': '.$stripefailurecode.' - '.$stripefailuremessage;
	    								$errmsg.=($stripefailuredeclinecode?' - '.$stripefailuredeclinecode:'');
	    							}

	    							$description='Stripe payment ERROR from doTakePaymentStripeForThirdparty: '.$FULLTAG;
	    							$postactionmessages[]=$errmsg.' ('.$stripearrayofkeys['publishable_key'].')';
	    							$this->errors[]=$errmsg;
	    						}
	    						else
	    						{
	    							dol_syslog('Successfuly charge card '.$stripecard->id);

	    							$postactionmessages[]='Success to charge card ('.$charge->id.' with '.$stripearrayofkeys['publishable_key'].')';

	    							// Save a stripe payment was done in realy life so later we will be able to force a commit on recorded payments
	    							// even if in batch mode (method doTakePaymentStripe), we will always make all action in one transaction with a forced commit.
	    							$this->stripechargedone++;

	    							// Default description used for label of event. Will be overwrite by another value later.
	    							$description='Stripe payment OK ('.$charge->id.') from doTakePaymentStripeForThirdparty: '.$FULLTAG;

	    							$db=$this->db;
	    							$ipaddress = (empty($_SERVER['REMOTE_ADDR'])?'':$_SERVER['REMOTE_ADDR']);
	    							$TRANSACTIONID = $charge->id;
	    							$currency=$conf->currency;
	    							$paymentmethod='stripe';
	    							$emetteur_name = $charge->customer;

	    							// Same code than into paymentok.php...

	    							$paymentTypeId = 0;
	    							if ($paymentmethod == 'paybox') $paymentTypeId = $conf->global->PAYBOX_PAYMENT_MODE_FOR_PAYMENTS;
	    							if ($paymentmethod == 'paypal') $paymentTypeId = $conf->global->PAYPAL_PAYMENT_MODE_FOR_PAYMENTS;
	    							if ($paymentmethod == 'stripe') $paymentTypeId = $conf->global->STRIPE_PAYMENT_MODE_FOR_PAYMENTS;
	    							if (empty($paymentTypeId))
	    							{
	    								$paymentType = $_SESSION["paymentType"];
	    								if (empty($paymentType)) $paymentType = 'CB';
	    								$paymentTypeId = dol_getIdFromCode($this->db, $paymentType, 'c_paiement', 'code', 'id', 1);
	    							}

	    							$currencyCodeType = $currency;

	    							$ispostactionok = 1;

	    							// Creation of payment line
	    							include_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
	    							$paiement = new Paiement($this->db);
	    							$paiement->datepaye     = $now;
	    							$paiement->date         = $now;
	    							if ($currencyCodeType == $conf->currency)
	    							{
	    								$paiement->amounts      = array($invoice->id => $amounttopay);   // Array with all payments dispatching with invoice id
	    							}
	    							else
	    							{
	    								$paiement->multicurrency_amounts = array($invoice->id => $amounttopay);   // Array with all payments dispatching

	    								$postactionmessages[] = 'Payment was done in a different currency than currency expected of company';
	    								$ispostactionok = -1;
	    								// Not yet supported, so error
	    								$error++;
	    								$errorforinvoice++;
	    							}
	    							$paiement->paiementid   = $paymentTypeId;
	    							$paiement->num_paiement = '';
	    							$paiement->num_payment = '';
	    							// Add a comment with keyword 'SellYourSaas' in text. Used by trigger.
	    							$paiement->note_public = 'SellYourSaas payment '.dol_print_date($now, 'standard').' using '.$paymentmethod.($ipaddress?' from ip '.$ipaddress:'').' - Transaction ID = '.$TRANSACTIONID;
	    							$paiement->note_private = 'SellYourSaas payment '.dol_print_date($now, 'standard').' using '.$paymentmethod.($ipaddress?' from ip '.$ipaddress:'').' - Transaction ID = '.$TRANSACTIONID;
	    							$paiement->ext_payment_id = $charge->id.':'.$customer->id.'@'.$stripearrayofkeys['publishable_key'];
	    							$paiement->ext_payment_site = 'stripe';

	    							if (! $errorforinvoice)
	    							{
	    								dol_syslog('* Record payment for invoice id '.$invoice->id.'. It includes closing of invoice and regenerating document');

	    								// This include closing invoices to 'paid' (and trigger including unsuspending) and regenerating document
	    								$paiement_id = $paiement->create($user, 1);
	    								if ($paiement_id < 0)
	    								{
	    								    $postactionmessages[] = $paiement->error.($paiement->error?' ':'').join("<br>\n", $paiement->errors);
	    									$ispostactionok = -1;
	    									$error++;
	    									$errorforinvoice++;
	    								}
	    								else
	    								{
	    									$postactionmessages[] = 'Payment created';
	    								}

	    								dol_syslog("The payment has been created for invoice id ".$invoice->id);
	    							}


	    							if (! $errorforinvoice && ! empty($conf->banque->enabled))
	    							{
	    								dol_syslog('* Add payment to bank');

	    								$bankaccountid = 0;
	    								if ($paymentmethod == 'paybox') $bankaccountid = $conf->global->PAYBOX_BANK_ACCOUNT_FOR_PAYMENTS;
	    								if ($paymentmethod == 'paypal') $bankaccountid = $conf->global->PAYPAL_BANK_ACCOUNT_FOR_PAYMENTS;
	    								if ($paymentmethod == 'stripe') $bankaccountid = $conf->global->STRIPE_BANK_ACCOUNT_FOR_PAYMENTS;

	    								if ($bankaccountid > 0)
	    								{
	    									$label='(CustomerInvoicePayment)';
	    									if ($invoice->type == Facture::TYPE_CREDIT_NOTE) $label='(CustomerInvoicePaymentBack)';  // Refund of a credit note
	    									$result=$paiement->addPaymentToBank($user, 'payment', $label, $bankaccountid, $emetteur_name, '');
	    									if ($result < 0)
	    									{
	    									    $postactionmessages[] = $paiement->error.($paiement->error?' ':'').join("<br>\n", $paiement->errors);
	    										$ispostactionok = -1;
	    										$error++;
	    										$errorforinvoice++;
	    									}
	    									else
	    									{
	    										$postactionmessages[] = 'Bank transaction of payment created (by doTakePaymentStripeForThirdparty)';
	    									}
	    								}
	    								else
	    								{
	    									$postactionmessages[] = 'Setup of bank account to use in module '.$paymentmethod.' was not set. No way to record the payment.';
	    									$ispostactionok = -1;
	    									$error++;
	    									$errorforinvoice++;
	    								}
	    							}

	    							if ($ispostactionok < 1)
	    							{
	    								$description='Stripe payment OK ('.$charge->id.' - '.$amounttopay.' '.$conf->currency.') but post action KO from doTakePaymentStripeForThirdparty: '.$FULLTAG;
	    							}
	    							else
	    							{
	    								$description='Stripe payment+post action OK ('.$charge->id.' - '.$amounttopay.' '.$conf->currency.') from doTakePaymentStripeForThirdparty: '.$FULLTAG;
	    							}
	    						}

	    						$object = $invoice;

	    						// Send email
	    						include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
	    						$formmail=new FormMail($this->db);

	    						// Set output language
	    						$outputlangs = new Translate('', $conf);
	    						$outputlangs->setDefaultLang(empty($object->thirdparty->default_lang) ? $mysoc->default_lang : $object->thirdparty->default_lang);
	    						$outputlangs->loadLangs(array("main", "members", "bills"));

	    						// Get email content from templae
	    						$arraydefaultmessage=null;

	    						$sendemailtocustomer = 1;

	    						if (empty($charge) || $charge->status == 'failed')
	    						{
	    							$labeltouse = 'InvoicePaymentFailure';
	    							if ($noemailtocustomeriferror) $sendemailtocustomer = 0;		// $noemailtocustomeriferror is set when error already reported on myaccount screen
	    						}
	    						else
	    						{
	    							$labeltouse = 'InvoicePaymentSuccess';
	    						}

	    						if ($sendemailtocustomer)
	    						{
	    						    dol_syslog("* Send email with result of payment - ".$labeltouse);

	    						    if (! empty($labeltouse)) $arraydefaultmessage=$formmail->getEMailTemplate($this->db, 'facture_send', $user, $outputlangs, 0, 1, $labeltouse);

		    						if (! empty($labeltouse) && is_object($arraydefaultmessage) && $arraydefaultmessage->id > 0)
		    						{
		    							$subject = $arraydefaultmessage->topic;
		    							$msg     = $arraydefaultmessage->content;
		    						}

		    						$substitutionarray=getCommonSubstitutionArray($outputlangs, 0, null, $object);

		    						$substitutionarray['__SELLYOURSAAS_PAYMENT_ERROR_DESC__']=$stripefailurecode.' '.$stripefailuremessage;

		    						complete_substitutions_array($substitutionarray, $outputlangs, $object);

		    						// Set the property ->ref_customer with ref_customer of contract so __REFCLIENT__ will be replaced in email content
		    						// Search contract linked to invoice
		    						$foundcontract = null;
		    						$invoice->fetchObjectLinked();
		    						if (is_array($invoice->linkedObjects['contrat']) && count($invoice->linkedObjects['contrat']) > 0)
		    						{
		    							//dol_sort_array($object->linkedObjects['facture'], 'date');
		    							foreach($invoice->linkedObjects['contrat'] as $idcontract => $contract)
		    							{
		    								$substitutionarray['__CONTRACT_REF__']=$contract->ref_customer;
		    								$substitutionarray['__REFCLIENT__']=$contract->ref_customer;
		    								$foundcontract = $contract;
		    								break;
		    							}
		    						}

		    						dol_syslog('__DIRECTDOWNLOAD_URL_INVOICE__='.$substitutionarray['__DIRECTDOWNLOAD_URL_INVOICE__']);

		    						$urlforsellyoursaasaccount = getRootUrlForAccount($foundcontract);
		    						if ($urlforsellyoursaasaccount)
		    						{
    		    						$tmpforurl=preg_replace('/.*document.php/', '', $substitutionarray['__DIRECTDOWNLOAD_URL_INVOICE__']);
    		    						if ($tmpforurl)
    		    						{
    		    						    $substitutionarray['__DIRECTDOWNLOAD_URL_INVOICE__']=$urlforsellyoursaasaccount.'/source/document.php'.$tmpforurl;
    		    						}
    		    						else
    		    						{
    		    						    $substitutionarray['__DIRECTDOWNLOAD_URL_INVOICE__']=$urlforsellyoursaasaccount;
    		    						}
		    						}

		    						$subjecttosend = make_substitutions($subject, $substitutionarray, $outputlangs);
		    						$texttosend = make_substitutions($msg, $substitutionarray, $outputlangs);

		    						// Attach a file ?
		    						$file='';
		    						$listofpaths=array();
		    						$listofnames=array();
		    						$listofmimes=array();
		    						if (is_object($invoice))
		    						{
		    							$invoicediroutput = $conf->facture->dir_output;
		    							$fileparams = dol_most_recent_file($invoicediroutput . '/' . $invoice->ref, preg_quote($invoice->ref, '/').'[^\-]+');
		    							$file = $fileparams['fullname'];
										$file = '';		// Disable attachment of invoice in emails

		    							if ($file)
		    							{
		    								$listofpaths=array($file);
		    								$listofnames=array(basename($file));
		    								$listofmimes=array(dol_mimetype($file));
		    							}
		    						}
		    						$from = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;


		    						$trackid='inv'.$invoice->id;
									$moreinheader='X-Dolibarr-Info: doTakeStripePaymentForThirdParty'."\r\n";

		    						// Send email (substitutionarray must be done just before this)
		    						include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		    						$mailfile = new CMailFile($subjecttosend, $invoice->thirdparty->email, $from, $texttosend, $listofpaths, $listofmimes, $listofnames, '', '', 0, -1, '', '', $trackid, $moreinheader);
		    						if ($mailfile->sendfile())
		    						{
		    							$result = 1;
		    						}
		    						else
		    						{
		    							$this->error=$langs->trans("ErrorFailedToSendMail", $from, $invoice->thirdparty->email).'. '.$mailfile->error;
		    							$result = -1;
		    						}

		    						if ($result < 0)
		    						{
		    							$errmsg=$this->error;
		    							$postactionmessages[] = $errmsg;
		    							$ispostactionok = -1;
		    						}
		    						else
		    						{
		    						    if ($file) $postactionmessages[] = 'Email sent to thirdparty (to '.$invoice->thirdparty->email.' with invoice document attached: '.$file.', language = '.$outputlangs->defaultlang.')';
		    						    else $postactionmessages[] = 'Email sent to thirdparty (to '.$invoice->thirdparty->email.' without any attached document, language = '.$outputlangs->defaultlang.')';
		    						}
	    						}

	    						// Track an event
	    						if (empty($charge) || $charge->status == 'failed')
	    						{
	    							$actioncode='PAYMENT_STRIPE_KO';
	    							$extraparams=$stripefailurecode;
	    							$extraparams.=(($extraparams && $stripefailuremessage)?' - ':'').$stripefailuremessage;
	    							$extraparams.=(($extraparams && $stripefailuredeclinecode)?' - ':'').$stripefailuredeclinecode;
	    						}
	    						else
	    						{
	    							$actioncode='PAYMENT_STRIPE_OK';
	    							$extraparams='';
	    						}

	    						dol_syslog("* Record event for payment result");

	    						// Insert record of payment (success or error)
	    						$actioncomm = new ActionComm($this->db);

	    						$actioncomm->type_code    = 'AC_OTH_AUTO';		// Type of event ('AC_OTH', 'AC_OTH_AUTO', 'AC_XXX'...)
	    						$actioncomm->code         = 'AC_'.$actioncode;
	    						$actioncomm->label        = $description;
	    						$actioncomm->note_private = join(",\n", $postactionmessages);
	    						$actioncomm->fk_project   = $invoice->fk_project;
	    						$actioncomm->datep        = $now;
	    						$actioncomm->datef        = $now;
	    						$actioncomm->percentage   = -1;   // Not applicable
	    						$actioncomm->socid        = $thirdparty->id;
	    						$actioncomm->contactid    = 0;
	    						$actioncomm->authorid     = $user->id;   // User saving action
	    						$actioncomm->userownerid  = $user->id;	// Owner of action
	    						// Fields when action is en email (content should be added into note)
	    						/*$actioncomm->email_msgid = $object->email_msgid;
	    						 $actioncomm->email_from  = $object->email_from;
	    						 $actioncomm->email_sender= $object->email_sender;
	    						 $actioncomm->email_to    = $object->email_to;
	    						 $actioncomm->email_tocc  = $object->email_tocc;
	    						 $actioncomm->email_tobcc = $object->email_tobcc;
	    						 $actioncomm->email_subject = $object->email_subject;
	    						 $actioncomm->errors_to   = $object->errors_to;*/
	    						$actioncomm->fk_element   = $invoice->id;
	    						$actioncomm->elementtype  = $invoice->element;
	    						$actioncomm->extraparams  = dol_trunc($extraparams, 250);

	    						$actioncomm->create($user);

	    						$this->description=$description;
	    						$this->postactionmessages=$postactionmessages;
	    					}
	    					else
	    					{
	    						$error++;
	    						$errorforinvoice++;
	    						dol_syslog("No card or payment method found for this stripe customer ".$customer->id, LOG_WARNING);
	    						$this->errors[]='Failed to get card | payment method for stripe customer = '.$customer->id;
	    					}
						}
    				} else {
    					if ($resultthirdparty <= 0)
    					{
    						dol_syslog('Failed to load customer for thirdparty_id = '.$thirdparty->id, LOG_WARNING);
    						$this->errors[]='Failed to load customer for thirdparty_id = '.$thirdparty->id;
    					}
    					else 		// $customer stripe not found
    					{
    					    dol_syslog('Failed to get Stripe customer id for thirdparty_id = '.$thirdparty->id." in mode ".$servicestatus." in Stripe env ".$stripearrayofkeysbyenv[$servicestatus]['publishable_key'], LOG_WARNING);
    					    $this->errors[]='Failed to get Stripe customer id for thirdparty_id = '.$thirdparty->id." in mode ".$servicestatus." in Stripe env ".$stripearrayofkeysbyenv[$servicestatus]['publishable_key'];
    					}
    					$error++;
    					$errorforinvoice++;
    				}
    			}
    			catch(Exception $e)
    			{
    				$error++;
    				$errorforinvoice++;
    				dol_syslog('Error '.$e->getMessage(), LOG_ERR);
    				$this->errors[]='Error '.$e->getMessage();
    			}
    		}
    		else
    		{
    			$error++;
    			$errorforinvoice++;
    			dol_syslog("Remain to pay is null for the invoice ".$invoice->id." ".$invoice->ref.". Why is the invoice not classified 'Paid' ?", LOG_WARNING);
    			$this->errors[]="Remain to pay is null for the invoice ".$invoice->id." ".$invoice->ref.". Why is the invoice not classified 'Paid' ?";
    		}
		}

		// Payments are processed, and next batch will be to make renewal

    	return $error;
    }


    /**
     * Action executed by scheduler
     * Loop on invoice for customer with default payment mode Paypal and take payment. Unsuspend if it was suspended.
     * CAN BE A CRON TASK
     *
     * @param	int		$maxnbofinvoicetotry    	Max number of payment to do (0 = No max)
     * @param	int		$noemailtocustomeriferror	1=No email sent to customer if there is a payment error (can be used when error is already reported on screen)
     * @return	int									0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doTakePaymentPaypal($maxnbofinvoicetotry = 0, $noemailtocustomeriferror = 0)
    {
    	global $conf, $langs;

    	$langs->load("agenda");

    	$savlog = $conf->global->SYSLOG_FILE;
    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doTakePaymentPaypal.log';

    	$error = 0;
    	$this->output = '';
    	$this->error='';

    	dol_syslog(__METHOD__, LOG_DEBUG);

    	$this->db->begin();

    	// ...

    	//$this->output = count($invoiceprocessed).' validated invoice with a valid default payment mode processed'.(count($invoiceprocessed)>0 ? ' : '.join(',', $invoiceprocessed) : '');
    	$this->output = 'Not implemented yet';

    	$this->db->commit();

    	// Payments are processed, and next batch will be to make renewal

    	$conf->global->SYSLOG_FILE = $savlog;

    	return $error;
    }


    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     * Loop on each contract. If it is a paid contract, and there is no unpaid invoice for contract, and end date < today + 2 days (so expired or soon expired),
     * we update qty of contract + qty of linked template invoice.
     *
     * @param	int		$thirdparty_id			Thirdparty id
     * @return	int								0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doRefreshContracts($thirdparty_id=0)
    {
    	global $conf, $langs, $user;

    	$langs->load("agenda");

    	$savlog = $conf->global->SYSLOG_FILE;
    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doRefreshContracts.log';

    	$now = dol_now();

    	$mode = 'paid';
    	$delayindaysshort = 2;	// So we update the resources 2 days before the invoice is generated
    	$enddatetoscan = dol_time_plus_duree($now, abs($delayindaysshort), 'd');		// $enddatetoscan = yesterday

    	$error = 0;
    	$this->output = '';
    	$this->error='';

    	$contractprocessed = array();
    	$contractignored = array();
    	$contracterror = array();

    	dol_syslog(__METHOD__, LOG_DEBUG);

    	$sql = 'SELECT c.rowid, c.ref_customer, cd.rowid as lid, cd.date_fin_validite';
    	$sql.= ' FROM '.MAIN_DB_PREFIX.'contrat as c, '.MAIN_DB_PREFIX.'contratdet as cd, '.MAIN_DB_PREFIX.'contrat_extrafields as ce,';
    	$sql.= ' '.MAIN_DB_PREFIX.'societe_extrafields as se';
    	$sql.= ' WHERE cd.fk_contrat = c.rowid AND ce.fk_object = c.rowid';
    	$sql.= " AND ce.deployment_status = 'done'";
    	//$sql.= " AND cd.date_fin_validite < '".$this->db->idate(dol_time_plus_duree($now, abs($delayindaysshort), 'd'))."'";
    	//$sql.= " AND cd.date_fin_validite > '".$this->db->idate(dol_time_plus_duree($now, abs($delayindayshard), 'd'))."'";
    	$sql.= " AND date_format(cd.date_fin_validite, '%Y-%m-%d') <= date_format('".$this->db->idate($enddatetoscan)."', '%Y-%m-%d')";
    	$sql.= " AND cd.statut = 4";
    	$sql.= " AND c.fk_soc = se.fk_object AND se.dolicloud = 'yesv2'";
    	if ($thirdparty_id > 0) $sql.=" AND c.fk_soc = ".$thirdparty_id;
    	//print $sql;

    	$resql = $this->db->query($sql);
    	if ($resql)
    	{
    		$num = $this->db->num_rows($resql);

    		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';

    		$i=0;
    		while ($i < $num)
    		{
    			$obj = $this->db->fetch_object($resql);
    			if ($obj)
    			{
    				if (! empty($contractprocessed[$obj->rowid]) || ! empty($contractignored[$obj->rowid]) || ! empty($contracterror[$obj->rowid])) continue;

    				// Test if this is a paid or not instance
    				$object = new Contrat($this->db);
    				$object->fetch($obj->rowid);		// fetch also lines
    				$object->fetch_thirdparty();

    				if ($object->id <= 0)
    				{
    					$error++;
    					$this->errors[] = 'Failed to load contract with id='.$obj->rowid;
    					continue;
    				}

    				dol_syslog("* Process contract in doRefreshContracts for contract id=".$object->id." ref=".$object->ref." ref_customer=".$object->ref_customer);

    				dol_syslog('Call sellyoursaasIsPaidInstance', LOG_DEBUG, 1);
    				$isAPayingContract = sellyoursaasIsPaidInstance($object);
    				dol_syslog('', 0, -1);
    				if ($mode == 'test' && $isAPayingContract)
    				{
    					$contractignored[$object->id]=$object->ref;
    					continue;											// Discard if this is a paid instance when we are in test mode
    				}
    				if ($mode == 'paid' && ! $isAPayingContract)
    				{
    					$contractignored[$object->id]=$object->ref;
    					continue;											// Discard if this is a test instance when we are in paid mode
    				}

    				// Update expiration date of instance
    				dol_syslog('Call sellyoursaasGetExpirationDate', LOG_DEBUG, 1);
    				$tmparray = sellyoursaasGetExpirationDate($object);
    				dol_syslog('', 0, -1);
    				$expirationdate = $tmparray['expirationdate'];
    				$duration_value = $tmparray['duration_value'];
    				$duration_unit = $tmparray['duration_unit'];
    				//var_dump($expirationdate.' '.$enddatetoscan);

    				// Test if there is pending invoice
    				$object->fetchObjectLinked();

    				dol_syslog('Search if there is at least one open invoice', LOG_DEBUG);
    				if (is_array($object->linkedObjects['facture']) && count($object->linkedObjects['facture']) > 0)
    				{
    					usort($object->linkedObjects['facture'], "cmp");

    					//dol_sort_array($contract->linkedObjects['facture'], 'date');
    					$someinvoicenotpaid=0;
    					foreach($object->linkedObjects['facture'] as $idinvoice => $invoice)
    					{
    						if ($invoice->statut == Facture::STATUS_DRAFT) continue;	// Draft invoice are not unpaid invoices

    						if (empty($invoice->paye))
    						{
    							$someinvoicenotpaid++;
    						}
    					}
    					if ($someinvoicenotpaid)
    					{
    						$this->output .= 'Contract '.$object->ref.' is qualified for refresh but there is '.$someinvoicenotpaid.' invoice(s) unpayed so we cancel refresh'."\n";
    						$contractignored[$object->id]=$object->ref;
    						continue;
    					}
    				}

    				if ($expirationdate && $expirationdate < $enddatetoscan)
    				{
    				    dol_syslog("Define the newdate of end of services from expirationdate=".$expirationdate.' ('.dol_print_date($expirationdate, 'dayhourlog').')');
    				    $newdate = $expirationdate;
    					$protecti=0;	//$protecti is to avoid infinite loop
    					while ($newdate < $enddatetoscan && $protecti < 1000)
    					{
    						$newdate = dol_time_plus_duree($newdate, $duration_value, $duration_unit);
    						$protecti++;
    					}

    					if ($protecti < 1000)	// If not, there is a pb
    					{
    						// We will update the end of date of contrat, so first we refresh contract data
    					    dol_syslog("We update qty of resources by a remote action refresh on ".$object->ref);

    						$this->db->begin();

    						$errorforlocaltransaction = 0;

    						$comment = 'Refresh contract '.$object->ref." by doRenewalContracts";
    						// First launch update of resources: This update status of install.lock+authorized key and update qty of contract lines + linked template invoice
    						$result = $this->sellyoursaasRemoteAction('refresh', $object, 'admin', '', '', '0', $comment);	// This include add of event if qty has changed
    						if ($result <= 0)
    						{
    							$contracterror[$object->id]=$object->ref;

    							$error++;
    							$errorforlocaltransaction++;
    							$this->error=$this->error;
    							$this->errors=$this->errors;
    						}
    						else
    						{
    							$contractprocessed[$object->id]=$object->ref;
    						}

    						if (! $errorforlocaltransaction)
    						{
    							$this->db->commit();
    						}
    						else
    						{
    							$this->db->rollback();
    						}
    					}
    					else
    					{
    						$error++;
    						$this->error = "Bad value for newdate in doRefreshContracts - expirationdate=".$expirationdate." enddatetoscan=".$enddatetoscan." duration_value=".$duration_value." duration_unit=".$duration_value;
    						dol_syslog($this->error, LOG_ERR);
    					}
    				}
    			}
    			$i++;
    		}
    	}
    	else
    	{
    		$error++;
    		$this->error = $this->db->lasterror();
    	}

    	$this->output .= count($contractprocessed).' paying contract(s) with end date before '.dol_print_date($enddatetoscan, 'day').' were refreshed'.(count($contractprocessed)>0 ? ' : '.join(',', $contractprocessed) : '').' (search done on contracts of SellYourSaas customers only)';

    	$conf->global->SYSLOG_FILE = $savlog;

    	return ($error ? 1: 0);
    }


    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     * Loop on each contract. If it is a paid contract, and there is no unpaid invoice for contract, and lines are not suspended and end date < today + 2 days (so expired or soon expired),
     * we make a refresh (update qty of contract + qty of linked template invoice)ss + the running contract service end date to end at next period.
     *
     * @param	int		$thirdparty_id			Thirdparty id
     * @return	int								0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doRenewalContracts($thirdparty_id=0)
    {
    	global $conf, $langs, $user;

    	$langs->load("agenda");

    	$savlog = $conf->global->SYSLOG_FILE;
    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doRenewalContracts.log';

    	$now = dol_now();

    	$mode = 'paid';
    	$delayindaysshort = 1;	// So we renew the resources 1 day after the invoice is generated and paid or in error (this means we have 2 chances to build invoice before renewal)
    	$enddatetoscan = dol_time_plus_duree($now, -1 * abs($delayindaysshort), 'd');		// $enddatetoscan = yesterday

    	$error = 0;
    	$this->output = '';
    	$this->error='';

    	$contractprocessed = array();
    	$contractignored = array();
    	$contracterror = array();

    	dol_syslog(__METHOD__, LOG_DEBUG);

    	$sql = 'SELECT c.rowid, c.ref_customer, cd.rowid as lid, cd.date_fin_validite';
    	$sql.= ' FROM '.MAIN_DB_PREFIX.'contrat as c, '.MAIN_DB_PREFIX.'contratdet as cd, '.MAIN_DB_PREFIX.'contrat_extrafields as ce,';
    	$sql.= ' '.MAIN_DB_PREFIX.'societe_extrafields as se';
    	$sql.= ' WHERE cd.fk_contrat = c.rowid AND ce.fk_object = c.rowid';
    	$sql.= " AND ce.deployment_status = 'done'";
    	//$sql.= " AND cd.date_fin_validite < '".$this->db->idate(dol_time_plus_duree($now, abs($delayindaysshort), 'd'))."'";
    	//$sql.= " AND cd.date_fin_validite > '".$this->db->idate(dol_time_plus_duree($now, abs($delayindayshard), 'd'))."'";
    	$sql.= " AND date_format(cd.date_fin_validite, '%Y-%m-%d') <= date_format('".$this->db->idate($enddatetoscan)."', '%Y-%m-%d')";
    	$sql.= " AND cd.statut = 4";
    	$sql.= " AND c.fk_soc = se.fk_object AND se.dolicloud = 'yesv2'";
    	if ($thirdparty_id > 0) $sql.=" AND c.fk_soc = ".$thirdparty_id;
    	//print $sql;

    	$resql = $this->db->query($sql);
    	if ($resql)
    	{
    		$num = $this->db->num_rows($resql);

    		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';

    		$i=0;
    		while ($i < $num)
    		{
    			$obj = $this->db->fetch_object($resql);
    			if ($obj)
    			{
    				if (! empty($contractprocessed[$obj->rowid]) || ! empty($contractignored[$obj->rowid]) || ! empty($contracterror[$obj->rowid])) continue;

    				// Test if this is a paid or not instance
    				$object = new Contrat($this->db);
    				$object->fetch($obj->rowid);		// fetch also lines
    				$object->fetch_thirdparty();

    				if ($object->id <= 0)
    				{
    					$error++;
    					$this->errors[] = 'Failed to load contract with id='.$obj->rowid;
    					continue;
    				}

    				dol_syslog("* Process contract in doRenewalContracts for contract id=".$object->id." ref=".$object->ref." ref_customer=".$object->ref_customer);

    				dol_syslog('Call sellyoursaasIsPaidInstance', LOG_DEBUG, 1);
    				$isAPayingContract = sellyoursaasIsPaidInstance($object);
    				dol_syslog('', 0, -1);
    				if ($mode == 'test' && $isAPayingContract)
    				{
    					$contractignored[$object->id]=$object->ref;
    					continue;											// Discard if this is a paid instance when we are in test mode
    				}
    				if ($mode == 'paid' && ! $isAPayingContract)
    				{
    					$contractignored[$object->id]=$object->ref;
    					continue;											// Discard if this is a test instance when we are in paid mode
    				}

    				// Update expiration date of instance
    				dol_syslog('Call sellyoursaasGetExpirationDate', LOG_DEBUG, 1);
    				$tmparray = sellyoursaasGetExpirationDate($object);
    				dol_syslog('', 0, -1);
    				$expirationdate = $tmparray['expirationdate'];
    				$duration_value = $tmparray['duration_value'];
    				$duration_unit = $tmparray['duration_unit'];
    				//var_dump($expirationdate.' '.$enddatetoscan);

    				// Test if there is pending invoice
    				$object->fetchObjectLinked();

    				if (is_array($object->linkedObjects['facture']) && count($object->linkedObjects['facture']) > 0)
    				{
    					usort($object->linkedObjects['facture'], "cmp");

    					//dol_sort_array($contract->linkedObjects['facture'], 'date');
    					$someinvoicenotpaid=0;
    					foreach($object->linkedObjects['facture'] as $idinvoice => $invoice)
    					{
    						if ($invoice->statut == Facture::STATUS_DRAFT) continue;	// Draft invoice are not invoice not paid

    						if (empty($invoice->paye))
    						{
    							$someinvoicenotpaid++;
    						}
    					}
    					if ($someinvoicenotpaid)
    					{
    						$this->output .= 'Contract '.$object->ref.' is qualified for renewal but there is '.$someinvoicenotpaid.' invoice(s) unpayed so we cancel renewal'."\n";
    						$contractignored[$object->id]=$object->ref;
    						continue;
    					}
    				}

    				if ($expirationdate && $expirationdate < $enddatetoscan)
    				{
    				    dol_syslog("Define the newdate of end of services from expirationdate=".$expirationdate);
    				    $newdate = $expirationdate;
    					$protecti=0;	//$protecti is to avoid infinite loop
    					while ($newdate < $enddatetoscan && $protecti < 1000)
    					{
    						$newdate = dol_time_plus_duree($newdate, $duration_value, $duration_unit);
    						$protecti++;
    					}

    					if ($protecti < 1000)	// If not, there is a pb
    					{
    						// We will update the end of date of contrat, so first we refresh contract data
    						dol_syslog("We will update the end of date of contract with newdate=".dol_print_date($newdate, 'dayhourrfc')." but first, we update qty of resources by a remote action refresh.");

    						$this->db->begin();

    						$errorforlocaltransaction = 0;

    						$comment = 'Refresh contract '.$object->ref." by doRenewalContracts";
    						// First launch update of resources: This update status of install.lock+authorized key and update qty on contract lines and on linked template invoice. An event is added if qty is changed.
    						$result = $this->sellyoursaasRemoteAction('refresh', $object, 'admin', '', '', '0', $comment);
    						if ($result <= 0)
    						{
    							$contracterror[$object->id]=$object->ref;

    							$error++;
    							$errorforlocaltransaction++;
    							$this->error=$this->error;
    							$this->errors=$this->errors;
    						}
							else
    						{
	    						$sqlupdate = 'UPDATE '.MAIN_DB_PREFIX."contratdet SET date_fin_validite = '".$this->db->idate($newdate)."'";
	    						$sqlupdate.= ' WHERE fk_contrat = '.$object->id;
	    						$resqlupdate = $this->db->query($sqlupdate);
	    						if ($resqlupdate)
	    						{
	    							$contractprocessed[$object->id]=$object->ref;

	    							$actioncode = 'RENEW_CONTRACT';
	    							$now = dol_now();

	    							// Create an event
	    							$actioncomm = new ActionComm($this->db);
	    							$actioncomm->type_code   = 'AC_OTH_AUTO';		// Type of event ('AC_OTH', 'AC_OTH_AUTO', 'AC_XXX'...)
	    							$actioncomm->code        = 'AC_'.$actioncode;
	    							$actioncomm->label       = 'Renewal of contrat '.$object->ref;
	    							$actioncomm->datep       = $now;
	    							$actioncomm->datef       = $now;
	    							$actioncomm->percentage  = -1;   // Not applicable
	    							$actioncomm->socid       = $object->thirdparty->id;
	    							$actioncomm->authorid    = $user->id;   // User saving action
	    							$actioncomm->userownerid = $user->id;	// Owner of action
	    							$actioncomm->fk_element  = $object->id;
	    							$actioncomm->elementtype = 'contract';
	    							$actioncomm->note        = 'Contract renewed by doRenewalContracts';
	    							$ret=$actioncomm->create($user);       // User creating action
	    						}
	    						else
	    						{
	    							$contracterror[$object->id]=$object->ref;

	    							$error++;
	    							$errorforlocaltransaction++;
	    							$this->error = $this->db->lasterror();
	    						}
    						}

    						if (! $errorforlocaltransaction)
    						{
    							$this->db->commit();
    						}
    						else
    						{
    							$this->db->rollback();
    						}
    					}
    					else
    					{
    						$error++;
    						$this->error = "Bad value for newdate in doRenewalContracts - expirationdate=".$expirationdate." enddatetoscan=".$enddatetoscan." duration_value=".$duration_value." duration_unit=".$duration_value;
    						dol_syslog($this->error, LOG_ERR);
    					}
    				}
    			}
    			$i++;
    		}
    	}
    	else
    	{
    		$error++;
    		$this->error = $this->db->lasterror();
    	}

    	$this->output .= count($contractprocessed).' paying contract(s) with end date before '.dol_print_date($enddatetoscan, 'day').' were renewed'.(count($contractprocessed)>0 ? ' : '.join(',', $contractprocessed) : '').' (search done on contracts of SellYourSaas customers only)';

    	$conf->global->SYSLOG_FILE = $savlog;

    	return ($error ? 1: 0);
    }




    /**
     * Action executed by scheduler
   	 * Suspend expired services of test instances if we are after planned end date (+ grace offset SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND)
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doSuspendExpiredTestInstances()
    {
    	global $conf, $langs;

    	$langs->load("agenda");

    	$savlog = $conf->global->SYSLOG_FILE;
    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doSuspendExpiredTestInstances.log';

    	dol_syslog(__METHOD__, LOG_DEBUG);
    	$result = $this->doSuspendInstances('test');

    	$conf->global->SYSLOG_FILE = $savlog;

    	return $result;
    }

    /**
     * Action executed by scheduler
   	 * Suspend expired services of paid instances if we are after planned end date (+ grace offset in SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND)
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doSuspendExpiredRealInstances()
    {
    	global $conf, $langs;

    	$langs->load("agenda");

    	$savlog = $conf->global->SYSLOG_FILE;
    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doSuspendExpiredRealInstances.log';

    	dol_syslog(__METHOD__, LOG_DEBUG);
    	$result = $this->doSuspendInstances('paid');

    	$conf->global->SYSLOG_FILE = $savlog;

    	return $result;
    }


   	/**
   	 * Called by batch only: doSuspendExpiredTestInstances or doSuspendExpiredRealInstances
   	 * It sets the status of services to "offline" and send an email to the customer.
   	 * Note: An instance can also be suspended from backoffice by setting service to "offline". In such a case, no email is sent.
   	 *
   	 * @param	string	$mode		'test' or 'paid'
   	 * @return	int					0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
   	 */
   	private function doSuspendInstances($mode)
   	{
    	global $conf, $langs, $user;

    	$MAXPERCALL = 25;

    	if ($mode != 'test' && $mode != 'paid')
    	{
    		$this->error = 'Function doSuspendInstances called with bad value for parameter $mode';
    		return -1;
    	}

    	$langs->loadLangs(array("sellyoursaas", "agenda"));

    	$error = 0;
    	$erroremail = '';
    	$this->output = '';
    	$this->error='';
    	$contractprocessed = array();
		$contractconvertedintemplateinvoice = array();

    	$gracedelay=9999999;
    	if ($mode == 'test') $gracedelay=$conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND;
    	if ($mode == 'paid') $gracedelay=$conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND;
    	if ($gracedelay < 1)
    	{
    		$this->error='BadValueForDelayBeforeSuspensionCheckSetup';
    		return -1;
    	}

    	dol_syslog(get_class($this)."::doSuspendInstances suspend expired instance in mode ".$mode." with grace delay of ".$gracedelay);

    	$now = dol_now();
    	$datetotest = dol_time_plus_duree($now, -1 * abs($gracedelay), 'd');

    	$this->db->begin();

    	$sql = 'SELECT c.rowid, c.ref_customer, cd.rowid as lid';
    	$sql.= ' FROM '.MAIN_DB_PREFIX.'contrat as c, '.MAIN_DB_PREFIX.'contratdet as cd, '.MAIN_DB_PREFIX.'contrat_extrafields as ce,';
    	$sql.= ' '.MAIN_DB_PREFIX.'societe_extrafields as se';
    	$sql.= ' WHERE cd.fk_contrat = c.rowid AND ce.fk_object = c.rowid';
    	$sql.= " AND ce.deployment_status = 'done'";
    	$sql.= " AND cd.date_fin_validite < '".$this->db->idate($datetotest)."'";
    	$sql.= " AND cd.statut = 4";												// Not yet suspended
    	$sql.= " AND se.fk_object = c.fk_soc AND se.dolicloud = 'yesv2'";
		$sql.= $this->db->order('c.rowid','ASC');
    	// Limit is managed into loop later

    	$resql = $this->db->query($sql);
    	if ($resql)
    	{
    		$numofexpiredcontractlines = $this->db->num_rows($resql);

    		$somethingdoneoncontract = 0;
    		$ifetchservice = 0;
    		while ($ifetchservice < $numofexpiredcontractlines)
    		{
    		    $ifetchservice++;

				$obj = $this->db->fetch_object($resql);
				if ($obj)
				{
					if (! empty($contractprocessed[$obj->rowid])) continue;

					if ($somethingdoneoncontract >= $MAXPERCALL)
					{
					    dol_syslog("We reach the limit of ".$MAXPERCALL." contract processed, so we quit loop for this batch doSuspendInstances to avoid to reach email quota.", LOG_WARNING);
					    break;
					}

					// Test if this is a paid or not instance
					$object = new Contrat($this->db);
					$object->fetch($obj->rowid);

					if ($object->id <= 0)
					{
						$error++;
						$this->errors[] = 'Failed to load contract with id='.$obj->rowid;
						continue;
					}

					$object->fetch_thirdparty();

					dol_syslog("* Process fetch line nb ".$ifetchservice." - contract id=".$object->id." ref=".$object->ref." ref_customer=".$object->ref_customer);

					dol_syslog('Call sellyoursaasIsPaidInstance', LOG_DEBUG, 1);
					$isAPayingContract = sellyoursaasIsPaidInstance($object);
					dol_syslog('', 0, -1);
					if ($mode == 'test' && $isAPayingContract)
					{
						dol_syslog("It is a paying contract, it will not be processed by this batch");
						continue;											// Discard if this is a paid instance when we are in test mode
					}
					if ($mode == 'paid' && ! $isAPayingContract)
					{
						dol_syslog("It is not a paying contract, it will not be processed by this batch");
						continue;											// Discard if this is a test instance when we are in paid mode
					}

					// Get expiration date
					dol_syslog('Call sellyoursaasGetExpirationDate', LOG_DEBUG, 1);
					$tmparray = sellyoursaasGetExpirationDate($object);
					dol_syslog('', 0, -1);
					$expirationdate = $tmparray['expirationdate'];

					if ($expirationdate && $expirationdate < $now)	// If contract expired (we already had a test into main select, this is a security)
					{
					    $somethingdoneoncontract++;

						$wemustsuspendinstance = false;

						// If thirdparty has a default payment mode,
						//   if no template invoice yet (for example a second instance for existing customer), we will create the template invoice (= test instance will move in a paid mode instead of being suspended).
						//   if already a template invoice exists, we will suspend instance
						$customerHasAPaymentMode = sellyoursaasThirdpartyHasPaymentMode($object->thirdparty->id);
						if ($customerHasAPaymentMode)
						{
							// Portion of code similar to more complete code into index.php
							// We set some parameter to be able to use same code
							$sellyoursaasutils = $this;
							$db = $this->db;
							$listofcontractid = array($object);

							foreach ($listofcontractid as $contract)
							{
							    dol_syslog("--- Create recurring invoice on contract contract_id = ".$contract->id." if it does not have yet.", LOG_DEBUG, 0);

								if ($contract->array_options['options_deployment_status'] != 'done')
								{
								    dol_syslog("--- Deployment status is not 'done', we discard this contract", LOG_DEBUG, 0);
								    continue;							// This is a not valid contract (undeployed or not yet completely deployed), so we discard this contract to avoid to create template not expected
								}
								if ($contract->total_ht == 0)
								{
								    dol_syslog("--- Amount is null, we discard this contract", LOG_DEBUG, 0);
								    continue;							// Amount is null, so we do not create recurring invoice for that. Note: This should not happen.
								}

								// Make a test to pass loop if there is already a template invoice
								$result = $contract->fetchObjectLinked();
								if ($result < 0)
								{
									continue;							// There is an error, so we discard this contract to avoid to create template twice
								}
								if (! empty($contract->linkedObjectsIds['facturerec']))
								{
									$templateinvoice = reset($contract->linkedObjectsIds['facturerec']);
									if ($templateinvoice > 0)			// There is already a template invoice, so we discard this contract to avoid to create template twice
									{
										// We will suspend the instance
										$wemustsuspendinstance = true;
										continue;
									}
								}

								dol_syslog("--- No template invoice found for this contract contract_id = ".$contract->id.", so we refresh contract before creating template invoice + creating invoice (if template invoice date is already in past) + making contract renewal.", LOG_DEBUG, 0);

								$comment = 'Refresh contract '.$contract->ref." by doSuspendInstances because we need to create a template invoice (test expired but a payment mode exists for customer)";
								// First launch update of resources: This update status of install.lock+authorized key and update qty of contract lines
								$result = $sellyoursaasutils->sellyoursaasRemoteAction('refresh', $contract, 'admin', '', '', '0', $comment);


								dol_syslog("--- No template invoice found for this contract contract_id = ".$contract->id.", so we create it then create real invoice (if template invoice date is already in past) then make contract renewal.", LOG_DEBUG, 0);

								// Now create invoice draft
								$dateinvoice = $contract->array_options['options_date_endfreeperiod'];
								if ($dateinvoice < $now) $dateinvoice = $now;

								$invoice_draft = new Facture($db);
								$tmpproduct = new Product($db);

								// Create empty invoice
								if (! $error)
								{
									$invoice_draft->socid				= $contract->socid;
									$invoice_draft->type				= Facture::TYPE_STANDARD;
									$invoice_draft->number				= '';
									$invoice_draft->date				= $dateinvoice;

									$invoice_draft->note_private		= 'Template invoice created by batch doSuspendInstances because expired and a payment mode exists for customer';
									$invoice_draft->mode_reglement_id	= dol_getIdFromCode($db, 'CB', 'c_paiement', 'code', 'id', 1);
									$invoice_draft->cond_reglement_id	= dol_getIdFromCode($db, 'RECEP', 'c_payment_term', 'code', 'rowid', 1);
									$invoice_draft->fk_account          = $conf->global->STRIPE_BANK_ACCOUNT_FOR_PAYMENTS;	// stripe

									$invoice_draft->fetch_thirdparty();

									$origin='contrat';
									$originid=$contract->id;

									$invoice_draft->origin = $origin;
									$invoice_draft->origin_id = $originid;

									// Possibility to add external linked objects with hooks
									$invoice_draft->linked_objects[$invoice_draft->origin] = $invoice_draft->origin_id;

									$idinvoice = $invoice_draft->create($user);      // This include class to add_object_linked() and add add_contact()
									if (! ($idinvoice > 0))
									{
									    if ($invoice_draft->error) $this->errors[] = $invoice_draft->error;
									    else $this->errors[] = 'Error creating draft invoice';
										$error++;
									}
								}
								// Add lines on invoice
								if (! $error)
								{
									// Add lines of contract to template invoice
									$srcobject = $contract;

									$lines = $srcobject->lines;
									if (empty($lines) && method_exists($srcobject, 'fetch_lines'))
									{
										$srcobject->fetch_lines();
										$lines = $srcobject->lines;
									}

									$frequency=1;
									$frequency_unit='m';

									$date_start = false;
									$fk_parent_line=0;
									$num=count($lines);
									for ($i=0; $i<$num; $i++)
									{
										$label=(! empty($lines[$i]->label)?$lines[$i]->label:'');
										$desc=(! empty($lines[$i]->desc)?$lines[$i]->desc:$lines[$i]->libelle);
										if ($invoice_draft->situation_counter == 1) $lines[$i]->situation_percent =  0;

										// Product type of line
										$product_type = ($lines[$i]->product_type ? $lines[$i]->product_type : 0);

										// Date start
										$date_start = false;
										if ($lines[$i]->date_debut_prevue) $date_start = $lines[$i]->date_debut_prevue;
										if ($lines[$i]->date_debut_reel)   $date_start = $lines[$i]->date_debut_reel;
										if ($lines[$i]->date_start)        $date_start = $lines[$i]->date_start;

    									// Date end
    									$date_end = false;
    									if ($lines[$i]->date_fin_prevue)   $date_end = $lines[$i]->date_fin_prevue;
    									if ($lines[$i]->date_fin_reel)     $date_end = $lines[$i]->date_fin_reel;
    									if ($lines[$i]->date_end)          $date_end = $lines[$i]->date_end;

										// If date start is in past, we set it to now
										$now = dol_now();
										if ($date_start < $now)
										{
											dol_syslog("--- Date start is in past, so we take current date as date start and update also end date of contract", LOG_DEBUG, 0);
											$tmparray = sellyoursaasGetExpirationDate($srcobject);
											$duration_value = $tmparray['duration_value'];
											$duration_unit = $tmparray['duration_unit'];

											$date_start = $now;
											$date_end = dol_time_plus_duree($now, $duration_value, $duration_unit) - 1;

											// BecauseWe update the end date planned of contract too
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
											$lines[$i]->fetch_optionals();
											$array_options = $lines[$i]->array_options;
										}

										$tva_tx = $lines[$i]->tva_tx;
										if (! empty($lines[$i]->vat_src_code) && ! preg_match('/\(/', $tva_tx)) $tva_tx .= ' ('.$lines[$i]->vat_src_code.')';

										// View third's localtaxes for NOW and do not use value from origin.
										$localtax1_tx = get_localtax($tva_tx, 1, $invoice_draft->thirdparty);
										$localtax2_tx = get_localtax($tva_tx, 2, $invoice_draft->thirdparty);

										//$price_invoice_template_line = $lines[$i]->subprice * GETPOST('frequency_multiple','int');
										$price_invoice_template_line = $lines[$i]->subprice;

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

										$tmpproduct->fetch($lines[$i]->fk_product);

										dol_syslog("--- Read frequency for product id=".$tmpproduct->id, LOG_DEBUG, 0);
										if ($tmpproduct->array_options['options_app_or_option'] == 'app')
										{
											$frequency = $tmpproduct->duration_value;
											$frequency_unit = $tmpproduct->duration_unit;
										}
									}
								}

								// Now we convert invoice into a template
								if (! $error)
								{
									//var_dump($invoice_draft->lines);
									//var_dump(dol_print_date($date_start, 'dayhour'));
									//exit;

									$frequency=1;
									$frequency_unit='m';
									$tmp=dol_getdate($date_start?$date_start:$now);
									$reyear=$tmp['year'];
									$remonth=$tmp['mon'];
									$reday=$tmp['mday'];
									$rehour=$tmp['hours'];
									$remin=$tmp['minutes'];
									$nb_gen_max=0;
									//print dol_print_date($date_start, 'dayhour');
									//var_dump($remonth);

									$invoice_rec = new FactureRec($db);

									$invoice_rec->titre = 'Template invoice for '.$contract->ref.' '.$contract->ref_customer;
									$invoice_rec->title = 'Template invoice for '.$contract->ref.' '.$contract->ref_customer;
									$invoice_rec->note_private = $contract->note_private;
									//$invoice_rec->note_public  = dol_concatdesc($contract->note_public, '__(Period)__ : __INVOICE_DATE_NEXT_INVOICE_BEFORE_GEN__ - __INVOICE_DATE_NEXT_INVOICE_AFTER_GEN__');
									$invoice_rec->note_public  = $contract->note_public;
									$invoice_rec->mode_reglement_id = $invoice_draft->mode_reglement_id;

									$invoice_rec->usenewprice = 0;

									$invoice_rec->frequency = $frequency;
									$invoice_rec->unit_frequency = $frequency_unit;
									$invoice_rec->nb_gen_max = $nb_gen_max;
									$invoice_rec->auto_validate = 0;

									$invoice_rec->fk_project = 0;

									$date_next_execution = dol_mktime($rehour, $remin, 0, $remonth, $reday, $reyear);
									$invoice_rec->date_when = $date_next_execution;

									// Get first contract linked to invoice used to generate template
									if ($invoice_draft->id > 0)
									{
										$srcObject = $invoice_draft;

										$srcObject->fetchObjectLinked();

										if (! empty($srcObject->linkedObjectsIds['contrat']))
										{
											$contractidid = reset($srcObject->linkedObjectsIds['contrat']);

											$invoice_rec->origin = 'contrat';
											$invoice_rec->origin_id = $contractidid;
											$invoice_rec->linked_objects[$invoice_draft->origin] = $invoice_draft->origin_id;
										}
									}

									$oldinvoice = new Facture($db);
									$oldinvoice->fetch($invoice_draft->id);

									$invoicerecid = $invoice_rec->create($user, $oldinvoice->id);
									if ($invoicerecid > 0)
									{
										$sql = 'UPDATE '.MAIN_DB_PREFIX.'facturedet_rec SET date_start_fill = 1, date_end_fill = 1 WHERE fk_facture = '.$invoice_rec->id;
										$result = $db->query($sql);
										if (! $error && $result < 0)
										{
											$error++;
											$this->errors[] = 'Error sql '.$db->lasterror();
										}

										$result=$oldinvoice->delete($user, 1);
										if (! $error && $result < 0)
										{
											$error++;
											if ($oldinvoice->error) $this->errors[] = $oldinvoice->error;
											else $this->errors[] = 'Error deleting invoice';
										}

										if (! $error)
										{
											$contractconvertedintemplateinvoice[$object->id]=$object->ref;
										}
									}
									else
									{
										$error++;
										if ($invoice_rec->error) $this->errors[] = $invoice_rec->error;
										else $this->errors[] = 'Error creating recurring invoice';
									}
								}
							}
						}
						else	// Third party has no payment mode defined, we suspend it.
						{
							$wemustsuspendinstance = true;
						}

						if ($wemustsuspendinstance)
						{
							//$conf->global->noapachereload = 1;       // Set a global variable that can be read later by trigger
						    $conf->global->noapachereload = 0;       // Set a global variable that can be read later by trigger
						    $comment = "Closed by batch doSuspendInstances('".$mode."') the ".dol_print_date($now, 'dayhourrfc').' (noapachereload='.$conf->global->noapachereload.')';
							$result = $object->closeAll($user, 0, $comment);			// This may execute trigger that make remote actions to suspend instance
							$conf->global->noapachereload = null;    // unset a global variable that can be read later by trigger
							if ($result < 0)
							{
								$error++;
								$this->error = $object->error;
								if (is_array($object->errors) && count($object->errors)) {
								    if (is_array($this->errors)) $this->errors = array_merge($this->errors, $object->errors);
								    else $this->errors = $object->errors;
								}
							}
							else
							{
								// Add a delay because the closeAll may have triggered a suspend remote action and we want to be sure the apache reload is complete
								sleep(1);

								$contractprocessed[$object->id]=$object->ref;

								// Send an email to warn customer of suspension
								if ($mode == 'test')
								{
									$labeltemplate = 'CustomerAccountSuspendedTrial';
								}
								if ($mode == 'paid')
								{
									$labeltemplate = 'CustomerAccountSuspended';
								}

								dol_syslog("Now we will send an email to customer id=".$object->thirdparty->id." with label ".$labeltemplate);

								// Send deployment email
								include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
								include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
								$formmail=new FormMail($this->db);

								// Define output language
								$outputlangs = $langs;
								$newlang = '';
								if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id','aZ09')) $newlang = GETPOST('lang_id','aZ09');
								if ($conf->global->MAIN_MULTILANGS && empty($newlang))	$newlang = $object->thirdparty->default_lang;
								if (! empty($newlang)) {
								    $outputlangs = new Translate("", $conf);
								    $outputlangs->setDefaultLang($newlang);
								    $outputlangs->loadLangs(array('main','bills','products'));
								}

								dol_syslog("GETPOST('lang_id','aZ09')=".GETPOST('lang_id','aZ09')." object->thirdparty->default_lang=".(is_object($object->thirdparty)?$object->thirdparty->default_lang:'object->thirdparty not defined')." newlang=".$newlang." outputlangs->defaultlang=".$outputlangs->defaultlang);

								$arraydefaultmessage=$formmail->getEMailTemplate($this->db, 'contract', $user, $outputlangs, 0, 1, $labeltemplate);

								$substitutionarray=getCommonSubstitutionArray($outputlangs, 0, null, $object);
								complete_substitutions_array($substitutionarray, $outputlangs, $object);

								$subject = make_substitutions($arraydefaultmessage->topic, $substitutionarray, $outputlangs);
								$msg     = make_substitutions($arraydefaultmessage->content, $substitutionarray, $outputlangs);
								$from = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;

								/*if (is_object($tmpobject) &&
								    ! empty($tmpobject->array_options['options_domain_registration_page'])
								    && $tmpobject->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME)
								{ */
								/* No required, $conf reset by complete_substitutions_array */

								$to = $object->thirdparty->email;

								$cmail = new CMailFile($subject, $to, $from, $msg, array(), array(), array(), '', '', 0, 1);
								$result = $cmail->sendfile();
								if (! $result || $cmail->error)
								{
									$erroremail .= ($erroremail ? ', ' : '').$cmail->error;
									$this->errors[] = $cmail->error;
									if (is_array($cmail->errors) && count($cmail->errors) > 0) $this->errors += $cmail->errors;
								}
							}
						}
					}
				}
    			$ifetchservice++;
    		}
    	}
    	else
    	{
    		$error++;
    		$this->error = $this->db->lasterror();
    	}

   		if (! $error)
   		{
   			$this->db->commit();
   			$this->output = $numofexpiredcontractlines.' expired contract lines found'."\n";
   			$this->output.= count($contractprocessed).' '.$mode.' running contract(s) with service end date before '.dol_print_date($datetotest, 'dayhourrfc').' suspended'.(count($contractprocessed)>0 ? ' : '.join(',', $contractprocessed) : '').' (search done on contracts of SellYourSaas customers only).'."\n";
   			$this->output.= count($contractconvertedintemplateinvoice).' '.$mode.' running contract(s) with service end date before '.dol_print_date($datetotest, 'dayhourrfc').' converted into template invoice'.(count($contractconvertedintemplateinvoice)>0 ? ' : '.join(',', $contractconvertedintemplateinvoice) : '');
   			if ($erroremail) $this->output.='. Got errors when sending some email : '.$erroremail;
   		}
   		else
   		{
   			$this->db->rollback();
   			$this->output = "Rollback after error\n";
   			$this->output.= $numofexpiredcontractlines.' expired contract lines found'."\n";
   			$this->output.= count($contractprocessed).' '.$mode.' running contract(s) with service end date before '.dol_print_date($datetotest, 'dayhourrfc').' to suspend'.(count($contractprocessed)>0 ? ' : '.join(',', $contractprocessed) : '').' (search done on contracts of SellYourSaas customers only).'."\n";
   			$this->output.= count($contractconvertedintemplateinvoice).' '.$mode.' running contract(s) with service end date before '.dol_print_date($datetotest, 'dayhourrfc').' to convert into template invoice'.(count($contractconvertedintemplateinvoice)>0 ? ' : '.join(',', $contractconvertedintemplateinvoice) : '');
   			if ($erroremail) $this->output.='. Got errors when sending some email : '.$erroremail;
   		}

    	return ($error ? 1: 0);
    }


    /**
     * Action executed by scheduler
     * Undeployed test instances if we are after planned end date (+ grace offset in SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT)
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doUndeployOldSuspendedTestInstances()
    {
    	global $conf, $langs;

    	$langs->load("agenda");

    	$savlog = $conf->global->SYSLOG_FILE;
    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doUndeployOldSuspendedTestInstances.log';

    	dol_syslog(__METHOD__, LOG_DEBUG);
    	$result = $this->doUndeployOldSuspendedInstances('test');

    	$conf->global->SYSLOG_FILE = $savlog;

    	return $result;
    }

    /**
     * Action executed by scheduler
     * Undeployed paid instances if we are after planned end date (+ grace offset in SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT)
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doUndeployOldSuspendedRealInstances()
    {
    	global $conf, $langs;

    	$langs->load("agenda");

    	$savlog = $conf->global->SYSLOG_FILE;
    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doUndeployOldSuspendedRealInstances.log';

    	dol_syslog(__METHOD__, LOG_DEBUG);
    	$result = $this->doUndeployOldSuspendedInstances('paid');

    	$conf->global->SYSLOG_FILE = $savlog;

    	return $result;
    }

    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     *
   	 * @param	string	$mode		'test' or 'paid'
     * @return	int					0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doUndeployOldSuspendedInstances($mode)
    {
    	global $conf, $langs, $user;

    	$langs->load("agenda");

    	$MAXPERCALL = 5;       // Undeploy can be long (1mn). So we limit to 5 per call

    	if ($mode != 'test' && $mode != 'paid')
    	{
    		$this->error = 'Function doUndeployOldSuspendedInstances called with bad value for parameter '.$mode;
    		return -1;
    	}

    	$error = 0;
    	$this->output = '';
    	$this->error='';

    	$delayindays = 9999999;
    	if ($mode == 'test') $delayindays = $conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT;
    	if ($mode == 'paid') $delayindays = $conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT;
		if ($delayindays <= 1)
		{
			$this->error='BadValueForDelayBeforeUndeploymentCheckSetup';
			return -1;
		}

    	dol_syslog(__METHOD__." we undeploy instances mode=".$mode." that are expired since more than ".$delayindays." days", LOG_DEBUG);

    	$now = dol_now();
    	$datetotest = dol_time_plus_duree($now, -1 * abs($delayindays), 'd');

    	$sql = 'SELECT c.rowid, c.ref_customer, s.client, cd.rowid as lid';
    	$sql.= ' FROM '.MAIN_DB_PREFIX.'contrat as c, '.MAIN_DB_PREFIX.'contratdet as cd, '.MAIN_DB_PREFIX.'contrat_extrafields as ce, ';
    	$sql.= ' '.MAIN_DB_PREFIX.'societe as s, '.MAIN_DB_PREFIX.'societe_extrafields as se';
    	$sql.= ' WHERE cd.fk_contrat = c.rowid AND ce.fk_object = c.rowid';
    	$sql.= " AND ce.deployment_status = 'done'";
    	$sql.= " AND cd.date_fin_validite < '".$this->db->idate($datetotest)."'";
    	$sql.= " AND cd.statut = 5";
    	$sql.= " AND s.rowid = c.fk_soc";
    	$sql.= " AND se.fk_object = s.rowid";
    	$sql.= " AND se.dolicloud = 'yesv2'";
    	$sql.= $this->db->order('s.client,c.rowid','ASC,ASC');
    	$sql.= $this->db->plimit(1000);	// To avoid too long answers. There is another limit on number of case really undeployed to MAXPERCALL later

    	$resql = $this->db->query($sql);
    	if ($resql)
    	{
    		$num = $this->db->num_rows($resql);

    		$contractprocessed = array();
    		$somethingdoneoncontract = 0;

    		$i=0;
    		while ($i < $num)
    		{
    			$obj = $this->db->fetch_object($resql);
    			if ($obj)
    			{
    				if (! empty($contractprocessed[$obj->rowid])) continue;

    				if ($somethingdoneoncontract >= $MAXPERCALL)
    				{
    				    dol_syslog("We reach the limit of ".$MAXPERCALL." contract processed, so we quit loop for this batch doUndeployOldSuspendedInstances to avoid a too long process.", LOG_WARNING);
    				    break;
    				}

    				// Test if this is a paid or not instance
    				$object = new Contrat($this->db);
    				$object->fetch($obj->rowid);

    				if ($object->id <= 0)
    				{
    					$error++;
    					$this->errors[] = 'Failed to load contract with id='.$obj->rowid;
    					continue;
    				}

    				$isAPayingContract = sellyoursaasIsPaidInstance($object);       // This make fetchObjectLinked and scan link on invoices or template invoices
    				if ($mode == 'test' && $isAPayingContract) continue;			// Discard if this is a paid instance when we are in test mode
    				if ($mode == 'paid' && ! $isAPayingContract) continue;			// Discard if this is a test instance when we are in paid mode

    				// Undeploy now
    				$this->db->begin();

    				$tmparray = sellyoursaasGetExpirationDate($object);
    				$expirationdate = $tmparray['expirationdate'];

    				$remotetouse = '';
    				if ($expirationdate && $expirationdate < $datetotest)
    				{
    				    $somethingdoneoncontract++;

    				    // Undeploy instance
    				    $remotetouse = 'undeploy';
    				    if ($mode == 'test') $remotetouse = 'undeployall';

    				    $conf->global->noapachereload = 1;       // Set a global variable that can be read later
    				    $comment = "Undeploy instance by doUndeployOldSuspendedInstances('".$mode."') the ".dol_print_date($now, 'dayhourrfc').' (noapachereload='.$conf->global->noapachereload.')';
    				    $result = $this->sellyoursaasRemoteAction($remotetouse, $object, 'admin', '', '', '0', $comment, 300);
    				    $conf->global->noapachereload = null;    // unset a global variable that can be read later
    				    if ($result <= 0)
    					{
    						$error++;
    						$this->error=$this->error;
    						$this->errors=$this->errors;
    					}
    					//$object->array_options['options_deployment_status'] = 'suspended';

    					$contractprocessed[$object->id]=$object->ref;	// To avoid to make action twice on same contract

        				// Finish undeploy

        				// Unactivate all lines
        				if (! $error)
        				{
        					dol_syslog("Unactivate all lines - doUndeployOldSuspendedInstances undeploy or undeployall");

        					$conf->global->noapachereload = 1;       // Set a global variable that can be read later by trigger
        					$comment = "Close after undeployment by doUndeployOldSuspendedInstances('".$mode."') the ".dol_print_date($now, 'dayhourrfc').' (noapachereload='.$conf->global->noapachereload.')';
        					$result = $object->closeAll($user, 1, $comment);   // Disable trigger to avoid any other action
        					$conf->global->noapachereload = null;    // unset a global variable that can be read later by trigger
        					if ($result <= 0)
        					{
        						$error++;
        						$this->error=$object->error;
        						$this->errors = array_merge((array) $this->errors, (array) $object->errors);
        					}
        				}

        				// End of undeployment is now OK / Complete
        				if (! $error)
        				{
        					$object->array_options['options_deployment_status'] = 'undeployed';
        					$object->array_options['options_undeployment_date'] = dol_now();
        					$object->array_options['options_undeployment_ip'] = getUserRemoteIP();

        					$result = $object->update($user);
        					if ($result < 0)
        					{
        						// We ignore errors. This should not happen in real life.
        						//setEventMessages($contract->error, $contract->errors, 'errors');
        					}
        					else
        					{
        						// Now we force disable of recurring invoices
        					    $object->fetchObjectLinked();

        					    if (is_array($object->linkedObjects['facturerec']))
        					    {
        					        foreach($object->linkedObjects['facturerec'] as $idtemplateinvoice => $templateinvoice)
        					        {
                                        // Disabled this template invoice
        					            $res = $templateinvoice->setValueFrom('suspended', 1);
        					            if ($res)
        					            {
        					               $res = $templateinvoice->setValueFrom('note_private', dol_concatdesc($templateinvoice->note_private, 'Disabled by doUndeployOldSuspendedInstances mode='.$mode.' the '.dol_print_date($now, 'dayhour')));
        					            }
        					        }
        					    }

        					    // Delete draft invoices linked to this thirdparty, after a successfull undeploy
        					    if (is_array($object->linkedObjects['facture']))
        					    {
            					    foreach ($object->linkedObjects['facture'] as $idinvoice => $invoicetodelete)
            					    {
            					        if ($invoicetodelete->statut == Facture::STATUS_DRAFT)
            					        {
            					            if (preg_match('/\(.*\)/', $invoicetodelete->ref))
            					            {
                    					        //$sql = "DELETE FROM ".MAIN_DB_PREFIX."facture WHERE fk_statut = ".Facture::STATUS_DRAFT." AND fk_soc = ".$object->fk_soc;
                    					        //$sql.= " AND rowid IN (".join(',', $object->linkedObjectsIds['facture']).")";
                    					        //var_dump($sql);
                					            $res = $invoicetodelete->delete($user);
                					            //var_dump($idinvoice.' '.$res);
            					            }
            					            else
            					            {
            					                dol_syslog("The draft invoice ".$invoicetodelete->ref." has not a ref that match '(...)' so we do not delete it.", LOG_WAR);
            					            }
            					        }
            					    }
        					    }
        					    //exit;
        					}
        				}
    				} else {
    				    dol_syslog("Record was qualified by select but not by test after fetch expirationdate=".$expirationdate." datetotest=".$datetotest, LOG_WARNING);
    				}

    				if (! $error) {
    				    $this->db->commit();

    				    if ($remotetouse && $mode == 'paid') {
        				    $contract = $object;
        				    $tmpcontract = $contract;

        				    if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED))
        				    {
        				        try {
        				            dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

        				            $arrayconfig=array();
        				            if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY))
        				            {
        				                $arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
        				            }

        				            $statsd = new DataDog\DogStatsd($arrayconfig);

                				    // Add flag for paying instance lost
                				    $ispaidinstance = sellyoursaasIsPaidInstance($contract);
                				    if ($ispaidinstance)
                				    {
                				    	$langs->load("sellyoursaas@sellyoursaas");

                				        dol_syslog("Send other metric sellyoursaas.payinginstancelost to datadog".(get_class($tmpcontract) == 'Contrat' ? ' contractid='.$tmpcontract->id.' contractref='.$tmpcontract->ref: ''));
                				        $arraytags=null;
                				        $statsd->increment('sellyoursaas.payinginstancelost', 1, $arraytags);

                				        global $dolibarr_main_url_root;
                				        $urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT,'/').'$/i','',trim($dolibarr_main_url_root));
                				        $urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
                				        //$urlwithroot=DOL_MAIN_URL_ROOT;					// This is to use same domain name than current

                				        $tmpcontract->fetch_thirdparty();
                				        $mythirdpartyaccount = $tmpcontract->thirdparty;

                				        $sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;
                				        if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
                				            && $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME)
                				        {
                				            $newnamekey = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
                				            if (! empty($conf->global->$newnamekey)) $sellyoursaasname = $conf->global->$newnamekey;
                				        }

                				        $titleofevent = dol_trunc($sellyoursaasname.' - '.gethostname().' - '.$langs->trans("PayingInstanceLost").': '.$tmpcontract->ref.' - '.$mythirdpartyaccount->name, 90);
                				        $messageofevent = ' - '.$langs->trans("IPAddress").' '.getUserRemoteIP()."\n";
                				        $messageofevent.= $langs->trans("PayingInstanceLost").': '.$tmpcontract->ref.' - '.$mythirdpartyaccount->name.' ['.$langs->trans("SeeOnBackoffice").']('.$urlwithouturlroot.'/societe/card.php?socid='.$mythirdpartyaccount->id.')'."\n";
                				        $messageofevent.= 'Lost after cron job made a remoteaction='.$remotetouse."\n";

                				        // See https://docs.datadoghq.com/api/?lang=python#post-an-event
                				        $statsd->event($titleofevent,
                				            array(
                				                'text'       =>  "%%% \n ".$titleofevent.$messageofevent." \n %%%",      // Markdown text
                				                'alert_type' => 'info',
                				                'source_type_name' => 'API',
                				                'host'       => gethostname()
                				            )
                				        );
                				    }
        				        }
        				        catch(Exception $e)
        				        {
        				            // Nothing
        				        }
        				    }
    				    }
    				} else {
    				    $this->db->rollback();
    				}
    			}
    			$i++;
    		}
    	}
    	else
    	{
    		$error++;
    		$this->error = $this->db->lasterror();
    	}

    	$this->output = count($contractprocessed).' contract(s), in mode '.$mode.', suspended, with a planned end date before '.dol_print_date($datetotest, 'dayrfc').', undeployed'.(count($contractprocessed)>0 ? ' : '.join(',', $contractprocessed) : '');

    	return ($error ? 1: 0);
    }




    /**
     * Make a remote action on a contract (deploy/undeploy/suspend/suspendmaintenance/unsuspend/rename/backup...).
     * This function is called on Master but remote action is done on remote agent.
     *
     * @param	string					$remoteaction	Remote action ('suspend/unsuspend/rename'=change apache virtual file, 'deploy/undeploy'=create/delete database, 'refresh'=update status of install.lock+authorized key + loop on each line and read remote data and update qty of metrics)
     * @param 	Contrat|ContratLigne	$object			Object contract or contract line
     * @param	string					$appusername	App login
     * @param	string					$email			Initial email
     * @param	string					$password		Initial password
     * @param	string					$forceaddevent	'1'=Force to add event. If '0', add of event is done only for remoteaction = 'deploy','deployall','deployoption','rename','suspend','unsuspend','undeploy'
     *													$forceaddevent is set by caller but is overwrote to on when we detect qty has changed.
     * @param	string					$comment		Comment
     * @param   int                     $timeout        Time out in seconds
     * @return	int										<0 if KO, >0 if OK
     */
    function sellyoursaasRemoteAction($remoteaction, $object, $appusername='admin', $email='', $password='', $forceaddevent='0', $comment='', $timeout = 90)
    {
    	global $conf, $langs, $user;

    	$langs->load("agenda");

    	$error = 0;
        $retarray = array();

    	$now = dol_now();

    	if (get_class($object) == 'Contrat')
    	{
    		$listoflines = $object->lines;
    	}
    	else
    	{
    		$listoflines = array($object);
    	}

    	dol_syslog("* sellyoursaasRemoteAction START (remoteaction=".$remoteaction." email=".$email." password=".$password.(get_class($object) == 'Contrat' ? ' contractid='.$object->id.' contractref='.$object->ref: '').")", LOG_DEBUG, 1);

    	include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
    	include_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';

		// Action 'refresh', 'recreateauthorizedkeys', 'deletelock', 'recreatelock' for contract, check install.lock file
    	if (in_array($remoteaction, array('refresh', 'recreateauthorizedkeys', 'deletelock', 'recreatelock')) && get_class($object) == 'Contrat')
    	{
    		// SFTP refresh
    		if (function_exists("ssh2_connect"))
    		{
    		    $server=$object->array_options['options_hostname_os'];
    		    dol_syslog("Try to ssh2_connect to ".$server);

    		    $connection = @ssh2_connect($server, 22);
    			if ($connection)
    			{
    				//print ">>".$object->array_options['options_username_os']." - ".$object->array_options['options_password_os']."<br>\n";exit;
    				if (! @ssh2_auth_password($connection, $object->array_options['options_username_os'], $object->array_options['options_password_os']))
    				{
    					dol_syslog("Could not authenticate with username ".$object->array_options['options_username_os'], LOG_WARNING);
    					$this->errors[] = "Could not authenticate with username ".$object->array_options['options_username_os']." and password ".preg_replace('/./', '*', $object->array_options['options_password_os']);
    					$error++;
    				}
    				else
    				{
    					if ($remoteaction == 'refresh')
    					{
	    					$sftp = ssh2_sftp($connection);
	    					if (! $sftp)
	    					{
	    						dol_syslog("Could not execute ssh2_sftp",LOG_ERR);
	    						$this->errors[]='Failed to connect to ssh2_sftp to '.$server;
	    						$error++;
	    					}

		    				if (! $error)
		    				{
		    					// Check if install.lock exists
		    					$dir = $object->array_options['options_database_db'];
		    					$fileinstalllock="ssh2.sftp://".intval($sftp).$object->array_options['options_hostname_os'].'/'.$object->array_options['options_username_os'].'/'.$dir.'/documents/install.lock';
		    					$fileinstalllock2=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$dir.'/documents/install.lock';
		    					$fstatlock=@ssh2_sftp_stat($sftp, $fileinstalllock2);
		    					$datelockfile=(empty($fstatlock['atime'])?'':$fstatlock['atime']);

		    					// Check if authorized_keys exists (created during os account creation, into skel dir)
		    					$fileauthorizedkeys="ssh2.sftp://".intval($sftp).$object->array_options['options_hostname_os'].'/'.$object->array_options['options_username_os'].'/'.$dir.'/documents/install.lock';
		    					$fileauthorizedkeys2=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/.ssh/authorized_keys';
		    					$fstatlock=@ssh2_sftp_stat($sftp, $fileauthorizedkeys2);
		    					$dateauthorizedkeysfile=(empty($fstatlock['atime'])?'':$fstatlock['atime']);
		    					//var_dump($datelockfile);
		    					//var_dump($fileauthorizedkeys2);

		    					$object->array_options['options_filelock'] = $datelockfile;
		    					$object->array_options['options_fileauthorizekey'] = $dateauthorizedkeysfile;
		    					$object->update($user);
		    				}
    					}

    					if ($remoteaction == 'recreateauthorizedkeys')
    					{
    						$sftp = ssh2_sftp($connection);
    						if (! $sftp)
    						{
    							dol_syslog("Could not execute ssh2_sftp",LOG_ERR);
    							$this->errors[]='Failed to connect to ssh2_sftp to '.$server;
    							$error++;
    						}

    						// Update ssl certificate
    						// Dir .ssh must have rwx------ permissions
    						// File authorized_keys must have rw------- permissions
    						$dircreated=0;
    						$result=ssh2_sftp_mkdir($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/.ssh');
    						if ($result) {
    							$dircreated=1;
    						}	// Created
    						else {
    							$dircreated=0;
    						}	// Creation fails or already exists

    						// Check if authorized_key exists
    						//$filecert="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_EXT_HOME.'/'.$object->username_web.'/.ssh/authorized_keys';
    						$filecert="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/.ssh/authorized_keys';  // With PHP 5.6.27+
    						$fstat=@ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/.ssh/authorized_keys');

    						// Create authorized_keys file
    						if (empty($fstat['atime']))		// Failed to connect or file does not exists
    						{
    							$stream = fopen($filecert, 'w');
    							if ($stream === false)
    							{
    								$error++;
    								$this->errors[] =$langs->transnoentitiesnoconv("ErrorConnectOkButFailedToCreateFile");
    							}
    							else
    							{
    								$publickeystodeploy = $conf->global->SELLYOURSAAS_PUBLIC_KEY;
    								// Add public keys
    								fwrite($stream,$publickeystodeploy);

    								fclose($stream);
    								// File authorized_keys must have rw------- permissions
                                    ssh2_sftp_chmod($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/.ssh/authorized_keys', 0600);
    								$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/.ssh/authorized_keys');
    							}
    						}
    						else
    						{
    							$error++;
    							$this->errors[] = $langs->transnoentitiesnoconv("ErrorFileAlreadyExists");
    						}

    						$object->array_options['options_fileauthorizekey']=(empty($fstat['atime'])?'':$fstat['atime']);

    						if (! empty($fstat['atime'])) $result = $object->update($user);
    					}

    					if ($remoteaction == 'deletelock')
    					{
    						$sftp = ssh2_sftp($connection);
    						if (! $sftp)
    						{
    							dol_syslog("Could not execute ssh2_sftp",LOG_ERR);
    							$this->errors[]='Failed to connect to ssh2_sftp to '.$server;
    							$error++;
    						}

    						// Check if install.lock exists
    						$dir = $object->array_options['options_database_db'];
    						$filetodelete=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$dir.'/documents/install.lock';
    						$result=ssh2_sftp_unlink($sftp, $filetodelete);

    						if (! $result)
    						{
    							$error++;
    							$this->errors[] = $langs->transnoentitiesnoconv("DeleteFails");
    						}
    						else
    						{
    							$object->array_options['options_filelock'] = '';
    						}
    						if ($result)
    						{
    							$result = $object->update($user, 1);
    						}
    					}

    					if ($remoteaction == 'recreatelock')
    					{
    						$sftp = ssh2_sftp($connection);
    						if (! $sftp)
    						{
    							dol_syslog("Could not execute ssh2_sftp",LOG_ERR);
    							$this->errors[]='Failed to connect to ssh2_sftp to '.$server;
    							$error++;
    						}

    						// Check if install.lock exists
    						$dir = $object->array_options['options_database_db'];
    						//$fileinstalllock="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$dir.'/documents/install.lock';
    						$fileinstalllock="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$dir.'/documents/install.lock';
    						$fstat=@ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$dir.'/documents/install.lock');
    						if (empty($fstat['atime']))
    						{
    							$stream = fopen($fileinstalllock, 'w');
    							//var_dump($stream);exit;
    							fwrite($stream,"// File to protect from install/upgrade.\n");
    							fclose($stream);
    							$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->array_options['options_username_os'].'/'.$dir.'/documents/install.lock');
    						}
    						else
    						{
    							$error++;
    							$this->errors[]=$langs->transnoentitiesnoconv("ErrorFileAlreadyExists");
    						}

    						$object->array_options['options_filelock']=(empty($fstat['atime'])?'':$fstat['atime']);

    						if (! empty($fstat['atime']))
    						{
    							$result = $object->update($user, 1);
    						}
    					}
    				}

    				if (function_exists('ssh2_disconnect'))
    				{
    				    //ssh2_disconnect($connection);     // Hang on some config
    				    $connection = null;
    				    unset($connection);
    				}
    				else
    				{
    				    $connection = null;
    				    unset($connection);
    				}
    			}
    			else {
    				$this->errors[]='Failed to connect to ssh2 to '.$server;
    				$error++;
    			}
    		}
    		else {
    			$this->errors[]='ssh2_connect not supported by this PHP';
    			$error++;
    		}
    	}

    	$ispaidinstance = 0;

    	// Loop on each line of contract ($tmpobject is a ContractLine)
    	// to set or not $doremoteaction
    	foreach($listoflines as $tmpobject)
    	{
    		if (empty($tmpobject))
    		{
    			dol_syslog("List of lines contains an empty ContratLine, we discard this line.", LOG_WARNING);
    			continue;
    		}

    		$producttmp = new Product($this->db);
    		$producttmp->fetch($tmpobject->fk_product);

    		// Is it a product linked to a package ?
    		dol_include_once('/sellyoursaas/class/packages.class.php');
    		$tmppackage = new Packages($this->db);
    		if (! empty($producttmp->array_options['options_package']))
    		{
    			$tmppackage->fetch($producttmp->array_options['options_package']);
    		}

    		// Note remote action 'undeployall' is used to undeploy test instances
    		// Note remote action 'undeploy' is used to undeploy paying instances
    		$doremoteaction = 0;
    		if (in_array($remoteaction, array('backup', 'deploy', 'deployall', 'rename', 'suspend', 'suspendmaintenance', 'unsuspend', 'undeploy', 'undeployall')) &&
    			($producttmp->array_options['options_app_or_option'] == 'app')) $doremoteaction = 1;
    		if (in_array($remoteaction, array('deploy','deployall','deployoption')) &&
    			($producttmp->array_options['options_app_or_option'] == 'option')) $doremoteaction = 1;

    		// remoteaction = 'deploy','deployall','deployoption','rename','suspend','suspendmaintenance','unsuspend','undeploy'
    		if ($doremoteaction)
    		{
    			dol_syslog("Enter into doremoteaction code, with contract id=".$tmpobject->id." app_or_option=".$producttmp->array_options['options_app_or_option']);
    			include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
    			$contract = new Contrat($this->db);
    			$contract->fetch($tmpobject->fk_contrat);
    			$contract->fetch_thirdparty();

    			// Set var to force to add an event at end of remote action.
    			$forceaddevent = 1;
    			if ($remoteaction == 'rename')
    			{
    			    $forceaddevent = 'Rename old name '.$object->oldcopy->ref_customer.' into '.$contract->ref_customer;
    			}

    			$ispaidinstance = sellyoursaasIsPaidInstance($contract);

    			$tmp=explode('.', $contract->ref_customer, 2);
    			$sldAndSubdomain=$tmp[0];
    			$domainname=$tmp[1];
    			if (! empty($contract->array_options['options_deployment_host']))
    			{
    			    $serverdeployment = $contract->array_options['options_deployment_host'];
    			}
    			else
    			{
                    $serverdeployment = $this->getRemoveServerDeploymentIp($domainname);
    			}
    			if (empty($serverdeployment))	// Failed to get remote ip
    			{
    				if (empty($this->error)) {
    					$this->error = 'Failed to get ip for deployment server';
    				}
    				dol_syslog($this->error.' domainname='.$domainname.' contract='.$contract->array_options['options_deployment_host'], LOG_ERR);
    				$error++;
    				break;
    			}

    			$urlforsellyoursaasaccount = getRootUrlForAccount($contract);
    			if (empty($urlforsellyoursaasaccount))	// Failed to get customer account url
    			{
    			    dol_syslog('Failed to get customer account url', LOG_ERR);
    			    $error++;
    			    break;
    			}

    			$tmpold=explode('.', $object->oldcopy->ref_customer, 2);
    			$sldAndSubdomainold=$tmpold[0];
    			$domainnameold=$tmpold[1];

   				$orgname = $contract->thirdparty->name;
   				$countryid=0;
   				$countrycode='';
   				$countrylabel='';
   				$countryidcodelabel='';
   				if ($contract->thirdparty->country_id > 0 && $contract->thirdparty->country_code && $contract->thirdparty->country)
   				{
   					$countryidcodelabel=$contract->thirdparty->country_id.':'.$contract->thirdparty->country_code.':'.$contract->thirdparty->country;
   				}

   				$targetdir            = $conf->global->DOLICLOUD_INSTANCES_PATH;
   				$archivedir           = $conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH;
   				if ($ispaidinstance)
   				{
   					$archivedir = $conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH;
   				}

    			$generatedunixlogin   =$contract->array_options['options_username_os'];
    			$generatedunixpassword=$contract->array_options['options_password_os'];
    			$generateddbname      =$contract->array_options['options_database_db'];
    			$generateddbport      =($contract->array_options['options_port_db']?$contract->array_options['options_port_db']:3306);
    			$generateddbusername  =$contract->array_options['options_username_db'];
    			$generateddbpassword  =$contract->array_options['options_password_db'];
    			$generateddbprefix    =($contract->array_options['options_prefix_db']?$contract->array_options['options_prefix_db']:'llx_');
    			$generatedunixhostname=$contract->array_options['options_hostname_os'];
    			$generateddbhostname  =$contract->array_options['options_hostname_db'];
    			$generateduniquekey   =getRandomPassword(true);

    			$customurl            =$contract->array_options['options_custom_url'];
    			$customvirtualhostline=$contract->array_options['options_custom_virtualhostline'];   // Set with value 'php_value date.timezone "'.$_POST["tz_string"].'"'; into file register_instance.php
    			$SSLON='On';
    			$CERTIFFORCUSTOMDOMAIN=$customurl;
    			if ($CERTIFFORCUSTOMDOMAIN)
    			{
    			    // Kept for backward compatibility
	    			if (preg_match('/on\.dolicloud\.com$/', $CERTIFFORCUSTOMDOMAIN))
	    			{
	    				$CERTIFFORCUSTOMDOMAIN='on.dolicloud.com';
	    			}
	    			else
	    			{
	    				// Check if SSL certificate for $customurl exists. If it does not exist, return an error to ask to upload certificate first.
	    				if (! file_exists($conf->sellyoursaas->dir_output.'/crt/'.$CERTIFFORCUSTOMDOMAIN.'.crt'))
	    				{
		    			    // TODO Return error to ask to upload a certificate first.
		    			    $CERTIFFORCUSTOMDOMAIN=getDomainFromURL($customurl, 2);
		    				$SSLON='Off';
		    			}
	    			}
    			}

				$savsalt = $conf->global->MAIN_SECURITY_SALT;
    			$savhashalgo = $conf->global->MAIN_SECURITY_HASH_ALGO;

    			$conf->global->MAIN_SECURITY_HASH_ALGO = empty($conf->global->SELLYOURSAAS_HASHALGOFORPASSWORD)?'':$conf->global->SELLYOURSAAS_HASHALGOFORPASSWORD;
    			dol_syslog("Using this MAIN_SECURITY_HASH_ALGO for __APPPASSWORDxxx__ variables : ".$conf->global->MAIN_SECURITY_HASH_ALGO);

    			$conf->global->MAIN_SECURITY_SALT = empty($conf->global->SELLYOURSAAS_SALTFORPASSWORDENCRYPTION)?'':$conf->global->SELLYOURSAAS_SALTFORPASSWORDENCRYPTION;
    			dol_syslog("Using this salt for __APPPASSWORDxxxSALTED__ variables : ".$conf->global->MAIN_SECURITY_SALT);
    			$password0salted = dol_hash($password);
    			$passwordmd5salted = dol_hash($password, 'md5');
    			$passwordsha256salted = dol_hash($password, 'sha256');
    			dol_syslog("passwordmd5salted=".$passwordmd5salted);

    			$conf->global->MAIN_SECURITY_SALT = '';
    			dol_syslog("Using empty salt for __APPPASSWORDxxx__ variables : ".$conf->global->MAIN_SECURITY_SALT);
    			$password0 = dol_hash($password);
    			$passwordmd5 = dol_hash($password, 'md5');
    			$passwordsha256 = dol_hash($password, 'sha256');
    			dol_syslog("passwordmd5=".$passwordmd5);

    			$conf->global->MAIN_SECURITY_SALT = $savsalt;
    			$conf->global->MAIN_SECURITY_HASH_ALGO = $savhashalgo;

    			// Replace __INSTANCEDIR__, __INSTALLHOURS__, __INSTALLMINUTES__, __OSUSERNAME__, __APPUNIQUEKEY__, __APPDOMAIN__, ...
    			$substitarray=array(
        			'__INSTANCEDIR__'=>$targetdir.'/'.$generatedunixlogin.'/'.$generateddbname,
        			'__INSTANCEDBPREFIX__'=>$generateddbprefix,
    				'__DOL_DATA_ROOT__'=>(empty($conf->global->SELLYOURSAAS_FORCE_DOL_DATA_ROOT) ? DOL_DATA_ROOT : $conf->global->SELLYOURSAAS_FORCE_DOL_DATA_ROOT),
        			'__INSTALLHOURS__'=>dol_print_date($now, '%H'),
        			'__INSTALLMINUTES__'=>dol_print_date($now, '%M'),
        			'__OSHOSTNAME__'=>$generatedunixhostname,
        			'__OSUSERNAME__'=>$generatedunixlogin,
        			'__OSPASSWORD__'=>$generatedunixpassword,
        			'__DBHOSTNAME__'=>$generateddbhostname,
        			'__DBNAME__'=>$generateddbname,
        			'__DBPORT__'=>$generateddbport,
        			'__DBUSER__'=>$generateddbusername,
        			'__DBPASSWORD__'=>$generateddbpassword,
        			'__PACKAGEREF__'=> $tmppackage->ref,
        			'__PACKAGENAME__'=> $tmppackage->label,
        			'__APPORGNAME__'=> $orgname,
        			'__APPCOUNTRYID__'=> $countryid,
        			'__APPCOUNTRYCODE__'=> $countrycode,
        			'__APPCOUNTRYLABEL__'=> $countrylabel,
        			'__APPCOUNTRYIDCODELABEL__'=> $countryidcodelabel,
        			'__APPEMAIL__'=>$email,
        			'__APPUSERNAME__'=>$appusername,
        			'__APPPASSWORD__'=>$password,
        			'__APPPASSWORD0__'=>$password0,
        			'__APPPASSWORDMD5__'=>$passwordmd5,
        			'__APPPASSWORDSHA256__'=>$passwordsha256,
        			'__APPPASSWORD0SALTED__'=>$password0salted,
        			'__APPPASSWORDMD5SALTED__'=>$passwordmd5salted,
        			'__APPPASSWORDSHA256SALTED__'=>$passwordsha256salted,
        			'__APPUNIQUEKEY__'=>$generateduniquekey,
        			'__APPDOMAIN__'=>$sldAndSubdomain.'.'.$domainname,
        			'__ALLOWOVERRIDE__'=>$tmppackage->allowoverride,
        			'__VIRTUALHOSTHEAD__'=>$customvirtualhostline,
    			    '__SELLYOURSAAS_LOGIN_FOR_SUPPORT__'=>$conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT,
    			);

    			// If a given timezone was set for contract/instance
    			if (! empty($contract->array_options['options_timezone'])) {
    				$substitarray['TZ=UTC'] = 'TZ='.$contract->array_options['options_timezone'];
    			}

    			$dirfortmpfiles = DOL_DATA_ROOT.'/sellyoursaas/temp';
    			dol_mkdir($dirfortmpfiles, '', '0775');
    			$tmppackage->srcconffile1 = $dirfortmpfiles.'/conf.php.'.$sldAndSubdomain.'.'.$domainname.'.tmp';
    			$tmppackage->srccronfile  = $dirfortmpfiles.'/cron.'.$sldAndSubdomain.'.'.$domainname.'.tmp';
    			$tmppackage->srccliafter  = $dirfortmpfiles.'/cliafter.'.$sldAndSubdomain.'.'.$domainname.'.tmp';

    			$conffile = make_substitutions($tmppackage->conffile1, $substitarray);
    			$cronfile = make_substitutions($tmppackage->crontoadd, $substitarray);
    			$cliafter = make_substitutions($tmppackage->cliafter, $substitarray);

    			$tmppackage->targetconffile1 = make_substitutions($tmppackage->targetconffile1, $substitarray);
    			$tmppackage->datafile1 = make_substitutions($tmppackage->datafile1, $substitarray);
    			$tmppackage->srcfile1 = make_substitutions($tmppackage->srcfile1, $substitarray);
    			$tmppackage->srcfile2 = make_substitutions($tmppackage->srcfile2, $substitarray);
    			$tmppackage->srcfile3 = make_substitutions($tmppackage->srcfile3, $substitarray);
    			$tmppackage->targetsrcfile1 = make_substitutions($tmppackage->targetsrcfile1, $substitarray);
    			$tmppackage->targetsrcfile2 = make_substitutions($tmppackage->targetsrcfile2, $substitarray);
    			$tmppackage->targetsrcfile3 = make_substitutions($tmppackage->targetsrcfile3, $substitarray);

    			dol_syslog("Create conf file ".$tmppackage->srcconffile1);
    			if ($tmppackage->srcconffile1 && $conffile)
    			{
        			dol_delete_file($tmppackage->srcconffile1, 0, 1, 0, null, false, 0);
        			$result = file_put_contents($tmppackage->srcconffile1, str_replace("\r", '', $conffile));
        			@chmod($tmppackage->srcconffile1, 0664);  // so user/group has "rw" ('admin' can delete if owner/group is 'admin' or 'www-data', 'root' can also read using nfs)
    			}
    			else
    			{
    			    dol_syslog("No conf file to create or no content");
    			}

    			dol_syslog("Create cron file ".$tmppackage->srccronfile);
    			if ($tmppackage->srccronfile && $cronfile)
    			{
        			dol_delete_file($tmppackage->srccronfile, 0, 1, 0, null, false, 0);
        			$result = file_put_contents($tmppackage->srccronfile, str_replace("\r", '', $cronfile)."\n");  // A cron file must have at least one new line before end of file
        			@chmod($tmppackage->srccronfile, 0664);  // so user/group has "rw" ('admin' can delete if owner/group is 'admin' or 'www-data', 'root' can also read using nfs)
    			}
    			else
    			{
    			    dol_syslog("No cron file to create or no content");
    			}

    			if ($tmppackage->srccliafter && $cliafter)
    			{
    			    dol_syslog("Create cli file ".$tmppackage->srccliafter);
    			    dol_delete_file($tmppackage->srccliafter, 0, 1, 0, null, false, 0);
        			$result = file_put_contents($tmppackage->srccliafter, str_replace("\r", '', $cliafter));
        			@chmod($tmppackage->srccliafter, 0664);  // so user/group has "rw" ('admin' can delete if owner/group is 'admin' or 'www-data', 'root' can also read using nfs)
    			}
    			else
    			{
    			    dol_syslog("No cli file to create or no content");
    			}

    			// Parameters for remote action
    			$commandurl = $generatedunixlogin.'&'.$generatedunixpassword.'&'.$sldAndSubdomain.'&'.$domainname;
    			$commandurl.= '&'.$generateddbname.'&'.$generateddbport.'&'.$generateddbusername.'&'.$generateddbpassword;
    			$commandurl.= '&'.str_replace(' ', '£', $tmppackage->srcconffile1);
    			$commandurl.= '&'.str_replace(' ', '£', $tmppackage->targetconffile1);
    			$commandurl.= '&'.str_replace(' ', '£', $tmppackage->datafile1);
    			$commandurl.= '&'.$tmppackage->srcfile1.'&'.$tmppackage->targetsrcfile1.'&'.$tmppackage->srcfile2.'&'.$tmppackage->targetsrcfile2.'&'.$tmppackage->srcfile3.'&'.$tmppackage->targetsrcfile3;
    			$commandurl.= '&'.$tmppackage->srccronfile.'&'.$tmppackage->srccliafter.'&'.$targetdir;
    			$commandurl.= '&'.$conf->global->SELLYOURSAAS_SUPERVISION_EMAIL;	// Param 22 in .sh
    			$commandurl.= '&'.$serverdeployment;
    			$commandurl.= '&'.$urlforsellyoursaasaccount;			            // Param 24 in .sh
    			$commandurl.= '&'.$sldAndSubdomainold;
    			$commandurl.= '&'.$domainnameold;
    			$commandurl.= '&'.$customurl;
				$commandurl.= '&'.$tmpobject->id;		// ID of line of contract
				$commandurl.= '&'.$conf->global->SELLYOURSAAS_NOREPLY_EMAIL;
				$commandurl.= '&'.$CERTIFFORCUSTOMDOMAIN;
				$commandurl.= '&'.$archivedir;
				$commandurl.= '&'.$SSLON;
				$commandurl.= '&'.(empty($conf->global->noapachereload)?'apachereload':'noapachereload');
				$commandurl.= '&'.str_replace(' ', '£', $tmppackage->allowoverride);
				$commandurl.= '&'.str_replace(' ', '£', $customvirtualhostline);
				$commandurl.= '&'.($ispaidinstance ? 1 : 0);
				$commandurl.= '&'.$conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT;
				//$outputfile = $conf->sellyoursaas->dir_temp.'/action-'.$remoteaction.'-'.dol_getmypid().'.out';


				$conf->global->MAIN_USE_RESPONSE_TIMEOUT = ($timeout >= 2 ? $timeout : 90);	// Timeout of call of external URL to make remote action

    			// Execute remote action
    			if (! $error)
    			{
    			    $urltoget='http://'.$serverdeployment.':8080/'.$remoteaction.'?'.urlencode($commandurl);
	    			include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
	    			$retarray = getURLContent($urltoget, 'GET', '', 0, array(), array('http', 'https'), 2);   // Timeout is defined before

	    			if ($retarray['curl_error_no'] != '' || $retarray['http_code'] != 200)
	    			{
	    				$error++;
	    				if ($retarray['curl_error_no'] != '') $this->errors[] = $retarray['curl_error_msg'];
	    				else $this->errors[] = $retarray['content'];
	    			}
    			}

    			if (! $error && in_array($remoteaction, array('deploy','deployall','deployoption')))
    			{
    				// Execute personalized SQL requests (sqlafter)
			    	if (! $error)
			    	{
			    		dol_syslog("Try to connect to customer instance database to execute personalized requests");

			    		//var_dump($generateddbhostname);	// fqn name dedicated to instance in dns
			    		//var_dump($serverdeployment);		// just ip of deployement server
			    		//$dbinstance = @getDoliDBInstance('mysqli', $generateddbhostname, $generateddbusername, $generateddbpassword, $generateddbname, $generateddbport);
			    		$dbinstance = @getDoliDBInstance('mysqli', $serverdeployment, $generateddbusername, $generateddbpassword, $generateddbname, $generateddbport);
			    		if (! $dbinstance || ! $dbinstance->connected)
			    		{
			    			$error++;
			    			$this->error = $dbinstance->error;
			    			$this->errors = $dbinstance->errors;
			    		}
			    		else
			    		{
			    			$substitarrayforsql = array();
			    			foreach($substitarray as $key => $val) {
			    				$substitarrayforsql[$key] = $dbinstance->escape($val);
			    			}
			    			dol_syslog("newsubstitarray=".join(',', $substitarrayforsql));

			    			$sqltoexecute = make_substitutions($tmppackage->sqlafter, $substitarrayforsql);

			    			$arrayofsql=explode(';', $sqltoexecute);
			    			foreach($arrayofsql as $sqltoexecuteline)
			    			{
			    				$sqltoexecuteline = trim($sqltoexecuteline);
			    				if ($sqltoexecuteline && (strpos($sqltoexecuteline, '--') === false || strpos($sqltoexecuteline, '--') > 0))
			    				{
			    					dol_syslog("Execute sql=".$sqltoexecuteline);
			    					$resql = $dbinstance->query($sqltoexecuteline);
			    				}
			    			}
			    		}
			    	}
    			}
    		}

    		// remoteaction = refresh => update the qty for this line if it is a line that is a metric
    		if ($remoteaction == 'refresh')
    		{
    			dol_syslog("Start refresh of nb of resources for a customer");

    			include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
    			dol_include_once('/sellyoursaas/class/packages.class.php');

    			$contract = new Contrat($this->db);
    			$contract->fetch($tmpobject->fk_contrat);

    			// Update resource count
    			if (! empty($producttmp->array_options['options_resource_formula']))
    			{
    				$targetdir = $conf->global->DOLICLOUD_INSTANCES_PATH;

    				$generatedunixlogin=$contract->array_options['options_username_os'];
    				$generatedunixpassword=$contract->array_options['options_password_os'];
    				$tmp=explode('.', $contract->ref_customer, 2);
    				$sldAndSubdomain=$tmp[0];
    				$domainname=$tmp[1];
    				$generateddbname      =$contract->array_options['options_database_db'];
    				$generateddbport      =($contract->array_options['options_port_db']?$contract->array_options['options_port_db']:3306);
    				$generateddbusername  =$contract->array_options['options_username_db'];
    				$generateddbpassword  =$contract->array_options['options_password_db'];
    				$generateddbprefix    =($contract->array_options['options_prefix_db']?$contract->array_options['options_prefix_db']:'llx_');
    				$generatedunixhostname=$contract->array_options['options_hostname_os'];
    				$generateddbhostname  =$contract->array_options['options_hostname_db'];
    				$generateduniquekey   =getRandomPassword(true);

    				$customurl            =$contract->array_options['options_custom_url'];
    				$customvirtualhostline=$contract->array_options['options_custom_virtualhostline'];
    				$SSLON='On';
    				$CERTIFFORCUSTOMDOMAIN=$customurl;
    				if ($CERTIFFORCUSTOMDOMAIN)
    				{
    				    // Kept for backward compatibility
    				    if (preg_match('/on\.dolicloud\.com$/', $CERTIFFORCUSTOMDOMAIN))
    				    {
    				        $CERTIFFORCUSTOMDOMAIN='on.dolicloud.com';
    				    }
    				    else
    				    {
    				        // Check if SSL certificate for $customurl exists. If it does not exist, return an error to ask to upload certificate first.
    				    	if (! file_exists($conf->sellyoursaas->dir_output.'/crt/'.$CERTIFFORCUSTOMDOMAIN.'.crt'))
    				        {
    				            include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
    				            $CERTIFFORCUSTOMDOMAIN=getDomainFromURL($customurl, 2);
    				            $SSLON='Off';

    				            // TODO Show an error or warning to ask to upload a certificate first.
    				        }
    				    }
    				}

    				// Is it a product linked to a package ?
    				$tmppackage = new Packages($this->db);
    				if (! empty($producttmp->array_options['options_package']))
    				{
    					$tmppackage->fetch($producttmp->array_options['options_package']);
    				}

    				$savsalt = $conf->global->MAIN_SECURITY_SALT;
    				$savhashalgo = $conf->global->MAIN_SECURITY_HASH_ALGO;

    				$conf->global->MAIN_SECURITY_HASH_ALGO = empty($conf->global->SELLYOURSAAS_HASHALGOFORPASSWORD)?'':$conf->global->SELLYOURSAAS_HASHALGOFORPASSWORD;
    				dol_syslog("Using this MAIN_SECURITY_HASH_ALGO for __APPPASSWORDxxx__ variables : ".$conf->global->MAIN_SECURITY_HASH_ALGO);

    				$conf->global->MAIN_SECURITY_SALT = empty($conf->global->SELLYOURSAAS_SALTFORPASSWORDENCRYPTION)?'':$conf->global->SELLYOURSAAS_SALTFORPASSWORDENCRYPTION;
    				dol_syslog("Using this salt for __APPPASSWORDxxxSALTED__ variables : ".$conf->global->MAIN_SECURITY_SALT);
    				$password0salted = dol_hash($password);
    				$passwordmd5salted = dol_hash($password, 'md5');
    				$passwordsha256salted = dol_hash($password, 'sha256');
    				dol_syslog("passwordmd5salted=".$passwordmd5salted);

    				$conf->global->MAIN_SECURITY_SALT = '';
    				dol_syslog("Using empty salt for __APPPASSWORDxxx__ variables : ".$conf->global->MAIN_SECURITY_SALT);
    				$password0 = dol_hash($password);
    				$passwordmd5 = dol_hash($password, 'md5');
    				$passwordsha256 = dol_hash($password, 'sha256');
    				dol_syslog("passwordmd5=".$passwordmd5);

    				$conf->global->MAIN_SECURITY_SALT = $savsalt;
    				$conf->global->MAIN_SECURITY_HASH_ALGO = $savhashalgo;

    				// Replace __INSTANCEDIR__, __INSTALLHOURS__, __INSTALLMINUTES__, __OSUSERNAME__, __APPUNIQUEKEY__, __APPDOMAIN__, ...
    				$substitarray=array(
        				'__INSTANCEDIR__'=>$targetdir.'/'.$generatedunixlogin.'/'.$generateddbname,
        				'__INSTANCEDBPREFIX__'=>$generateddbprefix,
        				'__DOL_DATA_ROOT__'=>DOL_DATA_ROOT,
        				'__INSTALLHOURS__'=>dol_print_date($now, '%H'),
        				'__INSTALLMINUTES__'=>dol_print_date($now, '%M'),
        				'__OSHOSTNAME__'=>$generatedunixhostname,
        				'__OSUSERNAME__'=>$generatedunixlogin,
        				'__OSPASSWORD__'=>$generatedunixpassword,
        				'__DBHOSTNAME__'=>$generateddbhostname,
        				'__DBNAME__'=>$generateddbname,
        				'__DBPORT__'=>$generateddbport,
        				'__DBUSER__'=>$generateddbusername,
        				'__DBPASSWORD__'=>$generateddbpassword,
        				'__PACKAGEREF__'=> $tmppackage->ref,
        				'__PACKAGENAME__'=> $tmppackage->label,
        				'__APPUSERNAME__'=>$appusername,
        				'__APPEMAIL__'=>$email,
        				'__APPPASSWORD__'=>$password,
        				'__APPPASSWORD0__'=>$password0,
        				'__APPPASSWORDMD5__'=>$passwordmd5,
        				'__APPPASSWORDSHA256__'=>$passwordsha256,
        				'__APPPASSWORD0SALTED__'=>$password0salted,
        				'__APPPASSWORDMD5SALTED__'=>$passwordmd5salted,
        				'__APPPASSWORDSHA256SALTED__'=>$passwordsha256salted,
        				'__APPUNIQUEKEY__'=>$generateduniquekey,
        				'__APPDOMAIN__'=>$sldAndSubdomain.'.'.$domainname,
        				'__ALLOWOVERRIDE__'=>'',
        				'__VIRTUALHOSTHEAD__'=>$customvirtualhostline,
        				'__SELLYOURSAAS_LOGIN_FOR_SUPPORT__'=>$conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT
    				);


					// Now execute the formula
    				$currentqty = $tmpobject->qty;
    				$newqty = null;

    				$tmparray=explode(':', $producttmp->array_options['options_resource_formula'], 2);
    				if ($tmparray[0] == 'SQL')
    				{
    					$sqlformula = make_substitutions($tmparray[1], $substitarray);

    					//$serverdeployment = $this->getRemoveServerDeploymentIp($domainname);
    					$serverdeployment = $contract->array_options['options_deployment_host'];

    					dol_syslog("Try to connect to remote instance database (at ".$generateddbhostname.") to execute formula calculation");

    					//var_dump($generateddbhostname);	// fqn name dedicated to instance in dns
    					//var_dump($serverdeployment);		// just ip of deployment server
    					//$dbinstance = @getDoliDBInstance('mysqli', $generateddbhostname, $generateddbusername, $generateddbpassword, $generateddbname, $generateddbport);
    					$dbinstance = @getDoliDBInstance('mysqli', $generateddbhostname, $generateddbusername, $generateddbpassword, $generateddbname, $generateddbport);
    					if (! $dbinstance || ! $dbinstance->connected)
    					{
    						$error++;
    						$this->error = $dbinstance->error.' ('.$generateddbusername.'@'.$generateddbhostname.'/'.$generateddbname.')';
    						$this->errors = $dbinstance->errors;
    					}
    					else
    					{
    						dol_syslog("Execute sql=".$sqlformula);
    						$resql = $dbinstance->query($sqlformula);
    						if ($resql)
    						{
    							$objsql = $dbinstance->fetch_object($resql);
    							if ($objsql)
    							{
    								$newqty = $objsql->nb;
    							}
    							else
    							{
    								$error++;
    								$this->error = 'SQL to get resource return nothing';
    								$this->errors[] = 'SQL to get resource return nothing';
    							}
    						}
    						else
    						{
    							$error++;
    							$this->error = $dbinstance->lasterror();
    							$this->errors[] = $dbinstance->lasterror();
    						}
    					}
    				}
    				elseif ($tmparray[0] == 'BASH')
    				{
    				    $bashformula = make_substitutions($tmparray[1], $substitarray);

    				    // SFTP refresh
    				    if (function_exists("ssh2_connect"))
    				    {
    				        $server=$object->array_options['options_hostname_os'];

    				        $connection = @ssh2_connect($server, 22);
    				        if ($connection)
    				        {
    				            dol_syslog("Get resource BASH ".$bashformula);

    				            $newqty = 0;

    				            $respass = @ssh2_auth_password($connection, $object->array_options['options_username_os'], $object->array_options['options_password_os']);
    				            if ($respass)
    				            {
	    				            $stream = @ssh2_exec($connection, $bashformula);
	    				            if ($stream)
	    				            {
	        				            $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
	        				            stream_set_blocking($errorStream, true);
	        				            stream_set_blocking($stream, true);

	        				            $resultstring = stream_get_contents($stream, 4096);
	        				            if ($resultstring) {
	        				            	$tmparray = explode(' ', $resultstring);
	        				            	$newqty = (int) $tmparray[1];
	        				            } else {
	        				            	$resultstring .= stream_get_contents($errorStream);
	        				            }
	    				            } else {
	    				            	dol_syslog("Get resource BASH failed to ssh2_exec");
	    				            }
    				            } else {
    				            	dol_syslog("Get resource BASH failed to ssh2_auth_password");
    				            }

    				            if (function_exists('ssh2_disconnect'))
    				            {
    				                ssh2_disconnect($connection);     // Hang on some config
    				                $connection = null;
    				                unset($connection);
    				            }
    				            else
    				            {
    				                $connection = null;
    				                unset($connection);
    				            }

    				            dol_syslog("newqty = ".$newqty." resultstring = ".$resultstring);
    				        }
    				        else
    				        {
    				            $error++;
    				            $this->error = 'ssh2_connect function not supported by your PHP';
    				        }
    				    }
    				}
    				else
    				{
    					$error++;
    					$this->error = 'Bad definition of formula to calculate resource for product '.$producttmp->ref;
    				}

    				if (! $error && ! is_null($newqty))
    				{
    				    if ($newqty != $currentqty)
    				    {
        					// tmpobject is contract line
        					$tmpobject->qty = $newqty;
        					$result = $tmpobject->update($user);
        					if ($result <= 0)
        					{
        						$error++;
        						$this->error = 'Failed to update the count for product '.$producttmp->ref;
        					}
        					else
        					{
        						$forceaddevent = 'Qty line '.$tmpobject->id.' updated '.$currentqty.' -> '.$newqty;

        						// Test if there is template invoice linkded
        						$contract->fetchObjectLinked();

        						if (is_array($contract->linkedObjects['facturerec']) && count($contract->linkedObjects['facturerec']) > 0)
        						{
        							//dol_sort_array($contract->linkedObjects['facture'], 'date');
        							$sometemplateinvoice=0;
        							$lasttemplateinvoice=null;
        							foreach($contract->linkedObjects['facturerec'] as $invoice)
        							{
        								//if ($invoice->suspended == FactureRec::STATUS_SUSPENDED) continue;	// Draft invoice are not invoice not paid
        								$sometemplateinvoice++;
        								$lasttemplateinvoice=$invoice;
        							}
        							if ($sometemplateinvoice > 1)
        							{
        								$error++;
        								$this->error = 'Contract '.$object->ref.' has too many template invoice ('.$sometemplateinvoice.') so we dont know which one to update';
        							}
        							elseif (is_object($lasttemplateinvoice))
        							{
        								$sqlsearchline = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'facturedet_rec WHERE fk_facture = '.$lasttemplateinvoice->id.' AND fk_product = '.$tmpobject->fk_product;
        								$resqlsearchline = $this->db->query($sqlsearchline);
        								if ($resqlsearchline)
        								{
        									$num_search_line = $this->db->num_rows($resqlsearchline);
        									if ($num_search_line > 1)
        									{
        										$error++;
        										$this->error = 'Contract '.$object->ref.' has a template invoice with id ('.$lasttemplateinvoice->id.') that has several lines for product id '.$tmpobject->fk_product.' so we don t know wich line to update qty';
        									}
        									else
        									{
    	    									$objsearchline = $this->db->fetch_object($resqlsearchline);
    	    									if ($objsearchline)	// If empty, it means, template invoice has no line corresponding to contract line
    	    									{
    	    										// Update qty
    	    										$invoicerecline = new FactureLigneRec($this->db);
    	    										$invoicerecline->fetch($objsearchline->rowid);

    	    										$tabprice = calcul_price_total($newqty, $invoicerecline->subprice, $invoicerecline->remise_percent, $invoicerecline->tva_tx, $invoicerecline->localtax1_tx, $invoicerecline->txlocaltax2, 0, 'HT', $invoicerecline->info_bits, $invoicerecline->product_type, $mysoc, array(), 100);

    	    										$invoicerecline->qty = $newqty;

    	    										$invoicerecline->total_ht  = $tabprice[0];
    	    										$invoicerecline->total_tva = $tabprice[1];
    	    										$invoicerecline->total_ttc = $tabprice[2];
    	    										$invoicerecline->total_localtax1 = $tabprice[9];
    	    										$invoicerecline->total_localtax2 = $tabprice[10];

    	    										$result = $invoicerecline->update($user);

    	    										$result = $lasttemplateinvoice->update_price();
    	    									}
        									}
        								}
        								else
        								{
        									$error++;
        									$this->error = $this->db->lasterror();
        								}
        							}
        						}
        					}
    					}
    					else
    					{
    					    dol_syslog("No change on qty. Still ".$currentqty);
    					}
    				}
    				else
    				{
    				    dol_syslog("Error Failed to get new value for metric", LOG_WARNING);
    				}

    				if (! $error)
    				{
   						$contract->array_options['options_latestresupdate_date']=dol_now();
    					$result = $contract->update($user);
    					if ($result <= 0)
    					{
    						$error++;
    						$this->error = 'Failed to update field options_latestresupdate_date on contract '.$contract->ref;
    					}
    				}
    			}
    		}
    	}

    	// Complete message if error
    	$recordanevent = 0;
    	$prefixlabel = '';
    	if ($forceaddevent && (get_class($object) == 'Contrat' || get_class($object) == 'ContratLigne'))
    	{
    	    $recordanevent = 1;
    	    if (! $error)
    	    {
    	       $prefixlabel = '';
    	    }
    	    elseif (! empty($retarray['http_code']))
    	    {
    	        $forceaddevent = dol_concatdesc("Error ".$retarray['http_code']." returned by the remote agent.", (is_numeric($forceaddevent)?'':$forceaddevent));
    	        $prefixlabel = 'ERROR ';
    	    }
    	    else
    	    {
    	        $prefixlabel = 'ERROR ';
    	    }
    	}

    	if ($recordanevent)
    	{
    	    $tmpcontract = $object;
    	    if (get_class($object) == 'ContratLigne')
    	    {
    	        $tmpcontract = new Contrat($this->db);
    	        $tmpcontract->fetch($object->fk_contrat);
    	    }

    	    $remoteip = getUserRemoteIP();

    	    // Create a new connection to record event in an other transaction
    	    global $dolibarr_main_db_type, $dolibarr_main_db_host, $dolibarr_main_db_user;
    	    global $dolibarr_main_db_pass, $dolibarr_main_db_name, $dolibarr_main_db_port;
    	    $dbtype = $dolibarr_main_db_type;
    	    $dbhost = $dolibarr_main_db_host;
    	    $dbuser = $dolibarr_main_db_user;
    	    $dbpass = $dolibarr_main_db_pass;
    	    $dbname = $dolibarr_main_db_name;
    	    $dbport = $dolibarr_main_db_port;
    	    $independantdb = getDoliDBInstance($dbtype, $dbhost, $dbuser, $dbpass, $dbname, $dbport);

    	    if ($independantdb->connected)
    	    {
        		// Create an event
        	    $actioncomm = new ActionComm($independantdb);
        		$actioncomm->type_code   = 'AC_OTH_AUTO';		// Type of event ('AC_OTH', 'AC_OTH_AUTO', 'AC_XXX'...)
        		$actioncomm->code        = 'AC_'.strtoupper($remoteaction);
        		$actioncomm->label       = $prefixlabel.'Remote action '.$remoteaction.(preg_match('/PROV/', $tmpcontract->ref) ? '' : ' on '.$tmpcontract->ref).' by '.($remoteip?$remoteip:'localhost');
        		$actioncomm->datep       = $now;
        		$actioncomm->datef       = $now;
        		$actioncomm->percentage  = -1;            // Not applicable
        		$actioncomm->socid       = $tmpcontract->socid;
        		$actioncomm->authorid    = $user->id;     // User saving action
        		$actioncomm->userownerid = $user->id;	  // Owner of action
        		$actioncomm->fk_element  = $tmpcontract->id;
        		$actioncomm->elementtype = 'contract';
        		$actioncomm->note_private = $comment;     // Description of event ($comment come from calling parameter of function sellyoursaasRemoteAction)
        		if (! is_numeric($forceaddevent))
        		{
        			$actioncomm->note_private = dol_concatdesc($actioncomm->note_private, $forceaddevent);
        		}
        		$ret=$actioncomm->create($user);       // User creating action

        		$independantdb->close();
    	    }
    	}


    	// Send to DataDog (metric + event)
    	if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED) && $remoteaction != 'backup')
    	{
    	    try {
        	    dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

        	    $arrayconfig=array();
        	    if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY))
        	    {
        	        $arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
        	    }

        	    $statsd = new DataDog\DogStatsd($arrayconfig);

        	    $arraytags=array('remoteaction'=> ($remoteaction?$remoteaction:'unknown'), 'result'=>($error ? 'ko' : 'ok'));

        	    $tmpcontract = $object;
        	    if (get_class($object) == 'ContratLigne')
        	    {
        	        $tmpcontract = new Contrat($this->db);
        	        $tmpcontract->fetch($object->fk_contrat);
        	    }

        	    dol_syslog("Send info to datadog".(get_class($tmpcontract) == 'Contrat' ? ' contractid='.$tmpcontract->id.' contractref='.$tmpcontract->ref: '')." remoteaction=".($remoteaction?$remoteaction:'unknown')." result=".($error ? 'ko' : 'ok'));

        	    $statsd->increment('sellyoursaas.remoteaction', 1, $arraytags);
    	    }
    	    catch(Exception $e)
    	    {

    	    }
    	}


    	dol_syslog("* sellyoursaasRemoteAction END (remoteaction=".$remoteaction." email=".$email." password=".$password." error=".$error." result=".($error ? 'ko' : 'ok')." retarray['http_code']=".$retarray['http_code'].(get_class($object) == 'Contrat' ? ' contractid='.$object->id.' contractref='.$object->ref: '').")", LOG_DEBUG, -1);

    	if ($error) return -1;
    	else return 1;
    }


    /**
     * Return IP of server to deploy to
     *
     * @param	string		$domainname		Domain name to select remote ip to deploy to (example: 'home.lan', 'dolicloud.com', ...)
     * @return	string						'' if KO, IP if OK
     */
    function getRemoveServerDeploymentIp($domainname)
    {
    	global $conf;

    	if (empty($domainname)) return '';

    	$error = 0;

    	$REMOTEIPTODEPLOYTO='';
    	$tmparray=explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
    	$found=0;
    	foreach($tmparray as $key => $val)
    	{
    	    $newval = preg_replace('/:.*$/', '', $val);
    		if ($newval == $domainname)
    		{
    			$found = $key+1;
    			break;
    		}
    	}
    	//print 'Found domain at position '.$found;
    	if (! $found)
    	{
    		$this->error="Failed to found position of server domain '".$domainname."' into SELLYOURSAAS_SUB_DOMAIN_NAMES=".$conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES;
    		$this->errors[]="Failed to found position of server domain '".$domainname."' into SELLYOURSAAS_SUB_DOMAIN_NAMES=".$conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES;
    		$error++;
    	}
    	else
    	{
    		$tmparray=explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_IP);
    		$REMOTEIPTODEPLOYTO=$tmparray[($found-1)];
	    	if (! $REMOTEIPTODEPLOYTO)
	    	{
	    		$this->error="Failed to found ip of server domain '".$domainname."' at position '".$found."' into SELLYOURSAAS_SUB_DOMAIN_IPS=".$conf->global->SELLYOURSAAS_SUB_DOMAIN_IP;
	    		$this->errors[]="Failed to found ip of server domain '".$domainname."' at position '".$found."' into SELLYOURSAAS_SUB_DOMAIN_IPS=".$conf->global->SELLYOURSAAS_SUB_DOMAIN_IP;
	    		$error++;
	    	}
    	}

    	if ($error) return '';
    	return $REMOTEIPTODEPLOYTO;
    }

}
