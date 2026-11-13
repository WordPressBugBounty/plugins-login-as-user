<?php

declare(strict_types=1);

namespace Web357\WP\LoginAsUser\Api;

defined('ABSPATH') || exit;

use InvalidArgumentException;
use Throwable;
use Web357\WP\LoginAsUser\Hooks\HookInterface;

/**
 * admin-ajax endpoints for the admin app: `web357_lau_{namespace}_{endpoint}`.
 * Every request needs the `web357_lau_admin` nonce (X-WP-Nonce header) and the endpoint's
 * capability (default manage_options). Responses: {success, data, message}; 403 for auth
 * failures, 400 for invalid input. An action returning null has already sent its own response.
 */
abstract class AdminApiAbstract implements HookInterface
{
    public const ACTION_PREFIX = 'web357_lau_';
    public const NONCE_ACTION = 'web357_lau_admin';

    protected string $namespace = '';

    /** @var array<string, array{method: string, cap?: string}> */
    protected array $endpoints = [];

    public function register(): void
    {
        foreach (array_keys($this->endpoints) as $endpoint) {
            add_action('wp_ajax_' . self::ACTION_PREFIX . $this->namespace . '_' . $endpoint, [$this, 'handleRequest']);
        }
    }

    public function handleRequest(): void
    {
        $action = substr(current_action(), strlen('wp_ajax_' . self::ACTION_PREFIX . $this->namespace . '_'));
        $nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE'])) : '';
        $cap = $this->endpoints[$action]['cap'] ?? 'manage_options';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION) || !current_user_can($cap)) {
            wp_send_json(['success' => false, 'message' => __('You are not allowed to do this.', 'login-as-user')], 403);
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        if (($this->endpoints[$action]['method'] ?? '') !== $method) {
            wp_send_json(['success' => false, 'message' => __('Invalid request.', 'login-as-user')], 400);
        }

        try {
            $data = $this->{'action' . ucfirst($action)}();
        } catch (InvalidArgumentException $e) {
            wp_send_json(['success' => false, 'message' => $e->getMessage()], 400);
            return;
        } catch (Throwable $e) {
            // Never send internals (SQL, paths) to the browser.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[login-as-user] ' . $action . ': ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated behind WP_DEBUG.
            }
            wp_send_json(['success' => false, 'message' => __('Something went wrong. Please try again.', 'login-as-user')], 500);
            return;
        }
        if ($data === null) {
            wp_die('', '', ['response' => 200]);
        }
        // Outside the try: wp_send_json() dies, and its die-exception (tests, some hosts) must not be caught.
        $message = $data['message'] ?? '';
        unset($data['message']);
        wp_send_json(['success' => true, 'data' => $data, 'message' => $message]);
    }

    /**
     * Request body: JSON (the admin app), or form-encoded POST fields. Every scalar is passed
     * through sanitize_text_field() except the keys in $rawKeys (free text such as CSS), which
     * keep their characters and must be sanitized by the caller.
     *
     * @param string[] $rawKeys
     */
    protected function body(array $rawKeys = []): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data)) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handleRequest().
            $data = $_POST ? wp_unslash(array_diff_key($_POST, ['action' => true])) : null;
        }
        if (!is_array($data)) {
            throw new InvalidArgumentException(esc_html__('Invalid request body.', 'login-as-user'));
        }
        $clean = map_deep($data, 'sanitize_text_field');
        foreach ($rawKeys as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $clean[$key] = $data[$key];
            }
        }
        return $clean;
    }

    /**
     * Sanitized query-string value.
     */
    protected function query(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified in handleRequest().
        return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : $default;
    }
}
