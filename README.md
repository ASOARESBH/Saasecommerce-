# BASEAPP

Projeto base em PHP puro (sem framework de terceiros) para servir como ponto de
partida de qualquer novo projeto. Foi extraído da arquitetura do VOXEL B.I.,
removendo **toda** a regra de negócio (radiologia, PACS, exames, financeiro,
SLA, planos etc.) e mantendo apenas a estrutura, a linguagem e os mecanismos:
MVC próprio, RBAC orientado a banco, multi-tenant opcional, middlewares,
auditoria, sessão e autoloading compatível com hospedagem compartilhada.

Use este repositório como "template inicial" para novos projetos: copie a
pasta, troque o nome da aplicação e comece a adicionar as telas/Controllers
do domínio específico de cada projeto.

## Por que um framework próprio (e não Laravel/Symfony)?

Este esqueleto prioriza rodar em qualquer hospedagem PHP simples (inclusive
compartilhada, sem SSH root e sem conseguir rodar `composer install` no
servidor), com zero dependências obrigatórias em runtime. O autoload é um
`spl_autoload_register` PSR-4 manual e o `.env` é lido por uma função própria
— nada disso depende do Composer estar disponível em produção.

## Estrutura

```
app/
  Controllers/     Controllers HTTP (fino: valida input, chama Model, escolhe View)
  Core/             Núcleo do framework — não deve ganhar regra de negócio
    Router.php          Roteador com suporte a grupos, prefixo e middleware por rota
    Controller.php       Classe base (view/json/redirect/csrfToken)
    Model.php             Classe base (PDO + helpers de escopo multi-tenant)
    Database.php          Conexão PDO singleton
    Auth.php               Login/logout/sessão/permissões do usuário logado
    Permission.php         RBAC: consulta roles/permissions no banco
    Middleware.php         Classe base + executor de middlewares
    TenantContext.php      Contexto de multi-tenant (opcional)
    View.php                Renderização de views com layout (header/footer)
    Logger.php               Log em arquivo (storage/logs/app.log)
    Audit/AuditLogger.php     Log de auditoria em banco (audit_logs)
  Middlewares/       Auth, Csrf, Permission, SuperAdmin, SessionTimeout, Tenant
  Models/             User, Role, Tenant (só estrutura, sem entidades de negócio)
  Views/              Views PHP simples (sem template engine), layout com Bootstrap 5
config/               config/database.php
database/
  migrations/         Schema base (roles, permissions, users, tenants, audit_logs)
  seeds/               Superadmin inicial + permissões mínimas
public/               Document root (index.php, assets)
routes/web.php         Todas as rotas da aplicação
storage/                logs, sessões, uploads
```

## RBAC (controle de acesso)

Não existe nenhuma permissão de negócio "hardcoded" em PHP. Tudo vem do banco:

- `roles` — ex: `superadmin`, `admin`, `user` (crie quantas quiser por projeto)
- `permissions` — ex: `manage_users`, `view_dashboard` (crie as do seu domínio)
- `role_permissions` — associação N:N entre role e permissão

A role com slug `superadmin` sempre tem acesso total (curinga), tanto em
`Auth::can()` quanto em `Permission::can()`. Para proteger uma rota:

```php
Router::get('/algo', 'AlgoController@index', [[PermissionMiddleware::class, 'ver_algo']]);
```

Para checar dentro de um Controller/View: `Auth::can('ver_algo')`.

## Multi-tenant (opcional)

O framework já vem com suporte a multi-tenant (`tenants`, `user_tenants`,
`TenantContext`, `TenantMiddleware`), mas ele é **inerte por padrão**: se
você nunca associar um usuário a um tenant (tabela `user_tenants` vazia), o
`TenantMiddleware` simplesmente não bloqueia nada e a aplicação funciona
como single-tenant normal.

Se o seu projeto não vai precisar de multi-tenant, você pode remover:
`app/Core/TenantContext.php`, `app/Middlewares/TenantMiddleware.php`,
`app/Models/Tenant.php`, as tabelas `tenants`/`user_tenants` e as rotas
`/selecionar-empresa`.

## Como iniciar um novo projeto a partir deste template

1. Copie a pasta `baseapp` e renomeie para o novo projeto.
2. Ajuste `composer.json` (`name`, `description`).
3. Copie `.env.example` para `.env` e preencha `DB_*`, `APP_NAME`, `APP_URL`, `APP_SECRET`.
4. Crie o banco e rode, nesta ordem:
   - `database/migrations/0001_core_schema.sql`
   - `database/seeds/0001_admin_seed.sql`
   - (ou acesse `public/_instalar.php?key=SEU_APP_SECRET` para rodar os dois
     direto pelo navegador — veja `DEPLOY_HOSTGATOR.md`)
5. Suba o conteúdo para o servidor com o *document root* apontando para `public/`
   (ou use o `.htaccess` da raiz se o document root for a raiz do projeto,
   como em hospedagem compartilhada — passo a passo completo em `DEPLOY_HOSTGATOR.md`).
6. Acesse `/login` com `admin@example.com` / `Admin@123` e **troque a senha
   imediatamente** (crie uma tela de "alterar senha" ou atualize direto no banco).
7. Apague `public/_instalar.php` e `public/_diagnostico.php` do servidor.
8. Comece a adicionar os Controllers/Models/Views/migrations do domínio do
   novo projeto — o núcleo em `app/Core` não deveria precisar mudar.

## Hospedagem compartilhada (HostGator/cPanel)

Veja o passo a passo completo em [`DEPLOY_HOSTGATOR.md`](./DEPLOY_HOSTGATOR.md):
upload via Gerenciador de Arquivos, criação do banco, `.env`, permissões de
pasta, versão do PHP, e os dois scripts de apoio inclusos em `public/`:

- `_instalar.php` — roda migration + seed direto no navegador (sem precisar
  de phpMyAdmin), protegido pela sua `APP_SECRET`.
- `_diagnostico.php` — checa versão do PHP, extensões, permissões de pasta e
  conexão com o banco numa tela só, também protegido pela `APP_SECRET`.

Apague os dois do servidor depois de usar.

## Convenções

- Controllers ficam finos: validam entrada, chamam Model/Service, escolhem a View.
- Models estendem `App\Core\Model` e usam PDO com *prepared statements* (nunca
  concatenar SQL vindo do usuário).
- Toda rota protegida deve passar por `AuthMiddleware`; ações sensíveis por
  `PermissionMiddleware`; painéis globais por `SuperAdminMiddleware`.
- Toda ação de escrita relevante deve chamar `AuditLogger::log(...)`.
- Formulários POST devem incluir `<input type="hidden" name="_csrf_token" ...>`
  e as rotas de escrita devem registrar `CsrfMiddleware`.

## Próximos passos sugeridos (não incluídos de propósito)

Ficaram de fora por serem decisões específicas de cada projeto, não parte do
"esqueleto": fluxo de recuperação de senha, tela de administração de
roles/permissions, envio de e-mail, testes automatizados, exportação para
Excel/PDF, filas/jobs em background. Adicione-os conforme a necessidade de
cada novo projeto.
