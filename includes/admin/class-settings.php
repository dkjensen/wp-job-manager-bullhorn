<?php
/**
 * Admin settings
 * 
 * @package WP Job Manager - Bullhorn Integration
 */


namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Settings {

    /**
     * Are we connected to the Bullhorn API?
     * 
     * @var boolean
     */
    public $connected = false;


    public function __construct() {
        add_action( 'admin_init', array( $this, 'init_settings' ) );

        add_filter( 'job_manager_settings', array( $this, 'settings' ) );

        // Authorization field callback
        add_action( 'wp_job_manager_admin_field_bullhorn_setup', array( $this, 'setup_field_callback' ), 10, 4 );
        add_action( 'wp_job_manager_admin_field_bullhorn_authorization', array( $this, 'bullhorn_authorization_field_callback' ), 10, 4 );

        add_action( 'job_manager_bullhorn_settings', array( $this, 'bullhorn_authorization' ) );
        add_action( 'job_manager_bullhorn_settings', array( $this, 'bullhorn_deauthorization' ) );
        add_action( 'admin_init', array( $this, 'bullhorn_sync_jobs' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
    }


    /**
     * WP Job Manager settings
     *
     * @param array $settings
     * @return array
     */
    public function settings( $settings ) {
        $settings = (array) $settings;
        $settings['bullhorn'] = array(
            __( 'Bullhorn', 'wp-job-manager-bullhorn' ),
            array(
                array(
                    'name'          => 'bullhorn_client_id',
                    'label'         => __( 'Bullhorn Client ID', 'wp-job-manager-bullhorn' ),
                    'type'          => 'text',
                ),
                array(
                    'name'          => 'bullhorn_client_secret',
                    'label'         => __( 'Bullhorn Client Secret', 'wp-job-manager-bullhorn' ),
                    'type'          => 'password',
                ),
                array(
                    'name'          => 'bullhorn_api_username',
                    'label'         => __( 'Bullhorn API Username', 'wp-job-manager-bullhorn' ),
                    'type'          => 'text',
                ),
                array(
                    'name'          => 'bullhorn_api_password',
                    'label'         => __( 'Bullhorn API Password', 'wp-job-manager-bullhorn' ),
                    'type'          => 'password',
                ),
                array(
                    'name'          => 'bullhorn_authorization',
                    'label'         => __( 'Bullhorn Authorization', 'wp-job-manager-bullhorn' ),
                    'type'          => 'bullhorn_authorization',
                ),
                array(
                    'name'          => 'bullhorn_applications',
                    'label'         => __( 'Post Applications to Bullhorn', 'wp-job-manager-bullhorn' ),
                    'type'          => 'checkbox',
                    'cb_label'      => __( 'Job applications submitted via the WP Job Manager - Applications plugin will be sent to Bullhorn', 'wp-job-manager-bullhorn' )
                )
            ),
            array(
                'before' => sprintf( __( 'Authorized redirect URI: <code>%1$s</code>', 'wp-job-manager-bullhorn' ), admin_url( 'edit.php?post_type=job_listing&page=job-manager-settings' ) ), 
                'after' => sprintf( '<a href="%s">%s</a>', wp_nonce_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-settings&sync=bullhorn' ) ), __( 'Sync now', 'wp-job-manager-bullhorn' ) )
            ),
        );

        return $settings;
    }
 

    public function bullhorn_authorization_field_callback( $option, $attributes, $value, $placeholder ) {
        if ( $this->connected ) :
        ?>

        <p>
            <a href="<?php print wp_nonce_url( add_query_arg( array( 'state' => 'bullhorn-deauthorization' ), admin_url( 'edit.php?post_type=job_listing&page=job-manager-settings' ) ) ); ?>" class="button button-error"><?php _e( 'Disconnect', 'wp-job-manager-bullhorn' ); ?></a>
        </p>

        <?php else :
            $authorization_url = WP_Job_Manager_Bullhorn()->oauth->getAuthorizationUrl();

            update_option( 'bullhorn_oauth_state', WP_Job_Manager_Bullhorn()->oauth->getState() );
            ?>

        <p>
            <a href="<?php print esc_url( $authorization_url ); ?>" class="button button-primary">
                <?php _e( 'Connect with Bullhorn', 'wp-job-manager-bullhorn' ); ?>
            </a>
        </p>

        <?php 
        endif;
    }


    public function init_settings() {
        if ( isset( $_GET['state'] ) && $_GET['state'] == get_option( 'bullhorn_oauth_state' ) && isset( $_GET['code'] ) && current_user_can( 'manage_options' ) ) {
            try {
                $authorization = WP_Job_Manager_Bullhorn()->oauth->get_access_token( $_GET['code'] );
            } catch ( \Exception $e ) {
                $authorization = new WP_Error( 'bullhorn_rest_api_error', $e->getMessage() );
            }

            if ( ! is_wp_error( $authorization ) ) {
                $login = WP_Job_Manager_Bullhorn()->clients['bullhorn']->login();

                if ( ! is_wp_error( $login ) ) {
                    wp_redirect( admin_url( 'edit.php?post_type=job_listing&page=job-manager-settings&connected=true#settings-bullhorn' ) );
                    exit;
                }
            }

            add_action( 'admin_notices', function() use ( $authorization ) {
                ?>

                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e( $authorization->get_error_message(), 'wp-job-manager-bullhorn' ); ?></p>
                </div>

                <?php
            } );
        }

        if ( current_user_can( 'manage_options' ) && is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
            if ( isset( $_GET['page'] ) && $_GET['page'] == 'job-manager-settings' ) {
                $this->connected = WP_Job_Manager_Bullhorn()->clients['bullhorn']->connected();

                if ( ! $this->connected ) {
                }

                do_action( 'job_manager_bullhorn_settings' );
            }
        }

        return false;
    }


    public function bullhorn_authorization() {

    }


    public function bullhorn_sync_jobs() {
        if ( isset( $_GET['sync'] ) && $_GET['sync'] == 'bullhorn' && wp_verify_nonce( $_GET['_wpnonce'] ) && current_user_can( 'manage_options' ) ) {
            do_action( 'job_manager_bullhorn_sync_jobs' );
        }
    }


    public function bullhorn_deauthorization() {
        if ( isset( $_GET['state'] ) && $_GET['state'] == 'bullhorn-deauthorization' && wp_verify_nonce( $_GET['_wpnonce'] ) && current_user_can( 'manage_options' ) ) {
            delete_option( 'bullhorn_client_id' );
            delete_option( 'bullhorn_client_secret' );
            delete_option( 'bullhorn_api_username' );
            delete_option( 'bullhorn_api_password' );
            delete_option( 'job_manager_bullhorn_token' );
            delete_option( 'bullhorn_rest_url' );
            delete_option( 'bullhorn_rest_token' );

            wp_cache_flush();

            wp_redirect( admin_url( 'edit.php?post_type=job_listing&page=job-manager-settings' ) );
            exit;
        }
    }


    public function scripts() {
        wp_enqueue_style( 'wp-job-manager-bullhorn-admin', WP_JOB_MANAGER_BULLHORN_PLUGIN_URL . '/assets/css/admin.min.css', array(), WP_JOB_MANAGER_BULLHORN_VER );

        wp_register_script( 'wp-job-manager-bullhorn-admin', WP_JOB_MANAGER_BULLHORN_PLUGIN_URL . '/assets/js/admin.min.js', array( 'jquery' ), WP_JOB_MANAGER_BULLHORN_VER, true );

        wp_localize_script( 'wp-job-manager-bullhorn-admin', 'job_manager_bullhorn', array(
            'application_form_column_bullhorn_label'    => __( 'Field', 'wp-job-manager-bullhorn' ),
            'application_form_fields'                   => get_option( 'job_application_form_fields' ),
            'application_clients'                       => array_keys( WP_Job_Manager_Bullhorn()->clients ),
            'application_client_fields'                 => array(
                'bullhorn'  => array(
                    'name'                  => __( 'Full name', 'wp-job-manager-bullhorn' ),
                    'firstName'             => __( 'First name', 'wp-job-manager-bullhorn' ),
                    'lastName'              => __( 'Last name', 'wp-job-manager-bullhorn' ),
                    'email'                 => __( 'Email', 'wp-job-manager-bullhorn' ),
                    'phone'                 => __( 'Phone', 'wp-job-manager-bullhorn' ),
                    'mobile'                => __( 'Mobile', 'wp-job-manager-bullhorn' ),
                    'address:address1'      => __( 'Address 1', 'wp-job-manager-bullhorn' ),
                    'address:address2'      => __( 'Address 2', 'wp-job-manager-bullhorn' ),
                    'address:city'          => __( 'City', 'wp-job-manager-bullhorn' ),
                    'address:state'         => __( 'State', 'wp-job-manager-bullhorn' ),
                    'address:zip'           => __( 'ZIP', 'wp-job-manager-bullhorn' ),
                    'address:countryID'     => __( 'Country code', 'wp-job-manager-bullhorn' ),
                    'Resume'                => __( 'Resume / CV', 'wp-job-manager-bullhorn' ),
                )
            )
        ) );

        wp_enqueue_script( 'wp-job-manager-bullhorn-admin' );
    }

}

return new Settings;
