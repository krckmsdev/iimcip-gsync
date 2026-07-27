<?php
defined('ABSPATH') || exit;

class CCS_CF7 {

    // Set to false if you don't want a duplicate local copy kept on the server.
    const ALSO_SAVE_LOCAL_COPY = false;

    private static $settings = null;

    public function __construct() {
        add_action('wpcf7_before_send_mail', array($this, 'save_attachment_copy'));
    }

    private static function settings() {
        if (self::$settings === null) {
            self::$settings = get_option('ccs_settings', array());
        }
        return self::$settings;
    }

    private static function form_ID() {
        $s = self::settings();
        return isset($s['form_id']) ? $s['form_id'] : '';
    }

    /**
     * For every job slug already saved in Folder Mapping, fetch that
     * post and check if ITS title equals the job_title CF7 sent us.
     * If it matches, return that row's folder_id + attachment_folder_id
     * directly. Returns null if nothing in the mapping matches.
     */
    private static function find_folder_ids_by_job_title($job_title) {
        $mapping = get_option('ccs_folder_mapping', array());

        foreach ($mapping as $row) {
            if (empty($row['job_slug'])) {
                continue;
            }

            $post = get_page_by_path($row['job_slug'], OBJECT, 'job');


            // $this->log("t1 = " . $post->post_title ." t2 = " . $job_title);
            // $this->log($post && $post->post_title === $job_title);


            if ($post && $post->post_title === $job_title) {
                return array(
                    'folder_id'            => $row['folder_id'],
                    'attachment_folder_id' => $row['attachment_folder_id'],
                    'reports_folder_id'    => isset($row['reports_folder_id']) ? $row['reports_folder_id'] : '',
                );
            }
        }

        return null;
    }

    /**
     * Exact column order for the weekly report Sheet — matches the
     * existing manually-maintained XLS files and the live CF7 form.
     */
    private static function sheet_header_columns() {
        return array(
            'SL', 'Submitted', 'job_title', 'your-name', 'DateofBirth', 'your-email',
            'Phonenumber', 'Currentcity', 'LinkedInprofile', 'portfoliowebsite', 'Nationality',
            'Noticeperiod', 'CurrentCTC', 'ExpectedCTC',
            'Degree_Name_1', 'Institution_name_1', 'Year_of_graduation_1', 'GPA_1',
            'Degree_Name_2', 'Institution_name_2', 'Year_of_graduation_2', 'GPA_2',
            'Degree_Name_3', 'Institution_name_3', 'Year_of_graduation_3', 'GPA_3',
            'Degree_Name_4', 'Institution_name_4', 'Year_of_graduation_4', 'GPA_4',
            'Schoolname', 'Yearofpassing', 'SchoolGrades', 'Instname', 'Yearofpass', 'CGrades',
            'TotalYearsofExperience', 'relevantexperience',
            'CurrentNameofCompany', 'CurrentLocation', 'CurrentJobtitle', 'CurrentDateofJoining',
            'CurrentKeyResponsibilities', 'CurrentAchievements',
            'Prev_NameofCompany_1', 'Prev_Location_1', 'Prev_Jobtitle_1', 'Prev_DateofJoining_1',
            'Prev_KeyResponsibilities_1', 'Prev_Achievements_1',
            'Prev_NameofCompany_2', 'Prev_Location_2', 'Prev_Jobtitle_2', 'Prev_DateofJoining_2',
            'Prev_KeyResponsibilities_2', 'Prev_Achievements_2',
            'Prev_NameofCompany_3', 'Prev_Location_3', 'Prev_Jobtitle_3', 'Prev_DateofJoining_3',
            'Prev_KeyResponsibilities_3', 'Prev_Achievements_3',
            'skill_items', 'skill_input', 'tools_items', 'tools_input',
            'Language1', 'Language2', 'Language3', 'Whyareyouinterested',
            'citizen_india', 'relocate_travel', 'company_policy', 'consent',
            'gender', 'disability', 'veteran',
            'Declaration1', 'Declaration2',
            'ResumeCVupload', 'Coverletter', 'Portfolio',
            'Submitted From',
        );
    }

    private static function get_field($posted_data, $key) {
        if (!isset($posted_data[$key])) {
            return '';
        }
        $val = $posted_data[$key];
        return is_array($val) ? implode(', ', $val) : $val;
    }

    private function build_sheet_row($posted_data, $file_urls) {
        $header = self::sheet_header_columns();
        $row    = array();

        foreach ($header as $col) {
            switch ($col) {
                case 'SL':
                    $row[] = '';
                    break;

                case 'Submitted':
                    $row[] = date('Y-m-d H:i:s');
                    break;

                case 'ResumeCVupload':
                case 'Coverletter':
                case 'Portfolio':
                    $row[] = isset($file_urls[$col]) ? $file_urls[$col] : '';
                    break;

                case 'Submitted From':
                    $row[] = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
                    break;

                default:
                    $row[] = self::get_field($posted_data, $col);
            }
        }

        return $row;
    }

    public function save_attachment_copy($contact_form) {

        if ($contact_form->id() != self::form_ID()) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }

        $uploaded_files = $submission->uploaded_files();


        if (empty($uploaded_files)) {
            $this->log('No file field found in submission — upload skipped.');
            return;
        }

        
        $this->log("started logging posted data" . print_r($submission->get_posted_data(), true));

        $posted_data     = $submission->get_posted_data();
        $job_title       = isset($posted_data['job_title']) ? $posted_data['job_title'] : 'unknown-job';
        $applicant_email = isset($posted_data['your-email']) ? $posted_data['your-email'] : 'unknown-email';

        $timestamp   = date('Y-m-d_H-i-s');
        $folder_name = sanitize_email($applicant_email) . '_' . $timestamp;

        // ---- Look up this job's Drive folders from Folder Mapping ----
        $folder_map = self::find_folder_ids_by_job_title($job_title);

        if (!$folder_map || empty($folder_map['attachment_folder_id'])) {
            $this->log("No attachment folder mapped for job title '{$job_title}' — skipping Drive/Sheet sync. Add this job to Folder Mapping to enable it.");
            return;
        }

        $attachment_folder_id = $folder_map['attachment_folder_id'];
        $reports_folder_id    = isset($folder_map['folder_id']) ? $folder_map['folder_id'] : '';

        $valid_files = array();

        foreach ($uploaded_files as $field_name => $file_paths) {
            $file_paths = (array) $file_paths;

            foreach ($file_paths as $tmp_path) {
                if (empty($tmp_path) || !file_exists($tmp_path)) {
                    $this->log("File field '{$field_name}' is empty or file missing — skipped.");
                    continue;
                }
                $valid_files[] = array('field' => $field_name, 'path' => $tmp_path);
            }
        }

        if (empty($valid_files)) {
            $this->log('File field existed but no valid files were found.');
            return;
        }

        if (self::ALSO_SAVE_LOCAL_COPY) {
            $this->save_local_copies($valid_files, $folder_name);
        }

        // Create a dynamic subfolder (email + timestamp) inside the job's
        // attachments folder, then upload each file into that subfolder.
        $applicant_folder_id = CCS_Google_Drive::create_folder($folder_name, $attachment_folder_id);

        if (!$applicant_folder_id) {
            $this->log("Could not create applicant subfolder '{$folder_name}' inside {$attachment_folder_id} — Drive upload aborted for this submission.");
            return;
        }

        $file_urls = array();

        foreach ($valid_files as $file) {
            $tmp_path      = $file['path'];
            $field_name    = $file['field'];
            $original_name = basename($tmp_path);
            $unique_name   = sanitize_email($applicant_email) . '_' . $timestamp . '_' . $original_name;

            $file_id = CCS_Google_Drive::upload_file($tmp_path, $unique_name, $applicant_folder_id);

            if ($file_id) {
                $file_urls[$field_name] = "https://drive.google.com/file/d/{$file_id}/view";
            } else {
                $this->log("Upload FAILED for '{$unique_name}' into folder {$applicant_folder_id}");
            }
        }

        // ---- Log this submission into this job's own weekly report Sheet ----
        if (class_exists('CCS_Google_Sheets') && !empty($reports_folder_id)) {
            $header = self::sheet_header_columns();
            $row    = $this->build_sheet_row($posted_data, $file_urls);
            CCS_Google_Sheets::add_submission_row($reports_folder_id, $header, $row);
        } elseif (empty($reports_folder_id)) {
            $this->log("No Reports Folder ID mapped for job title '{$job_title}' — skipping weekly Sheet sync (files were still uploaded).");
        }
    }

    private function save_local_copies($valid_files, $folder_name) {
        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit($upload_dir['basedir']) . 'job-application-attachments/';
        $target_dir = $base_dir . $folder_name . '/';

        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        foreach ($valid_files as $file) {
            $tmp_path      = $file['path'];
            $original_name = basename($tmp_path);
            $target_path   = $target_dir . $original_name;
            $copied        = copy($tmp_path, $target_path);

            if ($copied) {
                $this->log("Local copy saved: '{$original_name}' to {$target_dir}");
            } else {
                $this->log("Local copy FAILED for '{$original_name}' to {$target_path}");
            }
        }
    }

    private function log($message) {
        $upload_dir = wp_upload_dir();
        $log_dir    = trailingslashit($upload_dir['basedir']) . 'cf7-attachment-logs/';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $log_file  = $log_dir . 'cf7-attachment-log.txt';
        $timestamp = date('Y-m-d H:i:s');

        error_log("[{$timestamp}] {$message}" . PHP_EOL, 3, $log_file);
    }
}