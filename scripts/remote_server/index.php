<?php
/*
 * Server agent for SellYourSaas
 * This script is called by a tiny web server and run as root.
 */

// If 1, all parameters are written into the log file (passwords are masked)
$DEBUG = 1;

// Max allowed difference (in seconds) between the timestamp of a request and the agent clock
$requestmaxage = 300;
// Directory used to store the nonces already used (to reject replayed requests)
$noncedir = '/run/sellyoursaas-nonces';

// Files created by this agent (log, nonces) must not be readable by other users
umask(077);

$fh = fopen('/var/log/remote_server.log', 'a+');
if (empty($fh)) {
	http_response_code(501);
	exit();
}
@chmod('/var/log/remote_server.log', 0600);

$allowed_hosts_array = array();
$signature_key = '';
$dnsserver = '';
$instanceserver = '';
$backupdir = '';
$dolibarrdir = '';

// Read /etc/sellyoursaas.conf file
$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
		if ($tmpline[0] == 'allowed_hosts') {
			$allowed_hosts_array = explode(",", $tmpline[1]);
		}
		if ($tmpline[0] == 'signature_key') {	// This value must match the signature key defined on the master into setup constant SELLYOURSAAS_REMOTE_ACTION_SIGNATURE_KEY
			$signature_key = $tmpline[1];
		}
		if ($tmpline[0] == 'dnsserver') {
			$dnsserver = $tmpline[1];
		}
		if ($tmpline[0] == 'instanceserver') {
			$instanceserver = $tmpline[1];
		}
		if ($tmpline[0] == 'backupdir') {
			$backupdir = $tmpline[1];
		}
		if ($tmpline[0] == 'dolibarrdir') {
			$dolibarrdir = $tmpline[1];
		}
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit;
}

// Refuse to run any action if the signature key is missing or too weak (an empty key would make the signature forgeable)
if (strlen($signature_key) < 16) {
	fwrite($fh, "\n".date('Y-m-d H:i:s')." The 'signature_key' in /etc/sellyoursaas.conf is missing or too short (16 chars min). Request refused.\n");
	fclose($fh);

	http_response_code(503);

	print "The 'signature_key' in /etc/sellyoursaas.conf is missing or too short (16 chars min). Request refused.\n";

	exit();
}

// remote_server_launcher.sh already cd's into the mounted scripts directory before starting
// php -S, but that only holds if it survives however the init/service manager forks and
// daemonizes the process (observed to NOT survive a systemd-wrapped LSB service start/restart,
// leaving this process's cwd at whatever systemd's default WorkingDirectory is instead) - so
// set it here too, unconditionally, rather than relying on the launcher alone.
if (!empty($dolibarrdir)) {
	@chdir($dolibarrdir.'/htdocs/custom/sellyoursaas/scripts');
}
//if (! in_array('127.0.0.1', $allowed_hosts_array)) {
//	$allowed_hosts_array[] = '127.0.0.1';	// Add localhost if not present
//}


if (empty($allowed_hosts_array) || ! in_array($_SERVER['REMOTE_ADDR'], $allowed_hosts_array)) {
	fwrite($fh, "\n".date('Y-m-d H:i:s').' >>>>>>>>>> Call done with bad ip '.$_SERVER['REMOTE_ADDR']." : Not into 'allowed_hosts' of /etc/sellyoursaas.conf.\n");
	fclose($fh);

	http_response_code(403);

	print 'IP address '.$_SERVER['REMOTE_ADDR']." is not allowed to access this remote server agent. Check 'allowed_hosts' into /etc/sellyoursaas.conf.\n";

	exit();
}
$allowed_hosts=join(',', $allowed_hosts_array);

// Build param string
// $tmparray[0] is the action (path of URL), $tmparray[1] is the urlencoded list of parameters (POST body).
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
	fwrite($fh, "\n".date('Y-m-d H:i:s').' >>>>>>>>>> Call done with method '.$_SERVER['REQUEST_METHOD'].' by '.$_SERVER['REMOTE_ADDR']." refused. Only signed POST requests are allowed.\n");
	fclose($fh);

	http_response_code(405);

	print "Only signed POST requests are allowed. Upgrade the SellYourSaas master server.\n";

	exit();
}
$tmparray = array(trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'), (string) file_get_contents('php://input'));

$contentsigned = '';
$paramspace = '';			// The string to use as parameter for CLI scripts
$paramspacenoquotes = '';
$paramspaceforlog = '';		// Same as $paramspace but with passwords (param 1 = ospassword, param 7 = dbpassword) masked
$paramarray = array();
if (! empty($tmparray[1])) {
	$contentsigned = $tmparray[1];
	// Generate array of parameters
	$paramarray = explode('&', urldecode($tmparray[1]));
	foreach ($paramarray as $key => $val) {
		$paramspace .= escapeshellarg($val != '' ? $val : '-');	// Use ' to avoid problems with spaces in parameters, and replace '' by '-'
		$paramspace .= " ";
		$paramspaceforlog .= (in_array($key, array(1, 7)) ? 'XXXXXX' : escapeshellarg($val != '' ? $val : '-'))." ";
		$paramspacenoquotes .= ($val != '' ? $val : '-');	// Replace '' by '-'
		$paramspacenoquotes .= " ";
	}
}

// Set variables
$tmpparam = preg_split('/\s/', $paramspacenoquotes);		// $tmpparam is now array of parameters, like $paramarray but with '' replaced by '-'
$osusername = $tmpparam[0];
$instancename = $tmpparam[2];
$dbname = $tmpparam[4];
$dbusername = $tmpparam[6];
$cliafter = $tmpparam[18];
$cliafterpaid = $tmpparam[46];
$cliafterdeployoption = $tmpparam[47];
$requesttimestamp = isset($_SERVER['HTTP_X_SELLYOURSAAS_TIMESTAMP']) ? (string) $_SERVER['HTTP_X_SELLYOURSAAS_TIMESTAMP'] : '';
$requestnonce = isset($_SERVER['HTTP_X_SELLYOURSAAS_NONCE']) ? (string) $_SERVER['HTTP_X_SELLYOURSAAS_NONCE'] : '';
$signature = isset($_SERVER['HTTP_X_SELLYOURSAAS_SIGNATURE']) ? (string) $_SERVER['HTTP_X_SELLYOURSAAS_SIGNATURE'] : '';

// Recalculate the signature with action, timestamp, nonce and body received
$recalculatedsignature = hash_hmac('sha256', $tmparray[0]."\n".$requesttimestamp."\n".$requestnonce."\n".$contentsigned, $signature_key);


/*
 * Actions
 */

$output='';
$return_var=0;

if ($DEBUG) {
	fwrite($fh, "\n".date('Y-m-d H:i:s').' >>>>>>>>>>>>>>>>>>>> index.php Call for action '.$tmparray[0].' by '.$_SERVER['REMOTE_ADDR'].' URI='.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)."\n");
} else {
	fwrite($fh, "\n".date('Y-m-d H:i:s').' >>>>>>>>>>>>>>>>>>>> index.php Call for action '.$tmparray[0]." by ".$_SERVER['REMOTE_ADDR']."\n");
}

fwrite($fh, date('Y-m-d H:i:s').' dnsserver='.$dnsserver.", instanceserver=".$instanceserver.", allowed_hosts=".$allowed_hosts."\n");
//fwrite($fh, date('Y-m-d H:i:s').' contentsigned='.$contentsigned."\n");
//fwrite($fh, date('Y-m-d H:i:s').' signature_key='.$signature_key."\n");
// Never log the full recalculated signature: it would give a valid signature to anyone able to read the log
fwrite($fh, date('Y-m-d H:i:s').' signature='.$signature.", recalculatedsignature=".substr($recalculatedsignature, 0, 8)."...\n");

// Compare signature and recalculatedsignature
if (!hash_equals($recalculatedsignature, $signature)) {
	fwrite($fh, date('Y-m-d H:i:s')." The provided signature by the caller does not match the signature recalculated from received parameters and the local signature key saved into 'signature_key' in the /etc/sellyoursaas.conf file (instance server=".$instancename.")\n");

	http_response_code(598);
	print 'The provided signature by the caller does not match the signature recalculated from received parameters and the local signature key saved into "signature_key" in the /etc/sellyoursaas.conf file (instance server='.$instancename.')'."\n";
	//print "signature_key=".$signature_key;
	exit();
}

// Reject expired or replayed requests
if (!ctype_digit($requesttimestamp) || abs(time() - (int) $requesttimestamp) > $requestmaxage || !preg_match('/^[a-f0-9]{32}$/', $requestnonce)) {
	fwrite($fh, date('Y-m-d H:i:s')." The request timestamp is expired or the nonce is not valid (timestamp=".$requesttimestamp.", agent time=".time().").\n");
	fclose($fh);

	http_response_code(401);
	print "The request timestamp is expired (check clocks of master and deployment server are synchronized) or the nonce is not valid.\n";
	exit();
}

if (!is_dir($noncedir)) {
	@mkdir($noncedir, 0700, true);
}
// Purge the nonces older than the accepted time window
foreach ((array) @scandir($noncedir) as $noncefile) {
	if (preg_match('/^[a-f0-9]{64}$/', $noncefile) && @filemtime($noncedir.'/'.$noncefile) < (time() - 2 * $requestmaxage)) {
		@unlink($noncedir.'/'.$noncefile);
	}
}
// Mode 'x' fails if the file already exists, so a nonce can be used only once
$noncehandle = @fopen($noncedir.'/'.hash('sha256', $requestnonce), 'x');
if ($noncehandle === false) {
	fwrite($fh, date('Y-m-d H:i:s')." The nonce of the request was already used. Replayed request refused.\n");
	fclose($fh);

	http_response_code(409);
	print "The nonce of the request was already used. Replayed request refused.\n";
	exit();
}
fclose($noncehandle);

if (in_array($tmparray[0], array('deploy', 'undeploy', 'deployoption', 'deployall', 'undeployall', 'undeployoption'))) {
	if ($DEBUG) {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_deploy_undeploy.sh '.$tmparray[0].' '.$paramspaceforlog."\n");
	} else {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_deploy_undeploy.sh '.$tmparray[0].' ...'."\n");
	}
	fwrite($fh, date('Y-m-d H:i:s')." getcwd() = ".getcwd()."\n");

	// Add a security layer on the CLI scripts
	$params = array('osusername'=>$osusername, 'dbname'=>$dbname, 'dbusername'=>$dbusername);
	if (!in_array($tmparray[0], array('undeployall'))) {
		checkScriptFile($cliafter, $fh, $params);
		checkScriptFile($cliafterpaid, $fh, $params);
		checkScriptFile($cliafterdeployoption, $fh, $params);
	}

	exec('./action_deploy_undeploy.sh '.$tmparray[0].' '.$paramspace.' 2>&1', $output, $return_var);

	fwrite($fh, date('Y-m-d H:i:s').' return = '.$return_var."\n");
	fwrite($fh, date('Y-m-d H:i:s').' '.join("\n", $output));
	fclose($fh);

	$httpresponse = 550 + ($return_var < 50 ? $return_var : 0);
	if ($return_var == 0) {
		$httpresponse = 200;
	}
	http_response_code($httpresponse);

	print 'action_deploy_undeploy.sh for action '.$tmparray[0].' on '.$paramarray[2].' '.$paramarray[3].' return '.$return_var.", so remote agent returns http code ".$httpresponse."\n";

	exit();
}
if (in_array($tmparray[0], array('rename', 'suspend', 'suspendmaintenance', 'suspendredirect', 'unsuspend', 'unsuspend'))) {
	if ($DEBUG) {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_suspend_unsuspend.sh '.$tmparray[0].' '.$paramspaceforlog."\n");
	} else {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_suspend_unsuspend.sh '.$tmparray[0].' ...'."\n");
	}
	fwrite($fh, date('Y-m-d H:i:s')." getcwd() = ".getcwd()."\n");

	// Add a security layer on the CLI scripts
	//$params = array('osusername'=>$osusername, 'dbname'=>$dbname, 'dbusername'=>$dbusername);
	//checkScriptFile($cliafter, $fh, $params);
	//checkScriptFile($cliafterpaid, $fh, $params);

	exec('./action_suspend_unsuspend.sh '.$tmparray[0].' '.$paramspace.' 2>&1', $output, $return_var);

	fwrite($fh, date('Y-m-d H:i:s').' return = '.$return_var."\n");
	fwrite($fh, date('Y-m-d H:i:s').' '.join("\n", $output));
	fclose($fh);

	$httpresponse = 550 + ($return_var < 50 ? $return_var : 0);
	if ($return_var == 0) {
		$httpresponse = 200;
	}
	http_response_code($httpresponse);

	print 'action_suspend_unsuspend.sh for action '.$tmparray[0].' on '.$paramarray[2].' '.$paramarray[3].' return '.$return_var.", so remote agent returns http code ".$httpresponse."\n";

	exit();
}

if (in_array($tmparray[0], array('backup'))) {
	if ($DEBUG) {
		fwrite($fh, date('Y-m-d H:i:s').' sudo -u admin ./backup_instance.php '.$paramarray[2].'.'.$paramarray[3].' '.$backupdir." confirm\n");
	} else {
		fwrite($fh, date('Y-m-d H:i:s').' sudo -u admin ./backup_instance.php '.$paramarray[2].'.'.$paramarray[3].' '.$backupdir." confirm\n");
	}
	fwrite($fh, date('Y-m-d H:i:s')." getcwd() = ".getcwd()."\n");

	// Add a security layer on the CLI scripts
	//$params = array('osusername'=>$osusername, 'dbname'=>$dbname, 'dbusername'=>$dbusername);
	//checkScriptFile($cliafter, $fh, $params);
	//checkScriptFile($cliafterpaid, $fh, $params);

	exec('sudo -u admin ./backup_instance.php '.$paramarray[2].'.'.$paramarray[3].' '.$backupdir.' confirm --quick --forcersync --forcedump 2>&1', $output, $return_var);

	fwrite($fh, date('Y-m-d H:i:s').' return = '.$return_var."\n");
	fwrite($fh, date('Y-m-d H:i:s').' '.join("\n", $output)."\n");
	fclose($fh);

	$httpresponse = 550 + ($return_var < 50 ? $return_var : 0);
	if ($return_var == 0) {
		$httpresponse = 200;
	}
	http_response_code($httpresponse);

	print 'backup_instance.php for action '.$tmparray[0].' on '.$paramarray[2].'.'.$paramarray[3].' return '.$return_var.", so remote agent returns http code ".$httpresponse."\n";

	exit();
}

if (in_array($tmparray[0], array('test'))) {
	$httpresponse = 200;

	http_response_code($httpresponse);

	fwrite($fh, date('Y-m-d H:i:s').' action code "'.$tmparray[0].'" called. Nothing done.'."\n");

	exit();
}

if (in_array($tmparray[0], array('migrate'))) {
	if ($DEBUG) {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_migrate_instance.sh '.$tmparray[0].' '.$paramspaceforlog."\n");
	} else {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_migrate_instance.sh '.$tmparray[0].' ...'."\n");
	}
	fwrite($fh, date('Y-m-d H:i:s')." getcwd() = ".getcwd()."\n");

	// Add a security layer on the CLI scripts
	//$params = array('osusername'=>$osusername, 'dbname'=>$dbname, 'dbusername'=>$dbusername);
	//checkScriptFile($cliafter, $fh, $params);
	//checkScriptFile($cliafterpaid, $fh, $params);

	exec('./action_migrate_instance.sh '.$tmparray[0].' '.$paramspace.' 2>&1', $output, $return_var);

	fwrite($fh, date('Y-m-d H:i:s').' return = '.$return_var."\n");
	fwrite($fh, date('Y-m-d H:i:s').' '.join("\n", $output)."\n");
	fclose($fh);

	$httpresponse = 550 + ($return_var < 50 ? $return_var : 0);
	if ($return_var == 0) {
		$httpresponse = 200;
	}
	http_response_code($httpresponse);

	print 'action_migrate_instance.sh for action '.$tmparray[0].' on '.$paramarray[2].'.'.$paramarray[3].' return '.$return_var.", so remote agent returns http code ".$httpresponse."\n";

	exit();
}

if (in_array($tmparray[0], array('upgrade'))) {
	if ($DEBUG) {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_upgrade_instance.sh '.$tmparray[0].' '.$paramspaceforlog." \n");
	} else {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_upgrade_instance.sh '.$tmparray[0].' ...'."\n");
	}
	fwrite($fh, date('Y-m-d H:i:s')." getcwd() = ".getcwd()."\n");

	// Add a security layer on the CLI scripts
	//$params = array('osusername'=>$osusername, 'dbname'=>$dbname, 'dbusername'=>$dbusername);
	//checkScriptFile($cliafter, $fh, $params);
	//checkScriptFile($cliafterpaid, $fh, $params);

	exec('./action_upgrade_instance.sh '.$tmparray[0].' '.$paramspace.' 2>&1', $output, $return_var);

	fwrite($fh, date('Y-m-d H:i:s').' return = '.$return_var."\n");
	fwrite($fh, date('Y-m-d H:i:s').' '.join("\n", $output)."\n");
	fclose($fh);

	$httpresponse = 550 + ($return_var < 50 ? $return_var : 0);
	if ($return_var == 0) {
		$httpresponse = 200;
	}
	http_response_code($httpresponse);

	print 'action_upgrade_instance.sh for action '.$tmparray[0].' on '.$paramarray[2].'.'.$paramarray[3].' return '.$return_var.", so remote agent returns http code ".$httpresponse."\n";

	exit();
}

if (in_array($tmparray[0], array('deploywebsite'))) {
	if ($DEBUG) {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_website_instance.sh '.$tmparray[0].' '.$paramspaceforlog." \n");
	} else {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_website_instance.sh '.$tmparray[0].' ...'."\n");
	}
	fwrite($fh, date('Y-m-d H:i:s')." getcwd() = ".getcwd()."\n");

	// Add a security layer on the CLI scripts
	//$params = array('osusername'=>$osusername, 'dbname'=>$dbname, 'dbusername'=>$dbusername);
	//checkScriptFile($cliafter, $fh, $params);
	//checkScriptFile($cliafterpaid, $fh, $params);

	exec('./action_website_instance.sh '.$tmparray[0].' '.$paramspace.' 2>&1', $output, $return_var);

	fwrite($fh, date('Y-m-d H:i:s').' return = '.$return_var."\n");
	fwrite($fh, date('Y-m-d H:i:s').' '.join("\n", $output)."\n");
	fclose($fh);

	$httpresponse = 550 + ($return_var < 50 ? $return_var : 0);
	if ($return_var == 0) {
		$httpresponse = 200;
	}
	http_response_code($httpresponse);

	print 'action_website_instance.sh for action '.$tmparray[0].' on '.$paramarray[2].'.'.$paramarray[3].' return '.$return_var.", so remote agent returns http code ".$httpresponse."\n";

	exit();
}

if (in_array($tmparray[0], array('deploycustomurl'))) {
	if ($DEBUG) {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_customurl_instance.sh '.$tmparray[0].' '.$paramspaceforlog." \n");
	} else {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_customurl_instance.sh '.$tmparray[0].' ...'."\n");
	}
	fwrite($fh, date('Y-m-d H:i:s')." getcwd() = ".getcwd()."\n");

	// Add a security layer on the CLI scripts
	//$params = array('osusername'=>$osusername, 'dbname'=>$dbname, 'dbusername'=>$dbusername);
	//checkScriptFile($cliafter, $fh, $params);
	//checkScriptFile($cliafterpaid, $fh, $params);

	exec('./action_customurl_instance.sh '.$tmparray[0].' '.$paramspace.' 2>&1', $output, $return_var);

	fwrite($fh, date('Y-m-d H:i:s').' return = '.$return_var."\n");
	fwrite($fh, date('Y-m-d H:i:s').' '.join("\n", $output)."\n");
	fclose($fh);

	$httpresponse = 550 + ($return_var < 50 ? $return_var : 0);
	if ($return_var == 0) {
		$httpresponse = 200;
	}
	http_response_code($httpresponse);

	print 'action_customurl_instance.sh for action '.$tmparray[0].' on '.$paramarray[2].'.'.$paramarray[3].' return '.$return_var.", so remote agent returns http code ".$httpresponse."\n";

	exit();
}

if (in_array($tmparray[0], array('actionafterpaid'))) {
	if ($DEBUG) {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_after_instance.sh '.$tmparray[0].' '.$paramspaceforlog." \n");
	} else {
		fwrite($fh, date('Y-m-d H:i:s').' ./action_after_instance.sh '.$tmparray[0].' ...'."\n");
	}
	fwrite($fh, date('Y-m-d H:i:s')." getcwd() = ".getcwd()."\n");

	// Add a security layer on the CLI scripts
	$params = array('osusername'=>$osusername, 'dbname'=>$dbname, 'dbusername'=>$dbusername);
	checkScriptFile($cliafter, $fh, $params);
	checkScriptFile($cliafterpaid, $fh, $params);

	exec('./action_after_instance.sh '.$tmparray[0].' '.$paramspace.' 2>&1', $output, $return_var);

	fwrite($fh, date('Y-m-d H:i:s').' return = '.$return_var."\n");
	fwrite($fh, date('Y-m-d H:i:s').' '.join("\n", $output)."\n");
	fclose($fh);

	$httpresponse = 550 + ($return_var < 50 ? $return_var : 0);
	if ($return_var == 0) {
		$httpresponse = 200;
	}
	http_response_code($httpresponse);

	print 'action_after_instance.sh for action '.$tmparray[0].' on '.$paramarray[2].'.'.$paramarray[3].' return '.$return_var.", so remote agent returns http code ".$httpresponse."\n";

	exit();
}

fwrite($fh, date('Y-m-d H:i:s').' action code "'.$tmparray[0].'" not supported'."\n");
fclose($fh);

http_response_code(404);

print 'action code "'.$tmparray[0].'" not supported'."\n";

exit();



/**
 * Check script file
 *
 * @param 	string	$scriptfile		Name of file
 * @param	string	$fh				File handler
 * @param	array	$params			Parameters
 * @return 	int						0 if ok, line nb if there is a problem on a line
 */
function checkScriptFile($scriptfile, $fh, $params)
{
	fwrite($fh, date('Y-m-d H:i:s')." script file to scan is ".$scriptfile."\n");

	if (empty($scriptfile)) {
		fwrite($fh, date('Y-m-d H:i:s')." file is empty, we ignore it.\n");
		return 0;
	}
	if (! file_exists($scriptfile)) {
		fwrite($fh, date('Y-m-d H:i:s')." file is not found, we ignore it.\n");
		return 0;
	}

	$txt_file = file_get_contents($scriptfile); //Get the file
	$rows = preg_split('/\r\n|\r|\n/', $txt_file); //Split the file by each line TODO Split also on ";" so list of allowed is easier to manage

	$linenotvalid = 0;
	$i = 0;
	foreach ($rows as $line) {
		$i++;
		$newline = preg_replace('/;\s*$/', '', $line);

		// Check disallowed patterns
		if (preg_match('/\.\./i', $newline)) {
			$linenotvalid = $i;
			break;
		}
		// Check allowed pattern
		/*
		if (preg_match('/^#?touch __INSTANCEDIR__\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^#?rm -fr? __INSTANCEDIR__\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chmod( -R)? [-+ugoarwx]+ __INSTANCEDIR__\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chown( -R)? __OSUSERNAME__\.__OSUSERNAME__ __INSTANCEDIR__\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chown root[:\.]root __INSTANCEDIR__\/[\/a-z0-9_\.]+\.lock$/i', $newline)) {
			continue;
		}
		if (preg_match('/^__INSTANCEDIR__\/htdocs\/cloud\/init.sh __INSTANCEDIR__$/i', $newline)) {
			continue;
		}
		if (preg_match('/^#?cd __INSTANCEDIR__\/htdocs\/install; php upgrade2\.php 0\.0\.0 0\.0\.0 [a-z_,]+$/i', $newline)) {
			continue;
		}
		*/
		if (preg_match('/^#?touch \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^#?rm -fr? \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chmod( -R)? [-+ugoarwx]+ \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chown( -R)? osu[a-z0-9]+[:\.]osu[a-z0-9]+ \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chown( -R)? osu[a-z0-9]+[:\.]osu[a-z0-9]+ \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+\/\*\.lock$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chown root[:\.]root \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+\.lock$/i', $newline)) {
			continue;
		}
		if (preg_match('/^\/home\/jail\/home\/osu[a-z0-9]+\/dbn[a-z0-9]+\/htdocs\/cloud\/init.sh \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^#?cd \/home\/jail\/home\/osu[a-z0-9]+\/dbn[a-z0-9]+\/htdocs\/install\/?; php upgrade2\.php 0\.0\.0 0\.0\.0 [a-z0-9_,]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^#?cd \/home\/jail\/home\/osu[a-z0-9]+\/dbn[a-z0-9]+\/htdocs\/install\/?; php \/home\/jail\/home\/osu[a-z0-9]+\/dbn[a-z0-9]+\/htdocs\/install\/upgrade2\.php 0\.0\.0 0\.0\.0 [a-z0-9_,]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^#?php \/home\/jail\/home\/osu[a-z0-9]+\/dbn[a-z0-9]+\/htdocs\/install\/upgrade2\.php 0\.0\.0 0\.0\.0 [a-z0-9_,]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^#?cd \/home\/jail\/home\/osu[a-z0-9]+\/dbn[a-z0-9]+\/htdocs\/install\/?$/i', $newline)) {
			continue;
		}
		if (preg_match('/^#?mkdir \/home\/jail\/home\/osu[a-z0-9]+\/dbn[a-z0-9]+\/htdocs\/.well-known$/i', $newline)) {
			continue;
		}
		if (preg_match('/^#?chmod( -R)? [-+ugoarwx]+ \/home\/jail\/home\/osu[a-z0-9]+\/dbn[a-z0-9]+\/htdocs\/\.well-known$/i', $newline)) {
			continue;
		}
		if (preg_match('/^#?chown( -R)? [\w_:]+ \/home\/jail\/home\/osu[a-z0-9]+\/dbn[a-z0-9]+\/htdocs\/\.well-known$/i', $newline)) {
			continue;
		}

		// TODO enhance list of allowed patterns
		// ...

		// print $newline;

		$linenotvalid = $i;
		break;
	}

	if ($linenotvalid > 0) {
		fwrite($fh, date('Y-m-d H:i:s')." script file contains instructions line #".$linenotvalid." that does not match an allowed pattern.\n");

		// CLI file is not valid
		http_response_code(599);
		print 'The CLI file '.$scriptfile.' contains instructions line #'.$linenotvalid.' that does not match an allowed pattern.'."\n";
		exit();
	} else {
		fwrite($fh, date('Y-m-d H:i:s')." script file is valid.\n");
	}

	return 0;
}
