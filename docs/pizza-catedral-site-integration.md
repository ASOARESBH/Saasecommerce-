# Integração de site — exemplo Pizza Catedral

Este documento descreve como o site `pizzacatedral.com.br` pode consumir a API do SaaS E-commerce. A integração é um exemplo de canal e não altera o núcleo multi-tenant. O mesmo roteiro pode ser aplicado a qualquer outro site, aplicativo ou marketplace.

## Princípio

O site não acessa o banco do ERP. Ele consome exclusivamente a API REST, usando um cliente de API vinculado ao tenant correto. O site exibe o catálogo e coleta o pedido; o ERP calcula preço, desconto, entrega, pagamento pendente, status e auditoria.

## Credenciais

Crie um cliente em `POST /api/v1/integrations/clients` com os escopos `catalog:read`, `settings:read`, `orders:write`, `orders:read`, `customers:write`, `delivery:read` e `coupons:read`. O `key` e o `secret` são exibidos apenas uma vez.

Em produção, envie:

```http
X-API-Key: sk_...
X-API-Secret: ss_...
Content-Type: application/json
Idempotency-Key: site-pizzacatedral-<identificador-do-checkout>
```

Quando a assinatura for usada, envie também `X-Timestamp` em Unix time e `X-Signature` com:

```text
hex(HMAC-SHA256(X-Timestamp + "\n" + corpo-json, X-API-Secret))
```

## Catálogo

```http
GET /api/v1/menu
GET /api/v1/products
GET /api/v1/categories
GET /api/v1/addons
GET /api/v1/combos
GET /api/v1/settings
GET /api/v1/delivery-areas
```

O site deve armazenar apenas cache de apresentação com TTL curto. Disponibilidade, preços e regras comerciais continuam no ERP.

## Cliente e entrega

```http
POST /api/v1/customers
POST /api/v1/delivery/check
POST /api/v1/coupons/validate
```

A validação de entrega deve ocorrer novamente no momento da criação do pedido. O resultado exibido no checkout não deve ser tratado como autorização final, pois a configuração pode ter mudado.

## Pedido

```http
POST /api/v1/orders
```

Exemplo de corpo:

```json
{
  "external_order_id": "pizzacatedral-checkout-12345",
  "source": "pizzacatedral_site",
  "customer": {
    "name": "Cliente Exemplo",
    "phone": "31999999999",
    "email": "cliente@example.com",
    "address": {
      "postal_code": "30110000",
      "street": "Rua Exemplo",
      "number": "100",
      "neighborhood": "Centro",
      "city": "Belo Horizonte",
      "state": "MG"
    }
  },
  "items": [
    {"product_id": 10, "size_id": 2, "quantity": 1, "addons": [{"addon_id": 7, "quantity": 1}]}
  ],
  "payment_method": "pix",
  "utm": {"source": "google", "medium": "cpc", "campaign": "delivery"}
}
```

Se o mesmo `external_order_id` ou `Idempotency-Key` for enviado novamente, a API retorna o pedido existente e não cria duplicidade.

## Status e retorno ao site

O site pode consultar `GET /api/v1/orders/{id}` ou receber eventos em um endpoint configurado como conexão do tenant. Os eventos são `order.received`, `order.confirmed`, `order.preparing`, `order.ready`, `order.out_for_delivery`, `order.delivered` e `order.cancelled`.

## Checklist de homologação

| Verificação | Resultado esperado |
|---|---|
| Credencial de outro tenant | Resposta 401/403; nenhum dado exposto |
| Pedido repetido | Mesmo pedido interno, sem duplicidade |
| Produto inativo | Pedido recusado com 422 |
| Cupom inválido | Resposta de validação sem desconto |
| CEP fora da área | Resposta de entrega indisponível |
| Assinatura alterada | Webhook recusado com 401 |
| Falha no callback | Evento permanece na outbox e recebe retry |
