# Implementation Plan - Database Security Module

## Goal
Implement a robust database security module that scans for malicious content, backs up critical tables, and allows for safe cleanup of infected data.

## Components

### 1. Database Scanner (`OMS_Database_Scanner`)
- **Status**: Implemented & Tested.
- **Features**:
    - Scans critical tables (`posts`, `options`, `users`, etc.).
    - Checks for malicious patterns (eval, base64, etc.).
    - Verifies database integrity (missing tables/columns).
    - Detects suspicious modifications (admin users, options).

### 2. Database Backup (`OMS_Database_Backup`)
- **Status**: Pending Implementation.
- **Requirements**:
    - Backup specific tables before any cleanup operation.
    - Store backups securely (not web-accessible).
    - Restore functionality (rollback).
    - Pruning of old backups.

### 3. Database Cleanup
- **Status**: Partially implemented in Scanner.
- **Requirements**:
    - Targeted removal of malicious content.
    - Safe-guard against breaking site functionality.
    - Logging of all cleanup actions.

## Step-by-Step Implementation

1.  **Refine `OMS_Database_Backup`**:
    - Implement `backup_table($table_name)`.
    - Implement `restore_table($table_name, $backup_file)`.
    - Implement `get_backups()` and `delete_backup($id)`.

2.  **Enhance `OMS_Database_Scanner`**:
    - Ensure `clean_database_content` utilizes the backup system correctly.
    - Add more sophisticated patterns for database-specific malware (e.g., spam injections).

3.  **Integration**:
    - Hook into the main `Obfuscated_Malware_Scanner` class.
    - Add CLI commands for database scanning (optional but good for testing).
    - Add Admin UI for viewing database scan results.

4.  **Testing**:
    - Unit tests for Backup and Restore.
    - Integration tests with mock database data.

## Verification Plan
- Run `phpunit` to verify all components.
- Manually trigger scans with seeded malicious data in a test database.
- Verify backup creation and restoration success.
