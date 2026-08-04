<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OliForge_Polls_Shortcode {
    public static function init() {
        add_shortcode( 'oliforge-polls', array( __CLASS__, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
    }

    public static function register_assets() {
        wp_register_style(
            'oliforge-polls-frontend',
            OliForge_Polls_Util::get_build_url( 'style-frontend.css' ),
            array(),
            OLIFORGE_POLLS_VERSION
        );

        $asset = OliForge_Polls_Util::get_build_asset( 'frontend' );
        wp_register_script(
            'oliforge-polls-frontend',
            OliForge_Polls_Util::get_build_url( 'frontend.js' ),
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_localize_script(
            'oliforge-polls-frontend',
            'OliForgePolls',
            array(
                'restUrl' => esc_url_raw( rest_url( 'oliforge-polls/v1' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'i18n'    => array(
                    'vote'        => __( 'Vote', 'oliforge-polls' ),
                    'voted'       => __( 'Thanks! Your vote has been counted.', 'oliforge-polls' ),
                    'closed'      => __( 'Voting is closed.', 'oliforge-polls' ),
                    'chooseOption'=> __( 'Please choose an answer.', 'oliforge-polls' ),
                    'error'       => __( 'Could not save your vote. Please try again.', 'oliforge-polls' ),
                ),
            )
        );
    }

    public static function render_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
            ),
            $atts,
            'oliforge-polls'
        );

        $id = sanitize_text_field( $atts['id'] );
        if ( ! $id ) {
            return '';
        }

        $post_id = 0;
        if ( is_numeric( $id ) && strlen( $id ) <= 3 ) {
            $cache_key = 'oliforge_polls_sc_' . $id;
            $cached    = wp_cache_get( $cache_key, 'oliforge-polls' );
            if ( false !== $cached ) {
                $post_id = absint( $cached );
            } else {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- postmeta shortcode ID lookup; result is cached immediately below.
                $post_id = absint( $wpdb->get_var( $wpdb->prepare(
                    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_oliforge_polls_shortcode_id' AND meta_value = %s LIMIT 1",
                    $id
                ) ) );
                wp_cache_set( $cache_key, $post_id, 'oliforge-polls' );
            }
        }

        // Fallback to numeric ID as post_id if not found or not 3-digit
        if ( ! $post_id && is_numeric( $id ) ) {
            $post_id = absint( $id );
        }

        if ( ! OliForge_Polls_Util::is_public_poll( $post_id ) ) {
            return '';
        }

        if ( ! is_admin() ) {
            wp_enqueue_style( 'oliforge-polls-frontend' );
            wp_enqueue_script( 'oliforge-polls-frontend' );
        }

        return self::render_poll( $post_id );
    }

    public static function render_poll( $post_id ) {
        $post_id = absint( $post_id );

        if ( ! OliForge_Polls_Util::can_publicly_access_poll( $post_id ) ) {
            return '';
        }

        $data         = OliForge_Polls_Util::get_poll_data( $post_id );
        $styles       = OliForge_Polls_Util::build_inline_css( $data['styles'] );
        $result_data  = OliForge_Polls_Util::get_results( $post_id, $data );
        $total        = $result_data['total'];
        $result_items = $result_data['items'];
        $has_voted    = OliForge_Polls_Util::has_user_voted( $post_id );
        $is_started   = OliForge_Polls_Util::is_poll_started( $data );
        $is_closed    = OliForge_Polls_Util::is_poll_closed( $data );
        $can_vote     = OliForge_Polls_Util::can_vote_now( $data );
        $show_form    = $can_vote && ! $has_voted;
        $show_results = OliForge_Polls_Util::should_show_results( $data, $has_voted, $is_closed );
        $labels       = $data['labels'];
        $status_msg   = '';

        if ( ! $is_started ) {
            $status_msg = $labels['notStartedMessage'];
        } elseif ( $has_voted ) {
            $status_msg = $labels['alreadyVoted'];
        } elseif ( $is_closed ) {
            $status_msg = $labels['closedMessage'];
        }

        ob_start();
        ?>
        <div class="oliforge-polls" data-poll-id="<?php echo esc_attr( (string) $post_id ); ?>" data-results-visibility="<?php echo esc_attr( $data['results_visibility'] ?? 'after_vote' ); ?>" style="<?php echo esc_attr( $styles['container'] ); ?>" data-labels="<?php echo esc_attr( (string) wp_json_encode( $labels ) ); ?>">

            <?php if ( ! empty( $data['question'] ) && $show_form ) : ?>
                <div class="oliforge_polls__question" style="<?php echo esc_attr( $styles['question'] ); ?>"><?php echo esc_html( $data['question'] ); ?></div>
            <?php endif; ?>

            <?php if ( ! $is_started || $has_voted ) : ?>
                <div class="oliforge_polls__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></div>
            <?php endif; ?>

            <div class="oliforge_polls__status <?php echo ( $show_form && ! $status_msg ) ? 'is-hidden' : ''; ?>" style="<?php echo esc_attr( $styles['status'] ); ?>">
                <?php echo esc_html( $status_msg ); ?>
            </div>

            <?php if ( $show_form ) : ?>
                <div class="oliforge_polls__form-wrap">
                    <?php if ( ! empty( $data['description'] ) ) : ?>
                        <div class="oliforge_polls__description" style="<?php echo esc_attr( $styles['description'] ); ?>"><?php echo esc_html( $data['description'] ); ?></div>
                    <?php endif; ?>

                    <?php if ( ! empty( $data['start_date'] ) ) : ?>
                        <div class="oliforge_polls__meta" style="<?php echo esc_attr( $styles['description'] ); ?>">
                            <?php echo esc_html( str_replace( '%s', $data['start_date'], $labels['votingStarts'] ) ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $data['end_date'] ) ) : ?>
                        <div class="oliforge_polls__meta" style="<?php echo esc_attr( $styles['description'] ); ?>">
                            <?php echo esc_html( str_replace( '%s', $data['end_date'], $labels['votingEnds'] ) ); ?>
                        </div>
                    <?php endif; ?>

                    <form class="oliforge_polls__form">
                        <fieldset class="oliforge_polls__fieldset">
                            <legend class="screen-reader-text"><?php echo esc_html( $data['question'] ); ?></legend>
                            <div class="oliforge_polls__answers">
                                <?php foreach ( $data['answers'] as $index => $answer ) : ?>
                                    <label class="oliforge_polls__answer" style="<?php echo esc_attr( $styles['answer'] ); ?>">
                                        <input type="radio" name="oliforge_polls_answer_<?php echo esc_attr( $post_id ); ?>" value="<?php echo esc_attr( $index ); ?>" />
                                        <span><?php echo esc_html( $answer['text'] ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <div class="oliforge_polls__submit-wrap" style="<?php echo esc_attr( $styles['button_wrap'] ); ?>">
                            <button type="submit" class="oliforge_polls__submit" style="<?php echo esc_attr( $styles['button'] ); ?>"><?php echo esc_html( $labels['voteButton'] ); ?></button>
                        </div>
                        <div class="oliforge_polls__notice" aria-live="polite"></div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="oliforge_polls__results<?php echo $show_results ? '' : ' is-hidden'; ?>" style="<?php echo esc_attr( $styles['results'] ); ?>">
                <div class="oliforge_polls__results-total"><?php echo esc_html( str_replace( '%d', $total, $labels['totalVotes'] ) ); ?></div>

                <div class="oliforge_polls__result-items">
                    <?php foreach ( $result_items as $result_item ) : ?>
                        <div class="oliforge_polls__result-item" data-answer-id="<?php echo esc_attr( $result_item['id'] ); ?>">
                            <div class="oliforge_polls__result-head">
                                <span class="oliforge_polls__result-label"><?php echo esc_html( $result_item['label'] ); ?></span>
                                <span class="oliforge_polls__result-values"><?php echo esc_html( absint( $result_item['votes'] ) . ' / ' . $result_item['percent'] . '%' ); ?></span>
                            </div>
                            <div class="oliforge_polls__progress-bg" style="<?php echo esc_attr( $styles['progress_bg'] ); ?>">
                                <span class="oliforge_polls__progress-fill" style="width: <?php echo esc_attr( $result_item['percent'] ); ?>%; <?php echo esc_attr( $styles['progress'] ); ?>"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
