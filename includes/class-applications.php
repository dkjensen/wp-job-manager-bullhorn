<?php
/**
 * Applications handling
 * 
 * @package WP Job Manager - Bullhorn Integration
 */


namespace SeattleWebCo\WPJobManager\Recruiter\Bullhorn;

function apply( $application_id, $job_id ) {
    $apply  = new \WP_Job_Manager_Applications_Apply;
    $fields = array();

    /**
     * Lets try and get the real job ID because the second parameter here does not work consistently
     */
    $application = get_post( $application_id );
    $attachments = (array) get_post_meta( $application_id, '_attachment_file', true );

    if ( $application ) {
        $job = get_post( $application->post_parent );

        if ( $job->post_type == 'job_listing' ) {
            $job_id = $job->ID;
        }
    }

    if ( $job_id ) {
        $client = get_post_meta( $job_id, '_imported_from', true );

        if ( empty( $client ) ) {
            return;
        }

        if ( ! get_option( $client . '_applications', 0 ) ) {
            return;
        }

        foreach ( $apply->get_fields() as $field ) {
            if ( empty( $field[ $client ] ) ) {
                continue;
            }

            $object = explode( ':', $field[ $client ], 2 );

            if ( sizeof( $object ) > 1 ) {
                if ( substr( $field[ $client ], -2, 2 ) == '[]' ) {
                    $key = &$fields[ $object[0] ][ substr( $object[1], 0, -2 ) ];
                } else {
                    $key = &$fields[ $object[0] ][ $object[1] ];
                }
            } else {
                if ( substr( $field[ $client ], -2, 2 ) == '[]' ) {
                    $key = &$fields[ substr( $field[ $client ], 0, -2 ) ];
                } else {
                    $key = &$fields[ $field[ $client ] ];
                }
            }

            if ( isset( $field['rules'] ) && array_search( 'attachment', $field['rules'] ) ) {
                foreach ( $attachments as $key => $attachment ) {
                    if ( empty( $attachment ) ) {
                        continue;
                    }

                    $field['value'] = $attachment;

                    unset( $attachments[ $key ] );
                }
            }

            $value = apply_filters( 'job_manager_application_field_value', $field['value'], $field );

            if ( is_array( $key ) || substr( $field[ $client ], -2, 2 ) == '[]' ) {
                $key[] = $value;
            } else {
                $key = $value;
            }
        }

        if ( ! empty( $fields ) ) {
            /**
             * @var array   $fields
             */
            $posted = WP_Job_Manager_Bullhorn()->clients[ $client ]->post_job_application( $job_id, $fields, $application_id );
        }
    }
}
add_action( 'new_job_application', __NAMESPACE__ . '\apply', 10, 2 );


function save_application_form_fields( $value, $option, $old_value ) {
    foreach ( WP_Job_Manager_Bullhorn()->clients as $label => $client ) {
        if ( $option == 'job_application_form_fields' && isset( $_POST ) && isset( $_POST['field_' . $label] ) ) {
            $field_labels = ! empty( $_POST['field_label'] ) ? array_map( 'wp_kses_post', $_POST['field_label'] ) : array();

            $new_value = array();

            $i = 0;
            foreach ( $field_labels as $key => $field ) {
                if ( empty( $field_labels[ $key ] ) ) {
                    continue;
                }

                $field_name = sanitize_title( $field_labels[ $key ] );

                if ( isset( $new_value[ $field_name ] ) ) {
                    // Generate a unique field name by appending a number to the existing field name.
                    // Assumes no more than 100 fields with the same name would be needed? Otherwise it will override the field.
                    $counter = 1;
                    while ( $counter <= 100 ) {
                        $candidate = $field_name . '-' . $counter;
                        if ( ! isset( $new_value[ $candidate ] ) ) {
                            $field_name = $candidate;
                            break;
                        }
                        $counter++;
                    }
                }

                $new_value[ $field_name ] = $value[ $field_name ];

                $new_value[ $field_name ][ $label ] = isset( $_POST['field_' . $label] ) && isset( $_POST['field_' . $label][ $key ] ) ? $_POST['field_' . $label][ $key ] : '';
            
                $i++;
            }

            // wp_redirect( admin_url( 'edit.php?post_type=job_application&page=job-applications-form-editor&tab=fields' ) );

            $value = $new_value;
        }
    }

    return $value;
}
add_filter( 'pre_update_option', __NAMESPACE__ . '\save_application_form_fields', 10, 3 );

/**
 * After resetting application form to defaults, reload the page again
 * due to the localized JS not being updated yet
 *
 * @return void
 */
function reload_application_form() {
    wp_redirect( admin_url( 'edit.php?post_type=job_application&page=job-applications-form-editor&tab=fields' ) );
    exit;
}
add_action( 'delete_option_job_application_form_fields', __NAMESPACE__ . '\reload_application_form' );
