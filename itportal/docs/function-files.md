# Inventario file `function*` legacy

Tutti già in upload (nessun altro zip richiesto per continuare l’estrazione SQL).

| File legacy | Righe ~ | Funzioni ~ | Dominio nuovo |
|-------------|---------|------------|---------------|
| `function.php` | 35 803 | 247 | Auth, Tracking, Quality (parte), Engineering reports, Logistic, HR, … |
| `function2.php` | 22 271 | 69 | Dashboard helpers, scrap descriptions, targets, shift/login line |
| `functionchecklist.php` | 5 150 | 40 | Variante checklist (`*_check`) — spesso mirror di pezzi in `function.php` |
| `functionDashboard.php` | 5 019 | 28 | `SELECT_DASHBOARD_LINE`, operator/work minutes, dashboard fields |
| `functionTools.php` | 1 684 | 16 | MP, Efficiency, Production/Web target, Eng action items |

**Totale ~70k+ righe / ~400 funzioni** — non si includono mai interi: si estrae SQL → `src/<Domain>/Sql/` + servizio `portal('…')`.

## Priorità estrazione

1. ~~Tracking / auth / checklist core~~ (fatto da `function.php`)
2. ~~Scrap aggregate DASHBOARD~~ (fatto)
3. **Dashboard live** ← `functionDashboard.php` + pezzi `function2.php`
4. **Production tools** ← `functionTools.php` (MP / Efficiency / WTARGET)
5. **Scrap labels** ← `GET_DESCRIPTION_SCRAP*` in `function2.php`
6. Checklist variante `*_check` solo se diversa dalla già portata

## Regola anti-duplicato
Se la stessa query esiste in `function.php` e `function2.php` / `functionDashboard.php`, si tiene **una sola** in `LegacySql` e si documenta la fonte primaria.
