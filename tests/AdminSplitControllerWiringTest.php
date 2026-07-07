<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminSplitControllerWiringTest extends TestCase
{
    public function test_admin_layout_trait_uses_core_request_for_pagination(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/Http/Controllers/Traits/AdminWebLayoutTrait.php') ?: '';

        $this->assertStringContainsString('use MailPanel\Core\Request;', $source);
        $this->assertStringContainsString('protected function paginateRows(array $rows, Request $request', $source);
    }

    public function test_routing_controller_receives_mailbox_service(): void
    {
        $controller = file_get_contents(__DIR__ . '/../src/Http/Controllers/AdminRoutingController.php') ?: '';
        $factory = file_get_contents(__DIR__ . '/../src/Bootstrap/ApplicationFactory.php') ?: '';

        $this->assertStringContainsString('private readonly MailboxService $mailboxService', $controller);
        $this->assertMatchesRegularExpression(
            '/AdminRoutingController::class.*?MailGroupService::class.*?DomainService::class.*?MailboxService::class/s',
            $factory
        );
    }
}
