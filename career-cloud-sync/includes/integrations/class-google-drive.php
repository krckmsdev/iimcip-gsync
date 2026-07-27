<?php
defined('ABSPATH') || exit;

class CCS_Google_Drive {

      private static $settings = null;

    private static function settings() {
        if (self::$settings === null) {
            self::$settings = get_option('ccs_settings', array());
        }
        return self::$settings;
    }

    private static function client_id() {
        $s = self::settings();
        return isset($s['client_id']) ? $s['client_id'] : '';
    }

    private static function client_secret() {
        $s = self::settings();
        return isset($s['client_secret']) ? $s['client_secret'] : '';
    }

    private static function refresh_token() {
        $s = self::settings();
        return isset($s['refresh_token']) ? $s['refresh_token'] : '';
    }

    private static function parent_folder_id() {
        $s = self::settings();
        return isset($s['parent_folder_id']) ? $s['parent_folder_id'] : '';
    }

    // ==================================================================
    // FILL THESE IN — replace with your real values for testing.
    // Later, wire these to your options page (get_option() calls) —
    // just swap the constants below for get_option('ccs_client_id') etc.
    // ==================================================================
    // const CLIENT_ID        = self::client_id();
    // const CLIENT_SECRET    = self::client_secret();
    // const REFRESH_TOKEN    = self::refresh_token();
    // const PARENT_FOLDER_ID = self::parent_folder_id();
    // ==================================================================

    private static $access_token = null;

    /**
     * Exchange the stored refresh token for a short-lived access token.
     * Cached per-request so we don't call this twice in one submission.
     */
    public static function get_access_token() {
        if (self::$access_token) {
            return self::$access_token;
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'timeout' => 20,
            'body'    => array(
                'client_id'     => self::client_id(),
                'client_secret' => self::client_secret(),
                'refresh_token' => self::refresh_token(),
                'grant_type'    => 'refresh_token',
            ),
        ));

        if (is_wp_error($response)) {
            self::log('Token request failed (transport error): ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['access_token'])) {
            self::log('Token request failed (HTTP ' . $code . '): ' . wp_remote_retrieve_body($response));
            return false;
        }

        self::$access_token = $body['access_token'];
        return self::$access_token;
    }

    /**
     * Create a folder in Drive under $parent_id (defaults to PARENT_FOLDER_ID).
     * Returns the new folder's ID, or false on failure.
     */
    public static function create_folder($folder_name, $parent_id = null) {
        $access_token = self::get_access_token();
        if (!$access_token) {
            return false;
        }

        $parent = $parent_id ? $parent_id : self::parent_folder_id();

        $response = wp_remote_post('https://www.googleapis.com/drive/v3/files', array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'name'     => $folder_name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents'  => array($parent),
            )),
        ));

        if (is_wp_error($response)) {
            self::log('Folder creation failed (transport error): ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['id'])) {
            self::log('Folder creation failed (HTTP ' . $code . '): ' . wp_remote_retrieve_body($response));
            return false;
        }

        self::log("Created Drive folder '{$folder_name}' (ID: {$body['id']})");
        return $body['id'];
    }

    /**
     * Upload a single local file straight into a Drive folder.
     * Uses multipart upload — no external library needed.
     */
    public static function upload_file($file_path, $file_name, $folder_id) {
        $access_token = self::get_access_token();
        if (!$access_token) {
            return false;
        }

        if (!file_exists($file_path)) {
            self::log("Upload skipped — local file missing: {$file_path}");
            return false;
        }

        $mime_type = function_exists('mime_content_type') ? mime_content_type($file_path) : 'application/octet-stream';
        $file_data = file_get_contents($file_path);
        $boundary  = wp_generate_password(24, false);

        $metadata = wp_json_encode(array(
            'name'    => $file_name,
            'parents' => array($folder_id),
        ));

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= $metadata . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: {$mime_type}\r\n\r\n";
        $body .= $file_data . "\r\n";
        $body .= "--{$boundary}--";

        $response = wp_remote_post(
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart',
            array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'multipart/related; boundary=' . $boundary,
                ),
                'body' => $body,
            )
        );

        if (is_wp_error($response)) {
            self::log("Upload failed for '{$file_name}' (transport error): " . $response->get_error_message());
            return false;
        }

        $code   = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($result['id'])) {
            self::log("Upload failed for '{$file_name}' (HTTP {$code}): " . wp_remote_retrieve_body($response));
            return false;
        }

        self::log("Uploaded '{$file_name}' to Drive (file ID: {$result['id']}, folder: {$folder_id})");
        return $result['id'];
    }

    /**
     * Same logging approach as the rest of the plugin — its own file,
     * independent of WP_DEBUG.
     */
    public static function log($message) {
        $upload_dir = wp_upload_dir();
        $log_dir    = trailingslashit($upload_dir['basedir']) . 'cf7-attachment-logs/';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $log_file  = $log_dir . 'google-drive-log.txt';
        $timestamp = date('Y-m-d H:i:s');

        error_log("[{$timestamp}] {$message}" . PHP_EOL, 3, $log_file);
    }
}
