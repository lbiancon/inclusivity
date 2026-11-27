<?php

declare(strict_types=1);

/**
 * Lazy service map — one id → one small class.
 * Pages call portal('tracking.serial') instead of include 'function.php'.
 *
 * Legacy mapping (high level):
 *   LOT_SERIAL / PARENT_PART_SN / SELECT_SINGLE_PROCESS  → tracking.*
 *   checklist / get_check_*                             → quality.checklist (next)
 *   DASHBOARD_* / report line                           → dashboard.* / engineering.*
 *   LOGIN_PORTAL                                        → auth (already via App::auth())
 */

use ItPortal\Support\App;
use ItPortal\Tracking\PalletSearchService;
use ItPortal\Tracking\SerialSearchService;
use ItPortal\Tracking\TraceNormalizer;

return [
    'tracking.normalizer' => static fn (App $app): TraceNormalizer => new TraceNormalizer(),

    'tracking.serial' => static function (App $app): SerialSearchService {
        return new SerialSearchService($app->make('tracking.normalizer'));
    },

    'tracking.pallet' => static function (App $app): PalletSearchService {
        return new PalletSearchService($app->make('tracking.normalizer'));
    },
];
