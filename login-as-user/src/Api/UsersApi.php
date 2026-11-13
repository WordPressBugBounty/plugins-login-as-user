<?php

declare(strict_types=1);

namespace Web357\WP\LoginAsUser\Api;

defined('ABSPATH') || exit;

use WP_User;
use WP_User_Query;
use Web357\WP\LoginAsUser\Hooks\LastLoginHook;
use Web357\WP\LoginAsUser\Services\DateFormat;
use Web357\WP\LoginAsUser\Services\UserSources;

/**
 * The Users screen: one page of users with role, source, last login and, where the current user is allowed,
 * the nonce-protected URL of the existing switch request.
 */
class UsersApi extends AdminApiAbstract
{
    protected string $namespace = 'users';

    protected array $endpoints = [
        'list' => ['method' => 'GET', 'cap' => 'edit_users'],
    ];

    private const ORDER_COLUMNS = [
        'name' => 'display_name',
        'email' => 'user_email',
        'registered' => 'user_registered',
    ];

    public function actionList(): array
    {
        $page = max(1, (int) $this->query('page', '1'));
        $perPage = min(25, max(1, (int) $this->query('perPage', '20')));
        $search = trim($this->query('search'));
        $role = $this->query('role');
        $source = $this->query('source');
        $orderBy = self::ORDER_COLUMNS[$this->query('orderby')] ?? 'user_registered';
        $order = strtoupper($this->query('order')) === 'ASC' ? 'ASC' : 'DESC';

        $args = [
            'number' => $perPage,
            'paged' => $page,
            'count_total' => true,
            'orderby' => $orderBy,
            'order' => $order,
        ];
        if ($search !== '') {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name', 'user_nicename'];
        }
        if ($role !== '' && wp_roles()->is_role($role)) {
            $args['role'] = $role;
        }
        if ($source !== '' && $source !== UserSources::WP && isset(UserSources::active()[$source])) {
            // An empty include would mean "no restriction", so a source without users matches nobody.
            $args['include'] = UserSources::userIds($source) ?: [0];
        }

        $query = new WP_User_Query($args);
        $users = $query->get_results();
        $badges = UserSources::badgesFor(array_map(static fn(WP_User $user): int => (int) $user->ID, $users));
        $sourceLabels = UserSources::active();
        $total = (int) $query->get_total();

        $rows = [];
        foreach ($users as $user) {
            $rows[] = $this->row($user, $badges[$user->ID] ?? [UserSources::WP], $sourceLabels);
        }
        return [
            'users' => $rows,
            'total' => $total,
            'totalPages' => (int) ceil($total / $perPage),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * @param string[] $sourceIds
     * @param array<string, string> $sourceLabels
     */
    private function row(WP_User $user, array $sourceIds, array $sourceLabels): array
    {
        $roleNames = wp_roles()->get_names();
        $roles = [];
        foreach ($user->roles as $roleSlug) {
            $roles[] = ['value' => $roleSlug, 'label' => translate_user_role($roleNames[$roleSlug] ?? $roleSlug)];
        }

        $switchUrl = null;
        $reason = '';
        if ((int) $user->ID === get_current_user_id()) {
            $reason = __('This is your own account.', 'login-as-user');
        } elseif (!current_user_can('login_as_user', $user->ID)) {
            $reason = __('You are not allowed to log in as this user.', 'login-as-user');
        } else {
            // wp_nonce_url() returns the URL HTML-escaped (&#038;) for printing; JSON and href need the plain URL.
            $switchUrl = html_entity_decode(\w357LoginAsUser::loginasuser_url($user), ENT_QUOTES, 'UTF-8');
        }

        $proLocked = false;
         
        // SureCart customers and WooCommerce Subscriptions subscribers can be switched to in PRO only: no URL is handed out.
        if ($switchUrl !== null && array_intersect($sourceIds, ['surecart', 'woocommerce-subscriptions'])) {
            $switchUrl = null;
            $proLocked = true;
        }
         

        $lastLogin = (string) get_user_meta($user->ID, LastLoginHook::META_KEY, true);
        return [
            'id' => (int) $user->ID,
            'name' => $user->display_name,
            'login' => $user->user_login,
            'email' => $user->user_email,
            'avatar' => get_avatar_url($user->ID, ['size' => 64]),
            'roles' => $roles,
            'sources' => array_map(static fn(string $id): array => ['id' => $id, 'label' => $sourceLabels[$id] ?? $id], $sourceIds),
            'registered' => DateFormat::utcToSite($user->user_registered, false),
            'lastLogin' => $lastLogin !== '' ? DateFormat::utcToSite($lastLogin, true) : null,
            'switchUrl' => $switchUrl,
            'proLocked' => $proLocked,
            'disabledReason' => $reason,
        ];
    }
}
