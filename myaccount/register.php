<?php
/* Copyright (C) 2017-2019 Laurent Destailleur  <eldy@users.sourceforge.net>
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

//if (! defined('NOREQUIREUSER'))  define('NOREQUIREUSER','1');
//if (! defined('NOREQUIREDB'))    define('NOREQUIREDB','1');
//if (! defined('NOREQUIRESOC'))   define('NOREQUIRESOC','1');
//if (! defined('NOREQUIRETRAN'))  define('NOREQUIRETRAN','1');
//if (! defined('NOCSRFCHECK'))    define('NOCSRFCHECK','1');			// Do not check anti CSRF attack test
//if (! defined('NOIPCHECK'))      define('NOIPCHECK','1');				// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined('NOSTYLECHECK'))   define('NOSTYLECHECK','1');			// Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL','1');		// Do not check anti POST attack test
//if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU','1');			// If there is no need to load and show top and left menu
//if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML','1');			// If we don't need to load the html.form.class.php
//if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');
if (! defined("NOLOGIN")) {
	define("NOLOGIN", '1');
}				    // If this page is public (can be called outside logged session)
if (! defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}				// Do not check IP defined into conf $dolibarr_main_restrict_ip
if (! defined("MAIN_LANG_DEFAULT") && empty($_GET['lang'])) {
	define('MAIN_LANG_DEFAULT', 'auto');
}
if (! defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}

// Add specific definition to allow a dedicated session management
include './mainmyaccount.inc.php';

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) {
	$i--;
	$j--;
}
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) {
	$res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
}
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) {
	$res=include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) {
	$res=@include "../../main.inc.php";
}
if (! $res && file_exists("../../../main.inc.php")) {
	$res=@include "../../../main.inc.php";
}
if (! $res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
dol_include_once('/sellyoursaas/class/packages.class.php');
dol_include_once('/sellyoursaas/class/deploymentserver.class.php');

// Re set variables specific to new environment
$conf->global->SYSLOG_FILE_ONEPERSESSION='register';


//$langs=new Translate('', $conf);
//$langs->setDefaultLang(GETPOST('lang', 'aZ09')?GETPOST('lang', 'aZ09'):'auto');
$langs->loadLangs(array("main","companies","sellyoursaas@sellyoursaas","errors"));

if ($langs->defaultlang == 'en_US') {
	$langsen = $langs;
} else {
	$langsen=new Translate('', $conf);
	$langsen->setDefaultLang('en_US');
	$langsen->loadLangs(array("main","companies","sellyoursaas@sellyoursaas","errors"));
}


$partner=GETPOST('partner', 'int');
$partnerkey=GETPOST('partnerkey', 'alpha');
$plan=GETPOST('plan', 'alpha');
$sldAndSubdomain=strtolower(GETPOST('sldAndSubdomain', 'alpha'));
$tldid=GETPOST('tldid', 'alpha');
$origin = GETPOST('origin', 'aZ09');

$socid=GETPOST('socid', 'int') ? GETPOST('socid', 'int') : GETPOST('reusesocid', 'int');
$reusecontractid = GETPOST('reusecontractid', 'int');
$reusesocid = GETPOST('reusesocid', 'int');
$fromsocid = GETPOST('fromsocid', 'int');
$disablecustomeremail = GETPOST('disablecustomeremail', 'alpha');
$extcss=GETPOST('extcss', 'alpha');
if (empty($extcss)) {
	$extcss = getDolGlobalString('SELLYOURSAAS_EXTCSS', 'dist/css/myaccount.css');
} elseif ($extcss == 'generic') {
	$extcss = 'dist/css/myaccount.css';
}


// SERVER_NAME here is myaccount.mydomain.com (we can exploit only the part mydomain.com)
include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
$domainname = getDomainFromURL($_SERVER["SERVER_NAME"], 1);

$productid=GETPOST('service', 'int');
$productref=(GETPOST('productref', 'alpha') ? GETPOST('productref', 'alpha') : '');
$defaultproduct = '';
if (empty($productid) && empty($productref)) {
	$productref = $plan;
	if (empty($productref)) {
		$suffix = '_'.strtoupper(str_replace('.', '_', $domainname));
		$constname = "SELLYOURSAAS_DEFAULT_PRODUCT".$suffix;
		$defaultproduct = (empty($conf->global->$constname) ? $conf->global->SELLYOURSAAS_DEFAULT_PRODUCT : $conf->global->$constname);

		// Take first plan found
		$sqlproducts = 'SELECT p.rowid, p.ref, p.label, p.price, p.price_ttc, p.duration, pa.restrict_domains';
		$sqlproducts.= ' FROM '.MAIN_DB_PREFIX.'product as p, '.MAIN_DB_PREFIX.'product_extrafields as pe';
		$sqlproducts.= ' LEFT JOIN '.MAIN_DB_PREFIX.'packages as pa ON pe.package = pa.rowid';
		$sqlproducts.= ' WHERE p.tosell = 1 AND p.entity = '.$conf->entity;
		$sqlproducts.= " AND pe.fk_object = p.rowid AND pe.app_or_option = 'app'";
		$sqlproducts.= " AND p.ref NOT LIKE '%DolibarrV1%'";
		$sqlproducts.= " AND (pa.restrict_domains IS NULL"; // restict_domains can be empty (it's ok)
		$sqlproducts.= " OR pa.restrict_domains = '".$db->escape($domainname)."'"; // can be mydomain.com
		$sqlproducts.= " OR pa.restrict_domains LIKE '%.".$db->escape($domainname)."'"; // can be with.mydomain.com or the last domain of [mydomain1.com,with.mydomain2.com]
		$sqlproducts.= " OR pa.restrict_domains LIKE '%.".$db->escape($domainname).",%'"; // can be the first or the middle domain of [with.mydomain1.com,with.mydomain2.com,mydomain3.com]
		$sqlproducts.= " OR pa.restrict_domains LIKE '".$db->escape($domainname).",%'"; // can be the first domain of [mydomain1.com,mydomain2.com]
		$sqlproducts.= " OR pa.restrict_domains LIKE '%,".$db->escape($domainname).",%'"; // can be the middle domain of [mydomain1.com,mydomain2.com,mydomain3.com]
		$sqlproducts.= " OR pa.restrict_domains LIKE '%,".$db->escape($domainname)."'"; // can be the last domain of [mydomain1.com,mydomain2.com]
		$sqlproducts.= ")";
		if (! empty($defaultproduct)) {
			$sqlproducts.= " AND p.rowid = ".((int) $defaultproduct);
		}
		$sqlproducts.= " ORDER BY p.datec";
		//print $_SERVER["SERVER_NAME"].' - '.$sqlproducts;
		$resqlproducts = $db->query($sqlproducts);
		if ($resqlproducts) {
			$num = $db->num_rows($resqlproducts);

			$tmpprod = new Product($db);
			$obj = $db->fetch_object($resqlproducts);
			if ($obj) {
				$productref = $obj->ref;
			}
		} else {
			dol_print_error($db);
		}
	}
}

$tmpproduct = new Product($db);
$tmppackage = new Packages($db);

// Load main product
if ($productref != 'none') {
	$result = $tmpproduct->fetch($productid, $productref);
	if (empty($tmpproduct->id)) {
		print 'Service/Plan (Product id / ref) '.$productid.' / '.$productref.' was not found.';
		exit;
	}
	// We have the main product, we are searching the package
	if (empty($tmpproduct->array_options['options_package'])) {
		print 'Service/Plan (Product id / ref) '.$tmpproduct->id.' / '.$productref.' has no package defined on it.';
		exit;
	}
	$productref = $tmpproduct->ref;

	if (empty($tmpproduct->status)) {
		print "Product '".$productref."' is not on sale.";
		exit;
	}

	$tmppackage->fetch($tmpproduct->array_options['options_package']);
	if (empty($tmppackage->id)) {
		print "Package with id '".$tmpproduct->array_options['options_package']."' was not found.";
		exit;
	}
}

if (getDolGlobalString('CONTRACT_ADDON') == 'mod_contract_olive') {
	print "You must configure the module Contract to a numbering module that is able to generate new number for new contracts.";
	exit;
}


// Check partner exists if provided
if ($partner) {
	$partnerthirdparty=new Societe($db);
	$partnerthirdparty->fetch($partner);
	if (! $partnerthirdparty->id || (md5($partnerthirdparty->name_alias) != GETPOST('partnerkey', 'alpha') && $partnerthirdparty->name_alias != GETPOST('partnerkey', 'alpha'))) {
		print 'Bad partner keys.';
		exit;
	}
}


if ($reusecontractid) {
	$contract = new Contrat($db);
	$contract->fetch($reusecontractid);
	$socid = ($contract->socid > 0 ? $contract->socid : $contract->fk_soc);
	$tmparray=explode('.', $contract->ref_customer, 2);
	$sldAndSubdomain=strtolower($tmparray[0]);
	$tldid='.'.$tmparray[1];
}

$mythirdparty = new Societe($db);
if ($socid > 0) {
	$mythirdparty->fetch($socid);
}

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('sellyoursaas-register'));


// Code to set cookie for first utm_source
if (!empty($_GET["utm_source"]) || !empty($_GET["origin"]) || !empty($_GET["partner"])) {
	$cookiename = "utm_source_cookie";
	$cookievalue = empty($_GET["utm_source"]) ? (empty($_GET["origin"]) ? 'partner'.$_GET["partner"] : $_GET["origin"]) : $_GET["utm_source"];
	if (empty($_COOKIE[$cookiename]) && $domainname) {
		$domain = $domainname;
		$cookievalue .= '-'.date("Ymd-His").'-register';
		setcookie($cookiename, empty($cookievalue) ? '' : $cookievalue, empty($cookievalue) ? 0 : (time() + (86400 * 90)), '/', $domain, false, true); // keep cookie 90 days and add tag httponly
	}
}



/*
 * Action
 */

// Nothing



/*
 * View
 */

$form = new Form($db);

$conf->dol_hide_topmenu = 1;
$conf->dol_hide_leftmenu = 1;

$favicon=getDomainFromURL($_SERVER['SERVER_NAME'], 0);
if (! preg_match('/\.(png|jpg)$/', $favicon)) {
	$favicon.='.png';
}
if (! empty($conf->global->MAIN_FAVICON_URL)) {
	$favicon=$conf->global->MAIN_FAVICON_URL;
}

$head = '';
if ($favicon) {
	$href = 'img/'.$favicon;
	if (preg_match('/^http/i', $favicon)) {
		$href = $favicon;
	}
	$head.='<link rel="icon" href="'.$href.'">'."\n";
}
$head .= '<!-- Bootstrap core CSS -->';
$head .= '<link href="dist/css/bootstrap.css" type="text/css" rel="stylesheet">';
$head .= '<link href="'.$extcss.'" type="text/css" rel="stylesheet">';

// Javascript code on logon page only to detect user tz, dst_observed, dst_first, dst_second
$arrayofjs=array(
	'/core/js/dst.js'.(empty($conf->dol_use_jmobile) ? '' : '?version='.urlencode(DOL_VERSION))
);

$title = $langs->trans("Registration").($tmpproduct->label ? ' ('.$tmpproduct->label.')' : '');

$prefix=dol_getprefix('');
$cookieregistrationa='DOLREGISTERA_'.$prefix;
if (empty($_COOKIE[$cookieregistrationa])) {
	setcookie($cookieregistrationa, 1, 0, "/", null, false, true);	// Cookie to count nb of registration from this computer
}

llxHeader($head, $title, '', '', 0, 0, $arrayofjs, array(), '', 'register', '', 0, 1);

?>

<div id="waitMask" style="display:none;">
	<font size="3em" style="color:#888; font-weight: bold;"><?php echo $langs->trans("InstallingInstance") ?><br><?php echo $langs->trans("PleaseWait") ?><br></font>
	<img id="waitMaskImg" width="100px" src="<?php echo 'ajax-loader.gif'; ?>" alt="Loading" />
</div>

<?php

$parameters = array(
	'partner' => $partner,
	'partnerkey' => $partnerkey,
	'plan' => $plan,
	'sldAndSubdom' => $sldAndSubdomain,
	'tldid' => $tldid,
	'origin' => $origin,
	'reusecontractid' => $reusecontractid,
	'reusesocid' => $reusesocid,
	'fromsocid' => $fromsocid,
	'disablecusto' => $disablecustomeremail,
	'extcss' => $extcss,
	'domainname' => $domainname,
	'productid' => $productid,
	'productref' => $productref,
	'tmppackage' => $tmppackage,
	'mythirdparty' => $mythirdparty,
	'tmpproduct' => $tmpproduct
);
// return values of this hook:
// 0 = resPrint appended to the content of the page
// 1 = page contents replaced with resPrint
$reshook = $hookmanager->executeHooks('sellyoursaasGetRegisterPageForm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
$hookGetRegisterPageFormResPrint = $hookmanager->resPrint;
if ($reshook == 0) {
	?>
	<div class="large">
		<?php
		$sellyoursaasdomain = $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME;
		$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;

		$domainname=getDomainFromURL($_SERVER['SERVER_NAME'], 1);
		$constforaltname = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$domainname;
		if (! empty($conf->global->$constforaltname)) {
			$sellyoursaasdomain = $domainname;
			$sellyoursaasname = $conf->global->$constforaltname;
			//var_dump($constforaltname.' '.$sellyoursaasdomain.' '.$sellyoursaasname);   // Example: 'SELLYOURSAAS_NAME_FORDOMAIN-glpi-network.cloud glpi-network.cloud GLPI-Network'
		}

		$linklogo = '';
		$homepage = 'https://'.(empty($conf->global->SELLYOURSAAS_FORCE_MAIN_DOMAIN_NAME) ? $sellyoursaasdomain : $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME);
		if (isset($partnerthirdparty) && $partnerthirdparty->id > 0) {     // Show logo of partner
			require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
			$ecmfile=new EcmFiles($db);
			$relativepath = $conf->societe->multidir_output[$conf->entity]."/".$partnerthirdparty->id."/logos/".$partnerthirdparty->logo;
			$relativepath = preg_replace('/^'.preg_quote(DOL_DATA_ROOT, '/').'/', '', $relativepath);
			$relativepath = preg_replace('/[\\/]$/', '', $relativepath);
			$relativepath = preg_replace('/^[\\/]/', '', $relativepath);

			$ecmfile->fetch(0, '', $relativepath);
			if ($ecmfile->id > 0) {
				$linklogo = DOL_URL_ROOT.'/viewimage.php?modulepart=societe&hashp='.$ecmfile->share;
			}
			$homepage = '';
			if (! empty($partnerthirdparty->url)) {
				$url = preg_replace('#^https?://#', '', rtrim($partnerthirdparty->url, '/'));
				$homepage = 'https://'.$url;
			}
		}
		if (empty($linklogo)) {               // Show main logo of Cloud service
			// Show logo (search in order: small company logo, large company logo, theme logo, common logo)
			$linklogo = '';
			$constlogo = 'SELLYOURSAAS_LOGO';
			$constlogosmall = 'SELLYOURSAAS_LOGO_SMALL';

			$constlogoalt = 'SELLYOURSAAS_LOGO_'.str_replace('.', '_', strtoupper($sellyoursaasdomain));
			$constlogosmallalt = 'SELLYOURSAAS_LOGO_SMALL_'.str_replace('.', '_', strtoupper($sellyoursaasdomain));

			//var_dump($sellyoursaasdomain.' '.$constlogoalt.' '.$conf->global->$constlogoalt);exit;
			if (! empty($conf->global->$constlogoalt)) {
				$constlogo=$constlogoalt;
				$constlogosmall=$constlogosmallalt;
			}

			if (empty($linklogo) && ! empty($conf->global->$constlogosmall)) {
				if (is_readable($conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constlogosmall)) {
					$linklogo=DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode('logos/thumbs/'.$conf->global->$constlogosmall);
				}
			} elseif (empty($urllogo) && ! empty($conf->global->$constlogo)) {
				if (is_readable($conf->mycompany->dir_output.'/logos/'.$conf->global->$constlogo)) {
					$linklogo=DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode('logos/'.$conf->global->$constlogo);
				}
			} elseif (empty($urllogo) && is_readable(DOL_DOCUMENT_ROOT.'/theme/'.$conf->theme.'/img/dolibarr_logo.png')) {
				$linklogo=DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/dolibarr_logo.png';
			} elseif (empty($urllogo) && is_readable(DOL_DOCUMENT_ROOT.'/theme/dolibarr_logo.png')) {
				$linklogo=DOL_URL_ROOT.'/theme/dolibarr_logo.png';
			} else {
				$linklogo=DOL_URL_ROOT.'/theme/login_logo.png';
			}
		}

		if (! GETPOST('noheader', 'int')) {
			?>
		<div class="page-header-top">
			<div class="container">
			  <div class="registerheader" style="display:flex; justify-content:space-between;">
				  <div class="valignmiddle" style="padding-right: 25px;">
				  <a href="<?php echo $homepage ?>"><img class="logoheader"  src="<?php echo $linklogo; ?>" id="logo" /></a><br>
				  </div>
				  <?php if (empty($mythirdparty->id)) {
						$langs->load("website"); ?>
				  <div class="paddingtop20" style="float: right;">
					  <div class="btn-sm">
					  <span class="opacitymedium hideonsmartphone paddingright valignmiddle"><?php echo $langs->trans("AlreadyHaveAnAccount"); ?></span>
						<?php if (! empty($partner) || ! empty($partnerkey)) {
							print '<br class="hideonsmartphone">';
						} ?>
					  <a href="/" class="btn blue btn-sm btnalreadyanaccount valignmiddle"><?php echo $langs->trans("LoginAction"); ?></a>
					  </div>
						<?php if (! empty($homepage)) { ?>
					  <div class="btn-sm home-page-url">
					  <span class="opacitymedium"><a class="blue btn-sm" style="padding-left: 0;" href="<?php echo $homepage ?>"><?php echo $langs->trans("BackToHomePage"); ?></a></span>
					  </div>
						<?php } ?>
				  </div>
						<?php
				  } ?>
			  </div>

			  <!-- BEGIN TOP NAVIGATION MENU -->
			  <div class="top-menu">
			  </div> <!-- END TOP NAVIGATION MENU -->

			</div>
		  </div>
			<?php
		}
		?>
	  <div class="block medium center">
		<?php
		if (! GETPOST('noheader', 'int')) {
			?>
		<header class="invers">
			<div class="customregisterheader">
				<h1><?php echo $langs->trans("InstanceCreation") ?><br><small><?php echo($tmpproduct->label ? '('.$tmpproduct->label.')' : ''); ?></small></h1>
				<div class="paddingtop20">
					<div class="btn-sm">
					<span class="opacitymedium hideonsmartphone paddingright valignmiddle"><?php echo $langs->trans("AlreadyHaveAnAccount"); ?></span>
						<?php if (! empty($partner) || ! empty($partnerkey)) {
							print '<br class="hideonsmartphone">';
						} ?>
					<a href="/" class="btn blue btn-sm btnalreadyanaccount valignmiddle"><?php echo $langs->trans("LoginAction"); ?></a>
					</div>
						<?php if (! empty($homepage)) { ?>
					<div class="btn-sm home-page-url">
					<span class="opacitymedium"><a class="blue btn-sm" style="padding-left: 0;" href="<?php echo $homepage ?>"><?php echo $langs->trans("BackToHomePage"); ?></a></span>
					</div>
						<?php } ?>
				</div>
			</div>
		  <h1 class="defaultheader"><?php echo $langs->trans("InstanceCreation") ?><br><small><?php echo($tmpproduct->label ? '('.$tmpproduct->label.')' : ''); ?></small></h1>
		</header>
			<?php
		}
		print '<div class="signup2 centpercent customregistermain">';
		?>

			<?php
			if (! empty($tmpproduct->array_options['options_register_text'])) {
				$keytouse = $tmpproduct->array_options['options_register_text'];
				$registertexttoshow = '';
				if ($langs->trans($keytouse) != $keytouse) {
					$registertexttoshow = $langs->trans($keytouse);
				} else {	// We try english version
					if ($langsen->trans($keytouse) != $keytouse) {
						$registertexttoshow = $langsen->trans($keytouse);
					} else {
						// If no translation found, we do not show nothing
					}
				}

				print '<!-- show custom registration text of service using key '.dol_escape_htmltag($keytouse).' -->'."\n";
				print '<div class="customregisterinformation">'."\n";
				if ($registertexttoshow) {
					print '<div class="register_text">'."\n";
					print $registertexttoshow;
					print '</div>'."\n";
				} else {
					print '<!-- The translation key "'.$keytouse.'" has no translation found for '.$langs->defaultlang.' and '.$langsen->defaultlang.' so we do not show it. -->';
				}
				print '<div class="valignmiddle customcompanylogo">'."\n";
				print '<a href="'.$homepage.'"><img style="max-width:100%"src="'.$linklogo.'"></a><br>';
				print '</div>'."\n";
				print '</div>'."\n";
			}
			?>

		  <form action="register_instance.php" method="post" id="formregister">
			<div class="form-content">
			  <input type="hidden" name="token" value="<?php echo newToken(); ?>" />
			  <input type="hidden" name="forcesubdomain" value="<?php echo dol_escape_htmltag(GETPOST('forcesubdomain', 'alpha')); ?>" />
			  <input type="hidden" name="service" value="<?php echo dol_escape_htmltag($tmpproduct->id); ?>" />
			  <input type="hidden" name="productref" value="<?php echo($productref == 'none' ? 'none' : dol_escape_htmltag($tmpproduct->ref)); ?>" />
			  <input type="hidden" name="extcss" value="<?php echo dol_escape_htmltag($extcss); ?>" />
			  <input type="hidden" name="package" value="<?php echo dol_escape_htmltag($tmppackage->ref); ?>" />
			  <input type="hidden" name="partner" value="<?php echo dol_escape_htmltag($partner); ?>" />
			  <input type="hidden" name="partnerkey" value="<?php echo dol_escape_htmltag($partnerkey); ?>" />
			  <input type="hidden" name="socid" value="<?php echo dol_escape_htmltag($socid); ?>" />
			  <input type="hidden" name="reusesocid" value="<?php echo dol_escape_htmltag($reusesocid); ?>" />
			  <input type="hidden" name="reusecontractid" value="<?php echo dol_escape_htmltag($reusecontractid); ?>" />
			  <input type="hidden" name="fromsocid" value="<?php echo dol_escape_htmltag($fromsocid); ?>" />

			  <input type="hidden" name="origin" value="<?php echo dol_escape_htmltag($origin); ?>" /><!-- wil be saved into options_source -->
			  <!-- the utm_source_cookie=<?php echo dol_escape_htmltag(empty($_COOKIE["utm_source_cookie"]) ? '' : $_COOKIE["utm_source_cookie"]); ?> will be saved into options_source_utm -->

			  <input type="hidden" name="disablecustomeremail" value="<?php echo dol_escape_htmltag($disablecustomeremail); ?>" />
			  <!-- _SESSION['dol_loginsellyoursaas'] = <?php echo(empty($_SESSION['dol_loginsellyoursaas']) ? '' : $_SESSION['dol_loginsellyoursaas']); ?> -->

			  <section id="enterUserAccountDetails">


			<?php
			$disabled='';
			if (getDolGlobalInt('SELLYOURSAAS_DISABLE_NEW_INSTANCES') && !in_array(getUserRemoteIP(), explode(',', getDolGlobalString('SELLYOURSAAS_DISABLE_NEW_INSTANCES_EXCEPT_IP')))) {
				$disabled=' disabled';
				print '<!-- RegistrationSuspendedForTheMomentPleaseTryLater -->'."\n";
				print '<div class="alert alert-warning">';
				if (getDolGlobalString('SELLYOURSAAS_DISABLE_NEW_INSTANCES_MESSAGE')) {
					print getDolGlobalString('SELLYOURSAAS_DISABLE_NEW_INSTANCES_MESSAGE');
				} else {
					print $langs->trans("RegistrationSuspendedForTheMomentPleaseTryLater");
				}
				print '</div>';
			}

			if (isset($_SESSION['dol_events']['errors'])) {
				print '<div class="alert alert-error">';
				if (is_array($_SESSION['dol_events']['errors'])) {
					foreach ($_SESSION['dol_events']['errors'] as $key => $val) {
						print '<ul><li>'.$val.'</li></ul>';
					}
				} else {
					print '<ul><li>'.$_SESSION['dol_events']['errors'].'</li></ul>';
				}
				print '</div><br>'."\n";
			}
			?>

			<?php
			if (empty($mythirdparty->id)) {
				?>
			<div class="control-group  required">
				<label class="control-label" for="username" trans="1"><span class="fas fa-at opacityhigh"></span> <?php echo $langs->trans("Email") ?></label>
				<div class="controls">
					<input type="text"<?php echo $disabled; ?> name="username" maxlength="255" autofocus value="<?php echo GETPOST('username', 'alpha'); ?>" required="" id="username" />

				</div>
			</div>

			<div class="group">
				<div class="horizontal-fld">
					<div class="control-group  required">
						<label class="control-label" for="orgName"
							   trans="1"><span class="fa fa-building opacityhigh"></span> <?php echo $langs->trans("NameOfCompany") ?></label>
						<div class="controls">
							<input type="text"<?php echo $disabled; ?> name="orgName" maxlength="250"
								   value="<?php echo GETPOST('orgName', 'alpha'); ?>" required="" id="orgName"/>
						</div>
					</div>
				</div>
				<?php if (! getDolGlobalInt('SELLYOURSAAS_REGISTER_HIDE_PHONE')) { ?>
				<div class="horizontal-fld">
					<div class='control-group'>
						<label class='control-label' for='phone' trans='1'><span class="fa fa-phone opacityhigh"></span> <?php echo $langs->trans('Phone') ?></label>
						<div class="controls">
							<input type="text"<?php echo $disabled; ?> name="phone" maxlength="250"
								   value="<?php echo GETPOST('phone', 'alpha'); ?>" id="phone"/>
						</div>
					</div>
				</div>
				<?php } ?>
				</div>
				<?php
			}
			if (empty($reusecontractid)) {
				$langs->load("sellyoursaas@sellyoursaas");

				$tmppassinform = '';
				$tmppassinform2 = '';
				if (!empty($_SESSION['tmppassinform'])) {
					$tmppassinform = dolDecrypt($_SESSION['tmppassinform']);
					unset($_SESSION['tmppassinform']);
				}
				if (!empty($_SESSION['tmppassinform2'])) {
					$tmppassinform2 = dolDecrypt($_SESSION['tmppassinform2']);
					unset($_SESSION['tmppassinform2']);
				}
				?>
			<div class="group">
				<div class="horizontal-fld">

				<div class="control-group  required">
					<label class="control-label" for="password" trans="1"><span class="fa fa-lock opacityhigh"></span> <?php echo $langs->trans("Password") ?></label>
					<div class="controls">

						<input<?php echo $disabled; ?> title="<?php echo dol_escape_htmltag($langs->trans("RuleForPassword", 8)) ?>" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" name="password" type="password" minlength="8" maxlength="128" required autocomplete="new-password" spellcheck="false" autocapitalize="off" value="<?php echo $tmppassinform; ?>" />

					</div>
				</div>

				</div>
				<div class="horizontal-fld">
				  <div class="control-group required">
					<label class="control-label" for="password2" trans="1"><span class="fa fa-lock opacityhigh"></span> <?php echo $langs->trans("PasswordRetype") ?></label>
					<div class="controls">
					  <input<?php echo $disabled; ?> title="<?php echo dol_escape_htmltag($langs->trans("RuleForPassword", 8)) ?>" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" name="password2" type="password" minlength="8" maxlength="128" required autocomplete="new-password" spellcheck="false" autocapitalize="off" value="<?php echo $tmppassinform2; ?>" />
					</div>
				  </div>
				</div>
			</div>
				<?php
			}


			if (empty($mythirdparty->id)) {
				?>

			<div class="control-group  ">
				<label class="control-label" for="country"><span class="fa fa-globe opacityhigh"></span> <?php echo $langs->trans("Country") ?></label>
				<div class="controls">
				<?php
				$countryuser=strtoupper(dolGetCountryCodeFromIp(getUserRemoteIP()));
				print '<!-- Autodetected IP/Country: '.dol_escape_htmltag(getUserRemoteIP()).'/'.$countryuser.' -->'."\n";
				if (GETPOST('country')) {	// Can force a country instead of default autodetected value
					$countryuser = GETPOST('country');
				}
				if (empty($countryuser)) {
					$countryuser='US';
				}
				$countryuser = strtoupper($countryuser);
				print $form->select_country($countryuser, 'country', 'optionsValue="name"'.$disabled, 0, ($conf->dol_optimize_smallscreen ? 'minwidth200' : 'minwidth300'), 'code2', 1, 1); ?>
				</div>
			</div>

				<?php
			}
			?>

		  </section>

		  <?php
			if ($productref != 'none') {
				if (empty($reusecontractid)) {
					print '<br>';
				} else {
					print '<hr/>';
				} ?>

			  <!-- Selection of domain to create instance -->
			  <section id="selectDomain">
				<div class="fld select-domain required">
				  <label trans="1"><?php echo $langs->trans("ChooseANameForYourApplication") ?></label>
				  <div class="linked-flds">
					  <span class="nowraponall">
					<span class="opacitymedium">https://</span>
					<input<?php echo $disabled; ?> class="sldAndSubdomain" type="text" name="sldAndSubdomain" id="sldAndSubdomain" value="<?php echo $sldAndSubdomain; ?>" maxlength="29" required="" />
					</span>
					<select<?php echo $disabled; ?> name="tldid" id="tldid" >
						<?php
						// SERVER_NAME here is myaccount.mydomain.com (we can exploit only the part mydomain.com)
						$domainname = getDomainFromURL($_SERVER["SERVER_NAME"], 1);
						$domainstosuggest = array();
						$domainstosuggestcountryfilter = array();
						if (!getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION')) {
							$listofdomain = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);   // This is list of all sub domains to show into combo list
						} else {
							$staticdeploymentserver = new Deploymentserver($db);
							$listofdomain = $staticdeploymentserver->fetchAllDomains();
						}
						foreach ($listofdomain as $val) {
							$newval = $val;
							$reg = array();
							if (preg_match('/:(.+)$/', $newval, $reg)) {      // If this domain must be shown only if domain match
								$newval = preg_replace('/:.*$/', '', $newval);	// the part before the : that we use to compare the forcesubdomain parameter.

								$domainqualified = false;
								$tmpdomains = explode('+', $reg[1]);
								foreach ($tmpdomains as $tmpdomain) {
									if ($tmpdomain == $domainname || $newval == GETPOST('forcesubdomain', 'alpha')) {
										$domainqualified = true;
										break;
									}
								}
								if (! $domainqualified) {
									print '<!-- '.$newval.' disabled. Allowed only if main domain of registration page is '.$reg[1].' -->';
									continue;
								}
							}
							// $newval is subdomain (with.mysaasdomainname.com for example)

							// Restriction defined on package
							if (! empty($tmppackage->restrict_domains)) {   // There is a restriction on some domains for this package
								$restrictfound = false;
								$tmparray=explode(',', $tmppackage->restrict_domains);
								foreach ($tmparray as $tmprestrictdomain) {
									$newdomain = getDomainFromURL($newval, 1);
									if ($newdomain == $tmprestrictdomain) {
										$restrictfound=true;
										break;
									}
								}
								if (! $restrictfound && $newval != GETPOST('forcesubdomain', 'alpha')) {
									print '<!-- '.$newval.' disabled. There is a restriction on package to use only '.$tmppackage->restrict_domains.' -->';
									continue;   // The subdomain in SELLYOURSAAS_SUB_DOMAIN_NAMES has not a domain inside restrictlist of package, so we discard it.
								}
							}
							if (getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION')) {
								$deploymentserver = new Deploymentserver($db);
								$deploymentserver->fetch(0, $newval);

								if (!empty($deploymentserver->servercountries)) {
									$servercountries = explode(',', $deploymentserver->servercountries);
									$ipuser = getUserRemoteIP();
									$countryuser = dolGetCountryCodeFromIp($ipuser);
									if (GETPOST('country')) {	// Can force a country instead of default autodetected value
										$countryuser = GETPOST('country');
									}
									if (empty($countryuser)) {
										$countryuser='US';
									}
									$countryuser = strtolower($countryuser);

									if (in_array($countryuser, $servercountries)) {
										if (! preg_match('/^\./', $newval)) {
											$newval='.'.$newval;
										}
										$domainstosuggestcountryfilter[] = $newval; // Servers with user country
									} else {
										print '<!-- '.$newval.' disabled. Server country range '.$deploymentserver->servercountries.' does not contain '.$countryuser.' -->';
										continue;
									}
								} else {
									if (! preg_match('/^\./', $newval)) {
										$newval='.'.$newval;
									}
									$domainstosuggest[] = $newval;
								}
							} else {
								if (! preg_match('/^\./', $newval)) {
									$newval='.'.$newval;
								}
								$domainstosuggest[] = $newval;
							}
						}
						if (!empty($domainstosuggestcountryfilter)) {
							foreach ($domainstosuggest as $key => $value) {
								print '<!-- '.$value.' disabled. Matching server found with user location -->';
							}
							$domainstosuggest = $domainstosuggestcountryfilter;
						}

						// Defined a preselected domain
						$randomselect = '';
						$randomindex = 0;
						if (empty($tldid) && ! GETPOSTISSET('tldid') && ! GETPOSTISSET('forcesubdomain') && count($domainstosuggest) >= 1) {
							$maxforrandom = (count($domainstosuggest) - 1);
							$randomindex = mt_rand(0, $maxforrandom);
							$randomselect = $domainstosuggest[$randomindex];
						}
						// Force selection with no way to change value if SELLYOURSAAS_FORCE_RANDOM_SELECTION is set
						if (!empty($conf->global->SELLYOURSAAS_FORCE_RANDOM_SELECTION) && !empty($randomselect)) {
							$domainstosuggest = array();
							$domainstosuggest[] = $randomselect;
						}
						foreach ($domainstosuggest as $val) {
							print '<option value="'.$val.'"'.(($tldid == $val || ($val == '.'.GETPOST('forcesubdomain', 'alpha')) || $val == $randomselect) ? ' selected="selected"' : '').'>'.$val.'</option>';
						} ?>
					</select>
						<?php
						// Show warning if forcesubdomain set and not found
						if (GETPOST('forcesubdomain', 'alpha')) {
							$forcesubdomainfound = false;
							foreach ($listofdomain as $val) {
								//$newval = preg_replace('/^.*:/', '', $val);
								$newval = preg_replace('/:.*$/', '', $val);
								if ($newval == GETPOST('forcesubdomain', 'alpha')) {
									$forcesubdomainfound = true;
								}
							}
							if (! $forcesubdomainfound) {
								print '<br>Error: Value for forcesubdomain = '.GETPOST('forcesubdomain', 'alpha').' is not in list of available subdomains.';
							} else {
								print '<input type="hidden" name="forcesubdomain" value="'.dol_escape_htmltag(GETPOST('forcesubdomain', 'alpha')).'">';
							}
						} ?>
					<br class="unfloat" />
				  </div>
				</div>
			  </section>
				<?php
			}
			?>
			<br>


			<?php if (getDolGlobalInt('SELLYOURSAAS_ENABLE_OPTINMESSAGES')) { ?>
			<!-- checkbox for optin messages -->
			<section id="optinmessagesid">
				<input type="checkbox" id="optinmessages" name="optinmessages" class="valignmiddle inline" style="margin-top: 0" value="1">
				<label for="optinmessages" class="valignmiddle small inline opacitymedium"><?php echo $langs->trans("OptinForCommercialMessagesOnMyAccount", $sellyoursaasname); ?></label>
			</section>
			<?php } ?>

			<?php if (getDolGlobalInt('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA')) { ?>
			<!-- Checkbox for non profit orga -->
			<section id="checkboxnonprofitorgaid">
			<div class="group required">
				<input type="checkbox" id="checkboxnonprofitorga" name="checkboxnonprofitorga" class="valignmiddle inline" style="margin-top: 0" value="nonprofit" required=""<?php echo(GETPOST('checkboxnonprofitorga') ? ' checked="checked"' : ''); ?>>
				<label for="checkboxnonprofitorga" class="valignmiddle small inline"><?php
				if (getDolGlobalInt('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA') == 2) {
					echo $langs->trans("ConfirmNonProfitOrgaCaritative", $sellyoursaasname).'. ';
				} elseif (getDolGlobalInt('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA') == 3) {
					echo $langs->trans("ConfirmNonProfitOrgaSmall", $sellyoursaasname).'. ';
				} else {
					echo $langs->trans("ConfirmNonProfitOrga", $sellyoursaasname).'. ';
				}
				// Show the link for commecial service if there is a commercial alternative service
				if (getDolGlobalString('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_LINK_COMMERCIAL')) {
					echo $langs->trans("ConfirmNonProfitOrgaBis", getDolGlobalString('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_LINK_COMMERCIAL'), getDolGlobalString('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA_LINK_COMMERCIAL'));
				}
				?></label>
			</div>
			</section>
			<?php } ?>

			<?php if (getDolGlobalString('SELLYOURSAAS_TERMSANDCONDITIONS')) { ?>
			<!-- mandatory checkbox for terms and conditions -->
			<section id="checkboxtermsandconditions">
				<div class="group required">
					<input type="checkbox" id="checkboxtermsandconditions" name="checkboxtermsandconditions" class="valignmiddle inline" style="margin-top: 0" value="1" required="1"<?php echo(GETPOST('checkboxtermsandconditions') ? ' checked="checked"' : ''); ?>>
					<label for="checkboxtermsandconditions" class="valignmiddle small inline"><?php
						$urlfortermofuse = 'https://www.'.getDolGlobalString('SELLYOURSAAS_MAIN_DOMAIN_NAME').'/'.getDolGlobalString('SELLYOURSAAS_TERMSANDCONDITIONS');
						echo $langs->trans("WhenRegisteringYouAccept", $urlfortermofuse);
					?></label>
				</div>
			</section>
			<?php } ?>

			<br>

	   </div>

		  <section id="formActions">
			<?php
			// TODO Remove this, we should be able to use SELLYOURSAAS_TERMSANDCONDITIONS instead
			$urlfortermofuse = '';
			if ($conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME == 'dolicloud.com') {
				$urlfortermofuse = 'https://www.'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/en-terms-and-conditions.php';
				if (preg_match('/^fr/i', $langs->defaultlang)) {
					$urlfortermofuse = 'https://www.'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/fr-conditions-utilisations.php';
				}
				if (preg_match('/^es/i', $langs->defaultlang)) {
					$urlfortermofuse = 'https://www.'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/es-terminos-y-condiciones.php';
				}
			}
			if ($urlfortermofuse) {
				?>
			  <p class="termandcondition small center" style="color:#444; margin:10px 3px;" trans="1"><?php echo $langs->trans("WhenRegisteringYouAccept", $urlfortermofuse) ?></p>
				<?php
			}
			?>
			  <div class="form-actions center"">
				<?php
				if ($productref != 'none') {
					?>
					<input type="submit"<?php echo $disabled; ?> name="newinstance" style="margin: 10px;" value="<?php echo $langs->trans("SignMeUp") ?>" class="btn btn-primary" id="newinstance" />
					<?php
				} else {
					?>
					<input type="submit"<?php echo $disabled; ?> name="newinstance" style="margin: 10px;" value="<?php echo $langs->trans("CreateMyAccount") ?>" class="btn btn-primary" id="newinstance" />
					<?php
				}
				?>
			  </div>
			  <br>
		  </section>


		<!-- Add fields to send local user information -->
		<input type="hidden" name="tz" id="tz" value="" />
		<input type="hidden" name="tz_string" id="tz_string" value="" />
		<input type="hidden" name="dst_observed" id="dst_observed" value="" />
		<input type="hidden" name="dst_first" id="dst_first" value="" />
		<input type="hidden" name="dst_second" id="dst_second" value="" />
		<input type="hidden" name="screenwidth" id="screenwidth" value="" />
		<input type="hidden" name="screenheight" id="screenheight" value="" />


	 </form> <!-- end form-content -->

	</div>

	<?php
	// Execute hook getRegisterPageFooter
	$parameters = array('domainname' => $domainname, 'defaultproduct' => $defaultproduct, 'tmpproduct' => $tmpproduct);
	$reshook = $hookmanager->executeHooks('getRegisterPageFooter', $parameters); // Note that $action and $object may have been modified by some hooks.
	print $hookmanager->resPrint;
	?>

  </div>
</div>


	<?php
}
if ($reshook >= 0) {
	print $hookGetRegisterPageFormResPrint;
}
?>


<script type="text/javascript" language="javascript">
	function applyDomainConstraints( domain )
	{
		domain = domain.replace(/ /g, "");
		domain = domain.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
		// allow  "a-z", "A-Z", "0-9" and "-"
		domain = domain.replace(/[^\w\-]/g, "");
		domain = domain.replace(/\_/g, "");
		domain = domain.replace(/^[^a-z0-9\-]+/ig, "");		// We accept the - at start during input to avoid to have it to be removed automatically during typing
		domain = domain.replace(/[^a-z0-9\-]+$/ig, "");		// We accept the - at end during input to avoid to have it to be removed automatically during typing
		domain = domain.toLowerCase();
		if (!isNaN(domain)) {
		  return ""
		}
		while ( domain.length > 1 && !isNaN( domain.charAt(0))  ){
		  domain=domain.substr(1)
		}
		if (domain.length > 29) {
			domain = domain.substring(0, 28);
		}
		return domain
	}

	jQuery(document).ready(function() {

		/* Autofill the domain when filling the company */
		jQuery("#formregister").on("change keyup", "#orgName", function() {
			console.log("Update sldAndSubdomain in register.php");
			$("#sldAndSubdomain").val( applyDomainConstraints( $(this).val() ) );
		});

		/* Apply constraints if sldAndSubdomain field is change */
		jQuery("#formregister").on("change keyup", "#sldAndSubdomain", function() {
			console.log("Update sldAndSubdomain field in register.php");
			$(this).val( applyDomainConstraints( $(this).val() ) );
		});

		/* Sow hourglass */
		$('#formregister').submit(function() {
				console.log("We clicked on submit on register.php")

				jQuery(document.body).css({ 'cursor': 'wait' });
				jQuery("div#waitMask").show();
				jQuery("#waitMask").css("opacity"); // must read it first
				jQuery("#waitMask").css("opacity", "0.6");

				return true;	/* Use return false to show the hourglass without submitting the page (for debug) */
		});
	});
</script>


<?php

llxFooter('', 'public', 1);		// We disabled output of messages. Already done into page
$db->close();
