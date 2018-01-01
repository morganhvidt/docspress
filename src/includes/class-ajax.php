<?php

/**
 * Ajax Class
 */
class DocsPress_Ajax {
    /**
     * Post Type Object
     * @var
     */
    public $post_type_object;

    /**
     * Bind actions
     */
    function __construct() {
        $this->post_type_object = get_post_type_object( 'docs' );

        add_action( 'wp_ajax_docspress_create_doc', array( $this, 'create_doc' ) );
        add_action( 'wp_ajax_docspress_clone_doc', array( $this, 'clone_doc' ) );
        add_action( 'wp_ajax_docspress_remove_doc', array( $this, 'remove_doc' ) );
        add_action( 'wp_ajax_docspress_export_doc', array( $this, 'export_doc' ) );
        add_action( 'wp_ajax_docspress_admin_get_docs', array( $this, 'get_docs' ) );
        add_action( 'wp_ajax_docspress_sortable_docs', array( $this, 'sort_docs' ) );

        // feedback
        add_action( 'wp_ajax_docspress_ajax_feedback', array( $this, 'handle_feedback' ) );
        add_action( 'wp_ajax_nopriv_docspress_ajax_feedback', array( $this, 'handle_feedback' ) );
    }

    /**
     * Create a new doc
     *
     * @return void
     */
    public function create_doc() {
        check_ajax_referer( 'docspress-admin-nonce' );

        $title  = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'publish';
        $parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
        $order  = isset( $_POST['order'] ) ? absint( $_POST['order'] ) : 0;

        if ( ! current_user_can( $this->post_type_object->cap->publish_posts ) ) {
            $status = 'pending';
        }

        $post_id = wp_insert_post( array(
            'post_title'  => $title,
            'post_type'   => 'docs',
            'post_status' => $status,
            'post_parent' => $parent,
            'post_author' => get_current_user_id(),
            'menu_order'  => $order
        ) );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error();
        }

        $post = get_post($post_id);
        wp_send_json_success( array(
            'post' => array(
                'id'     => $post_id,
                'title'  => $title,
                'name'   => $post->post_name,
                'status' => $status,
                'caps'   => array(
                    'edit'   => current_user_can( $this->post_type_object->cap->edit_post, $post_id ),
                    'delete' => current_user_can( $this->post_type_object->cap->delete_post, $post_id )
                )
            ),
            'child' => array()
        ) );
    }

    /**
     * Clone a doc
     *
     * @return void
     */
    public function clone_doc() {
        check_ajax_referer( 'docspress-admin-nonce' );

        $title  = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
        $clone_from = isset( $_POST['clone_from'] ) ? $_POST['clone_from'] : 0;

        $result = array();

        if ($clone_from) {
            $clone_from_post = get_post($clone_from);

            if (is_wp_error($clone_from_post)) {
                wp_send_json_error();
            }

            $new_post_id = wp_insert_post( array(
                'post_title'  => $title,
                'post_type'   => 'docs',
                'post_status' => 'publish',
                'post_content' => $clone_from_post->post_content,
                'post_content_filtered' => $clone_from_post->post_content_filtered,
                'post_excerpt' => $clone_from_post->post_excerpt,
                'post_author' => get_current_user_id()
            ) );

            if (is_wp_error($new_post_id)) {
                wp_send_json_error();
            }

            $result = array(
                'post' => array(
                    'id'     => $new_post_id,
                    'title'  => $title,
                    'name'   => $clone_from_post->post_name,
                    'status' => 'publish',
                    'caps'   => array(
                        'edit'   => current_user_can( $this->post_type_object->cap->edit_post, $new_post_id ),
                        'delete' => current_user_can( $this->post_type_object->cap->delete_post, $new_post_id )
                    )
                ),
                'child' => $this->clone_child_docs($clone_from_post->ID, $new_post_id)
            );
        }

        wp_send_json_success($result);
    }

    public function clone_child_docs ($clone_from, $clone_to) {
        $childrens = new WP_Query( array(
            'post_type'      => 'docs',
            'posts_per_page' => -1,
            'post_parent'    => $clone_from,
            'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
        ) );
        $result = array();

        while ( $childrens->have_posts() ) :
            $childrens->the_post();
            $clone_from_post = $childrens->post;
            // $clone_from_post = get_post(get_the_ID());

            if (is_wp_error($clone_from_post)) {
                wp_send_json_error();
            }

            $post_id = wp_insert_post( array(
                'post_title'  => $clone_from_post->post_title,
                'post_type'   => $clone_from_post->post_type,
                'post_status' => $clone_from_post->post_status,
                'post_content' => $clone_from_post->post_content,
                'post_content_filtered' => $clone_from_post->post_content_filtered,
                'post_excerpt' => $clone_from_post->post_excerpt,
                'post_author' => get_current_user_id(),
                'post_parent' => $clone_to,
                'menu_order' => $clone_from_post->menu_order
            ) );

            if (is_wp_error($post_id)) {
                wp_send_json_error();
            }

            // add new subitems
            $result[] = array(
                'post' => array(
                    'id'     => $post_id,
                    'title'  => $clone_from_post->post_title,
                    'name'   => $clone_from_post->post_name,
                    'status' => $clone_from_post->post_status,
                    'caps'   => array(
                        'edit'   => current_user_can( $this->post_type_object->cap->edit_post, $post_id ),
                        'delete' => current_user_can( $this->post_type_object->cap->delete_post, $post_id )
                    )
                ),
                'child' => $this->clone_child_docs($clone_from_post->ID, $post_id)
            );
        endwhile;
        wp_reset_postdata();

        return $result;
    }

    /**
     * Delete a doc
     *
     * @return void
     */
    public function remove_doc() {
        check_ajax_referer( 'docspress-admin-nonce' );

        $force_delete = false;
        $post_id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        if ( ! current_user_can( 'delete_post', $post_id ) ) {
            wp_send_json_error( __( 'You are not allowed to delete this item.', DOCSPRESS_DOMAIN ) );
        }

        if ( $post_id ) {
            // delete childrens first if found
            $this->remove_child_docs( $post_id, $force_delete );

            // delete main doc
            wp_delete_post( $post_id, $force_delete );
        }

        wp_send_json_success();
    }

    /**
     * Remove child docs
     *
     * @param  integer  $parent_id
     *
     * @return void
     */
    public function remove_child_docs( $parent_id = 0, $force_delete ) {
        $childrens = get_children( array( 'post_parent' => $parent_id ) );

        if ( $childrens ) {
            foreach ($childrens as $child_post) {
                // recursively delete
                $this->remove_child_docs( $child_post->ID, $force_delete );

                wp_delete_post( $child_post->ID, $force_delete );
            }
        }
    }

    /**
     * Export as HTML
     *
     * @return void
     */
    public function export_doc () {
        $doc_id = isset( $_GET['doc_id'] ) ? absint( $_GET['doc_id'] ) : 0;

        if ($doc_id) {
            include_once dirname( __FILE__ ) . '/class-export.php';
            $export_class = new DocsPress_Export();
            $export_class->run($doc_id);
        }

        exit;
    }

    /**
     * Get all docs
     *
     * @return void
     */
    public function get_docs() {
        check_ajax_referer( 'docspress-admin-nonce' );

        $docs = new WP_Query( array(
            'post_type'      => 'docs',
            'post_status'    => array( 'publish', 'draft', 'pending' ),
            'posts_per_page' => -1,
            'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
        ) );

        $arranged = $this->build_tree( $docs->posts );
        // usort( $arranged, array( $this, 'sort_callback' ) );
        wp_send_json_success( $arranged );
    }

    /**
     * Store feedback for an article
     *
     * @return void
     */
    function handle_feedback() {
        check_ajax_referer( 'docspress-ajax' );

        $previous = isset( $_COOKIE['docspress_response'] ) ? explode( ',', $_COOKIE['docspress_response'] ) : array();
        $post_id  = intval( $_POST['post_id'] );
        $type     = in_array( $_POST['type'], array( 'positive', 'negative' ) ) ? $_POST['type'] : false;

        // check previous response
        if ( in_array( $post_id, $previous ) ) {
            $message = __( 'Sorry, you\'ve already recorded your feedback!', DOCSPRESS_DOMAIN );
            wp_send_json_error( $message );
        }

        // seems new
        if ( $type ) {
            $count = (int) get_post_meta( $post_id, $type, true );
            update_post_meta( $post_id, $type, $count + 1 );

            array_push( $previous, $post_id );
            $cookie_val = implode( ',',  $previous);

            setcookie( 'docspress_response', $cookie_val, time() + WEEK_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        }

        $message = __( 'Thanks for your feedback!', DOCSPRESS_DOMAIN );
        wp_send_json_success( $message );
    }

    /**
     * Sort docs
     *
     * @return void
     */
    public function sort_docs() {
        check_ajax_referer( 'docspress-admin-nonce' );

        $doc_ids = isset( $_POST['ids'] ) ? array_map( 'absint', $_POST['ids'] ) : array();

        if ( $doc_ids ) {
            foreach ($doc_ids as $order => $id) {
                wp_update_post( array(
                    'ID'         => $id,
                    'menu_order' => $order
                ) );
            }
        }

        exit;
    }

    /**
     * Build a tree of docs with parent-child relation
     *
     * @param  array   $docs
     * @param  integer  $parent
     *
     * @return array
     */
    public function build_tree( $docs, $parent = 0 ) {
        $result = array();

        if ( ! $docs ) {
            return $result;
        }

        foreach ($docs as $key => $doc) {
            if ( $doc->post_parent == $parent ) {
                unset( $docs[ $key ] );

                // build tree and sort
                $child = $this->build_tree( $docs, $doc->ID );
                // usort( $child, array( $this, 'sort_callback' ) );

                $result[] = array(
                    'post' => array(
                        'id'     => $doc->ID,
                        'title'  => $doc->post_title,
                        'name'   => $doc->post_name,
                        'status' => $doc->post_status,
                        'order'  => $doc->menu_order,
                        'caps'   => array(
                            'edit'   => current_user_can( $this->post_type_object->cap->edit_post, $doc->ID ),
                            'delete' => current_user_can( $this->post_type_object->cap->delete_post, $doc->ID )
                        )
                    ),
                    'child' => $child
                );
            }
        }

        return $result;
    }

    /**
     * Sort callback for sorting posts with their menu order
     *
     * @param  array  $a
     * @param  array  $b
     *
     * @return int
     */
    public function sort_callback( $a, $b ) {
        return $a['post']['order'] - $b['post']['order'];
    }
}

new DocsPress_Ajax();