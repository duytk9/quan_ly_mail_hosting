<?php

declare(strict_types=1);

namespace MailPanel\Tests;

use PHPUnit\Framework\TestCase;

final class AdminPasswordResetSecurityTest extends TestCase
{
    public function test_mailbox_and_owner_password_resets_require_admin_reauthentication(): void
    {
        $mailboxController = (string) file_get_contents(__DIR__ . '/../src/Http/Controllers/AdminMailboxController.php');
        $tenantController = (string) file_get_contents(__DIR__ . '/../src/Http/Controllers/AdminTenantController.php');
        $mailboxesView = (string) file_get_contents(__DIR__ . '/../src/Views/admin/pages/mailboxes.php');
        $tenantsView = (string) file_get_contents(__DIR__ . '/../src/Views/admin/pages/tenants.php');

        $this->assertMatchesRegularExpression(
            '/elseif \\(\\$action === \'password\'\\) \\{\\s*\\$this->assertCurrentAdminSensitiveAction\\(\\$request\\);/s',
            $mailboxController
        );
        $this->assertMatchesRegularExpression(
            '/if \\(\\$resetPassword === 1\\) \\{\\s*\\$this->assertCurrentAdminSensitiveAction\\(\\$request\\);/s',
            $tenantController
        );
        $this->assertMatchesRegularExpression('/name="action" value="password"[\\s\\S]*name="current_password"/', $mailboxesView);
        $this->assertMatchesRegularExpression('/name="action" value="password"[\\s\\S]*name="new_password"/', $mailboxesView);
        $this->assertMatchesRegularExpression('/name="reset_password"[\\s\\S]*name="current_password"/', $tenantsView);
        $this->assertMatchesRegularExpression('/name="reset_password"[\\s\\S]*name="new_password"/', $tenantsView);
        $this->assertStringNotContainsString('Mật khẩu mới là: [', $mailboxController);
        $this->assertStringNotContainsString('Mật khẩu mới là: [', $tenantController);
    }
}
