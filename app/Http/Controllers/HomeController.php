<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Tela 02: dashboard do site. Por enquanto só a casca (topbar e boas-vindas); o conteúdo entra na fase 2.
 */
class HomeController extends Controller
{
    public function index(): View
    {
        return view('site.pages.home');
    }
}
