<?php

declare(strict_types=1);

namespace Web357\WP\LoginAsUser\Hooks;

defined('ABSPATH') || exit;

use Web357\WP\LoginAsUser\Api\SettingsApi;
use Web357\WP\LoginAsUser\Api\UsersApi;


class RegisterAdminEndpointsHook implements HookInterface
{
    public function register(): void
    {
        if (!is_admin()) {
            return;
        }
        (new UsersApi())->register();
        (new SettingsApi())->register();
        
    }
}
