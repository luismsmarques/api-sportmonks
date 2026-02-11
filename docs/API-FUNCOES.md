# Documentação de API e Funções — API Sportmonks

Referência das **funções públicas** que o plugin expõe para uso em temas e outros plugins: assinaturas, parâmetros, valores de retorno e exemplos de uso.

Todas as funções globais têm o prefixo `aps_` e estão disponíveis após o plugin estar carregado. Em caso de falha (ex.: API indisponível ou token inválido), muitas devolvem `WP_Error`; convém usar `is_wp_error()` antes de usar o resultado.

---

## 1. Função de renderização de componentes

### `aps_render_component( $component_id, $args = array() )`

Renderiza um componente (bloco de conteúdo) no tema.

| Parâmetro      | Tipo  | Descrição |
|----------------|-------|-----------|
| `$component_id` | string | ID do componente: `team_schedule_results`, `competition_standings`, `head_to_head`, `injuries_suspensions`, `team_recent_form`, `events_timeline`. |
| `$args`         | array  | Parâmetros do componente (ex.: `team_id`, `league_id`, `fixture_id`). |

**Retorno:** `string` — HTML do componente (ou string vazia se o componente não existir).

**Exemplo:**

```php
// No template do tema
echo aps_render_component( 'team_schedule_results', array(
    'mode'    => 'team',
    'team_id' => 123,
) );

echo aps_render_component( 'competition_standings', array(
    'league_id' => 456,
) );
```

---

## 2. Jogos / Fixtures

### `aps_get_match_from_post( $post_id )`

Obtém os dados básicos do jogo a partir das meta do post (CPT `aps_jogo`).

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `$post_id` | int | ID do post do tipo "Jogo". |

**Retorno:** `array` — Chaves: `match_id`, `team_home_id`, `team_away_id`, `team_home_name`, `team_away_name`, `league_id`, `match_date`, `match_status`, `score_home`, `score_away`, `venue_name`, `venue_display`. Array vazio se não houver `_aps_match_id`.

- **`venue_name`** — Nome do estádio (meta `_aps_venue_name`); pode estar vazio se o plano API não incluir `venue`.
- **`venue_display`** — Texto para exibir o local: quando `venue_name` existe é igual a este; quando está vazio, o plugin compara `team_home_id` com a equipa principal (ex.: Porto) e devolve "O jogo será jogado em casa" ou "O jogo será jogado fora".

**Exemplo:**

```php
$dados = aps_get_match_from_post( get_the_ID() );
if ( ! empty( $dados['match_id'] ) ) {
    echo esc_html( $dados['team_home_name'] . ' vs ' . $dados['team_away_name'] );
    echo ' - ' . esc_html( $dados['score_home'] . '-' . $dados['score_away'] );
    if ( ! empty( $dados['venue_display'] ) ) {
        echo ' · ' . esc_html( $dados['venue_display'] );
    }
}
```

---

### `aps_get_match_data( $match_id, $use_cache = true )`

Obtém os dados do jogo (fixture) na API Sportmonks.

| Parâmetro   | Tipo  | Descrição |
|-------------|-------|-----------|
| `$match_id` | int   | ID do jogo na API. |
| `$use_cache` | bool | Usar cache (transients). Default `true`. |

**Retorno:** `array|WP_Error` — Resposta da API (estrutura com `data`, etc.) ou `WP_Error`.

**Exemplo:**

```php
$resposta = aps_get_match_data( 12345, true );
if ( is_wp_error( $resposta ) ) {
    return;
}
$jogo = $resposta['data'] ?? array();
```

---

### `aps_get_full_match_details( $match_id )`

Obtém o jogo com includes: participants, scores, state, events, statistics, events.type.

| Parâmetro   | Tipo | Descrição |
|-------------|------|-----------|
| `$match_id` | int  | ID do jogo na API. |

**Retorno:** `array|WP_Error`

**Exemplo:**

```php
$full = aps_get_full_match_details( 12345 );
if ( ! is_wp_error( $full ) && ! empty( $full['data'] ) ) {
    $eventos = APS_Theme_Helpers::get_match_events( $full );
    $stats   = APS_Theme_Helpers::get_match_statistics( $full );
}
```

---

### `aps_get_team_fixtures( $team_id, $params = array(), $includes = array(), $use_cache = true )`

Lista de jogos (fixtures) de uma equipa.

| Parâmetro   | Tipo  | Descrição |
|-------------|-------|-----------|
| `$team_id`  | int   | ID da equipa na API. |
| `$params`   | array | Parâmetros da API (ex.: `per_page`, `filters`, `order`). |
| `$includes` | array | Relações a incluir (ex.: `['league']`). |
| `$use_cache`| bool  | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

**Exemplo:**

```php
$fixtures = aps_get_team_fixtures( 123, array( 'per_page' => 10 ), array( 'league' ), true );
if ( ! is_wp_error( $fixtures ) && ! empty( $fixtures['data'] ) ) {
    foreach ( $fixtures['data'] as $f ) {
        // ...
    }
}
```

---

### `aps_get_fixtures_by_date_range( $from, $to, $params = array(), $includes = array(), $use_cache = true )`

Fixtures entre duas datas.

| Parâmetro   | Tipo   | Descrição |
|-------------|--------|-----------|
| `$from`     | string | Data início (YYYY-MM-DD). |
| `$to`       | string | Data fim (YYYY-MM-DD). |
| `$params`   | array  | Parâmetros da API. |
| `$includes` | array  | Includes. |
| `$use_cache`| bool   | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

**Exemplo:**

```php
$resposta = aps_get_fixtures_by_date_range( '2024-01-01', '2024-01-31', array(), array( 'league' ) );
```

---

### `aps_get_livescores( $params = array(), $includes = array(), $use_cache = true )`

Livescores (jogos em curso / recentes).

| Parâmetro   | Tipo  | Descrição |
|-------------|-------|-----------|
| `$params`   | array | Parâmetros da API. |
| `$includes` | array | Includes. |
| `$use_cache`| bool  | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

---

### `aps_format_match_status( $status )`

Traduz o código de estado do jogo para texto (PT).

| Parâmetro | Tipo   | Descrição |
|-----------|--------|-----------|
| `$status` | string | Código: NS, LIVE, HT, FT, CANC, POSTP. |

**Retorno:** `string`

**Exemplo:**

```php
echo aps_format_match_status( get_post_meta( get_the_ID(), '_aps_match_status', true ) );
// Por exemplo: "Terminado", "Ao Vivo"
```

---

## 3. Classificações e marcadores

### `aps_get_league_standings( $league_id )`

Classificação da liga.

| Parâmetro    | Tipo | Descrição |
|--------------|------|-----------|
| `$league_id` | int  | ID da liga na API. |

**Retorno:** `array|WP_Error`

---

### `aps_get_league_top_scorers( $league_id, $limit = 20 )`

Melhores marcadores da liga.

| Parâmetro    | Tipo | Descrição |
|--------------|------|-----------|
| `$league_id` | int  | ID da liga. |
| `$limit`     | int  | Número de jogadores. Default 20. |

**Retorno:** `array|WP_Error`

**Exemplo:**

```php
$bombas = aps_get_league_top_scorers( 456, 10 );
if ( ! is_wp_error( $bombas ) && ! empty( $bombas['data'] ) ) {
    foreach ( $bombas['data'] as $jogador ) {
        // ...
    }
}
```

---

## 4. Confronto direto e plantel

### `aps_get_head_to_head( $team1_id, $team2_id )`

Dados de confronto direto entre duas equipas.

| Parâmetro   | Tipo | Descrição |
|-------------|------|-----------|
| `$team1_id` | int  | ID equipa 1. |
| `$team2_id` | int  | ID equipa 2. |

**Retorno:** `array|WP_Error`

---

### `aps_get_team_squad( $team_id, $params = array(), $includes = array(), $use_cache = true )`

Plantel da equipa (API).

| Parâmetro   | Tipo  | Descrição |
|-------------|-------|-----------|
| `$team_id`  | int   | ID da equipa. |
| `$params`   | array | Parâmetros. |
| `$includes` | array | Includes. |
| `$use_cache`| bool  | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

---

### `aps_get_team_players( $team_id, $use_cache = true )`

Jogadores da equipa (include players).

| Parâmetro   | Tipo | Descrição |
|-------------|-----|-----------|
| `$team_id`  | int  | ID da equipa. |
| `$use_cache`| bool | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

---

### `aps_get_cached_team_squad( $team_id )`

Plantel a partir do cache preenchido pela sincronização do plugin (não chama a API).

| Parâmetro  | Tipo | Descrição |
|------------|-----|-----------|
| `$team_id` | int  | ID da equipa. |

**Retorno:** `array` — Array de jogadores em cache ou array vazio.

---

### `aps_get_cached_team_injuries( $team_id )`

Lesões da equipa a partir do cache da sync.

| Parâmetro  | Tipo | Descrição |
|------------|-----|-----------|
| `$team_id` | int  | ID da equipa. |

**Retorno:** `array`

---

### `aps_get_cached_team_transfers( $team_id )`

Transferências da equipa a partir do cache da sync.

| Parâmetro  | Tipo | Descrição |
|------------|-----|-----------|
| `$team_id` | int  | ID da equipa. |

**Retorno:** `array`

---

## 5. Lesões e transferências (API)

### `aps_get_team_sidelined( $team_id, $use_cache = true )`

Jogadores indisponíveis (sidelined) da equipa.

| Parâmetro   | Tipo | Descrição |
|-------------|-----|-----------|
| `$team_id`  | int  | ID da equipa. |
| `$use_cache`| bool | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

---

### `aps_get_injuries( $params = array(), $includes = array(), $use_cache = true )`

Lista de lesões (parâmetros gerais da API).

| Parâmetro   | Tipo  | Descrição |
|-------------|-------|-----------|
| `$params`   | array | Parâmetros. |
| `$includes` | array | Includes. |
| `$use_cache`| bool  | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

---

### `aps_get_transfers( $params = array(), $includes = array(), $use_cache = true )`

Transferências (parâmetros gerais).

| Parâmetro   | Tipo  | Descrição |
|-------------|-------|-----------|
| `$params`   | array | Parâmetros. |
| `$includes` | array | Includes. |
| `$use_cache`| bool  | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

---

### `aps_get_transfers_by_team( $team_id, $params = array(), $includes = array(), $use_cache = true )`

Transferências de uma equipa.

| Parâmetro   | Tipo  | Descrição |
|-------------|-------|-----------|
| `$team_id`  | int   | ID da equipa. |
| `$params`   | array | Parâmetros. |
| `$includes` | array | Includes. |
| `$use_cache`| bool  | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

---

## 6. Jogadores

### `aps_get_player( $player_id, $includes = array(), $use_cache = true )`

Perfil do jogador.

| Parâmetro    | Tipo  | Descrição |
|--------------|-------|-----------|
| `$player_id` | int   | ID do jogador. |
| `$includes`  | array | Includes. |
| `$use_cache`| bool  | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

---

### `aps_get_player_stats( $player_id, $use_cache = true )`

Estatísticas do jogador.

| Parâmetro    | Tipo | Descrição |
|--------------|-----|-----------|
| `$player_id` | int  | ID do jogador. |
| `$use_cache`| bool | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

---

## 7. Pesquisa

### `aps_search_teams( $name, $params = array(), $includes = array(), $use_cache = true )`

Pesquisa equipas por nome.

| Parâmetro   | Tipo   | Descrição |
|-------------|--------|-----------|
| `$name`     | string | Nome (ou parte). |
| `$params`   | array  | Parâmetros. |
| `$includes` | array  | Includes. |
| `$use_cache`| bool   | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

**Exemplo:**

```php
$resposta = aps_search_teams( 'Porto', array( 'per_page' => 5 ) );
```

---

### `aps_search_players( $name, $params = array(), $includes = array(), $use_cache = true )`

Pesquisa jogadores por nome.

| Parâmetro   | Tipo   | Descrição |
|-------------|--------|-----------|
| `$name`     | string | Nome (ou parte). |
| `$params`   | array  | Parâmetros. |
| `$includes` | array  | Includes. |
| `$use_cache`| bool   | Usar cache. Default `true`. |

**Retorno:** `array|WP_Error`

---

## 8. Mapeamento equipa ↔ Category

### `aps_get_team_category( $team_id )`

Devolve o termo (WP_Term) da Category mapeada à equipa no plugin.

| Parâmetro  | Tipo | Descrição |
|------------|-----|-----------|
| `$team_id` | int  | ID da equipa Sportmonks. |

**Retorno:** `WP_Term|null`

**Exemplo:**

```php
$term = aps_get_team_category( 123 );
if ( $term ) {
    echo esc_html( $term->name );
    echo get_term_link( $term );
}
```

---

### `aps_get_team_category_mapping()`

Mapeamento completo team_id → term_id (category).

**Retorno:** `array` — Ex.: `array( 123 => 4, 124 => 5 )`.

**Exemplo:**

```php
$map = aps_get_team_category_mapping();
foreach ( $map as $team_id => $term_id ) {
    // ...
}
```

---

## 9. Métodos estáticos da classe APS_Theme_Helpers (dados derivados)

Úteis quando já tem a resposta completa do jogo (ex.: de `aps_get_full_match_details`).

### `APS_Theme_Helpers::get_match_events( $match_data )`

Extrai o array de eventos do jogo a partir da resposta completa da API.

| Parâmetro     | Tipo  | Descrição |
|---------------|-------|-----------|
| `$match_data` | array | Resposta de `aps_get_full_match_details()`. |

**Retorno:** `array`

---

### `APS_Theme_Helpers::get_match_statistics( $match_data )`

Extrai as estatísticas do jogo.

| Parâmetro     | Tipo  | Descrição |
|---------------|-------|-----------|
| `$match_data` | array | Resposta de `aps_get_full_match_details()`. |

**Retorno:** `array`

---

### `APS_Theme_Helpers::get_match_lineups( $match_data )`

Extrai as formações/lineups.

| Parâmetro     | Tipo  | Descrição |
|---------------|-------|-----------|
| `$match_data` | array | Resposta de `aps_get_full_match_details()`. |

**Retorno:** `array`

**Exemplo:**

```php
$full = aps_get_full_match_details( 12345 );
if ( ! is_wp_error( $full ) ) {
    $eventos = APS_Theme_Helpers::get_match_events( $full );
    $stats   = APS_Theme_Helpers::get_match_statistics( $full );
    $lineups = APS_Theme_Helpers::get_match_lineups( $full );
}
```

---

## 10. Resumo por categoria

| Categoria        | Funções |
|------------------|---------|
| Componentes      | `aps_render_component` |
| Jogos/Fixtures   | `aps_get_match_from_post`, `aps_get_match_data`, `aps_get_full_match_details`, `aps_get_team_fixtures`, `aps_get_fixtures_by_date_range`, `aps_get_livescores`, `aps_format_match_status` |
| Classificações   | `aps_get_league_standings`, `aps_get_league_top_scorers` |
| H2H e plantel    | `aps_get_head_to_head`, `aps_get_team_squad`, `aps_get_team_players`, `aps_get_cached_team_squad`, `aps_get_cached_team_injuries`, `aps_get_cached_team_transfers` |
| Lesões/Transfers | `aps_get_team_sidelined`, `aps_get_injuries`, `aps_get_transfers`, `aps_get_transfers_by_team` |
| Jogadores        | `aps_get_player`, `aps_get_player_stats` |
| Pesquisa         | `aps_search_teams`, `aps_search_players` |
| Mapeamento       | `aps_get_team_category`, `aps_get_team_category_mapping` |
| Helpers estáticos| `APS_Theme_Helpers::get_match_events`, `get_match_statistics`, `get_match_lineups` |

As respostas da API seguem a estrutura da [Sportmonks API v3](https://docs.sportmonks.com/); o campo `data` contém normalmente o objeto ou lista de objetos pedidos.
