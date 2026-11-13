<?php

declare(strict_types=1);

namespace Web357\WP\LoginAsUser\Services;

defined('ABSPATH') || exit;

/**
 * Where a user "comes from": every user is a WP user; plugins add customer/subscriber/member sources.
 * A source exists only while its plugin is active. The ID sets back both the badges and the source filter.
 */
final class UserSources
{
    public const WP = 'wp';
    public const CACHE_SECONDS = 300;

    /**
     * Active sources as id => label (WP user first).
     *
     * @return array<string, string>
     */
    public static function active(): array
    {
        $sources = [self::WP => __('WP user', 'login-as-user')];
        $candidates = [
            'woocommerce' => __('WooCommerce customer', 'login-as-user'),
            'woocommerce-subscriptions' => __('WooCommerce Subscriptions subscriber', 'login-as-user'),
            'memberpress' => __('MemberPress member', 'login-as-user'),
            'surecart' => __('SureCart customer', 'login-as-user'),
        ];
        foreach ($candidates as $id => $label) {
            if (self::isActive($id)) {
                $sources[$id] = $label;
            }
        }
        return $sources;
    }

    public static function isActive(string $id): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($id . '/' . $id . '.php');
    }

    /**
     * IDs of the users that belong to a source. WooCommerce customers are cheap and read live;
     * the others are cached for a few minutes.
     *
     * @return int[]
     */
    public static function userIds(string $source): array
    {
        if ($source === self::WP || !isset(self::active()[$source])) {
            return [];
        }
        if ($source === 'woocommerce') {
            return self::queryIds($source);
        }
        $key = 'web357_lau_src_' . $source;
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }
        $ids = self::queryIds($source);
        set_transient($key, $ids, self::CACHE_SECONDS);
        return $ids;
    }

    /**
     * Source ids per user, for one page of users: [userId => ['wp', 'woocommerce', …]].
     *
     * @param int[] $userIds
     * @return array<int, string[]>
     */
    public static function badgesFor(array $userIds): array
    {
        $sets = [];
        foreach (array_keys(self::active()) as $source) {
            if ($source !== self::WP) {
                $sets[$source] = array_flip(self::userIds($source));
            }
        }
        $badges = [];
        foreach ($userIds as $userId) {
            $badges[$userId] = [self::WP];
            foreach ($sets as $source => $set) {
                if (isset($set[$userId])) {
                    $badges[$userId][] = $source;
                }
            }
        }
        return $badges;
    }

    /**
     * @return int[]
     */
    private static function queryIds(string $source): array
    {
        global $wpdb;
        $ids = [];
        switch ($source) {
            case 'woocommerce':
                // Customers are users with the customer role or flagged by WooCommerce after a paid order.
                $byRole = get_users(['role' => 'customer', 'fields' => 'ID']);
                $paying = get_users(['meta_key' => 'paying_customer', 'meta_value' => '1', 'fields' => 'ID']);
                $ids = array_merge($byRole, $paying);
                break;
            case 'woocommerce-subscriptions':
                $ids = self::subscriberIds();
                break;
            case 'memberpress':
                $table = $wpdb->prefix . 'mepr_transactions';
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin table name built from the WP prefix; result is cached in a transient by the caller.
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- as above.
                    $ids = $wpdb->get_col("SELECT DISTINCT user_id FROM {$table} WHERE status = 'complete' AND user_id > 0");
                }
                break;
            case 'surecart':
                $ids = get_users(['meta_key' => 'sc_customer_ids', 'meta_compare' => 'EXISTS', 'fields' => 'ID']);
                break;
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * @return array<int|string>
     */
    private static function subscriberIds(): array
    {
        global $wpdb;
        $hpos = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        if ($hpos) {
            $table = $wpdb->prefix . 'wc_orders';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WooCommerce HPOS table; cached by the caller.
            return $wpdb->get_col("SELECT DISTINCT customer_id FROM {$table} WHERE type = 'shop_subscription' AND customer_id > 0");
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WooCommerce posts storage; cached by the caller.
        return $wpdb->get_col("SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'shop_subscription' AND pm.meta_key = '_customer_user' AND pm.meta_value > 0");
    }
}
