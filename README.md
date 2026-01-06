# Obfuscated Malware Scanner

## Description
A top of the line WordPress security plugin designed for immediate malware prevention and cleanup, with special handling for Elementor and Astra themes. The plugin:
- Preserves custom theme content during cleanup
- Prevents malicious file uploads
- Monitors and blocks unauthorized file changes
- Immediately removes malicious files
- Repairs WordPress core files without backups
- Cleans database content automatically
- Works independently of WordPress's update system

## Key Features
- Real-time file upload scanning
- Theme content preservation
- File change monitoring using checksums
- Immediate removal of suspicious files
- Direct core file replacement
- Hourly cleanup process
- Database content scanning

## Theme Protection
- Preserves Elementor page builder data
- Maintains Astra theme settings
- Saves custom theme modifications
- Supports theme content export/import
- Restores theme content after cleanup

## Prevention Features
- Blocks dangerous file types
- Prevents upload of malicious files
- Monitors file system changes
- Maintains file permissions
- Verifies file integrity

## Cleanup Features
- Removes malicious files while preserving theme content
- Removes files with malicious content
- Cleans database of malicious content
- Repairs modified core files
- Fixes incorrect permissions

## Theme Content Backup
You can export your theme content to JSON format for backup:
- Elementor page builder data
- Astra theme settings
- Custom theme modifications
- Theme customizer settings

This content can be stored in a GitHub repository and restored when needed.

## Security Checks
The plugin scans for:
- Base64 encoded content
- Eval() and system() calls
- Shell commands
- Remote code execution
- Malicious database content
- Suspicious file types
- Unauthorized file changes

## Installation
1. Upload the plugin files to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically start protecting your site

## Logs
All security actions are logged to `wp-content/malware-scanner.log`

## Author
Your Name
