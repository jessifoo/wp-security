# Unfinished Flows Analysis

## Summary
This document identifies all unfinished flows, incomplete implementations, and missing features in the codebase.

## 🔴 Critical Unfinished Flows

### 1. Hourly Cleanup Schedule (Documentation Mismatch)
**Location**: `includes/class-obfuscated-malware-scanner.php:191`, `includes/class-oms-plugin.php:102`
**Status**: ⚠️ **INCOMPLETE**

**Issue**: 
- README.md promises "Hourly cleanup process"
- Only daily cleanup is implemented (`oms_daily_cleanup`)
- No hourly cleanup hook exists (`oms_hourly_cleanup`)

**Current Implementation**:
```php
// Only daily cleanup scheduled
wp_schedule_event( time(), 'daily', 'oms_daily_cleanup' );
add_action( 'oms_daily_cleanup', array( $this, 'run_full_cleanup' ) );
```

**Missing**:
- Hourly cleanup schedule registration
- Hourly cleanup action hook
- Handler method for hourly cleanup (if different from daily)

**Action Required**: 
- Either implement hourly cleanup OR update README.md to reflect daily-only cleanup

---

### 2. Database Index Definition Enhancement (TODO)
**Location**: `includes/class-oms-database-scanner.php:744-746`
**Status**: ⚠️ **PARTIALLY IMPLEMENTED**

**Issue**:
- Current implementation uses hardcoded index definitions
- TODO comment indicates future enhancement to fetch authoritative definitions from WordPress core

**Current Code**:
```php
// TODO: Optionally fetch authoritative index definitions from WordPress core
// schema (e.g., via wp-admin/includes/schema.php or SHOW INDEX queries) in a
// future enhancement to reduce false positives on standard installs.
```

**Impact**: 
- May produce false positives on custom WordPress installations
- Works but could be more accurate

**Action Required**: 
- Low priority - enhancement for better accuracy
- Consider implementing if false positives become an issue

---

### 3. API Registration Security Enhancement (Placeholder)
**Location**: `includes/class-oms-api.php:129`
**Status**: ⚠️ **BASIC IMPLEMENTATION**

**Issue**:
- Comment indicates temporary token or manual approval should be added
- Currently uses simple master_key validation

**Current Code**:
```php
// In a real scenario, we might want a temporary token or manual approval.
// For now, we'll accept a 'master_key' to establish trust.
```

**Impact**:
- Security is functional but could be enhanced
- No expiration mechanism for API keys
- No manual approval workflow

**Action Required**:
- Medium priority - security enhancement
- Consider adding token expiration and manual approval workflow

---

## 🟡 Missing Features (Documented but Not Implemented)

### 4. Multiple WordPress Installations Support
**Location**: Not implemented
**Status**: ❌ **NOT IMPLEMENTED**

**Documentation**: `REMAINING_WORK.md`, `IMPLEMENTATION_STATUS.md`

**Missing Components**:
- Configuration for multiple WordPress root paths
- WP-CLI command to scan all installations
- Resource-aware batch processing across installations
- Per-installation scanning with resource limits

**Current Limitation**:
- Plugin only scans current WordPress installation (uses `ABSPATH`, `WP_CONTENT_DIR`)
- No way to specify or scan other WordPress installations

**Action Required**:
- High priority if multi-installation support is needed
- Requires new class: `OMS_Multi_Installation_Scanner`
- Requires WP-CLI command: `ScanAllCommand`

---

### 5. Theme Content Export/Import
**Location**: Not implemented
**Status**: ❌ **NOT IMPLEMENTED**

**Documentation**: README.md mentions "Supports theme content export/import"

**Current Status**:
- Backup exists (theme files are backed up before quarantine)
- No JSON export functionality
- No JSON import/restore functionality
- No admin UI for export/import

**Action Required**:
- Low priority - nice-to-have feature
- Add JSON export/import methods
- Add admin UI for export/import

---

### 6. Automatic Rollback on Failure
**Location**: Not implemented
**Status**: ❌ **NOT IMPLEMENTED**

**Documentation**: `decisions.md` mentions "Automatic recovery mechanisms"

**Current Status**:
- Core file replacement exists
- Database transactions have rollback support
- No automatic rollback if corruption detected after file operations
- No safe state fallbacks

**Action Required**:
- Medium priority - safety enhancement
- Add rollback mechanism if corruption detected
- Implement transaction-like behavior for file operations

---

## 🟢 Code Quality Issues (Minor Incomplete Flows)

### 7. Empty Catch Blocks
**Location**: Multiple files
**Status**: ⚠️ **MINOR**

**Issue**: Some catch blocks only log errors but don't handle recovery

**Examples Found**:
- `includes/class-oms-database-scanner.php:292` - Table structure check catch block only logs
- `includes/class-oms-database-scanner.php:344` - Index check catch block only logs
- `includes/class-oms-database-scanner.php:424` - Table content scan catch block only logs

**Impact**: 
- Errors are logged but no recovery mechanism
- Function continues with partial data

**Action Required**:
- Low priority - code quality improvement
- Consider adding recovery mechanisms or more detailed error handling

---

### 8. Incomplete Error Recovery in Database Scanner
**Location**: `includes/class-oms-database-scanner.php`
**Status**: ⚠️ **PARTIAL**

**Issue**: 
- When validation fails, methods return empty arrays
- No retry mechanism
- No fallback strategies

**Examples**:
- `validate_db_identifier()` returns `false` on invalid input
- `get_id_column_name()` returns `false` if table not recognized
- Methods return early with empty arrays when validation fails

**Impact**:
- Scans may silently skip problematic tables/columns
- No notification to admin about skipped items

**Action Required**:
- Medium priority - improve error handling
- Add admin notifications for skipped scans
- Consider retry mechanisms for transient failures

---

## 📋 Priority Summary

### High Priority
1. **Hourly Cleanup** - Documentation mismatch needs resolution
2. **Multiple Installations Support** - If required for use case

### Medium Priority
3. **API Registration Security** - Add token expiration and approval workflow
4. **Automatic Rollback** - Safety enhancement
5. **Error Recovery** - Better handling of database scan failures

### Low Priority
6. **Database Index Enhancement** - Reduce false positives
7. **Theme Export/Import** - Nice-to-have feature
8. **Empty Catch Blocks** - Code quality improvement

---

## 🔍 Files Requiring Attention

1. `includes/class-obfuscated-malware-scanner.php` - Add hourly cleanup
2. `includes/class-oms-plugin.php` - Add hourly cleanup
3. `includes/class-oms-database-scanner.php` - Index enhancement TODO
4. `includes/class-oms-api.php` - Security enhancement placeholder
5. `README.md` - Update documentation to match implementation

---

## 🟢 Additional Findings

### 9. Switch Statement Without Default (Acceptable)
**Location**: `includes/class-oms-database-scanner.php:828-849`
**Status**: ✅ **ACCEPTABLE** (Has fallback)

**Issue**: Switch statement for `get_id_column_name()` has no default case

**Current Implementation**:
```php
switch ( $table_base ) {
    case 'posts': return 'ID';
    case 'users': return 'ID';
    // ... more cases
}
// Fallback: query information_schema to discover columns.
```

**Status**: ✅ Acceptable - Has fallback mechanism after switch statement

---

## 📝 Notes

- Most core functionality is complete and working
- Unfinished flows are mostly enhancements rather than critical bugs
- Database security module is implemented but has enhancement opportunities
- Documentation needs to be aligned with actual implementation
- Switch statements without defaults are acceptable when fallback logic exists
