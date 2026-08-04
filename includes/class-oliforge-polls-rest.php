<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OliForge_Polls_REST {
    const NAMESPACE = 'oliforge-polls/v1';

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route(
            self::NAMESPACE,
            '/polls/(?P<id>\d+)/vote',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'vote' ),
                'permission_callback' => array( __CLASS__, 'can_vote' ),
                'args'                => array(
                    'id'           => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => function( $value ) {
                            return absint( $value ) > 0;
                        },
                    ),
                    'answer_index' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => function( $value ) {
                            return is_numeric( $value ) && (int) $value >= 0;
                        },
                    ),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/polls/(?P<id>\d+)/results',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'results' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => function( $value ) {
                            return absint( $value ) > 0;
                        },
                    ),
                ),
            )
        );
    }

    public static function can_vote( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );

        if ( ! $nonce && $request->has_param( '_wpnonce' ) ) {
            $nonce = $request->get_param( '_wpnonce' );
        }

        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Invalid vote security token.', 'oliforge-polls' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        return true;
    }

    public static function vote( WP_REST_Request $request ) {
        $post_id      = absint( $request->get_param( 'id' ) );
        $answer_index = absint( $request->get_param( 'answer_index' ) );

        if ( ! $post_id || OliForge_Polls_CPT::POST_TYPE !== get_post_type( $post_id ) ) {
            return new WP_Error( 'oliforge_polls_not_found', __( 'Poll not found.', 'oliforge-polls' ), array( 'status' => 404 ) );
        }

        if ( ! OliForge_Polls_Util::is_public_poll( $post_id ) ) {
            return new WP_Error( 'oliforge_polls_not_public', __( 'Poll is not public.', 'oliforge-polls' ), array( 'status' => 403 ) );
        }

        $rate_limit = self::check_rate_limit( $post_id );
        if ( is_wp_error( $rate_limit ) ) {
            return $rate_limit;
        }

        if ( OliForge_Polls_Util::has_user_voted( $post_id ) ) {
            return new WP_Error( 'oliforge_polls_duplicate_vote', __( 'You have already voted.', 'oliforge-polls' ), array( 'status' => 409 ) );
        }

        $data = OliForge_Polls_Util::get_poll_data( $post_id );

        if ( ! OliForge_Polls_Util::is_poll_started( $data ) ) {
            return new WP_Error( 'oliforge_polls_not_started', __( 'Voting has not started yet.', 'oliforge-polls' ), array( 'status' => 403, 'title' => get_the_title( $post_id ) ) );
        }

        if ( OliForge_Polls_Util::is_poll_closed( $data ) ) {
            return new WP_Error( 'oliforge_polls_closed', __( 'Voting is closed.', 'oliforge-polls' ), array( 'status' => 403 ) );
        }

        if ( ! isset( $data['answers'][ $answer_index ] ) ) {
            return new WP_Error( 'oliforge_polls_invalid_answer', __( 'Selected answer is invalid.', 'oliforge-polls' ), array( 'status' => 400 ) );
        }

        $answer_id = sanitize_key( $data['answers'][ $answer_index ]['id'] );
        $saved     = OliForge_Polls_DB::record_vote( $post_id, $answer_id );

        if ( is_wp_error( $saved ) ) {
            $status = 'duplicate_vote' === $saved->get_error_code() ? 409 : 500;
            return new WP_Error( 'oliforge_polls_' . $saved->get_error_code(), $saved->get_error_message(), array( 'status' => $status ) );
        }

        // Keep the old per-poll cookie for backward-compatible front-end checks.
        setcookie( 'oliforge_polls_' . $post_id, '1', time() + YEAR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
        $_COOKIE[ 'oliforge_polls_' . $post_id ] = '1';

        $result_data = OliForge_Polls_Util::get_results( $post_id, $data );

        return rest_ensure_response(
            array(
                'message' => $data['labels']['votedMessage'],
                'total'   => $result_data['total'],
                'results' => $result_data['items'],
                'closed'      => OliForge_Polls_Util::is_poll_closed( $data ),
                'started'     => OliForge_Polls_Util::is_poll_started( $data ),
                'showResults' => OliForge_Polls_Util::should_show_results( $data, true, OliForge_Polls_Util::is_poll_closed( $data ) ),
            )
        );
    }

    public static function results( WP_REST_Request $request ) {
        $post_id = absint( $request->get_param( 'id' ) );

        if ( ! $post_id || OliForge_Polls_CPT::POST_TYPE !== get_post_type( $post_id ) ) {
            return new WP_Error( 'oliforge_polls_not_found', __( 'Poll not found.', 'oliforge-polls' ), array( 'status' => 404 ) );
        }

        if ( ! OliForge_Polls_Util::can_publicly_access_poll( $post_id ) ) {
            return new WP_Error( 'oliforge_polls_not_public', __( 'Poll is not public.', 'oliforge-polls' ), array( 'status' => 403 ) );
        }

        $data        = OliForge_Polls_Util::get_poll_data( $post_id );
        $result_data = OliForge_Polls_Util::get_results( $post_id, $data );
        $is_closed   = OliForge_Polls_Util::is_poll_closed( $data );
        $has_voted   = OliForge_Polls_Util::has_user_voted( $post_id );

        $show_results = OliForge_Polls_Util::should_show_results( $data, $has_voted, $is_closed ) || current_user_can( 'edit_post', $post_id );

        return rest_ensure_response(
            array(
                'total'       => $show_results ? $result_data['total'] : 0,
                'results'     => $show_results ? $result_data['items'] : array(),
                'closed'      => $is_closed,
                'started'     => OliForge_Polls_Util::is_poll_started( $data ),
                'showResults' => $show_results,
            )
        );
    }

    private static function check_rate_limit( $post_id ) {
        $limit  = absint( apply_filters( 'oliforge_polls_vote_rate_limit', 10, $post_id ) );
        $window = absint( apply_filters( 'oliforge_polls_vote_rate_window', MINUTE_IN_SECONDS, $post_id ) );

        if ( $limit < 1 || $window < 1 ) {
            return true;
        }

        $key   = self::get_rate_limit_key( $post_id );
        $count = (int) get_transient( $key );

        if ( $count >= $limit ) {
            return new WP_Error(
                'oliforge_polls_rate_limited',
                __( 'Too many vote attempts. Please try again later.', 'oliforge-polls' ),
                array( 'status' => 429 )
            );
        }

        set_transient( $key, $count + 1, $window );
        return true;
    }

    private static function get_rate_limit_key( $post_id ) {
        $ip = self::get_request_ip();
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        $fingerprint = hash_hmac( 'sha256', absint( $post_id ) . '|' . $ip . '|' . $ua, wp_salt( 'auth' ) );

        return 'oliforge_polls_rate_' . $fingerprint;
    }

    private static function get_request_ip() {
        $server_keys = array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' );

        foreach ( $server_keys as $key ) {
            if ( empty( $_SERVER[ $key ] ) ) {
                continue;
            }

            $value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
            if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
                return $value;
            }
        }

        return 'unknown';
    }
}
