# SEO Client Reporting Dashboard Pro for WordPress

[![Version](https://img.shields.io/badge/version-7.0.2-blue.svg)](https://github.com/harisfarooq856-cpu/SEO-Dashboard-Pro)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net)
[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-green.svg)](LICENSE)

A complete, enterprise-grade **Frontend SEO Reporting Dashboard & Client Portal** for WordPress agencies and SEO freelancers. Manage client campaigns, automatically aggregate Search Console and GA4 metrics, generate AI-powered performance executive summaries using Groq, and deliver white-labeled frontend client reporting portals without giving clients WordPress admin access.

---

## Key Features

- **Full Frontend Agency Control Panel**: Manage all agency clients, reports, design, integrations, and activity logs directly from a beautiful frontend SPA interface.
- **Dedicated Client Portals**: White-labeled portal access for clients with isolated data access, customizable branding, and custom pages.
- **Groq AI Analysis Engine**: Automatically summarize SEO wins, ranking drops, conversion spikes, and generate action plans with high-speed AI inference.
- **Native Google Integrations**:
  - **Google Search Console**: Clicks, impressions, CTR, and keyword position trends.
  - **Google Analytics 4 (GA4)**: Sessions, engagement metrics, channels, and conversion paths.
  - **Google Sheets**: Two-way sync for custom KPI tables and client deliverables.
  - **Gmail OAuth**: Automated scheduled PDF/email dispatch directly from your agency email address.
- **Backlink & Document Management**: Log, track, and categorize client backlinks, PR coverage, and shared strategy files.
- **Client Lead Tracking Panel**: Directly monitor captured inquiries, phone leads, and form submissions per client account.
- **Asynchronous Background Job Queue**: High-throughput cron-backed worker queue handling batch API refreshes and automated report generation without timing out.
- **Bank-Grade Data Security**: AES-256 encrypted credential storage for all third-party API tokens, OAuth keys, and client secrets.

---

## Repository Structure

```text
SEO-Dashboard-Pro/
├── assets/                          # Compiled styles and interactive frontend JS
│   ├── css/
│   │   ├── admin-app.css            # Agency admin dashboard styling
│   │   └── client-app.css           # Client portal interface styles
│   └── js/
│       ├── admin-app.js             # Agency dashboard Vue/JS client logic
│       └── client-app.js            # Client portal interactive script
├── includes/                        # Core application modules
│   ├── ajax-*.php                   # Modular AJAX controllers (AI, GA4, GSC, Leads, etc.)
│   ├── class-api.php                # REST API endpoints and data routing
│   ├── class-crypto.php             # AES-256 data encryption & token security
│   ├── class-database.php           # Custom database schema for multi-client metrics
│   ├── class-frontend-admin.php     # Agency frontend router and template loader
│   ├── class-frontend-render.php    # Frontend shortcode and page rendering
│   ├── class-job-queue.php          # Async background worker queue
│   ├── class-license.php            # Licensing and update manager
│   ├── class-roles.php              # Agency staff & client role definitions
│   ├── helper-*.php                 # Google, sanitization, and formatting helpers
│   └── views/                       # Blade/PHP views
│       ├── admin/                   # Agency control center pages & modal dialogs
│       └── client/                  # Client-facing portal views (Dashboard & Leads)
├── seo-client-dashboard.php         # Plugin bootstrap loader
├── seo-dash-security.php            # Security validation and access middleware
├── .gitignore                       # Git ignore rules
├── LICENSE                          # GNU General Public License v2.0
└── README.md                        # Documentation
```

---

## Requirements

- **WordPress:** 5.8 or higher
- **PHP:** 7.4 or higher (PHP 8.x compatible)
- **MySQL:** 5.7+ / MariaDB 10.2+
- **OpenSSL PHP Extension** (for credential encryption)

---

## Installation

1. Clone this repository:
   ```bash
   git clone git@github.com:harisfarooq856-cpu/SEO-Dashboard-Pro.git
   ```
2. Place the folder inside your WordPress plugins directory:
   ```text
   /wp-content/plugins/seo-client-dashboard/
   ```
3. Activate the plugin via **Plugins &rarr; Installed Plugins** in the WordPress admin panel.
4. Set up your agency portal URL under the dashboard settings to start adding clients.

---

## Author

**Haris Farooq**
- Website: [harisfarooq.dev](https://harisfarooq.dev)
- GitHub: [@harisfarooq856-cpu](https://github.com/harisfarooq856-cpu)

---

## License

This project is licensed under the **GNU General Public License v2.0 or later** - see the [LICENSE](LICENSE) file for details.
