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
 *       \brief      Library for sellyoursaas backoofice lib
 */

// Files with some lib
global $conf;

// Show totals
$serverprice = empty($conf->global->SELLYOURSAAS_INFRA_COST)?'100':$conf->global->SELLYOURSAAS_INFRA_COST;


include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';


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
	if (empty($instance)) $instance = $object->ref_customer;
	$username_web = $object->username_web;
	if (empty($username_web)) $username_web = $object->array_options['options_username_os'];
	$password_web = $object->password_web;
	if (empty($password_web)) $password_web = $object->array_options['options_password_os'];
	$database_db = $object->database_db;
	if (empty($database_db)) $database_db = $object->array_options['options_database_db'];

	$server=$instance;

	// SFTP refresh
	if (function_exists("ssh2_connect")) {
		$server_port = (! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? $conf->global->SELLYOURSAAS_SSH_SERVER_PORT : 22);

		if ($printoutput) print "ssh2_connect ".$server." ".$server_port." ".$username_web." ".$password_web."\n";

		$connection = ssh2_connect($server, $server_port);
		if ($connection) {
			if ($printoutput) print $instance." ".$username_web." ".$password_web."\n";

			if (! @ssh2_auth_password($connection, $username_web, $password_web)) {
				dol_syslog("Could not authenticate in dolicloud_files_refresh with username ".$username_web." . and password ".preg_replace('/./', '*', $password_web), LOG_ERR);
			} else {
				$sftp = ssh2_sftp($connection);
				if (! $sftp) {
					dol_syslog("Could not execute ssh2_sftp", LOG_ERR);
					$errors[]='Failed to connect to ssh2 to '.$server;
					return 1;
				}

				$dir=preg_replace('/_([a-zA-Z0-9]+)$/', '', $database_db);
				//$file="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_EXT_HOME.'/'.$object->username_web.'/'.$dir.'/htdocs/conf/conf.php';
				//$file="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/'.$dir.'/htdocs/conf/conf.php';    // With PHP 5.6.27+

				// Update ssl certificate
				// Dir .ssh must have rwx------ permissions
				// File authorized_keys_support must have rw------- permissions

				// Check if authorized_keys_support exists
				//$filecert="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_EXT_HOME.'/'.$object->username_web.'/.ssh/authorized_keys_support';
				$filecert="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/.ssh/authorized_keys_support';    // With PHP 5.6.27+
				$fstat=@ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/.ssh/authorized_keys_support');
				// Create authorized_keys_support file
				if (empty($fstat['atime']) || $recreateauthorizekey == 2) {
					if ($recreateauthorizekey) {
						@ssh2_sftp_mkdir($sftp, $conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/.ssh');

						$publickeystodeploy = $conf->global->SELLYOURSAAS_PUBLIC_KEY;

						// We overwrite authorized_keys_support
						if ($printoutput) print 'Write file '.$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/.ssh/authorized_keys_support.'."\n";

						$stream = @fopen($filecert, 'w');
						//var_dump($stream);exit;
						if ($stream) {
							fwrite($stream, $publickeystodeploy);
							fclose($stream);
							$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/.ssh/authorized_keys_support');
						} else {
							$errors[]='Failed to open for write '.$filecert."\n";
						}
					} else {
						if ($printoutput) print 'File '.$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web."/.ssh/authorized_keys_support not found.\n";
					}
				} else {
					if ($printoutput) print 'File '.$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web."/.ssh/authorized_keys_support already exists.\n";
				}
				$object->fileauthorizedkey=(empty($fstat['mtime'])?'':$fstat['mtime']);

				// Check if install.lock exists
				//$fileinstalllock="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_EXT_HOME.'/'.$object->username_web.'/'.$dir.'/documents/install.lock';
				//$fileinstalllock="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/'.$dir.'/documents/install.lock';    // With PHP 5.6.27+
				$fstatlock=@ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/'.$dir.'/documents/install.lock');
				$object->filelock=(empty($fstatlock['atime'])?'':$fstatlock['atime']);

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
 * Process refresh of database for contrat $object
 * This also update database field lastcheck.
 * This set a lot of object->xxx properties
 * 	->lastlogin_admin, ->lastpass_admin,
 * 	->nbofusers,
 * 	->modulesenabled, version, date_lastcheck, lastcheck
 *
 * @param 	Conf			$conf		Conf
 * @param 	Database		$db			Database handler
 * @param 	Contrat 		$object	    Customer (can modify caller)
 * @param	array			$errors	    Array of errors
 * @return	int										1
 */
function dolicloud_database_refresh($conf, $db, &$object, &$errors)
{
	$instance = $object->instance;
	if (empty($instance)) $instance = $object->ref_customer;
	$username_web = $object->username_web;
	if (empty($username_web)) $username_web = $object->array_options['options_username_os'];
	$password_web = $object->password_web;
	if (empty($password_web)) $password_web = $object->array_options['options_password_os'];

	$hostname_db = $object->hostname_db;
	if (empty($hostname_db)) $hostname_db = $object->array_options['options_hostname_db'];
	$port_db = $object->port_db;
	if (empty($port_db)) $port_db = (!empty($object->array_options['options_port_db']) ? $object->array_options['options_port_db'] : 3306);
	$username_db = $object->username_db;
	if (empty($username_db)) $username_db = $object->array_options['options_username_db'];
	$password_db = $object->password_db;
	if (empty($password_db)) $password_db = $object->array_options['options_password_db'];
	$database_db = $object->database_db;
	if (empty($database_db)) $database_db = $object->array_options['options_database_db'];
	$prefix_db = $object->prefix_db;
	if (empty($prefix_db)) $prefix_db = (empty($object->array_options['options_prefix_db']) ? 'llx_' : $object->array_options['options_prefix_db']);

	$server = (! empty($hostname_db) ? $hostname_db : $instance);

	$newdb=getDoliDBInstance('mysqli', $server, $username_db, $password_db, $database_db, $port_db);

	$ret=1;

	unset($object->lastlogin);
	unset($object->lastpass);
	unset($object->date_lastlogin);
	unset($object->date_lastcheck);
	unset($object->lastlogin_admin);
	unset($object->lastpass_admin);
	unset($object->modulesenabled);
	unset($object->version);
	unset($object->nbofusers);

	if (is_object($newdb)) {
		$error=0;
		$done=0;

		if ($newdb->connected && $newdb->database_selected) {
			$sqltocountusers = '';
			// Now search the real SQL request to count users
			foreach ($object->lines as $contractline) {
				if (empty($contractline->fk_product)) continue;
				$producttmp = new Product($db);
				$producttmp->fetch($contractline->fk_product);

				// If this is a line for a metric
				if ($producttmp->array_options['options_app_or_option'] == 'system' && $producttmp->array_options['options_resource_formula']
					&& ($producttmp->array_options['options_resource_label'] == 'User' || preg_match('/user/i', $producttmp->ref))) {
					$dbprefix = ($object->array_options['options_prefix_db']?$object->array_options['options_prefix_db']:'llx_');

					$sqltocountusers = $producttmp->array_options['options_resource_formula'];
					// Example: $sqltocountusers="SELECT COUNT(login) as nb FROM llx_user WHERE statut <> 0 AND login <> '__SELLYOURSAAS_LOGIN_FOR_SUPPORT__';
					$sqltocountusers = preg_replace('/^SQL:/', '', $sqltocountusers);
					$sqltocountusers = preg_replace('/__SELLYOURSAAS_LOGIN_FOR_SUPPORT__/', $conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT, $sqltocountusers);
					$sqltocountusers = preg_replace('/__INSTANCEDBPREFIX__/', $dbprefix, $sqltocountusers);
					break;
				}
			}

			$sqltogetlastloginadmin = "SELECT login, pass, datelastlogin FROM ".$prefix_db."user WHERE admin = 1 AND login <> '".$conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT."' ORDER BY statut DESC, datelastlogin DESC LIMIT 1";
			$sqltogetmodules = "SELECT name, value FROM ".$prefix_db."const WHERE name LIKE 'MAIN_MODULE_%' or name = 'MAIN_VERSION_LAST_UPGRADE' or name = 'MAIN_VERSION_LAST_INSTALL'";
			$sqltogetlastloginuser = "SELECT login, pass, datelastlogin FROM ".$prefix_db."user WHERE statut <> 0 AND login <> '".$conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT."' ORDER BY datelastlogin DESC LIMIT 1";

			// Get user/pass of last admin user
			if (! $error) {
				$resql=$newdb->query($sqltogetlastloginadmin);
				if ($resql) {
					$obj = $newdb->fetch_object($resql);
					$object->lastlogin_admin = $obj->login;
					$object->lastpass_admin = $obj->pass;
					$lastloginadmin = $object->lastlogin_admin;
					$lastpassadmin = $object->lastpass_admin;
				} else $error++;
			}

			// Get list of modules
			if (! $error) {
				$modulesenabled=array(); $lastinstall=''; $lastupgrade='';
				$resql=$newdb->query($sqltogetmodules);
				if ($resql) {
					$num=$newdb->num_rows($resql);
					$i=0;
					while ($i < $num) {
						$obj = $newdb->fetch_object($resql);
						if (preg_match('/MAIN_MODULE_/', $obj->name)) {
							$name=preg_replace('/^[^_]+_[^_]+_/', '', $obj->name);
							if (! preg_match('/_/', $name)) $modulesenabled[$name]=$name;
						}
						if (preg_match('/MAIN_VERSION_LAST_UPGRADE/', $obj->name)) {
							$lastupgrade=$obj->value;
						}
						if (preg_match('/MAIN_VERSION_LAST_INSTALL/', $obj->name)) {
							$lastinstall=$obj->value;
						}
						$i++;
					}
					$object->modulesenabled=join(',', $modulesenabled);
					$object->version=($lastupgrade?$lastupgrade:$lastinstall);
				} else $error++;
			}

			// Get nb of users (special case for hard coded field into some GUI tabs)
			if (! $error) {
				if ($sqltocountusers) {
					$sqlformula = $sqltocountusers;
					$dbinstance = $newdb;
					$newcommentonqty = '';
					$newqty = 0;

					$resql=$newdb->query($sqltocountusers);
					if ($resql) {
						if (preg_match('/^select count/i', $sqlformula)) {
							// If request is a simple SELECT COUNT
							$objsql = $dbinstance->fetch_object($resql);
							if ($objsql) {
								$newqty = $objsql->nb;
								$newcommentonqty .= '';
							} else {
								$error++;
								/*$this->error = 'SQL to get resource return nothing';
								 $this->errors[] = 'SQL to get resource return nothing';*/
								setEventMessages('SQL to get resource return nothing', null, 'errors');
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
								$newcommentonqty .= 'Qty '.$producttmp->ref.' = '.$newqty."\n";
								$newcommentonqty .= 'Note: '.join(', ', $arrayofcomment)."\n";
							} else {
								$error++;
								/*$this->error = 'SQL to get resource return nothing';
								 $this->errors[] = 'SQL to get resource return nothing';*/
								setEventMessages('SQL to get resource return nothing', null, 'errors');
							}
						}

						$object->nbofusers += $newqty;
					} else {
						$error++;
						setEventMessages($newdb->lasterror(), null, 'errors');
					}
				} else {
					setEventMessages('NoResourceToCountUsersFound', null, 'warnings');
				}
			}

			$deltatzserver=(getServerTimeZoneInt()-0)*3600;	// Diff between TZ of NLTechno and DoliCloud

			// Get last login of users
			if (! $error) {
				$resql=$newdb->query($sqltogetlastloginuser);
				if ($resql) {
					$obj = $newdb->fetch_object($resql);

					$object->lastlogin  = $obj->login;
					$object->lastpass   = $obj->pass;
					$object->date_lastlogin = ($obj->datelastlogin ? ($newdb->jdate($obj->datelastlogin)+$deltatzserver) : '');
				} else {
					$error++;
					$errors[]='Failed to connect to database '.$instance.' '.$username_db;
				}
			}

			$done++;
		} else {
			$errors[]='Failed to connect '.$conf->db->type.' '.$instance.' '.$username_db.' '.$password_db.' '.$database_db.' '.$port_db;
			$ret=-1;
		}

		$newdb->close();

		if (! $error && $done) {
			$now=dol_now();
			$object->date_lastcheck=$now;
			$object->lastcheck=$now;	// For backward compatibility

			//$object->array_options['options_filelock']=$now;
			//$object->array_options['options_fileauthorizekey']=$now;
			//$object->array_options['options_latestresupdate_date']=$now;

			$result = $object->update($user);	// persist
			if (method_exists($object, 'update_old')) $result = $object->update_old($user);	// persist

			if ($result < 0) {
				dol_syslog("Failed to persist data on object into database", LOG_ERR);
				if ($object->error) $errors[]=$object->error;
				$errors=array_merge($errors, $object->errors);
			} else {
				//var_dump($object);
			}
		}
	} else {
		$errors[]='Failed to connect '.$conf->db->type.' '.$server.' '.$username_db.' '.$password_db.' '.$database_db.' '.$port_db;
		$ret=-1;
	}

	return $ret;
}


/**
 * Calculate stats ('total', 'totalcommissions', 'totalinstancespaying', 'totalinstancessuspended', 'totalinstancesexpired', 'totalinstances' (nb instances included suspended), 'totalusers')
 * at date datelim (or realtime if date is empty)
 *
 * Rem: Comptage des users par status
 *
 * @param	Database	$db			Database handler
 * @param	integer		$datelim	Date limit
 * @return	array					Array of data
 */
function sellyoursaas_calculate_stats($db, $datelim)
{
	$error = 0;

	$total = $totalcommissions = $totalinstancespaying = $totalinstancespayingall = $totalinstancespayingwithoutrecinvoice = 0;
	$totalinstancesexpiredfree = $totalinstancesexpiredpaying = $totalinstancessuspendedfree = $totalinstancessuspendedpaying = 0;
	$totalinstances = $totalusers = 0;
	$listofinstancespaying=array(); $listofinstancespayingall=array(); $listofinstancespayingwithoutrecinvoice = array();
	$listofcustomers=array(); $listofcustomerspaying=array(); $listofcustomerspayingwithoutrecinvoice=array(); $listofcustomerspayingall=array();
	$listofsuspendedrecurringinvoice=array();

	$nbofinstancedeployed = 0;

	// Get list of deployed instances
	$sql = "SELECT c.rowid as id, c.ref_customer as instance, c.fk_soc as customer_id,";
	$sql.= " ce.deployment_status as instance_status,";
	$sql.= " s.parent, s.nom as name";
	$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object,";
	$sql.= " ".MAIN_DB_PREFIX."societe as s";
	$sql.= " WHERE s.rowid = c.fk_soc AND c.ref_customer <> '' AND c.ref_customer IS NOT NULL";
	$sql.= " AND ce.deployment_status = 'done'";
	$sql.= " AND (ce.suspendmaintenance_message IS NULL OR ce.suspendmaintenance_message NOT LIKE 'http%')";	// Exclude instances of type redirect
	if ($datelim) $sql.= " AND ce.deployment_date_end <= '".$db->idate($datelim)."'";	// Only instances deployed before this date

	dol_syslog("sellyoursaas_calculate_stats begin", LOG_DEBUG, 1);

	$resql=$db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		dol_syslog("sellyoursaas_calculate_stats found ".$num." record", LOG_DEBUG, 1);

		$i = 0;
		if ($num) {
			include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
			dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');

			$now = dol_now();
			$object = new Contrat($db);
			$templateinvoice = new FactureRec($db);
			//$cacheofthirdparties = array();

			// Loop on all deployed instances
			while ($i < $num) {
				$obj = $db->fetch_object($resql);
				if ($obj) {
					// We process the instance (ref_customer)
					$instance = $obj->instance;

					unset($object->linkedObjects);
					unset($object->linkedObjectsIds);

					// Load data of instance and set $instance_status (PROCESSING, DEPLOYED, SUSPENDED, UNDEPLOYED)
					$instance_status = 'UNKNOWN';
					$result = $object->fetch($obj->id);
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
							$issuspended = sellyoursaasIsSuspended($object);
							if ($issuspended) {
								$instance_status = 'SUSPENDED';
							}
							// Note: to check that all non deployed instance has line with status that is 5 (close), you can run
							// select * from llx_contrat as c, llx_contrat_extrafields as ce, llx_contratdet as cd WHERE ce.fk_object = c.rowid
							// AND cd.fk_contrat = c.rowid AND ce.deployment_status <> 'done' AND cd.statut <> 5;
							// You should get nothing.
						}


						// Load array $tmpdata
						$tmpdata = sellyoursaasGetExpirationDate($object, 0);
						$nbofuser = $tmpdata['nbusers'];
						$totalinstances++;
						$totalusers+=$nbofuser;


						// Set $payment_status ('TRIAL', 'PAID' or 'FAILURE')
						$payment_status='PAID';
						$ispaid = sellyoursaasIsPaidInstance($object, 0, 0);	// Return true if instance $object is paying instance (invoice or template invoice exists)
						if (! $ispaid) {		// This is a test only customer, trial expired or not, suspended or not (just no invoice or template invoice at all)
							$payment_status='TRIAL';

							// Count $totalinstancesexpiredfree and $totalinstancessuspendedfree
							if ($tmpdata['expirationdate'] < $now) {
								$totalinstancesexpiredfree++;
							}
							if ($tmpdata['status'] == ContratLigne::STATUS_CLOSED) {		// Status of line with package app
								$totalinstancessuspendedfree++;
							}

							$listofcustomers[$obj->customer_id]++;	// This is in fact the total of trial only customers
							//print "cpt=".$totalinstances." customer_id=".$obj->customer_id." instance=".$obj->instance." status=".$obj->status." instance_status=".$obj->instance_status." payment_status=".$obj->payment_status." => Price = ".$obj->price_instance.' * '.($obj->plan_meter_id == 1 ? $obj->nbofusers : 1)." + ".max(0,($obj->nbofusers - $obj->min_threshold))." * ".$obj->price_user." = ".$price." -> 0<br>\n";
						} else {
							$ispaymentko = sellyoursaasIsPaymentKo($object);
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
							if (is_array($object->linkedObjectsIds['facturerec'])) {		// $object->linkedObjects loaded by the previous sellyoursaasIsPaidInstance
								foreach ($object->linkedObjectsIds['facturerec'] as $rowidelementelement => $idtemplateinvoice) {
									$templateinvoice->suspended = 0;
									$templateinvoice->unit_frequency = 0;
									$templateinvoice->total_ht = 0;
									$templateinvoice->frequency = 0;

									$result = $templateinvoice->fetch($idtemplateinvoice);
									if ($result > 0) {
										if (! $templateinvoice->suspended) {
											if ($templateinvoice->unit_frequency == 'm' && $templateinvoice->frequency >= 1) {
												$price += $templateinvoice->total_ht / $templateinvoice->frequency;
												$price_ttc += $templateinvoice->total_ttc / $templateinvoice->frequency;
											} elseif ($templateinvoice->unit_frequency == 'y' && $templateinvoice->frequency >= 1) {
												$price += ($templateinvoice->total_ht / (12 * $templateinvoice->frequency));
												$price_ttc += ($templateinvoice->total_ttc / (12 * $templateinvoice->frequency));
											} else {
												$price += $templateinvoice->total_ht;
												$price_ttc += $templateinvoice->total_ttc;
											}

											$atleastonenotsuspended = 1;
										} else {
											$listofsuspendedrecurringinvoice[$idtemplateinvoice]=$idtemplateinvoice;
										}
									} else {
										$error++;
										dol_print_error($db);
									}
								}
							}

							if (! $atleastonenotsuspended) {	// Really a paying customer if template invoice not suspended
								$listofcustomerspayingwithoutrecinvoice[$obj->customer_id]++;
								$listofinstancespayingwithoutrecinvoice[$object->id]=array('thirdparty_id'=>$obj->customer_id, 'thirdparty_name'=>$obj->name, 'contract_ref'=>$object->ref);
								$totalinstancespayingwithoutrecinvoice++;
							}

							$listofcustomerspayingall[$obj->customer_id]++;
							$listofinstancespayingall[$object->id]=array('thirdparty_id'=>$obj->customer_id, 'thirdparty_name'=>$obj->name, 'contract_ref'=>$object->ref);
							$totalinstancespayingall++;

							if ($atleastonenotsuspended && $instance_status != 'SUSPENDED' && $payment_status != 'FAILURE' && $tmpdata['status'] != ContratLigne::STATUS_CLOSED) {
								// If service really paying and not suspended and no payment error
								$total += $price;

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

	dol_syslog("sellyoursaas_calculate_stats end", LOG_DEBUG, -1);

	//var_dump($listofinstancespaying);
	return array(
		'total'=>(double) $total,
		'totalcommissions'=>(double) $totalcommissions,
		'totalinstancespaying'=>(int) $totalinstancespaying,
		'totalinstancespayingall'=>(int) $totalinstancespayingall,
		'totalinstancespayingwithoutrecinvoice'=>(int) $totalinstancespayingwithoutrecinvoice,
		'totalinstancessuspendedfree'=>(int) $totalinstancessuspendedfree,
		'totalinstancessuspendedpaying'=>(int) $totalinstancessuspendedpaying,
		'totalinstancesexpiredfree'=>(int) $totalinstancesexpiredfree,
		'totalinstancesexpired'=>(int) $totalinstancesexpiredpaying,
		'totalinstances'=>(int) $totalinstances,						// Total instances (trial + paid)
		'totalusers'=>(int) $totalusers,								// Total users (trial + paid)
		'totalcustomers'=>(int) count($listofcustomers),				// Trial only customers
		'totalcustomerspaying'=>(int) count($listofcustomerspaying),	// Paying customers
		'listofinstancespaying'=>$listofinstancespaying,
		'listofinstancespayingall'=>$listofinstancespayingall,
		'listofinstancespayingwithoutrecinvoice'=>$listofinstancespayingwithoutrecinvoice,
		'listofsuspendedrecurringinvoice'=>$listofsuspendedrecurringinvoice
	);
}
