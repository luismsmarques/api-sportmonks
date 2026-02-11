=== API Sportmonks ===

Contributors: luismarques
Tags: sportmonks, football, soccer, fixtures, api, sync, matches, standings, live scores
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integra o WordPress com a API Sportmonks: sincroniza jogos, resultados, classificações, plantéis, lesões e transferências; CPT Jogo; shortcodes e componentes para calendários, tabelas e perfis. Desenvolvido por Luis Marques | Atlas Invencível (https://atlasinvencivel.pt/).

== Description ==

O **API Sportmonks** liga o seu site WordPress à API oficial da Sportmonks e mantém os dados de futebol sempre atualizados: jogos (fixtures), resultados, classificações, plantéis, lesões e transferências. Os jogos são criados como Custom Post Type (**Jogo**), com sincronização automática em intervalos configuráveis (15 min a diário). Use shortcodes ou componentes reutilizáveis para exibir calendários, próximos jogos, resultados, tabelas classificativas, confronto direto, plantel, lesões, transferências e perfil de jogador nas suas páginas e posts. Inclui painel de configuração, gestão de sincronização, explorador de dados da API e registo de erros.

**Desenvolvido por Luis Marques | Atlas Invencível** — [atlasinvencivel.pt](https://atlasinvencivel.pt/)

= Principais funcionalidades =

* **Sincronização automática** – Jogos das equipas configuradas são criados/atualizados como posts (tipo "Jogo") em intervalos configuráveis (15 min a diário).
* **Shortcodes prontos** – Calendário, próximo jogo, resultados, centro de jogo, classificações, melhores marcadores, confronto direto, plantel, lesões, transferências e perfil de jogador.
* **Componentes reutilizáveis** – Agenda/resultados, classificação da competição, head-to-head, lesões/suspensões, forma recente e linha de eventos, via shortcode ou PHP.
* **Painel de gestão** – Configurações, gestão de sync, explorador de dados da API e registo de erros num menu dedicado (Sportmonks).
* **Preparado para temas** – Funções helper (aps_get_*) e mapeamento equipas ↔ categorias para integrar nos seus templates.
* **Fallback de estádio** – Quando o nome do estádio não vem da API, o helper compara a equipa da casa com a equipa principal (ex.: Porto) e exibe "O jogo será jogado em casa" ou "O jogo será jogado fora".

= Requisitos =

* Conta Sportmonks e API Token (obtenha em My.Sportmonks).
* Extensões PHP: cURL e JSON.

== Installation ==

1. Faça upload da pasta `api-sportmonks` para `/wp-content/plugins/` ou instale pelo ecrã "Adicionar Plugins" do WordPress.
2. Ative o plugin através do menu **Plugins** no WordPress.
3. Em **Sportmonks > Configurações**, insira o seu **API Token** da Sportmonks e clique em **Testar Token**.
4. Adicione as equipas que deseja sincronizar (Team ID e nome) e, se quiser, mapeie cada equipa a uma Category.
5. Escolha a **Frequência de Sincronização** e guarde. Pode executar uma sincronização manual em **Sportmonks > Gestão de Sync**.

Para mostrar dados no site, use os shortcodes (ex.: `[aps_calendar team_id="123"]`, `[aps_standings league_id="456"]`) ou o shortcode de componentes: `[aps_component id="team_schedule_results" team_id="123"]`.

== Frequently Asked Questions ==

= Onde consigo o API Token da Sportmonks? =

Registe-se em [My.Sportmonks](https://my.sportmonks.com/) e gere um token na área da API. O plugin usa esse token em todas as chamadas à API Sportmonks.

= Como adiciono uma equipa para sincronizar? =

Em **Sportmonks > Configurações**, na secção Equipas, clique em **+ Adicionar Equipa**. Preencha o **Team ID** (ID da equipa na Sportmonks) e o **Nome da Equipa**. Opcionalmente, escolha uma Category para mapear. Guarde as configurações. O Team ID pode ser encontrado no Data Explorer do plugin (Sportmonks > Data Explorer) ou na documentação da API Sportmonks.

= Os jogos não aparecem. O que fazer? =

Verifique: (1) se o API Token está correto (use **Testar Token** nas Configurações); (2) se adicionou pelo menos uma equipa com Team ID válido; (3) se executou uma sincronização (**Sportmonks > Gestão de Sync > Sincronizar Agora**). Consulte **Sportmonks > Erros do Plugin** para ver mensagens de erro da API ou da sincronização.

== Changelog ==

= 1.0.1 =
* Melhoria: fallback de estádio (casa/fora) – quando o nome do estádio está vazio (API Sportmonks), o helper usa a equipa principal configurada e devolve "O jogo será jogado em casa" ou "O jogo será jogado fora" consoante team_home_id.
* Documentação: README e docs atualizados (API-FUNCOES, theme-helpers, PLUGIN-COMPLETO).

= 1.0.0 =
* Lançamento inicial: integração API Sportmonks, CPT Jogo, shortcodes, componentes, sync automática e manual, Data Explorer e Error Log.
