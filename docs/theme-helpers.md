# Uso dos Helpers no Tema

Este documento explica como um tema WordPress pode consumir os dados do plugin `api-sportmonks` no frontend.

## Requisitos
- Plugin `API Sportmonks` ativo.
- Token API configurado em **Sportmonks > Configuracoes**.
- As equipas adicionadas nas configuracoes (Team ID).

## Onde usar
Os helpers podem ser usados em:
- `functions.php`
- templates do tema (`single.php`, `page.php`, `archive.php`, etc.)
- templates custom (ex.: `template-parts/`).

## Componentes (Agenda e Resultados, Classificação, etc.)

O plugin expõe componentes prontos (calendário/resultados da equipa, classificações, etc.) que podes usar no tema de duas formas.

### 1. Shortcode (editor ou template)

No editor de páginas/posts ou num template, usa o shortcode `[aps_component]` com o `id` do componente e os parâmetros necessários.

**Agenda e Resultados** (agenda e resultados da equipa):
```text
[aps_component id="team_schedule_results" team_id="652"]
```

Parâmetros opcionais: `mode` (team | season | between), `season_id`, `start_date`, `end_date`.

**Classificação da competição:**
```text
[aps_component id="competition_standings" league_id="462" season_id="12345"]
```

**Historical data (Head2Head)** (confrontos diretos entre duas equipas):
```text
[aps_component id="head_to_head" team_1_id="652" team_2_id="605"]
```

**Injuries & Suspensions** (lesões e suspensões para um jogo):
```text
[aps_component id="injuries_suspensions" fixture_id="19502616"]
```

**Última Forma da Equipa** (resultados recentes, xG e estatísticas):
```text
[aps_component id="team_recent_form" team_id="652"]
```

**Events Timeline** (cronologia do jogo: golos, substituições, cartões, VAR):
```text
[aps_component id="events_timeline" fixture_id="19502603"]
```

Parâmetros: `fixture_id` (obrigatório).

Num template PHP, usa `do_shortcode()`:
```php
<?php echo do_shortcode( '[aps_component id="team_schedule_results" team_id="652"]' ); ?>
```

### 2. Função PHP (apenas em templates)

Em ficheiros do tema (ex.: `single.php`, `page.php`, `template-parts/equipa.php`) podes chamar o helper e imprimir o HTML do componente:

```php
<?php
if ( function_exists( 'aps_render_component' ) ) {
    echo aps_render_component( 'team_schedule_results', array(
        'team_id' => 652,
        'mode'    => 'team',
    ) );
}
?>
```

Exemplo dinâmico (ex.: na single da equipa, com Team ID vindo do custom field):
```php
<?php
$team_id = (int) get_post_meta( get_the_ID(), 'sportmonks_team_id', true );
if ( $team_id && function_exists( 'aps_render_component' ) ) {
    echo aps_render_component( 'team_schedule_results', array( 'team_id' => $team_id ) );
}
?>
```

Exemplo Head2Head (ex.: na página do jogo, com os dois team IDs):
```php
<?php
if ( function_exists( 'aps_render_component' ) ) {
    echo aps_render_component( 'head_to_head', array(
        'team_1_id' => 652,
        'team_2_id' => 605,
    ) );
}
?>
```

Exemplo Injuries & Suspensions (ex.: na página do jogo, com Fixture ID):
```php
<?php
$fixture_id = (int) get_post_meta( get_the_ID(), 'sportmonks_fixture_id', true );
if ( $fixture_id && function_exists( 'aps_render_component' ) ) {
    echo aps_render_component( 'injuries_suspensions', array( 'fixture_id' => $fixture_id ) );
}
?>
```

O componente **team_schedule_results** mostra os últimos resultados e os próximos jogos da equipa, com filtros por competição e local. O **competition_standings** mostra a tabela da competição com filtro de época. O **head_to_head** mostra o histórico de confrontos entre duas equipas (resultados, estatísticas de golos e forma nos últimos jogos). O **injuries_suspensions** mostra jogadores indisponíveis para um jogo (lesões e suspensões), com data de regresso quando disponível. Os IDs (`team_id`, `fixture_id`, `league_id`, etc.) devem ser os IDs da API Sportmonks.

## Helpers disponiveis (principais)

### Fixtures / Calendario / Resultados
```php
// Fixtures de uma equipa com intervalo (YYYY-MM-DD)
$fixtures = aps_get_team_fixtures( 652, array(
    'filters' => 'between:2025-08-01,2026-06-01'
) );

// Fixtures por intervalo (sem team)
$range = aps_get_fixtures_by_date_range( '2025-08-01', '2026-06-01' );
```

### Proximo Jogo
```php
$next = aps_get_team_fixtures( 652, array(
    'filters' => 'from:' . gmdate( 'Y-m-d' ),
    'order'   => 'asc',
    'per_page'=> 1,
) );
```

### Match Center (Eventos + Estatisticas)
```php
$match = aps_get_full_match_details( 19449003 );
$events = APS_Theme_Helpers::get_match_events( $match );
$stats = APS_Theme_Helpers::get_match_statistics( $match );
```

### Match (dados base do post)
```php
// Usa o post meta como fallback para dados basicos do jogo
$match_meta = aps_get_match_from_post( get_the_ID() );
```

### Match (dados base da API)
```php
// Retorna fixture com includes base (participants, scores, state, events, statistics)
$match = aps_get_match_data( 19449003 );
```

### Standings / Topscorers
```php
$standings = aps_get_league_standings( 462 );
$topscorers = aps_get_league_top_scorers( 462, 20 );
```

### H2H
```php
$h2h = aps_get_head_to_head( 652, 605 ); // Porto vs Benfica
```

### Plantel (Team Information -> players)
```php
// Lista de jogadores via include=players
$players = aps_get_team_players( 652 );
```

### Perfil de Jogador + Estatisticas
```php
$player = aps_get_player( 98765 );
$player_stats = aps_get_player_stats( 98765 );
```

### Boletim Clinico (Sidelined)
```php
$sidelined = aps_get_team_sidelined( 652 );
```

### Estado do jogo (formatação)
```php
echo esc_html( aps_format_match_status( 'FT' ) ); // Terminado
```

### Categoria WordPress ligada a uma equipa
```php
$category = aps_get_team_category( 652 );
if ( $category ) {
    echo esc_html( $category->name );
}

// Mapa completo (team_id => term_id)
$mapping = aps_get_team_category_mapping();

// URL do logo da equipa (guardado na meta da categoria ao associar equipa nas Configurações)
$term_id = $mapping[652] ?? 0;
if ( $term_id ) {
    $logo_url = get_term_meta( $term_id, 'aps_team_logo', true );
    // usar $logo_url no front end (ex.: <img src="<?php echo esc_url( $logo_url ); ?>" />)
}
```

## Estrutura dos dados
Os helpers devolvem a resposta completa da API (JSON) como array PHP.
Para aceder aos dados use a chave `data`:
```php
if ( ! is_wp_error( $players ) && ! empty( $players['data']['players'] ) ) {
    foreach ( $players['data']['players'] as $player ) {
        echo esc_html( $player['name'] );
    }
}
```

## Tratamento de erros
Todos os helpers podem devolver `WP_Error`:
```php
$fixtures = aps_get_team_fixtures( 652 );
if ( is_wp_error( $fixtures ) ) {
    echo esc_html( $fixtures->get_error_message() );
}
```

## Exemplo: Single do jogo (`aps_jogo`)

No single do CPT `aps_jogo` podes usar os custom fields para mostrar equipas, resultado e logos no front end:

```php
$post_id = get_the_ID();
$team_home_name = get_post_meta( $post_id, '_aps_team_home_name', true );
$team_away_name = get_post_meta( $post_id, '_aps_team_away_name', true );
$team_home_logo = get_post_meta( $post_id, '_aps_team_home_logo', true );  // URL do logo da equipa da casa
$team_away_logo = get_post_meta( $post_id, '_aps_team_away_logo', true );  // URL do logo da equipa visitante
$score_home    = get_post_meta( $post_id, '_aps_score_home', true );
$score_away    = get_post_meta( $post_id, '_aps_score_away', true );
$match_id      = get_post_meta( $post_id, '_aps_match_id', true );
```

A competição fica associada à taxonomia `aps_competicao` (usar `get_the_terms( $post_id, 'aps_competicao' )`).

```php
$match_meta = aps_get_match_from_post( get_the_ID() );
$match_id = $match_meta['match_id'] ?? 0;

if ( $match_id ) {
    $match = aps_get_full_match_details( $match_id );
    if ( ! is_wp_error( $match ) ) {
        $events = APS_Theme_Helpers::get_match_events( $match );
        $stats = APS_Theme_Helpers::get_match_statistics( $match );
    }
}
```

## Notas importantes
- Alguns endpoints e includes podem estar limitados pelo plano. Para esses casos, o helper devolve erro `403` ou `404`.
- Includes que podem falhar consoante o plano: `lineups`, `venue`, `referee`, `statistics.periods`, `h2h`.
- Use sempre fallback com dados do post (`aps_get_match_from_post`) quando a API falhar.
- Evite chamar APIs diretamente no frontend (JS). Use sempre helpers no PHP.

