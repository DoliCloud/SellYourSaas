<?php
/* Copyright (C) 2011-2020 Laurent Destailleur <eldy@users.sourceforge.net>
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

// Protection to avoid direct call of template
if (empty($conf) || ! is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}

?>
<!-- BEGIN PHP TEMPLATE myaccount.tpl.php -->
<?php

$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;
if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
	&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
	$newnamekey = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
	if (! empty($conf->global->$newnamekey)) $sellyoursaasname = $conf->global->$newnamekey;
}

print '
	<div class="page-content-wrapper">
			<div class="page-content">


	     <!-- BEGIN PAGE HEADER-->
	<!-- BEGIN PAGE HEAD -->
	<div class="page-head">
	  <!-- BEGIN PAGE TITLE -->
	<div class="page-title">
	  <h1>'.$langs->trans("MyAccount").' <small>'.$langs->trans("YourPersonalInformation").'</small></h1>
	</div>
	<!-- END PAGE TITLE -->


	</div>
	<!-- END PAGE HEAD -->
	<!-- END PAGE HEADER-->


	<div class="row">
	      <div class="col-md-6">

	        <div class="portlet light">
	          <div class="portlet-title">
	            <div class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("Organization").'</div>
	          </div>
	          <div class="portlet-body">

	            <form action="'.$_SERVER["PHP_SELF"].'" method="post" name="formsoc">
	            <input type="hidden" name="token" value="'.newToken().'">
				<input type="hidden" name="action" value="updatemythirdpartyaccount">
				<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">

	              <div class="form-body">

	                <div class="form-group">
	                  <label>'.$langs->trans("NameOfCompany").'</label>
	                  <input type="text" class="form-control" placeholder="'.$langs->trans("NameOfYourOrganization").'" value="'.$mythirdpartyaccount->name.'" name="orgName">
	                </div>

	                <div class="form-group">
	                  <label>'.$langs->trans("AddressLine").'</label>
	                  <textarea class="form-control" placeholder="'.$langs->trans("HouseNumberAndStreet").'" name="address">'.$mythirdpartyaccount->address.'</textarea>
	                </div>
	                <div class="form-group">
	                  <label>'.$langs->trans("Town").'</label>
	                  <input type="text" class="form-control" value="'.$mythirdpartyaccount->town.'" name="town">
	                </div>
	                <div class="form-group">
	                  <label>'.$langs->trans("Zip").'</label>
	                  <input type="text" class="form-control input-small" value="'.$mythirdpartyaccount->zip.'" name="zip">
	                </div>
	                <div class="form-group">
	                  <label>'.$langs->trans("State").'</label>
	                  <input type="text" class="form-control" placeholder="'.$langs->trans("StateOrCounty").'" name="stateorcounty" value="">
	                </div>
	                <div class="form-group">
	                  <label>'.$langs->trans("Country").'</label><br>';
					$countryselected = (GETPOSTISSET('country_id')?GETPOST('country_id', 'aZ09'):$mythirdpartyaccount->country_id);
					$exclude_country_code = array();
					if (! empty($conf->global->SELLYOURSAAS_EXCLUDE_COUNTRY_CODES)) $exclude_country_code = explode(',', $conf->global->SELLYOURSAAS_EXCLUDE_COUNTRY_CODES);
					print '<input type="hidden" name="country_id_old" value="'.$countryselected.'">'."\n";
					print $form->select_country($countryselected, 'country_id', '', 0, 'minwidth300', 'code2', 0, 1, 0, $exclude_country_code);
					print '
	                                </div>
	                                <div class="form-group">
	                                  <label>'.$langs->trans("VATIntra").'</label> ';
if (! empty($mythirdpartyaccount->tva_assuj) && empty($mythirdpartyaccount->tva_intra)) {
	print img_warning($langs->trans("Mandatory"), 'class="hideifnonassuj"');
}

					$placeholderforvat='';
					if ($mythirdpartyaccount->country_code == 'FR') $placeholderforvat='Exemple: FR12345678';
					elseif ($mythirdpartyaccount->country_code == 'BE') $placeholderforvat='Exemple: BE12345678';
					elseif ($mythirdpartyaccount->country_code == 'ES') $placeholderforvat='Exemple: ES12345678';
else $placeholderforvat=$langs->trans("EnterVATHere");

					print '
						<br>
	                  <input type="hidden" name="vatassuj_old" value="'.($mythirdpartyaccount->tva_assuj).'">
	                  <input type="checkbox" style="margin-bottom: 3px;" class="inline-block valignmiddle"'.($mythirdpartyaccount->tva_assuj?' checked="checked"':'').' id="vatassuj" name="vatassuj"> <label for="vatassuj" class="valignmiddle nobold">'.$langs->trans("IHaveAVATID").'</label>
						<br>
	                  <input type="hidden" name="vatnumber_old" value="'.$mythirdpartyaccount->tva_intra.'">
	                  <input type="text" class="input-small quatrevingtpercent hideifnonassuj" value="'.$mythirdpartyaccount->tva_intra.'" name="vatnumber" id="vatnumber" placeholder="'.$placeholderforvat.'">
	                    ';
					print "\n";
					print '<script>';
					print '$( document ).ready(function() {'."\n";
					print '$("#vatnumber").keyup(function() {'."\n";
					print "   console.log('We change the vatnumber='+$('#vatnumber').val());\n";
					print "   if ($('#vatnumber').val() != '')  { $('#vatassuj').prop('checked', true ); }\n";
					print '});'."\n";
					print '});'."\n";
					print '</script>';
					print "\n";

if (empty($conf->global->MAIN_DISABLEVATCHECK) && $mythirdpartyaccount->isInEEC() && (GETPOST('admin', 'alpha'))) {
	if (! empty($conf->use_javascript_ajax)) {
		print "\n";
		print '<script language="JavaScript" type="text/javascript">';
		print "function CheckVAT(a) {\n";
		print "newpopup('".DOL_URL_ROOT."/societe/checkvat/checkVatPopup.php?vatNumber='+a,'".dol_escape_js($langs->trans("VATIntraCheckableOnEUSite"))."', 540, 350);\n";
		print "}\n";
		print '</script>';
		print "\n";
		$s.='<a href="#" class="hideonsmartphone" onclick="javascript: CheckVAT(document.formsoc.vatnumber.value);">'.$langs->trans("VATIntraCheck").'</a>';
		$s = $form->textwithpicto($s, $langs->trans("VATIntraCheckDesc", $langs->transnoentitiesnoconv("VATIntraCheck")), 1);
	} else {
		$s.='<a href="'.$langs->transcountry("VATIntraCheckURL", $mythirdpartyaccount->country_id).'" target="_blank">'.img_picto($langs->trans("VATIntraCheckableOnEUSite"), 'help').'</a>';
	}
	print $s;
}
					print '
	                </div>
	              </div>
	              <!-- END FORM BODY -->

	              <div class="center">
	                <input type="submit" name="submit" value="'.$langs->trans("Save").'" class="btn green-haze btn-circle">
	              </div>

	            </form>
	            <!-- END FORM DIV -->
	           </div> <!-- END PORTLET-BODY -->
            </div>
';

if (! GETPOST('deleteaccount') && ($mythirdpartyaccount->array_options['options_checkboxnonprofitorga'] != 'nonprofit' || !getDolGlobalInt("SELLYOURSAAS_ENABLE_FREE_PAYMENT_MODE"))) {
	print '<div class="center"><br>';
	$urltoenterpaymentmode = $_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode);
	print '<a href="'.$urltoenterpaymentmode.'" class=""><span class="fa fa-credit-card paddingright"></span>';
	if ($nbpaymentmodeok) print $langs->trans("ModifyPaymentMode").'...';
	else print $langs->trans("AddAPaymentMode").'...';
	print '</a>';
	print '<br><br><br>';
	print '</div>';
}

print '

	      </div>
		';

print '<script type="text/javascript" language="javascript">
		jQuery(document).ready(function() {
			jQuery("#vatassuj").click(function() {
				console.log("Click on vatassuj "+jQuery("#vatassuj").is(":checked"));
				jQuery(".hideifnonassuj").hide();
				jQuery(".hideifnonassuj").show();
				jQuery("#vatnumber").focus();
			});
		});
		</script>';

print '

	      <div class="col-md-6">

			<div class="portlet light">
	          <div class="portlet-title">
	            <div class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("YourContactInformation").'</div>
	          </div>
	          <div class="portlet-body">

	            <form action="'.$_SERVER["PHP_SELF"].'" method="post">
                <input type="hidden" name="token" value="'.newToken().'">
				<input type="hidden" name="action" value="updatemythirdpartylogin">
				<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">

	              <div class="form-body">
	                <div class="form-group">
	                  <label>'.img_picto('', 'email', 'class="paddingright"').$langs->trans("Email").'</label>
	                  <input type="text" class="form-control" value="'.((GETPOSTISSET('email') && GETPOST('email')) ? GETPOST('email') : $mythirdpartyaccount->email).'" name="email">
	                  <input type="hidden" class="form-control" value="'.$mythirdpartyaccount->email.'" name="oldemail">
					</div>
					<div class="form-group">
					  <label>'.img_picto('', 'phone', 'class="paddingright"').$langs->trans("PhoneNumber").'</label>
	                  <input type="text" class="form-control" value="'.$mythirdpartyaccount->phone.'" name="phone">
	                  <input type="hidden" class="form-control" value="'.$mythirdpartyaccount->phone.'" name="oldphone">
					</div>
	                <div class="row">
	                  <div class="col-md-6">
	                    <div class="form-group">
	                      <label>'.$langs->trans("Firstname").'</label> ';
					if (empty($mythirdpartyaccount->array_options['options_firstname'])) print img_warning($langs->trans("Mandatory"));
					print '
							<br>
	                      <input type="text" class="inline-block" value="'.$mythirdpartyaccount->array_options['options_firstname'].'" name="firstName">
	                    </div>
	                  </div>
	                  <div class="col-md-6">
	                    <div class="form-group">
	                      <label>'.$langs->trans("Lastname").'</label> ';
					if (empty($mythirdpartyaccount->array_options['options_lastname'])) print img_warning($langs->trans("Mandatory"));
					print '<br>
	                      <input type="text" class="inline-block" value="'.$mythirdpartyaccount->array_options['options_lastname'].'" name="lastName">
	                    </div>
	                  </div>
	                </div>
	                <div class="form-group">
	                  <label>'.img_picto('', 'email', 'class="paddingright opacitymedium"').$form->textwithpicto($langs->trans("EmailCCInvoices"), $langs->trans("KeepEmptyToUseMainEmail"), 1, 'help', 'opacitymedium').'</label>
	                  <input type="text" class="form-control" value="'.(GETPOSTISSET('emailccinvoice') ? GETPOST('emailccinvoice') : $mythirdpartyaccount->array_options['options_emailccinvoice']).'" name="emailccinvoice">
	                  <input type="hidden" class="form-control" value="'.$mythirdpartyaccount->array_options['options_emailccinvoice'].'" name="oldemailccinvoice">
					</div>

					';
if (! empty($conf->global->SELLYOURSAAS_ENABLE_OPTINMESSAGES)) {
	print '
		                <div class="form-group paddingtop">
		                  <!--<label>'.$langs->trans("OptinForCommercialMessages").'</label><br>-->
		                  <input type="checkbox" class="form-control inline valignmiddle" style="margin-top: 0" value="1" '.($mythirdpartyaccount->array_options['options_optinmessages'] ? ' checked' : '').' id="optinmessages" name="optinmessages">
							<label for="optinmessages" class="valignmiddle nobold inline"><span class="inline valignmiddle opacitymedium small">'.$langs->trans("OptinForCommercialMessagesOnMyAccount", $sellyoursaasname).'</span></label>
		                </div>';
}
					print '
	              </div>
	              <div class="center">
	                <input type="submit" name="submit" value="'.$langs->trans("Save").'" class="btn green-haze btn-circle">
	              </div>

	            </form>

	          </div>
	        </div>


			<div class="portlet light">
	          <div class="portlet-title">
	            <div class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("Password").'</div>
	          </div>
	          <div class="portlet-body">

                <form action="'.$_SERVER["PHP_SELF"].'" method="post" id="updatepassword">
                <input type="hidden" name="token" value="'.newToken().'">
				<input type="hidden" name="action" value="updatepassword">
				<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">

	              <div class="form-body">
	                <div class="form-group">
	                  <label>'.$langs->trans("Password").'</label>
	                  <input type="password" class="form-control" name="password" required minlength="8" maxlength="128" autocomplete="new-password" spellcheck="false" autocapitalize="off">
	                </div>
	                <div class="form-group">
	                  <label>'.$langs->trans("RepeatPassword").'</label>
	                  <input type="password" class="form-control" name="password2" required minlength="8" maxlength="128" autocomplete="new-password" spellcheck="false" autocapitalize="off">
	                </div>
	              </div>
	              <div class="center">
	                <input type="submit" name="submit" value="'.$langs->trans("ChangePassword").'" class="btn green-haze btn-circle">
	              </div>

	            </form>

	          </div>
	        </div>


			';

if (! GETPOST('deleteaccount')) {
	print '<div class="center"><br><a href="#deletemyaccountarea" class="deletemyaccountclick"><span class="fa fa-trash paddingright"></span>'.$langs->trans("DeleteMyAccount").'...</a><br><br><br></div>';
}

			print '
			<script type="text/javascript" language="javascript">
			jQuery(document).ready(function() {
				';

				if (! GETPOST('deleteaccount')) print 'jQuery("#deletemyaccountarea").hide();';

				print '
				jQuery(".deletemyaccountclick").click(function() {
					console.log("Click on deletemyaccountclick");
					jQuery("#deletemyaccountarea").toggle();
					jQuery(".deletemyaccountclick").toggle();
				});
			});
			</script>

			<div class="portlet light deletemyaccountarea" id="deletemyaccountarea">
	          <div class="portlet-title">
	            <div class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("DeleteMyAccount").'</div>
	          </div>
	          <div class="portlet-body">
							<form class="form-group" action="'.$_SERVER["PHP_SELF"].'" method="POST">
                            <input type="hidden" name="token" value="'.newToken().'">

				              <div class="">
				                <p class="opacitymedium error" style="padding: 5px">
				                    ';
if (($nbofinstancesinprogressreseller + $nbofinstancesdonereseller + $nbofinstancessuspendedreseller) > 0) {
	print $langs->trans("ClosingAccountResellerNotPossible", ($nbofinstancesinprogressreseller + $nbofinstancesdonereseller + $nbofinstancessuspendedreseller), $langs->transnoentities("MyInstances"), $langs->transnoentities("DangerZone")).'<br>';
} elseif (($nbofinstancesinprogress + $nbofinstancesdone + $nbofinstancessuspended) > 0) {
	print $langs->trans("ClosingAccountNotPossible", ($nbofinstancesinprogress + $nbofinstancesdone + $nbofinstancessuspended), $langs->transnoentities("MyInstances"), $langs->transnoentities("DangerZone")).'<br>';
} elseif (!empty($conf->global->SELLYOURSAAS_DISABLE_NEW_INSTANCES)) {
	print '<!-- ClosingAccountIsTemporarlyDisabledTryLater -->'."\n";
	print $langs->trans("ClosingAccountIsTemporarlyDisabledTryLater").'<br>';
} else {
	print $langs->trans("PleaseBeSureCustomerAccount", $contract->ref_customer);
	print '
						                </p>
										<p class="center" style="padding-bottom: 15px">
											<input type="text" class="urlofinstancetodestroy" name="accounttodestroy" value="'.GETPOST('accounttodestroy', 'alpha').'" placeholder="'.$langs->trans("EmailOfAccountToDestroy").'" autofocus>
										</p>
										<p class="center">
											<input type="hidden" name="mode" value="myaccount"/>
											<input type="hidden" name="action" value="deleteaccount" />
											<input type="submit" class="btn btn-danger" name="deleteaccount" value="'.$langs->trans("DeleteMyAccount").'">
										';
}
									print '</p>
				              </div>

							</form>
				</div>
			</div>


	      </div><!-- END COL -->

	    </div> <!-- END ROW -->


	    </div>
		</div>
	';
?>
<!-- END PHP TEMPLATE myaccount.tpl.php -->
