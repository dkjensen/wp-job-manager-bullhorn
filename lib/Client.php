<?php
/**
 * Bullhorn API client wrapper
 * 
 * @package WP Job Manager - Bullhorn Integration
 */


namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Client {


    private $adapter;


    public function __construct( Adapter\Adapter $adapter ) {
        $this->adapter = $adapter;
    }


    public function connected() {
        return $this->adapter->connected();
    }


    public function login() {
        return $this->adapter->login();
    }


    public function adapter() {
        return $this->adapter;
    }


    public function get_job() {
        return $this->adapter->get_job();
    }


    public function get_jobs() {
        return $this->adapter->get_jobs();
    }

    
    public function post_job_application( $job_id, $data, $application_id ) {
        return $this->adapter->post_job_application( $job_id, $data, $application_id );
    }


    public function sync_jobs() {
        $jobs = $this->adapter->sync_jobs();

        foreach ( $jobs as $job_postdata ) {
            $existing = $this->adapter->job_exists( $job_postdata['meta_input']['_jid'] );
            $filled   = $job_postdata['meta_input']['_filled'];

            if ( ! $filled ) {
                if ( false !== $existing ) {
                    $job_postdata['ID'] = $existing;

                    wp_update_post( $job_postdata );
                } else {
                    $inserted = wp_insert_post( $job_postdata );

                    if ( ! $inserted ) {
                        Log::error( 'Error creating job listing from Bullhorn', $job_postdata );
                    }
                }
            } elseif ( $filled && $existing ) {
                update_post_meta( $existing, '_filled', 1 );
            }
        }
    }
}
