# SELLYOURSAAS


## Features

SellYourSaas is a tool to offer any Web application as a Saas service (for a free or paid offer).

<span style="color: #268260">100% of the needs, from customer acquisition to generation of information for end-of-year accounting, including deployment, management and backup of customer instances are provided by the project.</span>
 
The project provides ready to use and friendly interfaces to record definition of the packages to sell (which application, where to find sources and database images, how to deploy them), the tools to deploy the application on a remote server (on a simple mono server infrastructure or into a scalable farm), but also a customer dashboard for your subscribers, automatic invoicing and renewal. The administration and backoffice of solution is based on Dolibarr ERP CRM Open Source software.

This is a list of some features supported by this project:

- Creation of profiles of packages - to define what to deploy when a subscription to this package is done: files or directories to deploy, databases dump to load, cron entry to add, SSH public keys of administrator to deploy and any other command lines to launch.
- Create services that define the plan (which package and which options) and the price policy to use for invoicing: free, per application, per user, per Gigabytes, ... (or any combination).
- Customizable SQL or CLI commands for each sold services to define how to count the quantity to bill (For example a SQL request to count the number of users or a CLI command to get the consummed disk space).
- Get ready to use URLs of public online subscription forms to register to the hosting service.
- Autofill and autodetection of country and language in the subscription page using GeoIP, recording a probability of VPN usage for each subscriber (for fight against spammer or evil users).
- Each customer has its own system and data environment (jail).
- Option to support free subscription, time limited trial periods (with no credit card required) or payment instances.
- Define quotas (deployment per customer, per ip, per hour, ...).
- Provide a customer dashboard for customers to manage their subscription, download their invoices. You also decide if customers can deploy more applications or options with the same existing account from their customer dashboard. 
- Decide if customer has a direct access to the database and a restricted (or not) SSH access to its instance.
- Add a system layer to replace the php "mail" function to track and stop evil users using their created instance to try to make Spams.  
- Manage a network of reseller with commission dedicated to each reseller (a reseller has its own URL to register/create a new instances of an application and any customer that use it to create its instance is linked to the reseller. Reseller will gain a commission for each invoice paid by the customer). 
- Payment of customers can be done automatically by credit card (currently using Stripe service) or semi-automatically by SEPA mandate.
- Billing rules (date, amount, frequency of next payment) can be modified differently for each customer.
- Provide a lot of predefined email templates in several languages for the management of service (subscription, trial expiration, cancellation, ...)
- Can manage each customer and subscription from the backoffice (deploy, suspend, unsuspend, undeploy an instance manually, create a maintenance account, backup, ...).
- Provide statistics reports on trial instances, customers, etc.
- Scalable solution (install more servers if you have too much customers or users).
- A lot of "ready in the box" tools (backups, migration, upgrades, ...) 
- Multilang (both front and backoffice)
- A cost of infrastucture lower than 0.5 USD per instance/customer.
- A 100% automated workflow.


## Documentation

You may find asciidoc documentation here:

* <a href="https://github.com/eldy/sellyoursaas/blob/master/doc/Documentation%20SellYourSaas%20-%20Master%20and%20Deployment%20Servers%20-%20EN.asciidoc">English version</a>


## Licenses

### Main code

![GPLv3 logo](img/gplv3.png)

GPLv3 or (at your option) any later version.

See [COPYING](COPYING) for more information.


#### Documentation

All texts and readmes.

![GFDL logo](img/gfdl.png)
