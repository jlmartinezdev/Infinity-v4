<?php

namespace App\Http\Controllers;

use App\Models\AjustesGenerales;
use App\Models\FacturacionParametro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfiguracionController extends Controller
{
    public function index()
    {
        return view('configuracion.index');
    }

    /**
     * Impresión: tamaño papel recibo (guardado en localStorage).
     */
    public function impresion()
    {
        return view('configuracion.impresion');
    }

    /**
     * Ajustes generales: nombre empresa, logo, contactos, sitio web.
     */
    public function ajustes()
    {
        $ajustes = AjustesGenerales::obtener();
        if (!$ajustes) {
            AjustesGenerales::create([
                'nombre_empresa' => config('app.name'),
                'telefono' => null,
                'email' => null,
                'direccion' => null,
                'sitio_web' => null,
            ]);
            $ajustes = AjustesGenerales::obtener();
        }
        return view('configuracion.ajustes', compact('ajustes'));
    }

    public function storeAjustes(Request $request)
    {
        $validated = $request->validate([
            'nombre_empresa' => ['nullable', 'string', 'max:200'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'sitio_web' => ['nullable', 'string', 'max:255'],
        ]);

        $ajustes = AjustesGenerales::obtener();
        if (!$ajustes) {
            $ajustes = AjustesGenerales::create([
                'nombre_empresa' => $validated['nombre_empresa'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'email' => $validated['email'] ?? null,
                'direccion' => $validated['direccion'] ?? null,
                'sitio_web' => $validated['sitio_web'] ?? null,
            ]);
        }

        $data = [
            'nombre_empresa' => $validated['nombre_empresa'] ?? null,
            'telefono' => $validated['telefono'] ?? null,
            'email' => $validated['email'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'sitio_web' => $validated['sitio_web'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            if ($ajustes->logo) {
                Storage::disk('public')->delete($ajustes->logo);
            }
            $data['logo'] = $request->file('logo')->store('logo', 'public');
        }

        $ajustes->update($data);

        return redirect()->route('configuracion.ajustes')->with('success', 'Ajustes guardados correctamente.');
    }

    /**
     * Configuración de facturación interna, servicios y notificaciones.
     */
    public function facturacion()
    {
        $params = [
            'dia_creacion_factura_automatica' => FacturacionParametro::diaCreacionFacturaAutomatica(),
            'dia_fecha_cobro' => FacturacionParametro::diaFechaCobro(),
            'dia_vencimiento' => FacturacionParametro::diaVencimiento(),
            'dia_corte' => FacturacionParametro::diaCorte(),
            'hora_corte_automatico' => FacturacionParametro::horaCorteAutomatico(),
            'notificacion_tipo_plataforma' => FacturacionParametro::notificacionTipoPlataforma(),
            'notificacion_dias_antes' => FacturacionParametro::notificacionDiasAntes(),
        ];
        return view('configuracion.facturacion', compact('params'));
    }

    public function storeFacturacion(Request $request)
    {
        $validated = $request->validate([
            'dia_creacion_factura_automatica' => ['required', 'integer', 'min:1', 'max:31'],
            'dia_fecha_cobro' => ['required', 'integer', 'min:1', 'max:31'],
            'dia_vencimiento' => ['required', 'integer', 'min:1', 'max:31'],
            'dia_corte' => ['required', 'integer', 'min:1', 'max:31'],
            'hora_corte_automatico' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'notificacion_tipo_plataforma' => ['required', 'in:web,email,ambas'],
            'notificacion_dias_antes' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        FacturacionParametro::establecer('dia_creacion_factura_automatica', $validated['dia_creacion_factura_automatica'], 'Día del mes para creación automática de facturas internas');
        FacturacionParametro::establecer('dia_fecha_cobro', $validated['dia_fecha_cobro'], 'Día del mes para fecha de cobro');
        FacturacionParametro::establecer('dia_vencimiento', $validated['dia_vencimiento'], 'Día del mes de vencimiento de la factura interna');
        FacturacionParametro::establecer('dia_corte', $validated['dia_corte'], 'Día del mes para ejecutar corte automático');
        FacturacionParametro::establecer('hora_corte_automatico', $validated['hora_corte_automatico'], 'Hora para ejecutar corte automático (HH:MM)');
        FacturacionParametro::establecer('notificacion_tipo_plataforma', $validated['notificacion_tipo_plataforma'], 'Tipo de plataforma: web, email, ambas');
        FacturacionParametro::establecer('notificacion_dias_antes', $validated['notificacion_dias_antes'], 'Días antes del vencimiento para enviar recordatorio');

        return redirect()->route('configuracion.facturacion')->with('success', 'Configuración guardada correctamente.');
    }
}
