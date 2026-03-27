<?php
/* Copyright (C) 2011-2025 Laurent Destailleur <eldy@users.sourceforge.net>
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
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 *
 * @var Societe $mythirdpartyaccount
 */


// Protection to avoid direct call of template
if (empty($conf) || ! is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit(1);
}

$langs->load("ticket");
require_once DOL_DOCUMENT_ROOT.'/ticket/class/actions_ticket.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formticket.class.php';
$object = new ActionsTicket($db);
$form = new Form($db);
$listticketid = array();
$track_id = GETPOST("track_id");
if (!empty($track_id)) {
	$object->fetch(0, '', $track_id);
}

$backtourl = $_SERVER["PHP_SELF"]."?mode=ticket";

?>
<!-- BEGIN PHP TEMPLATE support.tpl.php -->
<?php
print '
	<div class="page-content-wrapper">
			<div class="page-content">

    <!-- BEGIN PAGE HEADER-->
	<!-- BEGIN PAGE HEAD -->
	<div class="page-head">
		<!-- BEGIN PAGE TITLE -->
		<div class="page-title">
		  <h1>'.(empty($object->dao->id) ? $langs->trans("TicketList") : "").'</h1>
		</div>
		<!-- END PAGE TITLE -->
	</div>
	<!-- END PAGE HEAD -->
	<!-- END PAGE HEADER-->';
	print '
			<div class="row">
			<div class="col-md-12">

			<div class="portlet light" id="planSection">';

	print '<div class="col-md-12">';
	print '<div class="div-table-responsive-no-min">';

	if (in_array($action, array("view", "add_message", "closeticket")) && !empty($track_id) && $object->dao->fk_soc == $mythirdpartyaccount->id) {
		$backtourl = $_SERVER["PHP_SELF"]."?mode=ticket&action=view&track_id=".$object->dao->track_id;

		print '<!-- public view ticket -->';

		print '<div id="form_view_ticket" class="margintoponly">';
		// Confirmation close
		if ($action == 'closeticket') {
			print $form->formconfirm($_SERVER["PHP_SELF"]."?mode=ticket&track_id=".$track_id."&backtourl=".urlencode($backtourl) , $langs->trans("CloseATicket"), $langs->trans("ConfirmCloseAticket"), "confirmcloseticket", '', '', 1);
		}
		print '<table class="noborder centpercent">';
		// Ref - Tracking ID
		print '<tr><td class="titlefield">'.$langs->trans("Ref").' / '.$langs->trans("TicketTrackId").'</td><td>';
		print img_picto('', 'ticket', 'class="pictofixedwidth"');
		print dolPrintHTML($object->dao->ref);
		print '<span class="opacitylow"> &nbsp; / &nbsp; '.dolPrintHTML($object->dao->track_id).'</span>';
		print '</td></tr>';

		// Subject
		print '<tr><td>'.$langs->trans("Subject").'</td><td>';
		print '<span class="bold large">';
		print dol_escape_htmltag($object->dao->subject);
		print '</span>';
		print '</td></tr>';

		// Statut
		print '<tr><td>'.$langs->trans("Status").'</td><td>';
		print $object->dao->getLibStatut(2);
		print '</td></tr>';

		// Type
		print '<tr><td>'.$langs->trans("Type").'</td><td>';
		print dol_escape_htmltag($object->dao->type_label);
		print '</td></tr>';

		// Category
		print '<tr><td>'.$langs->trans("Category").'</td><td>';
		if ($object->dao->category_label) {
			print img_picto('', 'category', 'class="pictofixedwidth"');
			print dol_escape_htmltag($object->dao->category_label);
		}
		print '</td></tr>';

		// Severity
		print '<tr><td>'.$langs->trans("Severity").'</td><td>';
		print dol_escape_htmltag($object->dao->severity_label);
		print '</td></tr>';

		// Creation date
		print '<tr><td>'.$langs->trans("DateCreation").'</td><td>';
		print img_picto('', 'calendar', 'class="pictofixedwidth"');
		print dol_print_date($object->dao->datec, 'dayhour');
		print '</td></tr>';

		// Author
		print '<tr><td>'.$langs->trans("Author").'</td><td>';
		if ($object->dao->fk_user_create > 0) {
			$langs->load("users");
			$fuser = new User($db);
			$fuser->fetch($object->dao->fk_user_create);
			print img_picto('', 'user', 'class="pictofixedwidth"');
			print $fuser->getFullName($langs);
		} else {
			print img_picto('', 'email', 'class="pictofixedwidth"');
			print dol_escape_htmltag($object->dao->origin_email);
		}

		print '</td></tr>';

		// Read date
		if (!empty($object->dao->date_read)) {
			print '<tr><td>'.$langs->trans("TicketReadOn").'</td><td>';
			print dol_print_date($object->dao->date_read, 'dayhour');
			print '</td></tr>';
		}

		// Close date
		if (!empty($object->dao->date_close)) {
			print '<tr><td>'.$langs->trans("TicketCloseOn").'</td><td>';
			print dol_print_date($object->dao->date_close, 'dayhour');
			print '</td></tr>';
		}

		// User assigned
		print '<tr><td>'.$langs->trans("AssignedTo").'</td><td>';
		if ($object->dao->fk_user_assign > 0) {
			$fuser = new User($db);
			$fuser->fetch($object->dao->fk_user_assign);
			print img_picto('', 'user', 'class="pictofixedwidth"');
			print $fuser->getFullName($langs, 0);
		}
		print '</td></tr>';

		// Add new external contributor
		if (getDolGlobalInt('TICKET_PUBLIC_SELECT_EXTERNAL_CONTRIBUTORS') && !empty($object->dao->fk_soc)) {
			print '<form method="post" id="form_view_add_contact" name="form_view_add_contact" action="'.$_SERVER['PHP_SELF'].'?track_id='.$object->dao->track_id.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="add_contact">';
			print '<input type="hidden" name="email" value="'.$_SESSION['email_customer'].'">';
			print '<tr><td>'.$langs->trans("AddContributor").'</td><td>';
			//print $form->selectcontacts($object->dao->fk_soc, '', 'contactid', 3, '', '', 1, 'minwidth100imp widthcentpercentminusxx maxwidth400');
			print $form->select_contact($object->dao->fk_soc, '', 'contactid', 3, '', '', 1, 'minwidth100imp widthcentpercentminusxx maxwidth400', true);
			print '<input type="submit" class="button smallpaddingimp reposition" name="btn_add_contact" value="'.$langs->trans('Add').'" />';
			print '</td></tr></form>';
		}

		// Progression
		if (getDolGlobalString('TICKET_SHOW_PROGRESSION')) {
			print '<tr><td>'.$langs->trans("Progression").'</td><td>';
			print($object->dao->progress > 0 ? dol_escape_htmltag((string) $object->dao->progress) : '0').'%';
			print '</td></tr>';
		}
		include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

		print "</table>";
		print '</div>';
		print '</div>';
		print '</div></div>';
		print '</div></div>';

		if ($action == 'add_message') {
			print '<br>';
			print load_fiche_titre($langs->trans('TicketAddMessage'), '', 'conversation');

			$formticket = new FormTicket($db);

			$formticket->action = "confirm_add_message";
			$formticket->track_id = $object->dao->track_id;

			$formticket->param = array('fk_user_create' => '-1', 'backtourl' => $_SERVER["PHP_SELF"]."?mode=ticket&action=view&track_id=".$object->dao->track_id,
									'returnurl' => $_SERVER["PHP_SELF"], 'track_id' => $object->dao->track_id);

			$formticket->withcancel = 1;

			$formticket->showMessageForm('100%');
		}

		if ($action != "add_message") {
			print '<div class="tabsAction right">';
			// List ticket
			print '<div class="inline-block divButAction"><a class="left" style="padding-right: 50px; vertical-align:middle" href="'.$_SERVER["PHP_SELF"].'?mode=ticket">'.$langs->trans('ViewMyTicketList').'</a></div>';

			if ($object->dao->status < Ticket::STATUS_CLOSED) {
				// New message
				print '<div class="inline-block divButAction"><a class="wordbreak btn" href="'.$_SERVER['PHP_SELF'].'?mode=ticket&action=add_message&track_id='.$object->dao->track_id.'&token='.newToken().'">'.$langs->trans('TicketAddMessage').'</a></div>';

				// Close ticket
				if ($object->dao->status >= Ticket::STATUS_NOT_READ && $object->dao->status < Ticket::STATUS_CLOSED) {
					print '<div class="inline-block divButAction"><a class="wordbreak btn" href="'.$_SERVER['PHP_SELF'].'?mode=ticket&action=closeticket&track_id='.$object->dao->track_id.'&token='.newToken().'">'.$langs->trans('CloseTicket').'</a></div>';
				}
			}

			print '</div>';

			print '<div class="ticketlargemargin">';
			print load_fiche_titre($langs->trans('TicketMessagesList'), '', 'conversation');
			print '</div>';
		}

		// View html list of message for ticket
		$ret = $object->dao->loadCacheMsgsTicket();
		if ($ret < 0) {
			dol_print_error($object->dao->db);
		}

		$action = GETPOST('action', 'aZ09');

		print '<div class="ticketlargemargin" style="padding-top: 0">';

		print '<!-- initial message of ticket -->'."\n";
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td class="nowrap titlefield">';
		print $langs->trans("InitialMessage");
		print '</td></tr>';
		print '<tr>';
		print '<td colspan="2">';
		print '<div class="longmessagecut small">';
		print dolPrintHTML($object->dao->message);
		print '</div>';
		print '</td>';
		print '</tr>';
		print '</table>';
		print '</div>';

		print '</div>';

		if (is_array($object->dao->cache_msgs_ticket) && count($object->dao->cache_msgs_ticket) > 0) {
			print '<div class="ticketlargemargin">';

			print '<div class="div-table-responsive-no-min">';
			print '<table class="noborder centpercent">';

			print '<tr class="liste_titre">';

			print '<td>';
			print $langs->trans('TicketMessagesList');
			print '</td>';

			print '<td>';
			print $langs->trans('User');
			print '</td>';

			foreach ($object->dao->cache_msgs_ticket as $id => $arraymsgs) {
				if (!$arraymsgs['private'] || ($arraymsgs['private'] == "1" && $show_private)) {
					print '<tr class="oddeven nohover">';
					print '<td><strong>';
					print img_picto('', 'object_action', 'class="paddingright"').dol_print_date($arraymsgs['datep'], 'dayhour');
					print '<strong></td>';
					print '<td>';
					if ($arraymsgs['fk_user_author'] > 0) {
						$userstat = new User($db);
						$res = $userstat->fetch($arraymsgs['fk_user_author']);
						if ($res) {
							print $userstat->getNomUrl(0, 'nolink');
						}
					} elseif (isset($arraymsgs['fk_contact_author'])) {
						$contactstat = new Contact($db);
						$res = $contactstat->fetch(0, null, '', $arraymsgs['fk_contact_author']);
						if ($res) {
							print $contactstat->getNomUrl(0, 'nolink');
						} else {
							print $arraymsgs['fk_contact_author'];
						}
					} else {
						print '<span class="opacitymedium">'.$langs->trans('Unknown').'</span>';
					}
					print '</td>';
					print '</tr>';
					print '<tr class="oddeven nohover">';
					print '<td'.($show_user ? ' colspan="2"' : '').'>';
					print $arraymsgs['message'];
					//attachment

					$documents = array();

					$sql = 'SELECT ecm.rowid as id, ecm.src_object_type, ecm.src_object_id, ecm.agenda_id';
					$sql .= ', ecm.filepath, ecm.filename, ecm.share';
					$sql .= ' FROM '.MAIN_DB_PREFIX.'ecm_files ecm';
					$sql .= " WHERE ecm.filepath = 'agenda/".(int) $arraymsgs['id']."'";
					$sql .= " OR (ecm.agenda_id = ".(int) $arraymsgs['id']." AND ecm.src_object_type = 'ticket' AND ecm.src_object_id = ".(int) $object->dao->id.")";
					$sql .= ' ORDER BY ecm.position ASC';

					$resql = $db->query($sql);
					if ($resql) {
						if ($db->num_rows($resql)) {
							while ($obj = $db->fetch_object($resql)) {
								$documents[$obj->id] = $obj;
							}
						}
					}
					if (!empty($documents)) {
						$isshared = 0;
						$footer = '<div class="timeline-documents-container">';
						foreach ($documents as $doc) {
							if (!empty($doc->share) || ($doc->src_object_type == 'ticket')) {
								$isshared = 1;
								$footer .= '<span id="document_'.$doc->id.'" class="timeline-documents" ';
								$footer .= ' data-id="'.$doc->id.'" ';
								$footer .= ' data-path="'.$doc->filepath.'"';
								$footer .= ' data-filename="'.dol_escape_htmltag($doc->filename).'" ';
								$footer .= '>';

								if (empty($doc->agenda_id)) {
									$dir_ref = $arraymsgs['id'];
									$modulepart = 'actions';
								} else {
									$split_dir = explode('/', $doc->filepath);
									$modulepart = array_shift($split_dir);
									$dir_ref = implode('/', $split_dir);
								}
								$filePath = DOL_DATA_ROOT.'/'.$doc->filepath.'/'.$doc->filename;
								$file_relative_path = $dir_ref.'/'.$doc->filename;
								$mime = dol_mimetype($filePath);
								$doclink = '';
								if (!empty($doc->share)) {
									$doclink = DOL_URL_ROOT.'/document.php?hashp='.urlencode($doc->share);
								} elseif ($doc->src_object_type == 'ticket') {
									$doclink = dol_buildpath('document.php', 1).'?modulepart='.$modulepart.'&attachment=0&file='.urlencode($file_relative_path).'&entity='.getEntity('ticket', 0);
								}

								$mimeAttr = ' mime="'.$mime.'" ';
								$class = '';
								if (in_array($mime, array('image/png', 'image/jpeg', 'application/pdf'))) {
									$class .= ' documentpreview';
								}

								$footer .= '<a href="'.$doclink.'" class="btn-link '.$class.'" target="_blank"  '.$mimeAttr.' >';
								$footer .= img_mime($filePath).' '.$doc->filename;
								$footer .= '</a>';

								$footer .= '</span>';
							}
						}
						$footer .= '</div>';
						if ($isshared == 1) {
							print '<br>';
							print '<br>';
							print $footer;
						}
					}
				}
			}
			print '</table>';
			print '</div>';
			print '</div>';
		} else {
			print '<div class="ticketpublicarea ticketlargemargin centpercent">';
			print '<div class="info">'.$langs->trans('NoMsgForThisTicket').'</div>';
			print '</div>';
		}

		print '<br>';
	} else {
		$num_rows = 0;
		$sql = "SELECT t.rowid as id";
		$sql .= " FROM ".MAIN_DB_PREFIX."ticket as t";
		$sql .= " WHERE t.fk_soc = '".$db->escape($mythirdpartyaccount->id)."'";		// $socid is id of third party account
		$sql .= $db->order('t.fk_statut', 'ASC');

		$resql=$db->query($sql);
		if ($resql) {
			$num_rows = $db->num_rows($resql);
			if ($num_rows) {
				$i = 0;
				while ($i < $num_rows) {
					$obj = $db->fetch_object($resql);
					$listticketid[] = $obj->id;
					$i++;
				}
			}
		}
		print '<div class="page-head">';
		print '</div>';

		if (empty($num_rows)) {
			print $langs->trans("NoRecordFound");
		} else {
			print '<table class="noborder centpercent">';
			foreach ($listticketid as $key => $ticketid) {
				$object->fetch($ticketid);
				print '<tr class="oddeven">';

				// Ref
				print '<td class="nowraponall">';
				print '<a href="'.$_SERVER["PHP_SELF"].'?mode=ticket&action=view&track_id='.$object->dao->track_id.'">';
				print img_object("", $object->dao->picto, 'class="paddingright"'). $object->dao->ref;
				print '</a>';
				print "</td>\n";

				// Creation date
				print '<td class="left">';
				print dol_print_date($object->dao->datec, 'dayhour');
				print "</td>";

				// Subject
				print '<td class="nowrap">';
				print $object->dao->subject;
				print "</td>\n";

				print '<td class="nowraponall right">';
				print $object->getLibStatut(5);
				print "</td>";

				print "</tr>\n";
			}
			print "</table>";
		}
		print '</div>';
		print '</div></div>';
		print '</div></div>';
	}
?>