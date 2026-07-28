<?php
defined('ABSPATH') || exit;

$all_entries = CCS_Log_Viewer::get_recent_entries();
?>
<div class="ccs-card ccs-table-card">
    <div class="ccs-card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <span>Sync Logs</span>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;"
              onsubmit="return confirm('Clear all logs? This cannot be undone.');">
            <input type="hidden" name="action" value="ccs_clear_logs">
            <?php wp_nonce_field('ccs_clear_logs'); ?>
            <button type="submit" class="button">Clear Logs</button>
        </form>
    </div>
    <div class="ccs-card-body">

        <?php if (isset($_GET['cleared'])) : ?>
            <div class="notice notice-success is-dismissible"><p>Logs cleared.</p></div>
        <?php endif; ?>

        <p>
            <label for="ccs-log-filter">Filter:</label>
            <select id="ccs-log-filter">
                <option value="">All sources</option>
                <?php foreach (CCS_Log_Viewer::LOG_FILES as $key => $info) : ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($info['label']); ?></option>
                <?php endforeach; ?>
            </select>

            <input type="search" id="ccs-log-search" placeholder="Search logs…" style="margin-left:10px; width:260px;">
        </p>

        <table class="ccs-table" id="ccs-log-table">
            <thead>
                <tr>
                    <th style="width:160px;">Time</th>
                    <th style="width:140px;">Source</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_entries)) : ?>
                    <tr><td colspan="3">No log entries yet.</td></tr>
                <?php else : ?>
                    <?php foreach ($all_entries as $entry) :
                        $msg = strtolower($entry['message']);
                        if (strpos($msg, 'failed') !== false || strpos($msg, 'error') !== false) {
                            $dot = 'ccs-dot-red';
                        } elseif (strpos($msg, 'skip') !== false) {
                            $dot = 'ccs-dot-yellow';
                        } else {
                            $dot = 'ccs-dot-green';
                        }
                    ?>
                        <tr class="ccs-log-row" data-source="<?php echo esc_attr($entry['source']); ?>">
                            <td><?php echo esc_html($entry['time']); ?></td>
                            <td><?php echo esc_html(CCS_Log_Viewer::LOG_FILES[$entry['source']]['label']); ?></td>
                            <td><span class="ccs-log-dot <?php echo $dot; ?>"></span><?php echo esc_html($entry['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="ccs-log-pagination">
            <button type="button" class="button" id="ccs-log-prev">&laquo; Prev</button>
            <span id="ccs-log-page-info">Page 1</span>
            <button type="button" class="button" id="ccs-log-next">Next &raquo;</button>
        </div>

        <p class="description">Showing the most recent <?php echo (int) CCS_Log_Viewer::MAX_LINES; ?> entries across all sources.</p>
    </div>
</div>