<?php
/**
 * Bullhorn API adapter
 * 
 * @package WP Job Manager - Bullhorn Integration
 */


namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn\Adapter;

use SeattleWebCo\WPJobManager\Recruiter\Bullhorn\Exception;
use League\OAuth2\Client\Provider\AbstractProvider;
use SeattleWebCo\WPJobManager\Recruiter\Bullhorn\Log;


class BullhornAdapter implements Adapter {

    private $access_token;


    public function __construct( AbstractProvider $oauth ) {
        $this->oauth        = $oauth;
        $this->access_token = $oauth->get_access_token();
    }


    public function connected() {
        return $this->get_job_boards();
    }


    public function get_job_boards() {
        $job_boards = $this->request( 'GET', 'jobboards' );

        if ( ! is_wp_error( $job_boards ) ) {
            return $job_boards->items;
        }

        return false;
    }


    public function get_job_ads( $job_board ) {
        $job_ads = $this->request( 'GET', 'jobboards/' . $job_board . '/ads?fields=description,portal.fields' );

        if ( ! is_wp_error( $job_ads ) ) {
            return $job_ads->items;
        }

        return false;
    }


    public function get_jobs() {
        $jobs = array();

        foreach ( $this->get_synced_job_boards() as $job_board ) {
            $job_ads = $this->get_job_ads( $job_board );

            if ( $job_ads ) {
                foreach ( $job_ads as $job_ad ) {
                    $jobs[] = $this->get_job( $job_ad->reference );
                }
            }
        }

        return $jobs;
    }

    /*
    public function post_job() {
        $job = $this->request( 'POST', 'jobs', array(
            'jobTitle'      => rand( 10000, 99999 )
        ) );

        if ( ! is_wp_error( $job ) ) {
            $ref = $job->jobId;
            $owner = $job->createdBy->userId;

            $job_ad = $this->request( 'POST', 'jobads', array(
                'title'     => rand( 10000, 99999 ),
                'reference' => $ref,
                'ownerUserId' => $owner
            ) );
        }
    }
    */


    public function get_synced_job_boards() {
        return array_filter( (array) get_option( 'bullhorn_job_boards' ) );
    }
    
    
    public function sync_jobs() {
        $jobs = array();

        foreach ( $this->get_synced_job_boards() as $job_board ) {
            $job_ads = $this->get_job_ads( $job_board );

            if ( $job_ads ) {
                foreach ( $job_ads as $job_ad ) {
                    $job = $this->get_job( $job_ad->reference );

                    if ( empty( $job->jobId ) ) {
                        continue;
                    }

                    $category = $subcategory = $job_type = null;

                    $classifications = $job_ad->portal->fields ?? [];

                    if ( $classifications ) {
                        foreach ( $classifications as $classification ) {
                            if ( strpos( strtolower( $classification->fieldName ), 'type' ) !== false ) {
                                $job_type = $classification->value;
                            }

                            if ( strpos( strtolower( $classification->fieldName ), 'category' ) !== false ) {
                                $categories = $classification;
                            }
                        }

                        if ( isset( $categories ) && isset( $categories->value ) ) {
                            $category = get_term_by( 'name', $categories->value, 'job_listing_category', ARRAY_A );
    
                            if ( ! $category ) {
                                $category = wp_insert_term( $categories->value, 'job_listing_category' );
                            }

                            if ( $category && ! is_wp_error( $category ) && isset( $categories->fields ) ) {
                                foreach ( $categories->fields as $field ) {
                                    if ( strpos( strtolower( $field->fieldName ), 'sub category' ) !== false ) {
                                        $_subcategory = get_term_by( 'name', $field->value, 'job_listing_category', ARRAY_A );
            
                                        if ( ! $subcategory ) {
                                            $_subcategory = wp_insert_term( $field->value, 'job_listing_category', array( 'parent' => $category['term_id'] ) );
                                        }

                                        if ( $_subcategory && ! is_wp_error( $_subcategory ) ) {
                                            $subcategory = $_subcategory;
                                        }

                                        break;
                                    }
                                }
                            }
                        }
                    }

                    if ( $job_type ) {
                        switch ( $job_type ) {
                            case 'Contract or Temp' :
                                $job_type = 'Contract';
                                break;
                        }
                
                        $job_type = get_term_by( 'name', $job_type, 'job_listing_type', ARRAY_A );
                    }

                    $category    = apply_filters( 'wp_job_manager_bullhorn_category', $category, $job );
                    $subcategory = apply_filters( 'wp_job_manager_bullhorn_subcategory', $subcategory, $job );
                    $job_type    = apply_filters( 'wp_job_manager_bullhorn_job_type', $job_type, $job );

                    $jobs[] = array(
                        'post_title' 		=> isset( $job->jobTitle ) ? $job->jobTitle : __( 'Untitled job', 'wp-job-manager-bullhorn' ),
                        'post_content' 		=> isset( $job->jobDescription ) ? $job->jobDescription : '',
                        'post_status'		=> 'publish',
                        'post_type'			=> 'job_listing',
                        'tax_input'         => array(
                            'job_listing_category' => array_filter( array( $category['term_id'], $subcategory['term_id'] ) ),
                            'job_listing_type' => array_filter( array( $job_type['term_id'] ) ),
                        ),
                        'meta_input'		=> array(
                            '_jid'			        => $job->jobId,
                            '_jobadid'              => $job_ad->adId,
                            '_job_boardid'          => $job_board,
                            '_job_salary'           => isset( $job->salary ) ? trim( job_manager_bullhorn_format_salary( $job->salary ), " \n\r\t\v\x00/" ) : '',
                            '_job_salary_period'    => isset( $job->salary ) && isset( $job->salary->ratePer ) ? $job->salary->ratePer : '',
                            '_job_location'         => isset( $job->location ) && isset( $job->location->name ) ? $job->location->name : '',
                            '_job_expires'          => isset( $job_ad->expiresAt ) ? date( 'Y-m-d', strtotime( $job_ad->expiresAt ) ) : '',
                            '_application'          => isset( $job->contact ) && isset( $job->contact->email ) ? $job->contact->email : get_option( 'admin_email' ),
                            '_company_name'         => get_option( 'blogname' ),
                            '_filled'               => isset( $job->status ) && isset( $job->status->active ) && $job->status->active ? 0 : 1,
                            '_imported_from'        => 'bullhorn',
                        ),
                    );
                }
            }
        }

        return $jobs;
    }


    public function job_exists( $id ) {
        global $wpdb;

        $exists = $wpdb->get_var( $wpdb->prepare( "
            SELECT post_id 
            FROM   $wpdb->postmeta 
            WHERE  meta_key = '_jid'
            AND    meta_value = '%s' 
            LIMIT  1", $id 
        ) );

        if ( ! $exists ) {
            return false;
        }

        return $exists;
    }


    public function get_job( $job ) {
        $job = $this->request( 'GET', 'jobs/' . $job );

        if ( ! is_wp_error( $job ) ) {
            return $job;
        }

        return false;
    }


    public function post_job_application( $job_id, $fields, $application_id ) {
        $job_board = absint( get_post_meta( $job_id, '_job_boardid', true ) );
        $job_ad = absint( get_post_meta( $job_id, '_jobadid', true ) );

        $application = $this->request( 'POST', 'jobboards/' . $job_board . '/ads/' . $job_ad . '/applications', (array) $fields );

        if ( is_wp_error( $application ) ) {
            return false;
        }

        return $application;
    }


    public function request( $method, $endpoint, $json = array() ) {
        try {
            $response = wp_remote_request( 'https://rest.bullhornstaffing.com/rest-services/e999/entity/' . $endpoint, array(
                'headers'       => array( 
                    'Content-Type'      => 'application/json',
                    'Authorization'     => 'Bearer ' . $this->access_token
                ),
                'method'        => $method,
                'data_format'   => 'body',
                'body'          => ! empty( $json ) ? json_encode( $json ) : null
            ) );

            $body = json_decode( (string) wp_remote_retrieve_body( $response ) );
            $code = wp_remote_retrieve_response_code( $response );

            if ( substr( $code, 0, 1 ) != 2 ) {
                throw new Exception( $body->message, $code, isset( $body->errors ) ? $body->errors : null );
            }

            return $body;
        } catch ( Exception $e ) {
            return new \WP_Error( 'job_manager_bullhorn_request', esc_html( $e->getMessage() ), $e->getDetails() );
        }
    }
}
