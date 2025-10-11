# CHANGELOG SELLYOURSAAS FOR <a href="https://www.dolibarr.org">DOLIBARR ERP CRM</a>


## Unreleased

* NEW Add warning on support page if contact info are not complete
* NEW Add badge with number of deployment server on tab title of deployment servers.
* NEW Add the tab for histori of events info on a deployment server card.
* NEW Options to disable the apt unattended-upgrade. Such silent upgrade changes system files on disk making fight against intrusion detection more difficult.
* NEW Can for a server on a service.
* NEW Search on firstname and last name of thirdparty with global search on contract.  
* NEW Full compatibility with PHP 8.4 (need dolibarr v22)
* NEW Can set a list of service on registration url to choose which package to install during registration
* NEW Add hidden option SELLYOURSAAS_EMAILORURL_TO_WARN to set another email or contact to contact
* NEW Search also on sellyoursaas firstname and lastname when using the quick filter on thirdparty
* NEW Can use an url instead of email to warn about a trouble on registration
* NEW Add list of pending reseller in the SellYourSaas widget
* NEW Can defined a whitelist of emails for registration (for special emails where MX record detection fails)
* NEW For payment mode credit transfer, an email is sent at invoice validation
* NEW Can filter on instance mask when selecting target of email.
* NEW Add info on how to use ducs command in backup tab.
* NEW Support discount codes with a number part (MYDISCOUNTCODE-123) acting as MYDISCOUNTCODE.
* NEW Add hostname property in list of deployment servers  
* NEW Add option SELLYOURSAAS_FORCE_NO_SELECTION_IF_SEVERAL
* NEW More protection on public registration
* NEW Can show the label on server on registration page
* NEW Add label on deployment servers
* NEW When invoice is flagged as dispute, we do not try to take payment
* NEW Can add recaptcha on registration page.
* NEW Add picto evil and dashboard at begin of thirdparty link
* NEW The action set as spammer flags also the thirdparty
* NEW Better log for registration process
* NEW Add mass action undeploy
* NEW Add script master_move_several_instances.php 
* NEW Add a tool to test the email like it is done during registration. 
* NEW On payment by card, the last 4 numbers are saved into the field "numero" of the payment in database.
* NEW Can force permission to see SSH/Mysql access per instance.
* NEW Show the possible substitutions keys for the helpdesk url into setup.
* NEW Support emails with + inside
* NEW Accept emojis in email templates
* NEW No need to reenter the 2 passwords when an instance creation failed.
* NEW Add public/private notes.
* NEW Add tool desktop_bannedip.sh to check ban and unban
* NEW Add support for a signature key of remote action messages.
* NEW Length of database and user name is now 12 and password 14 min.
* NEW Update the Stripe IPN service to be compatible with last Stripe API version (2023-10-16)
* FIX Redirection instances must not appear in the count of backuped instances.
* FIX letsencrypt for wildcard are now using 2 passes and needs 2 TXT entry in DNS.
* FIX Debug master_move_instance.php - Price of old instance is kept/reused.
* FIX code for email template to use to send email for credit transfer payment.
* SEC When using ssh, a user can't see the OS and package information.


## 2.1

* NEW Add ansible script to edit crontab on all servers.
* NEW Show statistics (success/error) of remote backups in list of deployment servers
* NEW Backup for instance is using localhostip if possible
* FIX Event "Increase end date of services for contract" was not linked to the thirdparty.
* NEW Better mesage on PDF in customer dashboard when PDF not yet available (SEPA payment).
* NEW Add option --nostats to not update stats when doing a backup
* NEW Show oldest date of backup ok/ko in popup on list of instances
* NEW Removed a lot of useless or duplicated SQL request in remoteAction.
* NEW Add a test on a signature of parameters provided to remoteaction
* NEW Request to become a reseller are now store into database. Add a menu entry to list all pending requests to become a reseller.
* NEW Add allowed patterns for shell scripts (CLI after install, CLI after switch to pay)
* NEW reorganisation of the setup tabs. Add a dedicated tab for reseller and endpoints options.
* FIX Notice on login attempt on myaccount fixed to allow fail2ban.
* FIX $emailtowarn on job scheduled was not using the sellyoursaas setuped email.
* NEW Can filter and sort in the list of "users of" a given instance.
* NEW Enhance script disk_used_per_instance to allow to process 1 instance only and to run updates manually
* NEW More options on SELLYOURSAAS_ONLY_NON_PROFIT_ORGA
* NEW Features "Shell after swtiching to paid" and "Sql after switch to paid" are now available.
* NEW Add the date of the first access.
* NEW Use a local cache for deployment if no remote cache is found.
* NEW Show info on latest backup on tooltip on column "Nb of backup"  
* NEW Add property invoice payment disputed on invoice


## 2.0

* FIX Renewal of letsencrypt wildcard certificate when we use more than 1 domain on the same server
* FIX Email sent by sellyoursaas admin tools on deployment server are sent using local mail
* NEW Payment mode of recurring invoices is automatically switched to credit card or direct debit when choosing payment mode from customer dashboard.
* NEW Can set the alternative style into config SELLYOURSAAS_EXTCSS instead of URL
* NEW Add --prune-empty-dirs on backup scripts to save inodes
* NEW Add a link to switch directly to the list of backup errors from the counter on list of deployment server
* NEW Add a copy/paste picto after each field in the SSH/SFTP/Database info tabs 
* NEW Can search on os or db username or db name from the quick search
* NEW Powerfull users need to enter a password for sudo with new default setup
* NEW Can use wildcard into list of whitelist IP (e.g. "1.2.3.*")
* NEW Add test on user admin for scripts that must be launched as "admin".
* NEW Save the choice "I am a non profit organization" of thirdparty creation.
* NEW Add SELLYOURSAAS_DISABLE_NEW_INSTANCES_EXCEPT_IP to set a list of IPs allowed to register even when registrations are globally closed.
* NEW Can set a message on the registration page (instead of generic message), when we disable globally creation of new instances.
* NEW Remove some warnings to prepare compatibility with PHP 8.2


## 1.3

 * NEW Can set max number of emails per instance
 * NEW Add count of backup and error backup into list of servers
 * NEW Store list of deployment servers into a table
 * NEW Add script disk_used_per_instance.sh
 * NEW Can send an email on sellyoursaas job error (need dolibarr 17+) 
 * NEW Can add a server and remove it from the Deployment tab. Need write permission of sellyoursaas. No more need for admin status.  
 * NEW Retreiving version of a deployed instance uses the SQL definition found into package instead of hardcoded SQL.
 * NEW Can defined frequency of backup per instance
 * NEW Show the number of open instances in the page to setup Deployment servers  
 * NEW Using the restore_instance tool add a line into historic of events for instance.
 * NEW Add a page view to list instances with current backup errors
 * NEW Add some blacklist tables et page to edit them.
 * NEW Add a whitelist table and page to edit it. Any IP in this table is allowed to create instance whatever are other checks.
 * NEW Add an evil picto on instances flagged as evil
 * NEW Add a page to list all evil instances (with filter predefined for this)
 * NEW Enhance the clean.sh script
 * NEW Add a parameter "Max number of deployement per IP (VPN)" (default 1) that is specific on VPN usage so different
       than the paramater "Max number of deployement per IP" (default 4)
 * NEW Add option SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE
 * NEW Can enable/disable external service to validate IP or email in a simple click into SellYourSaas module setup.
 * NEW The option "Direct access to SFTP/mysql" on service setup now accept the value "On demand only". The tab will be visible to
   customer with a message to ask the access doing a ticket.
 * NEW Split the setup page into several pages.
 * NEW Can contact IPQualityScore external service to validate email quality (like done for IP validation)
 * NEW Add option backuprsyncdayfrequency and backupdumpdayfrequency (Value 1 by default).
 * Experimental feature to allow end user to migrate its instance from its own backup (works for a Dolibarr instance only)
 * Experimental feature to allow end user to upgrate its instance to a higher version (works for a Dolibarr instance only)
 * FIX Can show a different alert/announce message for each deployment server.  
 * FIX Timezone is filled also for instances created from customer dashboard


## 1.2

* NEW #190 : Add form on uninstall. Enable on option SELLYOURSAAS_ASK_DESTROY_REASON.
* NEW First version of a process of automigration.
* NEW Exclude running installation from more than 24h into quota.
* NEW Add hidden option SELLYOURSAAS_ENABLE_SEPA_FOR_THIRDPARTYID
* NEW Add a limit of number of email with a "per server" setup (inside file /etc/sellyoursaas-public.conf)
* NEW Add Ansible scripts to update the sellyoursaas[-public].conf file.
* NEW #215: Add possibility to force a plan price for resselers
* NEW Add hidden option SELLYOURSAAS_LINK_TO_PARTNER_IF_FIRST_SOURCE to link a registration to a reseller when was just a first origin on
  web site and even when reseller id was not explicitely into registration url with utm_source=partnerXXX.
* NEW Add option SELLYOURSAAS_SUPPORT_URL
* Fix the payment term of recurring invoice was not propagated on invoice created just after a fix of credit card.
* Fix can not use a closed deployment server with html injection.
* NEW Add option backupignoretables in sellyoursaas.conf to exclude some tables from backup
* NEW Add protection against blacklisted ip on registration


## 1.1

* Support a variable __SMTP_SPF_STRING__ as a substitution string inside the action "SQL after a deployment".
* Can set maxemailperday into the sellyoursaas.conf file.
* Add option SELLYOURSAAS_ONLY_NON_PROFIT_ORGA
* Add a selector to select all resellers for emailing module
* FIX hook afterPDFCreation when there is several pages
* Add SELLYOURSAAS_SUPPORT_SHOW_MESSAGE to show a message
* NEW Add graph for average basket


## 1.0
Initial version.

