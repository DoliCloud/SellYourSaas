<?php
/* Copyright (C) 2011-2018 Laurent Destailleur <eldy@users.sourceforge.net>
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
	exit(1);
}

?>
<!-- BEGIN PHP TEMPLATE becomereseller.tpl.php -->
<?php

$sellyoursaasname = getDolGlobalString('SELLYOURSAAS_NAME');
if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
	$newnamekey = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
	if (getDolGlobalString($newnamekey)) {
		$sellyoursaasname = getDolGlobalString($newnamekey);
	}
}

// Print warning to read FAQ before
$url = getDolGlobalString('SELLYOURSAAS_RESELLER_URL');
if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
	$newnamekey = 'SELLYOURSAAS_RESELLER_URL-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
	if (getDolGlobalString($newnamekey)) {
		$url = getDolGlobalString($newnamekey);
	}
}

if (preg_match('/^fr/i', $langs->defaultlang)) {
	$url = preg_replace('/en-/', 'fr-', $url);
}
if (preg_match('/^es/i', $langs->defaultlang)) {
	$url = preg_replace('/en-/', 'es-', $url);
}
if (empty($url)) {
	$url = 'UrlDocresellerNotSetup (See param SELLYOURSAAS_RESELLER_URL)';
}

$dateapplyreseller = $mythirdpartyaccount->array_options['options_date_apply_for_reseller'];
if ($dateapplyreseller) {
	print '
	<div class="alert alert-success note note-success">
	<h4 class="block">'.$langs->trans("ARequestToBeAResellerHasAlreadyBeenSent", dol_print_date($dateapplyreseller, 'day')).'</h4>
	</div>
	';
} else {
	print '
			<div class="alert alert-success note note-success">
			<h4 class="block">'.$langs->trans("BecomeResellerDesc", $sellyoursaasname, $url, $sellyoursaasname).'</h4>
			</div>
		';

	print '
		<div class="page-content-wrapper">
				<div class="page-content">


		     <!-- BEGIN PAGE HEADER-->
		<!-- BEGIN PAGE HEAD -->
		<div class="page-head">


		</div>
		<!-- END PAGE HEAD -->
		<!-- END PAGE HEADER-->';


	print '
			    <div class="row" id="formreseller">
			      <div class="col-md-12">

					<div class="portlet light">

				      <div class="portlet-title">
				        <div class="caption">';
	print '<!-- form to send a request to be reseller -->'."\n";
	print '<form class="inline-block centpercent" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="mode" value="becomereseller">';
	print '<input type="hidden" name="action" value="sendbecomereseller">';

	// Set email to use when applying for reseller program
	$sellyoursaasemail = getDolGlobalString('SELLYOURSAAS_RESELLER_EMAIL', getDolGlobalString('SELLYOURSAAS_MAIN_EMAIL'));
	if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
			&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
		$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
		if (getDolGlobalString($newnamekey)) {
			$sellyoursaasemail = getDolGlobalString($newnamekey);
		}
	}

	$subject = (GETPOST('subject', 'none') ? GETPOST('subject', 'none') : (preg_match('/fr/i', $langs->defaultlang) ? $langs->trans("BecomeReseller") : $langsen->trans("BecomeReseller")).' - '.$sellyoursaasemail);

	$commissiondefault = (!getDolGlobalString('SELLYOURSAAS_DEFAULT_COMMISSION') ? 25 : $conf->global->SELLYOURSAAS_DEFAULT_COMMISSION);

	print $langs->trans("MailFrom").' : <input type="text" required name="from" value="'.(GETPOST('from', 'none') ? GETPOST('from', 'none') : $mythirdpartyaccount->email).'"><br><br>';

	print $langs->trans("MailTopic").' : <input type="text" required class="minwidth500" name="subject" value="'.$subject.'"><br><br>';

	$texttouse = GETPOST('content', 'none');
	// Text is in french or english (no other language for resellers)
	if (! $texttouse) {
		$sellyoursaasname = getDolGlobalString('SELLYOURSAAS_NAME');
		if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != getDolGlobalString('SELLYOURSAAS_MAIN_DOMAIN_NAME')) {
			$newnamekey = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
			if (getDolGlobalString($newnamekey)) {
				$sellyoursaasname = getDolGlobalString($newnamekey);
			}
		}

		$texttouse = (preg_match('/fr/i', $langs->defaultlang) ? $langs->trans("YourTextBecomeReseller", $sellyoursaasname, $commissiondefault) : $langsen->trans("YourTextBecomeReseller", $sellyoursaasname, $commissiondefault));
	}
	$texttouse=preg_replace('/\\\\n/', "\n", $texttouse);
	print '<textarea rows="6" required style="border: 1px solid #888" name="content" class="centpercent">';
	print $texttouse;
	print '</textarea><br><br>';

	/*include_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
	 $doleditor = new DolEditor('content', $texttouse, '95%');
	 $doleditor->Create(0);*/

	print '<center><input type="submit" name="submit" value="'.$langs->trans("SendMail").'" class="btn green-haze btn-circle">';
	print ' ';
	print '<input type="submit" name="cancel" formnovalidate value="'.$langs->trans("Cancel").'" class="btn green-haze btn-circle">';
	print '</center>';

	print '</form>';

	print ' 	</div></div>

					</div> <!-- END PORTLET -->



			      </div> <!-- END COL -->


			    </div> <!-- END ROW -->
			';

	print '
	    </div>
		</div>
	';
}

?>
<!-- END PHP TEMPLATE becomereseller.tpl.php -->
