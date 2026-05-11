# Changelog

All notable changes to this project will be documented in this file.

## [0.4.0] - 2026-05-11

### Changed

- Refined user-facing migration notices with consistent support links and improved readability.
- Restored the developer-focused state table and migration team reference table on the admin screen.
- Improved admin screen copy and reduced redundant status text.
- Added migration status labels to the multisite My Sites screen.
- Preserved current-operator protection during state transitions.

### Added

- Inline support link formatting for migration notices.
- Updated state lifecycle display for operational reference.

## [0.3.0] - 2026-05-11

### Added

- Migration state framework for multisite site lifecycle management.
- Admin settings page under Settings → Pitchfork Migration.
- Migration team registry and automatic provisioning.
- State-driven user management workflows.
- My Sites status labels for multisite users.
- Single-site compatibility safeguards for local development/testing.

### Migration States

- Pending Migration
- Migration Active
- Migration Complete
- UAT Complete
- Decommissioned

### User Management

- Automatically promote migration team members during active migration.
- Demote non-team users to subscriber during migration.
- Remove migration team during UAT completion.
- Remove site role assignments during decommissioning.
- Protect the currently logged-in administrator from accidental removal/demotion.

### Safety Improvements

- Single-site fallbacks for multisite-only functions.
- State transition reporting and action summaries.
- Automatic creation of missing migration team accounts.
- Nonce-protected state transitions.

### UI Improvements

- Migration status labels displayed on multisite My Sites screen.
- Dynamic admin notices reflecting current migration state.
