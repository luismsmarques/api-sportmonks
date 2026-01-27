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

### Categoria WordPress ligada a uma equipa
```php
$category = aps_get_team_category( 652 );
if ( $category ) {
    echo esc_html( $category->name );
}

// Mapa completo (team_id => term_id)
$mapping = aps_get_team_category_mapping();
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

## Notas importantes
- Alguns endpoints podem estar limitados pelo plano. Para esses casos, o helper devolve erro `403`.
- Evite chamar APIs diretamente no frontend (JS). Use sempre helpers no PHP.

