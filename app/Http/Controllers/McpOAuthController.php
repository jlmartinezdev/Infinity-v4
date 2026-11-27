<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Mcp\McpOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class McpOAuthController extends Controller
{
    public function __construct(private readonly McpOAuthService $oauth) {}

    public function recurso(): JsonResponse
    {
        return response()->json($this->oauth->metadatosRecurso());
    }

    public function servidor(): JsonResponse
    {
        return response()->json($this->oauth->metadatosServidor());
    }

    public function registrar(Request $request): JsonResponse
    {
        try {
            $creado = $this->oauth->registrar($request->all());
        } catch (\InvalidArgumentException) {
            return response()->json(['error' => 'invalid_client_metadata'], 400);
        }

        return response()->json($creado, 201);
    }

    public function autorizar(Request $request): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $preparado = $this->preparar($request);
        if (isset($preparado['error'])) {
            return $this->vistaError($preparado['error']);
        }

        $usuario = $request->user();
        if (! $usuario instanceof User || ! $usuario->tienePermiso('whatsapp.ver') || $usuario->esClientePortal()) {
            return $this->vistaError('Su usuario no puede autorizar WhatsApp. Hace falta el permiso de ver WhatsApp.');
        }

        return view('mcp.oauth-consent', [
            'nombre' => $preparado['nombre'],
            'params' => $this->params($request),
            'error' => null,
        ]);
    }

    public function decidir(Request $request): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $usuario = $request->user();
        $preparado = $this->preparar($request);
        if (! $usuario instanceof User || isset($preparado['error'])) {
            return $this->vistaError($preparado['error'] ?? 'El enlace de autorización no es válido.');
        }
        if (! $usuario->tienePermiso('whatsapp.ver') || $usuario->esClientePortal()) {
            return $this->vistaError('Su usuario no puede autorizar WhatsApp.');
        }

        $redirect = $preparado['redirect_uri'];
        $state = (string) $request->input('state', '');
        if ($request->input('accion') !== 'autorizar') {
            return $this->volver($redirect, ['error' => 'access_denied'], $state);
        }

        $resource = $request->filled('resource') ? (string) $request->input('resource') : null;
        $codigo = $this->oauth->emitirCodigo(
            (int) $usuario->usuario_id,
            $preparado['client_id'],
            $redirect,
            (string) $request->input('code_challenge'),
            $resource,
        );
        if ($codigo === null) {
            return $this->vistaError('No se pudo completar la autorización.');
        }

        return $this->volver($redirect, ['code' => $codigo], $state);
    }

    public function token(Request $request): JsonResponse
    {
        $emitido = $this->oauth->canjear($request);
        if ($emitido === null) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }

        return response()->json($emitido)->header('Cache-Control', 'no-store');
    }

    /** @return array{client_id?: string, nombre?: string, redirect_uri?: string, error?: string} */
    private function preparar(Request $request): array
    {
        if ((string) $request->input('response_type') !== 'code') {
            return ['error' => 'El conector no pidió un código de autorización.'];
        }

        $resource = $request->filled('resource') ? (string) $request->input('resource') : null;

        return $this->oauth->prepararAutorizacion(
            (string) $request->input('client_id'),
            (string) $request->input('redirect_uri'),
            (string) $request->input('code_challenge'),
            (string) $request->input('code_challenge_method'),
            $resource,
        );
    }

    /** @return array<string, string> */
    private function params(Request $request): array
    {
        $claves = ['response_type', 'client_id', 'redirect_uri', 'code_challenge', 'code_challenge_method', 'state', 'scope', 'resource'];
        $params = [];
        foreach ($claves as $clave) {
            $valor = (string) $request->input($clave, '');
            if ($valor !== '' && strlen($valor) <= 500) {
                $params[$clave] = $valor;
            }
        }

        return $params;
    }

    /** @param  array<string, string>  $extra */
    private function volver(string $redirect, array $extra, string $state): RedirectResponse
    {
        if ($state !== '' && strlen($state) <= 500 && ! str_contains($state, "\n")) {
            $extra['state'] = $state;
        }
        $sep = str_contains($redirect, '?') ? '&' : '?';

        return redirect()->away($redirect.$sep.http_build_query($extra, '', '&', PHP_QUERY_RFC3986));
    }

    private function vistaError(string $mensaje): View
    {
        return view('mcp.oauth-consent', [
            'nombre' => '',
            'params' => [],
            'error' => $mensaje,
        ]);
    }
}
