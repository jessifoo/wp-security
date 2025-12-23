# GitHub Actions Workflows

This directory contains all GitHub Actions workflow files for the Obfuscated Malware Scanner plugin.

## Workflow Files

1. **codeql.yml** - CodeQL Analysis (Disabled)
   - ⚠️ CodeQL does not support PHP
   - CodeQL supports: JavaScript/TypeScript, Python, Java/Kotlin, C/C++, C#, Go, Ruby, Swift
   - Workflow is disabled (manual dispatch only) with informational message
   - For PHP security analysis, use: psalm.yml, phpmd.yml, or plugin-check.yml

2. **phpcs.yml** - PHPCS WordPress Coding Standards
   - Runs WordPress Coding Standards checks
   - Generates JSON report and uploads as artifact

3. **phpmd.yml** - PHPMD (PHP Mess Detector)
   - Runs PHPMD for code quality analysis
   - Uploads SARIF results to GitHub Security

4. **plugin-check.yml** - WordPress Plugin Check
   - Runs WordPress Plugin Check action
   - Checks security, compatibility, and performance

5. **psalm.yml** - Psalm Security Scan
   - Runs Psalm security scanning
   - Uploads SARIF results to GitHub Security

## Active Workflows

All workflow files are unique and serve different purposes:
- ✅ codeql.yml - Disabled (CodeQL doesn't support PHP)
- ✅ phpcs.yml - WordPress coding standards
- ✅ phpmd.yml - PHP mess detector
- ✅ plugin-check.yml - WordPress plugin check
- ✅ psalm.yml - Psalm security scan

## Common Configuration

All workflows share:
- PHP 8.3
- Extensions: mbstring, xml, curl, json
- Concurrency control to prevent simultaneous runs
- Timeout settings (10-30 minutes)
- Proper permissions configuration
