<?php
/**
 * Cron jobs
 * 
 * @package WP Job Manager - Bullhorn Integration
 */


namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn;


function sync_jobs() {
    do_action( 'job_manager_bullhorn_before_job_sync' );

    foreach ( WP_Job_Manager_Bullhorn()->clients as $client ) {
        $client->sync_jobs();
    }

    do_action( 'job_manager_bullhorn_after_job_sync' );
}
add_action( 'job_manager_bullhorn_sync_jobs', __NAMESPACE__ . '\sync_jobs' );

function schedule_sync() {
    wp_clear_scheduled_hook( 'job_manager_bullhorn_sync_jobs' );

    wp_schedule_event( time(), 'bullhorn_sync', 'job_manager_bullhorn_sync_jobs' );
}
add_action( 'update_option_bullhorn_sync_interval', __NAMESPACE__ . '\schedule_sync' );
