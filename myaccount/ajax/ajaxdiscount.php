<?php
/* Copyright (C) 2020 Laurent Destailleur <eldy@users.sourceforge.net>
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
 */

/**
 *       \file      sellyoursaas/myaccount/ajax/ajaxdiscount.php
 *       \ingroup	sellyoursaas
 *       \brief     File to return text for a discount
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
} // Disables token renewal
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}

// Add specific definition to allow a dedicated session management
require '../mainmyaccount.inc.php';

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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

include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

$langs->loadLangs(array("main", "sellyoursaas@sellyoursaas"));


/*
 * View
 */

top_httphead_sellyoursaas();

//print '<!-- Ajax page called with url '.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?'.dol_escape_htmltag($_SERVER["QUERY_STRING"]).' -->'."\n";

dol_syslog("GET is ".join(',', $_GET));
//var_dump(GETPOST('productid'));

$contractids = explode(',', GETPOST('contractids', 'alpha'));

$discountcode = GETPOST('discountcode', 'aZ09');

$tmpcontract = new Contrat($db);
$tmpproduct = new Product($db);

$listofvalidregisterdiscountcode = array();
foreach ($contractids as $contractid) {
	$tmpcontract->fetch($contractid);
	if ($tmpcontract->array_options['options_deployment_status'] == 'done') {
		$lines = $tmpcontract->fetch_lines();
		foreach ($lines as $line) {
			$tmpproduct->fetch($line->fk_product);
			if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
				dol_syslog("Found product_id=".$tmpproduct->id);
				if ($tmpproduct->array_options['options_register_discountcode']) {
					$tmparray = explode(',', $tmpproduct->array_options['options_register_discountcode']);
					foreach ($tmparray as $tmp) {
						$tmparray2 = explode(':', $tmp);
						$codefound = trim($tmparray2[0]);
						$valuefound = trim($tmparray2[1]);
						$listofvalidregisterdiscountcode[$line->fk_product.'_'.$codefound] = array('product_id' => $line->fk_product, 'code' => $codefound, 'value' => $valuefound);
					}
				}
				//var_dump("Found product_id=".$tmpproduct->id." ".$tmpproduct->array_options['options_register_discount_code']);
			}
		}
	}
}

$discountcodetext = '<span class="discountcodeko">'.$langs->trans("DiscountCodeNotValid").'</span>';

foreach ($listofvalidregisterdiscountcode as $key => $val) {
	if (strtoupper(trim($val['code'])) == strtoupper(trim($discountcode))) {
		$discountvalue = $val['value'];
		$discountcodetext = '<span class="discountcodeok">'.$langs->trans("DiscountCodeIsValid", $discountvalue).'<span>';
	}
}

$return_arr = array('discountcodetext'=>$discountcodetext);

echo json_encode($return_arr);

$db->close();
