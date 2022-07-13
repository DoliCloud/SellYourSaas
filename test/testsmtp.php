#!/usr/bin/php
<?php

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path = __DIR__.'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}


print "Open socket\n";
$a = fsockopen('smtp.mail.me.com', 587);

var_dump($a);

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);


if ($a) {
		print "Close\n";
		fclose($a);
}
