<?php
/**
 * General functions
 * 
 * @package WP Job Manager - Bullhorn Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Format the salary object into a human-readable string
 *
 * @param stdClass $salary
 * @return string
 */
function job_manager_bullhorn_format_salary( stdClass $salary ) {
    $low = isset( $salary->rateLow ) ? $salary->rateLow : '';
    $high = isset( $salary->rateHigh ) ? $salary->rateHigh : '';
    $per = isset( $salary->ratePer ) ? $salary->ratePer : '';

    if ( $low === $high ) {
        $salary = $high;
    } else {
        $salary = min( $low, $high ) . ' &ndash; ' . max( $low, $high );
    }

    if ( $per ) {
        $salary .= '/' . esc_html_e( $per, 'wp-job-manager-bullhorn' ); 
    }

    return $salary;
}


/**
 * Adds custom job sync interval to the WP cron schedule
 *
 * @param array $schedules
 * @return array
 */
function job_manager_bullhorn_sync_interval( $schedules ) {
    $interval = (int) get_option( 'bullhorn_sync_interval', 15 );

    $schedules['bullhorn_sync'] = array(
        'interval'  => $interval * 60,
        'display'   => sprintf( _n( 'Every minute', 'Every %s minutes', $interval, 'wp-job-manager-bullhorn' ), $interval )
    );

    return $schedules;
}
add_filter( 'cron_schedules', 'job_manager_bullhorn_sync_interval' );

/**
 * Format date into ISO 8601 format
 *
 * @param string $value
 * @param array  $field
 * @return string
 */
function job_manager_bullhorn_format_date( $value, $field ) {
    if ( $field['bullhorn'] == 'availability:date' ) {
        $value = date( 'c', strtotime( $value ) );
    }

    return $value;
}   
add_filter( 'job_manager_application_field_value', 'job_manager_bullhorn_format_date', 10, 2 );


/**
 * Format fields into valid URLs
 *
 * @param string $value
 * @param array  $field
 * @return string
 */
function job_manager_bullhorn_format_url( $value, $field ) {
    $fields = array();

    if ( in_array( $field['bullhorn'], $fields ) ) {
        $value = esc_url( $value );
    }

    return $value;
}
add_filter( 'job_manager_application_field_value', 'job_manager_bullhorn_format_url', 10, 2 );
