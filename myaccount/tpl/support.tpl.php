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
	exit;
}

?>
<!-- BEGIN PHP TEMPLATE support.tpl.php -->
<?php
	require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

	$upload_dir = $conf->sellyoursaas->dir_temp."/support_".$mythirdpartyaccount->id.'.tmp';

if (!empty($_POST['addfile'])) {
	// Set tmp user directory
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	dol_add_file_process($upload_dir, 1, 0);

	$action = "presend";
}

if (!empty($_POST["removedfile"])) {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	dol_remove_file_process($_POST['removedfile'], 0, 0); // We really delete file linked to mailing

	$action = "presend";
}

	// Print warning to read FAQ before
	print '<!-- Message to read FAQ and get status -->'."\n";
if ($urlfaq || $urlstatus) {
	print '<div class="alert alert-success note note-success">'."\n";
	if ($urlfaq) {
		print '<h4 class="block">'.$langs->trans("PleaseReadFAQFirst", $urlfaq).'</h4>'."\n";
	}
	if ($urlstatus) {
		print '<br>'.$langs->trans("CurrentServiceStatus", $urlstatus)."\n";
	}
	print '</div>'."\n";
}

	print '
	<div class="page-content-wrapper">
			<div class="page-content">


	     <!-- BEGIN PAGE HEADER-->
	<!-- BEGIN PAGE HEAD -->
	<div class="page-head">
	  <!-- BEGIN PAGE TITLE -->
	<div class="page-title">
	  <h1>'.$langs->trans("NewTicket").' <small>'.$langs->trans("SupportDesc").'</small></h1>
	</div>
	<!-- END PAGE TITLE -->


	</div>
	<!-- END PAGE HEAD -->
	<!-- END PAGE HEADER-->';

	$sellyoursaassupporturl = $conf->global->SELLYOURSAAS_SUPPORT_URL;
if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
	$newnamekey = 'SELLYOURSAAS_SUPPORT_URL-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
	if (! empty($conf->global->$newnamekey)) $sellyoursaassupporturl = $conf->global->$newnamekey;
}

if ($sellyoursaassupporturl) {
	$sellyoursaassupporturl = str_replace('__EMAIL__', $mythirdpartyaccount->email, $sellyoursaassupporturl);
	$sellyoursaassupporturl = str_replace('__FIRSTNAME__', $mythirdpartyaccount->array_options['options_firstname'], $sellyoursaassupporturl);
	$sellyoursaassupporturl = str_replace('__LASTNAME__', $mythirdpartyaccount->array_options['options_lastname'], $sellyoursaassupporturl);

	print '<div class="row" id="supporturl"><div class="col-md-12"><div class="portlet light">';
	print $langs->trans("SupportURLExternal", $sellyoursaassupporturl).'<br />'."\n";
	print '</div></div></div>';
} else {
	print '
			    <div class="row" id="choosechannel">
			      <div class="col-md-12">

					<div class="portlet light">

				      <div class="portlet-title">
				        <div class="caption">';

					print '<!-- form to select channel -->'."\n";
					print '<form class="inline-block centpercent" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
					print '<input type="hidden" name="token" value="'.newToken().'">';
					print '<input type="hidden" name="mode" value="support">';
					print '<input type="hidden" name="action" value="presend">';

					print '<span class="opacitymedium">'.$langs->trans("SelectYourSupportChannel").'</span><br>';

					print '<select id="supportchannel" name="supportchannel" class="minwidth600">';
					print '<option value="">&nbsp;</option>';
	if (count($listofcontractid) == 0) {
		// Should not happen
	} else {
		$atleastonehigh=0;
		$atleastonefound=0;

		foreach ($listofcontractid as $id => $contract) {
							$planref = $contract->array_options['options_plan'];
							$statuslabel = $contract->array_options['options_deployment_status'];
							$instancename = preg_replace('/\..*$/', '', $contract->ref_customer);

							$dbprefix = $contract->array_options['options_db_prefix'];
							if (empty($dbprefix)) $dbprefix = 'llx_';

			if ($statuslabel == 'undeployed') {
				continue;
			}

							// Get info about PLAN of Contract
							$planlabel = $planref;		// By default but we will take ref and label of service of type 'app' later

							$planid = 0;
							$freeperioddays = 0;
							$directaccess = 0;

							$tmpproduct = new Product($db);
			foreach ($contract->lines as $keyline => $line) {
				if ($line->statut == 5 && $contract->array_options['options_deployment_status'] != 'undeployed') {
									$statuslabel = 'suspended';
				}

				if ($line->fk_product > 0) {
						$tmpproduct->fetch($line->fk_product);
					if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
						$planref = $tmpproduct->ref;			// Warning, ref is in language of user
						$planlabel = $tmpproduct->label;		// Warning, label is in language of user
						$planid = $tmpproduct->id;
						$freeperioddays = $tmpproduct->array_options['options_freeperioddays'];
						$directaccess = $tmpproduct->array_options['options_directaccess'];
						break;
					}
				}
			}

							$ispaid = sellyoursaasIsPaidInstance($contract);

							$color = "green";
							if ($statuslabel == 'processing') $color = 'orange';
							if ($statuslabel == 'suspended') $color = 'orange';
							if ($statuslabel == 'undeployed') $color = 'grey';

			if ($tmpproduct->array_options['options_typesupport'] != 'none') {
				if (! $ispaid) {
					$priority = 'low';
					$prioritylabel = $langs->trans("Trial").' = <span class="prioritylow">'.$langs->trans("Low").'</span>';
				} else {
					if ($ispaid) {
						if ($tmpproduct->array_options['options_typesupport'] == 'premium') {
							$priority = 'high';
							$prioritylabel = '<span class="priorityhigh">'.$langs->trans("High").'</span>';
							$atleastonehigh++;
						} else {
							$priority = 'medium';
							$prioritylabel = '<span class="prioritymedium">'.$langs->trans("Medium").'</span>';
						}
					}
				}
				$optionid = $priority.'_'.$id;
				$labeltoshow .= $langs->trans("Instance").' <strong>'.$contract->ref_customer.'</strong> ';
				//$labeltoshow = $tmpproduct->label.' - '.$contract->ref_customer.' ';
				//$labeltoshow .= $tmpproduct->array_options['options_typesupport'];
				//$labeltoshow .= $tmpproduct->array_options['options_typesupport'];
				$labeltoshow .= ' <span class="opacitymedium">('.$langs->trans("Priority").': ';
				$labeltoshow .= $prioritylabel;
				$labeltoshow .= ')</span>';
				print '<option value="'.$optionid.'"'.(GETPOST('supportchannel', 'alpha') == $optionid ? ' selected="selected"':'').'" data-html="'.dol_escape_htmltag($labeltoshow).'">';
				print dol_escape_htmltag($labeltoshow);
				print '</option>';
				//print ajax_combobox('supportchannel');

				$atleastonefound++;
			}
		}
	}

				// Add link other or miscellaneous
				if (! $atleastonefound) $labelother = $langs->trans("Miscellaneous");
	else $labelother = $langs->trans("Other");
				$labelother .= ' <span class="opacitymedium">('.$langs->trans("Priority").': <span class="prioritylow">'.$langs->trans("Low").'</span>)</span>';

				print '<option value="low_other"'.(GETPOST('supportchannel', 'alpha') == 'low_other' ? ' selected="selected"':'').' data-html="'.dol_escape_htmltag($labelother).'">'.dol_escape_htmltag($labelother).'</option>';
	if (empty($atleastonehigh)) {
		$labeltoshow = $langs->trans("PremiumSupport").' ('.$langs->trans("Priority").': <span class="priorityhigh">'.$langs->trans("High").'</span>) / '.$langs->trans("NoPremiumPlan");
		print '<option value="high_premium" disabled="disabled" data-html="'.dol_escape_htmltag('<strike>'.$labeltoshow).'</strike>">'.dol_escape_htmltag($labeltoshow).'</option>';
	}
				print '</select>';
				print ajax_combobox("supportchannel");

				print ' <input type="submit" name="choosechannel" value="'.$langs->trans("Choose").'" class="btn green-haze btn-circle margintop marginbottom marginleft marginright">';

				print '</form>';


	if ($action == 'presend' && GETPOST('supportchannel', 'alpha')) {
		print '<br><br>';

		$trackid = '';
		dol_init_file_process($upload_dir, $trackid);

		if (GETPOST('choosechannel')) {
			$counttodelete = 0;
			dol_delete_dir_recursive($upload_dir, $counttodelete);
		}

		// List of files
		$listofpaths = dol_dir_list($upload_dir, 'files', 0, '', '', 'name', SORT_ASC, 0);

		$out .= '<input type="hidden" class="removedfilehidden" name="removedfile" value="">'."\n";
		$out .= '<script type="text/javascript" language="javascript">';
		$out .= 'jQuery(document).ready(function () {';
		$out .= '    jQuery(".removedfile").click(function() {';
		$out .= '        jQuery(".removedfilehidden").val(jQuery(this).val());';
		$out .= '    });';
		$out .= '})';
		$out .= '</script>'."\n";
		if (count($listofpaths)) {
			foreach ($listofpaths as $key => $val) {
				$out .= '<div id="attachfile_'.$key.'" class="margintop">';
				$out .= img_mime($listofpaths[$key]['name']).' '.$listofpaths[$key]['name'];
				$out .= ' <input type="image" style="border: 0px;" src="'.img_picto($langs->trans("Search"), 'delete.png', '', '', 1).'" value="'.($key + 1).'" class="removedfile" id="removedfile_'.$key.'" name="removedfile_'.$key.'" />';
				$out .= '</div>';
			}
		} else {
			$out .= '<br>';
			//$out .= $langs->trans("NoAttachedFiles").'<br>';
		}

		/*
		print '<form class="inline-block centpercent" action="'.$_SERVER["PHP_SELF"].'" method="POST" enctype="multipart/form-data">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="addfile">';
		print $hiddeninputs;
		print $out;
		print '<input type="file" class="flat" id="addedfile" name="addedfile" value="'.$langs->trans("Upload").'" />';
		print ' ';
		print '<input type="submit" class="btn green-haze btn-circle" id="addfile" name="addfile" value="'.$langs->trans("MailingAddFile").'" />';
		print '</form>';
		*/

		print '<!-- form to send a ticket -->'."\n";
		print '<form id="mailform" class="inline-block centpercent" action="'.$_SERVER["PHP_SELF"].'" method="POST" enctype="multipart/form-data">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="send">';

		$tmpcontractid = $id;	// The last contract id found
		if (GETPOST('supportchannel', 'alpha')) {
			$tmparray = explode('_', GETPOST('supportchannel', 'alpha'));
			if (!empty($tmparray[1]) && $tmparray[1] > 0) {
				$tmpcontractid = $tmparray[1];
				// TODO Check that $tmpcontractid is into list of own contract ids.
			}
		}

		// Add link to add file
		print '<input type="hidden" name="mode" value="support">';
		print '<input type="hidden" name="contractid" value="'.$tmpcontractid.'">';
		print '<input type="hidden" name="supportchannel" value="'.GETPOST('supportchannel', 'alpha').'">';

		$sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
		if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
			&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
			$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
			if (! empty($conf->global->$newnamekey)) $sellyoursaasemail = $conf->global->$newnamekey;
		}

		if (! empty($conf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM) && preg_match('/high/', GETPOST('supportchannel', 'alpha'))) {
			// We must use the prioritary email
			$sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM;
			if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
				&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
				$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_PREMIUM_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
				if (! empty($conf->global->$newnamekey)) $sellyoursaasemail = $conf->global->$newnamekey;
			}
		}

		$subject = (GETPOST('subject', 'none')?GETPOST('subject', 'none'):'');

		print '<input type="hidden" name="to" value="'.$sellyoursaasemail.'">';

		print '<span class="supportemailfield inline-block bold">'.$langs->trans("MailFrom").'</span> <input type="text" name="from" value="'.(GETPOST('from', 'none')?GETPOST('from', 'none'):$mythirdpartyaccount->email).'"><br><br>';
		print '<span class="supportemailfield inline-block bold">'.$langs->trans("MailTopic").'</span> <input type="text" autofocus class="minwidth500" name="subject" value="'.$subject.'"><br><br>';

		//Combobox for Group of ticket
		$stringtoprint = '<span class="supportemailfield bold">'.$langs->trans("GroupOfTicket").'</span> ';
		$stringtoprint .= '<select name="groupticket" id ="groupticket"class="maxwidth500 minwidth400">';
		$stringtoprint .= '<option value="">&nbsp;</option>';

		$sql = "SELECT ctc.code, ctc.label";
		$sql .= " FROM ".MAIN_DB_PREFIX."c_ticket_category as ctc";
		$sql .= " WHERE ctc.public = 1";
		$sql .= " AND ctc.active = 1";
		$sql .= $db->order('ctc.pos', 'ASC');
		$resql = $db->query($sql);
		if ($resql) {
			$num_rows = $db->num_rows($resql);
			$i = 0;
			while ($i < $num_rows) {
				$obj = $db->fetch_object($resql);
				if ($obj) {
					$groupvalue = $obj->code;
					$grouplabel = $obj->label;
					$stringtoprint .= '<option value="'.dol_escape_htmltag($groupvalue).'" data-html="'.dol_escape_htmltag($grouplabel).'">'.dol_escape_htmltag($grouplabel).'</option>';
				}
				$i++;
			}
		}

		$stringtoprint .= '</select>';
		$stringtoprint .= ajax_combobox("groupticket");
		$stringtoprint .= '<br><br>';
		if ($num_rows > 1) {
			print $stringtoprint;
		} elseif ($num_rows == 1) {
			print '<input type="hidden" name="groupticket" id="groupticket" value="'.dol_escape_htmltag($groupvalue).'">';
		}

		print '<input type="file" class="flat" id="addedfile" name="addedfile[]" multiple value="'.$langs->trans("Upload").'" />';
		print ' ';
		print '<input type="submit" class="btn green-haze btn-circle" id="addfile" name="addfile" value="'.$langs->trans("MailingAddFile").'" />';

		print $out;
		print '<br>';

		// Description
		print '<textarea rows="6" placeholder="'.$langs->trans("YourText").'" style="border: 1px solid #888" name="content" class="centpercent">'.GETPOST('content', 'none').'</textarea><br><br>';

		// Button to send ticket/email
		print '<center><input type="submit" name="submit" value="'.$langs->trans("SendMail").'" class="btn green-haze btn-circle marginrightonly"';
		if ($conf->use_javascript_ajax) {
			print ' onClick="if (document.forms.mailform.addedfile.value != \'\') { alert(\''.dol_escape_js($langs->trans("FileWasNotUploaded")).'\'); return false; } else { return true; }"';
		}
		print '>';
		print ' ';
		print '<input type="submit" name="cancel" formnovalidate value="'.$langs->trans("Cancel").'" class="btn green-haze btn-circle marginleftonly">';
		print '</center>';

		print '</form>';
	}

				print ' 	</div></div>

					</div> <!-- END PORTLET -->



			      </div> <!-- END COL -->


			    </div> <!-- END ROW -->
			';
}

if (empty($sellyoursaassupporturl) && $action != 'presend') {
	print '
    				<!-- BEGIN PAGE HEADER-->
    				<!-- BEGIN PAGE HEAD -->
    				<div class="page-head">
    				<!-- BEGIN PAGE TITLE -->
    				<div class="page-title">
					<h1>'.$langs->trans("OldTickets").' <small>'.$langs->trans("OldTicketsDesc").'</small></h1>
    				</div>
    				<!-- END PAGE TITLE -->


    				</div>
    				<!-- END PAGE HEAD -->
    				<!-- END PAGE HEADER-->';

	print '
    		<div class="row">
    		<div class="col-md-12">

    		<div class="portlet light" id="planSection">

    		<div class="portlet-title">
    		<div class="caption">
    		<!--<span class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("Tickets").'</span>-->
    		</div>
    		</div>';

	print '
    					<div class="row" id="contractid'.$contract->id.'" data-contractref="'.$contract->ref.'">
    					<div class="col-md-12">';


	print $langs->trans("SoonAvailable");

	print '</div></div>';


	print '</div></div>';
}

	print '
	    </div>
		</div>
    ';

?>
<!-- END PHP TEMPLATE support.tpl.php -->
