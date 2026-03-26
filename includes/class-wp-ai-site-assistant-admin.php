<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_AI_Site_Assistant_Admin {
    private $tools;
    private $logger;

    public function __construct( $tools, $logger ) {
        $this->tools  = $tools;
        $this->logger = $logger;
    }

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function register_menu() {
        add_menu_page(
            __( 'AI Site Assistant', 'wp-ai-site-assistant' ),
            __( 'AI Assistant', 'wp-ai-site-assistant' ),
            'manage_options',
            'wp-ai-site-assistant',
            array( $this, 'render_page' ),
            'dashicons-superhero-alt',
            58
        );
    }

    public function register_settings() {
        register_setting( 'wp_ai_site_assistant_group', 'wp_ai_site_assistant_settings', array( $this, 'sanitize_settings' ) );

        add_settings_section(
            'wp_ai_site_assistant_main',
            __( 'Assistant Settings', 'wp-ai-site-assistant' ),
            '__return_false',
            'wp-ai-site-assistant-settings'
        );

        add_settings_field(
            'openai_api_key',
            __( 'OpenAI API Key', 'wp-ai-site-assistant' ),
            array( $this, 'render_api_key_field' ),
            'wp-ai-site-assistant-settings',
            'wp_ai_site_assistant_main'
        );

        add_settings_field(
            'openai_model',
            __( 'Model', 'wp-ai-site-assistant' ),
            array( $this, 'render_model_field' ),
            'wp-ai-site-assistant-settings',
            'wp_ai_site_assistant_main'
        );

        add_settings_field(
            'auto_execute_safe',
            __( 'Auto-execute safe actions', 'wp-ai-site-assistant' ),
            array( $this, 'render_auto_execute_field' ),
            'wp-ai-site-assistant-settings',
            'wp_ai_site_assistant_main'
        );

        add_settings_field(
            'max_log_entries',
            __( 'Max log entries', 'wp-ai-site-assistant' ),
            array( $this, 'render_max_logs_field' ),
            'wp-ai-site-assistant-settings',
            'wp_ai_site_assistant_main'
        );
    }

    public function sanitize_settings( $input ) {
        return array(
            'openai_api_key'    => isset( $input['openai_api_key'] ) ? sanitize_text_field( $input['openai_api_key'] ) : '',
            'openai_model'      => isset( $input['openai_model'] ) ? sanitize_text_field( $input['openai_model'] ) : 'gpt-4.1-mini',
            'auto_execute_safe' => ! empty( $input['auto_execute_safe'] ) ? 1 : 0,
            'max_log_entries'   => isset( $input['max_log_entries'] ) ? max( 20, absint( $input['max_log_entries'] ) ) : 100,
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_wp-ai-site-assistant' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'wp-ai-site-assistant-admin',
            WP_AI_SITE_ASSISTANT_URL . 'admin/admin.css',
            array(),
            WP_AI_SITE_ASSISTANT_VERSION
        );

        wp_enqueue_script(
            'wp-ai-site-assistant-admin',
            WP_AI_SITE_ASSISTANT_URL . 'admin/admin.js',
            array( 'wp-api-fetch' ),
            WP_AI_SITE_ASSISTANT_VERSION,
            true
        );

        $settings = get_option( 'wp_ai_site_assistant_settings', array() );

        wp_localize_script(
            'wp-ai-site-assistant-admin',
            'WPAISiteAssistant',
            array(
                'restUrl' => esc_url_raw( rest_url( 'wp-ai-site-assistant/v1' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'autoExecuteSafe' => ! empty( $settings['auto_execute_safe'] ),
                'hasApiKey' => ! empty( $settings['openai_api_key'] ),
            )
        );
    }

    public function render_page() {
        $logs = $this->logger->get_logs();
        ?>
        <div class="wrap wp-ai-site-assistant-wrap">
            <h1><?php esc_html_e( 'WP AI Site Assistant', 'wp-ai-site-assistant' ); ?></h1>
            <p><?php esc_html_e( 'MVP assistant for safe WordPress actions with approval flow.', 'wp-ai-site-assistant' ); ?></p>

            <div class="wp-ai-grid">
                <div class="wp-ai-panel">
                    <h2><?php esc_html_e( 'Assistant', 'wp-ai-site-assistant' ); ?></h2>
                    <div id="wp-ai-chat-log" class="wp-ai-chat-log">
                        <div class="wp-ai-message assistant">Hello. Ask me to create drafts, update content, or inspect posts/pages/products.</div>
                    </div>
                    <textarea id="wp-ai-prompt" rows="4" placeholder="Example: Create a draft page for Microsoft 365 Support with CTA and short intro"></textarea>
                    <div class="wp-ai-actions">
                        <button class="button button-primary" id="wp-ai-send"><?php esc_html_e( 'Run Assistant', 'wp-ai-site-assistant' ); ?></button>
                        <button class="button" id="wp-ai-refresh-logs"><?php esc_html_e( 'Refresh Logs', 'wp-ai-site-assistant' ); ?></button>
                    </div>
                    <div id="wp-ai-proposals"></div>
                </div>

                <div class="wp-ai-panel">
                    <h2><?php esc_html_e( 'Settings', 'wp-ai-site-assistant' ); ?></h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( 'wp_ai_site_assistant_group' );
                        do_settings_sections( 'wp-ai-site-assistant-settings' );
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>

            <div class="wp-ai-panel">
                <h2><?php esc_html_e( 'Audit Log', 'wp-ai-site-assistant' ); ?></h2>
                <div id="wp-ai-log-list" class="wp-ai-log-list">
                    <?php if ( empty( $logs ) ) : ?>
                        <p><?php esc_html_e( 'No log entries yet.', 'wp-ai-site-assistant' ); ?></p>
                    <?php else : ?>
                        <?php foreach ( $logs as $entry ) : ?>
                            <div class="wp-ai-log-entry">
                                <strong><?php echo esc_html( $entry['timestamp'] ); ?></strong>
                                <span><?php echo esc_html( $entry['action'] ); ?></span>
                                <code><?php echo esc_html( wp_json_encode( $entry['data'] ) ); ?></code>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_api_key_field() {
        $settings = get_option( 'wp_ai_site_assistant_settings', array() );
        ?>
        <input type="password" name="wp_ai_site_assistant_settings[openai_api_key]" value="<?php echo esc_attr( $settings['openai_api_key'] ?? '' ); ?>" class="regular-text" autocomplete="off" />
        <p class="description">Stored in WordPress options. For stronger security, move secrets to wp-config.php or environment variables.</p>
        <?php
    }

    public function render_model_field() {
        $settings = get_option( 'wp_ai_site_assistant_settings', array() );
        ?>
        <input type="text" name="wp_ai_site_assistant_settings[openai_model]" value="<?php echo esc_attr( $settings['openai_model'] ?? 'gpt-4.1-mini' ); ?>" class="regular-text" />
        <p class="description">Example: gpt-4.1-mini</p>
        <?php
    }

    public function render_auto_execute_field() {
        $settings = get_option( 'wp_ai_site_assistant_settings', array() );
        ?>
        <label>
            <input type="checkbox" name="wp_ai_site_assistant_settings[auto_execute_safe]" value="1" <?php checked( ! empty( $settings['auto_execute_safe'] ) ); ?> />
            Automatically execute safe actions such as creating drafts.
        </label>
        <?php
    }

    public function render_max_logs_field() {
        $settings = get_option( 'wp_ai_site_assistant_settings', array() );
        ?>
        <input type="number" min="20" max="500" name="wp_ai_site_assistant_settings[max_log_entries]" value="<?php echo esc_attr( $settings['max_log_entries'] ?? 100 ); ?>" />
        <?php
    }
}
