<?php

/**
 * Scanner class for malware detection
 *
 * Handles file scanning and pattern matching functionality.
 * Part of the refactoring effort to improve code organization and maintainability.
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die('Direct access is not allowed.');
}

/**
 * Scanner class responsible for file analysis and malware detection
 */
class OMS_Scanner
{
    /**
     * @var OMS_Logger Logger instance
     */
    private $logger;

    /**
     * @var OMS_Rate_Limiter Rate limiter instance
     */
    private $rate_limiter;

    /**
     * @var array Compiled malware patterns
     */
    private $compiled_patterns;

    /**
     * @var OMS_Cache Cache instance
     */
    private $cache;

    /**
     * Constructor
     *
     * @param OMS_Logger $logger Logger instance
     * @param OMS_Rate_Limiter $rate_limiter Rate limiter instance
     * @param OMS_Cache $cache Cache instance
     */
    public function __construct(OMS_Logger $logger, OMS_Rate_Limiter $rate_limiter, OMS_Cache $cache)
    {
        $this->logger = $logger;
        $this->rate_limiter = $rate_limiter;
        $this->cache = $cache;
        $this->compiled_patterns = $this->compile_patterns();
    }

    /**
     * Compile malware detection patterns
     *
     * @return array Array of compiled patterns
     */
    private function compile_patterns()
    {
        // Check if patterns are already cached
        $cached_patterns = $this->cache->get('compiled_malware_patterns');
        if ($cached_patterns) {
            return $cached_patterns;
        }

        $patterns = array();
        foreach (OMS_Config::MALWARE_PATTERNS as $name => $pattern) {
            try {
                // Validate and compile each pattern
                if (@preg_match($pattern, '') === false) {
                    $this->logger->error("Invalid malware pattern", array(
                        'pattern_name' => $name,
                        'pattern' => $pattern
                    ));
                    continue;
                }
                $patterns[$name] = $pattern;
            } catch (Exception $e) {
                $this->logger->error("Failed to compile pattern", array(
                    'pattern_name' => $name,
                    'error' => $e->getMessage()
                ));
            }
        }

        // Cache the compiled patterns
        $this->cache->set('compiled_malware_patterns', $patterns, OMS_Config::CACHE_CONFIG['ttl']);
        return $patterns;
    }

    /**
     * Calculate optimal chunk size based on available memory
     *
     * @param int $filesize Size of the file being scanned
     * @return int Optimal chunk size in bytes
     */
    private function calculate_optimal_chunk_size($filesize)
    {
        $memory_limit = $this->get_memory_limit();
        $available_memory = $memory_limit * (float)rtrim(OMS_Config::SCAN_CONFIG['memory_limit'], '%') / 100;

        $chunk_size = min(
            max(
                OMS_Config::SCAN_CONFIG['min_chunk_size'],
                (int)($available_memory / 4)
            ),
            OMS_Config::SCAN_CONFIG['max_chunk_size'],
            $filesize
        );

        return $chunk_size;
    }

    /**
     * Get PHP memory limit in bytes
     *
     * @return int Memory limit in bytes
     */
    private function get_memory_limit()
    {
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($memory_limit, -1));
        $bytes = (int)$memory_limit;

        switch ($unit) {
            case 'g':
                $bytes *= 1024;
            case 'm':
                $bytes *= 1024;
            case 'k':
                $bytes *= 1024;
        }

        return $bytes;
    }

    /**
     * Check if a file contains malware patterns
     *
     * @param string $path Path to file to check
     * @return bool True if malware detected, false otherwise
     * @throws OMS_Exception If file cannot be read or processed
     */
    public function contains_malware($path)
    {
        if (!file_exists($path)) {
            $this->logger->error("File not found for malware scan", ['path' => $path]);
            return false;
        }

        try {
            $filesize = filesize($path);
            if ($filesize === 0) {
                $this->logger->warning("Empty file detected", ['path' => $path]);
                return false;
            }

            $chunk_size = $this->calculate_optimal_chunk_size($filesize);
            return $this->scanFileChunks($path, $chunk_size);
        } catch (Exception $e) {
            $this->logger->error("Failed to scan file for malware", [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Scan file in chunks for malware patterns
     *
     * @param string $path Path to file
     * @param int $chunk_size Size of chunks to read
     * @return bool True if malware detected
     * @throws OMS_Exception If file cannot be read
     */
    private function scanFileChunks($path, $chunkSize)
    {
        $fp = fopen($path, 'rb');
        if (!$fp) {
            $this->logger->error("Unable to open file", ['path' => $path]);
            return false;
        }

        try {
            $content = '';
            while (!feof($fp)) {
                $chunk = fread($fp, $chunkSize);
                if ($chunk === false) {
                    $this->logger->error("Failed to read file chunk", ['path' => $path]);
                    return false;
                }

                // Keep previous overlap and append new chunk
                $content = substr($content, -OMS_Config::SCAN_CONFIG['overlap_size']) . $chunk;

                if ($this->match_patterns($content, $path, ftell($fp))) {
                    return true;
                }

                // Trim content for memory optimization
                if (strlen($content) > $chunkSize * 2) {
                    $content = substr($content, -$chunkSize);
                }
            }
            return false;
        } finally {
            fclose($fp);
        }
    }

    /**
     * Read a chunk from file with error handling
     *
     * @param resource $fp File pointer
     * @param int $chunk_size Size of chunk to read
     * @return array Array containing [chunk content, bytes read]
     * @throws OMS_Exception If read fails
     */
    private function read_chunk($fp, $chunk_size)
    {
        $chunk = fread($fp, $chunk_size);
        if ($chunk === false) {
            throw new OMS_Exception("Failed to read file chunk");
        }
        return [$chunk, strlen($chunk)];
    }

    /**
     * Maintain overlap buffer for pattern matching
     *
     * @param string $content Current content buffer
     * @return string Trimmed content maintaining overlap
     */
    private function maintain_overlap_buffer($content)
    {
        $overlap_size = OMS_Config::SCAN_CONFIG['overlap_size'];
        if (strlen($content) > $overlap_size) {
            return substr($content, -$overlap_size);
        }
        return $content;
    }

    /**
     * Apply rate limiting with configurable threshold
     */
    private function apply_rate_limiting()
    {
        if ($this->rate_limiter->should_throttle('file_scan')) {
            usleep(OMS_Config::SCAN_CONFIG['batch_pause'] * 1000);
        }
    }

    /**
     * Match patterns against content
     *
     * @param string $content Content to check
     * @param string $path File path for logging
     * @param int $position Current position in file
     * @return bool True if match found
     */
    private function match_patterns($content, $path, $position)
    {
        foreach ($this->compiled_patterns as $pattern_name => $pattern) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $this->log_pattern_match($matches, $path, $pattern_name, $position);
                return true;
            }
        }
        return false;
    }

    /**
     * Log pattern match with context
     *
     * @param array $matches Pattern matches
     * @param string $path File path
     * @param string $pattern_name Name of matched pattern
     * @param int $position Current position in file
     */
    private function log_pattern_match($matches, $path, $pattern_name, $position)
    {
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

    /**
     * Extract context around pattern match
     *
     * @param string $content Full content
     * @param int $match_pos Position of match
     * @return string Context around match
     */
    private function extract_match_context($content, $match_pos)
    {
        $context_size = 50; // Characters before and after match
        $start = max(0, $match_pos - $context_size);
        $length = min($context_size * 2, strlen($content) - $start);
        return substr($content, $start, $length);
    }

    /**
     * Check if a file is suspicious based on its characteristics
     *
     * @param string $path File path
     * @param SplFileInfo $file File information
     * @return bool True if file is suspicious
     */
    public function is_file_suspicious($path, SplFileInfo $file)
    {
        try {
            return $this->check_size($path, $file)
                || $this->check_permissions($path, $file)
                || $this->check_modification_time($path, $file);
        } catch (Exception $e) {
            $this->logger->error("Failed to check file suspiciousness", [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return true; // Treat as suspicious if verification fails
        }
    }

    /**
     * Check if file size is suspicious
     *
     * @param string $path File path
     * @param SplFileInfo $file File information
     * @return bool True if size is suspicious
     */
    private function check_size($path, SplFileInfo $file)
    {
        if ($file->getSize() > OMS_Config::MAX_FILE_SIZE) {
            $this->logger->warning("File exceeds maximum size", [
                'path' => $path,
                'size' => $file->getSize(),
            ]);
            return true;
        }
        return false;
    }

    /**
     * Check if file permissions are suspicious
     *
     * @param string $path File path
     * @param SplFileInfo $file File information
     * @return bool True if permissions are suspicious
     */
    private function check_permissions($path, SplFileInfo $file)
    {
        $perms = $file->getPerms();
        if (($perms & 0111) && !$file->isDir()) {
            $this->logger->warning("Suspicious file permissions", [
                'path' => $path,
                'permissions' => substr(sprintf('%o', $perms), -4),
            ]);
            return true;
        }
        return false;
    }

    /**
     * Check if file modification time is suspicious
     *
     * @param string $path File path
     * @param SplFileInfo $file File information
     * @return bool True if modification time is suspicious
     */
    private function check_modification_time($path, SplFileInfo $file)
    {
        $mtime = $file->getMTime();
        if ($mtime > time() || $mtime < strtotime('-1 year')) {
            $this->logger->warning("Suspicious file modification time", [
                'path' => $path,
                'mtime' => date('Y-m-d H:i:s', $mtime),
            ]);
            return true;
        }
        return false;
    }
}
