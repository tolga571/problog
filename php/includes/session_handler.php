<?php

declare(strict_types=1);

// Dosya tabanli oturumlar Railway gibi ortamlarda her deploy'da silinen
// gecici (ephemeral) diske yaziliyordu - her push sonrasi tum kullanicilar
// oturumdan atiliyordu. Oturumlari veritabaninda tutmak bu sorunu tamamen
// ortadan kaldiriyor (deploy'lardan etkilenmez, hem SQLite hem Postgres'te
// ayni sekilde calisir).
final class DbSessionHandler implements SessionHandlerInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare('SELECT data FROM sessions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? (string) $row['data'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $this->pdo->prepare(
            'INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?)
             ON CONFLICT (id) DO UPDATE SET data = excluded.data, last_activity = excluded.last_activity'
        )->execute([$id, $data, time()]);
        return true;
    }

    public function destroy(string $id): bool
    {
        $this->pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE last_activity < ?');
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
