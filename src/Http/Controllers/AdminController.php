<?php

declare(strict_types=1);

namespace MailPanel\Http\Controllers;

use MailPanel\Core\Request;
use MailPanel\Security\Actor;
use MailPanel\Security\AuthorizationService;
use MailPanel\Security\RequestActorResolver;
use MailPanel\Services\AliasService;
use MailPanel\Services\AdminSecurityService;
use MailPanel\Services\ConfigDeploymentService;
use MailPanel\Services\DashboardService;
use MailPanel\Services\DomainService;
use MailPanel\Services\ForwardService;
use MailPanel\Services\MailboxService;
use MailPanel\Services\PackageService;
use MailPanel\Services\QuotaService;
use MailPanel\Services\TenantService;
use MailPanel\Support\ApiResponse;
use Throwable;

final class AdminController
{
    public function __construct(
        private readonly RequestActorResolver $actorResolver,
        private readonly AuthorizationService $authorization,
        private readonly DashboardService $dashboardService,
        private readonly PackageService $packageService,
        private readonly TenantService $tenantService,
        private readonly DomainService $domainService,
        private readonly MailboxService $mailboxService,
        private readonly AliasService $aliasService,
        private readonly ForwardService $forwardService,
        private readonly ConfigDeploymentService $configDeploymentService,
        private readonly QuotaService $quotaService,
        private readonly AdminSecurityService $adminSecurityService
    ) {
    }

    public function dashboard(Request $request)
    {
        $actor = $this->actorResolver->resolve($request);

        if ($actor->tenantId === null) {
            return ApiResponse::success($this->dashboardService->systemOverview());
        }

        $tenantStats = $this->dashboardService->tenantOverview($actor->tenantId);
        $domains = $this->scopeRows($actor, $this->domainService->list());
        $mailboxes = $this->scopeRows($actor, $this->mailboxService->list());
        $aliases = $this->scopeRows($actor, $this->aliasService->list());
        $forwards = $this->scopeRows($actor, $this->forwardService->list());

        return ApiResponse::success([
            'tenants' => 1,
            'domains' => count($domains),
            'mailboxes' => count($mailboxes),
            'aliases' => count($aliases),
            'forwards' => count($forwards),
            'quota_used_mb' => (int) ($tenantStats['quota_used_mb'] ?? 0),
            'mailboxes_with_usage' => (int) ($tenantStats['mailboxes_with_usage'] ?? 0),
        ]);
    }

    public function packages(Request $request)
    {
        if ($request->method === 'GET') {
            return ApiResponse::success($this->packageService->list());
        }

        return $this->wrap(fn () => $this->packageService->create($request->body), 201);
    }

    public function tenants(Request $request)
    {
        $actor = $this->actorResolver->resolve($request);

        if ($request->method === 'GET') {
            return ApiResponse::success($this->scopeRows($actor, $this->tenantService->list()));
        }

        return $this->wrap(function () use ($actor, $request) {
            if ($actor->role !== 'super_admin') {
                throw new \RuntimeException('Only Admin level can create customer accounts.');
            }

            return $this->tenantService->create($request->body);
        }, 201);
    }

    public function domains(Request $request)
    {
        $actor = $this->actorResolver->resolve($request);

        if ($request->method === 'GET') {
            return ApiResponse::success($this->scopeRows($actor, $this->domainService->list()));
        }

        return $this->wrap(function () use ($actor, $request) {
            $this->authorization->requireTenantScope($actor, (int) ($request->body['tenant_id'] ?? 0));

            return $this->domainService->create($request->body);
        }, 201);
    }

    public function mailboxes(Request $request)
    {
        $actor = $this->actorResolver->resolve($request);

        if ($request->method === 'GET') {
            return ApiResponse::success($this->scopeRows($actor, $this->mailboxService->list()));
        }

        return $this->wrap(function () use ($actor, $request) {
            $targetTenantId = $this->mailboxService->resolveTenantIdForCreate($request->body);
            $this->authorization->requireTenantScope($actor, $targetTenantId);

            return $this->mailboxService->create($request->body + ['tenant_id' => $targetTenantId]);
        }, 201);
    }

    public function aliases(Request $request)
    {
        $actor = $this->actorResolver->resolve($request);

        if ($request->method === 'GET') {
            return ApiResponse::success($this->scopeRows($actor, $this->aliasService->list()));
        }

        return $this->wrap(function () use ($actor, $request) {
            $this->authorization->requireTenantScope($actor, (int) ($request->body['tenant_id'] ?? 0));

            return $this->aliasService->create($request->body);
        }, 201);
    }

    public function forwards(Request $request)
    {
        $actor = $this->actorResolver->resolve($request);

        if ($request->method === 'GET') {
            return ApiResponse::success($this->scopeRows($actor, $this->forwardService->list()));
        }

        return $this->wrap(function () use ($actor, $request) {
            $this->authorization->requireTenantScope($actor, (int) ($request->body['tenant_id'] ?? 0));

            return $this->forwardService->create($request->body);
        }, 201);
    }

    public function generateConfig(Request $request)
    {
        $actor = $this->actorResolver->resolve($request);

        return $this->wrap(function () use ($actor, $request) {
            $this->assertSensitiveSystemAction($request);

            return $this->configDeploymentService->generateValidationPlan($actor->id);
        }, 201);
    }

    public function configVersions(Request $request)
    {
        return ApiResponse::success($this->configDeploymentService->listVersions());
    }

    public function applyConfigVersion(Request $request)
    {
        return $this->wrap(
            function () use ($request) {
                $this->assertSensitiveSystemAction($request);

                return $this->configDeploymentService->applyVersion(
                    (int) ($request->body['version_id'] ?? 0),
                    $this->booleanBody($request, 'simulate', true)
                );
            },
            200
        );
    }

    public function rollbackConfigVersion(Request $request)
    {
        return $this->wrap(
            function () use ($request) {
                $this->assertSensitiveSystemAction($request);

                return $this->configDeploymentService->rollbackVersion(
                    (int) ($request->body['version_id'] ?? 0),
                    $this->booleanBody($request, 'simulate', true)
                );
            },
            200
        );
    }

    public function updateQuota(Request $request)
    {
        return $this->wrap(function () use ($request) {
            $actor = $this->actorResolver->resolve($request);
            $mailboxId = (int) ($request->body['mailbox_id'] ?? 0);
            $mailbox = $this->mailboxService->find($mailboxId);

            if ($mailbox === null) {
                throw new \InvalidArgumentException('Mailbox not found.');
            }

            if ($actor->role !== 'super_admin') {
                throw new \RuntimeException('Permission denied.');
            }

            return $this->quotaService->recordUsage(
                $mailboxId,
                (int) ($request->body['used_mb'] ?? 0),
                'system'
            );
        }, 200);
    }

    private function booleanBody(Request $request, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $request->body)) {
            return $default;
        }

        $value = $request->body[$key];
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }

    private function assertSensitiveSystemAction(Request $request): void
    {
        $actor = $this->actorResolver->resolve($request);
        if ($actor->role !== 'super_admin' || $actor->id <= 0) {
            throw new \RuntimeException('Only Admin level can deploy system configuration.');
        }

        $this->adminSecurityService->assertSensitiveActionAllowed(
            $actor->id,
            (string) ($request->body['current_password'] ?? ''),
            isset($request->body['otp']) ? (string) $request->body['otp'] : null
        );
    }

    private function scopeRows(Actor $actor, array $rows): array
    {
        if ($actor->tenantId === null) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => (int) ($row['tenant_id'] ?? $row['id'] ?? 0) === $actor->tenantId
        ));
    }

    private function wrap(callable $callback, int $status = 200)
    {
        try {
            return ApiResponse::success($callback(), [], $status);
        } catch (Throwable $exception) {
            return ApiResponse::exception($exception, 422, 'Admin API request failed.');
        }
    }
}
