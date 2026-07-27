<?php
defined('ABSPATH') || exit;

class CCS_Admin {

    const OPTION_KEY = 'ccs_settings';

    public function __construct() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function menu() {
        add_menu_page(
            'Career Cloud Sync',
            'Career Cloud Sync',
            'manage_options',
            'career-cloud-sync',
            array($this, 'page'),
            'dashicons-google'
        );
    }

    public function register_settings() {
        register_setting(
            'ccs_settings_group',
            self::OPTION_KEY,
            array('sanitize_callback' => array($this, 'sanitize'))
        );
    }

    public function sanitize($input) {
        $existing = get_option(self::OPTION_KEY, array());

        $output = array();
        $output['client_id']        = isset($input['client_id']) ? sanitize_text_field($input['client_id']) : '';
        $output['parent_folder_id'] = isset($input['parent_folder_id']) ? sanitize_text_field($input['parent_folder_id']) : '';
        $output['form_id']          = isset($input['form_id']) ? absint($input['form_id']) : 0;

        $output['client_secret'] = !empty($input['client_secret'])
            ? sanitize_text_field($input['client_secret'])
            : (isset($existing['client_secret']) ? $existing['client_secret'] : '');

        $output['refresh_token'] = !empty($input['refresh_token'])
            ? sanitize_text_field($input['refresh_token'])
            : (isset($existing['refresh_token']) ? $existing['refresh_token'] : '');

        return $output;
    }

    public function page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = get_option(self::OPTION_KEY, array());

        $client_id        = isset($settings['client_id']) ? $settings['client_id'] : '';
        $parent_folder_id = isset($settings['parent_folder_id']) ? $settings['parent_folder_id'] : '';
        $form_id          = isset($settings['form_id']) ? $settings['form_id'] : '';
        $has_secret       = !empty($settings['client_secret']);
        $has_refresh      = !empty($settings['refresh_token']);
        ?>
        <div class="wrap">
            <h1>Career Cloud Sync — Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('ccs_settings_group'); ?>
                <table class="form-table">

                    <tr>
                        <th scope="row"><label for="ccs_client_id">Google Client ID</label></th>
                        <td>
                            <input type="text" id="ccs_client_id" name="<?php echo self::OPTION_KEY; ?>[client_id]"
                                   value="<?php echo esc_attr($client_id); ?>" class="regular-text" autocomplete="off">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="ccs_client_secret">Google Client Secret</label></th>
                        <td>
                            <input type="password" id="ccs_client_secret" name="<?php echo self::OPTION_KEY; ?>[client_secret]"
                                   value="" class="regular-text" autocomplete="new-password"
                                   placeholder="<?php echo $has_secret ? 'Saved (hidden) — leave blank to keep it' : 'Not set yet'; ?>">
                            <p class="description">Leave blank to keep the currently saved secret. Type a new value only to replace it.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="ccs_refresh_token">Refresh Token</label></th>
                        <td>
                            <input type="password" id="ccs_refresh_token" name="<?php echo self::OPTION_KEY; ?>[refresh_token]"
                                   value="" class="regular-text" autocomplete="new-password"
                                   placeholder="<?php echo $has_refresh ? 'Saved (hidden) — leave blank to keep it' : 'Not set yet'; ?>">
                            <p class="description">Leave blank to keep the currently saved token. Type a new value only to replace it.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="ccs_parent_folder_id">Google Drive Parent Folder ID</label></th>
                        <td>
                            <input type="text" id="ccs_parent_folder_id" name="<?php echo self::OPTION_KEY; ?>[parent_folder_id]"
                                   value="<?php echo esc_attr($parent_folder_id); ?>" class="regular-text" autocomplete="off">
                            <p class="description">The ID from the Drive folder URL: drive.google.com/drive/folders/<strong>THIS_PART</strong></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="ccs_form_id">Contact Form 7 — Form ID</label></th>
                        <td>
                            <input type="number" id="ccs_form_id" name="<?php echo self::OPTION_KEY; ?>[form_id]"
                                   value="<?php echo esc_attr($form_id); ?>" class="small-text" autocomplete="off">
                            <p class="description">The numeric ID of the job application form (shown next to the form title in CF7).</p>
                        </td>
                    </tr>

                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }
}