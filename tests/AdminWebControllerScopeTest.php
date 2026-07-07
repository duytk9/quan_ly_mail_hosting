<?php

declare(strict_types=1);

namespace MailPanel\Tests;

use MailPanel\Http\Controllers\Traits\AdminWebLayoutTrait;
use PHPUnit\Framework\TestCase;

final class AdminWebControllerScopeTest extends TestCase
{
    public function test_scope_tenant_rows_filters_by_tenant_id_column_id(): void
    {
        $controller = $this->controller();

        $tenants = [
            ['id' => 11, 'name' => 'Tenant A'],
            ['id' => 12, 'name' => 'Tenant B'],
        ];

        $this->assertSame([$tenants[0]], $controller->scopeTenantRows($tenants, 11));
    }

    public function test_regular_tenant_scoping_still_uses_tenant_id_column(): void
    {
        $controller = $this->controller();

        $rows = [
            ['id' => 1, 'tenant_id' => 11, 'domain' => 'a.example.test'],
            ['id' => 2, 'tenant_id' => 12, 'domain' => 'b.example.test'],
        ];

        $this->assertSame([$rows[0]], $controller->scopeByTenant($rows, 11));
    }

    private function controller(): object
    {
        return new class {
            use AdminWebLayoutTrait {
                scopeByTenant as public;
                scopeTenantRows as public;
            }

            protected function view(): \MailPanel\Support\View
            {
                throw new \LogicException('Not needed for scope tests.');
            }

            protected function sessions(): \MailPanel\Security\SessionManager
            {
                throw new \LogicException('Not needed for scope tests.');
            }

            protected function authorization(): \MailPanel\Security\AuthorizationService
            {
                throw new \LogicException('Not needed for scope tests.');
            }
        };
    }
}
