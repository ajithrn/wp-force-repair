# WP Force Repair - Roadmap

## Phase 1: Force Update & Plugin Management (Completed)

- [x] **Repo Browser**: Search and browse plugins and themes from the official WordPress.org repository.
- [x] **Force Install**: Re-install existing plugins/themes or install new ones, forcibly overwriting existing files.
- [x] **Delete Support**: Safely delete installed plugins and themes.
- [x] **Premium UI**: Modern, glassmorphism-inspired interface with real-time terminal feedback.

## Phase 2: Site Recovery & Core Repair (Completed)

- [x] **Core Reinstall Manager**:
  - [x] Download and install latest WordPress Core safely.
  - [x] Integrity Scan: Detects unknown files in root directory.
  - [x] Auto-Quarantine: Automatically moves suspicious files to a safe location before reinstall.
- [x] **Quarantine Manager**:
  - [x] View list of quarantined files/backups.
  - [x] Restore files to original location.
  - [x] Permanently delete suspicious files.
- [x] **System Health Tools**:
  - [x] Flush Permalinks.
  - [x] Regenerate `.htaccess`.
  - [x] **Comment Cleanup**: Bulk delete Spam/Pending comments.

## Phase 3: Database Repair & Optimization (Planned)

- [ ] **Database Health Check**:
  - Structural integrity check of Core Tables.
  - Automated `REPAIR TABLE` for corrupted tables.
- [ ] **Cleanup & Optimization**:
  - Remove expired transients.
  - Clean orphaned Post Meta and Term Relationships.
  - Drop unknown/spam tables (with backup options).

## Phase 4: Automation & CLI (Future)

- [ ] **WP-CLI Support**: Run repair commands via terminal.
- [ ] **Scheduled Scans**: Daily integrity checks.
- [ ] **Email Alerts**: Notify admin on file changes.
