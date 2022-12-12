<?php
/* Copyright (C) 2011-2018 Laurent Destailleur <eldy@users.sourceforge.net>
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

// Protection to avoid direct call of template
if (empty($conf) || ! is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}

require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/files.lib.php";
dol_include_once('sellyoursaas/class/packages.class.php');

?>
<!-- BEGIN PHP TEMPLATE autoupgrade.tpl.php -->
<?php


$upload_dir = $conf->sellyoursaas->dir_temp."/autoupgrade_".$mythirdpartyaccount->id.'.tmp';
$backtopagesupport = GETPOST("backtopagesupport", 'alpha') ? GETPOST("backtopagesupport", 'alpha') : $_SERVER["PHP_SELF"].'?action=presend&mode=support&backfromautoupgrade=backfromautoupgrade&token='.newToken().'&contractid='.GETPOST('contractid', 'alpha').'&supportchannel='.GETPOST('supportchannel', 'alpha').'&ticketcategory_child_id='.(GETPOST('ticketcategory_child_id_back', 'alpha')?:GETPOST('ticketcategory_child_id', 'alpha')).'&ticketcategory='.(GETPOST('ticketcategory_back', 'alpha')?:GETPOST('ticketcategory', 'alpha')).'&subject='.(GETPOST('subject_back', 'alpha')?:GETPOST('subject', 'alpha'));
$arraybacktopage=explode("&", $backtopagesupport);
$ticketcategory_child_id = "";
$ticketcategory = "";
$stepautoupgrade = GETPOST("stepautoupgrade") ? GETPOST("stepautoupgrade") : 1;
$errortab = array();
$errors = 0;
$stringoflistofmodules = "";


// Check of the prerequisites (step 3)
if ($action == "instanceverification") {
	$confinstance = 0;

	$object = new Contrat($db);
	$instanceselect = GETPOST("instanceselect", "alpha");
	$instanceselect = explode("_", $instanceselect);
	$idcontract = $instanceselect[1];

	if ($idcontract > 0) {
		$result=$object->fetch($idcontract);
		if ($result < 0) {
			$errortab[] = $langs->trans("InstanceNotFound");
			$errors++;
		}
		if (!$error) {
			$object->fetch_thirdparty();

			$type_db = $conf->db->type;
			$hostname_db  = $object->array_options['options_hostname_db'];
			$username_db  = $object->array_options['options_username_db'];
			$password_db  = $object->array_options['options_password_db'];
			$database_db  = $object->array_options['options_database_db'];
			$port_db      = (!empty($object->array_options['options_port_db']) ? $object->array_options['options_port_db'] : 3306);
			$prefix_db    = (!empty($object->array_options['options_prefix_db']) ? $object->array_options['options_prefix_db'] : 'llx_');
			$hostname_os  = $object->array_options['options_hostname_os'];
			$username_os  = $object->array_options['options_username_os'];
			$password_os  = $object->array_options['options_password_os'];
			$username_web = $object->thirdparty->email;
			$password_web = $object->thirdparty->array_options['options_password'];

			$newdb = getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
			$newdb->prefix_db = $prefix_db;

			if (is_object($newdb) && $newdb->connected) {
				$confinstance = new Conf();
				$confinstance->setValues($newdb);
				$lastinstallinstance = empty($confinstance->global->MAIN_VERSION_LAST_INSTALL) ? '' : $confinstance->global->MAIN_VERSION_LAST_INSTALL;
				$lastupgradelinstance = empty($confinstance->global->MAIN_VERSION_LAST_UPGRADE) ? '' : $confinstance->global->MAIN_VERSION_LAST_UPGRADE;
				$laststableupgradeversion = getDolGlobalString("SELLYOURSAAS_LAST_STABLE_VERSION_DOLIBARR");
				if (!empty($laststableupgradeversion)) {
					$match = '/^'.getDolGlobalString("SELLYOURSAAS_LAST_STABLE_VERSION_DOLIBARR").'.*/';
					if (preg_match($match, $lastinstallinstance) || preg_match($match, $lastupgradelinstance)) {
						$errortab[] = $langs->trans("ErrorAlreadyLastStableVersion");
						$errors++;
					}
				} else {
					$dataofcontract = sellyoursaasGetExpirationDate($object, 0);
					$tmpproduct = new Product($db);
					$tmppackage = new Packages($db);

					if ($dataofcontract['appproductid'] > 0) {
						$tmpproduct->fetch($dataofcontract['appproductid']);
						$tmppackage->fetch($tmpproduct->array_options['options_package']);
					}
					$dirforexampleforsources = preg_replace('/__DOL_DATA_ROOT__/', DOL_DATA_ROOT, preg_replace('/\/htdocs\/?$/', '', $tmppackage->srcfile1));
					$dirforexampleforsourcesinstalldir = $dirforexampleforsources.'/htdocs/install/mysql/migration/';
					$filelist = dol_dir_list($dirforexampleforsourcesinstalldir, 'files');
					$laststableupgradeversion = 0;
					foreach ($filelist as $key => $value) {
						$version = explode("-", $value["name"])[1];
						$version = explode(".", $version)[0];
						$laststableupgradeversion = max($laststableupgradeversion, $version);
					}
					$match = '/^'.$laststableupgradeversion.'.*/';
					if (preg_match($match, $lastinstallinstance) || preg_match($match, $lastupgradelinstance)) {
						$errortab[] = $langs->trans("ErrorAlreadyLastStableVersion");
						$errors++;
					}
				}

				// Search of external modules
				$nbexternalmodules=0;
				$modulestodesactivate = "";
				$arraycoremodules = array();
				$namemodule = array();
				$listmodules = dol_dir_list(DOL_DOCUMENT_ROOT."/core/modules/", "files");
				foreach ($listmodules as $key => $module) {
					preg_match('/mod([[:upper:]].*)\.class\.php/', $module["name"], $namemodule);
					if (!empty($namemodule)) {
						$arraycoremodules[] = strtoupper($namemodule[1]);
					}
				}
				foreach ($confinstance->global as $key => $val) {
					if (preg_match('/^MAIN_MODULE_[^_]+$/', $key) && !empty($val)) {
						$moduletotest=preg_replace('/MAIN_MODULE_/', "", $key);
						if (!in_array($moduletotest, $arraycoremodules)) {
							if ($nbexternalmodules != 0) {
								$modulestodesactivate .= ",";
							}
							$modulestodesactivate .= $moduletotest;
							$nbexternalmodules++;
						}
					}
				}
				if ($nbexternalmodules != 0) {
					$errortab[] = $langs->trans("ExternalModulesNeedDisabled", $modulestodesactivate);
					$errors++;
				}
			} else {
				$errortab[] = $langs->trans("NewDbConnexionError");
				$errors++;
			}
		}
	} else {
		$errortab[] = $langs->trans("InstanceNotFound");
		$errors++;
	}
}

if ($action == "autoupgrade") {
	require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
	require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
	include_once DOL_DOCUMENT_ROOT."/core/class/utils.class.php";
	dol_include_once('/sellyoursaas/class/sellyoursaasutils.class.php');
	dol_include_once('sellyoursaas/class/packages.class.php');

	$utils = new Utils($db);
	$sellyoursaasutils = new SellYourSaasUtils($db);

	// If error occurs this is usefull to redirect to support page
	$keyticketcategory_child_id = array_keys(preg_grep('/ticketcategory_child_id.*/', $arraybacktopage))[0];
	$keyticketcategory = array_keys(preg_grep('/ticketcategory=.*/', $arraybacktopage))[0];
	$ticketcategory_child_id = explode("=", $arraybacktopage[$keyticketcategory_child_id])[1];
	$ticketcategory = explode("=", $arraybacktopage[$keyticketcategory])[1];

	$object = new Contrat($db);
	$instanceselect = GETPOST("instanceselect", "alpha");
	$instanceselect = explode("_", $instanceselect);
	$idcontract = $instanceselect[1];
	if ($idcontract > 0) {
		$result=$object->fetch($idcontract);
		if ($result < 0) {
			$errortab[] = $langs->trans("InstanceNotFound");
			$errors++;
		}

		$object->fetch_thirdparty();

		// TODO Check the thirdparty of instance is same than logged thirdparty
	}

	if (!$error) {
		$type_db = $conf->db->type;
		$hostname_db  = $object->array_options['options_hostname_db'];
		$username_db  = $object->array_options['options_username_db'];
		$password_db  = $object->array_options['options_password_db'];
		$database_db  = $object->array_options['options_database_db'];
		$port_db      = (!empty($object->array_options['options_port_db']) ? $object->array_options['options_port_db'] : 3306);
		$prefix_db    = (!empty($object->array_options['options_prefix_db']) ? $object->array_options['options_prefix_db'] : 'llx_');
		$hostname_os  = $object->array_options['options_hostname_os'];
		$username_os  = $object->array_options['options_username_os'];
		$password_os  = $object->array_options['options_password_os'];
		$username_web = $object->thirdparty->email;
		$password_web = $object->thirdparty->array_options['options_password'];

		$tmp = explode('.', $object->ref_customer, 2);
		$object->instance = $tmp[0];

		$object->hostname_db  = $hostname_db;
		$object->username_db  = $username_db;
		$object->password_db  = $password_db;
		$object->database_db  = $database_db;
		$object->port_db      = $port_db;
		$object->prefix_db    = $prefix_db;
		$object->hostname_os  = $hostname_os;
		$object->username_os  = $username_os;
		$object->password_os  = $password_os;
		$object->username_web = $username_web;
		$object->password_web = $password_web;

		$dataofcontract = sellyoursaasGetExpirationDate($object, 0);
		$tmpproduct = new Product($db);
		$tmppackage = new Packages($db);

		$newdb = getDoliDBInstance($type_db, $hostname_db, $username_db, $password_db, $database_db, $port_db);
		$newdb->prefix_db = $prefix_db;
		$lastversiondolibarrinstance = "";
		if (is_object($newdb) && $newdb->connected) {
			$confinstance = new Conf();
			$confinstance->setValues($newdb);
			$lastinstallinstance = isset($confinstance->global->MAIN_VERSION_LAST_INSTALL) ? explode(".", $confinstance->global->MAIN_VERSION_LAST_INSTALL)[0] : "0";
			$lastupgradeinstance = isset($confinstance->global->MAIN_VERSION_LAST_UPGRADE) ? explode(".", $confinstance->global->MAIN_VERSION_LAST_UPGRADE)[0] : "0";
			$lastversiondolibarrinstance = max($lastinstallinstance, $lastupgradeinstance);
		}

		if ($dataofcontract['appproductid'] > 0) {
			$tmpproduct->fetch($dataofcontract['appproductid']);
			$tmppackage->fetch($tmpproduct->array_options['options_package']);
			$dirforexampleforsources = preg_replace('/__DOL_DATA_ROOT__/', DOL_DATA_ROOT, preg_replace('/\/htdocs\/?$/', '', $tmppackage->srcfile1));
			$dirforexampleforsourcesinstalldir = $dirforexampleforsources.'/htdocs/install/mysql/migration/';
			$filelist = dol_dir_list($dirforexampleforsourcesinstalldir, 'files');
			$laststableupgradeversion = 0;
			foreach ($filelist as $key => $value) {
				$version = explode("-", $value["name"])[1];
				$version = explode(".", $version)[0];
				$laststableupgradeversion = max($laststableupgradeversion, $version);
			}
			$object->array_options["dirforexampleforsources"] = $dirforexampleforsources;
			$object->array_options["laststableupgradeversion"] = $laststableupgradeversion;
			$object->array_options["lastversiondolibarrinstance"] = $lastversiondolibarrinstance;
		} else {
			$errortab[] = $langs->trans("ErrorFetchingProductOrPackage");
			$errors++;
		}
	}

	if (!$errors) {
		$comment = 'Call of sellyoursaasRemoteAction(upgrade) on contract ref='.$object->ref;
		$notused = '';
		$exitcode = $sellyoursaasutils->sellyoursaasRemoteAction("upgrade", $object, 'admin', $notused, $notused, 1, $comment);
		if ($exitcode < 0) {
			$errors++;
			$errortab[] = $langs->trans("ErrorOnUpgradeScript");
			setEventMessages($langs->trans("ErrorOnUpgradeScript"), null, "errors");
		}

		// TODO Add an entry into actioncomm
		/*
		print "Create event into database\n";
		dol_syslog("Add event into database");

		$user = new User($db);
		$user->fetch($conf->global->SELLYOURSAAS_ANONYMOUSUSER);

		if ($user->id > 0) {
			$actioncomm=new ActionComm($db);
			if (is_object($object->thirdparty)) $actioncomm->socid=$object->thirdparty->id;
			$actioncomm->datep=dol_now('tzserver');
			$actioncomm->percentage=100;
			$actioncomm->label=($errors > 0 ? 'ERROR ': '').'Upgrade instance='.$instance.' dirroot='.$dirroot.' mode='.$mode.' from myaccount';
			$actioncomm->note_private='Upgrade instance='.$instance.' dirroot='.$dirroot.' mode='.$mode.' from myaccount'.($errors > 0 ? ' - errors='.$errors : '') ;
			$actioncomm->fk_element=$object->id;
			$actioncomm->elementtype='contract';
			$actioncomm->type_code='AC_OTH_AUTO';
			$actioncomm->userassigned[$user->id]=array('id'=>$user->id);
			$actioncomm->userownerid=$user->id;
			$actioncomm->create($user);
		}
		*/
	}
}


print '
<div class="page-content-wrapper">
    <div class="page-content">
    <!-- BEGIN PAGE HEADER-->
    <!-- BEGIN PAGE HEAD -->
        <div class="page-head">
        <!-- BEGIN PAGE TITLE -->
            <div class="page-title">
            <h1>'.$langs->trans("Autoupgrade").' <small>'.$langs->trans("AutoupgradeDesc", (!empty(getDolGlobalString("SELLYOURSAAS_LAST_STABLE_VERSION_DOLIBARR"))?"(v".getDolGlobalString("SELLYOURSAAS_LAST_STABLE_VERSION_DOLIBARR").")":"")).'</small></h1>
            </div>
        <!-- END PAGE TITLE -->
        </div>
    <!-- END PAGE HEAD -->
    <!-- END PAGE HEADER-->';

print'
    <div class="page-body">
    <div class="row" id="choosechannel">
    <div class="col-md-12">';

// Show result of check of prerequisites
if ($action == "instanceverification") {
	print '<!-- BEGIN STEP3-->
		<div class="portlet light divstep " id="Step3">
		<h2>'.$langs->trans("Step", 3).' - '.$langs->trans("UpgradeVerification").'</small></h2><br>';
	print '<div class="center">';
	print '<h4>';
	if ($errors) {
		print '<span style="color:red">'.$langs->trans('Error').'</span>';
	} else {
		print '<span style="color:green">'.$langs->trans('PrerequisitesOK').'</span>';
	}
	print '</h4>';
	print'</div>';
	if ($errors) {
		print '<br><div class="portlet dark" style="width:50%;margin-left:auto;margin-right:auto;">';
		print $langs->trans("ErrorListSupport").' :<br>';
		print '<ul style="list-style-type:\'-\';">';
		foreach ($errortab as $key => $error) {
			print '<li>';
			print $error;
			print '</li><br>';
		}
		print '</ul></div>';
		print '<div class="center"><a href="'.$backtopagesupport.'"><button type="button" class="btn green-haze btn-circle">'.$langs->trans("CancelUpgradeAndBacktoSupportPage").'</button></a></div>';
	} else {
		print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		print '<input type="hidden" name="action" value="autoupgrade">';
		print '<input type="hidden" name="mode" value="autoupgrade">';
		print '<input type="hidden" name="backtopagesupport" value="'.$backtopagesupport.'">';
		print '<input type="hidden" name="instanceselect" value="'.GETPOST("instanceselect", "alpha").'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<br><h4 class="center">'.$langs->trans("AutoupgradeStep3Text").'...</h4>';
		print '<br><div class="containerflexautomigration">
					<div class="right containerflexautomigrationitem paddingright paddingleft">
						<button id="" type="submit" class="btn green-haze btn-circle btnstep" onclick="applywaitMask()">'.$langs->trans("ConfirmAutoupgrade").'</button>
					</div>
					<div class="left containerflexautomigrationitem paddingright paddingleft">
						<a href="'.$backtopagesupport.'"><button type="button" class="btn green-haze btn-circle">'.$langs->trans("CancelUpgradeAndBacktoSupportPage").'</button></a>
					</div>
				</div>';
		print '</form>';
		print '<script>
			function applywaitMask(){
				$(\'#waitMask\').children().first().contents()[0].nodeValue = "'.$langs->trans("MigrationInProgress").'"
				$(\'#waitMask\').show();
				$(\'#waitMask\').attr(\'style\',\'opacity:0.8\');
			}
			</script>';
	}
	print '</div>';
	print'</div>';
	print '<!-- END STEP3-->';
} elseif ($action == "autoupgrade") {
	print '<!-- BEGIN STEP4-->';
	print '<div class="portlet light divstep " id="Step4">';
	if ($errors) {
		$upgradeerrormessage = $langs->trans("UpgradeErrorContent");
		$upgradeerrormessage .= "\n";
		$upgradeerrormessage .= "\n-------------------";
		$upgradeerrormessage .= "\nTimestamp: ".dol_print_date(dol_now(), "standard", 'gmt').' UTC';
		$upgradeerrormessage .= "\nErrors: ".implode(",", $errortab);
		print '<h2 class="center" style="color:red">';
		print $langs->trans("AutoupgradeError");
		print '</h2><br>';
		print '<div>';
		print $langs->trans("AutoupgradeErrorText");
		print '<br><br><br><form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		print '<div class="center">';
		print '<input type="submit" class="btn green-haze btn-circle" value="'.$langs->trans("BackToSupport").'">';
		print '</div>';
		print '<input type="hidden" name="action" value="presend">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="mode" value="support">';
		print '<input type="hidden" name="contractid" value="'.$object->id.'">';
		print '<input type="hidden" name="supportchannel" value="'.GETPOST('instanceselect', 'alpha').'">';
		print '<input type="hidden" name="backfromautomigration" value="backfromautomigration">';
		print '<input type="hidden" name="ticketcategory_child_id" value="'.(!empty($ticketcategory_child_id) ? $ticketcategory_child_id : GETPOST('ticketcategory_child_id', 'alpha')).'">';
		print '<input type="hidden" name="ticketcategory" value="'.(!empty($ticketcategory) ? $ticketcategory : GETPOST('ticketcategory', 'alpha')).'">';
		print '<input type="hidden" name="subject" value="'.$langs->trans("UpgradeErrorSubject").'">';
		print '<input type="hidden" name="content" value="'.$upgradeerrormessage.'">';
		print '</form>';
		print '</div>';
	} else {
		print '<h2 class="center" style="color:green">';
		print $langs->trans("AutoupgradeSucess");
		print '</h2><br>';
		print '<div>';
		print $langs->trans("AutoupgradeSucessText");
		print '&nbsp;<a href="https://'.$object->ref_customer.'">'.$object->ref_customer.'</a>';
		print '</div><br>';
		print '<div style="color:#bbaf01">';
		print $langs->trans("AutoupgradeSucessNote");
		print '</div>';
	}
	print '</div>';
	print '<!-- END STEP4-->';
} else {
	$idcontract = 0;
	$instanceselect = GETPOST("instanceselect", "alpha");
	if ($instanceselect) {
		$instanceselect = explode("_", $instanceselect);
		$idcontract = empty($instanceselect[1]) ? 0 : $instanceselect[1];
	}

	$newversion = (getDolGlobalString("SELLYOURSAAS_LAST_STABLE_VERSION_DOLIBARR") ? "(v".getDolGlobalString("SELLYOURSAAS_LAST_STABLE_VERSION_DOLIBARR").")" : "");

	if ($idcontract > 0) {
		$object = new Contrat($db);

		$result=$object->fetch($idcontract);

		$dataofcontract = sellyoursaasGetExpirationDate($object, 0);
		$tmpproduct = new Product($db);
		$tmppackage = new Packages($db);

		if ($dataofcontract['appproductid'] > 0) {
			$tmpproduct->fetch($dataofcontract['appproductid']);
			$tmppackage->fetch($tmpproduct->array_options['options_package']);

			//$tmppackage->srcfile1 = 'ddd_16.0';
			//var_dump($tmppackage->srcfile1);
			$newversion = preg_replace('/[^0-9\.]/', '', $tmppackage->srcfile1);
		}
	}

	print '<form action="'.$_SERVER["PHP_SELF"].'#Step'.($stepautoupgrade+1).'" method="GET">';
	print '<input type="hidden" name="backtopagesupport" value="'.$backtopagesupport.'">';
	print '<input type="hidden" name="action" value="'.($stepautoupgrade == 2 ? 'instanceverification' : 'view').'">';
	print '<input type="hidden" name="mode" value="autoupgrade">';
	print '<input type="hidden" name="stepautoupgrade" value="'.($stepautoupgrade+1).'">';
	print '<!-- BEGIN STEP1-->
		<div class="portlet light divstep " id="Step1">
				<h2>'.$langs->trans("Step", 1).' - '.$langs->trans("InstanceConfirmation").'</small></h1><br>
				<div>
				'.$langs->trans("AutoupgradeStep1Text").'...<br><br>
				</div>
				<div class="center" style="padding-top:10px">';
				print '<select id="instanceselect" name="instanceselect" class="minwidth600 maxwidth700" required="required">';
				print '<option value="">&nbsp;</option>';
	if (count($listofcontractid) == 0) {
		// Should not happen
	} else {
		$atleastonehigh=0;
		$atleastonefound=0;

		foreach ($listofcontractid as $id => $contract) {
							$planref = $contract->array_options['options_plan'];
							$statuslabel = $contract->array_options['options_deployment_status'];
							$instancename = preg_replace('/\..*$/', '', $contract->ref_customer);
							$dbprefix = $contract->array_options['options_prefix_db'];
							if (empty($dbprefix)) $dbprefix = 'llx_';

			if ($statuslabel == 'undeployed') {
				continue;
			}

							// Get info about PLAN of Contract
							$planlabel = $planref;		// By default but we will take ref and label of service of type 'app' later

							$planid = 0;
							$freeperioddays = 0;
							$directaccess = 0;

							$tmpproduct = new Product($db);
			foreach ($contract->lines as $keyline => $line) {
				if ($line->statut == 5 && $contract->array_options['options_deployment_status'] != 'undeployed') {
									$statuslabel = 'suspended';
				}

				if ($line->fk_product > 0) {
					$tmpproduct->fetch($line->fk_product);

					if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
						$planref = $tmpproduct->ref;			// Warning, ref is in language of user
						$planlabel = $tmpproduct->label;		// Warning, label is in language of user
						$planid = $tmpproduct->id;
						$freeperioddays = $tmpproduct->array_options['options_freeperioddays'];
						$directaccess = $tmpproduct->array_options['options_directaccess'];
						break;
					}
				}
			}

			$ispaid = sellyoursaasIsPaidInstance($contract);

			$color = "green";
			if ($statuslabel == 'processing') { $color = 'orange'; }
			if ($statuslabel == 'suspended') { $color = 'orange'; }
			if ($statuslabel == 'undeployed') { $color = 'grey'; }
			if (preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) { $color = 'lightgrey'; }

			if ($tmpproduct->array_options['options_typesupport'] != 'none'
				&& !preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
				if (! $ispaid) {	// non paid instances
					$priority = 'low';
					$prioritylabel = '<span class="prioritylow">'.$langs->trans("Priority").' '.$langs->trans("Low").'</span> <span class="opacitymedium">'.$langs->trans("Trial").'</span>';
				} else {
					if ($ispaid) {	// paid with level Premium
						if ($tmpproduct->array_options['options_typesupport'] == 'premium') {
							$priority = 'high';
							$prioritylabel = '<span class="priorityhigh">'.$langs->trans("Priority").' '.$langs->trans("High").'</span>';
							$atleastonehigh++;
						} else {	// paid with level Basic
							$priority = 'medium';
							$prioritylabel = '<span class="prioritymedium">'.$langs->trans("Priority").' '.$langs->trans("Medium").'</span>';
						}
					}
				}

				$optionid = $priority.'_'.$id;
				$labeltoshow = '';
				$labeltoshow .= $langs->trans("Instance").' <strong>'.$contract->ref_customer.'</strong> ';
				//$labeltoshow = $tmpproduct->label.' - '.$contract->ref_customer.' ';
				//$labeltoshow .= $tmpproduct->array_options['options_typesupport'];
				//$labeltoshow .= $tmpproduct->array_options['options_typesupport'];
				$labeltoshow .= ' - ';
				$labeltoshow .= $prioritylabel;

				print '<option value="'.$optionid.'"'.(GETPOST('instanceselect', 'alpha') == $optionid ? ' selected="selected"':'').'" data-html="'.dol_escape_htmltag($labeltoshow).'">';
				print dol_escape_htmltag($labeltoshow);
				print '</option>';
				print ajax_combobox('instanceselect', array(), 0, 0, 'resolve');

				$atleastonefound++;
			}
		}
	}
	print'</select><br><br>';
	print'</div>
			<div class="center divstep1upgrade"'.(!GETPOST('instanceselect', 'alpha') ?' style="display:none;"':'').'>
			<h4><div class="note note-warning">
			'.$langs->trans("AutoupgradeStep1Warning").'
			</div></h4>
			<div class="bold">
			'.$langs->trans("AutoupgradeStep1Note").'
			</div>
			</div><br>
			<div id="buttonstep1upgrade" class="containerflexautomigration"'.(!GETPOST('instanceselect', 'alpha') ?' style="display:none;"':'').'>
					<div class="right containerflexautomigrationitem paddingright paddingleft">
						<button id="buttonstep_2" type="submit" class="btn green-haze btn-circle btnstep">'.$langs->trans("NextStep").'</button>
					</div>
					<div class="left containerflexautomigrationitem paddingright paddingleft">
						<a href="'.$backtopagesupport.'"><button type="button" class="btn green-haze btn-circle">'.$langs->trans("CancelUpgradeAndBacktoSupportPage").'</button></a>
					</div>
				</div>
		</div>
		<!-- END STEP1-->';

	print '<!-- BEGIN STEP2-->
			<div id="Step2"></div>
			<div '.($stepautoupgrade <= 1 ? 'style="display:none;"' : '').'class="portlet light divstep" id="step2">
					<h2>'.$langs->trans("Step", 2).' - '.$langs->trans("VersionConfirmation").'</small></h1><br>
					<div>';

	print $langs->trans("AutoupgradeStep2Text", $newversion).'
					</div>
					<br>
					<div class="center">
					<div class="containerflexautomigration">
						<div class="right containerflexautomigrationitem paddingright paddingleft">
							<button id="buttonstep_3" type="submit" class="btn green-haze btn-circle btnstep">'.$langs->trans("NextStep").'</button>
						</div>
						<div class="left containerflexautomigrationitem paddingright paddingleft">
							<a href="'.$backtopagesupport.'"><button type="button" class="btn green-haze btn-circle">'.$langs->trans("CancelUpgradeAndBacktoSupportPage").'</button></a>
						</div>
					</div>
			</div>';
	print'<!-- END STEP2-->';
	print '</form>';

	print '<script>
		jQuery(document).ready(function() {
			$("#instanceselect").on("change",function(){
				console.log("change on instanceselect");
				if ($(this).val() != "") {
					$(".divstep1upgrade").show();
					$("#buttonstep1upgrade").show();
				} else {
					$(".divstep1upgrade").hide();
					$("#buttonstep1upgrade").hide();
				}
			});
		})
	</script>';
}
print "<style>
	* {
		scroll-behavior: smooth !important;
	}
	.topmarginstep{
		margin-top:100px;
	}
	.containerflexautomigration {
		display: flex;
		justify-content:center;
		flex-wrap: wrap;
	}
	.containerflexautomigrationitem {
		padding-bottom: 10px;
	}
	</style>";
print'</div>
	</div>
	</div>
	</div>
	</div>';
?>
<!-- END PHP TEMPLATE autoupgrade.tpl.php -->