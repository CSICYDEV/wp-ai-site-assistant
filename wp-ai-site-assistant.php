<?php
/**
 * Plugin Name: WP AI Site Assistant
 * Plugin URI: https://example.com/
 * Description: MVP WordPress AI assistant for safe content actions with approval flow, audit logs, and OpenAI Responses API support.
 * Version: 0.1.2.2
 * Author: OpenAI
 * License: GPLv2 or later
 * Text Domain: wp-ai-site-assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/CSICYDEV/wp-ai-site-assistant/',
	__FILE__,
	'wp-ai-site-assistant'
);

$updateChecker->setBranch('main');


if ( ! defined( 'WP_AI_SITE_ASSISTANT_VERSION' ) ) {
    define( 'WP_AI_SITE_ASSISTANT_VERSION', '0.1.2' );
}

if ( ! defined( 'WP_AI_SITE_ASSISTANT_PATH' ) ) {
    define( 'WP_AI_SITE_ASSISTANT_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WP_AI_SITE_ASSISTANT_URL' ) ) {
    define( 'WP_AI_SITE_ASSISTANT_URL', plugin_dir_url( __FILE__ ) );
}

require_once WP_AI_SITE_ASSISTANT_PATH . 'includes/class-wp-ai-site-assistant.php';

function wp_ai_site_assistant_boot() {
    $plugin = new WP_AI_Site_Assistant();
    $plugin->init();
}
add_action( 'plugins_loaded', 'wp_ai_site_assistant_boot' );
