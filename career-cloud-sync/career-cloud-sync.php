<?php
/**
 * Plugin Name: Career Cloud Sync
 * Plugin URI: https://iimcip.org/
 * Description: Automatically sync Contact Form 7 job applications with Google Drive and Google Sheets.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: IIMCIP
 * Author URI: https://iimcip.org/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: career-cloud-sync
 */

defined('ABSPATH') || exit;


final class Career_Cloud_Sync {
   
    const VERSION = '1.0.0';

    public function __construct() {
        
        $this->define_constants();

        add_action('plugins_loaded', [$this, 'init']);

        require_once CCS_PATH . 'includes/integrations/class-google-drive.php';
        require_once CCS_PATH . 'includes/integrations/class-google-sheets.php';

        require_once CCS_PATH . 'includes/integrations/class-cf7-apply-form.php';
        $CCS_CF7 = new CCS_CF7();

        require_once CCS_PATH . 'includes/folder-mapping.php';

        require_once CCS_PATH . 'includes/class-log-viewer.php';
        $CCS_Log_Viewer = new CCS_Log_Viewer();


        require_once CCS_PATH . 'includes/class-admin.php';
        $CCS_Admin = new CCS_Admin();

    }

    private function define_constants()
    {
        define('CCS_VERSION', self::VERSION);
        define('CCS_FILE', __FILE__);
        define('CCS_PATH', plugin_dir_path(__FILE__));
        define('CCS_URL', plugin_dir_url(__FILE__));
    }



 
    public function init() {
        // Plugin init
    }

    public function activate() {
      
    }

    public function deactivate() {
       
    }

    public static function uninstall() {
        // Cleanup
    }
}

if(class_exists('Career_Cloud_Sync')){

    $career_cloud_sync = new Career_Cloud_Sync();

    register_activation_hook(CCS_FILE, [$career_cloud_sync, 'activate']);
    register_deactivation_hook(CCS_FILE, [$career_cloud_sync, 'deactivate']);
    register_uninstall_hook(CCS_FILE, ['Career_Cloud_Sync', 'uninstall']);
}