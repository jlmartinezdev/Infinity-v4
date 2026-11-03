<?php

namespace App\Http\Controllers;

use App\Services\Sistema\DiscoService;
use Illuminate\View\View;

class DiscoController extends Controller
{
    public function index(DiscoService $disco): View
    {
        return view('sistema.disco', [
            'estado' => $disco->estado(),
        ]);
    }
}
