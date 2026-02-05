# WP Force Repair - Roadmap

## Phase 1: Force Update & Plugin Management (Completed)

- [x] **Repo Browser**: Search and browse plugins and themes from the official WordPress.org repository.
- [x] **Force Install**: Re-install existing plugins/themes or install new ones, forcibly overwriting existing files.
- [x] **Delete Support**: Safely delete installed plugins and themes.
- [x] **Premium UI**: Modern, glassmorphism-inspired interface with real-time terminal feedback.

## Phase 2: Site Recovery & Core Repair (Planned)

- [ ] **Core File Replacement**:
  - Download latest WordPress Core.
  - Smart logic to preserve `wp-config.php` and `wp-content` folder.
  - Parser to extract DB credentials and regenerate salts if config is compromised.
- [ ] **Filesystem Verification**:
  - Scan root directory for non-core (suspicious) files.
  - Scan `wp-content` for unauthorized scripts.
  - Bulk delete tool for cleanup.

## Phase 3: Database Repair & Optimization (Planned)

- [ ] **Database Health Check**:
  - Structural integrity check of Core Tables.
  - Automated `REPAIR TABLE` for corrupted tables.
- [ ] **Cleanup & Optimization**:
  - Remove expired transients.
  - Clean orphaned Post Meta and Term Relationships.
  - Drop unknown/spam tables (with backup options).
