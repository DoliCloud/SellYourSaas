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

	public $module = 'sellyoursaas';

	/**
	 * @var array  Does packages support multicompany module ? 0=No test on entity, 1=Test with field entity, 2=Test with link by societe
	 */
	public $ismultientitymanaged = 1;
	/**
	 * @var string String with name of icon for packages
	 */
	public $picto = 'label';

	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;


	/**
	 *  'type' field format ('integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter]]', 'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter]]]', 'varchar(x)', 'double(24,8)', 'real', 'price', 'text', 'text:none', 'html', 'date', 'datetime', 'timestamp', 'duration', 'mail', 'phone', 'url', 'password')
	 *         Note: Filter can be a string like "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.nature:is:NULL)"
	 *  'label' the translation key.
	 *  'picto' is code of a picto to show before value in forms
	 *  'enabled' is a condition when the field must be managed (Example: 1 or '$conf->global->MY_SETUP_PARAM)
	 *  'position' is the sort order of field.
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'default' is a default value for creation (can still be overwrote by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 if you want to have a total on list for this field. Field type must be summable like integer or double(24,8).
	 *  'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
	 *  'help' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
	 *  'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
	 *  'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *
	 *  Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
	 */

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields=array(
		'rowid' => array('type'=>'integer', 'label'=>'TechnicalID', 'visible'=>-1, 'enabled'=>1, 'position'=>1, 'notnull'=>1, 'index'=>1, 'comment'=>'Id',),
		'entity' => array('type'=>'integer', 'label'=>'Entity', 'visible'=>-2, 'enabled'=>1, 'position'=>5, 'notnull'=>1, 'index'=>1,),
		'ref' => array('type'=>'varchar(64)', 'label'=>'Ref', 'visible'=>1, 'enabled'=>1, 'position'=>10, 'notnull'=>1, 'index'=>1, 'searchall'=>1, 'comment'=>'Reference of object', 'csslist'=>'tdoverflowmax150'),
		'label' => array('type'=>'varchar(255)', 'label'=>'Label', 'visible'=>1, 'enabled'=>1, 'position'=>30, 'notnull'=>-1, 'searchall'=>1, 'css'=>'minwidth200'),
		'note_public'   =>array('type'=>'html',			'label'=>'NotePublic',		 'enabled'=>1, 'visible'=>-2,  'position'=>45),
		'note_private'  =>array('type'=>'html',			'label'=>'NotePrivate',		 'enabled'=>1, 'visible'=>-2,  'position'=>46),
		'restrict_domains' => array('type'=>'varchar(255)', 'label'=>'RestrictDomainNames', 'visible'=>-1, 'enabled'=>1, 'position'=>40, 'notnull'=>-1, 'help'=>'KeepEmptyForNoRestrictionOnDomain'),
		'srcfile1' => array('type'=>'varchar(255)', 'label'=>'Dir with sources 1', 'visible'=>-1, 'enabled'=>1, 'position'=>51, 'notnull'=>-1, 'css'=>'minwidth500', 'csslist'=>'tdoverflowmax400', 'cssview'=>'wordbreak'),
		'srcfile2' => array('type'=>'varchar(255)', 'label'=>'Dir with sources 2', 'visible'=>-1, 'enabled'=>1, 'position'=>52, 'notnull'=>-1, 'css'=>'minwidth500', 'csslist'=>'tdoverflowmax400', 'cssview'=>'wordbreak'),
		'srcfile3' => array('type'=>'varchar(255)', 'label'=>'Dir with sources 3', 'visible'=>-1, 'enabled'=>1, 'position'=>53, 'notnull'=>-1, 'css'=>'minwidth500', 'csslist'=>'tdoverflowmax400', 'cssview'=>'wordbreak'),
		'targetsrcfile1' => array('type'=>'varchar(255)', 'label'=>'Target relative dir for sources 1', 'visible'=>-1, 'enabled'=>1, 'position'=>54, 'notnull'=>-1, 'css'=>'minwidth500', 'csslist'=>'tdoverflowmax400'),
		'targetsrcfile2' => array('type'=>'varchar(255)', 'label'=>'Target relative dir for sources 2', 'visible'=>-1, 'enabled'=>1, 'position'=>55, 'notnull'=>-1, 'css'=>'minwidth500', 'csslist'=>'tdoverflowmax400'),
		'targetsrcfile3' => array('type'=>'varchar(255)', 'label'=>'Target relative dir for sources 3', 'visible'=>-1, 'enabled'=>1, 'position'=>56, 'notnull'=>-1, 'css'=>'minwidth500', 'csslist'=>'tdoverflowmax400'),
		'conffile1' => array('type'=>'text:none', 'label'=>'Template of config file 1', 'visible'=>-1, 'enabled'=>1, 'position'=>57, 'notnull'=>-1, 'cssview'=>'wordbreak', 'help'=>'Template fo config file<br><br>Can use substitution vars like<br>__APPDOMAIN__<br>__INSTANCEDIR__<br>__DBNAME__<br>__DBUSER__<br>__DBPASSWORD__<br>__APPUNIQUEKEY__<br>__APPEMAIL__<br>...'),
		'targetconffile1' => array('type'=>'varchar(255)', 'label'=>'Target relative file for config file 1', 'visible'=>-1, 'enabled'=>1, 'position'=>58, 'notnull'=>-1, 'css'=>'minwidth500', 'csslist'=>'tdoverflowmax400', 'cssview'=>'wordbreak'),
		'datafile1' => array('type'=>'varchar(255)', 'label'=>'Dir with dump files', 'visible'=>-1, 'enabled'=>1, 'position'=>59, 'notnull'=>-1, 'css'=>'minwidth500', 'csslist'=>'tdoverflowmax400', 'help'=>'You can set here __DOL_DATA_ROOT__/sellyoursaas/packages/__PACKAGEREF__ so you can put the sql dump files from the tab "Linked files"'),
		'crontoadd' => array('type'=>'text', 'label'=>'Template of cron file', 'visible'=>3, 'enabled'=>1, 'position'=>60, 'notnull'=>-1, 'help'=>'Content will be used to add a file into /var/spool/cron/crontabs<br><br>Can use substitution vars like<br>__INSTALLMINUTES__<br>__INSTALLHOURS__<br>__INSTANCEDIR__<br>__OSUSERNAME__<br>...'),
		'cliafter' => array('type'=>'text', 'label'=>'Shell after deployment', 'visible'=>3, 'enabled'=>1, 'position'=>65, 'notnull'=>-1, 'help'=>"Cli shell executed after deployment.<br><br>For example, you can use the following shell sequence to enable a module:<br>rm -fr __INSTANCEDIR__/documents/install.lock;<br>cd __INSTANCEDIR__/htdocs/install/;<br>php __INSTANCEDIR__/htdocs/install/upgrade2.php 0.0.0 0.0.0 MAIN_MODULE_MYMODULE;<br>touch __INSTANCEDIR__/documents/install.lock;<br>chown -R __OSUSERNAME__.__OSUSERNAME__ __INSTANCEDIR__/documents;"),
		'cliafterpaid' => array('type'=>'text', 'label'=>'Shell after switch to paid (not yet available)', 'visible'=>3, 'enabled'=>1, 'position'=>66, 'notnull'=>-1, 'help'=>"Cli shell executed after entering a payment (or on deployment if customer already a paying customer)."),
		'sqlafter' => array('type'=>'text', 'label'=>'Sql after deployment', 'visible'=>3, 'enabled'=>1, 'position'=>70, 'notnull'=>-1, 'help'=>'Sql executed after deployment.<br><br>Can use substitution vars like<br>__APPORGNAME__<br>__APPEMAIL__<br>__APPDOMAIN__<br>__APPCOUNTRYIDCODELABEL__<br>__SMTP_SPF_STRING__<br>__OSUSERNAME__<br>__APPPASSWORD0__<br>__APPPASSWORD0SALTED__<br>__APPPASSWORDSHA256__<br>__APPPASSWORDSHA256SALTED__<br>...'),
		'sqlpasswordreset' => array('type'=>'text', 'label'=>'Sql to reset password of a deployed instance user', 'visible'=>3, 'enabled'=>1, 'position'=>71, 'notnull'=>-1, 'help'=>'Sql executed after clicking on cogs on backoffice/instance_users.php after a deployment <br><br> Can use substitution vars like<br>__INSTANCEID__<br>__NEWUSERPASSWORD__<br>__NEWUSERPASSWORDCRYPTED__'),
		'sqlafterpaid' => array('type'=>'text', 'label'=>'Sql after switch to paid (not yet available)', 'visible'=>3, 'enabled'=>1, 'position'=>72, 'notnull'=>-1, 'help'=>'Sql executed after entering a payment (or on deployment if customer already a paying customer)'),
		'allowoverride' => array('type'=>'varchar(255)', 'label'=>'Option string for virtual host', 'visible'=>-1, 'enabled'=>1, 'position'=>75, 'notnull'=>-1, 'help'=>'Any string to add into the Apache virtual host file. For example, keep empty to not allow apache override<br>Use "AllowOverride All" to allow override.'),
		'version_formula' => array('type'=>'text', 'label'=>"VersionFormula",  'visible'=>3, 'enabled'=>1, 'position'=>76, 'help'=>'VersionFormulaExamples', 'lang'=>'sellyoursaas@sellyoursaas'),
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
	public $cliafterpaid;
	public $sqlafter;
	public $sqlpasswordreset;
	public $sqlafterpaid;
	public $allowoverride;
	public $register_text;
	public $status;
	// END MODULEBUILDER PROPERTIES

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
		global $langs, $extrafields, $hookmanager;
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

		// Clear fields
		if (property_exists($object, 'ref')) {
			$object->ref = empty($this->fields['ref']['default']) ? "Copy_Of_".$object->ref : $this->fields['ref']['default'];
		}
		if (property_exists($object, 'label')) {
			$object->label = empty($this->fields['label']['default']) ? $langs->trans("CopyOf")." ".$object->label : $this->fields['label']['default'];
		}
		if (property_exists($object, 'status')) {
			$object->status = self::STATUS_DRAFT;
		}
		if (property_exists($object, 'date_creation')) {
			$object->date_creation = dol_now();
		}
		if (property_exists($object, 'date_modification')) {
			$object->date_modification = null;
		}
		// ...
		// Clear extrafields that are unique
		if (is_array($object->array_options) && count($object->array_options) > 0) {
			$extrafields->fetch_name_optionals_label($this->table_element);
			foreach ($object->array_options as $key => $option) {
				$shortkey = preg_replace('/options_/', '', $key);
				if (!empty($extrafields->attributes[$this->table_element]['unique'][$shortkey])) {
					//var_dump($key); var_dump($clonedObj->array_options[$key]); exit;
					unset($object->array_options[$key]);
				}
			}
		}

		// Create clone
		$object->context['createfromclone'] = 'createfromclone';
		$result = $object->createCommon($user);
		if ($result < 0) {
			$error++;
			$this->error = $object->error;
			$this->errors = $object->errors;
		}

		unset($object->context['createfromclone']);

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
		return $result;
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
		return $this->deleteCommon($user, $notrigger, 0);
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
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $conf, $langs, $hookmanager;
		global $dolibarr_main_authentication, $dolibarr_main_demo;
		global $menumanager;

		if (!empty($conf->dol_no_mouse_hover)) {
			$notooltip = 1; // Force disable tooltips
		}

		$result = '';
		$companylink = '';

		$label = img_picto('', $this->picto).' <u>' . $langs->trans("Packages") . '</u>';
		if (isset($this->status)) {
			$label .= ' '.$this->getLibStatut(5);
		}
		$label .= '<br>';
		$label .= '<b>' . $langs->trans('Ref') . ':</b> ' . $this->ref;
		$label .= '<br><b>' . $langs->trans('Label') . ':</b> ' . $this->label;
		$label .= '<br><b>' . $langs->trans('RestrictDomainNames') . ':</b> ' . $this->restrict_domains;

		$url='';

		if ($option != 'nolink') {
			$url = dol_buildpath('/sellyoursaas/packages_card.php', 1).'?id='.$this->id;

			// Add param to save lastsearch_values or not
			$add_save_lastsearch_values = ($save_lastsearch_value == 1 ? 1 : 0);
			if ($save_lastsearch_value == -1 && preg_match('/list\.php/', $_SERVER["PHP_SELF"])) {
				$add_save_lastsearch_values=1;
			}
			if ($add_save_lastsearch_values) {
				$url .= '&save_lastsearch_values=1';
			}
		}

		$linkclose = '';
		if (empty($notooltip)) {
			if (!empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER)) {
				$label = $langs->trans("ShowPackages");
				$linkclose .= ' alt="'.dol_escape_htmltag($label, 1).'"';
			}
			$linkclose .= ' title="'.dol_escape_htmltag($label, 1).'"';
			$linkclose .= ' class="classfortooltip'.($morecss?' '.$morecss:'').'"';
		} else {
			$linkclose = ($morecss ? ' class="'.$morecss.'"' : '');
		}

		if ($option == 'nolink') {
			$linkstart = '<span';
		} else {
			$linkstart = '<a href="'.$url.'"';
		}
		$linkstart .= $linkclose.'>';
		if ($option == 'nolink') {
			$linkend = '</span>';
		} else {
			$linkend = '</a>';
		}

		$result .= $linkstart;

		if ($withpicto) {
			$result.=img_object(($notooltip?'':$label), ($this->picto?$this->picto:'generic'), ($notooltip?(($withpicto != 2) ? 'class="paddingright"' : ''):'class="'.(($withpicto != 2) ? 'paddingright ' : '').'classfortooltip"'), 0, 0, $notooltip?0:1);
		}
		if ($withpicto != 2) {
			$result .= $this->ref;
		}
		$result .= $linkend;

		return $result;
	}

	/**
	 *  Return the label of the status
	 *
	 *  @param  int		$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string 			       Label of status
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return the status
	 *
	 *  @param	int		$status        	Id status
	 *  @param  int		$mode          	0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return string 			       	Label of status
	 */
	public static function LibStatut($status, $mode = 0)
	{
		// phpcs:enable
		global $langs;

		$labelStatus = $langs->trans('Enabled');
		$labelStatusShort = $langs->trans('Enabled');
		$statusType = 'status4';
		if ($status == 0) {
			$statusType = 'status5';
			$labelStatus = $langs->trans('Disabled');
			$labelStatusShort = $langs->trans('Disabled');
		}

		return dolGetStatus($labelStatus, $labelStatusShort, '', $statusType, $mode);
	}

	/**
	 *	Charge les informations d'ordre info dans l'objet commande
	 *
	 *	@param  int		$id       Id of order
	 *	@return	void
	 */
	public function info($id)
	{
		$sql = 'SELECT rowid, date_creation as datec, tms as datem,';
		$sql.= ' fk_user_creat, fk_user_modif';
		$sql.= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' as t';
		$sql.= ' WHERE t.rowid = '.((int) $id);
		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);
				$this->id = $obj->rowid;
				if ($obj->fk_user_creat) {
					$cuser = new User($this->db);
					$cuser->fetch($obj->fk_user_creat);
					$this->user_creation = $cuser;
				}

				if ($obj->fk_user_modif) {
					$vuser = new User($this->db);
					$vuser->fetch($obj->fk_user_modif);
					$this->user_modification = $vuser;
				}

				$this->date_creation     = $this->db->jdate($obj->datec);
				$this->date_modification = $this->db->jdate($obj->datem);
			}

			$this->db->free($result);
		} else {
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
	 * CAN BE A CRON TASK. In such a case, parameters come from the schedule job setup field 'Parameters'
	 * Use public function doScheduledJob($param1, $param2, ...) to get parameters
	 *
	 * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
	 */
	public function doScheduledJob()
	{
		global $conf, $langs;

		$error = 0;
		$this->output = '';
		$this->error = '';

		dol_syslog(__METHOD__, LOG_DEBUG);

		// ...

		return 0;
	}

	/**
	 * Sets object to supplied categories.
	 *
	 * Deletes object from existing categories not supplied.
	 * Adds it to non existing supplied categories.
	 * Existing categories are left untouch.
	 *
	 * @param int[]|int $categories Category or categories IDs
	 * @return void
	 */
	public function setCategories($categories)
	{
		// Do nothing
	}
}
