<?php


// Do actions on object $object


// Avoid errors onto ssh2 and stats function warning
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

$langs->load("errors");

if ($action == 'addauthorizedkey')
{
	// SFTP connect
	if (! function_exists("ssh2_connect")) {
		dol_print_error('','ssh2_connect function does not exists'); exit;
	}

	$instance = 'xxxx';
	$type_db = $conf->db->type;

	$instance = $object->ref_customer;
	$hostname_db = $object->array_options['options_hostname_db'];
	$username_db = $object->array_options['options_username_db'];
	$password_db = $object->array_options['options_password_db'];
	$database_db = $object->array_options['options_database_db'];
	$port_db     = $object->array_options['options_port_db'];
	$username_web = $object->array_options['options_username_os'];
	$password_web = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;

	$connection = ssh2_connect($server, 22);
	if ($connection)
	{
		if (! @ssh2_auth_password($connection, $username_web, $password_web))
		{
			dol_syslog("Could not authenticate with username ".$username_web." and password ".preg_replace('/./', '*', $password_web), LOG_WARNING);
			setEventMessages("Could not authenticate with username ".$username_web." and password ".preg_replace('/./', '*', $password_web), null, 'warnings');
		}
		else
		{
			$sftp = ssh2_sftp($connection);

			// Update ssl certificate
			// Dir .ssh must have rwx------ permissions
			// File authorized_keys must have rw------- permissions
			$dircreated=0;
			$result=ssh2_sftp_mkdir($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh');
			if ($result) {
				$dircreated=1;
			}	// Created
			else {
				$dircreated=0;
			}	// Creation fails or already exists

			// Check if authorized_key exists
			//$filecert="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh/authorized_keys';
			$filecert="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh/authorized_keys';  // With PHP 5.6.27+
			$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh/authorized_keys');

			// Create authorized_keys file
			if (empty($fstat['atime']))		// Failed to connect or file does not exists
			{
				$stream = fopen($filecert, 'w');
				if ($stream === false)
				{
					setEventMessage($langs->transnoentitiesnoconv("ErrorConnectOkButFailedToCreateFile"),'errors');
				}
				else
				{
					// Add public keys
					$publickeystodeploy = $conf->global->SELLYOURSAAS_PUBLIC_KEY;
					fwrite($stream, $publickeystodeploy);
					fclose($stream);
					$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh/authorized_keys');
					setEventMessage($langs->transnoentitiesnoconv("FileCreated"),'mesgs');
				}
			}
			else setEventMessage($langs->transnoentitiesnoconv("ErrorFileAlreadyExists"),'warnings');

			$object->fileauthorizedkey=(empty($fstat['atime'])?'':$fstat['atime']);
			$object->array_options['options_fileauthorizekey']=(empty($fstat['atime'])?'':$fstat['atime']);

			if (! empty($fstat['atime']))
			{
				$result = $object->update($user);
			}
		}
	}
	else setEventMessage($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server),'errors');
}

if ($action == 'addinstalllock')
{
	// SFTP connect
	if (! function_exists("ssh2_connect")) {
		dol_print_error('','ssh2_connect function does not exists'); exit;
	}

	$instance = 'xxxx';
	$type_db = $conf->db->type;

	$instance = $object->ref_customer;
	$hostname_db = $object->array_options['options_hostname_db'];
	$username_db = $object->array_options['options_username_db'];
	$password_db = $object->array_options['options_password_db'];
	$database_db = $object->array_options['options_database_db'];
	$port_db     = $object->array_options['options_port_db'];
	$username_web = $object->array_options['options_username_os'];
	$password_web = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;

	$connection = ssh2_connect($server, 22);
	if ($connection)
	{
		//print $instance." ".$username_web." ".$password_web."<br>\n";
		if (! @ssh2_auth_password($connection, $username_web, $password_web))
		{
			dol_syslog("Could not authenticate with username ".$username_web." . and password ".preg_replace('/./', '*', $password_web), LOG_ERR);
		}
		else
		{
			$sftp = ssh2_sftp($connection);

			// Check if install.lock exists
			$dir=preg_replace('/_([a-zA-Z0-9]+)$/','',$database_db);
			//$fileinstalllock="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/'.$dir.'/documents/install.lock';
			$fileinstalllock="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/'.$dir.'/documents/install.lock';
			$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/'.$dir.'/documents/install.lock');
			if (empty($fstat['atime']))
			{
				$stream = fopen($fileinstalllock, 'w');
				//var_dump($stream);exit;
				fwrite($stream,"// File to protect from install/upgrade.\n");
				fclose($stream);
				$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/'.$dir.'/documents/install.lock');
				setEventMessage($langs->transnoentitiesnoconv("FileCreated"),'mesgs');
			}
			else setEventMessage($langs->transnoentitiesnoconv("ErrorFileAlreadyExists"),'warnings');

			$object->filelock=(empty($fstat['atime'])?'':$fstat['atime']);
			$object->array_options['options_filelock']=(empty($fstat['atime'])?'':$fstat['atime']);

			if (! empty($fstat['atime']))
			{
				$result = $object->update($user);
			}
		}
	}
	else setEventMessage($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server),'errors');
}

if ($action == 'delauthorizedkey')
{
	// SFTP connect
	if (! function_exists("ssh2_connect")) {
		dol_print_error('','ssh2_connect function does not exists'); exit;
	}

	$instance = 'xxxx';
	$type_db = $conf->db->type;

	$instance = $object->ref_customer;
	$hostname_db = $object->array_options['options_hostname_db'];
	$username_db = $object->array_options['options_username_db'];
	$password_db = $object->array_options['options_password_db'];
	$database_db = $object->array_options['options_database_db'];
	$port_db     = $object->array_options['options_port_db'];
	$username_web = $object->array_options['options_username_os'];
	$password_web = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;
	$connection = ssh2_connect($server, 22);
	if ($connection)
	{
		//print $instance." ".$username_web." ".$password_web."<br>\n";
		if (! @ssh2_auth_password($connection, $username_web, $password_web))
		{
			dol_syslog("Could not authenticate with username ".$username_web." . and password ".preg_replace('/./', '*', $password_web), LOG_ERR);
		}
		else
		{
			$sftp = ssh2_sftp($connection);

			// Check if install.lock exists
			$filetodelete=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh/authorized_keys';
			$result=ssh2_sftp_unlink($sftp, $filetodelete);

			if ($result) setEventMessage($langs->transnoentitiesnoconv("FileDeleted"),'mesgs');
			else setEventMessage($langs->transnoentitiesnoconv("DeleteFails"),'warnings');

			$object->fileauthorizedkey='';
			$object->array_options['options_fileauthorizekey']='';

			if ($result)
			{
				$result = $object->update($user);
			}
		}
	}
	else setEventMessage($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server),'errors');
}
if ($action == 'delinstalllock')
{
	// SFTP connect
	if (! function_exists("ssh2_connect")) {
		dol_print_error('','ssh2_connect function does not exists'); exit;
	}

	$instance = 'xxxx';
	$type_db = $conf->db->type;

	$instance = $object->ref_customer;
	$hostname_db = $object->array_options['options_hostname_db'];
	$username_db = $object->array_options['options_username_db'];
	$password_db = $object->array_options['options_password_db'];
	$database_db = $object->array_options['options_database_db'];
	$port_db     = $object->array_options['options_port_db'];
	$username_web = $object->array_options['options_username_os'];
	$password_web = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;

	$connection = ssh2_connect($server, 22);
	if ($connection)
	{
		//print $object->instance." ".$username_web." ".$password_web."<br>\n";
		if (! @ssh2_auth_password($connection, $username_web, $password_web))
		{
			dol_syslog("Could not authenticate with username ".$username_web." . and password ".preg_replace('/./', '*', $password_web), LOG_ERR);
		}
		else
		{
			$sftp = ssh2_sftp($connection);

			// Check if install.lock exists
			$dir=preg_replace('/_([a-zA-Z0-9]+)$/','',$database_db);
			$filetodelete=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/'.$dir.'/documents/install.lock';
			$result=ssh2_sftp_unlink($sftp, $filetodelete);

			if ($result) setEventMessage($langs->transnoentitiesnoconv("FileDeleted"),'mesgs');
			else setEventMessage($langs->transnoentitiesnoconv("DeleteFails"),'warnings');

			$object->filelock='';
			$object->array_options['options_filelock']='';

			if ($result)
			{
				$result = $object->update($user);
			}
		}
	}
	else setEventMessage($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server),'errors');
}


// We make a refresh of status of install.lock + authorized key, this does not update the qty (this is done in makeRenewal.
if ($action == 'refresh' || $action == 'setdate')
{
    dol_include_once("/sellyoursaas/backoffice/lib/refresh.lib.php");		// do not use dol_buildpath to keep global of var into refresh.lib.php working

	$object->oldcopy=dol_clone($object, 1);

	// Setup files refresh (does not update lastcheck field)
	$ret=dolicloud_files_refresh($conf,$db,$object,$errors);

	// Database refresh (also update lastcheck field)
	$ret=dolicloud_database_refresh($conf,$db,$object,$errors);

	$action = 'view';
}
