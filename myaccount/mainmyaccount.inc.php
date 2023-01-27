<?php
/* Copyright (C) 2002-2007  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2003       Xavier Dutoit           <doli@sydesy.com>
 * Copyright (C) 2004-2021  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2004       Sebastien Di Cintio     <sdicintio@ressource-toi.org>
 * Copyright (C) 2004       Benoit Mortier          <benoit.mortier@opensides.be>
 * Copyright (C) 2005-2021  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2011-2014  Philippe Grand          <philippe.grand@atoo-net.com>
 * Copyright (C) 2008       Matteli
 * Copyright (C) 2011-2016  Juanjo Menent           <jmenent@2byte.es>
 * Copyright (C) 2012       Christophe Battarel     <christophe.battarel@altairis.fr>
 * Copyright (C) 2014-2015  Marcos García           <marcosgdf@gmail.com>
 * Copyright (C) 2015       Raphaël Doursenaud      <rdoursenaud@gpcsolutions.fr>
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
 */

/**
 *	\file       htdocs/sellyoursaas/myaccount/mainmyaccount.inc.php
 *	\ingroup	core
 *	\brief      File that defines environment for Dolibarr GUI pages only (file not required by scripts)
 */


define('DOL_URL_ROOT', 'source');
define('DOL_APPLICATION_TITLE', 'MyAccount');


// Functions

if (! function_exists("llxHeader")) {
	/**
	 *	Show HTML header HTML + BODY + Top menu + left menu + DIV
	 *
	 * @param 	string 			$head				Optionnal head lines
	 * @param 	string 			$title				HTML title
	 * @param	string			$help_url			Url links to help page
	 * 		                            			Syntax is: For a wiki page: EN:EnglishPage|FR:FrenchPage|ES:SpanishPage
	 *                                  			For other external page: http://server/url
	 * @param	string			$target				Target to use on links
	 * @param 	int    			$disablejs			More content into html header
	 * @param 	int    			$disablehead		More content into html header
	 * @param 	array|string  	$arrayofjs			Array of complementary js files
	 * @param 	array|string  	$arrayofcss			Array of complementary css files
	 * @param	string			$morequerystring	Query string to add to the link "print" to get same parameters (use only if autodetect fails)
	 * @param   string  		$morecssonbody      More CSS on body tag. For example 'classforhorizontalscrolloftabs'.
	 * @param	string			$replacemainareaby	Replace call to main_area() by a print of this string
	 * @param	int				$disablenofollow	Disable the "nofollow" on meta robot header
	 * @param	int				$disablenoindex		Disable the "noindex" on meta robot header
	 * @return	void
	 */
	function llxHeader($head = '', $title = '', $help_url = '', $target = '', $disablejs = 0, $disablehead = 0, $arrayofjs = '', $arrayofcss = '', $morequerystring = '', $morecssonbody = '', $replacemainareaby = '', $disablenofollow = 0, $disablenoindex = 0)
	{
		global $conf, $hookmanager;

		// html header
		top_htmlhead_sellyoursaas($head, $title, $disablejs, $disablehead, $arrayofjs, $arrayofcss);

		print '<body id="mainbody"'.($morecssonbody?' class="'.$morecssonbody.'"':'').'>' . "\n";

		// top menu and left menu area
		if (empty($conf->dol_hide_topmenu) || GETPOST('dol_invisible_topmenu', 'int')) {
			//top_menu($head, $title, $target, $disablejs, $disablehead, $arrayofjs, $arrayofcss, $morequerystring, $help_url);
		}

		if (empty($conf->dol_hide_leftmenu)) {
			//left_menu('', $help_url, '', '', 1, $title, 1);
		}

		// main area
		if ($replacemainareaby) {
			print $replacemainareaby;
			return;
		}
		//main_area($title);
	}
}


/**
 *  Show HTTP header. Called by top_htmlhead().
 *
 *  @param  string  $contenttype    Content type. For example, 'text/html'
 *  @param	int		$forcenocache	Force disabling of cache for the page
 *  @return	void
 */
function top_httphead_sellyoursaas($contenttype = 'text/html', $forcenocache = 0)
{
	global $db, $conf, $hookmanager;

	if ($contenttype == 'text/html') {
		header("Content-Type: text/html; charset=".$conf->file->character_set_client);
	} else {
		header("Content-Type: ".$contenttype);
	}

	// Security options

	// X-Content-Type-Options
	header("X-Content-Type-Options: nosniff"); 	// With the nosniff option, if the server says the content is text/html, the browser will render it as text/html (note that most browsers now force this option to on)

	// X-Frame-Options
	if (!defined('XFRAMEOPTIONS_ALLOWALL')) {
		header("X-Frame-Options: SAMEORIGIN"); 	// Frames allowed only if on same domain (stop some XSS attacks)
	} else {
		header("X-Frame-Options: ALLOWALL");
	}

	// X-XSS-Protection
	//header("X-XSS-Protection: 1");      		// XSS filtering protection of some browsers (note: use of Content-Security-Policy is more efficient). Disabled as deprecated.

	// Content-Security-Policy
	if (!defined('MAIN_SECURITY_FORCECSP')) {
		// If CSP not forced from the page

		// A default security policy that keep usage of js external component like ckeditor, stripe, google, working
		//	$contentsecuritypolicy = "frame-ancestors 'self'; font-src *; img-src *; style-src * 'unsafe-inline' 'unsafe-eval'; default-src 'self' *.stripe.com 'unsafe-inline' 'unsafe-eval'; script-src 'self' *.stripe.com 'unsafe-inline' 'unsafe-eval'; frame-src 'self' *.stripe.com; connect-src 'self';";
		$contentsecuritypolicy = getDolGlobalString('MAIN_SECURITY_FORCECSP');

		if (!is_object($hookmanager)) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($db);
		}
		$hookmanager->initHooks(array("main"));

		$parameters = array('contentsecuritypolicy'=>$contentsecuritypolicy);
		$result = $hookmanager->executeHooks('setContentSecurityPolicy', $parameters); // Note that $action and $object may have been modified by some hooks
		if ($result > 0) {
			$contentsecuritypolicy = $hookmanager->resPrint; // Replace CSP
		} else {
			$contentsecuritypolicy .= $hookmanager->resPrint; // Concat CSP
		}

		if (!empty($contentsecuritypolicy)) {
			// For example, to restrict 'script', 'object', 'frames' or 'img' to some domains:
			// frame-ancestors 'self'; script-src https://api.google.com https://anotherhost.com; object-src https://youtube.com; frame-src https://youtube.com; img-src https://static.example.com
			// For example, to restrict everything to one domain, except 'object', ...:
			// default-src https://cdn.example.net; object-src 'none'
			// For example, to restrict everything to itself except img that can be on other servers:
			// default-src 'self'; img-src *;
			// Pre-existing site that uses too much js code to fix but wants to ensure resources are loaded only over https and disable plugins:
			// default-src https: 'unsafe-inline' 'unsafe-eval'; object-src 'none'
			header("Content-Security-Policy: ".$contentsecuritypolicy);
		}
	} else {
		header("Content-Security-Policy: ".constant('MAIN_SECURITY_FORCECSP'));
	}

	// Referrer-Policy
	// Say if we must provide the referrer when we jump onto another web page.
	// Default browser are 'strict-origin-when-cross-origin' (only domain is sent on other domain switching), we want more so we use 'same-origin' so browser doesn't send any referrer when going into another web site domain.
	if (!defined('MAIN_SECURITY_FORCERP')) {
		$referrerpolicy = getDolGlobalString('MAIN_SECURITY_FORCERP', "same-origin");

		header("Referrer-Policy: ".$referrerpolicy);
	}

	if ($forcenocache) {
		header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
	}

	// No need to add this token in header, we use instead the one into the forms.
	//header("anti-csrf-token: ".newToken());
}

/**
 * Ouput html header of a page. It calls also top_httphead_sellyoursaas()
 * This code is also duplicated into security2.lib.php::dol_loginfunction
 *
 * @param 	string 	$head			 Optionnal head lines
 * @param 	string 	$title			 HTML title
 * @param 	int    	$disablejs		 Disable js output
 * @param 	int    	$disablehead	 Disable head output
 * @param 	array  	$arrayofjs		 Array of complementary js files
 * @param 	array  	$arrayofcss		 Array of complementary css files
 * @param 	int    	$disableforlogin Do not load heavy js and css for login pages
 * @param   int     $disablenofollow Disable nofollow tag for meta robots
 * @param   int     $disablenoindex  Disable noindex tag for meta robots
 * @return	void
 */
function top_htmlhead_sellyoursaas($head, $title = '', $disablejs = 0, $disablehead = 0, $arrayofjs = '', $arrayofcss = '', $disableforlogin = 0, $disablenofollow = 0, $disablenoindex = 0)
{
	global $db, $conf, $langs, $user, $mysoc, $hookmanager;

	top_httphead_sellyoursaas();

	if (empty($conf->css)) {
		$conf->css = '/theme/eldy/style.css.php';	// If not defined, eldy by default
	}

	print '<!doctype html>'."\n";

	print '<html lang="'.substr($langs->defaultlang, 0, 2).'">'."\n";
	print '<!-- You language detected: '.$langs->defaultlang." -->\n";

	//print '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr">'."\n";
	if (empty($disablehead)) {
		if (!is_object($hookmanager)) {
			$hookmanager = new HookManager($db);
		}

		$ext = 'layout='.$conf->browser->layout.'&amp;version='.urlencode(DOL_VERSION);

		print "<head>\n";

		if (GETPOST('dol_basehref', 'alpha')) {
			print '<base href="'.dol_escape_htmltag(GETPOST('dol_basehref', 'alpha')).'">'."\n";
		}

		// Displays meta
		print '<meta charset="utf-8">'."\n";
		print '<meta name="robots" content="'.($disablenoindex ? 'index' : 'noindex').($disablenofollow ? ',follow' : ',nofollow').'">'."\n"; // Do not index
		print '<meta name="viewport" content="width=device-width, initial-scale=1.0">'."\n";	// Scale for mobile device
		print '<meta name="author" content="Dolibarr Development Team">'."\n";
		print '<meta name="anti-csrf-token" content="'.newToken().'">'."\n";
		if (getDolGlobalInt('MAIN_FEATURES_LEVEL')) {
			print '<meta name="MAIN_FEATURES_LEVEL" content="'.getDolGlobalInt('MAIN_FEATURES_LEVEL').'">'."\n";
		}
		// Favicon. Note, even if we remove this meta, the browser and android webview try to find a favicon.ico
		$favicon = getDomainFromURL($_SERVER['SERVER_NAME'], 0);
		if (! preg_match('/\.(png|jpg)$/', $favicon)) $favicon.='.png';
		if (getDolGlobalString('MAIN_FAVICON_URL')) {
			$favicon = getDolGlobalString('MAIN_FAVICON_URL');
		}
		if ($favicon && empty($conf->dol_use_jmobile)) {
			$href = 'img/'.$favicon;
			if (preg_match('/^http/i', $favicon)) $href = $favicon;
			print '<link rel="shortcut icon" type="image/x-icon" href="'.$href.'">'."\n";
		}

		// Displays title
		$appli=constant('DOL_APPLICATION_TITLE');
		if (!empty($conf->global->MAIN_APPLICATION_TITLE)) {
			$appli=$conf->global->MAIN_APPLICATION_TITLE;
		}

		print '<title>';
		if ($title && !empty($conf->global->MAIN_HTML_TITLE) && preg_match('/noapp/', $conf->global->MAIN_HTML_TITLE)) print dol_htmlentities($title);
		elseif ($title) print dol_htmlentities($appli.' - '.$title);
		else print dol_htmlentities($appli);
		print '</title>';

		print "\n";

		//$ext='';
		//if (! empty($conf->dol_use_jmobile)) $ext='version='.urlencode(DOL_VERSION);
		$ext='version='.urlencode(DOL_VERSION);
		if (GETPOST('version', 'int')) {
			$ext='version='.GETPOST('version', 'int');	// usefull to force no cache on css/js
		}
		// Refresh value of MAIN_IHM_PARAMS_REV before forging the parameter line.
		if (GETPOST('dol_resetcache')) {
			dolibarr_set_const($db, "MAIN_IHM_PARAMS_REV", ((int) $conf->global->MAIN_IHM_PARAMS_REV) + 1, 'chaine', 0, '', $conf->entity);
		}

		$themeparam = '?lang='.$langs->defaultlang.'&amp;theme='.$conf->theme.(GETPOST('optioncss', 'aZ09') ? '&amp;optioncss='.GETPOST('optioncss', 'aZ09', 1) : '').(empty($user->id) ? '' : ('&amp;userid='.$user->id)).'&amp;entity='.$conf->entity;

		$themeparam .= ($ext ? '&amp;'.$ext : '').'&amp;revision='.getDolGlobalInt("MAIN_IHM_PARAMS_REV");
		if (GETPOSTISSET('dol_hide_topmenu')) {
			$themeparam .= '&amp;dol_hide_topmenu='.GETPOST('dol_hide_topmenu', 'int');
		}
		if (GETPOSTISSET('dol_hide_leftmenu')) {
			$themeparam .= '&amp;dol_hide_leftmenu='.GETPOST('dol_hide_leftmenu', 'int');
		}
		if (GETPOSTISSET('dol_optimize_smallscreen')) {
			$themeparam .= '&amp;dol_optimize_smallscreen='.GETPOST('dol_optimize_smallscreen', 'int');
		}
		if (GETPOSTISSET('dol_no_mouse_hover')) {
			$themeparam .= '&amp;dol_no_mouse_hover='.GETPOST('dol_no_mouse_hover', 'int');
		}
		if (GETPOSTISSET('dol_use_jmobile')) {
			$themeparam .= '&amp;dol_use_jmobile='.GETPOST('dol_use_jmobile', 'int'); $conf->dol_use_jmobile = GETPOST('dol_use_jmobile', 'int');
		}
		if (GETPOSTISSET('THEME_DARKMODEENABLED')) {
			$themeparam .= '&amp;THEME_DARKMODEENABLED='.GETPOST('THEME_DARKMODEENABLED', 'int');
		}
		if (GETPOSTISSET('THEME_SATURATE_RATIO')) {
			$themeparam .= '&amp;THEME_SATURATE_RATIO='.GETPOST('THEME_SATURATE_RATIO', 'int');
		}
		if (!empty($conf->global->MAIN_ENABLE_FONT_ROBOTO)) {
			print '<link rel="preconnect" href="https://fonts.gstatic.com">'."\n";
			print '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@200;300;400;500;600&display=swap" rel="stylesheet">'."\n";
		}

		if (! defined('DISABLE_JQUERY') && ! $disablejs && $conf->use_javascript_ajax) {
			print '<!-- Includes CSS for JQuery (Ajax library) -->'."\n";
			$jquerytheme = 'base';
			if (!empty($conf->global->MAIN_USE_JQUERY_THEME)) {
				$jquerytheme = $conf->global->MAIN_USE_JQUERY_THEME;
			}
			if (constant('JS_JQUERY_UI')) {
				print '<link rel="stylesheet" type="text/css" href="'.JS_JQUERY_UI.'css/'.$jquerytheme.'/jquery-ui.min.css'.($ext ? '?'.$ext : '').'">'."\n"; // Forced JQuery
			} else {
				print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/includes/jquery/css/'.$jquerytheme.'/jquery-ui.css'.($ext?'?'.$ext:'').'">'."\n";    // JQuery
			}
			if (! defined('DISABLE_JQUERY_JNOTIFY')) {
				print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/includes/jquery/plugins/jnotify/jquery.jnotify-alt.min.css'.($ext?'?'.$ext:'').'">'."\n";          // JNotify
			}
			if (! defined('DISABLE_SELECT2') && (! empty($conf->global->MAIN_USE_JQUERY_MULTISELECT) || defined('REQUIRE_JQUERY_MULTISELECT'))) {     // jQuery plugin "mutiselect", "multiple-select", "select2"...
				$tmpplugin=empty($conf->global->MAIN_USE_JQUERY_MULTISELECT)?constant('REQUIRE_JQUERY_MULTISELECT'):$conf->global->MAIN_USE_JQUERY_MULTISELECT;
				print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/includes/jquery/plugins/'.$tmpplugin.'/dist/css/'.$tmpplugin.'.css'.($ext?'?'.$ext:'').'">'."\n";
			}
		}

		if (! defined('DISABLE_FONT_AWSOME')) {
			print '<!-- Includes CSS for font awesome -->'."\n";
			print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/theme/common/fontawesome-5/css/all.min.css'.($ext?'?'.$ext:'').'">'."\n";
			print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/theme/common/fontawesome-5/css/v4-shims.min.css'.($ext?'?'.$ext:'').'">'."\n";
		}

		print '<!-- Includes CSS for Dolibarr theme -->'."\n";
		// Output style sheets (optioncss='print' or ''). Note: $conf->css looks like '/theme/eldy/style.css.php'
		$themepath='styles.css.php';
		$themeparam='';
		//print 'themepath='.$themepath.' themeparam='.$themeparam;exit;
		print '<link rel="stylesheet" type="text/css" href="'.$themepath.$themeparam.'">'."\n";
		print '<link href="dist/css/bootstrap.css" type="text/css" rel="stylesheet">'."\n";
		if (! empty($conf->global->MAIN_FIX_FLASH_ON_CHROME)) {
			print '<!-- Includes CSS that does not exists as a workaround of flash bug of chrome -->'."\n".'<link rel="stylesheet" type="text/css" href="filethatdoesnotexiststosolvechromeflashbug">'."\n";
		}


		// CSS forced by page in top_htmlhead call (relative url starting with /)
		if (is_array($arrayofcss)) {
			foreach ($arrayofcss as $cssfile) {
				print '<!-- Includes CSS added by page -->'."\n".'<link rel="stylesheet" type="text/css" title="default" href="'.dol_buildpath($cssfile, 1);
				// We add params only if page is not static, because some web server setup does not return content type text/css if url has parameters and browser cache is not used.
				if (!preg_match('/\.css$/i', $cssfile)) {
					print $themeparam;
				}
				print '">'."\n";
			}
		}

		// Output standard javascript links
		if (! defined('DISABLE_JQUERY') && ! $disablejs && ! empty($conf->use_javascript_ajax)) {
			// JQuery. Must be before other includes
			print '<!-- Includes JS for JQuery -->'."\n";
			if (defined('JS_JQUERY') && constant('JS_JQUERY')) {
				print '<script src="'.JS_JQUERY.'jquery.min.js'.($ext?'?'.$ext:'').'"></script>'."\n";
			} else {
				print '<script src="'.DOL_URL_ROOT.'/includes/jquery/js/jquery.min.js'.($ext?'?'.$ext:'').'"></script>'."\n";
			}
			if (defined('JS_JQUERY_UI') && constant('JS_JQUERY_UI')) {
				print '<script src="'.JS_JQUERY_UI.'jquery-ui.min.js'.($ext?'?'.$ext:'').'"></script>'."\n";
			} else {
				print '<script src="'.DOL_URL_ROOT.'/includes/jquery/js/jquery-ui.min.js'.($ext?'?'.$ext:'').'"></script>'."\n";
			}
			// jQuery jnotify
			if (empty($conf->global->MAIN_DISABLE_JQUERY_JNOTIFY) && ! defined('DISABLE_JQUERY_JNOTIFY')) {
				print '<script src="'.DOL_URL_ROOT.'/includes/jquery/plugins/jnotify/jquery.jnotify.min.js'.($ext?'?'.$ext:'').'"></script>'."\n";
			}
			// Table drag and drop lines
			if (empty($disableforlogin) && !defined('DISABLE_JQUERY_TABLEDND')) {
				print '<script src="'.DOL_URL_ROOT.'/includes/jquery/plugins/tablednd/jquery.tablednd.min.js'.($ext ? '?'.$ext : '').'"></script>'."\n";
			}
			// Chart
			if (empty($disableforlogin) && (empty($conf->global->MAIN_JS_GRAPH) || $conf->global->MAIN_JS_GRAPH == 'chart') && !defined('DISABLE_JS_GRAPH')) {
				print '<script src="'.DOL_URL_ROOT.'/includes/nnnick/chartjs/dist/chart.min.js'.($ext ? '?'.$ext : '').'"></script>'."\n";
			}
			if (!defined('DISABLE_SELECT2') && (!empty($conf->global->MAIN_USE_JQUERY_MULTISELECT) || defined('REQUIRE_JQUERY_MULTISELECT'))) {
				// jQuery plugin "mutiselect", "multiple-select", "select2", ...
				$tmpplugin = empty($conf->global->MAIN_USE_JQUERY_MULTISELECT) ?constant('REQUIRE_JQUERY_MULTISELECT') : $conf->global->MAIN_USE_JQUERY_MULTISELECT;
				print '<script src="'.DOL_URL_ROOT.'/includes/jquery/plugins/'.$tmpplugin.'/dist/js/'.$tmpplugin.'.full.min.js'.($ext ? '?'.$ext : '').'"></script>'."\n"; // We include full because we need the support of containerCssClass
			}
			if (!defined('DISABLE_MULTISELECT')) {     // jQuery plugin "mutiselect" to select with checkboxes. Can be removed once we have an enhanced search tool
				print '<script src="'.DOL_URL_ROOT.'/includes/jquery/plugins/multiselect/jquery.multi-select.js'.($ext ? '?'.$ext : '').'"></script>'."\n";
			}
		}

		if (! $disablejs && ! empty($conf->use_javascript_ajax)) {
			// CKEditor
			if (empty($disableforlogin) && (isModEnabled('fckeditor') && (empty($conf->global->FCKEDITOR_EDITORNAME) || $conf->global->FCKEDITOR_EDITORNAME == 'ckeditor') && !defined('DISABLE_CKEDITOR')) || defined('FORCE_CKEDITOR')) {
				print '<!-- Includes JS for CKEditor -->'."\n";
				$pathckeditor = DOL_URL_ROOT . '/includes/ckeditor/ckeditor/';
				$jsckeditor='ckeditor.js';
				if (constant('JS_CKEDITOR')) {
					// To use external ckeditor 4 js lib
					$pathckeditor=constant('JS_CKEDITOR');
				}
				print '<script>';
				print '/* enable ckeditor by mainmyaccount.inc.php */';
				print 'var CKEDITOR_BASEPATH = \''.dol_escape_js($pathckeditor).'\';'."\n";
				// $themesubdir='' in standard usage
				$themesubdir = '';
				print 'var ckeditorConfig = \''.dol_escape_js(dol_buildpath($themesubdir.'/theme/'.$conf->theme.'/ckeditor/config.js'.($ext ? '?'.$ext : ''), 1)).'\';'."\n"; // $themesubdir='' in standard usage
				print 'var ckeditorFilebrowserBrowseUrl = \''.DOL_URL_ROOT.'/core/filemanagerdol/browser/default/browser.php?Connector='.DOL_URL_ROOT.'/core/filemanagerdol/connectors/php/connector.php\';'."\n";
				print 'var ckeditorFilebrowserImageBrowseUrl = \''.DOL_URL_ROOT.'/core/filemanagerdol/browser/default/browser.php?Type=Image&Connector='.DOL_URL_ROOT.'/core/filemanagerdol/connectors/php/connector.php\';'."\n";
				print '</script>'."\n";
				print '<script src="'.$pathckeditor.$jsckeditor.($ext?'?'.$ext:'').'"></script>'."\n";
				print '<script>';
				if (GETPOST('mode', 'aZ09') == 'Full_inline') {
					print 'CKEDITOR.disableAutoInline = false;'."\n";
				} else {
					print 'CKEDITOR.disableAutoInline = true;'."\n";
				}
				print '</script>'."\n";
			}

			// Browser notifications (if NOREQUIREMENU is on, it is mostly a page for popup, so we do not enable notif too. We hide also for public pages).
			if (!defined('NOBROWSERNOTIF') && !defined('NOREQUIREMENU') && !defined('NOLOGIN')) {
				$enablebrowsernotif=false;
				if (isModEnabled('agenda') && !empty($conf->global->AGENDA_REMINDER_BROWSER)) {
					$enablebrowsernotif = true;
				}
				if ($conf->browser->layout == 'phone') {
					$enablebrowsernotif = false;
				}
				if ($enablebrowsernotif) {
					print '<!-- Includes JS of Dolibarr (browser layout = '.$conf->browser->layout.')-->'."\n";
					print '<script src="'.DOL_URL_ROOT.'/core/js/lib_notification.js.php'.($ext?'?'.$ext:'').'"></script>'."\n";
				}
			}

			// Global js function
			print '<!-- Includes JS of Dolibarr -->'."\n";
			print '<script src="'.DOL_URL_ROOT.'/core/js/lib_head.js.php?lang='.$langs->defaultlang.($ext?'?'.$ext:'').'"></script>'."\n";

			// JS forced by page in top_htmlhead (relative url starting with /)
			if (is_array($arrayofjs)) {
				print '<!-- Includes JS added by page -->'."\n";
				foreach ($arrayofjs as $jsfile) {
					if (preg_match('/^(http|\/\/)/i', $jsfile)) {
						print '<script src="'.$jsfile.((strpos($jsfile, '?') === false) ? '?' : '&amp;').'lang='.$langs->defaultlang.'"></script>'."\n";
					} else {
						print '<script src="'.dol_buildpath($jsfile, 1).((strpos($jsfile, '?') === false) ? '?' : '&amp;').'lang='.$langs->defaultlang.'"></script>'."\n";
					}
				}
			}
		}

		if (!empty($head)) {
			print $head."\n";
		}
		if (!empty($conf->global->MAIN_HTML_HEADER)) {
			print $conf->global->MAIN_HTML_HEADER."\n";
		}

		print "</head>\n\n";
	}

	$conf->headerdone=1;	// To tell header was output
}



if (! function_exists("llxFooter")) {
	/**
	 * Show HTML footer
	 * Close div /DIV class=fiche + /DIV id-right + /DIV id-container + /BODY + /HTML.
	 * If global var $delayedhtmlcontent was filled, we output it just before closing the body.
	 *
	 * @param	string	$comment    				A text to add as HTML comment into HTML generated page
	 * @param	string	$zone						'private' (for private pages) or 'public' (for public pages)
	 * @param	int		$disabledoutputofmessages	Clear all messages stored into session without diplaying them
	 * @return	void
	 */
	function llxFooter($comment = '', $zone = 'private', $disabledoutputofmessages = 0)
	{
		global $conf, $langs, $user;
		global $delayedhtmlcontent;

		// Global html output events ($mesgs, $errors, $warnings)
		dol_htmloutput_events($disabledoutputofmessages);

		// Save $user->lastsearch_values if defined (define on list pages when a form field search_xxx exists)
		if (is_object($user) && ! empty($user->lastsearch_values_tmp) && is_array($user->lastsearch_values_tmp)) {
			// Clean data
			foreach ($user->lastsearch_values_tmp as $key => $val) {
				unset($_SESSION['lastsearch_values_tmp_'.$key]);
				if (count($val)) {
					if (empty($val['sortfield'])) unset($val['sortfield']);
					if (empty($val['sortorder'])) unset($val['sortorder']);
					dol_syslog('Save lastsearch_values_tmp_'.$key.'='.json_encode($val, 0, 1));
					$_SESSION['lastsearch_values_tmp_'.$key]=json_encode($val);
					unset($_SESSION['lastsearch_values_'.$key]);
				}
			}
		}

		// Core error message
		if (! empty($conf->global->MAIN_CORE_ERROR)) {
			if ($conf->use_javascript_ajax) {
				// Ajax version
				$title = img_warning().' '.$langs->trans('CoreErrorTitle');
				print ajax_dialog($title, $langs->trans('CoreErrorMessage'));
			} else {
				// html version
				$msg = img_warning().' '.$langs->trans('CoreErrorMessage');
				print '<div class="error">'.$msg.'</div>';
			}

			//define("MAIN_CORE_ERROR",0);      // Constant was defined and we can't change value of a constant
		}

		print "\n\n";

		print '</div> <!-- End div class="fiche" -->'."\n"; // End div fiche

		if (empty($conf->dol_hide_leftmenu)) print '</div> <!-- End div id-right -->'; // End div id-right

		print "\n";
		if ($comment) print '<!-- '.$comment.' -->'."\n";

		printCommonFooter($zone);
		//var_dump($langs);		// Uncommment to see the property _tab_loaded to see which language file were loaded

		if (! empty($conf->global->SELLYOURSAAS_MYACCOUNT_FOOTER)) {
			print $conf->global->SELLYOURSAAS_MYACCOUNT_FOOTER;
		}


		if (empty($conf->dol_hide_leftmenu) && empty($conf->dol_use_jmobile)) print '</div> <!-- End div id-container -->'."\n";	// End div container

		if (! empty($delayedhtmlcontent)) print $delayedhtmlcontent;

		// Wrapper to manage document_preview
		if (! empty($conf->use_javascript_ajax) && ($conf->browser->layout != 'phone')) {
			print "\n<!-- JS CODE TO ENABLE document_preview -->\n";
			print '<script type="text/javascript">
                jQuery(document).ready(function () {
			        jQuery(".documentpreview").click(function () {
            		    console.log("We click on preview for element with href="+$(this).attr(\'href\')+" mime="+$(this).attr(\'mime\'));
            		    document_preview($(this).attr(\'href\'), $(this).attr(\'mime\'), \''.dol_escape_js($langs->transnoentities("Preview")).'\');
                		return false;
        			});
        		});
            </script>' . "\n";
		}

		// Wrapper to manage dropdown
		if (! empty($conf->use_javascript_ajax) && ! defined('JS_JQUERY_DISABLE_DROPDOWN')) {
			print "\n<!-- JS CODE TO ENABLE dropdown -->\n";
			print '<script type="text/javascript">
                jQuery(document).ready(function () {
                  $(".dropdown dt a").on(\'click\', function () {
                      //console.log($(this).parent().parent().find(\'dd ul\'));
                      $(this).parent().parent().find(\'dd ul\').slideToggle(\'fast\');
                      // Note: Did not find a way to get exact height (value is update at exit) so i calculate a generic from nb of lines
                      heigthofcontent = 21 * $(this).parent().parent().find(\'dd div ul li\').length;
                      if (heigthofcontent > 300) heigthofcontent = 300; // limited by max-height on css .dropdown dd ul
                      posbottom = $(this).parent().parent().find(\'dd\').offset().top + heigthofcontent + 8;
                      //console.log(posbottom);
                      var scrollBottom = $(window).scrollTop() + $(window).height();
                      //console.log(scrollBottom);
                      diffoutsidebottom = (posbottom - scrollBottom);
                      console.log("heigthofcontent="+heigthofcontent+", diffoutsidebottom (posbottom="+posbottom+" - scrollBottom="+scrollBottom+") = "+diffoutsidebottom);
                      if (diffoutsidebottom > 0)
                      {
                            pix = "-"+(diffoutsidebottom+8)+"px";
                            console.log("We reposition top by "+pix);
                            $(this).parent().parent().find(\'dd\').css("top", pix);
                      }
                      // $(".dropdown dd ul").slideToggle(\'fast\');
                  });
                  $(".dropdowncloseonclick").on(\'click\', function () {
                     console.log("Link has class dropdowncloseonclick, so we close/hide the popup ul");
                     $(this).parent().parent().hide();
                  });

                  $(document).bind(\'click\', function (e) {
                      var $clicked = $(e.target);
                      if (!$clicked.parents().hasClass("dropdown")) $(".dropdown dd ul").hide();
                  });
                });
                </script>';
		}

		// A div for the address popup
		print "\n<!-- A div to allow dialog popup -->\n";
		print '<div id="dialogforpopup" style="display: none;"></div>'."\n";

		// Show conversion tracker.
		// The $_SESSION['showconversiontracker'] is set into code of the action 'createpaymentmode' after a payment mode has been recorded, into myaccount/index.php.
		if (! empty($_SESSION['showconversiontracker'])) {
			print "\n".'<!-- Conversion tracker $_SESSION[\'showconversiontracker\']='.$_SESSION['showconversiontracker'].' -->'."\n";
			if ($_SESSION['showconversiontracker'] == 'paymentrecorded') {
				print $conf->global->SELLYOURSAAS_CONVERSION_FOOTER;
				$_SESSION['showconversiontracker'] = '';
				unset($_SESSION['showconversiontracker']);
			}
		} else {
			print "\n".'<!-- No conversion tracker on this page -->'."\n";
		}

		print "</body>\n";
		print "</html>\n";
	}
}


if (! function_exists('dol_getprefix')) {
	/**
	 *  Return a prefix to use for this Dolibarr instance, for session/cookie names or email id.
	 *  This prefix is valid in a web context only and is unique for instance and avoid conflict
	 *  between multi-instances, even when having two instances with one root dir or two instances
	 *  in virtual servers.
	 *
	 *  @param  string  $mode       			'' (prefix for session name) or 'email' (prefix for email id)
	 *  @return	string      					A calculated prefix
	 */
	function dol_getprefix($mode = '')
	{
		// If prefix is for email (we need to have $conf alreayd loaded for this case)
		if ($mode == 'email') {
			global $conf;

			if (! empty($conf->global->MAIL_PREFIX_FOR_EMAIL_ID)) {	// If MAIL_PREFIX_FOR_EMAIL_ID is set (a value initialized with a random value is recommended)
				if ($conf->global->MAIL_PREFIX_FOR_EMAIL_ID != 'SERVER_NAME') {
					return 'sellyoursaas'.$conf->global->MAIL_PREFIX_FOR_EMAIL_ID;
				} elseif (isset($_SERVER["SERVER_NAME"])) {
					return 'sellyoursaas'.$_SERVER["SERVER_NAME"];
				}
			}

			// The recommended value (may be not defined for old versions)
			if (! empty($conf->file->instance_unique_id)) {
				return sha1('sellyoursaas'.$conf->file->instance_unique_id);
			}

			// For backward compatibility
			return sha1('sellyoursaas'.DOL_DOCUMENT_ROOT.DOL_URL_ROOT);
		}

		// If prefix is for session (no need to have $conf loaded)
		global $dolibarr_main_instance_unique_id, $dolibarr_main_cookie_cryptkey;	// This is loaded by filefunc.inc.php
		$tmp_instance_unique_id = empty($dolibarr_main_instance_unique_id) ? (empty($dolibarr_main_cookie_cryptkey) ? '' : $dolibarr_main_cookie_cryptkey) : $dolibarr_main_instance_unique_id; // Unique id of instance

		// The recommended value (may be not defined for old versions)
		if (!empty($tmp_instance_unique_id)) {
			return sha1('sellyoursaas'.$tmp_instance_unique_id);
		}

		// For backward compatibility
		if (isset($_SERVER["SERVER_NAME"]) && isset($_SERVER["DOCUMENT_ROOT"])) {
			return sha1('sellyoursaas'.$_SERVER["SERVER_NAME"].$_SERVER["DOCUMENT_ROOT"].DOL_DOCUMENT_ROOT.DOL_URL_ROOT);
		}

		return sha1('sellyoursaas'.DOL_DOCUMENT_ROOT.DOL_URL_ROOT);
	}
}


/**
 * Show login page for myaccount.
 *
 * @param		Translate	$langs		Lang object (must be initialized by a new).
 * @param		Conf		$conf		Conf object
 * @param		Societe		$mysoc		Company object
 * @return		void
 */
function dol_loginfunction($langs, $conf, $mysoc)
{
	global $dolibarr_main_demo, $db;
	global $hookmanager;

	//$langs=new Translate('', $conf);
	$langs->setDefaultLang(GETPOST('lang', 'aZ09')?GETPOST('lang', 'aZ09'):'auto');

	$langs->loadLangs(array("main","other","help","admin","sellyoursaas@sellyoursaas"));

	// Instantiate hooks of thirdparty module only if not already define
	$hookmanager->initHooks(array('mainmyaccountloginpage'));

	$main_authentication=$conf->file->main_authentication;

	$session_name=session_name();	// Get current session name

	$dol_url_root = DOL_URL_ROOT;

	// Title
	$appli=constant('DOL_APPLICATION_TITLE');
	$title=$appli.' '.constant('DOL_VERSION');
	if (! empty($conf->global->MAIN_APPLICATION_TITLE)) $title=$conf->global->MAIN_APPLICATION_TITLE;
	$titletruedolibarrversion=constant('DOL_VERSION');	// $title used by login template after the @ to inform of true Dolibarr version
	$title=$langs->trans("YourCustomerDashboard");
	if (GETPOST('reseller', 'alpha')) $title=$langs->trans("YourCustomerOrResellerDashboard");

	// Note: $conf->css looks like '/theme/eldy/style.css.php'
	$conf->css = "/theme/".(GETPOST('theme', 'alpha')?GETPOST('theme', 'alpha'):$conf->theme)."/style.css.php";
	$themepath=dol_buildpath($conf->css, 1);
	if (! empty($conf->modules_parts['theme'])) {		// Using this feature slow down application
		foreach ($conf->modules_parts['theme'] as $reldir) {
			if (file_exists(dol_buildpath($reldir.$conf->css, 0))) {
				$themepath=dol_buildpath($reldir.$conf->css, 1);
				break;
			}
		}
	}
	$conf_css = $themepath."?lang=".$langs->defaultlang;
	$conf_css = './styles.css.php';

	// Select templates dir
	$template_dir = dirname(__FILE__).'/tpl/';

	// Set cookie for timeout management
	$prefix=dol_getprefix('');
	$sessiontimeout='DOLSESSTIMEOUT_'.$prefix;
	if (! empty($conf->global->MAIN_SESSION_TIMEOUT)) setcookie($sessiontimeout, $conf->global->MAIN_SESSION_TIMEOUT, 0, "/", null, false, true);

	if (GETPOST('urlfrom', 'alpha')) $_SESSION["urlfrom"]=GETPOST('urlfrom', 'alpha');
	else unset($_SESSION["urlfrom"]);

	if (! GETPOST("username", 'alpha')) $focus_element='username';
	else $focus_element='password';

	$demologin='';
	$demopassword='';
	if (! empty($dolibarr_main_demo)) {
		$tab=explode(',', $dolibarr_main_demo);
		$demologin=$tab[0];
		$demopassword=$tab[1];
	}

	// Execute hook getLoginPageOptions (for table)
	$parameters=array('entity' => GETPOST('entity', 'int'));
	$reshook = $hookmanager->executeHooks('getLoginPageOptions', $parameters);    // Note that $action and $object may have been modified by some hooks.
	if (is_array($hookmanager->resArray) && ! empty($hookmanager->resArray)) {
		$morelogincontent = $hookmanager->resArray; // (deprecated) For compatibility
	} else {
		$morelogincontent = $hookmanager->resPrint;
	}

	// Execute hook getLoginPageExtraOptions (eg for js)
	$parameters=array('entity' => GETPOST('entity', 'int'));
	$reshook = $hookmanager->executeHooks('getLoginPageExtraOptions', $parameters);    // Note that $action and $object may have been modified by some hooks.
	$moreloginextracontent = $hookmanager->resPrint;

	// Login
	$login = (! empty($hookmanager->resArray['username']) ? $hookmanager->resArray['username'] : (GETPOST("username", "alpha") ? GETPOST("username", "alpha") : $demologin));
	$password = $demopassword;

	// Show logo (search in order: small company logo, large company logo, theme logo, common logo)
	$width=0;
	$urllogo = '';
	$constlogo = 'SELLYOURSAAS_LOGO';
	$constlogosmall = 'SELLYOURSAAS_LOGO_SMALL';

	include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

	$sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;
	$sellyoursaasdomain = $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME;

	$domainname=getDomainFromURL($_SERVER['SERVER_NAME'], 1);
	$constforaltname = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$domainname;

	if (! empty($conf->global->$constforaltname)) {
		$sellyoursaasdomain = $domainname;
		$sellyoursaasname = $conf->global->$constforaltname;
		$constlogo.='_'.strtoupper(str_replace('.', '_', $sellyoursaasdomain));
		$constlogosmall.='_'.strtoupper(str_replace('.', '_', $sellyoursaasdomain));
	}

	$homepage = 'https://'.(empty($conf->global->SELLYOURSAAS_FORCE_MAIN_DOMAIN_NAME) ? $sellyoursaasdomain : $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME);

	if (empty($urllogo) && ! empty($conf->global->$constlogosmall)) {
		if (is_readable($conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constlogosmall)) {
			$urllogo=DOL_URL_ROOT.'/viewimage.php?cache=1&amp;modulepart=mycompany&amp;file='.urlencode('logos/thumbs/'.$conf->global->$constlogosmall);
		}
	} elseif (empty($urllogo) && ! empty($conf->global->$constlogo)) {
		if (is_readable($conf->mycompany->dir_output.'/logos/'.$conf->global->$constlogo)) {
			$urllogo=DOL_URL_ROOT.'/viewimage.php?cache=1&amp;modulepart=mycompany&amp;file='.urlencode('logos/'.$conf->global->$constlogo);
			$width=128;
		}
	} elseif (empty($urllogo) && is_readable(DOL_DOCUMENT_ROOT.'/theme/'.$conf->theme.'/img/dolibarr_logo.png')) {
		$urllogo=DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/dolibarr_logo.png';
	} elseif (empty($urllogo) && is_readable(DOL_DOCUMENT_ROOT.'/theme/dolibarr_logo.png')) {
		$urllogo=DOL_URL_ROOT.'/theme/dolibarr_logo.png';
	} else {
		$urllogo=DOL_URL_ROOT.'/theme/login_logo.png';
	}

	// Security graphical code
	$captcha=0;
	$captcha_refresh='';
	if (function_exists("imagecreatefrompng") && ! empty($conf->global->MAIN_SECURITY_ENABLECAPTCHA)) {
		$captcha=1;
		$captcha_refresh=img_picto($langs->trans("Refresh"), 'refresh', 'id="captcha_refresh_img"');
	}

	// Extra link
	$forgetpasslink=0;
	$helpcenterlink=0;
	if (empty($conf->global->MAIN_SECURITY_DISABLEFORGETPASSLINK) || empty($conf->global->MAIN_HELPCENTER_DISABLELINK)) {
		if (empty($conf->global->MAIN_SECURITY_DISABLEFORGETPASSLINK)) {
			$forgetpasslink=1;
		}

		if (empty($conf->global->MAIN_HELPCENTER_DISABLELINK)) {
			$helpcenterlink=1;
		}
	}

	// Home message
	$main_home='';
	if (! empty($conf->global->MAIN_HOME)) {
		$substitutionarray=getCommonSubstitutionArray($langs);
		complete_substitutions_array($substitutionarray, $langs);
		$texttoshow = make_substitutions($conf->global->MAIN_HOME, $substitutionarray, $langs);

		$main_home=dol_htmlcleanlastbr($texttoshow);
	}

	// Google AD
	$main_google_ad_client = ((! empty($conf->global->MAIN_GOOGLE_AD_CLIENT) && ! empty($conf->global->MAIN_GOOGLE_AD_SLOT))?1:0);

	// Set jquery theme
	$dol_loginmesg = (! empty($_SESSION["dol_loginmesg"])?$_SESSION["dol_loginmesg"]:'');
	$favicon='';
	if (! empty($conf->global->MAIN_FAVICON_URL)) $favicon=$conf->global->MAIN_FAVICON_URL;
	$jquerytheme = 'base';
	if (! empty($conf->global->MAIN_USE_JQUERY_THEME)) $jquerytheme = $conf->global->MAIN_USE_JQUERY_THEME;

	// Set dol_hide_topmenu, dol_hide_leftmenu, dol_optimize_smallscreen, dol_no_mouse_hover
	$dol_hide_topmenu=GETPOST('dol_hide_topmenu', 'int');
	$dol_hide_leftmenu=GETPOST('dol_hide_leftmenu', 'int');
	$dol_optimize_smallscreen=GETPOST('dol_optimize_smallscreen', 'int');
	$dol_no_mouse_hover=GETPOST('dol_no_mouse_hover', 'int');
	$dol_use_jmobile=GETPOST('dol_use_jmobile', 'int');

	// Include login page template
	include $template_dir.'loginmyaccount.tpl.php';

	// Global html output events ($mesgs, $errors, $warnings)
	dol_htmloutput_events(0);

	$_SESSION["dol_loginmesg"] = '';
}
