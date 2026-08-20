<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\TenantContext;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('dashboard/index', [
            'title' => 'Visão geral',
            'usuario' => Auth::user(),
            'tenant' => TenantContext::get(),
        ]);
    }
}
