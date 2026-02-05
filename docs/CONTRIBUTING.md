# Developer Guide

This plugin is built with React and `@wordpress/scripts`.

## Prerequisites

- Node.js (Latest LTS)
- Composer (Optional, for PHP dependencies if expanded)

## Setup

```bash
npm install
```

## Build

To compile the frontend assets:

```bash
npm run build
```

## Dev Mode

To watch for changes:

```bash
npm start
```

## Packaging

To build assets and create a production-ready zip file in one step:

```bash
npm run package
```

To just zip (if already built):

```bash
npm run zip
```

## Release Workflow

We use an automated release workflow. To release a new version:

1. **Bump Version Locally**:
   Run the following command to bump the version in `package.json` and `wp-force-repair.php` and stage the changes:

   ```bash
   npm run bump          # Bumps patch (e.g., 1.0.0 -> 1.0.1)
   npm run bump minor    # Bumps minor (e.g., 1.0.0 -> 1.1.0)
   npm run bump major    # Bumps major (e.g., 1.0.0 -> 2.0.0)
   ```

2. **Commit and Push**:

   ```bash
   git commit -m "chore: bump version to X.X.X"
   git push origin main
   ```

3. **Auto-Release**:
   GitHub Actions will detect the version change, verify that a tag for this version does not exist, and automatically:
   - Build assets.
   - Create a zip file.
   - Create a new Release with the version tag.
