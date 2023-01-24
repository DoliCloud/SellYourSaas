<?php
/* Copyright (C) 2007-2020	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2008-2012	Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2008-2011	Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2014       Teddy Andreotti    	<125155@supinfo.com>
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
 *
 * You can add &admin=1 as parameter to get more features
 */

//if (! defined('NOREQUIREUSER'))  define('NOREQUIREUSER','1');
//if (! defined('NOREQUIREDB'))    define('NOREQUIREDB','1');
//if (! defined('NOREQUIRESOC'))   define('NOREQUIRESOC','1');
//if (! defined('NOREQUIRETRAN'))  define('NOREQUIRETRAN','1');
//if (! defined('NOCSRFCHECK'))    define('NOCSRFCHECK','1');			// Do not check anti CSRF attack test (we can go on this page after a stripe payment recording)
if (! defined('NOIPCHECK'))      define('NOIPCHECK', '1');				// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined('NOSTYLECHECK'))   define('NOSTYLECHECK','1');			// Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL','1');		// Do not check anti POST attack test
if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU', '1');			// If there is no need to load and show top and left menu
//if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML','1');			// If we don't need to load the html.form.class.php
//if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');
//if (! defined("NOLOGIN"))        define("NOLOGIN",'1');				    	// If this page is public (can be called outside logged session)
if (! defined("MAIN_LANG_DEFAULT") && empty($_GET['lang'])) define('MAIN_LANG_DEFAULT', 'auto');
if (! defined("MAIN_AUTHENTICATION_MODE")) define('MAIN_AUTHENTICATION_MODE', 'sellyoursaas');
if (! defined("MAIN_AUTHENTICATION_POST_METHOD")) define('MAIN_AUTHENTICATION_POST_METHOD', '0');
if (! defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');

// Load Dolibarr environment
include './mainmyaccount.inc.php';

// SERVER_NAME here is myaccount.mydomain.com (we can exploit only the part mydomain.com)
$tmpdomain = preg_replace('/^https?:\/\//i', '', $_SERVER["SERVER_NAME"]); // Remove http(s)://
$tmpdomain = preg_replace('/\/.*$/i', '', $tmpdomain); // Remove part after domain
$tmpdomain = preg_replace('/^.*\.([^\.]+)\.([^\.]+)$/', '\1.\2', $tmpdomain); // Remove part 'www.abc.' before 'mydomain.com'

// Code to set cookie for first utm_source
// Must be before the main that make a redirect on login if not logged
if (!empty($_GET["utm_source"]) || !empty($_GET["origin"]) || !empty($_GET["partner"])) {
	$cookiename = "utm_source_cookie";
	$cookievalue = empty($_GET["utm_source"]) ? (empty($_GET["origin"]) ? 'partner'.$_GET["partner"] : $_GET["origin"]) : $_GET["utm_source"];
	if (empty($_COOKIE[$cookiename]) && $tmpdomain) {
		$domain = $tmpdomain;
		setcookie($cookiename, empty($cookievalue) ? '' : $cookievalue, empty($cookievalue) ? 0 : (time() + (86400 * 60)), '/', $domain, false, true); // keep cookie 60 days and add tag httponly
	}
}

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
if (! $res && ! empty($_SERVER["DOCUMENT_ROOT"])) $res=@include $_SERVER["DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
//require_once DOL_DOCUMENT_ROOT.'/website/class/website.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societeaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companypaymentmode.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
require_once DOL_DOCUMENT_ROOT.'/stripe/class/stripe.class.php';
dol_include_once('/sellyoursaas/class/packages.class.php');
dol_include_once('/sellyoursaas/class/deploymentserver.class.php');
dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');
dol_include_once('/sellyoursaas/class/sellyoursaasutils.class.php');

$conf->global->SYSLOG_FILE_ONEPERSESSION=2;

$welcomecid = GETPOST('welcomecid', 'int');
$mode = GETPOST('mode', 'aZ09');

$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alphanohtml');
$backtourl = GETPOST('backtourl', 'alpha');
if (empty($mode) && empty($welcomecid)) $mode='dashboard';

//$langs=new Translate('', $conf);
//$langs->setDefaultLang(GETPOST('lang', 'aZ09') ? GETPOST('lang', 'aZ09') : 'auto');
$langs->loadLangs(array("main","companies","bills","sellyoursaas@sellyoursaas","other","errors",'mails','paypal','paybox','stripe','withdrawals','other','admin'));

if ($langs->defaultlang == 'en_US') {
	$langsen = $langs;
} else {
	$langsen=new Translate('', $conf);
	$langsen->setDefaultLang('en_US');
	$langsen->loadLangs(array("main","companies","bills","sellyoursaas@sellyoursaas","other","errors",'mails','paypal','paybox','stripe','withdrawals','other','admin'));
}

$langscompany=new Translate('', $conf);
$langscompany->setDefaultLang($mysoc->default_lang == 'auto' ? getLanguageCodeFromCountryCode($mysoc->country_code) : $mysoc->default_lang);
$langscompany->loadLangs(array("main","companies","bills","sellyoursaas@sellyoursaas","other","errors",'mails','paypal','paybox','stripe','withdrawals','other','admin'));


$mythirdpartyaccount = new Societe($db);

$service=GETPOST('service', 'int');
$firstrecord=GETPOST('firstrecord', 'int');
$lastrecord=GETPOST('lastrecord', 'int');
$search_instance_name=GETPOST('search_instance_name', 'alphanohtml');
$search_customer_name=GETPOST('search_customer_name', 'alphanohtml');
$reasonundeploy=GETPOST('reasonundeploy', 'alpha');
$commentundeploy=GETPOST('commentundeploy', 'alpha');

// Var used to create a new BAN for SEPA payments
$bankname = GETPOST('bankname', 'alphanohtml');
$iban = GETPOST('iban', 'alphanohtml');
$bic = GETPOST('bic', 'alphanohtml');


$MAXINSTANCEVIGNETTE = 4;

// Load variable for pagination
$limit = GETPOST('limit', 'int')?GETPOST('limit', 'int'):($mode == 'instance' ? $MAXINSTANCEVIGNETTE : 20);
$sortfield = GETPOST('sortfield', 'alphanohtml');
$sortorder = GETPOST('sortorder', 'alphanohtml');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1) { $page = 0; }     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
//if (! $sortfield) $sortfield="p.date_fin";
//if (! $sortorder) $sortorder="DESC";

$propertykey = GETPOST('propertykey', 'int');
$firstrecord = GETPOSTISSET('firstrecord')?GETPOST('firstrecord', 'int'):($page * $limit) + 1;
$lastrecord = GETPOSTISSET('lastrecord')?GETPOST('lastrecord', 'int'):(($page+1)*$limit);
if ($firstrecord < 1) $firstrecord=1;
if (GETPOSTISSET('reset')) { $search_instance_name = ''; $search_customer_name = ''; }
$fromsocid=GETPOST('fromsocid', 'int');

// Id of connected thirdparty
$socid = GETPOST('socid', 'int')?GETPOST('socid', 'int'):$_SESSION['dol_loginsellyoursaas'];
$idforfetch = $fromsocid > 0 ? $fromsocid : $socid;
if ($idforfetch > 0) {
	$result = $mythirdpartyaccount->fetch($idforfetch);					// fromid set if creation from reseller dashboard else we use socid
	if ($result <= 0) {
		dol_print_error($db, "Failed to load thirdparty for id=".($idforfetch));
		exit;
	}
}

if ($idforfetch <= 0 || empty($mythirdpartyaccount->status)) {
	$sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
	if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
		$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
		if (! empty($conf->global->$newnamekey)) $sellyoursaasemail = $conf->global->$newnamekey;
	}

	$_SESSION=array();
	$_SESSION['dol_loginmesg']=$langs->trans("SorryAccountDeleted", $sellyoursaasemail);
	//header("Location: index.php?username=".urlencode(GETPOST('username','alpha')));
	header("Location: index.php?usernamebis=".urlencode(GETPOST('username', 'alpha')));
	exit;
}

$langcode = 'en';
if ($langs->getDefaultLang(1) == 'es') $langcode = 'es';
if ($langs->getDefaultLang(1) == 'fr') $langcode = 'fr';

$urlfaq = '';
if (empty($conf->global->SELLYOURSAAS_MAIN_FAQ_URL)) {
	if (preg_match('/dolicloud\.com/', $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME)) {
		$urlfaq='https://www.'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/'.$langcode.'/faq';
	} else {
		$urlfaq='https://www.'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/faq-'.$langcode.'.php';
		if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
			&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
			$urlfaq = 'https://www.'.$mythirdpartyaccount->array_options['options_domain_registration_page'].'/faq-'.$langcode.'.php';
		}
	}
} else {
	$urlfaq = $conf->global->SELLYOURSAAS_MAIN_FAQ_URL;
}


$urlstatus = getDolGlobalString('SELLYOURSAAS_STATUS_URL');
if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
	&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
	$newnamekey = 'SELLYOURSAAS_STATUS_URL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
	if (!empty($conf->global->$newnamekey)) {
		$urlstatus = $conf->global->$newnamekey;
	}
}

$now =dol_now();
$tmp=dol_getdate($now);
$nowmonth = $tmp['mon'];
$nowyear = $tmp['year'];

require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
$documentstatic=new Contrat($db);
$documentstaticline=new ContratLigne($db);

$listofcontractid = array();
$listofcontractidopen = array();
$sql = 'SELECT c.rowid as rowid, ce.deployment_status';
$sql .= ' FROM '.MAIN_DB_PREFIX.'contrat as c';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'contrat_extrafields as ce ON ce.fk_object = c.rowid,';
//$sql .= ' '.MAIN_DB_PREFIX.'contratdet as d,';
$sql .= ' '.MAIN_DB_PREFIX.'societe as s';
$sql .= ' WHERE c.fk_soc = s.rowid AND s.rowid = '.((int) $mythirdpartyaccount->id);
//$sql .= ' AND d.fk_contrat = c.rowid';
$sql .= ' AND c.entity = '.((int) $conf->entity);
$sql .= " AND ce.deployment_status IN ('processing', 'done', 'undeployed')";
$resql=$db->query($sql);
if ($resql) {
	$num_rows = $db->num_rows($resql);
	$i = 0;
	while ($i < $num_rows) {
		$obj = $db->fetch_object($resql);
		if ($obj) {
			$contract=new Contrat($db);
			$contract->fetch($obj->rowid);					// This load also lines
			$listofcontractid[$obj->rowid] = $contract;
			if (in_array($obj->deployment_status, array('processing', 'done'))) {
				$listofcontractidopen[$obj->rowid] = $contract;
			}
		}
		$i++;
	}
} else {
	dol_print_error($db);
}

$mythirdpartyaccount->isareseller = 0;
if ($conf->global->SELLYOURSAAS_DEFAULT_RESELLER_CATEG > 0) {
	$categorie=new Categorie($db);
	$categorie->fetch($conf->global->SELLYOURSAAS_DEFAULT_RESELLER_CATEG);
	if ($categorie->containsObject('supplier', $mythirdpartyaccount->id) > 0) {
		$mythirdpartyaccount->isareseller = 1;
	}
}

$nbtotalofrecords = 0;
$listofcontractidresellerall = array();
$listofcontractidreseller = array();
$listofcustomeridreseller = array();

// Load list of child instances for resellers
// TODO: This may be very slow on large resellers
if ($mythirdpartyaccount->isareseller && in_array($mode, array('dashboard', 'mycustomerbilling', 'mycustomerinstances'))) {
	dol_syslog("Thirdparty is a reseller so we load the list of all child instances/contracts");

	// Full list of ID (no loading of object)
	$sql = 'SELECT DISTINCT c.rowid, c.fk_soc';
	$sql.= ' FROM '.MAIN_DB_PREFIX.'contrat as c';
	$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'contrat_extrafields as ce ON ce.fk_object = c.rowid,';
	$sql.= ' '.MAIN_DB_PREFIX.'contratdet as d, '.MAIN_DB_PREFIX.'societe as s';
	$sql.= " WHERE c.fk_soc = s.rowid AND s.parent = ".$mythirdpartyaccount->id;
	$sql.= " AND d.fk_contrat = c.rowid";
	$sql.= " AND c.entity = ".$conf->entity;
	$sql.= " AND ce.deployment_status IN ('processing', 'done', 'undeployed')";
	if ($search_instance_name) $sql.=natural_search(array('c.ref_customer'), $search_instance_name);
	if ($search_customer_name) $sql.=natural_search(array('s.nom','s.email'), $search_customer_name);
	$resql=$db->query($sql);
	$num_rows = $db->num_rows($resql);
	$i=0;
	while ($i < $num_rows) {
		$nbtotalofrecords++;
		$listofcontractidresellerall[$obj->rowid]=$obj->fk_soc;
		$listofcustomeridreseller[$obj->fk_soc]=1;
		$i++;
	}

	// List limited
	$sql = 'SELECT c.rowid as rowid, c.fk_soc';
	$sql.= ' FROM '.MAIN_DB_PREFIX.'contrat as c';
	$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'contrat_extrafields as ce ON ce.fk_object = c.rowid,';
	$sql.= ' '.MAIN_DB_PREFIX.'contratdet as d, '.MAIN_DB_PREFIX.'societe as s';
	$sql.= " WHERE c.fk_soc = s.rowid AND s.parent = ".$mythirdpartyaccount->id;
	$sql.= " AND d.fk_contrat = c.rowid";
	$sql.= " AND c.entity = ".$conf->entity;
	$sql.= " AND ce.deployment_status IN ('processing', 'done', 'undeployed')";
	if ($search_instance_name) $sql.=" AND c.ref_customer REGEXP '^[^\.]*".$db->escape($search_instance_name)."'";
	if ($search_customer_name) $sql.=natural_search(array('s.nom','s.email'), $search_customer_name);

	if (empty($lastrecord) || $lastrecord > $nbtotalofrecords) {
		$lastrecord = $nbtotalofrecords;
	}
	if ($lastrecord > 0) {
		// We disable this filter because we need all later to calculate the number of suspended instance from lines
		//$sql.= " LIMIT ".($firstrecord?$firstrecord:1).", ".(($lastrecord >= $firstrecord) ? ($lastrecord - $firstrecord + 1) : 5);
	}

	$resql=$db->query($sql);
	if ($resql) {
		$num_rows = $db->num_rows($resql);
		$i = 0;
		while ($i < $num_rows) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				if (empty($listofcontractidreseller[$obj->rowid])) {
					$contract=new Contrat($db);
					$contract->fetch($obj->rowid);					// This load also lines
					$listofcontractidreseller[$obj->rowid] = $contract;
				}
			}
			$i++;
		}
	} else dol_print_error($db);
}
//var_dump(array_keys($listofcontractidreseller));

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
$servicestatuspaypal = 0;
if (! empty($conf->paypal->enabled)) {
	$servicestatuspaypal = 0;
	if (! empty($conf->global->PAYPAL_LIVE) && ! GETPOST('forcesandbox', 'alpha') && empty($conf->global->SELLYOURSAAS_FORCE_PAYPAL_TEST)) {
		$servicestatuspaypal = 1;
	}
}

$initialaction = $action;



/*
 * Action
 */

if (empty($welcomecid)) {
	dol_syslog('----- index.php mode='.$mode.' action='.$action.' cancel='.$cancel, LOG_DEBUG, 1);
}

if ($cancel) {
	if ($action == 'sendbecomereseller') {
		$backtourl = 'index.php?mode=dashboard';
	}

	$action = '';
	if ($backtourl) {
		header("Location: ".$backtourl);
		exit;
	}
}

if (preg_match('/logout/', $mode)) {
	$mode = preg_replace('/logout_?/', '', $mode);

	session_destroy();
	$param='';
	if (GETPOSTISSET('username'))   $param.='&username='.urlencode(GETPOST('username', 'alpha'));
	if (GETPOSTISSET('password'))   $param.='&password='.urlencode(GETPOST('password', 'alpha'));
	if (GETPOSTISSET('login_hash')) $param.='&login_hash='.urlencode(GETPOST('login_hash', 'alpha'));
	if (GETPOSTISSET('action'))     $param.='&action='.urlencode(GETPOST('action', 'alpha'));
	if (GETPOSTISSET('actionlogin')) $param.='&actionlogin='.urlencode(GETPOST('actionlogin', 'alpha'));
	if ($mode) $param.='&mode='.urlencode($mode);
	header("Location: /index.php".($param?'?'.$param:''));
	exit;
}
if (getDolGlobalInt("SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE") && $action == 'updateforcepriceinstance') {
	$errors = 0;
	$result = 1;
	$priceproductid = GETPOST("priceproductid", "alpha");
	$price_instance = price2num(GETPOST("field_price_".$mythirdpartyaccount->id."_".$priceproductid));
	$price_instance_per_user = price2num(GETPOST("field_priceuser_".$mythirdpartyaccount->id."_".$priceproductid));

	$min_instance_price_reduction = getDolGlobalInt("SELLYOURSAAS_RESELLER_MIN_INSTANCE_PRICE_REDUCTION", 0) / 100;

	if (!$errors) {
		$product = new Product($db);
		$tmpprodchild = new Product($db);

		$res = $product->fetch($priceproductid);
		if ($res <= 0) {
			setEventMessages($langs->trans("ProductNotFound"), null, 'errors');
			$errors++;
		}

		if (!$errors) {
			$priceinstance['user'] = null;
			$priceinstance['options']["total"] = 0;
			$priceinstance['fix'] = (float) price2num($product->price);
			$product->sousprods = array();
			$product->get_sousproduits_arbo();
			$tmparraysousproduct = $product->get_arbo_each_prod();
			if (count($tmparraysousproduct) > 0) {
				foreach ($tmparraysousproduct as $key => $value) {
					$tmpprodchild->fetch($value['id']);
					$prodchildprice = (float) price2num($tmpprodchild->price);
					if (preg_match('/user/i', $tmpprodchild->ref) || preg_match('/user/i', $tmpprodchild->array_options['options_resource_label'])) {
						if (!empty($priceinstance['user'])) {
							$priceinstance['user'].= $prodchildprice;
						} else {
							$priceinstance['user'] = $prodchildprice;
						}
					} elseif ($tmpprodchild->array_options['options_app_or_option'] == 'system') {
						// Don't add system services to global price, these are options with calculated quantitie
					} else {
						if ($tmpprodchild->array_options['options_app_or_option'] == 'option') {
							$priceinstance['options']["lines"][$tmpprodchild->id]["posted"] = (float) price2num(GETPOST("field_price_option_".$tmpprodchild->id."_".$mythirdpartyaccount->id."_".$priceproductid));
							$priceinstance['options']["lines"][$tmpprodchild->id]["base"] = $prodchildprice;
							$priceinstance['options']["total"] += $prodchildprice;
						}
						$priceinstance['fix'] += $prodchildprice;
					}
				}
			}
			if ($price_instance === "") {
				setEventMessages($langs->trans("FixPriceMustBeNumeric"), null, 'errors');
				$errors++;
			} else {
				$price_instance = (float) $price_instance;
			}

			if (isset($priceinstance['user'])) {
				if ($price_instance_per_user === "") {
					setEventMessages($langs->trans("PricePerUsersMustBeNumeric"), null, 'errors');
					$errors++;
				} else {
					$price_instance_per_user = (float) $price_instance_per_user;
				}
			}

			if (!$errors) {
				$minfixprice = $priceinstance['fix'] - $priceinstance['options']["total"] - $min_instance_price_reduction * $priceinstance['fix'];
				if (!empty($priceinstance['user'])) {
					$minuserprice = $priceinstance['user'] - $min_instance_price_reduction * $priceinstance['user'];
				}
				if ($price_instance < $minfixprice) {
					setEventMessages($langs->trans("FixPriceMustBeMoreThenMinFixPrice", $minfixprice, $langs->getCurrencySymbol($conf->currency)), null, 'errors');
					$errors++;
				}
				if (isset($priceinstance['user']) && $price_instance_per_user < $minuserprice) {
					setEventMessages($langs->trans("PricePerUsersMustBeMoreThenMinUserPrice", $minuserprice, $langs->getCurrencySymbol($conf->currency)), null, 'errors');
					$errors++;
				}
				if (!$errors) {
					require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";

					$price_instance = price($price_instance);
					$result = dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_FIX_PRICE_".$mythirdpartyaccount->id."_".$priceproductid, price2num($price_instance), 'chaine', 0, '', $conf->entity);
					if ($result > 0 && isset($priceinstance['user'])) {
						$price_instance_per_user = price($price_instance_per_user);
						$result = dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_PRICE_PER_USER_".$mythirdpartyaccount->id."_".$priceproductid, price2num($price_instance_per_user), 'chaine', 0, '', $conf->entity);
					}
					if (isset($priceinstance['options']["lines"])) {
						$cptoptions = 1;
						foreach ($priceinstance['options']["lines"] as $key => $value) {
							$minoptionprice = $value["base"] - $min_instance_price_reduction * $value["base"];
							if ($value["posted"] < $minoptionprice) {
								setEventMessages($langs->trans("PriceOptionMustBeMoreThenMinOptionPrice", $cptoptions, $minoptionprice, $langs->getCurrencySymbol($conf->currency)), null, 'errors');
								$errors++;
							}
							if (!$errors) {
								$result = dolibarr_set_const($db, "SELLYOURSAAS_RESELLER_PRICE_OPTION_".$key."_".$mythirdpartyaccount->id."_".$priceproductid, price2num($value["posted"]), 'chaine', 0, '', $conf->entity);
								if ($result <= 0) {
									break;
								}
							}
							$cptoptions ++;
						}
					}
				}
			}
		}
	}

	if ($result <= 0 || $errors) {
		$action = "editproperty";
		$propertykey = $priceproductid;
	} else {
		setEventMessages($langs->trans("PricesSuccessfullyUpdated"), null);
	}
}

if (getDolGlobalInt("SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE") && $action == 'resetpropertyconfirm') {
	if (!empty($propertykey) && !empty($mythirdpartyaccount->id)) {
		require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
		$result = dolibarr_del_const($db, "SELLYOURSAAS_RESELLER_FIX_PRICE_".$mythirdpartyaccount->id."_".$propertykey, $conf->entity);
		if ($result > 0) {
			$result = dolibarr_del_const($db, "SELLYOURSAAS_RESELLER_PRICE_PER_USER_".$mythirdpartyaccount->id."_".$propertykey, $conf->entity);
		}
		$product = new Product($db);
		$tmpprodchild = new Product($db);

		$result = $product->fetch($propertykey);
		if ($result <= 0) {
			setEventMessages($langs->trans("ProductNotFound"), null, 'errors');
			$errors++;
		}
		$product->sousprods = array();
		$product->get_sousproduits_arbo();
		$tmparraysousproduct = $product->get_arbo_each_prod();
		if (count($tmparraysousproduct) > 0) {
			foreach ($tmparraysousproduct as $key => $value) {
				$tmpprodchild->fetch($value["id"]);	// To load the product
				if (preg_match('/user/i', $tmpprodchild->ref) || preg_match('/user/i', $tmpprodchild->array_options['options_resource_label'])) {
					// Already deleted
				} else {
					$result = dolibarr_del_const($db, "SELLYOURSAAS_RESELLER_PRICE_OPTION_".$value["id"]."_".$mythirdpartyaccount->id."_".$propertykey, $conf->entity);
				}
				if ($result <= 0) {
					break;
				}
			}
		}
		if ($result > 0) {
			setEventMessages($langs->trans("PricesSuccessfullyCleared"), null);
		} else {
			setEventMessages($langs->trans("ErrorClearingPrice"), null, "errors");
		}
	} else {
		setEventMessages($langs->trans("ErrorClearingPrice"), null, "errors");
	}
}

if ($action == 'updateurl') {
	$sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL;

	if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
		$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
		if (! empty($conf->global->$newnamekey)) $sellyoursaasemail = $conf->global->$newnamekey;
	}

	setEventMessages($langs->trans("FeatureNotYetAvailable").'.<br>'.$langs->trans("ContactUsByEmail", $sellyoursaasemail), null, 'warnings');
} elseif ($action == 'changeplan') {
	$sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
	if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
		$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
		if (! empty($conf->global->$newnamekey)) $sellyoursaasemail = $conf->global->$newnamekey;
	}

	setEventMessages($langs->trans("FeatureNotYetAvailable").'.<br>'.$langs->trans("ContactUsByEmail", $sellyoursaasemail), null, 'warnings');
	$action = '';
} elseif ($action == 'validatenonprofit') {
	$sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
	if ($mythirdpartyaccount->array_options['options_checkboxnonprofitorga'] == 'nonprofit'
		|| getDolGlobalInt("SELLYOURSAAS_ENABLE_FREE_PAYMENT_MODE")) {
		// Make renewals on contracts of customer
		dol_syslog("--- Make renewals on contracts for thirdparty id=".$mythirdpartyaccount->id, LOG_DEBUG, 0);

		$sellyoursaasutils = new SellYourSaasUtils($db);

		$result = $sellyoursaasutils->doRenewalContracts($mythirdpartyaccount->id);		// A refresh is also done if renewal is done
		if ($result != 0) {
			$error++;
			setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
			dol_syslog("Failed to make renewal of contract ".$sellyoursaasutils->error, LOG_ERR);
		}

		// Create a recurring invoice (+real invoice + contract renewal) if there is no recurring invoice yet
		if (! $error) {
			$result = $contract->fetch(GETPOST('contractid', 'int'));
			if ($result > 0) {
				dol_syslog("--- Create recurring invoice on contract contract_id = ".$contract->id." if it does not have yet.", LOG_DEBUG, 0);

				if ($contract->array_options['options_deployment_status'] != 'done') {
					dol_syslog("--- Deployment status is not 'done', we discard this contract", LOG_DEBUG, 0);
					setEventMessages("Deployment status is not 'done'", 'errors');
					header("Location: ".$_SERVER['PHP_SELF']."?mode=instances#contractid".$contract->id);// This is a not valid contract (undeployed or not yet completely deployed), so we discard this contract to avoid to create template not expected
				}

				// Make a test to pass loop if there is already a template invoice
				$result = $contract->fetchObjectLinked();
				if ($result < 0) {
					dol_syslog("--- Error during fetchObjectLinked, we discard this contract", LOG_ERR, 0);
					setEventMessages("Error during fetchObjectLinked", 'errors');
					header("Location: ".$_SERVER['PHP_SELF']."?mode=instances#contractid".$contract->id);// There is an error, so we discard this contract to avoid to create template twice
				}
				if (! empty($contract->linkedObjectsIds['facturerec'])) {
					$templateinvoice = reset($contract->linkedObjectsIds['facturerec']);
					if ($templateinvoice > 0) {			// There is already a template invoice, so we discard this contract to avoid to create template twice
						dol_syslog("--- There is already a recurring invoice on the contract contract_id = ".$contract->id, LOG_DEBUG, 0);
						setEventMessages("There is already a recurring invoice on the contract contract_id = ".$contract->id, 'errors');
						header("Location: ".$_SERVER['PHP_SELF']."?mode=instances#contractid".$contract->id);
					}
				}

				dol_syslog("--- No template invoice found linked to the contract contract_id = ".$contract->id." that is NOT null, so we refresh contract before creating template invoice + creating invoice (if template invoice date is already in past) + making contract renewal.", LOG_DEBUG, 0);

				$comment = 'Refresh contract '.$contract->ref.' after entering a payment mode on dashboard, because we need to create a template invoice (case of STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION set)';
				// First launch update of resources:
				// This update qty of contract lines + qty into linked template invoice.
				$result = $sellyoursaasutils->sellyoursaasRemoteAction('refreshmetrics', $contract, 'admin', '', '', '0', $comment);

				dol_syslog("--- No template invoice found linked to the contract contract_id = ".$contract->id.", so we create it then we create real invoice (if template invoice date is already in past) then make contract renewal.", LOG_DEBUG, 0);

				// Now create invoice draft
				$dateinvoice = $contract->array_options['options_date_endfreeperiod'];
				if ($dateinvoice < $now) $dateinvoice = $now;

				$invoice_draft = new Facture($db);
				$tmpproduct = new Product($db);

				// Create empty invoice
				if (! $error) {
					$invoice_draft->socid				= $contract->socid;
					$invoice_draft->type				= Facture::TYPE_STANDARD;
					$invoice_draft->number				= '';
					$invoice_draft->date				= $dateinvoice;

					$invoice_draft->note_private		= 'Template invoice created after adding a payment mode for card/stripe';
					$invoice_draft->mode_reglement_id	= dol_getIdFromCode($db, 'CB', 'c_paiement', 'code', 'id', 1);
					$invoice_draft->cond_reglement_id	= dol_getIdFromCode($db, 'RECEP', 'c_payment_term', 'code', 'rowid', 1);

					$invoice_draft->fetch_thirdparty();

					$origin='contrat';
					$originid=$contract->id;

					$invoice_draft->origin = $origin;
					$invoice_draft->origin_id = $originid;

					// Possibility to add external linked objects with hooks
					$invoice_draft->linked_objects[$invoice_draft->origin] = $invoice_draft->origin_id;

					$idinvoice = $invoice_draft->create($user);      // This include class to add_object_linked() and add add_contact()
					if (! ($idinvoice > 0)) {
						setEventMessages($invoice_draft->error, $invoice_draft->errors, 'errors');
						$error++;
					}
				}

				$frequency=0;
				$frequency_unit='m';
				$discountcode = strtoupper(trim(GETPOST('discountcode', 'aZ09')));	// If a discount code was prodived on page
				/* If a discount code exists on contract level, it was used to prefill the payment page, so it is received into the GETPOST('discountcode', 'int').
				if (empty($discountcode) && ! empty($contract->array_options['options_discountcode'])) {	// If no discount code provided, but we find one on contract, we use this one
					$discountcode = $contract->array_options['options_discountcode'];
				}*/

				$discounttype = '';
				$discountval = 0;
				$validdiscountcodearray = array();
				$nbofproductapp = 0;

				// Add lines on invoice
				if (! $error) {
					// Add lines of contract to template invoice
					$srcobject = $contract;

					$lines = $srcobject->lines;
					if (empty($lines) && method_exists($srcobject, 'fetch_lines')) {
						$srcobject->fetch_lines();
						$lines = $srcobject->lines;
					}

					$date_start = false;
					$fk_parent_line=0;
					$num=count($lines);
					for ($i=0; $i<$num; $i++) {
						$label=(! empty($lines[$i]->label)?$lines[$i]->label:'');
						$desc=(! empty($lines[$i]->desc)?$lines[$i]->desc:$lines[$i]->libelle);
						if ($invoice_draft->situation_counter == 1) $lines[$i]->situation_percent =  0;

						// Positive line
						$product_type = ($lines[$i]->product_type ? $lines[$i]->product_type : 0);

						// Date start
						$date_start = false;
						if ($lines[$i]->date_debut_prevue) $date_start = $lines[$i]->date_debut_prevue;
						if ($lines[$i]->date_debut_reel) $date_start = $lines[$i]->date_debut_reel;
						if ($lines[$i]->date_start) $date_start = $lines[$i]->date_start;

						// Date end
						$date_end = false;
						if ($lines[$i]->date_fin_prevue) $date_end = $lines[$i]->date_fin_prevue;
						if ($lines[$i]->date_fin_reel) $date_end = $lines[$i]->date_fin_reel;
						if ($lines[$i]->date_end) $date_end = $lines[$i]->date_end;

						// If date start is in past, we set it to now
						$now = dol_now();
						if ($date_start < $now) {
							dol_syslog("--- Date start is in past, so we take current date as date start and update also end date of contract", LOG_DEBUG, 0);
							$tmparray = sellyoursaasGetExpirationDate($srcobject, 0);
							$duration_value = $tmparray['duration_value'];
							$duration_unit = $tmparray['duration_unit'];

							$date_start = $now;
							$date_end = dol_time_plus_duree($now, $duration_value, $duration_unit) - 1;

							// Because we update the end date planned of contract too
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
							$lines[$i]->fetch_optionals($lines[$i]->rowid);
							$array_options = $lines[$i]->array_options;
						}

						$tva_tx = $lines[$i]->tva_tx;
						if (! empty($lines[$i]->vat_src_code) && ! preg_match('/\(/', $tva_tx)) $tva_tx .= ' ('.$lines[$i]->vat_src_code.')';

						// View third's localtaxes for NOW and do not use value from origin.
						$localtax1_tx = get_localtax($tva_tx, 1, $invoice_draft->thirdparty);
						$localtax2_tx = get_localtax($tva_tx, 2, $invoice_draft->thirdparty);

						//$price_invoice_template_line = $lines[$i]->subprice * GETPOST('frequency_multiple','int');
						$price_invoice_template_line = $lines[$i]->subprice;


						// Get data from product (frequency, discount type and val)
						$tmpproduct->fetch($lines[$i]->fk_product);

						dol_syslog("--- Read frequency for product id=".$tmpproduct->id, LOG_DEBUG, 0);
						if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
							// Protection to avoid to validate contract with several 'app' products.
							$nbofproductapp++;
							if ($nbofproductapp > 1) {
								dol_syslog("--- Error: Bad definition of contract. There is more than 1 service with type 'app'", LOG_ERR);
								$error++;
								break;
							}
							$frequency = $tmpproduct->duration_value;
							$frequency_unit = $tmpproduct->duration_unit;

							if ($tmpproduct->array_options['options_register_discountcode']) {
								$tmpvaliddiscountcodearray = explode(',', $tmpproduct->array_options['options_register_discountcode']);
								foreach ($tmpvaliddiscountcodearray as $valdiscount) {
									$valdiscountarray = explode(':', $valdiscount);
									$tmpcode = strtoupper(trim($valdiscountarray[0]));
									$tmpval = str_replace('%', '', trim($valdiscountarray[1]));
									if (is_numeric($tmpval)) {
										$validdiscountcodearray[$tmpcode] = array('code'=>$tmpcode, 'type'=>'percent', 'value'=>$tmpval);
									} else {
										dol_syslog("--- Error: Bad definition of discount for product id = ".$tmpproduct->id." with value ".$tmpproduct->array_options['options_register_discountcode'], LOG_ERR);
									}
								}
								// If we entered a discountcode or get it from contract
								if (! empty($validdiscountcodearray[$discountcode])) {
									$discounttype = $validdiscountcodearray[$discountcode]['type'];
									$discountval = $validdiscountcodearray[$discountcode]['value'];
								} else {
									$discountcode = '';
								}
								//var_dump($validdiscountcodearray); var_dump($discountcode); var_dump($discounttype); var_dump($discountval); exit;
								if ($discounttype == 'percent') {
									if ($discountval > $discount) {
										$discount = $discountval;		// If discount with coupon code is higher than the one defined into contract.
									}
								}
							}
						}

						// Insert the line
						$price_invoice_template_line = 0;
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
					}
				}

				// Now we convert invoice into a template
				if (! $error) {
					$frequency = 0;	// read frequency of product app
					$frequency_unit = (! empty($frequency_unit) ? $frequency_unit :'m');	// read frequency_unit of product app
					$tmp=dol_getdate($date_start?$date_start:$now);
					$reyear=$tmp['year'];
					$remonth=$tmp['mon'];
					$reday=$tmp['mday'];
					$rehour=$tmp['hours'];
					$remin=$tmp['minutes'];
					$nb_gen_max=0;

					$invoice_rec = new FactureRec($db);

					$invoice_rec->titre = 'Template invoice for '.$contract->ref.' '.$contract->ref_customer;
					$invoice_rec->title = 'Template invoice for '.$contract->ref.' '.$contract->ref_customer;
					$invoice_rec->note_private = $contract->note_private;
					$invoice_rec->note_public  = $contract->note_public;
					$invoice_rec->mode_reglement_id = $invoice_draft->mode_reglement_id;
					$invoice_rec->cond_reglement_id = $invoice_draft->cond_reglement_id;

					$invoice_rec->usenewprice = 0;

					$invoice_rec->frequency = $frequency;
					$invoice_rec->unit_frequency = $frequency_unit;
					$invoice_rec->nb_gen_max = $nb_gen_max;
					$invoice_rec->auto_validate = 0;

					$invoice_rec->fk_project = 0;

					$date_next_execution = dol_mktime($rehour, $remin, 0, $remonth, $reday, $reyear);
					$invoice_rec->date_when = $date_next_execution;

					// Add discount into the template invoice (it was already added into lines)
					if ($discountcode) {
						$invoice_rec->array_options['options_discountcode'] = $discountcode;
					}

					// Get first contract linked to invoice used to generate template
					if ($invoice_draft->id > 0) {
						$srcObject = $invoice_draft;

						$srcObject->fetchObjectLinked();

						if (! empty($srcObject->linkedObjectsIds['contrat'])) {
							$contractidid = reset($srcObject->linkedObjectsIds['contrat']);

							$invoice_rec->origin = 'contrat';
							$invoice_rec->origin_id = $contractidid;
							$invoice_rec->linked_objects[$invoice_draft->origin] = $invoice_draft->origin_id;
						}
					}

					$oldinvoice = new Facture($db);
					$oldinvoice->fetch($invoice_draft->id);

					$invoicerecid = $invoice_rec->create($user, $oldinvoice->id);
					if ($invoicerecid > 0) {
						$sql = 'UPDATE '.MAIN_DB_PREFIX.'facturedet_rec SET date_start_fill = 1, date_end_fill = 1 WHERE fk_facture = '.$invoice_rec->id;
						$result = $db->query($sql);
						if (! $error && $result < 0) {
							$error++;
							setEventMessages($db->lasterror(), null, 'errors');
						}

						$result=$oldinvoice->delete($user, 1);
						if (! $error && $result < 0) {
							$error++;
							setEventMessages($oldinvoice->error, $oldinvoice->errors, 'errors');
						}
					} else {
						$error++;
						setEventMessages($invoice_rec->error, $invoice_rec->errors, 'errors');
					}

					// Make renewals on contracts of customer
					if (! $error) {
						dol_syslog("--- Now we make renewal of contracts for thirdpartyid=".$mythirdpartyaccount->id." if payments were ok and contract are not unsuspended", LOG_DEBUG, 0);

						$sellyoursaasutils = new SellYourSaasUtils($db);

						$result = $sellyoursaasutils->doRenewalContracts($mythirdpartyaccount->id);		// A refresh is also done if renewal is done
						if ($result != 0) {
							$error++;
							setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
						}
					}
					header("Location: ".$_SERVER['PHP_SELF']."?mode=instances#contractid".$contract->id);
				}
			}
		}
	}

	$action = '';
} elseif ($action == 'send' && !GETPOST('addfile') && !GETPOST('removedfile')) {
	// Send support ticket
	$error = 0;

	$emailfrom = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;

	if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
		$newnamekey = 'SELLYOURSAAS_NOREPLY_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
		if (! empty($conf->global->$newnamekey)) $sellyoursaasemail = $conf->global->$newnamekey;
	}

	$emailto = GETPOST('to', 'alpha');
	$replyto = GETPOST('from', 'alpha');
	$topic = GETPOST('subject', 'restricthtml');
	$content = GETPOST('content', 'restricthtml');
	$groupticket=GETPOST('ticketcategory', 'aZ09');

	if (empty($replyto)) {
		$error++;
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("MailFrom")), null, 'errors');
	}
	if (empty($topic)) {
		$error++;
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("MailTopic")), null, 'errors');
	}
	if (GETPOSTISSET('ticketcategory') && !GETPOST('ticketcategory', 'aZ09')) {
		$error++;
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("GroupOfTicket")), null, 'errors');
	}
	if (empty($content)) {
		$error++;
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Description")), null, 'errors');
	}

	if (!$error) {
		$channel = GETPOST('supportchannel', 'alpha');
		$tmparray = explode('_', $channel, 2);
		$priority = 'low';
		if (!empty($tmparray[1])) {
			$priority = $tmparray[0];
			$contractid = $tmparray[1];
		}
		$tmpcontract = null;
		if ($contractid > 0) {
			$tmpcontract = $listofcontractid[$contractid];
		}

		// Set $topic + check thirdparty validity
		if (is_object($tmpcontract)) {
			$topic = '[Ticket - '.$tmpcontract->ref_customer.'] '.$topic;

			$tmpcontract->fetch_thirdparty();	// Note: It should match $mythirdpartyaccount
			if (!is_object($tmpcontract->thirdparty) || $tmpcontract->thirdparty->id != $mythirdpartyaccount->id) {
				// Error, we try to post a ticket using a contract id of another thirdparty
				$action = 'presend';
				$error++;
			}
		} else {
			$topic = '[Ticket - '.$mythirdpartyaccount->name.'] '.$topic;
		}

		// Set $content
		$content .= "<br><br>\n";
		$content .= 'Date: '.dol_print_date($now, 'dayhour')."<br>\n";
		if ($groupticket) {
			$content .= 'Group: '.dol_escape_htmltag($groupticket)."<br>\n";
		}
		$content .= 'Priority: '.$priority."<br>\n";
		if (is_object($tmpcontract)) {
			$content .= 'Instance: <a href="https://'.$tmpcontract->ref_customer.'">'.$tmpcontract->ref_customer."</a><br>\n";
			//$content .= 'Ref contract: <a href="xxx/contrat/card.php?id='.$tmpcontract->ref.">".$tmpcontract->ref."</a><br>\n"; 	// No link to backoffice as the mail is used with answer to.
			$content .= 'Ref contract: '.$tmpcontract->ref."<br>\n";
		} else {
			$content .= "Instance: None<br>\n";
			$content .= "Ref contract: None<br>\n";
		}

		// Sender
		if (is_object($tmpcontract) && is_object($tmpcontract->thirdparty)) {
			$content .= 'Organization: '.$tmpcontract->thirdparty->name."<br>\n";
			$content .= 'Email: '.$tmpcontract->thirdparty->email."<br>\n";
			$content .= $tmpcontract->thirdparty->array_options['options_lastname'].' '.$tmpcontract->thirdparty->array_options['options_firstname']."<br>\n";
		} else {
			$content .= 'Organization: '.$mythirdpartyaccount->name."<br>\n";
			$content .= 'Email: '.$mythirdpartyaccount->email."<br>\n";
		}

		// Add the services and support of contract
		if (is_object($tmpcontract)) {
			foreach ($tmpcontract->lines as $key => $val) {
				if ($val->fk_product > 0) {
					$product = new Product($db);
					$product->fetch($val->fk_product);
					$content .= '- '.$langs->trans("Service").' '.$product->ref.' - '.$langs->trans("Qty")." ".$val->qty;
					$content.=' ('.$product->array_options['options_app_or_option'].')';
					if ($product->array_options['options_app_or_option'] == 'app') {
						$content .= ' - Support type = '.$product->array_options['options_typesupport'];
					}
				} else {
					$content .= '- Service '.$val->label;
				}
				$content .= "<br>\n";;
			}
		}

		$arr_file = array();
		$arr_mime = array();
		$arr_name = array();
		$upload_dir = $conf->sellyoursaas->dir_temp."/support_".$mythirdpartyaccount->id.'.tmp';
		$listofpaths = dol_dir_list($upload_dir, 'files', 0, '', '', 'name', SORT_ASC, 0);
		if (count($listofpaths)) {
			foreach ($listofpaths as $key => $val) {
				$arr_file[] = $listofpaths[$key]['fullname'];
				$arr_mime[] = dol_mimetype($listofpaths[$key]['name']);
				$arr_name[] = $listofpaths[$key]['name'];
			}
		}

		$trackid = 'thi'.$mythirdpartyaccount->id;
		if (is_object($tmpcontract)) {
			$trackid = 'con'.$tmpcontract->id;
		}

		// Send email
		$cmailfile = new CMailFile($topic, $emailto, $emailfrom, $content, $arr_file, $arr_mime, $arr_name, '', '', 0, 1, '', '', $trackid, '', 'standard', $replyto);
		$result = $cmailfile->sendfile();

		if ($result) {
			setEventMessages($langs->trans("TicketSent"), null, 'warnings');
			$action = '';
		} else {
			setEventMessages($langs->trans("FailedToSentTicketPleaseTryLater").' '.$cmailfile->error, $cmailfile->errors, 'errors');
			$action = 'presend';
		}
	} else {
		$action = 'presend';
	}
} elseif ($action == 'sendbecomereseller') {
	// Send reseller request
	$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;
	$sellyoursaasnoreplyemail = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;

	if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
		$newnamekey = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
		if (! empty($conf->global->$newnamekey)) $sellyoursaasname = $conf->global->$newnamekey;
		$newnamekey = 'SELLYOURSAAS_NOREPLAY_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
		if (! empty($conf->global->$newnamekey)) $sellyoursaasnoreplyemail = $conf->global->$newnamekey;
	}

	// Set email to use when applying for reseller program. Use SELLYOURSAAS_RESELLER_EMAIL and if not found backfall on SELLYOURSAAS_MAIN_EMAIL.
	$emailto = getDolGlobalString('SELLYOURSAAS_RESELLER_EMAIL', getDolGlobalString('SELLYOURSAAS_MAIN_EMAIL'));
	if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
			$newnamekey = 'SELLYOURSAAS_RESELLER_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
			if (!empty($conf->global->$newnamekey)) $emailto = $conf->global->$newnamekey;
	}

	$emailfrom = $sellyoursaasnoreplyemail;
	$replyto = GETPOST('from', 'alpha');
	$topic = '['.$sellyoursaasname.'] - '.GETPOST('subject', 'none').' - '.$mythirdpartyaccount->name;
	$content = GETPOST('content', 'restricthtml');
	$content .= "<br><br>\n";
	$content .= 'Date: '.dol_print_date($now, 'dayhour')."<br>\n";
	$content .= 'ThirdParty ID: '.$mythirdpartyaccount->id."<br>\n";
	$content .= 'Email: '.GETPOST('from', 'alpha')."<br>\n";

	$trackid = 'thi'.$mythirdpartyaccount->id;

	$cmailfile = new CMailFile($topic, $emailto, $emailfrom, $content, array(), array(), array(), '', '', 0, 1, '', '', $trackid, '', 'standard', $replyto);
	$result = $cmailfile->sendfile();

	if ($result) setEventMessages($langs->trans("TicketSent"), null, 'warnings');
	else setEventMessages($langs->trans("FailedToSentTicketPleaseTryLater").' '.$cmailfile->error, $cmailfile->errors, 'errors');
	$action = '';
} elseif ($action == 'updatemythirdpartyaccount') {
	$error = 0;

	$orgname = GETPOST('orgName', 'nohtml');
	$address = GETPOST('address', 'nohtml');
	$town = GETPOST('town', 'nohtml');
	$zip = GETPOST('zip', 'nohtml');
	$stateorcounty = GETPOST('stateorcounty', 'nohtml');
	$country_code = GETPOST('country_id', 'aZ09');
	$vatassuj = (GETPOST('vatassuj', 'alpha') == 'on' ? 1 : 0);
	$vatnumber = GETPOST('vatnumber', 'alpha');

	if (empty($orgname)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("NameOfCompany")), null, 'errors');
		header("Location: ".$_SERVER['PHP_SELF']."?mode=myaccount#updatemythirdpartyaccount");
		exit;
	}
	if (empty($country_code)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Country")), null, 'errors');
		header("Location: ".$_SERVER['PHP_SELF']."?mode=myaccount#updatemythirdpartyaccount");
		exit;
	}

	$country_id = dol_getIdFromCode($db, $country_code, 'c_country', 'code', 'rowid');

	$mythirdpartyaccount->oldcopy = dol_clone($mythirdpartyaccount);

	$mythirdpartyaccount->name = $orgname;
	$mythirdpartyaccount->address = $address;
	$mythirdpartyaccount->town = $town;
	$mythirdpartyaccount->zip = $zip;
	if ($country_id > 0) {
		$mythirdpartyaccount->country_id = $country_id;
		$mythirdpartyaccount->country_code = $country_code;
	}
	$mythirdpartyaccount->tva_assuj = $vatassuj;
	$mythirdpartyaccount->tva_intra = preg_replace('/\s/', '', $vatnumber);

	if ($mythirdpartyaccount->tva_assuj && $mythirdpartyaccount->tva_intra) {
		include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		$vatisvalid = isValidVATID($mythirdpartyaccount);
		if (! $vatisvalid) {
			$error++;
			setEventMessages($langs->trans("ErrorBadValueForIntraVAT", $mythirdpartyaccount->tva_intra, $langs->transnoentitiesnoconv('VATIntra'), $mythirdpartyaccount->country_code, $langs->transnoentitiesnoconv('VATIsUsed')), null, 'errors');
			$mode='myaccount';
			//header("Location: ".$_SERVER['PHP_SELF']."?mode=myaccount#updatemythirdpartyaccount");
			//exit;
		}
	}

	if (! $error) {
		$db->begin();	// Start transaction

		$result = $mythirdpartyaccount->update($mythirdpartyaccount->id, $user);

		if ($result > 0) {
			$mythirdpartyaccount->country_code = $country_code;

			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			$db->commit();
		} else {
			$langs->load("errors");
			setEventMessages($langs->trans('ErrorFailedToSaveRecord'), null, 'errors');
			setEventMessages($mythirdpartyaccount->error, $mythirdpartyaccount->errors, 'errors');
			$db->rollback();
		}
	}
} elseif ($action == 'updatemythirdpartylogin') {
	$email = trim(GETPOST('email', 'nohtml'));
	$oldemail = trim(GETPOST('oldemail', 'nohtml'));
	$emailccinvoice = trim(GETPOST('emailccinvoice', 'nohtml'));
	$oldemailccinvoice = trim(GETPOST('oldemailccinvoice', 'nohtml'));
	$firstname = trim(GETPOST('firstName', 'nohtml'));
	$lastname = trim(GETPOST('lastName', 'nohtml'));
	$phone = trim(GETPOST('phone', 'nohtml'));
	$oldphone = trim(GETPOST('oldphone', 'nohtml'));

	if (empty($email)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Email")), null, 'errors');
		$error++;
	}
	if ($email && ! isValidEmail($email)) {
		setEventMessages($langs->trans("ErrorBadEMail", $email), null, 'errors');
		$error++;
	}
	if ($oldemail != $email) {		// A request to change email was done.
		// Test if email already exists
		$tmpthirdparty = new Societe($db);
		$tmpthirdparty->fetch(0, '', '', '', '', '', '', '', '', '', $email);
		if ($tmpthirdparty->id > 0) {
			$error++;
			setEventMessages($langs->trans("SorryEmailExistsforAnotherAccount", $email), null, 'errors');
		}
	}
	if (!empty($phone) && !isValidPhone($phone)) {
		setEventMessages($langs->trans("ErrorBadValueForPhone"), null, 'errors');
		$error++;
	}

	if (! $error) {
		$db->begin();	// Start transaction

		$mythirdpartyaccount->oldcopy = dol_clone($mythirdpartyaccount);
		$mythirdpartyaccount->email = $email;
		$mythirdpartyaccount->phone = $phone;
		$mythirdpartyaccount->array_options['options_firstname'] = $firstname;
		$mythirdpartyaccount->array_options['options_lastname'] = $lastname;
		$mythirdpartyaccount->array_options['options_optinmessages'] = GETPOST('optinmessages', 'aZ09') == '1' ? 1 : 0;
		$mythirdpartyaccount->array_options['options_emailccinvoice'] = $emailccinvoice;

		$result = $mythirdpartyaccount->update($mythirdpartyaccount->id, $user);

		if ($result > 0) {
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');

			$db->commit();

			header("Location: ".$_SERVER['PHP_SELF']."?mode=myaccount#updatemythirdpartylogin");
			exit;
		} else {
			$langs->load("errors");
			setEventMessages($langs->trans('ErrorFailedToSaveRecord'), null, 'errors');
			setEventMessages($mythirdpartyaccount->error, $mythirdpartyaccount->errors, 'errors');
			$db->rollback();
		}
	}
} elseif ($action == 'updatepassword') {
	$password = GETPOST('password', 'nohtml');
	$password2 = GETPOST('password2', 'nohtml');

	if (empty($password) || empty($password2)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Password")), null, 'errors');
		header("Location: ".$_SERVER['PHP_SELF']."?mode=myaccount#updatepassword");
		exit;
	}
	if ($password != $password2) {
		setEventMessages($langs->trans("ErrorPasswordMismatch"), null, 'errors');
		header("Location: ".$_SERVER['PHP_SELF']."?mode=myaccount#updatepassword");
		exit;
	}

	$db->begin();	// Start transaction

	$mythirdpartyaccount->oldcopy = dol_clone($mythirdpartyaccount);

	$mythirdpartyaccount->array_options['options_password'] = $password;

	$result = $mythirdpartyaccount->update($mythirdpartyaccount->id, $user);

	if ($result > 0) {
		setEventMessages($langs->trans("PasswordModified"), null, 'mesgs');
		$db->commit();
	} else {
		$langs->load("errors");
		setEventMessages($langs->trans('ErrorFailedToChangePassword'), null, 'errors');
		setEventMessages($mythirdpartyaccount->error, $mythirdpartyaccount->errors, 'errors');
		$db->rollback();
	}
} elseif ($action == 'createpaymentmode') {		// Create credit card stripe or sepa record
	if (GETPOST("submitsepa", 'aZ09')) {
		$langs->load("banks");
		include_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';
		include_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';

		$companybankaccount = new CompanyBankAccount($db);
		$companybankaccount->label = GETPOST('bankname', 'alphanohtml');
		$companybankaccount->bank = GETPOST('bankname', 'alphanohtml');
		$companybankaccount->iban_prefix = GETPOST('iban', 'alphanohtml');
		$companybankaccount->iban = GETPOST('iban', 'alphanohtml');
		$companybankaccount->bic = GETPOST('bic', 'alphanohtml');
		$companybankaccount->socid = $mythirdpartyaccount->id;
		$companybankaccount->datec = dol_now();
		$companybankaccount->frstrecur = 'RECUR';

		if (empty($companybankaccount->label)) {
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("BankName")), null, 'errors');
			$action = '';
			$error++;
		}

		// Test if iban is ok
		$testiban = checkIbanForAccount($companybankaccount);
		if (empty($companybankaccount->iban_prefix) || !$testiban) {
			setEventMessages($langs->trans("IbanNotValid"), null, 'errors');
			$action = '';
			$error++;
		}
		$testbic = checkSwiftForAccount($companybankaccount);
		if (empty($companybankaccount->bic) || !$testbic) {
			setEventMessages($langs->trans("SwiftNotValid"), null, 'errors');
			$action = '';
			$error++;
		}

		$db->begin();

		if (! $error) {
			$companybankid = $companybankaccount->create($user);

			if (empty($companybankaccount->rum)) {
				require_once DOL_DOCUMENT_ROOT.'/compta/prelevement/class/bonprelevement.class.php';
				$prelevement = new BonPrelevement($db);

				$companybankaccount->rum = $prelevement->buildRumNumber($mythirdpartyaccount->code_client, $companybankaccount->datec, $companybankid);
			}

			$resultbankcreate = $companybankaccount->update($user);
			if ($resultbankcreate > 0) {
				$sql = "UPDATE ".MAIN_DB_PREFIX ."societe_rib";
				$sql.= " SET status = '".$db->escape($servicestatusstripe)."'";
				$sql.= " WHERE rowid = " . $companybankid;
				$sql.= " AND type = 'ban'";
				$resql = $db->query($sql);
				if (! $resql) {
					dol_syslog("Failed to update societe_rib ".$db->lasterror(), LOG_ERR);
					setEventMessages($db->lasterror(), null, 'errors');
					$error++;
				}
				if (!$error) {
					$resultbanksetdefault = $companybankaccount->setAsDefault(0, '');
					if ($resultbanksetdefault > 0) {
						setEventMessages($langs->trans("BankSaved"), null, 'mesgs');
						setEventMessages($langs->trans("WeWillContactYouForMandaSepate"), null, 'warnings');
					} else {
						setEventMessages($companybankaccount->error, $companybankaccount->errors, 'errors');
						$error++;
					}
				}
			} else {
				if ($db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
					setEventMessages($langs->trans("ABankWithThisLabelAlreadyExists"), null, 'errors');
				} else {
					setEventMessages($companybankaccount->error, $companybankaccount->errors, 'errors');
				}
				$error++;
			}
		}

		if (!$error && isModEnabled('stripe')) {
			$companybankaccount->fetch(GETPOST('bankid'));
			$service = 'StripeTest';
			$servicestatus = 0;
			if (!empty($conf->global->STRIPE_LIVE) && !GETPOST('forcesandbox', 'alpha')) {
				$service = 'StripeLive';
				$servicestatus = 1;
			}

			$companypaymentmode = new CompanyPaymentMode($db);
			$companypaymentmode->fetch(null, null, $socid);
			$stripe = new Stripe($db);

			if ($companypaymentmode->type != 'ban') {
				$error++;
				setEventMessages('ThisPaymentModeIsNotSepa', null, 'errors');
			} else {
				$cu = $stripe->customerStripe($mythirdpartyaccount, $stripeacc, $servicestatus, 1);
				if (!$cu) {
					$error++;
					setEventMessages($stripe->error, $stripe->errors, 'errors');
				}

				if (!$error) {
					// Creation of Stripe SEPA + update of societe_account
					$card = $stripe->sepaStripe($cu, $companypaymentmode, $stripeacc, $servicestatus, 1);
					if (!$card) {
						$error++;
						setEventMessages($stripe->error, $stripe->errors, 'errors');
					} else {
						setEventMessages("SEPA on Stripe", "SEPA IBAN is now linked to customer account !");
					}
				}
			}
		}

		if (!$error) {
			$bankname = '';
			$iban = '';
			$bic = '';

			$db->commit();
		} else {
			$db->rollback();
		}
	} else {
		if (! empty($conf->global->STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION)) {
			$setupintentid = GETPOST('setupintentid', 'alpha');

			/*$thirdparty_id = $mythirdpartyaccount->id;
			$thirdparty_id = GETPOST('thirdparty_id', 'alpha');
			if ($thirdparty_id != $mythirdpartyaccount->id)
			{
				setEventMessages('Error: The thirdpartyid received ('.$thirdparty_id.') is not the same than the id of logged thirdparty in current session ('.$mythirdpartyaccount->id.')', null, 'errors');
				$action='';
				$mode='registerpaymentmode';
				$error++;
			}*/
			if (empty($setupintentid)) {
				setEventMessages('Error: Failed to get the setupintent id', null, 'errors');
				$action='';
				$mode='registerpaymentmode';
				$error++;
			}

			if (! $error) {
				$thirdpartyhadalreadyapaymentmode = sellyoursaasThirdpartyHasPaymentMode($mythirdpartyaccount->id);    // Check if customer has already a payment mode or not

				require_once DOL_DOCUMENT_ROOT.'/stripe/config.php';
				global $stripearrayofkeysbyenv;
				// Reforce the $stripearrayofkeys because content may change depending on option
				if (empty($conf->global->STRIPE_LIVE) || GETPOST('forcesandbox', 'alpha') || ! empty($conf->global->SELLYOURSAAS_FORCE_STRIPE_TEST)) {
					$stripearrayofkeys = $stripearrayofkeysbyenv[0];	// Test
				} else {
					$stripearrayofkeys = $stripearrayofkeysbyenv[1];	// Live
				}
				// Force to use the correct API key
				\Stripe\Stripe::setApiKey($stripearrayofkeys['secret_key']);

				$setupintent = \Stripe\SetupIntent::retrieve($setupintentid);
				if (empty($setupintent->payment_method)) {        // Example: $setupintent->payment_method = 'pm_...'
					setEventMessages('Error: The payment_method is empty into the setupintentid', null, 'errors');
					$action='';
					$mode='registerpaymentmode';
					$error++;
				}
			}

			if (! $error) {
				$payment_method = \Stripe\PaymentMethod::retrieve($setupintent->payment_method);

				// Note: Here setupintent->customer is defined but $payment_method->customer is not yet. It will be attached later by ->attach

				// Ajout
				$companypaymentmode = new CompanyPaymentMode($db);

				$companypaymentmode->fk_soc          = $mythirdpartyaccount->id;
				$companypaymentmode->bank            = GETPOST('bank', 'alpha');
				$companypaymentmode->label           = 'Setup intent for '.$payment_method->id;
				$companypaymentmode->number          = '';
				$companypaymentmode->last_four       = $payment_method->card->last4;
				$companypaymentmode->proprio         = GETPOST('proprio', 'alpha');
				$companypaymentmode->exp_date_month  = $payment_method->card->exp_month;
				$companypaymentmode->exp_date_year   = $payment_method->card->exp_year;
				$companypaymentmode->cvn             = '';
				$companypaymentmode->datec           = $now;
				$companypaymentmode->default_rib     = 1;
				$companypaymentmode->type            = 'card';
				$companypaymentmode->country_code    = $payment_method->card->country;
				$companypaymentmode->comment         = 'Credit card entered from customer dashboard with STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION on (using SetupIntent)';
				$companypaymentmode->ipaddress       = getUserRemoteIP();

				$companypaymentmode->stripe_card_ref = $payment_method->id;
				$companypaymentmode->stripe_account  = $setupintent->customer.'@'.$stripearrayofkeys['publishable_key'];
				$companypaymentmode->status          = $servicestatusstripe;

				$companypaymentmode->card_type       = $payment_method->card->brand;
				$companypaymentmode->owner_address   = $payment_method->billing_details->address->line1;
				$companypaymentmode->approved        = ($payment_method->card->checks->cvc_check == 'pass' ? 1 : 0);
				$companypaymentmode->email           = $payment_method->billing_details->email;

				$db->begin();

				if (! $error) {
					$result = $companypaymentmode->create($user);
					if ($result < 0) {
						$error++;
						setEventMessages($companypaymentmode->error, $companypaymentmode->errors, 'errors');
						$action='createcard';     // Force chargement page création
					}

					if (! $error) {
						$stripe = new Stripe($db);
						$stripeacc = $stripe->getStripeAccount($service);								// Get Stripe OAuth connect account if it exists (no remote access to Stripe here)

						// Get the Stripe customer (should have been created already when creating the setupintent)
						// Note that we should have already the customer in $setupintent->customer
						$cu = $stripe->customerStripe($mythirdpartyaccount, $stripeacc, $servicestatusstripe, 0);
						if (! $cu) {
							$error++;
							setEventMessages($stripe->error, $stripe->errors, 'errors');
						} else {
							dol_syslog('--- Stripe customer retrieved cu = '.$cu->id);

							// Attach payment_method from SetupIntent to customer
							try {
								//$payment_method_obj = \Stripe\PaymentMethod::retrieve($payment_method->id);
								$payment_method_obj = $payment_method;

								if (empty($payment_method_obj->customer)) {
									$arrayforattach = array(
										'customer' => $cu->id,
										//'metadata' => array('dol_version'=>DOL_VERSION, 'dol_entity'=>$conf->entity, 'ipaddress'=>getUserRemoteIP())
									);
									$result = $payment_method_obj->attach($arrayforattach);

									// TODO To set this payment mode as default, you must make
									// $arrayofparam = array('invoice_settings' => array('default_payment_method' => $payment_method_obj->id));
									// $cu->update($arrayofparam);
								} elseif ($payment_method_obj->customer != $cu->id) {
									$error++;
									$errormsg = "The payment method ".$payment_method->id." is already attached to the customer ".$payment_method_obj->customer." that is not ".$cu->id;
									dol_syslog($errormsg, LOG_ERR);
								}
							} catch (Stripe\Error\InvalidRequest $e) {
								//var_dump($e);
								$error++;
								$errormsg = $e->getMessage();
								if ($errormsg != 'The payment method you provided has already been attached to a customer.') {
									dol_syslog('--- FailedToAttachPaymentMethodToCustomer Exception '.$errormsg, LOG_WARNING);
									setEventMessages($langs->trans('FailedToAttachPaymentMethodToCustomer').($errormsg?'<br>'.$errormsg:''), null, 'errors');
									$action='';
								}
							} catch (Exception $e) {
								//var_dump($e);
								$error++;
								$errormsg = $e->getMessage();
								dol_syslog('--- FailedToAttachPaymentMethodToCustomer Exception '.$errormsg, LOG_WARNING);
								setEventMessages($langs->trans('FailedToAttachPaymentMethodToCustomer').($errormsg?'<br>'.$errormsg:''), null, 'errors');
								$action='';
							}
						}
					}

					if (! $error) {
						$companypaymentmode->setAsDefault($companypaymentmode->id, 1);
						dol_syslog("--- A credit card was recorded. Now we reset the stripeaccount (to force use of default Stripe setup)", LOG_DEBUG, 0);

						$sql = 'UPDATE '.MAIN_DB_PREFIX.'societe_extrafields set stripeaccount = NULL WHERE fk_object = '.$mythirdpartyaccount->id;
						$db->query($sql);

						if ($mythirdpartyaccount->client == 2) {
							dol_syslog("--- Set status of thirdparty to prospect+client instead of only prospect", LOG_DEBUG, 0);
							$mythirdpartyaccount->set_as_client();
						}

						if (! $error) {
							$labelofevent = 'Payment mode added by '.getUserRemoteIP();
							$codeofevent = 'AC_ADD_PAYMENT';
							if ($thirdpartyhadalreadyapaymentmode > 0) {
								$labelofevent = 'Payment mode modified by '.getUserRemoteIP();
								$codeofevent = 'AC_MOD_PAYMENT';
							}

							include_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
							// Create an event
							$actioncomm = new ActionComm($db);
							$actioncomm->type_code   = 'AC_OTH_AUTO';		// Type of event ('AC_OTH', 'AC_OTH_AUTO', 'AC_XXX'...)
							$actioncomm->code        = $codeofevent;
							$actioncomm->label       = $labelofevent;
							$actioncomm->datep       = $now;
							$actioncomm->datef       = $now;
							$actioncomm->percentage  = -1;   // Not applicable
							$actioncomm->socid       = $mythirdpartyaccount->id;
							$actioncomm->authorid    = $user->id;   // User saving action
							$actioncomm->userownerid = $user->id;	// Owner of action
							$actioncomm->note_private= $labelofevent.' - Company payment mode id created or modified = '.$companypaymentmode->id;
							//$actioncomm->fk_element  = $mythirdpartyaccount->id;
							//$actioncomm->elementtype = 'thirdparty';
							$ret=$actioncomm->create($user);       // User creating action
						}
					}
				}

				// We can a commit / begin here so we are sure the payment is recorded, even if payment later fails.
				// But we prefer to have payment mode recorded only if payment is success.

				$erroronstripecharge = 0;


				// Loop on each pending invoices of the thirdparty and try to pay them with payment = remain amount of invoice.
				// Note that it may have no pending invoice yet when contract is in trial mode (running or suspended)
				if (! $error) {
					dol_syslog("--- Now we search pending invoices for thirdparty to pay them (Note that it may have no pending invoice yet when contract is in trial mode)", LOG_DEBUG, 0);

					$sellyoursaasutils = new SellYourSaasUtils($db);

					$result = $sellyoursaasutils->doTakePaymentStripeForThirdparty($service, $servicestatusstripe, $mythirdpartyaccount->id, $companypaymentmode, null, 1, 1, 1, 1);	// Include draft invoices
					if ($result != 0) {
						$error++;
						setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
						dol_syslog("--- Error when taking payment for pending invoices in mode STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION ".$sellyoursaasutils->error, LOG_DEBUG, 0);
					} else {
						dol_syslog("--- Success to take payment for pending invoices in mode STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION", LOG_DEBUG, 0);
					}

					// If some payment was really done, we force commit to be sure to validate invoices payment done by stripe, whatever is global result of doTakePaymentStripeForThirdparty
					if ($sellyoursaasutils->stripechargedone > 0) {
						dol_syslog("--- Force commit to validate payments recorded after real Stripe charges", LOG_DEBUG, 0);

						$db->commit();

						$db->begin();
					}
				}

				// Make renewals on contracts of customer
				if (! $error) {
					dol_syslog("--- Make renewals on contracts for thirdparty id=".$mythirdpartyaccount->id, LOG_DEBUG, 0);

					$sellyoursaasutils = new SellYourSaasUtils($db);

					$result = $sellyoursaasutils->doRenewalContracts($mythirdpartyaccount->id);		// A refresh is also done if renewal is done
					if ($result != 0) {
						$error++;
						setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
						dol_syslog("Failed to make renewal of contract ".$sellyoursaasutils->error, LOG_ERR);
					}
				}

				// Create a recurring invoice (+real invoice + contract renewal) if there is no recurring invoice yet
				if (! $error) {
					foreach ($listofcontractid as $contract) {
						dol_syslog("--- Create recurring invoice on contract contract_id = ".$contract->id." if it does not have yet.", LOG_DEBUG, 0);

						if (preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
							dol_syslog("--- This contract is a redirection, we discard this contract", LOG_DEBUG, 0);
							continue;							// If contract is a redirection, we discard check and creation of any recurring invoice
						}
						if ($contract->array_options['options_deployment_status'] != 'done') {
							dol_syslog("--- Deployment status is not 'done', we discard this contract", LOG_DEBUG, 0);
							continue;							// This is a not valid contract (undeployed or not yet completely deployed), so we discard this contract to avoid to create template not expected
						}
						if ($contract->total_ht == 0) {
							dol_syslog("--- Amount is null, we discard this contract", LOG_DEBUG, 0);
							continue;							// Amount is null, so we do not create recurring invoice for that. Note: This should not happen.
						}

						// Make a test to pass loop if there is already a template invoice
						$result = $contract->fetchObjectLinked();
						if ($result < 0) {
							dol_syslog("--- Error during fetchObjectLinked, we discard this contract", LOG_ERR, 0);
							continue;							// There is an error, so we discard this contract to avoid to create template twice
						}
						if (! empty($contract->linkedObjectsIds['facturerec'])) {
							$templateinvoice = reset($contract->linkedObjectsIds['facturerec']);
							if ($templateinvoice > 0) {			// There is already a template invoice, so we discard this contract to avoid to create template twice
								dol_syslog("--- There is already a recurring invoice on the contract contract_id = ".$contract->id, LOG_DEBUG, 0);
								continue;
							}
						}

						dol_syslog("--- No template invoice found linked to the contract contract_id = ".$contract->id." that is NOT null, so we refresh contract before creating template invoice + creating invoice (if template invoice date is already in past) + making contract renewal.", LOG_DEBUG, 0);

						$comment = 'Refresh contract '.$contract->ref.' after entering a payment mode on dashboard, because we need to create a template invoice (case of STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION set)';
						// First launch update of resources:
						// This update qty of contract lines + qty into linked template invoice.
						$result = $sellyoursaasutils->sellyoursaasRemoteAction('refreshmetrics', $contract, 'admin', '', '', '0', $comment);

						dol_syslog("--- No template invoice found linked to the contract contract_id = ".$contract->id.", so we create it then we create real invoice (if template invoice date is already in past) then make contract renewal.", LOG_DEBUG, 0);

						// Now create invoice draft
						$dateinvoice = $contract->array_options['options_date_endfreeperiod'];
						if ($dateinvoice < $now) $dateinvoice = $now;

						$invoice_draft = new Facture($db);
						$tmpproduct = new Product($db);

						// Create empty invoice
						if (! $error) {
							$invoice_draft->socid				= $contract->socid;
							$invoice_draft->type				= Facture::TYPE_STANDARD;
							$invoice_draft->number				= '';
							$invoice_draft->date				= $dateinvoice;

							$invoice_draft->note_private		= 'Template invoice created after adding a payment mode for card/stripe';
							$invoice_draft->mode_reglement_id	= dol_getIdFromCode($db, 'CB', 'c_paiement', 'code', 'id', 1);
							$invoice_draft->cond_reglement_id	= dol_getIdFromCode($db, 'RECEP', 'c_payment_term', 'code', 'rowid', 1);
							$invoice_draft->fk_account          =getDolGlobalInt('STRIPE_BANK_ACCOUNT_FOR_PAYMENTS');	// stripe

							$invoice_draft->fetch_thirdparty();

							$origin='contrat';
							$originid=$contract->id;

							$invoice_draft->origin = $origin;
							$invoice_draft->origin_id = $originid;

							// Possibility to add external linked objects with hooks
							$invoice_draft->linked_objects[$invoice_draft->origin] = $invoice_draft->origin_id;

							$idinvoice = $invoice_draft->create($user);      // This include class to add_object_linked() and add add_contact()
							if (! ($idinvoice > 0)) {
								setEventMessages($invoice_draft->error, $invoice_draft->errors, 'errors');
								$error++;
							}
						}

						$frequency=1;
						$frequency_unit='m';
						$discountcode = strtoupper(trim(GETPOST('discountcode', 'aZ09')));	// If a discount code was prodived on page
						/* If a discount code exists on contract level, it was used to prefill the payment page, so it is received into the GETPOST('discountcode', 'int').
						if (empty($discountcode) && ! empty($contract->array_options['options_discountcode'])) {	// If no discount code provided, but we find one on contract, we use this one
							$discountcode = $contract->array_options['options_discountcode'];
						}*/

						$discounttype = '';
						$discountval = 0;
						$validdiscountcodearray = array();
						$nbofproductapp = 0;

						// Add lines on invoice
						if (! $error) {
							// Add lines of contract to template invoice
							$srcobject = $contract;

							$lines = $srcobject->lines;
							if (empty($lines) && method_exists($srcobject, 'fetch_lines')) {
								$srcobject->fetch_lines();
								$lines = $srcobject->lines;
							}

							$date_start = false;
							$fk_parent_line=0;
							$num=count($lines);
							for ($i=0; $i<$num; $i++) {
								$label=(! empty($lines[$i]->label)?$lines[$i]->label:'');
								$desc=(! empty($lines[$i]->desc)?$lines[$i]->desc:$lines[$i]->libelle);
								if ($invoice_draft->situation_counter == 1) $lines[$i]->situation_percent =  0;

								// Positive line
								$product_type = ($lines[$i]->product_type ? $lines[$i]->product_type : 0);

								// Date start
								$date_start = false;
								if ($lines[$i]->date_debut_prevue) $date_start = $lines[$i]->date_debut_prevue;
								if ($lines[$i]->date_debut_reel) $date_start = $lines[$i]->date_debut_reel;
								if ($lines[$i]->date_start) $date_start = $lines[$i]->date_start;

								// Date end
								$date_end = false;
								if ($lines[$i]->date_fin_prevue) $date_end = $lines[$i]->date_fin_prevue;
								if ($lines[$i]->date_fin_reel) $date_end = $lines[$i]->date_fin_reel;
								if ($lines[$i]->date_end) $date_end = $lines[$i]->date_end;

								// If date start is in past, we set it to now
								$now = dol_now();
								if ($date_start < $now) {
									dol_syslog("--- Date start is in past, so we take current date as date start and update also end date of contract", LOG_DEBUG, 0);
									$tmparray = sellyoursaasGetExpirationDate($srcobject, 0);
									$duration_value = $tmparray['duration_value'];
									$duration_unit = $tmparray['duration_unit'];

									$date_start = $now;
									$date_end = dol_time_plus_duree($now, $duration_value, $duration_unit) - 1;

									// Because we update the end date planned of contract too
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
									$lines[$i]->fetch_optionals($lines[$i]->rowid);
									$array_options = $lines[$i]->array_options;
								}

								$tva_tx = $lines[$i]->tva_tx;
								if (! empty($lines[$i]->vat_src_code) && ! preg_match('/\(/', $tva_tx)) $tva_tx .= ' ('.$lines[$i]->vat_src_code.')';

								// View third's localtaxes for NOW and do not use value from origin.
								$localtax1_tx = get_localtax($tva_tx, 1, $invoice_draft->thirdparty);
								$localtax2_tx = get_localtax($tva_tx, 2, $invoice_draft->thirdparty);

								//$price_invoice_template_line = $lines[$i]->subprice * GETPOST('frequency_multiple','int');
								$price_invoice_template_line = $lines[$i]->subprice;


								// Get data from product (frequency, discount type and val)
								$tmpproduct->fetch($lines[$i]->fk_product);

								dol_syslog("--- Read frequency for product id=".$tmpproduct->id, LOG_DEBUG, 0);
								if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
									// Protection to avoid to validate contract with several 'app' products.
									$nbofproductapp++;
									if ($nbofproductapp > 1) {
										dol_syslog("--- Error: Bad definition of contract. There is more than 1 service with type 'app'", LOG_ERR);
										$error++;
										break;
									}
									$frequency = $tmpproduct->duration_value;
									$frequency_unit = $tmpproduct->duration_unit;

									if ($tmpproduct->array_options['options_register_discountcode']) {
										$tmpvaliddiscountcodearray = explode(',', $tmpproduct->array_options['options_register_discountcode']);
										foreach ($tmpvaliddiscountcodearray as $valdiscount) {
											$valdiscountarray = explode(':', $valdiscount);
											$tmpcode = strtoupper(trim($valdiscountarray[0]));
											$tmpval = str_replace('%', '', trim($valdiscountarray[1]));
											if (is_numeric($tmpval)) {
												$validdiscountcodearray[$tmpcode] = array('code'=>$tmpcode, 'type'=>'percent', 'value'=>$tmpval);
											} else {
												dol_syslog("--- Error: Bad definition of discount for product id = ".$tmpproduct->id." with value ".$tmpproduct->array_options['options_register_discountcode'], LOG_ERR);
											}
										}
										// If we entered a discountcode or get it from contract
										if (! empty($validdiscountcodearray[$discountcode])) {
											$discounttype = $validdiscountcodearray[$discountcode]['type'];
											$discountval = $validdiscountcodearray[$discountcode]['value'];
										} else {
											$discountcode = '';
										}
										//var_dump($validdiscountcodearray); var_dump($discountcode); var_dump($discounttype); var_dump($discountval); exit;
										if ($discounttype == 'percent') {
											if ($discountval > $discount) {
												$discount = $discountval;		// If discount with coupon code is higher than the one defined into contract.
											}
										}
									}
								}

								// Insert the line
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
							}
						}

						// Now we convert invoice into a template
						if (! $error) {
							//var_dump($invoice_draft->lines);
							//var_dump(dol_print_date($date_start,'dayhour'));
							//exit;

							//$frequency=1;
							//$frequency_unit='m';
							$frequency = (! empty($frequency) ? $frequency : 1);	// read frequency of product app
							$frequency_unit = (! empty($frequency_unit) ? $frequency_unit :'m');	// read frequency_unit of product app
							$tmp=dol_getdate($date_start?$date_start:$now);
							$reyear=$tmp['year'];
							$remonth=$tmp['mon'];
							$reday=$tmp['mday'];
							$rehour=$tmp['hours'];
							$remin=$tmp['minutes'];
							$nb_gen_max=0;
							//print dol_print_date($date_start,'dayhour');
							//var_dump($remonth);

							$invoice_rec = new FactureRec($db);

							$invoice_rec->titre = 'Template invoice for '.$contract->ref.' '.$contract->ref_customer;
							$invoice_rec->title = 'Template invoice for '.$contract->ref.' '.$contract->ref_customer;
							$invoice_rec->note_private = $contract->note_private;
							//$invoice_rec->note_public  = dol_concatdesc($contract->note_public, '__(Period)__ : __INVOICE_DATE_NEXT_INVOICE_BEFORE_GEN__ - __INVOICE_DATE_NEXT_INVOICE_AFTER_GEN__');
							$invoice_rec->note_public  = $contract->note_public;
							$invoice_rec->mode_reglement_id = $invoice_draft->mode_reglement_id;
							$invoice_rec->cond_reglement_id = $invoice_draft->cond_reglement_id;

							$invoice_rec->usenewprice = 0;

							$invoice_rec->frequency = $frequency;
							$invoice_rec->unit_frequency = $frequency_unit;
							$invoice_rec->nb_gen_max = $nb_gen_max;
							$invoice_rec->auto_validate = 0;

							$invoice_rec->fk_project = 0;

							$date_next_execution = dol_mktime($rehour, $remin, 0, $remonth, $reday, $reyear);
							$invoice_rec->date_when = $date_next_execution;

							// Add discount into the template invoice (it was already added into lines)
							if ($discountcode) {
								$invoice_rec->array_options['options_discountcode'] = $discountcode;
							}

							// Get first contract linked to invoice used to generate template
							if ($invoice_draft->id > 0) {
								$srcObject = $invoice_draft;

								$srcObject->fetchObjectLinked();

								if (! empty($srcObject->linkedObjectsIds['contrat'])) {
									$contractidid = reset($srcObject->linkedObjectsIds['contrat']);

									$invoice_rec->origin = 'contrat';
									$invoice_rec->origin_id = $contractidid;
									$invoice_rec->linked_objects[$invoice_draft->origin] = $invoice_draft->origin_id;
								}
							}

							$oldinvoice = new Facture($db);
							$oldinvoice->fetch($invoice_draft->id);

							$invoicerecid = $invoice_rec->create($user, $oldinvoice->id);
							if ($invoicerecid > 0) {
								$sql = 'UPDATE '.MAIN_DB_PREFIX.'facturedet_rec SET date_start_fill = 1, date_end_fill = 1 WHERE fk_facture = '.$invoice_rec->id;
								$result = $db->query($sql);
								if (! $error && $result < 0) {
									$error++;
									setEventMessages($db->lasterror(), null, 'errors');
								}

								$result=$oldinvoice->delete($user, 1);
								if (! $error && $result < 0) {
									$error++;
									setEventMessages($oldinvoice->error, $oldinvoice->errors, 'errors');
								}
							} else {
								$error++;
								setEventMessages($invoice_rec->error, $invoice_rec->errors, 'errors');
							}

							// A template invoice was just created, we run generation of invoice if template invoice date is already in past
							if (! $error) {
								dol_syslog("--- A template invoice was generated with id ".$invoicerecid.", now we run createRecurringInvoices to build real invoice", LOG_DEBUG, 0);
								$facturerec = new FactureRec($db);

								$savperm1 = $user->rights->facture->creer;
								$savperm2 = $user->rights->facture->invoice_advance->validate;

								$user->rights->facture->creer = 1;
								if (empty($user->rights->facture->invoice_advance)) $user->rights->facture->invoice_advance=new stdClass();
								$user->rights->facture->invoice_advance->validate = 1;

								$result = $facturerec->createRecurringInvoices($invoicerecid, 1);		// Generate real invoice from pending recurring invoices
								if ($result != 0) {
									$error++;
									setEventMessages($facturerec->error, $facturerec->errors, 'errors');
								}

								$user->rights->facture->creer = $savperm1;
								$user->rights->facture->invoice_advance->validate = $savperm2;
							}
							if (! $error) {
								dol_syslog("--- Now we try to take payment for thirdpartyid = ".$mythirdpartyaccount->id, LOG_DEBUG, 0);	// Unsuspend if it was suspended (done by trigger BILL_CANCEL or BILL_PAYED).

								$sellyoursaasutils = new SellYourSaasUtils($db);

								$result = $sellyoursaasutils->doTakePaymentStripeForThirdparty($service, $servicestatusstripe, $mythirdpartyaccount->id, $companypaymentmode, null, 0, 1, 0, 1);
								if ($result != 0) {
									$error++;
									setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
									dol_syslog("--- Error when taking payment in mode STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION ".$sellyoursaasutils->error, LOG_DEBUG, 0);
								} else {
									dol_syslog("--- Success to take payment in mode STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION", LOG_DEBUG, 0);
								}

								// If some payment was really done, we force commit to be sure to validate invoices payment done by stripe, whatever is global result of doTakePaymentStripeForThirdparty
								if ($sellyoursaasutils->stripechargedone > 0) {
									dol_syslog("--- Force commit to validate payments recorded after real Stripe charges", LOG_DEBUG, 0);

									$rescommit = $db->commit();
									dol_syslog("--- rescommit = ".$rescommit." transaction_opened is now ".$db->transaction_opened, LOG_DEBUG, 0);

									$db->begin();
								}
							}

							// Make renewals on contracts of customer
							if (! $error) {
								dol_syslog("--- Now we make renewal of contracts for thirdpartyid=".$mythirdpartyaccount->id." if payments were ok and contract are not unsuspended", LOG_DEBUG, 0);

								$sellyoursaasutils = new SellYourSaasUtils($db);

								$result = $sellyoursaasutils->doRenewalContracts($mythirdpartyaccount->id);		// A refresh is also done if renewal is done
								if ($result != 0) {
									$error++;
									setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
								}
							}
						}
					}
				}

				if (! $error) {
					// Payment mode successfully recorded
					setEventMessages($langs->trans("PaymentModeRecorded"), null, 'mesgs');

					$db->commit();

					$url=$_SERVER["PHP_SELF"];
					if ($backurl) $url=$backurl;

					if ($thirdpartyhadalreadyapaymentmode > 0) {
						dol_syslog("PaymentModeHasBeenModified");

						// Set flag 'showconversiontracker' in session to output the js tracker by llxFooter function of customer dashboard.
						$_SESSION['showconversiontracker']='paymentmodified';

						$url.=(preg_match('/\?/', $url) ? '&' : '?' ).'paymentmodified=1';

						// Send to DataDog (metric + event)
						if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED)) {
							try {
								dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

								$arrayconfig=array();
								if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY)) {
									$arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
								}

								$statsd = new DataDog\DogStatsd($arrayconfig);

								$arraytags=null;
								$statsd->increment('sellyoursaas.paymentmodemodified', 1, $arraytags);
							} catch (Exception $e) {
							}
						}
					} else {
						dol_syslog("PaymentModeHasBeenAdded");

						// Set flag 'showconversiontracker' in session to output the js tracker by llxFooter function of customer dashboard.
						$_SESSION['showconversiontracker']='paymentrecorded';

						$url.=(preg_match('/\?/', $url) ? '&' : '?' ).'paymentrecorded=1';

						// Send to DataDog (metric + event)
						if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED)) {
							try {
								dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

								$arrayconfig=array();
								if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY)) {
									$arrayconfig=array('apiKey' => $conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
								}

								$statsd = new DataDog\DogStatsd($arrayconfig);

								$arraytags=null;
								$statsd->increment('sellyoursaas.paymentmodeadded', 1, $arraytags);

								global $dolibarr_main_url_root;
								$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT, '/').'$/i', '', trim($dolibarr_main_url_root));
								$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
								//$urlwithroot=DOL_MAIN_URL_ROOT;					// This is to use same domain name than current

								$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;
								if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
									&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
									$newnamekey = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
									if (! empty($conf->global->$newnamekey)) $sellyoursaasname = $conf->global->$newnamekey;
								}

								$titleofevent = dol_trunc($sellyoursaasname.' - '.gethostname().' - '.$langscompany->trans("NewCustomer").': '.$mythirdpartyaccount->name, 90);
								$messageofevent = ' - '.$langscompany->trans("PaymentModeAddedFrom").' '.getUserRemoteIP()."\n";
								$messageofevent.= $langscompany->trans("Customer").': '.$mythirdpartyaccount->name.' ['.$langscompany->trans("SeeOnBackoffice").']('.$urlwithouturlroot.'/societe/card.php?socid='.$mythirdpartyaccount->id.')'."\n".$langscompany->trans("SourceURLOfEvent").": ".$url;

								// See https://docs.datadoghq.com/api/?lang=python#post-an-event
								$statsd->event($titleofevent,
									array(
										'text'       =>  "%%% \n ".$titleofevent.$messageofevent." \n %%%",      // Markdown text
										'alert_type' => 'info',
										'source_type_name' => 'API',
										'host'       => gethostname()
									)
								);
							} catch (Exception $e) {
							}
						}
					}

					header('Location: '.$url);
					exit;
				} else {
					$db->rollback();

					$action='';
					$mode='registerpaymentmode';
				}
			}
		} else { // createpayment with old method (STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION is empty)
			$stripeToken = GETPOST("stripeToken", 'alpha');
			$label = 'Card '.dol_print_date($now, 'dayhourrfc');

			if (! $stripeToken) {
				dol_syslog("--- ErrorTokenWasNotProvidedByPreviousPage", LOG_ERR);
				setEventMessages($langs->trans("ErrorTokenWasNotProvidedByPreviousPage"), null, 'errors');
				$action='';
				$mode='registerpaymentmode';
				$error++;
			}

			if (! $error) {
				$thirdpartyhadalreadyapaymentmode = sellyoursaasThirdpartyHasPaymentMode($mythirdpartyaccount->id);    // Check if customer has already a payment mode or not

				// Add payment mode
				$companypaymentmode = new CompanyPaymentMode($db);

				$companypaymentmode->fk_soc          = $mythirdpartyaccount->id;
				$companypaymentmode->bank            = GETPOST('bank', 'alpha');
				$companypaymentmode->label           = $label;
				$companypaymentmode->number          = GETPOST('cardnumber', 'alpha');
				$companypaymentmode->last_four       = substr(GETPOST('cardnumber', 'alpha'), -4);
				$companypaymentmode->proprio         = GETPOST('proprio', 'alpha');
				$companypaymentmode->exp_date_month  = GETPOST('exp_date_month', 'int');
				$companypaymentmode->exp_date_year   = GETPOST('exp_date_year', 'int');
				$companypaymentmode->cvn             = GETPOST('cvn', 'alpha');
				$companypaymentmode->datec           = $now;
				$companypaymentmode->default_rib     = 1;
				$companypaymentmode->type            = 'card';
				$companypaymentmode->country_code    = $mythirdpartyaccount->country_code;
				$companypaymentmode->status          = $servicestatusstripe;
				$companypaymentmode->comment         = 'Credit card created from customer dashboard';     // TODO Fields not declared in companypaymentmode
				$companypaymentmode->ipaddress       = getUserRemoteIP();                                 // TODO Fields not declared in companypaymentmode
				// $companypaymentmode->stripe_card_ref is filled later
				// $companypaymentmode->stripe_account is filled later

				$db->begin();

				if (! $error) {
					$result = $companypaymentmode->create($user);
					if ($result < 0) {
						$error++;
						setEventMessages($companypaymentmode->error, $companypaymentmode->errors, 'errors');
						$action='createcard';     // Force chargement page création
					}

					if (! empty($conf->stripe->enabled) && class_exists('Stripe')) {
						$stripe = new Stripe($db);
						$stripeacc = $stripe->getStripeAccount($service);								// Get Stripe OAuth connect account if it exists (no remote access to Stripe here)

						// Create card on Stripe
						if (! $error) {
							// Get the Stripe customer and create if not linked (use default Stripe setup)
							$cu = $stripe->customerStripe($mythirdpartyaccount, $stripeacc, $servicestatusstripe, 1);
							if (! $cu) {
								$error++;
								setEventMessages($stripe->error, $stripe->errors, 'errors');
								dol_syslog('Error: Failed to get Stripe customer remote object (cu), servicestatusstripe = '.$servicestatusstripe.', stripe->error = '.$stripe->error, LOG_ERR);
							} else {
								dol_syslog('--- Stripe customer retrieved or created, now we try to create card');

								$metadata = array(
									'dol_version'=>DOL_VERSION,
									'dol_entity'=>$conf->entity,
									'ipaddress'=>getUserRemoteIP()	// ip of visitor used to create card
								);
								//if (! empty($dol_id))        			$metadata["dol_id"] = $dol_id;
								//if (! empty($dol_type))      			$metadata["dol_type"] = $dol_type;
								if (! empty($mythirdpartyaccount->id)) 	$metadata["dol_thirdparty_id"] = $mythirdpartyaccount->id;

								// Create Stripe card from Token
								try {
									$card = $cu->sources->create(array("source" => $stripeToken, "metadata" => $metadata));
								} catch (\Stripe\Error\Card $e) {
									// Since it's a decline, Stripe_CardError will be caught
									$body = $e->getJsonBody();
									$err  = $body['error'];

									$stripefailurecode = $err['code'];
									$stripefailuremessage = $err['message'];

									$error++;
									$errormsg = 'Code: '.$stripefailurecode.', '.$langs->trans("Message").': '.$stripefailuremessage;
									dol_syslog('--- FailedToCreateCardRecord Strip Error Card '.$errormsg, LOG_WARNING);
									setEventMessages($langs->trans('FailedToCreateCardRecord').($errormsg?'<br>'.$errormsg:''), null, 'errors');
									$action='';

									dol_syslog('--- FailedToCreateCardRecord '.json_encode($err), LOG_WARNING);
								} catch (Exception $e) {
									$error++;
									$errormsg = $e->getMessage();
									dol_syslog('--- FailedToCreateCardRecord Exception '.$e->getCode().' '.$errormsg, LOG_WARNING);
									setEventMessages($langs->trans('FailedToCreateCardRecord').($errormsg?'<br>'.$errormsg:''), null, 'errors');
									$action='';
								}

								if (! $error) {
									if (empty($card)) {
										$error++;
										dol_syslog('--- FailedToCreateCardRecord', LOG_WARNING, 0);
										setEventMessages($langs->trans('FailedToCreateCardRecord', ''), null, 'errors');
										$action='';
									} else {
										global $stripearrayofkeysbyenv;
										$stripearrayofkeys = $stripearrayofkeysbyenv[$servicestatusstripe];

										$sql = "UPDATE " . MAIN_DB_PREFIX . "societe_rib";
										$sql.= " SET stripe_card_ref = '".$db->escape($card->id)."',";
										$sql.= " stripe_account = '".$db->escape($cu->id.'@'.$stripearrayofkeys['publishable_key'])."',";
										$sql.= " status = ".((int) $servicestatusstripe).",";
										$sql.= " card_type = '".$db->escape($card->brand)."',";
										$sql.= " country_code = '".$db->escape($card->country)."',";
										$sql.= " exp_date_month = '".$db->escape($card->exp_month)."',";
										$sql.= " exp_date_year = '".$db->escape($card->exp_year)."',";
										$sql.= " last_four = '".$db->escape($card->last4)."',";
										$sql.= " ipaddress = '".$db->escape(getUserRemoteIP())."',";
										$sql.= " approved = ".($card->cvc_check == 'pass' ? 1 : 0);
										$sql.= " WHERE rowid = " . $companypaymentmode->id;
										$sql.= " AND type = 'card'";
										$resql = $db->query($sql);
										if (! $resql) {
											dol_syslog("Failed to update societe_rib ".$db->lasterror(), LOG_ERR);
											setEventMessages($db->lasterror(), null, 'errors');
										}

										$stripecard = $card->id;
									}
								}
							}
						}
					}

					if (! $error) {
						$companypaymentmode->setAsDefault($companypaymentmode->id, 1);
						dol_syslog("--- A credit card was recorded. Now we reset the stripeaccount (to force use of default Stripe setup)", LOG_DEBUG, 0);

						$sql = 'UPDATE '.MAIN_DB_PREFIX.'societe_extrafields set stripeaccount = NULL WHERE fk_object = '.$mythirdpartyaccount->id;
						$db->query($sql);

						if ($mythirdpartyaccount->client == 2) {
							dol_syslog("--- Set status of thirdparty to prospect+client instead of only prospect", LOG_DEBUG, 0);
							$mythirdpartyaccount->set_as_client();
						}

						if (! $error) {
							$labelofevent = 'Payment mode added by '.getUserRemoteIP();
							$codeofevent = 'AC_ADD_PAYMENT';
							if ($thirdpartyhadalreadyapaymentmode > 0) {
								$labelofevent = 'Payment mode modified by '.getUserRemoteIP();
								$codeofevent = 'AC_MOD_PAYMENT';
							}

							include_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
							// Create an event
							$actioncomm = new ActionComm($db);
							$actioncomm->type_code   = 'AC_OTH_AUTO';		// Type of event ('AC_OTH', 'AC_OTH_AUTO', 'AC_XXX'...)
							$actioncomm->code        = $codeofevent;
							$actioncomm->label       = $labelofevent;
							$actioncomm->datep       = $now;
							$actioncomm->datef       = $now;
							$actioncomm->percentage  = -1;   // Not applicable
							$actioncomm->socid       = $mythirdpartyaccount->id;
							$actioncomm->authorid    = $user->id;   // User saving action
							$actioncomm->userownerid = $user->id;	// Owner of action
							$actioncomm->note_private= $labelofevent.' - Company payment mode id created or modified = '.$companypaymentmode->id;
							//$actioncomm->fk_element  = $mythirdpartyaccount->id;
							//$actioncomm->elementtype = 'thirdparty';
							$ret=$actioncomm->create($user);       // User creating action
						}
					}
				}

				// We can a commit / begin here so we are sure the payment is recorded, even if payment later fails.
				// But we prefer to have payment mode recorded only if payment is success.

				$erroronstripecharge = 0;


				// Loop on each pending invoices of the thirdparty and try to pay them with payment = remain amount of invoice.
				// Note that it may have no pending invoice yet when contract is in trial mode (running or suspended)
				if (! $error) {
					dol_syslog("--- Now we search pending invoices for thirdparty to pay them (Note that it may have no pending invoice yet when contract is in trial mode)", LOG_DEBUG, 0);

					$sellyoursaasutils = new SellYourSaasUtils($db);

					$result = $sellyoursaasutils->doTakePaymentStripeForThirdparty($service, $servicestatusstripe, $mythirdpartyaccount->id, $companypaymentmode, null, 1, 1, 1, 1);	// Include draft invoices
					if ($result != 0) {
						$error++;
						setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
						dol_syslog("--- Error when taking payment ".$sellyoursaasutils->error, LOG_DEBUG, 0);
					} else {
						dol_syslog("--- Success to take payment", LOG_DEBUG, 0);
					}

					// If some payment was really done, we force commit to be sure to validate invoices payment done by stripe, whatever is global result of doTakePaymentStripeForThirdparty
					if ($sellyoursaasutils->stripechargedone > 0) {
						dol_syslog("--- Force commit to validate payments recorded after real Stripe charges", LOG_DEBUG, 0);

						$rescommit = $db->commit();
						dol_syslog("--- rescommit = ".$rescommit." transaction_opened is now ".$db->transaction_opened, LOG_DEBUG, 0);

						$db->begin();
					}
				}

				// Make renewals on contracts of customer
				if (! $error) {
					dol_syslog("--- Make renewals on crontacts for thirdparty id=".$mythirdpartyaccount->id, LOG_DEBUG, 0);

					$sellyoursaasutils = new SellYourSaasUtils($db);

					$result = $sellyoursaasutils->doRenewalContracts($mythirdpartyaccount->id);		// A refresh is also done if renewal is done
					if ($result != 0) {
						$error++;
						setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
						dol_syslog("Failed to make renewal of contract ".$sellyoursaasutils->error, LOG_ERR);
					}
				}

				// Create a recurring invoice (+real invoice + contract renewal) if there is no reccuring invoice yet
				if (! $error) {
					foreach ($listofcontractid as $contract) {
						dol_syslog("--- Create recurring invoice on contract contract_id = ".$contract->id." if it does not have yet.", LOG_DEBUG, 0);

						if ($contract->array_options['options_deployment_status'] != 'done') {
							dol_syslog("--- Deployment status is not 'done', we discard this contract", LOG_DEBUG, 0);
							continue;							// This is a not valid contract (undeployed or not yet completely deployed), so we discard this contract to avoid to create template not expected
						}
						if ($contract->total_ht == 0) {
							dol_syslog("--- Amount is null, we discard this contract", LOG_DEBUG, 0);
							continue;							// Amount is null, so we do not create recurring invoice for that. Note: This should not happen.
						}

						// Make a test to pass loop if there is already a template invoice
						$result = $contract->fetchObjectLinked();
						if ($result < 0) {
							continue;							// There is an error, so we discard this contract to avoid to create template twice
						}
						if (! empty($contract->linkedObjectsIds['facturerec'])) {
							$templateinvoice = reset($contract->linkedObjectsIds['facturerec']);
							if ($templateinvoice > 0) {			// There is already a template invoice, so we discard this contract to avoid to create template twice
								dol_syslog("--- There is already a recurring invoice on this contract.", LOG_DEBUG, 0);
								continue;
							}
						}

						dol_syslog("--- No template invoice found linked to the contract contract_id = ".$contract->id." that is NOT null, so we refresh contract before creating template invoice + creating invoice (if template invoice date is already in past) + making contract renewal.", LOG_DEBUG, 0);

						$comment = 'Refresh contract '.$contract->ref.' after entering a payment mode on dashboard, because we need to create a template invoice (old case when STRIPE_USE_INTENT_WITH_AUTOMATIC_CONFIRMATION is not set)';
						// First launch update of resources:
						// This update qty of contract lines + qty into linked template invoice.
						$result = $sellyoursaasutils->sellyoursaasRemoteAction('refreshmetrics', $contract, 'admin', '', '', '0', $comment);

						dol_syslog("--- No template invoice found linked to the contract contract_id = ".$contract->id.", so we create it then we create real invoice (if template invoice date is already in past) then make contract renewal.", LOG_DEBUG, 0);

						// Now create invoice draft
						$dateinvoice = $contract->array_options['options_date_endfreeperiod'];
						if ($dateinvoice < $now) $dateinvoice = $now;

						$invoice_draft = new Facture($db);
						$tmpproduct = new Product($db);

						// Create empty invoice
						if (! $error) {
							$invoice_draft->socid				= $contract->socid;
							$invoice_draft->type				= Facture::TYPE_STANDARD;
							$invoice_draft->number				= '';
							$invoice_draft->date				= $dateinvoice;

							$invoice_draft->note_private		= 'Template invoice created after adding a payment mode for card/stripe';
							$invoice_draft->mode_reglement_id	= dol_getIdFromCode($db, 'CB', 'c_paiement', 'code', 'id', 1);
							$invoice_draft->cond_reglement_id	= dol_getIdFromCode($db, 'RECEP', 'c_payment_term', 'code', 'rowid', 1);
							$invoice_draft->fk_account          = getDolGlobalInt('STRIPE_BANK_ACCOUNT_FOR_PAYMENTS');	// stripe

							$invoice_draft->fetch_thirdparty();

							$origin='contrat';
							$originid=$contract->id;

							$invoice_draft->origin = $origin;
							$invoice_draft->origin_id = $originid;

							// Possibility to add external linked objects with hooks
							$invoice_draft->linked_objects[$invoice_draft->origin] = $invoice_draft->origin_id;

							$idinvoice = $invoice_draft->create($user);      // This include class to add_object_linked() and add add_contact()
							if (! ($idinvoice > 0)) {
								setEventMessages($invoice_draft->error, $invoice_draft->errors, 'errors');
								$error++;
							}
						}
						// Add lines on invoice
						if (! $error) {
							// Add lines of contract to template invoice
							$srcobject = $contract;

							$lines = $srcobject->lines;
							if (empty($lines) && method_exists($srcobject, 'fetch_lines')) {
								$srcobject->fetch_lines();
								$lines = $srcobject->lines;
							}

							$frequency=1;
							$frequency_unit='m';

							$date_start = false;
							$fk_parent_line=0;
							$num=count($lines);
							for ($i=0; $i<$num; $i++) {
								$label=(! empty($lines[$i]->label)?$lines[$i]->label:'');
								$desc=(! empty($lines[$i]->desc)?$lines[$i]->desc:$lines[$i]->libelle);
								if ($invoice_draft->situation_counter == 1) $lines[$i]->situation_percent =  0;

								// Positive line
								$product_type = ($lines[$i]->product_type ? $lines[$i]->product_type : 0);

								// Date start
								$date_start = false;
								if ($lines[$i]->date_debut_prevue)
									$date_start = $lines[$i]->date_debut_prevue;
								if ($lines[$i]->date_debut_reel)
									$date_start = $lines[$i]->date_debut_reel;
								if ($lines[$i]->date_start)
									$date_start = $lines[$i]->date_start;

								// Date end
								$date_end = false;
								if ($lines[$i]->date_fin_prevue)
									$date_end = $lines[$i]->date_fin_prevue;
								if ($lines[$i]->date_fin_reel)
									$date_end = $lines[$i]->date_fin_reel;
								if ($lines[$i]->date_end)
									$date_end = $lines[$i]->date_end;

								// If date start is in past, we set it to now
								$now = dol_now();
								if ($date_start < $now) {
									dol_syslog("--- Date start is in past, so we take current date as date start and update also end date of contract", LOG_DEBUG, 0);
									$tmparray = sellyoursaasGetExpirationDate($srcobject, 0);
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
									$lines[$i]->fetch_optionals($lines[$i]->rowid);
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
								if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
									$frequency = $tmpproduct->duration_value;
									$frequency_unit = $tmpproduct->duration_unit;
								}
							}
						}

						// Now we convert invoice into a template
						if (! $error) {
							//var_dump($invoice_draft->lines);
							//var_dump(dol_print_date($date_start,'dayhour'));
							//exit;

							//$frequency=1;
							//$frequency_unit='m';
							$frequency = (! empty($frequency) ? $frequency : 1);	// read frequency of product app
							$frequency_unit = (! empty($frequency_unit) ? $frequency_unit :'m');	// read frequency_unit of product app
							$tmp=dol_getdate($date_start?$date_start:$now);
							$reyear=$tmp['year'];
							$remonth=$tmp['mon'];
							$reday=$tmp['mday'];
							$rehour=$tmp['hours'];
							$remin=$tmp['minutes'];
							$nb_gen_max=0;
							//print dol_print_date($date_start,'dayhour');
							//var_dump($remonth);

							$invoice_rec = new FactureRec($db);

							$invoice_rec->title = 'Template invoice for '.$contract->ref.' '.$contract->ref_customer;
							$invoice_rec->titre = $invoice_rec->title;		// for backward compatibility

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
							if ($invoice_draft->id > 0) {
								$srcObject = $invoice_draft;

								$srcObject->fetchObjectLinked();

								if (! empty($srcObject->linkedObjectsIds['contrat'])) {
									$contractidid = reset($srcObject->linkedObjectsIds['contrat']);

									$invoice_rec->origin = 'contrat';
									$invoice_rec->origin_id = $contractidid;
									$invoice_rec->linked_objects[$invoice_draft->origin] = $invoice_draft->origin_id;
								}
							}

							$oldinvoice = new Facture($db);
							$oldinvoice->fetch($invoice_draft->id);

							$invoicerecid = $invoice_rec->create($user, $oldinvoice->id);
							if ($invoicerecid > 0) {
								$sql = 'UPDATE '.MAIN_DB_PREFIX.'facturedet_rec SET date_start_fill = 1, date_end_fill = 1 WHERE fk_facture = '.$invoice_rec->id;
								$result = $db->query($sql);
								if (! $error && $result < 0) {
									$error++;
									setEventMessages($db->lasterror(), null, 'errors');
								}

								$result=$oldinvoice->delete($user, 1);
								if (! $error && $result < 0) {
									$error++;
									setEventMessages($oldinvoice->error, $oldinvoice->errors, 'errors');
								}
							} else {
								$error++;
								setEventMessages($invoice_rec->error, $invoice_rec->errors, 'errors');
							}

							// A template invoice was just created, we run generation of invoice if template invoice date is already in past
							if (! $error) {
								dol_syslog("--- A template invoice was generated with id ".$invoicerecid.", now we run createRecurringInvoices to build real invoice", LOG_DEBUG, 0);
								$facturerec = new FactureRec($db);

								$savperm1 = $user->rights->facture->creer;
								$savperm2 = $user->rights->facture->invoice_advance->validate;

								$user->rights->facture->creer = 1;
								if (empty($user->rights->facture->invoice_advance)) $user->rights->facture->invoice_advance=new stdClass();
								$user->rights->facture->invoice_advance->validate = 1;

								$result = $facturerec->createRecurringInvoices($invoicerecid, 1);		// Generate real invoice from pending recurring invoices
								if ($result != 0) {
									$error++;
									setEventMessages($facturerec->error, $facturerec->errors, 'errors');
								}

								$user->rights->facture->creer = $savperm1;
								$user->rights->facture->invoice_advance->validate = $savperm2;
							}
							if (! $error) {
								dol_syslog("--- Now we try to take payment for thirdpartyid = ".$mythirdpartyaccount->id, LOG_DEBUG, 0);	// Unsuspend if it was suspended (done by trigger BILL_CANCEL or BILL_PAYED).

								$sellyoursaasutils = new SellYourSaasUtils($db);

								$result = $sellyoursaasutils->doTakePaymentStripeForThirdparty($service, $servicestatusstripe, $mythirdpartyaccount->id, $companypaymentmode, null, 0, 1, 0, 1);
								if ($result != 0) {
									$error++;
									setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
									dol_syslog("--- Error when taking payment ".$sellyoursaasutils->error, LOG_DEBUG, 0);
								} else {
									dol_syslog("--- Success to take payment", LOG_DEBUG, 0);
								}

								// If some payment was really done, we force commit to be sure to validate invoices payment done by stripe, whatever is global result of doTakePaymentStripeForThirdparty
								if ($sellyoursaasutils->stripechargedone > 0) {
									dol_syslog("--- Force commit to validate payments recorded after real Stripe charges", LOG_DEBUG, 0);

									$rescommit = $db->commit();
									dol_syslog("--- rescommit = ".$rescommit." transaction_opened is now ".$db->transaction_opened, LOG_DEBUG, 0);

									$db->begin();
								}
							}

							// Make renewals on contracts of customer
							if (! $error) {
								dol_syslog("--- Now we make renewal of contracts for thirdpartyid=".$mythirdpartyaccount->id." if payments were ok and contract are not unsuspended", LOG_DEBUG, 0);

								$sellyoursaasutils = new SellYourSaasUtils($db);

								$result = $sellyoursaasutils->doRenewalContracts($mythirdpartyaccount->id);		// A refresh is also done if renewal is done
								if ($result != 0) {
									$error++;
									setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
								}
							}
						}
					}
				}

				if (! $error) {
					// Payment mode successfully recorded
					setEventMessages($langs->trans("PaymentModeRecorded"), null, 'mesgs');

					$db->commit();

					$url=$_SERVER["PHP_SELF"];
					if ($backurl) $url=$backurl;

					if ($thirdpartyhadalreadyapaymentmode > 0) {
						// Set flag 'showconversiontracker' in session to output the js tracker by llxFooter function of customer dashboard.
						$_SESSION['showconversiontracker']='paymentmodified';

						$url.=(preg_match('/\?/', $url) ? '&' : '?' ).'paymentmodified=1';

						// Send to DataDog (metric + event)
						if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED)) {
							try {
								dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

								$arrayconfig=array();
								if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY)) {
									$arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
								}

								$statsd = new DataDog\DogStatsd($arrayconfig);

								$arraytags=null;
								$statsd->increment('sellyoursaas.paymentmodemodified', 1, $arraytags);
							} catch (Exception $e) {
							}
						}
					} else {
						// Set flag 'showconversiontracker' in session to output the js tracker by llxFooter function of customer dashboard.
						$_SESSION['showconversiontracker']='paymentrecorded';

						$url.=(preg_match('/\?/', $url) ? '&' : '?' ).'paymentrecorded=1';

						// Send to DataDog (metric + event)
						if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED)) {
							try {
								dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

								$arrayconfig=array();
								if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY)) {
									$arrayconfig=array('apiKey' => $conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
								}

								$statsd = new DataDog\DogStatsd($arrayconfig);

								$arraytags=null;
								$statsd->increment('sellyoursaas.paymentmodeadded', 1, $arraytags);

								global $dolibarr_main_url_root;
								$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT, '/').'$/i', '', trim($dolibarr_main_url_root));
								$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
								//$urlwithroot=DOL_MAIN_URL_ROOT;					// This is to use same domain name than current

								$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;
								if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
									&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
									$newnamekey = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
									if (! empty($conf->global->$newnamekey)) $sellyoursaasname = $conf->global->$newnamekey;
								}

								$titleofevent = dol_trunc($sellyoursaasname.' - '.gethostname().' - '.$langscompany->trans("NewCustomer").': '.$mythirdpartyaccount->name, 90);
								$messageofevent = ' - '.$langscompany->trans("PaymentModeAddedFrom").' '.getUserRemoteIP()."\n";
								$messageofevent.= $langscompany->trans("Customer").': '.$mythirdpartyaccount->name.' ['.$langscompany->trans("SeeOnBackoffice").']('.$urlwithouturlroot.'/societe/card.php?socid='.$mythirdpartyaccount->id.')'."\n".$langscompany->trans("SourceURLOfEvent").": ".$url;

								// See https://docs.datadoghq.com/api/?lang=python#post-an-event
								$statsd->event($titleofevent,
									array(
										'text'       =>  "%%% \n ".$titleofevent.$messageofevent." \n %%%",      // Markdown text
										'alert_type' => 'info',
										'source_type_name' => 'API',
										'host'       => gethostname()
										)
									);
							} catch (Exception $e) {
							}
						}
					}

					header('Location: '.$url);
					exit;
				} else {
					$db->rollback();

					$action='';
					$mode='registerpaymentmode';
				}
			}
		}
	}
} elseif ($action == 'undeploy' || $action == 'undeployconfirmed') {
	$db->begin();

	$contract=new Contrat($db);
	$contract->fetch(GETPOST('contractid', 'int'));					// This load also lines
	$contract->fetch_thirdparty();

	if ($contract->socid != $mythirdpartyaccount->id) {
		setEventMessages($langs->trans("ErrorYouDontOwnTheInstanceYouTryToDelete", $contract->ref_customer), null, 'errors');
		$error++;
	}

	if (! $error && $action == 'undeploy') {
		$urlofinstancetodestroy = preg_replace('/^https:\/\//i', '', trim(GETPOST('urlofinstancetodestroy', 'alpha')));
		if (empty($urlofinstancetodestroy)) {
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("NameOfInstanceToDestroy")), null, 'errors');
			$error++;
		} elseif ($urlofinstancetodestroy != $contract->ref_customer) {
			setEventMessages($langs->trans("ErrorNameOfInstanceDoesNotMatch", $urlofinstancetodestroy, $contract->ref_customer), null, 'errors');
			$error++;
		}
	}

	if (! $error) {
		$stringtohash = $conf->global->SELLYOURSAAS_KEYFORHASH.$contract->thirdparty->email.dol_print_date($now, 'dayrfc');

		$hash = dol_hash($stringtohash);
		dol_syslog("Hash generated to allow immediate deletion: ".$hash);

		// Send confirmation email
		if ($action == 'undeploy') {
			$object = $contract;

			if (getDolGlobalInt('SELLYOURSAAS_ASK_DESTROY_REASON') && empty($reasonundeploy)) {
				// If reason was not provided
				//setEventMessages($langs->trans("AReasonForUndeploymentIsRequired"), null, 'errors');
				$errortoshowinconfirm = $langs->trans('AReasonForUndeploymentIsRequired');
				$error++;
				$action = 'confirmundeploy';
			}

			// SAME CODE THAN INTO ACTION_SELLYOURSAAS.CLASS.PHP

			// Disable template invoice
			$object->fetchObjectLinked();

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
							setEventMessages($invoice->error, $invoice->errors, 'errors');
						}
					}
				}
			}

			$comment = 'Services for '.$contract->ref.' closed after an undeploy request from Customer dashboard. Status of instance when request has been received was '.$contract->array_options['options_deployment_status'];

			if (! $error) {
				$sellyoursaasutils = new SellYourSaasUtils($db);
				$result = $sellyoursaasutils->sellyoursaasRemoteAction('suspend', $contract, 'admin', '', '', 0, $comment);
				if ($result <= 0) {
					$error++;
					setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
				}
			}

			// Insert extrafields of uninstall
			if (!$error) {
				$object->array_options['options_reasonundeploy'] = $reasonundeploy;
				$object->array_options['options_commentundeploy'] = $commentundeploy;
				$result = $object->insertExtraFields();
				if ($result <= 0) {
					$error++;
					setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
				}
			}
			// Finish undeploy

			if (! $error) {
				dol_syslog('--- Unactivate all lines of '.$contract->ref.' - undeploy process from myaccount', LOG_DEBUG, 0);

				$result = $contract->closeAll($user, 1, $comment);	// Triggers disabled by call (suspend were done just before)
				if ($result < 0) {
					$error++;
					setEventMessages($contract->error, $contract->errors, 'errors');
				}
			}

			if (! $error) {
				// Send deployment email
				include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
				include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
				$formmail=new FormMail($db);

				$arraydefaultmessage=$formmail->getEMailTemplate($db, 'contract', $user, $langs, 0, 1, 'InstanceUndeployed');	// Templates are init into data.sql

				$substitutionarray=getCommonSubstitutionArray($langs, 0, null, $contract);
				$substitutionarray['__HASH__']=$hash;

				complete_substitutions_array($substitutionarray, $langs, $contract);

				$subject = make_substitutions($arraydefaultmessage->topic, $substitutionarray, $langs);
				$msg     = make_substitutions($arraydefaultmessage->content, $substitutionarray, $langs);
				$from = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;
				$to = $contract->thirdparty->email;

				$cmail = new CMailFile($subject, $to, $from, $msg, array(), array(), array(), '', '', 0, 1);
				$result = $cmail->sendfile();
				if (! $result) {
					$error++;
					setEventMessages($cmail->error, $cmail->errors, 'warnings');
				}
			}
		}

		// Force to close services and launch "undeploy"
		if (! $error && $action == 'undeployconfirmed') {
			$hash = GETPOST('hash', 'none');

			dol_syslog("Hash received = ".$hash.' to compare to hash of '.$stringtohash.' = '.dol_hash($stringtohash));

			if (! dol_verifyHash($stringtohash, $hash)) {
				$error++;
				setEventMessages('InvalidLinkImmediateDestructionCanceled', null, 'warnings');
			} else {
				$object = $contract;

				dol_syslog("--- Start undeploy of ".$contract->ref." after a confirmation from email for ".$contract->ref_customer, LOG_DEBUG, 0);

				// SAME CODE THAN INTO ACTION_SELLYOURSAAS.CLASS.PHP

				// Disable template invoice
				$object->fetchObjectLinked();

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
								setEventMessages($invoice->error, $invoice->errors, 'errors');
							}
						}
					}
				}

				// Do not use the flush here, this will return header and break the redirect later.
				//flush();

				$comment = 'Contract for '.$contract->ref.' is undeployed after a click on the undeploy confirmation request (sent by email from customer dashboard)';

				if (! $error) {
					$sellyoursaasutils = new SellYourSaasUtils($db);
					$result = $sellyoursaasutils->sellyoursaasRemoteAction('undeploy', $contract, 'admin', '', '', 0, $comment, 300);
					if ($result <= 0) {
						$error++;
						setEventMessages($sellyoursaasutils->error, $sellyoursaasutils->errors, 'errors');
					}
				}

				// Finish deploy all

				$comment = 'Services for '.$contract->ref.' closed after a click on the undeploy confirmation request (sent by email from customer dashboard)';

				// Unactivate all lines
				if (! $error) {
					dol_syslog('--- Unactivate all lines of '.$contract->ref.' - undeployconfirmed process from myaccount', LOG_DEBUG, 0);

					$result = $object->closeAll($user, 1, $comment);
					if ($result <= 0) {
						$error++;
						setEventMessages($object->error, $object->errors, 'errors');
					}
				}

				// End of undeployment is now OK / Complete
				if (! $error) {
					$contract->array_options['options_deployment_status'] = 'undeployed';
					$contract->array_options['options_undeployment_date'] = dol_now('tzserver');
					$contract->array_options['options_undeployment_ip'] = $_SERVER['REMOTE_ADDR'];

					$result = $contract->update($user);
					if ($result < 0) {
						$error++;
						setEventMessages($contract->error, $contract->errors, 'errors');
					}
				}
			}
		}
	}

	//$error++;
	if (! $error) {
		$db->commit();

		if ($action == 'undeployconfirmed') {
			setEventMessages($langs->trans("InstanceWasUndeployedConfirmed"), null, 'warnings');
		} else {
			setEventMessages($langs->trans("InstanceWasUndeployed"), null, 'mesgs');
			setEventMessages($langs->trans("InstanceWasUndeployedToConfirm"), null, 'warnings');

			$tmpcontract = $contract;

			if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED)) {
				try {
					dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

					$arrayconfig=array();
					if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY)) {
						$arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
					}

					$statsd = new DataDog\DogStatsd($arrayconfig);

					// Add flag for paying instance lost
					$ispaidinstance = sellyoursaasIsPaidInstance($contract);
					if ($ispaidinstance) {
						$langs->load("sellyoursaas@sellyoursaas");

						dol_syslog("Send other metric sellyoursaas.payinginstancelost to datadog".(get_class($tmpcontract) == 'Contrat' ? ' contractid='.$tmpcontract->id.' contractref='.$tmpcontract->ref: ''));
						$arraytags=null;
						$statsd->increment('sellyoursaas.payinginstancelost', 1, $arraytags);

						global $dolibarr_main_url_root;
						$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT, '/').'$/i', '', trim($dolibarr_main_url_root));
						$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
						//$urlwithroot=DOL_MAIN_URL_ROOT;					// This is to use same domain name than current

						//$tmpcontract->fetch_thirdparty();
						$mythirdpartyaccount = $tmpcontract->thirdparty;

						$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;
						if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
							&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
							$newnamekey = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
							if (! empty($conf->global->$newnamekey)) $sellyoursaasname = $conf->global->$newnamekey;
						}

						$titleofevent = dol_trunc($sellyoursaasname.' - '.gethostname().' - '.$langs->trans("PayingInstanceLost").': '.$tmpcontract->ref.' - '.$mythirdpartyaccount->name, 90);
						$messageofevent = ' - '.$langs->trans("IPAddress").' '.getUserRemoteIP()."\n";
						$messageofevent.= $langs->trans("PayingInstanceLost").': '.$tmpcontract->ref.' - '.$mythirdpartyaccount->name.' - ['.$langs->trans("SeeOnBackoffice").']('.$urlwithouturlroot.'/societe/card.php?socid='.$mythirdpartyaccount->id.')'."\n";
						$messageofevent.= 'Lost after suspension of instance + recurring invoice after a destroy request.';

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
				} catch (Exception $e) {
					// Nothing
				}
			}
		}

		header('Location: '.$_SERVER["PHP_SELF"].'?modes=instances&tab=resources_'.$contract->id);
		exit;
	} else {
		$db->rollback();
	}
} elseif ($action == 'deleteaccount') {
	if (! GETPOST('accounttodestroy', 'alpha')) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("AccountToDelete")), null, 'errors');
	} else {
		if (GETPOST('accounttodestroy', 'alpha') != $mythirdpartyaccount->email) {
			setEventMessages($langs->trans("ErrorEmailMustMatch"), null, 'errors');
		} else {
			// TODO If there is at least 1 invoice, me must keep account
			$keepaccount = 1;

			// If we decided to keep account
			if ($keepaccount) {
				$mythirdpartyaccount->status = 0;
				$mythirdpartyaccount->update(0, $user);
				//setEventMessages($langs->trans("YourAccountHasBeenClosed"), null, 'errors');

				llxHeader($head, $langs->trans("MyAccount"), '', '', 0, 0, '', '', '', 'myaccount');

				print '
					<center>
				';
				print $langs->trans("YourAccountHasBeenClosed");
				print '
					</center>
				';

				// TODO
				// Make a redirect on cancellation survey

				llxFooter();

				exit;
			} else {
				$mythirdpartyaccount->delete(0, $user);
				setEventMessages($langs->trans("YourAccountHasBeenClosed"), null, 'errors');
			}
		}
	}
}



/*
 * View
 */

$form = new Form($db);

if ($welcomecid > 0) {
	// Here $_POST is empty, $GET has just welcomecid=..., $_SESSION['dol_loginsellyoursaas'] is socid =382
	/*var_dump($_POST);
	var_dump($_GET);
	var_dump($_SESSION);
	var_dump($mythirdpartyaccount);*/
	$contract=new Contrat($db);
	$contract->fetch($welcomecid);
	$listofcontractid[$welcomecid]=$contract;
	// Add a protection to avoid to see dashboard of others by changing welcomecid.
	if (($mythirdpartyaccount->isareseller == 0 && $contract->fk_soc != $_SESSION['dol_loginsellyoursaas'])           // Not reseller, and contract is for another thirdparty
	|| ($mythirdpartyaccount->isareseller == 1 && array_key_exists($contract->fk_soc, $listofcustomeridreseller))) { // Is a reseller and contract is for a company that is a customer of reseller
		dol_print_error_email('DEPLOY-WELCOMEID'.$welcomecid, 'Bad value for welcomeid', null, 'alert alert-error');
		exit;
	}
}
//var_dump($listofcontractid);

$favicon=getDomainFromURL($_SERVER['SERVER_NAME'], 0);
if (! preg_match('/\.(png|jpg)$/', $favicon)) $favicon.='.png';
if (! empty($conf->global->MAIN_FAVICON_URL)) $favicon=$conf->global->MAIN_FAVICON_URL;

$arrayofcss = array();
// Javascript code on logon page only to detect user tz, dst_observed, dst_first, dst_second
$arrayofjs=array(
	'/includes/jstz/jstz.min.js'.(empty($conf->dol_use_jmobile)?'':'?version='.urlencode(DOL_VERSION)),
	'/core/js/dst.js'.(empty($conf->dol_use_jmobile)?'':'?version='.urlencode(DOL_VERSION))
);


$head = '';
if ($favicon) $head.='<link rel="icon" href="img/'.$favicon.'">'."\n";
$head.='<!-- Bootstrap core CSS -->
<link href="dist/css/bootstrap.css" type="text/css" rel="stylesheet">
<link href="dist/css/myaccount.css" type="text/css" rel="stylesheet">
<link href="dist/css/stripe.css" type="text/css" rel="stylesheet">';
$head.="
<script>
var select2arrayoflanguage = {
	matches: function (matches) { return matches + '" .dol_escape_js($langs->transnoentitiesnoconv("Select2ResultFoundUseArrows"))."'; },
	noResults: function () { return '". dol_escape_js($langs->transnoentitiesnoconv("Select2NotFound")). "'; },
	inputTooShort: function (input) {
		var n = input.minimum;
		/*console.log(input);
		console.log(input.minimum);*/
		if (n > 1) return '". dol_escape_js($langs->transnoentitiesnoconv("Select2Enter")). "' + n + '". dol_escape_js($langs->transnoentitiesnoconv("Select2MoreCharacters")) ."';
			else return '". dol_escape_js($langs->transnoentitiesnoconv("Select2Enter")) ."' + n + '". dol_escape_js($langs->transnoentitiesnoconv("Select2MoreCharacter")) . "';
		},
	loadMore: function (pageNumber) { return '".dol_escape_js($langs->transnoentitiesnoconv("Select2LoadingMoreResults"))."'; },
	searching: function () { return '". dol_escape_js($langs->transnoentitiesnoconv("Select2SearchInProgress"))."'; }
};
</script>
";



llxHeader($head, $langs->trans("MyAccount"), '', '', 0, 0, $arrayofjs, $arrayofcss, '', 'myaccount');


?>

<div id="waitMask" style="display:none;">
<font size="3em" style="color:#888; font-weight: bold;"><?php echo $langs->trans("InstallingInstance") ?><br><?php echo $langs->trans("PleaseWait") ?><br></font>
	<img id="waitMaskImg" width="100px" src="<?php echo "ajax-loader.gif"; ?>" alt="Loading" />
</div>

<?php

$logoval = getDolGlobalString('SELLYOURSAAS_LOGO_MINI');
$logoblackval = getDolGlobalString('SELLYOURSAAS_LOGO_MINI_BLACK');
if (is_object($mythirdpartyaccount) && $mythirdpartyaccount->array_options['options_domain_registration_page']) {
	$domainforkey = strtoupper($mythirdpartyaccount->array_options['options_domain_registration_page']);
	$domainforkey = preg_replace('/\./', '_', $domainforkey);

	$constname = 'SELLYOURSAAS_LOGO_MINI_'.$domainforkey;
	$constnameblack = 'SELLYOURSAAS_LOGO_MINI_BLACK_'.$domainforkey;
	if (!empty($conf->global->$constname)) {
		$logoval=$conf->global->$constname;
	}
	if (!empty($conf->global->$constnameblack)) {
		$logoblackval=$conf->global->$constnameblack;
	}
}

$linklogo = DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&file='.urlencode('logos/thumbs/'.$logoval);
$linklogoblack = DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&file='.urlencode('logos/thumbs/'.$logoblackval);


print '
    <nav class="navbar navbar-toggleable-md navbar-inverse bg-inverse">

	  <!-- Search + Menu -->

	  <form class="navbar-toggle navbar-toggler-right form-inline my-md-0" action="'.$_SERVER["PHP_SELF"].'">
            <input type="hidden" name="token" value="'.newToken().'">
			<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">
			<!--
				          <input class="form-control mr-sm-2" style="max-width: 100px;" type="text" placeholder="'.$langs->trans("Search").'">
				          <button class="btn-transparent nav-link" type="submit"><i class="fa fa-search"></i></button>
			-->
	      <button class="inline-block navbar-toggler" type="button" data-toggle="collapse" data-target="#navbars" aria-controls="navbars" aria-expanded="false" aria-label="Toggle navigation">
	        <span class="navbar-toggler-icon"></span>
	      </button>
	  </form>

	  <!-- Logo -->
      <span class="navbar-brand"><img src="'.$linklogoblack.'" height="34px"></span>

	  <!-- Menu -->
      <div class="collapse navbar-collapse" id="navbars">
        <ul class="navbar-nav mr-auto">
          <li class="nav-item'.($mode == 'dashboard'?' active':'').'">
            <a class="nav-link" href="'.$_SERVER["PHP_SELF"].'?mode=dashboard"><i class="fa fa-tachometer"></i> '.$langs->trans("Dashboard").'</a>
          </li>
          <li class="nav-item'.($mode == 'instances'?' active':'').'">
            <a class="nav-link" href="'.$_SERVER["PHP_SELF"].'?mode=instances"><i class="fa fa-server"></i> '.$langs->trans("MyInstances").'</a>
          </li>
          <li class="nav-item'.($mode == 'billing'?' active':'').'">
            <a class="nav-link" href="'.$_SERVER["PHP_SELF"].'?mode=billing"><i class="fa fa-usd"></i> '.$langs->trans("MyBilling").'</a>
          </li>';

if ($mythirdpartyaccount->isareseller) {
	print '
			<li class="nav-item'.($mode == 'mycustomerinstances'?' active':'').'">
			<a class="nav-link" href="'.$_SERVER["PHP_SELF"].'?mode=mycustomerinstances"><i class="fa fa-server"></i> '.$langs->trans("MyCustomersInstances").'</a>
			</li>
			<li class="nav-item'.($mode == 'mycustomerbilling'?' active':'').'">
			<a class="nav-link" href="'.$_SERVER["PHP_SELF"].'?mode=mycustomerbilling"><i class="fa fa-usd"></i> '.$langs->trans("MyCustomersBilling").'</a>
			</li>';
}

		print '<li class="nav-item'.($mode == 'support'?' active':'').' dropdown">
            <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#"><i class="fa fa-gear"></i> '.$langs->trans("Support").'</a>
            <ul class="dropdown-menu">';
		// FAQ
		print '<li><a class="dropdown-item" href="'.$urlfaq.'" target="_newfaq"><i class="fa fa-question pictofixedwidth"></i> '.$langs->trans("FAQs").'</a></li>';
		// Support
		print '<li class="dropdown-divider"></li>';
		print '<li><a class="dropdown-item" href="'.$_SERVER["PHP_SELF"].'?mode=support"><i class="fa fa-hands-helping pictofixedwidth"></i> '.$langs->trans("ContactUs").'</a></li>';

		print '
            </ul>
          </li>

          <li class="nav-item'.($mode == 'myaccount'?' active':'').' dropdown">
             <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#socid='.$mythirdpartyaccount->id.'"><i class="fa fa-user"></i> '.$langs->trans("MyAccount").' ('.$mythirdpartyaccount->email.')</a>
             <ul class="dropdown-menu">
                 <li><a class="dropdown-item" href="'.$_SERVER["PHP_SELF"].'?mode=myaccount"><i class="fa fa-user pictofixedwidth"></i> '.$langs->trans("MyAccount").'</a></li>';
		// Reseler request
if (! $mythirdpartyaccount->isareseller) {
	$allowresellerprogram = (! empty($conf->global->SELLYOURSAAS_ALLOW_RESELLER_PROGRAM));
	if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
			$newnamekey = 'SELLYOURSAAS_ALLOW_RESELLER_PROGRAM-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
			if (isset($conf->global->$newnamekey)) $allowresellerprogram = $conf->global->$newnamekey;
	}

		// Check if there is at least one package with status resale ok
	if ($allowresellerprogram) {
			print '<li class="dropdown-divider"></li>';
			print '<li><a class="dropdown-item" href="'.$_SERVER["PHP_SELF"].'?mode=becomereseller"><i class="fa fa-briefcase pictofixedwidth"></i> '.$langs->trans("BecomeReseller").'</a></li>';
	}
}
		print '
			<li class="dropdown-divider"></li>
			<li><a class="dropdown-item" href="'.$_SERVER["PHP_SELF"].'?mode=logout"><i class="fa fa-sign-out pictofixedwidth"></i> '.$langs->trans("Logout").'</a></li>
             </ul>
           </li>

        </ul>


      </div>
    </nav>
';


print '
    <div class="container">
		<br>
';


//var_dump($_SESSION["dol_loginsellyoursaas"]);
//var_dump($user);


// Special case - when coming from a specific contract id $welcomid
if ($welcomecid > 0) {
	$contract = $listofcontractid[$welcomecid];
	$contract->fetch_thirdparty();

	print '
      <div class="jumbotron">
        <div class="col-sm-8 mx-auto">


		<!-- BEGIN PAGE HEAD -->
		<div class="page-head">
		<!-- BEGIN PAGE TITLE -->
		<div class="page-title">
		<h1>'.$langs->trans("Welcome").'</h1>
		</div>
		<!-- END PAGE TITLE -->
		</div>
		<!-- END PAGE HEAD -->


		<!-- BEGIN PORTLET -->
		<div class="portletnoborder light">

		<div class="portlet-header">
		<div class="caption">
		<span class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("InstallationComplete").'</span>
		</div>
		</div>';

	if (in_array($contract->thirdparty->country_code, array('aaa', 'bbb'))) {
		print '
		<div class="portlet-body">
		<p>
		'.$langs->trans("YourCredentialToAccessYourInstanceHasBeenSentByEmail").'
		</p>

		</div>';
	} else {
		$productref = $contract->array_options['options_plan'];
		$productlabel = $productref;

		$tmpproduct = new Product($db);
		$resfetch = $tmpproduct->fetch(0, $productref);
		if ($resfetch > 0) {
			$productlabel = $tmpproduct->label;
		}

		print '<!-- message installation finished -->
		<div class="portlet-body">
		<p>
		'.$langs->trans("YouCanAccessYourInstance", $productlabel).'&nbsp:
		</p>
		<p class="well">
		'.$langs->trans("URL").' : <a href="https://'.$contract->ref_customer.'" target="_blank" rel="noopener">'.$contract->ref_customer.'</a>';

		print '<br> '.$langs->trans("Username").' : '.($_SESSION['initialapplogin']?'<strong>'.$_SESSION['initialapplogin'].'</strong>':'NA').'
		<br> '.$langs->trans("Password").' : ';
		if (!empty($_SESSION['initialapppassword'])) {
			print '<strong id="initialpasswordinstance" data-pass="hidden">'.str_repeat("*", strlen($_SESSION['initialapppassword'])).'</strong>';
			print '&nbsp; &nbsp;';
			print '<i id="initialpasswordinstanceshow" class="fa fa-eye initialpasswordinstancebutton"></i>';
			print '<i id="initialpasswordinstancehide" class="fa fa-eye-slash initialpasswordinstancebutton" style="display:none"></i>';
		} else {
			print 'NA';
		}
		print '
		</p>
		<p>
		<a class="btn btn-primary wordbreak" target="_blank" rel="noopener" href="https://'.$contract->ref_customer.'?username='.urlencode($_SESSION['initialapplogin']).'">'.$langs->trans("TakeMeTo", $productlabel).' <span class="fa fa-external-link-alt"></span></a>
		</p>
		<script>
		jQuery(document).ready(function() {
			$(".initialpasswordinstancebutton").on("click", function(){
				if($("#initialpasswordinstance").attr("data-pass") == "hidden") {
					console.log("We show the password");
					$("#initialpasswordinstanceshow").hide();
					$("#initialpasswordinstancehide").show();
					$("#initialpasswordinstance").html("'.$_SESSION['initialapppassword'].'");
					$("#initialpasswordinstance").attr("data-pass","show");
				} else {
					console.log("We hide the password");
					$("#initialpasswordinstanceshow").show();
					$("#initialpasswordinstancehide").hide();
					$("#initialpasswordinstance").html("'.str_repeat("*", strlen($_SESSION['initialapppassword'])).'");
					$("#initialpasswordinstance").attr("data-pass","hidden");
				}
			})
		})
		</script>
		</div>';
	}

	print '
		</div> <!-- END PORTLET -->

        </div>
      </div>
	';
}

$showannoucefordomain = array();
// Detect global announce
if (! empty($conf->global->SELLYOURSAAS_ANNOUNCE_ON) && ! empty($conf->global->SELLYOURSAAS_ANNOUNCE)) {
	$showannoucefordomain['_global_'] = 'SELLYOURSAAS_ANNOUNCE';
}
// Detect local announce (per server)
foreach ($listofcontractidopen as $tmpcontract) {
	$tmpdomainname = getDomainFromURL($tmpcontract->ref_customer, 2);
	$deploymentserver = new Deploymentserver($db);
	$deploymentserver->fetch(null, $tmpdomainname);
	if (!empty($deploymentserver->servercustomerannouncestatus) && !empty($deploymentserver->servercustomerannounce)) {
		//$datemessage = $db->jdate();
		print '<div class="note note-warning">';
		print '<b>'.dol_print_date($deploymentserver->date_modification, 'dayhour').'</b>';
		if ($tmpdomainname != '_global_') {
			print ' - '.$langs->trans("InfoForDomain").' <b>*.'.$tmpdomainname.'</b>';
		}
		print ' : ';
		print '<h4 class="block">';
		$reg=array();
		if (preg_match('/^\((.*)\)$/', $deploymentserver->servercustomerannounce, $reg)) {
			print $langs->trans($reg[1]);
		} else {
			print $deploymentserver->servercustomerannounce;
		}
		print '</h4>';
		print '</div>';
	} elseif (getDolGlobalString('SELLYOURSAAS_ANNOUNCE_ON_'.$tmpdomainname)) {
		$showannoucefordomain[$tmpdomainname] = 'SELLYOURSAAS_ANNOUNCE_'.$tmpdomainname;
	}
}
// Show annouces detected
if (!empty($showannoucefordomain)) {
	foreach ($showannoucefordomain as $tmpdomainame => $tmpkey) {
		$sql = "SELECT name, value, tms from ".MAIN_DB_PREFIX."const where name = '".$db->escape($tmpkey)."'";
		$resql=$db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$datemessage = $db->jdate($obj->tms);

				print '<div class="note note-warning">';
				print '<b>'.dol_print_date($datemessage, 'dayhour').'</b>';
				if ($tmpdomainame != '_global_') {
					print ' - '.$langs->trans("Domain").' <b>'.$tmpdomainame.'</b>';
				}
				print ' : ';
				print '<h4 class="block">';
				$reg=array();
				if (preg_match('/^\((.*)\)$/', $obj->value, $reg)) {
					print $langs->trans($reg[1]);
				} else {
					print $obj->value;
				}
				print '</h4>';
				print '</div>';
			}
			$db->free($resql);
		} else {
			dol_print_error($db);
		}
	}
}

// List of available plans/products (available for reseller)
$arrayofplans=array();
$arrayofplanscode=array();
$sqlproducts = 'SELECT p.rowid, p.ref, p.label, p.price, p.price_ttc, p.duration, pe.availabelforresellers';
$sqlproducts.= ' FROM '.MAIN_DB_PREFIX.'product as p, '.MAIN_DB_PREFIX.'product_extrafields as pe';
$sqlproducts.= ' WHERE p.tosell = 1 AND p.entity = '.$conf->entity;
$sqlproducts.= " AND pe.fk_object = p.rowid AND pe.app_or_option = 'app'";
$sqlproducts.= " AND p.ref NOT LIKE '%DolibarrV1%'";
$sqlproducts.= " AND pe.availabelforresellers = 1";
//$sqlproducts.= " AND (p.rowid = ".$planid." OR 1 = 1)";
$sqlproducts.= " ORDER BY pe.position ASC";
$resqlproducts = $db->query($sqlproducts);
if ($resqlproducts) {
	$num = $db->num_rows($resqlproducts);

	$tmpprod = new Product($db);
	$tmpprodchild = new Product($db);
	$i=0;
	$maxcptoptions = 0;
	while ($i < $num) {
		$obj = $db->fetch_object($resqlproducts);
		if ($obj) {
			$tmpprod->fetch($obj->rowid);

			// Check that package is qualified
			/*
			if ($tmpprod->array_options['options_package'] > 0)
			{
				$tmppackage = new Packages($db);
				$tmppackage->fetch($tmpprod->array_options['options_package']);

				if ($tmppackage->restrict_domains)
				{

				}
			}
			*/

			$tmpprod->sousprods = array();
			$tmpprod->get_sousproduits_arbo();
			$tmparray = $tmpprod->get_arbo_each_prod();

			$label = $obj->label;

			$priceinstance=array();
			$priceinstance_ttc=array();

			$priceinstance['fix'] = $obj->price;
			$priceinstance_ttc['fix'] = $obj->price_ttc;
			$priceinstance['user'] = 0;
			$priceinstance_ttc['user'] = 0;
			$priceinstance['options'] = 0;
			$priceinstance_ttc['options'] = 0;
			$cptoptions = 0;

			if (count($tmparray) > 0) {
				foreach ($tmparray as $key => $value) {
					$tmpprodchild->fetch($value['id']);
					if (preg_match('/user/i', $tmpprodchild->ref) || preg_match('/user/i', $tmpprodchild->array_options['options_resource_label'])) {
						$priceinstance['user'] .= $tmpprodchild->price;
						$priceinstance_ttc['user'] .= $tmpprodchild->price_ttc;
					} elseif ($tmpprodchild->array_options['options_app_or_option'] == 'system') {
						// Don't add system services to global price, these are options with calculated quantities
					} else {
						if ($tmpprodchild->array_options['options_app_or_option'] == 'option') {
							$priceinstance['options'] += $tmpprodchild->price;
							$priceinstance_ttc['options'] += $tmpprodchild->price_ttc;
							$arrayofplansmodifyprice[$obj->rowid]["options"][$tmpprodchild->id]["price"] = price2num($tmpprodchild->price, 'MT');
							$cptoptions++;
						}
						$priceinstance['fix'] += $tmpprodchild->price;
						$priceinstance_ttc['fix'] += $tmpprodchild->price_ttc;
					}
				}
			}
			$maxcptoptions = max($maxcptoptions, $cptoptions);
			$pricetoshow = price2num($priceinstance['fix'], 'MT');
			if (empty($pricetoshow)) $pricetoshow = 0;
			$arrayofplans[$obj->rowid]=$label.' ('.price($pricetoshow, 1, $langs, 1, 0, -1, $conf->currency);
			$arrayofplansmodifyprice[$obj->rowid]["label"] = $label;
			$arrayofplansmodifyprice[$obj->rowid]["price"] = price2num($priceinstance['fix'] - $priceinstance['options'], 'MT');
			if ($tmpprod->duration) $arrayofplans[$obj->rowid].=' / '.($tmpprod->duration == '1m' ? $langs->trans("Month") : '');
			if ($priceinstance['user']) {
				$arrayofplans[$obj->rowid].=' + '.price(price2num($priceinstance['user'], 'MT'), 1, $langs, 1, 0, -1, $conf->currency).'/'.$langs->trans("User");
				$arrayofplansmodifyprice[$obj->rowid]["priceuser"] = price2num($priceinstance['user'], 'MT');
				if ($tmpprod->duration) $arrayofplans[$obj->rowid].=' / '.($tmpprod->duration == '1m' ? $langs->trans("Month") : '');
			}
			$arrayofplans[$obj->rowid].=')';
			$arrayofplanscode[$obj->rowid] = $obj->ref;
		}
		$i++;
	}
} else dol_print_error($db);


// Show partner links
if ($mythirdpartyaccount->isareseller) {
	print '
		<!-- Info reseller -->
		<div class="note note-info">
		<h4 class="block"><span class="fa fa-briefcase"></span> '.$langs->trans("YouAreAReseller").'.</h4>
		';
	print '<span class="opacitymedium">'.$langs->trans("YourURLToCreateNewInstance").':</span><br>';

	$sellyoursaasaccounturl = $conf->global->SELLYOURSAAS_ACCOUNT_URL;
	$sellyoursaasaccounturl = preg_replace('/'.preg_quote(getDomainFromURL($conf->global->SELLYOURSAAS_ACCOUNT_URL, 1), '/').'/', getDomainFromURL($_SERVER["SERVER_NAME"], 1), $sellyoursaasaccounturl);

	$urlforpartner = $sellyoursaasaccounturl.'/register.php?partner='.$mythirdpartyaccount->id.'&partnerkey='.md5($mythirdpartyaccount->name_alias);
	//print '<a class="wordbreak" href="'.$urlforpartner.'" target="_blankinstance" rel="noopener">';
	print '<input type="text" class="quatrevingtpercent" id="urlforpartner" name="urlforpartner" value="'.$urlforpartner.'">';
	print ajax_autoselect("urlforpartner");
	//print $urlforpartner;
	//print '</a>';

	print '<script type="text/javascript" language="javascript">
	jQuery(document).ready(function() {
		jQuery("#spanmorereselleroptions").click(function() {
			console.log("Click on spanmorereselleroptions");
			jQuery("#divmorereselleroptions").toggle();
		});
        jQuery("#divmorereselleroptions").toggle();
	});
		</script>';

	print '<br><a class="small" id="spanmorereselleroptions" href="#" style="color: #888">'.$langs->trans("OtherOptionsAndParameters").'... <span class="fa fa-angle-down"></span></a><br>';
	print '<div id="divmorereselleroptions" style="display: hidden" class="small">';
	if (is_array($arrayofplans) && count($arrayofplans) > 1) {
		print '&plan=XXX : ';
		print '<span class="opacitymedium">'.$langs->trans("ToForcePlan").', '.$langs->trans("whereXXXcanbe").' '.join(', ', $arrayofplanscode).'</span><br>';
	}
	print '&extcss=mycssurl : <span class="opacitymedium">'.$langs->trans("YouCanUseCSSParameter").'. An example is available with &extcss='.$sellyoursaasaccounturl.'/dist/css/alt-myaccount-example.css</span><br>';
	print '&disablecustomeremail=1 : <span class="opacitymedium">'.$langs->trans("ToDisableEmailThatConfirmsRegistration").'</span>';

	print '</div>';

	if (getDolGlobalInt("SELLYOURSAAS_RESELLER_ALLOW_CUSTOM_PRICE")) {
		print '<br>';
		print '<span class="opacitymedium small">'.$langs->trans("ForcePricesOfInstances").'</span>';
		print '<form action="'.$_SERVER["PHP_SELF"].'" name="modifyresellerprices" method="POST" >';
		print '<input type="hidden" name="action" value="updateforcepriceinstance">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<div class="div-table-responsive">';
		print '<table class="noborder small centpercent background-white padding">';
		print '<tr class="field_'.$key.' liste_titre"><th>';
		print $langs->trans("Label");
		print '</th>';
		print '<th>';
		print $langs->trans("FixPrice");
		print ' ('.$langs->trans("HT").')';
		print '</th>';
		print '<th>';
		print $langs->trans("PricePerUsers");
		print ' ('.$langs->trans("HT").')';
		print '</th>';
		for ($i=0; $i < $maxcptoptions; $i++) {
			print '<th>';
			print $langs->trans("OptionForcePrice", $i+1);
			print '</th>';
		}
		print '<th>';
		print '</th>';
		print '</tr>';

		// Ajout Options price change
		foreach ($arrayofplansmodifyprice as $key => $value) {
			print '<tr class="field_'.$key.' oddeven">';
			print '<td class="maxwidth150 tdoverflowmax200" title="'.dol_escape_htmltag($value['label']).'">';
			print $value["label"];
			print '</td> ';
			if ($action == 'editproperty' && $key == $propertykey) {
				print '<input type="hidden" name="priceproductid" value="'.$key.'">';
				print '<td>';
				print '<input class="flat field_price maxwidth50" type="text" id="field_price_'.$mythirdpartyaccount->id."_".$key.'" name="field_price_'.$mythirdpartyaccount->id."_".$key.'" value="'.(price(getDolGlobalString("SELLYOURSAAS_RESELLER_FIX_PRICE_".$mythirdpartyaccount->id."_".$key) ? : $value["price"]).'"><span>').$langs->getCurrencySymbol($conf->currency).'<span>';
				print '</td>';
				print '<td>';
				if (isset($value["priceuser"])) {
					print '<input class="flat field_price maxwidth50" type="text" id="field_priceuser_'.$mythirdpartyaccount->id."_".$key.'" name="field_priceuser_'.$mythirdpartyaccount->id."_".$key.'"value="'.(price(getDolGlobalString("SELLYOURSAAS_RESELLER_PRICE_PER_USER_".$mythirdpartyaccount->id."_".$key) ? :$value["priceuser"]).'"><span>').$langs->getCurrencySymbol($conf->currency).'</span>';
				}
				print '</td>';
				if (isset($value["options"])) {
					foreach ($value["options"] as $id => $data) {
						print '<td>';
						print '<input class="flat field_price maxwidth50" type="text" id="field_price_option_'.$id.'_'.$mythirdpartyaccount->id."_".$key.'" name="field_price_option_'.$id.'_'.$mythirdpartyaccount->id."_".$key.'"value="'.(price(getDolGlobalString("SELLYOURSAAS_RESELLER_PRICE_OPTION_".$id."_".$mythirdpartyaccount->id."_".$key) ? :$data["price"]).'"><span>').$langs->getCurrencySymbol($conf->currency).'</span>';
						print '</td>';
					}
				} else {
					for ($i=0; $i < $maxcptoptions; $i++) {
						print '<td></td>';
					}
				}
				print '<td class="center maxwidth100">';
				print '<input class="button smallpaddingimp btn green-haze btn-circle" type="submit" name="edit" value="'.$langs->trans("Save").'" style="margin: 2px;">';
				print '<input class="button button-cancel smallpaddingimp btn green-haze btn-circle" type="submit" name="cancel" value="'.$langs->trans("Cancel").'">';
				print '</td>';
			} else {
				print '<td>';
				print '<span>';
				print dol_escape_htmltag(price(getDolGlobalString("SELLYOURSAAS_RESELLER_FIX_PRICE_".$mythirdpartyaccount->id."_".$key) ? : $value["price"])).$langs->getCurrencySymbol($conf->currency).'&nbsp;';
				print '</span>';
				print '</td>';
				print '<td>';
				if (isset($value["priceuser"])) {
					print dol_escape_htmltag(price(getDolGlobalString("SELLYOURSAAS_RESELLER_PRICE_PER_USER_".$mythirdpartyaccount->id."_".$key) ? : $value["priceuser"])).$langs->getCurrencySymbol($conf->currency);
				}
				print '</td>';
				if (isset($value["options"])) {
					foreach ($value["options"] as $id => $data) {
						print '<td>';
						print dol_escape_htmltag(price(getDolGlobalString("SELLYOURSAAS_RESELLER_PRICE_OPTION_".$id."_".$mythirdpartyaccount->id."_".$key) ? : $data["price"])).$langs->getCurrencySymbol($conf->currency);
						print '</td>';
					}
				} else {
					for ($i=0; $i < $maxcptoptions; $i++) {
						print '<td></td>';
					}
				}
				print '<td class="center">';
				print '<a class="editfielda reposition marginleftonly marginrighttonly paddingright paddingleft" href="'.$_SERVER["PHP_SELF"].'?action=editproperty&token='.newToken().'&propertykey='.urlencode($key).'">'.img_edit().'</a>';
				print '<a class="resetfielda reposition marginleftonly marginrighttonly paddingright paddingleft" href="'.$_SERVER["PHP_SELF"].'?action=resetproperty&token='.newToken().'&propertykey='.urlencode($key).'" title="'.dol_escape_htmltag($langs->trans("ResetToRecommendedValue")).'">'.img_picto('', 'eraser', 'class="paddingrightonly" style="color: #444;"').'</a>';
				print '</td>';
			}
			print '</tr>';
		}
		print '</table></div>';

		print '</form>';
	}

	print '<br>';
	$urformycustomerinstances = '<strong>'.$langs->transnoentitiesnoconv("MyCustomersBilling").'</strong>';
	print str_replace('{s1}', $urformycustomerinstances, $langs->trans("YourCommissionsAppearsInMenu", $mythirdpartyaccount->array_options['options_commission'], '{s1}'));


	print '
		</div>
	';

	if ($action == 'resetproperty') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?propertykey='.$propertykey, $langs->trans('ResetForcedPrice'), $langs->trans('ConfirmResetForcedPrice'), 'resetpropertyconfirm', '', 0, 1);
		print $formconfirm;
	}
}



// Fill array of company payment modes
$arrayofcompanypaymentmode = array();
$sql = 'SELECT rowid, default_rib FROM '.MAIN_DB_PREFIX."societe_rib";
$sql.= " WHERE type in ('ban', 'card', 'paypal')";
$sql.= " AND fk_soc = ".$mythirdpartyaccount->id;
$sql.= " AND (type = 'ban' OR (type = 'card' AND status = ".$servicestatusstripe.") OR (type = 'paypal' AND status = ".$servicestatuspaypal."))";
$sql.= " ORDER BY default_rib DESC, tms DESC";

$resql = $db->query($sql);
if ($resql) {
	$num_rows = $db->num_rows($resql);
	if ($num_rows) {
		$i=0;
		while ($i < $num_rows) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				if ($obj->default_rib != 1) continue;	// Keep the default payment mode only

				$companypaymentmodetemp = new CompanyPaymentMode($db);
				$companypaymentmodetemp->fetch($obj->rowid);

				$arrayofcompanypaymentmode[] = $companypaymentmodetemp;
			}
			$i++;
		}
	}
}
$atleastonepaymentmode = (count($arrayofcompanypaymentmode) > 0 ? 1 : 0);
$nbpaymentmodeok = count($arrayofcompanypaymentmode);


// Fill var to count nb of instances
$nbofinstances = 0;
$nbofinstancesinprogress = 0;
$nbofinstancesdone = 0;
$nbofinstancessuspended = 0;
foreach ($listofcontractid as $contractid => $contract) {
	if ($contract->array_options['options_deployment_status'] == 'undeployed') { continue; }
	if ($contract->array_options['options_deployment_status'] == 'processing') { $nbofinstances++; $nbofinstancesinprogress++; continue; }

	$suspended = 0;
	foreach ($contract->lines as $keyline => $line) {
		if ($line->statut == ContratLigne::STATUS_CLOSED && $contract->array_options['options_deployment_status'] != 'undeployed') {
			$suspended = 1;
			break;
		}
	}

	$nbofinstances++;
	if ($suspended) {
		$nbofinstancessuspended++;
	} else {
		if (!preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
			$nbofinstancesdone++;
		}
	}
}

$nboftickets = $langs->trans("SoonAvailable");

// Analyse list of child instances for resellers
$nbofinstancesreseller = 0;
$nbofinstancesinprogressreseller = 0;
$nbofinstancesdonereseller = 0;
$nbofinstancessuspendedreseller = 0;
if ($mythirdpartyaccount->isareseller && count($listofcontractidreseller)) {
	// Fill var to count nb of instances
	foreach ($listofcontractidreseller as $contractid => $contract) {
		if ($contract->array_options['options_deployment_status'] == 'undeployed') { continue; }
		if ($contract->array_options['options_deployment_status'] == 'processing') { $nbofinstancesreseller++; $nbofinstancesinprogressreseller++; continue; }

		$suspended = 0;
		foreach ($contract->lines as $keyline => $line) {
			if ($line->statut == ContratLigne::STATUS_CLOSED && $contract->array_options['options_deployment_status'] != 'undeployed') {
				$suspended = 1;
				break;
			}
		}

		$nbofinstancesreseller++;
		if ($suspended) {
			$nbofinstancessuspendedreseller++;
		} else {
			if (!preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
				$nbofinstancesdonereseller++;
			}
		}
	}
}


$atleastonecontractwithtrialended = 0;
$atleastonepaymentinerroronopeninvoice = 0;


// Show warnings


if (empty($welcomecid) && ! in_array($action, array('instanceverification', 'autoupgrade'))) {
	$companypaymentmode = new CompanyPaymentMode($db);
	$result = $companypaymentmode->fetch(0, null, $mythirdpartyaccount->id);

	foreach ($listofcontractid as $contractid => $contract) {
		if ($mode == 'mycustomerbilling') continue;
		if ($mode == 'mycustomerinstances') continue;
		if ($contract->array_options['options_deployment_status'] == 'undeployed') continue;

		$delaybeforeendoftrial = 0;
		$isAPayingContract = sellyoursaasIsPaidInstance($contract);		// At least one template or final invoice
		$isASuspendedContract = sellyoursaasIsSuspended($contract);		// Is suspended or not ?
		$tmparray = sellyoursaasGetExpirationDate($contract, 1);
		$expirationdate = $tmparray['expirationdate'];					// End of date of service

		$messageforinstance=array();

		if (! $isAPayingContract && $contract->array_options['options_date_endfreeperiod'] > 0) {
			$dateendfreeperiod = $contract->array_options['options_date_endfreeperiod'];
			if (! is_numeric($dateendfreeperiod)) $dateendfreeperiod = dol_stringtotime($dateendfreeperiod);
			$delaybeforeendoftrial = ($dateendfreeperiod - $now);
			$delayindays = round($delaybeforeendoftrial / 3600 / 24);

			if (empty($atleastonepaymentmode)) {
				if ($delaybeforeendoftrial > 0) {
					// Trial not yet expired
					if (! $isASuspendedContract) {
						//$firstline = reset($contract->lines);
						print '
							<!-- XDaysBeforeEndOfTrial -->
							<div class="note note-warning">
							<h4 class="block">'.str_replace('{s1}', '<span class="wordbreak">'.$contract->ref_customer.'</span>', $langs->trans("XDaysBeforeEndOfTrial", abs($delayindays), '{s1}')).' !</h4>';
						if ($mode != 'registerpaymentmode') {
							print '<p class="pforbutton">';
							if ($contract->total_ht > 0) {
								// Link to add payment and to swith to instance
								print '<a href="'.$_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode).'" class="btn btn-warning wordbreak">';
								print $langs->trans("AddAPaymentMode");
								print '</a>';
								print ' &nbsp; ';
							} elseif (getDolGlobalInt('SELLYOURSAAS_ENABLE_FREE_PAYMENT_MODE')) {
								$daybeforeendoftrial = getDolGlobalInt('SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT');
								if ($delaybeforeendoftrial <= ($daybeforeendoftrial + 1)) {	// We add 1 to be sure that link is visible before we send the soft email remind
									// Link to validate definitely instance
									print '<a href="'.$_SERVER["PHP_SELF"].'?mode=instances&action=validatefreemode&contractid='.$contract->id.'#contractid'.$contract->id.'" class="btn btn-warning wordbreak">';
									print $langs->trans("ConfirmInstanceValidation");
									print '</a>';
									print ' &nbsp; ';
								} else {
									print '<!-- Button to validate defintely instance will appears '.$daybeforeendoftrial.' days before end of trial -->';
								}
							}
							print '<a class="btn btn-primary wordbreak" target="_blank" rel="noopener" href="https://'.$contract->ref_customer.'">'.$langs->trans("TakeMeToApp").' <span class="fa fa-external-link-alt"></span></a>';
							print '</p>';
						}
						print '
							</div>
						';
					} else {
						//$firstline = reset($contract->lines);
						print '
							<!-- TrialInstanceWasSuspended -->
							<div class="note note-warning">
							<h4 class="block">'.str_replace('{s1}', '<span class="wordbreak">'.$contract->ref_customer.'</span>', $langs->trans("TrialInstanceWasSuspended", '{s1}')).' !</h4>';
						if ($mode != 'registerpaymentmode') {
							if ($contract->total_ht > 0) {
								print '<p>';
								print '<a href="'.$_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode).'" class="btn btn-warning wordbreak">';
								print $langs->trans("AddAPaymentModeToRestoreInstance");
								print '</a>';
								print '</p>';
							} elseif (getDolGlobalInt('SELLYOURSAAS_ENABLE_FREE_PAYMENT_MODE') && $delaybeforeendoftrial < 7) {
								// Link to validate definitely instance
								print '<p>';
								print '<a href="'.$_SERVER["PHP_SELF"].'?mode=instances&action=validatefreemode&contractid='.$contract->id.'#contractid'.$contract->id.'" class="btn btn-warning wordbreak">';
								print $langs->trans("ConfirmInstanceValidationToRestoreInstance");
								print '</a>';
								print '</p>';
							}
						}
						print '
							</div>
						';
					}
				} else {
					// Trial expired
					$atleastonecontractwithtrialended++;

					$messageforinstance[$contract->ref_customer] = 1;

					//$firstline = reset($contract->lines);
					print '
						<!-- XDaysAfterEndOfTrial -->
						<div class="note note-warning">
						<h4 class="block">'.str_replace('{s1}', '<span class="wordbreak">'.$contract->ref_customer.'</span>', $langs->trans("XDaysAfterEndOfTrial", '{s1}', abs($delayindays))).' !</h4>';
					if ($mode != 'registerpaymentmode' && $contract->total_ht > 0) {
						print '
							<p>
							<a href="'.$_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode).'" class="btn btn-warning wordbreak">';
						print $langs->trans("AddAPaymentModeToRestoreInstance");
						print '</a>
							</p>';
					}
					print '
						</div>
					';
				}
			} else {
				if (!preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
					// If not a redirect instance
					if ($delaybeforeendoftrial > 0) {
						// Trial not yet expired
						if ($contract->array_options['options_deployment_status'] != 'processing') {
							//$firstline = reset($contract->lines);
							if ($contract->total_ht > 0) {
								print '
									<!-- XDaysBeforeEndOfTrialPaymentModeSet -->
									<div class="note note-info">
									<h4 class="block">'.$langs->trans("XDaysBeforeEndOfTrialPaymentModeSet", abs($delayindays), $contract->ref_customer).'</h4>
									</div>
								';
							} else {
								print '
									<!-- XDaysBeforeEndOfTrialForAlwaysFreeInstance -->
									<div class="note note-info">
									<h4 class="block">'.$langs->trans("XDaysBeforeEndOfTrialForAlwaysFreeInstance", abs($delayindays), $contract->ref_customer).'</h4>
									</div>
								';
							}
						}
					} else {
						// Trial expired
						$atleastonecontractwithtrialended++;
						if ($contract->total_ht > 0) {
							print '
								<!-- XDaysAfterEndOfTrialPaymentModeSet -->
								<div class="note note-info">
								<h4 class="block">'.$langs->trans("XDaysAfterEndOfTrialPaymentModeSet", $contract->ref_customer, abs($delayindays)).'</h4>
								</div>
							';
						} else {
							print '
								<!-- XDaysAfterEndOfTrialForAlwaysFreeInstance -->
								<div class="note note-info">
								<h4 class="block">'.$langs->trans("XDaysAfterEndOfTrialForAlwaysFreeInstance", $contract->ref_customer, abs($delayindays)).'</h4>
								</div>
							';
						}
					}
				}
			}
		}

		if ($isASuspendedContract) {
			if (empty($messageforinstance[$contract->ref_customer])		// If warning for 'expired trial' not already shown
				&& $delaybeforeendoftrial <= 0) {							// If trial has expired
				if (empty($contract->array_options['options_suspendmaintenance_message']) || !preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
					$delayafterexpiration = ($now - $expirationdate);
					$delayindays = round($delayafterexpiration / 3600 / 24);
					$delaybeforeundeployment = max(0, ($atleastonepaymentmode ? getDolGlobalInt('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT') : getDolGlobalInt('SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT')) - $delayindays);

					print '<!-- XDaysAfterEndOfPeriodInstanceSuspended '.$delayindays.' -->'."\n";
					print '<div class="note note-warning">'."\n";
					print '		<h4 class="block">'."\n";
					if ($delayindays >= 0) {
						print $langs->trans("XDaysAfterEndOfPeriodInstanceSuspended", $contract->ref_customer, abs($delayindays), $delaybeforeundeployment);
					} else {
						print $langs->trans("BeforeEndOfPeriodInstanceSuspended", $contract->ref_customer, $delaybeforeundeployment);
					}
					if (empty($atleastonepaymentmode)) {
						print '<br><a href="'.$_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode).'">'.$langs->trans("AddAPaymentModeToRestoreInstance").'</a>';
					} elseif (GETPOST('mode', 'alpha') != 'registerpaymentmode') {
						print '<br>'.$langs->trans("IfInstanceWaSuspendedBecauseOrPaymentErrors").' : <a href="'.$_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode).'">'.$langs->trans("FixPaymentModeToRestoreInstance").'</a>';
					}
					print '     </h4>'."\n";
					print '</div>'."\n";
				}
			}
		} elseif ($isAPayingContract && $expirationdate > 0) {
			$delaybeforeexpiration = ($expirationdate - $now);
			$delayindays = round($delaybeforeexpiration / 3600 / 24);

			if ($delayindays < 0) {	// Expired
				$hasOpenInvoice = sellyoursaasHasOpenInvoices($contract);
				if (! $hasOpenInvoice) {	// If there is open invoices, having end date not renewed is normal, so we do not show warning.
					print '
							<!-- XDaysAfterEndOfPeriodPaymentModeSet -->
							<div class="note note-warning">
							<h4 class="block">'.$langs->trans("XDaysAfterEndOfPeriodPaymentModeSet", $contract->ref_customer, abs($delayindays)).'</h4>
							</div>
						';
				}
			}
		}
	}

	// Test if there is a payment error, if yes, ask to fix payment data
	$sql = 'SELECT f.rowid, ee.code, ee.label, ee.extraparams FROM '.MAIN_DB_PREFIX.'facture as f';
	$sql.= ' INNER JOIN '.MAIN_DB_PREFIX."actioncomm as ee ON ee.fk_element = f.rowid AND ee.elementtype = 'invoice'";
	$sql.= " AND (ee.code LIKE 'AC_PAYMENT_%_KO' OR ee.label = 'Cancellation of payment by the bank')";		// See also into sellyoursaasIsPaymentKo
	$sql.= ' WHERE f.fk_soc = '.$mythirdpartyaccount->id.' AND f.paye = 0';
	$sql.= ' ORDER BY ee.datep DESC';

	$resql = $db->query($sql);
	if ($resql) {
		$num_rows = $db->num_rows($resql);
		$i=0;
		if ($num_rows) {
			$atleastonepaymentinerroronopeninvoice++;

			$obj = $db->fetch_object($resql);
			$labelerror = $obj->extraparams;
			if (empty($labelerror)) $labelerror=$langs->trans("UnknownError");

			// There is at least one payment error
			if ($obj->label == 'Cancellation of payment by the bank') {
				print '
						<div class="note note-warning note-cancelbythebank">
						<h4 class="block">'.$langs->trans("SomeOfYourPaymentFailed", $langs->transnoentitiesnoconv('PaymentChargedButReversedByBank')).'</h4>
						</div>
					';
			} elseif (preg_match('/PAYMENT_ERROR_INSUFICIENT_FUNDS/i', $obj->extraparams)) {
				print '
						<div class="note note-warning note-insuficientfunds">
						<h4 class="block">'.$langs->trans("SomeOfYourPaymentFailedINSUFICIENT_FUNDS", $labelerror).'</h4>
						</div>
					';
			} else {
				print '
						<div class="note note-warning note-someofyourpaymentfailed">
						<h4 class="block">'.$langs->trans("SomeOfYourPaymentFailed", $labelerror).'</h4>
						</div>
					';
			}
		}
	} else dol_print_error($db);
}


// Include mode with php template
if (! empty($mode)) {
	$fullpath = dol_buildpath("/sellyoursaas/myaccount/tpl/".$mode.".tpl.php");
	if (file_exists($fullpath)) {
		include $fullpath;
	}
}


print '
	</div>


	<!-- Bootstrap core JavaScript for menu popup
	================================================== -->
	<!-- Placed at the end of the document so the pages load faster -->
	<script src="dist/js/popper.min.js"></script>
	<script src="dist/js/bootstrap.min.js"></script>
';


llxFooter();

$db->close();
