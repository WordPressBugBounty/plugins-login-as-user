<?php

declare(strict_types=1);

namespace Web357\WP\LoginAsUser\Hooks;

defined('ABSPATH') || exit;

use Web357\WP\LoginAsUser\Api\AdminApiAbstract;
use Web357\WP\LoginAsUser\Engine;

class AdminPageHook implements HookInterface
{
    public const PAGE = 'login-as-user';
    public const UPGRADE_URL = 'https://www.web357.com/login-as-user-wordpress-plugin?utm_source=AdminApp&utm_medium=BuyProLink&utm_content=loginasuserwp&utm_campaign=upgrade-pro';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_init', [$this, 'redirectLegacySettingsUrl']);
        add_filter('admin_footer_text', [$this, 'ratingFooterText']);
        if (is_admin()) {
            add_filter('plugin_action_links_' . plugin_basename(Engine::pluginFile()), [$this, 'displaySettingsLink']);
        }
    }

    /**
     * URL of the admin app, e.g. url('#/settings').
     */
    public static function url(string $hash = ''): string
    {
        return admin_url('admin.php?page=' . self::PAGE) . $hash;
    }

    public function registerAdminMenu(): void
    {
        $title = __('Login as User', 'login-as-user');

        add_menu_page($title, $title, 'edit_users', self::PAGE, [$this, 'renderPluginPage'], 'dashicons-admin-users', 71);
        $screens = [
            '' => [__('Users', 'login-as-user'), 'edit_users'],
            '#/audit-log' => [__('Audit Log', 'login-as-user'), 'manage_options'],
            '#/settings' => [__('Settings', 'login-as-user'), 'manage_options'],
        ];
        foreach ($screens as $hash => [$label, $screenCap]) {
            // Free edition: flag the Pro-only screen in the sidebar (the page title stays plain text).
            $menuLabel = $hash === '#/audit-log' && !Engine::isPro() ? $label . self::proBadge() : $label;
            add_submenu_page(self::PAGE, $label, $menuLabel, $screenCap, self::PAGE . $hash, [$this, 'renderPluginPage']);
        }
    }

    /**
     * Small red "PRO" tag for a sidebar item. Inline styles: the menu shows on every admin page, where the app's CSS is not loaded.
     */
    private static function proBadge(): string
    {
        return ' <span style="display:inline-block;margin-left:4px;padding:2px 5px;border-radius:3px;background:#c02b2b;color:#fff;font-size:9px;font-weight:700;line-height:1;letter-spacing:.3px;vertical-align:middle">'
            . esc_html__('PRO', 'login-as-user') . '</span>';
    }

    /**
     * The settings page used to live under Settings → Login as User; send old bookmarks to the new screen.
     */
    public function redirectLegacySettingsUrl(): void
    {
        $target = $this->legacySettingsRedirect();
        if ($target !== null) {
            wp_safe_redirect($target);
            exit;
        }
    }

    /**
     * Where the current request should be sent when it asks for the old settings page, null otherwise.
     */
    public function legacySettingsRedirect(): ?string
    {
        global $pagenow;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, only compared against a fixed list.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($pagenow === 'options-general.php' && in_array($page, ['login-as-user', 'login-as-user-pro'], true) && current_user_can('manage_options')) {
            return self::url('#/settings');
        }
        return null;
    }

    /**
     * "Please rate us" line in the admin footer, on this plugin's screens only (both editions; links point to the Free listing on WordPress.org).
     *
     * @param mixed $text Default footer text.
     * @return mixed
     */
    public function ratingFooterText($text)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, self::PAGE) !== 0) {
            return $text;
        }
        $link = '<a href="https://wordpress.org/support/plugin/login-as-user/reviews/?filter=5#new-post" target="_blank" rel="noopener noreferrer">&#9733;&#9733;&#9733;&#9733;&#9733;</a>';
        return sprintf(
            /* translators: 1: plugin name, 2: five-star link, 3: WordPress.org link */
            __('Please rate %1$s %2$s on %3$s to help us spread the word.', 'login-as-user'),
            '<strong>Login as User</strong>',
            $link,
            '<a href="https://wordpress.org/plugins/login-as-user/" target="_blank" rel="noopener noreferrer">WordPress.org</a>'
        );
    }

    public function displaySettingsLink(array $links): array
    {
        if (current_user_can('manage_options')) {
            $links[] = '<a href="' . esc_url(self::url('#/settings')) . '">'
                . esc_html__('Settings', 'login-as-user') . '</a>';
        }
        return $links;
    }

    public function renderPluginPage(): void
    {
        $base = plugins_url('', Engine::pluginFile());
        $version = Engine::version();
        $dir = dirname(Engine::pluginFile());
        // Bust caches whenever the built bundle changes, not only on version bumps.
        $assetVersion = $version . '.' . (int) @filemtime($dir . '/public/js/login-as-user-admin.js');
        wp_enqueue_script('web357-lau-admin', $base . '/public/js/login-as-user-admin.js', ['wp-i18n'], $assetVersion, true);
        wp_set_script_translations('web357-lau-admin', Engine::TEXT_DOMAIN, $dir . '/languages');
        wp_enqueue_style('web357-lau-admin', $base . '/public/css/login-as-user-admin.css', [], $assetVersion);
        $data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(AdminApiAbstract::NONCE_ACTION),
            'version' => $version,
            'edition' => Engine::isPro() ? 'pro' : 'free',
            'canManage' => current_user_can('manage_options'),
            'homeUrl' => home_url('/'),
            'upgradeUrl' => self::UPGRADE_URL,
            'imagesUrl' => $base . '/public/images/',
        ];
        
        wp_localize_script('web357-lau-admin', 'web357LoginAsUser', $data);
        // .wp-header-end stays outside the React root: WordPress moves admin notices (from any plugin)
        // to just after it, and must never insert them into React-owned DOM. The page's h1 is rendered
        // by the app's top bar.
        echo '<div class="wrap"><hr class="wp-header-end"><div id="web357-lau-root"></div></div>';
    }
}
