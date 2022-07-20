# CHANGELOG SELLYOURSAAS FOR <a href="https://www.dolibarr.org">DOLIBARR ERP CRM</a>


## 1.3

 * NEW Add some blacklist tables et page to edit them.
 * NEW Add a whitelist table and page to edit it. Any IP in this table is allowed to create instance whatever are other checks.
 * NEW Add an evil picto on instances flagged as evil
 * NEW Add a page to list all evil instances (with filter predefined for this)
 * NEW Enhance the clean.sh script
 * NEW Add option SELLYOURSAAS_MAX_MONTHLY_AMOUNT_OF_INVOICE
 * NEW Add a parameter "Max number of deployement per IP (VPN)" (default 1) that is specific on VPN usage so different
       than the paramater "Max number of deployement per IP" (default 4)
 * NEW Can enablde/disable external service to validate IP or email in a simple click into SellYourSaas module setup.
 * NEW Add option Direct SFTP/mysql access "On demand only"
 * Prepare code for service to validate email quality (like done for IP validation)
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

