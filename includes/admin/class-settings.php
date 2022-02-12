<?php
/**
 * Admin settings
 * 
 * @package WP Job Manager - Bullhorn Integration
 */


namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn;

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
        add_action( 'wp_job_manager_admin_field_bullhorn_job_boards', array( $this, 'job_boards_field_callback' ), 10, 4 );

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
                    'name'          => 'bullhorn_authorization',
                    'label'         => __( 'Bullhorn Authorization', 'wp-job-manager-bullhorn' ),
                    'type'          => 'bullhorn_authorization',
                ),
                array(
                    'name'    => 'bullhorn_job_boards',
                    'label'   => __( 'Job Boards To Sync', 'wp-job-manager-bullhorn' ),
                    'type'    => 'bullhorn_job_boards',
                ),
                array(
                    'name'          => 'bullhorn_applications',
                    'label'         => __( 'Post Applications to Bullhorn', 'wp-job-manager-bullhorn' ),
                    'type'          => 'checkbox',
                    'cb_label'      => __( 'Job applications submitted via the WP Job Manager - Applications plugin will be sent to Bullhorn', 'wp-job-manager-bullhorn' )
                )
            ),
            array(
                'before' => sprintf( __( '<a href="%1$s" target="_blank">Register your Bullhorn Developers application</a> using the following value as an authorized redirect URI: <code>%2$s</code>', 'wp-job-manager-bullhorn' ), 'https://developers.bullhorn.com/partners/clients/add', admin_url( 'edit.php?post_type=job_listing&page=job-manager-settings' ) ),
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


    public function job_boards_field_callback( $option, $attributes, $value, $placeholder ) {
        if ( ! $this->connected ) {
            return;
        }
        ?>

        <fieldset>
            <legend class="screen-reader-text"><span><?php _e( 'Job Boards To Sync', 'wp-job-manager-bullhorn' ); ?></span></legend>

        <?php foreach ( WP_Job_Manager_Bullhorn()->clients['bullhorn']->adapter()->get_job_boards() as $job_board ) : ?>
            
            <label>
                <input name="bullhorn_job_boards[]"  type="checkbox" value="<?php print esc_attr( $job_board->boardId ); ?>" <?php checked( true, in_array( $job_board->boardId, WP_Job_Manager_Bullhorn()->clients['bullhorn']->adapter()->get_synced_job_boards() ) ); ?>>
                <?php print esc_html_e( $job_board->name, 'wp-job-manager-bullhorn' ); ?>
            </label><br>

        <?php endforeach; ?>

        </fieldset>

        <?php
    }


    public function init_settings() {
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
        if ( isset( $_GET['state'] ) && $_GET['state'] == get_option( 'bullhorn_oauth_state' ) && isset( $_GET['code'] ) && current_user_can( 'manage_options' ) ) {
            $authorization = WP_Job_Manager_Bullhorn()->oauth->get_access_token( $_GET['code'] );

            if ( ! is_wp_error( $authorization ) ) {
                WP_Job_Manager_Bullhorn()->cron->schedule_sync();

                wp_redirect( admin_url( 'edit.php?post_type=job_listing&page=job-manager-settings&connected=true#settings-bullhorn' ) );
                exit;
            } else {
                add_action( 'admin_notices', function() use ( $authorization ) {
                    ?>

                    <div class="notice notice-error is-dismissible">
                        <p><?php esc_html_e( $authorization->get_error_message(), 'wp-job-manager-bullhorn' ); ?></p>
                    </div>

                    <?php
                } );
            }
        }
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
            delete_option( 'job_manager_bullhorn_token' );
            delete_option( 'bullhorn_job_boards' );

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
                    'firstName'             => __( 'First name', 'wp-job-manager-bullhorn' ),
                    'lastName'              => __( 'Last name', 'wp-job-manager-bullhorn' ),
                    'salutation'            => __( 'Salutation', 'wp-job-manager-bullhorn' ),
                    'email'                 => __( 'Email', 'wp-job-manager-bullhorn' ),
                    'phone'                 => __( 'Phone', 'wp-job-manager-bullhorn' ),
                    'mobile'                => __( 'Mobile', 'wp-job-manager-bullhorn' ),
                    'address:street[]'      => __( 'Street address', 'wp-job-manager-bullhorn' ),
                    'address:city'          => __( 'City', 'wp-job-manager-bullhorn' ),
                    'address:state'         => __( 'State', 'wp-job-manager-bullhorn' ),
                    'address:postalCode'    => __( 'Postal code', 'wp-job-manager-bullhorn' ),
                    'address:countryCode'   => __( 'Country code', 'wp-job-manager-bullhorn' ),
                    'social:facebook'       => __( 'Facebook', 'wp-job-manager-bullhorn' ),
                    'social:twitter'        => __( 'Twitter', 'wp-job-manager-bullhorn' ),
                    'social:linkedin'       => __( 'LinkedIn', 'wp-job-manager-bullhorn' ),
                    'social:googleplus'     => __( 'Google Plus', 'wp-job-manager-bullhorn' ),
                    'social:youtube'        => __( 'YouTube', 'wp-job-manager-bullhorn' ),
                    'social:other'          => __( 'Other social', 'wp-job-manager-bullhorn' ),
                    'availability:date'     => __( 'Availability date', 'wp-job-manager-bullhorn' ),
                    'Resume'                => __( 'Resume', 'wp-job-manager-bullhorn' ),
                    'CoverLetter'           => __( 'Cover letter (file)', 'wp-job-manager-bullhorn' ),
                    'screening'             => __( 'Screening (file)', 'wp-job-manager-bullhorn' ),
                    'check'                 => __( 'Check (file)', 'wp-job-manager-bullhorn' ),
                    'reference'             => __( 'References (file)', 'wp-job-manager-bullhorn' ),
                    'license'               => __( 'License (file)', 'wp-job-manager-bullhorn' ),
                    'other'                 => __( 'Other (file)', 'wp-job-manager-bullhorn' )
                )
            )
        ) );

        wp_enqueue_script( 'wp-job-manager-bullhorn-admin' );
    }

}

return new Settings;
