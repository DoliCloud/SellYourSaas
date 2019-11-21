<?php
/* Copyright (C) 2007-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       sellyoursaas/class/dolicloud_customers.class.php
 *  \ingroup    sellyoursaas
 *  \brief      This file is an example for a CRUD class file (Create/Read/Update/Delete)
 *				Initialy built by build_class_from_table on 2012-06-26 21:03
 */

// Put here all includes required by your class file
require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");


/**
 *	Class of DoliCloud customers
 */
class Dolicloud_customers extends CommonObject
{
	var $db;							//!< To store db handler
	var $db2;
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	var $element='app_instance';			//!< Id that identify managed objects
	var $table_element='app_instance';	//!< Name of table without prefix where object is stored
	var $picto='contract';

    var $id;
    var $idold;

	var $instance;
	var $organization;
	var $email;
	var $locale;
	var $plan;
	var $date_registration='';
	var $date_endfreeperiod='';
	var $status;
	var $partner;
	var $source;
	var $total_invoiced;
	var $total_payed;
	var $tms='';
	var $hostname_web;
	var $username_web;
	var $password_web;
	var $hostname_db;
	var $database_db;
	var $port_db;
	var $username_db;
	var $password_db;
	var $date_lastcheck='';
	var $nbofusers;
	var $lastlogin='';
	var $lastpass='';
	var $date_lastlogin='';
	var $modulesenabled;
	var $version;

	var $fs_path;

	var $firstname;
	var $lastname;
	var $address;
	var $zip;
	var $town;
	var $country_id;
	var $state_id;
	var $vat_number;
	var $phone;

	var $subscription_status;
	var $paymentstatus;
	var $paymentmethod;
	var $paymentinfo;
	var $paymentnextbillingdate;
	var $paymentfrequency;	// 'monthly' or 'yearly'

	var $fileauthorizedkey;
	var $filelock;
	var $date_lastrsync='';		// date last successfull backup rsync + mysqldump
	var $backup_status;

	static $listOfStatus=array('TRIAL'=>'TRIAL','TRIAL_EXPIRED'=>'TRIAL_EXPIRED','ACTIVE'=>'ACTIVE','ACTIVE_PAYMENT_ERROR'=>'ACTIVE_PAYMENT_ERROR','SUSPENDED'=>'SUSPENDED','CLOSED_QUEUED'=>'CLOSE_QUEUED','UNDEPLOYED'=>'UNDEPLOYED');
	static $listOfStatusShort=array('TRIAL'=>'TRIAL','TRIAL_EXPIRED'=>'TRIAL_EXP.','ACTIVE'=>'ACT.','ACTIVE_PAYMENT_ERROR'=>'ACT_PAY_ERR.','SUSPENDED'=>'SUSPENDED','CLOSED_QUEUED'=>'CLOSE_Q.','UNDEPLOYED'=>'UNDEP.');

	static $listOfStatusNewShort=array('TRIALING'=>'TRIALING','TRIAL_EXPIRED'=>'TRIAL_EXPIRED','ACTIVE'=>'ACTIVE','ACTIVE_PAY_ERR'=>'ACTIVE_PAY_ERR','SUSPENDED'=>'SUSPENDED','UNDEPLOYED'=>'UNDEPLOYED','CLOSURE_REQUESTED'=>'CLOSURE_REQUESTED','CLOSED'=>'CLOSED');

    /**
     *  Constructor
     *
     *  @param	DoliDb		$db      Database handler
     *  @param	DoliDb		$db2     Database handler
     */
    function __construct($db, $db2)
    {
        $this->db = $db;
        $this->db2 = $db2;
        return 1;
    }


    /**
     *  Create object into database
     *
     *  @param	User	$user        User that create
     *  @param  int		$notrigger   0=launch triggers after, 1=disable triggers
     *  @return int      		   	 <0 if KO, Id of created object if OK
     */
    function create_old($user, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
		if (isset($this->instance)) $this->instance=trim($this->instance);
		if (isset($this->organization)) $this->organization=trim($this->organization);
		if (isset($this->email)) $this->email=trim($this->email);
		if (isset($this->plan)) $this->plan=trim($this->plan);
		if (isset($this->status)) $this->status=trim($this->status);
		if (isset($this->partner)) $this->partner=trim($this->partner);
		if (isset($this->source)) $this->source=trim($this->source);
		if (isset($this->total_invoiced)) $this->total_invoiced=trim($this->total_invoiced);
		if (isset($this->total_payed)) $this->total_payed=trim($this->total_payed);
		if (isset($this->hostname_web)) $this->hostname_web=trim($this->hostname_web);
		if (isset($this->username_web)) $this->username_web=trim($this->username_web);
		if (isset($this->password_web)) $this->password_web=trim($this->password_web);
		if (isset($this->hostname_db)) $this->hostname_db=trim($this->hostname_db);
		if (isset($this->database_db)) $this->database_db=trim($this->database_db);
		if (isset($this->port_db)) $this->port_db=trim($this->port_db);
		if (isset($this->username_db)) $this->username_db=trim($this->username_db);
		if (isset($this->password_db)) $this->password_db=trim($this->password_db);
		if (isset($this->nbofusers)) $this->nbofusers=trim($this->nbofusers);
		if (isset($this->modulesenabled)) $this->modulesenabled=trim($this->modulesenabled);
		if (isset($this->version)) $this->version=trim($this->version);
		if (isset($this->vat_number)) $this->vat_number=trim($this->vat_number);


		// Check parameters
		// Put here code to add control on parameters values

        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."dolicloud_customers(";

		$sql.= "instance,";
		$sql.= "organization,";
		$sql.= "email,";
		$sql.= "plan,";
		//$sql.= "date_registration,";
		$sql.= "date_endfreeperiod,";
		$sql.= "status,";
		$sql.= "partner,";
		$sql.= "source,";
		$sql.= "total_invoiced,";
		$sql.= "total_payed,";
		$sql.= "hostname_web,";
		$sql.= "username_web,";
		$sql.= "password_web,";
		$sql.= "hostname_db,";
		$sql.= "database_db,";
		$sql.= "port_db,";
		$sql.= "username_db,";
		$sql.= "password_db,";
		$sql.= "lastcheck,";
		$sql.= "nbofusers,";
		$sql.= "lastlogin,";
		$sql.= "lastpass,";
		$sql.= "date_lastlogin,";
		$sql.= "modulesenabled,";
		$sql.= "firstname,";
		$sql.= "lastname,";
		$sql.= "address,";
		$sql.= "zip,";
		$sql.= "town,";
		//$sql.= "country_id,";
		$sql.= "state_id,";
		$sql.= "vat_number,";
		$sql.= "phone,";
		$sql.= "fileauthorizedkey,";
		$sql.= "filelock,";
		$sql.= "version,";
		$sql.= "lastrsync,";
		$sql.= "backup_status";
        $sql.= ") VALUES (";

		$sql.= " ".(! isset($this->instance)?'NULL':"'".$this->db->escape($this->instance)."'").",";
		$sql.= " ".(! isset($this->organization)?'NULL':"'".$this->db->escape($this->organization)."'").",";
		$sql.= " ".(! isset($this->email)?'NULL':"'".$this->db->escape($this->email)."'").",";
		$sql.= " ".(! isset($this->plan)?'NULL':"'".$this->db->escape($this->plan)."'").",";
		//$sql.= " ".(! isset($this->date_registration) || dol_strlen($this->date_registration)==0?'NULL':$this->db->idate($this->date_registration)).",";
		$sql.= " ".(! isset($this->date_endfreeperiod) || dol_strlen($this->date_endfreeperiod)==0?'NULL':"'".$this->db->idate($this->date_endfreeperiod)."'").",";
		$sql.= " ".(! isset($this->status)?'NULL':"'".$this->status."'").",";
		$sql.= " ".(! isset($this->partner)?'NULL':"'".$this->db->escape($this->partner)."'").",";
		$sql.= " ".(! isset($this->source)?'NULL':"'".$this->db->escape($this->source)."'").",";
		$sql.= " ".(! isset($this->total_invoiced)?'NULL':"'".$this->total_invoiced."'").",";
		$sql.= " ".(! isset($this->total_payed)?'NULL':"'".$this->total_payed."'").",";
		$sql.= " ".(! isset($this->hostname_web)?'NULL':"'".$this->db->escape($this->hostname_web)."'").",";
		$sql.= " ".(! isset($this->username_web)?'NULL':"'".$this->db->escape($this->username_web)."'").",";
		$sql.= " ".(! isset($this->password_web)?'NULL':"'".$this->db->escape($this->password_web)."'").",";
		$sql.= " ".(! isset($this->hostname_db)?'NULL':"'".$this->db->escape($this->hostname_db)."'").",";
		$sql.= " ".(! isset($this->database_db)?'NULL':"'".$this->db->escape($this->database_db)."'").",";
		$sql.= " ".(! isset($this->port_db)?'NULL':"'".$this->port_db."'").",";
		$sql.= " ".(! isset($this->username_db)?'NULL':"'".$this->db->escape($this->username_db)."'").",";
		$sql.= " ".(! isset($this->password_db)?'NULL':"'".$this->db->escape($this->password_db)."'").",";
		$sql.= " ".(! isset($this->date_lastcheck) || dol_strlen($this->date_lastcheck)==0?'NULL':"'".$this->db->idate($this->date_lastcheck)."'").",";
		$sql.= " ".(! isset($this->nbofusers)?'NULL':"'".$this->nbofusers."'").",";
		$sql.= " ".(! isset($this->lastlogin) || dol_strlen($this->lastlogin)==0?'NULL':"'".$this->db->escape($this->lastlogin)."'").",";
		$sql.= " ".(! isset($this->lastpass) || dol_strlen($this->lastpass)==0?'NULL':"'".$this->db->escape($this->lastpass)."'").",";
		$sql.= " ".(! isset($this->date_lastlogin) || dol_strlen($this->date_lastlogin)==0?'NULL':"'".$this->db->idate($this->date_lastlogin)."'").",";
		$sql.= " ".(! isset($this->modulesenabled)?'NULL':"'".$this->db->escape($this->modulesenabled)."'").",";

		$sql.= " ".(! isset($this->firstname)?'NULL':"'".$this->db->escape($this->firstname)."'").",";
		$sql.= " ".(! isset($this->lastname)?'NULL':"'".$this->db->escape($this->lastname)."'").",";
		$sql.= " ".(! isset($this->address)?'NULL':"'".$this->db->escape($this->address)."'").",";
		$sql.= " ".(! isset($this->zip)?'NULL':"'".$this->db->escape($this->zip)."'").",";
		$sql.= " ".(! isset($this->town)?'NULL':"'".$this->db->escape($this->town)."'").",";
		//$sql.= " ".(! isset($this->country_id)?'NULL':"'".$this->db->escape($this->country_id)."'").",";
		$sql.= " ".(! isset($this->state_id)?'NULL':"'".$this->db->escape($this->state_id)."'").",";
		$sql.= " ".(! isset($this->vat_number)?'NULL':"'".$this->db->escape($this->vat_number)."'").",";
		$sql.= " ".(! isset($this->phone)?'NULL':"'".$this->db->escape($this->phone)."'").",";

		$sql.= " ".(! isset($this->fileauthorizedkey) || dol_strlen($this->fileauthorizedkey)==0?'NULL':"'".$this->db->idate($this->fileauthorizedkey)."'").",";
		$sql.= " ".(! isset($this->filelock) || dol_strlen($this->filelock)==0?'NULL':$this->db->idate($this->filelock)).",";

		$sql.= " ".(! isset($this->version)?'NULL':"'".$this->db->escape($this->version)."'").",";
		$sql.= " ".(! isset($this->date_lastrsync) || dol_strlen($this->date_lastrsync)==0?'NULL':"'".$this->db->idate($this->date_lastrsync)."'").",";

		$sql.= " ".(! isset($this->backup_status) || dol_strlen($this->backup_status)==0?'NULL':"'".$this->backup_status."'");

		$sql.= ")";

		$this->db->begin();

	   	dol_syslog(get_class($this)."::create_old sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
        {
            $this->idold = $this->db->last_insert_id(MAIN_DB_PREFIX."dolicloud_customers");

			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_CREATE',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
			}
        }

        // Commit or rollback
        if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::create_old ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
            return $this->idold;
		}
    }


    /**
     *  Load object in memory from database
     *
     *  @param	int		$id    			Id
     *  @param	string	$ref   			Ref
     *  @param	string	$organization 	Organization
     *  @return int         			<0 if KO, 0=Not found, Number of line found if OK
     */
    function fetch_old($id,$ref='',$organization='')
    {
    	global $langs;

    	if (empty($id) && empty($ref) && empty($organization)) dol_print_error('','Bad parameters for fetch');

        $sql = "SELECT";
		$sql.= " t.rowid,";

		$sql.= " t.lastcheck as date_lastcheck,";

		$sql.= " t.lastlogin,";
		$sql.= " t.lastpass,";
		$sql.= " t.date_lastlogin,";
		$sql.= " t.modulesenabled,";

		$sql.= " t.fileauthorizedkey,";
		$sql.= " t.filelock,";
		$sql.= " t.lastrsync,";
		$sql.= " t.backup_status,";
		$sql.= " t.version";

        $sql.= " FROM ".MAIN_DB_PREFIX."dolicloud_customers as t";
        if ($ref) $sql.= " WHERE t.instance = '".$this->db->escape($ref)."'";
        elseif ($organization) $sql.= " WHERE t.organization = '".$this->db->escape($organization)."'";
        else $sql.= " WHERE t.rowid = ".$id;

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
        	$numfound=$this->db->num_rows($resql);

            if ($numfound)
            {
                $obj = $this->db->fetch_object($resql);	// Take first onde

                $this->idold    = $obj->rowid;

				$this->date_lastcheck = $this->db->jdate($obj->date_lastcheck);

				$this->lastlogin = $obj->lastlogin;
				$this->lastpass = $obj->lastpass;
				$this->date_lastlogin = $this->db->jdate($obj->date_lastlogin);
				$this->modulesenabled = $obj->modulesenabled;

                $this->fileauthorizedkey = $this->db->jdate($obj->fileauthorizedkey);
                $this->filelock = $this->db->jdate($obj->filelock);

                $this->date_lastrsync = $this->db->jdate($obj->lastrsync);
                $this->backup_status = $obj->backup_status;
                $this->version = $obj->version;

                /*include_once(DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php');
                if ($this->country_id > 0)
                {
                	$tmp=getCountry($this->country_id,'all');
                	$this->country_code=$tmp['code']; $this->country=$tmp['label'];
                }
                if ($this->state_id > 0)
                {
                	$tmp=getState($this->state_id,'all');
                	$this->state_code=$tmp['code']; $this->state=$tmp['label'];
                }
				*/
                $ret=$numfound;
            }
            else $ret=0;

            $this->db->free($resql);

            return $ret;
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     *  Load object in memory from database
     *
     *  @param	int		$id    			Id
     *  @param	string	$ref   			Ref of instance (get last recent one when search is done on ref)
     *  @param	string	$organization 	Organization (get last recent one when search is done on ref)
     *  @param	string	$email			Email
     *  @return int         			<0 if KO, 0=Not found, Number of line found if OK
     */
    function fetch($id, $ref='', $organization='', $email='')
    {
    	global $langs;

    	if (empty($id) && empty($ref) && empty($organization) && empty($email)) dol_print_error('','Bad parameters for fetch');

    	// Add on.dolicloud.com to have a complete instance id
    	if (! empty($ref) && ! preg_match('/\.on\.dolicloud\.com$/',$ref)) $ref=$ref.'.on.dolicloud.com';

		$sql = "SELECT";
		$sql.= " i.id,";

		//$sql.= " i.version,";
		$sql.= " i.app_package_id,";
		$sql.= " i.created_date as date_registration,";
		$sql.= " i.customer_id,";
		$sql.= " i.db_name as database_db,";
		$sql.= " i.db_password as password_db,";
		$sql.= " i.db_port,";
		$sql.= " i.db_server as hostname_db,";
		$sql.= " i.db_username as username_db,";
		$sql.= " i.default_password,";
		$sql.= " i.deployed_date,";
		$sql.= " i.domain_id,";
		$sql.= " i.fs_path,";
		$sql.= " i.install_time,";
		$sql.= " i.ip_address as hostname_web,";
		$sql.= " i.last_login as date_lastlogin,";
		$sql.= " i.last_updated,";
		$sql.= " i.name as instance,";
		$sql.= " i.os_password as password_web,";
		$sql.= " i.os_username as username_web,";
		$sql.= " i.rm_install_url,";
		$sql.= " i.rm_web_app_name,";
		$sql.= " i.status as instance_status,";
		$sql.= " i.undeployed_date,";
		$sql.= " i.access_enabled,";
		$sql.= " i.default_username,";
		$sql.= " i.ssh_port,";

		$sql.= " p.id as packageid,";
		$sql.= " p.name as package,";

		$sql.= " im.value as nbofusers,";
		$sql.= " im.last_updated as date_lastcheck,";

		$sql.= " pao.amount as price_user,";
		$sql.= " pao.min_threshold as min_threshold,";

		$sql.= " pl.amount as price_instance_plan,";
		$sql.= " pl.meter_id as plan_meter_id,";
		$sql.= " pl.name as plan,";
		$sql.= " pl.interval_unit as interval_unit,";

		$sql.= " c.id as customer_id,";
		$sql.= " c.org_name as organization,";
		$sql.= " c.status as status,";
		$sql.= " c.past_due_start,";
		$sql.= " c.suspension_date,";
		$sql.= " c.tel as phone,";
		$sql.= " c.tax_identification_number as vat_number,";
		$sql.= " c.manual_collection,";

		$sql.= " s.amount as price_instance,";	// Real active amount per instance
		$sql.= " s.payment_status,";
		$sql.= " s.trial_end,";
		$sql.= " s.current_period_start,";
		$sql.= " s.current_period_end,";

		$sql.= " CONCAT(a.address_line1,'\n',a.address_line2) as address,";
		$sql.= " a.city as town,";
		$sql.= " a.zip as zip,";
		$sql.= " a.country as country_code,";

		$sql.= " per.username as email,";
		$sql.= " per.first_name as firstname,";
		$sql.= " per.last_name as lastname,";
		$sql.= " per.locale as locale,";
		$sql.= " per.password as personpassword,";

		$sql.= " cp.org_name as partner";

		$sql.= " FROM app_instance as i";
		$sql.= " LEFT JOIN app_instance_meter as im ON i.id = im.app_instance_id AND im.meter_id = 1,";	// meter_id = 1 = users
		$sql.= " customer as c";
		$sql.= " LEFT JOIN address as a ON c.address_id = a.id";
		$sql.= " LEFT JOIN channel_partner_customer as cc ON cc.customer_id = c.id";
		$sql.= " LEFT JOIN channel_partner as cp ON cc.channel_partner_id = cp.id";
		$sql.= " LEFT JOIN person as per ON c.primary_contact_id = per.id,";
		$sql.= " subscription as s, plan as pl";
		$sql.= " LEFT JOIN plan_add_on as pao ON pl.id=pao.plan_id and pao.meter_id = 1,";	// meter_id = 1 = users
		$sql.= " app_package as p";
		$sql.= " WHERE i.customer_id = c.id AND c.id = s.customer_id AND s.plan_id = pl.id AND pl.app_package_id = p.id";
		$sql.= " AND i.status <> 'UNDEPLOYED'";

        if ($ref) $sql.= " AND i.name = '".$this->db2->escape($ref)."'";
        elseif ($organization) $sql.= " AND c.organization = '".$this->db2->escape($organization)."'";
        elseif ($email) $sql.= " AND per.username = '".$this->db2->escape($email)."'";
        else $sql.= " AND i.id = ".$id;
    	if ($ref || $organization || $email) $sql.= " ORDER BY i.deployed_date DESC";

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db2->query($sql);
        if ($resql)
        {
        	$numfound=$this->db2->num_rows($resql);

            if ($numfound)
            {
                $obj = $this->db2->fetch_object($resql);	// Take first one

                $this->id    = $obj->id;

				$this->instance = preg_replace('/\.on\.dolicloud\.com$/','',$obj->instance);
				$this->ref = $obj->instance;
				$this->customer_id = $obj->customer_id;
				$this->organization = $obj->organization;
				$this->email = $obj->email;
				$this->locale = $obj->locale;
				$this->personpassword = $obj->personpassword;

				$this->package_id = $obj->package_id;
				$this->package = $obj->package;
				$this->plan = $obj->plan;

				if (empty($obj->price_user))
				{
					$this->price_instance = 0;
					$this->price_user = $obj->price_instance;
				}
				else
				{
					$this->price_user = $obj->price_user;
					$this->price_instance = $obj->price_instance;
				}

				$this->date_registration = $this->db2->jdate($obj->deployed_date);
				$this->date_endfreeperiod = $this->db2->jdate($obj->trial_end);
				$this->date_current_period_start = $this->db2->jdate($obj->current_period_start);
				$this->date_current_period_end = $this->db2->jdate($obj->current_period_end);
				$this->status = $obj->status;
				$this->partner = $obj->partner;
				$this->source = $obj->source;
				$this->total_invoiced = $obj->total_invoiced;
				$this->total_payed = $obj->total_payed;
				$this->tms = $this->db2->jdate($obj->tms);
				$this->hostname_web = $obj->hostname_web;
				$this->username_web = $obj->username_web;
				$this->password_web = $obj->password_web;
				$this->hostname_db = $obj->hostname_db;
				$this->database_db = $obj->database_db;
				$this->port_db = $obj->port_db;
				$this->username_db = $obj->username_db;
				$this->password_db = $obj->password_db;
				$this->date_lastcheck = $this->db2->jdate($obj->date_lastcheck);
				$this->nbofusers = $obj->nbofusers;
				$this->lastlogin = $obj->lastlogin;
				$this->lastpass = $obj->lastpass;
				$this->date_lastlogin = $this->db2->jdate($obj->date_lastlogin);
				$this->modulesenabled = $obj->modulesenabled;
				$this->fs_path = $obj->fs_path;

                $this->firstname = $obj->firstname;
                $this->lastname = $obj->lastname;
                $this->address = $obj->address;
                $this->zip = $obj->zip;
                $this->town = $obj->town;
                $this->country_id = $obj->country_id;
                $this->country_code = $obj->country_code;
                $this->state_id = $obj->state_id;
                $this->vat_number = $obj->vat_number;
                $this->phone = $obj->phone;

                $this->manual_collection = $obj->manual_collection;

                $this->instance_status = $obj->instance_status;
                $this->payment_status = $obj->payment_status;

                $this->paymentmethod = $obj->paymentmethod;
                $this->paymentinfo = $obj->paymentinfo;
                $this->paymentstatus = $obj->paymentstatus;
                $this->paymentnextbillingdate = $this->db2->jdate($obj->paymentnextbillingdate);
                $this->paymentfrequency = $obj->paymentfrequency;	// 'monthly' or 'yearly'

                $this->fileauthorizedkey = $this->db2->jdate($obj->fileauthorizedkey);
                $this->filelock = $this->db2->jdate($obj->filelock);

                //$this->date_lastrsync = $this->db2->jdate($obj->last_updated);	// May be overwritten by fetch_old
                //$this->version = $obj->version;

                include_once(DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php');
                if (! empty($this->country_id) && (! is_numeric($this->country_id) || $this->country_id > 0))
                {
                	$tmp=getCountry($this->country_id,'all');
                	$this->country_code=$tmp['code']; $this->country=$tmp['label'];
                }
                if (! empty($this->state_id) && (! is_numeric($this->state_id) || $this->state_id > 0))
                if ($this->state_id > 0)
                {
                	$tmp=getState($this->state_id,'all');
                	$this->state_code=$tmp['code']; $this->state=$tmp['label'];
                }

                // Set path
                $this->fs_path = '/home/jail/home/'.$this->username_web.'/'.(preg_replace('/_([a-zA-Z0-9]+)$/','',$this->database_db));

                $sqlpayment = 'SELECT type FROM payment_method WHERE customer_id = '.$this->customer_id.' ORDER BY last_updated DESC';

                $resqlpayment=$this->db2->query($sqlpayment);
                if ($resql)
                {
					$numfoundpayment=$this->db2->num_rows($resqlpayment);

					if ($numfoundpayment)
                	{
                		$objpayment = $this->db2->fetch_object($resqlpayment);	// Take first one
                		$this->payment_type = $objpayment->type;				// 'card' or 'paypal'
                	}
                }
                else dol_print_error($this->db2);

                // Load other info from old table
                $result=$this->fetch_old('',$this->instance);

                $ret=$numfound;
            }
            else $ret=0;

            $this->db2->free($resql);

            return $ret;
        }
        else
        {
      	    $this->error="Error ".$this->db2->lasterror();
            dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
            return -1;
        }
    }



    /**
     *  Update object into database
     *
     *  @param	User	$user        User that modify
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
     *  @return int     		   	 <0 if KO, >0 if OK
     */
    function update_old($user=null, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
		if (isset($this->instance)) $this->instance=trim($this->instance);
		if (isset($this->organization)) $this->organization=trim($this->organization);
		if (isset($this->email)) $this->email=trim($this->email);
		if (isset($this->plan)) $this->plan=trim($this->plan);
		if (isset($this->status)) $this->status=trim($this->status);
		if (isset($this->partner)) $this->partner=trim($this->partner);
		if (isset($this->source)) $this->source=trim($this->source);
		if (isset($this->total_invoiced)) $this->total_invoiced=trim($this->total_invoiced);
		if (isset($this->total_payed)) $this->total_payed=trim($this->total_payed);
		if (isset($this->hostname_web)) $this->hostname_web=trim($this->hostname_web);
		if (isset($this->username_web)) $this->username_web=trim($this->username_web);
		if (isset($this->password_web)) $this->password_web=trim($this->password_web);
		if (isset($this->hostname_db)) $this->hostname_db=trim($this->hostname_db);
		if (isset($this->database_db)) $this->database_db=trim($this->database_db);
		if (isset($this->port_db)) $this->port_db=trim($this->port_db);
		if (isset($this->username_db)) $this->username_db=trim($this->username_db);
		if (isset($this->password_db)) $this->password_db=trim($this->password_db);
		if (isset($this->nbofusers)) $this->nbofusers=trim($this->nbofusers);
		if (isset($this->modulesenabled)) $this->modulesenabled=trim($this->modulesenabled);
		if (isset($this->version)) $this->version=trim($this->version);
		if (isset($this->vat_number)) $this->vat_number=trim($this->vat_number);


		// Check parameters
		// Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."dolicloud_customers SET";

		$sql.= " instance=".(isset($this->instance)?"'".$this->db->escape($this->instance)."'":"null").",";
		$sql.= " organization=".(isset($this->organization)?"'".$this->db->escape($this->organization)."'":"null").",";
		$sql.= " email=".(isset($this->email)?"'".$this->db->escape($this->email)."'":"null").",";
		$sql.= " plan=".(isset($this->plan)?"'".$this->db->escape($this->plan)."'":"null").",";
		//$sql.= " date_registration=".(dol_strlen($this->date_registration)!=0 ? "'".$this->db->idate($this->date_registration)."'" : 'null').",";
		$sql.= " date_endfreeperiod=".(dol_strlen($this->date_endfreeperiod)!=0 ? "'".$this->db->idate($this->date_endfreeperiod)."'" : 'null').",";
		$sql.= " status=".(isset($this->status)?"'".$this->status."'":"null").",";
		$sql.= " partner=".(isset($this->partner)?"'".$this->db->escape($this->partner)."'":"null").",";
		$sql.= " source=".(isset($this->source)?"'".$this->db->escape($this->source)."'":"null").",";
		$sql.= " total_invoiced=".(isset($this->total_invoiced)?$this->total_invoiced:"null").",";
		$sql.= " total_payed=".(isset($this->total_payed)?$this->total_payed:"null").",";
		$sql.= dol_strlen($this->tms)!=0 ? " tms=".(dol_strlen($this->tms)!=0 ? "'".$this->db->idate($this->tms)."'" : 'null')."," : "";
		$sql.= " hostname_web=".(isset($this->hostname_web)?"'".$this->db->escape($this->hostname_web)."'":"null").",";
		$sql.= " username_web=".(isset($this->username_web)?"'".$this->db->escape($this->username_web)."'":"null").",";
		$sql.= " password_web=".(isset($this->password_web)?"'".$this->db->escape($this->password_web)."'":"null").",";
		$sql.= " hostname_db=".(isset($this->hostname_db)?"'".$this->db->escape($this->hostname_db)."'":"null").",";
		$sql.= " database_db=".(isset($this->database_db)?"'".$this->db->escape($this->database_db)."'":"null").",";
		$sql.= " port_db=".(isset($this->port_db)?$this->port_db:"null").",";
		$sql.= " username_db=".(isset($this->username_db)?"'".$this->db->escape($this->username_db)."'":"null").",";
		$sql.= " password_db=".(isset($this->password_db)?"'".$this->db->escape($this->password_db)."'":"null").",";
		$sql.= " lastcheck=".(dol_strlen($this->date_lastcheck)!=0 ? "'".$this->db->idate($this->date_lastcheck)."'" : 'null').",";
		$sql.= " nbofusers=".(isset($this->nbofusers)?$this->nbofusers:"null").",";
		$sql.= " lastlogin=".(dol_strlen($this->lastlogin)!=0 ? "'".$this->db->escape($this->lastlogin)."'" : 'null').",";
		$sql.= " lastpass=".(dol_strlen($this->lastpass)!=0 ? "'".$this->db->escape($this->lastpass)."'" : 'null').",";
		$sql.= " date_lastlogin=".(dol_strlen($this->date_lastlogin)!=0 ? "'".$this->db->idate($this->date_lastlogin)."'" : 'null').",";
		$sql.= " modulesenabled=".(isset($this->modulesenabled)?"'".$this->db->escape($this->modulesenabled)."'":"null").",";
		$sql.= " firstname=".(isset($this->firstname)?"'".$this->db->escape($this->firstname)."'":"null").",";
		$sql.= " lastname=".(isset($this->lastname)?"'".$this->db->escape($this->lastname)."'":"null").",";
		$sql.= " address=".(isset($this->address)?"'".$this->db->escape($this->address)."'":"null").",";
		$sql.= " zip=".(isset($this->zip)?"'".$this->db->escape($this->zip)."'":"null").",";
		$sql.= " town=".(isset($this->town)?"'".$this->db->escape($this->town)."'":"null").",";
		//$sql.= " country_id=".(isset($this->country_id)?"'".$this->db->escape($this->country_id)."'":"null").",";
		$sql.= " state_id=".(isset($this->state_id)?"'".$this->db->escape($this->state_id)."'":"null").",";
		$sql.= " phone=".(isset($this->phone)?"'".$this->db->escape($this->phone)."'":"null").",";
		$sql.= " fileauthorizedkey=".(dol_strlen($this->fileauthorizedkey)!=0 ? "'".$this->db->idate($this->fileauthorizedkey)."'" : 'null').",";
		$sql.= " filelock=".(dol_strlen($this->filelock)!=0 ? "'".$this->db->idate($this->filelock)."'" : 'null').",";
		$sql.= " lastrsync=".(dol_strlen($this->date_lastrsync)!=0 ? "'".$this->db->idate($this->date_lastrsync)."'" : 'null').",";
		$sql.= " backup_status=".(isset($this->backup_status)?"'".$this->db->escape($this->backup_status)."'":"null").",";
		$sql.= " version=".(isset($this->version)?"'".$this->db->escape($this->version)."'":"null").",";
		$sql.= " vat_number=".(isset($this->vat_number)?"'".$this->db->escape($this->vat_number)."'":"null");

        $sql.= " WHERE rowid=".$this->idold;

		$this->db->begin();

		dol_syslog(get_class($this)."::update_old sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_MODIFY',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
	    	}
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::update_old ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
    }

    /**
     *  Update object into database
     *
     *  @param	User	$user        User that modify
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
     *  @return int     		   	 <0 if KO, >0 if OK
     */
    function update($user=null, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters

		if (isset($this->instance)) $this->instance=trim($this->instance);
		if (isset($this->organization)) $this->organization=trim($this->organization);
		if (isset($this->email)) $this->email=trim($this->email);
		if (isset($this->plan)) $this->plan=trim($this->plan);
		if (isset($this->status)) $this->status=trim($this->status);
		if (isset($this->partner)) $this->partner=trim($this->partner);
		if (isset($this->source)) $this->source=trim($this->source);
		if (isset($this->total_invoiced)) $this->total_invoiced=trim($this->total_invoiced);
		if (isset($this->total_payed)) $this->total_payed=trim($this->total_payed);
		if (isset($this->hostname_web)) $this->hostname_web=trim($this->hostname_web);
		if (isset($this->username_web)) $this->username_web=trim($this->username_web);
		if (isset($this->password_web)) $this->password_web=trim($this->password_web);
		if (isset($this->hostname_db)) $this->hostname_db=trim($this->hostname_db);
		if (isset($this->database_db)) $this->database_db=trim($this->database_db);
		if (isset($this->port_db)) $this->port_db=trim($this->port_db);
		if (isset($this->username_db)) $this->username_db=trim($this->username_db);
		if (isset($this->password_db)) $this->password_db=trim($this->password_db);
		if (isset($this->nbofusers)) $this->nbofusers=trim($this->nbofusers);
		if (isset($this->modulesenabled)) $this->modulesenabled=trim($this->modulesenabled);
		if (isset($this->version)) $this->version=trim($this->version);
		if (isset($this->vat_number)) $this->vat_number=trim($this->vat_number);


		// Check parameters
		// Put here code to add control on parameters values


        // Force creation into old database
        $this->db->begin();
       		$tmpobject=dol_clone($this, 1);	// To search if it exists without changing current object
			$result=$tmpobject->fetch_old($this->idold,$this->instance);
			//var_dump($result.' '.$tmpobject->idold);
			if (empty($result))
			{
				$idold=$tmpobject->create_old($user,1);
				$this->idold=$idold;
			}
		$this->db->commit();



        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."dolicloud_customers SET";

		$sql.= " instance=".(isset($this->instance)?"'".$this->db->escape($this->instance)."'":"null").",";
		$sql.= " organization=".(isset($this->organization)?"'".$this->db->escape($this->organization)."'":"null").",";
		$sql.= " email=".(isset($this->email)?"'".$this->db->escape($this->email)."'":"null").",";
		$sql.= " plan=".(isset($this->plan)?"'".$this->db->escape($this->plan)."'":"null").",";
		//$sql.= " date_registration=".(dol_strlen($this->date_registration)!=0 ? "'".$this->db->idate($this->date_registration)."'" : 'null').",";
		$sql.= " date_endfreeperiod=".(dol_strlen($this->date_endfreeperiod)!=0 ? "'".$this->db->idate($this->date_endfreeperiod)."'" : 'null').",";
		$sql.= " status=".(isset($this->status)?"'".$this->status."'":"null").",";
		$sql.= " partner=".(isset($this->partner)?"'".$this->db->escape($this->partner)."'":"null").",";
		$sql.= " source=".(isset($this->source)?"'".$this->db->escape($this->source)."'":"null").",";
		$sql.= " total_invoiced=".(isset($this->total_invoiced)?$this->total_invoiced:"null").",";
		$sql.= " total_payed=".(isset($this->total_payed)?$this->total_payed:"null").",";
		$sql.= dol_strlen($this->tms)!=0 ? " tms=".(dol_strlen($this->tms)!=0 ? "'".$this->db->idate($this->tms)."'" : 'null')."," : "";
		$sql.= " hostname_web=".(isset($this->hostname_web)?"'".$this->db->escape($this->hostname_web)."'":"null").",";
		$sql.= " username_web=".(isset($this->username_web)?"'".$this->db->escape($this->username_web)."'":"null").",";
		$sql.= " password_web=".(isset($this->password_web)?"'".$this->db->escape($this->password_web)."'":"null").",";
		$sql.= " hostname_db=".(isset($this->hostname_db)?"'".$this->db->escape($this->hostname_db)."'":"null").",";
		$sql.= " database_db=".(isset($this->database_db)?"'".$this->db->escape($this->database_db)."'":"null").",";
		$sql.= " port_db=".(isset($this->port_db)?$this->port_db:"null").",";
		$sql.= " username_db=".(isset($this->username_db)?"'".$this->db->escape($this->username_db)."'":"null").",";
		$sql.= " password_db=".(isset($this->password_db)?"'".$this->db->escape($this->password_db)."'":"null").",";
		$sql.= " lastcheck=".(dol_strlen($this->date_lastcheck)!=0 ? "'".$this->db->idate($this->date_lastcheck)."'" : 'null').",";
		$sql.= " nbofusers=".(isset($this->nbofusers)?$this->nbofusers:"null").",";
		$sql.= " lastlogin=".(dol_strlen($this->lastlogin)!=0 ? "'".$this->db->escape($this->lastlogin)."'" : 'null').",";
		$sql.= " lastpass=".(dol_strlen($this->lastpass)!=0 ? "'".$this->db->escape($this->lastpass)."'" : 'null').",";
		$sql.= " date_lastlogin=".(dol_strlen($this->date_lastlogin)!=0 ? "'".$this->db->idate($this->date_lastlogin)."'" : 'null').",";
		$sql.= " modulesenabled=".(isset($this->modulesenabled)?"'".$this->db->escape($this->modulesenabled)."'":"null").",";
		$sql.= " firstname=".(isset($this->firstname)?"'".$this->db->escape($this->firstname)."'":"null").",";
		$sql.= " lastname=".(isset($this->lastname)?"'".$this->db->escape($this->lastname)."'":"null").",";
		$sql.= " address=".(isset($this->address)?"'".$this->db->escape($this->address)."'":"null").",";
		$sql.= " zip=".(isset($this->zip)?"'".$this->db->escape($this->zip)."'":"null").",";
		$sql.= " town=".(isset($this->town)?"'".$this->db->escape($this->town)."'":"null").",";
		//$sql.= " country_id=".(isset($this->country_id)?"'".$this->db->escape($this->country_id)."'":"null").",";
		$sql.= " state_id=".(isset($this->state_id)?"'".$this->db->escape($this->state_id)."'":"null").",";
		$sql.= " phone=".(isset($this->phone)?"'".$this->db->escape($this->phone)."'":"null").",";
		$sql.= " fileauthorizedkey=".(dol_strlen($this->fileauthorizedkey)!=0 ? "'".$this->db->idate($this->fileauthorizedkey)."'" : 'null').",";
		$sql.= " filelock=".(dol_strlen($this->filelock)!=0 ? "'".$this->db->idate($this->filelock)."'" : 'null').",";
		$sql.= " lastrsync=".(dol_strlen($this->date_lastrsync)!=0 ? "'".$this->db->idate($this->date_lastrsync)."'" : 'null').",";
		$sql.= " backup_status=".(isset($this->backup_status)?"'".$this->db->escape($this->backup_status)."'":"null").",";
		$sql.= " version=".(isset($this->version)?"'".$this->db->escape($this->version)."'":"null").",";
		$sql.= " vat_number=".(isset($this->vat_number)?"'".$this->db->escape($this->vat_number)."'":"null");

        $sql.= " WHERE rowid=".$this->idold;

        $this->db->begin();

		dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
		if (! $error)
		{
			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_MODIFY',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
	    	}
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
    }


 	/**
	 *  Delete object in database
	 *
     *	@param  User	$user        User that delete
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
	 *  @return	int					 <0 if KO, >0 if OK
	 */
	function delete($user, $notrigger=0)
	{
		global $conf, $langs;
		$error=0;

		$this->db->begin();

		if (! $error)
		{
			if (! $notrigger)
			{
				// Uncomment this and change MYOBJECT to your own tag if you
		        // want this action call a trigger.

		        //// Call triggers
		        //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
		        //$interface=new Interfaces($this->db);
		        //$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
		        //if ($result < 0) { $error++; $this->errors=$interface->errors; }
		        //// End call triggers
			}
		}

		if (! $error)
		{
    		$sql = "DELETE FROM ".MAIN_DB_PREFIX."dolicloud_customers";
    		$sql.= " WHERE rowid=".$this->id;

    		dol_syslog(get_class($this)."::delete sql=".$sql);
    		$resql = $this->db->query($sql);
        	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::delete ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
	}


	/**
	 *  Return name of contact with link (and eventually picto)
	 *	Use $this->id, $this->name, $this->firstname, this->civility_id
	 *
	 *	@param		int			$withpicto					Include picto with link
	 *	@param		string		$option						Where the link point to
	 *	@param		int			$maxlen						Max length of
	 *  @param      string      $prefixurl      			Prefix url
	 *  @param  	int     	$save_lastsearch_value    	-1=Auto, 0=No save of lastsearch_values when clicking, 1=Save lastsearch_values whenclicking
	 *	@return		string									String with URL
	 */
	function getNomUrl($withpicto=0, $option='', $maxlen=0, $prefixurl='', $save_lastsearch_value=-1)
	{
	    global $langs;

	    $result='';

	    $url = dol_buildpath('/sellyoursaas/backoffice/instance_links.php',1).'?instanceoldid='.$this->id;

	    if ($option != 'nolink')
	    {
	    	// Add param to save lastsearch_values or not
	    	$add_save_lastsearch_values=($save_lastsearch_value == 1 ? 1 : 0);
	    	if ($save_lastsearch_value == -1 && preg_match('/list\.php/',$_SERVER["PHP_SELF"])) $add_save_lastsearch_values=1;
	    	if ($add_save_lastsearch_values) $url.='&save_lastsearch_values=1';
	    }

	    $lien = '<a href="'.$url.'">';
	    $lienfin='</a>';

	    if ($withpicto) $result.=($lien.img_object($langs->trans("ShowCustomer").': '.$this->ref,'generic').$lienfin.' ');
	    $result.=$lien.($maxlen?dol_trunc($this->ref,$maxlen):$this->ref).$lienfin;
	    return $result;
	}


	/**
	 *    Return label of status (activity, closed)
	 *
	 *    @param	int		$mode       0=libelle long, 1=libelle court, 2=Picto + Libelle court, 3=Picto, 4=Picto + Libelle long
	 *    @param	Form	$form		Object form
	 *    @return   string        		Libelle
	 */
	function getLibStatut($mode=0,$form='')
	{
		return $this->LibStatut($this->status,$mode,$this->instance_status,$this->payment_status,$form,$this->subscription_status);
	}

	/**
	 *  Renvoi le libelle d'un statut donne
	 *
	 *  @param	int		$status         Id statut
	 *  @param	int		$mode           0=libelle long, 1=libelle court, 2=Picto + Libelle court, 3=Picto, 4=Picto + Libelle long, 5=Libelle court + Picto
	 *  @param  string  $instance_status  Instance status
	 *  @param  string  $payment_status   Payment status
	 *  @param  string  $form             Form
	 *  @param  string  $subscription_status  Subscription status
	 *  @return	string          		Libelle du statut
	 */
	function LibStatut($status,$mode=0,$instance_status='',$payment_status='',$form='',$subscription_status='')
	{
		global $langs;
		$langs->load('sellyoursaas@sellyoursaas');

		$st=$status;
		if (! empty($instance_status) && ! empty($payment_status) && is_object($form))
		{
            if ($status == 'CLOSED') $st='CLOSED';
            else if ($status == 'SUSPENDED') $st='SUSPENDED';
            else if ($status == 'CLOSURE_REQUESTED') $st='CLOSURE_REQUESTED';
            else if ($instance_status == 'UNDEPLOYED') $st='UNDEPLOYED';		// DEPLOYED/UNDEPLOYED
            else if ($payment_status == 'TRIAL') $st='TRIALING';
            else if ($payment_status == 'TRIALING') $st='TRIALING';
            else if ($payment_status == 'TRIAL_EXPIRED') $st='TRIAL_EXPIRED';
            else if ($status == 'ACTIVE' && $instance_status == 'DEPLOYED') $st=($payment_status && in_array($payment_status, array('OK','PAID')))?'ACTIVE':'ACTIVE_PAY_ERR';
            else
			{
                $st.=$status;
                $st.='<br>'.$instance_status;
                $st.='<br>'.$payment_status;
			}
			$txt='Customer: '.$status;
			$txt.='<br>Instance: '.$instance_status;
			$txt.='<br>Subscription (not used): '.$subscription_status;
            $txt.='<br>Payment: '.$payment_status;
		}

		if ($st == 'ACTIVE' || $st == 'OK' || $st == 'PAID') $picto=img_picto($langs->trans("Active"),'statut4');
		elseif ($st == 'CLOSED_QUEUED' || $st == 'CLOSURE_REQUESTED') $picto=img_picto($langs->trans("Disabled"),'statut6');
		elseif ($st == 'UNDEPLOYED' || $st == 'CLOSED') $picto=img_picto($langs->trans("Undeployed"),'statut5');
		elseif ($st == 'ACTIVE_PAYMENT_ERROR' || $st == 'ACTIVE_PAY_ERR' || $st == 'FAILURE' || $st == 'PAST_DUE') $picto=img_picto($langs->trans("ActivePaymentError"),'statut3');
		elseif ($st == 'SUSPENDED') $picto=img_picto($langs->trans("Suspended"),'statut3');
		elseif ($st == 'TRIAL_EXPIRED') $picto=img_picto($langs->trans("Expired"),'statut1');
		elseif ($st == 'TRIAL' || $st == 'TRIALING') $picto=img_picto($langs->trans("Trial"),'statut0');
		else $picto=img_picto($langs->trans("Trial"),'statut0');

		if (! empty($instance_status) && ! empty($payment_status) && is_object($form))	// New way
		{
			return ($mode==1?$st:$form->textwithpicto($st.' '.$picto, $txt));
		}

		if ($mode == 0)
		{
			return $status;
		}
		if ($mode == 1)
		{
			return $status;
		}
		if ($mode == 2)
		{
			return $picto.' '.$status;
		}
		if ($mode == 3)
		{
			return $picto;
		}
		if ($mode == 4)
		{
			return $picto.' '.$status;
		}
		if ($mode == 5)
		{
			return $status.' '.$picto;
		}
	}


	/**
	 *	Load an object from its id and create a new one in database
	 *
	 *  @param  User 	$user      	User that creates
	 *	@param	int		$fromid     Id of object to clone
	 * 	@return	int					New id of clone
	 */
	function createFromClone(User $user, $fromid)
	{
		$error=0;

		$object=new Dolicloud_customers($this->db);

		$this->db->begin();

		// Load source object
		$object->fetch($fromid);
		$object->id=0;
		$object->statut=0;

		// Clear fields
		// ...

		// Create clone
		$result=$object->create($user);

		// Other options
		if ($result < 0)
		{
			$this->error=$object->error;
			$error++;
		}

		if (! $error)
		{


		}

		// End
		if (! $error)
		{
			$this->db->commit();
			return $object->id;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *	Initialise object with example values
	 *	Id must be 0 if object instance is a specimen
	 *
	 *	@return	void
	 */
	function initAsSpecimen()
	{
		$this->id=0;

		$this->instance='specimeninstance';
		$this->organization='specimenorga';
		$this->email='test@test.com';
		$this->plan='';
		$this->date_registration='';
		$this->date_endfreeperiod='';
		$this->status='ACTIVE';
		$this->partner='';
		$this->source='';
		$this->total_invoiced='';
		$this->total_payed='';
		$this->tms='';
		$this->hostname_web='';
		$this->username_web='';
		$this->password_web='';
		$this->hostname_db='';
		$this->database_db='';
		$this->port_db='';
		$this->username_db='';
		$this->password_db='';
		$this->date_lastcheck='';
		$this->nbofusers='';
		$this->lastlogin='specimenlogin';
		$this->lastpass='';
		$this->date_lastlogin='2012-01-01';
		$this->modulesenabled='';
		$this->fileauthorizedkey='';
		$this->filelock='';
		$this->version='3.0.0';
		$this->date_lastrsync='2012-01-02';
		$this->backup_status='';
		$this->vat_number='FR123456';
	}

}

