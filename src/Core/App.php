<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    private Router $router;
    /** @var array<string, callable(Request, callable): Response> */
    private array $middleware = [];

    public function __construct(private string $basePath)
    {
        Env::load($basePath);
        date_default_timezone_set(Env::get('TZ', 'America/Mexico_City'));
        View::setDirectory($basePath . '/templates');
        $this->router = new Router();
        $this->registerMiddleware();
        (require $basePath . '/config/routes.php')($this->router);
    }

    public function run(): void
    {
        Session::start($this->basePath . '/storage/sessions');
        $this->handle(Request::fromGlobals())->send();
    }

    public function handle(Request $request): Response
    {
        try {
            $global = fn (Request $r) => $this->dispatch($r);
            $pipeline = $this->wrap(['setup', 'csrf'], $global);
            return $pipeline($request);
        } catch (HttpException $e) {
            return $this->errorResponse($request, $e->status, $e->getMessage(), $e->title);
        } catch (\Throwable $e) {
            error_log((string) $e);
            $detail = Env::get('APP_ENV') === 'production' ? '' : $e->getMessage();
            return $this->errorResponse($request, 500, $detail);
        }
    }

    private function dispatch(Request $request): Response
    {
        [$status, $route, $params] = $this->router->match($request->method, $request->path);
        if ($status !== 200) {
            throw new HttpException($status);
        }
        $request->params = $params;
        $handler = $route['handler'];
        $core = function (Request $r) use ($handler): Response {
            $result = is_array($handler)
                ? (new $handler[0]())->{$handler[1]}($r)
                : $handler($r);
            return $result instanceof Response ? $result : new Response((string) $result);
        };
        return $this->wrap($route['mw'], $core)($request);
    }

    /** Envuelve un manejador con una lista de middleware (el primero se ejecuta primero). */
    private function wrap(array $names, callable $core): callable
    {
        $next = $core;
        foreach (array_reverse($names) as $name) {
            $mw = $this->middleware[$name] ?? throw new \LogicException("Middleware desconocido: {$name}");
            $next = fn (Request $r) => $mw($r, $next);
        }
        return $next;
    }

    private function registerMiddleware(): void
    {
        // Si no hay usuarios, todo redirige al asistente de primer arranque
        $this->middleware['setup'] = function (Request $r, callable $next): Response {
            $needsSetup = (int) Database::value('SELECT COUNT(*) FROM users') === 0;
            $inSetup = $r->path === '/setup';
            if ($needsSetup && !$inSetup) {
                return Response::redirect('/setup');
            }
            if (!$needsSetup && $inSetup) {
                return Response::redirect('/login');
            }
            return $next($r);
        };

        $this->middleware['csrf'] = function (Request $r, callable $next): Response {
            if ($r->isMutating() && !Csrf::verify($r)) {
                // 403 y no 419: Apache convierte códigos no estándar en 500
                throw new HttpException(403, 'La sesión expiró o el formulario no es válido. Recarga la página e intenta de nuevo.', 'Sesión expirada');
            }
            return $next($r);
        };

        $this->middleware['guest'] = function (Request $r, callable $next): Response {
            return Auth::check() ? Response::redirect('/') : $next($r);
        };

        // Sesión iniciada (sin exigir empresa seleccionada)
        $this->middleware['auth.only'] = function (Request $r, callable $next): Response {
            if (!Auth::check()) {
                if ($r->wantsJson()) {
                    return Response::json(['error' => 'No autenticado'], 401);
                }
                if ($r->method === 'GET') {
                    Session::set('intended', $r->path);
                }
                return Response::redirect('/login');
            }
            return $next($r);
        };

        // Sesión iniciada y empresa activa seleccionada
        $this->middleware['auth'] = function (Request $r, callable $next): Response {
            return $this->middleware['auth.only']($r, function (Request $r) use ($next): Response {
                if (Auth::companyId() === null || Auth::membership() === null) {
                    Session::forget('company_id');
                    $companies = Auth::companies();
                    if (count($companies) === 1) {
                        Auth::selectCompany((int) $companies[0]['id']);
                    } elseif ($companies === []) {
                        Auth::logout();
                        Session::start($this->basePath . '/storage/sessions');
                        Session::flash('error', 'Tu usuario no tiene ninguna empresa asignada. Contacta al administrador.');
                        return Response::redirect('/login');
                    } else {
                        return Response::redirect('/empresa/seleccionar');
                    }
                }
                return $next($r);
            });
        };

        $this->middleware['admin'] = function (Request $r, callable $next): Response {
            if (!Auth::isAdmin()) {
                throw new HttpException(403, 'Esta sección es solo para administradores.');
            }
            return $next($r);
        };
    }

    private function errorResponse(Request $request, int $status, string $message, ?string $title = null): Response
    {
        $titles = [
            403 => 'Acceso denegado', 404 => 'Página no encontrada', 405 => 'Método no permitido',
            500 => 'Error interno',
        ];
        $title ??= $titles[$status] ?? 'Error';
        if ($request->wantsJson()) {
            return Response::json(['error' => $message ?: $title], $status);
        }
        try {
            $html = View::render('errors/error', compact('status', 'title', 'message'), 'layouts/guest');
        } catch (\Throwable) {
            $html = '<h1>' . e($title) . '</h1><p>' . e($message) . '</p>';
        }
        return new Response($html, $status);
    }
}
