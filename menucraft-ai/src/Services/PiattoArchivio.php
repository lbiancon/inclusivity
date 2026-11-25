<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use MenuCraft\Enums\PortataType;
use MenuCraft\Support\Money;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

/**
 * Catalogo piatti per azienda: riusa lo stesso piatto per nome+portata,
 * così il codice non è obbligatorio e i file settimanali non moltiplicano le voci.
 */
final class PiattoArchivio
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function normalizzaNome(string $nome): string
    {
        $nome = preg_replace('/\s+/u', ' ', trim($nome)) ?? '';

        return mb_strtolower($nome);
    }

    public static function codiceEffimero(string $codice): bool
    {
        return $codice === '' || preg_match('/^[A-Z][A-Z0-9]{0,2}-\d{8}$/', $codice) === 1;
    }

    /**
     * Trova o crea il piatto. Il codice suggerito è opzionale e, se è tipo A-20261116,
     * non crea un nuovo catalogo: si riusa il piatto con lo stesso nome e portata.
     */
    public function trovaOCrea(
        int $aziendaId,
        string $nome,
        PortataType $categoria,
        string $prezzo,
        string $codiceSuggerito = '',
        bool $aggiornaPrezzo = true,
    ): int {
        $nome = trim(preg_replace('/\s+/u', ' ', $nome) ?? '');
        $codiceSuggerito = strtoupper(trim($codiceSuggerito));
        $prezzo = $prezzo === '' ? '' : Money::normalizza($prezzo);
        $chiaveNome = self::normalizzaNome($nome);

        if ($codiceSuggerito !== '' && !self::codiceEffimero($codiceSuggerito)) {
            $perCodice = $this->pdo->prepare(
                'SELECT id, nome, categoria, prezzo FROM piatti
                 WHERE azienda_id = :azienda AND codice = :codice'
            );
            $perCodice->execute(['azienda' => $aziendaId, 'codice' => $codiceSuggerito]);
            $riga = $perCodice->fetch();
            if ($riga !== false) {
                $this->aggiorna((int) $riga['id'], $aziendaId, $nome, $categoria, $prezzo, $aggiornaPrezzo);

                return (int) $riga['id'];
            }
        }

        $perNome = $this->pdo->prepare(
            'SELECT id, codice, prezzo FROM piatti
             WHERE azienda_id = :azienda
               AND categoria = :categoria
               AND LOWER(TRIM(nome)) = :nome
             ORDER BY id ASC'
        );
        $perNome->execute([
            'azienda' => $aziendaId,
            'categoria' => $categoria->value,
            'nome' => $chiaveNome,
        ]);
        $candidati = $perNome->fetchAll();
        if ($candidati !== []) {
            $scelto = $candidati[0];
            foreach ($candidati as $candidato) {
                if (!self::codiceEffimero((string) $candidato['codice'])) {
                    $scelto = $candidato;
                    break;
                }
            }
            $this->aggiorna((int) $scelto['id'], $aziendaId, $nome, $categoria, $prezzo, $aggiornaPrezzo);

            return (int) $scelto['id'];
        }

        $codice = $codiceSuggerito;
        if (self::codiceEffimero($codice)) {
            $codice = $this->codiceStabile($aziendaId, $categoria, $nome);
        }

        if ($prezzo === '') {
            $prezzo = '0.00';
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO piatti (azienda_id, codice, nome, categoria, prezzo)
             VALUES (:azienda, :codice, :nome, :categoria, :prezzo)'
        );
        try {
            $insert->execute([
                'azienda' => $aziendaId,
                'codice' => $codice,
                'nome' => $nome,
                'categoria' => $categoria->value,
                'prezzo' => $prezzo,
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            // Condizione di gara sul codice: riprova cercando di nuovo.
            $perCodice = $this->pdo->prepare(
                'SELECT id FROM piatti WHERE azienda_id = :azienda AND codice = :codice'
            );
            $perCodice->execute(['azienda' => $aziendaId, 'codice' => $codice]);
            $id = $perCodice->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
            $codice = $this->codiceStabile($aziendaId, $categoria, $nome . '-' . bin2hex(random_bytes(2)));
            $insert->execute([
                'azienda' => $aziendaId,
                'codice' => $codice,
                'nome' => $nome,
                'categoria' => $categoria->value,
                'prezzo' => $prezzo,
            ]);
        }

        return (int) $this->pdo->lastInsertId();
    }

    private function aggiorna(
        int $piattoId,
        int $aziendaId,
        string $nome,
        PortataType $categoria,
        string $prezzo,
        bool $aggiornaPrezzo,
    ): void {
        if ($aggiornaPrezzo && $prezzo !== '') {
            $stmt = $this->pdo->prepare(
                'UPDATE piatti
                 SET nome = :nome, categoria = :categoria, prezzo = :prezzo
                 WHERE id = :id AND azienda_id = :azienda'
            );
            $stmt->execute([
                'nome' => $nome,
                'categoria' => $categoria->value,
                'prezzo' => $prezzo,
                'id' => $piattoId,
                'azienda' => $aziendaId,
            ]);

            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE piatti
             SET nome = :nome, categoria = :categoria
             WHERE id = :id AND azienda_id = :azienda'
        );
        $stmt->execute([
            'nome' => $nome,
            'categoria' => $categoria->value,
            'id' => $piattoId,
            'azienda' => $aziendaId,
        ]);
    }

    /**
     * Unisce i piatti duplicati (stesso nome e portata) tenendo il codice stabile
     * e ripuntando le voci di menu / righe ordine al piatto conservato.
     */
    public function unificaDuplicati(int $aziendaId): int
    {
        $gruppi = $this->pdo->prepare(
            'SELECT LOWER(TRIM(nome)) AS chiave, categoria,
                    GROUP_CONCAT(id ORDER BY id SEPARATOR ",") AS ids,
                    GROUP_CONCAT(codice ORDER BY id SEPARATOR "\n") AS codici
             FROM piatti
             WHERE azienda_id = :azienda
             GROUP BY LOWER(TRIM(nome)), categoria
             HAVING COUNT(*) > 1'
        );
        $gruppi->execute(['azienda' => $aziendaId]);
        $unificati = 0;

        foreach ($gruppi->fetchAll() as $gruppo) {
            $ids = array_map('intval', explode(',', (string) $gruppo['ids']));
            $codici = explode("\n", (string) $gruppo['codici']);
            $keeper = $ids[0];
            foreach ($ids as $i => $id) {
                if (!self::codiceEffimero((string) ($codici[$i] ?? ''))) {
                    $keeper = $id;
                    break;
                }
            }
            foreach ($ids as $id) {
                if ($id === $keeper) {
                    continue;
                }
                $this->ripunta($aziendaId, $id, $keeper);
                $cancella = $this->pdo->prepare(
                    'DELETE FROM piatti WHERE id = :id AND azienda_id = :azienda'
                );
                $cancella->execute(['id' => $id, 'azienda' => $aziendaId]);
                $unificati++;
            }
        }

        return $unificati;
    }

    private function ripunta(int $aziendaId, int $da, int $a): void
    {
        // Stesso giorno: se il keeper c'è già, togli il duplicato.
        $conflitti = $this->pdo->prepare(
            'SELECT v1.id
             FROM menu_voci v1
             INNER JOIN menu_voci v2
               ON v2.azienda_id = v1.azienda_id
              AND v2.data_pasto = v1.data_pasto
              AND v2.piatto_id = :a
             WHERE v1.azienda_id = :azienda AND v1.piatto_id = :da'
        );
        $conflitti->execute(['azienda' => $aziendaId, 'da' => $da, 'a' => $a]);
        $elimina = $this->pdo->prepare('DELETE FROM menu_voci WHERE id = :id');
        foreach ($conflitti->fetchAll() as $riga) {
            $elimina->execute(['id' => (int) $riga['id']]);
        }

        $voci = $this->pdo->prepare(
            'UPDATE menu_voci SET piatto_id = :a
             WHERE azienda_id = :azienda AND piatto_id = :da'
        );
        $voci->execute(['azienda' => $aziendaId, 'da' => $da, 'a' => $a]);

        $righe = $this->pdo->prepare(
            'UPDATE ordine_righe SET piatto_id = :a WHERE piatto_id = :da'
        );
        $righe->execute(['da' => $da, 'a' => $a]);
    }

    public function codiceStabile(int $aziendaId, PortataType $categoria, string $nome): string
    {
        $prefisso = match ($categoria) {
            PortataType::Primo => 'PR',
            PortataType::Secondo => 'SE',
            PortataType::Contorno => 'CO',
            PortataType::Dessert => 'DE',
            PortataType::Yogurt => 'YO',
            PortataType::Frutta => 'FR',
            PortataType::Bevanda => 'BE',
        };
        $slug = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', $nome) ?? '');
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'PIATTO';
        }
        $base = $prefisso . '-' . $slug;
        if (strlen($base) > 28) {
            $base = substr($base, 0, 28);
            $base = rtrim($base, '-');
        }

        $cerca = $this->pdo->prepare(
            'SELECT id FROM piatti WHERE azienda_id = :azienda AND codice = :codice'
        );
        $codice = $base;
        $n = 2;
        while (true) {
            $cerca->execute(['azienda' => $aziendaId, 'codice' => $codice]);
            if ($cerca->fetchColumn() === false) {
                return $codice;
            }
            $suffisso = '-' . $n;
            $codice = substr($base, 0, max(1, 32 - strlen($suffisso))) . $suffisso;
            $n++;
            if ($n > 99) {
                return $prefisso . '-' . strtoupper(bin2hex(random_bytes(4)));
            }
        }
    }
}
