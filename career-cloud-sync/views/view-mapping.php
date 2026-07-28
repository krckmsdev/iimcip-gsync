<?php
defined('ABSPATH') || exit;

$jobs    = ccs_get_all_jobs();
$mapping = get_option(CCS_MAPPING_OPTION_KEY, array());

if (empty($mapping)) {
    $mapping = array(array('job_slug' => '', 'folder_id' => '', 'attachment_folder_id' => ''));
}
?>
<div class="ccs-card ccs-table-card">
    <div class="ccs-card-header">Job → Drive Folder Mapping</div>
    <div class="ccs-card-body">
        <p class="description" style="margin-top:0;">Each row maps one job to the Drive folder where its applications should be uploaded.</p>

        <form method="post" action="options.php">
            <?php settings_fields('ccs_folder_mapping_group'); ?>

            <table class="ccs-table" id="ccs-mapping-table" data-next-index="<?php echo (int) count($mapping); ?>">
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
                            <td>
                                <select class="ccs-job-select" name="<?php echo CCS_MAPPING_OPTION_KEY; ?>[<?php echo (int) $i; ?>][job_slug]" required>
                                    <option value="">— Select a job —</option>
                                    <?php foreach ($jobs as $job) : ?>
                                        <option value="<?php echo esc_attr($job['slug']); ?>" <?php selected($row['job_slug'], $job['slug']); ?>><?php echo esc_html($job['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text"
                                       name="<?php echo CCS_MAPPING_OPTION_KEY; ?>[<?php echo (int) $i; ?>][folder_id]"
                                       value="<?php echo esc_attr($row['folder_id']); ?>" required>
                            </td>
                            <td>
                                <input type="text"
                                       name="<?php echo CCS_MAPPING_OPTION_KEY; ?>[<?php echo (int) $i; ?>][attachment_folder_id]"
                                       value="<?php echo esc_attr($row['attachment_folder_id']); ?>">
                            </td>
                            <td><button type="button" class="ccs-icon-btn ccs-remove-row" title="Remove row">&times;</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top:16px;">
                <button type="button" class="button button-secondary" id="ccs-add-row">+ Add Job Row</button>
            </p>

            <?php submit_button('Save Mapping'); ?>
        </form>
    </div>
</div>

<table style="display:none;">
    <tbody>
        <tr id="ccs-template-row" class="ccs-mapping-row">
            <td>
                <select class="ccs-job-select" name="<?php echo CCS_MAPPING_OPTION_KEY; ?>[__INDEX__][job_slug]" required>
                    <option value="">— Select a job —</option>
                    <?php foreach ($jobs as $job) : ?>
                        <option value="<?php echo esc_attr($job['slug']); ?>"><?php echo esc_html($job['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="text" name="<?php echo CCS_MAPPING_OPTION_KEY; ?>[__INDEX__][folder_id]" value=""></td>
            <td><input type="text" name="<?php echo CCS_MAPPING_OPTION_KEY; ?>[__INDEX__][attachment_folder_id]" value=""></td>
            <td><button type="button" class="ccs-icon-btn ccs-remove-row" title="Remove row">&times;</button></td>
        </tr>
    </tbody>
</table>