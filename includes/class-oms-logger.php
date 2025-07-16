<?php

/**
 * Logger class for the Obfuscated Malware Scanner
 */
class OMS_Logger
{
    /**
     * Log levels
     */
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';

    /**
     * Log file path
     */
    private $log_file;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->log_file = WP_CONTENT_DIR . '/oms-logs/malware-scanner.log';
        $this->init_log_dir();
    }

    /**
     * Initialize log directory
     */
    private function init_log_dir()
    {
        $log_dir = dirname($this->log_file);
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        // Secure the log directory
        $htaccess = $log_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order deny,allow\nDeny from all");
        }
    }

    /**
     * Log an error message
     */
    public function error($message)
    {
        $this->log(self::ERROR, $message);
    }

    /**
     * Log a warning message
     */
    public function warning($message)
    {
        $this->log(self::WARNING, $message);
    }

    /**
     * Log an info message
     */
    public function info($message)
    {
        $this->log(self::INFO, $message);
    }

    /**
     * Log a debug message
     */
    public function debug($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log(self::DEBUG, $message);
        }
    }

    // /**
    //  * Write a message to the log file
    //  */
    // private function log($level, $message)
    // {
    //     if (empty($message)) {
    //         return;
    //     }

    //     $timestamp = current_time('mysql');
    //     $pid = getmypid();
    //     $memory = size_format(memory_get_usage(true));

    //     $log_entry = sprintf(
    //         "[%s] [%s] [PID:%d] [MEM:%s] %s\n",
    //         $timestamp,
    //         $level,
    //         $pid,
    //         $memory,
    //         $message
    //     );

    //     // Rotate log if it's too large
    //     $this->maybe_rotate_log();

    //     // Write to log file
    //     error_log($log_entry, 3, $this->log_file);
    // }

    /**
     * Rotate log file if it's too large
     */
    private function maybe_rotate_log()
    {
        if (!file_exists($this->log_file)) {
            return;
        }

        $max_size = 10 * 1024 * 1024; // 10MB
        if (filesize($this->log_file) < $max_size) {
            return;
        }

        $backup = $this->log_file . '.' . date('Y-m-d-H-i-s');
        rename($this->log_file, $backup);

        // Keep only last 5 backups
        $backups = glob($this->log_file . '.*');
        if (count($backups) > 5) {
            usort($backups, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            $old_backups = array_slice($backups, 5);
            foreach ($old_backups as $old_backup) {
                unlink($old_backup);
            }
        }
    }

    /**
     * Log message.
     */
    public function log($message, $level = 'info')
    {
        $validLevels = array('debug', 'info', 'warning', 'error', 'critical');
        $level = strtolower($level);

        if (!in_array($level, $validLevels)) {
            $level = 'info';
        }

        $timestamp = current_time('mysql');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]) ? $backtrace[1]['function'] : 'unknown';

        $logMessage = sprintf(
            '[%s] [%s] [%s] %s',
            $timestamp,
            strtoupper($level),
            $caller,
            $message
        );

        // Log to WordPress error log for warning and above
        if (in_array($level, array('warning', 'error', 'critical'))) {
            error_log($logMessage);
        }

        // Store in database
        if (function_exists('update_option')) {
            $log_key = 'oms_security_log_' . date('Y-m-d');
            $daily_log = get_option($log_key, array());

            $daily_log[] = array(
                'timestamp' => $timestamp,
                'level' => $level,
                'message' => $message,
                'caller' => $caller
            );

            // Keep only last 1000 entries per day
            if (count($daily_log) > 1000) {
                $daily_log = array_slice($daily_log, -1000);
            }

            update_option($log_key, $daily_log, false);

            // Clean up old logs (keep last 7 days)
            $this->cleanupOldLogs();
        }

        // Write to file if configured
        if (defined('OMS_LOG_FILE') && OMS_Config::LOG_CONFIG) {
            $logFile = WP_CONTENT_DIR . '/oms-logs/security.log';
            $logDir = dirname($logFile);

            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            if (is_writable($logDir)) {
                file_put_contents(
                    $logFile,
                    $logMessage . PHP_EOL,
                    FILE_APPEND | LOCK_EX
                );

                // Rotate log file if it exceeds 5MB
                if (filesize($logFile) > 5 * 1024 * 1024) {
                    $this->rotateLogFile($logFile);
                }
            }
        }
    }

    private function cleanupOldLogs()
    {
        global $wpdb;

        $cutoff_date = date('Y-m-d', strtotime('-7 days'));
        $old_logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options 
				WHERE option_name LIKE 'oms_security_log_%' 
				AND option_name < %s",
                'oms_security_log_' . $cutoff_date
            )
        );

        foreach ($old_logs as $log) {
            delete_option($log->option_name);
        }
    }

    private function rotateLogFile($logFile)
    {
        $maxBackups = 5;

        // Remove oldest backup if exists
        if (file_exists($logFile . '.' . $maxBackups)) {
            unlink($logFile . '.' . $maxBackups);
        }

        // Rotate existing backups
        for ($i = $maxBackups - 1; $i >= 1; $i--) {
            $oldFile = $logFile . '.' . $i;
            $newFile = $logFile . '.' . ($i + 1);
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }

        // Rotate current log file
        rename($logFile, $logFile . '.1');

        // Create new empty log file
        touch($logFile);
        chmod($logFile, 0644);
    }
}
