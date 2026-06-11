<?php
declare(strict_types=1);

class ParametresService
{
    public static function get(): array
    {
        $row = db()->query('SELECT * FROM parametres ORDER BY id ASC LIMIT 1')->fetch();
        if (!$row) {
            return [
                'nom_pharmacie' => 'PharmaRoyal',
                'devise' => 'FCFA',
                'seuil_marge_min' => 15,
                'seuil_peremption_jours' => 30,
                'objectif_ca_jour' => 0,
                'delai_fournisseur_jours' => 3,
            ];
        }
        return $row;
    }

    public static function regle(string $cle, string $default = ''): string
    {
        $stmt = db()->prepare('SELECT valeur FROM regle_metier WHERE cle = ? LIMIT 1');
        $stmt->execute([$cle]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (string) $v : $default;
    }

    public static function regleInt(string $cle, int $default = 0): int
    {
        return (int) self::regle($cle, (string) $default);
    }
}
