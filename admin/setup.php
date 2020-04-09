<?php
/* Copyright (C) 2012 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *     \file       htdocs/sellyoursaas/admin/setup.php
 *     \brief      Page administration module SellYourSaas
 */


if (! defined('NOSCANPOSTFORINJECTION')) define('NOSCANPOSTFORINJECTION','1');		// Do not check anti CSRF attack test


// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include($_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php");
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include(substr($tmp, 0, ($i+1))."/main.inc.php");
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php");
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");

require_once(DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/files.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/images.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/geturl.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
require_once(DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php");

// Access control
if (! $user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$langs->loadLangs(array("admin", "errors", "install", "sellyoursaas@sellyoursaas"));

//exit;


/*
 * Actions
 */

if ($action == 'set')
{
	$error=0;

	if (! $error)
	{
	    dolibarr_set_const($db,'SELLYOURSAAS_FORCE_STRIPE_TEST',GETPOST("SELLYOURSAAS_FORCE_STRIPE_TEST",'int'),'chaine',0,'',$conf->entity);

	    dolibarr_set_const($db,"SELLYOURSAAS_NAME",GETPOST("SELLYOURSAAS_NAME"),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_MAIN_DOMAIN_NAME",GETPOST("SELLYOURSAAS_MAIN_DOMAIN_NAME"),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_SUB_DOMAIN_NAMES",GETPOST("SELLYOURSAAS_SUB_DOMAIN_NAMES"),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_SUB_DOMAIN_IP",GETPOST("SELLYOURSAAS_SUB_DOMAIN_IP"),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_MAIN_EMAIL",GETPOST("SELLYOURSAAS_MAIN_EMAIL"),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_MAIN_EMAIL_PREMIUM",GETPOST("SELLYOURSAAS_MAIN_EMAIL_PREMIUM"),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_SUPERVISION_EMAIL",GETPOST("SELLYOURSAAS_SUPERVISION_EMAIL"),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_NOREPLY_EMAIL",GETPOST("SELLYOURSAAS_NOREPLY_EMAIL"),'chaine',0,'',$conf->entity);

		$dir=GETPOST("DOLICLOUD_SCRIPTS_PATH");
		if (! dol_is_dir($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db,"DOLICLOUD_SCRIPTS_PATH",GETPOST("DOLICLOUD_SCRIPTS_PATH"),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_DEFAULT_PRODUCT",GETPOST("SELLYOURSAAS_DEFAULT_PRODUCT"),'chaine',0,'',$conf->entity);
		//dolibarr_set_const($db,"SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS",GETPOST("SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS"),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_DEFAULT_PRODUCT_CATEG",GETPOST("SELLYOURSAAS_DEFAULT_PRODUCT_CATEG"),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG",GETPOST("SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG"),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_ALLOW_RESELLER_PROGRAM",GETPOST("SELLYOURSAAS_ALLOW_RESELLER_PROGRAM"),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_DEFAULT_COMMISSION",GETPOST("SELLYOURSAAS_DEFAULT_COMMISSION"),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_DEFAULT_RESELLER_CATEG",GETPOST("SELLYOURSAAS_DEFAULT_RESELLER_CATEG"),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_REFS_URL",GETPOST("SELLYOURSAAS_REFS_URL"),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_ACCOUNT_URL",GETPOST("SELLYOURSAAS_ACCOUNT_URL",'alpha'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_PRICES_URL",GETPOST("SELLYOURSAAS_PRICES_URL",'alpha'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_STATUS_URL",GETPOST("SELLYOURSAAS_STATUS_URL",'alpha'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_RESELLER_URL",GETPOST("SELLYOURSAAS_RESELLER_URL",'alpha'),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_MYACCOUNT_FOOTER",GETPOST("SELLYOURSAAS_MYACCOUNT_FOOTER",'none'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_CONVERSION_FOOTER",GETPOST("SELLYOURSAAS_CONVERSION_FOOTER",'none'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_PUBLIC_KEY",GETPOST("SELLYOURSAAS_PUBLIC_KEY",'none'),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT",GETPOST("SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT",'int'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT",GETPOST("SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT",'int'),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND",GETPOST("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND",'int'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND",GETPOST("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND",'int'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT",GETPOST("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT",'int'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT",GETPOST("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT",'int'),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES",GETPOST("SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES",'none'),'chaine',0,'Nb days before stopping invoice payment try',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_NBHOURSBETWEENTRIES",GETPOST("SELLYOURSAAS_NBHOURSBETWEENTRIES",'none'),'chaine',0,'Nb hours minium between each invoice payment try',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_SALTFORPASSWORDENCRYPTION",GETPOST("SELLYOURSAAS_SALTFORPASSWORDENCRYPTION",'alpha'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_HASHALGOFORPASSWORD",GETPOST("SELLYOURSAAS_HASHALGOFORPASSWORD",'alpha'),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,'SELLYOURSAAS_MAXDEPLOYMENTPERIP',GETPOST("SELLYOURSAAS_MAXDEPLOYMENTPERIP",'int'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,'SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR',GETPOST("SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR",'int'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,'SELLYOURSAAS_INFRA_COST',GETPOST("SELLYOURSAAS_INFRA_COST",'int'),'chaine',0,'',$conf->entity);

		dolibarr_set_const($db,"SELLYOURSAAS_ANONYMOUSUSER",GETPOST("SELLYOURSAAS_ANONYMOUSUSER",'none'),'chaine',0,'',$conf->entity);
		dolibarr_set_const($db,"SELLYOURSAAS_LOGIN_FOR_SUPPORT",GETPOST("SELLYOURSAAS_LOGIN_FOR_SUPPORT",'none'),'chaine',0,'',$conf->entity);

		$dir=GETPOST("DOLICLOUD_INSTANCES_PATH");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db,"DOLICLOUD_INSTANCES_PATH",GETPOST("DOLICLOUD_INSTANCES_PATH"),'chaine',0,'',$conf->entity);

		$dir=GETPOST("DOLICLOUD_BACKUP_PATH");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db,"DOLICLOUD_BACKUP_PATH",GETPOST("DOLICLOUD_BACKUP_PATH"),'chaine',0,'',$conf->entity);

		$dir=GETPOST("SELLYOURSAAS_TEST_ARCHIVES_PATH");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db,"SELLYOURSAAS_TEST_ARCHIVES_PATH",GETPOST("SELLYOURSAAS_TEST_ARCHIVES_PATH"),'chaine',0,'',$conf->entity);

		$dir=GETPOST("SELLYOURSAAS_PAID_ARCHIVES_PATH");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db,"SELLYOURSAAS_PAID_ARCHIVES_PATH",GETPOST("SELLYOURSAAS_PAID_ARCHIVES_PATH"),'chaine',0,'',$conf->entity);

		$dir=GETPOST("SELLYOURSAAS_NAME_RESERVED");
		//if (! dol_is_dir($dir) && ! dol_is_link($dir)) setEventMessage($langs->trans("ErrorDirNotFound",$dir),'warnings');
		dolibarr_set_const($db,"SELLYOURSAAS_NAME_RESERVED",GETPOST("SELLYOURSAAS_NAME_RESERVED"),'chaine',0,'',$conf->entity);

		// Save images
		$dirforimage=$conf->mycompany->dir_output.'/logos/';
        foreach($_FILES as $postkey => $postvar)
        {
            $suffix = '';
            if (preg_match('/^logoblack/', $postkey, $reg))
            {
                $suffix.='_BLACK';
            }
            if (preg_match('/^logo(black)?_(.+)$/', $postkey, $reg))
            {
                $suffix.='_'.strtoupper($reg[2]);
            }
            $varforimage=$postkey;

    		if ($_FILES[$varforimage]["tmp_name"])
    		{
    			if (preg_match('/([^\\/:]+)$/i',$_FILES[$varforimage]["name"],$reg))
    			{
    				$original_file=$reg[1];

    				$isimage=image_format_supported($original_file);
    				if ($isimage >= 0)
    				{
    					dol_syslog("Move file ".$_FILES[$varforimage]["tmp_name"]." to ".$dirforimage.$original_file);
    					if (! is_dir($dirforimage))
    					{
    						dol_mkdir($dirforimage);
    					}
    					$result=dol_move_uploaded_file($_FILES[$varforimage]["tmp_name"],$dirforimage.$original_file,1,0,$_FILES[$varforimage]['error']);
    					if ($result > 0)
    					{
    					    dolibarr_set_const($db, "SELLYOURSAAS_LOGO".$suffix, $original_file, 'chaine', 0, '', $conf->entity);

    						// Create thumbs of logo (Note that PDF use original file and not thumbs)
    						if ($isimage > 0)
    						{
    							// Create thumbs
    							//$object->addThumbs($newfile);    // We can't use addThumbs here yet because we need name of generated thumbs to add them into constants.

    							// Create small thumb, Used on logon for example
    							$imgThumbSmall = vignette($dirforimage.$original_file, $maxwidthsmall, $maxheightsmall, '_small', $quality);
    							if (image_format_supported($imgThumbSmall) >= 0 && preg_match('/([^\\/:]+)$/i',$imgThumbSmall,$reg))
    							{
    								$imgThumbSmall = $reg[1];    // Save only basename
    								dolibarr_set_const($db, "SELLYOURSAAS_LOGO_SMALL".$suffix, $imgThumbSmall, 'chaine', 0, '', $conf->entity);
    							}
    							else dol_syslog($imgThumbSmall);

    							// Create mini thumb, Used on menu or for setup page for example
    							$imgThumbMini = vignette($dirforimage.$original_file, $maxwidthmini, $maxheightmini, '_mini', $quality);
    							if (image_format_supported($imgThumbMini) >= 0 && preg_match('/([^\\/:]+)$/i',$imgThumbMini,$reg))
    							{
    								$imgThumbMini = $reg[1];     // Save only basename
    								dolibarr_set_const($db, "SELLYOURSAAS_LOGO_MINI".$suffix, $imgThumbMini, 'chaine', 0, '', $conf->entity);
    							}
    							else dol_syslog($imgThumbMini);
    						}
    						else dol_syslog("ErrorImageFormatNotSupported", LOG_WARNING);
    					}
    					else if (preg_match('/^ErrorFileIsInfectedWithAVirus/',$result))
    					{
    						$error++;
    						$langs->load("errors");
    						$tmparray=explode(':',$result);
    						setEventMessages($langs->trans('ErrorFileIsInfectedWithAVirus', $tmparray[1]), null, 'errors');
    					}
    					else
    					{
    						$error++;
    						setEventMessages($langs->trans("ErrorFailedToSaveFile"), null, 'errors');
    					}
    				}
    				else
    				{
    					$error++;
    					$langs->load("errors");
    					setEventMessages($langs->trans("ErrorBadImageFormat"), null, 'errors');
    				}
    			}
    		}
        }
	}
}

if ($action == 'removelogo')
{
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
if ($action == 'removelogoblack')
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$constname='SELLYOURSAAS_LOGO_BLACK'.GETPOST('suffix', 'aZ09');
	$logofile=$conf->mycompany->dir_output.'/logos/'.$conf->global->$constname;
	if ($conf->global->$constname != '') dol_delete_file($logofile);
	dolibarr_del_const($db, "$constname",$conf->entity);

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

$help_url="";
llxHeader("",$langs->trans("SellYouSaasSetup"),$help_url);

$linkback='<a href="'.($backtopage?$backtopage:DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans('SellYouSaasSetup'), $linkback, 'setup');

//$head=array();
//dol_fiche_head($head, 'serversetup', $langs->trans("SellYourSaas"), -1);

print '<span class="opacitymedium">'.$langs->trans("SellYouSaasDesc")."</span><br>\n";
print "<br>\n";

$error=0;


print '<form enctype="multipart/form-data" method="POST" action="'.$_SERVER["PHP_SELF"].'" name="form_index">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set">';

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td class="titlefield">'.$langs->trans("ParametersOnMasterServer").'</td><td>'.$langs->trans("Value").'</td>';
print '<td>'.$langs->trans("Examples").'<div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
print "</tr>\n";

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_FORCE_STRIPE_TEST").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_FORCE_STRIPE_TEST" value="'.$conf->global->SELLYOURSAAS_FORCE_STRIPE_TEST.'">';
print '</td>';
print '<td>1</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasName").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_NAME" value="'.$conf->global->SELLYOURSAAS_NAME.'" class="minwidth300">';
print '</td>';
print '<td>My SaaS service</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasMainDomain").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_MAIN_DOMAIN_NAME" value="'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'" class="minwidth300">';
print '</td>';
print '<td>mysaasdomainname.com</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("SellYourSaasSubDomains"), $langs->trans("SellYourSaasSubDomainsHelp")).'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SUB_DOMAIN_NAMES" value="'.$conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES.'" class="minwidth300">';
print '</td>';
print '<td>with.mysaasdomainname.com,with.mysaas2.com:mysaas2.com...</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasSubDomainsIP").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SUB_DOMAIN_IP" value="'.$conf->global->SELLYOURSAAS_SUB_DOMAIN_IP.'" class="minwidth300">';
print '</td>';
print '<td>192.168.0.1,123.456.789.012...</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasMainEmail").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_MAIN_EMAIL" value="'.$conf->global->SELLYOURSAAS_MAIN_EMAIL.'" class="minwidth300">';
print '</td>';
print '<td>contact@mysaasdomainname.com</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasMainEmail").' (Premium)</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_MAIN_EMAIL_PREMIUM" value="'.$conf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM.'" class="minwidth300">';
print '</td>';
print '<td>contact+premium@mysaasdomainname.com</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasSupervisionEmail").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SUPERVISION_EMAIL" value="'.$conf->global->SELLYOURSAAS_SUPERVISION_EMAIL.'" class="minwidth300">';
print '</td>';
print '<td>supervision@mysaasdomainname.com</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasNoReplyEmail").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_NOREPLY_EMAIL" value="'.$conf->global->SELLYOURSAAS_NOREPLY_EMAIL.'" class="minwidth300">';
print '</td>';
print '<td>noreply@mysaasdomainname.com</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("DirForScriptPath").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="DOLICLOUD_SCRIPTS_PATH" value="'.$conf->global->DOLICLOUD_SCRIPTS_PATH.'">';
print '</td>';
print '<td>'.dol_buildpath('sellyoursaas/scripts').'</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("DefaultProductForInstances").'</td>';
print '<td>';
$defaultproductid=$conf->global->SELLYOURSAAS_DEFAULT_PRODUCT;
print $form->select_produits($defaultproductid, 'SELLYOURSAAS_DEFAULT_PRODUCT');
print '</td>';
print '<td>My SaaS service for instance</td>';
print '</tr>';

/*print '<tr class="oddeven"><td>'.$langs->trans("DefaultProductForUsers").'</td>';
print '<td>';
$defaultproductforusersid=$conf->global->SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS;
print $form->select_produits($defaultproductforusersid, 'SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS');
print '</td>';
print '<td>My SaaS service for users</td>';
print '</tr>';
*/

print '<tr class="oddeven"><td>'.$langs->trans("DefaultCategoryForSaaSServices").'</td>';
print '<td>';
$defaultproductcategid=$conf->global->SELLYOURSAAS_DEFAULT_PRODUCT_CATEG;
print $formother->select_categories(Categorie::TYPE_PRODUCT, $defaultproductcategid, 'SELLYOURSAAS_DEFAULT_PRODUCT_CATEG', 0, 1, 'miwidth300');
print '</td>';
print '<td>SaaS Products</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("DefaultCategoryForSaaSCustomers").'</td>';
print '<td>';
$defaultcustomercategid=$conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG;
print $formother->select_categories(Categorie::TYPE_CUSTOMER, $defaultcustomercategid, 'SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG', 0, 1, 'miwidth300');
print '</td>';
print '<td>SaaS Customers</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("DefaultCategoryForSaaSResellers").'</td>';
print '<td>';
$defaultcustomercategid=$conf->global->SELLYOURSAAS_DEFAULT_RESELLER_CATEG;
print $formother->select_categories(Categorie::TYPE_SUPPLIER, $defaultcustomercategid, 'SELLYOURSAAS_DEFAULT_RESELLER_CATEG', 0, 1, 'miwidth300');
print '</td>';
print '<td>SaaS Resellers</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_ALLOW_RESELLER_PROGRAM").'</td>';
print '<td>';
$allowresellerprogram=$conf->global->SELLYOURSAAS_ALLOW_RESELLER_PROGRAM;
print $form->selectyesno('SELLYOURSAAS_ALLOW_RESELLER_PROGRAM', $allowresellerprogram, 1);
print '</td>';
print '<td>Set to yes if you want your customers being able to apply to become resellers</td>';
print '</tr>';

if ($allowresellerprogram)
{
    print '<tr class="oddeven"><td>'.$langs->trans("DefaultCommission");
    print '</td>';
    print '<td>';
    print '<input class="maxwidth75" type="text" name="SELLYOURSAAS_DEFAULT_COMMISSION" value="'.$conf->global->SELLYOURSAAS_DEFAULT_COMMISSION.'">';
    print '</td>';
    print '<td>25</td>';
    print '</tr>';
}

print '<tr class="oddeven"><td>'.$langs->trans("RefsUrl", DOL_DOCUMENT_ROOT.'/sellyoursaas/git');
print '</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_REFS_URL" value="'.$conf->global->SELLYOURSAAS_REFS_URL.'">';
print '</td>';
print '<td>https://admin.mysaasdomainname.com/git</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasAccountUrl").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_ACCOUNT_URL" value="'.$conf->global->SELLYOURSAAS_ACCOUNT_URL.'">';
print '</td>';
print '<td>https://myaccount.mysaasdomainname.com<br>Note: Virtual hosts for such domains must link to <strong>'.dol_buildpath('sellyoursaas/myaccount').'</strong></td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasPricesUrl").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_PRICES_URL" value="'.$conf->global->SELLYOURSAAS_PRICES_URL.'">';
print '</td>';
print '<td>https://myaccount.mysaasdomainname.com/prices.html</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasStatusUrl").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_STATUS_URL" value="'.$conf->global->SELLYOURSAAS_STATUS_URL.'">';
print '</td>';
print '<td>https://status.mysaasdomainname.com</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SellYourSaasResellerUrl").'</td>';
print '<td>';
print '<input class="minwidth300" type="text" name="SELLYOURSAAS_RESELLER_URL" value="'.$conf->global->SELLYOURSAAS_RESELLER_URL.'">';
print '</td>';
print '<td>https://www.mysaasdomainname.com/en-become-a-dolicloud-reseller.php</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("FooterContent").'</td>';
print '<td>';
print '<textarea name="SELLYOURSAAS_MYACCOUNT_FOOTER" class="quatrevingtpercent" rows="3">'.$conf->global->SELLYOURSAAS_MYACCOUNT_FOOTER.'</textarea>';
print '</td>';
print '<td>&lt;script&gt;Your google analytics code&lt;/script&gt;</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("ConversionContent").'</td>';
print '<td>';
print '<textarea name="SELLYOURSAAS_CONVERSION_FOOTER" class="quatrevingtpercent" rows="3">'.$conf->global->SELLYOURSAAS_CONVERSION_FOOTER.'</textarea>';
print '</td>';
print '<td>&lt;script&gt;Your conversion trackers&lt;/script&gt;</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("AnonymousUser").'</td>';
print '<td>';
print $form->select_dolusers($conf->global->SELLYOURSAAS_ANONYMOUSUSER, 'SELLYOURSAAS_ANONYMOUSUSER', 1);
print '</td>';
print '<td>User used for all anonymous action (registering, actions from customer dashboard, ...)</td>';
print '</tr>';


print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NAME_RESERVED").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_NAME_RESERVED" value="'.$conf->global->SELLYOURSAAS_NAME_RESERVED.'">';
print '</td>';
print '<td>^mycompany[0-9]*\.</td>';
print '</tr>';


print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_SALTFORPASSWORDENCRYPTION").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_SALTFORPASSWORDENCRYPTION" value="'.$conf->global->SELLYOURSAAS_SALTFORPASSWORDENCRYPTION.'">';
print '</td>';
print '<td>Salt use to build substitution keys __APPPASSWORDxxxSALTED__</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_HASHALGOFORPASSWORD").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_HASHALGOFORPASSWORD" value="'.$conf->global->SELLYOURSAAS_HASHALGOFORPASSWORD.'">';
print '</td>';
print '<td>Algorithm used to build substitution keys __APPPASSWORD0xxx__</td>';
print '</tr>';


print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT" value="'.$conf->global->SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT.'">';
print '</td>';
print '<td>7</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT" value="'.$conf->global->SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT.'">';
print '</td>';
print '<td>1</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND" value="'.$conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND.'">';
print '</td>';
print '<td>2</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND" value="'.$conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND.'">';
print '</td>';
print '<td>12</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT" value="'.$conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT.'">';
print '</td>';
print '<td>30</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT" value="'.$conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT.'">';
print '</td>';
print '<td>120</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAXDEPLOYMENTPERIP").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAXDEPLOYMENTPERIP" value="'.(empty($conf->global->SELLYOURSAAS_MAXDEPLOYMENTPERIP)?20:$conf->global->SELLYOURSAAS_MAXDEPLOYMENTPERIP).'">';
print '</td>';
print '<td>20</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR" value="'.(empty($conf->global->SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR)?5:$conf->global->SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR).'">';
print '</td>';
print '<td>5</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_INFRA_COST").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_INFRA_COST" value="'.(empty($conf->global->SELLYOURSAAS_INFRA_COST)?0:$conf->global->SELLYOURSAAS_INFRA_COST).'">';
print '</td>';
print '<td>5</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBHOURSBETWEENTRIES").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBHOURSBETWEENTRIES" value="'.$conf->global->SELLYOURSAAS_NBHOURSBETWEENTRIES.'">';
print '</td>';
print '<td>49</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES").'</td>';
print '<td>';
print '<input class="maxwidth50" type="text" name="SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES" value="'.$conf->global->SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES.'">';
print '</td>';
print '<td>35</td>';
print '</tr>';

$tmpservices=array();
$tmpservicessub = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
foreach($tmpservicessub as $key => $tmpservicesub)
{
    $tmpservicesub = preg_replace('/:.*$/', '', $tmpservicesub);
    if ($key > 0) $tmpservices[$tmpservicesub]=getDomainFromURL($tmpservicesub, 1);
    else $tmpservices['0']=getDomainFromURL($tmpservicesub, 1);
}
foreach($tmpservices as $key => $tmpservice)
{
    $suffix = '';
    if ($key != '0') $suffix='_'.strtoupper(str_replace('.', '_', $tmpservice));

    // Logo
    print '<tr class="oddeven"><td><label for="logo">'.$tmpservice.' - '.$langs->trans("LogoWhiteBackground").' (png,jpg)</label></td><td>';
    print '<table width="100%" class="nobordernopadding"><tr class="nocellnopadd"><td valign="middle" class="nocellnopadd">';
    print '<input type="file" class="flat class=minwidth200" name="logo'.$suffix.'" id="logo'.$suffix.'">';
    print '</td><td class="nocellnopadd" valign="middle" align="right">';
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
    print '<tr class="oddeven"><td><label for="logo">'.$tmpservice.' - '.$langs->trans("LogoBlackBackground").' (png,jpg)</label></td><td>';
    print '<table width="100%" class="nobordernopadding"><tr class="nocellnopadd"><td valign="middle" class="nocellnopadd">';
    print '<input type="file" class="flat class=minwidth200" name="logoblack'.$suffix.'" id="logoblack'.$suffix.'">';
    print '</td><td class="nocellnopadd" valign="middle" align="right">';
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

print '</table>';


print '<br>';


print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td class="titlefield">'.$langs->trans("ParametersOnDeploymentServers").'</td><td>'.$langs->trans("Value").'</td>';
print '<td>'.$langs->trans("Examples").'<div class="floatright"><input type="submit" class="button buttongen" value="'.$langs->trans("Save").'"></div></td>';
print "</tr>\n";

print '<tr class="oddeven"><td>'.$langs->trans("DirForDoliCloudInstances").'</td>';
print '<td>';
print '<input size="40" type="text" name="DOLICLOUD_INSTANCES_PATH" value="'.$conf->global->DOLICLOUD_INSTANCES_PATH.'">';
print '</td>';
print '<td>/home/jail/home</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("DirForBackupInstances").'</td>';
print '<td>';
print '<input size="40" type="text" name="DOLICLOUD_BACKUP_PATH" value="'.$conf->global->DOLICLOUD_BACKUP_PATH.'">';
print '</td>';
print '<td>/home/jail/backup</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_TEST_ARCHIVES_PATH").'</td>';
print '<td>';
print '<input size="40" type="text" name="SELLYOURSAAS_TEST_ARCHIVES_PATH" value="'.$conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'">';
print '</td>';
print '<td>/home/jail/archives-test</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SELLYOURSAAS_PAID_ARCHIVES_PATH").'</td>';
print '<td>';
print '<input size="40" type="text" name="SELLYOURSAAS_PAID_ARCHIVES_PATH" value="'.$conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'">';
print '</td>';
print '<td>/home/jail/archives-paid</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("SSHPublicKey").'</td>';
print '<td>';
print '<textarea name="SELLYOURSAAS_PUBLIC_KEY" class="quatrevingtpercent" rows="3">'.$conf->global->SELLYOURSAAS_PUBLIC_KEY.'</textarea>';
print '</td>';
print '<td>Your SSH public key(s) deployed into each new instance</td>';
print '</tr>';

print '<tr class="oddeven"><td>'.$langs->trans("LoginForSupport").'</td>';
print '<td>';
print '<input type="text" name="SELLYOURSAAS_LOGIN_FOR_SUPPORT" value="'.$conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT.'">';
print '</td>';
print '<td>Login to use to create a support user account on customer instances</td>';
print '</tr>';

print '</table>';

print "</form>\n";


print "<br>";
print '<br>';

// Define $urlwithroot
$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT,'/').'$/i','',trim($dolibarr_main_url_root));
$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
//$urlwithroot=DOL_MAIN_URL_ROOT;						// This is to use same domain name than current. For Paypal payment, we can use internal URL like localhost.

/*
var_dump(DOL_URL_ROOT);
var_dump(dol_buildpath('/sellyoursaas/public/spamreport.php', 1));
var_dump(DOL_MAIN_URL_ROOT);
*/

$message='';
$url='<a href="'.dol_buildpath('/sellyoursaas/public/spamreport.php', 3).'?key='.($conf->global->SELLYOURSAAS_SECURITY_KEY?urlencode($conf->global->SELLYOURSAAS_SECURITY_KEY):'...').'" target="_blank">'.dol_buildpath('/sellyoursaas/public/spamreport.php', 3).'?key='.($conf->global->SELLYOURSAAS_SECURITY_KEY?urlencode($conf->global->SELLYOURSAAS_SECURITY_KEY):'KEYNOTDEFINED').'</a>';
$message.=img_picto('', 'object_globe.png').' '.$langs->trans("EndPointFor", "SpamReport", $url);
print $message;

print '<br>';

$message='';
$url='<a href="'.dol_buildpath('/sellyoursaas/myaccount/public/test.php', 3).'?key='.($conf->global->SELLYOURSAAS_SECURITY_KEY?urlencode($conf->global->SELLYOURSAAS_SECURITY_KEY):'...').'" target="_blank">'.dol_buildpath('/sellyoursaas/public/test.php', 3).'?key='.($conf->global->SELLYOURSAAS_SECURITY_KEY?urlencode($conf->global->SELLYOURSAAS_SECURITY_KEY):'KEYNOTDEFINED').'</a>';
$message.=img_picto('', 'object_globe.png').' '.$langs->trans("EndPointFor", "Test", $url);
print $message;

print "<br>";
print '<br>';


print 'idn_to_ascii function: '.yn(function_exists('idn_to_ascii')).'<br>';
print 'checkdnsrr function: '.yn(function_exists('checkdnsrr')).'<br>';
print "<br>\n";


//dol_fiche_end();


llxfooter();

$db->close();
