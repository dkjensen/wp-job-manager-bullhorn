<?php
/**
 * Main WP_Job_Manager_Bullhorn class file
 * 
 * @package WP Job Manager - Bullhorn Integration
 */


namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Recruiter {

    /**
	 * Plugin object
	 */
    private static $instance;


    /**
     * OAuth adapter
     *
     * @var mixed
     */
    public $oauth;


    /**
     * Webhooks handling class
     *
     * @var Webhooks
     */
    public $webhooks;


    /**
     * Logger class
     *
     * @var Log
     */
    public $log;


    /**
     * Provider client
     *
     * @var Client
     */
    public $clients;

    
    /**
     * Insures that only one instance of WP_Job_Manager_Bullhorn exists in memory at any one time.
     * 
     * @return Recruiter The one true instance of Recruiter
     */
    public static function instance() {
        if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WP_Job_Manager_Bullhorn ) ) {
            self::$instance = new Recruiter;
            self::$instance->includes();

            self::$instance->oauth          = new Provider\BullhornProvider( array(
                'clientId'       => get_option( 'bullhorn_client_id' ),
                'clientSecret'   => get_option( 'bullhorn_client_secret' ),
                'redirectUri'    => admin_url( 'edit.php?post_type=job_listing&page=job-manager-settings' )
            ) );

            self::$instance->clients        = array(
                'bullhorn'  => new Client( new Adapter\BullhornAdapter( self::$instance->oauth ) ),
            );
            self::$instance->webhooks       = new Webhooks;
            self::$instance->log            = new Log;

            do_action_ref_array( 'wp_job_manager_bullhorn_loaded', self::$instance ); 
        }
        
        return self::$instance;
    }


    /**
     * Include the goodies
     *
     * @return void
     */
    public function includes() {
        require_once WP_JOB_MANAGER_BULLHORN_PLUGIN_DIR . 'includes/class-applications.php';
        require_once WP_JOB_MANAGER_BULLHORN_PLUGIN_DIR . 'includes/class-webhooks.php';
        require_once WP_JOB_MANAGER_BULLHORN_PLUGIN_DIR . 'includes/cron-functions.php';
        require_once WP_JOB_MANAGER_BULLHORN_PLUGIN_DIR . 'includes/wp-job-manager-bullhorn-functions.php';

        if ( is_admin() ) {
            require_once WP_JOB_MANAGER_BULLHORN_PLUGIN_DIR . 'includes/admin/class-settings.php';
        }
    }


    /**
     * Throw error on object clone
     *
     * @return void
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wp-job-manager-bullhorn' ), '1.0.0' );
    }


    /**
     * Disable unserializing of the class
     * 
     * @return void
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wp-job-manager-bullhorn' ), '1.0.0' );
    }
}
