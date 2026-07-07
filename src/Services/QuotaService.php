<?php

declare(strict_types=1);

namespace MailPanel\Services;

use InvalidArgumentException;
use MailPanel\Repositories\Pdo\MailboxRepository;
use MailPanel\Repositories\Pdo\QuotaUsageRepository;

final class QuotaService
{
    public function __construct(
        private readonly QuotaUsageRepository $quotaUsage,
        private readonly MailboxRepository $mailboxes,
        private readonly AuditLogService $auditLog,
        private readonly ?AgentClientService $agentClient = null,
        private readonly string $vmailRoot = '/var/vmail'
    ) {
    }

    public function recordUsage(int $mailboxId, int $usedMb, string $source = 'agent'): array
    {
        $mailbox = $this->mailboxes->find($mailboxId);

        if ($mailbox === null) {
            throw new InvalidArgumentException('Mailbox not found.');
        }

        if (!in_array($source, ['agent', 'system'], true)) {
            throw new InvalidArgumentException('Invalid quota usage source.');
        }

        $usedMb = max(0, $usedMb);

        $this->quotaUsage->upsert((int) $mailbox['tenant_id'], $mailboxId, $usedMb);
        $entry = $this->quotaUsage->findByMailbox($mailboxId) ?? [];

        $this->auditLog->log([
            'tenant_id' => $mailbox['tenant_id'],
            'action' => 'quota.updated',
            'target_type' => 'quota_usage',
            'target_id' => $entry['id'] ?? null,
            'new_values' => $entry + ['source' => $source],
        ]);

        return $entry;
    }

    public function mailboxQuota(int $mailboxId): array
    {
        $mailbox = $this->mailboxes->find($mailboxId);

        if ($mailbox === null) {
            throw new InvalidArgumentException('Mailbox not found.');
        }

        $usage = $this->quotaUsage->findByMailbox($mailboxId) ?? ['used_mb' => 0];

        return [
            'mailbox_id' => $mailboxId,
            'email' => $mailbox['email'],
            'used_mb' => (int) ($usage['used_mb'] ?? 0),
            'quota_mb' => (int) $mailbox['quota_mb'],
            'percent' => (int) floor(((int) ($usage['used_mb'] ?? 0) / max((int) $mailbox['quota_mb'], 1)) * 100),
        ];
    }

    public function refreshMailboxUsage(array $mailboxes): array
    {
        if ($this->agentClient === null || $mailboxes === []) {
            return [];
        }

        $mailboxIndex = [];
        $emails = [];

        foreach ($mailboxes as $mailbox) {
            $mailboxId = (int) ($mailbox['id'] ?? 0);
            $email = strtolower(trim((string) ($mailbox['email'] ?? '')));

            if ($mailboxId <= 0 || $email === '') {
                continue;
            }

            $mailboxIndex[$email] = $mailbox;
            $emails[] = $email;
        }

        $emails = array_values(array_unique($emails));
        if ($emails === []) {
            return [];
        }

        $response = $this->agentClient->measureMailStorage([
            'vmail_root' => $this->vmailRoot,
            'mailboxes' => $emails,
            'timeout' => 120,
        ]);
        $result = is_array($response['result'] ?? null) ? $response['result'] : [];

        if ((int) ($result['returncode'] ?? 1) !== 0) {
            throw new \RuntimeException('Mailbox quota scan failed.');
        }

        $usageMap = json_decode((string) ($result['stdout'] ?? ''), true);
        if (!is_array($usageMap)) {
            throw new \RuntimeException('Mailbox quota scan returned invalid data.');
        }

        $updated = [];
        foreach ($usageMap as $email => $usage) {
            $email = strtolower((string) $email);
            $mailbox = $mailboxIndex[$email] ?? null;
            if (!is_array($mailbox)) {
                continue;
            }

            $usedMb = max(0, (int) ($usage['used_mb'] ?? 0));
            $mailboxId = (int) ($mailbox['id'] ?? 0);
            $this->quotaUsage->upsert((int) ($mailbox['tenant_id'] ?? 0), $mailboxId, $usedMb);
            $updated[$mailboxId] = [
                'email' => $email,
                'used_mb' => $usedMb,
                'used_bytes' => max(0, (int) ($usage['used_bytes'] ?? 0)),
            ];
        }

        if ($updated !== []) {
            $this->auditLog->log([
                'action' => 'quota.refreshed',
                'target_type' => 'quota_usage',
                'new_values' => [
                    'mailbox_count' => count($updated),
                    'source' => 'agent',
                ],
            ]);
        }

        return $updated;
    }
}
