<?php
/**
 * Post Queue — Stores pre-generated posts waiting to be drip-published.
 * Works with AAP_Scheduler for timed publishing.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Queue {

    const TABLE_SUFFIX = 'aap_queue';

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    // -------------------------------------------------------------------------
    // Activation: Create Table
    // -------------------------------------------------------------------------

    public static function create_tables() {
        global $wpdb;
        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            niche        VARCHAR(255)        NOT NULL DEFAULT '',
            title        VARCHAR(500)        NOT NULL DEFAULT '',
            post_id      BIGINT(20) UNSIGNED          DEFAULT NULL,
            status       VARCHAR(32)         NOT NULL DEFAULT 'queued',
            language     VARCHAR(64)         NOT NULL DEFAULT 'English',
            category     VARCHAR(255)        NOT NULL DEFAULT '',
            error_msg    TEXT                         DEFAULT NULL,
            silo_id      BIGINT(20) UNSIGNED          DEFAULT NULL,
            silo_role    VARCHAR(32)                  DEFAULT NULL,
            scheduled_at DATETIME                     DEFAULT NULL,
            published_at DATETIME                     DEFAULT NULL,
            created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Run schema migration check for existing tables safely
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
            $cols = $wpdb->get_col( "DESCRIBE {$table}" );
            if ( ! empty( $cols ) ) {
                if ( ! in_array( 'silo_id', $cols, true ) ) {
                    $wpdb->query( "ALTER TABLE {$table} ADD COLUMN silo_id BIGINT(20) UNSIGNED DEFAULT NULL" );
                }
                if ( ! in_array( 'silo_role', $cols, true ) ) {
                    $wpdb->query( "ALTER TABLE {$table} ADD COLUMN silo_role VARCHAR(32) DEFAULT NULL" );
                }
                if ( ! in_array( 'tag_count', $cols, true ) ) {
                    $wpdb->query( "ALTER TABLE {$table} ADD COLUMN tag_count INT(3) UNSIGNED NOT NULL DEFAULT 0" );
                }
                if ( ! in_array( 'meta', $cols, true ) ) {
                    $wpdb->query( "ALTER TABLE {$table} ADD COLUMN meta TEXT DEFAULT NULL" );
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public static function enqueue( string $niche, string $title = '', string $language = 'English', string $category = '', int $post_id = 0, ?int $silo_id = null, ?string $silo_role = null, int $tag_count = 0, array $meta = [] ) {
        global $wpdb;
        $wpdb->insert( self::table_name(), [
            'niche'     => $niche,
            'title'     => $title,
            'language'  => $language,
            'category'  => $category,
            'post_id'   => $post_id ?: null,
            'status'    => 'queued',
            'silo_id'   => $silo_id,
            'silo_role' => $silo_role,
            'tag_count' => $tag_count,
            'meta'      => ! empty( $meta ) ? maybe_serialize( $meta ) : null,
        ], [ '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s' ] );
        return $wpdb->insert_id;
    }

    public static function get_next_queued() {
        global $wpdb;
        self::recover_stuck_tasks();

        // If the queue is currently locked, do not return any task for execution to prevent concurrent overlaps
        if ( get_transient( 'aap_queue_lock' ) ) {
            return null;
        }

        return $wpdb->get_row(
            "SELECT * FROM " . self::table_name() .
            " WHERE status = 'queued' ORDER BY created_at ASC LIMIT 1"
        );
    }

    public static function get_all( int $limit = 50, int $offset = 0 ) {
        global $wpdb;
        self::recover_stuck_tasks();
        $table = self::table_name();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ) );
    }

    public static function count_by_status( string $status = '' ) {
        global $wpdb;
        $table = self::table_name();
        if ( $status ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status
            ) );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    public static function mark_processing( int $id ) {
        global $wpdb;
        $wpdb->update( self::table_name(), [ 'status' => 'processing' ], [ 'id' => $id ] );
        set_transient( 'aap_queue_lock', $id, 900 ); // Lock the queue for 15 minutes
        set_transient( 'aap_task_processing_' . $id, time(), 900 ); // Track this specific task
    }

    public static function mark_published( int $id, int $post_id ) {
        global $wpdb;
        $wpdb->update( self::table_name(), [
            'status'       => 'published',
            'post_id'      => $post_id,
            'published_at' => current_time( 'mysql' ),
        ], [ 'id' => $id ] );
        delete_transient( 'aap_queue_lock' );
        delete_transient( 'aap_task_processing_' . $id );
    }

    public static function mark_failed( int $id, string $error = '' ) {
        global $wpdb;
        $wpdb->update( self::table_name(), [
            'status'    => 'failed',
            'error_msg' => $error ?: 'Unknown error',
        ], [ 'id' => $id ] );
        delete_transient( 'aap_queue_lock' );
        delete_transient( 'aap_task_processing_' . $id );
    }

    public static function recover_stuck_tasks() {
        global $wpdb;
        $table = self::table_name();
        
        // Find processing items
        $processing = $wpdb->get_results( "SELECT id FROM {$table} WHERE status = 'processing'" );
        if ( ! empty( $processing ) ) {
            foreach ( $processing as $item ) {
                // If there's no active processing transient, the task has timed out/crashed
                if ( ! get_transient( 'aap_task_processing_' . $item->id ) ) {
                    $wpdb->update( $table, [
                        'status'    => 'failed',
                        'error_msg' => __( 'Process timed out or crashed during execution.', 'ai-auto-post' ),
                    ], [ 'id' => $item->id ] );
                    
                    // Also clear global queue lock if it belonged to this task
                    $lock_id = get_transient( 'aap_queue_lock' );
                    if ( $lock_id == $item->id ) {
                        delete_transient( 'aap_queue_lock' );
                    }
                }
            }
        }
    }

    public static function mark_paused( int $id ) {
        global $wpdb;
        $wpdb->update( self::table_name(), [ 'status' => 'paused' ], [ 'id' => $id ] );
        
        // Clear transients to unlock queue if this was the processing task
        $lock_id = get_transient( 'aap_queue_lock' );
        if ( $lock_id == $id ) {
            delete_transient( 'aap_queue_lock' );
        }
        delete_transient( 'aap_task_processing_' . $id );
    }

    public static function mark_resumed( int $id ) {
        global $wpdb;
        $wpdb->update( self::table_name(), [
            'status'    => 'queued',
            'error_msg' => '',
        ], [ 'id' => $id ] );
    }

    public static function delete( int $id ) {
        global $wpdb;
        $wpdb->delete( self::table_name(), [ 'id' => $id ], [ '%d' ] );
        
        // Clear lock if currently processing task is deleted
        $lock_id = get_transient( 'aap_queue_lock' );
        if ( $lock_id == $id ) {
            delete_transient( 'aap_queue_lock' );
        }
        delete_transient( 'aap_task_processing_' . $id );
    }

    public static function delete_multiple( array $ids ) {
        global $wpdb;
        $ids_clean = array_map( 'intval', $ids );
        if ( empty( $ids_clean ) ) return;
        
        $ids_placeholder = implode( ',', $ids_clean );
        $table = self::table_name();
        $wpdb->query( "DELETE FROM {$table} WHERE id IN ($ids_placeholder)" );
        
        $lock_id = (int) get_transient( 'aap_queue_lock' );
        if ( $lock_id && in_array( $lock_id, $ids_clean, true ) ) {
            delete_transient( 'aap_queue_lock' );
        }
        foreach ( $ids_clean as $id ) {
            delete_transient( 'aap_task_processing_' . $id );
        }
    }

    public static function clear_all() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE " . self::table_name() );
        delete_transient( 'aap_queue_lock' );
    }
}
