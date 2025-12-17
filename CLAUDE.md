# WordPress Security Plugin - Obfuscated Malware Scanner

A WordPress plugin for real-time malware prevention, detection, and cleanup.

## Project Overview

This plugin provides comprehensive security scanning for WordPress installations:
- Real-time malware detection with pattern matching
- Core file integrity verification against WordPress.org checksums
- Database scanning for malicious content
- File permission monitoring and correction
- Quarantine management for suspicious files
- Upload validation and sanitization

## Tech Stack

- **Language**: PHP 8.3+
- **Framework**: WordPress 6.4+
- **Testing**: PHPUnit, Codeception
- **Code Quality**: PHPCS (WordPress Coding Standards), Psalm, PHPStan
- **Package Management**: Composer

## Dependencies

### Runtime Requirements
- PHP >= 8.3
- PHP Extensions: json, pcre, mbstring, xml, curl, zip, gd, mysql
- WordPress >= 6.4

### Development Tools
- Composer (package management)
- PHPCS with WordPress Coding Standards
- PHPUnit for unit testing
- Codeception for integration/acceptance testing
- Psalm & PHPStan for static analysis

## Setup

### Initial Setup
```bash
# Install dependencies
composer install

# Run tests
composer test

# Run linters
composer lint
```

### Available Composer Scripts
- `composer test` - Run PHPUnit tests
- `composer test:all` - Run all tests (PHPUnit + Codeception)
- `composer phpcs` - Check code standards
- `composer phpcbf` - Auto-fix code standards
- `composer psalm` - Run Psalm static analysis
- `composer phpstan` - Run PHPStan analysis
- `composer lint` - Run all linters
- `composer check` - Run linters and tests

## Project Structure

```
wp-security/
├── admin/                  # Admin interface classes
│   └── class-oms-admin.php
├── includes/               # Core plugin classes
│   ├── class-obfuscated-malware-scanner.php  # Main scanner
│   ├── class-oms-config.php                   # Configuration
│   ├── class-oms-logger.php                   # Logging
│   ├── class-oms-database-scanner.php         # DB scanning
│   ├── class-oms-core-integrity-checker.php   # Core verification
│   ├── class-oms-quarantine-manager.php       # Quarantine handling
│   └── ...
├── tests/                  # PHPUnit & Codeception tests
├── vendor/                 # Composer dependencies
├── .claude/                # Claude Code configuration
│   ├── session-start.sh   # SessionStart hook
│   └── settings.json      # Hook configuration
├── composer.json          # PHP dependencies
├── phpcs.xml             # Code standards config
├── psalm.xml             # Psalm config
└── phpunit.xml           # PHPUnit config
```

## Key Classes

### Core Scanner
- `Obfuscated_Malware_Scanner` - Main scanner orchestration
- `OMS_Core_Integrity_Checker` - WordPress core file verification
- `OMS_Database_Scanner` - Database malware scanning
- `OMS_Quarantine_Manager` - Quarantine file management

### Security
- `OMS_File_Security_Policy` - File validation policies
- `OMS_Utils` - Security utilities (path sanitization, validation)

### Infrastructure
- `OMS_Logger` - Logging system
- `OMS_Cache` - Caching layer
- `OMS_Rate_Limiter` - Rate limiting for scans
- `OMS_Filesystem` - File system operations

## Security Considerations

This is a **security scanning tool** - the codebase intentionally contains:
- Malware pattern definitions (for detection, not execution)
- Security vulnerability analysis
- Attack pattern documentation

**Important**:
- All malware patterns are for detection only
- No malicious code is executed
- Files are scanned, not run
- Quarantined files are isolated and logged

## Development Workflow

1. **Make changes** to code
2. **Run linters**: `composer lint:fix` to auto-fix style issues
3. **Run tests**: `composer test:all` to verify functionality
4. **Check static analysis**: `composer psalm` and `composer phpstan`
5. **Commit** with descriptive messages

## Testing

### Unit Tests
```bash
composer test:unit
```

### Codeception Tests
```bash
composer test:codeception
```

### All Tests
```bash
composer test:all
```

## Code Standards

The project follows **WordPress Coding Standards** (WPCS):
- WordPress-Core ruleset
- Custom exclusions in `phpcs.xml`
- Auto-fixing available via `composer phpcbf`

## Environment Variables

None required for basic operation. Optional:
- `WP_DEBUG` - Enable WordPress debug mode
- `OMS_NOTIFY_ADMIN` - Enable admin email notifications

## Common Tasks

### Add a new malware pattern
Edit `includes/class-oms-config.php` and add to `MALWARE_PATTERNS` or `OBFUSCATION_PATTERNS`.

### Modify scan configuration
Edit `includes/class-oms-config.php` `SCAN_CONFIG` section.

### Add new security check
1. Create method in `Obfuscated_Malware_Scanner` class
2. Call from `run_full_cleanup()` or appropriate hook
3. Add tests in `tests/` directory

### Debug issues
- Check logs in `wp-content/uploads/oms-logs/`
- Enable `WP_DEBUG` for verbose output
- Review `get_status()` method output

## Known Issues

- Large file scanning can be memory-intensive
- Database scans require manual trigger for cleanup
- Core file repair is logged but not auto-executed (intentional)

## CI/CD

The project includes GitHub Actions workflows for:
- Code quality checks (PHPCS, Psalm, PHPStan)
- Test execution
- WordPress compatibility verification

## License

GPL v2 or later
