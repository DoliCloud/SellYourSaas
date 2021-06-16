<?php

$res=0;

// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
if (! $res && ! empty($_SERVER["DOCUMENT_ROOT"])) $res=@include $_SERVER["DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");
if(isset($_GET['action']) && !empty($_GET['action'])) {
    $action = $_GET['action'];
}
if(isset($_GET['codeticket']) && !empty($_GET['codeticket'])) {
    $codeticket = $_GET['codeticket'];
}

if($action == "getKnowledgeRecord"){
    $response = '';
    $sql = "SELECT kr.rowid, kr.ref, kr.fk_ticket, kr.url";
    $sql .= " FROM ".MAIN_DB_PREFIX."knowledgemanagement_knowledgerecord as kr";
    $sql .= " JOIN ".MAIN_DB_PREFIX."ticket as t";
    $sql .= " ON kr.fk_ticket = t.rowid";
    $sql .= " WHERE t.category_code = '".$db->escape($codeticket)."'";
    $resql = $db->query($sql);
    if ($resql) {
        $num = $db->num_rows($resql);
        $i = 0;
        $response .= '[';
        while ($i < $num) {
            $obj = $db->fetch_object($resql);
            $response .= $i.'\':{title:\''.$obj->ref.'\',';
            $response .= 'url:\''.$obj->url.'\'';
            $response .= '}';
            $i++;
        }
        $response .= ']';
    }else{
        dol_print_error($db);
    }
    $response = json_encode($response);
    echo $response;
}