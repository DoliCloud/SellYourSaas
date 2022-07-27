<?php
/* Copyright (C) 2012-2020 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *     \file       htdocs/sellyoursaas/admin/setup.php
 *     \brief      Page administration module SellYourSaas
 */


if (! defined('NOSCANPOSTFORINJECTION')) define('NOSCANPOSTFORINJECTION', '1');		// Do not check anti CSRF attack test


// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/files.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/images.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/geturl.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formticket.class.php";
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";
dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');

// Access control
if (! $user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$langs->loadLangs(array("admin", "errors", "install", "sellyoursaas@sellyoursaas"));

//exit;

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('sellyoursaas-setup'));

$tmpservices=array();
$tmpservicessub = explode(',', getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES'));
foreach ($tmpservicessub as $key => $tmpservicesub) {
	$tmpservicesub = preg_replace('/:.*$/', '', $tmpservicesub);
	if ($key > 0) $tmpservices[$tmpservicesub]=getDomainFromURL($tmpservicesub, 1);
	else $tmpservices['0']=getDomainFromURL($tmpservicesub, 1);
}
$arrayofsuffixfound = array();
foreach ($tmpservices as $key => $tmpservice) {
	$suffix = '';
	if ($key != '0') $suffix='_'.strtoupper(str_replace('.', '_', $tmpservice));

	if (in_array($suffix, $arrayofsuffixfound)) continue;
	$arrayofsuffixfound[$tmpservice] = $suffix;
}


/*
 * Actions
 */

if ($action == 'set') {
	$error=0;

	if (! $error) {
		//dolibarr_set_const($db,'SELLYOURSAAS_FORCE_STRIPE_TEST',GETPOST("SELLYOURSAAS_FORCE_STRIPE_TEST",'int'),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db, 'SELLYOURSAAS_MAIN_FAQ_URL', GETPOST("SELLYOURSAAS_MAIN_FAQ_URL", 'custom', 0, FILTER_VALIDATE_URL), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_NAME", GETPOST("SELLYOURSAAS_NAME"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_MAIN_DOMAIN_NAME", GETPOST("SELLYOURSAAS_MAIN_DOMAIN_NAME"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_NAMES", GETPOST("SELLYOURSAAS_SUB_DOMAIN_NAMES"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SUB_DOMAIN_IP", GETPOST("SELLYOURSAAS_SUB_DOMAIN_IP"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_MAIN_EMAIL", GETPOST("SELLYOURSAAS_MAIN_EMAIL"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_MAIN_EMAIL_PREMIUM", GETPOST("SELLYOURSAAS_MAIN_EMAIL_PREMIUM"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SUPERVISION_EMAIL", GETPOST("SELLYOURSAAS_SUPERVISION_EMAIL"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NOREPLY_EMAIL", GETPOST("SELLYOURSAAS_NOREPLY_EMAIL"), 'chaine', 0, '', $conf->entity);

		$dir=GETPOST("DOLICLOUD_SCRIPTS_PATH");
		if (! dol_is_dir($dir)) setEventMessage($langs->trans("ErrorDirNotFound", $dir), 'warnings');
		dolibarr_set_const($db, "DOLICLOUD_SCRIPTS_PATH", GETPOST("DOLICLOUD_SCRIPTS_PATH"), 'chaine', 0, '', $conf->entity);

		foreach ($arrayofsuffixfound as $suffix) {
			dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_PRODUCT".$suffix, GETPOST("SELLYOURSAAS_DEFAULT_PRODUCT".$suffix), 'chaine', 0, '', $conf->entity);
		}
		//dolibarr_set_const($db,"SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS",GETPOST("SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS"),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_PRODUCT_CATEG", GETPOST("SELLYOURSAAS_DEFAULT_PRODUCT_CATEG"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG", GETPOST("SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG"), 'chaine', 0, '', $conf->entity);

		// Option for resellers
		dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_COMMISSION", GETPOST("SELLYOURSAAS_DEFAULT_COMMISSION"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_DEFAULT_RESELLER_CATEG", GETPOST("SELLYOURSAAS_DEFAULT_RESELLER_CATEG"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_MINAMOUNT_TO_CLAIM", GETPOST("SELLYOURSAAS_MINAMOUNT_TO_CLAIM"), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_EMAIL", GETPOST("SELLYOURSAAS_RESELLER_EMAIL"), 'chaine', 0, '', $conf->entity);
		if (GETPOSTISSET('SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE')) {
			dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE", GETPOST("SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE"), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION')) {
			dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION", GETPOST("SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION"), 'chaine', 0, '', $conf->entity);
		}

		dolibarr_set_const($db, "SELLYOURSAAS_REFS_URL", GETPOST("SELLYOURSAAS_REFS_URL"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_ACCOUNT_URL", GETPOST("SELLYOURSAAS_ACCOUNT_URL", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_PRICES_URL", GETPOST("SELLYOURSAAS_PRICES_URL", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_STATUS_URL", GETPOST("SELLYOURSAAS_STATUS_URL", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_URL", GETPOST("SELLYOURSAAS_RESELLER_URL", 'alpha'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_MYACCOUNT_FOOTER", GETPOST("SELLYOURSAAS_MYACCOUNT_FOOTER", 'none'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_CONVERSION_FOOTER", GETPOST("SELLYOURSAAS_CONVERSION_FOOTER", 'none'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_CSS", GETPOST("SELLYOURSAAS_CSS", 'none'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SECURITY_KEY", GETPOST("SELLYOURSAAS_SECURITY_KEY", 'none'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_PUBLIC_KEY", GETPOST("SELLYOURSAAS_PUBLIC_KEY", 'none'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT", GETPOST("SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT", GETPOST("SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT", 'int'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND", GETPOST("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND", GETPOST("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT", GETPOST("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT", GETPOST("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT", 'int'), 'chaine', 0, '', $conf->entity);

		if (GETPOSTISSET("SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED")) {
			dolibarr_set_const($db, "SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED", GETPOST("SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED", 'alpha'), 'chaine', 0, '', $conf->entity);
		}

		if (GETPOSTISSET("SELLYOURSAAS_GETIPINTEL_ON")) {
			dolibarr_set_const($db, "SELLYOURSAAS_GETIPINTEL_ON", GETPOST("SELLYOURSAAS_GETIPINTEL_ON", 'alpha'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET("SELLYOURSAAS_GETIPINTEL_EMAIL")) {
			dolibarr_set_const($db, "SELLYOURSAAS_GETIPINTEL_EMAIL", GETPOST("SELLYOURSAAS_GETIPINTEL_EMAIL", 'alpha'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET("SELLYOURSAAS_IPQUALITY_ON")) {
			dolibarr_set_const($db, "SELLYOURSAAS_IPQUALITY_ON", GETPOST("SELLYOURSAAS_IPQUALITY_ON", 'alpha'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET("SELLYOURSAAS_IPQUALITY_KEY")) {
			dolibarr_set_const($db, "SELLYOURSAAS_IPQUALITY_KEY", GETPOST("SELLYOURSAAS_IPQUALITY_KEY", 'alpha'), 'chaine', 0, '', $conf->entity);
		}


		dolibarr_set_const($db, "SELLYOURSAAS_HASHALGOFORPASSWORD", GETPOST("SELLYOURSAAS_HASHALGOFORPASSWORD", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_SALTFORPASSWORDENCRYPTION", GETPOST("SELLYOURSAAS_SALTFORPASSWORDENCRYPTION", 'alpha'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, 'SELLYOURSAAS_MAXDEPLOYMENTPERIP', GETPOST("SELLYOURSAAS_MAXDEPLOYMENTPERIP", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SELLYOURSAAS_MAXDEPLOYMENTPERIPVPN', GETPOST("SELLYOURSAAS_MAXDEPLOYMENTPERIPVPN", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR', GETPOST("SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT', GETPOST("SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SELLYOURSAAS_MAXDEPLOYMENTPARALLEL', GETPOST("SELLYOURSAAS_MAXDEPLOYMENTPARALLEL", 'int'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, 'SELLYOURSAAS_VPN_PROBA_REFUSED', GETPOST("SELLYOURSAAS_VPN_PROBA_REFUSED", 'alphanohtml'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, 'SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE', GETPOST("SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE", 'int'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, 'SELLYOURSAAS_INFRA_COST', GETPOST("SELLYOURSAAS_INFRA_COST", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SELLYOURSAAS_PERCENTAGE_FEE', GETPOST("SELLYOURSAAS_PERCENTAGE_FEE", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NBHOURSBETWEENTRIES", GETPOST("SELLYOURSAAS_NBHOURSBETWEENTRIES", 'none'), 'chaine', 0, 'Nb hours minium between each invoice payment try', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES", GETPOST("SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES", 'none'), 'chaine', 0, 'Nb days before stopping invoice payment try', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_ANONYMOUSUSER", GETPOST("SELLYOURSAAS_ANONYMOUSUSER", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_LOGIN_FOR_SUPPORT", GETPOST("SELLYOURSAAS_LOGIN_FOR_SUPPORT", 'alpha'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_PASSWORD_FOR_SUPPORT", GETPOST("SELLYOURSAAS_PASSWORD_FOR_SUPPORT", 'none'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_DATADOG_ENABLED", GETPOST("SELLYOURSAAS_DATADOG_ENABLED", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_DATADOG_APIKEY", GETPOST("SELLYOURSAAS_DATADOG_APIKEY", 'alphanohtml'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_DATADOG_APPKEY", GETPOST("SELLYOURSAAS_DATADOG_APPKEY", 'alphanohtml'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_AUTOMIGRATION_CODE", GETPOST("SELLYOURSAAS_AUTOMIGRATION_CODE", 'alphanohtml'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, "SELLYOURSAAS_AUTOUPGRADE_CODE", GETPOST("SELLYOURSAAS_AUTOUPGRADE_CODE", 'alphanohtml'), 'chaine', 0, '', $conf->entity);

		if (GETPOSTISSET('SELLYOURSAAS_SUPPORT_URL')) {
			dolibarr_set_const($db, "SELLYOURSAAS_SUPPORT_URL", GETPOST("SELLYOURSAAS_SUPPORT_URL", 'alphanohtml'), 'chaine', 0, '', $conf->entity);
		}
		if (GETPOSTISSET('SELLYOURSAAS_SUPPORT_SHOW_MESSAGE')) {
			dolibarr_set_const($db, "SELLYOURSAAS_SUPPORT_SHOW_MESSAGE", GETPOST("SELLYOURSAAS_SUPPORT_SHOW_MESSAGE", 'alphanohtml'), 'chaine', 0, '', $conf->entity);
		}

		$dir=GETPOST("DOLICLOUD_INSTANCES_PATH");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db, "DOLICLOUD_INSTANCES_PATH", GETPOST("DOLICLOUD_INSTANCES_PATH"), 'chaine', 0, '', $conf->entity);

		$dir=GETPOST("DOLICLOUD_BACKUP_PATH");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db, "DOLICLOUD_BACKUP_PATH", GETPOST("DOLICLOUD_BACKUP_PATH"), 'chaine', 0, '', $conf->entity);

		$dir=GETPOST("SELLYOURSAAS_TEST_ARCHIVES_PATH");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db, "SELLYOURSAAS_TEST_ARCHIVES_PATH", GETPOST("SELLYOURSAAS_TEST_ARCHIVES_PATH"), 'chaine', 0, '', $conf->entity);

		$dir=GETPOST("SELLYOURSAAS_PAID_ARCHIVES_PATH");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db, "SELLYOURSAAS_PAID_ARCHIVES_PATH", GETPOST("SELLYOURSAAS_PAID_ARCHIVES_PATH"), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, "SELLYOURSAAS_NAME_RESERVED", GETPOST("SELLYOURSAAS_NAME_RESERVED"), 'chaine', 0, '', $conf->entity);
		if (getDolGlobalInt('SELLYOURSAAS_EMAIL_ADDRESSES_BANNED_ENABLED')) {
			dolibarr_set_const($db, "SELLYOURSAAS_EMAIL_ADDRESSES_BANNED", GETPOST("SELLYOURSAAS_EMAIL_ADDRESSES_BANNED"), 'chaine', 0, '', $conf->entity);
		}

		// Save images
		$dirforimage=$conf->mycompany->dir_output.'/logos/';
		foreach ($_FILES as $postkey => $postvar) {
			$suffix = '';
			if (preg_match('/^logoblack/', $postkey, $reg)) {
				$suffix.='_BLACK';
			}
			if (preg_match('/^logo(black)?_(.+)$/', $postkey, $reg)) {
				$suffix.='_'.strtoupper($reg[2]);
			}
			$varforimage=$postkey;

			if ($_FILES[$varforimage]["tmp_name"]) {
				if (preg_match('/([^\\/:]+)$/i', $_FILES[$varforimage]["name"], $reg)) {
					$original_file=$reg[1];

					$isimage=image_format_supported($original_file);
					if ($isimage >= 0) {
						dol_syslog("Move file ".$_FILES[$varforimage]["tmp_name"]." to ".$dirforimage.$original_file);
						if (! is_dir($dirforimage)) {
							dol_mkdir($dirforimage);
						}
						$result=dol_move_uploaded_file($_FILES[$varforimage]["tmp_name"], $dirforimage.$original_file, 1, 0, $_FILES[$varforimage]['error']);
						if ($result > 0) {
							dolibarr_set_const($db, "SELLYOURSAAS_LOGO".$suffix, $original_file, 'chaine', 0, '', $conf->entity);

							// Create thumbs of logo (Note that PDF use original file and not thumbs)
							if ($isimage > 0) {
								// Create thumbs
								//$object->addThumbs($newfile);    // We can't use addThumbs here yet because we need name of generated thumbs to add them into constants.

								// Create small thumb, Used on logon for example
								$imgThumbSmall = vignette($dirforimage.$original_file, $maxwidthsmall, $maxheightsmall, '_small', $quality);
								if (image_format_supported($imgThumbSmall) >= 0 && preg_match('/([^\\/:]+)$/i', $imgThumbSmall, $reg)) {
									$imgThumbSmall = $reg[1];    // Save only basename
									dolibarr_set_const($db, "SELLYOURSAAS_LOGO_SMALL".$suffix, $imgThumbSmall, 'chaine', 0, '', $conf->entity);
								} else dol_syslog($imgThumbSmall);

								// Create mini thumb, Used on menu or for setup page for example
								$imgThumbMini = vignette($dirforimage.$original_file, $maxwidthmini, $maxheightmini, '_mini', $quality);
								if (image_format_supported($imgThumbMini) >= 0 && preg_match('/([^\\/:]+)$/i', $imgThumbMini, $reg)) {
									$imgThumbMini = $reg[1];     // Save only basename
									dolibarr_set_const($db, "SELLYOURSAAS_LOGO_MINI".$suffix, $imgThumbMini, 'chaine', 0, '', $conf->entity);
								} else dol_syslog($imgThumbMini);
							} else dol_syslog("ErrorImageFormatNotSupported", LOG_WARNING);
						} elseif (preg_match('/^ErrorFileIsInfectedWithAVirus/', $result)) {
							$error++;
							$langs->load("errors");
							$tmparray=explode(':', $result);
							setEventMessages($langs->trans('ErrorFileIsInfectedWithAVirus', $tmparray[1]), null, 'errors');
						} else {
							$error++;
							setEventMessages($langs->trans("ErrorFailedToSaveFile"), null, 'errors');
						}
					} else {
						$error++;
						$langs->load("errors");
						setEventMessages($langs->trans("ErrorBadImageFormat"), null, 'errors');
					}
				}
			}
		}
	}

	if (! $error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}
}

if ($action == 'removelogo') {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$constname='SELLYOURSAAS_LOGO'.GETPOST('suffix', 'aZ09');
	$logofile=$conf->mycompany->dir_output.'/logos/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logofile);
	dolibarr_del_const($db, $constname, $conf->entity);

	$constname='SELLYOURSAAS_LOGO_SMALL'.GETPOST('suffix', 'aZ09');
	$logosmallfile=$conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logosmallfile);
	dolibarr_del_const($db, $constname, $conf->entity);

	$constname='SELLYOURSAAS_LOGO_MINI'.GETPOST('suffix', 'aZ09');
	$logominifile=$conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logominifile);
	dolibarr_del_const($db, $constname, $conf->entity);
}
if ($action == 'removelogoblack') {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$constname='SELLYOURSAAS_LOGO_BLACK'.GETPOST('suffix', 'aZ09');
	$logofile=$conf->mycompany->dir_output.'/logos/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logofile);
	dolibarr_del_const($db, "$constname", $conf->entity);

	$constname='SELLYOURSAAS_LOGO_SMALL_BLACK'.GETPOST('suffix', 'aZ09');
	$logosmallfile=$conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logosmallfile);
	dolibarr_del_const($db, $constname, $conf->entity);

	$constname='SELLYOURSAAS_LOGO_MINI_BLACK'.GETPOST('suffix', 'aZ09');
	$logominifile=$conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logominifile);
	dolibarr_del_const($db, $constname, $conf->entity);
}


/*
 * View
 */

$formother=new FormOther($db);
$form=new Form($db);
$formticket = new FormTicket($db);

$help_url="";
llxHeader("", $langs->trans("SellYouSaasSetup"), $help_url);

$linkback='<a href="'.($backtopage?$backtopage:DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans('SellYouSaasSetup'), $linkback, 'setup');

print '<span class="opacitymedium">'.$langs->trans("Prerequisites")."</span><br>\n";
print '<br>';

print 'Function <b>idn_to_ascii</b> available: '.(function_exists('idn_to_ascii') ? img_picto('', 'tick', 'class="paddingrightonly"').yn(1) : img_picto('', 'warning', 'class="paddingrightonly"').yn(0)).'<br>';
print 'Function <b>checkdnsrr</b> available: '.(function_exists('checkdnsrr') ? img_picto('', 'tick', 'class="paddingrightonly"').yn(1) : img_picto('', 'warning', 'class="paddingrightonly"').yn(0)).'<br>';
print 'Parameter <b>allow_url_fopen</b> is on: '.(ini_get('allow_url_fopen') ? img_picto('', 'tick', 'class="paddingrightonly"').yn(1) : img_picto('', 'warning', 'class="paddingrightonly"').yn(0)).'<br>';
$arrayoffunctionsdisabled = explode(',', ini_get('disable_functions'));
if (in_array('exec', $arrayoffunctionsdisabled)) {
	print "Parameter <b>disable_functions</b>: Bad. Must not contain 'exec'<br>";
} else {
	print 'Parameter <b>disable_functions</b>: '.img_picto('', 'tick', 'class="paddingrightonly"').' does not contains: exec<br>';
}
print "<br>\n";

//$head=array();
//dol_fiche_head($head, 'serversetup', $langs->trans("SellYourSaas"), -1);

print '<span class="opacitymedium">'.$langs->trans("SellYouSaasDesc")."</span><br>\n";
print "<br>\n";

$error=0;

print '<form enctype="multipart/form-data" method="POST" action="'.$_SERVER["PHP_SELF"].'" name="form_index">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td class="titlefield">'.$langs->trans("ParametersOnMasterServer").'</td><td>'.$langs->trans("Value").'</td>';
print '<td class="titlefield"><div class="float">'.$langs->trans("Examples").'</div><div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
print "</tr>\n";

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_FORCE_STRIPE_TEST").'</td>';
print '<td>';
print ajax_constantonoff('SELLYOURSAAS_FORCE_STRIPE_TEST', array(), $conf->entity, 0, 0, 1);
print '</td>';
print '<td><span class="opacitymedium small">1</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasName").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_NAME" value="'.getDolGlobalString('SELLYOURSAAS_NAME').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">My SaaS service</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasMainDomain").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_MAIN_DOMAIN_NAME" value="'.getDolGlobalString('SELLYOURSAAS_MAIN_DOMAIN_NAME').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">mysaasdomainname.com</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$form->textwithpicto($langs->trans("SellYourSaasSubDomains"), $langs->trans("SellYourSaasSubDomainsHelp")).'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SUB_DOMAIN_NAMES" value="'.getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">with.mysaasdomainname.com,with.mysaas2.com:mysaas2.com...</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasSubDomainsIP").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SUB_DOMAIN_IP" value="'.getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_IP').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">192.168.0.1,123.456.789.012...</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasMainEmail").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_MAIN_EMAIL" value="'.getDolGlobalString('SELLYOURSAAS_MAIN_EMAIL').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">contact@mysaasdomainname.com</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasMainEmail").' (Premium)</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_MAIN_EMAIL_PREMIUM" value="'.getDolGlobalString('SELLYOURSAAS_MAIN_EMAIL_PREMIUM').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">contact+premium@mysaasdomainname.com</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasSupervisionEmail").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SUPERVISION_EMAIL" value="'.getDolGlobalString('SELLYOURSAAS_SUPERVISION_EMAIL').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">supervision@mysaasdomainname.com</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasNoReplyEmail").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_NOREPLY_EMAIL" value="'.getDolGlobalString('SELLYOURSAAS_NOREPLY_EMAIL').'" class="minwidth300">';
print '</td>';
print '<td><span class="opacitymedium small">noreply@mysaasdomainname.com</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("DirForScriptPath").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="DOLICLOUD_SCRIPTS_PATH" value="'.getDolGlobalString('DOLICLOUD_SCRIPTS_PATH').'">';
print '</td>';
print '<td><span class="opacitymedium small">'.dol_buildpath('sellyoursaas/scripts').'</span></td>';
print '</tr>';

foreach ($arrayofsuffixfound as $service => $suffix) {
	print '<!-- suffix = '.$suffix.' -->'."\n";

	print '<tr class="oddeven"><td>'.($service ? $service.' - ' : '').$langs->trans("DefaultProductForInstances").'</td>';
	print '<td>';
	$constname = 'SELLYOURSAAS_DEFAULT_PRODUCT'.$suffix;
	print '<!-- constname = '.$constname.' -->';
	$defaultproductid = getDolGlobalString($constname);
	print $form->select_produits($defaultproductid, 'SELLYOURSAAS_DEFAULT_PRODUCT'.$suffix, '', 0, 0, 1, 2, '', 0, array(), 0, '1', 0, 'maxwidth500');
	print '</td>';
	print '<td><span class="opacitymedium small">My SaaS service for instance</span></td>';
	print '</tr>';
}

/*print '<tr class="oddeven"><td>'.$langs->trans("DefaultProductForUsers").'</td>';
print '<td>';
$defaultproductforusersid=getDolGlobalString('SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS');
print $form->select_produits($defaultproductforusersid, 'SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS');
print '</td>';
print '<td>My SaaS service for users</td>';
print '</tr>';
*/

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("DefaultCategoryForSaaSServices").'</td>';
print '<td>';
$defaultproductcategid=getDolGlobalString('SELLYOURSAAS_DEFAULT_PRODUCT_CATEG');
print $formother->select_categories(Categorie::TYPE_PRODUCT, $defaultproductcategid, 'SELLYOURSAAS_DEFAULT_PRODUCT_CATEG', 0, 1, 'miwidth300');
print '</td>';
print '<td><span class="opacitymedium small">SaaS Products</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("DefaultCategoryForSaaSCustomers").'</td>';
print '<td>';
$defaultcustomercategid=getDolGlobalString('SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG');
print $formother->select_categories(Categorie::TYPE_CUSTOMER, $defaultcustomercategid, 'SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG', 0, 1, 'miwidth300');
print '</td>';
print '<td><span class="opacitymedium small">SaaS Customers</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ALLOW_RESELLER_PROGRAM").'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_ALLOW_RESELLER_PROGRAM', array(), null, 0, 0, 1);
} else {
	if (empty($conf->global->SELLYOURSAAS_ALLOW_RESELLER_PROGRAM)) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=set_SELLYOURSAAS_ALLOW_RESELLER_PROGRAM">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=del_SELLYOURSAAS_ALLOW_RESELLER_PROGRAM">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
//print $form->selectyesno('SELLYOURSAAS_ALLOW_RESELLER_PROGRAM', $allowresellerprogram, 1);
print '</td>';
print '<td><span class="opacitymedium small">Set to yes if you want your customers being able to apply to become resellers</span></td>';
print '</tr>';

$allowresellerprogram = getDolGlobalInt('SELLYOURSAAS_ALLOW_RESELLER_PROGRAM');
if ($allowresellerprogram) {
	print '<tr class="oddeven"><td>'.$langs->trans("DefaultCommission");
	print '</td>';
	print '<td>';
	print '<input class="width50 right" type="text" name="SELLYOURSAAS_DEFAULT_COMMISSION" value="'.getDolGlobalString('SELLYOURSAAS_DEFAULT_COMMISSION').'"> %';
	print '</td>';
	print '<td><span class="opacitymedium small">25%</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("DefaultCategoryForSaaSResellers").'</td>';
	print '<td>';
	$defaultcustomercategid = getDolGlobalString('SELLYOURSAAS_DEFAULT_RESELLER_CATEG');
	print $formother->select_categories(Categorie::TYPE_SUPPLIER, $defaultcustomercategid, 'SELLYOURSAAS_DEFAULT_RESELLER_CATEG', 0, 1, 'miwidth300');
	print '</td>';
	print '<td><span class="opacitymedium small">SaaS Resellers</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasResellerUrl").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_RESELLER_URL" value="'.getDolGlobalString('SELLYOURSAAS_RESELLER_URL').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">https://www.mysaasdomainname.com/en-become-a-dolicloud-reseller.php</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MINAMOUNT_TO_CLAIM").'</td>';
	print '<td>';
	print '<input class="width50" type="text" name="SELLYOURSAAS_MINAMOUNT_TO_CLAIM" value="'.getDolGlobalString('SELLYOURSAAS_MINAMOUNT_TO_CLAIM').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">100</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_RESELLER_EMAIL").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_RESELLER_EMAIL" value="'.getDolGlobalString('SELLYOURSAAS_RESELLER_EMAIL').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">partner@mysaasdomainname.com</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE").'</td>';
	print '<td>';
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE', array(), null, 0, 0, 1);
	} else {
		if (empty($conf->global->SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE)) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=set_SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
		} else {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=del_SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
		}
	}
	//print $form->selectyesno('SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE', $allowresellerprogram, 1);
	print '</td>';
	print '<td></td>';
	print '</tr>';

	// If option to allow reseller custome prices is on, we can set a maximum for discount
	if (getDolGlobalInt('SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE')) {
		print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION").'</td>';
		print '<td>';
		print '<input class="maxwidth50 right" type="text" name="SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION" value="'.getDolGlobalInt('SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION', 0).'"> %';
		print '</td>';
		print '<td><span class="opacitymedium small">30 %</span></td>';
		print '</tr>';
	}
}

print '<tr class="oddeven"><td>'.$langs->trans("RefsUrl", DOL_DOCUMENT_ROOT.'/sellyoursaas/git');
print '</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_REFS_URL" value="'.getDolGlobalString('SELLYOURSAAS_REFS_URL').'">';
print '</td>';
print '<td><span class="opacitymedium small">https://admin.mysaasdomainname.com/git</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("SellYourSaasAccountUrl").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_ACCOUNT_URL" value="'.getDolGlobalString('SELLYOURSAAS_ACCOUNT_URL').'">';
print '</td>';
print '<td><span class="opacitymedium small">https://myaccount.mysaasdomainname.com<br>Note: Virtual hosts for such domains must link to <strong>'.dol_buildpath('sellyoursaas/myaccount').'</strong></span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasPricesUrl").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_PRICES_URL" value="'.getDolGlobalString('SELLYOURSAAS_PRICES_URL').'">';
print '</td>';
print '<td><span class="opacitymedium small">https://myaccount.mysaasdomainname.com/prices.html</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasStatusUrl").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_STATUS_URL" value="'.getDolGlobalString('SELLYOURSAAS_STATUS_URL').'">';
print '</td>';
print '<td><span class="opacitymedium small">https://status.mysaasdomainname.com</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $form->textwithpicto($langs->trans("SELLYOURSAAS_MAIN_FAQ_URL"), $langs->trans("SELLYOURSAAS_MAIN_FAQ_URLHelp"));
print '</td>';
print '<td colspan="2">';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_MAIN_FAQ_URL" value="'.getDolGlobalString('SELLYOURSAAS_MAIN_FAQ_URL').'">';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("FooterContent").'</td>';
print '<td>';
print '<textarea name="SELLYOURSAAS_MYACCOUNT_FOOTER" class="quatrevingtpercent" rows="3">'.getDolGlobalString('SELLYOURSAAS_MYACCOUNT_FOOTER').'</textarea>';
print '</td>';
print '<td><span class="opacitymedium small">&lt;script&gt;Your google analytics code&lt;/script&gt;</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("ConversionContent").'</td>';
print '<td>';
print '<textarea name="SELLYOURSAAS_CONVERSION_FOOTER" class="quatrevingtpercent" rows="3">'.getDolGlobalString('SELLYOURSAAS_CONVERSION_FOOTER').'</textarea>';
print '</td>';
print '<td><span class="opacitymedium small">&lt;script&gt;Your conversion trackers&lt;/script&gt;</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("CSSForCustomerAndRegisterPages").'</td>';
print '<td>';
print '<textarea name="SELLYOURSAAS_CSS" class="quatrevingtpercent" rows="3">'.getDolGlobalString('SELLYOURSAAS_CSS').'</textarea>';
print '</td>';
print '<td></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SecurityKeyForPublicPages").' <span class="opacitymedium">(To protect the URL for Spam reporting webhooks)</spam></td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_SECURITY_KEY" value="'.getDolGlobalString('SELLYOURSAAS_SECURITY_KEY').'">';
print '</td>';
print '<td><span class="opacitymedium small">123456abcdef</span></td>';
print '</tr>';



// Other

print '<tr class="liste_titre"><td>'.$langs->trans("Automation").'</td>';
print '<td>';
print '</td>';
print '<td>';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("AnonymousUser").'</td>';
print '<td>';
print $form->select_dolusers(getDolGlobalString('SELLYOURSAAS_ANONYMOUSUSER'), 'SELLYOURSAAS_ANONYMOUSUSER', 1);
print '</td>';
print '<td><span class="opacitymedium small">User used for all anonymous action (registering, actions from customer dashboard, ...)</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_HASHALGOFORPASSWORD").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_HASHALGOFORPASSWORD" value="'.getDolGlobalString('SELLYOURSAAS_HASHALGOFORPASSWORD').'">';
print '</td>';
print '<td><span class="opacitymedium small">\'sha1md5\', \'sha256\', \'password_hash\', ...<br>Useless if you don\'t use the substitution key __APPPASSWORD0__ in package definition (for example if you used __APPPASSWORDMD5__ or APPPASSWORDSHA256__ or __APPPASSWORDPASSWORD_HASH__ instead)</span></td>';
print '</tr>';

if (empty($conf->global->SELLYOURSAAS_HASHALGOFORPASSWORD) || $conf->global->SELLYOURSAAS_HASHALGOFORPASSWORD != 'password_hash') {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_SALTFORPASSWORDENCRYPTION").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_SALTFORPASSWORDENCRYPTION" value="'.getDolGlobalString('SELLYOURSAAS_SALTFORPASSWORDENCRYPTION').'">';
	print '</td>';
	print '<td><span class="opacitymedium small"></span></td>';
	print '</tr>';
}

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT').'">';
print '</td>';
print '<td><span class="opacitymedium small">7</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT').'">';
print '</td>';
print '<td><span class="opacitymedium small">1</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND').'">';
print '</td>';
print '<td><span class="opacitymedium small">2</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND').'">';
print '</td>';
print '<td><span class="opacitymedium small">12</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT').'">';
print '</td>';
print '<td><span class="opacitymedium small">30</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBHOURSBETWEENTRIES").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBHOURSBETWEENTRIES" value="'.getDolGlobalString('SELLYOURSAAS_NBHOURSBETWEENTRIES').'">';
print '</td>';
print '<td><span class="opacitymedium small">49</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES').'">';
print '</td>';
print '<td><span class="opacitymedium small">35</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT" value="'.getDolGlobalString('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT').'">';
print '</td>';
print '<td><span class="opacitymedium small">120</span></td>';
print '</tr>';


// Security for registration

print '<tr class="liste_titre"><td>'.$langs->trans("SecurityOfRegistrations").'</td>';
print '<td>';
print '</td>';
print '<td>';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAXDEPLOYMENTPERIP").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAXDEPLOYMENTPERIP" value="'.getDolGlobalInt('SELLYOURSAAS_MAXDEPLOYMENTPERIP', 20).'">';
print '</td>';
print '<td><span class="opacitymedium small">20</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR" value="'.getDolGlobalInt('SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR', 5).'">';
print '</td>';
print '<td><span class="opacitymedium small">5</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAXDEPLOYMENTPARALLEL").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAXDEPLOYMENTPARALLEL" value="'.getDolGlobalInt('SELLYOURSAAS_MAXDEPLOYMENTPARALLEL', 4).'">';
print '</td>';
print '<td><span class="opacitymedium small">4</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT" value="'.getDolGlobalInt('SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT', 4).'">';
print '</td>';
print '<td><span class="opacitymedium small">4</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NAME_RESERVED").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_NAME_RESERVED" value="'.getDolGlobalString('SELLYOURSAAS_NAME_RESERVED').'">';
print '</td>';
print '<td><span class="opacitymedium small">^mycompany[0-9]*\.</span></td>';
print '</tr>';

if (getDolGlobalInt('SELLYOURSAAS_EMAIL_ADDRESSES_BANNED_ENABLED')) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_EMAIL_ADDRESSES_BANNED").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_EMAIL_ADDRESSES_BANNED" value="'.getDolGlobalString('SELLYOURSAAS_EMAIL_ADDRESSES_BANNED').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">yopmail.com,hotmail.com,spammer@gmail.com</span></td>';
	print '</tr>';
}

// Enable DisposableEmail service
print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED"), 'This is a usefull component to fight against spam instances').'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED', array(), null, 0, 0, 1);
} else {
	if (empty($conf->global->SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED)) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=setSELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delSELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td></td>';
print '</tr>';

if (!empty($conf->global->SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED)) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_API_KEY", "DispoableEmail").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_API_KEY" value="'.getDolGlobalString('SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_API_KEY').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">1234567890123456</span></td>';
	print '</tr>';
}

// Enable GetIPIntel
print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("SELLYOURSAAS_GETIPINTEL_ON"), 'This is a usefull component to fight against spam instances').'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_GETIPINTEL_ON', array(), null, 0, 0, 1);
} else {
	if (empty($conf->global->SELLYOURSAAS_GETIPINTEL_ON)) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=setSELLYOURSAAS_GETIPINTEL_ON">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delSELLYOURSAAS_GETIPINTEL_ON">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td></td>';
print '</tr>';

if (!empty($conf->global->SELLYOURSAAS_GETIPINTEL_ON)) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_GETIPINTEL_EMAIL").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_GETIPINTEL_EMAIL" value="'.getDolGlobalString('SELLYOURSAAS_GETIPINTEL_EMAIL').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">myemail@email.com</span></td>';
	print '</tr>';
}

// Enable IPQualityScore
print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("SELLYOURSAAS_IPQUALITY_ON"), 'This is a very important component to fight against spam instances').'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_IPQUALITY_ON', array(), null, 0, 0, 1);
} else {
	if (empty($conf->global->SELLYOURSAAS_IPQUALITY_ON)) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=setSELLYOURSAAS_IPQUALITY_ON">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delSELLYOURSAAS_IPQUALITY_ON">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td></td>';
print '</tr>';

if (!empty($conf->global->SELLYOURSAAS_IPQUALITY_ON)) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_API_KEY", "IPQualityScore").'</td>';
	print '<td>';
	print '<input class="minwidth300" type="text" name="SELLYOURSAAS_IPQUALITY_KEY" value="'.getDolGlobalString('SELLYOURSAAS_IPQUALITY_KEY').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">1234567890123456</span></td>';
	print '</tr>';
}

if (!empty($conf->global->SELLYOURSAAS_GETIPINTEL_ON) || !empty($conf->global->SELLYOURSAAS_IPQUALITY_ON)) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_VPN_PROBA_REFUSED").'</td>';
	print '<td>';
	print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_VPN_PROBA_REFUSED" value="'.getDolGlobalString('SELLYOURSAAS_VPN_PROBA_REFUSED').'">';
	print '</td>';
	print '<td><span class="opacitymedium small">0.9, 1, Keep empty for no filter on VPN probability</span></td>';
	print '</tr>';

	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAXDEPLOYMENTPERIP").' (VPN)</td>';
	print '<td>';
	print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAXDEPLOYMENTPERIPVPN" value="'.getDolGlobalInt('SELLYOURSAAS_MAXDEPLOYMENTPERIPVPN', 2).'">';
	print '</td>';
	print '<td><span class="opacitymedium small">2</span></td>';
	print '</tr>';
}

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ONLY_NON_PROFIT_ORGA").'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA', array(), null, 0, 0, 1);
} else {
	if (empty($conf->global->SELLYOURSAAS_ONLY_NON_PROFIT_ORGA)) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=setSELLYOURSAAS_ONLY_NON_PROFIT_ORGA">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delSELLYOURSAAS_ONLY_NON_PROFIT_ORGA">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td><span class="opacitymedium small">Set to yes if you only want non-profit organisations as customers</span></td>';
print '</tr>';


// Other

print '<tr class="liste_titre"><td>'.$langs->trans("Other").'</td>';
print '<td>';
print '</td>';
print '<td>';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ENABLE_SEPA_FOR_THIRDPARTYID").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_ENABLE_SEPA_FOR_THIRDPARTYID" value="'.getDolGlobalString('SELLYOURSAAS_ENABLE_SEPA_FOR_THIRDPARTYID', '').'">';
print '</td>';
print '<td><span class="opacitymedium small">1,99,...</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE" value="'.getDolGlobalString('SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE', '').'">';
print '</td>';
print '<td><span class="opacitymedium small">0=No limit</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_INFRA_COST").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_INFRA_COST" value="'.getDolGlobalString('SELLYOURSAAS_INFRA_COST', 0).'">';
print '</td>';
print '<td><span class="opacitymedium small">50</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_PERCENTAGE_FEE").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_PERCENTAGE_FEE" value="'.getDolGlobalString('SELLYOURSAAS_PERCENTAGE_FEE', 0).'">';
print '</td>';
print '<td><span class="opacitymedium small">0.02</span></td>';
print '</tr>';

foreach ($arrayofsuffixfound as $service => $suffix) {
	print '<!-- suffix = '.$suffix.' -->'."\n";

	// Logo
	print '<tr class="oddeven"><td><label for="logo">'.$service.' - '.$langs->trans("LogoWhiteBackground").' (png,jpg)</label></td><td>';
	print '<table width="100%" class="nobordernopadding"><tr class="nocellnopadd"><td valign="middle" class="nocellnopadd">';
	print '<input type="file" class="flat class=minwidth200" name="logo'.$suffix.'" id="logo'.$suffix.'">';
	print '</td><td class="nocellnopadd" valign="middle">';
	$constname = 'SELLYOURSAAS_LOGO_MINI'.$suffix;
	print '<!-- constname = '.$constname.' -->';
	if (! empty($conf->global->$constname)) {
		print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=removelogo&suffix='.$suffix.'">'.img_delete($langs->trans("Delete")).'</a>';
		if (file_exists($conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constname)) {
			print ' &nbsp; ';
			print '<img src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&amp;file='.urlencode('logos/thumbs/'.$conf->global->$constname).'">';
		}
	} else {
		print '<img height="30" src="'.DOL_URL_ROOT.'/public/theme/common/nophoto.png">';
	}
	print '</td></tr></table>';
	print '</td><td>';
	print '</td></tr>';

	// Logo font black
	print '<tr class="oddeven"><td><label for="logo">'.$service.' - '.$langs->trans("LogoBlackBackground").' (png,jpg)</label></td><td>';
	print '<table width="100%" class="nobordernopadding"><tr class="nocellnopadd"><td valign="middle" class="nocellnopadd">';
	print '<input type="file" class="flat class=minwidth200" name="logoblack'.$suffix.'" id="logoblack'.$suffix.'">';
	print '</td><td class="nocellnopadd" valign="middle">';
	$constname = 'SELLYOURSAAS_LOGO_MINI_BLACK'.$suffix;
	if (! empty($conf->global->$constname)) {
		print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=removelogoblack&suffix='.$suffix.'">'.img_delete($langs->trans("Delete")).'</a>';
		if (file_exists($conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constname)) {
			print ' &nbsp; ';
			print '<img src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&amp;file='.urlencode('logos/thumbs/'.$conf->global->$constname).'">';
		}
	} else {
		print '<img height="30" src="'.DOL_URL_ROOT.'/public/theme/common/nophoto.png">';
	}
	print '</td></tr></table>';
	print '</td><td>';
	print '</td></tr>';
}

// SELLYOURSAAS_ACCEPT_DISCOUNTCODE
print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ACCEPT_DISCOUNTCODE").'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_ACCEPT_DISCOUNTCODE', array(), null, 0, 0, 0);
} else {
	if (empty($conf->global->SELLYOURSAAS_ACCEPT_DISCOUNTCODE)) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=set_SELLYOURSAAS_ACCEPT_DISCOUNTCODE">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=del_SELLYOURSAAS_ACCEPT_DISCOUNTCODE">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td><span class="opacitymedium small">Set to yes to add a field "Discount code" on the "Enter payment mode" page. Available discounts can be defined on services with type "Application".</td>';
print '</tr>';

// SELLYOURSAAS_ENABLE_OPTINMESSAGES
print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ENABLE_OPTINMESSAGES").'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_ENABLE_OPTINMESSAGES', array(), null, 0, 0, 0);
} else {
	if (empty($conf->global->SELLYOURSAAS_ENABLE_OPTINMESSAGES)) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=set_SELLYOURSAAS_ENABLE_OPTINMESSAGES">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=del_SELLYOURSAAS_ENABLE_OPTINMESSAGES">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td><span class="opacitymedium small">Set to yes to add a checkbox on register page to accept "Commercial offers".</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_INVOICE_FORCE_DATE_VALIDATION").'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_INVOICE_FORCE_DATE_VALIDATION', array(), null, 0, 0, 0);
} else {
	if (empty($conf->global->SELLYOURSAAS_INVOICE_FORCE_DATE_VALIDATION)) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=set_SELLYOURSAAS_INVOICE_FORCE_DATE_VALIDATION">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=del_SELLYOURSAAS_INVOICE_FORCE_DATE_VALIDATION">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td></td>';
print '</tr>';

// SELLYOURSAAS_DATADOG_ENABLED
print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_DATADOG_ENABLED").'</td>';
print '<td>';
$array = array('0' => 'No', '1' => 'Yes', '2' => 'Yes with detail of remote action errors');
print $form->selectarray('SELLYOURSAAS_DATADOG_ENABLED', $array, getDolGlobalString('SELLYOURSAAS_DATADOG_ENABLED'), 0);
print '</td>';
print '<td><span class="opacitymedium small">If a datadog agent is running on each of your server, enable this option so SellyourSaas will send metrics sellyoursaas.* to Datadog.</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_DATADOG_APPKEY").'</td>';
print '<td>';
print '<input class="maxwidth200" type="text" name="SELLYOURSAAS_DATADOG_APPKEY" value="'.getDolGlobalString('SELLYOURSAAS_DATADOG_APPKEY', '').'">';
print '</td>';
print '<td><span class="opacitymedium small">MyApp</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_DATADOG_APIKEY").'</td>';
print '<td>';
print '<input class="maxwidth200" type="text" name="SELLYOURSAAS_DATADOG_APIKEY" value="'.getDolGlobalString('SELLYOURSAAS_DATADOG_APIKEY', '').'">';
print '</td>';
print '<td><span class="opacitymedium small">45fdf4sds54fdf</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_AUTOMIGRATION_CODE").'</td>';
print '<td>';
print $formticket->selectGroupTickets(getDolGlobalString('SELLYOURSAAS_AUTOMIGRATION_CODE'), 'SELLYOURSAAS_AUTOMIGRATION_CODE', '', 2, 1, 0, 0, 'maxwidth400');
print '</td>';
print '<td></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_AUTOUPGRADE_CODE").'</td>';
print '<td>';
print $formticket->selectGroupTickets(getDolGlobalString('SELLYOURSAAS_AUTOUPGRADE_CODE'), 'SELLYOURSAAS_AUTOUPGRADE_CODE', '', 2, 1, 0, 0, 'maxwidth400');
print '</td>';
print '<td></td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->trans("SELLYOURSAAS_SUPPORT_URL").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SUPPORT_URL" class="quatrevingtpercent" value="'.getDolGlobalString('SELLYOURSAAS_SUPPORT_URL').'">';
print '</td>';
print '<td>';
print '<span class="opacitymedium small">'.$langs->trans("FillOnlyToUseAnExternalTicketSystem").'</span>';
print '</td>';
print '</tr>';

if (empty($conf->global->SELLYOURSAAS_SUPPORT_URL)) {
	print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_SUPPORT_SHOW_MESSAGE").'</td>';
	print '<td>';
	print '<textarea name="SELLYOURSAAS_SUPPORT_SHOW_MESSAGE" class="quatrevingtpercent" rows="3">'.getDolGlobalString('SELLYOURSAAS_SUPPORT_SHOW_MESSAGE').'</textarea>';
	print '</td>';
	print '<td></td>';
	print '</tr>';
}

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ASK_DESTROY_REASON").'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_ASK_DESTROY_REASON', array(), null, 0, 0, 0);
} else {
	if (empty($conf->global->SELLYOURSAAS_ASK_DESTROY_REASON)) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=set_SELLYOURSAAS_ASK_DESTROY_REASON">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=del_SELLYOURSAAS_ASK_DESTROY_REASON">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td><span class="opacitymedium small"></span></td>';
print '</tr>';

print '</table>';
print '</div>';


print '<br>';


// Parameters for deployment servers

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="titlefield">'.$langs->trans("ParametersOnDeploymentServers").'</td><td class="titlefield">'.$langs->trans("Value").'</td>';
print '<td class="titlefield"><div class="float valignmiddle">'.$langs->trans("Examples").'</div><div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
print "</tr>\n";

print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans("DirForDoliCloudInstances").'</td>';
print '<td>';
print '<input class="minwidth200" type="text" name="DOLICLOUD_INSTANCES_PATH" value="'.getDolGlobalString('DOLICLOUD_INSTANCES_PATH').'">';
print '</td>';
print '<td><span class="opacitymedium small">/home/jail/home</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">';
print $form->textwithpicto($langs->trans("DirForBackupTestInstances"), '').'</td>';
print '<td>';
//print '<input class="minwidth200" type="text" name="DOLICLOUD_TEST_BACKUP_PATH" value="'.getDolGlobalString('DOLICLOUD_TEST_BACKUP_PATH').'">';
print '<span class="opacitymedium">'.$langs->trans("FeatureNotYetAvailable").'</span>';
print '</td>';
print '<td>';
//print '/home/jail/backup, /mnt/diskbackup/backup';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">';
print $form->textwithpicto($langs->trans("DirForBackupInstances"), '').'</td>';
print '<td>';
print '<input class="minwidth200" type="text" name="DOLICLOUD_BACKUP_PATH" value="'.getDolGlobalString('DOLICLOUD_BACKUP_PATH').'">';
print '</td>';
print '<td><span class="opacitymedium small">/home/jail/backup, /mnt/diskbackup/backup</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">';
print $form->textwithpicto($langs->trans("SELLYOURSAAS_TEST_ARCHIVES_PATH"), $langs->trans("ArchiveInstanceDesc").'<br><br>'.$langs->trans("ArchiveTestInstanceDesc")).'</td>';
print '<td>';
print '<input class="minwidth200" type="text" name="SELLYOURSAAS_TEST_ARCHIVES_PATH" value="'.getDolGlobalString('SELLYOURSAAS_TEST_ARCHIVES_PATH').'">';
print '</td>';
print '<td><span class="opacitymedium small">/home/jail/archives-test, /mnt/diskbackup/archives-test</span></td>';
print '</tr>';

print '<tr class="oddeven"><td class="fieldrequired">';
print $form->textwithpicto($langs->trans("SELLYOURSAAS_PAID_ARCHIVES_PATH"), $langs->trans("ArchiveInstanceDesc")).'</td>';
print '<td>';
print '<input class="minwidth200" type="text" name="SELLYOURSAAS_PAID_ARCHIVES_PATH" value="'.getDolGlobalString('SELLYOURSAAS_PAID_ARCHIVES_PATH').'">';
print '</td>';
print '<td><span class="opacitymedium small">/home/jail/archives-paid, /mnt/diskbackup/archives-paid</span></td>';
print '</tr>';

// SSH public keys to deploy on authized_public file.
print '<tr class="oddeven"><td>'.$langs->trans("SSHPublicKey").'</td>';
print '<td>';
print '<textarea name="SELLYOURSAAS_PUBLIC_KEY" class="quatrevingtpercent" rows="3">'.getDolGlobalString('SELLYOURSAAS_PUBLIC_KEY').'</textarea>';
print '</td>';
print '<td><span class="opacitymedium small">'.$langs->trans("SSHPublicKeyDesc").'</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("LoginForSupport").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_LOGIN_FOR_SUPPORT" value="'.getDolGlobalString('SELLYOURSAAS_LOGIN_FOR_SUPPORT').'">';
print '</td>';
print '<td><span class="opacitymedium small">'.$langs->trans("LoginSupportHelp").'</span></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("PasswordForSupport").'</td>';
print '<td>';
print '<input type="password" name="SELLYOURSAAS_PASSWORD_FOR_SUPPORT" value="'.getDolGlobalString('SELLYOURSAAS_PASSWORD_FOR_SUPPORT').'">';
print showValueWithClipboardCPButton(getDolGlobalString('SELLYOURSAAS_PASSWORD_FOR_SUPPORT'), 0, 'none');
print '</td>';
print '<td><span class="opacitymedium small">Password to use to create a support user account on customer instances</span></td>';
print '</tr>';

print '</table>';
print '</div>';

print "</form>\n";


print "<br>";
print '<br>';

// Define $urlwithroot
$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT, '/').'$/i', '', trim($dolibarr_main_url_root));
$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
//$urlwithroot=DOL_MAIN_URL_ROOT;						// This is to use same domain name than current. For Paypal payment, we can use internal URL like localhost.

/*
var_dump(DOL_URL_ROOT);
var_dump(dol_buildpath('/sellyoursaas/public/spamreport.php', 1));
var_dump(DOL_MAIN_URL_ROOT);
*/

$message = '';
$url = '<a href="'.dol_buildpath('/sellyoursaas/public/spamreport.php', 3).'?key='.urlencode(getDolGlobalString('SELLYOURSAAS_SECURITY_KEY', 'KEYNOTDEFINED')).'&mode=test" target="_blank" rel="noopener">'.dol_buildpath('/sellyoursaas/public/spamreport.php', 3).'?key='.urlencode(getDolGlobalString('SELLYOURSAAS_SECURITY_KEY', 'KEYNOTDEFINED')).'[&mode=test]</a>';
$message .= img_picto('', 'object_globe.png').' '.$langs->trans("EndPointFor", "SpamReport", '{s1}');
$message = str_replace('{s1}', $url, $message);
print $message;

print '<br>';

/*
$message='';
$url='<a href="'.dol_buildpath('/sellyoursaas/myaccount/public/test.php', 3).'?key='.($conf->global->SELLYOURSAAS_SECURITY_KEY?urlencode($conf->global->SELLYOURSAAS_SECURITY_KEY):'...').'" target="_blank">'.dol_buildpath('/sellyoursaas/public/test.php', 3).'?key='.($conf->global->SELLYOURSAAS_SECURITY_KEY?urlencode($conf->global->SELLYOURSAAS_SECURITY_KEY):'KEYNOTDEFINED').'</a>';
$message.=img_picto('', 'object_globe.png').' '.$langs->trans("EndPointFor", "Test", '{s1}');
$message = str_replace('{s1}', $url, $message);
print $message;

print "<br>";
*/

//dol_fiche_end();


llxfooter();

$db->close();
