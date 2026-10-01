<?php
/* Copyright (C) 2004-2021 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *       \file       htdocs/sellyoursaas/backoffice/lib/refresh.lib.php
 *       \ingroup    sellyoursaas
 *       \brief      Library for sellyoursaas refresh lib
 */

// Files with some functions

include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';


/**
 * Process refresh of setup files for customer $object.
 * This does not update any lastcheck fields.
 *
 * @param 	Conf						$conf					Conf
 * @param 	Database					$db						Database handler
 * @param 	Contrat                  	$object	    			Customer (can modify caller)
 * @param	array						$errors	    			Array of errors
 * @param	int							$printoutput			Print output information
 * @param	int							$recreateauthorizekey	1=Recreate authorized key if not found, 2=Recreate authorized key event if found
 * @return	int													1
 */
function dolicloud_files_refresh($conf, $db, &$object, &$errors, $printoutput = 0, $recreateauthorizekey = 0)
{
	$instance = $object->instance;
	if (empty($instance)) {
		$instance = $object->ref_customer;
	}
	$username_os = $object->username_os;
	if (empty($username_os)) {
		$username_os = $object->array_options['options_username_os'];
	}
	$password_os = $object->password_os;
	if (empty($password_os)) {
		$password_os = $object->array_options['options_password_os'];
	}
	$database_db = $object->database_db;
	if (empty($database_db)) {
		$database_db = $object->array_options['options_database_db'];
	}

	$server = $instance;

	// SFTP refresh
	if (function_exists("ssh2_connect")) {
		$server_port = getDolGlobalInt('SELLYOURSAAS_SSH_SERVER_PORT', 22);

		if ($printoutput) {
			print "ssh2_connect ".$server." ".$server_port." ".$username_os." ".$password_os."\n";
		}

		$methods = array();
		if (getDolGlobalString('SELLYOURSAAS_SSH2_HOSTKEYALGO')) {
			$methods['hostkey'] = getDolGlobalString('SELLYOURSAAS_SSH2_HOSTKEYALGO');
		}
		if (getDolGlobalString('SELLYOURSAAS_SSH2_KEXALGO')) {
			$methods['kex'] = getDolGlobalString('SELLYOURSAAS_SSH2_KEXALGO');
		}
		if (!empty($methods)) {
			$connection = ssh2_connect($server, $server_port, $methods);
		} else {
			$connection = ssh2_connect($server, $server_port);
		}

		if ($connection) {
			if ($printoutput) {
				print $instance." ".$username_os." ".$password_os."\n";
			}

			if (! @ssh2_auth_password($connection, $username_os, $password_os)) {
				dol_syslog("Could not authenticate in dolicloud_files_refresh with username ".$username_os." . and password ".preg_replace('/./', '*', $password_os), LOG_ERR);
			} else {
				$sftp = ssh2_sftp($connection);
				if (! $sftp) {
					dol_syslog("Could not execute ssh2_sftp", LOG_ERR);
					$errors[]='Failed to connect to ssh2 to '.$server;
					return 1;
				}

				$dir=preg_replace('/_([a-zA-Z0-9]+)$/', '', $database_db);

				// Update ssl certificate
				// Dir .ssh must have rwx------ permissions
				// File authorized_keys_support must have rw------- permissions

				// Check if authorized_keys_support exists
				//$filecert="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_EXT_HOME.'/'.$object->username_os.'/.ssh/authorized_keys_support';
				$filecert="ssh2.sftp://".intval($sftp) . getDolGlobalString('DOLICLOUD_EXT_HOME').'/'.$username_os.'/.ssh/authorized_keys_support';    // With PHP 5.6.27+
				$fstat=@ssh2_sftp_stat($sftp, getDolGlobalString('DOLICLOUD_EXT_HOME') . '/'.$username_os.'/.ssh/authorized_keys_support');
				// Create authorized_keys_support file
				if (empty($fstat['atime']) || $recreateauthorizekey == 2) {
					if ($recreateauthorizekey) {
						@ssh2_sftp_mkdir($sftp, getDolGlobalString('DOLICLOUD_EXT_HOME') . '/'.$username_os.'/.ssh');

						$publickeystodeploy = getDolGlobalString('SELLYOURSAAS_PUBLIC_KEY');

						// We overwrite authorized_keys_support
						if ($printoutput) {
							print 'Write file ' . getDolGlobalString('DOLICLOUD_EXT_HOME').'/'.$username_os.'/.ssh/authorized_keys_support.'."\n";
						}

						$stream = @fopen($filecert, 'w');
						//var_dump($stream);exit;
						if ($stream) {
							fwrite($stream, $publickeystodeploy);
							fclose($stream);
							$fstat=ssh2_sftp_stat($sftp, getDolGlobalString('DOLICLOUD_EXT_HOME') . '/'.$username_os.'/.ssh/authorized_keys_support');
						} else {
							$errors[]='Failed to open for write '.$filecert."\n";
						}
					} else {
						if ($printoutput) {
							print 'File ' . getDolGlobalString('DOLICLOUD_EXT_HOME').'/'.$username_os."/.ssh/authorized_keys_support not found.\n";
						}
					}
				} else {
					if ($printoutput) {
						print 'File ' . getDolGlobalString('DOLICLOUD_EXT_HOME').'/'.$username_os."/.ssh/authorized_keys_support already exists.\n";
					}
				}
				$object->fileauthorizedkey=(empty($fstat['mtime']) ? '' : $fstat['mtime']);

				// Check if install.lock exists
				//$fileinstalllock="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_EXT_HOME.'/'.$object->username_os.'/'.$dir.'/documents/install.lock';
				//$fileinstalllock="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_os.'/'.$dir.'/documents/install.lock';    // With PHP 5.6.27+
				$fstatlock=@ssh2_sftp_stat($sftp, getDolGlobalString('DOLICLOUD_EXT_HOME') . '/'.$username_os.'/'.$dir.'/documents/install.lock');
				$object->filelock=(empty($fstatlock['atime']) ? '' : $fstatlock['atime']);

				// Check if installmodules.lock exists
				$fstatinstallmoduleslock=@ssh2_sftp_stat($sftp, getDolGlobalString('DOLICLOUD_EXT_HOME') . '/'.$username_os.'/'.$dir.'/documents/installmodules.lock');
				$object->fileinstallmoduleslock=(empty($fstatinstallmoduleslock['atime']) ? '' : $fstatinstallmoduleslock['atime']);

				// Define dates
				/*if (empty($object->date_registration) || empty($object->date_endfreeperiod))
				{
					// Overwrite only if not defined
					$object->date_registration=$fstatlock['mtime'];
					//$object->date_endfreeperiod=dol_time_plus_duree($object->date_registration,1,'m');
					$object->date_endfreeperiod=($object->date_registration?dol_time_plus_duree($object->date_registration,15,'d'):'');
				}*/
			}
		} else {
			$errors[]='Failed to connect to ssh2 to '.$server;
		}
	} else {
		$errors[]='ssh2_connect not supported by this PHP';
	}

	return 1;
}


/**
 * Calculate stats ('total', 'totalcommissions', 'totalinstancespaying', 'totalinstancessuspended',
 * 'totalinstancesexpiredfree', 'totalinstancesexpiredpaying', 'totalinstances' (nb instances included suspended), 'totalusers'
 * at date datelim (or realtime if date is empty)
 *
 * Rem: Comptage des users par status
 *
 * @param	Database	$db				Database handler
 * @param	integer		$datelim		Date limit
 * @param	integer		$datefirstday	Date first day
 * @return	array						Array of data
 */
function sellyoursaas_calculate_stats($db, $datelim, $datefirstday)
{
	$error = 0;

	$total = $totalcommissions = $totalnewinstances = $totallostinstances = $totalinstancespaying = $totalinstancespayingall = $totalinstancespayingwithoutrecinvoice = 0;
	$totalinstancesexpiredfree = $totalinstancesexpiredpaying = $totalinstancessuspendedfree = $totalinstancessuspendedpaying = 0;
	$totalinstances = $totalusers = 0;
	$listofinstancespaying=array();
	$listofinstancespayingall=array();
	$listofinstancespayingwithoutrecinvoice = array();
	$listofcustomerstrial=array();
	$listofcustomerspaying=array();
	$listofcustomerspayingwithoutrecinvoice=array();
	$listofcustomerspayingall=array();
	$listofsuspendedrecurringinvoice=array();
	$listofnewinstances=array();
	$listoflostinstances=array();

	$nbofinstancedeployed = 0;

	$now = dol_now();

	// Get list of living deployed instances (to get stats on deployment status, paying status, ...)
	$sql = "SELECT c.rowid as id, c.ref_customer as instance, c.fk_soc as customer_id,";
	$sql.= " ce.deployment_status as instance_status,";
	$sql.= " s.parent, s.nom as name";
	$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object,";
	$sql.= " ".MAIN_DB_PREFIX."societe as s";
	$sql.= " WHERE s.rowid = c.fk_soc AND c.ref_customer <> '' AND c.ref_customer IS NOT NULL";
	$sql.= " AND ce.deployment_status = 'done'";
	$sql.= " AND (ce.suspendmaintenance_message IS NULL OR ce.suspendmaintenance_message NOT LIKE 'http%')";	// Exclude instances of type redirect
	if ($datelim && ($datelim < $now)) {
		$sql.= " AND ce.deployment_date_end <= '".$db->idate($datelim)."'";		// Only instances deployed with end before this date
	}

	dol_syslog("sellyoursaas_calculate_stats begin", LOG_DEBUG, 1);

	$resql=$db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		dol_syslog("sellyoursaas_calculate_stats found ".$num." record", LOG_DEBUG);

		$i = 0;
		if ($num) {
			include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
			dol_include_once('/sellyoursaas/class/sellyoursaascontract.class.php');
			dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');

			$now = dol_now();
			$object = new SellYourSaasContract($db);
			$templateinvoice = new FactureRec($db);
			//$cacheofthirdparties = array();

			// Loop on all deployed instances
			while ($i < $num) {
				$obj = $db->fetch_object($resql);
				if ($obj) {
					// We process the instance (ref_customer)
					$instance = $obj->instance;

					dol_syslog("sellyoursaas_calculate_stats analyze ".$instance, LOG_DEBUG);

					unset($object->linkedObjects);
					unset($object->linkedObjectsIds);

					// Load data of instance and set $instance_status (PROCESSING, DEPLOYED, SUSPENDED, UNDEPLOYED)
					$instance_status = 'UNKNOWN';
					$result = $object->fetch($obj->id);	// Make the fetch_lines() required later in the sellyoursaasGetExpirationDate()
					if ($result <= 0) {
						$i++;
						dol_print_error($db, $object->error, $object->errors);
						continue;
					} else {
						if ($object->array_options['options_deployment_status'] == 'processing') {
							$instance_status = 'PROCESSING';
						} elseif ($object->array_options['options_deployment_status'] == 'undeployed') {
							$instance_status = 'UNDEPLOYED';
						} elseif ($object->array_options['options_deployment_status'] == 'done') {
							// should be here due to test into SQL request
							$instance_status = 'DEPLOYED';
							$nbofinstancedeployed++;
						}
						if ($instance_status == 'DEPLOYED') {
							$issuspended = sellyoursaasIsSuspended($object);	// Use the property ->nb... loaded by the fetch_lines() in the fetch()
							if ($issuspended) {
								$instance_status = 'SUSPENDED';
							}
							// Note: to check that all non deployed instance has line with status that is 5 (close), you can run
							// select * from llx_contrat as c, llx_contrat_extrafields as ce, llx_contratdet as cd WHERE ce.fk_object = c.rowid
							// AND cd.fk_contrat = c.rowid AND ce.deployment_status <> 'done' AND cd.statut <> 5;
							// You should get nothing.
						}


						// Load array $tmpdata
						$tmpdata = sellyoursaasGetExpirationDate($object, 0);	// Loop on $object->lines
						$nbofuser = $tmpdata['nbusers'];
						$totalinstances++;
						$totalusers+=$nbofuser;


						// Set $payment_status ('TRIAL', 'PAID' or 'FAILURE')
						$payment_status='PAID';

						// This load linkedObjectsIds
						$ispaid = sellyoursaasIsPaidInstance($object, 0, 0);	// Make the fetchObjectLink(). Return true if instance $object is paying instance (invoice or template invoice exists)

						if (! $ispaid) {		// This is a test only customer, trial expired or not, suspended or not (just no invoice or template invoice at all)
							$payment_status='TRIAL';

							// Count $totalinstancesexpiredfree and $totalinstancessuspendedfree
							if ($tmpdata['expirationdate'] < $now) {
								$totalinstancesexpiredfree++;
							}
							if ($tmpdata['status'] == ContratLigne::STATUS_CLOSED) {		// Status of line with package app
								$totalinstancessuspendedfree++;
							}

							if (empty($listofcustomerstrial[$obj->customer_id])) {
								$listofcustomerstrial[$obj->customer_id] = 0;	// This is the total of trial only customers
							}
							$listofcustomerstrial[$obj->customer_id]++;	// This is the total of trial only customers
							//print "cpt=".$totalinstances." customer_id=".$obj->customer_id." instance=".$obj->instance." status=".$obj->status." instance_status=".$obj->instance_status." payment_status=".$obj->payment_status." => Price = ".$obj->price_instance.' * '.($obj->plan_meter_id == 1 ? $obj->nbofusers : 1)." + ".max(0,($obj->nbofusers - $obj->min_threshold))." * ".$obj->price_user." = ".$price." -> 0<br>\n";
						} else {
							$ispaymentko = sellyoursaasIsPaymentKo($object);	// Read table of actioncomm to find if payment error on an open invoice linked to contract
							if ($ispaymentko) {
								$payment_status='FAILURE';
							}

							// This is a paying customer (with at least one invoice or recurring invoice)

							// Count $totalinstancesexpiredpaying and $totalinstancessuspendedpaying
							if ($tmpdata['expirationdate'] < $now) {
								$totalinstancesexpiredpaying++;
							}

							if ($tmpdata['status'] == ContratLigne::STATUS_CLOSED) {
								$totalinstancessuspendedpaying++;
							}

							// Calculate price of monthly invoicing
							$price = 0;
							$price_ttc = 0;

							$atleastonenotsuspended = 0;
							if (!empty($object->linkedObjectsIds['facturerec']) && is_array($object->linkedObjectsIds['facturerec'])) {		// $object->linkedObjects loaded by the previous sellyoursaasIsPaidInstance
								foreach ($object->linkedObjectsIds['facturerec'] as $rowidelementelement => $idtemplateinvoice) {
									$templateinvoice->suspended = 0;
									$templateinvoice->unit_frequency = 0;
									$templateinvoice->total_ht = 0;
									$templateinvoice->total_ttc = 0;
									$templateinvoice->frequency = 0;

									$nolines = 1;
									$noextrafields = 1;

									$result = $templateinvoice->fetch($idtemplateinvoice, '', '', $noextrafields, $nolines);
									if ($result > 0) {
										if (! $templateinvoice->suspended) {
											if ($templateinvoice->unit_frequency == 'm' && $templateinvoice->frequency >= 1) {
												$pricem = $templateinvoice->total_ht / $templateinvoice->frequency;
												$price_ttcm = $templateinvoice->total_ttc / $templateinvoice->frequency;
											} elseif ($templateinvoice->unit_frequency == 'y' && $templateinvoice->frequency >= 1) {
												$pricem = ($templateinvoice->total_ht / (12 * $templateinvoice->frequency));
												$price_ttcm = ($templateinvoice->total_ttc / (12 * $templateinvoice->frequency));
											} else {
												$pricem = $templateinvoice->total_ht;
												$price_ttcm = $templateinvoice->total_ttc;
											}

											if (getDolGlobalInt('SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE') <= 0 || $pricem < getDolGlobalInt('SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE')) {
												$price += $pricem;
												$price_ttc += $price_ttcm;
											}

											$atleastonenotsuspended = 1;
										} else {
											$listofsuspendedrecurringinvoice[$idtemplateinvoice] = $idtemplateinvoice;
										}
									} else {
										$error++;
										dol_print_error($db);
									}
								}
							}

							if (! $atleastonenotsuspended) {	// Really a paying customer if template invoice is not suspended
								if (empty($listofcustomerspayingwithoutrecinvoice[$obj->customer_id])) {
									$listofcustomerspayingwithoutrecinvoice[$obj->customer_id] = 0;
								}
								$listofcustomerspayingwithoutrecinvoice[$obj->customer_id]++;
								$listofinstancespayingwithoutrecinvoice[$object->id] = array('thirdparty_id'=>$obj->customer_id, 'thirdparty_name'=>$obj->name, 'contract_ref'=>$object->ref);
								$totalinstancespayingwithoutrecinvoice++;
							}

							if (empty($listofcustomerspayingall[$obj->customer_id])) {
								$listofcustomerspayingall[$obj->customer_id] = 0;
							}
							$listofcustomerspayingall[$obj->customer_id]++;
							$listofinstancespayingall[$object->id]=array('thirdparty_id'=>$obj->customer_id, 'thirdparty_name'=>$obj->name, 'contract_ref'=>$object->ref);
							$totalinstancespayingall++;

							if ($atleastonenotsuspended && $instance_status != 'SUSPENDED' && $payment_status != 'FAILURE' && $tmpdata['status'] != ContratLigne::STATUS_CLOSED) {
								// If service really paying and not suspended and no payment error
								$total += $price;

								if (empty($listofcustomerspaying[$obj->customer_id])) {
									$listofcustomerspaying[$obj->customer_id] = 0;
								}
								$listofcustomerspaying[$obj->customer_id]++;
								$listofinstancespaying[$object->id]=array('thirdparty_id'=>$obj->customer_id, 'thirdparty_name'=>$obj->name, 'contract_ref'=>$object->ref);
								$totalinstancespaying++;

								//print "cpt=".$totalinstancespaying." customer_id=".$obj->customer_id." instance=".$obj->instance." status=".$obj->status." instance_status=".$obj->instance_status." payment_status=".$obj->payment_status." => Price = ".$obj->price_instance.' * '.($obj->plan_meter_id == 1 ? $obj->nbofusers : 1)." + ".max(0,($obj->nbofusers - $obj->min_threshold))." * ".$obj->price_user." = ".$price."<br>\n";

								// If this instance has a parent company, we calculate the commission.
								if ($atleastonenotsuspended && ! empty($obj->parent)) {
									$thirdpartyparent = new Societe($db);		// TODO Extend the select with left join on parent + extrafield to get this data without doing a fetch
									$thirdpartyparent->fetch($obj->parent);
									$totalcommissions += price2num($price * $thirdpartyparent->array_options['options_commission'] / 100);
								}
							} else {
								// We have here some expired contracts not yet renew
								//print 'We exclude this contract '.$object->ref."\n";
							}
						}
					}
				}
				$i++;
			}
		}
	} else {
		$error++;
		dol_print_error($db);
	}

	dol_syslog("sellyoursaas_calculate_stats end of getting list of instance", LOG_DEBUG, -1);


	// Get list of new deployed instances
	$sql = "SELECT c.rowid as id, c.ref_customer as instance, c.fk_soc as customer_id,";
	$sql.= " s.parent, s.nom as name,";
	$sql.= " f.total_ht, f.unit_frequency";
	$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c,";
	$sql.= " ".MAIN_DB_PREFIX."element_element as ee,";
	$sql.= " ".MAIN_DB_PREFIX."facture_rec as f,";
	$sql.= " ".MAIN_DB_PREFIX."societe as s";
	$sql.= " WHERE s.rowid = c.fk_soc AND c.ref_customer <> '' AND c.ref_customer IS NOT NULL";	// client or client + prospect
	$sql.= " AND ee.sourcetype = 'contrat' AND ee.fk_source = c.rowid AND ee.targettype = 'facturerec' AND ee.fk_target = f.rowid";
	if ($datelim && ($datelim < $now)) {
		$sql.= " AND f.datec <= '".$db->idate($datelim)."'";	// Only instances deployed with end before this date
	}
	if ($datefirstday) {
		$sql.= " AND f.datec >= '".$db->idate($datefirstday)."'";	// Only instances deployed with end after this date
	}
	// We exclude contracts that are redirection contracts
	$sql .= " AND NOT EXISTS (SELECT ce.rowid FROM llx_contrat_extrafields as ce WHERE ce.fk_object=c.rowid AND ce.suspendmaintenance_message LIKE 'http%')";

	$sql .= " UNION ";

	$sql.= "SELECT c.rowid as id, c.ref_customer as instance, c.fk_soc as customer_id,";
	$sql.= " s.parent, s.nom as name,";
	$sql.= " f.total_ht, f.unit_frequency";
	$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c,";
	$sql.= " ".MAIN_DB_PREFIX."element_element as ee,";
	$sql.= " ".MAIN_DB_PREFIX."facture_rec as f,";
	$sql.= " ".MAIN_DB_PREFIX."societe as s";
	$sql.= " WHERE s.rowid = c.fk_soc AND c.ref_customer <> '' AND c.ref_customer IS NOT NULL";	// client or client + prospect
	$sql.= " AND ee.sourcetype = 'facturerec' AND ee.fk_source = f.rowid AND ee.targettype = 'contrat' AND ee.fk_target = c.rowid";
	if ($datelim && ($datelim < $now)) {
		$sql.= " AND f.datec <= '".$db->idate($datelim)."'";	// Only instances deployed with end before this date
	}
	if ($datefirstday) {
		$sql.= " AND f.datec >= '".$db->idate($datefirstday)."'";	// Only instances deployed with end after this date
	}
	// We exclude contracts that are redirection contracts
	$sql .= " AND NOT EXISTS (SELECT ce.rowid FROM llx_contrat_extrafields as ce WHERE ce.fk_object=c.rowid AND ce.suspendmaintenance_message LIKE 'http%')";


	dol_syslog("sellyoursaas_calculate_stats new begin", LOG_DEBUG, 1);

	$resql=$db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		dol_syslog("sellyoursaas_calculate_stats new found ".$num." record", LOG_DEBUG);

		$i = 0;
		if ($num) {
			// Loop on all deployed instances
			while ($i < $num) {
				$obj = $db->fetch_object($resql);
				if ($obj) {
					if (!isset($listofnewinstances[$obj->id])) {
						$listofnewinstances[$obj->id] = 0;
					} else {
						continue;	// We have already processed this contract
					}

					$listofnewinstances[$obj->id]++;

					$nbmonth = 1;
					if ($obj->unit_frequency == 'y') {
						$nbmonth = 12;
					}

					$pricem = ($obj->total_ht / $nbmonth);
					if (getDolGlobalInt('SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE') <= 0 || $pricem < getDolGlobalInt('SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE')) {
						$totalnewinstances += $pricem;
					}
				}
				$i++;
			}
		}
	}

	dol_syslog("sellyoursaas_calculate_stats new end", LOG_DEBUG, -1);


	// Get list of lost undeployed instances
	$sql = "SELECT c.rowid as id, c.ref_customer as instance, c.fk_soc as customer_id,";
	$sql.= " ce.deployment_status as instance_status, ce.undeployment_date,";
	$sql.= " s.parent, s.nom as name,";
	$sql.= " f.total_ht, f.unit_frequency";
	$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c,";
	$sql.= " ".MAIN_DB_PREFIX."contrat_extrafields as ce,";
	$sql.= " ".MAIN_DB_PREFIX."element_element as ee,";
	$sql.= " ".MAIN_DB_PREFIX."facture_rec as f,";
	$sql.= " ".MAIN_DB_PREFIX."societe as s";
	$sql.= " WHERE s.rowid = c.fk_soc AND c.ref_customer <> '' AND c.ref_customer IS NOT NULL";	// client or client + prospect
	$sql.= " AND ee.sourcetype = 'contrat' AND ee.fk_source = ce.fk_object AND ee.targettype = 'facturerec' AND ee.fk_target = f.rowid";
	if ($datelim && ($datelim < $now)) {
		$sql.= " AND ce.undeployment_date <= '".$db->idate($datelim)."'";	// Only instances deployed with end before this date
	}
	if ($datefirstday) {
		$sql.= " AND ce.undeployment_date >= '".$db->idate($datefirstday)."'";	// Only instances deployed with end after this date
	}
	$sql.= " AND c.rowid = ce.fk_object AND ce.deployment_status = 'undeployed'";
	$sql.= " AND (ce.suspendmaintenance_message IS NULL OR ce.suspendmaintenance_message NOT LIKE 'http%')";	// Exclude instances of type redirect

	$sql.= " UNION ";

	$sql.= "SELECT c.rowid as id, c.ref_customer as instance, c.fk_soc as customer_id,";
	$sql.= " ce.deployment_status as instance_status, ce.undeployment_date,";
	$sql.= " s.parent, s.nom as name,";
	$sql.= " f.total_ht, f.unit_frequency";
	$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c,";
	$sql.= " ".MAIN_DB_PREFIX."contrat_extrafields as ce,";
	$sql.= " ".MAIN_DB_PREFIX."element_element as ee,";
	$sql.= " ".MAIN_DB_PREFIX."facture_rec as f,";
	$sql.= " ".MAIN_DB_PREFIX."societe as s";
	$sql.= " WHERE s.rowid = c.fk_soc AND c.ref_customer <> '' AND c.ref_customer IS NOT NULL";	// client or client + prospect
	$sql.= " AND ee.sourcetype = 'facturerec' AND ee.fk_source = f.rowid AND ee.targettype = 'contrat' AND ee.fk_target = ce.fk_object";
	if ($datelim && ($datelim < $now)) {
		$sql.= " AND ce.undeployment_date <= '".$db->idate($datelim)."'";	// Only instances deployed with end before this date
	}
	if ($datefirstday) {
		$sql.= " AND ce.undeployment_date >= '".$db->idate($datefirstday)."'";	// Only instances deployed with end after this date
	}
	$sql.= " AND c.rowid = ce.fk_object AND ce.deployment_status = 'undeployed'";
	$sql.= " AND (ce.suspendmaintenance_message IS NULL OR ce.suspendmaintenance_message NOT LIKE 'http%')";	// Exclude instances of type redirect

	dol_syslog("sellyoursaas_calculate_stats lostinstances begin", LOG_DEBUG, 1);

	$resql=$db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		dol_syslog("sellyoursaas_calculate_stats lostinstances found ".$num." record", LOG_DEBUG);

		$i = 0;
		if ($num) {
			// Loop on all deployed instances
			while ($i < $num) {
				$obj = $db->fetch_object($resql);
				if ($obj) {
					if (!isset($listoflostinstances[$obj->id])) {
						$listoflostinstances[$obj->id] = 0;
					} else {
						continue;	// We have already processed this contract
					}
					$listoflostinstances[$obj->id]++;
					$nbmonth = 1;
					if ($obj->unit_frequency == 'y') {
						$nbmonth = 12;
					}
					$pricem = ($obj->total_ht / $nbmonth);
					if (getDolGlobalInt('SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE') <= 0 || $pricem < getDolGlobalInt('SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE')) {
						$totallostinstances += $pricem;
					}
				}
				$i++;
			}
		}
	}

	dol_syslog("sellyoursaas_calculate_stats lostinstances end", LOG_DEBUG, -1);


	//var_dump($listofinstancespaying);
	$retarray = array(
		'total'=>(float) $total,
		'totalcommissions'=>(float) $totalcommissions,
		'totalnewinstances'=>(float) $totalnewinstances,
		'totallostinstances'=>(float) $totallostinstances,
		'totalinstancespaying'=>(int) $totalinstancespaying,
		'totalinstancespayingall'=>(int) $totalinstancespayingall,
		'totalinstancespayingwithoutrecinvoice'=>(int) $totalinstancespayingwithoutrecinvoice,
		'totalinstancessuspendedfree'=>(int) $totalinstancessuspendedfree,
		'totalinstancessuspendedpaying'=>(int) $totalinstancessuspendedpaying,
		'totalinstancesexpiredfree'=>(int) $totalinstancesexpiredfree,
		'totalinstancesexpiredpaying'=>(int) $totalinstancesexpiredpaying,
		'totalinstances'=>(int) $totalinstances,						// Total instances (trial + paid)
		'totalusers'=>(int) $totalusers,								// Total users (trial + paid)
		'totalcustomers'=>(int) count($listofcustomerstrial),			// Trial only customers (for backward compatibility)
		'totalcustomerstrial'=>(int) count($listofcustomerstrial),		// Trial only customers
		'totalcustomerspaying'=>(int) count($listofcustomerspaying),	// Paying customers
		'listofinstancespaying'=>$listofinstancespaying,
		'listofinstancespayingall'=>$listofinstancespayingall,
		'listofinstancespayingwithoutrecinvoice'=>$listofinstancespayingwithoutrecinvoice,
		'listofsuspendedrecurringinvoice'=>$listofsuspendedrecurringinvoice,
		'listofnewinstances'=>$listofnewinstances,
		'listoflostinstances'=>$listoflostinstances
	);
	//var_dump($retarray);

	return $retarray;
}
