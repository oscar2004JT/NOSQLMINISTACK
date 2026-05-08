<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class MercadoWebController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'userId' => '123',
            'featuredOrderId' => '555',
            'apiBaseUrl' => config('services.serverless.api_base_url'),
        ]);
    }
}
