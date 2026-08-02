<?php
namespace Ultimate_WP_Booster\Engine\Optimization;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class DatabaseOptimizer {

    public static function get_stats() {
        global $wpdb;

        // 1. Revisions count
        $revisions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );

        // 2. Auto drafts count
        $auto_drafts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );

        // 3. Trashed posts count
        $trash_posts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );

        // 4. Spam comments count
        $spam_comments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );

        // 5. Trashed comments count
        $trash_comments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );

        // 6. Expired transients count
        $now = time();
        $expired_transients = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
            '_transient_timeout_%',
            $now
        ) );

        // 7. Fragmented tables and overhead size
        $overhead_bytes = 0;
        $tables_to_optimize = 0;
        $tables_status = $wpdb->get_results( "SHOW TABLE STATUS LIKE '" . esc_sql( $wpdb->esc_like( $wpdb->prefix ) ) . "%'" );
        if ( is_array( $tables_status ) ) {
            foreach ( $tables_status as $table ) {
                if ( ! empty( $table->Data_free ) ) {
                    $overhead_bytes += (int) $table->Data_free;
                    $tables_to_optimize++;
                }
            }
        }

        return array(
            'revisions'          => $revisions,
            'auto_drafts'        => $auto_drafts,
            'trash_posts'        => $trash_posts,
            'spam_comments'      => $spam_comments,
            'trash_comments'     => $trash_comments,
            'expired_transients' => $expired_transients,
            'overhead_bytes'     => $overhead_bytes,
            'overhead_formatted' => size_format( $overhead_bytes ),
            'tables_to_optimize' => $tables_to_optimize,
        );
    }

    public static function optimize( $options ) {
        global $wpdb;
        $results = array(
            'revisions'          => 0,
            'auto_drafts'        => 0,
            'trash_posts'        => 0,
            'spam_comments'      => 0,
            'trash_comments'     => 0,
            'expired_transients' => 0,
            'optimized_tables'   => 0,
        );

        // 1. Clean Revisions
        if ( ! empty( $options['revisions'] ) ) {
            $revision_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'" );
            if ( ! empty( $revision_ids ) ) {
                foreach ( $revision_ids as $id ) {
                    wp_delete_post_revision( (int) $id );
                    $results['revisions']++;
                }
            }
        }

        // 2. Clean Auto Drafts
        if ( ! empty( $options['auto_drafts'] ) ) {
            $auto_draft_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
            if ( ! empty( $auto_draft_ids ) ) {
                foreach ( $auto_draft_ids as $id ) {
                    wp_delete_post( (int) $id, true );
                    $results['auto_drafts']++;
                }
            }
        }

        // 3. Clean Trash Posts
        if ( ! empty( $options['trash_posts'] ) ) {
            $trash_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'" );
            if ( ! empty( $trash_ids ) ) {
                foreach ( $trash_ids as $id ) {
                    wp_delete_post( (int) $id, true );
                    $results['trash_posts']++;
                }
            }
        }

        // 4. Clean Spam Comments
        if ( ! empty( $options['spam_comments'] ) ) {
            $spam_ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
            if ( ! empty( $spam_ids ) ) {
                foreach ( $spam_ids as $id ) {
                    wp_delete_comment( (int) $id, true );
                    $results['spam_comments']++;
                }
            }
        }

        // 5. Clean Trash Comments
        if ( ! empty( $options['trash_comments'] ) ) {
            $trash_comment_ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );
            if ( ! empty( $trash_comment_ids ) ) {
                foreach ( $trash_comment_ids as $id ) {
                    wp_delete_comment( (int) $id, true );
                    $results['trash_comments']++;
                }
            }
        }

        // 6. Clean Expired Transients
        if ( ! empty( $options['expired_transients'] ) ) {
            $now = time();
            $expired_transient_names = $wpdb->get_col( $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
                '_transient_timeout_%',
                $now
            ) );
            if ( ! empty( $expired_transient_names ) ) {
                foreach ( $expired_transient_names as $name ) {
                    $transient_name = str_replace( '_transient_timeout_', '', $name );
                    delete_transient( $transient_name );
                    $results['expired_transients']++;
                }
            }
        }

        // 7. Optimize/Repair Tables
        if ( ! empty( $options['optimize_tables'] ) ) {
            $tables_status = $wpdb->get_results( "SHOW TABLE STATUS LIKE '" . esc_sql( $wpdb->esc_like( $wpdb->prefix ) ) . "%'" );
            if ( is_array( $tables_status ) ) {
                foreach ( $tables_status as $table ) {
                    if ( ! empty( $table->Data_free ) ) {
                        $wpdb->query( "OPTIMIZE TABLE " . esc_sql( $table->Name ) );
                        $results['optimized_tables']++;
                    }
                }
            }
        }

        return $results;
    }
}
