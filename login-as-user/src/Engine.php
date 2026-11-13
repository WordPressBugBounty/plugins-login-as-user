<?php

declare(strict_types=1);

namespace Web357\WP\LoginAsUser;

defined('ABSPATH') || exit;

use Web357\WP\LoginAsUser\Hooks\AdminPageHook;
use Web357\WP\LoginAsUser\Hooks\LastLoginHook;
use Web357\WP\LoginAsUser\Hooks\RegisterAdminEndpointsHook;


/**
 * Boots the admin app layer (menu, admin-ajax API, last-login tracking) beside the legacy switching engine.
 */
final class Engine
{
    public const TEXT_DOMAIN = 'login-as-user';

    private static bool $booted = false;

    public static function init(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

         
        if (self::isProActive()) {
            // The Pro edition owns the admin app; this one only shows the "deactivate me" notice.
            return;
        }
         

        (new RegisterAdminEndpointsHook())->register();
        (new AdminPageHook())->register();
        (new LastLoginHook())->register();
        
    }

    public static function isPro(): bool
    {
        return false;
    }

    public static function version(): string
    {
        return defined('LOGINASUSER_VERSION') ? (string) LOGINASUSER_VERSION : '1.0.0';
    }

    public static function pluginFile(): string
    {
        return dirname(__DIR__) . '/' . 'login-as-user.php';
    }

     
    private static function isProActive(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active('login-as-user-pro/login-as-user-pro.php');
    }
     
}
