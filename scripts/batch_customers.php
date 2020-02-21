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
 *      \file       sellyoursaas/scripts/batch_customers.php
 *		\ingroup    sellyoursaas
 *      \brief      Main SellYourSaas batch (to run on master hosts for action updatedatabase|updatecountsonly|updatestatsonly, on deployment hosts for other actions)
 *      			backup_instance.php (payed customers rsync + databases backup)
 *      			update database info for customer
 *      			update statistics
 */

if (! defined('NOREQUIREDB'))              define('NOREQUIREDB','1');					// Do not create database handler $db

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname($_SERVER['PHP_SELF']).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
    echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
    exit;
}

// Global variables
$version='1.0';
$error=0;


// -------------------- START OF YOUR CODE HERE --------------------
@set_time_limit(0);							// No timeout for this script
define('EVEN_IF_ONLY_LOGIN_ALLOWED',1);		// Set this define to 0 if you want to lock your script when dolibarr setup is "locked to admin user only".

// Load Dolibarr environment
$res=0;
// Try master.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/master.inc.php")) $res=@include(substr($tmp, 0, ($i+1))."/master.inc.php");
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/master.inc.php")) $res=@include(dirname(substr($tmp, 0, ($i+1)))."/master.inc.php");
// Try master.inc.php using relative path
if (! $res && file_exists("../master.inc.php")) $res=@include("../master.inc.php");
if (! $res && file_exists("../../master.inc.php")) $res=@include("../../master.inc.php");
if (! $res && file_exists("../../../master.inc.php")) $res=@include("../../../master.inc.php");
if (! $res) die("Include of master fails");
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

dol_include_once('/sellyoursaas/class/dolicloud_customers.class.php');
include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
include_once dol_buildpath("/sellyoursaas/backoffice/lib/refresh.lib.php");


// Read /etc/sellyoursaas.conf file
$databasehost='localhost';
$database='';
$databaseuser='sellyoursaas';
$databasepass='';
$ipserverdeployment='';
$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
    $array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
    foreach($array as $val)
    {
        $tmpline=explode("=", $val);
        if ($tmpline[0] == 'databasehost')
        {
            $databasehost = $tmpline[1];
        }
        if ($tmpline[0] == 'database')
        {
            $database = $tmpline[1];
        }
        if ($tmpline[0] == 'databaseuser')
        {
            $databaseuser = $tmpline[1];
        }
        if ($tmpline[0] == 'databasepass')
        {
            $databasepass = $tmpline[1];
        }
        if ($tmpline[0] == 'ipserverdeployment')
        {
            $ipserverdeployment = $tmpline[1];
        }
    }
}
else
{
    print "Failed to open /etc/sellyoursaas.conf file\n";
    exit;
}



/*
 * Main
 */

$dbmaster=getDoliDBInstance('mysqli', $databasehost, $databaseuser, $databasepass, $database, 3306);
if ($dbmaster->error)
{
    dol_print_error($dbmaster,"host=".$databasehost.", port=3306, user=".$databaseuser.", databasename=".$database.", ".$dbmaster->error);
    exit;
}
if ($dbmaster)
{
    $conf->setValues($dbmaster);
}
if (empty($db)) $db=$dbmaster;

//$langs->setDefaultLang('en_US'); 	// To change default language of $langs
$langs->load("main");				// To load language file for default language
@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
//$result=$user->fetch('','admin');	// Load user for login 'admin'. Comment line to run as anonymous user.
//if (! $result > 0) { dol_print_error('',$user->error); exit; }
//$user->getrights();


print "***** ".$script_file." (".$version.") - ".strftime("%Y%m%d-%H%M%S")." *****\n";
if (! isset($argv[1])) {	// Check parameters
    print "Usage on master            : ".$script_file." (updatedatabase|updatecountsonly|updatestatsonly) [instancefilter]\n";
    print "Usage on deployment servers: ".$script_file." (backuptestrsync|backuptestdatabase|backup) [instancefilter]\n";
    print "\n";
    print "- backuptestrsync     test rsync backup\n";
    print "- backuptestdatabase  test mysqldump backup\n";
    print "- backuprsync         creates backup (rsync)\n";
    print "- backupdatabase      creates backup (mysqldump)\n";
    print "- backup              creates backup (rsync + mysqldump) ***** Used by cron on deployment servers *****\n";
    print "- updatedatabase      (=updatecountsonly+updatestatsonly) updates list and nb of users, modules and version and stats.\n";
    print "- updatecountsonly    updates counters of instances only (only nb of user for instances)\n";
    print "- updatestatsonly     updates stats only (only table dolicloud_stats) and send data to Datagog if enabled ***** Used by cron on master server *****\n";
    exit;
}
print '--- start'."\n";
//print 'Argument 1='.$argv[1]."\n";
//print 'Argument 2='.$argv[2]."\n";

$now = dol_now();

$action=$argv[1];
$nbofok=0;
// Nb of deployed instances
$nbofinstancedeployed=0;
// Nb of paying instance
$nbofactiveok=0;
$nbofactive=0;
$nbofactivesusp=0;
$nbofactivepaymentko=0;
$nbofalltime=0;
$nboferrors=0;
$instancefilter=(isset($argv[2])?$argv[2]:'');
$instancefiltercomplete=$instancefilter;

// Forge complete name of instance
if (! empty($instancefiltercomplete) && ! preg_match('/\./', $instancefiltercomplete) && ! preg_match('/\.home\.lan$/', $instancefiltercomplete))
{
    $tmparray = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
    $tmpstring = preg_replace('/:.*$/', '', $tmparray[0]);
    $instancefiltercomplete=$instancefiltercomplete.".".$tmpstring;   // Automatically concat first domain name
}

$instances=array();
$instancesactivebutsuspended=array();
$instancesbackuperror=array();
$instancesupdateerror=array();


include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
$object=new Contrat($dbmaster);

// Get list of instance
$sql = "SELECT c.rowid as id, c.ref, c.ref_customer as instance,";
$sql.= " ce.deployment_status as instance_status";
$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object";
$sql.= " WHERE c.ref_customer <> '' AND c.ref_customer IS NOT NULL";
if ($instancefiltercomplete) {
    $stringforsearch = '';
    $tmparray = explode(',', $instancefiltercomplete);
    foreach($tmparray as $instancefiltecompletevalue)
    {
        if (! empty($stringforsearch)) $stringforsearch.=", ";
        $stringforsearch.="'".trim($instancefiltecompletevalue)."'";
    }
    $sql.= " AND c.ref_customer IN (".$stringforsearch.")";
}
else $sql.= " AND ce.deployment_status <> 'undeployed'";		// Exclude undeployed only if we don't request a specific instance
$sql.= " AND ce.deployment_status IS NOT NULL";
// Add filter on deployment server
if ($action == 'backup' || $action == 'backuprsync' || $action == 'backupdatabase' || $action == 'backuptestrsync' || $action == 'backuptestdatabase')
{
    $sql.=" AND ce.deployment_host = '".$dbmaster->escape($ipserverdeployment)."'";
}

$dbtousetosearch = $dbmaster;

print $sql."\n";                                    // To have this into the ouput of cron job
dol_syslog($script_file." sql=".$sql, LOG_DEBUG);
$resql=$dbtousetosearch->query($sql);
if ($resql)
{
	$num = $dbtousetosearch->num_rows($resql);
	$i = 0;
	if ($num)
	{
		while ($i < $num)
		{
			$obj = $dbtousetosearch->fetch_object($resql);
			if ($obj)
			{
				$instance = $obj->instance;
				$payment_status='PAID';

				dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');

				$instance_status_bis = '';
				$result = $object->fetch($obj->id);
				if ($result <= 0) {
				    $i++;
				    dol_print_error($dbmaster, $object->error, $object->errors);
				    continue;
				}
				else
				{
					if ($object->array_options['options_deployment_status'] == 'processing') { $instance_status = 'PROCESSING'; }
					elseif ($object->array_options['options_deployment_status'] == 'undeployed') { $instance_status = 'CLOSED'; $instance_status_bis = 'UNDEPLOYED'; }
					elseif ($object->array_options['options_deployment_status'] == 'done')       {
                        $instance_status = 'DEPLOYED';
                        $nbofinstancedeployed++;
					}
					else { $instance_status = 'UNKNOWN'; }
				}

				$issuspended = sellyoursaasIsSuspended($object);
				if ($issuspended)
				{
					$instance_status = 'SUSPENDED';
				}

				$ispaid = sellyoursaasIsPaidInstance($object);
				if (! $ispaid) $payment_status='TRIAL';
				else
				{
					$ispaymentko = sellyoursaasIsPaymentKo($object);
					if ($ispaymentko) $payment_status='FAILURE';
				}

				if (empty($instance_status_bis)) $instance_status_bis=$instance_status;
				print "Analyze instance ".($i+1)." ".$instance." status=".$instance_status." instance_status=".$instance_status_bis." payment_status=".$payment_status."\n";

				// Count
				if (! in_array($payment_status,array('TRIAL','TRIALING','TRIAL_EXPIRED')))
				{
					$nbofalltime++;
					if (! in_array($instance_status,array('PROCESSING')) && ! in_array($instance_status,array('CLOSED')) && ! in_array($instance_status_bis,array('UNDEPLOYED')))		// Nb of active
					{
						$nbofactive++;

						if (in_array($instance_status,array('SUSPENDED')))
						{
							$nbofactivesusp++;
							$instancesactivebutsuspended[$obj->id]=$obj->ref.' ('.$instance.')';
						}
						else if (in_array($payment_status,array('FAILURE','PAST_DUE'))) $nbofactivepaymentko++;
						else $nbofactiveok++; // not suspended, not close request

						$instances[$obj->id]=$instance;
						print "Qualify instance ".$instance." with instance_status=".$instance_status." instance_status_bis=".$instance_status_bis." payment_status=".$payment_status."\n";
					}
					else
					{
						//print "Found instance ".$instance." with instance_status=".$instance_status." instance_status_bis=".$instance_status_bis." payment_status=".$payment_status."\n";
					}
				}
				elseif ($instancefiltercomplete)
				{
					$instances[$obj->id]=$instance;
					print "Qualify instance ".$instance." with instance_status=".$instance_status." instance_status_bis=".$instance_status_bis." payment_status=".$payment_status."\n";
				}
				else
				{
					//print "Found instance ".$instance." with instance_status=".$instance_status." instance_status_bis=".$instance_status_bis." payment_status=".$payment_status."\n";
				}
			}
			$i++;
		}
	}
}
else
{
	$error++;
	$nboferrors++;
	dol_print_error($dbtousetosearch);
}
print "Found ".count($instances)." not trial instances including ".$nbofactivesusp." suspended + ".$nbofactivepaymentko." active with payment ko\n";


//print "----- Start loop for backup_instance\n";
if ($action == 'backup' || $action == 'backuprsync' || $action == 'backupdatabase' || $action == 'backuptestrsync' || $action == 'backuptestdatabase')
{
	if (empty($conf->global->DOLICLOUD_BACKUP_PATH))
	{
		print "Error: Setup of module SellYourSaas not complete. Path to backup not defined.\n";
		exit -1;
	}

	// Loop on each instance
	if (! $error)
	{
		$i = 0;
		foreach($instances as $instance)
		{
			$now=dol_now();

			$return_val=0; $error=0; $errors=array();	// No error by default into each loop

			// Run backup
			print "***** Process backup of instance ".($i+1)." ".$instance.' - '.strftime("%Y%m%d-%H%M%S")."\n";

			$mode = 'unknown';
			$mode = ($action == 'backup'?'confirm':$mode);
			$mode = ($action == 'backuprsync'?'confirmrsync':$mode);
			$mode = ($action == 'backupdatabase'?'confirmdatabase':$mode);
			$mode = ($action == 'backuptestdatabase'?'testdatabase':$mode);
			$mode = ($action == 'backuptestrsync'?'testrsync':$mode);

			$command=($path?$path:'')."backup_instance.php ".escapeshellarg($instance)." ".escapeshellarg($conf->global->DOLICLOUD_BACKUP_PATH)." ".$mode;
			echo $command."\n";

			if ($action == 'backup' || $action == 'backuprsync' || $action == 'backupdatabase')
			{
				//$output = shell_exec($command);
				ob_start();
				passthru($command, $return_val);
				$content_grabbed=ob_get_contents();
				ob_end_clean();

				echo "Result: ".$return_val."\n";
				echo "Output: ".$content_grabbed."\n";
			}

			if ($return_val != 0) $error++;

			// Return
			if (! $error)
			{
				$nbofok++;
				print '-> Backup process success for '.$instance."\n";
			}
			else
			{
				$nboferrors++;
				$instancesbackuperror[]=$instance;
				print '-> Backup process fails for '.$instance."\n";
			}

			sleep(1);

			$i++;
		}
	}
}


$today=dol_now();

$error=''; $errors=array();
$servicetouse=strtolower($conf->global->SELLYOURSAAS_NAME);

if ($action == 'updatedatabase' || $action == 'updatestatsonly' || $action == 'updatecountsonly')
{
	print "----- Start updatedatabase\n";

	dol_include_once('sellyoursaas/class/sellyoursaasutils.class.php');
	$sellyoursaasutils = new SellYourSaasUtils($dbmaster);

	// Loop on each instance
	if (! $error && $action != 'updatestatsonly')
	{
		$i=0;
		foreach($instances as $instance)
		{
			$return_val=0; $error=0; $errors=array();

			// Run database update
			print "Process update database info (nb of user) of instance ".($i+1)." ".$instance.' - '.strftime("%Y%m%d-%H%M%S")." : ";

			$dbmaster->begin();

			$result=$object->fetch('','',$instance);
			if ($result < 0) dol_print_error('',$object->error);

			$object->oldcopy=dol_clone($object, 1);

			$result = $sellyoursaasutils->sellyoursaasRemoteAction('refresh', $object);
			if ($result < 0)
			{
				$errors[] = 'Failed to do sellyoursaasRemoteAction(refresh) '.$sellyoursaasutils->error.(is_array($sellyoursaasutils->errors)?' '.join(',',$sellyoursaasutils->errors):'');
			}

			if (count($errors) == 0)
			{
				print "OK";

				$nbofok++;
				$dbmaster->commit();
			}
			else
			{
				$nboferrors++;
				$instancesupdateerror[]=$instance;
				print 'KO. '.join(',',$errors)."\n";
				$dbmaster->rollback();
			}

			$i++;
		}
	}


	if (! $error && $action != 'updatecountsonly')
	{
		$stats=array();

		// Get list of existing stats
		$sql ="SELECT name, x, y";                        // name is 'total', 'totalcommissions', 'totalinstancepaying', 'totalinstances', 'totalusers', 'benefit', 'totalcustomers', 'totalcustomerspaying'
		$sql.=" FROM ".MAIN_DB_PREFIX."dolicloud_stats";
		$sql.=" WHERE service = '".$servicetouse."'";

		dol_syslog($script_file." sql=".$sql, LOG_DEBUG);
		$resql=$dbmaster->query($sql);
		if ($resql)
		{
			$num = $dbmaster->num_rows($resql);
			$i = 0;
			if ($num)
			{
				while ($i < $num)
				{
					$obj = $dbmaster->fetch_object($resql);
					if ($obj)
					{
						$stats[$obj->name][$obj->x]=$obj->y;
						print "Found stats for ".$obj->name." x=".$obj->x." y=".$obj->y."\n";
					}
					$i++;
				}
			}
		}
		else
		{
			$error++;
			$nboferrors++;
			dol_print_error($dbmaster);
		}
		//print "Found already existing stats entries.\n";

		$tmp=dol_getdate(dol_now('tzserver'));
		$endyear=$tmp['year'];
		if (empty($serverprice))
		{
			print 'ERROR Value of variable $serverprice is not defined.';
			exit;
		}

		$YEARSTART = 2018;

		// Update all missing stats
		for($year = $YEARSTART; $year <= $endyear; $year++)
		{
			for($m = 1; $m <= 12; $m++)
			{
				$datefirstday=dol_get_first_day($year, $m, 1);
				$datelastday=dol_get_last_day($year, $m, 1);
				if ($datefirstday > $today) continue;

				$x=sprintf("%04d%02d",$year,$m);

				$statkeylist=array('total','totalcommissions','totalinstancespaying','totalinstances','totalusers','benefit','totalcustomerspaying','totalcustomers');
				foreach($statkeylist as $statkey)
				{
					if (! isset($stats[$statkey][$x]) || ($today <= $datelastday))
					{
						// Calculate stats fro this key
						print "Calculate and update stats for ".$statkey." x=".$x.' datelastday='.dol_print_date($datelastday, 'dayhour', 'gmt');

						$rep = null;
						$part = 0;

						$rep=sellyoursaas_calculate_stats($dbmaster, $datelastday);	// Get qty and amount into template invoices linked to active contracts
						$part = (empty($conf->global->SELLYOURSAAS_PERCENTAGE_FEE) ? 0 : $conf->global->SELLYOURSAAS_PERCENTAGE_FEE);

						if ($rep)
						{
							$total=$rep['total'];
							$totalcommissions=$rep['totalcommissions'];
							$totalinstancespaying=$rep['totalinstancespaying'];
							$totalinstances=$rep['totalinstances'];
							$totalusers=$rep['totalusers'];
							$totalcustomerspaying=$rep['totalcustomerspaying'];
							$totalcustomers=$rep['totalcustomers'];
							$benefit=($total * (1 - $part) - $serverprice - $totalcommissions);

							$y=0;
							if ($statkey == 'total') $y=$total;
							if ($statkey == 'totalcommissions') $y=$totalcommissions;
							if ($statkey == 'totalinstancespaying') $y=$totalinstancespaying;
							if ($statkey == 'totalinstances') $y=$totalinstances;
							if ($statkey == 'totalusers') $y=$totalusers;
							if ($statkey == 'benefit') $y=$benefit;
							if ($statkey == 'totalcustomerspaying') $y=$totalcustomerspaying;
							if ($statkey == 'totalcustomers') $y=$totalcustomers;

							print " -> ".$y."\n";

							if ($today <= $datelastday)	// Remove if current month
							{
								$sql ="DELETE FROM ".MAIN_DB_PREFIX."dolicloud_stats";
								$sql.=" WHERE name = '".$statkey."' AND x='".$x."'";
								$sql.=" AND service = '".$servicetouse."'";
								dol_syslog("sql=".$sql);
								$resql=$dbmaster->query($sql);
								if (! $resql) dol_print_error($dbmaster, '');
							}

							$sql ="INSERT INTO ".MAIN_DB_PREFIX."dolicloud_stats(service, name, x, y)";
							$sql.=" VALUES('".$servicetouse."', '".$statkey."', '".$x."', ".$y.")";
							dol_syslog("sql=".$sql);
							$resql=$dbmaster->query($sql);
							//if (! $resql) dol_print_error($dbmaster,'');		// Ignore error, we may have duplicate record here if record already exists and not deleted
						}
					}
				}
			}
		}
	}
}




// Result
$out = '';
if ($action == 'backup' || $action == 'backuprsync' || $action == 'backupdatabase' || $action == 'backuptestrsync' || $action == 'backuptestdatabase') {
    $out.= "\n";
    $out.= "***** Summary for host ".$ipserverdeployment."\n";
} else {
    $out.= "***** Summary for all deployment servers\n";
}
$out.= "Nb of instances deployed: ".$nbofinstancedeployed."\n";
$out.= "Nb of paying instances (all time): ".$nbofalltime."\n";
$out.= "Nb of paying instances (deployed with or without payment error): ".$nbofactive."\n";
$out.= "Nb of paying instances (deployed but suspended): ".$nbofactivesusp;
$out.= (count($instancesactivebutsuspended)?", suspension on ".join(', ',$instancesactivebutsuspended):"");
$out.= "\n";
$out.= "Nb of paying instances (deployed but payment ko, not yet suspended): ".$nbofactivepaymentko."\n";
if ($action != 'updatestatsonly')
{
	$out.= "Nb of paying instances processed ok: ".$nbofok."\n";
	$out.= "Nb of paying instances processed ko: ".$nboferrors;
}
$out.= (count($instancesbackuperror)?", error for backup on ".join(', ',$instancesbackuperror):"");
$out.= (count($instancesupdateerror)?", error for update on ".join(', ',$instancesupdateerror):"");
$out.= "\n";
print $out;


// Send to DataDog (metric)
if ($action == 'updatedatabase') {
    if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED))
    {
        try {
            print 'Send data to DataDog'."\n";
            dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

            $arrayconfig=array();
            if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY))
            {
                $arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
            }

            $statsd = new DataDog\DogStatsd($arrayconfig);

            $arraytags=null;
            $statsd->gauge('sellyoursaas.instancedeployed', (float) $nbofinstancedeployed, 1.0, $arraytags);
            $statsd->gauge('sellyoursaas.instancepaymentko', (float) $nbofactivesusp+$nbofactivepaymentko, 1.0, $arraytags);
            $statsd->gauge('sellyoursaas.instancepaymentok', (float) $nbofactive, 1.0, $arraytags);
        }
        catch(Exception $e)
        {

        }
    }
}

if (! $nboferrors)
{
	print '--- end OK - '.strftime("%Y%m%d-%H%M%S")."\n";

	if ($action == 'backup' || $action == 'backuprsync' || $action == 'backupdatabase' || $action == 'backuptestrsync' || $action == 'backuptestdatabase')
	{
		$from = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;
		$to = $conf->global->SELLYOURSAAS_SUPERVISION_EMAIL;
		$msg = 'Backup done without errors by '.$script_file." ".$argv[1]." ".$argv[2]."\n\n".$out;

		$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;                 // exemple 'DoliCloud'
		$sellyoursaasdomain = $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME;   // exemple 'dolicloud.com'

		$domainname=getDomainFromURL($_SERVER['SERVER_NAME'], 1);
		$constforaltname = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$domainname;
		if (! empty($conf->global->$constforaltname))
		{
		    $sellyoursaasdomain = $domainname;
		    $sellyoursaasname = $conf->global->$constforaltname;
		}

		include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		print 'Send email MAIN_MAIL_SENDMODE='.$conf->global->MAIN_MAIL_SENDMODE.' MAIN_MAIL_SMTP_SERVER='.$conf->global->MAIN_MAIL_SMTP_SERVER.' from='.$from.' to='.$to.' title=['.$sellyoursaasname.' - '.gethostname().'] Success for backup'."\n";
		$cmail = new CMailFile('['.$sellyoursaasname.' - '.gethostname().'] Success for backup', $to, $from, $msg);
		$result = $cmail->sendfile();
	}
}
else
{
	print '--- end ERROR nb='.$nboferrors.' - '.strftime("%Y%m%d-%H%M%S")."\n";

	if ($action == 'backup' || $action == 'backuprsync' || $action == 'backupdatabase' || $action == 'backuptestrsync' || $action == 'backuptestdatabase')
	{
		$from = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;
		$to = $conf->global->SELLYOURSAAS_SUPERVISION_EMAIL;
		$msg = 'Error in '.$script_file." ".$argv[1]." ".$argv[2]."\n\n".$out;

		include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		print 'Send email MAIN_MAIL_SENDMODE='.$conf->global->MAIN_MAIL_SENDMODE.' MAIN_MAIL_SMTP_SERVER='.$conf->global->MAIN_MAIL_SMTP_SERVER.' from='.$from.' to='.$to.' title=[Warning] Error(s) in backups - '.gethostname().' - '.dol_print_date(dol_now(), 'dayrfc')."\n";
		$cmail = new CMailFile('[Warning] Error(s) in backups - '.gethostname().' - '.dol_print_date(dol_now(), 'dayrfc'), $to, $from, $msg, array(), array(), array(), '', '', 0, 0, '', '', '', '', 'emailing');
		$result = $cmail->sendfile();

		// Send to DataDog (metric + event)
		if (! empty($conf->global->SELLYOURSAAS_DATADOG_ENABLED))
		{
		    try {
		        dol_include_once('/sellyoursaas/core/includes/php-datadogstatsd/src/DogStatsd.php');

		        $arrayconfig=array();
		        if (! empty($conf->global->SELLYOURSAAS_DATADOG_APIKEY))
		        {
		            $arrayconfig=array('apiKey'=>$conf->global->SELLYOURSAAS_DATADOG_APIKEY, 'app_key' => $conf->global->SELLYOURSAAS_DATADOG_APPKEY);
		        }

		        $statsd = new DataDog\DogStatsd($arrayconfig);

		        //$arraytags=array('result'=>'ko');
		        //$statsd->increment('sellyoursaas.backup', 1, $arraytags);

		        $sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;                 // exemple 'DoliCloud'
		        $sellyoursaasdomain = $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME;   // exemple 'dolicloud.com'

		        $domainname=getDomainFromURL($_SERVER['SERVER_NAME'], 1);
		        $constforaltname = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$domainname;
		        if (! empty($conf->global->$constforaltname))
		        {
		            $sellyoursaasdomain = $domainname;
		            $sellyoursaasname = $conf->global->$constforaltname;
		        }

		        $titleofevent =  dol_trunc('[Warning] '.$sellyoursaasname.' - '.gethostname().' - Backup in error', 90);
		        $statsd->event($titleofevent,
		            array(
		                'text'       => $titleofevent." : \n".$msg,
		                'alert_type' => 'warning',
		                'source_type_name' => 'API',
		                'host'       => gethostname()
		                )
		            );
		    }
		    catch(Exception $e)
		    {

		    }
		}
	}
}

$dbmaster->close();	// Close database opened handler

exit($nboferrors);
