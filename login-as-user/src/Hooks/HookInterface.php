<?php

declare(strict_types=1);

namespace Web357\WP\LoginAsUser\Hooks;

defined('ABSPATH') || exit;

interface HookInterface
{
    public function register(): void;
}
