<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once WP_AI_SITE_ASSISTANT_PATH . 'includes/class-wp-ai-site-assistant-admin.php';
require_once WP_AI_SITE_ASSISTANT_PATH . 'includes/class-wp-ai-site-assistant-rest.php';
require_once WP_AI_SITE_ASSISTANT_PATH . 'includes/class-wp-ai-site-assistant-tools.php';
require_once WP_AI_SITE_ASSISTANT_PATH . 'includes/class-wp-ai-site-assistant-openai.php';
require_once WP_AI_SITE_ASSISTANT_PATH . 'includes/class-wp-ai-site-assistant-logger.php';

class WP_AI_Site_Assistant {

    public function init() {
        $this->register_defaults();

        $logger = new WP_AI_Site_Assistant_Logger();
        $tools  = new WP_AI_Site_Assistant_Tools( $logger );
        $openai = new WP_AI_Site_Assistant_OpenAI( $tools, $logger );

        $admin = new WP_AI_Site_Assistant_Admin( $tools, $logger );
        $admin->init();

        $rest = new WP_AI_Site_Assistant_REST( $tools, $openai, $logger );
        $rest->init();
    }

    private function register_defaults() {
        add_option( 'wp_ai_site_assistant_settings', array(
            'openai_api_key'     => '',
            'openai_model'       => 'gpt-4.1-mini',
            'auto_execute_safe'  => 0,
            'max_log_entries'    => 100,
        ) );
    }
}
