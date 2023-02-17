<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ssh osu123456789@myinstance.withX.mysellyoursaasdomain.com
// hostkey can be:
// - ssh-rsa (old)
// - ecdsa-sha2-nistp256
$connection = ssh2_connect('myinstance.withX.mysellyoursaasdomain.com', 22, array('hostkey' => 'ecdsa-sha2-nistp256'));

var_dump($connection);
