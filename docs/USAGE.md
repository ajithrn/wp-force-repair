# User Guide & FAQ

## How to use WP Force Repair

### 1. Force Updating Plugins & Themes

If a plugin is broken or corrupted, you can force a fresh install from the official WordPress repository.

1. Navigate to **Tools > WP Force Repair**.
2. Go to the **Plugins** or **Themes** tab.
3. Click **Re-install** on any installed item to overwrite it with a fresh copy.
4. Alternatively, use the "Force Install" button to install a specific version if available.

### 2. Re-installing WordPress Core

Use this if you suspect core file corruption or hacking.

1. Go to the **Core Manager** tab.
2. Click **Force Re-install Core**.
3. **Quarantine Checkbox**:
    * **Checked (Recommended)**: Any file in your root folder that isn't part of standard WordPress (e.g., `unknown-file.php`) will be moved to the Quarantine folder.
    * **Unchecked**: Only `wp-admin`, `wp-includes`, and root PHP files are replaced. Unknown files are left alone.
4. The process ensures `wp-content` and `wp-config.php` remain untouched.

### 3. File Integrity & Quarantine

The **Integrity Scan** automatically checks for suspicious files in your root directory.

* **View Content**: Click the "View Content" button to inspect any unknown file safely.
* **Quarantine**: Move suspicious files to safe isolation (`wp-content/uploads/wfr-quarantine/`).
* **Restore**: If you mistakenly quarantined a valid file, you can restore it from the Quarantine section.

### 4. System Health Tools

Fix common issues with one click:

* **Flush Permalinks**: Fixes 404 "Page Not Found" errors.
* **Reset Permissions**: Fixes "403 Forbidden" errors or file upload issues by resetting folders to 755 and files to 644.
* **Regenerate Salts**: Logs out all users and invalidates cookies (useful if site hacked).

---

## Frequently Asked Questions (FAQ)

### Q: Will "Force Re-install Core" delete my site content?

**No.** It is designed to be safe. It carefully excludes `wp-content` (where your uploads, themes, and plugins live) and `wp-config.php` (your database connection). It only replaces the core software files.

### Q: What is the "Quarantine" folder?

Suspicious or unknown files found in your root directory are moved to:
`/wp-content/uploads/wfr-quarantine/`
They are renamed and isolated so they cannot execute code, but you can recover them via FTP or the "Restore" button if needed.

### Q: I quarantined my `robots.txt` or `ads.txt` by accident

Don't worry. Go to the **Quarantine** section at the bottom of the Core Manager page and click **Restore**.

### Q: Why isn't my premium plugin showing up for update?

Currently, WP Force Repair connects to the **public** WordPress.org repository. It cannot force-update paid/premium plugins that require license keys or private APIs.

### Q: What if I get a "White Screen of Death" (WSOD)?

If you cannot access the admin area, you can use our upcoming **Emergency Rescue Mode** (Phase 4), which will be a standalone script you can upload via FTP.
