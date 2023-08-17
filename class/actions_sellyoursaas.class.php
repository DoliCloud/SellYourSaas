<?php
/* Copyright (C) 2013 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *	\file       htdocs/sellyoursaas/class/actions_sellyoursaas.class.php
 *	\ingroup    sellyoursaas
 *	\brief      File to control actions
 */
require_once DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php";
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
dol_include_once('sellyoursaas/lib/sellyoursaas.lib.php');
dol_include_once('sellyoursaas/class/deploymentserver.class.php');


/**
 *	Class to manage hooks for module SellYourSaas
 */
class ActionsSellyoursaas
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int		Priority of hook (50 is used if value is not defined)
	 */
	public $priority;


	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 *    Return URL formated
	 *
	 *    @param	array			$parameters		Array of parameters
	 *    @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 *    @param    string			$action      	'add', 'update', 'view'
	 *    @return   int         					<0 if KO,
	 *                              				=0 if OK but we want to process standard actions too,
	 *                              				>0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action)
	{
		global $db,$langs,$conf,$user;

		if ($object->element == 'societe') {
			// Dashboard
			if ($user->hasRight('sellyoursaas', 'read') && ! empty($object->array_options['options_dolicloud'])) {
				$url = '';
				if ($object->array_options['options_dolicloud'] == 'yesv2') {
					$urlmyaccount = getDolGlobalString('SELLYOURSAAS_ACCOUNT_URL');
					$sellyoursaasname = getDolGlobalString('SELLYOURSAAS_NAME');
					if (! empty($object->array_options['options_domain_registration_page'])
						&& $object->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
						$constforaltname = $object->array_options['options_domain_registration_page'];
						$newnamekey = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$constforaltname;
						if (! empty($conf->global->$newnamekey)) {
							$newurlkey = 'SELLYOURSAAS_ACCOUNT_URL-'.$constforaltname;
							if (! empty($conf->global->$newurlkey)) {
								$urlmyaccount = $conf->global->$newurlkey;
							} else {
								$urlmyaccount = preg_replace('/'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/', $object->array_options['options_domain_registration_page'], $urlmyaccount);
							}

							$sellyoursaasname = $conf->global->$newnamekey;
						}
					}

					$dol_login_hash=dol_hash(getDolGlobalString('SELLYOURSAAS_KEYFORHASH').$object->email.dol_print_date(dol_now(), 'dayrfc'), 5);	// hash to login is sha256 and is valid one day
					$url=$urlmyaccount.'?mode=logout_dashboard&action=login&token='.newToken().'&actionlogin=login&username='.urlencode(empty($object->email) ? '' : $object->email).'&password=&login_hash='.$dol_login_hash;
				}

				if ($url) {
					$this->resprints = (empty($parameters['notiret'])?' -':'').'<!-- Added by getNomUrl hook of SellYourSaas -->';
					$this->resprints .= '<a href="'.$url.'" target="_myaccount" alt="'.$sellyoursaasname.' '.$langs->trans("Dashboard").'"><span class="fa fa-desktop paddingleft"></span></a>';
				}
			}
		}

		if ($object->element == 'contrat') {
			$reg = array();
			if (preg_match('/title="([^"]+)"/', $parameters['getnomurl'], $reg)) {
				$object->fetch_optionals();
				$newtitle = $reg[1].dol_escape_htmltag('<!-- Added by getNomUrl hook for contrat of SellYourSaas --><br>', 1);
				$newtitle .= dol_escape_htmltag('<b>'.$langs->trans("DeploymentStatus").'</b> : '.(empty($object->array_options['options_deployment_status']) ? '' : $object->array_options['options_deployment_status']), 1);
				if (!empty($object->array_options['options_suspendmaintenance_message']) && preg_match('/^http/i', $object->array_options['options_suspendmaintenance_message'])) {
					$newtitle .= dol_escape_htmltag('<br><b>'.$langs->trans("Redirection").'</b> : '.(empty($object->array_options['options_suspendmaintenance_message']) ? '' : $object->array_options['options_suspendmaintenance_message']), 1);
				}
				$this->resprints = preg_replace('/title="([^"]+)"/', 'title="'.$newtitle.'"', $parameters['getnomurl']);

				if (!empty($object->array_options['options_spammer'])) {
					$this->resprints .= img_picto($langs->trans("EvilInstance"), 'fa-book-dead', 'class="paddingleft"');
				}

				return 1;
			}
		}

		return 0;
	}

	/**
	 *    Return ref customer formated
	 *
	 *    @param	array			$parameters		Array of parameters
	 *    @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 *    @param    string			$action      	'add', 'update', 'view'
	 *    @return   int         					<0 if KO,
	 *                              				=0 if OK but we want to process standard actions too,
	 *                              				>0 if OK and we want to replace standard actions.
	 */
	public function getFormatedCustomerRef($parameters, &$object, &$action)
	{
		global $conf, $langs;

		if (! empty($parameters['objref'])) {
			$isanurlofasellyoursaasinstance=false;
			if (!getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION')) {
				$tmparray=explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
				foreach ($tmparray as $tmp) {
					$newtmp = preg_replace('/:.*$/', '', $tmp);
					if (preg_match('/'.preg_quote('.'.$newtmp, '/').'$/', $parameters['objref'])) {
						$isanurlofasellyoursaasinstance=true;
					}
				}
			} else {
				$staticdeploymentserver = new Deploymentserver($this->db);
				$tmparray = $staticdeploymentserver->fetchAllDomains();
				foreach ($tmparray as $tmp) {
					$newtmp = preg_replace('/:.*$/', '', $tmp);
					if (preg_match('/'.preg_quote('.'.$newtmp, '/').'$/', $parameters['objref'])) {
						$isanurlofasellyoursaasinstance=true;
					}
				}
			}

			if ($isanurlofasellyoursaasinstance) {
				$objref = $parameters['objref'];
				$url = 'https://'.$parameters['objref'];

				$this->results['objref'] = $objref.' <a href="'.$url.'" target="_blank">'.img_picto($url, 'object_globe').'</a>';

				// Add also link to custom url
				if (! empty($object->array_options['options_custom_url'])) {
					$objref = $object->array_options['options_custom_url'];
					$url = 'https://'.$object->array_options['options_custom_url'];
					$this->results['objref'] .= ' <a href="'.$url.'" target="_blank" class="opacitymedium">'.img_picto($url, 'object_globe').'</a>';
				}

				if ($parameters['currentcontext'] == 'contractcard') {
					/*if (! empty($object->array_options['options_cookieregister_previous_instance']))
					{
						$this->results['objref'] .= ' &nbsp; <a href="/aa">'.$langs->trans("SeeChain").'</a>';
					}*/
					if (!empty($object->array_options['options_spammer'])) {
						$this->results['objref'] .= ' '.img_picto($langs->trans("EvilInstance"), 'fa-book-dead', 'class="paddingleft"');
					}

					if (!empty($object->array_options['options_spammer']) && $object->array_options['options_deployment_status'] == 'done') {
						$this->results['objref'] .= ' '.img_warning($langs->trans('ActiveInstanceOfASpammer'));
					}
				}

				return 1;
			}
		}

		return 0;
	}

	/**
	 *    Execute action
	 *
	 *    @param	array	$parameters				Array of parameters
	 *    @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 *    @param    string	$action      			'add', 'update', 'view'
	 *    @return   int         					<0 if KO,
	 *                              				=0 if OK but we want to process standard actions too,
	 *                              				>0 if OK and we want to replace standard actions.
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action)
	{
		global $langs,$conf,$user;

		dol_syslog(get_class($this).'::addMoreActionsButtons action='.$action);
		$langs->load("sellyoursaas@sellyoursaas");

		if (in_array($parameters['currentcontext'], array('contractcard'))				// do something only for the context 'contractcard'
			&& ! empty($object->array_options['options_deployment_status'])) {
			if ($user->hasRight('sellyoursaas', 'write')) {
				if (in_array($object->array_options['options_deployment_status'], array('processing', 'undeployed'))) {
					if (!getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION')) {
						$alt = $langs->trans("SellYourSaasSubDomains").' '.$conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES;
						$alt.= '<br>'.$langs->trans("SellYourSaasSubDomainsIP").' '.$conf->global->SELLYOURSAAS_SUB_DOMAIN_IP;
					} else {
						$listsubdomainname = array();
						$listsubdomainip = array();
						$sql = "SELECT ref, ipaddress";
						$sql .= " FROM ".MAIN_DB_PREFIX."sellyoursaas_deploymentserver";
						$sql .= " WHERE entity = '".$this->db->escape($conf->entity)."'";
						$resql = $this->db->query($sql);
						if ($resql) {
							$num = $this->db->num_rows($resql);
							while ($i < $num) {
								$obj = $this->db->fetch_object($resql);
								$listsubdomainname[] = $obj->ref;
								$listsubdomainip[] = $obj->ipaddress;
								$i++;
							}
						} else {
							$this->error = $this->db->lasterror();
							dol_syslog(__METHOD__.' '.join(',', $this->errors), LOG_ERR);
							return -1;
						}
						$listsubdomainip = implode(',', $listsubdomainip);
						$listsubdomainname = implode(',', $listsubdomainname);
						$alt = $langs->trans("SellYourSaasSubDomains").' '.$listsubdomainname;
						$alt.= '<br>'.$langs->trans("SellYourSaasSubDomainsIP").' '.$listsubdomainip;
					}

					print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deploy&token='.urlencode(newToken()).'" title="'.dol_escape_htmltag($alt).'">' . $langs->trans('Redeploy') . '</a>';
				} else {
					print '<a class="butActionRefused" href="#" title="'.$langs->trans("ContractMustHaveStatusProcessingOrUndeployed").'">' . $langs->trans('Redeploy') . '</a>';
				}

				if (in_array($object->array_options['options_deployment_status'], array('done'))) {
					print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=refresh&token='.urlencode(newToken()).'">' . $langs->trans('RefreshRemoteData') . '</a>';

					if (empty($object->array_options['options_fileauthorizekey'])) {
						print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=recreateauthorizedkeys&token='.urlencode(newToken()).'">' . $langs->trans('RecreateAuthorizedKey') . '</a>';
					}

					/*if (empty($object->array_options['options_filelock']))
					{
						print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=recreatelock&token='.newToken().'">' . $langs->trans('RecreateLock') . '</a>';
					}
					else
					{
						print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deletelock&token='.newToken().'">' . $langs->trans('SellYourSaasRemoveLock') . '</a>';
					}*/

					/*if (empty($object->array_options['options_fileinstallmoduleslock']))
					 {
					 print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=recreateinstallmoduleslock&token='.newToken().'">' . $langs->trans('RecreateInstallModulesLock') . '</a>';
					 }
					 else
					 {
					 print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deleteinstallmoduleslock&token='.newToken().'">' . $langs->trans('SellYourSaasRemoveInstallModulesLock') . '</a>';
					 }*/
				} else {
					print '<a class="butActionRefused" href="#" title="'.$langs->trans("ContractMustHaveStatusDone").'">' . $langs->trans('RefreshRemoteData') . '</a>';
				}

				if (in_array($object->array_options['options_deployment_status'], array('done'))) {
					if (empty($object->array_options['options_suspendmaintenance_message'])) {
						print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=suspendmaintenancetoconfirm&token='.urlencode(newToken()).'">' . $langs->trans('Maintenance') . '</a>';
					} else {
						print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=unsuspend&token='.urlencode(newToken()).'">' . $langs->trans('StopMaintenance') . '</a>';
					}
				}

				if (in_array($object->array_options['options_deployment_status'], array('done'))) {
					print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=undeploy&token='.urlencode(newToken()).'">' . $langs->trans('Undeploy') . '</a>';
				} else {
					print '<a class="butActionRefused" href="#" title="'.$langs->trans("ContractMustHaveStatusDone").'">' . $langs->trans('Undeploy') . '</a>';
				}

				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=changecustomer&token='.urlencode(newToken()).'" title="'.$langs->trans("ChangeCustomer").'">' . $langs->trans('ChangeCustomer') . '</a>';
			}
		}

		return 0;
	}



	/**
	 *    Execute action
	 *
	 *    @param	array			$parameters		Array of parameters
	 *    @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 *    @param    string			$action      	'add', 'update', 'view'
	 *    @return   int         					<0 if KO,
	 *                              				=0 if OK but we want to process standard actions too,
	 *                              				>0 if OK and we want to replace standard actions.
	 */
	public function doActions($parameters, &$object, &$action)
	{
		global $db,$langs,$conf,$user;

		$error = 0;

		dol_syslog(get_class($this).'::doActions action='.$action);
		$langs->load("sellyoursaas@sellyoursaas");

		/*
		if (is_object($object) && (get_class($object) == 'Contrat') && is_object($object->thirdparty))
		{
			$object->email = $object->thirdparty->email;
		}*/


		if (in_array($parameters['currentcontext'], array('contractlist'))) {
			global $fieldstosearchall;

			$fieldstosearchall['s.email']="ThirdPartyEmail";
		}

		if (in_array($parameters['currentcontext'], array('contractcard'))) {
			if ($action == 'deploy' || $action == 'deployall') {
				$db->begin();

				// SAME CODE THAN INTO MYACCOUNT INDEX.PHP

				// Disable template invoice
				$object->fetchObjectLinked();

				$foundtemplate=0;
				$freqlabel = array('d'=>$langs->trans('Day'), 'm'=>$langs->trans('Month'), 'y'=>$langs->trans('Year'));
				if (is_array($object->linkedObjects['facturerec']) && count($object->linkedObjects['facturerec']) > 0) {
					usort($object->linkedObjects['facturerec'], "cmp");

					//var_dump($object->linkedObjects['facture']);
					//dol_sort_array($object->linkedObjects['facture'], 'date');
					foreach ($object->linkedObjects['facturerec'] as $idinvoice => $invoice) {
						if ($invoice->suspended == FactureRec::STATUS_SUSPENDED) {
							$result = $invoice->setStatut(FactureRec::STATUS_NOTSUSPENDED);
							if ($result <= 0) {
								$error++;
								$this->error=$invoice->error;
								$this->errors=$invoice->errors;
								setEventMessages($this->error, $this->errors, 'errors');
							}
						}
					}
				}

				if (! $error) {
					dol_include_once('sellyoursaas/class/sellyoursaasutils.class.php');
					$sellyoursaasutils = new SellYourSaasUtils($db);
					$result = $sellyoursaasutils->sellyoursaasRemoteAction('deployall', $object, 'admin', $object->thirdparty->email, $object->array_options['options_deployment_init_adminpass'], '0', 'Deploy from contract card', 300);
					if ($result <= 0) {
						$error++;
						$this->error=$sellyoursaasutils->error;
						$this->errors=$sellyoursaasutils->errors;
						setEventMessages($this->error, $this->errors, 'errors');
					}
				}

				// Finish deployall

				$comment = 'Activation after click on redeploy from contract card on '.dol_print_date(dol_now(), 'dayhourrfc');

				// Activate all lines
				if (! $error) {
					dol_syslog("Activate all lines - doActions deploy");

					$object->context['deployallwasjustdone']=1;		// Add a key so trigger into activateAll will know we have just made a "deployall"

					$result = $object->activateAll($user, dol_now(), 1, $comment);
					if ($result <= 0) {
						$error++;
						$this->error=$object->error;
						$this->errors=$object->errors;
						setEventMessages($this->error, $this->errors, 'errors');
					}
				}

				// End of deployment is now OK / Complete
				if (! $error) {
					$object->array_options['options_deployment_status'] = 'done';
					$object->array_options['options_deployment_date_end'] = dol_now();
					$object->array_options['options_undeployment_date'] = '';
					$object->array_options['options_undeployment_ip'] = '';

					$result = $object->update($user);
					if ($result < 0) {
						// We ignore errors. This should not happen in real life.
						//setEventMessages($contract->error, $contract->errors, 'errors');
					} else {
						setEventMessages($langs->trans("InstanceWasDeployed"), null, 'mesgs');
						setEventMessages($langs->trans("NoEmailSentToInformCustomer"), null, 'mesgs');
					}
				}

				if (! $error) {
					$db->commit();
				} else {
					$db->rollback();
				}

				$urlto=preg_replace('/action=[a-z_]+/', '', $_SERVER['REQUEST_URI']);
				$urlto=preg_replace('/&confirm=yes/', '', $urlto);
				$urlto=preg_replace('/&token=/', '&tokendisabled=', $urlto);
				if ($urlto) {
					dol_syslog("Redirect to page urlto=".$urlto." to avoid to do action twice if we do back");
					header("Location: ".$urlto);
					exit;
				}
			}

			if ($action == 'confirm_undeploy') {
				$db->begin();

				// SAME CODE THAN INTO MYACCOUNT INDEX.PHP

				// Disable template invoice
				$object->fetchObjectLinked();

				$foundtemplate=0;
				$freqlabel = array('d'=>$langs->trans('Day'), 'm'=>$langs->trans('Month'), 'y'=>$langs->trans('Year'));
				if (is_array($object->linkedObjects['facturerec']) && count($object->linkedObjects['facturerec']) > 0) {
					usort($object->linkedObjects['facturerec'], "cmp");

					//var_dump($object->linkedObjects['facture']);
					//dol_sort_array($object->linkedObjects['facture'], 'date');
					foreach ($object->linkedObjects['facturerec'] as $idinvoice => $invoice) {
						if ($invoice->suspended == FactureRec::STATUS_NOTSUSPENDED) {
							$result = $invoice->setStatut(FactureRec::STATUS_SUSPENDED);
							if ($result <= 0) {
								$error++;
								$this->error=$invoice->error;
								$this->errors=$invoice->errors;
								setEventMessages($this->error, $this->errors, 'errors');
							}
						}
					}
				}

				if (! $error) {
					dol_include_once('sellyoursaas/class/sellyoursaasutils.class.php');
					$sellyoursaasutils = new SellYourSaasUtils($db);
					$result = $sellyoursaasutils->sellyoursaasRemoteAction('undeploy', $object, 'admin', '', '', '0', 'Undeployed from contract card', 300);
					if ($result <= 0) {
						$error++;
						$this->error=$sellyoursaasutils->error;
						$this->errors=$sellyoursaasutils->errors;
						setEventMessages($this->error, $this->errors, 'errors');
					}
				}

				// Finish deployall

				$comment = 'Close after click on undeploy from contract card';

				// Unactivate all lines
				if (! $error) {
					dol_syslog("Unactivate all lines - doActions undeploy");

					$result = $object->closeAll($user, 1, $comment);
					if ($result <= 0) {
						$error++;
						$this->error=$object->error;
						$this->errors=$object->errors;
						setEventMessages($this->error, $this->errors, 'errors');
					}
				}

				// End of undeployment is now OK / Complete
				if (! $error) {
					$object->array_options['options_deployment_status'] = 'undeployed';
					$object->array_options['options_undeployment_date'] = dol_now();
					$object->array_options['options_undeployment_ip'] = $_SERVER['REMOTE_ADDR'];

					$result = $object->update($user);
					if ($result < 0) {
						// We ignore errors. This should not happen in real life.
						//setEventMessages($contract->error, $contract->errors, 'errors');
					} else {
						setEventMessages($langs->trans("InstanceWasUndeployed"), null, 'mesgs');
						//setEventMessages($langs->trans("InstanceWasUndeployedToConfirm"), null, 'mesgs');
					}
				}

				if (! $error) {
					$db->commit();
				} else {
					$db->rollback();
				}

				$urlto=preg_replace('/action=[a-z_]+/', '', $_SERVER['REQUEST_URI']);
				$urlto=preg_replace('/&confirm=yes/', '', $urlto);
				$urlto=preg_replace('/&token=/', '&tokendisabled=', $urlto);
				if ($urlto) {
					dol_syslog("Redirect to page urlto=".$urlto." to avoid to do action twice if we do back");
					header("Location: ".$urlto);
					exit;
				}
			}

			if ($action == 'confirm_changecustomer') {
				$db->begin();
				// $object is a contract

				$newid = GETPOST('socid', 'int');

				if ($newid != $object->thirdparty->id) {
					$object->oldcopy = dol_clone($object);

					$object->fk_soc = $newid;
					$object->socid = $newid;

					if (! $error) {
						$result = $object->update($user, 1);
						if ($result < 0) {
							$this->error = $object->error;
							$this->errors = $object->errors;
						}
					}

					if (! $error) {
						$object->fetchObjectLinked();

						if (is_array($object->linkedObjectsIds['facturerec'])) {
							foreach ($object->linkedObjectsIds['facturerec'] as $key => $val) {
								$tmpfacturerec = new FactureRec($this->db);
								$result = $tmpfacturerec->fetch($val);
								if ($result > 0) {
									$tmpfacturerec->oldcopy = dol_clone($tmpfacturerec);
									$tmpfacturerec->fk_soc = $newid;
									$tmpfacturerec->socid = $newid;

									$result = $tmpfacturerec->update($user, 1);
									if ($result < 0) {
										$this->error = $tmpfacturerec->error;
										$this->errors = $tmpfacturerec->errors;
									}
								}
							}
						}
					}
				}

				if (! $error) {
					setEventMessages($langs->trans("ThirdPartyModified"), null, 'mesgs');
					$db->commit();
				} else {
					$db->rollback();
				}

				$urlto=preg_replace('/action=[a-z_]+/', '', $_SERVER['REQUEST_URI']);
				$urlto=preg_replace('/&confirm=yes/', '', $urlto);
				$urlto=preg_replace('/&token=/', '&tokendisabled=', $urlto);
				if ($urlto) {
					dol_syslog("Redirect to page urlto=".$urlto." to avoid to do action twice if we do back");
					header("Location: ".$urlto);
					exit;
				}
			}

			if (in_array($action, array('refresh', 'refreshmetrics', 'refreshfilesonly', 'recreateauthorizedkeys', 'deletelock', 'recreatelock', 'unsuspend', 'suspendmaintenance'))) {
				dol_include_once('sellyoursaas/class/sellyoursaasutils.class.php');
				$sellyoursaasutils = new SellYourSaasUtils($db);

				$comment = 'Executed by doActions with action = '.$action;
				$result = $sellyoursaasutils->sellyoursaasRemoteAction($action, $object, 'admin', '', '', '0', $comment);
				if ($result <= 0) {
					$error++;
					$this->error=$sellyoursaasutils->error;
					$this->errors=$sellyoursaasutils->errors;
					//setEventMessages($this->error, $this->errors, 'errors'); // We already return errors with this->errors, no need to seEventMessages()
				} else {
					if ($action == 'refresh') setEventMessages($langs->trans("ResourceComputed"), null, 'mesgs');
					if ($action == 'refreshmetrics') setEventMessages($langs->trans("ResourceComputed"), null, 'mesgs');
					if ($action == 'refreshfilesonly') setEventMessages($langs->trans("ResourceComputed"), null, 'mesgs');
					if ($action == 'recreateauthorizedkeys') setEventMessages($langs->trans("FileCreated"), null, 'mesgs');
					if ($action == 'recreatelock') setEventMessages($langs->trans("FileCreated"), null, 'mesgs');
					if ($action == 'deletelock') setEventMessages($langs->trans("FilesDeleted"), null, 'mesgs');
				}
			}

			$suspendmaintenancemessage = GETPOST('suspendmaintenancemessage', 'nohtml');

			// End of deployment is now OK / Complete
			if (! $error && in_array($action, array('unsuspend', 'suspendmaintenance'))) {
				$object->array_options['options_suspendmaintenance_message'] = ($action == 'suspendmaintenance' ? ($suspendmaintenancemessage ? $suspendmaintenancemessage : 'nomessage') : '');

				$result = $object->update($user);
				if ($result < 0) {
					// We ignore errors. This should not happen in real life.
					//setEventMessages($contract->error, $contract->errors, 'errors');
				} else {
					if ($action == 'suspendmaintenance') {
						setEventMessages($langs->trans('InstanceInMaintenanceMode', $conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT), null, 'warnings');
					} else {
						setEventMessages('InstanceUnsuspended', null, 'mesgs');
					}
				}
			}
		}

		// Action when we click on "Pay all pending invoices" on a credit card line
		if (in_array($parameters['currentcontext'], array('thirdpartybancard')) && $action == 'sellyoursaastakepayment' && GETPOST('companymodeid', 'int') > 0) {
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

			dol_include_once('sellyoursaas/class/sellyoursaasutils.class.php');
			$sellyoursaasutils = new SellYourSaasUtils($db);
			//var_dump($service);var_dump($servicestatusstripe);

			include_once DOL_DOCUMENT_ROOT.'/societe/class/companypaymentmode.class.php';
			$companypaymentmode = new CompanyPaymentMode($db);
			$companypaymentmode->fetch(GETPOST('companymodeid', 'int'));     // Read into llx_societe_rib

			if ($companypaymentmode->id > 0) {
				$result = $sellyoursaasutils->doTakePaymentStripeForThirdparty($service, $servicestatusstripe, $object->id, $companypaymentmode, null, 0, 1, 1);
				if ($result > 0) {
					$error++;
					$this->error=$sellyoursaasutils->error;
					$this->errors=$sellyoursaasutils->errors;
					setEventMessages($sellyoursaasutils->description, null, 'errors');
					setEventMessages($this->error, $this->errors, 'errors');
				} else {
					setEventMessages($langs->trans("PaymentDoneOn".ucfirst($service), $sellyoursaasutils->stripechargedone), null, 'mesgs');
				}
			} else {
				$error++;
				$this->error='Failed to fetch company payment mode for id '.GETPOST('companymodeid', 'int');
				$this->errors=null;
				setEventMessages($this->error, $this->errors, 'errors');
			}
		}

		dol_syslog(get_class($this).'::doActions end');
		return 0;
	}

	/**
	 *    formConfirm
	 *
	 *    @param	array			$parameters		Array of parameters
	 *    @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 *    @param    string			$action      	'add', 'update', 'view'
	 *    @return   int         					<0 if KO,
	 *                              				=0 if OK but we want to process standard actions too,
	 *                              				>0 if OK and we want to replace standard actions.
	 */
	public function formConfirm($parameters, &$object, &$action)
	{
		global $db, $langs, $conf, $user, $form;

		dol_syslog(get_class($this).'::doActions action='.$action);
		$langs->load("sellyoursaas@sellyoursaas");

		if ($action == 'changecustomer') {
			// Change customer confirmation
			$showtype = 1;
			$showcode = 0;
			if ((float) DOL_VERSION < 18) {
				$formquestion = array(array('type' => 'other','name' => 'socid','label' => $langs->trans("SelectThirdParty"),'value' => $form->select_company($object->thirdparty->id, 'socid', '(s.client=1 OR s.client=2 OR s.client=3)', '', $showtype, 0, null, 0, 'minwidth100', '', '', 1, array(), false, array(), $showcode)));
			} else {
				$formquestion = array(array('type' => 'other','name' => 'socid','label' => $langs->trans("SelectThirdParty"),'value' => $form->select_company($object->thirdparty->id, 'socid', '((s.client:=:1) OR (s.client:=:2) OR (s.client:=:3))', '', $showtype, 0, null, 0, 'minwidth100', '', '', 1, array(), false, array(), $showcode)));
			}
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ChangeCustomer'), '', 'confirm_changecustomer', $formquestion, 'yes', 1);
			$this->resprints = $formconfirm;
		}

		if ($action == 'undeploy') {
			// Undeploy confirmation
			$formquestion = array();
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('Undeploy'), $langs->trans("ConfirmUndeploy"), 'confirm_undeploy', $formquestion, 'no', 1);
			$this->resprints = $formconfirm;
		}

		if ($action == 'suspendmaintenancetoconfirm') {
			// Switch to maintenance mode confirmation
			$formquestion = array(array('type' => 'textarea', 'name' => 'suspendmaintenancemessage', 'label' => $langs->trans("MaintenanceMessage"), 'value' =>'', 'morecss'=>'centpercent'));
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('Confirmation'), $langs->trans("ConfirmMaintenance", $conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT), 'suspendmaintenance', $formquestion, 'no', 1, 350);
			$this->resprints = $formconfirm;
		}

		return 0;
	}

	/**
	 *    formObjectOptions
	 *
	 *    @param	array			$parameters		Array of parameters
	 *    @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 *    @param    string			$action      	'add', 'update', 'view'
	 *    @return   int         					<0 if KO,
	 *                              				=0 if OK but we want to process standard actions too,
	 *                              				>0 if OK and we want to replace standard actions.
	 */
	public function formObjectOptions($parameters, &$object, &$action)
	{
		if ($parameters['currentcontext'] == 'contractcard') {
			//print '<tr><td>aaa</td><td></td></tr>';
		}
	}

	/**
	 * Complete search forms
	 *
	 * @param	array	$parameters		Array of parameters
	 * @return	int						1=Replace standard code, 0=Continue standard code
	 */
	public function addSearchEntry($parameters)
	{
		global $conf, $langs, $user;

		if ($user->hasRight('sellyoursaas', 'read')) {
			/*$langs->load("sellyoursaas@sellyoursaas");
			$search_boxvalue = $parameters['search_boxvalue'];

			$this->results['searchintocontract']=$parameters['arrayresult']['searchintocontract'];
			$this->results['searchintocontract']['position']=22;

			$this->results['searchintodolicloud']=array('position'=>23, 'img'=>'object_generic', 'label'=>$langs->trans("XXX", $search_boxvalue), 'text'=>img_picto('','object_generic').' '.$langs->trans("XXX", $search_boxvalue), 'url'=>dol_buildpath('/sellyoursaas/backoffice/dolicloud_list.php',1).'?search_multi='.urlencode($search_boxvalue));
			*/
		}

		return 0;
	}


	/**
	 * Complete search forms
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string			$action      	'add', 'update', 'view'
	 * @return	int								1=Replace standard code, 0=Continue standard code
	 */
	public function moreHtmlStatus($parameters, $object = null, $action = '')
	{
		global $conf, $langs, $user;
		global $object;

		if ($parameters['currentcontext'] == 'contractcard') {
			if (! empty($object->array_options['options_deployment_status'])) {
				dol_include_once('sellyoursaas/lib/sellyoursaas.lib.php');
				$ret = '<br><br><div class="right bold">';
				$ispaid = sellyoursaasIsPaidInstance($object);
				if ($object->array_options['options_deployment_status'] == 'done') {
					// Show warning if in maintenance mode
					if (! empty($object->array_options['options_suspendmaintenance_message'])) {
						if (preg_match('/^http/i', $object->array_options['options_suspendmaintenance_message'])) {
							$messagetoshow = $langs->trans("InstanceIsARedirectionInstance", $conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT);
						} else {
							$messagetoshow = $langs->trans("InstanceInMaintenanceMode", $conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT);
						}
						$messagetoshow .= '<br><u>'.$langs->trans("MaintenanceMessage").':</u><br>';
						$messagetoshow .= $object->array_options['options_suspendmaintenance_message'];
						$ret .= img_warning($messagetoshow, '', 'classfortooltip marginrightonly');
					}
					// Show payment status
					if ($ispaid) {
						$ret .= '<span class="badge badge-status4 badge-status valignmiddle inline-block">';
						if (getDolGlobalString("SELLYOURSAAS_ENABLE_FREE_PAYMENT_MODE")) {
							$ret .= $langs->trans("PayedOrConfirmedMode");
						} else {
							$ret .= $langs->trans("PayedMode");
						}
						$ret .= '</span>';
						// nbofserviceswait, nbofservicesopened, nbofservicesexpired and nbofservicesclosed
						if (! $object->nbofservicesclosed) {
							$daysafterexpiration = getDolGlobalString('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND');
							$ret.='<span class="badge2 small marginleftonly valignmiddle inline-block" title="Expiration = Date planed for end of service">Paid services will be suspended<br>'.$daysafterexpiration.' days after expiration.</span>';
						}
						if ($object->nbofservicesclosed) {
							$daysafterexpiration = getDolGlobalString('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT');
							$ret.='<span class="badge2 small marginleftonly valignmiddle inline-block" title="Expiration = Date planed for end of service">Paid instance will be undeployed<br>'.$daysafterexpiration.' days after expiration.</span>';
						}
					} else {
						$ret .= '<span class="badge badge-status5 badge-status valignmiddle inline-block" style="font-size: 1em">'.$langs->trans("TrialMode").'</span>';
						// nbofserviceswait, nbofservicesopened, nbofservicesexpired and nbofservicesclosed
						if (! $object->nbofservicesclosed) {
							$daysafterexpiration = getDolGlobalString('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND');
							$ret.='<span class="badge2 small marginleftonly valignmiddle inline-block" title="Expiration = Date planed for end of service">Test services will be suspended<br>'.$daysafterexpiration.' days after expiration.</span>';
						}
						if ($object->nbofservicesclosed) {
							$daysafterexpiration = getDolGlobalString('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT');
							$ret.='<span class="badge2 small marginleftonly valignmiddle inline-block" title="Expiration = Date planed for end of service">Test instance will be undeployed<br>'.$daysafterexpiration.' days after expiration.</span>';
						}
					}
				}
				$ret .= '</div>';

				$this->resprints = $ret;
			}
		}

		return 0;
	}


	/**
	 * Complete search forms
	 *
	 * @param	array			$parameters		Array of parameters
	 * @return	int								1=Replace standard code, 0=Continue standard code
	 */
	public function printEmail($parameters)
	{
		global $conf, $langs, $user;
		global $object, $action;

		if (in_array($parameters['currentcontext'], array('thirdpartycard','thirdpartycontact','thirdpartycomm','thirdpartysupplier','thirdpartyticket','thirdpartynote','thirdpartydocument','contactthirdparty','thirdpartypartnership','projectthirdparty','consumptionthirdparty','thirdpartybancard','thirdpartymargins','ticketlist','thirdpartynotification','agendathirdparty'))) {
			$parameters['notiret']=1;
			$this->getNomUrl($parameters, $object, $action);        // This is hook. It fills ->resprints
			unset($parameters['notiret']);
		}

		return 0;
	}



	/**
	 * Complete search forms
	 *
	 * @param	array	$parameters		Array of parameters
	 * @return	int						1=Replace standard code, 0=Continue standard code
	 */
	public function getDefaultFromEmail($parameters)
	{
		global $conf, $langs, $user;
		global $object;

		$langs->load("sellyoursaas@sellyoursaas");

		$result='';

		if ($user->hasRight('sellyoursaas', 'read')) {
			if (is_object($object)) {
				$thirdparty = null;
				if (is_object($object->thirdparty)) $thirdparty = $object->thirdparty;
				elseif ($object->element == 'societe') $thirdparty = $object;

				if (is_object($thirdparty)) {
					$categ_customer_sellyoursaas = $conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG;

					include_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
					$categobj = new Categorie($this->db);
					$categobj->fetch($categ_customer_sellyoursaas);

					// Search if customer is a dolicloud customer
					$hascateg = $categobj->containsObject('customer', $thirdparty->id);
					if ($hascateg) {
						// If the thirdparty is a prospect or customer tagged with SELLYOURSAAS category for prospect/customers, then we take the first sender profile
						$sql = "SELECT rowid, label, email FROM ".$this->db->prefix()."c_email_senderprofile";
						$sql .= " WHERE active = 1 AND (private = 0 OR private = ".((int) $user->id).")";
						$sql .= " ORDER BY position";
						$resql = $this->db->query($sql);
						if ($resql) {
							$obj = $this->db->fetch_object($resql);
							if ($obj) {
								$result = 'senderprofile_'.$obj->rowid.'_1';
							}
						}
					}
					//var_dump($hascateg);

					// Search if customer has a premium subscription
					//var_dump($object->thirdparty);
				}
			}
			$this->results['defaultfrom']=$result;
		}

		return 0;
	}


	/**
	 * Run substitutions during ODT generation
	 *
	 * @param	array	$parameters		Array of parameters
	 * @return	int						1=Replace standard code, 0=Continue standard code
	 */
	public function ODTSubstitution($parameters)
	{
		global $conf, $langs;
		global $object;

		$langs->load("sellyoursaas@sellyoursaas");

		$contract = $parameters['object'];
		if (get_class($contract) == 'Contrat') {
			// Read version
			$server = $contract->ref_customer;
			$hostname_db = $object->hostname_db;
			if (empty($hostname_db)) $hostname_db = $object->array_options['options_hostname_db'];
			$port_db = $object->port_db;
			if (empty($port_db)) $port_db = (! empty($object->array_options['options_port_db']) ? $object->array_options['options_port_db'] : 3306);
			$username_db = $contract->username_db;
			if (empty($username_db)) $username_db = $contract->array_options['options_username_db'];
			$password_db = $object->password_db;
			if (empty($password_db)) $password_db = $contract->array_options['options_password_db'];
			$database_db = $object->database_db;
			if (empty($database_db)) $database_db = $contract->array_options['options_database_db'];

			$server = (! empty($hostname_db) ? $hostname_db : $server);

			$newdb = getDoliDBInstance('mysqli', $server, $username_db, $password_db, $database_db, $port_db);

			if (is_object($newdb) && $newdb->connected) {
				// Get version
				$parameters['substitutionarray']['sellyoursaas_version']=7;
				$sql = " SELECT value FROM ".MAIN_DB_PREFIX."const where name = 'MAIN_VERSION_LAST_UPGRADE'";
				$resql = $newdb->query($sql);
				if ($resql) {
					$obj = $newdb->fetch_object($resql);
					if ($obj) {
						$tmp=explode('.', $obj->value);
						$vermaj=$tmp[0];
						$parameters['substitutionarray']['sellyoursaas_version']=$vermaj;
					}
				}

				$newdb->close();
			}
		}

		$parameters['substitutionarray']['sellyoursaas_signature_logo']=DOL_DATA_ROOT.'/mycompany/notdownloadable/signature_owner.jpg';

		return 0;
	}


	/**
	 * Complete list
	 *
	 * @param	array	$parameters		Array of parameters
	 * @param	object	$object			Object
	 * @return	string					HTML content to add by hook
	 */
	public function printFieldListTitle($parameters, &$object)
	{
		global $conf, $langs;
		global $param, $sortfield, $sortorder;
		global $contextpage;

		if ($parameters['currentcontext'] == 'contractlist' && in_array($contextpage, array('sellyoursaasinstances','sellyoursaasinstancesvtwo'))) {
			$langs->load("sellyoursaas@sellyoursaas");
			if (empty($conf->global->SELLYOURSAAS_DISABLE_TRIAL_OR_PAID))
				print_liste_field_titre("TrialOrPaid", $_SERVER["PHP_SELF"], '', '', $param, ' align="center"', $sortfield, $sortorder);
			if (empty($conf->global->SELLYOURSAAS_DISABLE_PAYMENT_MODE_SAVED))
				print_liste_field_titre("PaymentModeSaved", $_SERVER["PHP_SELF"], '', '', $param, ' align="center"', $sortfield, $sortorder);
		}
		if ($parameters['currentcontext'] == 'thirdpartybancard') {
			$langs->load("sellyoursaas@sellyoursaas");
			print_liste_field_titre("", $_SERVER["PHP_SELF"], '', '', $param, ' align="center"', $sortfield, $sortorder);
		}

		return 0;
	}

	/**
	 * Complete list
	 *
	 * @param	array	$parameters		Array of parameters
	 * @param	object	$object			Object
	 * @return	string					HTML content to add by hook
	 */
	public function printFieldListOption($parameters, &$object)
	{
		global $conf, $langs;
		global $contextpage;

		if ($parameters['currentcontext'] == 'contractlist' && in_array($contextpage, array('sellyoursaasinstances','sellyoursaasinstancesvtwo'))) {
			//global $param, $sortfield, $sortorder;
			if (empty($conf->global->SELLYOURSAAS_DISABLE_TRIAL_OR_PAID)) {
				print '<td class="liste_titre"></td>';
			}
			if (empty($conf->global->SELLYOURSAAS_DISABLE_PAYMENT_MODE_SAVED)) {
				print '<td class="liste_titre"></td>';
			}
		}

		return 0;
	}

	/**
	 * Complete the list of contracts
	 *
	 * @param	array	$parameters		Array of parameters
	 * @param	object	$object			Object
	 * @return	string					HTML content to add by hook
	 */
	public function printFieldListValue($parameters, &$object)
	{
		global $conf, $langs;
		global $db;
		global $contextpage;

		if ($parameters['currentcontext'] == 'contractlist' && in_array($contextpage, array('sellyoursaasinstances','sellyoursaasinstancesvtwo'))) {
			if (empty($conf->global->SELLYOURSAAS_DISABLE_TRIAL_OR_PAID)) { // Column "Mode paid or free" not hidden
				global $contractmpforloop;
				if (! is_object($contractmpforloop)) {
					$contractmpforloop = new Contrat($db);
				}
				$contractmpforloop->id = $parameters['obj']->rowid ? $parameters['obj']->rowid : $parameters['obj']->id;
				$contractmpforloop->socid = $parameters['obj']->socid;

				print '<td class="center">';
				if (!empty($parameters['obj']->options_deployment_status) && $parameters['obj']->options_deployment_status != 'undeployed') {
					if (! preg_match('/\.on\./', $parameters['obj']->ref_customer)) {
						dol_include_once('sellyoursaas/lib/sellyoursaas.lib.php');

						$ret = '<div class="bold">';
						$ispaid = sellyoursaasIsPaidInstance($contractmpforloop);	// This call fetchObjectLinked
						if ($ispaid) $ret .= '<span class="badge badge-status4" style="font-size: 1em;">'.$langs->trans("PayedMode").'</span>';
						else $ret .= '<span class="badge" style="font-size: 1em">'.$langs->trans("TrialMode").'</span>';
						$ret .= '</div>';

						print $ret;
					}
				}
				print '</td>';
			}
			if (empty($conf->global->SELLYOURSAAS_DISABLE_PAYMENT_MODE_SAVED)) {    // Column "Payment mode recorded" not hidden
				print '<td class="center">';
				if (!empty($parameters['obj']->options_deployment_status)) {
					dol_include_once('sellyoursaas/lib/sellyoursaas.lib.php');

					$atleastonepaymentmode = sellyoursaasThirdpartyHasPaymentMode($parameters['obj']->socid);

					if ($atleastonepaymentmode) print $langs->trans("Yes");
				}
				print '</td>';
			}
		}

		if ($parameters['currentcontext'] == 'thirdpartybancard') {
			print '<td class="center">';
			if (! empty($parameters['obj']->rowid) && $parameters['linetype'] == 'stripecard') {
				$langs->load("sellyoursaas@sellyoursaas");
				print '<a class="sellyoursaastakepayment" href="'.$_SERVER["PHP_SELF"].'?socid='.((int) $object->id).'&action=sellyoursaastakepayment&token='.newToken().'&companymodeid='.((int) $parameters['obj']->rowid).'">'.$langs->trans("PayBalance").'</a>';
			}
			print '</td>';
		}

		return 0;
	}


	/**
	 * Execute action
	 *
	 * @param	array	$parameters		Array of parameters
	 * @param   Object	$pdfhandler   	PDF builder handler
	 * @param   string	$action     	'add', 'update', 'view'
	 * @return  int 		        	<0 if KO,
	 *                          		=0 if OK but we want to process standard actions too,
	 *  	                            >0 if OK and we want to replace standard actions.
	 */
	public function afterPDFCreation($parameters, &$pdfhandler, &$action)
	{
		global $conf,$langs;
		global $hookmanager;

		if (! empty($conf->global->SELLYOURSAAS_AFTERPDFCREATION_HOOK_DISABLED)) {
			dol_syslog("Trigger afterPDFCreation was called but constant 'SELLYOURSAAS_AFTERPDFCREATION_HOOK_DISABLED' is defined.", LOG_WARNING);
			return 0;
		}

		if (! is_object($parameters['object'])) {
			dol_syslog("Trigger afterPDFCreation was called but parameter 'object' was not set by caller.", LOG_WARNING);
			return 0;
		}

		if (! isset($parameters['object']->thirdparty) || ! is_object($parameters['object']->thirdparty)) {
			dol_syslog("Trigger afterPDFCreation was called but property thirdparty of object was not load by caller or does not exists.");
			return 0;
		}

		// If not a sellyoursaas user, we leave
		if (empty($parameters['object']->thirdparty->array_options['options_dolicloud']) || $parameters['object']->thirdparty->array_options['options_dolicloud'] == 'no') {
			return 0;
		}

		$mythirdpartyaccount = $parameters['object']->thirdparty;

		// Define logo
		$secondlogo = getDolGlobalString('SELLYOURSAAS_LOGO_SMALL');
		$secondlogoblack = getDolGlobalString('SELLYOURSAAS_LOGO_SMALL_BLACK');
		if (is_object($mythirdpartyaccount) && $mythirdpartyaccount->array_options['options_domain_registration_page']) {
			$domainforkey = strtoupper($mythirdpartyaccount->array_options['options_domain_registration_page']);
			$domainforkey = preg_replace('/\./', '_', $domainforkey);

			$constname = 'SELLYOURSAAS_LOGO_SMALL_'.$domainforkey;
			$constnameblack = 'SELLYOURSAAS_LOGO_SMALL_BLACK_'.$domainforkey;
			if (getDolGlobalString($constname)) {
				$secondlogo = getDolGlobalString($constname);
			}
			if (getDolGlobalString($constnameblack)) {
				$secondlogoblack = getDolGlobalString($constnameblack);
			}
		}

		// Is second logo is same than main logo ?
		if ($secondlogo == $conf->global->MAIN_INFO_SOCIETE_LOGO_SMALL || empty($secondlogo)) {
			return 0;
		}

		// If this is a customer of SellYourSaas, we add logo of SellYourSaas
		$outputlangs=$langs;

		$this->marge_haute =isset($conf->global->MAIN_PDF_MARGIN_TOP)?$conf->global->MAIN_PDF_MARGIN_TOP:10;

		//var_dump($parameters['object']);

		$ret=0;
		dol_syslog(get_class($this).'::executeHooks action='.$action);

		$file = $parameters['file'];

		$formatarray = pdf_getFormat();
		$format = array($formatarray['width'], $formatarray['height']);

		// Create empty PDF
		$pdf=pdf_getInstance($format);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));

		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}
		//$pdf->SetCompression(false);

		$pagecounttmp = $pdf->setSourceFile($file);
		if ($pagecounttmp) {
			try {
				for ($i = 1; $i <= $pagecounttmp; $i++) {
					$tplidx = $pdf->importPage($i);
					$s = $pdf->getTemplatesize($tplidx);
					$pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
					$pdf->useTemplate($tplidx);
				}
				$logo = $conf->mycompany->dir_output.'/logos/thumbs/'.$secondlogo;

				$height=pdf_getHeightForLogo($logo);
				$pdf->Image($logo, 80, $this->marge_haute, 0, 10);	// width=0 (auto)
			} catch (Exception $e) {
				dol_syslog("Failed to add the second image by the sellyoursaas hook", LOG_ERR);
				$this->error = $e->getMessage();
			}
		} else {
			dol_syslog("Error: Can't read PDF content with setSourceFile, for file ".$file, LOG_ERR);
		}

		if ($pagecounttmp) {
			$pdf->Output($file, 'F');
			if (! empty($conf->global->MAIN_UMASK)) {
				@chmod($file, octdec($conf->global->MAIN_UMASK));
			}
		}

		return $ret;
	}


	/**
	 * Overloading the loadDataForCustomReports function : returns data to complete the customreport tool
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function loadDataForCustomReports($parameters, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$langs->load("sellyoursaas@sellyoursaas");

		$this->results = array();

		$head = array();
		$h = 0;

		if ($parameters['tabfamily'] == 'sellyoursaas') {
			//$this->results['modenotusedforlist'] = 1;
			$head[$h][0] = dol_buildpath('/sellyoursaas/backoffice/index.php', 1);
			$head[$h][1] = $langs->trans("Home");
			$head[$h][2] = 'home';
			$h++;

			if (!getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION')) {
				$head[$h][0] = dol_buildpath('/sellyoursaas/backoffice/deployment_servers.php', 1);
				$head[$h][1] = $langs->trans("DeploymentServers");
				$head[$h][2] = 'deploymentservers';
				$h++;
			} else {
				$head[$h][0] = '/custom/sellyoursaas/deploymentserver_list.php';
				$head[$h][0] = dol_buildpath('/sellyoursaas/deploymentserver_list.php', 1);
				$head[$h][1] = $langs->trans("DeploymentServers");
				$head[$h][2] = 'deploymentservers';
				$h++;
			}

			$head[$h][0] = dol_buildpath('/sellyoursaas/backoffice/setup_antispam.php', 1);
			$head[$h][1] = $langs->trans("AntiSpam");
			$head[$h][2] = 'antispam';
			$h++;

			$this->results['title'] = $langs->trans("DoliCloudArea");
			$this->results['picto'] = 'sellyoursaas@sellyoursaas';
		}

		//if ($parameters['tabfamily'] == 'sellyoursaas') {
			$head[$h][0] = 'customreports.php?objecttype='.$parameters['objecttype'].(empty($parameters['tabfamily'])?'':'&tabfamily='.$parameters['tabfamily']);
			$head[$h][1] = $langs->trans("CustomReports");
			$head[$h][2] = 'customreports';
			$h++;
		//}

		if ($parameters['tabfamily'] == 'sellyoursaas') {
			$head[$h][0] = dol_buildpath('/sellyoursaas/backoffice/notes.php', 1);
			$head[$h][1] = $langs->trans("Notes");
			$head[$h][2] = 'notes';
			$h++;
		}

		$this->results['head'] = $head;

		$arrayoftypes = array(
			'packages' => array('label' => 'Packages', 'picto'=>'label', 'ObjectClassName' => 'Packages', 'enabled' => $conf->sellyoursaas->enabled, 'ClassPath' => "/sellyoursaas/class/packages.class.php", 'langs'=>'sellyousaas@sellyoursaas')
		);
		$this->results['arrayoftype'] = $arrayoftypes;

		return 1;
	}

	/**
	 * Overloading the restrictedArea function : check permission on an object
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int 		      			  	=0
	 */
	public function completeFieldsToSearchAll($parameters, &$action, $hookmanager)
	{
		$this->results['fieldstosearchall']['username_os'] = 'Username OS';
		$this->results['fieldstosearchall']['database_db'] = 'Database DB';
		$this->results['fieldstosearchall']['username_db'] = 'Username DB';

		return 0;
	}


	/**
	 * Overloading the restrictedArea function : check permission on an object
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int 		      			  	=0 if OK but we want to process standard actions too,
	 *  	                            		>0 if OK and we want to replace standard actions.
	 */
	public function restrictedArea($parameters, &$action, $hookmanager)
	{
		global $user;

		if ($parameters['features'] == 'packages') {
			if ($user->hasRight('sellyoursaas', 'read')) {
				$this->results['result'] = 1;
				return 1;
			} else {
				$this->results['result'] = 0;
				return 1;
			}
		}

		return 0;
	}
}
