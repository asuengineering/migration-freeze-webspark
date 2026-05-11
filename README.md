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
| Pending Migration  | No user changes                                                              |
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
Settings → Pitchfork Migration
```

Provides:

- current migration state,
- state transition controls,
- migration-team visibility,
- and transition summaries.

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
v0.3.0
```

---

# License

Internal operational tooling for ASU Engineering migration workflows.
