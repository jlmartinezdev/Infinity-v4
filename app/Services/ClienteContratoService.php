<?php

namespace App\Services;

use App\Helpers\TelefonoParaguayHelper;
use App\Models\AjustesGenerales;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\SifenConfiguracion;
use Illuminate\Support\Collection;

/**
 * Arma el modelo de contrato de internet con datos del cliente, plan y empresa.
 */
class ClienteContratoService
{
    /**
     * @return array{
     *   cliente: Cliente,
     *   cliente_nombre: string,
     *   cliente_documento: string,
     *   cliente_telefono: string,
     *   cliente_email: string,
     *   cliente_direccion: string,
     *   empresa: array<string, mixed>,
     *   servicios: list<array<string, mixed>>,
     *   fecha: \Carbon\Carbon,
     *   ciudad: string,
     *   titulo: string,
     *   clausulas: array<string, string>
     * }
     */
    public function paraCliente(Cliente $cliente, ?int $servicioId = null): array
    {
        $cliente->loadMissing(['servicios.plan']);

        $servicios = $this->serviciosDelContrato($cliente, $servicioId);
        $empresa = $this->datosEmpresa();

        $ciudadConfig = trim((string) config('contrato.ciudad_firma', ''));
        $ciudad = $ciudadConfig !== ''
            ? $ciudadConfig
            : (string) ($empresa['ciudad'] ?: 'Paraguay');

        return [
            'cliente' => $cliente,
            'cliente_nombre' => self::nombreCliente($cliente),
            'cliente_documento' => self::documentoCliente($cliente),
            'cliente_telefono' => TelefonoParaguayHelper::formatear($cliente->telefono) ?: '—',
            'cliente_email' => trim((string) ($cliente->email ?? '')) ?: '—',
            'cliente_direccion' => trim((string) ($cliente->direccion ?? '')) ?: '—',
            'empresa' => $empresa,
            'servicios' => $servicios->all(),
            'fecha' => now(),
            'ciudad' => $ciudad,
            'titulo' => (string) config('contrato.titulo'),
            'clausulas' => config('contrato.clausulas', []),
        ];
    }

    public static function nombreCliente(Cliente $cliente): string
    {
        $nombre = trim(trim((string) ($cliente->nombre ?? '')).' '.trim((string) ($cliente->apellido ?? '')));

        return $nombre !== '' ? $nombre : 'Cliente #'.$cliente->cliente_id;
    }

    public static function documentoCliente(Cliente $cliente): string
    {
        $cedula = trim((string) ($cliente->cedula ?? ''));

        return $cedula !== '' ? $cedula : '—';
    }

    public static function formatearGs(float|int|string|null $monto): string
    {
        return number_format((float) $monto, 0, ',', '.').' Gs.';
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function serviciosDelContrato(Cliente $cliente, ?int $servicioId): Collection
    {
        $todos = ($cliente->servicios ?? collect())
            ->filter(fn (Servicio $s) => $s->estado !== Servicio::ESTADO_CANCELADO)
            ->values();

        if ($servicioId) {
            $filtrados = $todos->filter(fn (Servicio $s) => (int) $s->servicio_id === $servicioId)->values();
            if ($filtrados->isNotEmpty()) {
                $todos = $filtrados;
            }
        }

        return $todos->map(fn (Servicio $s) => $this->mapServicio($s))->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapServicio(Servicio $s): array
    {
        $plan = $s->plan;
        $precio = $plan?->precio;
        $estados = Servicio::estadosDisponibles();
        $estadoCodigo = (string) ($s->estado ?? '');

        return [
            'servicio_id' => (int) $s->servicio_id,
            'etiqueta' => $s->etiqueta(),
            'plan' => trim((string) ($plan?->nombre ?? '')) ?: '—',
            'velocidad' => trim((string) ($plan?->velocidad ?? '')) ?: '—',
            'precio' => $precio !== null ? self::formatearGs($precio) : '—',
            'ip' => trim((string) ($s->ip ?? '')) ?: '—',
            'fecha_instalacion' => $s->fecha_instalacion?->format('d/m/Y') ?: '—',
            'estado' => $estados[$estadoCodigo] ?? $estadoCodigo ?: '—',
            'cpe_onu' => trim((string) ($s->cpe_onu ?? '')) ?: '',
            'cpe_router' => trim((string) ($s->cpe_router ?? '')) ?: '',
            'cpe_antena' => trim((string) ($s->cpe_antena ?? '')) ?: '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosEmpresa(): array
    {
        $ajustes = AjustesGenerales::obtener();
        $sifen = SifenConfiguracion::activa()
            ?? SifenConfiguracion::query()->orderBy('id')->first();

        $nombreAjustes = trim((string) ($ajustes?->nombre_empresa ?? ''));
        $fantasia = trim((string) ($sifen?->nombre_fantasia ?? ''));
        $razon = trim((string) ($sifen?->razon_social ?? ''));
        $nombre = $nombreAjustes !== '' ? $nombreAjustes : ($fantasia !== '' ? $fantasia : ($razon !== '' ? $razon : (string) config('app.name', 'InterPlus+')));

        $ruc = '';
        if ($sifen && trim((string) $sifen->ruc) !== '' && trim((string) $sifen->ruc) !== '0000000') {
            $ruc = trim((string) $sifen->ruc);
            if (isset($sifen->dv_ruc) && (string) $sifen->dv_ruc !== '') {
                $ruc .= '-'.$sifen->dv_ruc;
            }
        }

        $direccion = trim((string) ($ajustes?->direccion ?? ''));
        if ($direccion === '') {
            $direccion = trim((string) ($sifen?->direccion ?? ''));
            $nro = trim((string) ($sifen?->numero_casa ?? ''));
            if ($nro !== '' && $nro !== '0') {
                $direccion = trim($direccion.' N° '.$nro);
            }
        }

        $telefono = trim((string) ($ajustes?->telefono ?? ''));
        if ($telefono === '') {
            $telefono = trim((string) ($sifen?->telefono ?? ''));
        }

        $email = trim((string) ($ajustes?->email ?? ''));
        if ($email === '') {
            $email = trim((string) ($sifen?->email ?? ''));
        }

        $ciudad = trim((string) ($sifen?->ciudad_descripcion ?? ''));
        if ($ciudad === '') {
            $ciudad = trim((string) ($sifen?->distrito_descripcion ?? ''));
        }

        return [
            'nombre' => $nombre,
            'razon_social' => $razon !== '' ? $razon : $nombre,
            'ruc' => $ruc !== '' ? $ruc : '—',
            'direccion' => $direccion !== '' ? $direccion : '—',
            'telefono' => $telefono !== '' ? $telefono : '—',
            'email' => $email !== '' ? $email : '—',
            'web' => trim((string) ($ajustes?->sitio_web ?? '')) ?: '—',
            'ciudad' => $ciudad,
            'logo_url' => $ajustes?->urlLogo(),
        ];
    }
}
