<?php
defined('ABSPATH') || exit;

class CCS_Log_Viewer {

    const LOG_FILES = array(
        'cf7'    => array('label' => 'CF7 Submissions', 'file' => 'cf7-attachment-log.txt'),
        'drive'  => array('label' => 'Google Drive',    'file' => 'google-drive-log.txt'),
        'sheets' => array('label' => 'Google Sheets',   'file' => 'google-sheets-log.txt'),
    );

    const MAX_LINES = 300;

    public function __construct() {
        add_action('admin_post_ccs_clear_logs', array($this, 'handle_clear_logs'));
    }

    public static function log_dir() {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'cf7-attachment-logs/';
    }

    public static function read_entries($source, $file, $limit) {
        $path = self::log_dir() . $file;
        if (!file_exists($path)) {
            return array();
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return array();
        }

        $lines   = array_slice($lines, -$limit);
        $entries = array();

        foreach ($lines as $line) {
            $time = '';
            $msg  = $line;

            if (preg_match('/^\[([\d\-: ]+)\]\s*(.*)$/', $line, $m)) {
                $time = $m[1];
                $msg  = $m[2];
            }

            $entries[] = array('time' => $time, 'source' => $source, 'message' => $msg);
        }

        return $entries;
    }

    public static function get_recent_entries() {
        $all_entries = array();
        foreach (self::LOG_FILES as $key => $info) {
            $all_entries = array_merge($all_entries, self::read_entries($key, $info['file'], self::MAX_LINES));
        }

        usort($all_entries, function ($a, $b) {
            return strcmp($b['time'], $a['time']);
        });

        return array_slice($all_entries, 0, self::MAX_LINES);
    }

    public function handle_clear_logs() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }
        check_admin_referer('ccs_clear_logs');

        foreach (self::LOG_FILES as $info) {
            $path = self::log_dir() . $info['file'];
            if (file_exists($path)) {
                file_put_contents($path, '');
            }
        }

        wp_safe_redirect(add_query_arg('cleared', '1', wp_get_referer()));
        exit;
    }
}