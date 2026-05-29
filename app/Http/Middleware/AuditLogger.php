<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs action/module only — never request bodies (passwords, payment data).
 */
class AuditLogger
{
    /** @var list<string> */
    protected array $skipRoutes = [
        'login',
        'logout',
        'pos.cashier',
    ];

    public function handle(Request $request, Closure $next, string $module = 'backoffice'): Response
    {
        $response = $next($request);

        if (! $request->user()) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, $this->skipRoutes, true)) {
            return $response;
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        $action = $routeName ?: $request->path();
        $subject = $request->route()?->parameters() ?? [];
        $subjectId = null;
        $subjectType = null;
        foreach ($subject as $param) {
            if (is_object($param) && method_exists($param, 'getKey')) {
                $subjectId = (int) $param->getKey();
                $subjectType = class_basename($param);
                break;
            }
        }

        try {
            AuditLog::record(
                userId: (int) $request->user()->id,
                module: $module,
                action: $action,
                routeName: $routeName,
                method: $request->method(),
                subjectId: $subjectId,
                subjectType: $subjectType,
            );
        } catch (\Throwable) {
            // Never block the request if audit insert fails.
        }

        return $response;
    }
}
