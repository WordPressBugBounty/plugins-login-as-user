<?php

declare(strict_types=1);

namespace Web357\WP\LoginAsUser\Api;

defined('ABSPATH') || exit;

use Web357\WP\LoginAsUser\Models\SettingsModel;
use Web357\WP\LoginAsUser\Services\UserSources;

class SettingsApi extends AdminApiAbstract
{
    protected string $namespace = 'settings';

    protected array $endpoints = [
        'get' => ['method' => 'GET', 'cap' => 'edit_users'],
        'save' => ['method' => 'POST'],
    ];

    /**
     * Users who can only switch accounts need the non-sensitive meta (roles, sources); only managers get the settings.
     */
    public function actionGet(): array
    {
        return [
            'settings' => current_user_can('manage_options') ? (new SettingsModel())->getSettings() : null,
            'meta' => self::meta(),
        ];
    }

    public function actionSave(): array
    {
        return [
            'settings' => (new SettingsModel())->saveSettings($this->body(SettingsModel::RAW_KEYS)),
            'meta' => self::meta(),
            'message' => __('Settings saved.', 'login-as-user'),
        ];
    }

    public static function meta(): array
    {
        $roles = [];
        foreach (wp_roles()->get_names() as $slug => $name) {
            $roles[] = ['value' => $slug, 'label' => translate_user_role($name)];
        }
        $managerRoles = [];
        foreach (wp_roles()->role_objects as $slug => $role) {
            if ($role->has_cap('edit_users')) {
                $managerRoles[] = ['value' => (string) $slug, 'label' => translate_user_role(wp_roles()->get_names()[$slug] ?? $slug)];
            }
        }
        $sources = [];
        foreach (UserSources::active() as $id => $label) {
            $sources[] = ['value' => $id, 'label' => $label];
        }
        $integrations = [];
        foreach (SettingsModel::integrations() as $id => $config) {
            $integrations[] = ['id' => $id, 'label' => $config['label'], 'supportsPosition' => $config['supportsPosition']];
        }
        return [
            'roles' => $roles,
            'managerRoles' => $managerRoles,
            'sources' => $sources,
            'integrations' => $integrations,
        ];
    }
}
