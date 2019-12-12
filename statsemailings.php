<?php
/* Copyright (C) 2008-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 */

/**
 *    	\file       htdocs/sellyoursaas/statsemailings.php
 *		\ingroup    sellyoursaas
 *		\brief      Page des stats
 *		\author		Laurent Destailleur
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include($_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php");
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include(substr($tmp, 0, ($i+1))."/main.inc.php");
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php");
// Try main.inc.php using relative path
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");


// Load config
$CALLFORCONFIG=1;
include_once('index.php');
require_once(DOL_DOCUMENT_ROOT."/core/class/dolgraph.class.php");
require_once DOL_DOCUMENT_ROOT.'/comm/mailing/class/mailing.class.php';


// Load traductions files
//$langs->load("sellyoursaas");
$langs->load("companies");
$langs->load("other");


// Get parameters
$socid = isset($_GET["socid"])?$_GET["socid"]:'';

// Protection
if (! $user->rights->sellyoursaas->emailings->voir)
{
	accessforbidden();
	exit;
}

$dirmod=DOL_DOCUMENT_ROOT."/core/modules/mailings";
$dirmod2="./core/modules/mailings";

$mesg = '';


/*
 * 	Actions
 */

if ($_GET["action"] == 'buildemailingchien')
{
	// Cree un emailing brouillon
	$sujet='La Newsletter hebdomadaire de ChiensDeRace.com';
	$body='';

	// Connexion base
	$dbchien = mysql_connect($dbhostchien, $dbuserchien, $dbpasswordchien);
	mysql_select_db($dbdatabasechien,$dbchien);

	// sante
	$sante='';
	$REQUETE="select ID_NEWS, TITRE_NEWS, TEXTE_NEWS from T_NEWS";
	$REQUETE.=" where ID_CATEG = 20 AND (AUTEUR_NEWS ='1040' OR AUTEUR_NEWS='1038') ORDER by ID_NEWS DESC";
	$result = mysql_query("$REQUETE",$dbchien);

	while ($row = mysql_fetch_object($result))
	{
		$ID_NEWS=$row->ID_NEWS;
		$TITRE_NEWS=$row->TITRE_NEWS;
		$TEXTE_NEWS=$row->TEXTE_NEWS;
		$sante=$TITRE_NEWS."<br><br>".$TEXTE_NEWS."<br><a href='http://www.chiensderace.com/news/novel.php?ID=".$ID_NEWS."'>Lire cet article</a><br>";
		break;
	}


	// actualite
	$actualite='';
	$REQUETE="select ID_NEWS, TITRE_NEWS, TEXTE_NEWS from T_NEWS";
	$REQUETE.=" where ID_CATEG = 73 AND (AUTEUR_NEWS ='1040' OR AUTEUR_NEWS='1038') ORDER by ID_NEWS DESC";
	$result = mysql_query("$REQUETE",$dbchien);

	while ($row = mysql_fetch_object($result))
	{
		$ID_NEWS=$row->ID_NEWS;
		$TITRE_NEWS=$row->TITRE_NEWS;
		$TEXTE_NEWS=$row->TEXTE_NEWS;
		$actualite=$TITRE_NEWS."<br><br>".$TEXTE_NEWS."<br><a href='http://www.chiensderace.com/news/novel.php?ID=".$ID_NEWS."'>Lire cet article</a><br>";
		break;
	}

	$race_semaine='';
	$REQUETE="select ID_RACES, LIB_RACES, ORIGINE_RACES from T_RACES";
	$result = mysql_query("$REQUETE",$dbchien);
	$i=0;
	while ($row = mysql_fetch_object($result))
	{
		$ID_RACES[$i]=$row->ID_RACES;
		$LIB_RACES[$i]=$row->LIB_RACES;
		$ORIGINE_RACES[$i]=$row->ORIGINE_RACES;
		$i++;
	}
	$j=rand(0,$i--);
	$race_semaine=$LIB_RACES[$j]." (Origine : ".$ORIGINE_RACES[$j].")<br><br>Découvrez cette race cette semaine avec ChiensDeRace.com.<br><a href='http://www.chiensderace.com/php/fiche_race.php?RACE=".$ID_RACES[$j]."'>Voir la fiche de race</a><br>";

	$file_in='newsletter_type_chien.html';
    $fichier= fopen($file_in, 'r');
	$lines = file($file_in);

	foreach ($lines as $line_num => $line)
	{
		// on vire les retour chariots
		$line=trim(preg_replace("/[\n\r]/",'',$line));
		if ($line == '$sante') $line=$sante;
	       	if ($line == '$actualite') $line=$actualite;
	       	if ($line == '$race_semaine') $line=$race_semaine;
		$body.=$line;
	}


    $mil = new Mailing($db);

    $mil->email_from   = 'newsletter@chiensderace.com';
    $mil->titre        = $sujet;
    $mil->title        = $sujet;
    $mil->sujet        = $sujet;
    $mil->body         = $body;

    $result = $mil->create($user);
    if ($result >= 0)
    {
        Header("Location: ".DOL_URL_ROOT.'/comm/mailing/card.php?id='.$mil->id);
        exit;
    }
    else
    {
        $msg=$mil->error;
    }
}

/***************************************************
* PAGE
*
* Put here all code to build page
****************************************************/

llxHeader();

$form=new Form($db);

if ($msg) print $msg.'<br>';


$dbchien=getDoliDBInstance('mysqli', $dbhostchien, $dbuserchien, $dbpasswordchien, $dbdatabasechien, 3306);
if (! $dbchien->connected)
{
	dol_print_error($dbchien,"Can not connect to server ".$dbhostchien." with user ".$dbuserchien);
	exit;
}
if (! $dbchien->database_selected)
{
	dol_print_error($dbchien,"Database ".$dbdatabasechien." can not be selected");
	exit;
}
$dbchat=getDoliDBInstance('mysqli', $dbhostchat, $dbuserchat, $dbpasswordchat, $dbdatabasechat, 3306);
if (! $dbchat->connected)
{
	dol_print_error($dbchat,"Can not connect to server ".$dbhostchat." with user ".$dbuserchat);
	exit;
}
if (! $dbchat->database_selected)
{
	dol_print_error($dbchat,"Database ".$dbdatabasechat." can not be selected");
	exit;
}
$dbchatparlons=getDoliDBInstance('mysqli', $dbhostchatparlons, $dbuserchatparlons, $dbpasswordchatparlons, $dbdatabasechatparlons, 3306);
if (! $dbchatparlons->connected)
{
	dol_print_error($dbchatparlons,"Can not connect to server ".$dbhostchatparlons." with user ".$dbuserchatparlons);
	exit;
}
if (! $dbchatparlons->database_selected)
{
	dol_print_error($dbchatparlons,"Database ".$dbdatabasechatparlons." can not be selected");
	exit;
}


// Build graph
$WIDTH=800;
$HEIGHT=160;

// Create temp directory
$dir = DOL_DATA_ROOT.'/sellyoursaas/';
$dirtmp = 'temp/';
if (! file_exists($dir.$dirtmp))
{
	if (dol_mkdir($dir.$dirtmp) < 0)
	{
		$mesg = $langs->trans("ErrorCanNotCreateDir",$dir.$dirtmp);
	}
}


// Get datas
$graph_data = array();
$lastval=array();
$relativepath=$dirtmp."statsannonces.png".$categ;


        print '<table class="noborder" width="100%">';
        print '<tr class="liste_titre">';
        print '<td>Groupe de donnees</td>';
        print '<td align="center">ML_XXX=-1</td>';
        print '<td align="center">ML_XXX=0</td>';
		print '<td align="center">ML_XXX=1</td>';
        print "</tr>\n";

        clearstatcache();

        $listdir=array();
        $listdir[]=$dirmod;
        if (! empty($dirmod2)) $listdir[]=$dirmod2;
        $listtype=array('adresses','personnes');

        foreach ($listtype as $type)
        {
        foreach ($listdir as $dir)
        {
        $handle=opendir($dir);

        $var=True;
        while (($file = readdir($handle))!==false)
        {
            if (substr($file, 0, 1) <> '.' && substr($file, 0, 3) <> 'CVS')
            {
                if (preg_match("/(.*(chiensderace|chatsderace))\.modules\.php$/",$file,$reg))
                {
            		$modulename=$reg[1];
        			if ($modulename == 'example') continue;

                    // Chargement de la classe
                    $file = $dir."/".$modulename.".modules.php";
                    $classname = "mailing_".$modulename;
                    require_once($file);

                    if (preg_match('/chiens/',$modulename)) $db=$dbchien;
                    if (preg_match('/chat/',$modulename)) $db=$dbchat;
                    $obj = new $classname($db);

                    $qualified=1;
                    foreach ($obj->require_module as $key)
                    {
                        if (! $conf->$key->enabled || (! $user->admin && $obj->require_admin))
                        {
                            $qualified=0;
                            //print "Les pr�requis d'activation du module mailing ne sont pas respect�s. Il ne sera pas actif";
                            break;
                        }
                    }

                    // Si le module mailing est qualifi�
                    if ($qualified)
                    {
                        $var = !$var;
                        print '<tr '.$bc[$var].'>';

                        print '<td>';
                        if (! $obj->picto) $obj->picto='generic';
                        print img_object('',$obj->picto).' '.$obj->getDesc();
                        print ' - Newsletter '.$type;
                        print '</td>';

                        /*
                        print '<td width=\"100\">';
                        print $modulename;
                        print "</td>";
                        */
                        $nbofrecipient=$obj->getNbOfRecipients(-1,$type);
                        print '<td class="center">';
                        if ($nbofrecipient >= 0)
                        {
                        	print $nbofrecipient;
                        }
                        else
                        {
                        	print $langs->trans("Error").' '.img_error($obj->error);
                        }
                        print '</td>';

                        $nbofrecipient=$obj->getNbOfRecipients(0,$type);
                        print '<td class="center">';
                        if ($nbofrecipient >= 0)
                        {
                        	print $nbofrecipient;
                        }
                        else
                        {
                        	print $langs->trans("Error").' '.img_error($obj->error);
                        }
                        print '</td>';

                        $nbofrecipient=$obj->getNbOfRecipients(1,$type);
                        print '<td class="center">';
                        if ($nbofrecipient >= 0)
                        {
                        	print $nbofrecipient;
                        }
                        else
                        {
                        	print $langs->trans("Error").' '.img_error($obj->error);
                        }
                        print '</td>';

                        print "</tr>\n";
                    }
                }
            }
        }
        closedir($handle);
        }
        }


        $listdir=array();
        $listdir[]=$dirmod;
        if (! empty($dirmod2)) $listdir[]=$dirmod2;
        $listtype=array('forum');

        foreach ($listtype as $type)
        {
        foreach ($listdir as $dir)
        {
        $handle=opendir($dir);

        $var=True;
        while (($file = readdir($handle))!==false)
        {
            if (substr($file, 0, 1) <> '.' && substr($file, 0, 3) <> 'CVS')
            {
                if (preg_match("/(.*(chiensderace|chatsderace)_forum)\.modules\.php$/",$file,$reg))
                {
                	$modulename=$reg[1];
        			if ($modulename == 'example') continue;

                    // Chargement de la classe
                    $file = $dir."/".$modulename.".modules.php";
                    $classname = "mailing_".$modulename;
                    require_once($file);

                    if (preg_match('/chiens/',$modulename)) $db=$dbchien;
                    if (preg_match('/chat/',$modulename)) $db=$dbchatparlons;
                    $obj = new $classname($db);

                    $qualified=1;
                    foreach ($obj->require_module as $key)
                    {
                        if (! $conf->$key->enabled || (! $user->admin && $obj->require_admin))
                        {
                            $qualified=0;
                            //print "Les pr�requis d'activation du module mailing ne sont pas respect�s. Il ne sera pas actif";
                            break;
                        }
                    }

                    // Si le module mailing est qualifi�
                    if ($qualified)
                    {
                        $var = !$var;

                        // Newsletter

                        print '<tr '.$bc[$var].'>';

                        print '<td>';
                        if (! $obj->picto) $obj->picto='generic';
                        print img_object('',$obj->picto).' '.$obj->getDesc();
                        print ' - Newsletter '.$type;
                        print '</td>';

                        print '<td>&nbsp;</td>';

                        $nbofrecipient=$obj->getNbOfRecipients(-1,$type);
                        print '<td class="center">';
                        if ($nbofrecipient >= 0)
                        {
                        	print $nbofrecipient;
                        }
                        else
                        {
                        	print $langs->trans("Error").' '.img_error($obj->error);
                        }
                        print '</td>';

                        $nbofrecipient=$obj->getNbOfRecipients(1,$type);
                        print '<td class="center">';
                        if ($nbofrecipient >= 0)
                        {
                        	print $nbofrecipient;
                        }
                        else
                        {
                        	print $langs->trans("Error").' '.img_error($obj->error);
                        }
                        print '</td>';

                        print "</tr>\n";


                        // Offres commerciales

                        $var = !$var;
                        print '<tr '.$bc[$var].'>';

                        print '<td>';
                        if (! $obj->picto) $obj->picto='generic';
                        print img_object('',$obj->picto).' '.$obj->getDesc();
                        print ' - Offres commerciales '.$type;
                        print '</td>';

                        print '<td>&nbsp;</td>';

                        $nbofrecipient=$obj->getNbOfRecipients(-2,$type);
                        print '<td class="center">';
                        if ($nbofrecipient >= 0)
                        {
                        	print $nbofrecipient;
                        }
                        else
                        {
                        	print $langs->trans("Error").' '.img_error($obj->error);
                        }
                        print '</td>';

                        $nbofrecipient=$obj->getNbOfRecipients(2,$type);
                        print '<td class="center">';
                        if ($nbofrecipient >= 0)
                        {
                        	print $nbofrecipient;
                        }
                        else
                        {
                        	print $langs->trans("Error").' '.img_error($obj->error);
                        }
                        print '</td>';

                        print "</tr>\n";
                    }
                }
            }
        }
        closedir($handle);
        }
        }

        print '</table>';
		print '<br>';

		print 'Les emails sont definis dans T_ADRESSES (inscription via adresse)+T_PERSONNES (inscription via la box)+FORUM_USERS (incription par forum)<br>';
		print 'Si ML_XXX=-1, a demande explicitement a etre desincrit<br>';
		print 'Si ML_XXX=0,  ne s\'est pas inscrit<br>';
		print 'Si ML_XXX=1,  s\'est inscrit (explicitement ou auto car avant loi optin)<br>';







	print '<br><br>';
	print '<b>Cliquer sur ce bouton pour fabriquer un emailing brouillon chiensderace du moment</b>:<br><br>';
	print '<form action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="action" value="buildemailingchien">';
	print '<input type="submit" class="button" value="Generer newsletter brouillon"><br>';
	print '</form>';

$dbchien->close();
$dbchat->close();

llxFooter();
