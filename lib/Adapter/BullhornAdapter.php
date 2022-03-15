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
        $ping = $this->request( 'GET', 'ping' );

        if ( is_wp_error( $ping ) || empty( $ping->sessionExpires ) ) {
            return false;
        }

        return true;
    }

    public function login() {
        $login = $this->request( 'POST', 'login?version=*&access_token=' . $this->access_token, array(), 'https://rest.bullhornstaffing.com/rest-services/' );

        if ( ! is_wp_error( $login ) ) {
            $bullhorn_rest_token = $login->BhRestToken;
            $bullhorn_rest_url = $login->restUrl;

            if ( $bullhorn_rest_token ) {
                update_option( 'bullhorn_rest_token', $bullhorn_rest_token );
            }

            if ( $bullhorn_rest_url ) {
                update_option( 'bullhorn_rest_url', $bullhorn_rest_url );
            }
        }
    }


    public function get_jobs() {
        $this->login();

        $jobs = $this->request( 'POST', 'query/JobOrder', array(
            'where' => 'id is not null AND isOpen = true', // isOpen = true
            'fields' => 'id, address, categories, description, employmentType, isOpen, payRate, salary, salaryUnit, status, title',
            'count' => 5000,
            'start' => 0
        ) );

        return $jobs->data ?? array();
    }


    
    public function sync_jobs() {
        $jobs = array();

        $job_ads = $this->get_jobs();
        
        if ( $job_ads ) {
            foreach ( $job_ads as $job ) {
                $category = $job_type = null;

                if ( isset( $job->categories ) && $job->categories->total ) {
                    $category_name = $job->categories->data[0]->name ?? '';

                    $category = get_term_by( 'name', $category_name, 'job_listing_category', ARRAY_A );

                    if ( ! $category ) {
                        $category = wp_insert_term( $category_name, 'job_listing_category' );
                    }
                }

                if ( ! empty( $job->employmentType ) ) {
                    $job_type_name = $job->employmentType;

                    $job_type = get_term_by( 'name', $job_type_name, 'job_listing_type', ARRAY_A );

                    if ( ! $job_type ) {
                        $job_type = wp_insert_term( $job_type_name, 'job_listing_type' );
                    }
                }


                $category    = apply_filters( 'wp_job_manager_bullhorn_category', $category, $job );
                $job_type    = apply_filters( 'wp_job_manager_bullhorn_job_type', $job_type, $job );

                $jobs[] = array(
                    'post_title' 		=> isset( $job->title ) ? $job->title : __( 'Untitled job', 'wp-job-manager-bullhorn' ),
                    'post_content' 		=> isset( $job->description ) ? $job->description : '',
                    'post_status'		=> 'publish',
                    'post_type'			=> 'job_listing',
                    'tax_input'         => array(
                        'job_listing_category' => array_filter( array( $category['term_id'] ?? false ) ),
                        'job_listing_type' => array_filter( array( $job_type['term_id'] ?? false ) ),
                    ),
                    'meta_input'		=> array(
                        '_jid'			        => $job->id,
                        '_job_salary'           => trim( ! empty( $job->payRate ) ?: $job->salary, " \n\r\t\v\x00/" ),
                        '_job_salary_period'    => $job->salaryUnit ?? '',
                        '_job_location'         => implode( ', ', array_filter( array( $job->address->city, $job->address->state, $job->address->countryCode ) ) ),
                        '_filled'               => absint( 'Accepting Candidates' !== $job->status ),
                        '_company_name'         => get_option( 'blogname' ),
                        '_imported_from'        => 'bullhorn',
                        '_application'          => get_option( 'admin_email' ),
                    ),
                );
            }
        }

        return $jobs;
    }


    public function job_exists( $id ) {
        global $wpdb;

        $exists = $wpdb->get_var( $wpdb->prepare( "
            SELECT pm1.post_id 
            FROM   $wpdb->postmeta pm1
            JOIN $wpdb->postmeta pm2
                ON pm1.post_id = pm2.post_id
                AND pm2.meta_key = '_imported_from'
                AND pm2.meta_value = 'bullhorn'
            WHERE  pm1.meta_key = '_jid'
            AND    pm1.meta_value = '%s' 
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
        $this->login();

        $jid = get_post_meta( $job_id, '_jid', true );

        $resume = $this->post_job_application_documents( $application_id );

        if ( ! empty( $resume->text ) ) {
            $fields['description'] = json_encode( $resume->text );
        }

        if ( empty( $fields['name'] ) ) {
            $fields['name'] = trim( ( $fields['firstName'] ?? '') . ' ' . ( $fields['lastName'] ?? '' ) );
        }

        $fields['status'] = 'New Candidate';

        unset( $fields['Resume'] );

        $search_candidate = $this->request( 'GET', 'search/Candidate?count=1&query=email:"' . $fields['email'] . '"&fields=id,email' );

        if ( ! is_wp_error( $search_candidate ) ) {
            $id = $search_candidate->data[0]->id;
        }

        if ( isset( $id ) ) {
            $candidate = $this->request( 'POST', "entity/Candidate/{$id}", $fields );
        } else {
            $candidate = $this->request( 'PUT', 'entity/Candidate', $fields );
        }

        if ( is_wp_error( $candidate ) ) {
            return false;
        }

        $job_submission = $this->request( 'PUT', 'entity/JobSubmission', array(
            'candidate' => array( 'id' => (int) $candidate->changedEntityId ),
            'jobOrder'  => array( 'id' => (int) $jid ),
            'status'    => 'New Lead',
            'dateWebResponse' => time() * 1000
        ) );

        if ( is_wp_error( $job_submission ) ) {
            return false;
        }

        $promoted = $this->request( 'POST', 'entity/JobSubmission/' . $job_submission->changedEntityId, array(
            'status'    => 'Submitted',
            'dateAdded' => time() * 1000
        ) );

        return $candidate;
    }

    /**
     * Post job application
     *
     * @param int $application_post_id
     * @return mixed
     */
    public function post_job_application_documents( $application_post_id ) {
        $documents = get_post_meta( $application_post_id, '_attachment_file', true );

        if ( ! empty( $documents ) && is_array( $documents ) ) {
            foreach ( $documents as $document ) {
                $file = new \CURLFile( $document );
                $file->setPostFilename( basename( $document ) );

                $ch = curl_init();
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
                curl_setopt( $ch, CURLOPT_URL, get_option( 'bullhorn_rest_url', 'https://rest.bullhornstaffing.com/rest-services/' ) . '/resume/convertToText?format=' . pathinfo( $document, PATHINFO_EXTENSION ) );
                curl_setopt( $ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer " . $this->access_token,
                    "BhRestToken: " . get_option( 'bullhorn_rest_token', '' ) . "",
                    "Content-Type: multipart/form-data",
                ] );
                curl_setopt( $ch, CURLOPT_POSTFIELDS, [ 'fileData' => $file ] );

                $response = curl_exec( $ch );

                return json_decode( $response );
            }
        }
    }


    public function request( $method, $endpoint, $json = array(), $rest_url = null ) {
        try {
            if ( ! $rest_url ) {
                $rest_url = get_option( 'bullhorn_rest_url', 'https://rest.bullhornstaffing.com/rest-services/' );
            }

            $request_data = array(
                'headers'       => array( 
                    'Content-Type'      => 'application/json'
                ),
                'method'        => $method,
                'data_format'   => 'body',
                'body'          => ! empty( $json ) ? json_encode( $json ) : null
            );

            if ( 'https://rest.bullhornstaffing.com/rest-services/' !== $rest_url ) {
                $request_data['headers']['BhRestToken'] = get_option( 'bullhorn_rest_token', '' );
                $request_data['headers']['Authorization'] = 'Bearer ' . $this->access_token;
            }

            $request_data['timeout'] = 15;

            $response = wp_remote_request( $rest_url . $endpoint, $request_data );

            $body = json_decode( (string) wp_remote_retrieve_body( $response ) );
            $code = wp_remote_retrieve_response_code( $response );

            if ( substr( $code, 0, 1 ) != 2 ) {
                throw new Exception( $body->errorMessage ?? '', $code, isset( $body->errors ) ? $body->errors : null );
                Log::error( $body->errorMessage, array( $rest_url . $endpoint ) );
            }

            return $body;
        } catch ( Exception $e ) {
            return new \WP_Error( 'job_manager_bullhorn_request', esc_html( $e->getMessage() ), $e->getDetails() );
        }
    }
}
