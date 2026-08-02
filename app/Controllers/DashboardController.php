<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Controller de exemplo: tela inicial pos-login. Substitua o conteudo
 * de app/Views/dashboard/index.php pelas telas reais do seu projeto.
 */
class DashboardController extends Controller {
    public function index(): void {
        $pdo = Database::getInstance();

        $totalUsuarios = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

        $this->view('dashboard/index', [
            'title'         => 'Dashboard',
            'usuario'       => Auth::user(),
            'totalUsuarios' => $totalUsuarios,
        ]);
    }
}
