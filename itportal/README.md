# JSS IT Portal (modern rewrite)

PHP **8.3** rewrite of the Colleferro `ITPortal_new` intranet.

## What works in this milestone

- Modern UI shell (home, login, menu RBAC)
- Auth compatible with legacy flows:
  - badge / magnetic card (`pass`)
  - LDAP username + password (`user` + `pass`)
  - logout + 15‑minute idle timeout
- Tracking UI: **Serial Track** + **Pallet Track**
- Mock mode (`AUTH_DRIVER=mock`) so the UI is demoable without Oracle/LDAP

## Run locally

```bash
cd itportal
composer dump-autoload
cp config/.env.example config/.env   # if needed
php -S 127.0.0.1:8080 -t public
```

Open http://127.0.0.1:8080

### Demo badges (mock)

| Badge   | Role |
|---------|------|
| `demo`  | SU   |
| `12345` | SU   |
| `71875` | QA   |
| `guard` | GUA  |

LDAP mock: user `ldap.user` + any password.

## Production wiring

Set in `config/.env`:

```
AUTH_DRIVER=oracle
ORACLE_USER=...
ORACLE_PASSWORD=...
ORACLE_CONNECTION=10.101.25.10/CFP2
LDAP_HOST=ldap://...
```

Requires PHP extensions: `oci8`, `ldap` (and later `mysqli`/`pdo_mysql` for portal DBs).

## Migration map (from legacy)

| Legacy | New |
|--------|-----|
| `login.php` / `LOGIN_PORTAL` | `Auth\AuthService::loginByBadge` |
| `ldap.php` / `LOGIN_PORTAL_LDAP` | `Auth\LdapAuthenticator` + `loginByLdap` |
| `logout.php` | `public/logout.php` |
| `menu.php` `MENU_SITE` | `config/menu.php` + `Menu\MenuBuilder` |
| `search/serial.php` + `tables.php` | `tracking/serial.php` + `api/tracking/serial.php` |
| `loginCard/MySwipe.php` | `assets/js/cardswipe.js` |

Modules marked **soon** in the menu are inventoried and gated by the same roles; pages will be ported next (Quality, Engineering, …).
