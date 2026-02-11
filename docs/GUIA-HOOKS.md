# Guia de Hooks — API Sportmonks

Este documento lista os **hooks personalizados** (actions e filters) que o plugin API Sportmonks define ou utiliza de forma relevante, para que desenvolvedores possam estender o comportamento do plugin.

---

## 1. Actions (Ações)

### 1.1 `aps_sportmonks_sync_event`

**Tipo:** Action (evento WP-Cron)  
**Definido em:** Plugin (agendado por `APS_Cron_Handler`, executado por `APS_Sync_Manager`)  
**Quando é disparado:** Em cada execução agendada da sincronização (conforme a frequência definida em **Sportmonks > Configurações**: 15 min, 30 min, horária, 2h, 6h, 12h ou diária).

**O que faz:** O plugin usa este hook para executar a sincronização de fixtures (jogos) de todas as equipas configuradas, além da sincronização opcional de plantéis, lesões e transferências em cache, e do processamento de jogos eliminados na API.

**Como usar:** Pode adicionar a sua própria função a este action para correr código antes, durante ou depois da lógica de sync do plugin (por exemplo, para notificações, logs externos ou atualização de outros dados). A callback é executada no mesmo momento em que a sync corre.

**Exemplo — executar código após cada sync agendada:**

```php
add_action( 'aps_sportmonks_sync_event', 'meu_site_apos_sync_sportmonks', 20 );

function meu_site_apos_sync_sportmonks() {
    // A sync do plugin já foi executada (prioridade 10).
    $ultima_sync = get_option( 'aps_smonks_last_sync', '' );
    if ( $ultima_sync ) {
        // Exemplo: enviar notificação ou atualizar cache do tema
        error_log( 'Sportmonks: sync concluída em ' . $ultima_sync );
    }
}
```

**Exemplo — executar código antes da sync (prioridade baixa):**

```php
add_action( 'aps_sportmonks_sync_event', 'meu_site_antes_sync_sportmonks', 5 );

function meu_site_antes_sync_sportmonks() {
    // Correr antes da sync (que está em prioridade 10)
    do_action( 'meu_tema_pre_sync_sportmonks' );
}
```

**Nota:** A sincronização manual (botão "Sincronizar Agora" ou sync por datas) chama internamente a mesma rotina que este evento; por isso, se precisar de distinguir sync agendada de manual, terá de usar outra abordagem (por exemplo, verificar contexto ou adicionar um action próprio no seu código quando disparar sync manual).

---

## 2. Filters (Filtros)

O plugin **não define** filtros próprios (`apply_filters`) no código atual. Abaixo está documentado o filtro do WordPress que o plugin **utiliza**, para referência em cenários de extensão.

### 2.1 `cron_schedules` (WordPress)

**Tipo:** Filter  
**Utilizado em:** `APS_Cron_Handler::add_custom_schedules()`  
**Propósito no plugin:** Adicionar intervalos de agendamento personalizados para o evento `aps_sportmonks_sync_event`.

**O que o plugin adiciona:** Os seguintes intervalos ficam disponíveis para qualquer cron no site:

| Slug       | Intervalo | Nome (PT)           |
|-----------|-----------|---------------------|
| `15min`   | 900 s     | A cada 15 minutos  |
| `30min`   | 1800 s    | A cada 30 minutos  |
| `2hours`  | 7200 s    | A cada 2 horas     |
| `6hours`  | 21600 s   | A cada 6 horas     |
| `12hours` | 43200 s   | A cada 12 horas    |

(Já existem no WordPress: `hourly`, `daily`, etc.)

**Como usar:** Se precisar de um intervalo extra só para o seu código, pode adicioná-lo através deste filtro. O plugin já regista os intervalos acima; não é necessário alterá-los para usar o API Sportmonks.

**Exemplo — adicionar um intervalo personalizado (para outros fins):**

```php
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['cada_45min'] = array(
        'interval' => 45 * MINUTE_IN_SECONDS,
        'display'  => __( 'A cada 45 minutos', 'meu-tema' ),
    );
    return $schedules;
} );
```

---

## 3. Resumo

| Hook                       | Tipo   | Definido pelo plugin? | Uso típico                                      |
|----------------------------|--------|------------------------|-------------------------------------------------|
| `aps_sportmonks_sync_event` | Action | Sim (WP-Cron)          | Executar código na mesma altura da sync agendada |
| `cron_schedules`           | Filter | Não (WordPress)        | Plugin adiciona intervalos; pode adicionar mais  |

Se no futuro o plugin passar a definir actions ou filters adicionais (por exemplo, para alterar dados antes de guardar um jogo ou antes de enviar um request à API), eles serão documentados aqui ou no ficheiro de documentação completa do plugin.
