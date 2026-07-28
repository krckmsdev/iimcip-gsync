<?php
defined('ABSPATH') || exit;
/** @var none — this view fetches its own data; it's included by CCS_Admin_Menu::render_page() */

$settings = get_option(CCS_Admin::OPTION_KEY, array());
$status   = get_option(CCS_Admin::STATUS_KEY, array());

$just_saved = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true';

$client_id   = isset($settings['client_id']) ? $settings['client_id'] : '';
$form_id     = isset($settings['form_id']) ? $settings['form_id'] : '';
$has_secret  = !empty($settings['client_secret']);
$has_refresh = !empty($settings['refresh_token']);

$has_status = !empty($status);
$is_ok      = $has_status && !empty($status['ok']);
$last_check = $has_status ? date('M j, Y H:i', $status['time']) : 'Never checked';
?>
<div class="ccs-grid">

    <div class="ccs-col-4 ccs-card">
        <div class="ccs-card-header">Connection Status</div>
        <div class="ccs-card-body">
            <p class="ccs-eyebrow">Last verified</p>
            <p id="ccs-status-time-inline" style="margin-top:0;"><?php echo esc_html($last_check); ?></p>
            <p>
               
                <button type="button" class="button button-secondary" id="ccs-test-connection" data-auto-test="<?php echo $just_saved ? '1' : '0'; ?>">Test Google Connection</button>
                <span id="ccs-test-spinner" class="spinner" style="float:none;"></span>

            </p>
            <p class="description">This only checks the connection — it never changes or clears your saved credentials.</p>
        </div>
    </div>

    <div class="ccs-col-8 ccs-card">
        <div class="ccs-card-header">Google API Settings</div>
        <div class="ccs-card-body">
            <form method="post" action="options.php">
                <?php settings_fields('ccs_settings_group'); ?>
                <table class="form-table">

                    <tr>
                        <th scope="row"><label for="ccs_client_id">Google Client ID</label></th>
                        <td>
                            <input type="text" id="ccs_client_id" name="<?php echo CCS_Admin::OPTION_KEY; ?>[client_id]"
                                   value="<?php echo esc_attr($client_id); ?>" class="regular-text" autocomplete="off">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="ccs_client_secret">Google Client Secret</label></th>
                        <td>
                            <input type="password" id="ccs_client_secret" name="<?php echo CCS_Admin::OPTION_KEY; ?>[client_secret]"
                                   value="" class="regular-text" autocomplete="new-password"
                                   placeholder="<?php echo $has_secret ? 'Saved (hidden) — leave blank to keep it' : 'Not set yet'; ?>">
                            <p class="description">Leave blank to keep the currently saved secret. Type a new value only to replace it.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="ccs_refresh_token">Refresh Token</label></th>
                        <td>
                            <input type="password" id="ccs_refresh_token" name="<?php echo CCS_Admin::OPTION_KEY; ?>[refresh_token]"
                                   value="" class="regular-text" autocomplete="new-password"
                                   placeholder="<?php echo $has_refresh ? 'Saved (hidden) — leave blank to keep it' : 'Not set yet'; ?>">
                            <p class="description">Leave blank to keep the currently saved token. Type a new value only to replace it.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="ccs_form_id">Contact Form 7 — Form ID</label></th>
                        <td>
                            <input type="number" id="ccs_form_id" name="<?php echo CCS_Admin::OPTION_KEY; ?>[form_id]"
                                   value="<?php echo esc_attr($form_id); ?>" class="small-text" autocomplete="off">
                            <p class="description">The numeric ID of the job application form (shown next to the form title in CF7).</p>
                        </td>
                    </tr>

                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
    </div>

</div>
