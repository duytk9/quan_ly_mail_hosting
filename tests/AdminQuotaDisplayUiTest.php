<?php

declare(strict_types=1);

namespace MailPanel\Tests;

use PHPUnit\Framework\TestCase;

final class AdminQuotaDisplayUiTest extends TestCase
{
    public function test_mailbox_list_displays_used_and_assigned_quota(): void
    {
        $view = (string) file_get_contents(__DIR__ . '/../src/Views/admin/pages/mailboxes.php');

        $this->assertStringContainsString('$usedMailboxQuotaMb . \' / \' . $assignedMailboxQuotaMb . \' MB\'', $view);
        $this->assertStringContainsString('<strong><?= $usedMb ?> / <?= $quotaMb ?> MB</strong>', $view);
        $this->assertStringContainsString('Đã dùng / được cấp', $view);
        $this->assertStringNotContainsString('Quota không chỉnh sửa được', $view);
        $this->assertMatchesRegularExpression(
            '/<td>\s*<strong><\?= \$usedMb \?> \/ <\?= \$quotaMb \?> MB<\/strong>[\s\S]*?<\/td>\s*<td><\?= \$view->render/',
            $view
        );
        $this->assertMatchesRegularExpression(
            '/<details class="action-menu">[\s\S]*?<input type="hidden" name="action" value="quota">[\s\S]*?<button class="btn btn-secondary btn-sm" type="submit">Cập nhật quota<\/button>/',
            $view
        );
    }

    public function test_tenant_list_distinguishes_used_allocated_and_assigned_quota(): void
    {
        $view = (string) file_get_contents(__DIR__ . '/../src/Views/admin/pages/tenants.php');

        $this->assertStringContainsString('<?= (int) $usage[\'quota_mb\'] ?> / <?= (int) ($item[\'max_total_quota_mb\'] ?? 0) ?>', $view);
        $this->assertStringContainsString('Đã dùng / được cấp', $view);
        $this->assertStringContainsString('Đã cấp mailbox: <?= (int) ($usage[\'assigned_quota_mb\'] ?? 0) ?> MB', $view);
    }
}
