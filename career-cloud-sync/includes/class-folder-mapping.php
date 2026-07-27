<?php
defined('ABSPATH') || exit;

class CCS_Folder_Mapping {

    const OPTION_KEY = 'ccs_folder_mapping';

    public function __construct() {
        add_action('admin_menu', array($this, 'submenu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function submenu() {
        add_submenu_page(
            'career-cloud-sync',
            'Folder Mapping',
            'Folder Mapping',
            'manage_options',
            'ccs-folder-mapping',
            array($this, 'page')
        );
    }

    public function register_settings() {
        register_setting(
            'ccs_folder_mapping_group',
            self::OPTION_KEY,
            array('sanitize_callback' => array($this, 'sanitize'))
        );
    }

    public function sanitize($input) {
        $clean = array();

        if (empty($input) || !is_array($input)) {
            return $clean;
        }

        foreach ($input as $row) {
            $job_slug = isset($row['job_slug']) ? sanitize_title($row['job_slug']) : '';

            // Skip completely empty rows (e.g. an unused template row).
            if (empty($job_slug)) {
                continue;
            }

            $clean[] = array(
                'job_slug'             => $job_slug,
                'folder_id'            => isset($row['folder_id']) ? sanitize_text_field($row['folder_id']) : '',
                'attachment_folder_id' => isset($row['attachment_folder_id']) ? sanitize_text_field($row['attachment_folder_id']) : '',
            );
        }

        return $clean;
    }

    /**
     * Helper for other classes: look up the Drive folder IDs saved for a
     * given job slug. Returns null if no mapping exists for that slug.
     */
    public static function get_folder_ids($job_slug) {
        $job_slug = sanitize_title($job_slug);
        $mapping  = get_option(self::OPTION_KEY, array());

        foreach ($mapping as $row) {
            if (isset($row['job_slug']) && $row['job_slug'] === $job_slug) {
                return array(
                    'folder_id'            => isset($row['folder_id']) ? $row['folder_id'] : '',
                    'attachment_folder_id'  => isset($row['attachment_folder_id']) ? $row['attachment_folder_id'] : '',
                );
            }
        }

        return null;
    }

    private function get_all_jobs() {
        $jobs = get_posts(array(
            'post_type'      => 'job',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $list = array();
        foreach ($jobs as $job) {
            $list[] = array(
                'slug'  => $job->post_name,
                'title' => $job->post_title,
            );
        }
        return $list;
    }

   

    private function render_job_dropdown($jobs, $selected_slug, $index) {
        echo '<select class="ccs-job-select" name="' . self::OPTION_KEY . '[' . esc_attr($index) . '][job_slug]" required>';
        echo '<option value="">— Select a job —</option>';
        foreach ($jobs as $job) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($job['slug']),
                selected($selected_slug, $job['slug'], false),
                esc_html($job['title'])
            );
        }
        echo '</select>';
    }

    public function page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $jobs    = $this->get_all_jobs();
        $mapping = get_option(self::OPTION_KEY, array());

        if (empty($mapping)) {
            $mapping = array(array('job_slug' => '', 'folder_id' => '', 'attachment_folder_id' => ''));
        }
        ?>
        <div class="wrap">
            <h1>Job → Drive Folder Mapping</h1>
            <p>Each row maps one job to the Drive folder where its applications should be uploaded. Add one row per job (about 20–25 total).</p>

            <form method="post" action="options.php">
                <?php settings_fields('ccs_folder_mapping_group'); ?>

                <table class="widefat" id="ccs-mapping-table">
                    <thead>
                        <tr>
                            <th style="width:30%;">Job</th>
                            <th style="width:30%;">Folder ID</th>
                            <th style="width:30%;">Attachment Folder ID</th>
                            <th style="width:10%;"></th>
                        </tr>
                    </thead>
                    <tbody id="ccs-mapping-rows">
                        <?php foreach ($mapping as $i => $row) : ?>
                            <tr class="ccs-mapping-row">
                                <td><?php $this->render_job_dropdown($jobs, $row['job_slug'], $i); ?></td>
                                <td>
                                    <input type="text" class="regular-text"
                                           name="<?php echo self::OPTION_KEY; ?>[<?php echo (int) $i; ?>][folder_id]"
                                           value="<?php echo esc_attr($row['folder_id']); ?>" required>
                                </td>
                                <td>
                                    <input type="text" class="regular-text"
                                           name="<?php echo self::OPTION_KEY; ?>[<?php echo (int) $i; ?>][attachment_folder_id]"
                                           value="<?php echo esc_attr($row['attachment_folder_id']); ?>">
                                </td>
                                <td><button type="button" class="button ccs-remove-row">Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p><button type="button" class="button button-secondary" id="ccs-add-row">+ Add Job Row</button></p>

                <?php submit_button('Save Mapping'); ?>
            </form>
        </div>

        <!-- Hidden template row, used by JS to build new rows. -->
        <table style="display:none;">
            <tbody>
                <tr id="ccs-template-row" class="ccs-mapping-row">
                    <td>
                        <?php $this->render_job_dropdown($jobs, '', '__INDEX__'); ?>
                    </td>
                    <td><input type="text" class="regular-text" name="<?php echo self::OPTION_KEY; ?>[__INDEX__][folder_id]" value=""></td>
                    <td><input type="text" class="regular-text" name="<?php echo self::OPTION_KEY; ?>[__INDEX__][attachment_folder_id]" value=""></td>
                    <td><button type="button" class="button ccs-remove-row">Remove</button></td>
                </tr>
            </tbody>
        </table>

        <script>
        jQuery(function($) {
            var nextIndex = <?php echo count($mapping); ?>;

            $('#ccs-add-row').on('click', function() {
                var $newRow = $('#ccs-template-row').clone(true);
                $newRow.removeAttr('id');

                $newRow.find('select, input').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace('__INDEX__', nextIndex));
                    }
                });

                $('#ccs-mapping-rows').append($newRow);
                nextIndex++;
            });

            $(document).on('click', '.ccs-remove-row', function() {
                var $rows = $('#ccs-mapping-rows .ccs-mapping-row');
                if ($rows.length > 1) {
                    $(this).closest('tr').remove();
                } else {
                    var $row = $(this).closest('tr');
                    $row.find('select').val('');
                    $row.find('input[type=text]').val('');
                }
            });
        });
        </script>
        <?php
    }
}