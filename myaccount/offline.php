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
 *
 * This page can be called when virtual host make a redirect due to not a
 * virtual host that has been set to offline.
 */

//if (! defined('NOREQUIREUSER'))  define('NOREQUIREUSER','1');
//if (! defined('NOREQUIREDB'))    define('NOREQUIREDB','1');
//if (! defined('NOREQUIRESOC'))   define('NOREQUIRESOC','1');
//if (! defined('NOREQUIRETRAN'))  define('NOREQUIRETRAN','1');
//if (! defined('NOCSRFCHECK'))    define('NOCSRFCHECK','1');			// Do not check anti CSRF attack test
//if (! defined('NOSTYLECHECK'))   define('NOSTYLECHECK','1');			// Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL','1');		// Do not check anti POST attack test
//if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU','1');			// If there is no need to load and show top and left menu
//if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML','1');			// If we don't need to load the html.form.class.php
//if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');
if (! defined("NOLOGIN"))        define("NOLOGIN", '1');				    // If this page is public (can be called outside logged session)
if (! defined('NOIPCHECK'))      define('NOIPCHECK', '1');					// Do not check IP defined into conf $dolibarr_main_restrict_ip
if (! defined("MAIN_LANG_DEFAULT") && empty($_GET['lang'])) define('MAIN_LANG_DEFAULT', 'auto');
if (! defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');
//if (! defined('NOSESSION'))      define('NOSESSION', '1');

// Add specific definition to allow a dedicated session management
include './mainmyaccount.inc.php';

// Load Dolibarr environment
$res=0;
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
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';


$instance = GETPOST('instance');	// example: myinstance.with.sellyoursaasdomain.com
$messageonly = GETPOST('messageonly');	// offline.php?messageonly=1

// Search instance
$contract = new Contrat($db);
if ($instance) {
	$result = $contract->fetch(0, '', $instance);
	$contract->fetch_thirdparty();
}

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



/*
 * View
 */

if (empty($messageonly)) {
	top_htmlhead('', 'OffLine Page');

	print '
    <body id="offline" style="font-size: 1.2em">

    <br><br><br>
    <div style="text-align: center">
    <span class="fa fa-desktop" style="font-size: 40px; opacity: 0.3"></span><br><br>

    ';
}

// Show global announce
if (! empty($conf->global->SELLYOURSAAS_ANNOUNCE_ON) && ! empty($conf->global->SELLYOURSAAS_ANNOUNCE)) {
	$sql = "SELECT tms from ".MAIN_DB_PREFIX."const where name = 'SELLYOURSAAS_ANNOUNCE'";
	$resql=$db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$datemessage = $db->jdate($obj->tms);

		print '<div class="note note-warning">';
		print '<b>'.dol_print_date($datemessage, 'dayhour').'</b> : ';
		   $reg=array();
		if (preg_match('/^\((.*)\)$/', $conf->global->SELLYOURSAAS_ANNOUNCE, $reg)) {
			$texttoshow = $langs->trans($reg[1]);
		} else {
			$texttoshow = $conf->global->SELLYOURSAAS_ANNOUNCE;
		}
		print '<h4 class="block">'.$texttoshow.'</h4></div>';
	} else {
		dol_print_error($db);
	}
}

// TODO Implement the SELLYOURSAAS_ANNOUNCE_ON_xxxx


if (empty($messageonly)) {
	print $langs->trans("SorryInstancesAreOffLine", dol_escape_htmltag($instance)).'<br>';
	print '<br>';
	print '<br>';
	if ($instance && $instance != 'myaccount') {
		print '<a href="https://'.dol_escape_htmltag($instance).'">'.$langs->trans("RetryNow").'</a><br>';
		print '<br>';
	}
	print '<br>';
	print '<br>';

	//print $langs->trans("GoOnYourDashboardToGetMoreInfo", $_SERVER['SERVER_NAME'], $_SERVER['SERVER_NAME']);
	print '<br>'."\n";

	print '</div>

    </body>';
}
