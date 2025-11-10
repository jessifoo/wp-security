# Code Quality Issues Found

## Critical Bugs (Will Cause Runtime Errors)

### 1. Undefined Variables in `should_throttle()` method
**File:** `includes/class-obfuscated-malware-scanner.php`
**Lines:** 1358, 1361, 1365, 1376, 1382, 1389, 1391

- **Line 1358**: Calls `$this->getMemoryLimit()` but method is `get_memory_limit()` (snake_case)
- **Line 1361**: Uses `$memoryLimit` but variable is `$memory_limit`
- **Line 1365**: Uses `$memoryLimit` but variable is `$memory_limit`
- **Line 1376**: Uses `$requestCount` and `$maxRequests` but variables are `$request_count` and `$max_requests`
- **Line 1382**: Uses `$requestCount` but variable is `$request_count`
- **Line 1389**: Uses `$peakStart` and `$peakEnd` but variables are `$peak_start` and `$peak_end`
- **Line 1391**: Uses `$load` and `$max_load` which are only defined inside an `if` block (scope issue)

**Impact:** These will cause "Undefined variable" PHP notices/warnings and logic errors.

## Code Quality Issues

### 2. Debug Code in Production
**Files:** Multiple
- `error_log()` calls (lines 101, 1191 in class-obfuscated-malware-scanner.php; lines 239, 279 in class-file-security-policy.php; line 155 in class-oms-logger.php)
- `debug_backtrace()` calls (lines 1180, 142)
- Should use logger methods instead of direct `error_log()`

### 3. Non-Strict Comparisons
**File:** `includes/class-obfuscated-malware-scanner.php`
**Line:** 592
- `in_array()` without strict mode (`true` parameter)
- Can cause type coercion bugs

### 4. Timezone Issues
**Files:** Multiple
- `date()` instead of `gmdate()` or timezone-aware functions
- Lines: 1372 (class-obfuscated-malware-scanner.php), 427 (class-oms-scanner.php), 269 (class-file-security-policy.php), 107, 160, 211 (class-oms-logger.php)
- `date()` is affected by runtime timezone changes

### 5. Missing Documentation
**Files:** Multiple
- Missing `@throws` tags in docblocks (lines 827, 1063, 1115, 1410)
- Missing parameter descriptions (line 1177)
- Inline comments not ending with periods (many instances)

### 6. Variable Naming Violations
**File:** `includes/class-obfuscated-malware-scanner.php`
- Variables not in snake_case (WordPress standard):
  - `$maxMemoryPercent` → should be `$max_memory_percent`
  - `$requestKey` → should be `$request_key` (though this one is actually correct)
  - `$lineNumber` → should be `$line_number`
  - `$contextStart` → should be `$context_start`
  - `$contextLines` → should be `$context_lines`

### 7. Incomplete TODO Comments
**File:** `includes/class-obfuscated-malware-scanner.php`
**Line:** 1234
- `@TODO CLeanup of WHAT?` - Unclear what needs cleanup

### 8. File Operations Not Using WP_Filesystem
**Files:** Multiple
- Direct PHP filesystem calls instead of WP_Filesystem methods
- This is a WordPress best practice but may be intentional for low-level operations
- Functions: `fopen()`, `fread()`, `fclose()`, `file_get_contents()`, `file_put_contents()`, `rename()`, `unlink()`, `chmod()`, `mkdir()`

### 9. Runtime Configuration Changes
**File:** `includes/class-obfuscated-malware-scanner.php`
**Line:** 571
- `ini_set()` calls at runtime (though errors are now properly handled)
- WordPress standards discourage runtime configuration changes

### 10. Large File Size
**File:** `includes/class-obfuscated-malware-scanner.php`
- 1525 lines - Consider breaking into smaller, focused classes
- Violates Single Responsibility Principle

### 11. Magic Numbers
**Files:** Multiple
- Hard-coded values like `0.8`, `0.2`, `1024`, `0755`, `0333`, `0000`
- Should be defined as named constants

### 12. Potential Security Issues
- Direct file operations without proper sanitization in some places
- `wp_remote_get()` with `stream: true` and `filename` parameter (line 1143) - verify this is safe

## Summary

**Critical Issues:** 7 bugs that will cause runtime errors
**High Priority:** Debug code, undefined variables, non-strict comparisons
**Medium Priority:** Documentation, naming conventions, timezone issues
**Low Priority:** File operations, code organization, magic numbers
