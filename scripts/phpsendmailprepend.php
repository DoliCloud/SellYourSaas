<?php
/**
 * This script is a prepend file so env is set for phpsendmail.php
 *
 * It allows also to force use of antivirus whatever is setup in application.
 *
 * Modify your php.ini file to add:
 * auto_prepend_file = /usr/local/bin/phpsendmailprepend.php
 */

// if (! empty($_SERVER) && (preg_match('/phpsendmail/', @$_SERVER['SCRIPT_FILENAME']) || preg_match('/phpsendmail/', @$_SERVER['SCRIPT_NAME'])) )
$tmpactionprepend = @$_POST['action'];

/* TODO Enable this by default
$listofwrappers = stream_get_wrappers();
$arrayofstreamtodisable = array('compress.zlib', 'ftps', 'glob', 'data', 'expect', 'ftp', 'ogg', 'phar', 'rar', 'zip', 'zlib');
foreach ($arrayofstreamtodisable as $streamtodisable) {
	if (!empty($listofwrappers) && in_array($streamtodisable, $listofwrappers)) {
		stream_wrapper_unregister($streamtodisable);
	}
}
*/
//$tmp = stream_get_wrappers();
//var_dump($tmp);

if (preg_match('/^send_/', $tmpactionprepend) || in_array($tmpactionprepend, array('send', 'sendallconfirmed', 'relance'))) {
	$tmpfile='/tmp/phpsendmailprepend-'.posix_getuid().'-'.getmypid().'.tmp';
	@unlink($tmpfile);
	file_put_contents($tmpfile, var_export($_SERVER, true));
	chmod($tmpfile, 0660);
}

// environment variables that should be available in child processes
$envVars = array(
	'HTTP_HOST',
	'SCRIPT_NAME',
	'SERVER_NAME',
	'DOCUMENT_ROOT',
	'REMOTE_ADDR',
	'REQUEST_URI'
);
// sanitizing environment variables for Bash ShellShock mitigation
// (CVE-2014-6271, CVE-2014-7169, CVE-2014-7186, CVE-2014-7187, CVE-2014-6277)

$sanitizeChars = str_split('(){};');
foreach ($envVars as $key) {
	$value = str_replace($sanitizeChars, '', @$_SERVER[$key]);
	putenv("$key=$value");
}

define('MAIN_ANTIVIRUS_COMMAND', '/usr/bin/clamdscan');
define('MAIN_ANTIVIRUS_PARAM', '--fdpass');
