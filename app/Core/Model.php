<?php
namespace App\Core;

use PDO;

abstract class Model {
    protected PDO $pdo;
    protected string $table = '';

    /**
     * Defina como true nos Models que possuem coluna tenant_id, para que
     * tenantWhere()/tenantParam() apliquem o escopo automaticamente.
     * Em projetos sem multi-tenant, deixe sempre false (padrao).
     */
    protected bool $hasTenant = false;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    /**
     * Retorna clausula " AND tenant_id = :tenant_id" para uso em WHERE,
     * ou string vazia se o Model nao usa tenant ou nao ha tenant no contexto.
     */
    protected function tenantWhere(string $alias = ''): string {
        if (!$this->hasTenant || !TenantContext::isSet()) {
            return '';
        }
        $col = $alias ? "{$alias}.tenant_id" : 'tenant_id';
        return " AND {$col} = :tenant_id";
    }

    /**
     * Retorna array ['tenant_id' => X] para uso em execute().
     */
    protected function tenantParam(): array {
        return ($this->hasTenant && TenantContext::isSet())
            ? ['tenant_id' => TenantContext::id()]
            : [];
    }
}
