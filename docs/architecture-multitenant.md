# Arquitetura SaaS E-commerce Multi-Tenant

## Objetivo

O sistema é uma plataforma de gestão e-commerce aplicável a múltiplas empresas, lojas, marcas e canais de venda. Pizza Catedral é apenas um possível consumidor da API; não existe regra de negócio, tabela ou controller dedicado à marca.

> **Princípio de fonte única:** o ERP é a fonte oficial dos dados operacionais; sites, marketplaces, aplicativos, redes sociais e outros canais são interfaces comerciais e integrações.

## Fluxo de dados

```mermaid
flowchart LR
    C[Site / App / Marketplace] -->|API Key + HMAC| A[/API v1/]
    A --> T[TenantContext]
    T --> S[Services de domínio]
    S --> D[(MySQL / MariaDB)]
    S --> O[Outbox de eventos]
    O --> I[Adaptadores de integração]
    I --> C
    W[Webhook externo] --> H[/webhooks/{tenant}/{provider}/{event}]
    H --> V[Validação HMAC + idempotência]
    V --> S
```

## Isolamento por tenant

Todas as tabelas de negócio possuem `tenant_id`. Toda consulta de serviço recebe o tenant do `TenantContext::requireId()` e todos os `INSERT`, `UPDATE` e `DELETE` usam parâmetros preparados. A API resolve o tenant pela credencial de cliente; webhooks resolvem pelo slug da empresa e validam o segredo configurado para o provedor.

A sessão administrativa carrega o tenant selecionado pelo vínculo `user_tenants`. O papel e as permissões são carregados da role associada àquele vínculo. Um usuário não pode selecionar ou consultar uma empresa à qual não esteja relacionado.

## Limites de responsabilidade

| Camada | Responsabilidade |
|---|---|
| `routes/web.php` | Contrato de URLs e versão da API. |
| `ApiAuth` | API Key, Bearer, segredo, HMAC e resolução do tenant. |
| Controllers | Validar formato de entrada, autorizar e delegar. |
| Services | Regras de negócio, transações, idempotência e eventos. |
| Banco | Integridade referencial, índices e persistência tenant-aware. |
| Outbox | Entrega eventual de eventos para canais externos com retry. |
| Integrações | Adaptar payloads e autenticação de provedores sem contaminar o domínio. |

## Modelo de integração

Cada tenant pode ter vários `api_clients` e `integration_connections`. Um cliente pode representar um site próprio, um aplicativo, um marketplace ou um parceiro. Uma conexão representa a configuração de um provedor, como um gateway de pagamento, um sistema de logística ou uma plataforma de marketing.

Os segredos de conexões são cifrados com `APP_SECRET`. Logs registram método, endpoint, status, identificador externo, payload sanitizado e erro, mas nunca credenciais. Eventos de domínio entram em `outbox_events`; um worker envia o evento, aplica backoff exponencial e limita as tentativas.

## Extensão para novos canais

Um novo canal não precisa alterar `OrderService`. Ele deve receber um `api_client`, consumir o catálogo em `/api/v1/products`, `/api/v1/categories`, `/api/v1/addons`, `/api/v1/combos` e `/api/v1/delivery-areas`, enviar clientes e pedidos com `external_order_id` ou `Idempotency-Key`, e receber mudanças de status por webhook configurado.

A integração específica deve conter somente o mapeamento de payload, a autenticação do provedor e os endpoints do canal. O cálculo de preço, cupom, taxa, disponibilidade, status e auditoria permanece no ERP.

## Ambientes

A aplicação distingue `development`, `staging` e `production` por `APP_ENV`. Cada ambiente deve ter um `.env` próprio, banco próprio e segredos próprios. Nunca copie credenciais de produção para desenvolvimento.

## Operação

O instalador executa as migrações em `database/migrations` e as seeds em `database/seeds`. Depois do primeiro uso, altere a senha do usuário inicial, crie um cliente de API para cada canal e apague os scripts auxiliares expostos no document root.
