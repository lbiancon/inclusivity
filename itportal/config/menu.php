<?php

declare(strict_types=1);

/**
 * Menu definitions migrated from legacy menu.php MENU_SITE().
 * Roles match the old portal (SU, QA, QI, D, …).
 */
return [
    [
        'id' => 'tracking',
        'label' => 'Tracking',
        'icon' => 'search',
        'public' => true,
        'children' => [
            [
                'label' => 'Serial Track',
                'href' => '/tracking/serial.php',
                'roles' => null,
            ],
            [
                'label' => 'Pallet / Cont Track',
                'href' => '/tracking/pallet.php',
                'roles' => ['SU', 'D', 'CA', 'QA', 'QI'],
                'superuser_for' => ['QA', 'QI'],
            ],
            [
                'label' => 'Filling Track',
                'href' => '/engineering/filling.php',
                'roles' => ['SU', 'D'],
                'coming_soon' => true,
            ],
            [
                'label' => 'Final Station Track',
                'href' => '/engineering/final-station.php',
                'roles' => ['SU', 'D'],
                'coming_soon' => true,
            ],
        ],
    ],
    [
        'id' => 'services',
        'label' => 'Services',
        'icon' => 'globe',
        'public' => true,
        'children' => [
            [
                'label' => 'Prenota Pasto',
                'href' => '/mensa/',
                'roles' => null,
                'coming_soon' => true,
            ],
        ],
    ],
    [
        'id' => 'contact',
        'label' => 'Contact',
        'icon' => 'book',
        'public' => true,
        'children' => [
            [
                'label' => 'JSS Contact Search',
                'href' => 'https://joyson.sharepoint.com/sites/JSS_EMEA/SitePages/Telephone-List.aspx',
                'external' => true,
                'roles' => null,
            ],
        ],
    ],
    [
        'id' => 'quality',
        'label' => 'Quality',
        'icon' => 'shield',
        'roles' => ['SU', 'D', 'QI', 'QA', 'QS', 'QAI'],
        'children' => [
            ['label' => 'Bracket Control', 'href' => '/quality/bracket.php', 'roles' => ['SU', 'QI', 'D', 'QAI'], 'coming_soon' => true],
            ['label' => 'Sblocco Tray', 'href' => '/quality/sblocco-tray.php', 'roles' => ['SU', 'QI', 'D', 'QAI'], 'coming_soon' => true],
            ['label' => 'LAT Test', 'href' => '/quality/lat-test.php', 'roles' => ['SU', 'QI', 'D', 'QAI'], 'coming_soon' => true],
            ['label' => 'SPT Test', 'href' => '/quality/spt-test.php', 'roles' => ['SU', 'QA', 'D', 'QAI'], 'coming_soon' => true],
            ['label' => 'Lab Test ACI', 'href' => '/quality/lab-test.php', 'roles' => ['SU', 'QA', 'D', 'QAI'], 'coming_soon' => true],
            ['label' => 'Checklist', 'href' => '/quality/checklist.php', 'roles' => ['SU', 'QS', 'D', 'QI', 'QA']],
            ['label' => 'Airbag', 'href' => '/airbag/', 'roles' => ['SU', 'QA', 'D'], 'coming_soon' => true],
            ['label' => 'Inflator', 'href' => '/inflator/', 'roles' => ['SU', 'QI', 'D'], 'coming_soon' => true],
        ],
    ],
    [
        'id' => 'production',
        'label' => 'Production',
        'icon' => 'factory',
        'roles' => ['SU', 'D', 'RI', 'RA', 'QS', 'DA', 'CI', 'CTS', 'AHR', 'UHR', 'QA', 'QI'],
        'children' => [
            ['label' => 'Inflator', 'href' => '/inflator/', 'roles' => ['SU', 'D', 'CI', 'QI', 'MQI', 'RI'], 'coming_soon' => true],
            ['label' => 'Airbag', 'href' => '/airbag/', 'roles' => ['SU', 'D', 'CA', 'QA', 'RA', 'DA', 'CTS'], 'coming_soon' => true],
            ['label' => 'MP IF', 'href' => '/tools/mp.php', 'roles' => ['SU', 'D'], 'coming_soon' => true],
            ['label' => 'MP AB', 'href' => '/tools/mp2.php', 'roles' => ['SU', 'D'], 'coming_soon' => true],
            ['label' => 'Efficiency', 'href' => '/tools/efficiency.php', 'roles' => ['SU', 'D'], 'coming_soon' => true],
            ['label' => 'Production Target', 'href' => '/tools/production.php', 'roles' => ['SU', 'D'], 'coming_soon' => true],
        ],
    ],
    [
        'id' => 'it',
        'label' => 'I.T.',
        'icon' => 'server',
        'roles' => ['SU', 'ITM', 'IT'],
        'children' => [
            ['label' => 'Utility', 'href' => '/it/', 'roles' => ['SU'], 'coming_soon' => true],
            ['label' => 'Web Dashboard', 'href' => '/dashboard/', 'roles' => ['SU'], 'coming_soon' => true],
            ['label' => 'Action Items', 'href' => '/it/items.php', 'roles' => ['SU', 'ITM', 'IT'], 'coming_soon' => true],
            ['label' => 'Downtime IT', 'href' => '/it/downtime.php', 'roles' => ['SU', 'ITM', 'IT'], 'coming_soon' => true],
        ],
    ],
    [
        'id' => 'maintenance',
        'label' => 'Maintenance',
        'icon' => 'wrench',
        'roles' => ['SU', 'D', 'MI', 'RMI', 'RI', 'QI', 'QA'],
        'children' => [
            ['label' => 'Import Ticket Desc', 'href' => '/maintenance/ticket.php', 'roles' => ['SU', 'D', 'RMI'], 'coming_soon' => true],
            ['label' => 'Report Day Downtime', 'href' => '/maintenance/downtime.php', 'roles' => null, 'coming_soon' => true],
        ],
    ],
    [
        'id' => 'qad',
        'label' => 'QAD',
        'icon' => 'database',
        'roles' => ['SU', 'QADU', 'QADM'],
        'children' => [
            ['label' => 'QAD BackFlush', 'href' => '/qad/backflush.php', 'roles' => null, 'coming_soon' => true],
            ['label' => 'QAD Shipping', 'href' => '/qad/shipping.php', 'roles' => null, 'coming_soon' => true],
        ],
    ],
    [
        'id' => 'logistic',
        'label' => 'Logistic',
        'icon' => 'boxes',
        'roles' => ['SU', 'LO', 'QADM'],
        'children' => [
            ['label' => 'Container Management', 'href' => '/logistic/containers.php', 'roles' => ['SU', 'D', 'QADM', 'LO'], 'coming_soon' => true],
            ['label' => 'Consumo Elio Filling', 'href' => '/logistic/elio.php', 'roles' => ['SU', 'D', 'LO'], 'coming_soon' => true],
            ['label' => 'Consumo Propellente ACI', 'href' => '/logistic/propellente.php', 'roles' => ['SU', 'D', 'LO'], 'coming_soon' => true],
        ],
    ],
    [
        'id' => 'hr',
        'label' => 'H.R.',
        'icon' => 'users',
        'roles' => ['SU', 'UHR', 'AHR'],
        'children' => [
            ['label' => 'Corsi Attuali', 'href' => '/hr/courses-online.php', 'roles' => null, 'coming_soon' => true],
            ['label' => 'Corsi Archivio', 'href' => '/hr/courses-archive.php', 'roles' => null, 'coming_soon' => true],
            ['label' => 'Elenco Personale', 'href' => '/hr/personal.php', 'roles' => null, 'coming_soon' => true],
            ['label' => 'Elenco Corsi', 'href' => '/hr/courses.php', 'roles' => null, 'coming_soon' => true],
        ],
    ],
    [
        'id' => 'engineering',
        'label' => 'Engineering',
        'icon' => 'cog',
        'roles' => ['SU', 'D', 'EI', 'EA', 'MI', 'RI', 'RA', 'RMI', 'QI', 'QA', 'CI'],
        'children' => [
            ['label' => 'Report Part Line', 'href' => '/engineering/report-part-line.php', 'roles' => ['SU', 'D', 'EI', 'EA', 'MI', 'RI', 'RA', 'RMI', 'QI'], 'coming_soon' => true],
            ['label' => 'Report Scrap Line', 'href' => '/engineering/report-scrap-line.php', 'roles' => ['SU', 'D', 'EI', 'EA', 'MI', 'RI', 'RA', 'RMI', 'QI'], 'coming_soon' => true],
            ['label' => 'Report Downtime', 'href' => '/engineering/report-downtime.php', 'roles' => null, 'coming_soon' => true],
            ['label' => 'Report OEE', 'href' => '/engineering/report-oee.php', 'roles' => ['SU', 'D'], 'coming_soon' => true],
            ['label' => 'Line Action Items', 'href' => '/engineering/items.php', 'roles' => ['SU', 'D', 'EI', 'EA', 'MI', 'RI', 'RA', 'RMI', 'QI'], 'coming_soon' => true],
        ],
    ],
    [
        'id' => 'accessi',
        'label' => 'Accessi',
        'icon' => 'eye',
        'roles' => ['SU', 'D', 'GUA'],
        'children' => [
            ['label' => 'Accessi', 'href' => '/accessi/', 'roles' => null, 'coming_soon' => true],
            ['label' => 'Nuovo Autista', 'href' => '/accessi/nuovo.php', 'roles' => null, 'coming_soon' => true],
            ['label' => 'Ricerca', 'href' => '/accessi/ricerca.php', 'roles' => null, 'coming_soon' => true],
        ],
    ],
];
