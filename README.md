# WP Force Repair

**WP Force Repair** is a specialized recovery tool designed to fix hacked or broken WordPress sites. It allows you to safely force re-install, update, or replace plugins and themes directly from the official repository, verifying file integrity.

## Features

- **Force Install/Update**: Re-install existing plugins/themes or install new ones, forcibly overwriting existing files to fix corruption.
- **Core Manager**:
  - **Force Re-install Core**: Safely replaces `wp-admin` and `wp-includes` while keeping `wp-content` and `wp-config.php` safe.
  - **Integrity Scan**: Scans the root directory for unknown or suspicious files.
  - **Quarantine System**: Isolate suspicious files, restore them if needed, or permanently delete them.
- **System Health Tools**:
  - **Flush Permalinks**: Fixes 404 errors by resetting rewrite rules.
  - **Regenerate .htaccess**: Recreates your `.htaccess` file with a backup.
  - **Regenerate Salt Keys**: Refreshes security keys in `wp-config.php` and forces a logout for all users.
- **Native UI**: A clean, familiar interface built with React that integrates seamlessly with the standard WordPress dashboard.

## Installation

1. Download the latest `wp-force-repair.zip` from our **[GitHub Releases Page](https://github.com/ajithrn/wp-force-repair/releases/latest)**.
2. Go to **WordPress Dashboard -> Plugins -> Add New -> Upload Plugin**.
3. Upload the zip file and click **Install Now**.
4. Activate the plugin.

## Contributing

Interested in developing or modifying this plugin? Check out our [Developer Guide](docs/CONTRIBUTING.md).

## Roadmap

We have exciting plans for the future of WP Force Repair, including deeper Database repair tools.

👉 [View the full ROADMAP.md](docs/ROADMAP.md)

## Credits

**Author**: Ajith R N  
**Website**: [ajithrn.com](https://ajithrn.com)
