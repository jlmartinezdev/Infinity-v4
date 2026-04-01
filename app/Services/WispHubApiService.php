<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente para la API de WispHub.
 * Documentación: https://wisphub.net/api-docs/#tag/Clientes
 */
class WispHubApiService
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.wisphub.base_url', 'https://api.wisphub.net'), '/');
        $this->apiKey = config('services.wisphub.api_key', '');
    }

    /**
     * Verifica si la integración está configurada.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Obtiene el listado de clientes con paginación.
     *
     * @param  array{limit?: int, offset?: int, estado?: int}  $params  limit, offset, estado (1=Activo, 2=Suspendido, 3=Cancelado, 4=Gratis)
     * @return array{count: int, next: ?string, previous: ?string, results: array<int, array>}
     */
    public function getClientes(array $params = []): array
    {
        $defaults = ['limit' => 50, 'offset' => 0];
        $query = array_merge($defaults, array_filter($params));

        $response = $this->request('GET', '/api/clientes/', $query);

        if (! $response->successful()) {
            Log::warning('WispHub API getClientes failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['count' => 0, 'next' => null, 'previous' => null, 'results' => []];
        }

        return $response->json();
    }

    /**
     * Obtiene todos los clientes paginando hasta agotar resultados.
     *
     * @param  int|null  $estado  1=Activo, 2=Suspendido, 3=Cancelado, 4=Gratis, null=Todos
     * @param  int  $maxLimit  Límite máximo de registros (por seguridad)
     * @return array<int, array>
     */
    public function getAllClientes(?int $estado = null, int $maxLimit = 10000): array
    {
        $todos = [];
        $offset = 0;
        $limit = 100;

        while (count($todos) < $maxLimit) {
            $params = ['limit' => $limit, 'offset' => $offset];
            if ($estado !== null) {
                $params['estado'] = $estado;
            }
            $data = $this->getClientes($params);
            $results = $data['results'] ?? [];
            if (empty($results)) {
                break;
            }
            $todos = array_merge($todos, $results);
            $offset += $limit;
            if (count($results) < $limit) {
                break;
            }
        }

        return array_slice($todos, 0, $maxLimit);
    }

    /**
     * Obtiene un cliente por id_servicio.
     */
    public function getCliente(int $idServicio): ?array
    {
        $response = $this->request('GET', "/api/clientes/{$idServicio}/");

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Obtiene el perfil adicional de un cliente (nombre, apellidos, cedula, direccion, telefono, etc.).
     */
    public function getClientePerfil(int $idServicio): ?array
    {
        $response = $this->request('GET', "/api/clientes/{$idServicio}/perfil/");

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Listado de zonas (útil para mapear al crear clientes en WispHub).
     */
    public function getZonas(): array
    {
        $response = $this->request('GET', '/api/zonas/', ['limit' => 500]);
        if (! $response->successful()) {
            return [];
        }
        $data = $response->json();
        return $data['results'] ?? [];
    }

    /**
     * Listado de planes de internet (para mapear plan_id WispHub -> Infinity).
     */
    public function getPlanesInternet(): array
    {
        $response = $this->request('GET', '/api/plan-internet/', ['limit' => 500]);
        if (! $response->successful()) {
            return [];
        }
        $data = $response->json();
        return $data['results'] ?? [];
    }

    /**
     * Realiza una petición a la API.
     *
     * @param  array<string, mixed>  $query
     */
    protected function request(string $method, string $path, array $query = []): \Illuminate\Http\Client\Response
    {
        $url = $this->baseUrl . $path;

        return Http::withHeaders([
            'Authorization' => 'Api-Key ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(30)->{$method}($url, $method === 'GET' ? $query : []);
    }
}
