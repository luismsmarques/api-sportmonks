# Manual do Usuário — API Sportmonks

Este manual explica, de forma simples, como **configurar** e **usar** o plugin API Sportmonks no dia a dia: onde estão as opções, o que fazem e como mostrar calendários, resultados e outros dados no seu site.

---

## 1. O que é o plugin?

O API Sportmonks liga o seu site WordPress à API da Sportmonks para trazer dados de futebol (jogos, resultados, classificações, plantéis, etc.) e mostrá-los nas suas páginas. Pode configurar as equipas que quer acompanhar e deixar o plugin atualizar os dados automaticamente.

---

## 2. Onde está o menu do plugin?

Depois de ativar o plugin, aparece no menu lateral do WordPress um item chamado **Sportmonks** (com ícone de grupos). Ao clicar, é levado para a página de **Configurações**. No mesmo menu tem:

- **Sportmonks** / **Configurações** — definir token da API, equipas e opções de sincronização  
- **Gestão de Sync** — ver última sincronização, listar jogos e sincronizar agora ou por datas  
- **Erros do Plugin** — ver mensagens de erro (útil se algo falhar)  
- **Data Explorer** — ferramenta para testar a API (mais para uso técnico)  
- **Componentes** — pré-visualizar os blocos/componentes antes de os usar no site  

---

## 3. Configuração passo a passo

### 3.1 Obter o API Token

1. Crie uma conta em [My.Sportmonks](https://my.sportmonks.com/) se ainda não tiver.  
2. Na área da API, gere um **API Token**.  
3. Copie o token (uma sequência de caracteres).  

### 3.2 Inserir o token no WordPress

1. No WordPress, vá a **Sportmonks > Configurações**.  
2. No campo **API Token**, cole o token que copiou.  
3. Clique em **Testar Token**.  
   - Se aparecer mensagem de sucesso, está correto.  
   - Se der erro, confira se copiou o token completo e se a conta Sportmonks está ativa.  
4. Clique em **Guardar Configurações**.  

### 3.3 Adicionar equipas

Para o plugin sincronizar jogos, tem de indicar **quais equipas** quer acompanhar.

1. Em **Sportmonks > Configurações**, procure a secção **Equipas**.  
2. Clique em **+ Adicionar Equipa**.  
3. Preencha:  
   - **Team ID** — número que identifica a equipa na Sportmonks (pode procurar no Data Explorer ou na documentação da Sportmonks).  
   - **Nome da Equipa** — nome que quer ver no site (ex.: "FC Porto").  
4. Opcionalmente, pode **mapear com uma Category**: escolha uma categoria do WordPress para associar a essa equipa (útil para filtrar jogos por equipa no site).  
5. Repita para cada equipa que quiser.  
6. Clique em **Guardar Configurações**.  

### 3.4 Frequência de sincronização

Na mesma página de Configurações, no campo **Frequência de Sincronização**, escolha com que frequência o plugin deve atualizar os dados (por exemplo, a cada hora ou a cada 6 horas). Guarde as configurações.

### 3.5 Outras opções (avançadas)

- **Sincronizar plantéis / lesões / transferências** — ative ou desative conforme quiser usar esses dados (em cache).  
- **Sincronizar jogos eliminados** — se ativado, o plugin pode enviar para o lixo os jogos que a Sportmonks removeu da API, mantendo o site alinhado com a API.  
- **Cache (TTL)** — valores em segundos para quanto tempo guardar em cache plantéis, lesões e transferências; normalmente pode deixar os valores predefinidos.  

---

## 4. Sincronização (atualizar os dados)

- **Automática:** O plugin atualiza sozinho conforme a frequência que definiu nas Configurações.  
- **Manual:** Em **Sportmonks > Gestão de Sync** pode clicar em **Sincronizar Agora** para atualizar imediatamente.  
- **Por datas:** Na mesma página pode escolher um intervalo de datas e usar **Sincronizar por datas** para trazer apenas jogos nesse período.  

Depois da sincronização, os jogos aparecem como posts do tipo **Jogo** no WordPress (em **Posts > Jogos** ou no listado de posts, consoante o seu tema).

---

## 5. Como mostrar dados no site

Pode mostrar calendários, resultados, classificações, etc., de duas formas principais: **shortcodes** e **componentes**.

### 5.1 Shortcodes (blocos de texto prontos)

Os shortcodes são pequenos códigos que coloca dentro de uma **página** ou **post**. Ao publicar, o WordPress substitui o código pelo conteúdo (lista de jogos, tabela, etc.).

Onde usar: ao editar uma página ou post, insira o shortcode no texto (no editor de blocos pode usar o bloco "Shortcode").

**Exemplos:**

- **Calendário de jogos** (substitua `123` pelo ID da equipa):  
  `[aps_calendar team_id="123" from="2024-01-01" to="2024-12-31"]`

- **Próximo jogo** da equipa:  
  `[aps_next_game team_id="123"]`

- **Resultados** da equipa:  
  `[aps_results team_id="123" from="2024-01-01" to="2024-12-31"]`

- **Classificação** de uma liga (substitua `456` pelo ID da liga):  
  `[aps_standings league_id="456"]`

- **Melhores marcadores**:  
  `[aps_topscorers league_id="456" limit="10"]`

- **Confronto direto** entre duas equipas:  
  `[aps_h2h team1_id="123" team2_id="124"]`

- **Plantel**, **lesões** e **transferências** da equipa:  
  `[aps_team_squad team_id="123"]`  
  `[aps_injuries team_id="123"]`  
  `[aps_transfers team_id="123"]`

- **Centro de jogo** (eventos e estatísticas de um jogo; use o ID do jogo na API):  
  `[aps_match_center match_id="789"]`

- **Perfil de jogador**:  
  `[aps_player_profile player_id="999"]`

Os parâmetros `from` e `to` são datas no formato ano-mês-dia (ex.: 2024-06-01). Se os omitir, o plugin usa intervalos predefinidos.

### 5.2 Componentes (shortcode único com vários tipos)

O plugin tem também **componentes** que pode usar com um único shortcode: **`[aps_component]`**.

Formato geral:  
`[aps_component id="NOME_DO_COMPONENTE" param1="valor1" param2="valor2"]`

**Componentes disponíveis (exemplos de uso):**

- **Agenda e Resultados**  
  `[aps_component id="team_schedule_results" mode="team" team_id="123"]`

- **Classificação da competição**  
  `[aps_component id="competition_standings" league_id="456"]`  
  ou com um jogo específico:  
  `[aps_component id="competition_standings" fixture_id="789"]`

- **Confronto direto (Head to Head)**  
  `[aps_component id="head_to_head" team_1_id="123" team_2_id="124"]`

- **Lesões e suspensões** (para um jogo)  
  `[aps_component id="injuries_suspensions" fixture_id="789"]`

- **Última forma da equipa**  
  `[aps_component id="team_recent_form" team_id="123"]`

- **Linha de eventos do jogo**  
  `[aps_component id="events_timeline" fixture_id="789"]`

Pode experimentar cada componente em **Sportmonks > Componentes**: escolher o componente, preencher os parâmetros e ver a pré-visualização antes de colar o shortcode na página.

---

## 6. Menus e ecrãs no painel

| Onde | O que faz |
|------|-----------|
| **Sportmonks > Configurações** | API Token, equipas, frequência de sync, opções de cache e jogos eliminados. |
| **Sportmonks > Gestão de Sync** | Ver última sync, métricas, listar jogos, sincronizar agora ou por datas. |
| **Sportmonks > Erros do Plugin** | Lista de erros, filtros e exportar CSV. |
| **Sportmonks > Data Explorer** | Testar endpoints da API (uso mais técnico). |
| **Sportmonks > Componentes** | Pré-visualizar componentes e obter o shortcode com parâmetros. |
| **Posts > Jogos** (ou listagem de “Jogo”) | Ver e editar os jogos sincronizados (tipo de conteúdo “Jogo”). |

---

## 7. Resumo rápido

1. **Configurar:** Sportmonks > Configurações → API Token + equipas + frequência → Guardar.  
2. **Sincronizar:** Automático pela frequência; ou manual em Gestão de Sync.  
3. **Mostrar no site:** Inserir shortcodes (ex.: `[aps_calendar team_id="123"]`, `[aps_standings league_id="456"]`) ou `[aps_component id="..." ...]` em páginas/posts.  
4. **Problemas:** Ver Sportmonks > Erros do Plugin e confirmar token e Team IDs em Configurações.  

Para detalhes técnicos (funções PHP, API interna, etc.), consulte a documentação completa do plugin e o guia de API/Funções na pasta `docs/`.
