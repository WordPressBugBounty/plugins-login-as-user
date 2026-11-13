<?php

declare(strict_types=1);

namespace Web357\WP\LoginAsUser\Hooks;

defined('ABSPATH') || exit;

use WP_User;

/**
 * Records the time of each real sign-in (UTC) for the Users screen. Switching accounts sets auth cookies
 * directly and never fires `wp_login`, so impersonation does not touch this value.
 */
class LastLoginHook implements HookInterface
{
    public const META_KEY = 'web357_lau_last_login';

    public function register(): void
    {
        add_action('wp_login', [$this, 'record'], 10, 2);
    }

    /**
     * @param string $userLogin
     * @param WP_User $user
     */
    public function record($userLogin, $user): void
    {
        if ($user instanceof WP_User) {
            update_user_meta($user->ID, self::META_KEY, gmdate('Y-m-d H:i:s'));
        }
    }
}
