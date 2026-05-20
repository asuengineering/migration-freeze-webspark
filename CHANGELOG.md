# Changelog

All notable changes to this project will be documented in this file.

## [0.8.0] - 2026-05-19

### Added

- Redirection plugin audit export support.
- Redirect reconstruction exports for Drupal Redirect migration workflows.
- Dedicated redirects CSV artifact integrated into the audit export pipeline.
- Redirect metadata preservation including regex/query handling and status codes.

### Changed

- Integrated redirect exports into audit history UI and ZIP bundles.
- Standardized redirect export structure alongside existing Gravity Forms and Yoast SEO exports.
- Improved migration audit portability for Drupal/Webspark rebuild planning.

## [0.7.0] - 2026-05-19

### Added

- Yoast SEO audit export support for posts, pages, CPTs, taxonomy overrides, and sitewide settings.
- Supplemental SEO export metadata for migration validation and rebuild QA.
- Direct export links for SEO audit artifacts.

### Changed

- Filtered SEO exports to include only meaningful editorial SEO overrides relevant to Drupal/Webspark rebuild workflows.
- Corrected Yoast export URL generation to align with the primary audit artifact structure.
- Encoded supplemental SEO metadata correctly as JSON in CSV exports.
- Improved Yoast audit export reliability and row-count accuracy.

## [0.6.0] - 2026-05-19

### Added

- Gravity Forms audit trail support for form definitions, notifications, and confirmations.
- Export of inactive Gravity Forms entries with an explicit inactive status flag.
- Audit trail documentation updates for the new Gravity Forms scope.

### Changed

- Updated release metadata and version numbers to reflect the v0.6.0 milestone.
- Kept existing migration workflow, content audit, and site-state behavior intact.

## [0.5.0] - 2026-05-19

### Changed

- Added a quiet default "Situation Normal" state so plugin activation no longer implies migration activity.
- Renamed the settings page and menu label to "Migration Status".
- Simplified the audit export workflow into separate artifacts for content, taxonomies, taxonomy relationships, media, menu items, and users.
- Improved audit summary labels and generated-content counts for planning and QA.
- Updated export filenames to include the site slug and timestamp.
- Flattened the ZIP export structure.
- Removed the retained draft feature and its related admin/UI references.

### Added

- Migration audit trail page under Settings.
- Export history retention for recent audit runs.
- Generated content-view summary metrics for audit planning.
- Menu item export restoration after regression testing.

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
