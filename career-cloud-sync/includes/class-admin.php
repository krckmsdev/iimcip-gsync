<?php
defined('ABSPATH') || exit;


class CCS_Admin {

    const OPTION_KEY  = 'ccs_settings';
    const STATUS_KEY  = 'ccs_connection_status';
    const PAGE_SLUG   = 'career-cloud-sync';

    const ICON_RELATIVE_PATH = 'assets/images/google-icon.png';

    /** @var string The exact page hook suffix returned by add_menu_page(). */
    private $page_hook = '';

    public function __construct() {
        // Settings + AJAX
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_ccs_test_connection', array($this, 'ajax_test_connection'));

        // Menu + assets
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_head', array($this, 'print_menu_icon_style'));
    }

    /* =========================================================
     * Settings: registration, sanitization
     * ========================================================= */

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

        // Any credential actually changed? The old "Connected" status is
        // now a lie — wipe it so the UI shows "Not tested yet" instead of
        // a stale green pill, until the auto-retest below confirms it.
        $credentials_changed = (
            $output['client_id']     !== (isset($existing['client_id']) ? $existing['client_id'] : '') ||
            $output['client_secret'] !== (isset($existing['client_secret']) ? $existing['client_secret'] : '') ||
            $output['refresh_token'] !== (isset($existing['refresh_token']) ? $existing['refresh_token'] : '')
        );

        if ($credentials_changed) {
            delete_option(self::STATUS_KEY);
        }

        return $output;
    }
 

    /* =========================================================
     * AJAX: Test Google Connection
     * ========================================================= */

    public function ajax_test_connection() {
        check_ajax_referer('ccs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $token = CCS_Google_Drive::get_access_token();
        $now   = current_time('timestamp');

        if ($token) {
            update_option(self::STATUS_KEY, array('ok' => true, 'time' => $now));
            wp_send_json_success(array(
                'message'  => 'Connected',
                'verified' => date('M j, Y H:i', $now),
            ));
        }

        update_option(self::STATUS_KEY, array('ok' => false, 'time' => $now));
        wp_send_json_error(array(
            'message'  => 'Connection failed — check credentials and Sync Logs for details.',
            'verified' => date('M j, Y H:i', $now),
        ));
    }

    /* =========================================================
     * Menu, assets, icon
     * ========================================================= */

    public function menu() {
        $icon_path = CCS_PATH . self::ICON_RELATIVE_PATH;
        $icon      = file_exists($icon_path) ? CCS_URL . self::ICON_RELATIVE_PATH : 'dashicons-google';

        $this->page_hook = add_menu_page(
            'Career Cloud Sync',
            'Career Cloud Sync',
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'render_page'),
            $icon
        );
    }

    /**
     * Exact match against the stored hook suffix — loads ONLY on this
     * plugin's own page, never elsewhere in wp-admin.
     */
    public function enqueue_assets($hook) {
        if ($hook !== $this->page_hook) {
            return;
        }

        wp_enqueue_style('ccs-admin-style', CCS_URL . 'assets/css/ccs-admin.css', array(), CCS_VERSION);

        wp_enqueue_script('ccs-admin-script', CCS_URL . 'assets/js/ccs-admin.js', array('jquery'), CCS_VERSION, true);
        wp_localize_script('ccs-admin-script', 'ccsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ccs_admin_nonce'),
        ));
    }

    /**
     * Sizes the custom menu icon. Runs on admin_head (every admin page,
     * since the sidebar is global) — NOT on enqueue_assets, which only
     * fires on our own plugin page.
     */
    public function print_menu_icon_style() {
        $icon_path = CCS_PATH . self::ICON_RELATIVE_PATH;
        if (!file_exists($icon_path)) {
            return;
        }
        ?>
        <style>
            #adminmenu .toplevel_page_<?php echo esc_attr(self::PAGE_SLUG); ?> .wp-menu-image img {
                width: 20px;
                height: 20px;
                object-fit: contain;
                padding: 0;
                margin-top: 7px;
            }
        </style>
        <?php
    }

    /* =========================================================
     * Page render — hero, tabs, and the three view partials
     * ========================================================= */

    private function is_connected() {
        $status = get_option(self::STATUS_KEY, array());
        return !empty($status['ok']);
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $connected  = $this->is_connected();
        $status     = get_option(self::STATUS_KEY, array());
        $has_status = !empty($status);
        $last_check = $has_status ? date('M j, Y H:i', $status['time']) : 'Never checked';
        $pill_class = $has_status ? ($connected ? 'ccs-pill-green' : 'ccs-pill-red') : 'ccs-pill-gray';
        $status_txt = $has_status ? ($connected ? 'Connected' : 'Connection Failed') : 'Not tested yet';
        ?>
        <div class="wrap ccs-wrap">

            <div class="ccs-hero">
                <div class="ccs-hero-left">
                    <?php echo self::google_logo_svg(); ?>
                    <div>
                        <p class="ccs-hero-title">Career Cloud Sync</p>
                        <p class="ccs-hero-subtitle">Google Drive &amp; Sheets sync for job applications</p>
                    </div>
                </div>
                <span id="ccs-status-pill" class="ccs-pill <?php echo esc_attr($pill_class); ?>">
                    <span class="ccs-pill-dot"></span>
                    <span id="ccs-status-text"><?php echo esc_html($status_txt); ?></span>
                </span>
            </div>

            <nav class="ccs-tabs">
                <a href="#settings" class="ccs-tab is-active" data-tab="settings">Settings</a>
                <a href="#mapping" class="ccs-tab <?php echo $connected ? '' : 'is-disabled'; ?>" data-tab="mapping"
                   <?php echo $connected ? '' : 'data-locked="1" title="Connect Google in Settings first"'; ?>>
                    Folder Mapping
                </a>
                <a href="#logs" class="ccs-tab" data-tab="logs">Sync Logs</a>
            </nav>

            <div class="ccs-tab-panel is-active" data-panel="settings">
                <?php require CCS_PATH . 'views/view-settings.php'; ?>
            </div>

            <div class="ccs-tab-panel" data-panel="mapping">
                <?php require CCS_PATH . 'views/view-mapping.php'; ?>
            </div>

            <div class="ccs-tab-panel" data-panel="logs">
                <?php require CCS_PATH . 'views/view-logs.php'; ?>
            </div>

        </div>
        <?php
    }

    /**
     * Google's official 4-color "G" mark, inline so there's no external
     * request and no dependency on an icon font being loaded.
     */
    public static function google_logo_svg($size = 34) {
        return '<svg class="ccs-hero-logo" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <path fill="#4285F4" d="M45.1 24.5c0-1.6-.15-3.15-.42-4.64H24v9.02h11.84c-.51 2.77-2.07 5.11-4.4 6.68v5.53h7.11c4.16-3.83 6.55-9.48 6.55-16.6z"/>
            <path fill="#34A853" d="M24 46c5.94 0 10.92-1.97 14.56-5.32l-7.11-5.53c-1.97 1.32-4.5 2.1-7.45 2.1-5.73 0-10.58-3.87-12.31-9.07H4.34v5.7C7.96 41.07 15.4 46 24 46z"/>
            <path fill="#FBBC05" d="M11.69 28.18A13.9 13.9 0 0 1 11 24c0-1.45.25-2.86.69-4.18v-5.7H4.34A21.9 21.9 0 0 0 2 24c0 3.55.85 6.9 2.34 9.88l7.35-5.7z"/>
            <path fill="#EA4335" d="M24 10.75c3.23 0 6.13 1.11 8.41 3.29l6.31-6.31C34.9 3.99 29.94 2 24 2 15.4 2 7.96 6.93 4.34 14.12l7.35 5.7c1.73-5.2 6.58-9.07 12.31-9.07z"/>
        </svg>';
    }
}