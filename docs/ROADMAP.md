# WP Force Repair - Roadmap

## Phase 1: Force Update & Plugin Management (Completed)

- [x] **Repo Browser**: Search and browse plugins and themes from the official WordPress.org repository.
- [x] **Force Install**: Re-install existing plugins/themes or install new ones, forcibly overwriting existing files.
- [x] **Delete Support**: Safely delete installed plugins and themes.
- [x] **Premium UI**: Modern, glassmorphism-inspired interface with real-time terminal feedback.

## Phase 2: Site Recovery & Core Repair (Completed)

- [x] **Core Reinstall Manager**:
  - [x] Download and install latest WordPress Core safely.
  - [x] Integrity Scan: Detects unknown files (includes **Quick View**).
  - [x] Auto-Quarantine: Automatically moves suspicious files to a safe location before reinstall.
- [x] **Quarantine Manager**:
  - [x] View list of quarantined files/backups.
  - [x] Restore files to original location.
  - [x] Permanently delete suspicious files.
- [x] **System Health Tools**:
  - [x] Flush Permalinks.
  - [x] Regenerate `.htaccess`.

  - [x] **Comment Cleanup**: Bulk delete Spam/Pending comments.
  - [x] **Reset File Permissions**: Fix folders (755) and files (644).

  - [x] **Reset File Permissions**: Fix folders (755) and files (644).

## Phase 2.5: Backup Manager (Planned)

- [ ] **Full Site Backup**:
  - Download entire root directory as ZIP.
  - Smart exclusions (node_modules, .git, existing backups).
- [ ] **Database Backup**:
  - Export full database (SQL dump).
  - Support for `mysqldump` (fast) with PHP fallback (compatible).
  - Gzip compression.

- [x] **Database Health Check**:
  - [x] Structural integrity check of Core Tables.
  - [x] **Plugin Detection**: Identify table owners and status (Active/Inactive).
  - [x] **Optimization**: Optimize tables and reduce overhead.
- [ ] **Cleanup & Optimization**:
  - Remove expired transients.
  - Clean orphaned Post Meta and Term Relationships.
  - Drop unknown/spam tables (with backup options).

## Phase 3: Plugin & Theme Manager (Completed)

- [x] **Activation Manager**: Toggle Activate/Deactivate for plugins and themes.
- [x] **Smart Detection**: Link plugins to their WP Registry page.

## Phase 4: Emergency Rescue Mode (Planned)

- [ ] **Standalone Rescue Script**:
  - **Architecture**: No-WP-Load PHP script.
  - **Disable Plugins**: Emergency deactivation of all plugins.
  - **Rescue Theme**: Force switch to default theme (Twenty*).
  - **Core/Plugin Reinstall**: Reinstall without admin access.
  - **Force Install**: Upload ZIPs directly.
  - **Emergency Backup**: Dump Database (SQL) & Zip Files (essential before repairs).
  - **Root File Manager**: Manage files via web interface.
  - **Rescue Admin**: Create emergency admin user via SQL.
