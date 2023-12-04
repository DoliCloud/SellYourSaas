<?php
/* Copyright (C) 2005-2018 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *		\file       htdocs/sellyoursaas/myaccount/antispamimage.php
 *		\brief      Return antispam image
 */

if (! defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (! defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}				// Do not check IP defined into conf $dolibarr_main_restrict_ip
if (! defined('NOREQUIREUSER')) {
	define('NOREQUIREUSER', 1);
}
if (! defined('NOREQUIREDB')) {
	define('NOREQUIREDB', 1);
}
if (! defined('NOREQUIRETRAN')) {
	define('NOREQUIRETRAN', 1);
}
if (! defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (! defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', 1);
}
if (! defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (! defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}

require_once './mainmyaccount.inc.php';

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


/*
 * View
 */

$length=5;
$letters = 'aAbBCDeEFgGhHJKLmMnNpPqQRsStTuVwWXYZz2345679';
$number = strlen($letters);
$string = '';
for ($i = 0; $i < $length; $i++) {
	$string .= $letters[mt_rand(0, $number - 1)];
}
//print $string;


$sessionkey='dol_antispam_value';
$_SESSION[$sessionkey]=$string;

$img = imagecreate(80, 32);
if (empty($img)) {
	dol_print_error('', "Problem with GD creation");
	exit;
}

// Define mime type
top_httphead_sellyoursaas('image/png', 1);

$background_color = imagecolorallocate($img, 250, 250, 250);
$ecriture_color = imagecolorallocate($img, 0, 0, 0);
imagestring($img, 4, 24, 8, $string, $ecriture_color);
imagepng($img);
