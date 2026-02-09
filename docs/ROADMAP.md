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

## Phase 2.5: Backup Manager (Completed)

- [x] **Full Site Backup**:
  - Download entire root directory as ZIP.
  - Smart exclusions (node_modules, .git, existing backups).
- [x] **Database Backup**:
  - Export full database (SQL dump).
  - Support for `mysqldump` (fast) with PHP fallback (compatible).
  - **Auto-Cleanup**: Prompts to delete backups to save space.

## Phase 3: Database & Core Health (In Progress)

- [x] **Database Health Check**:
  - [x] Structural integrity check of Core Tables.
  - [x] **Plugin Detection**: Identify table owners and status (Active/Inactive).
  - [x] **Optimization**: Optimize tables and reduce overhead.
  - [x] **Table Inspector**: View size, rows, and overhead.
- [ ] **Cleanup & Optimization**:
- [ ] **Cleanup & Optimization**:
  - Remove expired transients.
  - Clean orphaned Post Meta and Term Relationships.
  - Drop unknown/spam tables (with backup options).

## Phase 3: Plugin & Theme Manager (Completed)

- [x] **Activation Manager**: Toggle Activate/Deactivate for plugins and themes.
- [x] **Smart Detection**: Link plugins to their WP Registry page.

## Phase 4: Advanced System Tools (Planned)

- [ ] **WP-Cron Manager**:
  - View all scheduled cron events.
  - Manually run specific cron jobs.
  - Delete stuck or orphaned cron events.
- [ ] **Server Information**:
  - Detailed PHP info (Memory Limit, Max Execution Time).
  - MySQL version and database size.
  - Check for essential extensions (Zip, Curl, Imagick).
- [ ] **Capabilities Manager**:
  - Reset Administrator capabilities to default (fixes "Sorry, you are not allowed to access this page").
  - Repair broken user roles.
- [ ] **Mail Tester**:
  - simple tool to verify if `wp_mail()` is working correctly.
