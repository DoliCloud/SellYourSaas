<?php
/* Copyright (C) 2001-2002	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2006-2017	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2009-2012	Regis Houssin			<regis.houssin@capnetworks.com>
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
 *     	\file       htdocs/sellyoursaas/public/spamreport.php
 *		\ingroup    sellyoursaas
 *		\brief      Page to report SPAM
 */

if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
// Do not check anti CSRF attack test
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
// If there is no need to load and show top and left menu
if (!defined("NOLOGIN")) {
	define("NOLOGIN", '1');
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1'); // Do not check IP defined into conf $dolibarr_main_restrict_ip
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}

// For MultiCompany module.
// Do not use GETPOST here, function is not defined and get of entity must be done before including main.inc.php
$entity=(! empty($_GET['entity']) ? (int) $_GET['entity'] : (! empty($_POST['entity']) ? (int) $_POST['entity'] : (! empty($_GET['e']) ? (int) $_GET['e'] : (! empty($_POST['e']) ? (int) $_POST['e'] : 1))));
if (is_numeric($entity)) define("DOLENTITY", $entity);

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

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/payments.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societeaccount.class.php';

$mode = GETPOST('mode', 'aZ09');
$key = GETPOST('key', 'aZ09');

if ($key != $conf->global->SELLYOURSAAS_SECURITY_KEY) {
	print 'Bad value for securitykey';
	http_response_code(400);
	exit(0);
}


/*
 * View
 */

$tmpfile = DOL_DATA_ROOT.'/dolibarr_sellyoursaas_spamreport.log';
$date = strftime("%Y-%m-%d %H:%M:%S", time());
$body = file_get_contents('php://input');

file_put_contents($tmpfile, "\n***** Spam report received ".$date."*****\n", FILE_APPEND);
file_put_contents($tmpfile, var_export($_SERVER, true), FILE_APPEND);
file_put_contents($tmpfile, $body, FILE_APPEND);
file_put_contents($tmpfile, "\n", FILE_APPEND);

echo "Spam report received at ".$date."<br>\n";
echo "Content of alert message received:<br>\n";
echo dol_escape_htmltag($body);
echo "<br>\n";



// Send email
file_put_contents($tmpfile, "Now we send an email to supervisor ".$conf->global->SELLYOURSAAS_SUPERVISION_EMAIL."\n", FILE_APPEND);

$headers = 'From: <'.$conf->global->SELLYOURSAAS_NOREPLY_EMAIL.">\r\n";
if ($mode != 'test' && $mode != 'nomail') {
	if (empty($conf->global->SELLYOURSAAS_SPAMREPORT_EMAIL_DISABLED)) {
		$success=mail($conf->global->SELLYOURSAAS_SUPERVISION_EMAIL, '[Alert] Spam report received from external SMTP service', 'Spam was reported by external SMTP service:'."\r\n".($body ? $body : 'Body empty'), $headers);
		if (!$success) {
			$errorMessage = error_get_last()['message'];
			print dol_escape_htmltag($errorMessage);
		} else {
			file_put_contents($tmpfile, "Email sent to ".dol_escape_htmltag($conf->global->SELLYOURSAAS_SUPERVISION_EMAIL)."\n", FILE_APPEND);
			print "Email sent to ".dol_escape_htmltag($conf->global->SELLYOURSAAS_SUPERVISION_EMAIL)."<br>\n";
		}
	} else {
		file_put_contents($tmpfile, "Email not sent (email on spamreport disabled)\n", FILE_APPEND);
		print "Email not sent (email on spamreport disabled)<br>\n";
	}
} else {
	file_put_contents($tmpfile, "Email not sent (test mode)\n", FILE_APPEND);
	print "Email not sent (test mode)<br>\n";
}

// Send to DataDog (metric + event)
if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED)) {
	if (empty($conf->global->SELLYOURSAAS_SPAMREPORT_DATADOG_DISABLED)) {
		try {
			file_put_contents($tmpfile, "Now we send ping to DataDog\n", FILE_APPEND);
			echo "Now we send ping to DataDog<br>\n";

			dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

			$arrayconfig=array();
			if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY)) {
				$arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
			}

			$statsd = new DataDog\DogStatsd($arrayconfig);

			$arraytags=null;

			// Add metric in Datadog
			file_put_contents($tmpfile, "Ping metric spamreported ".($mode != 'test' ? 'sent' : 'not sent (test mode)')." to DataDog\n", FILE_APPEND);
			if ($mode != 'test' && $mode != 'nodatadog') {
				$statsd->increment('sellyoursaas.spamreported', 1, $arraytags);
			}

			// Add event in Datadog
			$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;
			$sellyoursaasdomain = $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME;

			$domainname=getDomainFromURL($_SERVER['SERVER_NAME'], 1);          // exemple 'DoliCloud'
			$constforaltname = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$domainname;     // exemple 'dolicloud.com'
			if (! empty($conf->global->$constforaltname)) {
				$sellyoursaasdomain = $domainname;
				$sellyoursaasname = $conf->global->$constforaltname;
			}

			$titleofevent =  dol_trunc('[Warning] '.$sellyoursaasname.' - '.gethostname().' - Spam of a customer detected', 90);

			if ($mode != 'test' && $mode != 'nodatadog') {
				$body = file_get_contents('php://input');

				$statsd->event($titleofevent,
					array(
						'text'       => "Spam of a customer detected.\n@".$conf->global->SELLYOURSAAS_SUPERVISION_EMAIL."\n\n".$body."\n".var_export($_SERVER, true),
						'alert_type' => 'warning',
						'source_type_name' => 'API',
						'host'       => gethostname()
					)
				);
			}

			file_put_contents($tmpfile, "Ping event ".($mode != 'test' ? 'sent' : 'not sent (test mode)')." to DataDog\n", FILE_APPEND);
			echo "Ping ".($mode != 'test' ? 'sent' : 'not sent (test mode)')." to DataDog<br>\n";
		} catch (Exception $e) {
			// Nothing in exception
		}
	} else {
		file_put_contents($tmpfile, "Datadog ping not sent (datadog ping on spamreport disabled)\n", FILE_APPEND);
		print "Datadog ping not sent (datadog ping on spamreport disabled)<br>\n";
	}
}
