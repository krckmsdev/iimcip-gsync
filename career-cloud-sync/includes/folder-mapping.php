<?php
defined('ABSPATH') || exit;

define('CCS_MAPPING_OPTION_KEY', 'ccs_folder_mapping');

add_action('admin_init', 'ccs_register_folder_mapping_settings');

function ccs_register_folder_mapping_settings() {
    register_setting(
        'ccs_folder_mapping_group',
        CCS_MAPPING_OPTION_KEY,
        array('sanitize_callback' => 'ccs_sanitize_folder_mapping')
    );
}

function ccs_sanitize_folder_mapping($input) {
    $clean     = array();
    $seen_jobs = array();

    if (empty($input) || !is_array($input)) {
        return $clean;
    }

    foreach ($input as $row) {
        $job_slug = isset($row['job_slug']) ? sanitize_title($row['job_slug']) : '';

        if (empty($job_slug)) {
            continue;
        }

        // Same job mapped twice — keep the first row, skip the rest.
        if (in_array($job_slug, $seen_jobs, true)) {
            continue;
        }
        $seen_jobs[] = $job_slug;

        $clean[] = array(
            'job_slug'             => $job_slug,
            'folder_id'            => isset($row['folder_id']) ? sanitize_text_field($row['folder_id']) : '',
            'attachment_folder_id' => isset($row['attachment_folder_id']) ? sanitize_text_field($row['attachment_folder_id']) : '',
        );
    }

    return $clean;
}

function ccs_get_folder_ids($job_slug) {
    $job_slug = sanitize_title($job_slug);
    $mapping  = get_option(CCS_MAPPING_OPTION_KEY, array());

    foreach ($mapping as $row) {
        if (isset($row['job_slug']) && $row['job_slug'] === $job_slug) {
            return array(
                'folder_id'            => isset($row['folder_id']) ? $row['folder_id'] : '',
                'attachment_folder_id' => isset($row['attachment_folder_id']) ? $row['attachment_folder_id'] : '',
            );
        }
    }

    return null;
}

function ccs_get_all_jobs() {
    $jobs = get_posts(array(
        'post_type'      => 'job',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    $list = array();
    foreach ($jobs as $job) {
        $list[] = array('slug' => $job->post_name, 'title' => $job->post_title);
    }
    return $list;
}