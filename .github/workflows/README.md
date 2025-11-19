# GitHub Actions Workflows

This directory contains all GitHub Actions workflow files for the Obfuscated Malware Scanner plugin.

## Workflow Files

1. **codacy.yml** - Codacy Security Scan
   - Runs Codacy Analysis CLI for security scanning
   - Uploads SARIF results to GitHub Security

2. **codeql.yml** - CodeQL Analysis
   - Performs CodeQL security analysis for PHP
   - Runs on push, PR, schedule (weekly), and manual dispatch

3. **phpcs.yml** - PHPCS WordPress Coding Standards
   - Runs WordPress Coding Standards checks
   - Generates JSON report and uploads as artifact

4. **phpmd.yml** - PHPMD (PHP Mess Detector)
   - Runs PHPMD for code quality analysis
   - Uploads SARIF results to GitHub Security

5. **plugin-check.yml** - WordPress Plugin Check
   - Runs WordPress Plugin Check action
   - Checks security, compatibility, and performance

6. **psalm.yml** - Psalm Security Scan
   - Runs Psalm security scanning
   - Uploads SARIF results to GitHub Security

## No Duplicates

All workflow files are unique and serve different purposes:
- ✅ codacy.yml - Unique (Codacy scanning)
- ✅ codeql.yml - Unique (CodeQL analysis)
- ✅ phpcs.yml - Unique (WordPress coding standards)
- ✅ phpmd.yml - Unique (PHP mess detector)
- ✅ plugin-check.yml - Unique (WordPress plugin check)
- ✅ psalm.yml - Unique (Psalm security scan)

## Common Configuration

All workflows share:
- PHP 8.1
- Extensions: mbstring, xml, curl, json
- Concurrency control to prevent simultaneous runs
- Timeout settings (10-30 minutes)
- Proper permissions configuration
