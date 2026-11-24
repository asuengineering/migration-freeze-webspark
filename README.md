# Pitchfork - Migration Freeze

A WordPress plugin for managing staged site migration workflows across multisite networks.

## Overview

Pitchfork - Migration Freeze provides a lightweight operational framework for:

- controlling user access during migrations,
- promoting temporary migration-team access,
- reducing site visibility during decommissioning,
- and tracking migration state directly inside WordPress.

The plugin was designed primarily for large WordPress multisite environments managing high-volume migration and retirement workflows.

---

# Features

## Migration States

The plugin supports the following lifecycle states:

| State              | Behavior                                                                     |
| ------------------ | ---------------------------------------------------------------------------- |
| Situation Normal   | Plugin is active with no migration warning or user changes                   |
| Migration Pending  | No user changes                                                              |
| Migration Active   | Migration team promoted to administrators; other users demoted to subscriber |
| Migration Complete | No user changes                                                              |
| UAT Complete       | Migration team removed from the site                                         |
| Decommissioned     | Site role assignments removed for all users except the current operator      |

---

# Migration Team

The plugin maintains a predefined migration team list.

During migration:

- migration-team members are automatically granted administrator access,
- missing migration-team users are automatically created if needed,
- and current operators are protected from accidental lockout.

---

# Multisite Support

Primary target:

- WordPress multisite networks

The plugin also includes:

- single-site safety fallbacks,
- multisite-aware role handling,
- My Sites migration status labels,
- and protections against multisite-only function failures.

---

# Admin Features

## Settings Screen

Located at:

```text
Settings → Migration Status
```

Provides:

- current migration state,
- state transition controls,
- migration-team visibility,
- and transition summaries.

## Migration Audit Trail

Located at:

```text
Settings → Migration Audit Trail
```

Provides:

- separate export artifacts for content, rendered SEO, taxonomies, taxonomy relationships, media, menu items, Gravity Forms, users, Yoast SEO, and redirects,
- recent export history,
- generated content-view summaries for audit planning,
- and migration-oriented rebuild auditing support.

Menu item exports currently capture:

- menu purpose classification,
- CTA button behavior,
- CTA button colors,
- social media icon identifiers,
- menu item rendering intent,
- menu item target behavior,
- submenu button metadata,
- and supplemental menu rendering metadata.

Gravity Forms exports currently capture:

- forms and form settings relevant to Drupal/Webforms rebuilds,
- per-form notifications,
- per-form confirmations,
- and inactive forms marked accordingly.

Yoast SEO exports currently capture:

- editorial SEO overrides for posts, pages, and CPTs,
- taxonomy SEO overrides,
- supplemental sitewide SEO settings,
- Open Graph and Twitter metadata,
- focus keyphrases,
- canonical overrides,
- noindex/nofollow directives,
- and cornerstone content indicators.

Rendered SEO exports crawl each published content record that has a permalink and capture:

- the effective HTML title, meta description, canonical, and robots directives,
- `X-Robots-Tag` response headers,
- Open Graph and Twitter card metadata,
- alternate-language links,
- redirects, response status, content type, and per-URL crawl failures,
- duplicate and conflicting metadata tags,
- and all discovered supported values in supplemental JSON.

The companion `rendered-seo-analysis.json` artifact is intentionally limited to
importer-oriented pattern discovery. It includes:

- ranked title patterns with counts and coverage,
- title candidates grouped sitewide and by content type,
- self-referencing, internal override, external override, missing, and invalid canonical classifications,
- and normalized robots directive sets with counts and coverage.

The analysis JSON contains aggregate measurements only. Source identities and all
page-level SEO values remain in `rendered-seo.csv`, which prevents the aggregate
artifact from duplicating the migration dataset.

The rendered export is plugin-agnostic. It records public output produced by Yoast,
Rank Math, Divi, theme code, WordPress core, or any combination of those systems.
Only published content already selected by the content audit is crawled; failures are
reported as rows and do not stop the rest of the audit export. The analysis reports
observed candidates and coverage without applying a minimum-coverage threshold.

Redirect exports currently capture:

- source and destination URLs,
- HTTP redirect status codes,
- regex redirect behavior,
- query-string handling,
- redirect grouping,
- redirect hit counts,
- redirect timestamps,
- and supplemental Redirection plugin metadata.

## My Sites Labels

On multisite installs, the plugin appends migration status labels to the:

```text
/wp-admin/my-sites.php
```

listing for easier operational visibility.

---

# Safety Features

- Nonce-protected state changes
- Current-user lockout protection
- Single-site compatibility guards
- Non-destructive decommission workflow
- State transition reporting

---

# Development

Current development branch:

```text
develop
```

Current milestone:

```text
v0.8.4
```

---

# License

Internal operational tooling for ASU Engineering migration workflows.
