<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OliForge_Polls_Util {
    public static function default_settings() {
        return array(
            'question'               => '',
            'description'            => '',
            'answers'                => array(
                array(
                    'id'    => wp_generate_uuid4(),
                    'text'  => 'Option 1',
                    'votes' => 0,
                ),
                array(
                    'id'    => wp_generate_uuid4(),
                    'text'  => 'Option 2',
                    'votes' => 0,
                ),
            ),
            'show_results_after_vote'=> true,
            'results_visibility'      => 'after_vote',
            'start_date'             => '',
            'end_date'               => '',
            'labels'                 => array(
                'voteButton'   => __( 'Vote', 'oliforge-polls' ),
/* translators: Placeholder values used in poll labels/results. */
                'totalVotes'   => __( 'Total votes: %d', 'oliforge-polls' ),
/* translators: Placeholder values used in poll labels/results. */
                'votingStarts' => __( 'Voting starts: %s', 'oliforge-polls' ),
/* translators: Placeholder values used in poll labels/results. */
                'votingEnds'   => __( 'Voting ends: %s', 'oliforge-polls' ),
                'votedMessage' => __( 'Thanks! Your vote has been counted.', 'oliforge-polls' ),
                'alreadyVoted' => __( 'You have already voted.', 'oliforge-polls' ),
                'closedMessage'=> __( 'Voting has ended. Final results:', 'oliforge-polls' ),
                'notStartedMessage' => __( 'Voting has not started yet.', 'oliforge-polls' ),
            ),
            'styles'                 => array(
                'containerBg'          => '#ffffff',
                'containerBorderColor' => '#d1d5db',
                'containerBorderWidth' => 1,
                'containerBorderStyle' => 'solid',
                'containerRadius'      => 12,
                'containerPadding'     => 20,
                'containerShadow'      => '0 8px 24px rgba(0,0,0,0.08)',
                'questionFontFamily'   => 'inherit',
                'questionFontSize'     => 24,
                'questionFontWeight'   => 700,
                'questionColor'        => '#111827',
                'descriptionFontSize'  => 14,
                'descriptionColor'     => '#6b7280',
                'answerFontFamily'     => 'inherit',
                'answerFontSize'       => 16,
                'answerColor'          => '#111827',
                'answerBg'             => '#f9fafb',
                'answerBorderColor'    => '#d1d5db',
                'answerBorderWidth'    => 1,
                'answerBorderRadius'   => 10,
                'buttonBg'             => '#2563eb',
                'buttonTextColor'      => '#ffffff',
                'buttonFontSize'       => 15,
                'buttonRadius'         => 10,
                'buttonPaddingY'       => 12,
                'buttonPaddingX'       => 16,
                'buttonPosition'      => 'left',
                'resultsTextColor'     => '#111827',
                'resultsFontSize'      => 15,
                'progressBg'           => '#e5e7eb',
                'progressFill'         => '#2563eb',
                'progressHeight'       => 10,
                'statusColor'          => '#b45309',
            ),
        );
    }


    public static function is_public_poll( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id || OliForge_Polls_CPT::POST_TYPE !== get_post_type( $post_id ) ) {
            return false;
        }

        return 'publish' === get_post_status( $post_id );
    }

    public static function can_publicly_access_poll( $post_id ) {
        return self::is_public_poll( $post_id ) || current_user_can( 'edit_post', absint( $post_id ) );
    }

    public static function get_poll_data( $post_id ) {
        $stored   = get_post_meta( $post_id, '_oliforge_polls_data', true );
        $defaults = self::default_settings();

        if ( ! is_array( $stored ) ) {
            $data = array_merge( $defaults, array(
                'post_id'      => $post_id,
                'shortcode_id' => self::get_shortcode_id( $post_id ),
            ) );
            return class_exists( 'OliForge_Polls_DB' ) ? self::apply_db_counts_to_data( $post_id, $data ) : $data;
        }

        $data = wp_parse_args( $stored, $defaults );
        $data['post_id']      = $post_id;
        $data['shortcode_id'] = self::get_shortcode_id( $post_id );
        $data['styles'] = wp_parse_args( isset( $stored['styles'] ) && is_array( $stored['styles'] ) ? $stored['styles'] : array(), $defaults['styles'] );
        $data['labels'] = wp_parse_args( isset( $stored['labels'] ) && is_array( $stored['labels'] ) ? $stored['labels'] : array(), $defaults['labels'] );

        if ( empty( $data['answers'] ) || ! is_array( $data['answers'] ) ) {
            $data['answers'] = $defaults['answers'];
        }

        foreach ( $data['answers'] as &$answer ) {
            $answer = wp_parse_args(
                is_array( $answer ) ? $answer : array(),
                array(
                    'id'    => wp_generate_uuid4(),
                    'text'  => '',
                    'votes' => 0,
                )
            );
            $answer['id']    = sanitize_key( $answer['id'] );
            $answer['votes'] = absint( $answer['votes'] );
        }
        unset( $answer );

        if ( class_exists( 'OliForge_Polls_DB' ) ) {
            $data = self::apply_db_counts_to_data( $post_id, $data );
        }

        return $data;
    }

    public static function sanitize_poll_data( $input ) {
        $defaults = self::default_settings();
        $input    = is_array( $input ) ? $input : array();
        $styles   = isset( $input['styles'] ) && is_array( $input['styles'] ) ? $input['styles'] : array();
        $labels   = isset( $input['labels'] ) && is_array( $input['labels'] ) ? $input['labels'] : array();

        $sanitized = array(
            'question'                => sanitize_text_field( $input['question'] ?? '' ),
            'description'             => sanitize_textarea_field( $input['description'] ?? '' ),
            'show_results_after_vote' => ! empty( $input['show_results_after_vote'] ),
            'results_visibility'      => self::sanitize_select( $input['results_visibility'] ?? ( ! empty( $input['show_results_after_vote'] ) ? 'after_vote' : 'after_end' ), array( 'after_vote', 'after_end', 'always', 'hidden' ), 'after_vote' ),
            'start_date'              => self::sanitize_datetime_local( $input['start_date'] ?? '' ),
            'end_date'                => self::sanitize_datetime_local( $input['end_date'] ?? '' ),
            'labels'                  => array(
                'voteButton'   => sanitize_text_field( $labels['voteButton'] ?? $defaults['labels']['voteButton'] ),
                'totalVotes'   => sanitize_text_field( $labels['totalVotes'] ?? $defaults['labels']['totalVotes'] ),
                'votingStarts' => sanitize_text_field( $labels['votingStarts'] ?? $defaults['labels']['votingStarts'] ),
                'votingEnds'   => sanitize_text_field( $labels['votingEnds'] ?? $defaults['labels']['votingEnds'] ),
                'votedMessage' => sanitize_text_field( $labels['votedMessage'] ?? $defaults['labels']['votedMessage'] ),
                'alreadyVoted' => sanitize_text_field( $labels['alreadyVoted'] ?? $defaults['labels']['alreadyVoted'] ),
                'closedMessage'=> sanitize_text_field( $labels['closedMessage'] ?? $defaults['labels']['closedMessage'] ),
                'notStartedMessage' => sanitize_text_field( $labels['notStartedMessage'] ?? $defaults['labels']['notStartedMessage'] ),
            ),
            'answers'                 => array(),
            'styles'                  => array(
                'containerBg'          => sanitize_hex_color( $styles['containerBg'] ?? $defaults['styles']['containerBg'] ) ?: $defaults['styles']['containerBg'],
                'containerBorderColor' => sanitize_hex_color( $styles['containerBorderColor'] ?? $defaults['styles']['containerBorderColor'] ) ?: $defaults['styles']['containerBorderColor'],
                'containerBorderWidth' => self::sanitize_int_range( $styles['containerBorderWidth'] ?? $defaults['styles']['containerBorderWidth'], 0, 20, $defaults['styles']['containerBorderWidth'] ),
                'containerBorderStyle' => self::sanitize_select( $styles['containerBorderStyle'] ?? $defaults['styles']['containerBorderStyle'], array( 'none', 'solid', 'dashed', 'dotted' ), $defaults['styles']['containerBorderStyle'] ),
                'containerRadius'      => self::sanitize_int_range( $styles['containerRadius'] ?? $defaults['styles']['containerRadius'], 0, 60, $defaults['styles']['containerRadius'] ),
                'containerPadding'     => self::sanitize_int_range( $styles['containerPadding'] ?? $defaults['styles']['containerPadding'], 0, 60, $defaults['styles']['containerPadding'] ),
                'containerShadow'      => sanitize_text_field( $styles['containerShadow'] ?? $defaults['styles']['containerShadow'] ),
                'questionFontFamily'   => sanitize_text_field( $styles['questionFontFamily'] ?? $defaults['styles']['questionFontFamily'] ),
                'questionFontSize'     => self::sanitize_int_range( $styles['questionFontSize'] ?? $defaults['styles']['questionFontSize'], 10, 72, $defaults['styles']['questionFontSize'] ),
                'questionFontWeight'   => self::sanitize_int_range( $styles['questionFontWeight'] ?? $defaults['styles']['questionFontWeight'], 100, 900, $defaults['styles']['questionFontWeight'] ),
                'questionColor'        => sanitize_hex_color( $styles['questionColor'] ?? $defaults['styles']['questionColor'] ) ?: $defaults['styles']['questionColor'],
                'descriptionFontSize'  => self::sanitize_int_range( $styles['descriptionFontSize'] ?? $defaults['styles']['descriptionFontSize'], 10, 40, $defaults['styles']['descriptionFontSize'] ),
                'descriptionColor'     => sanitize_hex_color( $styles['descriptionColor'] ?? $defaults['styles']['descriptionColor'] ) ?: $defaults['styles']['descriptionColor'],
                'answerFontFamily'     => sanitize_text_field( $styles['answerFontFamily'] ?? $defaults['styles']['answerFontFamily'] ),
                'answerFontSize'       => self::sanitize_int_range( $styles['answerFontSize'] ?? $defaults['styles']['answerFontSize'], 10, 40, $defaults['styles']['answerFontSize'] ),
                'answerColor'          => sanitize_hex_color( $styles['answerColor'] ?? $defaults['styles']['answerColor'] ) ?: $defaults['styles']['answerColor'],
                'answerBg'             => sanitize_hex_color( $styles['answerBg'] ?? $defaults['styles']['answerBg'] ) ?: $defaults['styles']['answerBg'],
                'answerBorderColor'    => sanitize_hex_color( $styles['answerBorderColor'] ?? $defaults['styles']['answerBorderColor'] ) ?: $defaults['styles']['answerBorderColor'],
                'answerBorderWidth'    => self::sanitize_int_range( $styles['answerBorderWidth'] ?? $defaults['styles']['answerBorderWidth'], 0, 20, $defaults['styles']['answerBorderWidth'] ),
                'answerBorderRadius'   => self::sanitize_int_range( $styles['answerBorderRadius'] ?? $defaults['styles']['answerBorderRadius'], 0, 40, $defaults['styles']['answerBorderRadius'] ),
                'buttonBg'             => sanitize_hex_color( $styles['buttonBg'] ?? $defaults['styles']['buttonBg'] ) ?: $defaults['styles']['buttonBg'],
                'buttonTextColor'      => sanitize_hex_color( $styles['buttonTextColor'] ?? $defaults['styles']['buttonTextColor'] ) ?: $defaults['styles']['buttonTextColor'],
                'buttonFontSize'       => self::sanitize_int_range( $styles['buttonFontSize'] ?? $defaults['styles']['buttonFontSize'], 10, 32, $defaults['styles']['buttonFontSize'] ),
                'buttonRadius'         => self::sanitize_int_range( $styles['buttonRadius'] ?? $defaults['styles']['buttonRadius'], 0, 40, $defaults['styles']['buttonRadius'] ),
                'buttonPaddingY'       => self::sanitize_int_range( $styles['buttonPaddingY'] ?? $defaults['styles']['buttonPaddingY'], 4, 40, $defaults['styles']['buttonPaddingY'] ),
                'buttonPaddingX'       => self::sanitize_int_range( $styles['buttonPaddingX'] ?? $defaults['styles']['buttonPaddingX'], 4, 60, $defaults['styles']['buttonPaddingX'] ),
                'buttonPosition'      => self::sanitize_select( $styles['buttonPosition'] ?? $defaults['styles']['buttonPosition'], array( 'left', 'center', 'right' ), $defaults['styles']['buttonPosition'] ),
                'resultsTextColor'     => sanitize_hex_color( $styles['resultsTextColor'] ?? $defaults['styles']['resultsTextColor'] ) ?: $defaults['styles']['resultsTextColor'],
                'resultsFontSize'      => self::sanitize_int_range( $styles['resultsFontSize'] ?? $defaults['styles']['resultsFontSize'], 10, 32, $defaults['styles']['resultsFontSize'] ),
                'progressBg'           => sanitize_hex_color( $styles['progressBg'] ?? $defaults['styles']['progressBg'] ) ?: $defaults['styles']['progressBg'],
                'progressFill'         => sanitize_hex_color( $styles['progressFill'] ?? $defaults['styles']['progressFill'] ) ?: $defaults['styles']['progressFill'],
                'progressHeight'       => self::sanitize_int_range( $styles['progressHeight'] ?? $defaults['styles']['progressHeight'], 4, 30, $defaults['styles']['progressHeight'] ),
                'statusColor'          => sanitize_hex_color( $styles['statusColor'] ?? $defaults['styles']['statusColor'] ) ?: $defaults['styles']['statusColor'],
            ),
        );

        $answers = isset( $input['answers'] ) && is_array( $input['answers'] ) ? $input['answers'] : array();
        foreach ( $answers as $answer ) {
            if ( ! is_array( $answer ) ) {
                continue;
            }
            $text = sanitize_text_field( $answer['text'] ?? '' );
            if ( '' === $text ) {
                continue;
            }
            $sanitized['answers'][] = array(
                'id'    => sanitize_key( $answer['id'] ?? wp_generate_uuid4() ),
                'text'  => $text,
                'votes' => absint( $answer['votes'] ?? 0 ),
            );
        }

        if ( count( $sanitized['answers'] ) < 2 ) {
            $sanitized['answers'] = $defaults['answers'];
        }

        return $sanitized;
    }

    public static function sanitize_int_range( $value, $min, $max, $default ) {
        $value = is_numeric( $value ) ? (int) $value : $default;
        if ( $value < $min || $value > $max ) {
            return $default;
        }
        return $value;
    }

    public static function sanitize_select( $value, $allowed, $default ) {
        $value = sanitize_text_field( (string) $value );
        return in_array( $value, $allowed, true ) ? $value : $default;
    }

    public static function sanitize_datetime_local( $value ) {
        $value = sanitize_text_field( (string) $value );
        if ( '' === $value ) {
            return '';
        }
        $dt = date_create( $value );
        return $dt ? $dt->format( 'Y-m-d\TH:i' ) : '';
    }

    public static function apply_db_counts_to_data( $post_id, $data ) {
        if ( empty( $data['answers'] ) || ! is_array( $data['answers'] ) ) {
            return $data;
        }

        OliForge_Polls_DB::ensure_answer_rows( $post_id, $data['answers'] );
        $counts = OliForge_Polls_DB::get_counts( $post_id );

        foreach ( $data['answers'] as &$answer ) {
            $answer_id = sanitize_key( $answer['id'] ?? '' );
            $answer['votes'] = absint( $counts[ $answer_id ] ?? 0 );
        }
        unset( $answer );

        return $data;
    }

    public static function get_results( $post_id, $data = null ) {
        $data = is_array( $data ) ? $data : self::get_poll_data( $post_id );
        return OliForge_Polls_DB::get_results( $post_id, $data['answers'] ?? array() );
    }

    public static function total_votes( $data ) {
        $total = 0;
        if ( empty( $data['answers'] ) || ! is_array( $data['answers'] ) ) {
            return 0;
        }
        foreach ( $data['answers'] as $answer ) {
            $total += absint( $answer['votes'] ?? 0 );
        }
        return $total;
    }

    public static function has_user_voted( $post_id ) {
        if ( isset( $_COOKIE[ 'oliforge_polls_' . $post_id ] ) ) {
            return true;
        }

        return class_exists( 'OliForge_Polls_DB' ) ? OliForge_Polls_DB::has_vote( $post_id ) : false;
    }

    public static function is_poll_started( $data ) {
        if ( empty( $data['start_date'] ) ) {
            return true;
        }
        $start_ts = strtotime( $data['start_date'] );
        if ( ! $start_ts ) {
            return true;
        }
        return current_time( 'timestamp' ) >= $start_ts;
    }

    public static function is_poll_closed( $data ) {
        if ( empty( $data['end_date'] ) ) {
            return false;
        }
        $end_ts = strtotime( $data['end_date'] );
        if ( ! $end_ts ) {
            return false;
        }
        return current_time( 'timestamp' ) >= $end_ts;
    }

    public static function can_vote_now( $data ) {
        return self::is_poll_started( $data ) && ! self::is_poll_closed( $data );
    }

    public static function should_show_results( $data, $has_voted = false, $is_closed = false ) {
        $mode = isset( $data['results_visibility'] ) ? sanitize_key( $data['results_visibility'] ) : ( ! empty( $data['show_results_after_vote'] ) ? 'after_vote' : 'after_end' );

        if ( 'always' === $mode ) {
            return true;
        }

        if ( 'after_vote' === $mode ) {
            return $has_voted || $is_closed;
        }

        if ( 'after_end' === $mode ) {
            return $is_closed;
        }

        return false;
    }

    public static function build_inline_css( $styles ) {
        $styles = wp_parse_args( is_array( $styles ) ? $styles : array(), self::default_settings()['styles'] );

        $container_styles = array(
            'background:' . esc_attr( $styles['containerBg'] ),
            'border:' . absint( $styles['containerBorderWidth'] ) . 'px ' . esc_attr( $styles['containerBorderStyle'] ) . ' ' . esc_attr( $styles['containerBorderColor'] ),
            'border-radius:' . absint( $styles['containerRadius'] ) . 'px',
            'padding:' . absint( $styles['containerPadding'] ) . 'px',
            'box-shadow:' . esc_attr( $styles['containerShadow'] ),
        );

        $inline_styles = array(
            'container'  => implode( ';', $container_styles ),
            'question'   => 'font-family:' . esc_attr( $styles['questionFontFamily'] ) . ';font-size:' . absint( $styles['questionFontSize'] ) . 'px;font-weight:' . absint( $styles['questionFontWeight'] ) . ';color:' . esc_attr( $styles['questionColor'] ),
            'description'=> 'font-size:' . absint( $styles['descriptionFontSize'] ) . 'px;color:' . esc_attr( $styles['descriptionColor'] ),
            'answer'     => 'font-family:' . esc_attr( $styles['answerFontFamily'] ) . ';font-size:' . absint( $styles['answerFontSize'] ) . 'px;color:' . esc_attr( $styles['answerColor'] ) . ';background:' . esc_attr( $styles['answerBg'] ) . ';border:' . absint( $styles['answerBorderWidth'] ) . 'px solid ' . esc_attr( $styles['answerBorderColor'] ) . ';border-radius:' . absint( $styles['answerBorderRadius'] ) . 'px',
            'button'     => 'background:' . esc_attr( $styles['buttonBg'] ) . ';color:' . esc_attr( $styles['buttonTextColor'] ) . ';font-size:' . absint( $styles['buttonFontSize'] ) . 'px;border-radius:' . absint( $styles['buttonRadius'] ) . 'px;padding:' . absint( $styles['buttonPaddingY'] ) . 'px ' . absint( $styles['buttonPaddingX'] ) . 'px',
            'results'    => 'color:' . esc_attr( $styles['resultsTextColor'] ) . ';font-size:' . absint( $styles['resultsFontSize'] ) . 'px',
            'progress_bg'=> 'background:' . esc_attr( $styles['progressBg'] ) . ';height:' . absint( $styles['progressHeight'] ) . 'px',
            'progress'   => 'background:' . esc_attr( $styles['progressFill'] ),
            'status'     => 'color:' . esc_attr( $styles['statusColor'] ),
            'button_wrap' => 'text-align:' . esc_attr( $styles['buttonPosition'] ),
        );

        return array_map( 'safecss_filter_attr', $inline_styles );
    }

    public static function get_asset_url( $path ) {
        $min = '.min';
        if ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
            $min = '';
        }

        $extension = pathinfo( $path, PATHINFO_EXTENSION );
        $base = substr( $path, 0, -strlen( $extension ) - 1 );
        
        $min_path = OLIFORGE_POLLS_PATH . $base . '.min.' . $extension;
        if ( '' !== $min && file_exists( $min_path ) ) {
            return OLIFORGE_POLLS_URL . $base . '.min.' . $extension;
        }

        return OLIFORGE_POLLS_URL . $path;
    }


    public static function get_build_asset( $handle ) {
        $asset_file = OLIFORGE_POLLS_PATH . 'build/' . $handle . '.asset.php';
        $asset = file_exists( $asset_file ) ? require $asset_file : array(
            'dependencies' => array(),
            'version'      => OLIFORGE_POLLS_VERSION,
        );

        if ( empty( $asset['version'] ) ) {
            $asset['version'] = OLIFORGE_POLLS_VERSION;
        }

        if ( empty( $asset['dependencies'] ) || ! is_array( $asset['dependencies'] ) ) {
            $asset['dependencies'] = array();
        }

        return $asset;
    }

    public static function get_build_url( $path ) {
        return OLIFORGE_POLLS_URL . 'build/' . ltrim( $path, '/' );
    }

    public static function get_shortcode_id( $post_id ) {
        $shortcode_id = get_post_meta( $post_id, '_oliforge_polls_shortcode_id', true );
        if ( ! $shortcode_id ) {
            $shortcode_id = self::generate_unique_shortcode_id();
            update_post_meta( $post_id, '_oliforge_polls_shortcode_id', $shortcode_id );
        }
        return $shortcode_id;
    }

    private static function generate_unique_shortcode_id() {
        global $wpdb;
        $attempts = 0;
        $max_attempts = 100;

        while ( $attempts < $max_attempts ) {
            $id = (string) wp_rand( 100, 999 );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uniqueness check during ID generation; caching a per-attempt transient would be incorrect here.
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_oliforge_polls_shortcode_id' AND meta_value = %s LIMIT 1",
                $id
            ) );

            if ( ! $exists ) {
                return $id;
            }
            $attempts++;
        }

        // Fallback if somehow we can't find a unique 3-digit number (highly unlikely with 900 options)
        return (string) time();
    }
}