<?php

declare(strict_types=1);

namespace MailPanel\Tests;

use PHPUnit\Framework\TestCase;

final class QuotaUsageRefreshTest extends TestCase
{
    public function test_quota_service_refreshes_usage_through_agent_allowlist(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../src/Services/QuotaService.php');

        $this->assertStringContainsString('private readonly ?AgentClientService $agentClient = null', $source);
        $this->assertStringContainsString('public function refreshMailboxUsage(array $mailboxes): array', $source);
        $this->assertStringContainsString('$this->agentClient->measureMailStorage', $source);
        $this->assertStringContainsString('quota.refreshed', $source);
        $this->assertStringNotContainsString('shell_exec', $source);
        $this->assertStringNotContainsString('proc_open', $source);
    }

    public function test_admin_mailbox_page_refreshes_quota_before_rendering(): void
    {
        $controller = (string) file_get_contents(__DIR__ . '/../src/Http/Controllers/AdminMailboxController.php');
        $factory = (string) file_get_contents(__DIR__ . '/../src/Bootstrap/ApplicationFactory.php');

        $this->assertStringContainsString('private readonly QuotaService $quotaService', $controller);
        $this->assertStringContainsString('$this->refreshQuotaUsage($mailboxes);', $controller);
        $this->assertStringContainsString('$mailboxes = $this->scopeByTenant($this->mailboxService->list(), $tenantId);', $controller);
        $this->assertStringContainsString('(string) $config->get(\'mailpanel.vmail_root\', \'/var/vmail\')', $factory);
        $this->assertMatchesRegularExpression(
            '/new AdminMailboxController\([\s\S]*?TenantService::class\),\s*\$c->get\(QuotaService::class\),\s*\$c->get\(AdminSecurityService::class\)/',
            $factory
        );
        $this->assertMatchesRegularExpression(
            '/new AdminDomainController\([\s\S]*?TenantService::class\),\s*\$c->get\(AdminSecurityService::class\)/',
            $factory
        );
    }
}
