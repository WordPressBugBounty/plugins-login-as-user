<?php

declare(strict_types=1);

namespace Web357\WP\LoginAsUser\Services;

defined('ABSPATH') || exit;

final class DateFormat
{
    /**
     * A UTC MySQL datetime shown in the site time zone and the site's date (and time) format.
     */
    public static function utcToSite(string $utc, bool $withTime): string
    {
        $timestamp = strtotime($utc . ' UTC');
        if ($timestamp === false) {
            return '';
        }
        $format = get_option('date_format') . ($withTime ? ' ' . get_option('time_format') : '');
        return (string) wp_date($format, $timestamp);
    }
}
