<?php

declare(strict_types=1);

namespace MailPanel\Tests;

use PHPUnit\Framework\TestCase;

final class AdminSensitiveActionSecurityTest extends TestCase
{
    public function test_portal_domain_update_is_super_admin_step_up_only(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/src/Http/Controllers/AdminSecurityController.php');
        $this->assertIsString($source);

        $this->assertStringContainsString('handleUpdatePortalDomain', $source);
        $this->assertStringContainsString('Only Admin level can update the portal domain.', $source);
        $this->assertStringContainsString('$this->assertCurrentAdminSensitiveAction($request);', $source);
    }

    public function test_config_version_actions_require_super_admin_step_up(): void
    {
        $deploymentController = file_get_contents(dirname(__DIR__) . '/src/Http/Controllers/AdminConfigDeploymentController.php');
        $adminController = file_get_contents(dirname(__DIR__) . '/src/Http/Controllers/AdminController.php');
        $this->assertIsString($deploymentController);
        $this->assertIsString($adminController);

        $this->assertStringContainsString('$this->assertCurrentAdminSensitiveAction($request);', $deploymentController);
        $this->assertStringContainsString('Only Admin level can deploy system configuration.', $deploymentController);
        $this->assertStringContainsString('$this->assertSensitiveSystemAction($request);', $adminController);
        $this->assertStringContainsString('assertSensitiveActionAllowed(', $adminController);
    }
}
