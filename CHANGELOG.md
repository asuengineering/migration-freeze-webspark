# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

### Added

- Added a plugin-agnostic `rendered-seo.csv` artifact based on the public HTML head of published content.
- Added effective title, description, canonical, robots, Open Graph, Twitter card, hreflang, response, redirect, duplicate, conflict, and error reporting.
- Added conservative title-pattern candidates with sitewide and content-type frequency classifications.

### Fixed

- Corrected rendered SEO selection so published content with a permalink is crawled even when its registered post type does not expose a truthy `publicly_queryable` property.
- Synchronized the plugin header and runtime version constant at `0.9.0-beta.2`.

## [0.8.4] - 2026-11-23

### Changed

- Updated the approved migration team roster to the eight team members authorized for WordPress backend access.
- Corrected Rohit Joshi's ASURITE ID to `rjoshi10` from the typo supplied in the access request.

## [0.8.3] - 2026-10-27

### Changed

- Updated the approved migration team roster to the five currently credentialed team members.
- Standardized migration team email addresses as each member's ASURITE ID followed by `@asu.edu`.

## [0.8.2] - 2026-06-08

### Fixed

- Corrected content view summary totals displayed in the Migration Audit Trail admin screen.
- Restored the content total calculation to use the exporter-generated summary values stored in audit history records.
- Removed legacy summary-reconstruction logic that could overwrite content view totals with raw content row counts.

### Changed

- Simplified audit summary rendering by treating stored audit summary data as the authoritative source for display.
- Improved clarity of the content summary table labels and total row presentation.

## [0.8.1] - 2026-05-19

### Added

- Menu-purpose export metadata support for Pitchfork navigation reconstruction.
- Exported CTA button metadata including button color and target behavior.
- Exported social media icon metadata for menu items.
- Exported menu item purpose classification for Drupal/Webspark rebuild workflows.

### Changed

- Enhanced menu-items.csv exports with reconstruction-oriented menu metadata.
- Preserved menu item rendering intent for CTA buttons, social icons, submenu buttons, and navigation links.
- Refreshed ZIP bundle and metadata generation after menu export post-processing.

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
