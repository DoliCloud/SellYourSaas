<?php
/* Copyright (C) 2007-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) ---Put here your own copyright and developer email---
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
 *  \file       dev/skeletons/customeraccount.class.php
 *  \ingroup    mymodule othermodule1 othermodule2
 *  \brief      This file is an example for a CRUD class file (Create/Read/Update/Delete)
 *				Initialy built by build_class_from_table on 2014-02-26 10:42
 */

// Put here all includes required by your class file
require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
//require_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
//require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");


/**
 *	Put here description of your class
 */
class Customeraccount extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	var $element='customeraccount';			//!< Id that identify managed objects
	var $table_element='customeraccount';		//!< Name of table without prefix where object is stored

    var $id;

	var $version;
	var $acquired_at='';
	var $address_id;
	var $channel_partner_id;
	var $last_payment_failure_date='';
	var $past_due_start='';
	var $org_name;
	var $payment_error_start_date='';
	var $payment_method_id;
	var $payment_status;
	var $plan_id;
	var $primary_contact_id;
	var $registered_date='';
	var $status;
	var $tel;
	var $tax_id;
	var $discount_id;
	var $suspension_date='';
	var $manual_collection;




    /**
     *  Constructor
     *
     *  @param	DoliDb		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;
        return 1;
    }


    /**
     *  Create object into database
     *
     *  @param	User	$user        User that creates
     *  @param  int		$notrigger   0=launch triggers after, 1=disable triggers
     *  @return int      		   	 <0 if KO, Id of created object if OK
     */
    function create($user, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters

		if (isset($this->version)) $this->version=trim($this->version);
		if (isset($this->address_id)) $this->address_id=trim($this->address_id);
		if (isset($this->channel_partner_id)) $this->channel_partner_id=trim($this->channel_partner_id);
		if (isset($this->org_name)) $this->org_name=trim($this->org_name);
		if (isset($this->payment_method_id)) $this->payment_method_id=trim($this->payment_method_id);
		if (isset($this->payment_status)) $this->payment_status=trim($this->payment_status);
		if (isset($this->plan_id)) $this->plan_id=trim($this->plan_id);
		if (isset($this->primary_contact_id)) $this->primary_contact_id=trim($this->primary_contact_id);
		if (isset($this->status)) $this->status=trim($this->status);
		if (isset($this->tel)) $this->tel=trim($this->tel);
		if (isset($this->tax_id)) $this->tax_id=trim($this->tax_id);
		if (isset($this->discount_id)) $this->discount_id=trim($this->discount_id);
		if (isset($this->manual_collection)) $this->manual_collection=trim($this->manual_collection);



		// Check parameters
		// Put here code to add control on parameters values

        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."customer(";

		$sql.= "version,";
		$sql.= "acquired_at,";
		$sql.= "address_id,";
		$sql.= "channel_partner_id,";
		$sql.= "last_payment_failure_date,";
		$sql.= "past_due_start,";
		$sql.= "org_name,";
		$sql.= "payment_error_start_date,";
		$sql.= "payment_method_id,";
		$sql.= "payment_status,";
		$sql.= "plan_id,";
		$sql.= "primary_contact_id,";
		$sql.= "registered_date,";
		$sql.= "status,";
		$sql.= "tel,";
		$sql.= "tax_id,";
		$sql.= "discount_id,";
		$sql.= "suspension_date,";
		$sql.= "manual_collection";


        $sql.= ") VALUES (";

		$sql.= " ".(! isset($this->version)?'NULL':"'".$this->version."'").",";
		$sql.= " ".(! isset($this->acquired_at) || dol_strlen($this->acquired_at)==0?'NULL':$this->db->idate($this->acquired_at)).",";
		$sql.= " ".(! isset($this->address_id)?'NULL':"'".$this->address_id."'").",";
		$sql.= " ".(! isset($this->channel_partner_id)?'NULL':"'".$this->channel_partner_id."'").",";
		$sql.= " ".(! isset($this->last_payment_failure_date) || dol_strlen($this->last_payment_failure_date)==0?'NULL':$this->db->idate($this->last_payment_failure_date)).",";
		$sql.= " ".(! isset($this->past_due_start) || dol_strlen($this->past_due_start)==0?'NULL':$this->db->idate($this->past_due_start)).",";
		$sql.= " ".(! isset($this->org_name)?'NULL':"'".$this->db->escape($this->org_name)."'").",";
		$sql.= " ".(! isset($this->payment_error_start_date) || dol_strlen($this->payment_error_start_date)==0?'NULL':$this->db->idate($this->payment_error_start_date)).",";
		$sql.= " ".(! isset($this->payment_method_id)?'NULL':"'".$this->payment_method_id."'").",";
		$sql.= " ".(! isset($this->payment_status)?'NULL':"'".$this->db->escape($this->payment_status)."'").",";
		$sql.= " ".(! isset($this->plan_id)?'NULL':"'".$this->plan_id."'").",";
		$sql.= " ".(! isset($this->primary_contact_id)?'NULL':"'".$this->primary_contact_id."'").",";
		$sql.= " ".(! isset($this->registered_date) || dol_strlen($this->registered_date)==0?'NULL':$this->db->idate($this->registered_date)).",";
		$sql.= " ".(! isset($this->status)?'NULL':"'".$this->db->escape($this->status)."'").",";
		$sql.= " ".(! isset($this->tel)?'NULL':"'".$this->db->escape($this->tel)."'").",";
		$sql.= " ".(! isset($this->tax_id)?'NULL':"'".$this->db->escape($this->tax_id)."'").",";
		$sql.= " ".(! isset($this->discount_id)?'NULL':"'".$this->discount_id."'").",";
		$sql.= " ".(! isset($this->suspension_date) || dol_strlen($this->suspension_date)==0?'NULL':$this->db->idate($this->suspension_date)).",";
		$sql.= " ".(! isset($this->manual_collection)?'NULL':"'".$this->manual_collection."'")."";


		$sql.= ")";

		$this->db->begin();

	   	dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."customer");

			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action calls a trigger.

	            //// Call triggers
	            //include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
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
	            dol_syslog(get_class($this)."::create ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
            return $this->id;
		}
    }


    /**
     *  Load object in memory from the database
     *
     *  @param	int		$id    Id object
     *  @return int          	<0 if KO, >0 if OK
     */
    function fetch($id)
    {
    	global $langs;
        $sql = "SELECT";

		$sql.= " t.id,";
		$sql.= " t.version,";
		$sql.= " t.acquired_at,";
		$sql.= " t.address_id,";
		$sql.= " t.channel_partner_id,";
		$sql.= " t.last_payment_failure_date,";
		$sql.= " t.past_due_start,";
		$sql.= " t.org_name,";
		$sql.= " t.payment_error_start_date,";
		$sql.= " t.payment_method_id,";
		$sql.= " t.payment_status,";
		$sql.= " t.plan_id,";
		$sql.= " t.primary_contact_id,";
		$sql.= " t.registered_date,";
		$sql.= " t.status,";
		$sql.= " t.tel,";
		$sql.= " t.tax_id,";
		$sql.= " t.discount_id,";
		$sql.= " t.suspension_date,";
		$sql.= " t.manual_collection";


        $sql.= " FROM ".MAIN_DB_PREFIX."customer as t";
        $sql.= " WHERE t.id = ".$id;

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id    = $obj->id;

				$this->version = $obj->version;
				$this->acquired_at = $this->db->jdate($obj->acquired_at);
				$this->address_id = $obj->address_id;
				$this->channel_partner_id = $obj->channel_partner_id;
				$this->last_payment_failure_date = $this->db->jdate($obj->last_payment_failure_date);
				$this->past_due_start = $this->db->jdate($obj->past_due_start);
				$this->org_name = $obj->org_name;
				$this->payment_error_start_date = $this->db->jdate($obj->payment_error_start_date);
				$this->payment_method_id = $obj->payment_method_id;
				$this->payment_status = $obj->payment_status;
				$this->plan_id = $obj->plan_id;
				$this->primary_contact_id = $obj->primary_contact_id;
				$this->registered_date = $this->db->jdate($obj->registered_date);
				$this->status = $obj->status;
				$this->tel = $obj->tel;
				$this->tax_id = $obj->tax_id;
				$this->discount_id = $obj->discount_id;
				$this->suspension_date = $this->db->jdate($obj->suspension_date);
				$this->manual_collection = $obj->manual_collection;


            }
            $this->db->free($resql);

            return 1;
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *  Update object into database
     *
     *  @param	User	$user        User that modifies
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
     *  @return int     		   	 <0 if KO, >0 if OK
     */
    function update($user=null, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters

		if (isset($this->version)) $this->version=trim($this->version);
		if (isset($this->address_id)) $this->address_id=trim($this->address_id);
		if (isset($this->channel_partner_id)) $this->channel_partner_id=trim($this->channel_partner_id);
		if (isset($this->org_name)) $this->org_name=trim($this->org_name);
		if (isset($this->payment_method_id)) $this->payment_method_id=trim($this->payment_method_id);
		if (isset($this->payment_status)) $this->payment_status=trim($this->payment_status);
		if (isset($this->plan_id)) $this->plan_id=trim($this->plan_id);
		if (isset($this->primary_contact_id)) $this->primary_contact_id=trim($this->primary_contact_id);
		if (isset($this->status)) $this->status=trim($this->status);
		if (isset($this->tel)) $this->tel=trim($this->tel);
		if (isset($this->tax_id)) $this->tax_id=trim($this->tax_id);
		if (isset($this->discount_id)) $this->discount_id=trim($this->discount_id);
		if (isset($this->manual_collection)) $this->manual_collection=trim($this->manual_collection);



		// Check parameters
		// Put here code to add a control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."customer SET";

		$sql.= " version=".(isset($this->version)?$this->version:"null").",";
		$sql.= " acquired_at=".(dol_strlen($this->acquired_at)!=0 ? "'".$this->db->idate($this->acquired_at)."'" : 'null').",";
		$sql.= " address_id=".(isset($this->address_id)?$this->address_id:"null").",";
		$sql.= " channel_partner_id=".(isset($this->channel_partner_id)?$this->channel_partner_id:"null").",";
		$sql.= " last_payment_failure_date=".(dol_strlen($this->last_payment_failure_date)!=0 ? "'".$this->db->idate($this->last_payment_failure_date)."'" : 'null').",";
		$sql.= " past_due_start=".(dol_strlen($this->past_due_start)!=0 ? "'".$this->db->idate($this->past_due_start)."'" : 'null').",";
		$sql.= " org_name=".(isset($this->org_name)?"'".$this->db->escape($this->org_name)."'":"null").",";
		$sql.= " payment_error_start_date=".(dol_strlen($this->payment_error_start_date)!=0 ? "'".$this->db->idate($this->payment_error_start_date)."'" : 'null').",";
		$sql.= " payment_method_id=".(isset($this->payment_method_id)?$this->payment_method_id:"null").",";
		$sql.= " payment_status=".(isset($this->payment_status)?"'".$this->db->escape($this->payment_status)."'":"null").",";
		$sql.= " plan_id=".(isset($this->plan_id)?$this->plan_id:"null").",";
		$sql.= " primary_contact_id=".(isset($this->primary_contact_id)?$this->primary_contact_id:"null").",";
		$sql.= " registered_date=".(dol_strlen($this->registered_date)!=0 ? "'".$this->db->idate($this->registered_date)."'" : 'null').",";
		$sql.= " status=".(isset($this->status)?"'".$this->db->escape($this->status)."'":"null").",";
		$sql.= " tel=".(isset($this->tel)?"'".$this->db->escape($this->tel)."'":"null").",";
		$sql.= " tax_id=".(isset($this->tax_id)?"'".$this->db->escape($this->tax_id)."'":"null").",";
		$sql.= " discount_id=".(isset($this->discount_id)?$this->discount_id:"null").",";
		$sql.= " suspension_date=".(dol_strlen($this->suspension_date)!=0 ? "'".$this->db->idate($this->suspension_date)."'" : 'null').",";
		$sql.= " manual_collection=".(isset($this->manual_collection)?$this->manual_collection:"null")."";


        $sql.= " WHERE id=".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action calls a trigger.

	            //// Call triggers
	            //include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
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
     *	@param  User	$user        User that deletes
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
		        // want this action calls a trigger.

		        //// Call triggers
		        //include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		        //$interface=new Interfaces($this->db);
		        //$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
		        //if ($result < 0) { $error++; $this->errors=$interface->errors; }
		        //// End call triggers
			}
		}

		if (! $error)
		{
    		$sql = "DELETE FROM ".MAIN_DB_PREFIX."customer";
    		$sql.= " WHERE id=".$this->id;

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
	 *	Load an object from its id and create a new one in database
	 *
	 *  @param  User 	$user      	User that creates
	 *	@param	int		$fromid     Id of object to clone
	 * 	@return	int					New id of clone
	 */
	function createFromClone(User $user, $fromid)
	{
		$error=0;

		$object=new Customeraccount($this->db);

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

		$this->version='';
		$this->acquired_at='';
		$this->address_id='';
		$this->channel_partner_id='';
		$this->last_payment_failure_date='';
		$this->past_due_start='';
		$this->org_name='';
		$this->payment_error_start_date='';
		$this->payment_method_id='';
		$this->payment_status='';
		$this->plan_id='';
		$this->primary_contact_id='';
		$this->registered_date='';
		$this->status='';
		$this->tel='';
		$this->tax_id='';
		$this->discount_id='';
		$this->suspension_date='';
		$this->manual_collection='';


	}

}
