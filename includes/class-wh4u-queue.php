<?php
/**
 * Retry queue for failed API operations with exponential backoff.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Queue {

    /**
     * Backoff delays in seconds for each attempt (exponential).
     *
     * @var array<int>
     */
    private static $backoff_delays = array( 30, 120, 480, 1920, 7200 );

    /** @var int Maximum items to process per cron run. */
    private static $batch_size = 10;

    /**
     * Enqueue a failed order for retry.
     *
     * @param int    $order_id     Order ID.
     * @param string $action       API action path.
     * @param array  $payload      Request payload.
     * @param int    $max_attempts Maximum retry attempts.
     * @return int|false Queue item ID or false.
     */
    public static function enqueue( $order_id, $action, $payload, $max_attempts = 5 ) {
        global $wpdb;

        $delay        = isset( self::$backoff_delays[0] ) ? self::$backoff_delays[0] : 30;
        $scheduled_at = gmdate( 'Y-m-d H:i:s', time() + $delay );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result = $wpdb->insert(
            $wpdb->prefix . 'wh4u_queue',
            array(
                'order_id'     => absint( $order_id ),
                'action'       => sanitize_text_field( $action ),
                'payload'      => wp_json_encode( $payload ),
                'attempts'     => 0,
                'max_attempts' => absint( $max_attempts ),
                'status'       => 'pending',
                'scheduled_at' => $scheduled_at,
            ),
            array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        if ( $result === false ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->update(
            $wpdb->prefix . 'wh4u_orders',
            array(
                'status'        => 'pending',
                'next_retry_at' => $scheduled_at,
                'updated_at'    => current_time( 'mysql', true ),
            ),
            array( 'id' => absint( $order_id ) ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        return $wpdb->insert_id;
    }

    /**
     * Process pending queue items (called by WP-Cron).
     *
     * @return int Number of items processed.
     */
    public static function process_pending() {
        global $wpdb;

        $table = $wpdb->prefix . 'wh4u_queue';
        $now   = current_time( 'mysql', true );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom table, time-sensitive queue query, $table from $wpdb->prefix
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT %d",
                $now,
                self::$batch_size
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( empty( $items ) ) {
            return 0;
        }

        $processed = 0;

        foreach ( $items as $item ) {
            self::process_item( $item );
            $processed++;
        }

        return $processed;
    }

    /**
     * Process a single queue item.
     *
     * @param object $item Queue row object.
     * @return bool True if completed, false if failed/re-queued.
     */
    private static function process_item( $item ) {
        global $wpdb;

        $table_queue  = $wpdb->prefix . 'wh4u_queue';
        $table_orders = $wpdb->prefix . 'wh4u_orders';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->update(
            $table_queue,
            array( 'status' => 'processing' ),
            array( 'id' => $item->id ),
            array( '%s' ),
            array( '%d' )
        );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom table, queue processing, $table_orders from $wpdb->prefix
        $order = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table_orders} WHERE id = %d", (int) $item->order_id )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( ! $order ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->update(
                $table_queue,
                array( 'status' => 'cancelled', 'last_error' => 'Order not found', 'processed_at' => current_time( 'mysql', true ) ),
                array( 'id' => $item->id ),
                array( '%s', '%s', '%s' ),
                array( '%d' )
            );
            return false;
        }

        $client = WH4U_Api_Client::from_user( $order->user_id );

        if ( is_wp_error( $client ) ) {
            self::handle_failure( $item, $order, $client->get_error_message() );
            return false;
        }

        $payload  = json_decode( $item->payload, true );
        $response = $client->post( $item->action, is_array( $payload ) ? $payload : array() );

        if ( is_wp_error( $response ) ) {
            self::handle_failure( $item, $order, $response->get_error_message() );
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->update(
            $table_queue,
            array(
                'status'       => 'completed',
                'attempts'     => $item->attempts + 1,
                'processed_at' => current_time( 'mysql', true ),
            ),
            array( 'id' => $item->id ),
            array( '%s', '%d', '%s' ),
            array( '%d' )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->update(
            $table_orders,
            array(
                'status'       => 'completed',
                'api_response' => wp_json_encode( WH4U_Logger::mask_secrets( $response ) ),
                'retry_count'  => $item->attempts + 1,
                'updated_at'   => current_time( 'mysql', true ),
            ),
            array( 'id' => $order->id ),
            array( '%s', '%s', '%d', '%s' ),
            array( '%d' )
        );

        return true;
    }

    /**
     * Handle a failed queue item: re-schedule or mark as permanently failed.
     *
     * @param object $item         Queue row.
     * @param object $order        Order row.
     * @param string $error_message Error description.
     * @return void
     */
    private static function handle_failure( $item, $order, $error_message ) {
        global $wpdb;

        $table_queue  = $wpdb->prefix . 'wh4u_queue';
        $table_orders = $wpdb->prefix . 'wh4u_orders';

        $new_attempts = $item->attempts + 1;

        if ( $new_attempts >= $item->max_attempts ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->update(
                $table_queue,
                array(
                    'status'       => 'failed',
                    'attempts'     => $new_attempts,
                    'last_error'   => sanitize_text_field( $error_message ),
                    'processed_at' => current_time( 'mysql', true ),
                ),
                array( 'id' => $item->id ),
                array( '%s', '%d', '%s', '%s' ),
                array( '%d' )
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->update(
                $table_orders,
                array(
                    'status'        => 'failed',
                    'error_message' => sanitize_text_field( $error_message ),
                    'retry_count'   => $new_attempts,
                    'updated_at'    => current_time( 'mysql', true ),
                ),
                array( 'id' => $order->id ),
                array( '%s', '%s', '%d', '%s' ),
                array( '%d' )
            );

            WH4U_Notifications::send_order_notification( $order->id, 'failed' );

            return;
        }

        $delay_index = min( $new_attempts - 1, count( self::$backoff_delays ) - 1 );
        $delay       = self::$backoff_delays[ $delay_index ];
        $next_run    = gmdate( 'Y-m-d H:i:s', time() + $delay );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->update(
            $table_queue,
            array(
                'status'       => 'pending',
                'attempts'     => $new_attempts,
                'last_error'   => sanitize_text_field( $error_message ),
                'scheduled_at' => $next_run,
            ),
            array( 'id' => $item->id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->update(
            $table_orders,
            array(
                'retry_count'   => $new_attempts,
                'next_retry_at' => $next_run,
                'error_message' => sanitize_text_field( $error_message ),
                'updated_at'    => current_time( 'mysql', true ),
            ),
            array( 'id' => $order->id ),
            array( '%d', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Manually retry a specific queue item (resets attempts).
     *
     * @param int $queue_id Queue item ID.
     * @return bool True on success.
     */
    public static function manual_retry( $queue_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wh4u_queue';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom table, $table from $wpdb->prefix
        $item = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $queue_id )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ( ! $item ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->update(
            $table,
            array(
                'status'       => 'pending',
                'attempts'     => 0,
                'last_error'   => null,
                'scheduled_at' => current_time( 'mysql', true ),
                'processed_at' => null,
            ),
            array( 'id' => $queue_id ),
            array( '%s', '%d', '%s', '%s', '%s' ),
            array( '%d' )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->update(
            $wpdb->prefix . 'wh4u_orders',
            array(
                'status'     => 'pending',
                'updated_at' => current_time( 'mysql', true ),
            ),
            array( 'id' => $item->order_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return true;
    }

    /**
     * Get queue items with pagination.
     *
     * @param array $args Query arguments.
     * @return array{items: array, total: int}
     */
    public static function get_items( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'   => '',
            'user_id'  => 0,
            'per_page' => 20,
            'page'     => 1,
        );
        $args       = wp_parse_args( $args, $defaults );
        $table_q    = $wpdb->prefix . 'wh4u_queue';
        $table_o    = $wpdb->prefix . 'wh4u_orders';

        $where  = '1=1';
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND q.status = %s';
            $params[] = sanitize_text_field( $args['status'] );
        }

        if ( $args['user_id'] > 0 ) {
            $where   .= ' AND o.user_id = %d';
            $params[] = absint( $args['user_id'] );
        }

        $offset   = max( 0, ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] ) );
        $per_page = absint( $args['per_page'] );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_q, $table_o from $wpdb->prefix, $where built from validated values
        $count_sql = "SELECT COUNT(*) FROM {$table_q} q INNER JOIN {$table_o} o ON q.order_id = o.id WHERE {$where}";
        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $count_sql is safe
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $count_sql is safe, no user input
            $total = (int) $wpdb->get_var( $count_sql );
        }

        $params[] = $per_page;
        $params[] = $offset;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom table, paginated list query
        $items = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_q, $table_o from $wpdb->prefix, $where built from validated values
            $wpdb->prepare( "SELECT q.* FROM {$table_q} q INNER JOIN {$table_o} o ON q.order_id = o.id WHERE {$where} ORDER BY q.scheduled_at DESC LIMIT %d OFFSET %d", $params )
        );

        return array(
            'items' => $items ? $items : array(),
            'total' => $total,
        );
    }
}
