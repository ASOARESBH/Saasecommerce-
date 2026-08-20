# SaaS E-commerce ERP Multi-Tenant

Plataforma em **PHP 8.x puro, MySQL/MariaDB, HTML, CSS e JavaScript vanilla** para gestão de múltiplas operações de e-commerce e delivery. O produto foi construído para que cada empresa tenha seu próprio tenant, catálogo, clientes, pedidos, regras comerciais, operação e integrações.

Pizza Catedral é somente um exemplo de canal. O núcleo não contém regras específicas de pizzaria ou de uma marca. A mesma API pode atender sites próprios, aplicativos, marketplaces, redes sociais, parceiros e futuros canais de venda.

> **Fonte única:** o ERP é a fonte oficial de catálogo, preços, disponibilidade, descontos, áreas de entrega, pedidos, pagamentos e status operacionais. Nenhum site acessa o banco diretamente.

## O que está implementado

| Domínio | Entrega |
|---|---|
| Multi-tenancy | `tenant_id` em todas as tabelas de negócio, `TenantContext`, seleção de empresa e isolamento obrigatório. |
| Segurança | RBAC por tenant, API Key/Bearer, segredo, HMAC, CSRF web, headers de segurança, rate limiting, auditoria e prepared statements. |
| Catálogo | Categorias, produtos, tamanhos, adicionais, combos, regras de preço, imagens, disponibilidade e ficha técnica. |
| Pedidos | Criação idempotente, itens, adicionais, cupons, taxas, pagamento, endereço, histórico e status operacional. |
| Clientes e CRM | Deduplicação por telefone/e-mail, endereços, histórico, ticket médio, recorrência e consentimentos. |
| Delivery | Áreas por CEP/cidade, taxa, pedido mínimo, raio, prazo, entregadores, atribuição e expedição. |
| Cozinha | Fila operacional por status e transições `received`, `preparing`, `ready`. |
| Estoque | Insumos, unidades, mínimo, custo médio, movimentações e estrutura para ficha técnica. |
| Financeiro | Pagamentos, transações, taxas, despesas, fechamento de caixa e relatórios agregados. |
| Marketing e fidelidade | Campanhas, UTM, origens, cupons, pontos, contas de fidelidade e movimentações. |
| Integrações | Clientes de API, conexões por provedor, webhooks HMAC, logs sanitizados, outbox e retry exponencial. |
| Operação | Dashboard, cozinha, expedição, pagamentos, estoque, finanças e relatórios por período. |
| Documentação | OpenAPI em `docs/openapi/openapi.yaml`, arquitetura em `docs/architecture-multitenant.md` e exemplo de integração Pizza Catedral. |

## Arquitetura

```text
Site / App / Marketplace
          ↓ API Key + HMAC
      /api/v1
          ↓
   Controllers versionados
          ↓
   Services de domínio
          ↓ tenant_id obrigatório
      MySQL/MariaDB
          ↓
     Outbox + retry
          ↓
   Webhooks para canais
```

O núcleo está separado em Controllers, Services, Core, Views, migrations e documentação. O front-end do painel usa JavaScript puro; não há React, Vue, Angular, jQuery, Bootstrap ou Tailwind como dependência de runtime.

## Estrutura principal

```text
app/
  Controllers/Api/V1/   API REST versionada e controllers operacionais
  Core/                 Router, Auth, TenantContext, ApiAuth, Crypto, RateLimiter
  Services/             Catálogo, pedidos, clientes, cupons, delivery, analytics e integrações
  Middlewares/          Sessão, CSRF, RBAC, timeout e tenant
  Views/                Login, seleção de tenant e painel operacional

database/
  migrations/           Schema base + domínio SaaS multi-tenant
  seeds/                Roles, permissões e tenant inicial

docs/
  architecture-multitenant.md
  openapi/openapi.yaml
  pizza-catedral-site-integration.md

public/
  index.php             Document root e dispatcher
  assets/               CSS e JavaScript vanilla
routes/web.php            Rotas web, API v1 e webhooks
storage/                  Logs, sessões e uploads
```

## Instalação

1. Copie `.env.example` para `.env` e configure `APP_ENV`, `APP_URL`, `APP_SECRET` e as variáveis `DB_*`. Use um segredo real com pelo menos 32 caracteres.
2. Crie um banco MySQL/MariaDB vazio. O sistema é compatível com MariaDB 10.6+ e MySQL 8+.
3. Execute em ordem todos os arquivos de `database/migrations` e depois todos os arquivos de `database/seeds`, ou use `public/_instalar.php?key=SEU_APP_SECRET` uma única vez.
4. Entre com `admin@example.com` / `Admin@123`, troque a senha e confirme o tenant `Minha Loja` criado pela seed.
5. Apague `public/_instalar.php` e `public/_diagnostico.php` do servidor depois da instalação.
6. Aponte o document root do Apache para `public/` sempre que possível.

## API v1

A API exige cliente de integração vinculado ao tenant. Crie um cliente pelo painel ou por:

```http
POST /api/v1/integrations/clients
Content-Type: application/json

{"name":"Meu site","scopes":["catalog:read","settings:read","orders:write","customers:write","delivery:read","coupons:read"]}
```

Use os valores retornados apenas no momento da criação:

```http
X-API-Key: sk_...
X-API-Secret: ss_...
Content-Type: application/json
Idempotency-Key: checkout-123
```

Endpoints principais:

| Método | Endpoint | Uso |
|---|---|---|
| GET | `/api/v1/products` | Catálogo paginado e filtrável. |
| GET | `/api/v1/categories` | Categorias ativas. |
| GET | `/api/v1/addons` | Adicionais e complementos. |
| GET | `/api/v1/combos` | Combos vigentes. |
| GET | `/api/v1/settings` | Configurações públicas do tenant. |
| POST | `/api/v1/customers` | Cria ou atualiza cliente. |
| POST | `/api/v1/delivery/check` | Taxa, mínimo e estimativa de entrega. |
| POST | `/api/v1/coupons/validate` | Validação de cupom. |
| POST | `/api/v1/orders` | Cria pedido idempotente. |
| GET | `/api/v1/orders/{id}` | Consulta pedido. |
| POST | `/api/v1/orders/{id}/cancel` | Cancela pedido. |
| POST | `/api/v1/orders/{id}/status` | Atualiza status operacional. |
| GET | `/api/v1/dashboard` | Indicadores operacionais. |

O contrato completo está em [`docs/openapi/openapi.yaml`](docs/openapi/openapi.yaml).

## Webhooks e sincronização

A entrada de eventos usa:

```text
POST /webhooks/{tenantSlug}/{provider}/{event}
```

O segredo do provedor é cifrado em `integration_connections`. O webhook valida assinatura HMAC, timestamp, identificador externo e duplicidade. Eventos emitidos pelo ERP entram em `outbox_events`; o worker `POST /api/v1/internal/outbox/process` aplica retry com backoff exponencial e grava `integration_logs` sem persistir credenciais.

## Testes e validações

O projeto mantém zero dependências obrigatórias em runtime e inclui `tests/smoke.php` para validar autoload e cifragem. Para verificar o código localmente:

```bash
php tests/smoke.php
find app public routes tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Deploy tradicional

Consulte [`DEPLOY_HOSTGATOR.md`](DEPLOY_HOSTGATOR.md) para upload em cPanel/HostGator, configuração do document root, `.env`, permissões e diagnóstico. Nunca publique `.env`, segredos de API ou dados reais no GitHub.
