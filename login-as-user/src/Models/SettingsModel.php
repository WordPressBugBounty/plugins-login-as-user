<?php

declare(strict_types=1);

namespace Web357\WP\LoginAsUser\Models;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Reads and validates the plugin settings for the admin app.
 *
 * Everything is stored in the existing `login_as_user_options` option in the shape the switching engine already
 * reads ('yes'/'no' strings, ['yes'] for the cart checkbox, …). The admin app talks in plain booleans, numbers and
 * arrays; this class translates both ways. Unknown keys are discarded and invalid values throw
 * InvalidArgumentException naming the field, so a rejected save changes nothing.
 */
class SettingsModel
{
    public const OPTION = 'login_as_user_options';

    private const LOGIN_AS_TYPES = ['user_login', 'user_firstname', 'user_lastname', 'user_fullname', 'only_icon'];
    private const MESSAGE_POSITIONS = ['top', 'bottom', 'none'];
    private const BUTTON_SIZES = ['', 'small', 'large'];

    /** Free text that must not be flattened by the generic sanitizer (it keeps newlines). */
    public const RAW_KEYS = [];

    /**
     * Plugin integrations with their own button options (Pro): id => label and position support.
     *
     * @return array<string, array{label: string, supportsPosition: bool}>
     */
    public static function integrations(): array
    {
        $integrations = [];
        
        return $integrations;
    }

    /**
     * Settings as the admin app sees them.
     */
    public function getSettings(): array
    {
        $saved = $this->saved();
        $settings = [
            'redirect_to' => (string) ($saved['redirect_to'] ?? ''),
            'logout_redirect_url' => (string) ($saved['logout_redirect_url'] ?? ''),
            'login_as_type' => in_array($saved['login_as_type'] ?? '', self::LOGIN_AS_TYPES, true) ? $saved['login_as_type'] : 'user_login',
            'login_as_type_characters_limit' => max(0, min(15, (int) ($saved['login_as_type_characters_limit'] ?? 0))),
            'message_display_position' => in_array($saved['message_display_position'] ?? '', self::MESSAGE_POSITIONS, true) ? $saved['message_display_position'] : 'bottom',
            'show_admin_link_in_topbar' => ($saved['show_admin_link_in_topbar'] ?? 'yes') !== 'no',
            'enable_ping_animation' => ($saved['enable_ping_animation'] ?? 'no') === 'yes',
            'preserve_wc_cart_on_switch' => in_array('yes', (array) ($saved['preserve_wc_cart_on_switch'] ?? []), true),
        ];
        
        return $settings;
    }

    /**
     * Validates the whole payload first; only then writes it and applies side effects.
     */
    public function saveSettings(array $input): array
    {
        $clean = $this->validate($input);
        update_option(self::OPTION, array_merge($this->saved(), $clean), true);
        
        return $this->getSettings();
    }

    /**
     * @return array<string, mixed> values in the stored (engine) shape
     */
    public function validate(array $input): array
    {
        $clean = [];
        $rules = $this->rules();
        foreach (array_intersect_key($input, $rules) as $key => $value) {
            $clean[$key] = $rules[$key]($value);
        }
        return $clean;
    }

    /**
     * @return array<string, callable(mixed): mixed>
     */
    private function rules(): array
    {
        $rules = [
            'redirect_to' => fn($v) => self::redirectTo($v),
            'logout_redirect_url' => fn($v) => self::logoutRedirectUrl($v),
            'login_as_type' => fn($v) => self::oneOf($v, __('Login button label', 'login-as-user'), self::LOGIN_AS_TYPES),
            'login_as_type_characters_limit' => fn($v) => (string) self::int($v, __('Characters limit', 'login-as-user'), 0, 15),
            'message_display_position' => fn($v) => self::oneOf($v, __('Message position', 'login-as-user'), self::MESSAGE_POSITIONS),
            'show_admin_link_in_topbar' => fn($v) => self::bool($v) ? 'yes' : 'no',
            'enable_ping_animation' => fn($v) => self::bool($v) ? 'yes' : 'no',
            'preserve_wc_cart_on_switch' => fn($v) => self::bool($v) ? ['yes'] : [],
        ];
        
        return $rules;
    }

    

    private function saved(): array
    {
        $saved = get_option(self::OPTION, []);
        return is_array($saved) ? $saved : [];
    }

    private static function redirectTo($value): string
    {
        $value = trim(wp_strip_all_tags((string) $value));
        if ($value === '') {
            return '';
        }
        if ($value[0] === '/' || $value[0] === '\\') {
            throw new InvalidArgumentException(esc_html__('After switching to a user, go to: please remove the slash in front of the path.', 'login-as-user'));
        }
        if (strpos($value, 'wp-admin') === 0) {
            throw new InvalidArgumentException(esc_html__('After switching to a user, go to: you cannot redirect the user to the admin page.', 'login-as-user'));
        }
        if (strpos($value, 'wp-login') === 0) {
            throw new InvalidArgumentException(esc_html__('After switching to a user, go to: you cannot redirect the user to the login page.', 'login-as-user'));
        }
        if (!wp_http_validate_url(esc_url_raw(home_url('/') . $value))) {
            throw new InvalidArgumentException(esc_html__('After switching to a user, go to: the URL is not valid.', 'login-as-user'));
        }
        return $value;
    }

    private static function logoutRedirectUrl($value): string
    {
        $value = ltrim(trim(wp_strip_all_tags((string) $value)), '/');
        if ($value !== '' && !wp_http_validate_url(esc_url_raw(home_url('/') . $value))) {
            throw new InvalidArgumentException(esc_html__('After switching back, go to: the URL is not valid.', 'login-as-user'));
        }
        return $value;
    }

    /**
     * @param mixed $value
     */
    public static function int($value, string $label, int $min, int $max): int
    {
        if (!is_numeric($value) || (int) $value != $value || $value < $min || $value > $max) {
            /* translators: 1: field name, 2: minimum, 3: maximum */
            throw new InvalidArgumentException(esc_html(sprintf(__('%1$s must be a whole number between %2$d and %3$d.', 'login-as-user'), $label, $min, $max)));
        }
        return (int) $value;
    }

    /**
     * @param mixed $value
     * @param string[] $allowed
     */
    public static function oneOf($value, string $label, array $allowed): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            /* translators: %s: field name */
            throw new InvalidArgumentException(esc_html(sprintf(__('%s has an invalid value.', 'login-as-user'), $label)));
        }
        return $value;
    }

    /**
     * @param mixed $value
     */
    public static function bool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    
}
