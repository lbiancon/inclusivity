# Da `function.php` enormi → moduli leggeri

## Problema legacy
`function.php` (~35k righe), `function2.php`, `functionDashboard.php`, `functionchecklist.php`, `functionTools.php` venivano `include` su quasi ogni pagina: tutto in memoria, difficile da testare, rischi di collisioni di nomi.

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
| `LOT_SERIAL` / `part_sn` in `tables.php` | `tracking.serial` | fatto (mock) |
| `pallet_sn` in `tables.php` | `tracking.pallet` | fatto (mock) |
| `PARENT_PART_SN` / `CHILD_SN` | `tracking.genealogy` | next |
| `SELECT_SINGLE_PROCESS` | `tracking.process` | next |
| `geTsmb` / `getPhoto` | `tracking.photos` | next |
| `get_check_*` / checklist | `quality.checklist` | next |
| `DASHBOARD_*` | `dashboard.*` | next |
| report line / scrap / OEE | `engineering.*` | next |
| MP / efficiency | `production.*` | next |
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
