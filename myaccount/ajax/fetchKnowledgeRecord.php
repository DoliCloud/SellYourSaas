<?php

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1); // Disables token renewal
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX', '1');
if (!defined('NOREQUIRESOC'))   define('NOREQUIRESOC', '1');
if (!defined('NOCSRFCHECK'))    define('NOCSRFCHECK', '1');
if (!defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');

// Add specific definition to allow a dedicated session management
require '../mainmyaccount.inc.php';

// Load Dolibarr environment
$res=0;

// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");

$action = GETPOST('action', 'aZ09');
$idticketgroup = GETPOST('idticketgroup', 'int');

if ($action == "getKnowledgeRecord" && $idticketgroup > 0) {
    $response = '';
    // TODO Take list of URL found into subtable llx_links
    $sql = "SELECT kr.rowid, kr.ref, kr.question, kr.answer, kr.fk_c_ticket_category, kr.url";
    $sql .= " FROM ".MAIN_DB_PREFIX."knowledgemanagement_knowledgerecord as kr";
    $sql .= " WHERE kr.fk_c_ticket_category = ".((int) $idticketgroup);
    //$sql .= " AND entity = ".$conf->entity;
    $resql = $db->query($sql);
    if ($resql) {
        $num = $db->num_rows($resql);
        $i = 0;
        // TODO Do not forge a json string. Just forge a PHP array and return it with json_encode($response)
        $response .= '[';
        while ($i < $num) {
            $obj = $db->fetch_object($resql);
            $response .= $i.'\':{ title:\''.$obj->question.'\', ';
            $response .= 'ref:\''.$obj->url.'\', ';
            $response .= 'answer:\''.$obj->answer.'\', ';
            $response .= 'url:\''.$obj->url.'\'';	// Return array of url for this question.
            $response .= '}';
            $i++;
        }
        $response .= ']';
    } else{
        dol_print_error($db);
    }
    $response = json_encode($response);
    echo $response;
}