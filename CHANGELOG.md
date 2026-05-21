# Changelog

All notable changes to this project will be documented in this file.

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
