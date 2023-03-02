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

// Need global variable to be defined by caller (like dol_loginfunction)
// $title
// $urllogo
// $focus_element
// $message

// Protection to avoid direct call of template
if (empty($conf) || ! is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}


header('Cache-Control: Public, must-revalidate');
header("Content-type: text/html; charset=".$conf->file->character_set_client);

if (GETPOST('dol_hide_topmenu')) $conf->dol_hide_topmenu=1;
if (GETPOST('dol_hide_leftmenu')) $conf->dol_hide_leftmenu=1;
if (GETPOST('dol_optimize_smallscreen')) $conf->dol_optimize_smallscreen=1;
if (GETPOST('dol_no_mouse_hover')) $conf->dol_no_mouse_hover=1;
if (GETPOST('dol_use_jmobile')) $conf->dol_use_jmobile=1;

// If we force to use jmobile, then we reenable javascript
if (! empty($conf->dol_use_jmobile)) $conf->use_javascript_ajax=1;

$php_self = dol_escape_htmltag($_SERVER['PHP_SELF']);
$php_self.= dol_escape_htmltag($_SERVER["QUERY_STRING"])?'?'.dol_escape_htmltag($_SERVER["QUERY_STRING"]):'';
$php_self = preg_replace('/&hashreset=[0-9a-zA-Z]+/', '', $php_self);

$arrayofjs=array();
$titleofpage=$langs->trans('SendNewPassword');

$disablenofollow=1;
if (! preg_match('/'.constant('DOL_APPLICATION_TITLE').'/', $titleofpage)) $disablenofollow=0;

$favicon=getDomainFromURL($_SERVER['SERVER_NAME'], 0);
if (! preg_match('/\.(png|jpg)$/', $favicon)) $favicon.='.png';
if (! empty($conf->global->MAIN_FAVICON_URL)) $favicon=$conf->global->MAIN_FAVICON_URL;
if ($favicon) {
	$href = 'img/'.$favicon;
	if (preg_match('/^http/i', $favicon)) $href = $favicon;
	$head.='<link rel="icon" href="'.$href.'">'."\n";
}

print top_htmlhead_sellyoursaas($head, $titleofpage, 0, 0, $arrayofjs, array(), 0, $disablenofollow);

?>
<!-- BEGIN PHP TEMPLATE PASSWORDFORGOTTEN.TPL.PHP -->

<body class="body bodylogin">

<style>
div.error { background: unset; }
</style>

<?php if (empty($conf->dol_use_jmobile)) { ?>
<script type="text/javascript">
$(document).ready(function () {
	// Set focus on correct field
	<?php if ($focus_element) { ?>$('#<?php echo $focus_element; ?>').focus(); <?php } ?>		// Warning to use this only on visible element
});
</script>
<?php } ?>

<div class="login_center center">
<div class="login_vertical_align">


<form id="login" name="login" method="POST" action="<?php echo $php_self; ?>">
<input type="hidden" name="token" value="<?php echo newToken(); ?>">
<input type="hidden" name="action" value="buildnewpassword">


<div class="signup">

<div id="login_left">
<img alt="" src="<?php echo $urllogo; ?>" id="logo" />
</div>

<div class="block medium">

		<header class="inverse">
		  <h1><?php echo dol_escape_htmltag($title); ?></h1>

<div class="center login_main_home divpasswordmessagedesc paddingtopbottom<?php echo empty($conf->global->MAIN_LOGIN_BACKGROUND)?'':' backgroundsemitransparent'; ?>">
<?php if ($mode == 'dolibarr' || ! $disabled) { ?>
	<span class="passwordmessagedesc opacitymedium">
	<?php
	if (empty($asknewpass) && ! preg_match('/class="(ok|warning)"/', $message)) {
		echo str_replace('<br>', ' ', $langs->trans('SendNewPasswordDesc'));
	}
	?>
	</span>
<?php } else { ?>
	<div class="warning" align="center">
	<?php echo $langs->trans('AuthenticationDoesNotAllowSendNewPassword', $mode); ?>
	</div>
<?php } ?>
</div>

		</header>


<div class="login_table">

<div id="login_line1">

<div id="login_right">

<?php
if (! preg_match('/class="(ok|warning)"/', $message)) {
	?>
<table class="center">
<!-- Login -->
<tr>
<td class="nowrap valignmiddle" style="text-align: center;">
	<?php if (! empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER)) { ?><label for="username" class="hidden"><?php echo $langs->trans("Login"); ?></label><?php } ?>
<span class="span-icon-user fa fa-user">
	<?php
	if (empty($asknewpass)) {
		?>
	<input type="email" id="username" maxlength="255" placeholder="<?php echo $langs->trans("LoginEmail"); ?>" <?php echo $disabled; ?> name="username" class="flat input-field input-icon-user usernamepasswordforgotten" value="<?php echo dol_escape_htmltag($username); ?>" tabindex="1"  required autofocus="autofocus" />
	<br><br>
		<?php
	} else {
		print $langs->trans("PasswordChangeRequest", $username).'<br><br>';
		print '<input type="hidden" name="action" value="confirmpasswordreset">';
		print '<input type="hidden" name="id" value="'.$id.'">';
		print '<input type="hidden" name="hashreset" value="'.$hashreset.'">';

		print '<input type="password" maxlength="128" id="newpassword1" placeholder="'.$langs->trans("NewPassword").'" name="newpassword1" class="flat input-icon-user" tabindex="2" autofocus="autofocus" />';
		print '<br><br>';

		print '<input type="password" maxlength="128" id="newpassword2" placeholder="'.$langs->trans("PasswordRetype").'" name="newpassword2" class="flat input-icon-user" tabindex="3" />';
		print '<br><br>';
	}
	?>
</span>
</td>
</tr>

	<?php
	/*
	if (! empty($morelogincontent)) {
	if (is_array($morelogincontent)) {
		foreach ($morelogincontent as $format => $option)
		{
			if ($format == 'table') {
				echo '<!-- Option by hook -->';
				echo $option;
			}
		}
	}
	else {
		echo '<!-- Option by hook -->';
		echo $morelogincontent;
	}
	}
	*/

	if (empty($asknewpass) && 1) {
		// Add a variable param to force not using cache (jmobile)
		$php_self = preg_replace('/[&\?]time=(\d+)/', '', $php_self);	// Remove param time
		if (preg_match('/\?/', $php_self)) $php_self.='&time='.dol_print_date(dol_now(), 'dayhourlog');
		else $php_self.='?time='.dol_print_date(dol_now(), 'dayhourlog');
		?>
	<!-- Captcha -->
	<tr>
	<td class="nowrap none center">

	<table class="login_table_securitycode centpercent"><tr>
	<td>
	<span class="span-icon-security">
	<input id="securitycode" placeholder="<?php echo $langs->trans("SecurityCode"); ?>" class="flat input-icon-security" type="text" size="12" maxlength="5" name="code" tabindex="3" />
	</span>
	</td>
	<td> &nbsp; <i class="fa fa-arrow-left"></i> <img src="antispamimage.php" style="border: 1px solid #ddd;" width="76" id="img_securitycode" /></td>
	<td><a href="<?php echo $php_self; ?>" tabindex="4"><?php echo $captcha_refresh; ?></a></td>
	</tr></table>

	</br>
	</td></tr>
	<?php } ?>
</table>
<?php } ?>
</div> <!-- end div login-right -->

</div> <!-- end div login-line1 -->


<br>
<div id="login_line2" style="clear: both">

<?php
// Show error message if defined
if ($message) {
	?>
	<div class="center">
	<?php
	if (preg_match('/class="ok"/', $message)) print '<br><font class="ok">';
	elseif (preg_match('/class="warning"/', $message)) print '<br><font class="warning">';
	else print '<font class="error">';
	print $message;
	print '</font>';
	?>
	</div><br>
	<?php
}
?>

<section id="formActions">
<div class="form-actions center">

<?php
if (empty($asknewpass) && ! preg_match('/class="(ok|warning)"/', $message)) {
	?>
<!-- Button SendNewPassword -->
<input type="submit" style="margin-top: 8px;" class="btn btn-primary" name="password" value="&nbsp; <?php echo $langs->trans('SendNewPasswordLink'); ?> &nbsp;" tabindex="4" />
	<?php
} elseif (! empty($asknewpass) && $asknewpass == 1) {
	?>
<!-- Button ConfirmReset -->
<input type="submit" style="margin-top: 8px;" class="btn btn-primary" name="confirmpasswordreset" value="&nbsp; <?php echo $langs->trans('ConfirmPasswordReset'); ?> &nbsp;" tabindex="4" />
	<?php
}
?>

<div align="center" style="margin-top: 8px; margin-bottom: 8px;"">
	<?php
	$moreparam='';
	if (! empty($conf->dol_hide_topmenu))   $moreparam.=(strpos($moreparam, '?')===false?'?':'&').'dol_hide_topmenu='.$conf->dol_hide_topmenu;
	if (! empty($conf->dol_hide_leftmenu))  $moreparam.=(strpos($moreparam, '?')===false?'?':'&').'dol_hide_leftmenu='.$conf->dol_hide_leftmenu;
	if (! empty($conf->dol_no_mouse_hover)) $moreparam.=(strpos($moreparam, '?')===false?'?':'&').'dol_no_mouse_hover='.$conf->dol_no_mouse_hover;
	if (! empty($conf->dol_use_jmobile))    $moreparam.=(strpos($moreparam, '?')===false?'?':'&').'dol_use_jmobile='.$conf->dol_use_jmobile;

	print '<a class="alogin" href="'.$dol_url_root.'/index.php'.$moreparam.'">('.((empty($asknewpass) || $asknewpass == 2)? $langs->trans('BackToLoginPage') : $langs->trans("Cancel")).')</a>';
	?>
</div>

</div>
</section>

</div> <!-- end login line 2 -->

</div> <!-- end login table -->

</div>
</div>

</form>

<br>
<br>

<!-- authentication mode = <?php echo $main_authentication ?> -->
<!-- cookie name used for this session = <?php echo $session_name ?> -->
<!-- urlfrom in this session = <?php echo isset($_SESSION["urlfrom"])?$_SESSION["urlfrom"]:''; ?> -->

<!-- Common footer is not used for login page, this is same than footer but inside login tpl -->

<?php


if (! empty($conf->global->MAIN_HTML_FOOTER)) print $conf->global->MAIN_HTML_FOOTER;

if (! empty($morelogincontent) && is_array($morelogincontent)) {
	foreach ($morelogincontent as $format => $option) {
		if ($format == 'js') {
			echo "\n".'<!-- Javascript by hook -->';
			echo $option."\n";
		}
	}
} elseif (! empty($moreloginextracontent)) {
	echo '<!-- Javascript by hook -->';
	echo $moreloginextracontent;
}

// Google Analytics (need Google module)
if (! empty($conf->google->enabled) && ! empty($conf->global->MAIN_GOOGLE_AN_ID)) {
	if (empty($conf->dol_use_jmobile)) {
		print "\n";
		print '<script type="text/javascript">'."\n";
		print '  var _gaq = _gaq || [];'."\n";
		print '  _gaq.push([\'_setAccount\', \''.$conf->global->MAIN_GOOGLE_AN_ID.'\']);'."\n";
		print '  _gaq.push([\'_trackPageview\']);'."\n";
		print ''."\n";
		print '  (function() {'."\n";
		print '    var ga = document.createElement(\'script\'); ga.type = \'text/javascript\'; ga.async = true;'."\n";
		print '    ga.src = (\'https:\' == document.location.protocol ? \'https://ssl\' : \'http://www\') + \'.google-analytics.com/ga.js\';'."\n";
		print '    var s = document.getElementsByTagName(\'script\')[0]; s.parentNode.insertBefore(ga, s);'."\n";
		print '  })();'."\n";
		print '</script>'."\n";
	}
}
?>

</div>
</div>	<!-- end of center -->

<?php
if (! empty($conf->global->SELLYOURSAAS_MYACCOUNT_FOOTER)) {
	print $conf->global->SELLYOURSAAS_MYACCOUNT_FOOTER;
}
?>

</body>
</html>
<!-- END PHP TEMPLATE -->
