#!/usr/bin/php
<?php
/* Copyright (C) 2012-2023 Laurent Destailleur	<eldy@users.sourceforge.net>
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
 * FEATURE
 *
 * Make a backup of files (rsync) or database (mysqdump) of a deployed instance.
 * There is also a report/tracking done into master database.
 * This script is run from the deployment servers.
 *
 * Note:
 * ssh keys must be authorized to have testrsync and confirmrsync working.
 * remote access to database must be granted for option 'testdatabase' or 'confirmdatabase'.
 */

if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');					// Do not create database handler $db
if (!defined('NOSESSION')) define('NOSESSION', '1');
if (!defined('NOREQUIREVIRTUALURL')) define('NOREQUIREVIRTUALURL', '1');

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';
// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}
if (0 != posix_getuid()) {
	echo "Script must be ran with root.\n";
	print "\n";
	exit(-1);
}

// Global variables
$version='1.0';
$error=0;


// Global variables
$version='1.0';
$RSYNCDELETE=0;
$HISTODIRTEXT="";

$errstring = "";

$testorconfirm=isset($argv[1])?$argv[1]:'';

$keystocheck = array(2, 3, 4, 5);
foreach ($keystocheck as $keytocheck) {
	if (isset($argv[$keytocheck])) {
		if ($argv[$keytocheck] == '--delete') {
			$RSYNCDELETE=1;
		} elseif ($argv[$keytocheck] == 'month') {
			$HISTODIRTEXT='month';
		} elseif ($argv[$keytocheck] == 'week') {
			$HISTODIRTEXT='week';
		} elseif ($argv[$keytocheck] == 'none') {
			$HISTODIRTEXT='';
		}
	}
}

@set_time_limit(0);							// No timeout for this script
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);

// Read /etc/sellyoursaas.conf file


$databasehost='localhost';
$databaseport='3306';
$database='';
$databaseuser='sellyoursaas';
$databasepass='';
$ipserverdeployment='';
$dolibarrdir='';
$usecompressformatforarchive='gzip';
$backupignoretables='';
$backupcompressionalgorithms='';	// can be '' or 'zstd'
$backuprsyncdayfrequency=1;	// Default value is an rsync every 1 day.
$backupdumpdayfrequency=1;	// Default value is a sql dump every 1 day.

$DOMAIN = '';
$HISTODIR = '';
$homedir = '/mnt/diskhome/home';
$backupdir = 'mnt/diskbackup/backup';
$remotebackupdir = '/mnt/diskbackup';

// Source
$DIRSOURCE1 = "/home";
$DIRSOURCE2 = "";

// Target
$SERVDESTI = '';
$SERVPORTDESTI = '22';
$USER = 'admin';
$DIRDESTI1 = '';
$DIRDESTI2 = '';

$EMAILFROM = '';
$EMAILTO = '';

$DISTRIB_RELEASE = exec('lsb_release -r -s');
$instanceserver = '';

$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
		if ($tmpline[0] == 'domain') {
			$DOMAIN = $tmpline[1];
		}
		if ($tmpline[0] == 'homedir') {
			$homedir = $tmpline[1];
		}
		if ($tmpline[0] == 'backupdir') {
			$backupdir = $tmpline[1];
		}
		if ($tmpline[0] == 'remotebackupdir') {
			$remotebackupdir = $tmpline[1];
		}
		if ($tmpline[0] == 'remotebackupserver') {
			$SERVDESTI = $tmpline[1];
		}
		if ($tmpline[0] == 'remotebackupserverport') {
			$SERVPORTDESTI = $tmpline[1];
		}
		if ($tmpline[0] == 'remotebackupuser') {
			$USER = $tmpline[1];
		}
		if ($tmpline[0] == 'ipserverdeployment') {
			$ipserverdeployment = $tmpline[1];
		}
		if ($tmpline[0] == 'emailfrom') {
			$EMAILFROM = $tmpline[1];
		}
		if ($tmpline[0] == 'emailsupervision') {
			$EMAILTO = $tmpline[1];
		}
		if ($tmpline[0] == 'instanceserver') {
			$instanceserver = $tmpline[1];
		}
		if ($tmpline[0] == 'dolibarrdir') {
			$dolibarrdir = $tmpline[1];
		}
		if ($tmpline[0] == 'backupcompressionalgorithms') {
			$backupcompressionalgorithms = preg_replace('/[^a-z]/', '', $tmpline[1]);
		}
		if ($tmpline[0] == 'backuprsyncdayfrequency') {
			$backuprsyncdayfrequency = $tmpline[1];
		}
		if ($tmpline[0] == 'backupdumpdayfrequency') {
			$backupdumpdayfrequency = $tmpline[1];
		}
		if ($tmpline[0] == 'databaseuser') {
			$databaseuser = $tmpline[1];
		}
		if ($tmpline[0] == 'databasepass') {
			$databasepass = $tmpline[1];
		}
		if ($tmpline[0] == 'databasehost') {
			$databasehost = $tmpline[1];
		}
		if ($tmpline[0] == 'database') {
			$database = $tmpline[1];
		}
		if ($tmpline[0] == 'databaseport') {
			$databaseport = $tmpline[1];
		}
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit(-1);
}

if (empty($dolibarrdir)) {
	print "Failed to find 'dolibarrdir' entry into /etc/sellyoursaas.conf file\n";
	exit(-1);
}
if (empty($backuprsyncdayfrequency)) {
	print "Bad value for 'backuprsyncdayfrequency'. Must contains the number of days between each rsync.\n";
	exit(-1);
}
if (empty($backupdumpdayfrequency)) {
	print "Bad value for 'backupdumpdayfrequency'. Must contains the number of days between each sql dump.\n";
	exit(-1);
}
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
if (! $res && file_exists(__DIR__."/../../master.inc.php")) $res=@include __DIR__."/../../../master.inc.php";
if (! $res && file_exists(__DIR__."/../../../master.inc.php")) $res=@include __DIR__."/../../../master.inc.php";
if (! $res && file_exists($dolibarrdir."/htdocs/master.inc.php")) $res=@include $dolibarrdir."/htdocs/master.inc.php";
if (! $res) {
	print ("Include of master fails");
	exit(-1);
}

include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
include_once DOL_DOCUMENT_ROOT."/core/class/utils.class.php";
include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
dol_include_once("/sellyoursaas/core/lib/dolicloud.lib.php");

$HISTODIR = dol_print_date(dol_now(), '%d');
if ($argv[2] == "w" || $argv[2] == "week") {
	$HISTODIR = dol_print_date(dol_now(), '%w');
}
if ($argv[2] == "n" || $argv[2] == "none") {
	$HISTODIR = "";
}

$DIRSOURCE2 = $backupdir;

$DIRDESTI1 = $remotebackupdir.'/home_'.gethostname();
$DIRDESTI2 = $remotebackupdir.'/backup_'.gethostname();

if (empty($EMAILFROM)) {
	$EMAILFROM = 'noreply@'.$DOMAIN;
}
if (empty($EMAILTO)) {
	$EMAILTO = 'supervision@'.$DOMAIN;
}

$OPTIONS = "-4 --prune-empty-dirs --stats -rlt --chmod=u=rwX";
if ($DISTRIB_RELEASE == "20.04" || $DISTRIB_RELEASE == "22.04") {
	$OPTIONS = $OPTIONS;
} else {
	$OPTIONS = $OPTIONS." --noatime";
}

if ($RSYNCDELETE == 1) {
	$OPTIONS = $OPTIONS." --delete --delete-excluded";
}

$TESTN = "";
if ($testorconfirm != "confirm") {
	$TESTN = "-n";
}

print "***** ".$script_file." (".$version.") - ".dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt')." *****\n";
if (empty($argv[1])) {
	echo "Usage: ${0} (test|confirm) [month|week|none] [osuX] [--delete]\n";
	echo "With  month (default) is to keep 1 month of backup using --backup option of rsync\n";
	echo "      week is to keep 1 week of backup using --backup option of rsync\n";
	echo "      none is to not archive old versions using the --backup option of rsync. For example when you already do it using snapshots on backup server (recommended).\n";
	echo "You can also set a group of 4 first letters on username to backup the backup of a limited number of users.\n";
	exit(-1);
}

if (empty($SERVDESTI)) {
	print "Can't find name of remote backup server (remotebackupserver=) in /etc/sellyoursaas.conf\n";
	print "Usage: ".$argv[0]." (test|confirm) [osuX]\n";
	exit(-1);
}

if (empty($DOMAIN)) {
	print "Value for domain seems to not be set into /etc/sellyoursaas.conf\n";
	print "Usage: ".$argv[0]." (test|confirm) [osuX]\n";
	exit(-1);
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
if (empty($db)) {
	$db = $dbmaster;
}

$user = new User($dbmaster);
$user->fetch($conf->global->SELLYOURSAAS_ANONYMOUSUSER);

// Nb of deployed instances
$nbofinstancedeployed=0;
// Nb of errors
$nboferrors=0;
// List of instances
$instances=array();				// array of paid instances
$instancestrial=array();		// array of trial instances
$instancespaidsuspended=array();
$instancespaidsuspendedandpaymenterror=array();
$instancespaidnotsuspended=array();
$instancespaidnotsuspendedpaymenterror=array();
$instancesbackuperror=array();
$instancesupdateerror=array();
$instancesbackupsuccess=array();
$instancesbackupsuccessdiscarded=array();

$instancefilter=((isset($argv[3]) && $argv[3] != '--delete') ? $argv[3] : '');
$instancefiltercomplete=$instancefilter;

// Forge complete name of instance
if (! empty($instancefiltercomplete) && ! preg_match('/\./', $instancefiltercomplete) && ! preg_match('/\.home\.lan$/', $instancefiltercomplete)) {
	if (empty(getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION'))) {
		$tmparray = explode(',', getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES'));
	} else {
		dol_include_once('sellyoursaas/class/deploymentserver.class.php');
		$staticdeploymentserver = new Deploymentserver($db);
		$tmparray = $staticdeploymentserver->fetchAllDomains();
	}
	$tmpstring = preg_replace('/:.*$/', '', $tmparray[0]);
	$instancefiltercomplete = $instancefiltercomplete.".".$tmpstring;   // Automatically concat first domain name
}


$ret1 = array();
$ret2 = array();
$totalinstancessaved=0;
$totalinstancesfailed=0;
// The following line is to have an empty dir to clear the last incremental directories
if (!dol_is_dir($homedir."/emptydir")) {
	dol_mkdir($homedir."/emptydir");
}

$SERVERDESTIARRAY = explode(',', $SERVDESTI);
// Loop on each target server
foreach ($SERVERDESTIARRAY as $servername) {
	$ret1[$servername] = 0;
	$ret2[$servername] = 0;
}

// Loop on each target server to make backup of SOURCE1
$command = '';
foreach ($SERVERDESTIARRAY as $servername) {
	print dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S').' Do rsync of '.$DIRSOURCE1.' to remote '.$USER.'@'.$servername.':'.$DIRDESTI1."...\n";

	if (empty($HISTODIR)) {
		$command = "rsync ".$TESTN." -x --exclude-from=".$path."backup_backups.exclude ".$OPTIONS." ".$DIRSOURCE1."/* ".$USER."@".$servername.":".$DIRDESTI1;
	} else {
		$command = "rsync ".$TESTN." -x --exclude-from=".$path."backup_backups.exclude ".$OPTIONS." --backup --backup-dir=".$DIRDESTI1."/backupold_".$HISTODIR." ".$DIRSOURCE1."/* ".$USER."@".$servername.":".$DIRDESTI1;
	}
	print dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')." ".$command."\n";
	$output = array();
	$return_var = 0;
	exec($command, $output, $return_var);
	if ($return_var != 0) {
		$ret1[$servername] = $ret1[$servername] + 1;
		print "ERROR Failed to make rsync for ".$DIRSOURCE1." to ".$servername." ret=".$ret1[$servername]." \n";
		print "Command was: ".$command."\n";
		$errstring .="\n".dol_print_date(dol_now(), "%Y-%m-%d %H:%M:%S")." Dir ".$DIRSOURCE1." to ".$servername.". ret=".$ret1[$servername].". Command was: ".$command."\n";
	}
	sleep(2);
}

if (!empty($instanceserver)) {
	print "\n";
	print dol_print_date(dol_now(), "%Y-%m-%d %H:%M:%S")." Do rsync of customer directories into ".$DIRSOURCE2."/osu... to remote ".$SERVDESTI."...\n";

	$nbdu = 0;
	include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
	$object=new Contrat($dbmaster);

	$sql = "SELECT c.rowid as id, c.ref, c.ref_customer as instance,";
	$sql.= " ce.deployment_status as instance_status, ce.username_os as osu";
	$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object";
	$sql.= " WHERE c.ref_customer <> '' AND c.ref_customer IS NOT NULL";
	if (isset($argv[3]) && $argv[3] != "--delete") {
		$sql.= " AND c.ref_customer IN (".$dbmaster->escape($argv[3]).")";
	} else {
		$sql.= " AND ce.deployment_status = 'done'";		// Get 'deployed' only, but only if we don't request a specific instance
	}
	$sql.= " AND ce.deployment_status IS NOT NULL";
	$sql.= " AND (ce.suspendmaintenance_message IS NULL OR ce.suspendmaintenance_message NOT LIKE 'http%')";	// Exclude instance of type redirect
	$sql.= " AND ce.deployment_host = '".$dbmaster->escape($ipserverdeployment)."'";

	$dbtousetosearch = $dbmaster;

	print $sql."\n";                                    // To have this into the ouput of cron job
	$resql=$dbtousetosearch->query($sql);
	if ($resql) {
		$num = $dbtousetosearch->num_rows($resql);
		$i = 0;
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

					$instances[$obj->id] = array('id'=>$obj->id, 'ref'=>$obj->ref, 'instance'=>$instance, 'latestbackup_date_ok'=>$dbtousetosearch->jdate($obj->latestbackup_date_ok), 'backup_frequency'=>$obj->backup_frequency);
					print "Qualify instance ".$instance." with instance_status=".$instance_status." payment_status=".$payment_status."\n";
				} elseif ($instancefiltercomplete) {
					$instances[$obj->id] = array('id'=>$obj->id, 'ref'=>$obj->ref, 'instance'=>$instance, 'latestbackup_date_ok'=>$dbtousetosearch->jdate($obj->latestbackup_date_ok), 'backup_frequency'=>$obj->backup_frequency);
					print "Qualify instance ".$instance." with instance_status=".$instance_status." payment_status=".$payment_status."\n";
				} else {
					$instancestrial[$obj->id] = array('id'=>$obj->id, 'ref'=>$obj->ref, 'instance'=>$instance, 'latestbackup_date_ok'=>$dbtousetosearch->jdate($obj->latestbackup_date_ok), 'backup_frequency'=>$obj->backup_frequency);
					//print "Found instance ".$instance." with instance_status=".$instance_status." instance_status_bis=".$instance_status_bis." payment_status=".$payment_status."\n";
				}

				if (!empty($instances[$obj->id])) {
					print dol_print_date(dol_now(), "%Y-%m-%d %H:%M:%S")." ----- Process ".$instances[$obj->id]['instance']." in directory ".$backupdir."/".$obj->osu." \n";

					$object->fetch($obj->id);
					if (dol_is_dir($backupdir."/".$obj->osu)) {
						$atleastoneerrorononeserver = 0;

						// Loop on each target server to make backup of backup of instance
						foreach ($SERVERDESTIARRAY as $servername) {
							if (empty($HISTODIR)) {
								$command = "rsync ".$TESTN." -x --exclude-from=".$path."backup_backups.exclude ".$OPTIONS." ".$DIRSOURCE2."/".$obj->osu." ".$USER."@".$servername.":".$DIRDESTI2;
							} else {
								$command = "rsync ".$TESTN." -x --exclude-from=".$path."backup_backups.exclude ".$OPTIONS." --backup --backup-dir=".$DIRDESTI2."/backupold_".$HISTODIR." ".$DIRSOURCE2."/".$obj->osu." ".$USER."@".$servername.":".$DIRDESTI2;
							}
							print dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')." ".$command."\n";
							$output = array();
							$return_var = 0;
							exec($command, $output, $return_var);

							if ($return_var != 0) {
								$ret2[$servername] = $ret2[$servername] + 1;
								print "ERROR Failed to make rsync for ".$DIRSOURCE2." to ".$servername." ret=".$ret2[$servername]." \n";
								print "Command was: ".$command."\n";
								$totalinstancesfailed += 1;
								$errstring .="\n".dol_print_date(dol_now(), "%Y-%m-%d %H:%M:%S")." Dir ".$DIRSOURCE2." to ".$servername.". ret=".$ret2[$servername].". Command was: ".$command."\n";

								$atleastoneerrorononeserver = 1;
							} else {
								//Duc to modify
								$totalinstancessaved += 1;

								print dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')." Scan dir named ".$DIRSOURCE2."/".$obj->osu."\n";

								if ($nbdu < 50) {
									if (dol_is_dir($homedir."/".$obj->osu."/")) {
										$DELAYUPDATEDUC = -15;
										print dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')." Search if a recent duc file exists with find ".$homedir."/".$obj->osu.".duc.db -mtime ".$DELAYUPDATEDUC." 2>/dev/null | wc -l\n";
										$command = "find ".$homedir."/".$obj->osu."/.duc.db -mtime ".$DELAYUPDATEDUC." 2>/dev/null | wc -l";
										$output = array();
										$return_var = 0;
										exec($command, $output, $return_var);
										if ($output[0] == "0") {
											print dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')." No recent .duc.db into".$homedir."/".$obj->osu."/.duc.db and nb already updated = ".$nbdu.", so we update it.\n";
											$command = "duc index ".$homedir."/".$obj->osu."/ -x -m 3 -d ".$homedir."/".$obj->osu."/.duc.db";
											print $command."\n";
											$output = array();
											$return_var = 0;
											exec($command, $output, $return_var);
											$command = "chown ".$obj->osu.".".$obj->osu." ".$homedir."/".$obj->osu."/.duc.db";
											exec($command, $output, $return_var);
											$nbdu ++;
										} else {
											print dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')." File ".$homedir."/".$obj->osu.".duc.db was recently updated \n";
										}
									} else {
										print dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')." Dir ".$homedir."/".$obj->osu."/ does not exists, we cancel duc for ".$homedir."/".$obj->osu."/ \n";
									}
								} else {
									print dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')." Max nb of update to do reached (".$nbdu."), we cancel duc for ".$homedir."/".$obj->osu."/ \n";
								}
							}
						}

						if ($testorconfirm == "confirm") {
							$object->array_options["options_latestbackupremote_date"] = dol_now();
							if ($atleastoneerrorononeserver) {
								$object->array_options["options_latestbackupremote_status"] = "KO";
							} else {
								$object->array_options["options_latestbackupremote_date_ok"] = dol_now();
								$object->array_options["options_latestbackupremote_status"] = "OK";
							}

							$res = $object->update($user, 1); //Make script stop crash
							if ($res <= 0) {
								print "\nUpdate of Contract error ".$backupdir."/".$obj->osu.": ".$object->error.", ".join($object->errors)."\n";
							}
						}
					} else {
						print "No directory found starting with name ".$backupdir."/".$obj->osu."\n";
						$errstring .= dol_print_date(dol_now(), "%Y-%m-%d %H:%M:%S")." No directory found starting with name ".$backupdir."/".$obj->osu."\n";
					}
					print "\n";
				}
				$i++;
			}
		}
	}
}
//print "\n\n".dol_print_date(dol_now(), "%Y-%m-%d %H:%M:%S")." End with errstring=".$errstring;

// Loop on each targeted server for return code
$atleastoneerror = 0;

foreach ($SERVERDESTIARRAY as $servername) {
	print dol_print_date(dol_now(), "%Y-%m-%d %H:%M:%S")." End for ".$servername." ret1[".$servername."]=".$ret1[$servername]." ret2[".$servername."]=".$ret2[$servername]."\n";
	if ($ret1[$servername] != 0) {
		$atleastoneerror = 1;
	} elseif ($ret2[$servername] != 0) {
		$atleastoneerror = 1;
	}
}

//Delete temporary emptydir
dol_delete_dir($homedir."/emptydir");

// Send email if there is one error
if ($atleastoneerror != 0) {
	$subject = "[Warning] Backup of backup to remote server(s) failed for ".gethostname();
	$msg = "Failed to make copy backup to remote backup server(s) ".$SERVDESTI.".\nNumber of instances successfully saved: ".$totalinstancessaved."\nNumber of instances unsuccessfully saved: ".$totalinstancesfailed."\nErrors or warnings are:\n".$errstring;
	$cmail = new CMailFile($subject, $EMAILTO, $EMAILFROM, $msg);
	$cmail->sendfile();
	exit(1);
}

if (isset($argv[3]) && $argv[3] != "--delete") {
	print "Script was called for only one of few given instances. No email or supervision event sent on success in such situation.\n";
} else {
	print "Send email to ".$EMAILTO." to inform about backup success\n";
	$subject = "[Backup of Backup - ".gethostname()."] Backup of backup to remote server succeed";
	$msg = "The backup of backup for ".gethostname()." to remote backup server ".$SERVDESTI." succeed.\nNumber of instances successfully saved: ".$totalinstancessaved."\n".$errstring;
	$cmail = new CMailFile($subject, $EMAILTO, $EMAILFROM, $msg);
	$cmail->sendfile();
}
print "\n";

exit(0);
