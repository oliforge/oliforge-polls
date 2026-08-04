<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OliForge_Polls_DB {
    const DB_VERSION = '1.0.0';
    const OPTION_DB_VERSION = 'oliforge_polls_db_version';
    const VOTER_COOKIE = 'oliforge_polls_voter';

    public static function votes_table() {
        global $wpdb;
        return $wpdb->prefix . 'oliforge_polls_votes';
    }

    public static function counts_table() {
        global $wpdb;
        return $wpdb->prefix . 'oliforge_polls_vote_counts';
    }

    public static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $votes_table     = self::votes_table();
        $counts_table    = self::counts_table();

        $sql_votes = "CREATE TABLE {$votes_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            poll_id BIGINT UNSIGNED NOT NULL,
            answer_id VARCHAR(64) NOT NULL,
            voter_key VARCHAR(128) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            ip_hash CHAR(64) NULL,
            user_agent_hash CHAR(64) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_vote (poll_id, voter_key),
            KEY poll_id (poll_id),
            KEY answer_id (answer_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $sql_counts = "CREATE TABLE {$counts_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            poll_id BIGINT UNSIGNED NOT NULL,
            answer_id VARCHAR(64) NOT NULL,
            votes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY poll_answer (poll_id, answer_id),
            KEY poll_id (poll_id)
        ) {$charset_collate};";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required on activation to create or update plugin-owned custom tables.

        dbDelta( $sql_votes );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required on activation to create or update plugin-owned custom tables.
        dbDelta( $sql_counts );

        update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
    }

    public static function maybe_create_tables() {
        if ( self::DB_VERSION !== get_option( self::OPTION_DB_VERSION ) ) {
            self::create_tables();
        }
    }

    public static function migrate_meta_votes_to_counts() {
        $poll_ids = get_posts(
            array(
                'post_type'      => OliForge_Polls_CPT::POST_TYPE,
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'fields'         => 'ids',
            )
        );

        foreach ( $poll_ids as $poll_id ) {
            $data = get_post_meta( $poll_id, '_oliforge_polls_data', true );
            if ( empty( $data['answers'] ) || ! is_array( $data['answers'] ) ) {
                continue;
            }

            foreach ( $data['answers'] as $answer ) {
                $answer_id = isset( $answer['id'] ) ? sanitize_key( $answer['id'] ) : '';
                if ( '' === $answer_id ) {
                    continue;
                }

                $existing = self::get_count_for_answer( $poll_id, $answer_id );
                if ( $existing > 0 ) {
                    continue;
                }

                self::set_count( $poll_id, $answer_id, absint( $answer['votes'] ?? 0 ) );
            }
        }
    }

    public static function ensure_answer_rows( $poll_id, $answers ) {
        if ( empty( $answers ) || ! is_array( $answers ) ) {
            return;
        }

        foreach ( $answers as $answer ) {
            if ( empty( $answer['id'] ) ) {
                continue;
            }
            self::ensure_count_row( $poll_id, sanitize_key( $answer['id'] ) );
        }
    }

    public static function ensure_count_row( $poll_id, $answer_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom vote counters require atomic database writes.
        $wpdb->query( $wpdb->prepare(
                'INSERT IGNORE INTO %i (poll_id, answer_id, votes, updated_at) VALUES (%d, %s, 0, %s)',
                self::counts_table(),
                absint( $poll_id ),
                sanitize_key( $answer_id ),
                current_time( 'mysql' )
            )
        );
    }

    public static function set_count( $poll_id, $answer_id, $votes ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom vote counters require atomic database writes.
        $wpdb->query( $wpdb->prepare(
                'INSERT INTO %i (poll_id, answer_id, votes, updated_at) VALUES (%d, %s, %d, %s) ON DUPLICATE KEY UPDATE votes = VALUES(votes), updated_at = VALUES(updated_at)',
                self::counts_table(),
                absint( $poll_id ),
                sanitize_key( $answer_id ),
                absint( $votes ),
                current_time( 'mysql' )
            )
        );
    }

    public static function increment_count( $poll_id, $answer_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Query uses a plugin-owned custom table identifier with sanitized table name and prepared value placeholders.
        return false !== $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO %i (poll_id, answer_id, votes, updated_at) VALUES (%d, %s, 1, %s) ON DUPLICATE KEY UPDATE votes = votes + 1, updated_at = VALUES(updated_at)',
                self::counts_table(),
                absint( $poll_id ),
                sanitize_key( $answer_id ),
                current_time( 'mysql' )
            )
        );
    }

    public static function get_count_for_answer( $poll_id, $answer_id ) {
        global $wpdb;
        return absint(
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Real-time custom table lookup for voting integrity.
            $wpdb->get_var( $wpdb->prepare(
                    'SELECT votes FROM %i WHERE poll_id = %d AND answer_id = %s LIMIT 1',
                    self::counts_table(),
                    absint( $poll_id ),
                    sanitize_key( $answer_id )
                )
            )
        );
    }

    public static function get_counts( $poll_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Query uses a plugin-owned custom table identifier with sanitized table name and prepared value placeholders.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT answer_id, votes FROM %i WHERE poll_id = %d',
                self::counts_table(),
                absint( $poll_id )
            ),
            ARRAY_A
        );

        $counts = array();
        foreach ( $rows as $row ) {
            $counts[ $row['answer_id'] ] = absint( $row['votes'] );
        }

        return $counts;
    }

    public static function get_results( $poll_id, $answers ) {
        self::ensure_answer_rows( $poll_id, $answers );
        $counts = self::get_counts( $poll_id );
        $total  = array_sum( $counts );
        $items  = array();

        foreach ( $answers as $answer ) {
            $answer_id = sanitize_key( $answer['id'] ?? '' );
            $votes     = absint( $counts[ $answer_id ] ?? 0 );
            $items[]   = array(
                'id'      => $answer_id,
                'label'   => sanitize_text_field( $answer['text'] ?? '' ),
                'votes'   => $votes,
                'percent' => $total > 0 ? round( ( $votes / $total ) * 100, 1 ) : 0,
            );
        }

        return array(
            'total' => absint( $total ),
            'items' => $items,
        );
    }

    public static function has_vote( $poll_id, $voter_key = '' ) {
        global $wpdb;
        $voter_key = $voter_key ? sanitize_text_field( $voter_key ) : self::get_voter_key( false );

        if ( '' === $voter_key ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Query uses a plugin-owned custom table identifier with sanitized table name and prepared value placeholders.
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE poll_id = %d AND voter_key = %s LIMIT 1',
                self::votes_table(),
                absint( $poll_id ),
                $voter_key
            )
        );
    }

    public static function record_vote( $poll_id, $answer_id ) {
        global $wpdb;

        $poll_id    = absint( $poll_id );
        $answer_id  = sanitize_key( $answer_id );
        $voter_key  = self::get_voter_key( true );
        $user_id    = get_current_user_id();
        $created_at = current_time( 'mysql' );

        if ( '' === $answer_id || '' === $voter_key ) {
            return new WP_Error( 'invalid_vote', __( 'Invalid vote data.', 'oliforge-polls' ) );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- write path for plugin-owned vote table; no caching applies to INSERT.
        $inserted = $wpdb->insert(
            self::votes_table(),
            array(
                'poll_id'         => $poll_id,
                'answer_id'       => $answer_id,
                'voter_key'       => $voter_key,
                'user_id'         => $user_id,
                'ip_hash'         => self::hash_ip(),
                'user_agent_hash' => self::hash_user_agent(),
                'created_at'      => $created_at,
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            if ( false !== strpos( strtolower( (string) $wpdb->last_error ), 'duplicate' ) ) {
                return new WP_Error( 'duplicate_vote', __( 'You have already voted.', 'oliforge-polls' ) );
            }
            return new WP_Error( 'db_error', __( 'Could not save your vote.', 'oliforge-polls' ) );
        }

        self::increment_count( $poll_id, $answer_id );
        return true;
    }


    public static function reset_poll( $poll_id ) {
        global $wpdb;
        $poll_id = absint( $poll_id );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to reset plugin-owned vote data.
        $wpdb->delete( self::votes_table(), array( 'poll_id' => $poll_id ), array( '%d' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Required to reset plugin-owned vote data.
        $wpdb->delete( self::counts_table(), array( 'poll_id' => $poll_id ), array( '%d' ) );

        $data = OliForge_Polls_Util::get_poll_data( $poll_id );
        self::ensure_answer_rows( $poll_id, $data['answers'] ?? array() );
    }

    public static function recount_poll( $poll_id ) {
        global $wpdb;
        $poll_id = absint( $poll_id );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to reset plugin-owned vote data.
        $wpdb->delete( self::counts_table(), array( 'poll_id' => $poll_id ), array( '%d' ) );

        $data = OliForge_Polls_Util::get_poll_data( $poll_id );
        self::ensure_answer_rows( $poll_id, $data['answers'] ?? array() );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Query uses a plugin-owned custom table identifier with sanitized table name and prepared value placeholders.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT answer_id, COUNT(*) AS votes FROM %i WHERE poll_id = %d GROUP BY answer_id',
                self::votes_table(),
                $poll_id
            ),
            ARRAY_A
        );

        foreach ( $rows as $row ) {
            self::set_count( $poll_id, sanitize_key( $row['answer_id'] ), absint( $row['votes'] ) );
        }
    }

    public static function get_vote_rows( $poll_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Query uses a plugin-owned custom table identifier with sanitized table name and prepared value placeholders.
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, poll_id, answer_id, voter_key, user_id, ip_hash, user_agent_hash, created_at FROM %i WHERE poll_id = %d ORDER BY created_at DESC, id DESC',
                self::votes_table(),
                absint( $poll_id )
            ),
            ARRAY_A
        );
    }

    public static function get_voter_key( $create = true ) {
        if ( is_user_logged_in() ) {
            return 'user:' . get_current_user_id();
        }

        $cookie_name = self::VOTER_COOKIE;
        if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
            $token = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
            if ( preg_match( '/^[A-Za-z0-9]{32,64}$/', $token ) ) {
                return 'anon:' . hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
            }
        }

        if ( ! $create ) {
            return '';
        }

        $token = wp_generate_password( 48, false, false );
        setcookie( $cookie_name, $token, time() + YEAR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
        $_COOKIE[ $cookie_name ] = $token;

        return 'anon:' . hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
    }

    private static function hash_ip() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        return $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : null;
    }

    private static function hash_user_agent() {
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        return $ua ? hash_hmac( 'sha256', $ua, wp_salt( 'auth' ) ) : null;
    }

    public static function drop_tables() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin uninstall; intentional schema removal of plugin-owned tables.
        $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::votes_table() ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin uninstall; intentional schema removal of plugin-owned tables.
        $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::counts_table() ) );
        delete_option( self::OPTION_DB_VERSION );
    }
}