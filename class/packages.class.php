<?php
/* Copyright (C) 2007-2017  Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2014-2016  Juanjo Menent       <jmenent@2byte.es>
 * Copyright (C) 2015       Florian Henry       <florian.henry@open-concept.pro>
 * Copyright (C) 2015       Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
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
 * \file        class/packages.class.php
 * \ingroup     sellyoursaas
 * \brief       This file is a CRUD class file for Packages (Create/Read/Update/Delete)
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
//require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
//require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

/**
 * Class for Packages
 */
class Packages extends CommonObject
{
	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'packages';
	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'packages';

	/**
	 * @var array  Does packages support multicompany module ? 0=No test on entity, 1=Test with field entity, 2=Test with link by societe
	 */
	public $ismultientitymanaged = 1;
	/**
	 * @var string String with name of icon for packages
	 */
	public $picto = 'label';


	/**
	 *  'type' if the field format ('integer', 'integer:Class:pathtoclass', 'varchar(x)', 'double(24,8)', 'text', 'html', 'datetime', 'timestamp', 'float')
	 *  'label' the translation key.
	 *  'enabled' is a condition when the field must be managed.
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'default' is a default value for creation (can still be replaced by the global setup of default values)
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
	 *  'position' is the sort order of field.
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 if you want to have a total on list for this field. Field type must be summable like integer or double(24,8).
	 *  'css' is the CSS style to use on field. For example: 'maxwidth200'
	 *  'help' is a string visible as a tooltip on field
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'arraykeyval' to set list of value if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel")
	 */

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields=array(
		'rowid' => array('type'=>'integer', 'label'=>'TechnicalID', 'visible'=>-1, 'enabled'=>1, 'position'=>1, 'notnull'=>1, 'index'=>1, 'comment'=>'Id',),
		'ref' => array('type'=>'varchar(64)', 'label'=>'Ref', 'visible'=>1, 'enabled'=>1, 'position'=>10, 'notnull'=>1, 'index'=>1, 'searchall'=>1, 'comment'=>'Reference of object',),
		'entity' => array('type'=>'integer', 'label'=>'Entity', 'visible'=>-2, 'enabled'=>1, 'position'=>20, 'notnull'=>1, 'index'=>1,),
		'label' => array('type'=>'varchar(255)', 'label'=>'Label', 'visible'=>1, 'enabled'=>1, 'position'=>30, 'notnull'=>-1, 'searchall'=>1, 'css'=>'minwidth200'),
	    'note_public'   =>array('type'=>'html',			'label'=>'NotePublic',		 'enabled'=>1, 'visible'=>-2,  'position'=>45),
	    'note_private'  =>array('type'=>'html',			'label'=>'NotePrivate',		 'enabled'=>1, 'visible'=>-2,  'position'=>46),
		'restrict_domains' => array('type'=>'varchar(255)', 'label'=>'RestrictDomainNames', 'visible'=>-1, 'enabled'=>1, 'position'=>40, 'notnull'=>-1, 'help'=>'KeepEmptyForNoRestrictionOnDomain'),
		'srcfile1' => array('type'=>'varchar(255)', 'label'=>'Dir with sources 1', 'visible'=>-1, 'enabled'=>1, 'position'=>51, 'notnull'=>-1, 'css'=>'minwidth500'),
		'srcfile2' => array('type'=>'varchar(255)', 'label'=>'Dir with sources 2', 'visible'=>-1, 'enabled'=>1, 'position'=>52, 'notnull'=>-1, 'css'=>'minwidth500'),
		'srcfile3' => array('type'=>'varchar(255)', 'label'=>'Dir with sources 3', 'visible'=>-1, 'enabled'=>1, 'position'=>53, 'notnull'=>-1, 'css'=>'minwidth500'),
		'targetsrcfile1' => array('type'=>'varchar(255)', 'label'=>'Target relative dir for sources 1', 'visible'=>-1, 'enabled'=>1, 'position'=>54, 'notnull'=>-1, 'css'=>'minwidth500'),
		'targetsrcfile2' => array('type'=>'varchar(255)', 'label'=>'Target relative dir for sources 2', 'visible'=>-1, 'enabled'=>1, 'position'=>55, 'notnull'=>-1, 'css'=>'minwidth500'),
		'targetsrcfile3' => array('type'=>'varchar(255)', 'label'=>'Target relative dir for sources 3', 'visible'=>-1, 'enabled'=>1, 'position'=>56, 'notnull'=>-1, 'css'=>'minwidth500'),
		'conffile1' => array('type'=>'text:none', 'label'=>'Template of config file 1', 'visible'=>-1, 'enabled'=>1, 'position'=>57, 'notnull'=>-1, 'help'=>'Template fo config file<br><br>Can use substitution vars like<br>__APPDOMAIN__<br>__INSTANCEDIR__<br>__DBNAME__<br>__DBUSER__<br>__DBPASSWORD__<br>__APPUNIQUEKEY__<br>__APPEMAIL__<br>...'),
		'targetconffile1' => array('type'=>'varchar(255)', 'label'=>'Target relative file for config file 1', 'visible'=>-1, 'enabled'=>1, 'position'=>58, 'notnull'=>-1, 'css'=>'minwidth500'),
		'datafile1' => array('type'=>'varchar(255)', 'label'=>'Dir with dump files', 'visible'=>-1, 'enabled'=>1, 'position'=>59, 'notnull'=>-1, 'css'=>'minwidth500', 'help'=>'You can set here __DOL_DATA_ROOT__/sellyoursaas/packages/__PACKAGEREF__ so you can put the sql dump files from the tab "Linked files"'),
		//'targetdatafile1' => array('type'=>'varchar(255)', 'label'=>'Target dir for data 1', 'visible'=>-1, 'enabled'=>1, 'position'=>55, 'notnull'=>-1, 'css'=>'minwidth500'),
		'crontoadd' => array('type'=>'text', 'label'=>'Template of cron file', 'visible'=>3, 'enabled'=>1, 'position'=>60, 'notnull'=>-1, 'help'=>'Content will be used to add a file into /var/spool/cron/crontabs<br><br>Can use substitution vars like<br>__INSTALLMINUTES__<br>__INSTALLHOURS__<br>__INSTANCEDIR__<br>__OSUSERNAME__<br>...'),
		'cliafter' => array('type'=>'text', 'label'=>'Shell after', 'visible'=>3, 'enabled'=>1, 'position'=>65, 'notnull'=>-1, 'help'=>"Cli shell executed after deployment.<br><br>For example, you can use the following shell sequence to enable a module:<br>rm -fr __INSTANCEDIR__/documents/install.lock;<br>cd __INSTANCEDIR__/htdocs/install/;<br>php __INSTANCEDIR__/htdocs/install/upgrade2.php 0.0.0 0.0.0 MAIN_MODULE_MYMODULE;<br>touch __INSTANCEDIR__/documents/install.lock;<br>chown -R __OSUSERNAME__.__OSUSERNAME__ __INSTANCEDIR__/documents;"),
		'sqlafter' => array('type'=>'text', 'label'=>'Sql after', 'visible'=>3, 'enabled'=>1, 'position'=>70, 'notnull'=>-1, 'help'=>'Sql executed after deployment.<br><br>Can use substitution vars like<br>__APPPASSWORD0__<br>__APPPASSWORD0SALTED__<br>__APPPASSWORDSHA256__<br>__APPPASSWORDSHA256SALTED__<br>__APPEMAIL__<br>__APPDOMAIN__<br>__OSUSERNAME__<br>...'),
	    'allowoverride' => array('type'=>'varchar(255)', 'label'=>'Option string for virtual host', 'visible'=>-1, 'enabled'=>1, 'position'=>75, 'notnull'=>-1, 'help'=>'Any string to add into the Apache virtual host file. For example, keep empty to not allow apache override<br>Use "AllowOverride All" to allow override.'),
		'version_formula' => array('type'=>'text', 'label'=>"VersionFormula",  'visible'=>3, 'enabled'=>1, 'position'=>76, 'help'=>'VersionFormulaExamples', 'lang'=>'sellyoursaas@sellyoursaas'),
	    //'register_text' =>array('type'=>'varchar(255)',			'label'=>'RegisterText',	 'enabled'=>1, 'visible'=>-1,  'position'=>100, 'help'=>'EnterHereTranslationKeyToUseOnRegisterPage'),
		'date_creation' => array('type'=>'datetime', 'label'=>'DateCreation', 'visible'=>-2, 'enabled'=>1, 'position'=>500, 'notnull'=>1,),
		'tms' => array('type'=>'timestamp', 'label'=>'DateModification', 'visible'=>-2, 'enabled'=>1, 'position'=>501, 'notnull'=>1,),
		'fk_user_creat' => array('type'=>'integer', 'label'=>'UserAuthor', 'visible'=>-2, 'enabled'=>1, 'position'=>502, 'notnull'=>1,),
		'fk_user_modif' => array('type'=>'integer', 'label'=>'UserModif', 'visible'=>-2, 'enabled'=>1, 'position'=>503, 'notnull'=>-1,),
		'status' => array('type'=>'integer', 'label'=>'Status', 'visible'=>1, 'enabled'=>1, 'position'=>1000, 'notnull'=>1, 'default'=>0, 'index'=>1, 'arrayofkeyval'=>array('0'=>'Disabled', '1'=>'Active')),
		'import_key' => array('type'=>'varchar(14)', 'label'=>'ImportId', 'visible'=>-2, 'enabled'=>1, 'position'=>1010, 'notnull'=>-1, 'index'=>1,),
	);
	public $rowid;
	public $ref;
	public $entity;
	public $label;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $restrict_domains;
	public $import_key;
	public $sqldump;
	public $srcfile1;
	public $targetsrcfile1;
	public $srcfile2;
	public $targetsrcfile2;
	public $srcfile3;
	public $targetsrcfile3;
	public $conffile1;
	public $targetconffile1;
	public $datafile1;
	public $targetdatafile1;
	public $crontoadd;
	public $cliafter;
	public $sqlafter;
	public $allowoverride;
	public $register_text;
	public $status;
	// END MODULEBUILDER PROPERTIES



	// If this object has a subtable with lines

	/**
	 * @var int    Name of subtable line
	 */
	//public $table_element_line = 'packagesdet';
	/**
	 * @var int    Field with ID of parent key if this field has a parent
	 */
	//public $fk_element = 'fk_packages';
	/**
	 * @var int    Name of subtable class that manage subtable lines
	 */
	//public $class_element_line = 'Packagesline';
	/**
	 * @var array  Array of child tables (child tables to delete before deleting a record)
	 */
	//protected $childtables=array('packagesdet');
	/**
	 * @var PackagesLine[]     Array of subtable lines
	 */
	//public $lines = array();



	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf;

		$this->db = $db;

		if (empty($conf->global->MAIN_SHOW_TECHNICAL_ID)) $this->fields['rowid']['visible']=0;
	}

	/**
	 * Create object into database
	 *
	 * @param  User $user      User that creates
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = false)
	{
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Clone and object into another one
	 *
	 * @param  	User 	$user      	User that creates
	 * @param  	int 	$fromid     Id of object to clone
	 * @return 	mixed 				New object created, <0 if KO
	 */
	public function createFromClone(User $user, $fromid)
	{
		global $hookmanager, $langs;
	    $error = 0;

	    dol_syslog(__METHOD__, LOG_DEBUG);

	    $object = new self($this->db);

	    $this->db->begin();

	    // Load source object
	    $object->fetchCommon($fromid);
	    // Reset some properties
	    unset($object->id);
	    unset($object->fk_user_creat);
	    unset($object->import_key);

	    // Clear other fields
	    $object->ref = "copy_of_".$object->ref;
	    $object->title = $langs->trans("CopyOf")." ".$object->title;
	    // ...

	    // Create clone
		$object->context['createfromclone'] = 'createfromclone';
	    $result = $object->createCommon($user);
	    if ($result < 0) {
	        $error++;
	        $this->error = $object->error;
	        $this->errors = $object->errors;
	    }

	    // End
	    if (!$error) {
	        $this->db->commit();
	        return $object;
	    } else {
	        $this->db->rollback();
	        return -1;
	    }
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param int    $id   Id object
	 * @param string $ref  Ref
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		$result = $this->fetchCommon($id, $ref);
		if ($result > 0 && ! empty($this->table_element_line)) $this->fetchLines();
		return $result;
	}

	/**
	 * Load object lines in memory from the database
	 *
	 * @param int    $id   Id object
	 * @param string $ref  Ref
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetchLines($id, $ref = null)
	{
		$this->lines=array();

		// Load lines with object PackagesLine

		return count($this->lines)?1:0;
	}

	/**
	 * Update object into database
	 *
	 * @param  User $user      User that modifies
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = false)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param User $user       User that deletes
	 * @param bool $notrigger  false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = false)
	{
		return $this->deleteCommon($user, $trigger);
	}

	/**
	 *  Return a link to the object card (with optionaly the picto)
	 *
	 *	@param	int		$withpicto					Include picto in link (0=No picto, 1=Include picto into link, 2=Only picto)
	 *	@param	string	$option						On what the link point to
     *  @param	int  	$notooltip					1=Disable tooltip
     *  @param  string  $morecss            		Add more css on link
     *  @param  int     $save_lastsearch_value    	-1=Auto, 0=No save of lastsearch_values when clicking, 1=Save lastsearch_values whenclicking
	 *	@return	string								String with URL
	 */
	function getNomUrl($withpicto=0, $option='', $notooltip=0, $morecss='', $save_lastsearch_value=-1)
	{
		global $db, $conf, $langs;
        global $dolibarr_main_authentication, $dolibarr_main_demo;
        global $menumanager;

        if (! empty($conf->dol_no_mouse_hover)) $notooltip=1;   // Force disable tooltips

        $result = '';
        $companylink = '';

        $label = '<u>' . $langs->trans("Packages") . '</u>';
        $label.= '<br>';
        $label.= '<b>' . $langs->trans('Ref') . ':</b> ' . $this->ref;
        $label.= '<br><b>' . $langs->trans('Label') . ':</b> ' . $this->label;
        $label.= '<br><b>' . $langs->trans('RestrictDomainNames') . ':</b> ' . $this->restrict_domains;

        $url='';
        if ($option != 'nolink')
        {
        	$url = dol_buildpath('/sellyoursaas/packages_card.php',1).'?id='.$this->id;

	        // Add param to save lastsearch_values or not
	        $add_save_lastsearch_values=($save_lastsearch_value == 1 ? 1 : 0);
	        if ($save_lastsearch_value == -1 && preg_match('/list\.php/',$_SERVER["PHP_SELF"])) $add_save_lastsearch_values=1;
	        if ($add_save_lastsearch_values) $url.='&save_lastsearch_values=1';
        }

        $linkclose='';
        if (empty($notooltip))
        {
            if (! empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER))
            {
                $label=$langs->trans("ShowPackages");
                $linkclose.=' alt="'.dol_escape_htmltag($label, 1).'"';
            }
            $linkclose.=' title="'.dol_escape_htmltag($label, 1).'"';
            $linkclose.=' class="classfortooltip'.($morecss?' '.$morecss:'').'"';
        }
        else $linkclose = ($morecss?' class="'.$morecss.'"':'');

		$linkstart = '<a href="'.$url.'"';
		$linkstart.=$linkclose.'>';
		$linkend='</a>';

		$result .= $linkstart;
		if ($withpicto) $result.=img_object(($notooltip?'':$label), ($this->picto?$this->picto:'generic'), ($notooltip?(($withpicto != 2) ? 'class="paddingright"' : ''):'class="'.(($withpicto != 2) ? 'paddingright ' : '').'classfortooltip"'), 0, 0, $notooltip?0:1);
		if ($withpicto != 2) $result.= $this->ref;
		$result .= $linkend;

		return $result;
	}

	/**
	 *  Retourne le libelle du status d'un user (actif, inactif)
	 *
	 *  @param	int		$mode          0=libelle long, 1=libelle court, 2=Picto + Libelle court, 3=Picto, 4=Picto + Libelle long, 5=Libelle court + Picto
	 *  @return	string 			       Label of status
	 */
	function getLibStatut($mode=0)
	{
		return $this->LibStatut($this->status,$mode);
	}

	/**
	 *  Return the status
	 *
	 *  @param	int		$status        	Id status
	 *  @param  int		$mode          	0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return string 			       	Label of status
	 */
	static function LibStatut($status,$mode=0)
	{
		global $langs;

		if ($mode == 0)
		{
			$prefix='';
			if ($status == 1) return $langs->trans('Enabled');
			if ($status == 0) return $langs->trans('Disabled');
		}
		if ($mode == 1)
		{
			if ($status == 1) return $langs->trans('Enabled');
			if ($status == 0) return $langs->trans('Disabled');
		}
		if ($mode == 2)
		{
			if ($status == 1) return img_picto($langs->trans('Enabled'),'statut4').' '.$langs->trans('Enabled');
			if ($status == 0) return img_picto($langs->trans('Disabled'),'statut5').' '.$langs->trans('Disabled');
		}
		if ($mode == 3)
		{
			if ($status == 1) return img_picto($langs->trans('Enabled'),'statut4');
			if ($status == 0) return img_picto($langs->trans('Disabled'),'statut5');
		}
		if ($mode == 4)
		{
			if ($status == 1) return img_picto($langs->trans('Enabled'),'statut4').' '.$langs->trans('Enabled');
			if ($status == 0) return img_picto($langs->trans('Disabled'),'statut5').' '.$langs->trans('Disabled');
		}
		if ($mode == 5)
		{
			if ($status == 1) return $langs->trans('Enabled').' '.img_picto($langs->trans('Enabled'),'statut4');
			if ($status == 0) return $langs->trans('Disabled').' '.img_picto($langs->trans('Disabled'),'statut5');
		}
		if ($mode == 6)
		{
			if ($status == 1) return $langs->trans('Enabled').' '.img_picto($langs->trans('Enabled'),'statut4');
			if ($status == 0) return $langs->trans('Disabled').' '.img_picto($langs->trans('Disabled'),'statut5');
		}
	}

	/**
	 *	Charge les informations d'ordre info dans l'objet commande
	 *
	 *	@param  int		$id       Id of order
	 *	@return	void
	 */
	function info($id)
	{
		$sql = 'SELECT rowid, date_creation as datec, tms as datem,';
		$sql.= ' fk_user_creat, fk_user_modif';
		$sql.= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' as t';
		$sql.= ' WHERE t.rowid = '.((int) $id);
		$result=$this->db->query($sql);
		if ($result)
		{
			if ($this->db->num_rows($result))
			{
				$obj = $this->db->fetch_object($result);
				$this->id = $obj->rowid;
				if ($obj->fk_user_author)
				{
					$cuser = new User($this->db);
					$cuser->fetch($obj->fk_user_author);
					$this->user_creation   = $cuser;
				}

				if ($obj->fk_user_valid)
				{
					$vuser = new User($this->db);
					$vuser->fetch($obj->fk_user_valid);
					$this->user_validation = $vuser;
				}

				if ($obj->fk_user_cloture)
				{
					$cluser = new User($this->db);
					$cluser->fetch($obj->fk_user_cloture);
					$this->user_cloture   = $cluser;
				}

				$this->date_creation     = $this->db->jdate($obj->datec);
				$this->date_modification = $this->db->jdate($obj->datem);
				$this->date_validation   = $this->db->jdate($obj->datev);
			}

			$this->db->free($result);

		}
		else
		{
			dol_print_error($this->db);
		}
	}

	/**
	 * Initialise object with example values
	 * Id must be 0 if object instance is a specimen
	 *
	 * @return void
	 */
	public function initAsSpecimen()
	{
		$this->initAsSpecimenCommon();
	}


	/**
	 * Action executed by scheduler
	 * CAN BE A CRON TASK
	 *
	 * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
	 */
	public function doScheduledJob()
	{
		global $conf, $langs;

		$this->output = '';
		$this->error='';

		dol_syslog(__METHOD__, LOG_DEBUG);

		// ...

		return 0;
	}
}

/**
 * Class PackagesLine. You can also remove this and generate a CRUD class for lines objects.
 */
/*
class PackagesLine
{
	// @var int ID
	public $id;
	// @var mixed Sample line property 1
	public $prop1;
	// @var mixed Sample line property 2
	public $prop2;
}
*/
