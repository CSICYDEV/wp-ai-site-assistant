<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_AI_Site_Assistant_OpenAI {
    private $tools;
    private $logger;

    public function __construct( $tools, $logger ) {
        $this->tools  = $tools;
        $this->logger = $logger;
    }

    public function plan_actions( $prompt ) {
        $settings = get_option( 'wp_ai_site_assistant_settings', array() );
        $api_key  = isset( $settings['openai_api_key'] ) ? trim( $settings['openai_api_key'] ) : '';

        if ( empty( $api_key ) ) {
            return $this->fallback_planner( $prompt );
        }

        $payload = array(
            'model' => ! empty( $settings['openai_model'] ) ? $settings['openai_model'] : 'gpt-4.1-mini',
            'instructions' => 'You are a WordPress admin assistant. Return either a short explanation message or one function call. Prefer safe actions. Only use the provided tools. Never invent fields. If the user asks for destructive actions, explain why approval is needed.',
            'input' => $prompt,
            'tool_choice' => 'auto',
            'tools' => $this->prepare_tools_for_openai( $this->tools->get_definitions() ),
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/responses', array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 40,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code ) {
            $error_message = 'OpenAI request failed.';

            if ( is_array( $body ) && ! empty( $body['error']['message'] ) ) {
                $error_message = 'OpenAI request failed: ' . $body['error']['message'];
            }

            $this->logger->add_log( 'openai_error', array(
                'prompt' => $prompt,
                'status_code' => $code,
                'response' => $body,
            ) );

            return new WP_Error( 'openai_error', $error_message, array( 'status' => 500, 'details' => $body ) );
        }

        $this->logger->add_log( 'openai_plan', array( 'prompt' => $prompt ) );

        $output = isset( $body['output'] ) && is_array( $body['output'] ) ? $body['output'] : array();

        foreach ( $output as $item ) {
            if ( isset( $item['type'] ) && 'function_call' === $item['type'] ) {
                $arguments = array();
                if ( ! empty( $item['arguments'] ) ) {
                    $decoded = json_decode( $item['arguments'], true );
                    if ( is_array( $decoded ) ) {
                        $arguments = $decoded;
                    }
                }

                return array(
                    'mode' => 'proposal',
                    'tool' => $item['name'],
                    'args' => $arguments,
                    'safe' => $this->tools->is_safe_tool( $item['name'] ),
                    'message' => 'Action planned successfully.',
                );
            }
        }

        $text = $this->extract_text( $output );

        return array(
            'mode' => 'message',
            'message' => $text ? $text : 'No action proposed.',
        );
    }


    private function prepare_tools_for_openai( $definitions ) {
        $tools = array();

        foreach ( $definitions as $definition ) {
            $tools[] = array(
                'type' => 'function',
                'name' => isset( $definition['name'] ) ? $definition['name'] : '',
                'description' => isset( $definition['description'] ) ? $definition['description'] : '',
                'parameters' => isset( $definition['parameters'] ) ? $definition['parameters'] : array( 'type' => 'object', 'properties' => array() ),
                'strict' => true,
            );
        }

        return $tools;
    }

    private function extract_text( $output ) {
        $parts = array();
        foreach ( $output as $item ) {
            if ( isset( $item['type'] ) && 'message' === $item['type'] && ! empty( $item['content'] ) ) {
                foreach ( $item['content'] as $content_item ) {
                    if ( isset( $content_item['type'] ) && 'output_text' === $content_item['type'] && ! empty( $content_item['text'] ) ) {
                        $parts[] = $content_item['text'];
                    }
                }
            }
        }
        return trim( implode( "\n", $parts ) );
    }

    private function fallback_planner( $prompt ) {
        $lower = strtolower( $prompt );

        if ( false !== strpos( $lower, 'create' ) || false !== strpos( $lower, 'draft' ) || false !== strpos( $lower, 'new page' ) ) {
            $post_type = ( false !== strpos( $lower, 'product' ) ) ? 'product' : ( ( false !== strpos( $lower, 'post' ) ) ? 'post' : 'page' );
            return array(
                'mode' => 'proposal',
                'tool' => 'create_draft_post',
                'args' => array(
                    'post_type' => $post_type,
                    'title'     => 'New ' . ucfirst( $post_type ) . ' Draft',
                    'content'   => '<h2>Draft generated by fallback planner</h2><p>' . esc_html( wp_trim_words( $prompt, 40 ) ) . '</p>',
                    'excerpt'   => 'Generated from assistant prompt.',
                ),
                'safe' => true,
                'message' => 'API key missing, so I created a basic draft proposal using the local fallback planner.',
            );
        }

        if ( false !== strpos( $lower, 'list' ) || false !== strpos( $lower, 'show me' ) || false !== strpos( $lower, 'recent' ) ) {
            $post_type = ( false !== strpos( $lower, 'product' ) ) ? 'product' : ( ( false !== strpos( $lower, 'post' ) ) ? 'post' : 'page' );
            return array(
                'mode' => 'proposal',
                'tool' => 'list_content',
                'args' => array(
                    'post_type' => $post_type,
                    'limit'     => 5,
                ),
                'safe' => true,
                'message' => 'API key missing, so I prepared a local content lookup action.',
            );
        }

        return array(
            'mode' => 'message',
            'message' => 'Add an OpenAI API key to enable natural language planning. Without a key, I can still propose basic draft and listing actions.',
        );
    }
}
