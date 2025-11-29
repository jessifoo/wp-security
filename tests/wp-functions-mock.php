<?php
/**
 * WordPress functions mock for testing
 */

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        return is_dir($target) || mkdir($target, 0777, true);
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return trailingslashit(dirname($file));
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/\\') . '/';
    }
}

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path($path) {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('|(?<=.)/+|', '/', $path);
        return $path;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        $time = time();
        return $type === 'mysql' ? date('Y-m-d H:i:s', $time) : $time;
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        global $wp_upload_dir_mock;
        if (isset($wp_upload_dir_mock)) {
            return $wp_upload_dir_mock;
        }

        $upload_dir = sys_get_temp_dir() . '/wordpress/wp-content/uploads';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        return [
            'path' => $upload_dir,
            'url' => 'http://example.com/wp-content/uploads',
            'subdir' => '',
            'basedir' => $upload_dir,
            'baseurl' => 'http://example.com/wp-content/uploads',
            'error' => false
        ];
    }
}

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

if (!function_exists('size_format')) {
    function size_format($bytes, $decimals = 0) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $step = 1024;
        $i = 0;
        while (($bytes / $step) > 0.9) {
            $bytes = $bytes / $step;
            $i++;
            if ($i >= count($units)) {
                break;
            }
        }
        return round($bytes, $decimals) . $units[$i];
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        switch ($show) {
            case 'version':
                return '6.4.2';
            default:
                return '';
        }
    }
}

if (!function_exists('wp_safe_remote_get')) {
    function wp_safe_remote_get($url, $args = array()) {
        return array(
            'response' => array(
                'code' => 200
            ),
            'body' => ''
        );
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return isset($response['response']['code']) ? $response['response']['code'] : 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        global $is_wp_error_mock;
        if (isset($is_wp_error_mock)) {
            return $is_wp_error_mock;
        }
        return false;
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        global $wp_remote_get_mock;
        if (isset($wp_remote_get_mock)) {
            return $wp_remote_get_mock;
        }
        return array(
            'response' => array('code' => 200),
            'body' => ''
        );
    }
}

if (!function_exists('get_locale')) {
    function get_locale() {
        return 'en_US';
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url) {
        $query = http_build_query($args);
        return $url . '?' . $query;
    }
}

if (!function_exists('get_plugins')) {
    function get_plugins($plugin_folder = '') {
        global $get_plugins_mock;
        return isset($get_plugins_mock) ? $get_plugins_mock : array();
    }
}
