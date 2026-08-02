# Template Base de Projeto (PHP Puro MVC)

Este repositório é o ponto de partida padrão para novos projetos desenvolvidos em PHP puro, seguindo a arquitetura MVC. Ele foi projetado para ser escalável, de fácil manutenção e pronto para implantação em ambientes de hospedagem compartilhada como o HostGator.

## 🚀 Como Iniciar um Novo Projeto a Partir Deste Template

1. **Clone este repositório** para a pasta do novo projeto:
   ```bash
   git clone git@github.com:ASOARESBH/phppadrao.git nome-do-novo-projeto
   cd nome-do-novo-projeto
   ```

2. **Remova o histórico do Git** do template e inicie um novo repositório:
   ```bash
   rm -rf .git
   git init
   git add .
   git commit -m "Commit inicial a partir do template phppadrao"
   ```

3. **Crie o novo repositório no GitHub** e adicione a origem:
   ```bash
   git remote add origin git@github.com:ASOARESBH/nome-do-novo-projeto.git
   git push -u origin master
   ```

4. **Configure o Ambiente**:
   - Copie `.env.example` para `.env`
   - Preencha as variáveis de ambiente, especialmente `APP_NAME`, `APP_URL`, `APP_SECRET` e os dados de conexão com o banco de dados (`DB_*`).
   - *Nota: Gere um `APP_SECRET` forte de 32 caracteres.*

5. **Inicie o Banco de Dados**:
   - No HostGator, crie o banco de dados via cPanel.
   - Você pode importar os arquivos em `database/migrations/` e `database/seeds/` via phpMyAdmin, ou acessar `https://seu-dominio.com.br/_instalar.php?key=SEU_APP_SECRET` no navegador para instalar automaticamente.

## 🏗️ Arquitetura e Padrões

* **Frontend**: HTML, CSS e JavaScript puro (Vanilla JS). Sem frameworks como React ou Angular.
* **Backend**: PHP 8.x puro, arquitetura MVC (Model-View-Controller).
* **Rotas**: Controladas via `routes/web.php` e `app/Core/Router.php`.
* **Segurança**: Autenticação nativa, RBAC (Role-Based Access Control) via banco de dados, middlewares para CSRF e permissões.
* **Sessão**: Gerenciada em `storage/sessions` para evitar problemas em hospedagem compartilhada.
* **Multi-tenant**: Suporte opcional integrado.

## 📂 Estrutura de Diretórios Principal

* `app/`: Lógica da aplicação (Controllers, Models, Core, Middlewares).
* `config/`: Configurações gerais (banco de dados).
* `database/`: Migrations e seeds SQL.
* `public/`: Document Root (index.php, assets, scripts de instalação).
* `routes/`: Definição de rotas da aplicação.
* `storage/`: Arquivos gerados (logs, sessões, uploads).

## 🚀 Deploy (HostGator)

1. Empacote o projeto em um arquivo `.zip`.
2. Faça o upload via Gerenciador de Arquivos do cPanel para o diretório raiz do domínio (ex: `public_html`).
3. Extraia o `.zip`. Certifique-se de que o arquivo `.htaccess` na raiz também foi extraído.
4. Configure o `.env` no servidor.
5. Garanta que o diretório `storage/` e seus subdiretórios tenham permissão `755`.

Para mais detalhes, consulte o arquivo `DEPLOY_HOSTGATOR.md`.
