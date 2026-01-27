# API Sportmonks WordPress Plugin

Plugin WordPress para integração com a API Sportmonks, permitindo sincronizar dados de futebol e criar posts de jogos automaticamente.

## Características

- **Sincronização Automática**: Sincroniza dados de jogos das equipas configuradas em intervalos configuráveis
- **Custom Post Type**: Cria posts do tipo "aps_jogo" com dados essenciais dos jogos
- **Mapeamento de Taxonomias**: Liga IDs das equipas com Categories e IDs das ligas com a taxonomia customizada "aps_competicao"
- **Data Explorer**: Interface para explorar dados diretamente da API Sportmonks
- **Error Logging**: Sistema completo de registo de erros com interface admin
- **Theme Helpers**: Funções helper para temas obterem dados da API

## Requisitos

- WordPress 5.0+
- PHP 7.4+
- cURL extension
- JSON extension
- Token da API Sportmonks (obtenha em [My.Sportmonks](https://my.sportmonks.com/))

## Instalação

1. Faça upload da pasta `api-sportmonks` para `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Configure o plugin em **Sportmonks > Configurações**
4. Adicione o seu token da API Sportmonks
5. Configure as equipas que deseja sincronizar

## Configuração

### Configuração Inicial

1. Vá para **Sportmonks > Configurações**
2. Insira o seu **API Token** da Sportmonks
3. Clique em **Testar Token** para verificar se está correto
4. Configure a **Frequência de Sincronização** desejada

### Adicionar Equipas

1. Clique em **+ Adicionar Equipa**
2. Preencha:
   - **Team ID**: ID da equipa na API Sportmonks
   - **Nome da Equipa**: Nome da equipa
   - **League ID**: (Opcional) ID da liga
3. **Mapear com Category**: Selecione uma category existente ou crie uma nova e sincronize
4. **Mapear com Competição**: Selecione uma competição existente ou crie uma nova e sincronize
5. Clique em **Guardar Configurações**

### Mapeamento de Taxonomias

O plugin permite ligar:
- **IDs das Equipas** (Sportmonks) com **Categories** (WordPress)
- **IDs das Ligas** (Sportmonks) com **Competições** (taxonomia customizada)

Quando sincronizar jogos, o plugin automaticamente associa os posts do CPT "aps_jogo" com as taxonomias corretas baseado nos mapeamentos.

## Uso

### Sincronização Manual

Na página de configurações, clique em **Sincronizar Agora** para executar uma sincronização manual imediata.

### Data Explorer

Use a página **Data Explorer** para testar endpoints da API diretamente e ver as respostas em formato JSON.

### Error Log

A página **Error Log** mostra todos os erros registados pelo plugin, com filtros por tipo e data, e opção de exportar para CSV.

## Funções Helper para Temas

O plugin fornece várias funções helper que podem ser usadas nos templates do tema:

```php
// Obter dados básicos de um jogo
$match_data = aps_get_match_from_post( $post_id );

// Obter dados completos de um jogo da API
$full_match = aps_get_full_match_details( $match_id );

// Obter classificação da liga
$standings = aps_get_league_standings( $league_id );

// Obter top marcadores
$top_scorers = aps_get_league_top_scorers( $league_id, 20 );

// Obter histórico H2H
$h2h = aps_get_head_to_head( $team1_id, $team2_id );

// Formatar status do jogo
$status = aps_format_match_status( 'LIVE' ); // Retorna "Ao Vivo"
```

### Exemplos adicionais (tema)

```php
// Fixtures de uma equipa (calendario e resultados)
$fixtures = aps_get_team_fixtures( 123, array( 'filters' => 'between:2024-08-01,2025-06-01' ) );

// Proximo jogo (usa fixtures futuros)
$next = aps_get_team_fixtures( 123, array( 'filters' => 'from:' . gmdate( 'Y-m-d' ) ) );

// Plantel (pode vir do cache se existir)
$squad = aps_get_cached_team_squad( 123 );

// Lista de jogadores via include (alternativa quando team-squads nao esta no plano)
$players = aps_get_team_players( 123 );

// Lesoes e transferencias (cache ou direto)
$injuries = aps_get_cached_team_injuries( 123 );
$transfers = aps_get_cached_team_transfers( 123 );

// Match center (eventos/estatisticas)
$full_match = aps_get_full_match_details( 456 );
$events = APS_Theme_Helpers::get_match_events( $full_match );
$stats = APS_Theme_Helpers::get_match_statistics( $full_match );

// Pesquisa por nome (equipa/jogador)
$teams = aps_search_teams( 'Porto' );
$players = aps_search_players( 'Pepe' );

// Estatisticas standard do jogador
$player_stats = aps_get_player_stats( 98765 );
```

## Estrutura de Dados

### Meta Fields do CPT "aps_jogo"

- `_aps_match_id` - ID do jogo na API Sportmonks
- `_aps_team_home_id` - ID equipa casa
- `_aps_team_away_id` - ID equipa visitante
- `_aps_team_home_name` - Nome equipa casa
- `_aps_team_away_name` - Nome equipa visitante
- `_aps_league_id` - ID da liga
- `_aps_match_date` - Data/hora do jogo
- `_aps_match_status` - Estado do jogo (NS, LIVE, HT, FT, etc)
- `_aps_score_home` - Golos equipa casa
- `_aps_score_away` - Golos equipa visitante
- `_aps_last_sync` - Timestamp última sincronização

### Taxonomias

- **category**: Usada para equipas (mapeamento via meta field `aps_team_id`)
- **aps_competicao**: Taxonomia customizada para ligas/competições (mapeamento via meta field `aps_league_id`)

## Segurança

- Todos os inputs são sanitizados e validados
- Nonces em todos os formulários
- Capability checks (apenas utilizadores com `manage_options`)
- API token armazenado de forma segura
- Escaping de todos os outputs

## Suporte

Para questões sobre a API Sportmonks, consulte a [documentação oficial](https://docs.sportmonks.com/football/welcome).

## Licença

GPL v2 or later

