Here’s an AI-friendly edited version of your document, designed to help guide AI systems to build the plugin in line with your expectations. This version uses clear prompts, actionable tasks, and explicit structure that AI can easily interpret and implement.

WordPress Security Plugin Instructions for AI

Core Goals
• Build a zero-maintenance WordPress security plugin.
• Ensure compatibility with shared hosting environments.
• Automatically handle malware detection, recovery, and content preservation.
• Focus on multi-site WordPress installations.
• Prevent data loss and maintain custom content (e.g., Elementor/Astra themes).

Implementation Requirements

File Operations
• Scanning:
• Use chunk-based file scanning with overlap logic to detect patterns spanning chunks.
• Identify and remove confirmed suspicious files that are not known wordpress core, plugin core, or site content files.
• Preserve and ignore legitimate zero-byte files (index.php, .htaccess, .gitkeep).
• Verification:
• Compare WordPress core files to official checksums.
• Automatically download and restore corrupted core files.
• Batch Processing:
• Optimize for shared hosting by scanning files in batches.
• Pause processing when memory or CPU usage exceeds thresholds.

Database Security
• Verification:
• Check for structural integrity of the database (e.g., missing tables, incorrect indexes).
• Preservation:
• Backup critical tables before applying fixes.
• Protect custom data created by themes, plugins, or page builders.
• Threat Detection:
• Scan database content for malicious injections.

Custom Content Protection
• Preserve theme and plugin customizations, such as:
• Custom CSS and templates.
• Elementor/page builder settings.
• Safeguard user-uploaded files from false positives during scanning.

Error Prevention
• Never suppress errors; always log them with context (e.g., file paths, error type, stack traces).
• Provide automatic recovery mechanisms, such as:
• Reverting changes if corruption is detected.
• Re-downloading files when verification fails.

Development Guidelines

Code Quality
• Avoid undefined functions, constants, or configurations.
• Handle all errors explicitly; no silent failures.
• Ensure complete input validation (e.g., sanitize paths, validate regex patterns).

Complexity Management
• Avoid unnecessary abstractions or new files unless required for clarity.
• Use lightweight utilities to manage shared functionality.
• Do not introduce framework dependencies or complex architectures.

Performance
• Optimize memory usage for shared hosting limits (target <50MB for most operations).
• Use caching to store reusable data like compiled patterns and checksums.

Testing
• Write tests to cover:
• Edge cases (e.g., large uploads, rare malware patterns, corrupted files).
• Multi-site scenarios (e.g., testing with 5 sites).
• Custom content preservation.

Checklist

Must Have Features 1. Malware pattern detection:
• Use regex-based patterns for scanning.
• Ensure chunk overlap for boundary-spanning patterns. 2. Zero-byte file handling:
• Identify legitimate files (index.php, .gitkeep) vs. suspicious ones. 3. Core file verification:
• Compare files against version-specific checksums.
• Automatically repair corrupted files. 4. Detailed error logging:
• Log critical information, such as file paths, error codes, and stack traces. 5. Multi-site support:
• Efficiently scan files across multiple installations.
• Minimize resource usage for shared hosting. 6. Recovery mechanisms:
• Restore critical files or data if issues are detected.
• Quarantine suspicious files for review.

Must Not Have
• Framework dependencies.
• Suppressed errors or silent failures.
• High memory usage (>50MB for shared hosting).
• Complex abstractions or unnecessary files.

Features

File System Security 1. Core File Protection:
• Verify WordPress core files against checksums.
• Automatically repair or replace corrupted files. 2. Malware Detection:
• Scan files for malware patterns using regex.
• Handle chunk overlaps during scanning.
• Identify obfuscated code or zero-byte files. 3. Custom Content Protection:
• Preserve theme/plugin customizations (e.g., Elementor content).
• Safeguard uploaded files.

Database Security 1. Integrity Checks:
• Verify table structures and indexes.
• Detect and resolve common issues. 2. Backup and Restore:
• Backup critical tables before applying fixes.
• Provide automatic rollback on failure. 3. Content Protection:
• Detect malicious database injections.
• Preserve user-generated content.

Architecture

Core Components 1. Scanner:
• Handles file system operations (e.g., scanning, pattern matching, validation).
• Verifies WordPress core files against checksums. 2. Security Policy:
• Defines rules for validating files and database content.
• Provides recovery procedures for corrupted files. 3. Plugin Core:
• Coordinates plugin initialization and feature integration.
• Supports multi-site functionality. 4. Utilities:
• Contains reusable helpers (e.g., file path sanitization, checksum utilities). 5. Cache:
• Stores compiled patterns, checksum data, and temporary results. 6. Logger:
• Logs detailed error, warning, and info messages.

Implementation Strategy

Phase 1: Core Security
• Goal: Implement basic malware detection and core file verification.
• Deliverables:
• Regex-based pattern matching with chunk overlap.
• WordPress core file verification using checksums.
• File quarantine system.

Phase 2: Enhanced Protection
• Goal: Add database security and custom content preservation.
• Deliverables:
• Database integrity checks.
• Protection for theme and plugin customizations.
• Safe handling of Elementor/page builder content.

Phase 3: Multi-Site Support
• Goal: Optimize resource usage for multi-site installations.
• Deliverables:
• Scanning support for multiple WordPress installations.
• Resource-aware batch processing.

Phase 4: Automation and Maintenance
• Goal: Automate updates and recovery for zero maintenance.
• Deliverables:
• Self-healing mechanisms for corrupted installations.
• Automatic pattern and configuration updates.

Error Handling and Logging
• Detection:
• Capture all errors, including stack traces and context.
• Recovery:
• Retry failed operations automatically.
• Provide fallbacks to safe states where possible.
• Logging:
• Use structured log entries with details like timestamps, file paths, and error codes.

How AI Should Build This 1. Start with Phase 1:
• Implement a basic scanner with regex-based malware detection and WordPress core file verification.
• Ensure the scanner handles chunk overlaps and logs all critical operations. 2. Incrementally Add Features:
• Add database security and content preservation in separate steps.
• Test each feature thoroughly before moving to the next. 3. Focus on Resource Efficiency:
• Use caching for compiled patterns and reuse results where possible.
• Write batch-processing logic to minimize memory and CPU usage. 4. Follow the Checklist:
• Ensure that all Must Have features are implemented and tested.
• Avoid introducing anything listed under Must Not Have.
