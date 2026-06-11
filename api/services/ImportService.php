<?php
declare(strict_types=1);

class ImportService
{
    /** @return string[] */
    public static function expectedColumns(): array
    {
        return ['nom', 'prix_achat', 'prix_vente', 'stock_actuel', 'stock_min', 'categorie', 'code_barre'];
    }

    public static function medicamentsTemplateCsv(): string
    {
        $cols = self::expectedColumns();
        $header = implode(';', $cols);
        $example = 'Paracétamol 500mg;150;250;100;10;Antalgiques;3760123456789';
        return "\xEF\xBB\xBF" . $header . "\r\n" . $example . "\r\n";
    }

    /**
     * @return array{ok: int, errors: list<array{line: int, message: string}>, rows: list<array>}
     */
    public static function importMedicaments(string $filePath, string $extension, int $userId): array
    {
        $rows = match (strtolower($extension)) {
            'xlsx', 'xls' => self::readSpreadsheet($filePath),
            'csv', 'txt' => self::readCsv($filePath),
            default => throw new RuntimeException('Format non supporté. Utilisez .xlsx, .xls ou .csv'),
        };

        if (count($rows) < 2) {
            throw new RuntimeException('Fichier vide ou sans données');
        }

        $header = array_map(fn($h) => self::normalizeHeader((string) $h), $rows[0]);
        $map = [];
        foreach (self::expectedColumns() as $col) {
            $idx = array_search($col, $header, true);
            if ($idx !== false) {
                $map[$col] = $idx;
            }
        }
        if (!isset($map['nom'], $map['prix_achat'], $map['prix_vente'])) {
            throw new RuntimeException('Colonnes obligatoires manquantes : nom, prix_achat, prix_vente');
        }

        $pdo = db();
        $cats = $pdo->query('SELECT id, nom FROM categorie')->fetchAll();
        $catByName = [];
        foreach ($cats as $c) {
            $catByName[mb_strtolower(trim($c['nom']))] = (int) $c['id'];
        }
        $defaultCat = $cats[0]['id'] ?? null;

        $ok = 0;
        $errors = [];
        $pdo->beginTransaction();

        try {
            for ($i = 1, $n = count($rows); $i < $n; $i++) {
                $row = $rows[$i];
                if (self::rowEmpty($row)) {
                    continue;
                }
                $lineNum = $i + 1;
                try {
                    $nom = trim((string) ($row[$map['nom']] ?? ''));
                    if ($nom === '') {
                        throw new RuntimeException('Nom vide');
                    }
                    $prixAchat = (float) str_replace(',', '.', (string) ($row[$map['prix_achat']] ?? 0));
                    $prixVente = (float) str_replace(',', '.', (string) ($row[$map['prix_vente']] ?? 0));
                    if ($prixAchat < 0 || $prixVente < 0) {
                        throw new RuntimeException('Prix invalide');
                    }
                    $stock = isset($map['stock_actuel']) ? (int) ($row[$map['stock_actuel']] ?? 0) : 0;
                    $stockMin = isset($map['stock_min']) ? (int) ($row[$map['stock_min']] ?? 5) : 5;
                    $codeBarre = isset($map['code_barre']) ? trim((string) ($row[$map['code_barre']] ?? '')) : '';
                    $catName = isset($map['categorie']) ? mb_strtolower(trim((string) ($row[$map['categorie']] ?? ''))) : '';
                    $idCat = ($catName && isset($catByName[$catName])) ? $catByName[$catName] : $defaultCat;

                    $existing = $pdo->prepare('SELECT id FROM medicament WHERE LOWER(nom) = LOWER(?) AND actif = 1 LIMIT 1');
                    $existing->execute([$nom]);
                    $medId = $existing->fetchColumn();

                    if ($medId) {
                        $pdo->prepare(
                            'UPDATE medicament SET prix_achat=?, prix_vente=?, stock_actuel=?, stock_min=?, id_categorie=COALESCE(?, id_categorie), code_barre=COALESCE(NULLIF(?, ""), code_barre) WHERE id=?'
                        )->execute([$prixAchat, $prixVente, $stock, $stockMin, $idCat, $codeBarre, $medId]);
                    } else {
                        $pdo->prepare(
                            'INSERT INTO medicament (nom, prix_achat, prix_vente, stock_actuel, stock_min, id_categorie, code_barre, actif) VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
                        )->execute([$nom, $prixAchat, $prixVente, $stock, $stockMin, $idCat, $codeBarre ?: null]);
                    }
                    $ok++;
                } catch (Throwable $e) {
                    $errors[] = ['line' => $lineNum, 'message' => $e->getMessage()];
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $pdo->prepare(
            'INSERT INTO import_log (id_utilisateur, type_import, fichier, lignes_ok, lignes_erreur, details) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $userId,
            'medicaments',
            basename($filePath),
            $ok,
            count($errors),
            json_encode(['errors' => $errors], JSON_UNESCAPED_UNICODE),
        ]);

        audit($userId, 'import', 'medicaments', 0);

        return ['ok' => $ok, 'errors' => $errors];
    }

    /** @return list<list<string>> */
    private static function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException('Impossible de lire le fichier');
        }
        $first = fgets($handle);
        rewind($handle);
        $delim = str_contains((string) $first, ';') ? ';' : ',';
        while (($data = fgetcsv($handle, 0, $delim)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);
        return $rows;
    }

    /** @return list<list<mixed>> */
    private static function readSpreadsheet(string $path): array
    {
        if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
            throw new RuntimeException('Import Excel (.xlsx) nécessite Composer. Utilisez un fichier CSV ou exécutez : cd api && composer install');
        }
        require_once __DIR__ . '/../vendor/autoload.php';
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        return $spreadsheet->getActiveSheet()->toArray();
    }

    private static function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));
        $h = str_replace(['é', 'è', 'ê'], 'e', $h);
        return preg_replace('/[^a-z0-9_]/', '_', $h) ?? $h;
    }

    /** @param list<mixed> $row */
    private static function rowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }
}
