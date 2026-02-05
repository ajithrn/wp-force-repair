# WP Force Repair

![WP Force Repair Logo](assets/logo.png)

**WP Force Repair** is a specialized recovery tool designed to fix hacked or broken WordPress sites. It allows you to safely force re-install, update, or replace plugins and themes directly from the official repository, verifying file integrity.

## Features

- **Repo Browser**: Search and browse plugins and themes from the official WordPress.org repository.
- **Force Install**: Re-install existing plugins/themes or install new ones, forcibly overwriting existing files if necessary.
- **Delete Support**: Safely delete installed plugins and themes directly from the dashboard.
- **Premium UI**: A darker, modern interface built with React and scoped CSS (Isolation).
- **Real-time Feedback**: Live terminal logs during installation processes.

## Installation

1. Download the `force-update-plugin-theme.zip` file.
2. Go to your WordPress Dashboard -> Plugins -> Add New -> Upload Plugin.
3. Upload the zip file and click "Install Now".
4. Activate the plugin.

## Development

This plugin is built with React and `@wordpress/scripts`.

### Prerequisites

- Node.js (Latest LTS)
- Composer (Optional, for PHP dependencies if expanded)

### Setup

```bash
npm install
```

### Build

To compile the frontend assets:

```bash
npm run build
```

### Dev Mode

To watch for changes:

```bash
npm start
```

### Packaging

To build assets and create a production-ready zip file in one step:

```bash
npm run package
```

To just zip (if already built):

```bash
npm run zip
```

## Roadmap

We have exciting plans for the future of WP Force Repair, including Core File replacement and Database repair tools.

👉 [View the full ROADMAP.md](ROADMAP.md)

## Credits

**Author**: Ajith R N  
**Website**: [ajithrn.com](https://ajithrn.com)
