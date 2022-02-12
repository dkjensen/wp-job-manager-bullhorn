<?php
/**
 * Logging class
 * 
 * @package WP Job Manager - Bullhorn Integration
 */


namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Log {

	/**
	 * Instance of the logger class
	 *
	 * @var Monolog\Logger
	 */
	protected $log = null;


	/**
	 * Setup
	 */
	public function __construct() {
		$this->log = new Logger( 'wp-job-manager-bullhorn' );
    	$this->log->pushHandler( new StreamHandler( WP_JOB_MANAGER_BULLHORN_LOG, Logger::DEBUG ) );
	}


	/**
	 * Logs an info message
	 *
	 * @param string $message
	 * @param array  $details
	 * @return void
	 */
	public function info( $message, $details = array() ) {
		$this->log->info( esc_html( $message ), (array) $details );
	}


	/**
	 * Logs an error message
	 *
	 * @param string $message
	 * @param array  $details
	 * @return void
	 */
	public function error( $message, $details = array() ) {
		$this->log->error( esc_html( $message ), (array) $details );
	}
	
}
