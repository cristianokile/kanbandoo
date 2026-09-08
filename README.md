# KanbanDoo

Um gerenciador ágil de tarefas em formato Kanban focado em produtividade e organização por cliente/empresa.

> **Status:** MVP em validação (staging). O endurecimento de segurança e o processo de instalação
> ainda serão revistos antes de qualquer uso em produção.

## 🚀 Funcionalidades

### Quadro
- Colunas configuráveis (*Menções*, *A Fazer*, *Em Andamento*, *Revisão / Aguardando*, *Concluído*).
- Arrastar e soltar com reordenação persistida — a posição em que o card é solto é a posição que ele mantém.
- Se o salvamento falhar, o quadro é restaurado em vez de mostrar um estado que não existe no servidor.
- **Limite de WIP por coluna** com aviso visual ao ultrapassar.
- **Recolher coluna** e **agrupar por cliente**, com a preferência lembrada no navegador.
- **Minhas tarefas**: filtra em um clique o que está atribuído a você.
- Métricas do topo (em aberto, hoje, atrasadas, concluídas hoje) recalculadas a cada atualização.
- Tarefas concluídas há mais de 14 dias saem do quadro automaticamente (sem serem apagadas) e podem
  ser arquivadas manualmente pelo menu da coluna.
- O quadro se atualiza sozinho a cada 30 s quando a aba está visível e nada está aberto ou sendo arrastado.

### Card
- Linha superior discreta: ponto colorido de prioridade + nome do cliente em cinza, com o menu de ações à direita. O título vem abaixo, como o único elemento em destaque.
- Etiquetas, prazo com tom de alerta (atrasado / hoje / próximo), progresso do checklist (3/7) e nº de comentários.
- Ações em um menu **⋮** com rótulos — nada de ícones sem legenda.
- Cronômetro por tarefa; iniciar um cronômetro pausa o anterior, para o tempo não contar em duplicidade.
- **Tarefa bomba**: borda vermelha com sombra pulsante, fixada no topo da coluna e **imóvel** até ser
  resolvida — o arrastar é desativado no card (`filter` do SortableJS), o menu não oferece "Mover para"
  e `Ctrl`+setas avisa em vez de mover. A trava também vale no servidor, que recusa a mudança de coluna.
  Concluir continua permitido: concluir é resolver. Para movê-la sem concluir, desmarque a bomba.

### Tarefa
- Subtarefas com barra de progresso, comentários, diário de execução (resumos) e perfil do cliente.
- Atalhos úteis do cliente (site, BM, MCC, redes sociais, pastas) salvos no cadastro da empresa.

### Etiquetas
- Etiquetas livres por tarefa (até 8), com sugestão do que já existe no quadro.
- Aparecem no card com cor estável por etiqueta; clicar em uma filtra o quadro por ela.
- Filtro dedicado na barra de busca, com a contagem de uso de cada etiqueta.

### Aparência (liquid glass)
- Superfícies em vidro: barra superior, colunas, cards, menus, avisos e modais.
- A receita segue os componentes **Liquid Glass do David UI** (`bg-white/2.5`, borda clara,
  `backdrop-blur` e realce especular no topo), implementada como uma primitiva única `.kd-glass`
  no nosso sistema de tokens — um lugar só para ajustar o efeito em toda a interface.
- Um fundo ambiente fixo dá ao vidro algo para refratar.
- **Botão de transparência** na barra superior liga e desliga o efeito. Sem escolha explícita,
  o sistema segue `prefers-reduced-transparency` do sistema operacional; a escolha do usuário vence.
- O desfoque é suspenso enquanto um card está sendo arrastado, para o quadro não perder fluidez.

Para calibrar o efeito, mexa nos tokens `--glass-tint`, `--glass-blur` e `--glass-border`
em `assets/css/styles.css` — nada mais precisa ser tocado.

### Uso no dia a dia
- **Desfazer** em ações destrutivas (excluir, concluir, mover, duplicar) — sem caixas de confirmação.
- Filtros refletidos na URL: o link pode ser compartilhado e sobrevive ao recarregamento.
- Link direto para uma tarefa: `/tarefas/123`.
- Tema claro/escuro por tokens de cor, salvo no perfil (acompanha você em qualquer máquina).

### Atalhos de teclado
| Atalho | Ação |
| --- | --- |
| `N` | Nova tarefa |
| `/` | Focar a busca |
| `Enter` / `Espaço` | Abrir o card em foco |
| `E` | Editar o card em foco |
| `Ctrl` + `←` / `→` | Mover o card em foco entre colunas |
| `Esc` | Fechar modal ou menu |

Todo o quadro é operável sem mouse, incluindo mover cards entre colunas.

### Celular
Abaixo de 768 px o quadro vira **uma coluna por vez**, com um seletor de colunas no topo.

---

## 🧭 Rotas

Toda requisição passa por `index.php`, que consulta `routes.php` e entrega ao módulo
correspondente. Acrescentar uma tela é acrescentar uma linha em `routes.php`.

| URL | Módulo | Acesso |
| --- | --- | --- |
| `/` e `/tarefas` | `app/pages/tarefas.php` | autenticado |
| `/tarefas/{id}` | idem, abrindo a tarefa | autenticado |
| `/clientes` | `app/pages/clientes.php` | autenticado |
| `/equipe` | `app/pages/equipe.php` | administrador |
| `/perfil` | `app/pages/perfil.php` | autenticado |
| `/entrar` / `/sair` | `app/pages/login.php` / `logout.php` | público / autenticado |
| `/api/tarefas` | `app/api/tarefas.php` | autenticado |
| `/api/preferencias` | `app/api/preferencias.php` | autenticado |

Os endereços antigos (`kanban.php`, `clientes.php`, `usuarios.php`, `login.php`...)
respondem com redirecionamento 301 para a rota nova, então links salvos continuam funcionando.

Cada módulo não sabe por qual URL foi acessado: use `url('/clientes')` no PHP e
`KD.url('/api/tarefas')` no JavaScript, e o app funciona igual na raiz do domínio
ou dentro de uma subpasta.

---

## 💻 Como Rodar Localmente

### Pré-requisitos
- PHP 8.0+ com extensão `pdo_sqlite` (ou `pdo_mysql`)
- SQLite (padrão) ou MySQL 5.7+ / MariaDB 10+

### Passo a Passo

1. **Configuração**:
   - O padrão é SQLite em `storage/database.sqlite` — nada a configurar.
   - Para MySQL ou outro fuso horário, crie/edite `config/config.local.php`
     (`DB_DRIVER`, `DB_HOST`, `DB_NAME`, `APP_TIMEZONE`, ...).

2. **Servidor**:
   ```bash
   php -S localhost:8000 router.php
   ```
   O `router.php` faz o servidor embutido reproduzir o mesmo comportamento do
   `.htaccess` no Apache. Ou use `iniciar.bat` / `iniciar.ps1` no Windows.

3. **Primeira instalação**:
   - Acesse `http://localhost:8000/install/install.php` e clique em **Instalar e Criar Banco**.
   - Em bases já existentes, as alterações de schema são aplicadas automaticamente
     por `includes/migrations.php` (controladas na tabela `schema_migrations`, sem perda de dados).

4. **Login Padrão**:
   - **Usuário**: `admin`
   - **Senha**: `admin123`

---

## 🗂️ Estrutura

```
index.php       ponto de entrada único
routes.php      mapa de rotas (uma linha por tela)
router.php      roteamento do servidor embutido do PHP
.htaccess       reescrita de URLs no Apache
app/
  bootstrap.php   contexto da aplicação: url(), asset(), view_header()
  Router.php      roteador
  pages/          uma tela por arquivo (tarefas, clientes, equipe, perfil, login, 404)
  api/            endpoints (tarefas, preferencias)
assets/css/     styles.css — design system em tokens (tema claro = troca de variáveis)
                (CSS e JS saem com ?v=<mtime> via asset(), então alterações de estilo
                 nunca ficam presas no cache do navegador)
assets/js/      kd-core.js (utilitários, avisos, modais, tema)
                kd-board.js (quadro, filtros, arrastar e soltar, teclado)
                kd-task.js (modais de tarefa)
includes/       auth, db, migrations, functions, header, navbar, footer
includes/partials/  modais globais
install/        instalador e schemas
storage/        banco SQLite e uploads
```

### Como acrescentar uma tela

1. Crie `app/pages/relatorios.php` (comece com `require_once dirname(__DIR__) . '/bootstrap.php';`).
2. Adicione a rota em `routes.php`: `$router->add('/relatorios', 'pages/relatorios.php');`.
3. Se precisar de um endpoint, crie `app/api/relatorios.php` e registre `/api/relatorios`.

Nada mais do sistema precisa ser tocado.
