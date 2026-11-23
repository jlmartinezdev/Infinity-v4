<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Cliente;
use App\Services\Sorteo\SorteoService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SorteoWebController extends ApiController
{
    public function __construct(private readonly SorteoService $sorteo) {}

    public function ficha(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'min:1'],
        ]);
        $cliente = Cliente::query()->find($data['cliente_id']);
        if (! $cliente) {
            return $this->fail('No encontramos ese cliente.', 404);
        }

        return $this->ok($this->sorteo->fichaPublica($cliente));
    }

    public function celular(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'min:1'],
            'telefono' => ['required', 'string', 'max:30'],
            'nombre_google' => ['required', 'string', 'min:2', 'max:80'],
            'cedula_ultimos' => ['nullable', 'string', 'max:8'],
        ], [
            'nombre_google.required' => 'Escribí el nombre que aparece en tu reseña de Google.',
            'nombre_google.min' => 'Escribí el nombre que aparece en tu reseña de Google.',
            'nombre_google.max' => 'El nombre de Google es demasiado largo.',
        ]);
        $cliente = Cliente::query()->find($data['cliente_id']);
        if (! $cliente) {
            return $this->fail('No encontramos ese cliente.', 404);
        }

        try {
            $ficha = $this->sorteo->guardarCelularPublico(
                $cliente,
                $data['telefono'],
                $data['cedula_ultimos'] ?? null,
                $data['nombre_google'],
            );
        } catch (DomainException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok($ficha, 'Celular guardado');
    }
}
