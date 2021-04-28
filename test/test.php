#!/usr/bin/php
<?php

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
