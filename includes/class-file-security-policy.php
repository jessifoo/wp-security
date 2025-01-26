<?php

/**
 * Security policy for file validation
 */
class OMS_File_Security_Policy {
    /**
     * @var array Allowed MIME types and their corresponding extensions
     */
    private $allowed_types = array(
        'image/jpeg' => array('jpg', 'jpeg'),
        'image/png' => array('png'),
        'image/gif' => array('gif'),
        'application/pdf' => array('pdf'),
        'text/plain' => array('txt'),
    );

    /**
     * @var array List of forbidden file extensions
     */
    private $forbidden_extensions = [
        // PHP variants
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'pht', 'phar', 'inc',
        // Other server-side
        'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp',
        // JavaScript
        'js', 'jsx', 'mjs',
        // Shell scripts
        'sh', 'bash', 'ksh', 'zsh', 'bat', 'cmd',
        // System files
        'htaccess', 'htpasswd', 'ini', 'phps', 'sql',
        // Compressed files that might contain malicious code
        'zip', 'rar', 'tar', 'gz', '7z'
    ];

    /**
     * @var array Paths that should never contain executable files
     */
    private $restricted_paths = array(
        'wp-admin',
        'wp-includes',
        'wp-config.php'
    );

    /**
     * @var array List of protected theme paths
     */
    private $protected_theme_paths = [
        'wp-content/themes/astra',
        'wp-content/plugins/elementor'
    ];

    /**
     * @var array Suspicious file permissions
     */
    private $suspicious_perms = array(
        'executable' => 0111,  // Any execute permission
    );

    /**
     * @var array Suspicious modification times
     */
    private $suspicious_times = array(
        'night_hours' => array(0, 4),  // Suspicious between midnight and 4 AM
    );

    /**
     * @var array Protected paths that should never be modified
     */
    private $protected_paths = array(
        // Elementor
        'wp-content/plugins/elementor',
        'wp-content/plugins/elementor-pro',
        // Astra
        'wp-content/themes/astra',
        'wp-content/plugins/astra-pro-sites',
        // WordPress Core
        'wp-includes',
        'wp-admin',
    );

    /**
     * @var array Paths that require extra scrutiny
     */
    private $sensitive_paths = array(
        'wp-content/uploads',
        'wp-content/cache',
        'wp-content/upgrade',
    );

    /**
     * @var array Known good file patterns (e.g., minified files)
     */
    private $known_good_patterns = array(
        '/\.min\.(js|css)$/',  // Minified assets
        '/elementor.*\.js$/',  // Elementor scripts
        '/astra.*\.js$/',      // Astra scripts
    );

    /**
     * Validate a file against security policy
     *
     * @param string $path Full path to file
     * @param array $options Validation options
     * @return array Validation result with 'valid' boolean and 'reason' string
     * @throws OMS_Security_Exception If validation fails critically
     */
    public function validate_file($path, $options = array()) {
        try {
            // Verify nonce if provided
            if (isset($options['nonce']) && !wp_verify_nonce($options['nonce'], 'oms_file_validation')) {
                throw new OMS_Security_Exception('Invalid security token');
            }

            // Basic file checks
            if (!file_exists($path)) {
                throw new OMS_Security_Exception('File does not exist');
            }

            if (!is_file($path)) {
                return array(
                    'valid' => false,
                    'reason' => 'Not a regular file'
                );
            }

            // Check file size
            if (filesize($path) === 0) {
                return array(
                    'valid' => false,
                    'reason' => 'Zero byte file'
                );
            }

            // Use WordPress file type verification
            $file_type = wp_check_filetype(basename($path));
            if (!$file_type['type']) {
                return array(
                    'valid' => false,
                    'reason' => 'Invalid file type'
                );
            }

            // Check file extension
            $ext = strtolower($file_type['ext']);
            if (in_array($ext, $this->forbidden_extensions)) {
                return array(
                    'valid' => false,
                    'reason' => 'Forbidden file extension'
                );
            }

            // Get relative path from WordPress root
            $relative_path = OMS_Utils::get_relative_path($path);

            // Check if file is in a restricted path
            foreach ($this->restricted_paths as $restricted_path) {
                if (strpos($relative_path, $restricted_path) === 0) {
                    return array(
                        'valid' => false,
                        'reason' => 'File in restricted path'
                    );
                }
            }

            // Check if file is in a protected theme path
            $is_theme_file = false;
            foreach ($this->protected_theme_paths as $theme_path) {
                if (strpos($relative_path, $theme_path) === 0) {
                    $is_theme_file = true;
                    break;
                }
            }

            // Perform content checks
            $content_check = OMS_Utils::check_file_content($path);
            if (!$content_check['safe']) {
                // If it's a theme file, we need to be more careful
                if ($is_theme_file) {
                    // TODO: Implement theme content preservation logic
                    // For now, just log the issue
                    error_log("Potentially malicious content in theme file: $path");
                    return array(
                        'valid' => true,
                        'reason' => 'Theme file with suspicious content - monitoring'
                    );
                }
                return array(
                    'valid' => false,
                    'reason' => $content_check['reason']
                );
            }

            // Check permissions using WordPress functions
            $stat = stat($path);
            if ($stat === false) {
                return array(
                    'valid' => false,
                    'reason' => 'Unable to check file permissions'
                );
            }

            $perms = $stat['mode'] & 0777;
            if ($perms & $this->suspicious_perms['executable']) {
                return array(
                    'valid' => false,
                    'reason' => 'File has executable permissions'
                );
            }

            // Check modification time
            $mod_hour = (int)get_date_from_gmt(date('Y-m-d H:i:s', $stat['mtime']), 'G');
            if ($mod_hour >= $this->suspicious_times['night_hours'][0] && 
                $mod_hour <= $this->suspicious_times['night_hours'][1]) {
                if (!$is_theme_file) {
                    return array(
                        'valid' => false,
                        'reason' => 'File modified during suspicious hours'
                    );
                }
                // For theme files, just log the suspicious modification
                error_log("Theme file modified during suspicious hours: $path");
            }

            // All checks passed
            return array(
                'valid' => true,
                'reason' => 'File passed all security checks'
            );

        } catch (Exception $e) {
            throw new OMS_Security_Exception('File validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if a path is safe (no directory traversal, etc)
     */
    private function is_path_safe($path) {
        // Normalize path
        $path = str_replace('\\', '/', $path);
        
        // Check for directory traversal
        if (strpos($path, '../') !== false || strpos($path, '..\\') !== false) {
            return false;
        }

        // Check for null bytes
        if (strpos($path, "\0") !== false) {
            return false;
        }

        // Check for control characters
        if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a file is in a protected path
     *
     * @param string $file_path File path to check
     * @return bool True if file is in protected path
     */
    public function is_protected_path($file_path) {
        $relative_path = $this->get_relative_path($file_path);
        foreach ($this->protected_paths as $protected_path) {
            if (strpos($relative_path, $protected_path) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a file matches known good patterns
     *
     * @param string $file_path File path to check
     * @return bool True if file matches known good pattern
     */
    public function is_known_good_file($file_path) {
        foreach ($this->known_good_patterns as $pattern) {
            if (preg_match($pattern, $file_path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get relative path from WordPress root
     *
     * @param string $file_path Absolute file path
     * @return string Relative path from WordPress root
     */
    private function get_relative_path($file_path) {
        return str_replace(ABSPATH, '', $file_path);
    }

    /**
     * Add an allowed MIME type
     */
    public function add_allowed_type($mime_type, $extensions) {
        $this->allowed_types[$mime_type] = (array)$extensions;
    }

    /**
     * Add a restricted path
     */
    public function add_restricted_path($path) {
        $this->restricted_paths[] = $path;
    }

    /**
     * Add a forbidden extension
     */
    public function add_forbidden_extension($ext) {
        $this->forbidden_extensions[] = $ext;
    }
}
