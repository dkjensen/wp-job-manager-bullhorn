<?php
/**
 * API interface
 * 
 * @package WP Job Manager - Bullhorn Integration
 */


namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn\Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


interface Adapter {


    public function connected();

    
    public function get_jobs();


    public function get_job( $job_id );


    public function post_job_application( $job_id, $data, $application_id );


    public function sync_jobs();


    public function job_exists( $job_id );

}
