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
    * **Checked (Recommended)**: Any file in your root directory that isn't part of standard WordPress will be moved to the Quarantine folder.
    * **Unchecked**: Only `wp-admin`, `wp-includes`, and root PHP files are replaced. Unknown files are left alone.
4. **Safety First**: The process automatically protects your `wp-content` folder and `wp-config.php` file, so your site data remains safe.

### 3. Deep Integrity Scan & Quarantine

The **Integrity Scan** checks your entire WordPress installation, including subfolders like `wp-content` and `plugins`.

* **Explore**: Click on any folder to scan inside it.
* **Smart Protection**: Essential system files (like `wp-config.php`) show a **Lock Icon 🔒** and cannot be quarantined or deleted accidentally.
* **Quarantine**: Move suspicious files to safe isolation (`wp-content/uploads/wfr-quarantine/`).
* **Auto-Cleanup**: When you restore or permanently delete the last file in a quarantine folder, the empty folder is automatically removed to keep things tidy.

### 4. System Tools

Fix common issues with one click:

* **Flush Permalinks**: Fixes 404 "Page Not Found" errors.
* **Reset Permissions**: Fixes "403 Forbidden" errors or file upload issues by resetting folders to 755 and files to 644.
* **Regenerate Salts**: Logs out all users and invalidates cookies (useful if site hacked).
* **Connection Tester**: Checks if your server can talk to itself (loopback). Essential for scheduled tasks (WP-Cron) and plugin updates.

### 5. Backup Manager (Beta)

Before making major repairs, it is always safe to take a backup.

* **Download Files**: Creates a zip of your entire root directory (`backup-{site-name}-files-{date}.zip`).
* **Download Database**: Exports your database as a SQL file (`backup-{site-name}-db-{date}.sql` or `.zip`).
* **Auto-Cleanup**: After downloading, the plugin will ask if you want to delete the backup file from the server. We recommend clicking **Delete** to save space and keep your server clean.

### 6. Database Health

Optimize your database by cleaning up overhead and removing unused tables.

* **Plugin Detection**: The "Belongs To" column shows which plugin created each table.
  * **Green Dot**: Active Plugin.
  * **Red Dot**: Inactive Plugin (Safe to delete if plugin is removed?).
  * **Click Name**: Links to the official WordPress plugin page for more info.
* **Optimize**: Click "Optimize" to defragment tables and reclaim space.
* **Sorting**: Click column headers to sort by Size, Rows, or Overhead.

### 7. Managing Plugins & Themes

You can manage your installed extensions directly:

* **Toggle Status**: Click **Activate** or **Deactivate** to quickly enable/disable plugins.
* **Force Reinstall**: If a plugin is acting up, click "Reinstall" to replace it with a fresh copy from the repository.
* **Delete**: Permanently remove the plugin files.

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
