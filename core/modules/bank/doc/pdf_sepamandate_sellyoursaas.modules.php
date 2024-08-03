<?php
/* Copyright (C) 2016 Laurent Destailleur <eldy@users.sourceforge.net>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/bank/doc/pdf_sepamandate_sellyoursaas.modules.php
 *	\ingroup    project
 *	\brief      File of class to generate document with template sepamandate
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/bank/modules_bank.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

require_once DOL_DOCUMENT_ROOT.'/core/modules/bank/doc/pdf_sepamandate.modules.php';


/**
 *	Class to generate SEPA mandate for SellyourSaas
 */
class pdf_sepamandate_sellyoursaas extends pdf_sepamandate
{
	/**
	 * Issuer
	 * @var Societe
	 */
	public $emetteur;

	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		global $conf,$langs,$mysoc;

		// Translations
		$langs->loadLangs(array("main", "bank", "withdrawals", "companies"));

		$this->db = $db;
		$this->name = "sepamandate_sellyoursaas";
		$this->description = $langs->transnoentitiesnoconv("SepaMandateForSellYourSaas");

		// Page size for A4 format
		$this->type = 'pdf';
		$formatarray=pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur,$this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

		$this->option_logo = 1; // Display logo FAC_PDF_LOGO
		$this->option_tva = 1; // Manage the vat option FACTURE_TVAOPTION

		// Retrieves transmitter
		$this->emetteur = $mysoc;
		if (!$this->emetteur->country_code) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2); // By default if not defined
		}

		// Define column position
		$this->posxref = $this->marge_gauche;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to create pdf of company bank account sepa mandate
	 *
	 *	@param	CompanyBankAccount	$object   		    Object bank account to generate document for
	 *	@param	    Translate	$outputlangs	    Lang output object
	 *  @param		string		$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int			$hidedetails		Do not show line details (not used for this template)
	 *  @param		int			$hidedesc			Do not show desc (not used for this template)
	 *  @param		int			$hideref			Do not show ref (not used for this template)
	 *  @param      null|array  $moreparams         More parameters
	 *	@return	    int         				    1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		// phpcs:enable
		global $conf, $hookmanager, $langs, $user, $mysoc;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		// Load translation files required by the page
		$outputlangs->loadLangs(array("main", "dict", "withdrawals", "companies", "projects", "bills"));

		if (! empty($conf->bank->dir_output)) {
			//$nblines = count($object->lines);  // This is set later with array of tasks

			// Definition of $dir and $file
			if ($object->specimen) {
				if (!empty($moreparams['force_dir_output'])) {
					$dir = $moreparams['force_dir_output'];
				} else {
					$dir = $conf->bank->dir_output;
				}
				$file = $dir."/SPECIMEN.pdf";
			} else {
				$objectref = dol_sanitizeFileName($object->ref);
				if (!empty($moreparams['force_dir_output'])) {
					$dir = $moreparams['force_dir_output'];
				} else {
					$dir = $conf->bank->dir_output."/".$objectref;
				}
				$file = $dir . "/" . $langs->transnoentitiesnoconv("SepaMandateShort").' '.$objectref . "-".dol_sanitizeFileName($object->rum).".pdf";
			}

			if (! file_exists($dir)) {
				if (dol_mkdir($dir) < 0) {
					$this->error=$langs->transnoentities("ErrorCanNotCreateDir", $dir);
					return 0;
				}
			}

			if (file_exists($dir)) {
				// Add pdfgeneration hook
				if (! is_object($hookmanager)) {
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager=new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters=array('file'=>$file,'object'=>$object,'outputlangs'=>$outputlangs);
				global $action;
				$reshook=$hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks

				$pdf=pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
				$heightforinfotot = 50;	// Height reserved to output the info and total part
				$heightforfreetext= (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT) ? $conf->global->MAIN_PDF_FREETEXT_HEIGHT : 5);	// Height reserved to output the free text on last page
				$heightforfooter = $this->marge_basse + 8;	// Height reserved to output the footer (value include bottom margin)
				if (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS')) {
					$heightforfooter += 6;
				}
				$pdf->SetAutoPageBreak(1, 0);

				if (class_exists('TCPDF')) {
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));

				$pdf->Open();
				$pagenb=0;
				$pdf->SetDrawColor(128, 128, 128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("SepaMandate"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("SepaMandate"));
				if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
					$pdf->SetCompression(false);
				}

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);   // Left, Top, Right

				// New page
				$pdf->AddPage();
				$pagenb++;
				$this->_pagehead($pdf, $object, 1, $outputlangs);
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell(0, 3, '');		// Set interline to 3
				$pdf->SetTextColor(0, 0, 0);

				$tab_top = 50;
				$tab_top_newpage = 40;
				$tab_height = $this->page_hauteur - $tab_top - $heightforfooter - $heightforfreetext;

				// Show notes
				if (! empty($object->note_public)) {
					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxref, $tab_top - 2, dol_htmlentitiesbr($object->note_public), 0, 1);
					$nexY = $pdf->GetY();
					$height_note=$nexY-($tab_top-2);

					// Rect takes a length in 3rd parameter
					$pdf->SetDrawColor(192, 192, 192);
					$pdf->Rect($this->marge_gauche, $tab_top-3, $this->page_largeur-$this->marge_gauche-$this->marge_droite, $height_note+1);

					$tab_height = $tab_height - $height_note;
					$tab_top = $nexY+6;
				} else {
					$height_note=0;
				}

				$iniY = $tab_top + 7;
				$curY = $tab_top + 7;
				$nexY = $tab_top + 7;

				$posY = $curY;

				$pdf->SetFont('', '', $default_font_size);

				$pdf->line($this->marge_gauche, $posY, $this->page_largeur - $this->marge_droite, $posY);
				$posY+=2;

				$pdf->SetXY($this->marge_gauche, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $outputlangs->transnoentitiesnoconv("RUMLong").' ('.$outputlangs->transnoentitiesnoconv("RUM").') : '.$object->rum, 0, 'L');

				$posY=$pdf->GetY();
				$posY+=2;
				$pdf->SetXY($this->marge_gauche, $posY);
				$ics='';
				$idbankfordirectdebit = getDolGlobalInt('PRELEVEMENT_ID_BANKACCOUNT');
				if ($idbankfordirectdebit > 0) {
					$tmpbankfordirectdebit = new Account($this->db);
					$tmpbankfordirectdebit->fetch($idbankfordirectdebit);
					$ics = $tmpbankfordirectdebit->ics;	// ICS for direct debit
				}
				if (empty($ics) && getDolGlobalString('PRELEVEMENT_ICS')) {
					$ics = getDolGlobalString('PRELEVEMENT_ICS');
				}
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $outputlangs->transnoentitiesnoconv("CreditorIdentifier").' ('.$outputlangs->transnoentitiesnoconv("ICS").') : '.$ics, 0, 'L');

				$posY=$pdf->GetY();
				$posY+=1;
				$pdf->SetXY($this->marge_gauche, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $outputlangs->transnoentitiesnoconv("CreditorName").' : '.$mysoc->name, 0, 'L');

				$posY=$pdf->GetY();
				$posY+=1;
				$pdf->SetXY($this->marge_gauche, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $outputlangs->transnoentitiesnoconv("Address").' : ', 0, 'L');
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $mysoc->getFullAddress(1), 0, 'L');

				$posY=$pdf->GetY();
				$posY+=3;

				$pdf->line($this->marge_gauche, $posY, $this->page_largeur - $this->marge_droite, $posY);

				$pdf->SetFont('', '', $default_font_size - 1);

				$posY+=8;
				$pdf->SetXY($this->marge_gauche, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 8, $outputlangs->transnoentitiesnoconv("SEPALegalText", $mysoc->name, $mysoc->name), 0, 'L');

				// Your data form
				$posY=$pdf->GetY();
				$posY+=8;
				$pdf->line($this->marge_gauche, $posY, $this->page_largeur - $this->marge_droite, $posY);
				$posY+=2;

				$pdf->SetFont('', '', $default_font_size);

				$pdf->SetXY($this->marge_gauche, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $outputlangs->transnoentitiesnoconv("SEPAFillForm"), 0, 'C');

				$thirdparty=new Societe($this->db);
				if ($object->socid > 0) {
					$thirdparty->fetch($object->socid);
				}

				$sepaname = '______________________________________________';
				if ($thirdparty->id > 0) {
					$sepaname = $thirdparty->name.($object->proprio ? ' ('.$object->proprio.')' : '');
				}
				$posY=$pdf->GetY();
				$posY+=3;
				$pdf->SetXY($this->marge_gauche, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $outputlangs->transnoentitiesnoconv("SEPAFormYourName").' * : ', 0, 'L');
				$pdf->SetXY(80, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $sepaname, 0, 'L');

				$sepavatid = '__________________________________________________';
				if (!empty($thirdparty->idprof1)) {
					$sepavatid = $thirdparty->idprof1;
				}
				$posY = $pdf->GetY();
				$posY += 1;
				$pdf->SetXY($this->marge_gauche, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $outputlangs->transnoentitiesnoconv('ProfId1'.$thirdparty->country_code).' * : ', 0, 'L');
				$pdf->SetXY(80, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $sepavatid, 0, 'L');

				$address = '______________________________________________';
				if ($thirdparty->id > 0) {
					$tmpaddresswithoutcountry = $thirdparty->getFullAddress();	// we test on address without country
					if ($tmpaddresswithoutcountry) {
						$address = $thirdparty->getFullAddress(1);	// full address
					}
				}
				$posY=$pdf->GetY();
				$posY+=1;
				$pdf->SetXY($this->marge_gauche, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $outputlangs->transnoentitiesnoconv("Address").' : ', 0, 'L');
				$pdf->SetXY(80, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $address, 0, 'L');
				if (preg_match('/_____/', $address)) {
					$posY+=6;
					$pdf->SetXY(80, $posY);
					$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $address, 0, 'L');
				}

				$ban = '__________________________________________________';
				if (!empty($object->iban)) {
					$ban = $object->iban;
				}
				$posY=$pdf->GetY();
				$posY+=1;
				$pdf->SetXY($this->marge_gauche, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $outputlangs->transnoentitiesnoconv("SEPAFormYourBAN").' * : ', 0, 'L');
				$pdf->SetXY(80, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $ban, 0, 'L');

				$bic = '__________________________________________________';
				if (!empty($object->bic)) {
					$bic = $object->bic;
				}
				$posY=$pdf->GetY();
				$posY+=1;
				$pdf->SetXY($this->marge_gauche, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $outputlangs->transnoentitiesnoconv("SEPAFormYourBIC").' * : ', 0, 'L');
				$pdf->SetXY(80, $posY);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $bic, 0, 'L');


				$posY=$pdf->GetY();
				$posY+=1;
				$pdf->SetXY($this->marge_gauche, $posY);
				$txt = $outputlangs->transnoentitiesnoconv("SEPAFrstOrRecur").' * : ';
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $txt, 0, 'L');
				$pdf->Rect(80, $posY, 5, 5);
				$pdf->SetXY(80, $posY);
				if ($object->frstrecur == 'RECUR' || $object->frstrecur == 'RCUR') {
					$pdf->MultiCell(5, 3, 'X', 0, 'L');
				}
				$pdf->SetXY(86, $posY);
				$txt = $langs->transnoentitiesnoconv("ModeRECUR").'  '.$langs->transnoentitiesnoconv("or");
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $txt, 0, 'L');
				$posY+=6;
				$pdf->Rect(80, $posY, 5, 5);
				$pdf->SetXY(80, $posY);
				if ($object->frstrecur == 'FRST') {
					$pdf->MultiCell(5, 3, 'X', 0, 'L');
				}
				$pdf->SetXY(86, $posY);
				$txt = $langs->transnoentitiesnoconv("ModeFRST");
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $txt, 0, 'L');
				if (empty($object->frstrecur)) {
					$posY+=6;
					$pdf->SetXY(80, $posY);
					$txt = '('.$langs->transnoentitiesnoconv("PleaseCheckOne").')';
					$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 3, $txt, 0, 'L');
				}

				$posY=$pdf->GetY();
				$posY+=3;
				$pdf->line($this->marge_gauche, $posY, $this->page_largeur - $this->marge_droite, $posY);
				$posY+=3;


				// Show square
				if ($pagenb == 1) {
					$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0);
					$bottomlasttab=$this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				} else {
					$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0);
					$bottomlasttab=$this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				}

				//var_dump($tab_top);
				//var_dump($heightforinfotot);
				//var_dump($heightforfreetext);
				//var_dump($heightforfooter);
				//var_dump($bottomlasttab);

				// Affiche zone infos
				$posy=$this->_tableau_info($pdf, $object, $bottomlasttab, $outputlangs);

				/*
				 * Footer of the page
				 */
				$this->_pagefoot($pdf, $object, $outputlangs);
				if (method_exists($pdf, 'AliasNbPages')) {
					$pdf->AliasNbPages();
				}

				$pdf->Close();

				$pdf->Output($file, 'F');

				// Add pdfgeneration hook
				if (! is_object($hookmanager)) {
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager=new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters=array('file'=>$file,'object'=>$object,'outputlangs'=>$outputlangs);
				global $action;
				$reshook=$hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);    // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0) {
					$this->error = $hookmanager->error;
					$this->errors = $hookmanager->errors;
				}

				dolChmod($file);

				$this->result = array('fullpath'=>$file);

				return 1;   // No error
			} else {
				$this->error=$langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		}

		$this->error = $langs->transnoentities("ErrorConstantNotDefined", "DELIVERY_OUTPUTDIR");
		return 0;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *   Show miscellaneous information (payment mode, payment term, ...)
	 *
	 *   @param		TCPDF				$pdf     		Object PDF
	 *   @param		CompanyBankAccount	$object			Object to show
	 *   @param		int			$posy			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @return	float
	 */
	protected function _tableau_info(&$pdf, $object, $posy, $outputlangs)
	{
		// phpcs:enable
		global $conf, $mysoc;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$diffsizetitle=(!getDolGlobalString('PDF_DIFFSIZE_TITLE') ? 1 : $conf->global->PDF_DIFFSIZE_TITLE);

		$posy+=$this->_signature_area($pdf, $object, $posy, $outputlangs);

		$pdf->SetXY($this->marge_gauche, $posy);
		$pdf->SetFont('', '', $default_font_size);
		$pdf->MultiCell(100, 3, $outputlangs->transnoentitiesnoconv("PleaseReturnMandate", (!getDolGlobalString('SELLYOURSAAS_MAIN_EMAIL') ? $mysoc->email : $conf->global->SELLYOURSAAS_MAIN_EMAIL)), 0, 'L', 0);
		$posy=$pdf->GetY()+2;

		$pdf->SetXY($this->marge_gauche, $posy);
		$pdf->SetFont('', '', $default_font_size - $diffsizetitle);
		$pdf->MultiCell(100, 6, $mysoc->name, 0, 'L', 0);
		$pdf->MultiCell(100, 6, $outputlangs->convToOutputCharset($mysoc->getFullAddress(1)), 0, 'L', 0);
		$posy=$pdf->GetY()+2;

		return $posy;
	}
}
