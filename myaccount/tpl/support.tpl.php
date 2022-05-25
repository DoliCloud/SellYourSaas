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
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formticket.class.php';
	require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticket.class.php';

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
					if (!empty(getDolGlobalString('SELLYOURSAAS_SUPPORT_SHOW_MESSAGE'))) {
						print '<span>'.getDolGlobalString('SELLYOURSAAS_SUPPORT_SHOW_MESSAGE').'</span><br><br>';
					}
	// Hidden when SELLYOURSAAS_ONLY_NON_PROFIT_ORGA is set
	if (!getDolGlobalInt('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA')) {
					  
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
				if ($statuslabel == 'processing') { $color = 'orange'; }
				if ($statuslabel == 'suspended') { $color = 'orange'; }
				if ($statuslabel == 'undeployed') { $color = 'grey'; }
				if (preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) { $color = 'lightgrey'; }

				if ($tmpproduct->array_options['options_typesupport'] != 'none'
					&& !preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
					if (! $ispaid) {	// non paid instances
						$priority = 'low';
						$prioritylabel = '<span class="prioritylow">'.$langs->trans("Priority").' '.$langs->trans("Low").'</span> <span class="opacitymedium">'.$langs->trans("Trial").'</span>';
					} else {
						if ($ispaid) {	// paid with level Premium
							if ($tmpproduct->array_options['options_typesupport'] == 'premium') {
								$priority = 'high';
								$prioritylabel = '<span class="priorityhigh">'.$langs->trans("Priority").' '.$langs->trans("High").'</span>';
								$atleastonehigh++;
							} else {	// paid with level Basic
								$priority = 'medium';
								$prioritylabel = '<span class="prioritymedium">'.$langs->trans("Priority").' '.$langs->trans("Medium").'</span>';
							}
						}
					}

					$optionid = $priority.'_'.$id;
					$labeltoshow = '';
					$labeltoshow .= $langs->trans("Instance").' <strong>'.$contract->ref_customer.'</strong> ';
					//$labeltoshow = $tmpproduct->label.' - '.$contract->ref_customer.' ';
					//$labeltoshow .= $tmpproduct->array_options['options_typesupport'];
					//$labeltoshow .= $tmpproduct->array_options['options_typesupport'];
					$labeltoshow .= ' - ';
					$labeltoshow .= $prioritylabel;

					print '<option value="'.$optionid.'"'.(GETPOST('supportchannel', 'alpha') == $optionid ? ' selected="selected"':'').'" data-html="'.dol_escape_htmltag($labeltoshow).'">';
					print dol_escape_htmltag($labeltoshow);
					print '</option>';
					//print ajax_combobox('supportchannel');

					$atleastonefound++;
				}
			}
		}

		// Add link other or miscellaneous
		if (! $atleastonefound) {
			$labelother = $langs->trans("Miscellaneous");
		} else {
			$labelother = $langs->trans("Other");
		}

		$labelother .= ' &nbsp; <span class="prioritylow">'.$langs->trans("Priority").' '.$langs->trans("Low").'</span>';

		print '<option value="low_other"'.(GETPOST('supportchannel', 'alpha') == 'low_other' ? ' selected="selected"':'').' data-html="'.dol_escape_htmltag($labelother).'">'.dol_escape_htmltag($labelother).'</option>';
		if (empty($atleastonehigh)) {
			$labeltoshow = $langs->trans("PremiumSupport").' <span class="priorityhigh">'.$langs->trans("Priority").' '.$langs->trans("High").'</span> ('.$langs->trans("NoPremiumPlan").')';
			print '<option value="high_premium" disabled="disabled" data-html="'.dol_escape_htmltag('<strike>'.$labeltoshow).'</strike>">'.dol_escape_htmltag($labeltoshow).'</option>';
		}
		print '</select>';
		print ajax_combobox("supportchannel");

		print ' <input type="submit" name="choosechannel" value="'.$langs->trans("Choose").'" class="btn green-haze btn-circle margintop marginbottom marginleft marginright">';

		print '</form>';
	}

	if (($action == 'presend' && GETPOST('supportchannel', 'alpha')) || getDolGlobalInt('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA')) {
		print !getDolGlobalInt('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA') ? '<br><br>' : '<br>';
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

		// Hidden when SELLYOURSAAS_ONLY_NON_PROFIT_ORGA is set
		if (!getDolGlobalInt('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA')) {
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
	}

		// Combobox for Group of ticket
		$formticket = new FormTicket($db);

		$atleastonepublicgroup = 0;
		$ticketstat = new Ticket($db);
		$ticketstat->loadCacheCategoriesTickets();
		if (is_array($ticketstat->cache_category_tickets) && count($ticketstat->cache_category_tickets)) {
			foreach ($ticketstat->cache_category_tickets as $tg) {
				if (!empty($tg['public'])) {
					$atleastonepublicgroup++;
				}
			}
		}

		if ($atleastonepublicgroup) {
			$stringtoprint = $formticket->selectGroupTickets('', 'ticketcategory', 'public=1', 0, 0, 1, 0, '', 1, $langs);
			//$stringtoprint .= ajax_combobox('groupticket');
			$stringtoprint .= '<br>';
		}

		$stringtoprint .= '<!-- Script to manage change of ticket group -->
		<script>
		jQuery(document).ready(function() {
			function groupticketchange(){
				idgroupticket = $("#ticketcategory_select").val();
				console.log("We called groupticketchange and have selected id="+idgroupticket+", so we try to load list KM linked to event");

				$("#KWwithajax").html("");

				if (idgroupticket != "") {
					$.ajax({ url: \'/ajax/fetchKnowledgeRecord.php\',
						 data: { action: \'getKnowledgeRecord\', idticketgroup: idgroupticket, token: \''.newToken().'\', lang:\''.dol_escape_htmltag($langs->defaultlang).'\'},
						 type: \'GET\',
						 success: function(response) {
							var urllist = \'\';
							console.log("We received response "+response);
							response = JSON.parse(response)
							for (key in response) {
								console.log(response[key])
								urllist += \'<li><a href="\'+ response[key].url + \'" target="_blank">\'+response[key].title+\'</a></li>\';
							}
							if (urllist != "") {
								console.log(urllist)
								$("#KWwithajax").html(\'<div class="opacitymedium margintoponly">'.dol_escape_htmltag($langs->trans("KMFoundForTicketGroup")).':</div><ul class="kmlist">\'+urllist+\'<ul>\');
								$("#KWwithajax").show();
							}
						 },
						 error : function(output) {
							console.log("error");
						 },
					});
				}
			};

			$("#ticketcategory_select").bind("change",function() { 
				groupticketchange();';
		if (!empty($conf->global->SELLYOURSAAS_AUTOMIGRATION_CODE)) {
			$stringtoprint .= '
				tmp = $("#ticketcategory_select_child_id").val();
				$("#ticketcategory_child_id_back").val(tmp);
				tmp = $("#ticketcategory_select").val();
				$("#ticketcategory_back").val(tmp);
				';
			$stringtoprint .= '
				if ("'.$conf->global->SELLYOURSAAS_AUTOMIGRATION_CODE.'" == $("#ticketcategory_select").val()){
					console.log("We hide for automigration");
					$("#hideforautomigration").hide();
					$("#showforautomigration").show();
				}else{
					if($("#hideforautomigration").attr("style") == "display: none;"){
						console.log("We show full form");
						$("#hideforautomigration").show();
						$("#showforautomigration").hide();
					}
					$("#buttonforautomigrationwithhidden").hide();
				}
			});';
			if (GETPOST('backfromautomigration', 'alpha')) {
				$stringtoprint .= '
				$("#hideforautomigration").show();
				$("#showforautomigration").hide();
				$("#buttonforautomigrationwithhidden").show();';
			}
			$stringtoprint .= 'if ("'.$conf->global->SELLYOURSAAS_AUTOMIGRATION_CODE.'" == $("#ticketcategory_select").val()){
				console.log("We hide for automigration");
				$("#hideforautomigration").show();
				$("#showforautomigration").hide();
				$("#buttonforautomigrationwithhidden").show();
			}';
			$stringtoprint .= '
			$("#hideautomigrationdiv").on("click",function(){
				$("#hideforautomigration").show();
				$("#showforautomigration").hide();
				$("#buttonforautomigrationwithhidden").show();
			})';
			$stringtoprint .= '
			$("input[name=\'subject\']").on("change",function(){
				$("#subject_back").val($(this).val());
			})';
		}
		$stringtoprint .= '
			MutationObserver = window.MutationObserver || window.WebKitMutationObserver;
			var trackChange = function(element) {
			var observer = new MutationObserver(function(mutations, observer) {
				if (mutations[0].attributeName == "value") {
				$(element).trigger("change");
				}
			});
			observer.observe(element, {
				attributes: true
			});
			}

			trackChange($("#ticketcategory_select")[0]);
		});
		</script>'."\n";
		$stringtoprint .= '<div class="supportemailfield " id="KWwithajax"></div>';
		$stringtoprint .= '<br>';
		print $stringtoprint;
		if (!empty($conf->global->SELLYOURSAAS_AUTOMIGRATION_CODE)) {
			print '<div id=showforautomigration style="display:none;">';
			print '<div style="display:flex;justify-content: space-evenly;">';
			print '<button type="submit" form="migrationForm" class="btn green-haze btn-circle margintop marginbottom marginleft marginright">'.$langs->trans("GoToAutomigration").'</button>';
			print '<button id="hideautomigrationdiv" type="button" class="btn green-haze btn-circle margintop marginbottom marginleft marginright">'.$langs->trans("AutomigrationErrorOrNoAutomigration").'</button>';
			print '</div>';
			print '<br><br><br><br><br><br><br><br><br><br><br>';
			print '</div>';
			print '<div id="hideforautomigration"><div>';
		}

	// Hidden when SELLYOURSAAS_ONLY_NON_PROFIT_ORGA is set
	if (!getDolGlobalInt('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA')) {
		print '<input type="file" class="flat" id="addedfile" name="addedfile[]" multiple value="'.$langs->trans("Upload").'" />';
		print ' ';
		print '<input type="submit" class="btn green-haze btn-circle" id="addfile" name="addfile" value="'.$langs->trans("MailingAddFile").'" />';
		if (!empty($conf->global->SELLYOURSAAS_AUTOMIGRATION_CODE)) {
			print '<div class="center" id="buttonforautomigrationwithhidden" style="display:none;">';
			print '<br><br><button type="submit" form="migrationForm" class="btn green-haze btn-circle margintop marginbottom marginleft marginright">'.$langs->trans("GoToAutomigration").'</button>';
			print '</div>';
		}
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
		if (!empty($conf->global->SELLYOURSAAS_AUTOMIGRATION_CODE)) {
			print '</div>';
			print '</form>';
			print '<form action="#Step1" method="post" id="migrationForm">';
			print '<input type="hidden" name="mode" value="automigration">';
			print '<input type="hidden" name="contractid" value="'.$tmpcontractid.'">';
			print '<input type="hidden" name="supportchannel" value="'.GETPOST('supportchannel', 'alpha').'">';
			print '<input type="hidden" id="ticketcategory_child_id_back" name="ticketcategory_child_id_back" value="'.GETPOST('ticketcategory_child_id', 'alpha').'">';
			print '<input type="hidden" id="ticketcategory_back" name="ticketcategory_back" value="'.GETPOST('ticketcategory', 'alpha').'">';
			print '<input type="hidden" id="subject_back" name="subject_back" value="'.$subject.'">';
			print '<input type="hidden" name="action" value="view">';
		}
		print '</form>';
		}
	}

				print ' 	</div></div>

					</div> <!-- END PORTLET -->



			      </div> <!-- END COL -->


			    </div> <!-- END ROW -->
			';
}

if (empty($sellyoursaassupporturl) && ($action != 'presend' || !GETPOST('supportchannel', 'alpha'))) {
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
    					<!-- <div class="row" id="contractid'.$contract->id.'" data-contractref="'.$contract->ref.'"> -->
    					<div class="col-md-12">';



	require_once DOL_DOCUMENT_ROOT.'/ticket/class/actions_ticket.class.php';
	require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticketstats.class.php';
	$staticticket = new Ticket($db);

	$sql = "SELECT t.rowid, t.ref, t.track_id, t.datec, t.subject, t.fk_statut";
	$sql .= " FROM ".MAIN_DB_PREFIX."ticket as t";
	$sql .= " WHERE t.fk_soc = '".$db->escape($socid)."'";		// $socid is id of third party account
	$sql .= $db->order('t.fk_statut', 'ASC');

	$resql=$db->query($sql);
	if ($resql) {
		print '<div class="div-table-responsive-no-min">';
		$num_rows = $db->num_rows($resql);
		if ($num_rows) {
			print '<table class="noborder centpercent">';
			$i = 0;
			while ($i < $num_rows) {
				$obj = $db->fetch_object($resql);

				$staticticket->id = $obj->rowid;
				$staticticket->ref = $obj->ref;
				$staticticket->track_id = $obj->track_id;
				$staticticket->fk_statut = $obj->fk_statut;
				$staticticket->progress = $obj->progress;
				$staticticket->subject = $obj->subject;

				print '<tr class="oddeven">';

				// Ref
				print '<td class="nowraponall">';
				print $staticticket->getNomUrl(1);
				print "</td>\n";

				// Creation date
				print '<td class="left">';
				print dol_print_date($db->jdate($obj->datec), 'dayhour');
				print "</td>";

				// Subject
				print '<td class="nowrap">';
				print $obj->subject;
				print "</td>\n";

				print '<td class="nowraponall right">';
				print $staticticket->getLibStatut(5);
				print "</td>";

				print "</tr>\n";
				$i++;
			}
			print "</table>";
		} else {
			print $langs->trans("SoonAvailable");
		}
		print '</div>';
	} else {
		dol_print_error($db);
	}
	print '</div></div>';


	print '</div></div>';
}

	print '
	    </div>
		</div>
    ';

?>
<!-- END PHP TEMPLATE support.tpl.php -->
