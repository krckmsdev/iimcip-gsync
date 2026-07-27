<?php
defined('ABSPATH') || exit;

class CCS_Google_Sheets {

    const DEFAULT_TAB = 'Sheet1';

    /**
     * Main entry point. $reports_folder_id is that job's own folder
     * (from Folder Mapping's "Folder ID" field) — no global folder.
     *
     * Finds this week's Sheet by matching the week label against file
     * names already in that folder. If found, appends a row. If not
     * found, creates a brand new Sheet (header + first row written
     * together, since a fresh Sheet has nothing to "append" to).
     *
     * Returns the Sheet's file ID, or false on failure.
     */
    public static function add_submission_row($reports_folder_id, $header, $row) {
        if (empty($reports_folder_id)) {
            self::log('add_submission_row called with no reports_folder_id — skipped.');
            return false;
        }

        $week_label  = self::get_current_week_label();
        $existing_id = self::find_sheet_by_week_label($reports_folder_id, $week_label);

        $sl_index = array_search('SL', $header);

        if ($existing_id) {
            if ($sl_index !== false) {
                $row[$sl_index] = self::get_data_row_count($existing_id) + 1;
            }
            self::append_row($existing_id, $row);
            return $existing_id;
        }

        if ($sl_index !== false) {
            $row[$sl_index] = 1;
        }

        return self::create_weekly_sheet($reports_folder_id, $week_label, $header, $row);
    }

    private static function get_data_row_count($sheet_id) {
        $access_token = CCS_Google_Drive::get_access_token();
        if (!$access_token) {
            return 0;
        }

        $range = self::DEFAULT_TAB . '!A2:A';
        $url   = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values/{$range}";

        $response = wp_remote_get($url, array(
            'timeout' => 20,
            'headers' => array('Authorization' => 'Bearer ' . $access_token),
        ));

        if (is_wp_error($response)) {
            self::log('Row count lookup failed (transport error): ' . $response->get_error_message());
            return 0;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return !empty($body['values']) ? count($body['values']) : 0;
    }

    public static function get_current_week_label() {
        $today = current_time('timestamp');

        $day_of_week = (int) date('N', $today); // 1 (Mon) .. 7 (Sun)
        $monday_ts   = strtotime('-' . ($day_of_week - 1) . ' days', $today);
        $sunday_ts   = strtotime('+6 days', $monday_ts);

        $start_month = date('M', $monday_ts);
        $end_month   = date('M', $sunday_ts);
        $start_day   = date('j', $monday_ts);
        $end_day     = date('j', $sunday_ts);
        $year        = date('Y', $sunday_ts);

        if ($start_month === $end_month) {
            return "{$start_month} {$start_day}-{$end_day} {$year}";
        }

        return "{$start_month} {$start_day} to {$end_month} {$end_day} {$year}";
    }

    private static function find_sheet_by_week_label($reports_folder_id, $week_label) {
        $files = self::list_reports_folder_files($reports_folder_id);

        foreach ($files as $file) {
            if (strpos($file['name'], $week_label) !== false) {
                self::log("Found existing weekly sheet for '{$week_label}': {$file['name']} (ID: {$file['id']})");
                return $file['id'];
            }
        }

        return false;
    }

    private static function get_next_sequence_number($reports_folder_id) {
        $files = self::list_reports_folder_files($reports_folder_id);
        $max   = 0;

        foreach ($files as $file) {
            if (preg_match('/^(\d+)\s/', $file['name'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }

    private static function list_reports_folder_files($reports_folder_id) {
        $access_token = CCS_Google_Drive::get_access_token();
        if (!$access_token) {
            return array();
        }

        $query = sprintf(
            "'%s' in parents and mimeType='application/vnd.google-apps.spreadsheet' and trashed=false",
            $reports_folder_id
        );

        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
            'q'      => $query,
            'fields' => 'files(id,name)',
        ));

        $response = wp_remote_get($url, array(
            'timeout' => 20,
            'headers' => array('Authorization' => 'Bearer ' . $access_token),
        ));

        if (is_wp_error($response)) {
            self::log('Reports folder listing failed (transport error): ' . $response->get_error_message());
            return array();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return !empty($body['files']) ? $body['files'] : array();
    }

    private static function create_weekly_sheet($reports_folder_id, $week_label, $header, $row) {
        $access_token = CCS_Google_Drive::get_access_token();
        if (!$access_token) {
            return false;
        }

        $seq_number = self::get_next_sequence_number($reports_folder_id);
        $file_name  = sprintf('%02d %s', $seq_number, $week_label);

        $response = wp_remote_post('https://www.googleapis.com/drive/v3/files', array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'name'     => $file_name,
                'mimeType' => 'application/vnd.google-apps.spreadsheet',
                'parents'  => array($reports_folder_id),
            )),
        ));

        if (is_wp_error($response)) {
            self::log('Sheet creation failed (transport error): ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['id'])) {
            self::log('Sheet creation failed: ' . wp_remote_retrieve_body($response));
            return false;
        }

        $sheet_id = $body['id'];
        self::log("Created new weekly sheet '{$file_name}' (ID: {$sheet_id})");

        self::write_values($sheet_id, self::DEFAULT_TAB . '!A1', array($header, $row));

        return $sheet_id;
    }

    private static function append_row($sheet_id, $row) {
        $access_token = CCS_Google_Drive::get_access_token();
        if (!$access_token) {
            return false;
        }

        $range = self::DEFAULT_TAB . '!A1:append';
        $url   = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values/{$range}?"
               . http_build_query(array(
                     'valueInputOption' => 'RAW',
                     'insertDataOption' => 'INSERT_ROWS',
                 ));

        $response = wp_remote_post($url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode(array('values' => array($row))),
        ));

        if (is_wp_error($response)) {
            self::log('Row append failed (transport error): ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            self::log("Row append failed (HTTP {$code}): " . wp_remote_retrieve_body($response));
            return false;
        }

        self::log("Appended row to sheet ID {$sheet_id}");
        return true;
    }

    private static function write_values($sheet_id, $range, $rows) {
        $access_token = CCS_Google_Drive::get_access_token();
        if (!$access_token) {
            return false;
        }

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values/{$range}?"
             . http_build_query(array('valueInputOption' => 'RAW'));

        $response = wp_remote_request($url, array(
            'method'  => 'PUT',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode(array('values' => $rows)),
        ));

        if (is_wp_error($response)) {
            self::log('Header/first-row write failed (transport error): ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            self::log("Header/first-row write failed (HTTP {$code}): " . wp_remote_retrieve_body($response));
            return false;
        }

        self::log("Wrote header + first row to sheet ID {$sheet_id}");
        return true;
    }

    private static function log($message) {
        $upload_dir = wp_upload_dir();
        $log_dir    = trailingslashit($upload_dir['basedir']) . 'cf7-attachment-logs/';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $log_file  = $log_dir . 'google-sheets-log.txt';
        $timestamp = date('Y-m-d H:i:s');

        error_log("[{$timestamp}] {$message}" . PHP_EOL, 3, $log_file);
    }
}