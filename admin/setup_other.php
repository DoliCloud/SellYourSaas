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
		dolibarr_set_const($db, 'SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE', GETPOST("SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE", 'int'), 'chaine', 0, '', $conf->entity);

		dolibarr_set_const($db, 'SELLYOURSAAS_INFRA_COST', GETPOST("SELLYOURSAAS_INFRA_COST", 'int'), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'SELLYOURSAAS_PERCENTAGE_FEE', GETPOST("SELLYOURSAAS_PERCENTAGE_FEE", 'int'), 'chaine', 0, '', $conf->entity);

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

		dolibarr_set_const($db, "SELLYOURSAAS_SECURITY_KEY", GETPOST("SELLYOURSAAS_SECURITY_KEY", 'none'), 'chaine', 0, '', $conf->entity);

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

$error=0;

$head = sellyoursaas_admin_prepare_head();
print dol_get_fiche_head($head, "setup_other", "SellYouSaasSetup", -1, "sellyoursaas@sellyoursaas");

print '<form enctype="multipart/form-data" method="POST" action="'.$_SERVER["PHP_SELF"].'" name="form_index">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td><td>'.$langs->trans("Value").'</td>';
print '<td><div class="float">'.$langs->trans("Examples").'</div><div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
print "</tr>\n";

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

// Allow SEPA Payment for ?
print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ENABLE_SEPA_FOR_THIRDPARTYID").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_ENABLE_SEPA_FOR_THIRDPARTYID" value="'.getDolGlobalString('SELLYOURSAAS_ENABLE_SEPA_FOR_THIRDPARTYID', '').'">';
print '</td>';
print '<td><span class="opacitymedium small">12345</span></td>';
print '</tr>';

// Allow Sepa
print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ENABLE_SEPA").'</td>';
print '<td>';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('SELLYOURSAAS_ENABLE_SEPA', array(), null, 0, 0, 0);
} else {
	if (empty($conf->global->SELLYOURSAAS_ENABLE_SEPA)) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=SELLYOURSAAS_ENABLE_SEPA">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
	} else {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=SELLYOURSAAS_ENABLE_SEPA">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
	}
}
print '</td>';
print '<td><span class="opacitymedium small">Set to yes to add Sepa as a Payment method.</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE" value="'.getDolGlobalString('SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE', '').'">';
print '</td>';
print '<td><span class="opacitymedium small">0=No limit</span></td>';
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

print '<tr class="oddeven"><td>'.$langs->trans("SecurityKeyForPublicPages").' <span class="opacitymedium">(To protect the URL for Spam reporting webhooks)</spam></td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_SECURITY_KEY" value="'.getDolGlobalString('SELLYOURSAAS_SECURITY_KEY').'">';
print '</td>';
print '<td><span class="opacitymedium small">123456abcdef</span></td>';
print '</tr>';

print '</table>';
print '</div>';

print '</table>';
print '</div>';

print "</form>\n";


print "<br>";


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

print '<br><br>';

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
