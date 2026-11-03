<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\Soporte\InformeAccesoCpe;
use Illuminate\View\View;

class CpeAccesoController extends Controller
{
    public function index(): View
    {
        return view('whatsapp.accesos', [
            'waTab' => 'accesos',
            'fallos' => InformeAccesoCpe::recientes(150),
        ]);
    }
}
