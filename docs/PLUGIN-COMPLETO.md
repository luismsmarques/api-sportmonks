# API Sportmonks — Documentação Completa do Plugin

Documento de referência com todas as funcionalidades, opções, APIs e possibilidades do plugin **API Sportmonks** para WordPress.

---

## 1. Visão Geral

| Item | Valor |
|------|--------|
| **Nome** | API Sportmonks |
| **Versão** | 1.0.0 |
| **Text Domain** | api-sportmonks |
| **Requisitos** | WordPress 5.0+, PHP 7.4+, cURL, JSON |
| **Autor** | Luis Marques (Atlas Invencível) |
| **Site** | [atlasinvencivel.pt](https://atlasinvencivel.pt/) |
| **Descrição** | Integra o WordPress com a API Sportmonks: sincroniza jogos, resultados, classificações, plantéis, lesões e transferências; CPT Jogo; shortcodes e componentes; painel de configuração, gestão de sync e explorador de dados. |

**Constantes definidas:**
- `APS_SMONKS_VERSION` — versão do plugin
- `APS_SMONKS_PLUGIN_DIR` — caminho da pasta do plugin
- `APS_SMONKS_PLUGIN_URL` — URL da pasta do plugin
- `APS_SMONKS_PLUGIN_BASENAME` — basename do plugin

---

## 2. Estrutura de Ficheiros

```
api-sportmonks/
├── api-sportmonks.php          # Bootstrap e classe principal
├── design-prompts-rules.md    # Regras de design Super Portistas
├── README.md
├── docs/
│   ├── theme-helpers.md       # Uso dos helpers no tema
│   └── PLUGIN-COMPLETO.md     # Este ficheiro
├── assets/
│   ├── css/
│   │   ├── components-admin.css
│   │   ├── components-frontend.css
│   │   ├── data-explorer.css
│   │   ├── settings.css
│   │   └── widgets.css
│   └── js/
│       ├── components-admin.js
│       ├── components-frontend.js
│       ├── data-explorer.js
│       ├── settings.js
│       └── widgets.js
└── includes/
    ├── class-api-client.php       # Cliente HTTP para API Sportmonks
    ├── class-components.php      # Componentes reutilizáveis + shortcode [aps_component]
    ├── class-cpt-jogo.php        # Custom Post Type "Jogo"
    ├── class-cron-handler.php    # Agendamento WP-Cron
    ├── class-data-explorer.php   # Interface para explorar dados da API
    ├── class-error-logger.php    # Registo de erros em BD
    ├── class-settings.php        # Páginas de configuração e Gestão de Sync
    ├── class-shortcodes.php      # Shortcodes clássicos (calendário, resultados, etc.)
    ├── class-sync-manager.php    # Sincronização de fixtures, squads, lesões, transferências
    ├── class-taxonomy-manager.php # Taxonomia aps_competicao + mapeamento equipas→Categories
    ├── class-theme-helpers.php   # Funções helper para temas (aps_*)
    └── class-shortcodes.php      # Shortcodes [aps_calendar], [aps_standings], etc.
```

---

## 3. Menu Admin (Sportmonks)

| Página | Slug | Capacidade | Descrição |
|--------|------|------------|-----------|
| Sportmonks (raiz) | `aps-sportmonks` | `manage_options` | Redireciona para Configurações |
| Configurações | `aps-sportmonks` | `manage_options` | API Token, equipas, frequência, cache, sync eliminados |
| Gestão de Sync | `aps-sync-manager` | `manage_options` | Última sync, métricas, filtros, lista de jogos, sync por datas |
| Erros do Plugin | `aps-error-log` | `manage_options` | Lista de erros, filtros, export CSV, limpar logs |
| Data Explorer | `aps-data-explorer` | `manage_options` | Testar endpoints da API e ver JSON |
| Componentes | `aps-components` | `manage_options` | Pré-visualizar componentes antes de usar no template |

---

## 4. Configurações (Options)

### 4.1 API e Sincronização

| Option | Tipo | Default | Descrição |
|--------|------|---------|-----------|
| `aps_smonks_api_token` | string | '' | Token da API Sportmonks |
| `aps_smonks_sync_frequency` | string | 'hourly' | Frequência do cron: `15min`, `30min`, `hourly`, `2hours`, `6hours`, `12hours`, `daily` |
| `aps_smonks_teams` | array | [] | Lista de equipas: `team_id`, `team_name` (por equipa). Mapeamento com Category é guardado em term_meta e no Taxonomy Manager |

### 4.2 Sincronização Avançada

| Option | Tipo | Default | Descrição |
|--------|------|---------|-----------|
| `aps_smonks_sync_squads` | int (0/1) | 1 | Sincronizar plantéis (cache) |
| `aps_smonks_sync_injuries` | int (0/1) | 1 | Sincronizar lesões (cache) |
| `aps_smonks_sync_transfers` | int (0/1) | 1 | Sincronizar transferências (cache) |
| `aps_smonks_sync_deleted` | int (0/1) | 1 | Enviar para o lixo jogos eliminados na API |
| `aps_smonks_sync_deleted_days` | int | 90 | Dias a verificar para jogos eliminados (1–365) |

### 4.3 Cache (TTL em segundos)

| Option | Tipo | Default | Descrição |
|--------|------|---------|-----------|
| `aps_smonks_cache_ttl_squads` | int | 21600 (6h) | TTL cache plantel |
| `aps_smonks_cache_ttl_injuries` | int | 1800 (30min) | TTL cache lesões |
| `aps_smonks_cache_ttl_transfers` | int | 21600 (6h) | TTL cache transferências |

### 4.4 Estado da Sincronização

| Option | Descrição |
|--------|-----------|
| `aps_smonks_last_sync_started` | Data/hora de início da última sync |
| `aps_smonks_last_sync` | Data/hora de fim da última sync |
| `aps_smonks_last_sync_results` | Array com contagens (success, error, updated, created, trashed, squads, injuries, transfers) |
| `aps_smonks_last_sync_metrics` | Métricas detalhadas (fixtures_total, fixtures_created, etc.) |

---

## 5. Custom Post Type: aps_jogo

- **Slug de rewrite:** `jogo`
- **Suportes:** title, editor, thumbnail, custom-fields
- **Taxonomias:** category, aps_competicao
- **Público:** sim; archive; show_in_rest

### 5.1 Meta Fields do CPT aps_jogo

| Meta Key | Descrição |
|----------|-----------|
| `_aps_match_id` | ID do jogo na API Sportmonks |
| `_aps_team_home_id` | ID equipa casa |
| `_aps_team_away_id` | ID equipa visitante |
| `_aps_team_home_name` | Nome equipa casa |
| `_aps_team_away_name` | Nome equipa visitante |
| `_aps_team_home_logo` | URL do logo equipa casa |
| `_aps_team_away_logo` | URL do logo equipa visitante |
| `_aps_league_id` | ID da liga |
| `_aps_venue_id` | ID do estádio |
| `_aps_venue_name` | Nome do estádio |
| `_aps_match_date` | Data/hora do jogo |
| `_aps_match_status` | Estado: NS, LIVE, HT, FT, CANC, POSTP |
| `_aps_score_home` | Golos equipa casa |
| `_aps_score_away` | Golos equipa visitante |
| `_aps_last_sync` | Timestamp da última sincronização |

### 5.2 Listagem Admin (Jogos)

- Colunas: título, equipas (casa vs visitante), data, estado, resultado, competição.
- Filtros: por equipa (meta home/away), por estado, por intervalo de datas.
- Ordenação: por data, estado.
- Ação em massa: “Atualizar dados da API” (bulk refresh).
- Na edição: meta box “Detalhes do Jogo”, meta box “Informação da API” (Match ID, última sync, botão “Atualizar dados da API”, link para Data Explorer).

---

## 6. Taxonomias

### 6.1 aps_competicao (Competições)

- **Slug de rewrite:** `competicao`
- **Objeto:** apenas `aps_jogo`
- **Hierárquica:** não
- **Meta:** guarda o League ID da Sportmonks (mapeamento liga ↔ termo).

### 6.2 Category (WordPress)

- Usada para **equipas**: cada equipa configurada pode ser mapeada a uma Category.
- **Term meta:** `aps_team_id` (ID da equipa Sportmonks).
- **Term meta:** `aps_team_logo` (URL do logo, preenchido ao guardar equipa nas Configurações).
- O Taxonomy Manager mantém o mapeamento interno team_id → category term_id.

---

## 7. Sincronização (Sync Manager)

### 7.1 Evento Cron

- **Hook:** `aps_sportmonks_sync_event`
- Agendado conforme `aps_smonks_sync_frequency`.

### 7.2 O que a sync faz

1. Para cada equipa em `aps_smonks_teams`: obtém fixtures (com filtros de datas adequados).
2. Cria ou atualiza posts `aps_jogo`; associa categorias (equipas) e termos `aps_competicao` (ligas).
3. Opcionalmente: sincroniza squads, injuries e transfers (para cache).
4. Se `aps_smonks_sync_deleted` estiver ativo: consulta jogos eliminados na API e envia os posts correspondentes para o lixo.

### 7.3 Ações AJAX (Admin)

| Action | Descrição |
|--------|-----------|
| `aps_manual_sync` | Sincronizar agora (todas as equipas) |
| `aps_manual_sync_range` | Sincronizar por intervalo de datas (parâmetros: start_date, end_date) |
| `aps_refresh_match` | Atualizar um único jogo por post_id (atualiza meta a partir da API) |

### 7.4 Página Gestão de Sync

- Mostra última sync, resultados e métricas.
- Filtros: equipa, data início/fim.
- Listagem paginada de jogos (aps_jogo) com link para editar.
- Botão “Sincronizar por datas” (sync range).
- Link para “Ver todos os erros” (Error Log).

---

## 8. API Client (Sportmonks v3 Football)

- **Base URL:** `https://api.sportmonks.com/v3/football`
- **Autenticação:** query param `api_token`
- **Cache:** transients (prefixo `aps_api_`); TTL padrão 300s para requests genéricos; squads/injuries/transfers usam TTL das opções.

### 8.1 Métodos do API Client

| Método | Descrição |
|--------|-----------|
| `request( $endpoint, $params, $includes, $use_cache )` | Request genérico GET |
| `get_team( $team_id, $includes, $use_cache )` | Equipa |
| `get_fixtures( $team_id, $params, $includes, $use_cache )` | Fixtures por equipa |
| `get_match( $match_id, $params, $includes, $use_cache )` | Um jogo (fixture) |
| `get_league( $league_id, $includes, $use_cache )` | Liga |
| `get_league_standings( $league_id, ... )` | Classificação |
| `get_league_top_scorers( $league_id, $params, $use_cache )` | Melhores marcadores |
| `get_head_to_head( $team1_id, $team2_id, $use_cache )` | Confronto direto |
| `get_all_fixtures( $params, $includes, $use_cache )` | Todos os fixtures (params) |
| `get_fixtures_by_date( $date, ... )` | Fixtures por data |
| `get_fixtures_by_date_range( $from, $to, ... )` | Fixtures por intervalo |
| `get_latest_updated_fixtures( $params, ... )` | Últimos atualizados |
| `get_livescores( $params, ... )` | Livescores |
| `get_inplay_livescores( $params, ... )` | Em jogo |
| `get_team_squad( $team_id, ... )` | Plantel |
| `get_extended_team_squad( $team_id, ... )` | Plantel alargado |
| `get_injuries( $params, ... )` | Lesões |
| `get_team_sidelined( $team_id, $use_cache )` | Indisponíveis (equipa) |
| `get_team_players( $team_id, $use_cache )` | Jogadores da equipa |
| `get_player_with_stats( $player_id, $use_cache )` | Jogador com estatísticas |
| `get_transfers( $params, ... )` | Transferências |
| `get_transfers_by_team( $team_id, $params, ... )` | Transferências por equipa |
| `get_player( $player_id, $includes, $use_cache )` | Jogador |

O cliente também permite limpar o cache de transients (método interno para apagar `_transient_aps_api_*`).

---

## 9. Theme Helpers (funções globais aps_*)

Todas podem devolver `WP_Error` em caso de falha. Os dados vêm normalmente em formato de resposta API (ex.: `$response['data']`).

### 9.1 Jogos / Fixtures

| Função | Descrição |
|--------|-----------|
| `aps_get_match_from_post( $post_id )` | Dados básicos do jogo a partir do post (meta); inclui `venue_name` e `venue_display` — quando o estádio está vazio, "O jogo será jogado em casa" ou "O jogo será jogado fora" consoante `team_home_id` vs equipa principal (ex.: Porto) |
| `aps_get_match_data( $match_id, $use_cache )` | Fixture da API (includes base) |
| `aps_get_full_match_details( $match_id )` | Jogo com participants, scores, state, events, statistics, events.type |
| `aps_get_team_fixtures( $team_id, $params, $includes, $use_cache )` | Fixtures da equipa (params: filters, order, per_page, etc.) |
| `aps_get_fixtures_by_date_range( $from, $to, $params, $includes, $use_cache )` | Fixtures entre duas datas |
| `aps_get_livescores( $params, $includes, $use_cache )` | Livescores |
| `aps_format_match_status( $status )` | Traduz código de estado (NS, LIVE, HT, FT, etc.) para texto |

### 9.2 Classificações e Marcadores

| Função | Descrição |
|--------|-----------|
| `aps_get_league_standings( $league_id )` | Classificação da liga |
| `aps_get_league_top_scorers( $league_id, $limit = 20 )` | Melhores marcadores |

### 9.3 H2H e Plantel

| Função | Descrição |
|--------|-----------|
| `aps_get_head_to_head( $team1_id, $team2_id )` | Confronto direto |
| `aps_get_team_squad( $team_id, $params, $includes, $use_cache )` | Plantel (API) |
| `aps_get_team_players( $team_id, $use_cache )` | Jogadores da equipa (include players) |
| `aps_get_cached_team_squad( $team_id )` | Plantel a partir do cache (sync) |
| `aps_get_cached_team_injuries( $team_id )` | Lesões em cache |
| `aps_get_cached_team_transfers( $team_id )` | Transferências em cache |

### 9.4 Lesões e Transferências

| Função | Descrição |
|--------|-----------|
| `aps_get_team_sidelined( $team_id, $use_cache )` | Indisponíveis (sidelined) |
| `aps_get_injuries( $params, $includes, $use_cache )` | Lesões (params) |
| `aps_get_transfers( $params, $includes, $use_cache )` | Transferências |
| `aps_get_transfers_by_team( $team_id, $params, $includes, $use_cache )` | Transferências por equipa |

### 9.5 Jogadores

| Função | Descrição |
|--------|-----------|
| `aps_get_player( $player_id, $includes, $use_cache )` | Perfil do jogador |
| `aps_get_player_stats( $player_id, $use_cache )` | Estatísticas do jogador |

### 9.6 Pesquisa

| Função | Descrição |
|--------|-----------|
| `aps_search_teams( $name, $params, $includes, $use_cache )` | Pesquisa equipas por nome |
| `aps_search_players( $name, $params, $includes, $use_cache )` | Pesquisa jogadores por nome |

### 9.7 Mapeamento Equipa ↔ Category

| Função | Descrição |
|--------|-----------|
| `aps_get_team_category( $team_id )` | Objeto WP_Term da Category mapeada à equipa |
| `aps_get_team_category_mapping( )` | Array team_id => term_id (category) |

### 9.8 Helpers estáticos na classe APS_Theme_Helpers

- `get_match_events( $full_match_response )` — eventos do jogo a partir da resposta completa.
- `get_match_statistics( $full_match_response )` — estatísticas do jogo.
- `get_team_fixtures`, `get_fixtures_by_date_range`, `get_full_match_details`, `get_league_standings`, `get_league_top_scorers`, `get_head_to_head`, `get_cached_team_squad`, `get_cached_team_injuries`, `get_cached_team_transfers`, etc. (usados pelos shortcodes e componentes).

---

## 10. Shortcodes Clássicos (APS_Shortcodes)

Todos usam a classe CSS base `.aps-widget`. Parâmetros em snake_case.

| Shortcode | Parâmetros | Descrição |
|-----------|------------|-----------|
| `[aps_calendar]` | `team_id`, `from`, `to` | Lista de jogos (calendário). Se não houver team_id, usa intervalo global. |
| `[aps_next_game]` | `team_id` | Próximo jogo da equipa + countdown (JS) |
| `[aps_results]` | `team_id`, `from`, `to` | Resultados da equipa no intervalo |
| `[aps_match_center]` | `match_id` | Eventos + estatísticas do jogo |
| `[aps_standings]` | `league_id` | Tabela classificativa |
| `[aps_topscorers]` | `league_id`, `limit` (default 10) | Melhores marcadores |
| `[aps_h2h]` | `team1_id`, `team2_id` | Histórico confronto direto |
| `[aps_team_squad]` | `team_id`, `use_cache` (1/0) | Plantel |
| `[aps_injuries]` | `team_id`, `use_cache` | Boletim clínico (lesões) |
| `[aps_transfers]` | `team_id`, `use_cache` | Transferências da equipa |
| `[aps_player_profile]` | `player_id` | Nome e foto do jogador |

---

## 11. Componentes (APS_Components) e Shortcode [aps_component]

Uso: `[aps_component id="ID_DO_COMPONENTE" param1="valor1" ...]` ou em PHP: `aps_render_component( 'ID_DO_COMPONENTE', array( 'param' => 'value' ) )`.

### 11.1 Registro de Componentes

| ID | Nome | Parâmetros |
|----|------|-------------|
| `team_schedule_results` | Agenda e Resultados | `mode` (team/season/between), `team_id`, `season_id`, `start_date`, `end_date` |
| `competition_standings` | Classificação da competição | `fixture_id`, `league_id`, `season_id` |
| `head_to_head` | Historical data (Head2Head) | `team_1_id`, `team_2_id` |
| `injuries_suspensions` | Injuries & Suspensions | `fixture_id` |
| `team_recent_form` | Última Forma da Equipa | `team_id` |
| `events_timeline` | Events Timeline (Livescores & Events) | `fixture_id` |

### 11.2 AJAX (Componentes)

- `aps_preview_component` — pré-visualização no admin (Componentes).
- `aps_fetch_standings` — usado pelo frontend para carregar classificações (registado também para `nopriv`).

---

## 12. Data Explorer

- Página admin para testar a API sem sair do WordPress.
- **AJAX:** `aps_fetch_api_data` (endpoint genérico), `aps_fetch_team_bundle`, e vários “widgets”: `aps_fetch_widget_calendar`, `aps_fetch_widget_next_fixture`, `aps_fetch_widget_results`, `aps_fetch_widget_squad`, `aps_fetch_widget_player`, `aps_fetch_widget_injuries`, `aps_fetch_widget_match_center`, `aps_fetch_widget_standings`, `aps_fetch_widget_topscorers`, `aps_fetch_widget_h2h`.
- Permite abrir com `?match_id=...` para pré-preencher um jogo.

---

## 13. Error Logger

- **Tabela:** `aps_error_logs` (criada na ativação).
- **Métodos:** `log()`, `get_logs()`, `delete_log()`, `clear_old_logs()`, `delete_all_logs()`, `export_csv_download()`.
- **Página:** filtros por tipo de erro e data; ações: eliminar um log, limpar logs antigos (por dias), limpar todos, exportar CSV.
- Tipos de erro usados no plugin: por exemplo `API_ERROR`, `SYNC_ERROR`, etc.

---

## 14. Cron (Cron Handler)

- **Schedules customizados:** 15min, 30min, 2hours, 6hours, 12hours (além de `hourly` e `daily` do WP).
- Ao guardar configurações, chama-se `APS_Cron_Handler::update_schedule()` para reagendar o evento `aps_sportmonks_sync_event`.

---

## 15. Segurança e Boas Práticas

- Acesso a páginas e ações admin: `current_user_can( 'manage_options' )`.
- Nonces: em formulários e AJAX (ex.: `aps_save_settings`, `aps_components_nonce`, `aps_data_explorer_nonce`, `aps_settings_nonce`).
- Sanitização: `sanitize_text_field`, `absint`, callbacks `sanitize_teams` e `sanitize_checkbox` nas opções.
- Token da API não é exposto em logs (mascarado como `***`).
- Escaping em output: `esc_html`, `esc_attr`, `esc_url`, `esc_js` conforme o contexto.

---

## 16. Ativação e Desativação

**Ativação:**
- Criação da tabela `aps_error_logs`.
- Registo da taxonomia `aps_competicao` e do CPT `aps_jogo`.
- `flush_rewrite_rules()`.

**Desativação:**
- Desagendamento do evento `aps_sportmonks_sync_event`.
- `flush_rewrite_rules()`.

---

## 17. Assets

| Contexto | CSS | JS |
|----------|-----|-----|
| Configurações / Gestão Sync | settings.css | settings.js |
| Componentes (admin) | components-admin.css | components-admin.js |
| Frontend (componentes) | components-frontend.css | components-frontend.js |
| Data Explorer | data-explorer.css | data-explorer.js |
| Shortcodes (widgets) | widgets.css | widgets.js |

Scripts são carregados com `APS_SMONKS_VERSION` para cache busting.

---

## 18. Documentação Adicional

- **README.md** — instalação, configuração, uso básico, exemplos de helpers, meta fields, taxonomias, segurança.
- **docs/theme-helpers.md** — uso dos helpers e componentes no tema, single do jogo, tratamento de erros, estrutura dos dados.
- **design-prompts-rules.md** — sistema de design “Super Portistas” para aplicar a componentes futuros (cores, layout, animações, responsividade).

---

## 19. Resumo das Possibilidades para Desenvolvedores

1. **Tema:** Usar `aps_get_*` e `aps_render_component()` em templates; ler meta `_aps_*` nos singles de `aps_jogo`; usar `aps_competicao` e categories para navegação/arquivos.
2. **Conteúdo:** Inserir shortcodes `[aps_calendar]`, `[aps_standings]`, etc., ou `[aps_component id="..."]` em páginas/posts.
3. **Sync:** Configurar equipas e frequência; sync manual ou por datas na Gestão de Sync; bulk refresh de jogos; manter jogos eliminados alinhados com a API.
4. **Debug:** Data Explorer para testar endpoints; Error Log para falhas de API e sync.
5. **Extensão:** API Client e Theme Helpers cobrem fixtures, equipas, ligas, jogadores, lesões, transferências, H2H, livescores; não há filtros/actions expostos pelo plugin — extensão seria via novas funções que usem o mesmo client/options.

---

*Documento gerado com base no código e documentação existente do plugin API Sportmonks (versão 1.0.0).*
