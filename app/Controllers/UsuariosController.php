<?php
namespace App\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Controller;
use App\Core\TenantContext;
use App\Models\Role;
use App\Models\User;

/**
 * CRUD de usuarios de exemplo, demonstrando o RBAC do framework
 * (roles vindas do banco, sem nada hardcoded) e o uso opcional de
 * multi-tenant (App\Core\TenantContext). Nenhuma regra de negocio
 * aqui — e um ponto de partida para voce adaptar por projeto.
 */
class UsuariosController extends Controller {
    public function index(): void {
        $userModel = new User();
        $usuarios  = TenantContext::isSet()
            ? $userModel->findByTenant(TenantContext::id())
            : $userModel->all();

        $this->view('usuarios/index', [
            'title'    => 'Usuarios',
            'usuarios' => $usuarios,
        ]);
    }

    public function create(): void {
        $roles = (new Role())->all();

        $this->view('usuarios/form', [
            'title'   => 'Novo usuario',
            'usuario' => null,
            'roles'   => $roles,
        ]);
    }

    public function store(): void {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId   = (int) ($_POST['role_id'] ?? 0);

        if (!$name || !$email || !$password || !$roleId) {
            $this->redirect('/usuarios/create?erro=campos_obrigatorios');
            return;
        }

        $userModel = new User();

        if ($userModel->findByEmail($email)) {
            $this->redirect('/usuarios/create?erro=email_duplicado');
            return;
        }

        $userId = $userModel->create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
            'role_id'  => $roleId,
        ]);

        if (TenantContext::isSet()) {
            $userModel->attachToTenant($userId, TenantContext::id(), $roleId);
        }

        AuditLogger::log('create', 'user', $userId, ['email' => $email]);

        $this->redirect('/usuarios?sucesso=criado');
    }

    public function edit(int $id): void {
        $userModel = new User();
        $usuario   = $userModel->findById($id);

        if (!$usuario) {
            $this->redirect('/usuarios?erro=nao_encontrado');
            return;
        }

        $this->view('usuarios/form', [
            'title'   => 'Editar usuario',
            'usuario' => $usuario,
            'roles'   => (new Role())->all(),
        ]);
    }

    public function update(int $id): void {
        $name   = trim($_POST['name'] ?? '');
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (!$name || !$roleId) {
            $this->redirect("/usuarios/{$id}/edit?erro=campos_obrigatorios");
            return;
        }

        (new User())->update($id, [
            'name'    => $name,
            'role_id' => $roleId,
            'status'  => $status,
        ]);

        AuditLogger::log('update', 'user', $id);

        $this->redirect('/usuarios?sucesso=atualizado');
    }

    public function toggleStatus(int $id): void {
        (new User())->toggleStatus($id);
        AuditLogger::log('toggle_status', 'user', $id);
        $this->redirect('/usuarios');
    }

    public function remover(int $id): void {
        $success = (new User())->delete($id);
        AuditLogger::log('delete', 'user', $id);
        $this->json(['success' => $success]);
    }
}
