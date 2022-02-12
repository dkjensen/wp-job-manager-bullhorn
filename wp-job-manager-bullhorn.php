<?php
/**
 * Plugin Name: WP Job Manager - Bullhorn Integration
 * Description: 
 * Version: 0.0.0-development
 * Author: Seattle Web Co.
 * Author URI: https://seattlewebco.com
 * Text Domain: wp-job-manager-bullhorn
 * Requires PHP: 7.2.5
 *
 * @package WP Job Manager - Bullhorn Integration
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WP_JOB_MANAGER_BULLHORN_VER', '0.0.0-development' );
define( 'WP_JOB_MANAGER_BULLHORN_PLUGIN_NAME', 'WP Job Manager - Bullhorn Integration' );
define( 'WP_JOB_MANAGER_BULLHORN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_JOB_MANAGER_BULLHORN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'WP_JOB_MANAGER_BULLHORN_LOG' ) ) {
    define( 'WP_JOB_MANAGER_BULLHORN_LOG', WP_JOB_MANAGER_BULLHORN_PLUGIN_DIR . 'logs/log-debug.log' );
}


require WP_JOB_MANAGER_BULLHORN_PLUGIN_DIR . 'vendor/autoload.php';
require WP_JOB_MANAGER_BULLHORN_PLUGIN_DIR . 'includes/class-recruiter.php';


function WP_Job_Manager_Bullhorn() {
    return \SeattleWebCo\WPJobManager\Recruiter\Bullhorn\Recruiter::instance();
}
WP_Job_Manager_Bullhorn();

register_activation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'job_manager_bullhorn_sync_jobs' );

    wp_schedule_event( time(), 'bullhorn_sync', 'job_manager_bullhorn_sync_jobs' );
} );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'job_manager_bullhorn_sync_jobs' );
} );
