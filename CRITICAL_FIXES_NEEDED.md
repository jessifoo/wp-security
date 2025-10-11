# Critical Fixes Required Before Production

## 🔴 MUST FIX (Fatal Errors)

### 1. Missing `get_status()` Method
**File:** `includes/class-obfuscated-malware-scanner.php`
```php
public function get_status() {
    return array(
        'last_scan' => get_option('oms_last_scan', 'Never'),
        'files_scanned' => get_option('oms_files_scanned', 0),
        'issues_found' => get_option('oms_issues_found', 0),
        'issues' => get_option('oms_detected_issues', array())
    );
}
```

### 2. Initialize Missing Properties
**File:** `includes/class-obfuscated-malware-scanner.php:59-69`
```php
public function __construct() {
    try {
        $this->logger = new OMS_Logger();
        $this->cache = new OMS_Cache();  // ← ADD THIS
        $this->rateLimiter = new OMS_Rate_Limiter();  // ← ADD THIS
        $this->securityPolicy = new OMS_File_Security_Policy();  // ← ADD THIS
        $this->scanner = new OMS_Scanner($this->logger, $this->rateLimiter, $this->cache);  // ← ADD THIS
        $this->compiledPatterns = $this->loadOrCompilePatterns();
        $this->logger->info('Scanner initialized successfully');
    } catch (OMS_Exception $e) {
        error_log("OMS initialization failed: " . $e->getMessage());
    }
}
```

### 3. Fix Undefined Variable in Pattern Matching
**File:** `includes/class-oms-scanner.php:289-303`
```php
private function log_pattern_match($matches, $path, $pattern_name, $position, $content) {  // ← ADD $content parameter
    $match_pos = $matches[0][1];
    $match_content = $matches[0][0];
    
    // Get context around match
    $context = $this->extract_match_context($content, $match_pos);
    
    $this->logger->warning("Malware pattern detected", [
        'path' => $path,
        'pattern_name' => $pattern_name,
        'position' => $position - strlen($content) + $match_pos,
        'context' => $context
    ]);
}
```

**And update the call site:**
```php
private function match_patterns($content, $path, $position) {
    foreach ($this->compiled_patterns as $pattern_name => $pattern) {
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $this->log_pattern_match($matches, $path, $pattern_name, $position, $content);  // ← ADD $content
            return true;
        }
    }
    return false;
}
```

## 🟡 HIGH PRIORITY (Will Cause Errors)

### 4. Remove Unused Import
**File:** `includes/class-obfuscated-malware-scanner.php:9`
```php
// DELETE THIS LINE:
use PSpell\Config;
```

### 5. Fix Inconsistent Return Types
**File:** `includes/class-obfuscated-malware-scanner.php:738-784`

**Current (wrong):**
```php
public function validateFile($path) {
    // Returns false in some places
    return false;  
    // Returns boolean from scanFileChunks() in others
    return $this->scanFileChunks($path, $chunkSize);
}
```

**Fixed:**
```php
public function validateFile($path) {
    $this->logger->info("Validating file: $path");
    
    try {
        $filesize = filesize($path);
        if ($filesize > OMS_Config::SCAN_CONFIG['max_file_size']) {
            $this->logger->warning(sprintf(
                'File exceeds maximum size limit: %s (%d bytes)',
                $path,
                $filesize
            ));
            return array('valid' => false, 'reason' => 'File too large');
        }
        
        $perms = fileperms($path) & 0777;
        if ($perms > OMS_Config::SCAN_CONFIG['allowed_permissions']['file']) {
            $this->logger->warning(sprintf(
                'File has unsafe permissions: %s (0%o)',
                $path,
                $perms
            ));
            return array('valid' => false, 'reason' => 'Unsafe permissions');
        }
        
        $basename = basename($path);
        if (in_array($basename, OMS_Config::SCAN_CONFIG['excluded_files'])) {
            return array('valid' => true, 'reason' => 'Excluded file');
        }
        
        if ($filesize === 0) {
            $this->logger->warning("Zero-byte file detected: $path");
            return array('valid' => false, 'reason' => 'Zero-byte file');
        }
        
        $chunkSize = $this->calculateOptimalChunkSize();
        $scanResult = $this->scanFileChunks($path, $chunkSize);
        
        return array(
            'valid' => $scanResult,
            'reason' => $scanResult ? 'Clean' : 'Malware detected'
        );
    } catch (Exception $e) {
        $this->handleException($e, "Error validating file: $path");
        return array('valid' => false, 'reason' => 'Scan error: ' . $e->getMessage());
    }
}
```

### 6. Fix scanFileChunks Return Logic
**File:** `includes/class-obfuscated-malware-scanner.php:329-435`

**Current issue:** Line 429 returns `true` for clean files, but method is called `containsMalware()` suggesting opposite logic.

**Standardize:**
- `containsMalware()` should return `true` if malware found
- `scanFileChunks()` should return `true` if file is clean

## 🟢 MEDIUM PRIORITY (Functional Issues)

### 7. Fix Activation Hook
**File:** `obfuscated-malware-scanner.php:95`

**Current (wrong):**
```php
register_activation_hook(plugin_basename(__FILE__), array($this, 'runFullCleanup'));
```

**Fixed:**
```php
register_activation_hook(__FILE__, array($plugin, 'activate'));
```

**And add to OMS_Plugin:**
```php
public function activate() {
    // Create log directory
    $log_dir = WP_CONTENT_DIR . '/oms-logs';
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }
    
    // Create quarantine directory
    $quarantine_dir = WP_CONTENT_DIR . '/oms-quarantine';
    if (!file_exists($quarantine_dir)) {
        wp_mkdir_p($quarantine_dir);
    }
    
    // Schedule cron
    if (!wp_next_scheduled('oms_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'oms_daily_cleanup');
    }
    
    // Initialize options
    add_option('oms_last_scan', current_time('mysql'));
    add_option('oms_files_scanned', 0);
    add_option('oms_issues_found', 0);
    add_option('oms_detected_issues', array());
}
```

### 8. Add WordPress Settings API
**File:** `admin/class-oms-admin.php`

Add methods to handle settings:
```php
public function register_settings() {
    register_setting('oms_options', 'oms_scan_schedule');
    register_setting('oms_options', 'oms_auto_quarantine');
    register_setting('oms_options', 'oms_email_notifications');
    
    add_settings_section(
        'oms_main_section',
        __('Scanner Settings', 'obfuscated-malware-scanner'),
        array($this, 'render_main_section'),
        'oms_options'
    );
}

public function render_main_section() {
    echo '<p>' . __('Configure automatic malware scanning and cleanup.', 'obfuscated-malware-scanner') . '</p>';
}
```

### 9. Implement Scan Action Handler
**File:** `admin/class-oms-admin.php`

Add method to handle manual scan:
```php
public function handle_manual_scan() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'obfuscated-malware-scanner'));
    }
    
    check_admin_referer('oms_manual_scan');
    
    $scanner = new Obfuscated_Malware_Scanner();
    $scanner->runFullCleanup();
    
    update_option('oms_last_scan', current_time('mysql'));
    
    wp_redirect(admin_url('options-general.php?page=obfuscated-malware-scanner&scan=complete'));
    exit;
}
```

## 🔵 LOW PRIORITY (Code Quality)

### 10. Add PHPDoc Blocks
Add documentation for all public methods following WordPress standards:

```php
/**
 * Scan a file for malware patterns.
 *
 * @since 1.0.0
 * @param string $path Full path to the file to scan.
 * @return bool True if malware detected, false otherwise.
 * @throws OMS_Exception If file cannot be read.
 */
public function containsMalware($path) {
    // ...
}
```

### 11. Standardize Method Naming
Choose one convention:
- **Option A (WordPress):** `snake_case` for all methods
- **Option B (Modern PHP):** `camelCase` for all methods

**Recommendation:** Use `camelCase` since you're already using it for class names, and it's more consistent with modern PHP.

### 12. Add Unit Tests for Critical Methods
Priority test coverage:
- `containsMalware()` - with various malware patterns
- `validateFile()` - edge cases
- `quarantineFile()` - permission scenarios
- `getCoreChecksums()` - API failures

## Implementation Order

1. **Phase 1 (Required for basic functionality):**
   - Add `get_status()` method
   - Initialize all properties in constructor
   - Fix undefined `$content` variable

2. **Phase 2 (Required for production):**
   - Remove unused PSpell import
   - Fix return type inconsistencies
   - Standardize naming conventions

3. **Phase 3 (Required for user features):**
   - Implement activation hook
   - Add WordPress Settings API
   - Implement manual scan handler

4. **Phase 4 (Polish):**
   - Add PHPDoc blocks
   - Write unit tests
   - Complete documentation

## Testing Commands

After implementing fixes:

```bash
# Run linting
composer phpcs

# Run static analysis
composer phpstan

# Run tests
composer test

# Full check
composer check
```
