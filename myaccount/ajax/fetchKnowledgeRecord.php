<?php

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
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1'); // Do not check IP defined into conf $dolibarr_main_restrict_ip
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

$action = GETPOST('action', 'aZ09');
$idticketgroup = GETPOST('idticketgroup', 'aZ09');
$idticketgroup = GETPOST('idticketgroup', 'aZ09');
$lang = GETPOST('lang', 'aZ09');

// Security check
if (!defined("NOLOGIN")) {	// No need for restrictedArea if not logged. Later the select will filter on public articles for public categories only if not logged.
	//  restrictedArea($user, 'knowledgemanagement', 0, 'knowledgemanagement_knowledgerecord', 'knowledgerecord');
}


/*
 * Actions
 */

// None


/*
 * View
 */

if ($action == "getKnowledgeRecord") {
	$response = '';
	$sql = "SELECT kr.rowid, kr.ref, kr.question, kr.answer,l.url,ctc.code";
	$sql .= " FROM ".MAIN_DB_PREFIX."links as l";	// Links
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."knowledgemanagement_knowledgerecord as kr ON kr.rowid = l.objectid";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."c_ticket_category as ctc ON ctc.rowid = kr.fk_c_ticket_category";
	$sql .= " WHERE ctc.code = '".$db->escape($idticketgroup)."'";
	$sql .= " AND ctc.active = 1";
	//if (defined("NOLOGIN")) {
	$sql .= " AND ctc.public = 1";
	//}
	$sql .= " AND (kr.lang = '".$db->escape($lang)."' OR kr.lang IS NULL OR kr.lang = '')";
	$sql .= " AND kr.status = 1";
	//$sql .= " AND (kr.answer IS NOT NULL AND kr.answer <> '')";
	//print $sql;

	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;
		$response = array();
		while ($i < $num) {
			$obj = $db->fetch_object($resql);
			$response[] = array('title'=>$obj->question,'ref'=>$obj->url,'answer'=>$obj->answer,'url'=>$obj->url);
			$i++;
		}
	} else {
		dol_print_error($db);
	}
	$response =json_encode($response);
	echo $response;
}
