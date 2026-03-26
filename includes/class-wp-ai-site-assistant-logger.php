<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_AI_Site_Assistant_Logger {
    private $option_name = 'wp_ai_site_assistant_logs';

    public function add_log( $action, $data = array() ) {
        $logs = get_option( $this->option_name, array() );
        $settings = get_option( 'wp_ai_site_assistant_settings', array() );
        $max = isset( $settings['max_log_entries'] ) ? max( 20, absint( $settings['max_log_entries'] ) ) : 100;

        array_unshift( $logs, array(
            'timestamp' => current_time( 'mysql' ),
            'action'    => $action,
            'data'      => $data,
        ) );

        if ( count( $logs ) > $max ) {
            $logs = array_slice( $logs, 0, $max );
        }

        update_option( $this->option_name, $logs, false );
    }

    public function get_logs() {
        $logs = get_option( $this->option_name, array() );
        return is_array( $logs ) ? $logs : array();
    }
}
