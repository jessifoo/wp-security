# Security Audit Report — Obfuscated Malware Scanner Plugin

**Date**: 2026-08-19
**Scope**: Five core source files plus supporting classes (API, quarantine manager, config)
**Auditor**: Automated Security Analysis

---

## Executive Summary

This audit identified **14 findings** across the five scoped files and their tightly-coupled dependencies. The most critical issues involve a **hardcoded shared secret**, an **unauthenticated REST API registration endpoint**, **path traversal gaps in `is_verified_core_file`**, and several **TOCTOU race conditions** in file operations. No command injection, SSRF, or unsafe deserialization vulnerabilities were found — the codebase does not call `exec`/`system`/`shell_exec`/`passthru`/`proc_open`/`unserialize` with attacker-controlled data, and the only outbound HTTP request uses a hardcoded WordPress.org API URL.

---

## Findings

### FINDING 1 — Hardcoded Linking Key Enables Registration Takeover

**Severity**: CRITICAL
**File**: `/workspace/includes/class-oms-config.php` line 476
**Related**: `/workspace/includes/class-oms-api.php` lines 126–156

**Vulnerable code** (`class-oms-config.php:476`):
```php
const OMS_LINKING_KEY = 'oms_secret_key_change_me';
```

**Vulnerable code** (`class-oms-api.php:136`):
```php
if ( ! hash_equals( OMS_Config::OMS_LINKING_KEY, $params['master_key'] ) ) {
```

**Attack chain**: The `/oms/v1/register` endpoint (which has `permission_callback => '__return_true'` — unauthenticated) compares a caller-supplied `master_key` against a hardcoded constant. Anyone who reads the open-source plugin code can POST to `/wp-json/oms/v1/register` with `{"master_key": "oms_secret_key_change_me", "dashboard_url": "https://evil.com"}` and receive a freshly-generated API key that grants access to `/status`, `/scan`, and `/report` endpoints. This allows an unauthenticated attacker to:
1. Trigger full site scans (`/scan`), causing DoS.
2. Read security logs (`/report`), leaking file paths and scan results.
3. Overwrite the `oms_master_dashboard_url` option to point to an attacker-controlled server.

**Existing mitigations**: `hash_equals` prevents timing attacks — but the secret itself is public.

**Recommendation**: Replace the hardcoded constant with a per-site secret generated during activation and stored as a WordPress option. Alternatively, gate registration behind `manage_options` capability.

---

### FINDING 2 — Unauthenticated REST Registration Endpoint

**Severity**: HIGH
**File**: `/workspace/includes/class-oms-api.php` lines 56–64

**Vulnerable code**:
```php
register_rest_route(
    $namespace,
    '/register',
    array(
        'methods'             => 'POST',
        'callback'            => array( $this, 'handle_registration' ),
        'permission_callback' => '__return_true',
    )
);
```

**Attack chain**: Combined with Finding 1, this makes the registration endpoint fully exploitable without any authentication. Even if the hardcoded key is changed, the endpoint still has no rate limiting, no nonce verification, and no capability check — making it a brute-force target.

**Existing mitigations**: The `master_key` check (but see Finding 1).

**Recommendation**: Add `current_user_can('manage_options')` to the permission callback, or require a nonce, or add rate limiting.

---

### FINDING 3 — `is_verified_core_file` Path Bypass via Relative Path Manipulation

**Severity**: HIGH
**File**: `/workspace/includes/class-oms-core-integrity-checker.php` lines 131–137

**Vulnerable code**:
```php
public function is_verified_core_file( $path, $safe_files ) {
    $relative_path = str_replace( ABSPATH, '', $path );
    $relative_path = str_replace( '\\', '/', $relative_path );
    return in_array( $relative_path, $safe_files, true );
}
```

**Attack chain**: The `str_replace( ABSPATH, '', $path )` call performs a simple string replacement, not a path normalization. If `ABSPATH` is `/var/www/html/` and a malicious file exists at `/var/www/html/wp-includes/../wp-includes/version.php`, the `str_replace` will produce `wp-includes/../wp-includes/version.php`, which won't match the `safe_files` entry `wp-includes/version.php` — causing it to be scanned normally (safe behavior). However, the reverse is more concerning: if an attacker can create a file at `/var/www/html/wp-admin/evil.php` and the scan iterates with a path that resolves to `/var/www/html/wp-admin/evil.php`, the `str_replace` correctly excludes it from `safe_files`. The real risk is that this function is called in `Obfuscated_Malware_Scanner::validate_file()` (line 802) to **skip scanning** of verified core files. If `$path` is passed with an alternate representation that matches after `str_replace` (e.g., a symlink pointing to a different file), the malicious file could be whitelisted.

**Existing mitigations**: `$safe_files` comes from checksums fetched from WordPress.org (not user-controlled). The `strict` parameter on `in_array` is `true`.

**Recommendation**: Use `realpath()` to canonicalize `$path` before comparison, and verify it still starts with `ABSPATH`.

---

### FINDING 4 — TOCTOU Race Condition in Upload File Validation

**Severity**: MEDIUM
**File**: `/workspace/includes/class-obfuscated-malware-scanner.php` lines 318–357

**Vulnerable code**:
```php
public function check_uploaded_file( $meta_id, $post_id, $meta_key, $meta_value ) {
    // ...
    $file_path = OMS_Utils::sanitize_path( $upload_dir['basedir'] . '/' . $meta_value );
    $validation_result = $this->security_policy->validate_file( $file_path );
    // ... time gap ...
    if ( ! $is_valid || $this->contains_malware( $file_path ) ) {
        $this->quarantine_file( $file_path );
```

**Attack chain**: Between `validate_file()` and `contains_malware()`, or between `contains_malware()` and `quarantine_file()`, the file at `$file_path` could be swapped by a concurrent process. An attacker with write access to the uploads directory could:
1. Upload a benign file that passes validation.
2. Race to replace it with a malicious file after validation but before quarantine check completes.
3. If the malicious file is swapped in after `contains_malware()` returns false, it persists on disk.

**Existing mitigations**: `sanitize_path()` prevents path traversal. The window is small but exploitable in high-concurrency environments.

**Recommendation**: Use file locking (`flock`) during the validate-scan-quarantine sequence, or compute a hash before validation and re-verify after scan.

---

### FINDING 5 — TOCTOU Race in Quarantine Manager

**Severity**: MEDIUM
**File**: `/workspace/includes/class-oms-quarantine-manager.php` lines 40–139

**Vulnerable code**:
```php
public function quarantine_file( $path ) {
    // ... checks directory exists ...
    $rename_result = rename( $path, $quarantine_path );
    // ... if rename fails, try copy + delete ...
    $copy_result = copy( $path, $quarantine_path );
    if ( $copy_result ) {
        $unlink_result = unlink( $path );
```

**Attack chain**: Between the `copy()` and `unlink()` calls (lines 90–93), the original file still exists. If an attacker has concurrent access, they could:
1. Read the original malicious file before `unlink()` completes.
2. Replace the file between `copy()` and `unlink()`, so a different file gets deleted.
3. If `unlink()` fails (line 99), the malicious file remains on disk AND a copy exists in quarantine — the quarantine copy is then deleted (line 107), leaving the original untouched.

**Existing mitigations**: The code attempts `rename()` first (atomic on same filesystem). The fallback to `chmod(0000)` is a reasonable last resort.

**Recommendation**: On the copy+delete fallback path, truncate the original file (`file_put_contents($path, '')`) before unlinking, to eliminate the window where malicious content is accessible.

---

### FINDING 6 — `$meta_value` Path Component Not Fully Validated

**Severity**: MEDIUM
**File**: `/workspace/includes/class-obfuscated-malware-scanner.php` line 331

**Vulnerable code**:
```php
$file_path = OMS_Utils::sanitize_path( $upload_dir['basedir'] . '/' . $meta_value );
```

**Attack chain**: `$meta_value` comes from `added_post_meta` hook and represents the `_wp_attached_file` meta value. While `sanitize_path()` calls `is_path_safe()` (which blocks `..` traversal, null bytes, and stream wrappers), the path is constructed by concatenating `basedir` + `/` + the raw `$meta_value` before sanitization. If `$meta_value` contains encoded traversal sequences that `wp_normalize_path()` doesn't decode (e.g., double-encoded `%252e%252e`), the resulting path might escape the uploads directory. The `rawurldecode()` in `is_path_safe()` handles single-encoded sequences but not double-encoded.

**Existing mitigations**: `is_path_safe()` decodes once with `rawurldecode()`, checks for `..` segments, blocks stream wrappers and null bytes.

**Recommendation**: Verify the final resolved path starts with `$upload_dir['basedir']` after `realpath()`. Apply `sanitize_file_name()` to `$meta_value` before concatenation.

---

### FINDING 7 — Regex Injection via `OMS_Config::MALICIOUS_PATTERNS`

**Severity**: LOW (config-controlled, not user-facing)
**Files**: `/workspace/includes/class-oms-filesystem.php` lines 40–41, `/workspace/includes/class-oms-utils.php` lines 153–154

**Vulnerable code** (`class-oms-filesystem.php:41`):
```php
if ( preg_match( '#' . $pattern . '#i', $content ) ) {
```

**Vulnerable code** (`class-oms-utils.php:154`):
```php
if ( preg_match( '#' . $pattern . '#i', $content ) ) {
```

**Attack chain**: Patterns from `OMS_Config::MALICIOUS_PATTERNS` are concatenated directly into regex delimiters without escaping. If a pattern contains an unescaped `#` character, it would break the regex delimiter and could cause `preg_match` to fail silently (returning `false`), potentially causing a malicious file to pass scanning. Since these patterns are defined in a PHP constant (not user input), exploitation requires modifying the config file — but a plugin update or compromised developer could introduce a pattern that disables the scanner.

**Existing mitigations**: Patterns are hardcoded constants. `OMS_Scanner::compile_patterns()` validates `MALWARE_PATTERNS` (which use `/` delimiters), but `MALICIOUS_PATTERNS` and `OBFUSCATION_PATTERNS` are not pre-validated.

**Recommendation**: Pre-validate all patterns at compile time (like `OMS_Scanner` does for `MALWARE_PATTERNS`), or use a consistent delimiter and escape pattern content.

---

### FINDING 8 — Special Character Ratio Check Bypass

**Severity**: LOW
**File**: `/workspace/includes/class-oms-filesystem.php` lines 60–67, `/workspace/includes/class-oms-utils.php` lines 172–180

**Vulnerable code**:
```php
$special_chars = preg_match_all( '/[^a-zA-Z0-9\s]/', $content );
$total_chars   = strlen( $content );
if ( $total_chars > 0 && ( $special_chars / $total_chars ) > 0.3 ) {
```

**Attack chain**: An attacker can pad an obfuscated payload with sufficient alphanumeric characters to keep the special character ratio below 30%. For example, appending `str_repeat('A', strlen($payload) * 3)` as a PHP comment would dilute the ratio below threshold while the obfuscated payload at the beginning of the file still executes. This is a scanner evasion technique, not a vulnerability in the traditional sense.

**Existing mitigations**: This check is one layer among many (malware patterns, obfuscation patterns, etc.).

**Recommendation**: Consider per-block analysis rather than whole-file ratio, or use entropy-based detection on sliding windows.

---

### FINDING 9 — Theme File Bypass via `is_known_good_file` Overly Broad Patterns

**Severity**: MEDIUM
**File**: `/workspace/includes/class-file-security-policy.php` lines 146–150, 461–468, 508–543

**Vulnerable code** (patterns, lines 146–150):
```php
private $known_good_patterns = array(
    '/\.min\.(js|css)$/',
    '/elementor.*\.js$/',
    '/astra.*\.js$/',
);
```

**Vulnerable code** (bypass path, line 510):
```php
if ( $this->is_known_good_file( $path ) ) {
    return array(
        'valid'  => true,
        'reason' => 'Theme file matches known-good pattern - safe',
    );
}
```

**Attack chain**: A malicious file placed in a protected theme path (e.g., `wp-content/themes/astra/elementor-backdoor.js` or `wp-content/plugins/elementor/payload.min.js`) would match the broad regex patterns and be whitelisted even if content checks flagged it as suspicious. The `elementor.*\.js$` pattern matches ANY `.js` file whose path contains "elementor" anywhere. An attacker who can write files to theme/plugin directories can name their malicious JS file to match these patterns.

**Existing mitigations**: This bypass only applies to theme files already flagged as suspicious — it's a secondary check. The file must be in a `protected_theme_paths` directory.

**Recommendation**: Tighten the patterns to match specific known-good filenames or use checksums for known-good theme/plugin files.

---

### FINDING 10 — Log File Content Exposure via REST API

**Severity**: MEDIUM
**File**: `/workspace/includes/class-oms-api.php` lines 208–221

**Vulnerable code**:
```php
public function get_report() {
    $log_path = $this->scanner->get_log_path();
    $log_file = $log_path . '/security.log';
    $logs = array();
    if ( file_exists( $log_file ) ) {
        $logs = array_slice( file( $log_file ), -50 );
    }
    return new WP_REST_Response( array( 'logs' => $logs ), 200 );
}
```

**Attack chain**: The `/report` endpoint returns raw log file contents. Log entries contain `esc_html`-escaped file paths and error messages, but could still leak sensitive information:
- Full filesystem paths (revealing server structure)
- Plugin/theme names and versions
- Malware detection details (helping attacker understand what's detected)
- Stack traces (from `handle_exception`)

Combined with Finding 1 (hardcoded key), an unauthenticated attacker can read these logs.

**Existing mitigations**: API key check via `check_api_permission` (but see Finding 1). Log lines are partially escaped.

**Recommendation**: Sanitize log output before returning via API. Redact full filesystem paths. Add rate limiting to the report endpoint.

---

### FINDING 11 — No `realpath()` Canonicalization in Path Safety Checks

**Severity**: MEDIUM
**File**: `/workspace/includes/class-oms-utils.php` lines 34–52, 78–128

**Vulnerable code** (`sanitize_path`, line 36):
```php
$path = wp_normalize_path( $path );
if ( ! self::is_path_safe( $path ) ) {
    throw new InvalidArgumentException( 'Path contains path traversal or is invalid' );
}
```

**Vulnerable code** (`is_path_safe`, lines 85–98):
```php
$decoded    = rawurldecode( (string) $path );
$normalized = wp_normalize_path( $decoded );
$parts = array_values( array_filter( explode( '/', $normalized ), ... ) );
if ( in_array( '..', $parts, true ) ) {
    return false;
}
```

**Attack chain**: `is_path_safe()` performs string-level checks for `..` segments but never resolves symlinks. If an attacker creates a symlink within the WordPress directory tree (e.g., `wp-content/uploads/link -> /etc/`), a path like `wp-content/uploads/link/passwd` would pass all checks (no `..`, no null bytes, no stream wrappers) but resolve to `/etc/passwd`. Symlink creation requires local write access, but this is realistic in shared hosting environments.

**Existing mitigations**: Stream wrapper check blocks `php://`, `data://` etc. Null byte check is present. The `..` segment check is thorough including URL-decoded variants.

**Recommendation**: Call `realpath()` on the resolved path and verify it remains within `ABSPATH` or the expected directory subtree.

---

### FINDING 12 — `file_put_contents` Without Atomic Write in `.htaccess` Creation

**Severity**: LOW
**File**: `/workspace/includes/class-file-security-policy.php` lines 603–612
**File**: `/workspace/includes/class-obfuscated-malware-scanner.php` lines 621–637

**Vulnerable code** (`class-file-security-policy.php:606`):
```php
$result = file_put_contents( $htaccess_file, "Order deny,allow\nDeny from all\nRequire all denied\n" );
```

**Attack chain**: The `.htaccess` protection file is created non-atomically. In the window between `wp_mkdir_p()` (creating the directory) and `file_put_contents()` (writing the `.htaccess`), the backup/quarantine directory is accessible via HTTP. An attacker could request files from the directory during this window. Additionally, if `file_put_contents` is interrupted, a partial `.htaccess` might not provide full protection.

**Existing mitigations**: The window is extremely small. Apache `.htaccess` parsing is forgiving of incomplete files.

**Recommendation**: Write to a temp file and atomically rename it, or use `wp_mkdir_p` with restrictive permissions (0700) so the directory is inaccessible even without `.htaccess`.

---

### FINDING 13 — Insufficient Input Validation on `add_restricted_path` / `add_forbidden_extension`

**Severity**: LOW
**File**: `/workspace/includes/class-file-security-policy.php` lines 487–498

**Vulnerable code**:
```php
public function add_restricted_path( $path ) {
    $this->restricted_paths[] = $path;
}

public function add_forbidden_extension( $ext ) {
    $this->forbidden_extensions[] = $ext;
}
```

**Attack chain**: These public methods accept arbitrary strings without validation. If called with attacker-controlled input (e.g., via a filter or action hook that another plugin exposes), an attacker could:
1. Add `''` (empty string) as a restricted path, which would match all paths via `strpos($relative_path, '')` always returning `0` — causing all file validations to fail (DoS).
2. Add a path like `wp-content/uploads` to block legitimate uploads.

**Existing mitigations**: These methods are not currently called with user input — they appear to be for programmatic use only.

**Recommendation**: Add input validation (non-empty string, no path traversal) and consider making these methods `private` or `protected`.

---

### FINDING 14 — `get_relative_path` Inconsistency Between Classes

**Severity**: INFO
**Files**: `/workspace/includes/class-oms-utils.php` lines 60–70, `/workspace/includes/class-file-security-policy.php` lines 476–478, `/workspace/includes/class-oms-core-integrity-checker.php` lines 131–137

**Description**: Three different implementations of "get relative path from ABSPATH" exist:

1. **`OMS_Utils::get_relative_path()`** — Uses `wp_normalize_path()` on both the input and ABSPATH before `strpos`/`substr`. Most robust.
2. **`OMS_File_Security_Policy::get_relative_path()`** — Simple `str_replace(ABSPATH, '', $file_path)` without normalization.
3. **`OMS_Core_Integrity_Checker::is_verified_core_file()`** — `str_replace(ABSPATH, '', $path)` plus backslash normalization.

The inconsistency means the same file could produce different relative paths depending on which method is used, potentially causing bypass or false-positive discrepancies. For example, on Windows or with mixed path separators, method 2 might fail to strip ABSPATH while method 1 succeeds.

**Recommendation**: Consolidate all relative path computation to use `OMS_Utils::get_relative_path()`.

---

## Summary Table

| # | Severity | File | Issue |
|---|----------|------|-------|
| 1 | CRITICAL | class-oms-config.php:476 | Hardcoded linking key |
| 2 | HIGH | class-oms-api.php:56-64 | Unauthenticated registration endpoint |
| 3 | HIGH | class-oms-core-integrity-checker.php:131-137 | Path bypass in core file verification |
| 4 | MEDIUM | class-obfuscated-malware-scanner.php:318-357 | TOCTOU in upload validation |
| 5 | MEDIUM | class-oms-quarantine-manager.php:40-139 | TOCTOU in quarantine operations |
| 6 | MEDIUM | class-obfuscated-malware-scanner.php:331 | Incomplete meta_value path validation |
| 7 | LOW | class-oms-filesystem.php:41, class-oms-utils.php:154 | Regex injection via config patterns |
| 8 | LOW | class-oms-filesystem.php:60-67 | Special char ratio bypass |
| 9 | MEDIUM | class-file-security-policy.php:146-150 | Overly broad known-good patterns |
| 10 | MEDIUM | class-oms-api.php:208-221 | Log content exposure via API |
| 11 | MEDIUM | class-oms-utils.php:34-52 | No symlink resolution in path checks |
| 12 | LOW | class-file-security-policy.php:603-612 | Non-atomic .htaccess creation |
| 13 | LOW | class-file-security-policy.php:487-498 | No validation on add_restricted_path |
| 14 | INFO | Multiple files | Inconsistent relative path computation |

## Items NOT Found (Negative Findings)

- **No command injection**: No `exec()`, `system()`, `shell_exec()`, `passthru()`, or `proc_open()` calls exist in the scoped files.
- **No SSRF**: The only outbound HTTP request is `wp_remote_get()` to `https://api.wordpress.org/core/checksums/1.0/` with server-controlled `$wp_version` and `get_locale()` parameters — not attacker-controlled.
- **No unsafe deserialization**: No `unserialize()` calls found.
- **No SQL injection**: Database operations use WordPress APIs (`get_option`, `update_option`, etc.).
- **No XSS in scanner output**: Log and admin notice output consistently uses `esc_html()`, `wp_kses_post()`, and `esc_attr()`.
