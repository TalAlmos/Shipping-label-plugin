<?php
/**
 * Debug Helper Class
 */

if (!defined('WPINC')) {
    die;
}

class WSL_Debug {
    /**
     * Log data to a file
     * 
     * @param string $interface_name Name of the API interface (e.g., 'address_validation')
     * @param array|string $data Data to log
     * @param string $direction Either 'request' or 'response'
     * @return bool Success/failure
     */
    public static function log_api_data($interface_name, $data, $direction = 'request') {
        // CHANGE: Always log API data regardless of WP_DEBUG setting
        // This ensures we capture all API communication
        
        try {
            // Create the debug directory if it doesn't exist
            $upload_dir = wp_upload_dir();
            $debug_base_dir = $upload_dir['basedir'] . '/wsl-debug';
            $debug_dir = $debug_base_dir . '/' . ($direction === 'request' ? 'post' : 'response');
            
            // Create directories with explicit permissions
            if (!file_exists($debug_base_dir)) {
                $made_dir = wp_mkdir_p($debug_base_dir);
                if (!$made_dir) {
                    error_log('WSL Debug: Failed to create base debug directory at ' . $debug_base_dir);
                    return false;
                }
                // Set proper permissions (755 = owner can read/write/execute, others can read/execute)
                chmod($debug_base_dir, 0755);
            }
            
            if (!file_exists($debug_dir)) {
                $made_dir = wp_mkdir_p($debug_dir);
                if (!$made_dir) {
                    error_log('WSL Debug: Failed to create direction debug directory at ' . $debug_dir);
                    return false;
                }
                chmod($debug_dir, 0755);
            }
            
            // Create the filename with date
            $date = date('Ymd');
            $time = date('His');
            $filename = $interface_name . '_' . $date . '_' . $time . '.json';
            $file_path = $debug_dir . '/' . $filename;
            
            // Format the data for output
            if (is_array($data) || is_object($data)) {
                // Handle circular references by using try/catch
                try {
                    $output = json_encode($data, JSON_PRETTY_PRINT);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $output = 'JSON encode error: ' . json_last_error_msg() . "\n\n";
                        $output .= print_r($data, true);
                    }
                } catch (Exception $e) {
                    $output = 'Exception during JSON encoding: ' . $e->getMessage() . "\n\n";
                    $output .= print_r($data, true);
                }
            } else {
                $output = (string) $data;
            }
            
            // Write to file
            $result = file_put_contents($file_path, $output);
            
            if ($result === false) {
                error_log('WSL Debug: Failed to write to file ' . $file_path);
                return false;
            }
            
            // Ensure file has the right permissions
            chmod($file_path, 0644); // 644 = owner can read/write, others can read
            
            // Log successful debugging
            error_log('WSL Debug: Successfully logged ' . $direction . ' data for ' . $interface_name . ' to ' . $file_path);
            
            return true;
            
        } catch (Exception $e) {
            error_log('WSL Debug: Exception during logging: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test the debug functionality
     * 
     * This can be called directly to verify the debug system is working
     */
    public static function test_debug_system() {
        $test_data = array(
            'test' => true,
            'timestamp' => current_time('mysql'),
            'message' => 'This is a test of the WSL debug system'
        );
        
        $request_result = self::log_api_data('debug_test', $test_data, 'request');
        $response_result = self::log_api_data('debug_test', $test_data, 'response');
        
        return array(
            'request_logged' => $request_result,
            'response_logged' => $response_result,
            'upload_dir' => wp_upload_dir(),
            'server_info' => array(
                'php_version' => phpversion(),
                'os' => PHP_OS,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'permissions_check' => array(
                    'can_write_to_uploads' => is_writable(wp_upload_dir()['basedir']),
                )
            )
        );
    }
} 