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


$domainemails = array('yhaoo.com','yahoo.com','dolibarr.fr');

foreach ($domainemails as $domainemail) {
	print "idn_to_ascii(".$domainemail.") = ";
	print idn_to_ascii($domainemail)."<br>\n";
	print "checkdnsrr(idn_to_ascii(".$domainemail."), 'MX') = ";
	print checkdnsrr(idn_to_ascii($domainemail), 'MX')."<br>\n";
	if (checkdnsrr(idn_to_ascii($domainemail), 'MX')) {
		$mxhosts=array();
		$weight=array();
		getmxrr(idn_to_ascii($domainemail), $mxhosts, $weight);
		var_dump($mxhosts);
		if (count($mxhosts) == 1 && empty($mxhosts[0])) print "KO<br>\n";
		else print "OK";
	}
	print "\n";
}
