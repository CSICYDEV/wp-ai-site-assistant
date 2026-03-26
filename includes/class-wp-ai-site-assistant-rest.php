<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_AI_Site_Assistant_REST {
    private $tools;
    private $openai;
    private $logger;

    public function __construct( $tools, $openai, $logger ) {
        $this->tools  = $tools;
        $this->openai = $openai;
        $this->logger = $logger;
    }

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'wp-ai-site-assistant/v1', '/chat', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'chat' ),
            'permission_callback' => array( $this, 'permissions' ),
        ) );

        register_rest_route( 'wp-ai-site-assistant/v1', '/execute', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'execute' ),
            'permission_callback' => array( $this, 'permissions' ),
        ) );

        register_rest_route( 'wp-ai-site-assistant/v1', '/logs', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'logs' ),
            'permission_callback' => array( $this, 'permissions' ),
        ) );
    }

    public function permissions() {
        return current_user_can( 'manage_options' );
    }

    public function chat( WP_REST_Request $request ) {
        $prompt = sanitize_textarea_field( (string) $request->get_param( 'prompt' ) );

        if ( empty( $prompt ) ) {
            return new WP_Error( 'empty_prompt', 'Prompt is required.', array( 'status' => 400 ) );
        }

        $response = $this->openai->plan_actions( $prompt );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    public function execute( WP_REST_Request $request ) {
        $tool   = sanitize_key( (string) $request->get_param( 'tool' ) );
        $args   = $request->get_param( 'args' );
        $args   = is_array( $args ) ? $args : array();

        $result = $this->tools->execute( $tool, $args );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    public function logs() {
        return rest_ensure_response( array(
            'logs' => $this->logger->get_logs(),
        ) );
    }
}
