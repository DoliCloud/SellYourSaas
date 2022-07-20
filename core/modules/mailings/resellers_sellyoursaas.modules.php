<?php
/* Copyright (C) 2005-2018 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This file is an example to follow to add your own email selector inside
 * the Dolibarr email tool.
 * Follow instructions given in README file to know what to change to build
 * your own emailing list selector.
 * Code that need to be changed in this file are marked by "CHANGE THIS" tag.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/mailings/modules_mailings.php';
include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';


/**
 * mailing_resellers_sellyoursaas
 */
class mailing_resellers_sellyoursaas extends MailingTargets
{
	public $name = 'mailing_resellers';
	public $desc = 'Resellers SellYourSaas';
	public $require_admin = 0;

	public $enabled = '$conf->sellyoursaas->enabled && !empty($conf->global->SELLYOURSAAS_ALLOW_RESELLER_PROGRAM)';

	public $require_module = array();
	public $picto = 'sellyoursaas@sellyoursaas';
	public $db;


	/**
	 *	Constructor
	 *
	 * 	@param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db=$db;
	}


	/**
	 *   Affiche formulaire de filtre qui apparait dans page de selection des destinataires de mailings
	 *
	 *   @return     string      Retourne zone select
	 */
	public function formFilter()
	{
		global $langs;
		$langs->load("members");

		$form=new Form($this->db);
		$formother=new FormAdmin($this->db);

		$arraystatus=array('1'=>'Open', '0'=>'Closed');
		$s.=$langs->trans("Status").': ';
		$s.=$form->selectarray('status_reseller', $arraystatus, GETPOST('status_reseller', 'alpha'), 1);

		$s.=$langs->trans("Country").': ';
		$s.=$form->select_country(GETPOST('country_id_reseller', 'alpha'), 'country_id_reseller', '', 0, 'minwidth300', '', 1);

		$s.='<br> ';

		$s.=$langs->trans("Language").': ';

		$s.=$formother->select_language(GETPOST('lang_id_reseller', 'array'), 'lang_id_reseller', 0, 'null', 1, 0, 0, '', 0, 0, 1);

		$s.=$langs->trans("NotLanguage").': ';
		$formother=new FormAdmin($this->db);
		$s.=$formother->select_language(GETPOST('not_lang_id_reseller', 'array'), 'not_lang_id_reseller', 0, 'null', 1, 0, 0, '', 0, 0, 1);

		return $s;
	}


	/**
	 *  Renvoie url lien vers fiche de la source du destinataire du mailing
	 *
	 *  @param		int			$id		ID
	 *  @return     string      		Url lien
	 */
	public function url($id)
	{
		return '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$id.'">'.img_object('', "company").'</a>';
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  This is the main function that returns the array of emails
	 *
	 *  @param	int		$mailing_id    	Id of emailing
	 *  @param	array	$filtersarray   Requete sql de selection des destinataires
	 *  @return int           			<0 if error, number of emails added if ok
	 */
	public function add_to_target($mailing_id, $filtersarray = array())
	{
		// phpcs:enable
		global $conf;

		$target = array();
		$cibles = array();
		$j = 0;

		if (GETPOSTISSET('lang_id_reseller') && is_array($_POST['lang_id_reseller'])) {
			foreach ($_POST['lang_id_reseller'] as $key => $val) {
				if (empty($val)) {
					unset($_POST['lang_id_reseller'][$key]);
				}
			}
		}
		if (GETPOSTISSET('not_lang_id_reseller') && is_array($_POST['not_lang_id_reseller'])) {
			foreach ($_POST['not_lang_id_reseller'] as $key => $val) {
				if (empty($val)) {
					unset($_POST['not_lang_id_reseller'][$key]);
				}
			}
		}

		$productid = GETPOST('productid', 'int');

		$sql = " SELECT s.rowid as id, s.email, s.nom as lastname, '' as firstname, s.default_lang, c.code as country_code, c.label as country_label";
		$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields as se on se.fk_object = s.rowid";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_country as c on s.fk_pays = c.rowid";
		$sql .= ", ".MAIN_DB_PREFIX."categorie_fournisseur as cs";
		$sql .= " WHERE email IS NOT NULL AND email <> ''";
		$sql .= " AND cs.fk_soc = s.rowid AND cs.fk_categorie = ".((int) $conf->global->SELLYOURSAAS_DEFAULT_RESELLER_CATEG);
		if ( GETPOST('status_reseller', 'int') >= 0) {
			$sql .= " AND s.status = ".((int) GETPOST('status_reseller', 'int'));
		}
		if (GETPOST('lang_id_reseller') && GETPOST('lang_id_reseller') != 'none') $sql.= natural_search('default_lang', join(',', GETPOST('lang_id_reseller', 'array')), 3);
		if (GETPOST('not_lang_id_reseller') && GETPOST('not_lang_id_reseller') != 'none') $sql.= natural_search('default_lang', join(',', GETPOST('not_lang_id_reseller', 'array')), -3);
		if (GETPOST('country_id_reseller') && GETPOST('country_id_reseller') != 'none') $sql.= " AND fk_pays IN ('".$this->db->sanitize(GETPOST('country_id_reseller', 'intcomma'), 1)."')";

		$sql.= " ORDER BY email";
		//print $sql;

		// Stocke destinataires dans cibles
		$result=$this->db->query($sql);
		if ($result) {
			$num = $this->db->num_rows($result);
			$i = 0;

			dol_syslog("resellers_sellyoursaas.modules.php: mailing $num target found");

			$old = '';
			while ($i < $num) {
				$obj = $this->db->fetch_object($result);
				if ($old <> $obj->email) {
					$cibles[$j] = array(
						'email' => $obj->email,
						'lastname' => $obj->lastname,
						'id' => $obj->id,
						'firstname' => $obj->firstname,
						'other' => 'lang='.$obj->default_lang.';country_code='.$obj->country_code,
						'source_url' => $this->url($obj->id),
						'source_id' => $obj->id,
						'source_type' => 'thirdparty'
					);
					$old = $obj->email;
					$j++;
				}

				$i++;
			}
		} else {
			dol_syslog($this->db->error());
			$this->error=$this->db->error();
			return -1;
		}

		// You must fill the $target array with record like this
		// $target[0]=array('email'=>'email_0','name'=>'name_0','firstname'=>'firstname_0');
		// ...
		// $target[n]=array('email'=>'email_n','name'=>'name_n','firstname'=>'firstname_n');

		// Example: $target[0]=array('email'=>'myemail@mydomain.com','name'=>'Doe','firstname'=>'John');

		// ----- Your code end here -----

		return parent::addTargetsToDatabase($mailing_id, $cibles);
	}


	/**
	 *	On the main mailing area, there is a box with statistics.
	 *	If you want to add a line in this report you must provide an
	 *	array of SQL request that returns two field:
	 *	One called "label", One called "nb".
	 *
	 *	@return		array
	 */
	public function getSqlArrayForStats()
	{
		// CHANGE THIS: Optionnal

		//var $statssql=array();
		//$this->statssql[0]="SELECT field1 as label, count(distinct(email)) as nb FROM mytable WHERE email IS NOT NULL";

		return array();
	}


	/**
	 *	Return here number of distinct emails returned by your selector.
	 *	For example if this selector is used to extract 500 different
	 *	emails from a text file, this function must return 500.
	 *
	 *	@param	string	$filter		Filter
	 *	@param	string	$option		Options
	 *	@return	int					Nb of recipients
	 */
	public function getNbOfRecipients($filter = 1, $option = '')
	{
		global $conf;

		$sql = "SELECT COUNT(DISTINCT(email)) as nb FROM ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."categorie_fournisseur as cs WHERE s.email IS NOT NULL AND s.email <> ''";
		$sql .= " AND cs.fk_soc = s.rowid AND cs.fk_categorie = ".((int) $conf->global->SELLYOURSAAS_DEFAULT_RESELLER_CATEG);

		$a = parent::getNbOfRecipients($sql);
		if ($a < 0) return -1;
		return $a;
	}
}
