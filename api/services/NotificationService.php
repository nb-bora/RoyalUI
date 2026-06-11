<?php
declare(strict_types=1);

class NotificationService
{
    public static function create(
        string $type,
        string $titre,
        string $message,
        string $priorite = 'info',
        ?int $idUtilisateur = null,
        ?string $roleCible = null,
        ?string $lienAction = null,
        ?int $idReference = null,
        ?string $expireAt = null
    ): int {
        $stmt = db()->prepare(
            'INSERT INTO notification (id_utilisateur, role_cible, type, priorite, titre, message, lien_action, id_reference, expire_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $idUtilisateur, $roleCible, $type, $priorite, $titre, $message,
            $lienAction, $idReference, $expireAt,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function notifyRole(string $role, string $type, string $titre, string $message, string $priorite = 'info', ?string $lien = null): void
    {
        self::create($type, $titre, $message, $priorite, null, $role, $lien);
    }

    public static function listForUser(array $user, int $limit = 50): array
    {
        $stmt = db()->prepare(
            "SELECT * FROM notification
             WHERE (expire_at IS NULL OR expire_at > NOW())
               AND (id_utilisateur = ? OR id_utilisateur IS NULL AND (role_cible IS NULL OR role_cible = ?))
             ORDER BY FIELD(priorite, 'critique', 'haute', 'info'), created_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, (int) $user['id'], PDO::PARAM_INT);
        $stmt->bindValue(2, $user['role']);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countUnread(array $user): int
    {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM notification
             WHERE lu = 0 AND (expire_at IS NULL OR expire_at > NOW())
               AND (id_utilisateur = ? OR id_utilisateur IS NULL AND (role_cible IS NULL OR role_cible = ?))"
        );
        $stmt->execute([(int) $user['id'], $user['role']]);
        return (int) $stmt->fetchColumn();
    }

    public static function markRead(int $id, int $userId): void
    {
        db()->prepare(
            'UPDATE notification SET lu = 1 WHERE id = ? AND (id_utilisateur = ? OR id_utilisateur IS NULL)'
        )->execute([$id, $userId]);
    }

    public static function markAllRead(array $user): void
    {
        db()->prepare(
            "UPDATE notification SET lu = 1 WHERE lu = 0
             AND (id_utilisateur = ? OR id_utilisateur IS NULL AND (role_cible IS NULL OR role_cible = ?))"
        )->execute([(int) $user['id'], $user['role']]);
    }
}
