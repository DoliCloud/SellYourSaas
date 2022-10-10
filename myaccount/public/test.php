<?php
/* This program is free software; you can redistribute it and/or modify
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
 *     	\file       test.php
 *		\brief      Page test
 */

if (! defined("NOLOGIN"))        define("NOLOGIN", '1');				    // If this page is public (can be called outside logged session)
if (! defined('NOCSRFCHECK'))    define('NOCSRFCHECK', '1');
if (! defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');

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
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societeaccount.class.php';




/*
 * View
 */

print '<html>
<head>
<script type="text/javascript" src="localdata.js"></script>
</head>

<body>';

$tmpfile = '/var/log/sellyoursaas_test.log';
$date = dol_print_date(dol_now('gmt'), "%Y-%m-%d %H:%M:%S", 'gmt');

file_put_contents($tmpfile, "\n***** Test report received ".$date."*****\n", FILE_APPEND);
file_put_contents($tmpfile, var_export($_SERVER, true), FILE_APPEND);
echo "Test report received at ".$date."<br>\n";

$body = file_get_contents('php://input');
file_put_contents($tmpfile, $body, FILE_APPEND);

echo "Content of message received:<br>\n";
echo $body;

file_put_contents($tmpfile, "\n", FILE_APPEND);
echo "<br>\n";


echo "Now make internal js test...<br>\n";


// Send email
//file_put_contents($tmpfile, "Now we send an email to supervisor ".$conf->global->SELLYOURSAAS_SUPERVISION_EMAIL."\n", FILE_APPEND);

/*$headers = 'From: <'.$conf->global->SELLYOURSAAS_NOREPLY_EMAIL.">\r\n";
$success=mail($conf->global->SELLYOURSAAS_SUPERVISION_EMAIL, '[Alert] Spam report received from SendGrid', 'Spam was reported by SendGrid:'."\r\n".$body, $headers);
if (!$success) {
	$errorMessage = error_get_last()['message'];
	print $errorMessage;
}
else
{
	echo "Email sent to ".$conf->global->SELLYOURSAAS_SUPERVISION_EMAIL."<br>\n";
}

// Send to DataDog (metric + event)
if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED))
{
	try {
		file_put_contents($tmpfile, "Now we send ping to DataDog\n", FILE_APPEND);
		echo "Now we send ping to DataDog<br>\n";

		dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

		$arrayconfig=array();
		if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY))
		{
			$arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
		}

		$statsd = new DataDog\DogStatsd($arrayconfig);

		$arraytags=null;

		$statsd->increment('sellyoursaas.spamreported', 1, $arraytags);

		$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;
		$titleofevent =  dol_trunc('[Alert] '.$sellyoursaasname.' - '.gethostname().' - Spam of a customer detected', 90);
		$statsd->event($titleofevent,
			array(
				'text'       => "Spam of a customer detected.\n@".$conf->global->SELLYOURSAAS_SUPERVISION_EMAIL."\n\n".var_export($_SERVER, true),
				'alert_type' => 'warning',
				'source_type_name' => 'API',
				'host'       => gethostname()
			)
		);

		echo "Ping sent to DataDog<br>\n";
	}
	catch(Exception $e)
	{

	}
}
*/

print '</body>
</html>';
