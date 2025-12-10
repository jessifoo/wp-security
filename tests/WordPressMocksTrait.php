<?php

trait WordPressMocksTrait
{
    protected $wp_root_dir;
    protected $wp_content_dir;
    protected $wp_uploads_dir;

    protected function setup_wordpress_mocks()
    {
        // Set up WordPress root directory
        $this->wp_root_dir = sys_get_temp_dir() . '/wordpress';
        $this->wp_content_dir = $this->wp_root_dir . '/wp-content';
        $this->wp_uploads_dir = $this->wp_content_dir . '/uploads';

        // Create necessary directories
        if (!is_dir($this->wp_uploads_dir)) {
            mkdir($this->wp_uploads_dir, 0777, true);
        }

        // Define WordPress constants if not already defined
        if (!defined('ABSPATH')) {
            define('ABSPATH', $this->wp_root_dir . '/');
        }

        if (!defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', $this->wp_content_dir);
        }

        if (!defined('HOUR_IN_SECONDS')) {
            define('HOUR_IN_SECONDS', 3600);
        }
        if (!defined('MINUTE_IN_SECONDS')) {
            define('MINUTE_IN_SECONDS', 60);
        }
        if (!defined('DAY_IN_SECONDS')) {
            define('DAY_IN_SECONDS', 24 * 3600);
        }
        if (!defined('WEEK_IN_SECONDS')) {
            define('WEEK_IN_SECONDS', 7 * 24 * 3600);
        }
        if (!defined('YEAR_IN_SECONDS')) {
            define('YEAR_IN_SECONDS', 365 * 24 * 3600);
        }

        if (!defined('DB_NAME')) {
            define('DB_NAME', 'wordpress_test');
        }

        if (!defined('ARRAY_A')) {
            define('ARRAY_A', 'ARRAY_A');
        }
        if (!defined('ARRAY_N')) {
            define('ARRAY_N', 'ARRAY_N');
        }
        if (!defined('OBJECT')) {
            define('OBJECT', 'OBJECT');
        }

        // Define global variables for mocks
        global $wp_verify_nonce_mock, $wp_check_filetype_mock;
        $wp_verify_nonce_mock = true;
        $wp_check_filetype_mock = ['type' => 'text/plain', 'ext' => 'txt'];

        // Mock WordPress functions
        if (!function_exists('wp_verify_nonce')) {
            function wp_verify_nonce($nonce, $action = -1) {
                global $wp_verify_nonce_mock;
                return $wp_verify_nonce_mock;
            }
        }

        if (!function_exists('wp_check_filetype')) {
            function wp_check_filetype($filename, $mimes = null) {
                global $wp_check_filetype_mock;
                return $wp_check_filetype_mock;
            }
        }

        if (!function_exists('esc_url_raw')) {
            function esc_url_raw($url, $protocols = null) {
                return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
            }
        }

        if (!function_exists('wp_normalize_path')) {
            function wp_normalize_path($path) {
                $path = str_replace('\\', '/', $path);
                $path = preg_replace('|/+|', '/', $path);
                return $path;
            }
        }

        if (!function_exists('get_date_from_gmt')) {
            function get_date_from_gmt($string, $format = 'Y-m-d H:i:s') {
                return date($format, strtotime($string));
            }
        }

        if (!function_exists('get_transient')) {
            function get_transient($transient) {
                return false;
            }
        }

        if (!function_exists('set_transient')) {
            function set_transient($transient, $value, $expiration = 0) {
                return true;
            }
        }

        if (!function_exists('delete_transient')) {
            function delete_transient($transient) {
                return true;
            }
        }
    }

    protected function teardown_wordpress_mocks()
    {
        // Clean up WordPress directories
        if (is_dir($this->wp_root_dir)) {
            $this->rrmdir($this->wp_root_dir);
        }
    }

    private function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    protected function mockWPUploadDir($basedir)
    {
        global $wp_upload_dir_mock;
        $wp_upload_dir_mock = array('basedir' => $basedir);

        if (!function_exists('wp_upload_dir')) {
            function wp_upload_dir() {
                global $wp_upload_dir_mock;
                return $wp_upload_dir_mock;
            }
        }
    }

    private function mockDeleteAttachment()
    {
        if (!function_exists('wp_delete_attachment')) {
            function wp_delete_attachment($post_id, $force_delete = false) {
                // Track that this function was called for assertions
                if (!isset($GLOBALS['wp_delete_attachment_calls'])) {
                    $GLOBALS['wp_delete_attachment_calls'] = [];
                }
                $GLOBALS['wp_delete_attachment_calls'][] = [
                    'post_id' => $post_id,
                    'force_delete' => $force_delete
                ];
                return true;
            }
        }
    }

    protected function resetMockCalls()
    {
        $GLOBALS['wp_delete_attachment_calls'] = [];
    }

    protected function assertAttachmentWasDeleted($post_id)
    {
        $this->assertNotEmpty(
            $GLOBALS['wp_delete_attachment_calls'],
            'wp_delete_attachment was not called'
        );

        $found = false;
        foreach ($GLOBALS['wp_delete_attachment_calls'] as $call) {
            if ($call['post_id'] === $post_id) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "wp_delete_attachment was not called with post_id: $post_id");
    }

    protected function mockWPVerifyNonce($value)
    {
        global $wp_verify_nonce_mock;
        $wp_verify_nonce_mock = $value;
    }

    protected function mockWPCheckFiletype($type, $ext)
    {
        global $wp_check_filetype_mock;
        $wp_check_filetype_mock = ['type' => $type, 'ext' => $ext];
    }
}
