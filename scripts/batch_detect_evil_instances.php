#!/usr/bin/php
<?php
/* Copyright (C) 2007-2018 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 * or see http://www.gnu.org/*
 */

/**
 *      \file       sellyoursaas/scripts/batch_detect_evil_instances.php
 *		\ingroup    sellyoursaas
 *      \brief      Script to detect evils instances by scanning inside its data for blacklist content
 */

if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');					// Do not create database handler $db
if (!defined('NOSESSION')) define('NOSESSION', '1');
if (!defined('NOREQUIREVIRTUALURL')) define('NOREQUIREVIRTUALURL', '1');

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname($_SERVER['PHP_SELF']).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}

// Global variables
$version='1.0';
$error=0;


// -------------------- START OF YOUR CODE HERE --------------------
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
if (! $res && file_exists(__DIR__."/../../master.inc.php")) $res=@include __DIR__."/../../master.inc.php";
if (! $res && file_exists(__DIR__."/../../../master.inc.php")) $res=@include __DIR__."/../../../master.inc.php";
if (! $res) {
	print "Include of master fails";
	exit(-1);
}
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/utils.class.php';
include_once dol_buildpath("/sellyoursaas/backoffice/lib/refresh.lib.php");		// This set $serverprice
include_once dol_buildpath("/sellyoursaas/class/blacklistip.class.php");
include_once dol_buildpath("/sellyoursaas/class/blacklistfrom.class.php");
include_once dol_buildpath("/sellyoursaas/class/blacklistto.class.php");
include_once dol_buildpath("/sellyoursaas/class/blacklistcontent.class.php");
include_once dol_buildpath("/sellyoursaas/class/blacklistdir.class.php");


// Read /etc/sellyoursaas.conf file
$databasehost='localhost';
$databaseport='3306';
$database='';
$databaseuser='sellyoursaas';
$databasepass='';
$ipserverdeployment='';
$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
		if ($tmpline[0] == 'databasehost') {
			$databasehost = $tmpline[1];
		}
		if ($tmpline[0] == 'databaseport') {
			$databaseport = $tmpline[1];
		}
		if ($tmpline[0] == 'database') {
			$database = $tmpline[1];
		}
		if ($tmpline[0] == 'databaseuser') {
			$databaseuser = $tmpline[1];
		}
		if ($tmpline[0] == 'databasepass') {
			$databasepass = $tmpline[1];
		}
		if ($tmpline[0] == 'ipserverdeployment') {
			$ipserverdeployment = $tmpline[1];
		}
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit(-1);
}

// Read /etc/sellyoursaas-public.conf file
$pathtospamdir = '/tmp/spam';
$fp = @fopen('/etc/sellyoursaas-public.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas-public.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
		if ($tmpline[0] == 'pathtospamdir') {
			$pathtospamdir = $tmpline[1];
		}
	}
}


/*
 * Main
 */

$dbmaster=getDoliDBInstance('mysqli', $databasehost, $databaseuser, $databasepass, $database, $databaseport);
if ($dbmaster->error) {
	dol_print_error($dbmaster, "host=".$databasehost.", port=".$databaseport.", user=".$databaseuser.", databasename=".$database.", ".$dbmaster->error);
	exit(-1);
}
if ($dbmaster) {
	$conf->setValues($dbmaster);
}
if (empty($db)) $db=$dbmaster;

//$langs->setDefaultLang('en_US'); 	// To change default language of $langs
$langs->load("main");				// To load language file for default language
@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
//$result=$user->fetch('','admin');	// Load user for login 'admin'. Comment line to run as anonymous user.
//if (! $result > 0) { dol_print_error('',$user->error); exit(-1); }
//$user->getrights();


print "***** ".$script_file." (".$version.") - ".strftime("%Y%m%d-%H%M%S")." *****\n";
if (! isset($argv[1])) {	// Check parameters
	print "Script to detect evils instances by scanning inside its data for blacklist content.\n";
	print "Usage on deployment servers: ".$script_file." (test|testemail|remove) [instancefilter]\n";
	print "\n";
	print "Options are:\n";
	print "- test          test scan\n";
	print "- testemail     test scan and send email\n";
	print "- remove        not yet available\n";
	exit(-1);
}
print '--- Start script with mode '.$argv[1]."\n";
//print 'Argument 1='.$argv[1]."\n";
//print 'Argument 2='.$argv[2]."\n";

$now = dol_now();

$action=$argv[1];
$nbofok=0;
// Nb of deployed instances
$nbofinstancedeployed=0;
// Nb of paying instance
$nboferrors=0;
$instances=array();
$instancespaidsuspended=array();
$instancespaidsuspendedandpaymenterror=array();
$instancespaidnotsuspended=array();
$instancespaidnotsuspendedpaymenterror=array();
$instancesbackuperror=array();
$instancesupdateerror=array();
$instancesbackupsuccess=array();


$instancefilter=(isset($argv[2])?$argv[2]:'');
$instancefiltercomplete=$instancefilter;

// Forge complete name of instance
if (! empty($instancefiltercomplete) && ! preg_match('/\./', $instancefiltercomplete) && ! preg_match('/\.home\.lan$/', $instancefiltercomplete)) {
	$tmparray = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
	$tmpstring = preg_replace('/:.*$/', '', $tmparray[0]);
	$instancefiltercomplete = $instancefiltercomplete.".".$tmpstring;   // Automatically concat first domain name
}

$return_var = 0;


// Preparation

print "Go into dir /home/jail/home\n";
chdir('/home/jail/home/');

dol_mkdir($pathtospamdir);


print "----- Generate file blacklistip\n";

$tmpblacklistip = new Blacklistip($db);
$tmparrayblacklistip = $tmpblacklistip->fetchAll('', '', 1000, 0, array('status'=>1));
if (is_numeric($tmparrayblacklistip) && $tmparrayblacklistip < 0) {
	echo "Erreur: failed to get blacklistip elements.\n";
}

if (!empty($tmparrayblacklistip)) {
	//home/admin/wwwroot/dolibarr_documents/sellyoursaas_local/spam

	// Generate the file balcklistip
	$filetobuild = $pathtospamdir.'/blacklistip';
	$fh = fopen($filetobuild, "w");
	foreach ($tmparrayblacklistip as $val) {
		$buffer = trim($val->content);
		fwrite($fh, $buffer."\n");
	}
	fclose($fh);
	$newmask = '0666';
	@chmod($filetobuild, octdec($newmask));
	print 'File '.$filetobuild.' has been updated'."\n";
}


print "----- Generate file blacklistfrom\n";

$tmpblacklistfrom = new Blacklistfrom($db);
$tmparrayblacklistfrom = $tmpblacklistfrom->fetchAll('', '', 1000, 0, array('status'=>1));
if (is_numeric($tmparrayblacklistfrom) && $tmparrayblacklistfrom < 0) {
	echo "Erreur: failed to get blacklistfrom elements.\n";
}

if (!empty($tmparrayblacklistfrom)) {
	// Generate the file balcklistfrom
	$filetobuild = $pathtospamdir.'/blacklistfrom';
	$fh = fopen($filetobuild, "w");
	foreach ($tmparrayblacklistfrom as $val) {
		$buffer = trim($val->content);
		fwrite($fh, $buffer."\n");
	}
	fclose($fh);
	$newmask = '0664';
	@chmod($filetobuild, octdec($newmask));

	print 'File '.$filetobuild.' has been updated'."\n";
}


print "----- Generate file blacklistto\n";

$tmpblacklistto = new Blacklistto($db);
$tmparrayblacklistto = $tmpblacklistto->fetchAll('', '', 1000, 0, array('status'=>1));
if (is_numeric($tmparrayblacklistto) && $tmparrayblacklistto < 0) {
	echo "Erreur: failed to get blacklistto elements.\n";
}

if (!empty($tmparrayblacklistto)) {
	// Generate the file balcklistip
	$filetobuild = $pathtospamdir.'/blacklistto';
	$fh = fopen($filetobuild, "w");
	foreach ($tmparrayblacklistto as $val) {
		$buffer = trim($val->content);
		fwrite($fh, $buffer."\n");
	}
	fclose($fh);
	$newmask = '0664';
	@chmod($filetobuild, octdec($newmask));
	print 'File '.$filetobuild.' has been updated'."\n";
}


print "----- Generate file blacklistcontent\n";

$tmpblacklistcontent = new Blacklistcontent($db);
$tmparrayblacklistcontent = $tmpblacklistcontent->fetchAll('', '', 1000, 0, array('status'=>1));
if (is_numeric($tmparrayblacklistcontent) && $tmparrayblacklistcontent < 0) {
	echo "Erreur: failed to get blacklistcontent elements.\n";
}

if (!empty($tmparrayblacklistcontent)) {
	// Generate the file balcklistcontent
	$filetobuild = $pathtospamdir.'/blacklistcontent';
	$fh = fopen($filetobuild, "w");
	foreach ($tmparrayblacklistcontent as $val) {
		$buffer = trim($val->content);
		fwrite($fh, $buffer."\n");
	}
	fclose($fh);
	$newmask = '0664';
	@chmod($filetobuild, octdec($newmask));
	print 'File '.$filetobuild.' has been updated'."\n";
}


$tmpblacklistdir = new Blacklistdir($db);
$tmparrayblacklistdir = $tmpblacklistdir->fetchAll('', '', 1000, 0, array('status'=>1));
if (is_numeric($tmparrayblacklistdir) && $tmparrayblacklistdir < 0) {
	echo "Erreur: failed to get blacklistdir elements.\n";
}


print "----- Generate file blacklistdir\n";

if (!empty($tmparrayblacklistdir)) {
	// Generate the file balcklistdir
	$filetobuild = $pathtospamdir.'/blacklistdir';
	$fh = fopen($filetobuild, "w");
	foreach ($tmparrayblacklistdir as $val) {
		$buffer = trim($val->content);
		fwrite($fh, $buffer."\n");
	}
	fclose($fh);
	$newmask = '0664';
	@chmod($filetobuild, octdec($newmask));
	print 'File '.$filetobuild.' has been updated'."\n";
}



// Initialize the array $instances*
print "----- Initialize the arrayinstances*\n";


// Nb of deployed instances
$nbofinstancedeployed=0;
// Nb of errors
$nboferrors=0;
// List of instances
$instances=array();
$instancestrial=array();
$instancespaidsuspended=array();
$instancespaidsuspendedandpaymenterror=array();
$instancespaidnotsuspended=array();
$instancespaidnotsuspendedpaymenterror=array();
$instancesbackuperror=array();
$instancesupdateerror=array();
$instancesbackupsuccess=array();


$instancefilter=(isset($argv[2])?$argv[2]:'');
$instancefiltercomplete=$instancefilter;

// Forge complete name of instance
if (! empty($instancefiltercomplete) && ! preg_match('/\./', $instancefiltercomplete) && ! preg_match('/\.home\.lan$/', $instancefiltercomplete)) {
	$tmparray = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
	$tmpstring = preg_replace('/:.*$/', '', $tmparray[0]);
	$instancefiltercomplete = $instancefiltercomplete.".".$tmpstring;   // Automatically concat first domain name
}

include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
$object=new Contrat($dbmaster);

// Get list of instance
$sql = "SELECT c.rowid as id, c.ref, c.ref_customer as instance,";
$sql.= " ce.deployment_status as instance_status, ce.latestbackup_date_ok, ce.username_os as osu";
$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object";
$sql.= " WHERE c.ref_customer <> '' AND c.ref_customer IS NOT NULL";
if ($instancefiltercomplete) {
	$stringforsearch = '';
	$tmparray = explode(',', $instancefiltercomplete);
	foreach ($tmparray as $instancefiltecompletevalue) {
		if (! empty($stringforsearch)) $stringforsearch.=", ";
		$stringforsearch.="'".trim($instancefiltecompletevalue)."'";
	}
	$sql.= " AND c.ref_customer IN (".$stringforsearch.")";
} else {
	$sql.= " AND ce.deployment_status = 'done'";		// Get 'deployed' only, but only if we don't request a specific instance
}
$sql.= " AND ce.deployment_status IS NOT NULL";
$sql.= " AND (ce.suspendmaintenance_message IS NULL OR ce.suspendmaintenance_message NOT LIKE 'http%')";	// Exclude instance of type redirect
// Add filter on deployment server
//if ($action == 'backup' || $action == 'backupdelete' ||$action == 'backuprsync' || $action == 'backupdatabase' || $action == 'backuptestrsync' || $action == 'backuptestdatabase') {
	$sql.=" AND ce.deployment_host = '".$dbmaster->escape($ipserverdeployment)."'";
//}

$dbtousetosearch = $dbmaster;

print $sql."\n";                                    // To have this into the ouput of cron job

dol_syslog($script_file, LOG_DEBUG);

$resql=$dbtousetosearch->query($sql);
if ($resql) {
	$num = $dbtousetosearch->num_rows($resql);
	$i = 0;
	if ($num) {
		// Loop on each deployed instance/contract
		while ($i < $num) {
			$obj = $dbtousetosearch->fetch_object($resql);
			if ($obj) {
				// We process the instance
				$instance = $obj->instance;

				dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');

				unset($object->linkedObjects);
				unset($object->linkedObjectsIds);

				// Load data of instance and set $instance_status (PROCESSING, DEPLOYED, SUSPENDED, UNDEPLOYED)
				$instance_status = 'UNKNOWN';
				$result = $object->fetch($obj->id);
				if ($result <= 0) {
					$i++;
					dol_print_error($dbmaster, $object->error, $object->errors);
					continue;
				} else {
					if ($object->array_options['options_deployment_status'] == 'processing') {
						$instance_status = 'PROCESSING';
					} elseif ($object->array_options['options_deployment_status'] == 'undeployed') {
						$instance_status = 'UNDEPLOYED';
					} elseif ($object->array_options['options_deployment_status'] == 'done') {
						// should be here due to test into SQL request
						$instance_status = 'DEPLOYED';
						$nbofinstancedeployed++;
					}
				}

				if ($instance_status == 'DEPLOYED') {
					$issuspended = sellyoursaasIsSuspended($object);
					if ($issuspended) {
						$instance_status = 'SUSPENDED';
					}
					// Note: to check that all non deployed instance has line with status that is 5 (close), you can run
					// select * from llx_contrat as c, llx_contrat_extrafields as ce, llx_contratdet as cd WHERE ce.fk_object = c.rowid
					// AND cd.fk_contrat = c.rowid AND ce.deployment_status <> 'done' AND cd.statut <> 5;
					// You should get nothing.
				}

				// Set $payment_status ('TRIAL', 'PAID' or 'FAILURE')
				$payment_status='PAID';
				$ispaid = sellyoursaasIsPaidInstance($object);	// This load linkedObjectsIds
				if (! $ispaid) {
					$payment_status='TRIAL';
				} else {
					$ispaymentko = sellyoursaasIsPaymentKo($object);
					if ($ispaymentko) {
						$payment_status='FAILURE';
					}
				}

				print "Analyze".($instancefiltercomplete ? '' : ' deployed')." instance ".($i+1)." ".$instance.": instance_status=".$instance_status." payment_status=".$payment_status.(empty($object->array_options['options_suspendmaintenance_message']) ? "" : " (maintenance/redirect: ".($object->array_options['options_suspendmaintenance_message']).")")."\n";

				// Count
				if (! in_array($payment_status, array('TRIAL'))) {
					// We analyze all non trial deployed instances
					if (in_array($instance_status, array('SUSPENDED'))) {
						$instancespaidsuspended[$obj->id] = $obj->ref.' ('.$instance.')';
						if (in_array($payment_status, array('FAILURE'))) {
							$instancespaidsuspendedandpaymenterror[$obj->id] = $obj->ref.' ('.$instance.')';
						}
					} else {
						$instancespaidnotsuspended[$obj->id] = $obj->ref.' ('.$instance.')';
						if (in_array($payment_status, array('FAILURE'))) {
							$instancespaidnotsuspendedpaymenterror[$obj->id] = $obj->ref.' ('.$instance.')';
						}
					}

					$instances[$obj->id] = array('id'=>$obj->id, 'ref'=>$obj->ref, 'instance'=>$instance, 'osu'=>$obj->osu, 'latestbackup_date_ok'=>$dbtousetosearch->jdate($obj->latestbackup_date_ok));
					print "Qualify instance ".$instance." with instance_status=".$instance_status." payment_status=".$payment_status."\n";
				} elseif ($instancefiltercomplete) {
					$instances[$obj->id] = array('id'=>$obj->id, 'ref'=>$obj->ref, 'instance'=>$instance, 'osu'=>$obj->osu, 'latestbackup_date_ok'=>$dbtousetosearch->jdate($obj->latestbackup_date_ok));
					print "Qualify instance ".$instance." with instance_status=".$instance_status." payment_status=".$payment_status."\n";
				} else {
					$instancestrial[$obj->id] = array('id'=>$obj->id, 'ref'=>$obj->ref, 'instance'=>$instance, 'osu'=>$obj->osu, 'latestbackup_date_ok'=>$dbtousetosearch->jdate($obj->latestbackup_date_ok));
					//print "Found instance ".$instance." with instance_status=".$instance_status." instance_status_bis=".$instance_status_bis." payment_status=".$payment_status."\n";
				}
			}
			$i++;
		}
	}
} else {
	$error++;
	$nboferrors++;
	dol_print_error($dbtousetosearch);
}
print "We found ".count($instancestrial)." deployed trial + ".count($instances)." deployed paid instances including ".count($instancespaidsuspended)." suspended + ".count($instancespaidnotsuspendedpaymenterror)." active with payment ko\n";



print "----- Generate file mailquota for paid instances\n";

foreach ($instances as $instanceid => $instancearray) {
	// TODO
	// We complete the file $filetobuild = $pathtospamdir.'/mailquota';
	echo 'Process paid instance id='.$instancearray['id'].' ref='.$instancearray['ref'].' osu='.$instancearray['osu']."\n";
}


print "----- Loop for spam keys into index.php using blacklistcontent\n";

foreach ($instancestrial as $instanceid => $instancearray) {
	if (!empty($tmparrayblacklistcontent)) {
		foreach ($tmparrayblacklistcontent as $val) {
			$buffer = dol_sanitizePathName(trim($val->content));
			if ($buffer) {
				$command = "grep -l '".escapeshellcmd(str_replace("'", ".", $buffer))."' ".$instancearray['osu']."/dbn*/htdocs/index.php";
				echo 'Scan if we found the string '.$buffer.' with '.$command.' ';
				$fullcommand=$command;
				$output=array();
				//echo $command."\n";
				exec($fullcommand, $output, $return_var);
				if ($return_var == 0) {		// grep -l returns 0 if something was found
					// We found an evil string
					print "- ALERT: the evil string '".$buffer."' was found in content of index.php\n";
					$nboferrors = 1;
				} else {
					print "- OK\n";
				}
			}
		}
	}
}


print "----- Loop for spam dir or files using blacklistdir\n";

foreach ($instancestrial as $instanceid => $instancearray) {
	if (!empty($tmparrayblacklistdir)) {
		foreach ($tmparrayblacklistdir as $val) {
			$buffer = dol_sanitizePathName(trim($val->content));
			if ($buffer) {
				$command = "find ".$instancearray['osu']."/dbn*/htdocs/ -maxdepth 2";
				if (!empty($val->noblacklistif)) {
					$tmpdirarray = explode('|', $val->noblacklistif);
					foreach ($tmpdirarray as $tmpdir) {
						if ($tmpdir) {
							$command .= " ! -path '*".escapeshellcmd(preg_replace('/[^a-z0-9\.\-]/', '', $tmpdir))."*'";
						}
					}
				}
				$command .= " | grep '".escapeshellcmd($buffer)."'";
				/*if (!empty($val->noblacklistif)) {
					$command .= " | grep -v '".str_replace("'", ".", $val->noblacklistif)."'";
				}*/
				echo 'Scan if we found the blacklist dir '.$buffer.' with '.$command.' ';
				$fullcommand=$command;
				$output=array();
				//echo $command."\n";

				exec($fullcommand, $output, $return_var);
				if ($return_var == 0) {		// command returns 0 if something was found
					// We found an evil string
					print "- ALERT: the evil dir/file '".$buffer."' was found\n";
					$nboferrors = 2;
				} else {
					print "- OK\n";
				}
			}
		}
	}
}


$dbmaster->close();	// Close database opened handler

if ($nboferrors) {
	print '--- end ERROR nb='.$nboferrors.' - '.strftime("%Y%m%d-%H%M%S")."\n";
} else {
	print '--- end OK with no error - '.strftime("%Y%m%d-%H%M%S")."\n";
}

if ($nboferrors) {
	if ($action == 'testemail') {
		$from = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;
		$to = $conf->global->SELLYOURSAAS_SUPERVISION_EMAIL;
		// Supervision tools are generic for all domain. No way to target a specific supervision email.

		$msg = 'Error in '.$script_file." ".$argv[1]." ".$argv[2]." (finished at ".strftime("%Y%m%d-%H%M%S").")\n\n".$out;

		include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		print 'Send email MAIN_MAIL_SENDMODE='.$conf->global->MAIN_MAIL_SENDMODE.' MAIN_MAIL_SMTP_SERVER='.$conf->global->MAIN_MAIL_SMTP_SERVER.' from='.$from.' to='.$to.' title=[Warning] Alert(s) in batch_detect_evil_instances - '.gethostname().' - '.dol_print_date(dol_now(), 'dayrfc')."\n";
		$cmail = new CMailFile('[Warning] Alert(s) in batch_detect_evil_instances - '.gethostname().' - '.dol_print_date(dol_now(), 'dayrfc'), $to, $from, $msg, array(), array(), array(), '', '', 0, 0, '', '', '', '', 'emailing');
		$result = $cmail->sendfile();
	}
}

exit($nboferrors);
