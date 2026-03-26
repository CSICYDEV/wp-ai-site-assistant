<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_AI_Site_Assistant_Tools {
    private $logger;

    public function __construct( $logger ) {
        $this->logger = $logger;
    }

    public function get_definitions() {
        return array(
            array(
                'type' => 'function',
                'name' => 'create_draft_post',
                'description' => 'Create a draft post, page, or product in WordPress.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'post_type' => array(
                            'type' => 'string',
                            'enum' => array( 'post', 'page', 'product' ),
                            'description' => 'The WordPress post type to create.',
                        ),
                        'title' => array(
                            'type' => 'string',
                            'description' => 'The title of the new content item.',
                        ),
                        'content' => array(
                            'type' => 'string',
                            'description' => 'Main HTML or plain text content.',
                        ),
                        'excerpt' => array(
                            'type' => 'string',
                            'description' => 'Optional short summary.',
                        ),
                    ),
                    'required' => array( 'post_type', 'title' ),
                    'additionalProperties' => false,
                ),
                'safe' => true,
            ),
            array(
                'type' => 'function',
                'name' => 'update_post_content',
                'description' => 'Update an existing post, page, or product content by ID.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'post_id' => array(
                            'type' => 'integer',
                            'description' => 'Numeric WordPress post ID.',
                        ),
                        'title' => array(
                            'type' => 'string',
                            'description' => 'Optional replacement title.',
                        ),
                        'content' => array(
                            'type' => 'string',
                            'description' => 'Optional replacement content.',
                        ),
                        'excerpt' => array(
                            'type' => 'string',
                            'description' => 'Optional replacement excerpt.',
                        ),
                    ),
                    'required' => array( 'post_id' ),
                    'additionalProperties' => false,
                ),
                'safe' => false,
            ),
            array(
                'type' => 'function',
                'name' => 'list_content',
                'description' => 'List recent content items from WordPress.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'post_type' => array(
                            'type' => 'string',
                            'enum' => array( 'post', 'page', 'product' ),
                            'description' => 'The post type to inspect.',
                        ),
                        'limit' => array(
                            'type' => 'integer',
                            'description' => 'How many items to return.',
                        ),
                    ),
                    'required' => array( 'post_type' ),
                    'additionalProperties' => false,
                ),
                'safe' => true,
            ),
        );
    }

    public function execute( $tool_name, $args = array() ) {
        switch ( $tool_name ) {
            case 'create_draft_post':
                return $this->create_draft_post( $args );
            case 'update_post_content':
                return $this->update_post_content( $args );
            case 'list_content':
                return $this->list_content( $args );
            default:
                return new WP_Error( 'unknown_tool', 'Unknown tool: ' . $tool_name, array( 'status' => 400 ) );
        }
    }

    public function is_safe_tool( $tool_name ) {
        foreach ( $this->get_definitions() as $definition ) {
            if ( $definition['name'] === $tool_name ) {
                return ! empty( $definition['safe'] );
            }
        }
        return false;
    }

    private function create_draft_post( $args ) {
        $post_type = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'page';
        $title     = isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '';
        $content   = isset( $args['content'] ) ? wp_kses_post( $args['content'] ) : '';
        $excerpt   = isset( $args['excerpt'] ) ? sanitize_textarea_field( $args['excerpt'] ) : '';

        if ( ! in_array( $post_type, array( 'post', 'page', 'product' ), true ) ) {
            return new WP_Error( 'invalid_post_type', 'Invalid post type.', array( 'status' => 400 ) );
        }

        if ( 'product' === $post_type && ! post_type_exists( 'product' ) ) {
            return new WP_Error( 'missing_woocommerce', 'WooCommerce product post type is not available.', array( 'status' => 400 ) );
        }

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_fields', 'Title is required.', array( 'status' => 400 ) );
        }

        if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
            $content = '';
        }

        $post_id = wp_insert_post( array(
            'post_type'    => $post_type,
            'post_status'  => 'draft',
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
        ), true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $result = array(
            'success' => true,
            'message' => 'Draft created successfully.',
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
        );

        $this->logger->add_log( 'create_draft_post', array_merge( $args, array( 'post_id' => $post_id ) ) );
        return $result;
    }

    private function update_post_content( $args ) {
        $post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
        if ( ! $post_id ) {
            return new WP_Error( 'missing_post_id', 'post_id is required.', array( 'status' => 400 ) );
        }

        $existing = get_post( $post_id );
        if ( ! $existing ) {
            return new WP_Error( 'missing_post', 'Post not found.', array( 'status' => 404 ) );
        }

        $update = array( 'ID' => $post_id );
        if ( isset( $args['title'] ) ) {
            $update['post_title'] = sanitize_text_field( $args['title'] );
        }
        if ( isset( $args['content'] ) ) {
            $update['post_content'] = wp_kses_post( $args['content'] );
        }
        if ( isset( $args['excerpt'] ) ) {
            $update['post_excerpt'] = sanitize_textarea_field( $args['excerpt'] );
        }

        $updated = wp_update_post( $update, true );
        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        $result = array(
            'success' => true,
            'message' => 'Post updated successfully.',
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
        );

        $this->logger->add_log( 'update_post_content', $args );
        return $result;
    }

    private function list_content( $args ) {
        $post_type = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'page';
        $limit     = isset( $args['limit'] ) ? max( 1, min( 20, absint( $args['limit'] ) ) ) : 5;

        if ( 'product' === $post_type && ! post_type_exists( 'product' ) ) {
            return new WP_Error( 'missing_woocommerce', 'WooCommerce product post type is not available.', array( 'status' => 400 ) );
        }

        $query = new WP_Query( array(
            'post_type'      => $post_type,
            'posts_per_page' => $limit,
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $items = array();
        foreach ( $query->posts as $post ) {
            $items[] = array(
                'ID'        => $post->ID,
                'title'     => get_the_title( $post ),
                'status'    => $post->post_status,
                'modified'  => $post->post_modified,
                'edit_url'  => get_edit_post_link( $post->ID, 'raw' ),
            );
        }

        $result = array(
            'success' => true,
            'items'   => $items,
        );

        $this->logger->add_log( 'list_content', $args );
        return $result;
    }
}
