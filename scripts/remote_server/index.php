<?php
/*
 * Server agent for SellYourSaas
 * This script is called by a tiny web server and run as root.
 */

$DEBUG = 1;

$fh = fopen('/var/log/remote_server.log', 'a+');
if (empty($fh)) {
	http_response_code(501);
	exit();
}

$allowed_hosts_array = array();
$signature_key = '';
$dnsserver = '';
$instanceserver = '';
$backupdir = '';

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
		if ($tmpline[0] == 'signature_key') {
			$signature_key = explode(",", $tmpline[1]);
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
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit;
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
$param = preg_replace('/^\//', '', $_SERVER['REQUEST_URI']);
$tmparray = explode('?', $param, 2);

$contentsigned = '';
$paramspace='';
$paramarray=array();
if (! empty($tmparray[1])) {
	// Remove last param that is the signature to get the message signed
	$contentsigned = preg_replace('/&[a-z0-9]+$/i', '', urldecode($tmparray[1]));
	// Generate array of parameters
	$paramarray = explode('&', urldecode($tmparray[1]));
	foreach ($paramarray as $val) {
		$paramspace.=($val != '' ? $val : '-').' ';
	}
}

// Set variables
$tmpparam = preg_split('/\s/', $paramspace);
$osusername = $tmpparam[0];
$dbname = $tmpparam[4];
$dbusername = $tmpparam[6];
$cliafter = $tmpparam[18];
$cliafterpaid = $tmpparam[46];

// Recalculate the signature with message received
$recalculatedsignature = md5($contentsigned.$signature_key);
// Extract the signature received
$signature = empty($tmpparam[47]) ? '' : $tmpparam[47];


/*
 * Actions
 */

$output='';
$return_var=0;

if ($DEBUG) fwrite($fh, "\n".date('Y-m-d H:i:s').' >>>>>>>>>>>>>>>>>>>> Call for action '.$tmparray[0].' by '.$_SERVER['REMOTE_ADDR'].' URI='.$_SERVER['REQUEST_URI']."\n");
else fwrite($fh, "\n".date('Y-m-d H:i:s').' >>>>>>>>>>>>>>>>>>>> Call for action '.$tmparray[0]." by ".$_SERVER['REMOTE_ADDR']."\n");

fwrite($fh, date('Y-m-d H:i:s').' dnsserver='.$dnsserver.", instanceserver=".$instanceserver.", allowed_hosts=".$allowed_hosts."\n");
fwrite($fh, date('Y-m-d H:i:s').' signature='.$signature.", recalculatedsignature=".$recalculatedsignature."\n");

// Compare signature and recalculatedsignature
if ($signature != $recalculatedsignature) {
	fwrite($fh, date('Y-m-d H:i:s')." The provided signature does not match the signature recalculated from parameters and the signature key.\n");

	http_response_code(598);
	print 'The provided signature does not match the signature recalculated from parameters and the signature key.'."\n";
	exit();
}

if (in_array($tmparray[0], array('deploy', 'undeploy', 'deployoption', 'deployall', 'undeployall'))) {
	if ($DEBUG) fwrite($fh, date('Y-m-d H:i:s').' ./action_deploy_undeploy.sh '.$tmparray[0].' '.$paramspace."\n");
	else fwrite($fh, date('Y-m-d H:i:s').' ./action_deploy_undeploy.sh '.$tmparray[0].' ...'."\n");
	fwrite($fh, date('Y-m-d H:i:s')." getcwd() = ".getcwd()."\n");

	// Add a security layer on the CLI scripts
	$params = array('osusername'=>$osusername, 'dbname'=>$dbname, 'dbusername'=>$dbusername);
	if (!in_array($tmparray[0], array('undeployall'))) {
		checkScriptFile($cliafter, $fh, $params);
		checkScriptFile($cliafterpaid, $fh, $params);
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
if (in_array($tmparray[0], array('rename', 'suspend', 'suspendmaintenance', 'unsuspend', 'unsuspend'))) {
	if ($DEBUG) fwrite($fh, date('Y-m-d H:i:s').' ./action_suspend_unsuspend.sh '.$tmparray[0].' '.$paramspace."\n");
	else fwrite($fh, date('Y-m-d H:i:s').' ./action_suspend_unsuspend.sh '.$tmparray[0].' ...'."\n");
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
	if ($DEBUG) fwrite($fh, date('Y-m-d H:i:s').' sudo -u admin ./backup_instance.php '.$paramarray[2].'.'.$paramarray[3].' '.$backupdir." confirm\n");
	else fwrite($fh, date('Y-m-d H:i:s').' sudo -u admin ./backup_instance.php '.$paramarray[2].'.'.$paramarray[3].' '.$backupdir." confirm\n");
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
	if ($DEBUG) fwrite($fh, date('Y-m-d H:i:s').' ./action_migrate_instance.sh '.$tmparray[0].' '.$paramspace."\n");
	else fwrite($fh, date('Y-m-d H:i:s').' ./action_migrate_instance.sh '.$tmparray[0].' ...'."\n");
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
	if ($DEBUG) fwrite($fh, date('Y-m-d H:i:s').' ./action_upgrade_instance.sh '.$tmparray[0].' '.$paramspace." \n");
	else fwrite($fh, date('Y-m-d H:i:s').' ./action_upgrade_instance.sh '.$tmparray[0].' '.$paramspace." \n");
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
	if ($DEBUG) fwrite($fh, date('Y-m-d H:i:s').' ./action_website_instance.sh '.$tmparray[0].' '.$paramspace." \n");
	else fwrite($fh, date('Y-m-d H:i:s').' ./action_website_instance.sh '.$tmparray[0].' '.$paramspace." \n");
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

if (in_array($tmparray[0], array('actionafterpaid'))) {
	if ($DEBUG) fwrite($fh, date('Y-m-d H:i:s').' ./action_after_instance.sh '.$tmparray[0].' '.$paramspace." \n");
	else fwrite($fh, date('Y-m-d H:i:s').' ./action_after_instance.sh '.$tmparray[0].' '.$paramspace." \n");
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
	$rows = preg_split('/\r\n|\r|\n/', $txt_file); //Split the file by each line

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
		if (preg_match('/^touch __INSTANCEDIR__\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^rm -fr? __INSTANCEDIR__\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chmod( -R)? [-+ugoarwx]+ __INSTANCEDIR__\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chown( -R)? __OSUSERNAME__.__OSUSERNAME__ __INSTANCEDIR__/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chown root.root __INSTANCEDIR__/[\/a-z0-9_\.]+\.lock$/i', $newline)) {
			continue;
		}
		if (preg_match('/^__INSTANCEDIR__\/htdocs\/cloud\/init.sh __INSTANCEDIR__$/i', $newline)) {
			continue;
		}
		// Check more patterns
		if (preg_match('/^touch \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^rm -fr? \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chmod( -R)? [-+ugoarwx]+ \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chown( -R)? osu[a-z0-9]+\.osu[a-z0-9]+ \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		if (preg_match('/^chown root.root \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+\.lock$/i', $newline)) {
			continue;
		}
		if (preg_match('/^\/home\/jail\/home\/osu[a-z0-9]+\/dbn[a-z0-9]+\/htdocs\/cloud\/init.sh \/home\/jail\/home\/osu[a-z0-9]+\/[\/a-z0-9_\.]+$/i', $newline)) {
			continue;
		}
		// TODO enhance list of allowed patterns
		// ...

		$linenotvalid = $i;
		break;
	}

	if ($linenotvalid > 0) {
		fwrite($fh, date('Y-m-d H:i:s')." script file contains instructions line ".$linenotvalid." that does not match an allowed pattern.\n");

		// CLI file is not valid
		http_response_code(599);
		print 'The CLI file '.$scriptfile.' contains instructions line '.$linenotvalid.' that does not match an allowed pattern.'."\n";
		exit();
	} else {
		fwrite($fh, date('Y-m-d H:i:s')." script file is valid.\n");
	}

	return 0;
}
