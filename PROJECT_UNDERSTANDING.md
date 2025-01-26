# Project Understanding Document

## Project Overview
The Obfuscated Malware Scanner is a WordPress plugin designed for automated maintenance and security of WordPress sites, particularly focused on shared hosting environments. The plugin aims to reduce manual intervention by automatically handling security issues, file integrity, and content preservation.

## Core Components

### 1. Main Plugin Structure
- Entry Point: `obfuscated-malware-scanner.php`
- Core Scanner: `class-obfuscated-malware-scanner.php`
- Configuration: `class-oms-config.php`
- Caching: `class-oms-cache.php`
- Plugin Management: `class-oms-plugin.php`

### 2. Key Features
- Real-time malware scanning
- WordPress core file integrity checking
- Custom content preservation (especially for Elementor and Astra themes)
- Automated cleanup of suspicious files
- Database content scanning
- File permission management

### 3. Known Issues and Considerations
- Potential race conditions in cleanup operations
- File handling security concerns requiring attention
- Resource management for large file scanning
- External API dependencies (WordPress.org)
- Input validation improvements needed

## Project File Hierarchy and Components

### Core Plugin Files

```
obfuscated-malware-scanner/
├── obfuscated-malware-scanner.php       # Plugin entry point and initialization
├── class-obfuscated-malware-scanner.php # Main scanner implementation
├── class-oms-plugin.php                 # Plugin lifecycle management
├── class-oms-config.php                 # Configuration management
├── class-oms-cache.php                  # Caching implementation
├── class-oms-utils.php                  # Utility functions
├── class-oms-logger.php                 # Logging functionality
├── class-oms-exception.php              # Custom exception handling
├── class-oms-rate-limiter.php          # Rate limiting functionality
├── tests/                               # Test suite directory
├── review/                              # Code review documentation
└── vendor/                              # Dependencies
```

### Component Descriptions

#### Core Components

1. **Plugin Entry Point (`obfuscated-malware-scanner.php`)**
   - Defines plugin metadata and constants
   - Handles plugin initialization
   - Manages dependencies and file loading
   - Sets up WordPress hooks and filters

2. **Main Scanner (`class-obfuscated-malware-scanner.php**)**
   - Implements core scanning functionality
   - Handles file analysis and malware detection
   - Manages file cleanup operations
   - Coordinates with WordPress core file verification
   - Preserves custom content during operations

3. **Plugin Management (`class-oms-plugin.php`)**
   - Manages plugin lifecycle (activation, deactivation)
   - Handles WordPress integration
   - Coordinates component initialization
   - Manages plugin hooks and filters

#### Support Components

4. **Configuration (`class-oms-config.php`)**
   - Manages plugin settings and options
   - Handles configuration validation
   - Provides default configurations
   - Manages environment-specific settings

5. **Cache Management (`class-oms-cache.php`)**
   - Implements caching mechanisms
   - Manages scan results caching
   - Handles cache invalidation
   - Optimizes performance for shared hosting

6. **Utilities (`class-oms-utils.php`)**
   - Provides common utility functions
   - Handles path sanitization
   - Implements file system operations
   - Manages security-related utilities

7. **Logging (`class-oms-logger.php`)**
   - Implements logging functionality
   - Manages log rotation
   - Handles different log levels
   - Provides debugging capabilities

8. **Exception Handling (`class-oms-exception.php`)**
   - Defines custom exception types
   - Manages error handling
   - Provides structured error information
   - Ensures proper error reporting

9. **Rate Limiting (`class-oms-rate-limiter.php`)**
   - Implements rate limiting functionality
   - Prevents resource exhaustion
   - Manages concurrent operations
   - Handles shared hosting constraints

### Component Interactions

- The main scanner class (`Obfuscated_Malware_Scanner`) is the central component that coordinates with other components
- Configuration (`OMS_Config`) provides settings used by all components
- Cache (`OMS_Cache`) optimizes performance across operations
- Logger (`OMS_Logger`) maintains operation history and debugging information
- Utils (`OMS_Utils`) provides shared functionality across components
- Rate Limiter (`OMS_Rate_Limiter`) ensures resource-friendly operation
- Exception handling (`OMS_Exception`) provides structured error management

### Design Principles

1. **Modularity**
   - Each component has a single responsibility
   - Components communicate through well-defined interfaces
   - Easy to maintain and extend

2. **Performance**
   - Caching for resource-intensive operations
   - Rate limiting for shared hosting environments
   - Optimized file operations

3. **Security**
   - Input validation at all entry points
   - Secure file handling
   - WordPress core file verification
   - Custom content preservation

4. **Reliability**
   - Exception handling for all operations
   - Logging for debugging and auditing
   - Configuration validation
   - Resource usage management

## Prioritized Action Items

### High Priority Tasks

1. **Improve File Scanning Performance on Shared Hosting**
   - Optimize chunk size for malware scanning
   - Implement smarter file traversal
   - Reduce memory usage during scans
   
2. **Enhance Zero-byte File Detection and Cleanup**
   - Add dedicated zero-byte file detection
   - Implement safe removal process
   - Track cleanup operations
   
3. **Strengthen Custom Content Preservation**
   - Improve theme/plugin file detection
   - Enhanced Elementor content handling
   - Better Astra theme preservation

### Medium Priority Tasks

1. **File Content Validation**
   - Validate file content against extensions
   - Detect obfuscated PHP in media files
   - Implement MIME type checking

2. **API Response Caching**
   - Cache WordPress.org API responses
   - Implement fallback mechanisms
   - Optimize cache invalidation

3. **Error Handling Improvements**
   - Enhanced operation logging
   - Clear error messaging
   - Better debugging support

### Low Priority Tasks

1. **Race Condition Handling**
   - Basic locking mechanisms
   - Operation queuing

2. **Exception Handling**
   - Specific exception types
   - Targeted recovery procedures

### Items to Ignore

1. Broad permission checking scope
2. Complex auth systems
3. Extensive logging mechanisms

## Implementation Plan: File Scanning Performance

### Task Breakdown

1. **Chunk Size Optimization**
   - Current Issue: Fixed chunk size may not be optimal for all environments
   - Solution Components:
     - Dynamic chunk size calculation based on memory limits
     - Progressive scanning for large files
     - Memory usage monitoring
   
2. **Smart File Traversal**
   - Current Issue: Recursive iteration can be resource-intensive
   - Solution Components:
     - Priority-based file scanning
     - Skip known-good directories
     - Batch processing with pauses
   
3. **Memory Management**
   - Current Issue: Large files can exhaust memory
   - Solution Components:
     - Stream-based file reading
     - Garbage collection optimization
     - Memory limit enforcement

### Implementation Steps

1. **Phase 1: Chunk Size Optimization**
   ```php
   class OMS_Scanner {
       private function calculate_optimal_chunk_size() {
           // Get memory limit
           // Calculate available memory
           // Set chunk size based on available memory
           // Implement progressive scanning for large files
       }
       
       private function scan_file_with_optimal_chunks() {
           // Use calculated chunk size
           // Monitor memory usage
           // Adjust chunk size if needed
       }
   }
   ```

2. **Phase 2: Smart Traversal**
   ```php
   class OMS_Scanner {
       private function get_scan_priority($file) {
           // Check file type
           // Check last modification time
           // Check location in directory structure
       }
       
       private function batch_process_files() {
           // Group files by priority
           // Process in batches
           // Implement pauses between batches
       }
   }
   ```

3. **Phase 3: Memory Management**
   ```php
   class OMS_Scanner {
       private function stream_read_file($file) {
           // Use streams for file reading
           // Monitor memory usage
           // Implement cleanup after each read
       }
   }
   ```

### Success Metrics

1. **Performance**
   - 50% reduction in scan time
   - 30% reduction in memory usage
   - No timeouts on shared hosting

2. **Reliability**
   - Zero memory exhaustion errors
   - Complete scans without interruption
   - Consistent performance across different file sizes

3. **Resource Usage**
   - CPU usage below hosting limits
   - Memory usage within allocated limits
   - No impact on site responsiveness

## Current State and Direction

### Immediate Goals
1. Maintain WordPress sites without constant supervision
2. Handle obfuscated code detection and removal
3. Manage zero-byte file issues
4. Monitor MySQL database health
5. Preserve legitimate custom content

### Areas Needing Attention
1. Security improvements in file handling
2. Resource optimization for shared hosting environments
3. Better error handling and logging
4. Enhanced input validation
5. Race condition mitigation

## Development Guidelines
1. Focus on minimum viable product with each change
2. Maintain existing functionality unless clear reason for change
3. Ensure all code is tested and working
4. No undefined functions or constants
5. Preserve custom content during operations
6. Never suppress errors

## Testing and Validation
- PHPUnit tests available in `/tests` directory
- Security reviews maintained in `/review` directory
- Code review process documented in `code-review.csv`

## Dependencies
- WordPress core
- PHP requirements (version specified in composer.json)
- External WordPress.org API for core file verification
