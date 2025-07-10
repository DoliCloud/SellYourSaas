# SellYourSaaS 🚀

**Offer any web application as a SaaS with full automation—from deployment to billing.**

---

## 🧩 Overview

**SellYourSaaS** is a powerful open-source platform that transforms any web application into a full-fledged SaaS product — with minimal effort.
It automates everything from customer onboarding and instance deployment to invoicing, usage tracking, and reseller commissions.

Built on top of [Dolibarr ERP & CRM](https://www.dolibarr.org/), SellYourSaaS gives you a complete solution to launch, manage, and scale your SaaS offering — all with **low infrastructure cost** and a **fully automated workflow**.

---

## 📑 Menu

* [Key Features](#-key-features)

  * [Deployment & Instance Management](#-deployment--instance-management)
  * [Subscription & Billing](#-subscription--billing)
  * [Usage Tracking & Reporting](#-usage-tracking--reporting)
  * [Subscription Flow & Customer Dashboard](#-subscription-flow--customer-dashboard)
  * [Admin Tools](#-admin-tools)
  * [Reseller Support](#-reseller-support)
  * [Multilingual & Scalable](#-multilingual--scalable)
* [Quick Start](#-quick-start)
* [Documentation](#-documentation)
* [License](#-license)
* [Contribution](#-contribution)
* [Contact & Community](#-contact--community)

---

## 📌 Key Features

### 🚀 Deployment & Instance Management

* Define **packages**: applications, files, database dumps, cron jobs, custom commands.
* Automated **instance provisioning** for every new subscriber.
* Flexible deployment support: single server or scalable infrastructure.
* **SSH key** support for administrators; isolated environments (jails) per customer.
* Built-in tools for **backup, migration, and upgrades**.

### 💳 Subscription & Billing

* Create pricing plans: free, per-user, per-app, per-GB, or hybrid models.
* **Trial periods** supported (even without credit card).
* **Quota limits**: restrict deployment per customer, IP, time window, etc.
* Stripe and SEPA support for automatic and semi-automatic billing.
* Modifiable billing frequency, due dates, and amounts per customer.

### 📈 Usage Tracking & Reporting

* Custom SQL or CLI metrics to track billable usage (e.g. disk space, user count).
* Built-in **statistics dashboards** for trial usage, customer counts, etc.
* Generate detailed reports for accounting or audits.

### 🌍 Subscription Flow & Customer Dashboard

* Public subscription forms with auto-detection of **country/language** via GeoIP.
* Built-in VPN usage scoring to prevent spam or abuse.
* End users get a **dashboard** to manage subscriptions, invoices, and deploy additional apps.
* Control customer access to databases and SSH for their instance.

### 🛠️ Admin Tools

* Manual override options: deploy/suspend/undeploy instances, reset passwords, create maintenance access, etc.
* Manage subscriptions and invoices from Dolibarr back office.
* Replace PHP `mail()` with a secure system-wide mail layer to block spammers.

### 🤝 Reseller Support

* Each reseller has a unique referral link to register customers.
* Automated **commission system** for resellers.
* Track earnings based on paid invoices linked to reseller accounts.

### 🌐 Multilingual & Scalable

* Fully translated back office and front end (multi-language support).
* Supports **horizontal scaling**—simply add servers as you grow.
* Infrastructure cost: **< \$0.50 per instance**.
* Designed to run with a **100% automated workflow**.

---

## 🚀 Quick Start

### 🔧 Requirements

* PHP 7.4+
* MySQL / MariaDB
* [Dolibarr ERP/CRM](https://www.dolibarr.org/)
* Web server (Apache/Nginx)
* Git

### 📥 Installation

1. Clone the repository into your Dolibarr `htdocs/custom` folder:

   ```bash
   git clone https://github.com/DoliCloud/SellYourSaas.git
   ```
2. Enable the **SellYourSaaS** module from the Dolibarr admin interface.
3. Follow the documentation below to define packages, create services, and deploy.

---

## 📚 Documentation

* 📘 [English Documentation (asciidoc)](https://github.com/DoliCloud/SellYourSaas/tree/master/doc)

Includes setup instructions, deployment examples, system architecture, pricing models, and more.

---

## 📄 License

**Code:**
This project is licensed under the [GNU GPL v3](https://www.gnu.org/licenses/gpl-3.0.en.html) (or later).

**Documentation:**
All documentation and READMEs are under the [GNU Free Documentation License (GFDL)](https://www.gnu.org/licenses/fdl-1.3.en.html).

---

## 🤝 Contribution

Contributions are welcome!

* Fork this repo
* Create a branch: `git checkout -b fix/something`
* Commit changes: `git commit -m "Fix typo or improve feature"`
* Push and open a Pull Request

Please review existing issues and discussions before starting major work.

---

## 📬 Contact & Community

* Official Site: [www.sellyoursaas.org](https://www.sellyoursaas.org/)
* Twitter: [@Dolibarr](https://twitter.com/Dolibarr)
* Contact: [Contact Form](https://www.sellyoursaas.org/contact.php)

---
