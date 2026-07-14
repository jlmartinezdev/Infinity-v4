<?php

namespace App\Http\Controllers;

use App\Models\SifenConfiguracion;
use App\Services\Sifen\SifenCertificadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

class SifenConfiguracionController extends Controller
{
    public function edit()
    {
        $config = SifenConfiguracion::obtenerOInicializar();
        $certificadoService = app(SifenCertificadoService::class);

        $estado = [
            'ambiente' => config('sifen.ambiente', 'test'),
            'certificado_existe' => File::exists(config('sifen.certificado.path')),
            'certificado_configurado' => $certificadoService->disponible(),
            'certificado_ruta' => config('sifen.certificado.path'),
            'csc_env' => filled(config('sifen.csc.token')),
            'password_env' => filled(config('sifen.certificado.password')),
            'password_db' => filled($config->certificado_password),
            'api_envio_modo' => null,
            'api_envio_endpoint' => null,
            'api_ambiente' => null,
        ];

        if (config('sifen.api.enabled')) {
            try {
                $apiStatus = app(\App\Services\Sifen\SifenApiClient::class)->status();
                $estado['api_envio_modo'] = $apiStatus['envio_modo'] ?? null;
                $estado['api_envio_endpoint'] = $apiStatus['envio_endpoint'] ?? null;
                $estado['api_ambiente'] = $apiStatus['ambiente'] ?? null;
                if (! empty($apiStatus['ambiente'])) {
                    $estado['ambiente'] = $apiStatus['ambiente'];
                }
            } catch (\Throwable) {
                // Sin conexión a sifen-api: no bloquear la pantalla de configuración.
            }
        }

        return view('configuracion.sifen', compact('config', 'estado'));
    }

    public function update(Request $request)
    {
        $config = SifenConfiguracion::obtenerOInicializar();

        $validated = $request->validate([
            'ruc' => ['required', 'string', 'regex:/^\d{5,8}$/'],
            'dv_ruc' => ['required', 'integer', 'min:0', 'max:9'],
            'tipo_contribuyente' => ['required', 'integer', 'in:1,2'],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_fantasia' => ['nullable', 'string', 'max:255'],
            'numero_timbrado' => ['required', 'string', 'regex:/^\d{8}$/'],
            'establecimiento' => ['required', 'integer', 'min:1', 'max:999'],
            'punto_expedicion' => ['required', 'integer', 'min:1', 'max:999'],
            'timbrado_vigencia_desde' => ['required', 'date'],
            'timbrado_vigencia_hasta' => ['nullable', 'date', 'after_or_equal:timbrado_vigencia_desde'],
            'codigo_actividad_economica' => ['nullable', 'string', 'max:10'],
            'descripcion_actividad_economica' => ['nullable', 'string', 'max:255'],
            'direccion' => ['required', 'string', 'max:255'],
            'numero_casa' => ['nullable', 'string', 'max:10'],
            'departamento' => ['required', 'integer', 'min:1'],
            'departamento_descripcion' => ['required', 'string', 'max:50'],
            'distrito' => ['required', 'integer', 'min:1'],
            'distrito_descripcion' => ['required', 'string', 'max:50'],
            'ciudad' => ['required', 'integer', 'min:1'],
            'ciudad_descripcion' => ['required', 'string', 'max:50'],
            'telefono' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:100'],
            'csc_id' => ['nullable', 'string', 'max:4'],
            'csc_token' => ['nullable', 'string', 'size:32'],
            'certificado_password' => ['nullable', 'string', 'max:128'],
            'certificado_p12' => ['nullable', 'file', 'max:5120', 'extensions:p12,pfx'],
            'serie_actual' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data = collect($validated)->except([
            'csc_token',
            'certificado_password',
            'certificado_p12',
            'activo',
        ])->all();

        $data['activo'] = $request->boolean('activo', true);

        if ($request->filled('csc_token')) {
            $data['csc_token'] = $validated['csc_token'];
        }

        if ($request->filled('certificado_password')) {
            $data['certificado_password'] = Crypt::encryptString($validated['certificado_password']);
        }

        if ($request->hasFile('certificado_p12')) {
            $destino = config('sifen.certificado.path');
            File::ensureDirectoryExists(dirname($destino));
            File::put($destino, $request->file('certificado_p12')->get());
            @chmod($destino, 0666);
            app(SifenCertificadoService::class)->sincronizarP12Activo($destino);
        }

        $config->update($data);

        if ($request->hasFile('certificado_p12') || $request->filled('certificado_password')) {
            try {
                app(SifenCertificadoService::class)->cargarDesdeP12();
            } catch (\Throwable $e) {
                return redirect()->route('configuracion.sifen')
                    ->with('error', 'Datos guardados, pero el certificado no pudo validarse: '.$e->getMessage());
            }
        }

        return redirect()->route('configuracion.sifen')->with('success', 'Configuración SIFEN guardada correctamente.');
    }
}
