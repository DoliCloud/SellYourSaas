<?php
/* Copyright (C) 2018 Laurent Destailleur <eldy@users.sourceforge.net>
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
 *      \file       htdocs/sellyoursaas/core/login/functions_sellyoursaas.php
 *      \ingroup    core
 *      \brief      Authentication functions for Sellyoursaas backoffice
 */


/**
 * Check validity of user/password/entity
 * If test is ko, reason must be filled into $_SESSION["dol_loginmesg"]
 *
 * @param	string	$usertotest		Login
 * @param	string	$passwordtotest	Password
 * @param   int		$entitytotest   Number of instance (always 1 if module multicompany not enabled)
 * @return	string					Login if OK, '' if KO
 */
function check_user_password_sellyoursaas($usertotest, $passwordtotest, $entitytotest)
{
	global $conf, $langs, $db;

	dol_syslog("functions_sellyoursaas::check_user_password_sellyoursaas usertotest=".$usertotest);

	$thirdparty = new Societe($db);
	$result = $thirdparty->fetch(0, '', '', '', '', '', '', '', '', '', $usertotest);

	if ($result <= 0) {
		dol_syslog("functions_sellyoursaas::check_user_password_sellyoursaas Authentication KO not allowed for user '".$usertotest."'", LOG_NOTICE);
		sleep(1);	// Anti brut force protection. Must be same delay when password is not valid

		// Load translation files required by the page
		$langs->loadLangs(array('main', 'errors'));

		$login = '';
		$_SESSION["dol_loginmesg"]=$langs->transnoentitiesnoconv("ErrorBadLoginPassword");
	} else {
		//dol_syslog("thirdparty found with id=".$thirdparty->id);

		// Test with hash
		if (GETPOST('login_hash', 'alpha', 1)) {
			$dol_login_hash=dol_hash(getDolGlobalString('SELLYOURSAAS_KEYFORHASH').$usertotest.dol_print_date(dol_now(), 'dayrfc'), 5);	// hash is valid one day
			//var_dump(GETPOST('login_hash', 'alpha', 1));
			//var_dump($dol_login_hash);exit;

			if (GETPOST('login_hash', 'alpha', 1) == $dol_login_hash) {
				$tmpuser = new User($db);
				$tmpuser->fetch(getDolGlobalInt('SELLYOURSAAS_ANONYMOUSUSER'));
				if ($tmpuser->login) {
					if ($tmpuser->status == $tmpuser::STATUS_DISABLED) {
						// Load translation files required by the page
						$langs->loadLangs(array('main', 'errors', 'sellyoursaas'));

						$_SESSION["dol_loginmesg"] = 'SellYourSaasSetupNotComplete: The anonymous user has been set to a disabled user.';
						return '';
					} else {
						// Login is ok
						$_SESSION["dol_loginsellyoursaas"] = $thirdparty->id;
						return $tmpuser->login;
					}
				} else {
					dol_syslog("functions_sellyoursaas::check_user_password_sellyoursaas Authentication KO Setup not complete", LOG_NOTICE);

					$_SESSION["dol_loginmesg"] = 'SellYourSaasSetupNotComplete: The anonymous user to use for customer dashboard has not been set';		// Set invisible message
					return '';
				}
			}
		}

		if (empty($passwordtotest)) {
			$_SESSION["dol_loginmesg"]='<!-- No message -->';		// Set invisible message
			return '';
		}

		// Test password validity.
		// Default usage is to have password stored into extrafields (options_password) and encoded with password_hash (Value looks like $2y$10B...)
		// Old versions may have stored password using sha/md5 encoding.
		// The column oldpassword was used to store the password hash coming from an another information system.

		$passwordtotest_crypted = dol_hash($passwordtotest);

		/*var_dump($passwordtotest);
		var_dump(dol_hash($passwordtotest));
		var_dump($thirdparty->array_options['options_password']);
		var_dump($thirdparty->array_options['options_oldpassword']);
		var_dump(hash('sha256', $passwordtotest));
		*/

		if (dol_verifyHash($passwordtotest, $thirdparty->array_options['options_password']) ||
			$passwordtotest_crypted == $thirdparty->array_options['options_password'] ||			// For compatibility with old versions
			hash('sha256', $passwordtotest) == $thirdparty->array_options['options_oldpassword']	// For compatibility with old versions
			) {
			if (!getDolGlobalString('SELLYOURSAAS_ANONYMOUSUSER')) {
				dol_syslog("functions_sellyoursaas::check_user_password_sellyoursaas Authentication KO Setup not complete", LOG_NOTICE);

				// Load translation files required by the page
				$langs->loadLangs(array('main', 'errors'));

				$login='';
				$_SESSION["dol_loginmesg"] = 'SellYourSaasSetupNotComplete Anonymous user not defined';
			} else {
				// If authentication is OK, we force to use the user defined into SellYourSaas setup by SELLYOURSAAS_ANONYMOUSUSER
				$tmpuser = new User($db);
				$tmpuser->fetch(getDolGlobalInt('SELLYOURSAAS_ANONYMOUSUSER'));
				if ($tmpuser->login) {
					// Second factor login, if any module implementing it is installed: entirely
					// hook-driven (context 'mainmyaccountloginpage', method checkSecondFactorLogin)
					// so this file never needs to know which module (twofactorauth or an
					// equivalent) provides it, nor which POST field names/verification method it
					// uses - the listening module owns all of that internally and only reports
					// back an abstract status.
					//   status 'none'    : nothing enrolled for this thirdparty via this provider, proceed
					//   status 'pending' : second factor required, none supplied in this request yet
					//   status 'success' : second factor required and verified
					//   status 'error'   : second factor required, supplied but invalid
					//   status 'blocked' : too many failed attempts, pending state fully reset
					// No SELLYOURSAAS_ENABLE_2FA check here on purpose: whether the toggle is
					// on or off is entirely the listening module's call to make (e.g. it may
					// choose to keep enforcing 2FA for an already-enrolled thirdparty even
					// after the toggle is switched off, and only use it to hide new
					// enrollment - see printSecondFactorSettings on the twofactorauth side).
					$secondfactorstatus = 'none';
					$secondfactormessage = '';
					global $hookmanager;
					if (!is_object($hookmanager)) {
						include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
						$hookmanager = new HookManager($db);
					}
					$hookmanager->initHooks(array('mainmyaccountloginpage'));

					$parameters = array('socid' => $thirdparty->id);
					$hookobject = null;
					$hookaction = '';
					$hookmanager->executeHooks('checkSecondFactorLogin', $parameters, $hookobject, $hookaction);

					if (!empty($hookmanager->resArray['status'])) {
						// A hook result can come back as a plain string (one listener) or, per
						// Dolibarr's array_merge_recursive-based hook aggregation, as an array of
						// values if more than one module answered - take the first one, a single
						// active second-factor provider is the supported scenario here.
						$secondfactorstatus = is_array($hookmanager->resArray['status']) ? reset($hookmanager->resArray['status']) : $hookmanager->resArray['status'];
					}
					if (!empty($hookmanager->resArray['message'])) {
						$secondfactormessage = is_array($hookmanager->resArray['message']) ? reset($hookmanager->resArray['message']) : $hookmanager->resArray['message'];
					}

					if ($secondfactorstatus == 'pending') {
						// Password just validated, second factor not supplied yet - the login
						// template re-renders in 2FA mode within this same request (no redirect
						// happens today between checkLoginPassEntity() failing and
						// dol_loginfunction() re-rendering, same mechanism the existing "bad
						// password" case already relies on), so username/password stay available
						// to re-post from GETPOST() on the next submission - nothing needs to be
						// stashed server side beyond which thirdparty is pending.
						$_SESSION['sellyoursaas_2fa_pending_socid'] = $thirdparty->id;
						$_SESSION["dol_loginmesg"] = '<!-- No message -->';
						return '';
					} elseif ($secondfactorstatus == 'blocked') {
						unset($_SESSION['sellyoursaas_2fa_pending_socid']);
						sleep(1);
						$langs->loadLangs(array('main', 'errors'));
						$_SESSION["dol_loginmesg"] = $langs->transnoentitiesnoconv("ErrorTooManyAttempts");
						return '';
					} elseif ($secondfactorstatus == 'error') {
						// stay in 2FA mode, don't fall back to password re-entry
						$_SESSION['sellyoursaas_2fa_pending_socid'] = $thirdparty->id;
						sleep(1); // Anti brute-force protection. Must be same delay when 2FA is not valid
						dol_syslog("functions_sellyoursaas::check_user_password_sellyoursaas Authentication KO bad second factor for '" . $usertotest . "'", LOG_NOTICE);
						$langs->loadLangs(array('main', 'errors'));
						$_SESSION["dol_loginmesg"] = (!empty($secondfactormessage) ? $secondfactormessage : $langs->transnoentitiesnoconv("ErrorBadLoginPassword"));
						return '';
					}

					// status 'none' or 'success': login is ok
					unset($_SESSION['sellyoursaas_2fa_pending_socid']);
					$_SESSION["dol_loginsellyoursaas"] = $thirdparty->id;
					return $tmpuser->login;
				}
			}
		} else {
			dol_syslog("functions_sellyoursaas::check_user_password_sellyoursaas Authentication KO not allowed for user '".$usertotest."'", LOG_NOTICE);
			sleep(1);	// Anti brut force protection. Must be same delay when password is not valid

			// Load translation files required by the page
			$langs->loadLangs(array('main', 'errors'));

			$login='';
			$_SESSION["dol_loginmesg"]=$langs->transnoentitiesnoconv("ErrorBadLoginPassword");
		}
	}

	return $login;
}
