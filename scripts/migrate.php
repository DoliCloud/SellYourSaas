#!/usr/bin/php
<?php
/* Copyright (C) 2020 Laurent Destailleur	<eldy@users.sourceforge.net>
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
 * Migrate an old instance on a new server. Script must be ran with admin.
 */

if (!defined('NOSESSION')) define('NOSESSION', '1');

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}

// Global variables
$version='1.0';
$error=0;

// Include Dolibarr environment
@set_time_limit(0);							// No timeout for this script
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);		// Set this define to 0 if you want to lock your script when dolibarr setup is "locked to admin user only".
//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');                                  // Do not create database handler $db
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');                                // Do not load object $user

// Load Dolibarr environment
$res=0;
// Try master.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/master.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/master.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/master.inc.php")) $res=@include dirname(substr($tmp, 0, ($i+1)))."/master.inc.php";
// Try master.inc.php using relative path
if (! $res && file_exists("./master.inc.php")) $res=@include "./master.inc.php";
if (! $res && file_exists("../master.inc.php")) $res=@include "../master.inc.php";
if (! $res && file_exists("../../master.inc.php")) $res=@include "../../master.inc.php";
if (! $res && file_exists("../../../master.inc.php")) $res=@include "../../../master.inc.php";
if (! $res) die("Include of master fails");
// After this $db, $mysoc, $langs, $conf and $hookmanager are defined (Opened $db handler to database will be closed at end of file).
// $user is created but empty.

dol_include_once("/sellyoursaas/core/lib/dolicloud.lib.php");
dol_include_once('/sellyoursaas/class/packages.class.php');
include_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/utils.class.php';
//include_once(DOL_DOCUMENT_ROOT.'/user/class/user.class.php');

// Read /etc/sellyoursaas.conf file
$databasehost='localhost';
$databaseport='3306';
$database='';
$databaseuser='sellyoursaas';
$databasepass='';
$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
		if ($tmpline[0] == 'masterserver') {
			$masterserver = $tmpline[1];
		}
		if ($tmpline[0] == 'ipserverdeployment') {
			$ipserverdeployment = $tmpline[1];
		}
		if ($tmpline[0] == 'instanceserver') {
			$instanceserver = $tmpline[1];
		}
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
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit;
}


$langs->loadLangs(array("main", "errors"));

$oldinstance=isset($argv[1])?$argv[1]:'';

$newinstance=isset($argv[2])?$argv[2]:'';
$mode=isset($argv[3])?$argv[3]:'';

$langsen = new Translate('', $conf);
$langsen->setDefaultLang($mysoc->default_lang);
$langsen->loadLangs(array("main", "errors"));

$user->fetch($conf->global->SELLYOURSAAS_ANONYMOUSUSER);


/*
 *	Main
 */

print "***** ".$script_file." *****\n";

if (empty($newinstance) || empty($mode)) {
	print "Migrate an old instance on a new server. Script must be ran with admin.\n";
	print "Script must be ran from the master server.\n";
	print "Usage: ".$script_file." oldinstance newinstance (test|confirm) [MYPRODUCTREF]\n";
	print "Return code: 0 if success, <>0 if error\n";
	exit(-1);
}

/*
	if (0 != posix_getuid()) {
		echo "Script must be ran with root.\n";
		exit(-1);
	}
} else {*/
if (0 == posix_getuid()) {
	echo "Script must not be ran with root (but with the 'admin' sellyoursaas account).\n";
	exit(-1);
}

/*if (empty($ipserverdeployment))
{
	echo "Script can't find the value of 'ipserverdeployment' in sellyoursaas.conf file).\n";
	exit(-1);
}
if (empty($instanceserver))
{
	echo "This server seems to not be a server for deployment of instances (this should be defined in sellyoursaas.conf file).\n";
	exit(-1);
}*/

//$dbmaster=getDoliDBInstance('mysqli', $databasehost, $databaseuser, $databasepass, $database, $databaseport);
$dbmaster = $db;
if ($dbmaster->error) {
	dol_print_error($dbmaster, "host=".$databasehost.", port='.$databaseport.', user=".$databaseuser.", databasename=".$database.", ".$dbmaster->error);
	exit;
}
if ($dbmaster) {
	$conf->setValues($dbmaster);
}
if (empty($db)) $db=$dbmaster;


//$user = new User();
//$user->fetch($conf->global->SELLYOURSAAS_ANONYMOUSUSER);


// Forge complete name of instance
if (! empty($newinstance) && ! preg_match('/\./', $newinstance) && ! preg_match('/\.home\.lan$/', $newinstance)) {
	$tmparray = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
	$tmpstring = preg_replace('/:.*$/', '', $tmparray[0]);
	$newinstance=$newinstance.".".$tmpstring;   // Automatically concat first domain name
}


// TODO Test if $ipserverdeployment (in sellyoursaas.conf) match ip target by $newinstance. If not error.
// Test $ipserverdeployment


$tmppackage = new Packages($dbmaster);


// Get data of old instance
$oldobject = new Contrat($dbmaster);
$result=$oldobject->fetch('', '', $oldinstance);
$oldobject->fetch_thirdparty();

if ($result <= 0 || $oldobject->statut == 0 || $oldobject->array_options['options_deployment_status'] != 'done') {
	print "Error: old instance to migrate '".$oldinstance."' with a deployment status = 'done' not found.\n";
	exit(-1);
}

$oldoshost=$oldobject->array_options['options_hostname_os'];
$oldosuser=$oldobject->array_options['options_username_os'];
$oldospass=$oldobject->array_options['options_password_os'];
//$oldosdir=$oldobject->array_options['dir'];

$olddbhost=$oldobject->array_options['options_hostname_db'];
$olddbname=$oldobject->array_options['options_database_db'];
$olddbport=($oldobject->array_options['options_port_db'] ? $oldobject->array_options['options_port_db'] : 3306);
$olddbuser=$oldobject->array_options['options_username_db'];
$olddbpass=$oldobject->array_options['options_password_db'];

/*$db2=getDoliDBInstance('mysqli', $olddbhost, $olddbuser, $olddbpass, $olddbname, $olddbport);
if ($db2->error)
{
	dol_print_error($db2,"host=".$olddbhost.", port=".$olddbport.", user=".$olddbuser.", databasename=".$olddbname.", ".$db2->error);
	exit(-1);
}*/

$productref = '';
if (isset($argv[4])) $productref = $argv[4];
if (empty($productref)) {
	// Get tmppackage
	foreach ($oldobject->lines as $keyline => $line) {
		$tmpproduct = new Product($dbmaster);
		if ($line->fk_product > 0) {
			$tmpproduct->fetch($line->fk_product);
			if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
				if ($tmpproduct->array_options['options_package'] > 0) {
					$productref = $tmpproduct->ref;
					$tmppackage->fetch($tmpproduct->array_options['options_package']);
					$freeperioddays = $tmpproduct->array_options['options_freeperioddays'];
					break;
				} else {
					dol_syslog("Error: ID of package not defined on productwith ID ".$line->fk_product);
				}
			}
		}
	}
}
if (empty($productref)) {
	print "Error: Failed to get product ref of instance '".$oldinstance."'\n";
	exit(-1);
}

$createthirdandinstance = 0;

$newobject = new Contrat($dbmaster);
$result=$newobject->fetch('', '', $newinstance);
if ($result > 0 && ($newobject->statut > 0 || $newobject->array_options['options_deployment_status'] != 'processing')) {
	print "Error: An existing instance called '".$newinstance."' with deployment status != 'processing' already exists.\n";
	exit(-1);
}

$newobject->instance = $newinstance;
$newobject->username_web = $oldobject->array_options['options_username_os'];
$newobject->password_web = $oldobject->array_options['options_password_os'];
$newobject->hostname_web = $oldobject->array_options['options_hostname_os'];
$newobject->username_db  = $oldobject->array_options['options_username_db'];
$newobject->password_db  = $oldobject->array_options['options_password_db'];
$newobject->database_db  = $oldobject->array_options['options_database_db'];

if (empty($newobject->instance) || empty($newobject->username_web) || empty($newobject->password_web) || empty($newobject->database_db)) {
	print "Error: Some properties for instance ".$newinstance." could not be retreived from old instance (missing instance, username_web, password_web or database_db).\n";
	exit(-3);
}

$olddirdb=preg_replace('/_([a-zA-Z0-9]+)/', '', $oldobject->array_options['options_database_db']);
$sourcedir=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$oldobject->array_options['options_username_os'].'/'.$olddirdb;

$oldsftpconnectstring=$oldosuser.'@'.$oldoshost.':'.$sourcedir;


print '--- Create new container for new instance'."\n";

$newpass = $oldobject->array_options['options_deployment_initial_password'];
if (empty($newpass)) $newpass = getRandomPassword(true, array('I'), 16);

$command='php '.($path?$path:'./')."../myaccount/register_instance.php ".escapeshellarg($productref)." ".escapeshellarg($newinstance)." ".escapeshellarg($newpass)." ".escapeshellarg($oldobject->thirdparty->id);
echo $command."\n";

$return_val = 0;
if ($mode == 'confirm') {
	//$output = shell_exec($command);
	/*ob_start();
	passthru($command, $return_val);
	$content_grabbed=ob_get_contents();
	ob_end_clean();
	$return_val = $resultarray['result'];
	*/
	$utils = new Utils($db);
	$outputfile = $conf->admin->dir_temp.'/out.tmp';
	$resultarray = $utils->executeCLI($command, $outputfile);

	$return_val = $resultarray['result'];
	$content_grabbed = $resultarray['output'];

	echo "Result: ".$return_val."\n";
	echo "Output: ".$content_grabbed."\n";
}

if ($return_val != 0) $error++;

// Return
if (! $error) {
	print '-> Creation of new instance success with name '.$newinstance."\n";
} else {
	print '-> Failed to create new instance with name '.$newinstance."\n";
	exit(-1);
}

$newobject = new Contrat($dbmaster);
$result=$newobject->fetch('', '', $newinstance);

$newserver=$newobject->array_options['options_hostname_os'];
$newlogin=$newobject->array_options['options_username_os'];
$newpassword=$newobject->array_options['options_password_os'];
$newserverbase=$newobject->array_options['options_hostname_db'];
$newloginbase=$newobject->array_options['options_username_db'];
$newpasswordbase=$newobject->array_options['options_password_db'];
$newdatabasedb=$newobject->array_options['options_database_db'];

if ($result <= 0 || empty($newlogin) || empty($newdatabasedb)) {
	print "Error: Failed to find instance '".$newinstance."' (it should have been created before). Are you in test mode ?\n";
	exit(-1);
}

if (! empty($oldobject->array_options['options_custom_url'])) {
	print "Update new instance to set the custom url to ".$oldobject->array_options['options_custom_url']."\n";
	$newobject->array_options['options_custom_url'] = $oldobject->array_options['options_custom_url'];
	if ($mode == 'confirm') {
		$newobject->update($user, 1);
	}
}

$newsftpconnectstring=$newlogin.'@'.$newserver.':'.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$newlogin.'/'.preg_replace('/_([a-zA-Z0-9]+)$/', '', $newdatabasedb);

$createthirdandinstance=1;

// Now sync files

$targetdir=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$newlogin.'/'.$newdatabasedb;
print '--- Synchro of files '.$sourcedir.' to '.$targetdir."\n";
print 'SFTP old connect string : '.$oldsftpconnectstring."\n";
print 'SFTP new connect string : '.$newsftpconnectstring."\n";
print 'SFTP old password '.$oldospass."\n";
//print 'SFTP new password '.$newpassword."\n";

$command="rsync";
$param=array();
if (! in_array($mode, array('confirm'))) $param[]="-n";
//$param[]="-a";
if (! in_array($mode, array('diff','diffadd','diffchange'))) $param[]="-rlt";
else { $param[]="-rlD"; $param[]="--modify-window=1000000000"; $param[]="--delete -n"; }
$param[]="-v";
if (empty($createthirdandinstance)) $param[]="-u";		// If we have just created instance, we overwrite file during rsync
$param[]="--exclude .buildpath";
$param[]="--exclude .git";
$param[]="--exclude .gitignore";
$param[]="--exclude .settings";
$param[]="--exclude .project";
$param[]="--exclude htdocs/conf/conf.php";
if (! in_array($mode, array('diff','diffadd','diffchange'))) $param[]="--stats";
if (in_array($mode, array('clean','confirmclean'))) $param[]="--delete";
$param[]="-e 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no'";

$param[]=$oldosuser.'@'.$oldoshost.":".$sourcedir.'/*';
//$param[]=$newlogin.'@'.$newserver.":".$targetdir;
$param[]=$targetdir;

//var_dump($param);
$fullcommand=$command." ".join(" ", $param);
$output=array();
$return_var=0;
print $fullcommand."\n";
//exec($fullcommand, $output, $return_var);

print "Run this command on target host and press a key to continue...\n";
$input = trim(fgets(STDIN));



// Output result
foreach ($output as $outputline) {
	print $outputline."\n";
}

// Remove install.lock file if mode )) confirmunlock
/*
if ($mode == 'confirmunlock')
{
	// SFTP connect
	if (! function_exists("ssh2_connect")) { dol_print_error('','ssh2_connect function does not exists'); exit(1); }

	$newserver=$newobject->instance.'.with.dolicloud.com';
	$server_port = (! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? $conf->global->SELLYOURSAAS_SSH_SERVER_PORT : 22);
	$connection = ssh2_connect($newserver, $server_port);
	if ($connection)
	{
		//print $object->instance." ".$object->username_web." ".$object->password_web."<br>\n";
		if (! @ssh2_auth_password($connection, $newobject->username_web, $newobject->password_web))
		{
			dol_syslog("Could not authenticate with username ".$username." . and password ".preg_replace('/./', '*', $password), LOG_ERR);
			exit(-5);
		}
		else
		{
			$sftp = ssh2_sftp($connection);

			// Check if install.lock exists
			$dir=preg_replace('/_([a-zA-Z0-9]+)$/','',$newdatabasedb);
			$fileinstalllock=$conf->global->DOLICLOUD_EXT_HOME.'/'.$object->username_web.'/'.$dir.'/documents/install.lock';

			print 'Remove file '.$fileinstalllock."\n";

			ssh2_sftp_unlink($sftp, $fileinstalllock);
		}
	}
	else
	{
		print 'Failed to connect to ssh2 to '.$server;
		exit(-6);
	}
}
*/

if ($return_var) {
	print "-> Error during rsync of instance ".$newobject->ref_customer.": ".$targetdir."\n";
	exit(-1);
} else {
	print "-> Files were sync into dir of instance ".$newobject->ref_customer.": ".$targetdir."\n";
}
print "\n";



print "--- Set permissions with chown -R ".$newlogin.".".$newlogin." ".$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$newlogin.'/'.$newdatabasedb."\n";
$output=array();
$return_varchmod=0;
if ($mode == 'confirm') {
	if (empty($conf->global->DOLICLOUD_INSTANCES_PATH) || empty($newlogin) || empty($newdatabasedb)) {
		print 'Bad value for data. We stop to avoid drama';
		exit(-7);
	}
	$fullcommand = "chown -R ".$newlogin.".".$newlogin." ".$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$newlogin.'/'.$newdatabasedb;

	print $fullcommand."\n";
	//exec($fullcommand, $output, $return_varchmod);

	print "Run this command on target host and press a key to continue...\n";
	$input = trim(fgets(STDIN));
}

// Output result
foreach ($output as $outputline) {
	print $outputline."\n";
}

print "\n";

print "-> Files owner were modified for instance ".$newobject->ref_customer.": ".$targetdir." to user ".$newlogin."\n";


print '--- Dump database '.$olddbname.' into /tmp/mysqldump_'.$olddbname.'_'.gmstrftime('%d').".sql\n";

$command="mysqldump";
$param=array();
$param[]=$olddbname;
$param[]="-h";
$param[]=$olddbhost;
$param[]="-u";
$param[]=$olddbuser;
$param[]='-p"'.str_replace(array('"','`'), array('\"','\`'), $olddbpass).'"';
$param[]="--compress";
$param[]="-l";
$param[]="--single-transaction";
$param[]="-K";
$param[]="--tables";
$param[]="-c";
$param[]="-e";
$param[]="--hex-blob";
$param[]="--default-character-set=utf8";

$fullcommand=$command." ".join(" ", $param);
$fullcommand.=' > /tmp/mysqldump_'.$olddbname.'_'.gmstrftime('%d').'.sql';
$output=array();
$return_varmysql=0;
print strftime("%Y%m%d-%H%M%S").' '.$fullcommand."\n";
exec($fullcommand, $output, $return_varmysql);
print strftime("%Y%m%d-%H%M%S").' mysqldump done (return='.$return_varmysql.')'."\n";

// Output result
foreach ($output as $outputline) {
	print $outputline."\n";
}

if ($return_var) {
	print "-> Error during mysql dump of instance ".$oldobject->ref_customer."\n";
	exit(-1);
}


print '--- Load database '.$newdatabasedb.' from /tmp/mysqldump_'.$olddbname.'_'.gmstrftime('%d').".sql\n";
//print "If the load fails, try to run mysql -u".$newloginbase." -p".$newpasswordbase." -D ".$newobject->database_db."\n";

$fullcommanda='echo "drop table llx_accounting_account;" | mysql -h'.$newserverbase.' -u'.$newloginbase.' -p'.$newpasswordbase.' -D '.$newdatabasedb;
$output=array();
$return_var=0;
print strftime("%Y%m%d-%H%M%S").' Drop table to prevent load error with '.$fullcommanda."\n";
if ($mode == 'confirm' || $mode == 'confirmrm') {
	exec($fullcommanda, $output, $return_var);
	foreach ($output as $line) print $line."\n";
}

$fullcommandb='echo "drop table llx_accounting_system;" | mysql -h'.$newserverbase.' -u'.$newloginbase.' -p'.$newpasswordbase.' -D '.$newdatabasedb;
$output=array();
$return_var=0;
print strftime("%Y%m%d-%H%M%S").' Drop table to prevent load error with '.$fullcommandb."\n";
if ($mode == 'confirm' || $mode == 'confirmrm') {
	exec($fullcommandb, $output, $return_var);
	foreach ($output as $line) print $line."\n";
}

$fullcommand="cat /tmp/mysqldump_".$olddbname.'_'.gmstrftime('%d').".sql | mysql -h".$newserverbase." -u".$newloginbase." -p".$newpasswordbase." -D ".$newdatabasedb;
print strftime("%Y%m%d-%H%M%S")." Load dump with ".$fullcommand."\n";
if ($mode == 'confirm' || $mode == 'confirmrm') {
	$output=array();
	$return_var=0;
	print strftime("%Y%m%d-%H%M%S").' '.$fullcommand."\n";
	exec($fullcommand, $output, $return_var);
	foreach ($output as $line) print $line."\n";
}

if ($return_var) {
	print "-> Error during mysql load of instance ".$newobject->ref_customer."\n";
	exit(-1);
}


// TODO Move link of template invoice on old contract to this new contract
$sql = 'UPDATE '.MAIN_DB_PREFIX.'facture_rec SET fk_source = '.$newobject->id.' WHERE';
$sql.= ' fk_source = '.$oldobject->id." AND sourcetype = 'contrat' AND targettype = 'facturerec'";
print $sql."\n";
//$dbmaster->query($sql);


print "\n";

if ($mode == 'confirm') {
	print '-> Dump loaded into database '.$newdatabasedb.'. You can test instance on URL https://'.$newobject->ref_customer."\n";
	print "Finished.\n";
} else {
	print '-> Dump NOT loaded (test mode) into database '.$newdatabasedb.'. You can test instance on URL https://'.$newobject->ref_customer."\n";
}
print "Finished. DON'T FORGET TO SUSPEND INSTANCE ON OLD SYSTEM AND TO MOVE RECURRING INVOICE ON NEW INSTANCE !!!\n";


exit($return_var + $return_varmysql);


// Add end do something like
// update record set address = '79.137.96.15' where address <> '79.137.96.15' AND domain_id IN (select id from domain where sld = 'testldr14') LIMIT 1;
