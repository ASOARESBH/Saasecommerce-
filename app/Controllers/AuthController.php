<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class AuthController extends Controller {

    public function showLogin(): void {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        if (Auth::check()) {
            $this->redirect('/dashboard');
        }

        $this->view('auth/login', ['title' => 'Login'], 'auth');
    }

    public function login(): void {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $this->view('auth/login', [
                'title' => 'Login',
                'error' => 'Preencha todos os campos.',
            ], 'auth');
            return;
        }

        if (!Auth::login($email, $password)) {
            $this->view('auth/login', [
                'title' => 'Login',
                'error' => 'E-mail ou senha incorretos.',
            ], 'auth');
            return;
        }

        if (Auth::isSuperAdmin()) {
            $this->redirect('/dashboard');
        }

        // Multi-tenant: se o usuario pertence a mais de uma organizacao, pede pra escolher
        $tenants = Auth::userTenants();

        if (count($tenants) > 1) {
            $this->redirect('/selecionar-empresa');
        }

        $this->redirect('/dashboard');
    }

    public function logout(): void {
        Auth::logout();
        $this->redirect('/login');
    }

    public function selectTenant(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $tenants = Auth::userTenants();

        if (empty($tenants)) {
            $this->redirect('/dashboard');
        }

        $this->view('auth/select_tenant', [
            'title'   => 'Selecionar organizacao',
            'tenants' => $tenants,
        ], 'auth');
    }

    public function setTenant(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $allowed  = array_column(Auth::userTenants(), 'tenant_id');

        if (!$tenantId || !in_array($tenantId, $allowed)) {
            $this->redirect('/selecionar-empresa');
        }

        Auth::setTenant($tenantId);
        $this->redirect('/dashboard');
    }
}
