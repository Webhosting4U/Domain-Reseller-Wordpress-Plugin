<?php
/**
 * Admin pages for order history and queue status with retry buttons.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Admin_History {

    /**
     * Render the order history page.
     *
     * @return void
     */
    public static function render_page() {
        if ( ! current_user_can( 'wh4u_manage_domains' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'wh4u-domains' ) );
        }

        $user_id  = get_current_user_id();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination and filter params
        $page     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status filter
        $status   = isset( $_GET['order_status'] ) ? sanitize_text_field( wp_unslash( $_GET['order_status'] ) ) : '';
        $per_page = 20;

        global $wpdb;
        $table = $wpdb->prefix . 'wh4u_orders';

        $where  = 'user_id = %d';
        $params = array( $user_id );

        $valid_statuses = array( 'pending', 'pending_manual', 'processing', 'completed', 'failed', 'cancelled' );
        if ( ! empty( $status ) && in_array( $status, $valid_statuses, true ) ) {
            $where   .= ' AND status = %s';
            $params[] = $status;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix, $where built from validated allowlisted values, dynamic param count
        $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) );

        $offset   = max( 0, ( $page - 1 ) * $per_page );
        $params[] = $per_page;
        $params[] = $offset;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix, $where built from validated allowlisted values, dynamic param count
        $orders = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $params
        ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $total_pages = (int) ceil( $total / $per_page );

        $status_labels = array(
            'pending'        => __( 'Pending', 'wh4u-domains' ),
            'pending_manual' => __( 'Pending Manual', 'wh4u-domains' ),
            'processing'     => __( 'Processing', 'wh4u-domains' ),
            'completed'      => __( 'Completed', 'wh4u-domains' ),
            'failed'         => __( 'Failed', 'wh4u-domains' ),
            'cancelled'      => __( 'Cancelled', 'wh4u-domains' ),
        );
        ?>
        <div class="wrap wh4u-admin-wrap">
            <h1><?php esc_html_e( 'Order History', 'wh4u-domains' ); ?></h1>

            <ul class="subsubsub">
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wh4u-domains-history' ) ); ?>"<?php echo empty( $status ) ? ' class="current"' : ''; ?>><?php esc_html_e( 'All', 'wh4u-domains' ); ?> <span class="count">(<?php echo esc_html( $total ); ?>)</span></a> |</li>
                <?php foreach ( $valid_statuses as $i => $s ) : ?>
                    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wh4u-domains-history&order_status=' . rawurlencode( $s ) ) ); ?>"<?php echo $status === $s ? ' class="current"' : ''; ?>><?php echo esc_html( $status_labels[ $s ] ); ?></a><?php echo $i < count( $valid_statuses ) - 1 ? ' |' : ''; ?></li>
                <?php endforeach; ?>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px;"><?php esc_html_e( 'ID', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Domain', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Period', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Retries', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Error', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'wh4u-domains' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $orders ) ) : ?>
                        <tr><td colspan="9"><?php esc_html_e( 'No orders found.', 'wh4u-domains' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $orders as $order ) : ?>
                        <tr>
                            <td><?php echo esc_html( $order->id ); ?></td>
                            <td><?php echo esc_html( $order->domain ); ?></td>
                            <td><?php echo esc_html( ucfirst( $order->order_type ) ); ?></td>
                            <td>
                                <span class="wh4u-status wh4u-status-<?php echo esc_attr( $order->status ); ?>">
                                    <?php echo esc_html( isset( $status_labels[ $order->status ] ) ? $status_labels[ $order->status ] : $order->status ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $order->reg_period . ' yr' ); ?></td>
                            <td><?php echo esc_html( $order->retry_count ); ?></td>
                            <td><?php echo $order->error_message ? esc_html( substr( $order->error_message, 0, 80 ) ) : '&mdash;'; ?></td>
                            <td><?php echo esc_html( $order->created_at ); ?></td>
                            <td>
                                <?php if ( $order->status === 'pending_manual' ) : ?>
                                    <button class="button button-small wh4u-process-now" data-order-id="<?php echo esc_attr( $order->id ); ?>">
                                        <?php esc_html_e( 'Process Now', 'wh4u-domains' ); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $pagination_args = array(
                        'base'    => add_query_arg( 'paged', '%#%' ),
                        'format'  => '',
                        'current' => $page,
                        'total'   => $total_pages,
                    );
                    echo wp_kses_post( paginate_links( $pagination_args ) );
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the public orders admin page using WP_Query on the CPT.
     *
     * @return void
     */
    public static function render_public_orders_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'wh4u-domains' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination
        $paged  = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status filter
        $status = isset( $_GET['order_status'] ) ? sanitize_text_field( wp_unslash( $_GET['order_status'] ) ) : '';

        $valid_statuses = array( 'wh4u-pending', 'wh4u-approved', 'wh4u-rejected' );
        $query_status   = ! empty( $status ) && in_array( $status, $valid_statuses, true )
            ? $status
            : array( 'wh4u-pending', 'wh4u-approved', 'wh4u-rejected' );

        $query = new WP_Query( array(
            'post_type'      => 'wh4u_public_order',
            'post_status'    => $query_status,
            'posts_per_page' => 20,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $status_labels = array(
            'wh4u-pending'  => __( 'Pending', 'wh4u-domains' ),
            'wh4u-approved' => __( 'Approved', 'wh4u-domains' ),
            'wh4u-rejected' => __( 'Rejected', 'wh4u-domains' ),
        );
        ?>
        <div class="wrap wh4u-admin-wrap">
            <h1><?php esc_html_e( 'Public Registration Orders', 'wh4u-domains' ); ?></h1>

            <ul class="subsubsub">
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wh4u-domains-public-orders' ) ); ?>"<?php echo empty( $status ) ? ' class="current"' : ''; ?>><?php esc_html_e( 'All', 'wh4u-domains' ); ?> <span class="count">(<?php echo esc_html( $query->found_posts ); ?>)</span></a> |</li>
                <?php foreach ( $valid_statuses as $i => $s ) : ?>
                    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wh4u-domains-public-orders&order_status=' . rawurlencode( $s ) ) ); ?>"<?php echo $status === $s ? ' class="current"' : ''; ?>><?php echo esc_html( $status_labels[ $s ] ); ?></a><?php echo $i < count( $valid_statuses ) - 1 ? ' |' : ''; ?></li>
                <?php endforeach; ?>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px;"><?php esc_html_e( 'ID', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Domain', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Phone', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Period', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'wh4u-domains' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! $query->have_posts() ) : ?>
                        <tr><td colspan="10"><?php esc_html_e( 'No public orders found.', 'wh4u-domains' ); ?></td></tr>
                    <?php else : ?>
                        <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <?php
                            $pid        = get_the_ID();
                            $post_obj   = get_post( $pid );
                            $order_stat = $post_obj->post_status;
                        ?>
                        <tr>
                            <td><?php echo esc_html( $pid ); ?></td>
                            <td><?php echo esc_html( get_post_meta( $pid, '_wh4u_domain', true ) ); ?></td>
                            <td><?php
                                $otype = get_post_meta( $pid, '_wh4u_order_type', true );
                                $otype_labels = array(
                                    'register' => __( 'Register', 'wh4u-domains' ),
                                    'transfer' => __( 'Transfer', 'wh4u-domains' ),
                                );
                                echo esc_html( isset( $otype_labels[ $otype ] ) ? $otype_labels[ $otype ] : __( 'Register', 'wh4u-domains' ) );
                            ?></td>
                            <td><?php echo esc_html( get_post_meta( $pid, '_wh4u_first_name', true ) . ' ' . get_post_meta( $pid, '_wh4u_last_name', true ) ); ?></td>
                            <td><?php echo esc_html( get_post_meta( $pid, '_wh4u_email', true ) ); ?></td>
                            <td><?php echo esc_html( get_post_meta( $pid, '_wh4u_phone', true ) ); ?></td>
                            <td><?php echo esc_html( get_post_meta( $pid, '_wh4u_reg_period', true ) . ' yr' ); ?></td>
                            <td>
                                <span class="wh4u-status wh4u-status-<?php echo esc_attr( str_replace( 'wh4u-', '', $order_stat ) ); ?>">
                                    <?php echo esc_html( isset( $status_labels[ $order_stat ] ) ? $status_labels[ $order_stat ] : $order_stat ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( get_the_date( 'Y-m-d H:i:s' ) ); ?></td>
                            <td>
                                <?php if ( $order_stat === 'wh4u-pending' ) : ?>
                                    <button type="button" class="button button-small button-primary wh4u-public-order-action" data-order-id="<?php echo esc_attr( $pid ); ?>" data-action="approve">
                                        <?php esc_html_e( 'Approve', 'wh4u-domains' ); ?>
                                    </button>
                                    <button type="button" class="button button-small wh4u-public-order-action" data-order-id="<?php echo esc_attr( $pid ); ?>" data-action="reject">
                                        <?php esc_html_e( 'Reject', 'wh4u-domains' ); ?>
                                    </button>
                                <?php elseif ( $order_stat === 'wh4u-approved' ) : ?>
                                    <span class="dashicons dashicons-yes-alt" style="color:#46b450;vertical-align:middle;"></span>
                                <?php elseif ( $order_stat === 'wh4u-rejected' ) : ?>
                                    <span class="dashicons dashicons-dismiss" style="color:#dc3232;vertical-align:middle;"></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $query->max_num_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php echo wp_kses_post( paginate_links( array(
                        'base'    => add_query_arg( 'paged', '%#%' ),
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $query->max_num_pages,
                    ) ) ); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        wp_reset_postdata();
    }

    /**
     * Render the queue status page.
     *
     * @return void
     */
    public static function render_queue_page() {
        if ( ! current_user_can( 'wh4u_manage_domains' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'wh4u-domains' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination
        $page     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status filter
        $q_status = isset( $_GET['q_status'] ) ? sanitize_text_field( wp_unslash( $_GET['q_status'] ) ) : '';

        $queue_args = array(
            'status'   => $q_status,
            'page'     => $page,
            'per_page' => 20,
        );

        if ( ! current_user_can( 'manage_options' ) ) {
            $queue_args['user_id'] = get_current_user_id();
        }

        $result = WH4U_Queue::get_items( $queue_args );

        $items       = $result['items'];
        $total       = $result['total'];
        $total_pages = (int) ceil( $total / 20 );

        $status_labels = array(
            'pending'    => __( 'Pending', 'wh4u-domains' ),
            'processing' => __( 'Processing', 'wh4u-domains' ),
            'completed'  => __( 'Completed', 'wh4u-domains' ),
            'failed'     => __( 'Failed', 'wh4u-domains' ),
            'cancelled'  => __( 'Cancelled', 'wh4u-domains' ),
        );
        ?>
        <div class="wrap wh4u-admin-wrap">
            <h1><?php esc_html_e( 'Queue Status', 'wh4u-domains' ); ?></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px;"><?php esc_html_e( 'ID', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Order ID', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Attempts', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Max', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Error', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Scheduled', 'wh4u-domains' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'wh4u-domains' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $items ) ) : ?>
                        <tr><td colspan="9"><?php esc_html_e( 'No queue items found.', 'wh4u-domains' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $items as $item ) : ?>
                        <tr>
                            <td><?php echo esc_html( $item->id ); ?></td>
                            <td><?php echo esc_html( $item->order_id ); ?></td>
                            <td><?php echo esc_html( $item->action ); ?></td>
                            <td><?php echo esc_html( $item->attempts ); ?></td>
                            <td><?php echo esc_html( $item->max_attempts ); ?></td>
                            <td>
                                <span class="wh4u-status wh4u-status-<?php echo esc_attr( $item->status ); ?>">
                                    <?php echo esc_html( isset( $status_labels[ $item->status ] ) ? $status_labels[ $item->status ] : $item->status ); ?>
                                </span>
                            </td>
                            <td><?php echo $item->last_error ? esc_html( substr( $item->last_error, 0, 80 ) ) : '&mdash;'; ?></td>
                            <td><?php echo esc_html( $item->scheduled_at ); ?></td>
                            <td>
                                <?php if ( in_array( $item->status, array( 'failed', 'pending' ), true ) ) : ?>
                                    <button class="button button-small wh4u-retry-queue" data-queue-id="<?php echo esc_attr( $item->id ); ?>">
                                        <?php esc_html_e( 'Retry Now', 'wh4u-domains' ); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php echo wp_kses_post( paginate_links( array(
                        'base'    => add_query_arg( 'paged', '%#%' ),
                        'current' => $page,
                        'total'   => $total_pages,
                    ) ) ); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
