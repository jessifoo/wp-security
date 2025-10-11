# Autoloading Standards for WordPress Plugin

## Decision: Use Classmap (WordPress Standard), NOT PSR-4

This document formalizes the autoloading strategy for this WordPress plugin and explains why we chose WordPress conventions over PSR-4.

---

## TL;DR

**We use classmap autoloading with WordPress naming conventions because:**
- ✅ It's the WordPress ecosystem standard
- ✅ Better performance for plugins
- ✅ Avoids namespace conflicts
- ✅ Compatible with all WordPress versions
- ✅ Enables lazy loading

---

## Comparison: PSR-4 vs WordPress Classmap

| Aspect | PSR-4 | WordPress Classmap (✅ Our Choice) |
|--------|-------|-----------------------------------|
| **Namespaces** | Required | Optional (we don't use them) |
| **Class Names** | `Vendor\Package\ClassName` | `OMS_Logger`, `OMS_Cache` |
| **File Names** | `ClassName.php` | `class-oms-logger.php` |
| **Directory** | `src/` | `includes/`, `admin/` |
| **Autoload Type** | PSR-4 mapping | Classmap lookup |
| **Performance** | Slight overhead | Direct lookup (faster) |
| **WordPress Standard** | ❌ Uncommon | ✅ Recommended |
| **Lazy Loading** | ✅ Yes | ✅ Yes |
| **Global Scope** | Namespaced | ⚠️ Global (but prefixed) |

---

## Our Implementation

### composer.json
```json
{
    "autoload": {
        "classmap": [
            "includes/",
            "admin/"
        ]
    }
}
```

### File Structure
```
includes/
├── class-oms-config.php           → class OMS_Config
├── class-oms-logger.php           → class OMS_Logger
├── class-oms-cache.php            → class OMS_Cache
├── class-oms-scanner.php          → class OMS_Scanner
├── class-oms-utils.php            → class OMS_Utils
├── class-oms-exception.php        → class OMS_Exception
├── class-oms-rate-limiter.php     → class OMS_Rate_Limiter
├── class-file-security-policy.php → class OMS_File_Security_Policy
└── class-oms-plugin.php           → class OMS_Plugin

admin/
└── class-oms-admin.php            → class OMS_Admin
```

### Naming Conventions

**Class Names:**
- Format: `{Prefix}_{Name}`
- Prefix: `OMS_` (Obfuscated Malware Scanner)
- Examples: `OMS_Logger`, `OMS_Cache`, `OMS_Scanner`
- **Why underscores?** WordPress convention since PHP 5.2 days (before namespaces)

**File Names:**
- Format: `class-{prefix}-{name}.php`
- Lowercase with hyphens (kebab-case)
- Prefix with `class-` for clarity
- Examples: `class-oms-logger.php`, `class-oms-cache.php`
- **Why hyphens?** WordPress coding standards for file readability

---

## Why NOT PSR-4?

### 1. **WordPress Ecosystem Standard**

**WordPress Core Example:**
```php
// WordPress core uses this:
class WP_Query { }           // in class-wp-query.php
class WP_User { }            // in class-wp-user.php
class WP_Post { }            // in class-wp-post.php

// NOT this:
namespace WordPress\Core;
class Query { }              // PSR-4 style
```

**Popular Plugins Use Classmap:**
- WooCommerce: `WC_Product`, `WC_Order`, `WC_Cart`
- Yoast SEO: `WPSEO_Admin`, `WPSEO_Options`
- Contact Form 7: `WPCF7_ContactForm`

### 2. **Namespace Pollution Risk**

**Problem with PSR-4 in WordPress:**
```php
// Your plugin:
namespace MyPlugin;
class Cache { }

// Another plugin also does:
namespace TheirPlugin;
class Cache { }

// WordPress might load either one unpredictably!
// Global namespace conflicts are VERY common
```

**Our Solution:**
```php
// Unique prefix prevents conflicts:
class OMS_Cache { }        // Your plugin
class WC_Cache { }         // WooCommerce
class WPSEO_Cache { }      // Yoast SEO
// No conflicts possible!
```

### 3. **Performance Benefits**

**Classmap Lookup:**
```php
// Composer generates this:
$classMap = [
    'OMS_Logger' => '/path/to/class-oms-logger.php',
    'OMS_Cache'  => '/path/to/class-oms-cache.php',
];

// Direct array lookup = O(1) performance
```

**PSR-4 Lookup:**
```php
// PSR-4 must:
// 1. Parse namespace
// 2. Build file path from class name
// 3. Check if file exists
// 4. Load file
// Slightly slower due to string manipulation
```

### 4. **WordPress Version Compatibility**

**WordPress supports PHP 7.2+ now, but:**
- Many sites still run older PHP versions
- WordPress core doesn't use namespaces
- Mixing namespace styles creates confusion
- Our classmap approach works everywhere

### 5. **Developer Expectations**

**WordPress developers expect:**
```php
// This pattern:
$logger = new OMS_Logger();
$cache = new OMS_Cache();

// NOT this:
use MyPlugin\Utils\Logger;
use MyPlugin\Utils\Cache;
$logger = new Logger();
$cache = new Cache();
```

---

## Lazy Loading is Enabled

**Important:** We removed the `files` array from autoload!

**Before (❌ Wrong):**
```json
{
    "autoload": {
        "classmap": ["includes/", "admin/"],
        "files": [
            "includes/class-oms-config.php",
            "includes/class-oms-logger.php"
        ]
    }
}
```
**Problem:** Forces loading on EVERY request, even if classes aren't used.

**After (✅ Correct):**
```json
{
    "autoload": {
        "classmap": ["includes/", "admin/"]
    }
}
```
**Benefit:** Classes load only when first referenced (lazy loading).

---

## How It Works

### 1. During `composer install` or `composer dump-autoload`:
```bash
$ composer dump-autoload -o
```

Composer scans `includes/` and `admin/` directories and generates:

**vendor/composer/autoload_classmap.php:**
```php
<?php
return array(
    'OMS_Admin' => $baseDir . '/admin/class-oms-admin.php',
    'OMS_Cache' => $baseDir . '/includes/class-oms-cache.php',
    'OMS_Config' => $baseDir . '/includes/class-oms-config.php',
    'OMS_Exception' => $baseDir . '/includes/class-oms-exception.php',
    'OMS_File_Security_Policy' => $baseDir . '/includes/class-file-security-policy.php',
    'OMS_Logger' => $baseDir . '/includes/class-oms-logger.php',
    'OMS_Plugin' => $baseDir . '/includes/class-oms-plugin.php',
    'OMS_Rate_Limiter' => $baseDir . '/includes/class-oms-rate-limiter.php',
    'OMS_Scanner' => $baseDir . '/includes/class-oms-scanner.php',
    'OMS_Utils' => $baseDir . '/includes/class-oms-utils.php',
    'Obfuscated_Malware_Scanner' => $baseDir . '/includes/class-obfuscated-malware-scanner.php',
);
```

### 2. When Code Runs:
```php
// First time OMS_Logger is referenced:
$logger = new OMS_Logger();

// Composer's autoloader:
// 1. Looks up 'OMS_Logger' in classmap
// 2. Finds '/includes/class-oms-logger.php'
// 3. Loads the file (only now!)
// 4. Class is available
```

### 3. Subsequent Uses:
```php
// Already loaded, no file I/O:
$logger2 = new OMS_Logger();  // Instant
```

---

## When Would PSR-4 Be Appropriate?

PSR-4 is excellent for:
- ✅ **Standalone PHP applications** (not WordPress plugins)
- ✅ **Modern PHP libraries** (Symfony, Laravel components)
- ✅ **Microservices** where namespace isolation is critical
- ✅ **API packages** distributed via Packagist

PSR-4 is problematic for:
- ❌ **WordPress plugins** (conflicts with ecosystem standards)
- ❌ **Legacy PHP applications** (PHP <5.3 without namespace support)
- ❌ **Shared hosting environments** (where multiple WordPress sites exist)

---

## Coding Standards

### Class Definition
```php
<?php
/**
 * Logger class for malware scanner.
 *
 * @package ObfuscatedMalwareScanner
 */

// Security check (WordPress standard)
if (!defined('ABSPATH')) {
    die('Direct access is not allowed.');
}

/**
 * Handles logging functionality.
 */
class OMS_Logger {
    
    /**
     * Log an info message.
     *
     * @param string $message Message to log.
     * @return void
     */
    public function info($message) {
        // Implementation
    }
}
```

### Usage in Plugin Files
```php
<?php
/**
 * Plugin entry point.
 *
 * @package ObfuscatedMalwareScanner
 */

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Classes are now available via autoload
$logger = new OMS_Logger();
$cache = new OMS_Cache();
$scanner = new OMS_Scanner($logger);
```

---

## Benefits Summary

### For This Plugin:

1. **Compatibility** ✅
   - Works with all WordPress versions
   - No conflicts with other plugins
   - Follows WordPress coding standards

2. **Performance** ✅
   - Lazy loading (classes load on demand)
   - Fast lookup via array map
   - No runtime string manipulation

3. **Maintainability** ✅
   - Clear, predictable file structure
   - Easy for WordPress developers to understand
   - Consistent with ecosystem expectations

4. **Security** ✅
   - No namespace confusion
   - Explicit class prefixes prevent collisions
   - Works in shared hosting environments

---

## References

- [WordPress Plugin Handbook: Coding Standards](https://developer.wordpress.org/plugins/plugin-basics/best-practices/)
- [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- [Composer: Autoloading Documentation](https://getcomposer.org/doc/04-schema.md#autoload)
- [PHP-FIG PSR-4 Specification](https://www.php-fig.org/psr/psr-4/)

---

## Conclusion

**For WordPress plugins, classmap autoloading with prefixed class names is the right choice.**

PSR-4 is excellent for modern PHP development, but WordPress has its own ecosystem standards that prioritize:
- Backwards compatibility
- Plugin interoperability
- Shared hosting constraints
- Developer expectations

By following WordPress conventions, we ensure our plugin integrates seamlessly with the WordPress ecosystem while maintaining excellent performance and maintainability.
