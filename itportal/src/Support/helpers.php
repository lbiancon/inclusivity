<?php

declare(strict_types=1);

use ItPortal\Support\App;

if (!function_exists('portal')) {
    /**
     * Global safe accessor used from any page/module.
     * Example: portal('tracking.serial')->search('ABC')
     */
    function portal(?string $service = null): mixed
    {
        $app = App::getInstance();
        if ($service === null) {
            return $app;
        }
        return $app->make($service);
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
