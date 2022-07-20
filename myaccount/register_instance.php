<?php
/* Copyright (C) 2017 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 * Call can be done with
 * reusecontractid=id of contract
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
if (! defined("NOLOGIN"))        define("NOLOGIN", '1');				    // If this page is public (can be called outside logged session)
if (! defined('NOIPCHECK'))      define('NOIPCHECK', '1');				// Do not check IP defined into conf $dolibarr_main_restrict_ip
if (! defined("MAIN_LANG_DEFAULT") && empty($_GET['lang'])) define('MAIN_LANG_DEFAULT', 'auto');
if (! defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname($_SERVER['PHP_SELF']).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) != 'cli') {
	// Add specific definition to allow a dedicated session management
	include './mainmyaccount.inc.php';
} else {
	// Add specific definition to allow a dedicated session management
	include $path.'mainmyaccount.inc.php';
}

// Load Dolibarr environment
$res=0;
if (substr($sapi_type, 0, 3) != 'cli') {
	// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
	if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
	// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
	$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
	while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
	if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
	if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
	// Try main.inc.php using relative path
	if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
	if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
} else {
	// Try master.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
	$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
	while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
	if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/master.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/master.inc.php";
	if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/master.inc.php")) $res=@include dirname(substr($tmp, 0, ($i+1)))."/master.inc.php";
	// Try master.inc.php using relative path
	if (! $res && file_exists("./master.inc.php")) $res=@include "./master.inc.php";
	if (! $res && file_exists("../master.inc.php")) $res=@include "../master.inc.php";
	if (! $res && file_exists("../../master.inc.php")) $res=@include "../../master.inc.php";
	if (! $res && file_exists("../../../master.inc.php")) $res=@include "../../../master.inc.php";
	if (! $res) die("Include of master fails");
	// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
	// $user is created but empty.
}
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/cron/class/cronjob.class.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');
dol_include_once('/sellyoursaas/class/packages.class.php');
dol_include_once('/sellyoursaas/class/sellyoursaasutils.class.php');
dol_include_once('/sellyoursaas/class/blacklistip.class.php');
dol_include_once('/sellyoursaas/class/whitelistip.class.php');

// Re set variables specific to new environment
$conf->global->SYSLOG_FILE_ONEPERSESSION=1;


//$langs=new Translate('', $conf);
//$langs->setDefaultLang(GETPOST('lang', 'aZ09')?GETPOST('lang', 'aZ09'):'auto');
$langs->loadLangs(array("main", "companies", "sellyoursaas@sellyoursaas", "errors"));

if ($langs->defaultlang == 'en_US') {
	$langsen = $langs;
} else {
	$langsen=new Translate('', $conf);
	$langsen->setDefaultLang('en_US');
	$langsen->loadLangs(array("main", "companies", "sellyoursaas@sellyoursaas", "errors"));
}


// Force user
if (empty($user->id)) {
	$user->fetch($conf->global->SELLYOURSAAS_ANONYMOUSUSER);
	// Set $user to the anonymous user
	if (empty($user->id)) {
		dol_print_error_email('SETUPANON', 'Error setup of module not complete or wrong. Missing the anonymous user.', null, 'alert alert-error');
		exit(-1);
	}

	$user->getrights();
}

$action = GETPOST('action', 'alpha');
$orgname = dol_trunc(ucfirst(trim(GETPOST('orgName', 'alpha'))), 250, 'right', 'UTF-8', 1);
$email = dol_trunc(trim(GETPOST('username', 'alpha')), 255, 'right', 'UTF-8', 1);
$domainemail = preg_replace('/^.*@/', '', $email);
$password = dol_trunc(trim(GETPOST('password', 'alpha')), 128, 'right', 'UTF-8', 1);
$password2 = dol_trunc(trim(GETPOST('password2', 'alpha')), 128, 'right', 'UTF-8', 1);
$country_code = trim(GETPOST('address_country', 'alpha'));
$sldAndSubdomain = trim(GETPOST('sldAndSubdomain', 'alpha'));
$tldid = trim(GETPOST('tldid', 'alpha'));
$optinmessages = (GETPOST('optinmessages', 'aZ09') == '1' ? 1 : 0);
$checkbosnonprofitorga = (GETPOST('checkbosnonprofitorga', 'aZ09') == '1' ? 1 : 0);

$origin = GETPOST('origin', 'aZ09');
$partner=GETPOST('partner', 'int');
$partnerkey=GETPOST('partnerkey', 'alpha');		// md5 of partner name_alias
$customurl = '';

$fromsocid=GETPOST('fromsocid', 'int');
$reusecontractid = GETPOST('reusecontractid', 'int');
$reusesocid = GETPOST('reusesocid', 'int');
$disablecustomeremail = GETPOST('disablecustomeremail', 'alpha');

$service=GETPOST('service', 'int');
$productid=GETPOST('service', 'int');
$plan=GETPOST('plan', 'alpha');
$productref=(GETPOST('productref', 'alpha')?GETPOST('productref', 'alpha'):($plan?$plan:''));
$extcss=GETPOST('extcss', 'alpha');

// If ran from command line
if (substr($sapi_type, 0, 3) == 'cli') {
	$productref = $argv[1];
	$instancefullname = $argv[2];
	$instancefullnamearray = explode('.', $instancefullname);
	$sldAndSubdomain = $instancefullnamearray[0];
	unset($instancefullnamearray[0]);
	$tldid = '.'.join('.', $instancefullnamearray);
	$password = $argv[3];
	$reusesocid = $argv[4];
	$customurl = $argv[5];
	if (empty($productref) || empty($sldAndSubdomain) || empty($tldid) || empty($password) || empty($reusesocid)) {
		print "***** ".$script_file." *****\n";
		print "Create an instance from command line. Run this script from the master server. Note: No email are sent to customer.\n";
		print "Usage:   ".$script_file." SERVICETODEPLOY shortnameinstance.sellyoursaasdomain password CustomerID [custom_domain]\n";
		print "Example: ".$script_file." SERVICETODEPLOY myinstance.withX.mysellyoursaasdomain.com mypassword 123 [myinstance.withold.mysellyoursaasdomain.com]\n";
		exit(-1);
	}

	$CERTIFFORCUSTOMDOMAIN = $customurl;
	if ($CERTIFFORCUSTOMDOMAIN &&
		(! file_exists($conf->sellyoursaas->dir_output.'/crt/'.$CERTIFFORCUSTOMDOMAIN.'.crt') || ! file_exists($conf->sellyoursaas->dir_output.'/crt/'.$CERTIFFORCUSTOMDOMAIN.'.key') || ! file_exists($conf->sellyoursaas->dir_output.'/crt/'.$CERTIFFORCUSTOMDOMAIN.'-intermediate.crt'))) {
		print "***** ".$script_file." *****\n";
		print "Create an instance from command line. Run this script from the master server. Note: No email are sent to customer.\n";
		print "Usage:   ".$script_file." SERVICETODEPLOY shortnameinstance.mysellyoursaasdomain.com password CustomerID [custom_domain]\n";
		print 'Error:   A certificate file '.$conf->sellyoursaas->dir_output.'/crt/'.$CERTIFFORCUSTOMDOMAIN.'(.crt|.key|-intermediate.crt) not found.'."\n";
		exit(-1);
	}
	$password2 = $password;
	$disablecustomeremail = 1;
}


if (substr($sapi_type, 0, 3) != 'cli') {
	$remoteip = getUserRemoteIP();
} else {
	$remoteip = '127.0.0.1';
}

$domainname = preg_replace('/^\./', '', $tldid);

// Sanitize $sldAndSubdomain. Remove start and end -
$sldAndSubdomain = preg_replace('/^\-+/', '', $sldAndSubdomain);
$sldAndSubdomain = preg_replace('/\-+$/', '', $sldAndSubdomain);
// Avoid uppercase letters
$sldAndSubdomain = strtolower($sldAndSubdomain);

$tmpproduct = new Product($db);
$tmppackage = new Packages($db);

// Load main product
if (empty($reusecontractid) && $productref != 'none') {
	$result = $tmpproduct->fetch($productid, $productref, '', '', 1, 1, 1);
	if (empty($tmpproduct->id)) {
		print 'Service/Plan (Product id / ref) '.$productid.' / '.$productref.' was not found.'."\n";
		exit(-1);
	}
	// We have the main product, we are searching the package
	if (empty($tmpproduct->array_options['options_package'])) {
		print 'Service/Plan (Product id / ref) '.$tmpproduct->id.' / '.$productref.' has no package defined on it.'."\n";
		exit(-2);
	}
	// We have the main product, we are searching the duration
	if (empty($tmpproduct->duration_value) || empty($tmpproduct->duration_unit)) {
		print 'Service/Plan name (Product ref) '.$productref.' has no default duration'."\n";
		exit(-3);
	}

	$tmppackage->fetch($tmpproduct->array_options['options_package']);
	if (empty($tmppackage->id)) {
		print 'Package with id '.$tmpproduct->array_options['options_package'].' was not found.'."\n";
		exit(-4);
	}
}

$freeperioddays = $tmpproduct->array_options['options_freeperioddays'];

$now = dol_now();


/*
 * Actions
 */

//print "partner=".$partner." productref=".$productref." orgname = ".$orgname." email=".$email." password=".$password." password2=".$password2." country_code=".$country_code." remoteip=".$remoteip." sldAndSubdomain=".$sldAndSubdomain." tldid=".$tldid;

// Back to url
$newurl=preg_replace('/register_instance\.php/', 'register.php', $_SERVER["PHP_SELF"]);

if ($reusecontractid) {		// When we use the "Restart deploy" after error from account backoffice
	$newurl=preg_replace('/register_instance/', 'index', $newurl);
	if (! preg_match('/\?/', $newurl)) $newurl.='?';
	$newurl.='&mode=instances';
	$newurl.='&reusecontractid='.$reusecontractid;
} elseif ($reusesocid) {		// When we use the "Add another instance" from myaccount dashboard
	if (empty($productref) && ! empty($service)) {	// if $productref is defined, we have already load the $tmpproduct
		$tmpproduct = new Product($db);
		$tmpproduct->fetch($service, '', '', '', 1, 1, 1);
		$productref = $tmpproduct->ref;
	}

	$newurl=preg_replace('/register_instance/', 'index', $newurl);
	if (! preg_match('/\?/', $newurl)) $newurl.='?';
	$newurl.='&reusesocid='.$reusesocid;
	$newurl.='&mode='.(GETPOST('mode', 'alpha') == 'mycustomerinstances' ? 'mycustomerinstances': 'instances');
	if (! preg_match('/sldAndSubdomain/i', $sldAndSubdomain)) $newurl.='&sldAndSubdomain='.urlencode($sldAndSubdomain);
	if (! preg_match('/tldid/i', $tldid)) $newurl.='&tldid='.urlencode($tldid);
	if (! preg_match('/service/i', $newurl)) $newurl.='&service='.urlencode($service);
	if (! preg_match('/partner/i', $newurl)) $newurl.='&partner='.urlencode($partner);
	if (! preg_match('/partnerkey/i', $newurl)) $newurl.='&partnerkey='.urlencode($partnerkey);		// md5 of partner name alias
	if (! preg_match('/origin/i', $newurl)) $newurl.='&origin='.urlencode($origin);
	if (! preg_match('/disablecustomeremail/i', $newurl)) $newurl.='&disablecustomeremail='.urlencode($disablecustomeremail);

	if ($reusesocid < 0) { // -1, the thirdparty was not selected
		// Return to dashboard, the only page where the customer is requested.
		$newurl=preg_replace('/register/', 'index', $newurl);
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Customer")), null, 'errors');
			header("Location: ".$newurl.'#addanotherinstance');
		} else {
			print $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Customer"))."\n";
		}
		exit(-10);
	}

	if ($productref != 'none' && empty($sldAndSubdomain)) {
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("NameForYourApplication")), null, 'errors');
			header("Location: ".$newurl);
		} else {
			print $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("NameForYourApplication"))."\n";
		}
		exit(-11);
	}
	if ($productref != 'none' && strlen($sldAndSubdomain) >= 29) {
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("ErrorFieldTooLong", $langs->transnoentitiesnoconv("NameForYourApplication")), null, 'errors');
			header("Location: ".$newurl);
		} else {
			print $langs->trans("ErrorFieldTooLong", $langs->transnoentitiesnoconv("NameForYourApplication"))."\n";
		}
		exit(-12);
	}
	if ($productref != 'none' && ! preg_match('/^[a-zA-Z0-9\-]+$/', $sldAndSubdomain)) {		// Only a-z A-Z 0-9 and - . Note: - is removed by javascript part of register page.
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("ErrorOnlyCharAZAllowedFor", $langs->transnoentitiesnoconv("NameForYourApplication")), null, 'errors');
			header("Location: ".$newurl);
		} else {
			print $langs->trans("ErrorOnlyCharAZAllowedFor", $langs->transnoentitiesnoconv("NameForYourApplication"))."\n";
		}
		exit(-13);
	}
	if ($productref != 'none' && empty($tldid)) {
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Subdomain")), null, 'errors');
			header("Location: ".$newurl);
		} else {
			print $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Subdomain"))."\n";
		}
		exit(-14);
	}
	if (empty($password) || empty($password2)) {
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Password")), null, 'errors');
			header("Location: ".$newurl);
		} else {
			print $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Password"))."\n";
		}
		exit(-15);
	}
	if ($password != $password2) {
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("ErrorPasswordMismatch"), null, 'errors');
			header("Location: ".$newurl);
		} else {
			print $langs->trans("ErrorPasswordMismatch")."\n";
		}
		exit(-16);
	}
} else { // When we deploy from the register.php page
	// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
	$hookmanager->initHooks(array('sellyoursaas-register-instance'));

	if (! preg_match('/\?/', $newurl)) $newurl.='?';
	if (! preg_match('/orgName/i', $newurl)) $newurl.='&orgName='.urlencode($orgname);
	if (! preg_match('/username/i', $newurl)) $newurl.='&username='.urlencode($email);
	if (! preg_match('/address_country/i', $newurl)) $newurl.='&address_country='.urlencode($country_code);
	if (! preg_match('/sldAndSubdomain/i', $sldAndSubdomain)) $newurl.='&sldAndSubdomain='.urlencode($sldAndSubdomain);
	if (! preg_match('/tldid/i', $tldid)) $newurl.='&tldid='.urlencode($tldid);
	if (! preg_match('/plan/i', $newurl)) $newurl.='&plan='.urlencode($productref);
	if (! preg_match('/partner/i', $newurl)) $newurl.='&partner='.urlencode($partner);
	if (! preg_match('/partnerkey/i', $newurl)) $newurl.='&partnerkey='.urlencode($partnerkey);		// md5 of partner name alias
	if (! preg_match('/origin/i', $newurl)) $newurl.='&origin='.urlencode($origin);

	$parameters = array('tldid' => $tldid, 'username' => $email, 'sldAndSubdomain' => $sldAndSubdomain);
	$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
	if ($reshook < 0) {
		setEventMessages($hookmanager->error, null, 'errors');
		header("Location: ".$newurl);
		exit(-20);
	}

	if ($productref != 'none' && empty($sldAndSubdomain)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("NameForYourApplication")), null, 'errors');
		header("Location: ".$newurl);
		exit(-21);
	}
	if ($productref != 'none' && ! preg_match('/^[a-zA-Z0-9\-]+$/', $sldAndSubdomain)) {
		setEventMessages($langs->trans("ErrorOnlyCharAZAllowedFor", $langs->transnoentitiesnoconv("NameForYourApplication")), null, 'errors');
		header("Location: ".$newurl);
		exit(-22);
	}
	if (empty($orgname)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("NameOfCompany")), null, 'errors');
		header("Location: ".$newurl);
		exit(-23);
	}
	if (empty($tldid)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Domain")), null, 'errors');
		header("Location: ".$newurl);
		exit(-24);
	}
	if (! preg_match('/[a-zA-Z0-9][a-zA-Z0-9]/', $orgname)) {
		setEventMessages($langs->trans("ErrorFieldMustHaveXChar", $langs->transnoentitiesnoconv("NameOfCompany"), 2), null, 'errors');
		header("Location: ".$newurl);
		exit(-25);
	}
	if (empty($email)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Email")), null, 'errors');
		header("Location: ".$newurl);
		exit(-26);
	}
	if (! isValidEmail($email)) {
		setEventMessages($langs->trans("ErrorBadEMail", $email), null, 'errors');
		header("Location: ".$newurl);
		exit(-27);
	}
	if (function_exists('isValidMXRecord') && isValidMXRecord($domainemail) == 0) {
		dol_syslog("Try to register with a bad value for email domain : ".$domainemail);
		setEventMessages($langs->trans("BadValueForDomainInEmail", $domainemail, $conf->global->SELLYOURSAAS_MAIN_EMAIL), null, 'errors');
		header("Location: ".$newurl);
		exit(-28);
	}

	/* No more used, use instead SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED and SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_BANNED
	if (! empty($conf->global->SELLYOURSAAS_EMAIL_ADDRESSES_BANNED)) {
		$listofbanned = explode(",", $conf->global->SELLYOURSAAS_EMAIL_ADDRESSES_BANNED);
		if (! empty($listofbanned)) {
			foreach ($listofbanned as $banned) {
				if (preg_match('/'.preg_quote($banned, '/').'/i', $email)) {
					setEventMessages($langs->trans("ErrorEMailAddressBannedForSecurityReasons", $email), null, 'errors');
					header("Location: ".$newurl);
					exit(-29);
				}
			}
		}
	}
	*/
	if (! empty($conf->global->SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ENABLED) && ! empty($conf->global->SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_API_KEY)) {
		$allowed = false;
		$disposable = false;
		$allowedemail = (! empty($conf->global->SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ALLOWED) ? json_decode($conf->global->SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ALLOWED, true) : array());
		$bannedemail = (! empty($conf->global->SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_BANNED) ? json_decode($conf->global->SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_BANNED, true) : array());
		$parts = explode("@", $email);
		$domaintocheck = $parts[1];

		// Check cache of domain already check and allowed
		if (! empty($allowedemail)) {
			foreach ($allowedemail as $alloweddomainname) {
				if ($alloweddomainname == $domaintocheck) {
					$allowed = true;
					break;
				}
			}
		}

		// If not found in allowed database
		if ($allowed === false) {
			// Check cache of domain already check and banned
			if (! empty($bannedemail)) {
				foreach ($bannedemail as $banneddomainname) {
					if ($banneddomainname == $domaintocheck) {
						$disposable = true;
						break;
					}
				}
			}

			// Check in API Block Disposable E-mail database
			if ($disposable === false) {
				$emailtowarn = $conf->global->MAIN_INFO_SOCIETE_MAIL;
				$apikey = $conf->global->SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_API_KEY;

				// Check if API account and credit are ok
				$request = "https://status.block-disposable-email.com/status/?apikey=".$apikey;
				$result = file_get_contents($request);
				$resultData = json_decode($result, true);

				if ($resultData["request_status"] == "ok" && $resultData["apikeystatus"] == "active" && $resultData["credits"] > "0") {
					$request = 'https://api.block-disposable-email.com/easyapi/json/'.$apikey.'/'.$domaintocheck;
					$result = file_get_contents($request);
					$resultData = json_decode($result, true);

					if ($resultData["request_status"] == "success") {
						require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

						// domain is allowed
						if ($resultData["domain_status"] == "ok") {
							array_push($allowedemail, $domaintocheck);
							dolibarr_set_const($db, 'SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_ALLOWED', json_encode($allowedemail), 'chaine', 0, '', $conf->entity);
						} elseif ($resultData["domain_status"] == "block") {
							array_push($bannedemail, $domaintocheck);
							dolibarr_set_const($db, 'SELLYOURSAAS_BLOCK_DISPOSABLE_EMAIL_BANNED', json_encode($bannedemail), 'chaine', 0, '', $conf->entity);
							setEventMessages($langs->trans("ErrorEMailAddressBannedForSecurityReasons"), null, 'errors');
							header("Location: ".$newurl);
							exit(-40);
						} else {
							setEventMessages($langs->trans("ErrorTechnicalErrorOccurredPleaseContactUsByEmail", $emailtowarn), null, 'errors');
							header("Location: ".$newurl);
							exit(-41);
						}
					} else {
						setEventMessages($langs->trans("ErrorTechnicalErrorOccurredPleaseContactUsByEmail", $emailtowarn), null, 'errors');
						header("Location: ".$newurl);
						exit(-42);
					}
				} else {
					setEventMessages($langs->trans("ErrorTechnicalErrorOccurredPleaseContactUsByEmail", $emailtowarn), null, 'errors');
					header("Location: ".$newurl);
					exit(-43);
				}
			} else {
				setEventMessages($langs->trans("ErrorEMailAddressBannedForSecurityReasons"), null, 'errors');
				header("Location: ".$newurl);
				exit(-44);
			}
		}
	}
	if (empty($password) || empty($password2)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Password")), null, 'errors');
		header("Location: ".$newurl);
		exit(-50);
	}
	if ($password != $password2) {
		setEventMessages($langs->trans("ErrorPasswordMismatch"), null, 'errors');
		header("Location: ".$newurl);
		exit(-55);
	}
}



/*
 * View
 */

$errormessages = array();

//print '<center>'.$langs->trans("PleaseWait").'</center>';		// Message if redirection after this page fails


$error = 0;

dol_syslog("Start view of register_instance (reusecontractid = ".$reusecontractid.", reusesocid = ".$reusesocid.", fromsocid = ".$fromsocid.", sldAndSubdomain = ".$sldAndSubdomain.")");


if (empty($remoteip)) {
	dol_syslog("InstanceCreationBlockedForSecurityPurpose: empty remoteip", LOG_WARNING);	// Should not happen, ip should always be defined.
	$emailtowarn = $conf->global->MAIN_INFO_SOCIETE_MAIL;
	if (substr($sapi_type, 0, 3) != 'cli') {
		setEventMessages($langs->trans("InstanceCreationBlockedForSecurityPurpose", $emailtowarn, 'Unknown remote IP'), null, 'errors');
		header("Location: ".$newurl);
	} else {
		print $langs->trans("InstanceCreationBlockedForSecurityPurpose", $emailtowarn, 'Unknown remote IP')."\n";
	}
	exit(-60);
}

$tmpblacklistip = new Blacklistip($db);
$tmparrayblacklist = $tmpblacklistip->fetchAll('', '', 1000, 0, array('status'=>1));
if (is_numeric($tmparrayblacklist) && $tmparrayblacklist < 0) {
	echo "Erreur: failed to get blacklistip elements.\n";
	exit(-61);
}
$tmpwhitelistip = new Whitelistip($db);
$tmparraywhitelist = $tmpwhitelistip->fetchAll('', '', 1000, 0, array('status'=>1));
if (is_numeric($tmparraywhitelist) && $tmparraywhitelist < 0) {
	echo "Erreur: failed to get whitelistip elements.\n";
	exit(-61);
}

$whitelisted = false;
if (!empty($tmparraywhitelist)) {
	foreach ($tmparraywhitelist as $val) {
		if ($val->content == $remoteip) {
			$whitelisted = true;
			break;
		}
	}
}

if (!$whitelisted && !empty($tmparrayblacklist)) {
	foreach ($tmparrayblacklist as $val) {
		if ($val->content == $remoteip) {
			dol_syslog("InstanceCreationBlockedForSecurityPurpose: remoteip ".$remoteip." is in blacklistip", LOG_WARNING);
			$emailtowarn = $conf->global->MAIN_INFO_SOCIETE_MAIL;
			if (substr($sapi_type, 0, 3) != 'cli') {
				setEventMessages($langs->trans("InstanceCreationBlockedForSecurityPurpose", $emailtowarn, 'IP already included for legal action'), null, 'errors');
				header("Location: ".$newurl);
			} else {
				print $langs->trans("InstanceCreationBlockedForSecurityPurpose", $emailtowarn, 'IP already included for legal action')."\n";
			}
			exit(-62);
		}
	}
}

// TODO Move other check on abuse here



$contract = new Contrat($db);
if ($reusecontractid) {
	// Get contract
	$result = $contract->fetch($reusecontractid);
	if ($result < 0) {
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("NotFound"), null, 'errors');
			header("Location: ".$newurl);
		} else {
			print $langs->trans("NotFound")."\n";
		}
		exit(-65);
	}

	// Get tmppackage
	foreach ($contract->lines as $keyline => $line) {
		$tmpproduct = new Product($db);
		if ($line->fk_product > 0) {
			$tmpproduct->fetch($line->fk_product, '', '', '', 1, 1, 1);
			if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
				if ($tmpproduct->array_options['options_package'] > 0) {
					$tmppackage->fetch($tmpproduct->array_options['options_package']);
					$freeperioddays = $tmpproduct->array_options['options_freeperioddays'];
					break;
				} else {
					dol_syslog("Error: ID of package not defined on productwith ID ".$line->fk_product);
				}
			}
		}
	}

	$contract->fetch_thirdparty();

	$tmpthirdparty = $contract->thirdparty;

	// Check thirdparty is same than the one in session
	if (substr($sapi_type, 0, 3) != 'cli') {
		$thirdpartyidinsession = $_SESSION['dol_loginsellyoursaas'];
		if ($thirdpartyidinsession != $tmpthirdparty->id) {
			dol_syslog("Instance creation blocked for ".$remoteip." - Try to create instance for thirdparty id = ".$tmpthirdparty->id." when id in session is ".$thirdpartyidinsession);
			if (substr($sapi_type, 0, 3) != 'cli') {
				setEventMessages($langs->trans("ErrorInvalidReuseIDSurelyAHackAttempt"), null, 'errors');
				header("Location: index.php");
			} else {
				print $langs->trans("ErrorInvalidReuseIDSurelyAHackAttempt")."\n";
			}
			exit(-66);
		}
	}

	$email = $tmpthirdparty->email;
	$password = substr(getRandomPassword(true, array('I')), 0, 9);		// Password is no more known (no more in memory) when we make a retry/restart of deploy

	$generatedunixhostname = $contract->array_options['options_hostname_os'];
	$generatedunixlogin = $contract->array_options['options_username_os'];
	$generatedunixpassword = $contract->array_options['options_password_os'];
	$generateddbhostname = $contract->array_options['options_hostname_db'];
	$generateddbname = $contract->array_options['options_database_db'];
	$generateddbport = ($contract->array_options['options_port_db']?$contract->array_options['options_port_db']:3306);
	$generateddbusername = $contract->array_options['options_username_db'];
	$generateddbpassword = $contract->array_options['options_password_db'];

	$tmparray = explode('.', $contract->ref_customer, 2);
	$sldAndSubdomain = $tmparray[0];
	$domainname = $tmparray[1];
	$tldid = '.'.$domainname;
	$fqdninstance = $sldAndSubdomain.'.'.$domainname;
} else {
	// Check number of instance with same IP deployed (Rem: for partners, ip are the one of their customer)
	$MAXDEPLOYMENTPERIP = (empty($conf->global->SELLYOURSAAS_MAXDEPLOYMENTPERIP) ? 20 : $conf->global->SELLYOURSAAS_MAXDEPLOYMENTPERIP);
	$MAXDEPLOYMENTPERIPVPN = (empty($conf->global->SELLYOURSAAS_MAXDEPLOYMENTPERIPVPN) ? 2 : $conf->global->SELLYOURSAAS_MAXDEPLOYMENTPERIPVPN);

	$nbofinstancewithsameip=-1;
	$select = 'SELECT COUNT(*) as nb FROM '.MAIN_DB_PREFIX."contrat_extrafields WHERE deployment_ip = '".$db->escape($remoteip)."'";
	$select.= " AND deployment_status IN ('processing', 'done')";
	$resselect = $db->query($select);
	if ($resselect) {
		$objselect = $db->fetch_object($resselect);
		if ($objselect) {
			$nbofinstancewithsameip = $objselect->nb;
		}
	}
	dol_syslog("nbofinstancewithsameip = ".$nbofinstancewithsameip." for ip ".$remoteip." (must be lower or equal than ".$MAXDEPLOYMENTPERIP." except if ip is 127.0.0.1)");
	if ($remoteip != '127.0.0.1' && (($nbofinstancewithsameip < 0) || ($nbofinstancewithsameip > $MAXDEPLOYMENTPERIP))) {
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("TooManyInstancesForSameIp"), null, 'errors');
			header("Location: ".$newurl);
		} else {
			print $langs->trans("TooManyInstancesForSameIp")."\n";
		}
		exit(-70);
	}

	$nbofinstancewithsameipvpn=-1;
	$select = 'SELECT COUNT(*) as nb FROM '.MAIN_DB_PREFIX."contrat_extrafields WHERE deployment_ip = '".$db->escape($remoteip)."' AND deployment_vpn_proba = 1";
	$select.= " AND deployment_status IN ('processing', 'done')";
	$resselect = $db->query($select);
	if ($resselect) {
		$objselect = $db->fetch_object($resselect);
		if ($objselect) {
			$nbofinstancewithsameipvpn = $objselect->nb;
		}
	}
	dol_syslog("nbofinstancewithsameipvpn = ".$nbofinstancewithsameipvpn." for ip ".$remoteip." (must be lower or equal than ".$MAXDEPLOYMENTPERIPVPN." except if ip is 127.0.0.1)");
	if ($remoteip != '127.0.0.1' && (($nbofinstancewithsameipvpn < 0) || ($nbofinstancewithsameipvpn > $MAXDEPLOYMENTPERIPVPN))) {
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("TooManyInstancesForSameIp"), null, 'errors');
			header("Location: ".$newurl);
		} else {
			print $langs->trans("TooManyInstancesForSameIp")."\n";
		}
		exit(-70);
	}

	// Check number of instance with same IP on same hour
	$MAXDEPLOYMENTPERIPPERHOUR = (empty($conf->global->SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR) ? 5 : $conf->global->SELLYOURSAAS_MAXDEPLOYMENTPERIPPERHOUR);

	$nbofinstancewithsameip=-1;
	$select = 'SELECT COUNT(*) as nb FROM '.MAIN_DB_PREFIX."contrat_extrafields WHERE deployment_ip = '".$db->escape($remoteip)."'";
	$select.= " AND deployment_date_start > '".$db->idate(dol_now() - 3600)."'";
	$resselect = $db->query($select);
	if ($resselect) {
		$objselect = $db->fetch_object($resselect);
		if ($objselect) $nbofinstancewithsameip = $objselect->nb;
	}
	dol_syslog("nbofinstancewithsameipperhour = ".$nbofinstancewithsameip." for ip ".$remoteip." (must be lower or equal than ".$MAXDEPLOYMENTPERIPPERHOUR." except if ip is 127.0.0.1)");
	if ($remoteip != '127.0.0.1' && (($nbofinstancewithsameip < 0) || ($nbofinstancewithsameip > $MAXDEPLOYMENTPERIP))) {
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("TooManyInstancesForSameIpThisHour"), null, 'errors');
			header("Location: ".$newurl);
		} else {
			print $langs->trans("TooManyInstancesForSameIpThisHour")."\n";
		}
		exit(-71);
	}

	// Check if some deployment are already in process and ask to wait
	$MAXDEPLOYMENTPARALLEL = (empty($conf->global->SELLYOURSAAS_MAXDEPLOYMENTPARALLEL) ? 2 : $conf->global->SELLYOURSAAS_MAXDEPLOYMENTPARALLEL);

	$tmp=explode('.', $sldAndSubdomain.$tldid, 2);
	$sldAndSubdomain=$tmp[0];
	$domainname=$tmp[1];
	$sellyoursaasutils = new SellYourSaasUtils($db);
	$serverdeployement = $sellyoursaasutils->getRemoteServerDeploymentIp($domainname);

	$nbofinstanceindeployment = -1;
	$select = 'SELECT COUNT(*) as nb FROM '.MAIN_DB_PREFIX."contrat_extrafields";
	$select .= " WHERE deployment_host = '".$db->escape($serverdeployement)."'";
	$select .= " AND deployment_status IN ('processing')";
	$select .= " AND deployment_date_start >= DATE_SUB(NOW(), INTERVAL 1 DAY)";	// We ignore deployment started more than 24h ago: They are finished even if not correctly flagged as 'done'.
	$resselect = $db->query($select);
	if ($resselect) {
		$objselect = $db->fetch_object($resselect);
		if ($objselect) $nbofinstanceindeployment = $objselect->nb;
	} else {
		dol_print_error($db, 'Bad sql request');
	}
	dol_syslog("nbofinstanceindeployment = ".$nbofinstanceindeployment." for ip ".$remoteip." (must be lower or equal than ".$MAXDEPLOYMENTPARALLEL." except if ip is 127.0.0.1)");
	if ($remoteip != '127.0.0.1' && (($nbofinstanceindeployment < 0) || ($nbofinstanceindeployment > $MAXDEPLOYMENTPARALLEL))) {
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("TooManyRequestPleaseTryLater"), null, 'errors');
			header("Location: ".$newurl);
		} else {
			print $langs->trans("TooManyRequestPleaseTryLater")."\n";
		}
		exit(-72);
	}

	$tmpthirdparty=new Societe($db);
	if ($reusesocid > 0) {
		$result = $tmpthirdparty->fetch($reusesocid);
		if ($result < 0) {
			dol_print_error_email('FETCHTP'.$reusesocid, $tmpthirdparty->error, $tmpthirdparty->errors, 'alert alert-error');
			exit(-73);
		}

		// Check that thirdparty is ok
		if (substr($sapi_type, 0, 3) != 'cli') {
			$thirdpartyidinsession = $_SESSION['dol_loginsellyoursaas'];
			if ($fromsocid > 0) {
				if ($thirdpartyidinsession != $fromsocid) {
					dol_syslog("Instance creation blocked for ".$remoteip." - Try to create instance for reseller id = ".$fromsocid." when id in session is ".$thirdpartyidinsession);
					if (substr($sapi_type, 0, 3) != 'cli') {
						setEventMessages($langs->trans("ErrorInvalidReuseIDSurelyAHackAttempt"), null, 'errors');
						header("Location: index.php");
					} else {
						print $langs->trans("ErrorInvalidReuseIDSurelyAHackAttempt")."\n";
					}
					exit(-74);
				}
				if ($tmpthirdparty->parent != $thirdpartyidinsession) {
					dol_syslog("Instance creation blocked for ".$remoteip." - Try to create instance for reseller id = ".$fromsocid." when existing customer has reseller id ".$tmpthirdparty->parent);
					if (substr($sapi_type, 0, 3) != 'cli') {
						setEventMessages($langs->trans("ErrorInvalidReuseIDSurelyAHackAttempt"), null, 'errors');
						header("Location: index.php");
					} else {
						print $langs->trans("ErrorInvalidReuseIDSurelyAHackAttempt")."\n";
					}
					exit(-75);
				}
			} else {
				if ($thirdpartyidinsession != $reusesocid) {
					dol_syslog("Instance creation blocked for ".$remoteip." - Try to create instance for thirdparty id = ".$reusesocid." when id in session is ".$thirdpartyidinsession);
					if (substr($sapi_type, 0, 3) != 'cli') {
						setEventMessages($langs->trans("ErrorInvalidReuseIDSurelyAHackAttempt"), null, 'errors');
						header("Location: index.php");
					} else {
						print $langs->trans("ErrorInvalidReuseIDSurelyAHackAttempt")."\n";
					}
					exit(-76);
				}
			}
		}

		$email = $tmpthirdparty->email;

		// Check number of instances for the same thirdparty account
		$MAXINSTANCESPERACCOUNT = ((empty($tmpthirdparty->array_options['options_maxnbofinstances']) && $tmpthirdparty->array_options['options_maxnbofinstances'] != '0') ? (empty($conf->global->SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT) ? 4 : $conf->global->SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT) : $tmpthirdparty->array_options['options_maxnbofinstances']);

		$listofcontractid = array();
		$listofcontractidopen = array();
		$sql = 'SELECT c.rowid as rowid, ce.deployment_status';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'contrat as c';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'contrat_extrafields as ce ON ce.fk_object = c.rowid,';
		//$sql .= ' '.MAIN_DB_PREFIX.'contratdet as d,';
		$sql .= ' '.MAIN_DB_PREFIX.'societe as s';
		$sql .= " WHERE c.fk_soc = s.rowid AND s.rowid = ".((int) $tmpthirdparty->id);
		//$sql .= " AND d.fk_contrat = c.rowid";
		$sql .= " AND c.entity = ".((int) $conf->entity);
		$sql .= " AND ce.deployment_status IN ('processing', 'done', 'undeployed')";
		$resql=$db->query($sql);
		if ($resql) {
			$num_rows = $db->num_rows($resql);
			$i = 0;
			while ($i < $num_rows) {
				$obj = $db->fetch_object($resql);
				if ($obj) {
					$listofcontractid[$obj->rowid]=$obj->rowid;
					if (in_array($obj->deployment_status, array('processing', 'done'))) {
						$listofcontractidopen[$obj->rowid] = $obj->rowid;
					}
				}
				$i++;
			}
		}

		if (count($listofcontractidopen) >= $MAXINSTANCESPERACCOUNT) {
			$sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
			if (! empty($tmpthirdparty->array_options['options_domain_registration_page'])
				&& $tmpthirdparty->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
				$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$tmpthirdparty->array_options['options_domain_registration_page'];
				if (! empty($conf->global->$newnamekey)) $sellyoursaasemail = $conf->global->$newnamekey;
			}

			if (substr($sapi_type, 0, 3) != 'cli') {
				setEventMessages($langs->trans("MaxNumberOfInstanceReached", $MAXINSTANCESPERACCOUNT, $sellyoursaasemail), null, 'errors');
				header("Location: index.php");
			} else {
				print $langs->trans("MaxNumberOfInstanceReached", $MAXINSTANCESPERACCOUNT, $sellyoursaasemail)."\n";
			}
			exit(-77);
		}
	} else {
		// Create thirdparty (if it already exists, do nothing and return a warning to user)
		dol_syslog("Fetch thirdparty from email ".$email);
		$result = $tmpthirdparty->fetch(0, '', '', '', '', '', '', '', '', '', $email);
		if ($result < 0) {
			dol_print_error_email('FETCHTP'.$email, $tmpthirdparty->error, $tmpthirdparty->errors, 'alert alert-error');
			exit(-1);
		} elseif ($result > 0) {	// Found 1 record of an existing account.
			$myaccounturl = $conf->global->SELLYOURSAAS_ACCOUNT_URL;
			$myaccountorigindomain = $tmpthirdparty->array_options['options_domain_registration_page'];
			if (! empty($tmpthirdparty->array_options['options_domain_registration_page'])
				&& $tmpthirdparty->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
				$newnamekey = 'SELLYOURSAAS_ACCOUNT_URL-'.$tmpthirdparty->array_options['options_domain_registration_page'];
				if (! empty($conf->global->$newnamekey)) $myaccounturl = $conf->global->$newnamekey;
			}
			$myaccounturl.='?mode=instances&addanotherinstance=1&service='.((int) $service).'&sldAndSubdomain='.urlencode($sldAndSubdomain).'#addanotherinstance';

			if (substr($sapi_type, 0, 3) != 'cli') {
				setEventMessages($langs->trans("AccountAlreadyExistsForEmail", $myaccounturl), null, 'errors');
				header("Location: ".$newurl);
			} else {
				print $langs->trans("AccountAlreadyExistsForEmail", $myaccounturl)."\n";
			}
			exit(-78);
		} else dol_syslog("Email not already used. Good.");
	}

	$fqdninstance = $sldAndSubdomain.$tldid;

	if ($productref != 'none') {
		$result = $contract->fetch(0, '', $fqdninstance);
		if ($result > 0) {
			if (substr($sapi_type, 0, 3) != 'cli') {
				// Instance ref already exists, we redirect to register page with appropriate error message
				setEventMessages($langs->trans("InstanceNameAlreadyExists", $fqdninstance), null, 'errors');
				header("Location: ".$newurl);
			} else {
				print $langs->trans("InstanceNameAlreadyExists", $fqdninstance)."\n";
			}
			exit(-80);
		} else {
			dol_syslog("Contract name not already used. Good.");
		}
	}

	if (! empty($conf->global->SELLYOURSAAS_NAME_RESERVED) && preg_match('/'.$conf->global->SELLYOURSAAS_NAME_RESERVED.'/', $fqdninstance)) {
		// @TODO Exclude some thirdparties


		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans("InstanceNameReseved", $fqdninstance), null, 'errors');
			header("Location: ".$newurl);
		} else {
			print $langs->trans("InstanceNameReseved", $fqdninstance)."\n";
		}
		exit(-81);
	}

	// Generate credentials

	$generatedunixlogin = strtolower('osu'.substr(getRandomPassword(true, array('I')), 0, 9));		// Must be lowercase as it can be used for default email
	$generatedunixpassword = substr(getRandomPassword(true, array('I')), 0, 10);

	$generateddbname = 'dbn'.substr(getRandomPassword(true, array('I')), 0, 8);
	$generateddbusername = 'dbu'.substr(getRandomPassword(true, array('I')), 0, 9);
	$generateddbpassword = substr(getRandomPassword(true, array('I')), 0, 10);
	$generateddbhostname = (! empty($conf->global->SELLYOURSAAS_FORCE_DATABASE_HOST) ? $conf->global->SELLYOURSAAS_FORCE_DATABASE_HOST : $sldAndSubdomain.'.'.$domainname);
	$generateddbport = (! empty($conf->global->SELLYOURSAAS_FORCE_DATABASE_PORT) ? $conf->global->SELLYOURSAAS_FORCE_DATABASE_PORT : 3306);
	$generatedunixhostname = $sldAndSubdomain.'.'.$domainname;


	// Create the new thirdparty

	$tmpthirdparty->oldcopy = dol_clone($tmpthirdparty);

	$password_encoding = 'password_hash';
	$password_crypted = dol_hash($password);

	$tmpthirdparty->name = $orgname;
	$tmpthirdparty->email = $email;
	$tmpthirdparty->client = 2;
	$tmpthirdparty->tva_assuj = 1;
	$tmpthirdparty->array_options['options_dolicloud'] = 'yesv2';
	$tmpthirdparty->array_options['options_date_registration'] = dol_now();
	$tmpthirdparty->array_options['options_domain_registration_page'] = getDomainFromURL($_SERVER["SERVER_NAME"], 1);
	$tmpthirdparty->array_options['options_source'] = 'REGISTERFORM'.($origin?'-'.$origin:'');
	$tmpthirdparty->array_options['options_source_utm'] = (empty($_COOKIE['utm_source_cookie']) ? '' : $_COOKIE['utm_source_cookie']);
	$tmpthirdparty->array_options['options_password'] = $password;
	$tmpthirdparty->array_options['options_optinmessages'] = $optinmessages;
	//$tmpthirdparty->array_options['options_checkbosnonprofitorga'] = $checkbosnonprofitorga;		// For the moment we don't save this info

	if ($productref == 'none') {	// If reseller
		$tmpthirdparty->fournisseur = 1;
		$tmpthirdparty->array_options['options_commission'] = (empty($conf->global->SELLYOURSAAS_DEFAULT_COMMISSION) ? 25 : $conf->global->SELLYOURSAAS_DEFAULT_COMMISSION);
	}

	if ($country_code) {
		$tmpthirdparty->country_id = getCountry($country_code, 3, $db);
		$tmpthirdparty->default_lang = getLanguageCodeFromCountryCode($country_code);	// $langs->defaultlang;
		$tmparray = explode('_', $tmpthirdparty->default_lang);
		/*if (! in_array($tmparray[0], array('fr', 'es', 'en'))) {
			$tmpthirdparty->default_lang = 'en_US';
		}*/
	}

	$reg = array();
	if (!empty($_COOKIE['utm_source_cookie']) && preg_match('/^partner(\d+)$/', $_COOKIE['utm_source_cookie'], $reg)) {
		// The source is from a partner
		if (getDolGlobalInt('SELLYOURSAAS_LINK_TO_PARTNER_IF_FIRST_SOURCE')) {
			$tmpthirdparty->parent = ((int) $reg[1]);		// Add link to parent/reseller id with the id of first source in all web site
		}
	}


	// Start transaction
	$db->begin();

	if ($tmpthirdparty->id > 0) {
		if (empty($reusesocid)) {
			$result = $tmpthirdparty->update(0, $user);
			if ($result <= 0) {
				$db->rollback();

				if (substr($sapi_type, 0, 3) != 'cli') {
					setEventMessages($tmpthirdparty->error, $tmpthirdparty->errors, 'errors');
					header("Location: ".$newurl);
				} else {
					print $tmpthirdparty->error."\n";
				}
				exit(-90);
			}
		}
	} else {
		// Set lang to backoffice language
		$savlangs = $langs;
		$langs = $langsen;

		$tmpthirdparty->code_client = -1;
		if ($productref == 'none') {	// If reseller
			$tmpthirdparty->code_fournisseur = -1;
		}
		if ($partner > 0) {
			$tmpthirdparty->parent = $partner;		// Add link to parent/reseller id with the id of partner explicitely into registration link
		}

		$result = $tmpthirdparty->create($user);
		if ($result <= 0) {
			$db->rollback();

			if (substr($sapi_type, 0, 3) != 'cli') {
				setEventMessages($tmpthirdparty->error, $tmpthirdparty->errors, 'errors');
				header("Location: ".$newurl);
			} else {
				print $tmpthirdparty->error."\n";
			}
			exit(-91);
		}

		// Restore lang to user/visitor language
		$langs = $savlangs;
	}

	if (! empty($conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG)) {
		$result = $tmpthirdparty->setCategories(array($conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG => $conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG), 'customer');
		if ($result < 0) {
			$db->rollback();

			if (substr($sapi_type, 0, 3) != 'cli') {
				setEventMessages($tmpthirdparty->error, $tmpthirdparty->errors, 'errors');
				header("Location: ".$newurl);
			} else {
				print $tmpthirdparty->error."\n";
			}
			exit(-92);
		}
	} else {
		$db->rollback();

		dol_print_error_email('SETUPTAG', 'Setup of module not complete. The default customer tag is not defined.', null, 'alert alert-error');
		exit(-1);
	}

	if ($productref == 'none') {
		if (! empty($conf->global->SELLYOURSAAS_DEFAULT_RESELLER_CATEG)) {
			$tmpthirdparty->name_alias = dol_sanitizeFileName($tmpthirdparty->name);
			$result = $tmpthirdparty->setCategories(array($conf->global->SELLYOURSAAS_DEFAULT_RESELLER_CATEG => $conf->global->SELLYOURSAAS_DEFAULT_RESELLER_CATEG), 'supplier');
			if ($result < 0) {
				$db->rollback();

				if (substr($sapi_type, 0, 3) != 'cli') {
					setEventMessages($tmpthirdparty->error, $tmpthirdparty->errors, 'errors');
					header("Location: ".$newurl);
				} else {
					print $tmpthirdparty->error."\n";
				}
				exit(-93);
			}
		} else {
			$db->rollback();

			dol_print_error_email('SETUPTAG', 'Setup of module not complete. The default reseller tag is not defined.', null, 'alert alert-error');
			exit(-1);
		}
	}

	$object = $tmpthirdparty;

	$date_start = $now;
	$date_end = dol_time_plus_duree($date_start, $freeperioddays, 'd');


	// Create contract/instance

	if (! $error && $productref != 'none') {
		dol_syslog("Create contract with deployment status 'Processing'");

		$contract->ref_customer = $sldAndSubdomain.$tldid;
		$contract->socid = $tmpthirdparty->id;
		$contract->commercial_signature_id = $user->id;
		$contract->commercial_suivi_id = $user->id;
		$contract->date_contrat = $now;
		$contract->note_private = 'Contract created from the online instance registration form or the customer dashboard. forcesubdomain was '.(GETPOST('forcesubdomain')?GETPOST('forcesubdomain'):' empty').'.';

		$tmp=explode('.', $contract->ref_customer, 2);
		$sldAndSubdomain=$tmp[0];
		$domainname=$tmp[1];

		$sellyoursaasutils = new SellYourSaasUtils($db);
		$onlyifopen = 1;
		if (GETPOST('forcesubdomain')) {
			$onlyifopen = 0;
		}
		$serverdeployement = $sellyoursaasutils->getRemoteServerDeploymentIp($domainname, $onlyifopen);
		if (empty($serverdeployement)) {
			$db->rollback();

			dol_print_error_email('BADDOMAIN', 'Trying to deploy on a not valid domain '.$domainname.' (not exists or closed).', null, 'alert alert-error');
			exit(-94);
		}

		$contract->array_options['options_plan'] = $productref;
		$contract->array_options['options_deployment_status'] = 'processing';
		$contract->array_options['options_deployment_date_start'] = $now;
		$contract->array_options['options_deployment_init_email'] = $email;
		$contract->array_options['options_deployment_init_adminpass'] = $password;
		$contract->array_options['options_date_endfreeperiod'] = $date_end;
		$contract->array_options['options_undeployment_date'] = '';
		$contract->array_options['options_undeployment_ip'] = '';
		$contract->array_options['options_deployment_host'] = $serverdeployement;
		$contract->array_options['options_hostname_os'] = $generatedunixhostname;
		$contract->array_options['options_username_os'] = $generatedunixlogin;
		$contract->array_options['options_password_os'] = $generatedunixpassword;
		$contract->array_options['options_sshaccesstype'] = (empty($tmpproduct->array_options['options_sshaccesstype'])?0:$tmpproduct->array_options['options_sshaccesstype']);
		$contract->array_options['options_hostname_db'] = $generateddbhostname;
		$contract->array_options['options_database_db'] = $generateddbname;
		$contract->array_options['options_port_db'] = $generateddbport;
		$contract->array_options['options_username_db'] = $generateddbusername;
		$contract->array_options['options_password_db'] = $generateddbpassword;

		if ($customurl) {
			$contract->array_options['options_custom_url'] = $customurl;
		}

		//$contract->array_options['options_nb_users'] = 1;
		//$contract->array_options['options_nb_gb'] = 0.01;

		// TODO Remove hardcoded code here
		if (preg_match('/glpi|flyve/i', $productref) && GETPOST("tz_string")) {
			$contract->array_options['options_custom_virtualhostline'] = 'php_value date.timezone "'.GETPOST("tz_string").'"';
		}

		$user_agent = (empty($_SERVER["HTTP_USER_AGENT"]) ? '' : $_SERVER["HTTP_USER_AGENT"]);
		$user_language = (empty($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? '' : $_SERVER["HTTP_ACCEPT_LANGUAGE"]);

		$contract->array_options['options_timezone'] = GETPOST("tz_string");
		$contract->array_options['options_deployment_ip'] = $remoteip;
		$contract->array_options['options_deployment_ua'] = (($user_agent || $user_language) ? dol_trunc($user_agent.(($user_agent && $user_language) ? ' - ' : '').$user_language, 250) : '');

		$contract->array_options['options_deployment_ipquality'] = 'remoteip='.$remoteip.': ';
		$contract->array_options['options_deployment_emailquality'] = 'email='.$email.': ';

		$prefix=dol_getprefix('');
		$cookieregistrationa='DOLREGISTERA_'.$prefix;
		$cookieregistrationb='DOLREGISTERB_'.$prefix;
		$nbregistration = (int) $_COOKIE[$cookieregistrationa];
		if (! empty($_COOKIE[$cookieregistrationa])) {
			$contract->array_options['options_cookieregister_counter'] = ($nbregistration ? $nbregistration : 1);
		}
		if (! empty($_COOKIE[$cookieregistrationb])) {
			$contract->array_options['options_cookieregister_previous_instance'] = dol_decode($_COOKIE[$cookieregistrationb]);
		}

		// Add security controls
		$abusetest = 0;
		$vpnproba = '';


		// TODO Insert evaluation by disposablemail here


		// Evaluate VPN probability with Getintel
		if (!empty($conf->global->SELLYOURSAAS_GETIPINTEL_ON)) {
			$emailforvpncheck='contact+checkcustomer@mysaasdomainname.com';
			if (!empty($conf->global->SELLYOURSAAS_GETIPINTEL_EMAIL)) $emailforvpncheck = $conf->global->SELLYOURSAAS_GETIPINTEL_EMAIL;
			$url = 'http://check.getipintel.net/check.php?ip='.urlencode($remoteip).'&contact='.urlencode($emailforvpncheck).'&flag=f';
			$result = getURLContent($url, 'GET', '', 1, array(), array('http', 'https'), 0);
			/* The proxy check system will return negative values on error. For standard format (non-json), an additional HTTP 400 status code is returned
				-1 Invalid no input
				-2 Invalid IP address
				-3 Unroutable address / private address
				-4 Unable to reach database, most likely the database is being updated. Keep an eye on twitter for more information.
				-5 Your connecting IP has been banned from the system or you do not have permission to access a particular service. Did you exceed your query limits? Did you use an invalid email address? If you want more information, please use the contact links below.
				-6 You did not provide any contact information with your query or the contact information is invalid.
				If you exceed the number of allowed queries, you'll receive a HTTP 429 error.
			 */
			if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
				$vpnproba = (float) price2num($result['content'], 2, 1);
				$contract->array_options['options_deployment_ipquality'] .= 'geti-vpn='.round($vpnproba, 2).';';
				$contract->array_options['options_deployment_vpn_proba'] = round($vpnproba, 2);
			} else {
				$contract->array_options['options_deployment_ipquality'] .= 'geti-check failed. http_code = '.dol_trunc($result['http_code'], 100).';';
				$contract->array_options['options_deployment_vpn_proba'] = '';
			}

			// Refused if VPN probability from GetIP is too high
			if (!$whitelisted && empty($abusetest) && !empty($conf->global->SELLYOURSAAS_VPN_PROBA_REFUSED)) {
				if (is_numeric($vpnproba) && $vpnproba >= (float) $conf->global->SELLYOURSAAS_VPN_PROBA_REFUSED) {
					dol_syslog("Instance creation blocked for ".$remoteip." - VPN probability ".$vpnproba." is higher or equal than ".$conf->global->SELLYOURSAAS_VPN_PROBA_REFUSED);
					$abusetest = 1;
				}
			}
		}

		// Evaluate VPN probability with IPQualityScore but also TOR or bad networks and email
		if (!empty($conf->global->SELLYOURSAAS_IPQUALITY_ON)) {
			if (empty($abusetest) && !empty($conf->global->SELLYOURSAAS_IPQUALITY_KEY)) {
				include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

				// Retrieve additional (optional) data points which help us enhance fraud scores.
				$user_agent = (empty($_SERVER["HTTP_USER_AGENT"]) ? '' : $_SERVER["HTTP_USER_AGENT"]);
				$user_language = (empty($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? '' : $_SERVER["HTTP_ACCEPT_LANGUAGE"]);

				// Set the strictness for this query. (0 (least strict) - 3 (most strict))
				$strictness = 1;

				// You may want to allow public access points like coffee shops, schools, corporations, etc...
				$allow_public_access_points = 'true';

				// Reduce scoring penalties for mixed quality IP addresses shared by good and bad users.
				$lighter_penalties = 'true';

				// Create parameters array.
				$parameters = array(
					'user_agent' => $user_agent,
					'user_language' => $user_language,
					'strictness' => $strictness,
					'allow_public_access_points' => $allow_public_access_points,
					'lighter_penalties' => $lighter_penalties
				);

				/* User & Transaction Scoring
				 * Score additional information from a user, order, or transaction for risk analysis
				 * Please see the documentation and example code to include this feature in your scoring:
				 * https://www.ipqualityscore.com/documentation/proxy-detection/transaction-scoring
				 * This feature requires a Premium plan or greater
				 */
				$transaction_parameters = array();

				// Format Parameters
				if (is_array($transaction_parameters) && count($transaction_parameters)) {
					$formatted_parameters = http_build_query(array_merge($parameters, $transaction_parameters));
				} else {
					$formatted_parameters = http_build_query($parameters);
				}

				// Create API URL for IP Check
				$url = sprintf(
					'https://www.ipqualityscore.com/api/json/ip/%s/%s?%s',
					$conf->global->SELLYOURSAAS_IPQUALITY_KEY,
					urlencode($remoteip),
					$formatted_parameters
				);

				$fraudscore = 0;
				$result = getURLContent($url);
				if (is_array($result) && $result['http_code'] == 200 && !empty($result['content'])) {
					try {
						dol_syslog("Result of call of ipqualityscore: ".$result['content'], LOG_DEBUG);
						$jsonreponse = json_decode($result['content'], true);
						dol_syslog("For ".$remoteip.", fraud_score=".$jsonreponse['fraud_score']." - is_crawler=".$jsonreponse['is_crawler']." - vpn=".$jsonreponse['vpn']." - recent_abuse=".$jsonreponse['recent_abuse']." - tor=".($jsonreponse['tor'] || $jsonreponse['active_tor']));
						if ($jsonreponse['success']) {
							if ($jsonreponse['recent_abuse'] && !empty($conf->global->SELLYOURSAAS_IPQUALITY_BLOCK_ABUSING_IP)) {	// Not recommanded if users are using shared IP
								dol_syslog("Instance creation blocked for ".$remoteip." - This is an IP with recent abuse reported");
								$abusetest = 2;
							}
							if ($jsonreponse['tor'] || $jsonreponse['active_tor']) {
								// So recommanded that is it enabled always, no option to disable this
								dol_syslog("Instance creation blocked for ".$remoteip." - This is a TOR or evil IP - host=".$jsonreponse['host']);
								$abusetest = 3;
							}
							$contract->array_options['options_deployment_ipquality'] .= 'ipq-tor='.(($jsonreponse['tor'] || $jsonreponse['active_tor']) ? 1 : 0).';';
							$contract->array_options['options_deployment_ipquality'] .= 'ipq-vpn='.(($jsonreponse['vpn'] || $jsonreponse['active_vpn']) ? 1 : 0).';';
							$contract->array_options['options_deployment_ipquality'] .= 'ipq-recent_abuse='.($jsonreponse['recent_abuse'] ? 1 : 0).';';
							$contract->array_options['options_deployment_ipquality'] .= 'ipq-fraud_score='.$jsonreponse['fraud_score'].';';
							$contract->array_options['options_deployment_ipquality'] .= 'ipq-host='.$jsonreponse['host'].';';
							$fraudscore = (int) $jsonreponse['fraud_score'];

							if ($vpnproba === '') {
								// If vpn proba was not found with getip, we use the one found from ipqualityscore
								$vpnproba = (($jsonreponse['vpn'] || $jsonreponse['active_vpn']) ? 1 : 0);
								$contract->array_options['options_deployment_vpn_proba'] = round($vpnproba, 2);
							}
						} else {
							$contract->array_options['options_deployment_ipquality'] .= 'ipq-check failed. Success property not found. '.dol_trunc($result['content'], 100).';';
						}
					} catch (Exception $e) {
						$contract->array_options['options_deployment_ipquality'] .= 'ipq-check failed. Exception '.dol_trunc($e->getMessage(), 100).';';
					}
				} else {
					$contract->array_options['options_deployment_ipquality'] .= 'ipq-check failed. http_code = '.dol_trunc($result['http_code'], 100).';';
				}

				// Refused if VPN probability is too high
				if (!$whitelisted && empty($abusetest) && !empty($conf->global->SELLYOURSAAS_VPN_PROBA_REFUSED)) {
					$conf->global->SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED = 85;

					if (is_numeric($vpnproba) && $vpnproba >= (float) $conf->global->SELLYOURSAAS_VPN_PROBA_REFUSED && ($fraudscore >= $conf->global->SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED)) {
						dol_syslog("Instance creation blocked for ".$remoteip." - VPN probability ".$vpnproba." is higher or equal than ".$conf->global->SELLYOURSAAS_VPN_PROBA_REFUSED.' with a fraudscore >= '.$conf->global->SELLYOURSAAS_VPN_FRAUDSCORE_REFUSED);
						$abusetest = 1;
					}
				}


				// Create API URL for Email Check
				$url = sprintf(
					'https://www.ipqualityscore.com/api/json/email/%s/%s?%s',
					$conf->global->SELLYOURSAAS_IPQUALITY_KEY,
					urlencode($email),
					$formatted_parameters
					);

				$fraudscore = 0;
				$result = getURLContent($url);
				if (is_array($result) && $result['http_code'] == 200 && !empty($result['content'])) {
					try {
						dol_syslog("Result of call of ipqualityscore: ".$result['content'], LOG_DEBUG);
						$jsonreponse = json_decode($result['content'], true);
						dol_syslog("For ".$remoteip.", valid=".$jsonreponse['valid']." - disposable=".$jsonreponse['disposable']." - dns_valid=".$jsonreponse['dns_valid']." - timed_out=".$jsonreponse['timed_out']);
						if ($jsonreponse['success']) {
							$contract->array_options['options_deployment_emailquality'] .= 'ipq-valid='.$jsonreponse['valid'].';';
							$contract->array_options['options_deployment_emailquality'] .= 'ipq-disposable='.$jsonreponse['disposable'].';';
							$contract->array_options['options_deployment_emailquality'] .= 'ipq-dns_valid='.$jsonreponse['dns_valid'].';';
							$contract->array_options['options_deployment_emailquality'] .= 'ipq-timed_out='.$jsonreponse['timed_out'].';';
							$contract->array_options['options_deployment_emailquality'] .= 'ipq-recent_abuse='.$jsonreponse['recent_abuse'].';';
						} else {
							$contract->array_options['options_deployment_emailquality'] .= 'ipq-check failed. Success property not found. '.dol_trunc($result['content'], 100).';';
						}
					} catch (Exception $e) {
						$contract->array_options['options_deployment_emailquality'] .= 'ipq-check failed. Exception '.dol_trunc($e->getMessage(), 100).';';
					}
				} else {
					$contract->array_options['options_deployment_emailquality'] .= 'ipq-check failed. http_code = '.dol_trunc($result['http_code'], 100).';';
				}

				// Refused if Email fraud probability is too high
				if (!$whitelisted && empty($abusetest)) {
					if ($jsonreponse['recent_abuse'] === false && ($jsonreponse['valid'] === true || ($jsonreponse['timed_out'] === true && $jsonreponse['disposable'] === false && $jsonreponse['dns_valid'] === true))) {
						// Email valid
					} else {
						dol_syslog("Instance creation blocked for email ".$email." - Email fraud probability ".$fraudscore." is higher or equal than ".$conf->global->SELLYOURSAAS_EMAIL_FRAUDSCORE_REFUSED);
						// TODO Enable this
						// $abusetest = 6;
					}
				}
			}
		}


		$contract->array_options['options_deployment_ipquality'] = dol_trunc($contract->array_options['options_deployment_ipquality'], 250);
		$contract->array_options['options_deployment_emailquality'] = dol_trunc($contract->array_options['options_deployment_emailquality'], 250);
		//dol_syslog("options_deployment_ipquality = ".$contract->array_options['options_deployment_ipquality'], LOG_DEBUG);
		//dol_syslog("options_deployment_emailquality = ".$contract->array_options['options_deployment_emailquality'], LOG_DEBUG);

		// SELLYOURSAAS_BLACKLIST_IP_MASKS and SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN are hidden constants.
		// Deprecated. Use instead the List of blacklist ips into menu. This is done a begin of page

		// Block for some IPs
		if (!$whitelisted && empty($abusetest) && !empty($conf->global->SELLYOURSAAS_BLACKLIST_IP_MASKS)) {
			$arrayofblacklistips = explode(',', $conf->global->SELLYOURSAAS_BLACKLIST_IP_MASKS);
			foreach ($arrayofblacklistips as $blacklistip) {
				if ($remoteip == $blacklistip) {
					dol_syslog("Instance creation blocked for ".$remoteip." - This IP is in blacklist SELLYOURSAAS_BLACKLIST_IP_MASKS");
					$abusetest = 4;
				}
			}
		}

		// Block for some IPs if VPN proba is higher that a threshold
		if (!$whitelisted && empty($abusetest) && !empty($conf->global->SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN)) {
			if (is_numeric($vpnproba) && $vpnproba >= (empty($conf->global->SELLYOURSAAS_VPN_PROBA_FOR_BLACKLIST) ? 1 : (float) $conf->global->SELLYOURSAAS_VPN_PROBA_FOR_BLACKLIST)) {
				$arrayofblacklistips = explode(',', $conf->global->SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN);
				foreach ($arrayofblacklistips as $blacklistip) {
					if ($remoteip == $blacklistip) {
						dol_syslog("Instance creation blocked for ".$remoteip." - This IP is in blacklist SELLYOURSAAS_BLACKLIST_IP_MASKS_FOR_VPN");
						$abusetest = 5;
					}
				}
			}
		}

		if ($abusetest) {
			$db->rollback();

			$emailtowarn = $conf->global->MAIN_INFO_SOCIETE_MAIL;
			dol_syslog("InstanceCreationBlockedForSecurityPurpose ip ".$remoteip." is refused with value abusetest=".$abusetest, LOG_DEBUG);

			if (substr($sapi_type, 0, 3) != 'cli') {
				setEventMessages($langs->trans("InstanceCreationBlockedForSecurityPurpose", $emailtowarn, $remoteip), null, 'errors');
				//http_response_code(403);
				header("Location: ".$newurl);
			} else {
				print $langs->trans("InstanceCreationBlockedForSecurityPurpose", $emailtowarn, $remoteip)."\n";
			}
			exit(-95);
		}


		$result = $contract->create($user);
		if ($result <= 0) {
			$db->rollback();

			dol_print_error_email('CREATECONTRACT', $contract->error, $contract->errors, 'alert alert-error');
			exit(-96);
		}
	}


	// Create contract line for INSTANCE
	if (! $error && $productref != 'none') {
		dol_syslog("Add line to contract for INSTANCE with freeperioddays = ".$freeperioddays);

		if (empty($object->country_code)) {
			$object->country_code = dol_getIdFromCode($db, $object->country_id, 'c_country', 'rowid', 'code');
		}

		$qty = 1;
		//if (! empty($contract->array_options['options_nb_users'])) $qty = $contract->array_options['options_nb_users'];
		$vat = get_default_tva($mysoc, $object, $tmpproduct->id);
		$localtax1_tx = get_default_localtax($mysoc, $object, 1, 0);
		$localtax2_tx = get_default_localtax($mysoc, $object, 2, 0);
		//var_dump($mysoc->country_code);
		//var_dump($object->country_code);
		//var_dump($tmpproduct->tva_tx);
		//var_dump($vat);exit;

		$price = getDolGlobalString("SELLYOURSAAS_RESELLER_FIX_PRICE_".$partner."_".$tmpproduct->id) ? getDolGlobalString("SELLYOURSAAS_RESELLER_FIX_PRICE_".$partner."_".$tmpproduct->id) : $tmpproduct->price;
		$discount = $tmpthirdparty->remise_percent;

		$productidtocreate = $tmpproduct->id;
		$desc = '';
		if (empty($conf->global->SELLYOURSAAS_NO_PRODUCT_DESCRIPTION_IN_CONTRACT)) {
			$desc = $tmpproduct->description;
		}

		$contractlineid = $contract->addline($desc, $price, $qty, $vat, $localtax1_tx, $localtax2_tx, $productidtocreate, $discount, $date_start, $date_end, 'HT', 0);
		if ($contractlineid < 0) {
			dol_print_error_email('CREATECONTRACTLINE1', $contract->error, $contract->errors, 'alert alert-error');
			exit(-97);
		}
	}

	//var_dump('user:'.$dolicloudcustomer->price_user);
	//var_dump('instance:'.$dolicloudcustomer->price_instance);

	$j=1;

	// Create contract line for other products
	if (! $error && $productref != 'none') {
		dol_syslog("Add line to contract for depending products (like USERS or options)");

		$prodschild = $tmpproduct->getChildsArbo($tmpproduct->id, 1);

		$tmpsubproduct = new Product($db);
		foreach ($prodschild as $prodid => $arrayprodid) {
			$tmpsubproduct->fetch($prodid);	// To load the price

			$qty = 1;
			//if (! empty($contract->array_options['options_nb_users'])) $qty = $contract->array_options['options_nb_users'];
			$vat = get_default_tva($mysoc, $object, $prodid);
			$localtax1_tx = get_default_localtax($mysoc, $object, 1, $prodid);
			$localtax2_tx = get_default_localtax($mysoc, $object, 2, $prodid);

			if (preg_match('/user/i', $tmpsubproduct->ref) || preg_match('/user/i', $tmpsubproduct->array_options['options_resource_label'])) {
				$price = getDolGlobalString("SELLYOURSAAS_RESELLER_PRICE_PER_USER_".$partner."_".$tmpproduct->id) ? getDolGlobalString("SELLYOURSAAS_RESELLER_PRICE_PER_USER_".$partner."_".$tmpproduct->id) : $tmpsubproduct->price;
			} else {
				$price = getDolGlobalString("SELLYOURSAAS_RESELLER_PRICE_OPTION_".$tmpsubproduct->id."_".$partner."_".$tmpproduct->id) ? getDolGlobalString("SELLYOURSAAS_RESELLER_PRICE_OPTION_".$tmpsubproduct->id."_".$partner."_".$tmpproduct->id) : $tmpsubproduct->price;
			}
			$desc = '';
			if (empty($conf->global->SELLYOURSAAS_NO_PRODUCT_DESCRIPTION_IN_CONTRACT)) {
				$desc = $tmpsubproduct->description;
			}
			$discount = 0;

			if ($qty > 0) {
				$j++;

				$contractlineid = $contract->addline($desc, $price, $qty, $vat, $localtax1_tx, $localtax2_tx, $prodid, $discount, $date_start, $date_end, 'HT', 0);
				if ($contractlineid < 0) {
					dol_print_error_email('CREATECONTRACTLINE'.$j, $contract->error, $contract->errors, 'alert alert-error');
					exit(-98);
				}
			}
		}
	}

	dol_syslog("Reload all lines after creation (".$j." lines in contract) to have contract->lines ok");
	$contract->fetch_lines();

	if (! $error) {
		$db->commit();
	} else {
		$db->rollback();
	}
}


// -----------------------------------------------------------------------------------------------------------------------
// Create unix user and directories, DNS, virtual host and database
//
// With old method:
// Check the user www-data is allowed to "sudo /usr/bin/create_test_instance.sh"
// If you get error "sudo: PERM_ROOT: setresuid(0, -1, -1): Operation not permitted", check module mpm_itk
//<IfModule mpm_itk_module>
//LimitUIDRange 0 5000
//LimitGIDRange 0 5000
//</IfModule>
// If you get error "sudo: sorry, you must have a tty to run sudo", disable key "Defaults requiretty" from /etc/sudoers
//
// With new method, call the deploy server
// -----------------------------------------------------------------------------------------------------------------------

if (! $error && $productref != 'none') {
	dol_include_once('/sellyoursaas/class/sellyoursaasutils.class.php');
	$sellyoursaasutils = new SellYourSaasUtils($db);

	$comment = 'Deploy instance '.$contract->ref;

	$result = $sellyoursaasutils->sellyoursaasRemoteAction('deployall', $contract, 'admin', $email, $password, '0', $comment, 300);
	if ($result <= 0) {
		$error++;
		$errormessages=$sellyoursaasutils->errors;
		if ($sellyoursaasutils->error) $errormessages[]=$sellyoursaasutils->error;
	}
}


// Finish deployall - Activate all lines
if (! $error && $productref != 'none') {
	dol_syslog("Activate all lines - by register_instance");

	$contract->context['deployallwasjustdone']=1;		// Add a key so trigger into activateAll will know we have just made a "deployall"

	if ($fromsocid) $comment = 'Activation after deployment from instance creation by reseller id='.$fromsocid;
	else $comment = 'Activation after deployment from online registration or dashboard';

	$result = $contract->activateAll($user, dol_now(), 1, $comment);			// This may execute the triggers
	if ($result <= 0) {
		$error++;
		$errormessages[]=$contract->error;
		$errormessages[]=array_merge($contract->errors, $errormessages);
	}
}

// End of deployment is now OK / Complete
if (! $error && $productref != 'none') {
	$contract->array_options['options_deployment_status'] = 'done';
	$contract->array_options['options_deployment_date_end'] = dol_now();
	$contract->array_options['options_undeployment_date'] = '';
	$contract->array_options['options_undeployment_ip'] = '';

	// Clear password, we don't need it anymore.
	if (empty($conf->global->SELLYOURSAAS_KEEP_INIT_ADMINPASS)) {
		$contract->array_options['options_deployment_init_adminpass'] = '';
	}

	// Set cookie to store last registered instance
	$prefix=dol_getprefix('');
	$cookieregistrationa='DOLREGISTERA_'.$prefix;
	$cookieregistrationb='DOLREGISTERB_'.$prefix;
	$nbregistration = ((int) $_COOKIE[$cookieregistrationa] + 1);
	setcookie($cookieregistrationa, $nbregistration, 0, "/", null, false, true);	// Cookie to count nb of registration from this computer
	setcookie($cookieregistrationb, dol_encode($contract->ref_customer), 0, "/", null, false, true);					// Cookie to save previous registered instance

	$result = $contract->update($user);
	if ($result < 0) {
		// We ignore errors. This should not happen in real life.
		//setEventMessages($contract->error, $contract->errors, 'errors');
	}
}


// Go to dashboard with login session forced

if (! $error) {
	// Deployment is complete and finished.
	// First time we go at end of process, so we send en email.

	if ($productref == 'none') {
		$fromsocid = $tmpthirdparty->id;
	}

	$newurl=$_SERVER["PHP_SELF"];
	$newurl=preg_replace('/register_instance\.php/', 'index.php?welcomecid='.$contract->id.(($fromsocid > 0)?'&fromsocid='.$fromsocid:''), $newurl);

	$anonymoususer=new User($db);
	$anonymoususer->fetch($conf->global->SELLYOURSAAS_ANONYMOUSUSER);
	$_SESSION['dol_login']=$anonymoususer->login;				// Set dol_login in session so for next page index.php we will load, we are already logged.

	if ($fromsocid > 0) $_SESSION['dol_loginsellyoursaas']=$fromsocid;
	else $_SESSION['dol_loginsellyoursaas']=$contract->thirdparty->id;

	$_SESSION['initialapplogin']='admin';
	$_SESSION['initialapppassword']=$password;

	if (! $disablecustomeremail) {	// In most cases this test is true
		// Send deployment email
		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
		include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		$formmail=new FormMail($db);

		$emailtemplate = '';
		if ($productref != 'none') {
			$emailtemplate = 'InstanceDeployed';
			$arraydefaultmessage=$formmail->getEMailTemplate($db, 'contract', $user, $langs, 0, 1, $emailtemplate);		// Templates were initialiazed into data.sql
		} else {
			$emailtemplate = '(ChannelPartnerCreated)';
			$arraydefaultmessage=$formmail->getEMailTemplate($db, 'thirdparty', $user, $langs, 0, 1, $emailtemplate);	// Templates were initialized into data.sql
		}

		$substitutionarray=getCommonSubstitutionArray($langs, 0, null, $contract);
		$substitutionarray['__PACKAGEREF__']=$tmppackage->ref;
		$substitutionarray['__PACKAGELABEL__']=$tmppackage->label;
		$substitutionarray['__PACKAGEEMAILHEADER__']=$tmppackage->header;	// TODO
		$substitutionarray['__PACKAGEEMAILFOOTER__']=$tmppackage->footer;	// TODO
		$substitutionarray['__APPUSERNAME__']=$_SESSION['initialapplogin'];
		$substitutionarray['__APPPASSWORD__']=$password;

		// TODO Replace this with $tmppackage->header and $tmppackage->footer
		dol_syslog('Set substitution var for __EMAIL_FOOTER__ with $tmppackage->ref='.strtoupper($tmppackage->ref));
		$substitutionarray['__EMAIL_FOOTER__']='';
		if ($emailtemplate) {
			if ($langs->trans("EMAIL_FOOTER_".strtoupper($tmppackage->ref)) != "EMAIL_FOOTER_".strtoupper($tmppackage->ref)) {
				$substitutionarray['__EMAIL_FOOTER__'] = $langs->trans("EMAIL_FOOTER_".strtoupper($tmppackage->ref));
			}
		}

		complete_substitutions_array($substitutionarray, $langs, $contract);

		$subject = make_substitutions($arraydefaultmessage->topic, $substitutionarray, $langs);
		$msg     = make_substitutions($arraydefaultmessage->content, $substitutionarray, $langs);

		$sellyoursaasemailnoreply = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;

		$domainname=getDomainFromURL($_SERVER['SERVER_NAME'], 1);
		$constforaltemailnoreply = 'SELLYOURSAAS_NOREPLY_EMAIL-'.$domainname;
		if (! empty($conf->global->$constforaltemailnoreply)) {
			$sellyoursaasemailnoreply = $conf->global->$constforaltemailnoreply;
		}

		$to = $contract->thirdparty->email;

		$trackid = 'thi'.$_SESSION['dol_loginsellyoursaas'];

		$cmail = new CMailFile($subject, $to, $sellyoursaasemailnoreply, $msg, array(), array(), array(), '', '', 0, 1, '', '', $trackid);
		$result = $cmail->sendfile();
		if (! $result) {
			$error++;
			setEventMessages($cmail->error, $cmail->errors, 'warnings');
		}
	} else { // In rare cases, we are here
		if (substr($sapi_type, 0, 3) != 'cli') {
			setEventMessages($langs->trans('NoEmailSentAfterRegistration'), null, 'warnings');
		} else {
			print $langs->trans('NoEmailSentAfterRegistration')."\n";
		}
	}

	if (substr($sapi_type, 0, 3) != 'cli') {
		dol_syslog("Deployment successful with contract ID = ".$contract->id);
		header("Location: ".$newurl);
	} else {
		print "Instance created with ID = ".$contract->id."\n";
	}
	exit(0);
}


// Error

dol_syslog("Deployment error");

if ($reusecontractid > 0) {
	setEventMessages('', $errormessages, 'errors');
	header("Location: ".$newurl);
	exit(-1);
}

$errormessages[] = '<br>';

// If we are here, there was an error
if ($productref != 'none') {
	$errormessages[] = 'Deployment of instance '.$sldAndSubdomain.$tldid.' from '.($remoteip?$remoteip:'localhost').' started but failed.';
} else {
	$errormessages[] = 'Creation of account '.$email.' from '.($remoteip?$remoteip:'localhost').' has failed.';
}
$errormessages[] = $langs->trans("OurTeamHasBeenAlerted");

// Force reload ot thirdparty
if (is_object($contract) && method_exists($contract, 'fetch_thirdparty')) {
	$contract->fetch_thirdparty();
}

// Send email to customer
if (is_object($contract->thirdparty)) {
	$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;
	$sellyoursaasemailsupervision = $conf->global->SELLYOURSAAS_SUPERVISION_EMAIL;
	$sellyoursaasemailnoreply = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;

	$domainname=getDomainFromURL($_SERVER['SERVER_NAME'], 1);
	$constforaltname = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$domainname;
	$constforaltemailsupervision = 'SELLYOURSAAS_SUPERVISION_EMAIL-'.$domainname;
	$constforaltemailnoreply = 'SELLYOURSAAS_NOREPLY_EMAIL-'.$domainname;
	if (! empty($conf->global->$constforaltname)) {
		$sellyoursaasdomain = $domainname;
		$sellyoursaasname = $conf->global->$constforaltname;
		$sellyoursaasemailsupervision = $conf->global->$constforaltemailsupervision;
		$sellyoursaasemailnoreply = $conf->global->$constforaltemailnoreply;
	}

	$to = $contract->thirdparty->email;

	if (substr($sapi_type, 0, 3) != 'cli') {
		// We send email but only if not in Command Line mode
		dol_syslog("Error in deployment, send email to customer (copy supervision)", LOG_ERR);

		$email = new CMailFile('['.$sellyoursaasname.'] Registration/deployment temporary error - '.dol_print_date(dol_now(), 'dayhourrfc'), $to, $sellyoursaasemailnoreply, $langs->trans("AnErrorOccuredDuringDeployment")."<br><br>\n".join("<br>\n", $errormessages)."<br><br>\n", array(), array(), array(), $sellyoursaasemailsupervision, '', 0, -1, '', '', '', '', 'emailing');
		$email->sendfile();
	} else {
		dol_syslog("Error in deployment, no email sent because we are in CLI mode", LOG_ERR);
	}
}


$conf->dol_hide_topmenu = 1;
$conf->dol_hide_leftmenu = 1;

$favicon=getDomainFromURL($_SERVER['SERVER_NAME'], 0);
if (! preg_match('/\.(png|jpg)$/', $favicon)) $favicon.='.png';
if (! empty($conf->global->MAIN_FAVICON_URL)) $favicon=$conf->global->MAIN_FAVICON_URL;

$head = '';
if ($favicon) $head.='<link rel="icon" href="img/'.$favicon.'">'."\n";
$head.='<!-- Bootstrap core CSS -->
<link href="dist/css/bootstrap.css" type="text/css" rel="stylesheet">
<link href="dist/css/myaccount.css" type="text/css" rel="stylesheet">';

$title = $langs->trans("Registration").($tmpproduct->label?' ('.$tmpproduct->label.')':'');

llxHeader($head, $title, '', '', 0, 0, array(), array('../dist/css/myaccount.css'));

?>

<div id="waitMask" style="display:none;">
	<font size="3em" style="color:#888; font-weight: bold;"><?php echo $langs->trans("InstallingInstance") ?><br><?php echo $langs->trans("PleaseWait") ?><br></font>
	<img id="waitMaskImg" width="100px" src="<?php echo 'ajax-loader.gif'; ?>" alt="Loading" />
</div>

<div class="signup">

	  <div style="text-align: center;">
		<?php
		// $generateddbhostname is the name of instance we tried to deploy
		$sellyoursaasdomain = getDomainFromURL($_SERVER['SERVER_NAME'], 1);

		// Show logo (search in order: small company logo, large company logo, theme logo, common logo)
		$linklogo = '';
		$constlogo = 'SELLYOURSAAS_LOGO';
		$constlogosmall = 'SELLYOURSAAS_LOGO_SMALL';

		$constlogoalt = 'SELLYOURSAAS_LOGO_'.str_replace('.', '_', strtoupper($sellyoursaasdomain));
		$constlogosmallalt = 'SELLYOURSAAS_LOGO_SMALL_'.str_replace('.', '_', strtoupper($sellyoursaasdomain));

		if (getDolGlobalString($constlogoalt)) {
			$constlogo=$constlogoalt;
			$constlogosmall=$constlogosmallalt;
		}

		if (empty($linklogo) && ! empty($conf->global->$constlogosmall)) {
			if (is_readable($conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constlogosmall)) {
				$linklogo=DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode('logos/thumbs/'.$conf->global->$constlogosmall);
			}
		} elseif (empty($linklogo) && ! empty($conf->global->$constlogo)) {
			if (is_readable($conf->mycompany->dir_output.'/logos/'.$conf->global->$constlogo)) {
				$linklogo=DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode('logos/'.$conf->global->$constlogo);
			}
		} else {
			$linklogo = DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&file='.urlencode('logos/thumbs/'.getDolGlobalString('SELLYOURSAAS_LOGO_SMALL', 'notdefined.png'));
		}

		if (GETPOST('partner', 'alpha')) {
			$tmpthirdparty = new Societe($db);
			$result = $tmpthirdparty->fetch(GETPOST('partner', 'alpha'));
			$logo = $tmpthirdparty->logo;
		}
		print '<img style="center" class="logoheader"  src="'.$linklogo.'" id="logo" />';
		?>
	  </div>
	  <div class="block medium">

		<header class="inverse">
		  <h1><?php echo $langs->trans("Registration") ?> <small><?php echo ($tmpproduct->label?' - '.$tmpproduct->label:''); ?></small></h1>
		</header>


	  <form action="register_instance" method="post" id="formregister">
		<div class="form-content">
		  <input type="hidden" name="token" value="<?php echo newToken(); ?>" />
		  <input type="hidden" name="service" value="<?php echo dol_escape_htmltag($tmpproduct->ref); ?>" />
		  <input type="hidden" name="extcss" value="<?php echo dol_escape_htmltag($extcss); ?>" />
		  <input type="hidden" name="package" value="<?php echo dol_escape_htmltag($tmppackage->ref); ?>" />
		  <input type="hidden" name="partner" value="<?php echo dol_escape_htmltag($partner); ?>" />
		  <input type="hidden" name="disablecustomeremail" value="<?php echo dol_escape_htmltag($disablecustomeremail); ?>" />

		  <section id="enterUserAccountDetails">

			<center>OOPS...</center>
			<?php
			dol_print_error_email('DEPLOY-'.$generateddbhostname.'-', '', $errormessages, 'alert alert-error');

			/*
			$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;
			$sellyoursaasemail = $conf->global->SELLYOURSAAS_SUPERVISION_EMAIL;
			$sellyoursaasemailnoreply = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;

			$sellyoursaasdomain=getDomainFromURL($_SERVER['SERVER_NAME'], 1);
			$constforaltname = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$sellyoursaasdomain;
			$constforaltemailto = 'SELLYOURSAAS_SUPERVISION_EMAIL-'.$sellyoursaasdomain;
			$constforaltemailnoreply = 'SELLYOURSAAS_NOREPLY_EMAIL-'.$sellyoursaasdomain;
			if (! empty($conf->global->$constforaltname))
			{
				$sellyoursaasdomain = $sellyoursaasdomain;
				$sellyoursaasname = $conf->global->$constforaltname;
				$sellyoursaasemail = $conf->global->$constforaltemailto;
				$sellyoursaasemailnoreply = $conf->global->$constforaltemailnoreply;
			}

			$to = $sellyoursaasemail;
			$from = $sellyoursaasemailnoreply;
			$email = new CMailFile('[Alert] Failed to deploy instance '.$generateddbhostname.' - '.dol_print_date(dol_now(), 'dayhourrfc'), $to, $from, join("\n",$errormessages)."\n", array(), array(), array(), $conf->global->SELLYOURSAAS_SUPERVISION_EMAIL, '', 0, 0, '', '', '', '', 'emailing');
			$email->sendfile();
			*/
			?>

		  </section>
		</div>
	   </form>
	   </div>
</div>

<?php
llxFooter();
