<?php
/**
 * Exception handling class
 * 
 * @package WP Job Manager - Bullhorn Integration
 */

 
namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Exception extends \Exception {

    /**
     * Additional details to describe the exception
     *
     * @var mixed
     */
    protected $details;

    /**
     * Overwrite constructor
     *
     * @param string  $message
     * @param integer $code
     * @param array   $details
     */
	public function __construct( $message = '', $code = 0, $details = array() ) {
        if ( ! empty( $details ) ) {
            $this->details = json_encode( (array) $details );
        }

        WP_Job_Manager_Bullhorn()->log->error( $message, $this->getDetails() );

        parent::__construct( __( 'Bullhorn Message: ', 'wp-job-manager-bullhorn' ) . $message, intval( $code ), null );
    }


    /**
     * Additional details
     *
     * @return mixed
     */
    public function getDetails() {
        return $this->details;
    }
	
}
