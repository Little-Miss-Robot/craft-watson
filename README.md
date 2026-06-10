# Watson

Watson collects and displays Content Security Policy (CSP) violation reports in the Craft CMS control panel. Point your CSP `report-uri` at Watson's endpoint, and violations are stored, grouped, and surfaced in a dashboard so you can tighten your policy without flying blind.

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later

## Installation

### From the Plugin Store

Go to the Plugin Store in your project's control panel, search for **Watson**, and press **Install**.

### With Composer

```bash
composer require littlemissrobot/craft-watson
./craft plugin/install watson
```

## Configuration

### 1. Add the reporting endpoint to your CSP

Watson exposes a reporting endpoint at `/csp-report`. Add a `report-uri` directive to your CSP header:

```
Content-Security-Policy: ...; report-uri /csp-report
```

If you manage your CSP through [Sherlock](https://putyourlightson.com/plugins/sherlock), add the directive to your `config/sherlock.php`:

```php
'contentSecurityPolicySettings' => [
    'enabled' => true,
    'enforce' => false, // use report-only mode while you tune
    'directives' => [
        // ... your existing directives ...
        [true, 'report-uri', '/csp-report'],
    ],
],
```

### 2. Plugin settings

Settings are available under **Watson → Settings** in the control panel, or via `config/watson.php`:

```php
<?php

return [
    // Number of days to retain violations before they are pruned. Default: 90.
    'retentionDays' => 90,

    // Directives to ignore entirely (e.g. noisy browser extensions).
    // Accepts an array of directive strings: ['script-src', 'style-src']
    'ignoredDirectives' => [],
];
```

## Usage

### Violations

**Watson → Violations** lists every incoming report with its blocked URI, violated directive, document URL, and timestamp. Use the filters to narrow by directive or status.

### Summary

**Watson → Summary** groups violations by directive, giving you a quick read on which parts of your policy are blocking the most requests.

### Statuses

Each violation can be marked as:

| Status     | Meaning                                             |
| ---------- | --------------------------------------------------- |
| `new`      | Unreviewed — just came in                           |
| `resolved` | Policy updated, no longer expected                  |
| `ignored`  | Noise (browser extension, third-party inject, etc.) |

### Console commands

```bash
# Print a summary of the top violation groups
./craft watson/violations/summary
./craft watson/violations/summary --limit=50  # default: 20

# Purge violations older than N days (default: 90)
./craft watson/violations/purge
./craft watson/violations/purge --days=30
```

## How it works

The endpoint at `/csp-report` accepts both the legacy `application/csp-report` format and the newer `application/reports+json` (Reporting API) format. Watson stores the effective directive, blocked URI, document URI, referrer, user agent, and IP address. The endpoint always returns `204 No Content`.

## License

MIT
