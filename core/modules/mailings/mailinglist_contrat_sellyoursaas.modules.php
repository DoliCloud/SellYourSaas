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
include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';


/**
 * mailing_resellers_sellyoursaas
 */
class mailing_mailinglist_contrat_sellyoursaas extends MailingTargets
{
	public $name = 'mailinglist_contrat_sellyoursaas';
	public $desc = 'Prospects or Customers SellYourSaas with open recurring invoice';
	public $require_admin = 0;

	public $enabled = '$conf->sellyoursaas->enabled';

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
		global $langs; //TODO:
		$langs->load("members");

		$form = new Form($this->db);
		$formother = new FormAdmin($this->db);

		$arraystatus=array('processing'=>'Processing','done'=>'Done','undeployed'=>'Undeployed');

		$s = '';

		$s.=img_picto($langs->trans("Language"), 'language', 'class="pictofixedwidth"');
		$s .= $formother->select_language(GETPOST('contract_sellyoursaas_lang_id', 'array'), 'contract_sellyoursaas_lang_id', 0, null, $langs->trans("Language"), 0, 0, '', 0, 0, 1);

		/*
		$s .= ' &nbsp; ';

		$s .= $langs->trans("DeploymentStatus").': ';
		$s .= '<select name="contract_sellyoursaas_filter" class="flat">';
		$s .= '<option value="none">&nbsp;</option>';
		foreach ($arraystatus as $key => $status) {
			$s .= '<option value="'.$key.'"'.(GETPOST('contract_sellyoursaas_filter', 'alpha') == $key ? ' selected':'').'>'.$status.'</option>';
		}
		$s .= '</select>';
		*/
		$s .= '<br>';

		$s .= img_picto('', 'product');
		$s .= $form->select_produits(GETPOST('contract_sellyoursaas_productid', 'int'), 'contract_sellyoursaas_productid', '', 20, 0, 1, 2, '', 0, array(), 0, '1', 0, '', 0, '', array(), 1);
		$s .= '<br>';

		$s .= '<input value="'.GETPOST('contractpricetotal', 'int').'" name="contractpricetotal" class="width100 right marginrightonly" placeholder="'.$langs->trans("UnitPriceOfLine").'">';
		$s .= '<input value="'.GETPOST('quantityproduct', 'int').'" name="quantityproduct" class="width100 right marginrightonly" placeholder="'.$langs->trans("Quantity").'">';
		$s .= '<input value="'.GETPOST('discountproduct', 'int').'" name="discountproduct" class="width100 right marginrightonly" placeholder="'.$langs->trans("Discount").'">';
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
	 *  @param	array	$filtersarray   Some filter parameters to select targeted recipients
	 *  @return int           			<0 if error, number of emails added if ok
	 */
	public function add_to_target($mailing_id, $filtersarray = array())
	{
		// phpcs:enable
		global $conf;

		$target = array();
		$cibles = array();
		$j = 0;

		$productid = GETPOST('contract_sellyoursaas_productid', 'int');
		$contractpricetotal = price2num(GETPOST('contractpricetotal'));
		$quantityproduct = price2num(GETPOST('quantityproduct'));
		$discountproduct = price2num(GETPOST('discountproduct'));

		$sql = " SELECT DISTINCT s.rowid as id, s.email, s.nom as lastname, '' as firstname, s.default_lang, c.code as country_code, c.label as country_label";
		$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields as se on se.fk_object = s.rowid";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_country as c on s.fk_pays = c.rowid";

		/*if ((! empty($_POST['contract_sellyoursaas_filter']) && $_POST['contract_sellyoursaas_filter'] != 'none') || (! empty($_POST['filterip']) && $_POST['filterip'] != 'none') || ($productid > 0)) {
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."contrat as co on co.fk_soc = s.rowid";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as coe on coe.fk_object = co.rowid";
		}*/

		if ($contractpricetotal > 0 || $productid > 0 || $quantityproduct > 0 || $discountproduct != '') {
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture_rec as fr on fr.fk_soc = s.rowid and fr.suspended = 0";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facturedet_rec as fdr on fdr.fk_facture = fr.rowid";
		}
		$sql .= " WHERE email IS NOT NULL AND email <> ''";
		if (GETPOST('contract_sellyoursaas_lang_id') && GETPOST('contract_sellyoursaas_lang_id') != 'none') {
			$sql.= natural_search('default_lang', join(',', GETPOST('contract_sellyoursaas_lang_id', 'array')), 3);
		}
		/*if (GETPOST('contract_sellyoursaas_filter') && GETPOST('contract_sellyoursaas_filter') != 'none') {
			$sql.= " AND coe.deployment_status = '".$this->db->escape(GETPOST('contract_sellyoursaas_filter'))."'";
		}*/
		if ($quantityproduct > 0) {
			$sql .= " AND fdr.qty = ".((float) $quantityproduct);
		}
		if ($productid > 0) {
			$sql .= " AND fdr.fk_product = ".((int) $productid);
		}
		if ($discountproduct != '') {
			if (empty($discountproduct)) {
				$sql .= " AND (fdr.remise_percent IS NULL or fdr.remise_percent = 0)";
			} else {
				$sql .= " AND fdr.remise_percent = ".((float) $discountproduct);
			}
		}
		if ($contractpricetotal > 0) {
			$sql .= " AND fdr.subprice = ".((float) $contractpricetotal);
		}
		if (empty($this->evenunsubscribe)) {
			$sql .= " AND NOT EXISTS (SELECT rowid FROM ".MAIN_DB_PREFIX."mailing_unsubscribe as mu WHERE mu.email = s.email and mu.entity = ".((int) $conf->entity).")";
		}

		$sql.= " ORDER BY email";
		//print $sql;

		$this->sql = $sql;

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

		$sql = "SELECT COUNT(DISTINCT(email)) as nb FROM ".MAIN_DB_PREFIX."societe as s WHERE email IS NOT NULL AND email <> ''";
		if (empty($this->evenunsubscribe)) {
			$sql .= " AND NOT EXISTS (SELECT rowid FROM ".MAIN_DB_PREFIX."mailing_unsubscribe as mu WHERE mu.email = s.email and mu.entity = ".((int) $conf->entity).")";
		}

		$a = parent::getNbOfRecipients($sql);
		if ($a < 0) {
			return -1;
		}
		return $a;
	}
}
