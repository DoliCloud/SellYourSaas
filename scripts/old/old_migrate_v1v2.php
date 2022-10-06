#!/usr/bin/php
<?php
/* Copyright (C) 2012 Laurent Destailleur	<eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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
 *
 * Update an instance on stratus5 server with new ref version.
 */

if (!defined('NOSESSION')) define('NOSESSION', '1');

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}

// Global variables
$version='1.0';
$error=0;

// Include Dolibarr environment
@set_time_limit(0);							// No timeout for this script
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);		// Set this define to 0 if you want to lock your script when dolibarr setup is "locked to admin user only".

// Load Dolibarr environment
$res=0;
// Try master.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/master.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/master.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/master.inc.php")) $res=@include dirname(substr($tmp, 0, ($i+1)))."/master.inc.php";
// Try master.inc.php using relative path
if (! $res && file_exists("../master.inc.php")) $res=@include "../master.inc.php";
if (! $res && file_exists("../../master.inc.php")) $res=@include "../../master.inc.php";
if (! $res && file_exists("../../../master.inc.php")) $res=@include "../../../master.inc.php";
if (! $res) die("Include of master fails");
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

dol_include_once("/sellyoursaas/core/lib/dolicloud.lib.php");
dol_include_once('/sellyoursaas/class/packages.class.php');
include_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

$langs->loadLangs(array("main", "errors"));

$db2=getDoliDBInstance('mysqli', $conf->global->DOLICLOUD_DATABASE_HOST, $conf->global->DOLICLOUD_DATABASE_USER, $conf->global->DOLICLOUD_DATABASE_PASS, $conf->global->DOLICLOUD_DATABASE_NAME, $conf->global->DOLICLOUD_DATABASE_PORT);
if ($db2->error) {
	dol_print_error($db2, "host=".$conf->global->DOLICLOUD_DATABASE_HOST.", port=".$conf->global->DOLICLOUD_DATABASE_PORT.", user=".$conf->global->DOLICLOUD_DATABASE_USER.", databasename=".$conf->global->DOLICLOUD_DATABASE_NAME.", ".$db2->error);
	exit(-1);
}

$contract = new Contrat($db);

$defaultproductref='DOLICLOUD-PACK-Dolibarr';

$oldinstance=isset($argv[1])?$argv[1]:'';
$newinstance=isset($argv[2])?$argv[2]:'';
$mode=isset($argv[3])?$argv[3]:'';

$langsen = new Translate('', $conf);
$langsen->setDefaultLang($mysoc->default_lang);
$langsen->loadLangs(array("main", "errors"));

$user->fetch($conf->global->SELLYOURSAAS_ANONYMOUSUSER);


/*
 *	Main
 */

print "***** ".$script_file." *****\n";

if (empty($oldinstance) || empty($newinstance) || empty($mode)) {
	print "Migrate an old instance on new server. Script must be ran with root.\n";
	print "Usage: ".$script_file." oldinstance newinstance (test|confirm) [".$defaultproductref."]\n";
	print "Return code: 0 if success, <>0 if error\n";
	exit(-1);
}

if (0 != posix_getuid()) {
	echo "Script must be ran with root.\n";
	exit(-1);
}

if (! empty($oldinstance) && ! preg_match('/\.on\.dolicloud\.com$/', $oldinstance) && ! preg_match('/\.home\.lan$/', $oldinstance)) {
	$oldinstance=$oldinstance.".on.dolicloud.com";
}
// Forge complete name of instance
if (! empty($newinstance) && ! preg_match('/\./', $newinstance) && ! preg_match('/\.home\.lan$/', $newinstance)) {
	$tmparray = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
	$tmpstring = preg_replace('/:.*$/', '', $tmparray[0]);
	$newinstance=$newinstance.".".$tmpstring;   // Automatically concat first domain name
}

$oldobject = new Contrat($db2);
$result=$oldobject->fetch('', $oldinstance);
if ($result <= 0) {
	print "Error: old instance ".$oldinstance." not found.\n";
	exit(-2);
}
if (empty($oldobject->instance) || empty($oldobject->username_web) || empty($oldobject->password_web) || empty($oldobject->database_db)) {
	print "Error: Some properties for old instance ".$oldinstance." was not registered into database.\n";
	exit(-3);
}
if (isset($argv[4])) $productref = $argv[4];
elseif ($oldobject->plan == 'Dolibarr ERP & CRM Basic') {
	$productref='DOLICLOUD-PACK-Dolibarr';
} elseif ($oldobject->plan == 'Dolibarr ERP & CRM Basic (yearly)') {
	$productref='DOLICLOUD-PACK-Dolibarr';
	$overwritefrequencyunit='y';
} elseif ($oldobject->plan == 'Dolibarr ERP & CRM Premium') {
	$productref='DOLICLOUD-PACK-DolibarrPrem';
} elseif ($oldobject->plan == 'Dolibarr ERP & CRM Premium (yearly)') {
	$productref='DOLICLOUD-PACK-DolibarrPrem';
	$overwritefrequencyunit='y';
} elseif ($oldobject->plan == 'DoliPos Basic') {
	$productref='DOLICLOUD-PACK-DoliPos';
} elseif ($oldobject->plan == 'Dolibarr ERP & CRM 2Byte Basic') {
	$productref='DOLICLOUD-PACK-Dolibarr';
	$overwritefksoc=414;
} else {
	print 'Unknown plan '.$oldobject->plan."\n";
	exit(-4);
}
//var_dump(dol_print_date($oldobject->date_current_period_end, 'dayhour'));

$createthirdandinstance = 0;

$idofinstancefound = 0;

$sql = "SELECT c.rowid, c.statut";
$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object";
$sql.= "  WHERE c.entity IN (".getEntity('contract').")";
$sql.= " AND c.statut > 0";
$sql.= " AND c.ref_customer = '".$db->escape($newinstance)."'";
$sql.= " AND ce.deployment_status = 'done'";
$resql = $db->query($sql);
if (! $resql) {
	dol_print_error($resql);
	exit(-2);
}
$num_rows = $db->num_rows($resql);
if ($num_rows > 1) {
	print 'Error: several instance with this name found'."\n";
	exit(-2);
} else {
	$obj = $db->fetch_object($resql);
	if ($obj) $idofinstancefound = $obj->rowid;
}

include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
$newobject = new Contrat($db);
$result=0;
if ($idofinstancefound) $result=$newobject->fetch($idofinstancefound);
if ($result <= 0 || $newobject->statut == 0) {
	print "newinstance ".$newinstance." with status > 0 not found. Do you want to create new instance (and thirdparty with email ".$oldobject->email." if required)";

	$line = '';
	while (strtolower(trim($line)) != 'y' && strtolower(trim($line)) != 'n') {
		$line = readline(' (y/N) ? ');
	}
	if (trim($line) != 'y') {
		// Exit by default
		print "Canceled\n";
		exit(-2);
	}

	$errormessages = array();
	$idofinstancecreated = 0;
	$createthirdandinstance = 1;
	$reusecontractid = 0;
	$reusesocid = 0;
	$productid = 0;
	$password = 'tochange';
	$orgname = $oldobject->organization;
	$email = $oldobject->email;
	$country_code = $oldobject->country_code;
	$locale = $oldobject->locale;
	$taxid = $oldobject->vat_number;
	if (strlen($locale) == 2) $locale = $locale.'_'.strtoupper($locale);

	// $oldobject->plan contains something like 'Dolibarr ERP & CRM Premium'
	$partner = ($overwritefksoc ? $overwritefksoc : 0);

	$tmpproduct = new Product($db);
	$tmppackage = new Packages($db);
	if (empty($reusecontractid)) {
		$result = $tmpproduct->fetch($productid, $productref);
		if (empty($tmpproduct->id)) {
			print 'Service/Plan (Product id / ref) '.$productid.' / '.$productref.' was not found.';
			exit(-1);
		}
		// We have the main product, we are searching the package
		if (empty($tmpproduct->array_options['options_package'])) {
			print 'Service/Plan (Product id / ref) '.$tmpproduct->id.' / '.$productref.' has no package defined on it.';
			exit(-1);
		}
		// We have the main product, we are searching the duration
		if (empty($tmpproduct->duration_value) || empty($tmpproduct->duration_unit)) {
			print 'Service/Plan name (Product ref) '.$productref.' has no default duration';
			exit(-1);
		}

		$tmppackage->fetch($tmpproduct->array_options['options_package']);
		if (empty($tmppackage->id)) {
			print 'Package with id '.$tmpproduct->array_options['options_package'].' was not found.';
			exit(-1);
		}
	}

	$freeperioddays = $tmpproduct->array_options['options_freeperioddays'];
	$freeperioddays += 15;

	$now = dol_now();


	// Create thirdparty

	$tmpthirdparty = new Societe($db);
	$result = $tmpthirdparty->fetch(0, '', '', '', '', '', '', '', '', '', $email);
	if ($result < 0) {
		dol_print_error_email('FETCHTP'.$email, $tmpthirdparty->error, $tmpthirdparty->errors, 'alert alert-error');
		exit(-1);
	} elseif ($result > 0) {	// Found one record
		$reusesocid = $tmpthirdparty->id;

		// Comment this to accept existing thirdparties
		//dol_print_error_email('FETCHTP'.$email, 'Thirdparty already exists', null, 'alert alert-error');
		//exit(-2);
	} else {
		dol_syslog("Email not already used. Good.");
	}

	$generatedunixlogin = strtolower('osu'.substr(getRandomPassword(true, array('I')), 0, 9));		// Must be lowercase as it can be used for default email
	$generatedunixpassword = substr(getRandomPassword(true, array('I')), 0, 10);

	$generateddbname = 'dbn'.substr(getRandomPassword(true, array('I')), 0, 8);
	$generateddbusername = 'dbu'.substr(getRandomPassword(true, array('I')), 0, 9);
	$generateddbpassword = substr(getRandomPassword(true, array('I')), 0, 10);
	$generateddbhostname = $newinstance;
	$generateddbport = 3306;
	$generatedunixhostname = $newinstance;


	// Create thirdparty

	$db->begin();	// Start transaction

	$tmpthirdparty->oldcopy = dol_clone($tmpthirdparty);

	$password_encoding = 'password_hash';
	$password_crypted = dol_hash($password);

	$tmpthirdparty->name = $orgname;
	$tmpthirdparty->email = $email;
	$tmpthirdparty->client = 3;
	$tmpthirdparty->tva_assuj = 1;
	$tmpthirdparty->tva_intra = $taxid;
	$tmpthirdparty->default_lang = ($locale ? $locale : $langs->defaultlang);
	$tmpthirdparty->array_options['options_firstname'] = $oldobject->firstname;
	$tmpthirdparty->array_options['options_lastname'] = $oldobject->lastname;
	$tmpthirdparty->array_options['options_dolicloud'] = 'yesv2';
	$tmpthirdparty->array_options['options_date_registration'] = dol_now();
	$tmpthirdparty->array_options['options_source']='MIGRATIONV1';
	$tmpthirdparty->array_options['options_password'] = $password;
	$tmpthirdparty->array_options['options_oldpassword'] = $oldobject->personpassword;	// Come from  person.password (search is possible in table with email)

	if ($country_code) {
		$tmpthirdparty->country_id = getCountry($country_code, 3, $db);
	}

	if ($tmpthirdparty->id > 0) {
		if (empty($reusesocid) || $tmpthirdparty->oldcopy->array_options['options_dolicloud'] == 'yesv1') {
			print "Update thirdparty with id=".$tmpthirdparty->id."\n";
			$result = $tmpthirdparty->update(0, $user);
			if ($result <= 0) {
				$db->rollback();
				//setEventMessages($tmpthirdparty->error, $tmpthirdparty->errors, 'errors');
				//header("Location: ".$newurl);
				dol_print_error($db, $tmpthirdparty->error, $tmpthirdparty->errors);
				exit(-1);
			}
		}
	} else {
		// Set lang to backoffice language
		$savlangs = $langs;
		$langs = $langsen;

		$tmpthirdparty->code_client = -1;
		if ($partner > 0) $tmpthirdparty->parent = $partner;		// Add link to parent/reseller

		print "Create thirdparty\n";
		$result = $tmpthirdparty->create($user);
		if ($result <= 0) {
			$db->rollback();
			//setEventMessages($tmpthirdparty->error, $tmpthirdparty->errors, 'errors');
			//header("Location: ".$newurl);
			dol_print_error($db, $tmpthirdparty->error, $tmpthirdparty->errors);
			exit(-1);
		}

		// Restore lang to user/visitor language
		$langs = $savlangs;
	}

	if (! empty($conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG)) {
		print "Set category of customer ".$conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG."\n";
		$result = $tmpthirdparty->setCategories(array($conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG => $conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG), 'customer');
		if ($result < 0) {
			$db->rollback();
			//setEventMessages($tmpthirdparty->error, $tmpthirdparty->errors, 'errors');
			//header("Location: ".$newurl);
			dol_print_error($db, $tmpthirdparty->error, $tmpthirdparty->errors);
			exit(-1);
		}
	} else {
		$db->rollback();
		dol_print_error_email('SETUPTAG', 'Setup of module not complete. The default customer tag is not defined.', null, 'alert alert-error');
		exit(-1);
	}

	// Date new payment for recurring invoice
	$date_next_execution = 0;
	if (! empty($oldobject->date_current_period_end)) {
		$date_next_execution = $oldobject->date_current_period_end;
		if ($date_next_execution < (dol_now() + 3600 * 24 * 7)) $date_next_execution = dol_time_plus_duree($date_next_execution, 1, 'm');
	}

	$date_start = $now;
	$date_end = dol_time_plus_duree($date_start, $freeperioddays, 'd');
	$date_endfreeperiod = $oldobject->date_endfreeperiod;

	if (! $error) {
		print "Create contract with deployment status 'Processing'\n";
		dol_syslog("Create contract with deployment status 'Processing'");

		$contract->ref_customer = $newinstance;
		$contract->socid = $tmpthirdparty->id;
		$contract->commercial_signature_id = $user->id;
		$contract->commercial_suivi_id = $user->id;
		$contract->date_contrat = $now;
		$contract->note_private = 'Contract created from the online instance registration form.';

		$contract->array_options['options_plan'] = $tmpproduct->ref;
		$contract->array_options['options_deployment_status'] = 'processing';
		$contract->array_options['options_deployment_date_start'] = $now;
		$contract->array_options['options_deployment_init_email'] = $email;
		$contract->array_options['options_deployment_init_adminpass'] = $password;
		$contract->array_options['options_date_endfreeperiod'] = $date_endfreeperiod;
		$contract->array_options['options_undeployment_date'] = '';
		$contract->array_options['options_undeployment_ip'] = '';
		$contract->array_options['options_hostname_os'] = $generatedunixhostname;
		$contract->array_options['options_username_os'] = $generatedunixlogin;
		$contract->array_options['options_password_os'] = $generatedunixpassword;
		$contract->array_options['options_hostname_db'] = $generateddbhostname;
		$contract->array_options['options_database_db'] = $generateddbname;
		$contract->array_options['options_port_db'] = $generateddbport;
		$contract->array_options['options_username_db'] = $generateddbusername;
		$contract->array_options['options_password_db'] = $generateddbpassword;
		$contract->array_options['options_custom_url'] = $oldinstance;
		//$contract->array_options['options_nb_users'] = 1;
		//$contract->array_options['options_nb_gb'] = 0.01;

		$contract->array_options['options_deployment_ip'] = $_SERVER["REMOTE_ADDR"];
		$vpnproba = '';
		$contract->array_options['options_deployment_vpn_proba'] = $vpnproba;

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

		$idofinstancecreated = $contract->create($user);
		if ($idofinstancecreated <= 0) {
			$db->rollback();
			dol_print_error_email('CREATECONTRACT', $contract->error, $contract->errors, 'alert alert-error');
			exit(-1);
		}
	}

	$object = $tmpthirdparty;

	// Create contract line for INSTANCE
	if (! $error) {
		print "Add line to contract for INSTANCE with freeperioddays = ".$freeperioddays."\n";
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
		//var_dump($vat);exit(-1);

		$price = $tmpproduct->price;
		if (! empty($overwritepriceinstance)) $price = $overwritepriceinstance;

		$discount = 0;
		if (! empty($overwritediscount)) $discount = $overwritediscount;

		$productidtocreate = $tmpproduct->id;

		$contractlineid = $contract->addline('', $price, $qty, $vat, $localtax1_tx, $localtax2_tx, $productidtocreate, $discount, $date_start, $date_end, 'HT', 0);
		if ($contractlineid < 0) {
			$db->rollback();
			dol_print_error_email('CREATECONTRACTLINE1', $contract->error, $contract->errors, 'alert alert-error');
			exit(-1);
		}
	}

	//var_dump('user:'.$dolicloudcustomer->price_user);
	//var_dump('instance:'.$dolicloudcustomer->price_instance);
	//exit(-1);

	$j=1;

	// Create contract line for other products
	if (! $error) {
		print "Add line to contract for depending products (like USERS or options)\n";
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

			$price = $tmpsubproduct->price;
			if (! empty($overwritepriceuser)) $price = $overwritepriceuser;

			$discount = 0;
			if (! empty($overwritediscount)) $discount = $overwritediscount;

			if ($qty > 0) {
				$j++;

				$contractlineid = $contract->addline('', $price, $qty, $vat, $localtax1_tx, $localtax2_tx, $prodid, $discount, $date_start, $date_end, 'HT', 0);
				if ($contractlineid < 0) {
					$db->rollback();
					dol_print_error_email('CREATECONTRACTLINE'.$j, $contract->error, $contract->errors, 'alert alert-error');
					exit(-1);
				}
			}
		}
	}

	print "Reload all lines after creation (".$j." lines in contract) to have contract->lines ok\n";
	dol_syslog("Reload all lines after creation (".$j." lines in contract) to have contract->lines ok");
	$contract->fetch_lines();

	$result=$newobject->fetch($idofinstancecreated);
	if ($result <= 0) {
		$db->rollback();
		print "Error: newinstance ".$newinstance." still not found";
		exit(-1);
	}

	if (! $error) {
		$db->commit();
	} else {
		$db->rollback();
	}


	if (! $error && $productref != 'none') {
		dol_include_once('/sellyoursaas/class/sellyoursaasutils.class.php');
		$sellyoursaasutils = new SellYourSaasUtils($db);

		$result = $sellyoursaasutils->sellyoursaasRemoteAction('deployall', $contract, 'admin', $email, $password);
		if ($result <= 0) {
			$error++;
			$errormessages[]=$sellyoursaasutils->errors;
			if ($sellyoursaasutils->error) $errormessages[]=$sellyoursaasutils->error;
		}
	}


	// Finish deployall - Activate all lines
	if (! $error && $productref != 'none') {
		dol_syslog("Activate all lines - by register_instance");

		$contract->context['deployallwasjustdone']=1;		// Add a key so trigger into activateAll will know we have just made a "deployall"

		if ($fromsocid) $comment = 'Activation after deployment from migration for reseller id='.$fromsocid;
		else $comment = 'Activation after deployment from migration';

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

		// Set cookie to store last registered instance
		$prefix=dol_getprefix('');
		$cookieregistrationa='DOLREGISTERA_'.$prefix;
		$cookieregistrationb='DOLREGISTERB_'.$prefix;
		$nbregistration = ((int) $_COOKIE[$cookieregistrationa] + 1);
		//setcookie($cookieregistrationa, $nbregistration, 0, "/", null, false, true);	// Cookie to count nb of registration from this computer
		//setcookie($cookieregistrationb, dol_encode($contract->ref_customer), 0, "/", null, false, true);					// Cookie to save previous registered instance

		$result = $contract->update($user);
		if ($result < 0) {
			// We ignore errors. This should not happen in real life.
			//setEventMessages($contract->error, $contract->errors, 'errors');
		}
	}

	// Create template invoice if there is not yet template invoice
	if (! $error && $productref != 'none') {
		dol_syslog("--- Create recurring invoice on contract if it does not have yet.", LOG_DEBUG, 0);

		// Make a test to pass loop if there is already a template invoice
		$result = $contract->fetchObjectLinked();
		if ($result < 0) {
			print 'Error in fetch object linked on contract, so we cancel migration.';
			exit(1);							// There is an error, so we discard this contract to avoid to create template twice
		}
		if (! empty($contract->linkedObjectsIds['facturerec'])) {
			$templateinvoice = reset($contract->linkedObjectsIds['facturerec']);
			if ($templateinvoice > 0) {			// There is already a template invoice, so we discard this contract to avoid to create template twice
				print 'There is already a template invoice on target instance, so we cancel migration.';
				exit(2);
			}
		}
		if ($contract->array_options['options_deployment_status'] != 'done') {
			print 'Not a valid target instance (status not Done), so we cancel migration.';
			exit(3);							// This is a not valid contract (undeployed or not yet completely deployed), so we discard this contract to avoid to create template not expected
		}

		dol_syslog("--- No template invoice found for the contract contract_id = ".$contract->id.", so we refresh contract before creating template invoice + creating invoice (if template invoice date is already in past) + making contract renewal.", LOG_DEBUG, 0);


		// First launch update of resources: This update status of install.lock+authorized key and update qty of contract lines
		$result = $sellyoursaasutils->sellyoursaasRemoteAction('refresh', $contract);


		dol_syslog("--- No template invoice found for the contract contract_id = ".$contract->id.", so we create it then create real invoice (if template invoice date is already in past) then make contract renewal.", LOG_DEBUG, 0);

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
					if (! empty($date_next_execution)) $date_end = $date_next_execution;

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

				// For yearly subscription, increase price by factor 12.
				if (! empty($overwritefrequencyunit) && $overwritefrequencyunit = 'y') $price_invoice_template_line = 12 * $price_invoice_template_line;

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

			$frequency=1;
			$frequency_unit='m';
			if (! empty($overwritefrequencyunit)) $frequency_unit = $overwritefrequencyunit;

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

			if (empty($date_next_execution)) $date_next_execution = dol_mktime($rehour, $remin, 0, $remonth, $reday, $reyear);
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
					$errormessages[]=$db->lasterror();
				}

				$result=$oldinvoice->delete($user, 1);
				if (! $error && $result < 0) {
					$error++;
					$errormessages[]=$oldinvoice->errors;
				}
			} else {
				$error++;
				$errormessages[]=$invoice_rec->errors;
			}
		}
	}

	if ($error) {
		print 'Error '.join("\n", $errormessages);
		exit(-8);
	}
} else {
	print 'Error: instance '.$newinstance.' with id '.$idofinstancefound.' already exists'."\n";
	exit(-9);
}

$newobject->instance = $newinstance;
$newobject->username_web = $newobject->array_options['options_username_os'];
$newobject->password_web = $newobject->array_options['options_password_os'];
$newobject->hostname_web = $newobject->array_options['options_hostname_os'];
$newobject->username_db  = $newobject->array_options['options_username_db'];
$newobject->password_db  = $newobject->array_options['options_password_db'];
$newobject->database_db  = $newobject->array_options['options_database_db'];

if (empty($newobject->instance) || empty($newobject->username_web) || empty($newobject->password_web) || empty($newobject->database_db)) {
	print "Error: Some properties for instance ".$newinstance." was not registered into database (missing instance, username_web, password_web or database_db.\n";
	exit(-3);
}

$olddirdb=preg_replace('/_([a-zA-Z0-9]+)/', '', $oldobject->database_db);
$oldlogin=$oldobject->username_web;
$oldpassword=$oldobject->password_web;
$oldloginbase=$oldobject->username_db;
$oldpasswordbase=$oldobject->password_db;
$newdirdb=$newobject->database_db;
$newlogin=$newobject->username_web;
$newpassword=$newobject->password_web;
$newloginbase=$newobject->username_db;
$newpasswordbase=$newobject->password_db;

$sourcedir=$conf->global->DOLICLOUD_EXT_HOME.'/'.$oldlogin.'/'.$olddirdb;
$targetdir=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$newlogin.'/'.$newdirdb;
$oldserver=$oldobject->hostname_web;
$newserver=$newobject->array_options['options_hostname_os'];

if (empty($oldlogin) || empty($olddirdb)) {
	print "Error: properties for instance ".$oldinstance." are not registered completely (missing at least login or database name).\n";
	exit(-5);
}

$oldsftpconnectstring=$oldobject->username_web.'@'.$oldobject->hostname_web.':'.$conf->global->DOLICLOUD_EXT_HOME.'/'.$oldlogin.'/'.preg_replace('/_([a-zA-Z0-9]+)$/', '', $olddirdb);
$newsftpconnectstring=$newobject->username_web.'@'.$newobject->hostname_web.':'.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$newlogin.'/'.preg_replace('/_([a-zA-Z0-9]+)$/', '', $newdirdb);

print '--- Synchro of files '.$sourcedir.' to '.$targetdir."\n";
print 'SFTP old connect string : '.$oldsftpconnectstring."\n";
print 'SFTP new connect string : '.$newsftpconnectstring."\n";
print 'SFTP old password '.$oldobject->password_web."\n";
//print 'SFTP new password '.$newobject->password_web."\n";

$command="rsync";
$param=array();
if (! in_array($mode, array('confirm'))) $param[]="-n";
//$param[]="-a";
if (! in_array($mode, array('diff','diffadd','diffchange'))) $param[]="-rlt";
else { $param[]="-rlD"; $param[]="--modify-window=1000000000"; $param[]="--delete -n"; }
$param[]="-v";
if (empty($createthirdandinstance)) $param[]="-u";		// If we have just created instance, we overwrite file during rsync
$param[]="--exclude .buildpath";
$param[]="--exclude dolibarr.log";
$param[]="--exclude *.comdolibarrSSL";
$param[]="--exclude .git";
$param[]="--exclude .gitignore";
$param[]="--exclude .settings";
$param[]="--exclude .project";
$param[]="--exclude htdocs/conf/conf.php";
if (! in_array($mode, array('diff','diffadd','diffchange'))) $param[]="--stats";
if (in_array($mode, array('clean','confirmclean'))) $param[]="--delete";
$param[]="-e 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no'";

$param[]=$oldlogin.'@'.$oldserver.":".$sourcedir.'/*';
//$param[]=$newlogin.'@'.$newserver.":".$targetdir;
$param[]=$targetdir;

//var_dump($param);
$fullcommand=$command." ".join(" ", $param);
$output=array();
$return_var=0;
print $fullcommand."\n";
if ($mode != 'test') {
	exec($fullcommand, $output, $return_var);
}

// Output result
foreach ($output as $outputline) {
	print $outputline."\n";
}



print "-> Files were sync into dir of instance ".$newobject->ref_customer.": ".$targetdir."\n";
print "Using ".$fullcommand."\n";
print "\n";



print "--- Set permissions with chown -R ".$newlogin.".".$newlogin." ".$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$newlogin.'/'.$newdirdb."\n";
$output=array();
$return_varchmod=0;
if ($mode == 'confirm') {
	if (empty($conf->global->DOLICLOUD_INSTANCES_PATH) || empty($newlogin) || empty($newdirdb)) {
		print 'Bad value for data. We stop to avoid drama';
		exit(-7);
	}
	exec("chown -R ".$newlogin.".".$newlogin." ".$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$newlogin.'/'.$newdirdb, $output, $return_varchmod);
}

// Output result
foreach ($output as $outputline) {
	print $outputline."\n";
}

print "\n";

print "-> Files owner were modified for instance ".$newobject->ref_customer.": ".$targetdir." to user ".$newlogin."\n";


print '--- Dump database '.$oldobject->database_db.' into /tmp/mysqldump_'.$oldobject->database_db.'_'.gmstrftime('%d').".sql\n";

$command="mysqldump";
$param=array();
$param[]=$oldobject->database_db;
$param[]="-h";
$param[]=$oldserver;
$param[]="-u";
$param[]=$oldobject->username_db;
$param[]='-p"'.str_replace(array('"','`'), array('\"','\`'), $oldobject->password_db).'"';
$param[]="--compress";
$param[]="-l";
$param[]="--single-transaction";
$param[]="-K";
$param[]="--tables";
$param[]="-c";
$param[]="-e";
$param[]="--hex-blob";
$param[]="--default-character-set=utf8";

$fullcommand=$command." ".join(" ", $param);
$fullcommand.=' > /tmp/mysqldump_'.$oldobject->database_db.'_'.gmstrftime('%d').'.sql';
$output=array();
$return_varmysql=0;
print strftime("%Y%m%d-%H%M%S").' '.$fullcommand."\n";
if ($mode != 'test') {
	exec($fullcommand, $output, $return_varmysql);
	print strftime("%Y%m%d-%H%M%S").' mysqldump done (return='.$return_varmysql.')'."\n";
}

// Output result
foreach ($output as $outputline) {
	print $outputline."\n";
}



print '--- Disable new logins in '.$oldobject->database_db."\n";

$command="mysql";
$param=array();
$param[]=$oldobject->database_db;
$param[]="-h";
$param[]=$oldserver;
$param[]="-u";
$param[]=$oldobject->username_db;
$param[]='-p"'.str_replace(array('"','`'), array('\"','\`'), $oldobject->password_db).'"';

$fullcommand=$command." ".join(" ", $param);
$fullcommand.=' -e "REPLACE INTO llx_const (name, entity, value, type, visible) values(\'MAIN_ONLY_LOGIN_ALLOWED\', 0, \'nobody\', \'chaine\', 0);"';	//  UPDATE llx_user SET statut = 0 where login=\'admin\'"
$output=array();
$return_varmysql2=0;
print strftime("%Y%m%d-%H%M%S").' '.$fullcommand."\n";
if ($mode != 'test') {
	exec($fullcommand, $output, $return_varmysql2);
	print strftime("%Y%m%d-%H%M%S").' mysql done (return='.$return_varmysql2.')'."\n";
}

// Output result
foreach ($output as $outputline) {
	print $outputline."\n";
}



print '--- Load database '.$newobject->database_db.' from /tmp/mysqldump_'.$oldobject->database_db.'_'.gmstrftime('%d').".sql\n";
//print "If the load fails, try to run mysql -u".$newloginbase." -p".$newpasswordbase." -D ".$newobject->database_db."\n";

$fullcommanda='echo "drop table llx_accounting_account;" | mysql -u'.$newloginbase.' -p'.$newpasswordbase.' -D '.$newobject->database_db;
$output=array();
$return_varload=0;
print strftime("%Y%m%d-%H%M%S").' Drop table to prevent load error with '.$fullcommanda."\n";
if ($mode == 'confirm') {
	exec($fullcommanda, $output, $return_varload);
	foreach ($output as $line) print $line."\n";
}

$fullcommandb='echo "drop table llx_accounting_system;" | mysql -u'.$newloginbase.' -p'.$newpasswordbase.' -D '.$newobject->database_db;
$output=array();
$return_varload=0;
print strftime("%Y%m%d-%H%M%S").' Drop table to prevent load error with '.$fullcommandb."\n";
if ($mode == 'confirm') {
	exec($fullcommandb, $output, $return_varload);
	foreach ($output as $line) print $line."\n";
}

$fullcommand="cat /tmp/mysqldump_".$oldobject->database_db.'_'.gmstrftime('%d').".sql | mysql -u".$newloginbase." -p".$newpasswordbase." -D ".$newobject->database_db;
print strftime("%Y%m%d-%H%M%S")." Load dump with ".$fullcommand."\n";
if ($mode == 'confirm') {
	$output=array();
	$return_varload=0;
	print strftime("%Y%m%d-%H%M%S").' '.$fullcommand."\n";
	exec($fullcommand, $output, $return_varload);
	foreach ($output as $line) print $line."\n";
}

$fullcommandc='echo "UPDATE llx_const set value = \''.$newlogin.'\' WHERE name = \'CRON_KEY\';" | mysql -u'.$newloginbase.' -p'.$newpasswordbase.' -D '.$newobject->database_db;
$output=array();
$return_varcron=0;
print strftime("%Y%m%d-%H%M%S").' Update cron key '.$fullcommandc."\n";
if ($mode == 'confirm') {
	exec($fullcommandc, $output, $return_varcron);
	foreach ($output as $line) print $line."\n";
}



print "\n";

if ($mode == 'confirm') {
	print '-> Dump loaded into database '.$newobject->database_db.'. You can test instance on URL https://'.$newobject->ref_customer."\n";
	if (empty($createthirdandinstance)) print 'WARNING: The rsync was done with -u'."\n";
	print "Finished. DON'T FORGET TO\n";
	print " - SET INVOICING ON OLD SYSTEM FOR ".$oldinstance." TO MANUAL COLLECTION\n";
	print " - CHANGE TEMPLATE INVOICE PRICE IF SPECIFIC INVOICING\n";
	print " - CHANGE DNS /etc/bind/on.dolicloud.com.hosts OF ".$newobject->ref_customer." TO ".$conf->global->SELLYOURSAAS_SUB_DOMAIN_IP." AND THEN rndc reload on.dolicloud.com\n";
} else {
	print '-> Dump NOT loaded (test mode) into database '.$newobject->database_db.'. You can test instance on URL https://'.$newobject->ref_customer."\n";
	print "Finished. DON'T FORGET TO DELETE CONTRACT AND TEMPLATE INVOICE AFTER THIS TEST !!!\n";
}

echo "return detail is : $return_var + $return_varchmod + $return_varmysql + $return_varmysql2 + $return_varload + $return_varcron\n";
exit($return_var + $return_varchmod + $return_varmysql + $return_varmysql2 + $return_varload + $return_varcron);


// Add end do something like
// update record set address = '79.137.96.15' where address <> '79.137.96.15' AND domain_id IN (select id from domain where sld = 'testldr14') LIMIT 1;
