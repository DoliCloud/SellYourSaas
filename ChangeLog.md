# CHANGELOG SELLYOURSAAS FOR <a href="https://www.dolibarr.org">DOLIBARR ERP CRM</a>

## 1.2

* Close #190 : Add form on uninstall.
* First version of a process of automigration.
* Exclude running installation from more than 24h into quota.
* Add hidden option SELLYOURSAAS_ENABLE_SEPA_FOR_THIRDPARTYID
* Add a limit of number of email with a "per server" setup (inside file /etc/sellyoursaas-public.conf)
* Add Ansible scripts to update the sellyoursaas[-public].conf file.
* Close #215: Add possibility to force a plan price for resselers
* Fix the payment term of recurring invoice was not propagated on invoice created just after a fix of credit card.
* Fix can not use a closed deployment server with html injection.


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

