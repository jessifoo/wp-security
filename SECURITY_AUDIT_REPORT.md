# Security Audit Report — OMS Plugin

**Date**: 2026-08-19  
**Scope**: Database Scanner, Database Cleaner, Cache, Rate Limiter, Error Handler, Exception, Command  
**Auditor**: Automated Security Review  

---

## Executive Summary

This audit covers seven files in the Obfuscated Malware Scanner (OMS) WordPress plugin. The codebase shows generally good security practices — `$wpdb->prepare()` is used for SQL queries, output is escaped with `esc_html()`, and identifier validation exists. However, several medium-to-high severity issues were identified, primarily around filter-based security control bypasses, information disclosure, rate limiter weaknesses, a hardcoded secret, and incomplete infinite-loop guards.

---

## Finding 1 — Privilege Escalation via `oms_allowed_cleanup_tables` Filter (HIGH)

**File**: `includes/class-oms-database-cleaner.php`, lines 517–533  
**Category**: Privilege escalation / security control bypass  

### Vulnerable Code

```php
private function is_allowed_table( $table_name ) {
    global $wpdb;
    $table_base = str_replace( $wpdb->prefix, '', $table_name );

    $allowed = apply_filters( 'oms_allowed_cleanup_tables', $this->allowed_tables, $table_name );

    return in_array( $table_base, $allowed, true );
}
```

### Attack Chain

1. A malicious plugin or compromised theme hooks into `oms_allowed_cleanup_tables`.
2. The callback returns an expanded array that includes sensitive non-WP-core tables (e.g., `wp_users`, custom plugin tables storing credentials/payment data).
3. The attacker triggers database cleanup with crafted `$issues` pointing at rows in those tables.
4. The cleaner deletes arbitrary rows from tables that were never intended to be cleanable.

### Impact

Arbitrary row deletion in any database table, leading to data loss, authentication bypass (deleting user records), or denial of service.

### Existing Mitigations

- The hardcoded `$allowed_tables` array is limited to WP core tables.
- The `$issues` array typically comes from the scanner, not direct user input.

### Recommendation

Remove the `apply_filters` call or add a capability check (`current_user_can('manage_options')`) before the filter is applied. At minimum, validate that the filtered result is a strict subset of the hardcoded allowed list.

---

## Finding 2 — Privilege Escalation via `oms_table_id_columns` Filter (HIGH)

**File**: `includes/class-oms-database-cleaner.php`, lines 558–565  
**Category**: Privilege escalation / security control bypass  

### Vulnerable Code

```php
$id_columns = apply_filters( 'oms_table_id_columns', $id_columns, $table_name );
return isset( $id_columns[ $table_base ] ) ? $id_columns[ $table_base ] : false;
```

### Attack Chain

1. A malicious plugin hooks into `oms_table_id_columns` and changes the ID column mapping for a table (e.g., maps `posts` to `post_author` instead of `ID`).
2. When `delete_row_with_backup()` runs, it uses `$wpdb->delete( $table_name, array( $id_column => $row_id ) )`.
3. If `$id_column` is a non-unique column (e.g., `post_author`), a single delete call can remove **all rows** matching that value — deleting all posts by a given author instead of one specific post.

### Impact

Mass data deletion via column confusion. An attacker could delete all posts, all options with a given autoload value, etc.

### Existing Mitigations

- The `$id_columns` defaults are correct WP core mappings.

### Recommendation

Do not expose the ID column mapping via a filter. If extensibility is needed, validate that the returned column name actually has a `UNIQUE` or `PRIMARY KEY` constraint on the target table.

---

## Finding 3 — Security Control Bypass via `oms_expected_indexes` Filter (MEDIUM)

**File**: `includes/class-oms-database-scanner.php`, lines 772  
**Category**: Security control bypass  

### Vulnerable Code

```php
$expected = apply_filters( 'oms_expected_indexes', $expected, $table_name, $table_base );
```

### Attack Chain

1. A malicious plugin hooks into `oms_expected_indexes` and returns an empty array for all tables.
2. The integrity check for missing indexes is completely bypassed, suppressing detection of tables that have had their indexes maliciously dropped.
3. Dropped indexes can degrade performance (DoS) and may indicate ongoing database tampering that goes unreported.

### Impact

Suppression of security scan findings. An attacker who has compromised the database can hide evidence of tampering.

### Existing Mitigations

- The filter only affects the index check portion of the scan.

### Recommendation

Consider removing this filter or marking it as a privileged operation requiring `manage_options` capability.

---

## Finding 4 — Hardcoded Linking Key / Default Secret (HIGH)

**File**: `includes/class-oms-config.php`, line 476  
**Category**: Hardcoded credential  

### Vulnerable Code

```php
const OMS_LINKING_KEY = 'oms_secret_key_change_me';
```

### Attack Chain

1. The constant `OMS_LINKING_KEY` is shipped with a default placeholder value.
2. If site administrators do not change it (and there is no enforcement), any functionality gated by this key can be bypassed by using the well-known default.
3. Depending on how this key is used (centralized management handshake), an attacker could impersonate a management server or bypass authentication.

### Impact

Unauthorized access to management/linking functionality. Severity depends on what `OMS_LINKING_KEY` protects.

### Existing Mitigations

- The naming suggests users should change it (`change_me`), but there is no runtime enforcement.

### Recommendation

- Remove the hardcoded default and require the key to be set via `wp-config.php` or the admin UI.
- Add a runtime check that triggers an admin notice if the key is still the default value.
- Generate a random key on first activation.

---

## Finding 5 — Information Disclosure in Error Messages (MEDIUM)

**File**: `includes/class-oms-database-scanner.php`, line 157; `includes/class-oms-database-cleaner.php`, line 155; line 217  
**Category**: Information disclosure  

### Vulnerable Code

```php
// class-oms-database-scanner.php:157
return array(
    'success' => false,
    'issues'  => array(),
    'message' => $e->getMessage(),  // Raw exception message returned
);

// class-oms-database-cleaner.php:155
return array(
    'success'  => false,
    'message'  => $e->getMessage(),  // Raw exception message
    'rollback' => true,
);

// class-oms-database-cleaner.php:217
'message' => sprintf( 'Database delete failed: %s', $wpdb->last_error ),
```

### Attack Chain

1. An attacker triggers conditions that cause database errors (e.g., specially crafted content that causes query failures).
2. The raw `$e->getMessage()` or `$wpdb->last_error` is returned in the response array.
3. These messages can contain database table names, column names, query fragments, MySQL version info, and file paths — all useful for further attacks.

### Impact

Database schema enumeration, query structure disclosure, and server path disclosure aiding further exploitation.

### Existing Mitigations

- Internal logging uses `esc_html()` on messages.
- The return values are used internally but may be surfaced to the admin UI or REST responses.

### Recommendation

Return generic error messages to callers and log the detailed messages internally. For example:
```php
'message' => 'Database operation failed. Check logs for details.',
```

---

## Finding 6 — Incomplete Loop Guard in `scan_column_content` (MEDIUM)

**File**: `includes/class-oms-database-scanner.php`, lines 468–525  
**Category**: Denial of Service  

### Vulnerable Code

```php
while ( true ) {
    $rows = $wpdb->get_results( /* ... LIMIT 100 OFFSET $offset ... */ );

    foreach ( $rows as $row ) { /* ... */ }

    $offset += $batch_size;

    // Prevent infinite loops.
    if ( $offset > 10000 ) {
        break;
    }
}
```

### Attack Chain

1. The loop has no check for an empty `$rows` result set. If `$wpdb->get_results()` returns an empty array (no more rows), the loop continues iterating with increasing offsets until it hits the 10,000 cap.
2. For a table with, say, 50 rows and 10 text columns, this means 100 unnecessary queries per column (1,000 per table) before breaking.
3. Across 7 critical tables, this is thousands of wasted database queries on every scan.

### Impact

Performance degradation / resource exhaustion on the database server. On a shared hosting environment, this could impact other sites.

### Existing Mitigations

- The 10,000 cap prevents truly infinite loops.

### Recommendation

Add `if ( empty( $rows ) ) { break; }` immediately after the query result, before the `foreach`.

---

## Finding 7 — Rate Limiter Bypass via `OMS_RATE_LIMIT_ENABLED` Constant (MEDIUM)

**File**: `includes/class-oms-rate-limiter.php`, lines 104–113  
**Category**: Rate limiting bypass  

### Vulnerable Code

```php
private function is_rate_limiting_enabled() {
    if ( ! defined( 'OMS_RATE_LIMIT_ENABLED' ) ) {
        define( 'OMS_RATE_LIMIT_ENABLED', true );
    }
    return OMS_RATE_LIMIT_ENABLED;
}
```

### Attack Chain

1. Any plugin or `wp-config.php` can define `OMS_RATE_LIMIT_ENABLED` as `false` before OMS loads.
2. Once the constant is set to `false`, all rate limiting is permanently disabled for the entire request lifecycle.
3. An attacker who can influence `wp-config.php` (e.g., through a file-write vulnerability) or install a must-use plugin can disable all resource protection.

### Impact

Complete rate limiting bypass, enabling resource-exhaustive scans that could DoS the server.

### Existing Mitigations

- The constant defaults to `true` if not previously defined.

### Recommendation

- Log a warning if rate limiting is disabled.
- Consider making this a filterable option (with capability check) rather than a bare constant, or at minimum validate the constant type.

---

## Finding 8 — Race Condition in Rate Limiter Request Counter (LOW)

**File**: `includes/class-oms-rate-limiter.php`, lines 159–173  
**Category**: Rate limiting bypass / TOCTOU  

### Vulnerable Code

```php
private function is_request_limit_exceeded() {
    $request_key   = 'oms_request_count_' . gmdate( 'Y-m-d-H' );
    $request_count = (int) get_transient( $request_key );
    $max_requests  = OMS_Config::RATE_LIMIT_CONFIG['requests_per_hour'];

    if ( $request_count > $max_requests ) {
        return true;
    }

    // Increment request count.
    set_transient( $request_key, $request_count + 1, HOUR_IN_SECONDS );
    return false;
}
```

### Attack Chain

1. The read-then-increment pattern (`get_transient` → compare → `set_transient`) is not atomic.
2. Under concurrent requests, multiple requests can read the same count before any of them increment it.
3. This allows exceeding the rate limit by the number of concurrent requests.

### Impact

Rate limit bypass under concurrent load. Practical severity is low because WordPress transients use a single-row update that provides some serialization.

### Existing Mitigations

- WordPress transients in a MySQL-backed object cache provide some natural serialization.
- The rate limit values are generous (100/hour).

### Recommendation

If precise rate limiting is required, use an atomic increment approach (e.g., `$wpdb->query("UPDATE ... SET count = count + 1")`) or use a Redis-based atomic counter if available.

---

## Finding 9 — Unsanitized `backup_id` in Transient Key (MEDIUM)

**File**: `includes/class-oms-database-cleaner.php`, lines 450–464  
**Category**: Cache key injection  

### Vulnerable Code

```php
public function restore_from_backup( $backup_id ) {
    $backups = get_transient( 'oms_cleanup_backup_' . $backup_id );
    // ...
    delete_transient( 'oms_cleanup_backup_' . $backup_id );
```

### Attack Chain

1. `restore_from_backup()` is a public method that accepts a `$backup_id` string.
2. The `$backup_id` is concatenated directly into the transient key with no sanitization or format validation.
3. An attacker who can call this method (e.g., via an admin AJAX handler) could pass a crafted `backup_id` to read/delete arbitrary transients by controlling the suffix of the transient key.
4. While WordPress transient keys are length-limited (172 chars for the key), this could still access other `oms_cleanup_backup_`-prefixed transients or cause unexpected behavior.

### Impact

Potential reading/deletion of unrelated transients if attacker controls the `backup_id` parameter.

### Existing Mitigations

- The method is on a class that is typically only instantiated by trusted code.
- `get_current_backup_id()` generates IDs using `wp_generate_password(6, false)`.

### Recommendation

Validate `$backup_id` format with a regex like `/^cleanup_\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}_[a-zA-Z0-9]{6}$/` before using it.

---

## Finding 10 — Error Handler Logs Raw Exception Messages (LOW)

**File**: `includes/class-oms-error-handler.php`, lines 40–52  
**Category**: Information disclosure  

### Vulnerable Code

```php
public function handle_exception( Exception $e, $context = '' ) {
    $message = $e->getMessage();
    if ( ! empty( $context ) ) {
        $message = $context . ': ' . $message;
    }
    $this->logger->error( $message );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $this->logger->debug( $e->getTraceAsString() );
    }
}
```

### Attack Chain

1. Exception messages containing sensitive data (file paths, SQL queries, credentials in connection strings) are logged directly.
2. When `WP_DEBUG` is true, the full stack trace including file paths and line numbers is logged.
3. If log files are accessible (e.g., misconfigured web server), this reveals internal application structure.

### Impact

Information disclosure via log files. Server paths, class names, and method signatures exposed.

### Existing Mitigations

- The logger's `init_log_dir()` creates an `.htaccess` file denying access.
- Debug trace is only logged when `WP_DEBUG` is enabled.
- Production sites should have `WP_DEBUG` disabled.

### Recommendation

- Sanitize exception messages before logging (strip potential credentials/connection strings).
- Ensure log directory also has an `index.php` file to prevent directory listing on misconfigured servers.

---

## Finding 11 — `OMS_Exception::handleException` Uses `error_log` Directly (LOW)

**File**: `includes/class-oms-exception.php`, line 38  
**Category**: Information disclosure  

### Vulnerable Code

```php
public function handleException( Exception $e, string $context = '' ): never {
    error_log( sprintf( 'OMS Exception in %s: %s', esc_html( $context ), esc_html( $e->getMessage() ) ) );
    throw $e;
}
```

### Attack Chain

1. While `esc_html()` is applied, `error_log()` writes to the PHP error log rather than the OMS log.
2. The PHP error log may have different access controls than the OMS log directory (which has `.htaccess` protection).
3. On some hosting setups, PHP error logs are accessible at predictable paths.

### Impact

Exception details disclosed in a potentially less-protected log location.

### Existing Mitigations

- `esc_html()` prevents HTML injection in the log.

### Recommendation

Route all error logging through the `OMS_Logger` class for consistent access control.

---

## Finding 12 — In-Memory Cache Has No Key Validation (LOW)

**File**: `includes/class-oms-cache.php`, lines 37–65  
**Category**: Cache poisoning  

### Vulnerable Code

```php
public function get( $key ) {
    if ( isset( $this->cache[ $key ] ) &&
        ( time() - $this->cache_times[ $key ] ) < OMS_Config::CACHE_CONFIG['ttl'] ) {
        return $this->cache[ $key ];
    }
    return null;
}

public function set( $key, $value, $ttl = null ) {
    // ... no key validation ...
    $this->cache[ $key ]       = $value;
    $this->cache_times[ $key ] = time();
}
```

### Attack Chain

1. Cache keys are constructed from table names in `get_expected_table_structure()` (line 688): `'oms_table_structure_' . $table_name`.
2. If a caller passes a manipulated `$table_name`, the cache key is poisoned.
3. The poisoned cache entry could return incorrect "expected" table structures, causing the integrity checker to produce false negatives (missing malware) or false positives (disrupting the site).

### Impact

Low in practice because `$table_name` comes from internal hardcoded lists. But the cache API has no defensive key validation.

### Existing Mitigations

- Cache keys are constructed from trusted internal data.
- The cache is in-memory only (request-scoped), so poisoning doesn't persist across requests.
- The `$ttl` parameter is accepted but ignored — all entries use the global TTL from config.

### Recommendation

Validate cache keys against a pattern (alphanumeric + underscore + dots only). The ignored `$ttl` parameter should either be used or removed to avoid confusion.

---

## Finding 13 — `$wpdb->last_error` Exposed in Cleaner Response (MEDIUM)

**File**: `includes/class-oms-database-cleaner.php`, line 217  
**Category**: Information disclosure  

### Vulnerable Code

```php
if ( false === $deleted ) {
    return array(
        'success' => false,
        'message' => sprintf( 'Database delete failed: %s', $wpdb->last_error ),
    );
}
```

### Attack Chain

1. `$wpdb->last_error` can contain full SQL error messages including query text, table names, and column info.
2. This is returned in the response array which may be serialized to JSON and sent to the client.

### Impact

Database error disclosure to authenticated admin users, or to any user if the response is not properly access-controlled.

### Existing Mitigations

- Only occurs when a delete operation fails.

### Recommendation

Log the detailed error and return a generic message.

---

## Finding 14 — `str_replace` Used for Table Prefix Stripping (LOW)

**File**: `includes/class-oms-database-scanner.php`, line 85; `includes/class-oms-database-cleaner.php`, line 520  
**Category**: Logic flaw  

### Vulnerable Code

```php
$table_base = str_replace( $wpdb->prefix, '', $identifier );
```

### Attack Chain

1. `str_replace` replaces **all** occurrences, not just the prefix.
2. If the table prefix appears in the table name itself (e.g., prefix `wp_` and table name `wp_wp_options`), this would incorrectly strip both occurrences, producing `options` instead of `wp_options`.
3. This could cause an unintended table to pass the allowed-table check.

### Impact

Very low — requires an unusual table prefix and naming collision. But the logic is subtly incorrect.

### Existing Mitigations

- Standard WordPress prefixes don't overlap with table base names.

### Recommendation

Use a prefix-aware stripping approach:
```php
if ( 0 === strpos( $identifier, $wpdb->prefix ) ) {
    $table_base = substr( $identifier, strlen( $wpdb->prefix ) );
}
```

---

## Finding 15 — Command.php Has No Security Controls (INFO)

**File**: `src/Command.php`  
**Category**: Design review  

### Observation

The `Command` class is a simple `ArrayAccess` + `Iterator` implementation with no command injection, no shell interaction, no privilege escalation vectors, and no WP-CLI integration. It is a data container only.

Despite its name suggesting command execution, this class is **not a security risk** in its current form. There are no:
- `exec()`, `shell_exec()`, `system()`, `passthru()`, or `proc_open()` calls
- WP-CLI command registrations
- Any method that executes stored array elements as commands

### Recommendation

Consider renaming the class to better reflect its purpose (e.g., `Collection` or `DataContainer`) to avoid security audit false alarms.

---

## Summary Table

| # | Finding | Severity | File | Type |
|---|---------|----------|------|------|
| 1 | Filter-based allowed table bypass | HIGH | database-cleaner.php:530 | Privilege escalation |
| 2 | Filter-based ID column manipulation | HIGH | database-cleaner.php:564 | Privilege escalation |
| 3 | Filter-based scan suppression | MEDIUM | database-scanner.php:772 | Security bypass |
| 4 | Hardcoded linking key | HIGH | oms-config.php:476 | Hardcoded credential |
| 5 | Raw exception messages in responses | MEDIUM | scanner/cleaner | Info disclosure |
| 6 | Incomplete loop guard (no empty check) | MEDIUM | database-scanner.php:468 | DoS |
| 7 | Constant-based rate limit disable | MEDIUM | rate-limiter.php:104 | Rate limit bypass |
| 8 | Non-atomic request counter | LOW | rate-limiter.php:159 | Rate limit bypass |
| 9 | Unsanitized backup_id in transient key | MEDIUM | database-cleaner.php:451 | Cache key injection |
| 10 | Raw exception messages in logs | LOW | error-handler.php:40 | Info disclosure |
| 11 | Direct error_log bypassing OMS_Logger | LOW | oms-exception.php:38 | Info disclosure |
| 12 | No cache key validation | LOW | oms-cache.php:37 | Cache poisoning |
| 13 | `$wpdb->last_error` in response | MEDIUM | database-cleaner.php:217 | Info disclosure |
| 14 | `str_replace` for prefix stripping | LOW | scanner/cleaner | Logic flaw |
| 15 | Command.php naming confusion | INFO | Command.php | Design |
