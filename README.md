# SELLYOURSAAS


## Features

SellYourSaas is a tool to manage and sell any Web application as a Saas service.
It covers definition of the packages to sell (which application, where to sources and database image, how to install it), the tools to deploy the application on a remote server, but also a customer dashboard for your subscribers, automatic invoicing and renewal. The administration / backoffice is Dolibarr ERP CRM Open Source software.

This is a list of some features supported by this project:

- Creation of profiles of packages - to define what to deploy when a subscription to this package is done: files or directories to deploy, databases dump to load, cron entry to add, SSH public keys of administrator to deploy and any other command lines to launch.
- Create services that define the plan (which package and option) and price policy to use for invoicing: per application, per user, per Gigabytes or any combination of this.
- Option to support free trial periods (with no credit card required) on some plans.
- Provides ready to use URLs for an online subscription to a hosting service by your prospects or customers.
- Each customer has its own system and data environment (jail).
- Each customer can deploy more applications/services with their existing account.
- Provide a customer dashboard for customers to manage their subscription, download their invoices.
- Can define the SQL or CLI command for each sold services to define how to count the quantity to bill (For example a SQL request to count the number of users).
- Autofill and autodetect country in the subscription page using GeoIP.
- Include a probability of VPN usage for each subscriber (to fight against spammer).
- Can decide if customer has direct access to the MySQL/MariaDB database and restricted (or not) SSH access to its instance.
- Add a system layer to replace the php mail function to track and stop evil users using their created instance to try to make Spams.  
- Manage a network of reseller with commission dedicated to each reseller (a reseller has its own URL to register/create a new instances of an application and any customer that use it to create its instance is linked to the reseller. Reseller will gain a commission for each invoice paid by the customer). 
- Payment of customers can be done automatically by credit card using Stripe (Stripe may not be visible by user) or semi-automatic by SEPA mandate.
- Billing rules (date, amount, frequency of next payment) can be modified differently for each customer.
- Provide a lot of predefined email templates in several languages for the subscription management (subscription, trial expiration, cancellation, ...)
- Can manage each customer/subscription from the backoffice (for example to deploy, suspend, unsuspend, undeploy an instance manually).
- Provide statistics reports on trial instances, customers, etc.
- A lot of ready in the box tools (backups, migration, upgrades, ...) 


Licenses
--------

### Main code

![GPLv3 logo](img/gplv3.png)

GPLv3 or (at your option) any later version.

See [COPYING](COPYING) for more information.


#### Documentation

All texts and readmes.

![GFDL logo](img/gfdl.png)
