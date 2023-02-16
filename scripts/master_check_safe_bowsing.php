#!/usr/bin/php
<?php
/* Copyright (C) 2012-2019 Laurent Destailleur	<eldy@users.sourceforge.net>
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

/**
 *      \file       sellyoursaas/scripts/master_check_safe_browsing.php
 *		\ingroup    sellyoursaas
 *      \brief      Script to test the Google Safe browsing status of some URLs
 */

if (!defined('NOSESSION')) define('NOSESSION', '1');
if (!defined('NOREQUIREVIRTUALURL')) define('NOREQUIREVIRTUALURL', '1');

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit;
}

// Global variables
$version='1.0';
$error=0;

$dirroot=isset($argv[1])?$argv[1]:'';
$instance=isset($argv[2])?$argv[2]:'';
$mode=isset($argv[3])?$argv[3]:'';

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
include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

//$conf->setValues($db);

$apikey = getDolGlobalString('GOOGLE_SEARCH_BROWSER_API_KEY');

if (empty($apikey)) {
	print "Can't find API key";
	exit(-1);
}

$url = 'https://safebrowsing.googleapis.com/v4/threatMatches:find?key='.$apikey;

$request = '{
	"client": {
	"clientId":      "DoliCloud",
	"clientVersion": "1.0"
	},
	"threatInfo": {
	"threatTypes":      ["MALWARE", "SOCIAL_ENGINEERING"],'."\n";
$request .= '"platformTypes":    ["WINDOWS"],'."\n";
$request .= '"threatEntryTypes": ["URL"],
	"threatEntries": [
	{"url": "https://flashcompo.with2.novafirstcloud.com/"},
	{"url": "http://www.urltocheck2.org/"},
	]
	}
}';
//var_dump($request);

$addheaders = array('Content-Type: application/json');

$tmparray = getURLContent($url, 'POSTALREADYFORMATED', $request, 1, $addheaders);

var_dump($tmparray);
