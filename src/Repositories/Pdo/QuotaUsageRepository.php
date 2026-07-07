<?php

declare(strict_types=1);

namespace MailPanel\Repositories\Pdo;

final class QuotaUsageRepository extends AbstractPdoRepository
{
    public function upsert(int $tenantId, int $mailboxId, int $usedMb): void
    {
        $sql = 'INSERT INTO quota_usage (tenant_id, mailbox_id, used_mb, calculated_at, created_at, updated_at)
                VALUES (:tenant_id, :mailbox_id, :used_mb, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE used_mb = VALUES(used_mb), calculated_at = NOW(), updated_at = NOW()';
        $this->execute($sql, [
            'tenant_id' => $tenantId,
            'mailbox_id' => $mailboxId,
            'used_mb' => $usedMb,
        ]);
    }

    public function findByMailbox(int $mailboxId): ?array
    {
        return $this->fetchOne('SELECT * FROM quota_usage WHERE mailbox_id = :mailbox_id', ['mailbox_id' => $mailboxId]);
    }

    public function tenantTotals(int $tenantId): array
    {
        return $this->fetchOne(
            'SELECT COALESCE(SUM(used_mb), 0) AS used_mb, COUNT(*) AS mailbox_count FROM quota_usage WHERE tenant_id = :tenant_id',
            ['tenant_id' => $tenantId]
        ) ?? ['used_mb' => 0, 'mailbox_count' => 0];
    }

    /**
     * @return array<int, int>
     */
    public function tenantUsageMap(): array
    {
        $rows = $this->fetchAll('SELECT tenant_id, COALESCE(SUM(used_mb), 0) AS used_mb FROM quota_usage GROUP BY tenant_id');
        $usage = [];

        foreach ($rows as $row) {
            $usage[(int) ($row['tenant_id'] ?? 0)] = (int) ($row['used_mb'] ?? 0);
        }

        return $usage;
    }

    /**
     * @param list<int> $mailboxIds
     * @return array<int, int>
     */
    public function mailboxUsageMap(array $mailboxIds): array
    {
        $mailboxIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $mailboxIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($mailboxIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($mailboxIds), '?'));
        $rows = $this->fetchAll(
            "SELECT mailbox_id, COALESCE(used_mb, 0) AS used_mb FROM quota_usage WHERE mailbox_id IN ({$placeholders})",
            $mailboxIds
        );
        $usage = [];

        foreach ($rows as $row) {
            $usage[(int) ($row['mailbox_id'] ?? 0)] = (int) ($row['used_mb'] ?? 0);
        }

        return $usage;
    }
}
