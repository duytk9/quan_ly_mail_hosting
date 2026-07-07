<?php

declare(strict_types=1);

namespace MailPanel\Core;

use MailPanel\Security\AuthorizationService;
use MailPanel\Security\CsrfService;
use MailPanel\Security\RequestActorResolver;
use MailPanel\Security\TokenGuard;
use MailPanel\Services\RateLimiterService;
use MailPanel\Support\ApiResponse;
use RuntimeException;

final class Router
{
    private array $routes = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function add(string $method, string $path, callable|array $handler, array $meta = []): void
    {
        $this->routes[strtoupper($method)][$path] = [
            'handler' => $handler,
            'meta' => $meta,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $route = $this->routes[$request->method][$request->path] ?? null;

        if ($route === null) {
            return $request->isApi()
                ? Response::json([
                    'success' => false,
                    'data' => null,
                    'error' => ['message' => 'Not found'],
                    'meta' => [],
                ], 404)
                : Response::html('<!doctype html><html lang="vi"><meta charset="utf-8"><title>404</title><body><h1>404</h1><p>Trang kh&#244;ng t&#7891;n t&#7841;i.</p></body></html>', 404);
        }

        $handler = $route['handler'];
        $meta = $route['meta'] ?? [];
        try {
            $this->applyRequestGuards($request, $meta);
            $this->authorize($request, $meta);
        } catch (RuntimeException $exception) {
            $status = $this->securityFailureStatus($exception);

            if ($status === null) {
                throw $exception;
            }

            return $this->securityFailureResponse($request, $exception->getMessage(), $status);
        }

        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = $this->container->get($class);

            return $controller->{$method}($request);
        }

        $response = $handler($request, $this->container);

        if (!$response instanceof Response) {
            throw new RuntimeException('Route handler must return a response.');
        }

        return $response;
    }

    private function securityFailureStatus(RuntimeException $exception): ?int
    {
        $message = $exception->getMessage();

        return match (true) {
            $message === 'Unauthenticated.' => 401,
            $message === 'Forbidden.' => 403,
            $message === 'Permission denied.' => 403,
            $message === 'Tenant scope violation.' => 403,
            $message === 'Readonly role cannot modify resources.' => 403,
            $message === 'API token scope denied.' => 403,
            $message === 'CSRF token mismatch.' => 403,
            $message === 'Password change required.' => 403,
            str_starts_with($message, 'Too many requests.') => 429,
            default => null,
        };
    }

    private function securityFailureResponse(Request $request, string $message, int $status): Response
    {
        if ($request->isApi()) {
            return ApiResponse::error($message, $status);
        }

        if ($status === 401) {
            return Response::redirect('/admin/login');
        }

        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $title = $status === 403 ? 'Truy c&#7853;p b&#7883; t&#7915; ch&#7889;i' : ($status === 429 ? 'Qu&#225; nhi&#7873;u y&#234;u c&#7847;u' : 'L&#7895;i x&#225;c th&#7921;c');

        return Response::html(
            "<!doctype html><html lang=\"vi\"><meta charset=\"utf-8\"><title>{$title}</title><body><h1>{$title}</h1><p>{$safeMessage}</p></body></html>",
            $status
        );
    }

    private function applyRequestGuards(Request $request, array $meta): void
    {
        if ($request->isApi()) {
            /** @var RateLimiterService $rateLimiter */
            $rateLimiter = $this->container->get(RateLimiterService::class);
            /** @var Config $config */
            $config = $this->container->get(Config::class);
            $settings = $config->get('app.rate_limits.api', ['max_attempts' => 120, 'window_seconds' => 60]);
            $bucket = sprintf('api:%s:%s', $request->ip(), $request->path);
            $rateLimiter->hit($bucket, (int) $settings['max_attempts'], (int) $settings['window_seconds']);
        }

        $isMutating = in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $csrfEnabled = (($meta['csrf'] ?? true) === true);

        if (!$request->isApi() && $isMutating && $csrfEnabled) {
            /** @var CsrfService $csrf */
            $csrf = $this->container->get(CsrfService::class);
            $csrf->verifyRequest($request);
        }

        if ($request->isApi() && $isMutating && $csrfEnabled && (($meta['auth'] ?? false) === true)) {
            /** @var TokenGuard $tokenGuard */
            $tokenGuard = $this->container->get(TokenGuard::class);

            if (!$this->hasBearerToken($request) && $tokenGuard->resolve($request) === null) {
                /** @var RequestActorResolver $actorResolver */
                $actorResolver = $this->container->get(RequestActorResolver::class);

                if ($actorResolver->hasSessionIdentity()) {
                    /** @var CsrfService $csrf */
                    $csrf = $this->container->get(CsrfService::class);
                    $csrf->verifyRequest($request);
                }
            }
        }
    }

    private function hasBearerToken(Request $request): bool
    {
        return TokenGuard::hasBearerCredential($request->header('Authorization', '') ?? '');
    }

    private function authorize(Request $request, array $meta): void
    {
        if (($meta['auth'] ?? false) !== true) {
            return;
        }

        /** @var RequestActorResolver $actorResolver */
        $actorResolver = $this->container->get(RequestActorResolver::class);
        /** @var AuthorizationService $authorization */
        $authorization = $this->container->get(AuthorizationService::class);
        /** @var TokenGuard $tokenGuard */
        $tokenGuard = $this->container->get(TokenGuard::class);

        $actor = $actorResolver->resolve($request);

        if (($meta['allow_guest_fallback'] ?? false) !== true && $actor->id <= 0) {
            throw new RuntimeException('Unauthenticated.');
        }

        $this->assertPasswordRotationSatisfied($request, $meta, $actorResolver);

        if (isset($meta['roles'])) {
            $authorization->requireRole($actor, $meta['roles']);
        }

        if (isset($meta['permission'])) {
            $authorization->requirePermission($actor, (string) $meta['permission']);
        }

        if (isset($meta['permissions'])) {
            $authorization->requirePermission(
                $actor,
                (array) $meta['permissions'],
                (($meta['permissions_mode'] ?? 'all') === 'any')
            );
        }

        if (isset($meta['writable']) && $meta['writable'] === true) {
            $authorization->assertWritable($actor);
        }

        if (isset($meta['tenant_body_field']) && array_key_exists($meta['tenant_body_field'], $request->body)) {
            $authorization->requireTenantScope($actor, (int) $request->body[$meta['tenant_body_field']]);
        }

        if (isset($meta['token_scopes'])) {
            $token = $tokenGuard->resolve($request);

            if ($token !== null) {
                $authorization->requireScopes($token, $meta['token_scopes']);
            }
        }
    }

    private function assertPasswordRotationSatisfied(Request $request, array $meta, RequestActorResolver $actorResolver): void
    {
        if (($meta['allow_password_change'] ?? false) === true) {
            return;
        }

        if (in_array($request->path, ['/api/logout', '/api/user/password'], true)) {
            return;
        }

        $identity = $actorResolver->sessionIdentity();
        if (!is_array($identity) || empty($identity['force_password_change'])) {
            return;
        }

        throw new RuntimeException('Password change required.');
    }
}
