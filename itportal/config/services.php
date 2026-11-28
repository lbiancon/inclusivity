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

use ItPortal\Dashboard\DashboardLineService;
use ItPortal\Engineering\ScrapReportService;
use ItPortal\Production\ManpowerService;
use ItPortal\Quality\ChecklistService;
use ItPortal\Support\App;
use ItPortal\Tracking\PalletSearchService;
use ItPortal\Tracking\ProcessSearchService;
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

    'tracking.process' => static function (App $app): ProcessSearchService {
        return new ProcessSearchService($app->make('tracking.normalizer'));
    },

    'quality.checklist' => static fn (App $app): ChecklistService => new ChecklistService(),

    'engineering.scrap' => static fn (App $app): ScrapReportService => new ScrapReportService(),

    'dashboard.line' => static fn (App $app): DashboardLineService => new DashboardLineService(),

    'production.mp' => static fn (App $app): ManpowerService => new ManpowerService(),
];
