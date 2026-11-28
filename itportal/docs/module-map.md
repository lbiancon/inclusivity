# Da `function*.php` enormi → moduli leggeri

Vedi anche [`function-files.md`](function-files.md) per l’inventario completo dei 5 file.

## Problema legacy
`function.php` (~35k), `function2.php` (~22k), `functionDashboard.php`, `functionchecklist.php`, `functionTools.php` venivano `include` su quasi ogni pagina: tutto in memoria, difficile da testare, rischi di collisioni di nomi.

## Regola nuova
1. **Un dominio = una cartella** sotto `src/` (`Tracking/`, `Quality/`, `Engineering/`, …).
2. **Una responsabilità = una classe** (max poche centinaia di righe).
3. **Niente include globali**: si usa `portal('tracking.serial')`.
4. Il file `config/services.php` registra i servizi; il container li crea **solo al primo uso**.

## Come richiamare da ovunque (in sicurezza)
```php
$app = require __DIR__ . '/../src/bootstrap.php';

// Opzione A — tipizzata
$result = portal('tracking.serial')->search($_POST['part_sn'] ?? '');

// Opzione B — auth già pronta
portal()->auth()->requireLogin();
```

Non esiste più `include 'function.php'`.

## Mappa legacy → moduli (in corso)

| Legacy (file / funzione) | Nuovo servizio | Stato |
|--------------------------|----------------|-------|
| `LOGIN_PORTAL` / `login.php` | `App::auth()` | fatto |
| `validate_ldap` | `Auth\LdapAuthenticator` | fatto |
| `MENU_SITE` | `Menu\MenuBuilder` + `config/menu.php` | fatto |
| `LOT_SERIAL` / `part_sn` in `tables.php` | `tracking.serial` → `OracleSerialRepository` + `Sql/LegacySql` | fatto (mock + SQL Oracle pronta) |
| `PARENT_PART_SN` | stesso flusso serial (genealogy) | fatto (SQL estratta) |
| `SELECT_PALLET_CONT_AIR` / `pallet_sn` | `tracking.pallet` → `OraclePalletRepository` | fatto (mock + SQL Oracle pronta) |
| `SELECT_PACKOUT` / `CHILD_SN` | SQL in `LegacySql` (PACKOUT_*, CHILD_*) | SQL estratta, wiring UI next |
| `SELECT_SINGLE_PROCESS` | `tracking.process` + API + panel in Serial Track | fatto (mock + SQL Oracle) |
| `get_check_list_config` / `get_check_config` | `quality.checklist` + `/quality/checklist.php` | fatto |
| `insert_login_check` / `logout` / `save_check` | `quality.checklist` login/save APIs | fatto (mock + SQL) |
| `SELECT_REPORT_LINE_SCRAP` (core SUM) | `engineering.scrap` | fatto |
| `SELECT_DASHBOARD_LINE` (`functionDashboard.php`) | `dashboard.line` | fatto (SQL + servizio mock) |
| `GET_DESCRIPTION_SCRAP` (`function2.php`) | `LegacySql::SCRAP_DESCRIPTIONS` | SQL estratta |
| `SELECT_TABLE_MP` / EFF (`functionTools.php`) | `production.mp` | fatto (SQL + servizio mock) |
| `functionchecklist.php` (`*_check`) | confrontare vs `quality.checklist` | next se diverge |
| `geTsmb` / `getPhoto` | `tracking.photos` | next |
| OEE / report line HTML completo | `engineering.*` | next |
| Efficiency / WTARGET UI | `production.*` | next |
| `MSG_ERROR` / date helpers | `Support\Flash`, `Support\Dates` | next |

## Convenzione nomi file
```
src/Tracking/SerialSearchService.php   # cerca seriale
src/Tracking/PalletSearchService.php   # cerca pallet
src/Tracking/TraceNormalizer.php       # sanitizza input
src/Quality/ChecklistService.php       # (futuro)
config/services.php                    # registro unico
```

Quando porti una funzione da `function.php`, **non** copiarla intera: estrai solo quella responsabilità in una classe del dominio, registrala in `services.php`, aggiorna questa tabella.

## Regola Oracle (importante)
1. La query resta **quella di `function.php`** (stesse tabelle/join/filtri).
2. Si cambia solo la concatenazione `'…$var…'` in bind `:param` (`OracleQuery`).
3. Senza rete Oracle → `AUTH_DRIVER=mock` (UI).
4. In stabilimento → `AUTH_DRIVER=oracle` + `ORACLE_*` in `.env` (mai in git).
5. SQL catalogate in `src/Tracking/Sql/LegacySql.php` — niente dati aziendali, solo testo SQL.
