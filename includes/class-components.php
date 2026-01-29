<?php
/**
 * Components Class
 *
 * Provides reusable frontend components and admin preview.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_Components {
	/**
	 * Instance
	 *
	 * @var APS_Components
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return APS_Components
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_ajax_aps_preview_component', array( $this, 'ajax_preview_component' ) );
		add_action( 'wp_ajax_aps_fetch_standings', array( $this, 'ajax_fetch_standings' ) );
		add_action( 'wp_ajax_nopriv_aps_fetch_standings', array( $this, 'ajax_fetch_standings' ) );
		add_shortcode( 'aps_component', array( $this, 'shortcode_component' ) );
	}

	/**
	 * Component registry.
	 *
	 * @return array
	 */
	private function get_registry() {
		return array(
			'team_schedule_results' => array(
				'label'       => __( 'Agenda e Resultados', 'api-sportmonks' ),
				'description' => __( 'Lista de jogos passados e futuros da equipa.', 'api-sportmonks' ),
				'fields'      => array(
					array(
						'key'     => 'mode',
						'label'   => __( 'Modo', 'api-sportmonks' ),
						'type'    => 'select',
						'options' => array(
							'team'    => __( 'Por equipa', 'api-sportmonks' ),
							'season'  => __( 'Por temporada', 'api-sportmonks' ),
							'between' => __( 'Entre datas', 'api-sportmonks' ),
						),
						'default' => 'team',
					),
					array( 'key' => 'team_id', 'label' => __( 'Team ID', 'api-sportmonks' ), 'type' => 'number', 'default' => '' ),
					array( 'key' => 'season_id', 'label' => __( 'Season ID', 'api-sportmonks' ), 'type' => 'number', 'default' => '' ),
					array( 'key' => 'start_date', 'label' => __( 'Data inicio', 'api-sportmonks' ), 'type' => 'date', 'default' => '' ),
					array( 'key' => 'end_date', 'label' => __( 'Data fim', 'api-sportmonks' ), 'type' => 'date', 'default' => '' ),
				),
			),
			'competition_standings' => array(
				'label'       => __( 'Classificacao da competicao', 'api-sportmonks' ),
				'description' => __( 'Tabela da competicao do jogo atual.', 'api-sportmonks' ),
				'fields'      => array(
					array( 'key' => 'fixture_id', 'label' => __( 'Fixture ID', 'api-sportmonks' ), 'type' => 'number', 'default' => '' ),
					array( 'key' => 'league_id', 'label' => __( 'League ID (opcional)', 'api-sportmonks' ), 'type' => 'number', 'default' => '' ),
					array( 'key' => 'season_id', 'label' => __( 'Season ID (opcional)', 'api-sportmonks' ), 'type' => 'number', 'default' => '' ),
				),
			),
			'head_to_head' => array(
				'label'       => __( 'Historical data (Head2Head)', 'api-sportmonks' ),
				'description' => __( 'Resultados historicos entre duas equipas, estatisticas de golos e tendencias.', 'api-sportmonks' ),
				'fields'      => array(
					array( 'key' => 'team_1_id', 'label' => __( 'Team 1 ID', 'api-sportmonks' ), 'type' => 'number', 'default' => '' ),
					array( 'key' => 'team_2_id', 'label' => __( 'Team 2 ID', 'api-sportmonks' ), 'type' => 'number', 'default' => '' ),
				),
			),
			'injuries_suspensions' => array(
				'label'       => __( 'Injuries & Suspensions', 'api-sportmonks' ),
				'description' => __( 'Jogadores indisponiveis para o jogo (lesoes e suspensoes) com data estimada de regresso.', 'api-sportmonks' ),
				'fields'      => array(
					array( 'key' => 'fixture_id', 'label' => __( 'Fixture ID', 'api-sportmonks' ), 'type' => 'number', 'default' => '' ),
				),
			),
			'team_recent_form' => array(
				'label'       => __( 'Última Forma da Equipa', 'api-sportmonks' ),
				'description' => __( 'Resume os resultados recentes de uma equipa, as tendências de xG e as principais estatísticas.', 'api-sportmonks' ),
				'fields'      => array(
					array( 'key' => 'team_id', 'label' => __( 'Team ID', 'api-sportmonks' ), 'type' => 'number', 'default' => '' ),
				),
			),
			'events_timeline' => array(
				'label'       => __( 'Events Timeline (Livescores & Events)', 'api-sportmonks' ),
				'description' => __( 'Timeline dos principais eventos do jogo: golos, substituições, cartões, VAR.', 'api-sportmonks' ),
				'fields'      => array(
					array( 'key' => 'fixture_id', 'label' => __( 'Fixture ID', 'api-sportmonks' ), 'type' => 'number', 'default' => '' ),
				),
			),
		);
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'aps-sportmonks',
			__( 'Componentes', 'api-sportmonks' ),
			__( 'Componentes', 'api-sportmonks' ),
			'manage_options',
			'aps-components',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'sportmonks_page_aps-components' !== $hook ) {
			return;
		}

		$this->register_frontend_assets();

		wp_enqueue_style( 'aps-components', APS_SMONKS_PLUGIN_URL . 'assets/css/components-admin.css', array(), APS_SMONKS_VERSION );
		wp_enqueue_script( 'aps-components', APS_SMONKS_PLUGIN_URL . 'assets/js/components-admin.js', array( 'jquery' ), APS_SMONKS_VERSION, true );
		wp_localize_script( 'aps-components', 'apsComponents', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'aps_components_nonce' ),
			'registry'  => $this->get_registry(),
			'messages'  => array(
				'noComponent' => __( 'Selecione um componente.', 'api-sportmonks' ),
				'loading'     => __( 'A carregar...', 'api-sportmonks' ),
			),
		) );

		wp_enqueue_style( 'aps-components-frontend' );
		wp_enqueue_script( 'aps-components-frontend' );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		$this->register_frontend_assets();
	}

	/**
	 * Register frontend assets.
	 */
	private function register_frontend_assets() {
		wp_register_style( 'aps-components-frontend', APS_SMONKS_PLUGIN_URL . 'assets/css/components-frontend.css', array(), APS_SMONKS_VERSION );
		wp_register_script( 'aps-components-frontend', APS_SMONKS_PLUGIN_URL . 'assets/js/components-frontend.js', array( 'jquery' ), APS_SMONKS_VERSION, true );
		wp_localize_script( 'aps-components-frontend', 'apsComponentsFront', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'aps_components_front_nonce' ),
			'labels'  => array(
				'loading' => __( 'A carregar...', 'api-sportmonks' ),
				'error'   => __( 'Nao foi possivel carregar a classificacao.', 'api-sportmonks' ),
			),
		) );
	}

	/**
	 * Render admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$registry = $this->get_registry();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description"><?php esc_html_e( 'Use este painel para testar componentes antes de os incluir no template.', 'api-sportmonks' ); ?></p>

			<div class="aps-components-admin">
				<div class="aps-components-panel">
					<label for="aps-component-select"><?php esc_html_e( 'Componente', 'api-sportmonks' ); ?></label>
					<select id="aps-component-select" class="regular-text">
						<option value=""><?php esc_html_e( '-- Selecionar --', 'api-sportmonks' ); ?></option>
						<?php foreach ( $registry as $key => $component ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $component['label'] ); ?></option>
						<?php endforeach; ?>
					</select>

					<div id="aps-component-description" class="aps-component-description"></div>
					<div id="aps-component-fields" class="aps-component-fields"></div>

					<button type="button" id="aps-component-preview" class="button button-primary">
						<?php esc_html_e( 'Testar componente', 'api-sportmonks' ); ?>
					</button>
				</div>

				<div class="aps-components-result">
					<h2><?php esc_html_e( 'Pré-visualização', 'api-sportmonks' ); ?></h2>
					<div id="aps-component-preview-html" class="aps-component-preview"></div>

					<h2><?php esc_html_e( 'Resposta da API', 'api-sportmonks' ); ?></h2>
					<pre id="aps-component-preview-json"></pre>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_component( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => '',
			),
			$atts
		);

		$component_id = sanitize_text_field( $atts['id'] );
		unset( $atts['id'] );

		return $this->render_component( $component_id, $atts );
	}

	/**
	 * AJAX: Preview component.
	 */
	public function ajax_preview_component() {
		check_ajax_referer( 'aps_components_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'api-sportmonks' ) ) );
		}

		$component_id = sanitize_text_field( $_POST['component_id'] ?? '' );
		$args = $_POST['args'] ?? array();
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		$rendered = $this->render_component( $component_id, $args );
		$response = $this->get_component_data( $component_id, $args );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		wp_send_json_success( array(
			'html' => $rendered,
			'json' => wp_json_encode( $response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
		) );
	}

	/**
	 * AJAX: Fetch standings for frontend filter.
	 */
	public function ajax_fetch_standings() {
		check_ajax_referer( 'aps_components_front_nonce', 'nonce' );

		$season_id = absint( $_POST['season_id'] ?? 0 );
		$league_id = absint( $_POST['league_id'] ?? 0 );
		$fixture_id = absint( $_POST['fixture_id'] ?? 0 );

		$response = $this->get_component_data(
			'competition_standings',
			array(
				'season_id' => $season_id,
				'league_id' => $league_id,
				'fixture_id' => $fixture_id,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$data = $response['data'] ?? array();
		$html = $this->render_competition_table( $data );

		wp_send_json_success( array(
			'html' => $html,
		) );
	}

	/**
	 * Render component HTML.
	 *
	 * @param string $component_id Component key.
	 * @param array  $args Component args.
	 * @return string
	 */
	public function render_component( $component_id, $args = array() ) {
		$component_id = sanitize_text_field( $component_id );
		if ( empty( $component_id ) ) {
			return '<p>' . esc_html__( 'Componente nao encontrado.', 'api-sportmonks' ) . '</p>';
		}

		$response = $this->get_component_data( $component_id, $args );
		if ( is_wp_error( $response ) ) {
			return '<p>' . esc_html( $response->get_error_message() ) . '</p>';
		}

		$data = $response['data'] ?? array();

		switch ( $component_id ) {
			case 'team_schedule_results':
				return $this->render_team_schedule_results( $data );
			case 'competition_standings':
				return $this->render_competition_standings( $data );
			case 'head_to_head':
				return $this->render_head_to_head( $data );
			case 'injuries_suspensions':
				return $this->render_injuries_suspensions( $data );
			case 'team_recent_form':
				return $this->render_team_recent_form( $data );
			case 'events_timeline':
				return $this->render_events_timeline( $data );
			default:
				return '<p>' . esc_html__( 'Componente nao encontrado.', 'api-sportmonks' ) . '</p>';
		}
	}

	/**
	 * Fetch component data.
	 *
	 * @param string $component_id Component key.
	 * @param array  $args Component args.
	 * @return array|WP_Error
	 */
	private function get_component_data( $component_id, $args ) {
		$api_client = APS_API_Client::get_instance();

		switch ( $component_id ) {
			case 'team_schedule_results':
				$mode = sanitize_text_field( $args['mode'] ?? 'team' );
				$team_id = absint( $args['team_id'] ?? 0 );
				$season_id = absint( $args['season_id'] ?? 0 );
				$start_date = sanitize_text_field( $args['start_date'] ?? '' );
				$end_date = sanitize_text_field( $args['end_date'] ?? '' );

				if ( ! $team_id ) {
					return new WP_Error( 'missing_params', __( 'Team ID e obrigatorio.', 'api-sportmonks' ) );
				}

				if ( 'season' === $mode ) {
					if ( ! $season_id ) {
						return new WP_Error( 'missing_params', __( 'Season ID e obrigatorio.', 'api-sportmonks' ) );
					}
					return $api_client->request(
						"schedules/seasons/{$season_id}/teams/{$team_id}",
						array(),
						array(),
						false
					);
				}

				if ( 'between' === $mode ) {
					if ( ! $start_date || ! $end_date ) {
						return new WP_Error( 'missing_params', __( 'Datas sao obrigatorias.', 'api-sportmonks' ) );
					}
					return $api_client->request(
						"fixtures/between/{$start_date}/{$end_date}/{$team_id}",
						array(),
						array( 'participants', 'scores', 'state', 'league' ),
						false
					);
				}

				$start_date = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
				$end_date   = gmdate( 'Y-m-d', strtotime( '+180 days' ) );

				$team_response = $api_client->request(
					"fixtures/between/{$start_date}/{$end_date}/{$team_id}",
					array(),
					array( 'participants', 'scores', 'state', 'league' ),
					false
				);

				if ( is_wp_error( $team_response ) ) {
					return $team_response;
				}

				return array(
					'data' => array(
						'fixtures' => $team_response['data'] ?? $team_response,
						'meta'     => array(
							'mode'           => $mode,
							'limit_upcoming' => 20,
							'limit_finished' => 50,
							'team_id'        => $team_id,
							'start_date'     => $start_date,
							'end_date'       => $end_date,
						),
					),
				);

			case 'competition_standings':
				$fixture_id = absint( $args['fixture_id'] ?? 0 );
				$league_id = absint( $args['league_id'] ?? 0 );
				$season_id = absint( $args['season_id'] ?? 0 );
				$seasons = array();
				$league_response = null;

				if ( ! $league_id && ! $season_id ) {
					if ( ! $fixture_id ) {
						return new WP_Error( 'missing_params', __( 'Fixture ID e obrigatorio.', 'api-sportmonks' ) );
					}

					$fixture_response = $api_client->request(
						"fixtures/{$fixture_id}",
						array(),
						array( 'league', 'season' ),
						false
					);

					if ( is_wp_error( $fixture_response ) ) {
						return $fixture_response;
					}

					$league_id = absint( $fixture_response['data']['league_id'] ?? ( $fixture_response['data']['league']['id'] ?? 0 ) );
					$season_id = absint( $fixture_response['data']['season_id'] ?? ( $fixture_response['data']['season']['id'] ?? 0 ) );
				}

				if ( $league_id ) {
					$league_response = $api_client->request(
						"leagues/{$league_id}",
						array(),
						array( 'seasons' ),
						false
					);

					if ( ! is_wp_error( $league_response ) ) {
						$seasons = $this->extract_league_seasons( $league_response );
					}
				}

				if ( $season_id ) {
					$standings = $api_client->request(
						"standings/seasons/{$season_id}",
						array(),
						array( 'participant', 'details' ),
						false
					);

					if ( is_wp_error( $standings ) ) {
						return $standings;
					}

					return array(
						'data' => array(
							'standings'  => $standings['data'] ?? array(),
							'seasons'    => $seasons,
							'season_id'  => $season_id,
							'league_id'  => $league_id,
							'fixture_id' => $fixture_id,
						),
						'raw' => array(
							'standings' => $standings,
							'seasons'   => $league_response ?? null,
						),
					);
				}

				if ( ! $league_id ) {
					return new WP_Error( 'missing_params', __( 'League ID nao encontrado.', 'api-sportmonks' ) );
				}

				$standings = $api_client->request(
					"standings/seasons/latest/leagues/{$league_id}",
					array(),
					array( 'participant', 'details' ),
					false
				);

				if ( is_wp_error( $standings ) ) {
					return $standings;
				}

				$first_row = $standings['data'][0] ?? array();
				$season_id = absint( $first_row['season_id'] ?? 0 );

				return array(
					'data' => array(
						'standings'  => $standings['data'] ?? array(),
						'seasons'    => $seasons,
						'season_id'  => $season_id,
						'league_id'  => $league_id,
						'fixture_id' => $fixture_id,
					),
					'raw' => array(
						'standings' => $standings,
						'seasons'   => $league_response ?? null,
					),
				);

			case 'head_to_head':
				$team_1_id = absint( $args['team_1_id'] ?? 0 );
				$team_2_id = absint( $args['team_2_id'] ?? 0 );

				if ( ! $team_1_id || ! $team_2_id ) {
					return new WP_Error( 'missing_params', __( 'Team 1 ID e Team 2 ID sao obrigatorios.', 'api-sportmonks' ) );
				}

				$response = $api_client->request(
					"fixtures/head-to-head/{$team_1_id}/{$team_2_id}",
					array(),
					array( 'participants', 'league', 'scores', 'state', 'events' ),
					false
				);

				if ( is_wp_error( $response ) ) {
					return $response;
				}

				$raw_fixtures = $response['data'] ?? $response;
				if ( ! is_array( $raw_fixtures ) ) {
					$raw_fixtures = array();
				}

				$fixtures = $this->sort_fixtures_by_date( $raw_fixtures );
				$fixtures = array_reverse( $fixtures ); // Most recent first for H2H.

				$team_1_name = '';
				$team_2_name = '';
				if ( ! empty( $fixtures[0]['participants'] ) ) {
					foreach ( $fixtures[0]['participants'] as $p ) {
						$id = (int) ( $p['id'] ?? 0 );
						$name = $p['name'] ?? '';
						if ( $id === $team_1_id ) {
							$team_1_name = $name;
						}
						if ( $id === $team_2_id ) {
							$team_2_name = $name;
						}
					}
				}

				return array(
					'data' => array(
						'fixtures'    => $fixtures,
						'team_1_id'   => $team_1_id,
						'team_2_id'   => $team_2_id,
						'team_1_name' => $team_1_name,
						'team_2_name' => $team_2_name,
						'stats'       => $this->compute_head_to_head_stats( $fixtures, $team_1_id, $team_2_id ),
					),
				);

			case 'injuries_suspensions':
				$fixture_id = absint( $args['fixture_id'] ?? 0 );
				if ( ! $fixture_id ) {
					return new WP_Error( 'missing_params', __( 'Fixture ID e obrigatorio.', 'api-sportmonks' ) );
				}

				$includes = array( 'sidelined.sideline.player', 'sidelined.sideline.type', 'participants', 'league', 'state' );
				// Venue may return 403 on some plans; template shows it only when present.
				$response = $api_client->request(
					"fixtures/{$fixture_id}",
					array(),
					$includes,
					false
				);

				if ( is_wp_error( $response ) ) {
					return $response;
				}

				$fixture = $response['data'] ?? $response;
				if ( ! is_array( $fixture ) ) {
					$fixture = array();
				}

				return array( 'data' => $fixture );

			case 'team_recent_form':
				$team_id = absint( $args['team_id'] ?? 0 );
				if ( ! $team_id ) {
					return new WP_Error( 'missing_params', __( 'Team ID e obrigatorio.', 'api-sportmonks' ) );
				}

				// xgfixture requires higher API plan; omit to avoid 403 – xG section shows only if data exists
				$includes = array( 'latest', 'latest.statistics', 'latest.participants', 'latest.scores' );
				$response = $api_client->get_team( $team_id, $includes, false );

				if ( is_wp_error( $response ) ) {
					return $response;
				}

				$team_data = $response['data'] ?? $response;
				if ( ! is_array( $team_data ) ) {
					$team_data = array();
				}

				$processed = $this->process_team_recent_form_data( $team_data, $team_id );
				return array( 'data' => $processed );

			case 'events_timeline':
				$fixture_id = absint( $args['fixture_id'] ?? 0 );
				if ( ! $fixture_id ) {
					return new WP_Error( 'missing_params', __( 'Fixture ID e obrigatorio.', 'api-sportmonks' ) );
				}

				// venue requires higher API plan; omit to avoid 403 – venue_name shows only if present in response
				$includes = array( 'participants', 'league', 'state', 'scores', 'events.type', 'events.period', 'events.player' );
				$response = $api_client->request(
					"fixtures/{$fixture_id}",
					array(),
					$includes,
					false
				);

				if ( is_wp_error( $response ) ) {
					return $response;
				}

				$fixture = $response['data'] ?? $response;
				if ( ! is_array( $fixture ) ) {
					$fixture = array();
				}

				$processed = $this->process_events_timeline_data( $fixture );
				return array( 'data' => $processed );
		}

		return new WP_Error( 'invalid_component', __( 'Componente nao encontrado.', 'api-sportmonks' ) );
	}

	/**
	 * Render premium team schedule & results component.
	 *
	 * @param array $data Response data.
	 * @return string
	 */
	private function render_team_schedule_results( $data ) {
		wp_enqueue_style( 'aps-components-frontend' );
		wp_enqueue_script( 'aps-components-frontend' );

		$fixtures = $this->extract_fixtures_from_schedule( $data );
		$fixtures = $this->sort_fixtures_by_date( $fixtures );
		$meta     = $data['meta'] ?? array();
		$team_id  = absint( $meta['team_id'] ?? 0 );
		if ( ! $team_id ) {
			$team_id = $this->extract_team_id_from_fixtures( $fixtures );
		}

		$limit_upcoming = absint( $meta['limit_upcoming'] ?? 20 );
		$limit_finished = absint( $meta['limit_finished'] ?? 50 );
		$split          = $this->get_schedule_finished_and_upcoming( $fixtures, $limit_upcoming, $limit_finished );
		$fixtures_upcoming = $split['upcoming'];
		$fixtures_finished = $split['finished'];
		$all_fixtures      = array_merge( $fixtures_upcoming, $fixtures_finished );
		$league_options    = $this->extract_schedule_leagues( $all_fixtures );
		$per_page          = 5;

		ob_start();
		?>
		<div class="aps-component aps-team-schedule-results aps-premium-card" data-component="team-schedule-results" data-page-size="<?php echo esc_attr( $per_page ); ?>">
			<div class="aps-premium-pattern aps-premium-pattern--schedule" aria-hidden="true">
				<svg viewBox="0 0 120 120">
					<rect x="12" y="12" width="96" height="96" rx="18" fill="currentColor" />
					<circle cx="36" cy="36" r="10" fill="#fff" opacity="0.35" />
					<circle cx="84" cy="84" r="8" fill="#fff" opacity="0.35" />
				</svg>
			</div>

			<div class="aps-premium-header">
				<div class="aps-premium-icon aps-premium-icon--schedule">
					<svg viewBox="0 0 24 24" aria-hidden="true">
						<path d="M7 3v2H5a2 2 0 0 0-2 2v2h18V7a2 2 0 0 0-2-2h-2V3h-2v2H9V3H7zm14 8H3v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8z" fill="currentColor" />
					</svg>
				</div>
				<div class="aps-premium-title">
					<h3><?php esc_html_e( 'Agenda e Resultados', 'api-sportmonks' ); ?></h3>
					<p><?php esc_html_e( 'Agenda completa de jogos recentes e futuros.', 'api-sportmonks' ); ?></p>
					<div class="aps-premium-count">
						<span class="aps-schedule-count"><?php echo esc_html( count( $fixtures_upcoming ) + count( $fixtures_finished ) ); ?></span>
						<small><?php esc_html_e( 'jogos', 'api-sportmonks' ); ?></small>
					</div>
				</div>
				<div class="aps-schedule-filters">
					<div class="aps-schedule-filter">
						<label><?php esc_html_e( 'Competicao', 'api-sportmonks' ); ?></label>
						<select class="aps-schedule-filter-league">
							<option value=""><?php esc_html_e( 'Todas', 'api-sportmonks' ); ?></option>
							<?php foreach ( $league_options as $league ) : ?>
								<option value="<?php echo esc_attr( $league['id'] ); ?>"><?php echo esc_html( $league['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="aps-schedule-filter">
						<label><?php esc_html_e( 'Local', 'api-sportmonks' ); ?></label>
						<select class="aps-schedule-filter-location">
							<option value=""><?php esc_html_e( 'Todos', 'api-sportmonks' ); ?></option>
							<option value="home"><?php esc_html_e( 'Casa', 'api-sportmonks' ); ?></option>
							<option value="away"><?php esc_html_e( 'Fora', 'api-sportmonks' ); ?></option>
						</select>
					</div>
				</div>
			</div>

			<div class="aps-premium-content">
				<?php if ( empty( $fixtures_upcoming ) && empty( $fixtures_finished ) ) : ?>
					<p class="aps-schedule-empty"><?php esc_html_e( 'Sem jogos encontrados.', 'api-sportmonks' ); ?></p>
				<?php else : ?>
					<div class="aps-schedule-tabs" role="tablist">
						<button type="button" class="aps-schedule-tab aps-schedule-tab--upcoming is-active" role="tab" aria-selected="true" data-tab="upcoming">
							<?php esc_html_e( 'Por realizar', 'api-sportmonks' ); ?>
							<span class="aps-schedule-tab-count">(<?php echo esc_html( count( $fixtures_upcoming ) ); ?>)</span>
						</button>
						<button type="button" class="aps-schedule-tab aps-schedule-tab--finished" role="tab" aria-selected="false" data-tab="finished">
							<?php esc_html_e( 'Realizados', 'api-sportmonks' ); ?>
							<span class="aps-schedule-tab-count">(<?php echo esc_html( count( $fixtures_finished ) ); ?>)</span>
						</button>
					</div>

					<div class="aps-schedule-tab-panels">
						<div class="aps-schedule-tab-panel aps-schedule-tab-panel--upcoming" role="tabpanel" data-tab="upcoming" aria-hidden="false">
							<?php echo $this->render_schedule_fixture_list( $fixtures_upcoming, $team_id, $per_page, 'upcoming' ); ?>
						</div>
						<div class="aps-schedule-tab-panel aps-schedule-tab-panel--finished" role="tabpanel" data-tab="finished" aria-hidden="true">
							<?php echo $this->render_schedule_fixture_list( $fixtures_finished, $team_id, $per_page, 'finished' ); ?>
						</div>
					</div>

					<p class="aps-schedule-empty aps-schedule-no-results" style="display:none;"><?php esc_html_e( 'Sem jogos para os filtros selecionados.', 'api-sportmonks' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render a single schedule fixture list with pagination (Ver mais).
	 *
	 * @param array  $fixtures Fixtures for this tab.
	 * @param int    $team_id  Team ID for location.
	 * @param int    $per_page Items visible per page.
	 * @param string $tab_key  Tab key (upcoming|finished).
	 * @return string
	 */
	private function render_schedule_fixture_list( $fixtures, $team_id, $per_page, $tab_key ) {
		if ( empty( $fixtures ) ) {
			return '<p class="aps-schedule-empty">' . esc_html__( 'Sem jogos.', 'api-sportmonks' ) . '</p>';
		}

		ob_start();
		?>
		<ul class="aps-schedule-list-premium aps-schedule-tab-list" data-tab="<?php echo esc_attr( $tab_key ); ?>" data-page-size="<?php echo esc_attr( $per_page ); ?>">
			<?php foreach ( $fixtures as $index => $fixture ) : ?>
				<?php
				$home       = $this->get_team_by_location( $fixture['participants'] ?? array(), 'home' );
				$away       = $this->get_team_by_location( $fixture['participants'] ?? array(), 'away' );
				$score      = $this->get_current_score( $fixture );
				$league_id  = $fixture['league_id'] ?? '';
				$league_name = $fixture['league']['name'] ?? ( $fixture['league_name'] ?? '' );
				$location   = $this->get_team_location_for_fixture( $fixture, $team_id );
				$match_time = $this->format_fixture_time( $fixture );
				$status_label = $this->get_fixture_status_label( $fixture );
				$is_first_page = $index < $per_page;
				?>
				<li
					class="aps-schedule-item-premium <?php echo $is_first_page ? '' : ' aps-schedule-item--hidden'; ?>"
					data-league="<?php echo esc_attr( $league_id ); ?>"
					data-location="<?php echo esc_attr( $location ); ?>"
					data-index="<?php echo esc_attr( $index ); ?>"
				>
					<div class="aps-schedule-meta">
						<span class="aps-schedule-league"><?php echo esc_html( $league_name ); ?></span>
						<span class="aps-schedule-location">
							<?php echo esc_html( $location === 'home' ? __( 'Casa', 'api-sportmonks' ) : __( 'Fora', 'api-sportmonks' ) ); ?>
						</span>
					</div>
					<div class="aps-schedule-teams">
						<div class="aps-schedule-team">
							<?php if ( ! empty( $home['image_path'] ) ) : ?>
								<img src="<?php echo esc_url( $home['image_path'] ); ?>" alt="<?php echo esc_attr( $home['name'] ?? '' ); ?>" loading="lazy" />
							<?php endif; ?>
							<span><?php echo esc_html( $home['name'] ?? '' ); ?></span>
						</div>
						<div class="aps-schedule-score">
							<span class="aps-schedule-badge"><?php echo esc_html( $status_label ); ?></span>
							<?php echo esc_html( $score !== '' ? $score : $match_time ); ?>
						</div>
						<div class="aps-schedule-team">
							<?php if ( ! empty( $away['image_path'] ) ) : ?>
								<img src="<?php echo esc_url( $away['image_path'] ); ?>" alt="<?php echo esc_attr( $away['name'] ?? '' ); ?>" loading="lazy" />
							<?php endif; ?>
							<span><?php echo esc_html( $away['name'] ?? '' ); ?></span>
						</div>
					</div>
					<div class="aps-schedule-date"><?php echo esc_html( $fixture['starting_at'] ?? '' ); ?></div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( count( $fixtures ) > $per_page ) : ?>
			<div class="aps-schedule-pagination" data-tab="<?php echo esc_attr( $tab_key ); ?>">
				<button type="button" class="aps-schedule-load-more">
					<?php esc_html_e( 'Ver mais', 'api-sportmonks' ); ?>
				</button>
			</div>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Compute head-to-head stats from finished fixtures (wins, draws, goals).
	 *
	 * @param array $fixtures Fixtures list.
	 * @param int   $team_1_id First team ID.
	 * @param int   $team_2_id Second team ID.
	 * @return array{total: int, wins_1: int, draws: int, wins_2: int, goals_1: int, goals_2: int, form_1: array, form_2: array}
	 */
	private function compute_head_to_head_stats( $fixtures, $team_1_id, $team_2_id ) {
		$wins_1 = 0;
		$wins_2 = 0;
		$draws = 0;
		$goals_1 = 0;
		$goals_2 = 0;
		$form_1 = array();
		$form_2 = array();

		foreach ( $fixtures as $fixture ) {
			if ( $this->get_fixture_status_key( $fixture ) !== 'finished' ) {
				continue;
			}

			$score = $this->get_current_score( $fixture );
			if ( $score === '' ) {
				continue;
			}

			$parts = array_map( 'intval', explode( '-', $score, 2 ) );
			$home_goals = $parts[0] ?? 0;
			$away_goals = $parts[1] ?? 0;

			$home = $this->get_team_by_location( $fixture['participants'] ?? array(), 'home' );
			$away = $this->get_team_by_location( $fixture['participants'] ?? array(), 'away' );
			$home_id = (int) ( $home['id'] ?? 0 );
			$away_id = (int) ( $away['id'] ?? 0 );

			if ( $home_id === $team_1_id && $away_id === $team_2_id ) {
				$goals_1 += $home_goals;
				$goals_2 += $away_goals;
				if ( $home_goals > $away_goals ) {
					$wins_1++;
					$form_1[] = 'W';
					$form_2[] = 'L';
				} elseif ( $home_goals < $away_goals ) {
					$wins_2++;
					$form_1[] = 'L';
					$form_2[] = 'W';
				} else {
					$draws++;
					$form_1[] = 'D';
					$form_2[] = 'D';
				}
			} elseif ( $home_id === $team_2_id && $away_id === $team_1_id ) {
				$goals_1 += $away_goals;
				$goals_2 += $home_goals;
				if ( $away_goals > $home_goals ) {
					$wins_1++;
					$form_1[] = 'W';
					$form_2[] = 'L';
				} elseif ( $away_goals < $home_goals ) {
					$wins_2++;
					$form_1[] = 'L';
					$form_2[] = 'W';
				} else {
					$draws++;
					$form_1[] = 'D';
					$form_2[] = 'D';
				}
			}
		}

		$total = $wins_1 + $draws + $wins_2;
		$form_1 = array_slice( array_reverse( $form_1 ), 0, 5 );
		$form_2 = array_slice( array_reverse( $form_2 ), 0, 5 );

		return array(
			'total'   => $total,
			'wins_1'  => $wins_1,
			'draws'   => $draws,
			'wins_2'  => $wins_2,
			'goals_1' => $goals_1,
			'goals_2' => $goals_2,
			'form_1'  => $form_1,
			'form_2'  => $form_2,
		);
	}

	/**
	 * Render head-to-head (historical data) component.
	 *
	 * @param array $data Response data with fixtures, team names, stats.
	 * @return string
	 */
	private function render_head_to_head( $data ) {
		wp_enqueue_style( 'aps-components-frontend' );
		wp_enqueue_script( 'aps-components-frontend' );

		$fixtures = $data['fixtures'] ?? array();
		$team_1_name = $data['team_1_name'] ?? __( 'Equipa 1', 'api-sportmonks' );
		$team_2_name = $data['team_2_name'] ?? __( 'Equipa 2', 'api-sportmonks' );
		$stats = $data['stats'] ?? array();
		$total = (int) ( $stats['total'] ?? 0 );
		$wins_1 = (int) ( $stats['wins_1'] ?? 0 );
		$draws = (int) ( $stats['draws'] ?? 0 );
		$wins_2 = (int) ( $stats['wins_2'] ?? 0 );
		$goals_1 = (int) ( $stats['goals_1'] ?? 0 );
		$goals_2 = (int) ( $stats['goals_2'] ?? 0 );
		$form_1 = $stats['form_1'] ?? array();
		$form_2 = $stats['form_2'] ?? array();
		$finished_fixtures = array_filter( $fixtures, function ( $f ) {
			return $this->get_fixture_status_key( $f ) === 'finished';
		} );
		$per_page = 5;
		$show_load_more = count( $finished_fixtures ) > $per_page;

		ob_start();
		?>
		<div class="aps-component aps-head-to-head aps-premium-card aps-premium-card--h2h" data-component="head-to-head">
			<div class="aps-premium-pattern aps-premium-pattern--h2h" aria-hidden="true">
				<svg viewBox="0 0 120 120">
					<path d="M60 12 L90 50 L60 88 L30 50 Z" fill="currentColor" />
					<circle cx="60" cy="50" r="18" fill="#fff" opacity="0.35" />
				</svg>
			</div>

			<div class="aps-premium-header">
				<div class="aps-premium-icon aps-premium-icon--h2h">
					<svg viewBox="0 0 24 24" aria-hidden="true">
						<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" fill="currentColor" />
					</svg>
				</div>
				<div class="aps-premium-title">
					<h3><?php esc_html_e( 'Historical data (Head2Head)', 'api-sportmonks' ); ?></h3>
					<p><?php esc_html_e( 'Resultados historicos entre as duas equipas, estatisticas de golos e tendencias.', 'api-sportmonks' ); ?></p>
					<div class="aps-premium-count aps-premium-count--h2h">
						<span><?php echo esc_html( count( $fixtures ) ); ?></span>
						<small><?php esc_html_e( 'confrontos', 'api-sportmonks' ); ?></small>
					</div>
				</div>
			</div>

			<div class="aps-premium-content">
				<?php if ( empty( $fixtures ) ) : ?>
					<p class="aps-h2h-empty"><?php esc_html_e( 'Nenhum confronto encontrado entre estas equipas.', 'api-sportmonks' ); ?></p>
				<?php else : ?>
					<div class="aps-h2h-versus">
						<div class="aps-h2h-team aps-h2h-team--1">
							<span class="aps-h2h-team-name"><?php echo esc_html( $team_1_name ); ?></span>
							<?php if ( ! empty( $form_1 ) ) : ?>
								<div class="aps-h2h-form" aria-label="<?php esc_attr_e( 'Forma ultimos jogos', 'api-sportmonks' ); ?>">
									<?php foreach ( $form_1 as $r ) : ?>
										<span class="aps-h2h-form-dot aps-h2h-form-dot--<?php echo esc_attr( strtolower( $r ) ); ?>"><?php echo esc_html( $r ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
						<div class="aps-h2h-vs">VS</div>
						<div class="aps-h2h-team aps-h2h-team--2">
							<span class="aps-h2h-team-name"><?php echo esc_html( $team_2_name ); ?></span>
							<?php if ( ! empty( $form_2 ) ) : ?>
								<div class="aps-h2h-form" aria-label="<?php esc_attr_e( 'Forma ultimos jogos', 'api-sportmonks' ); ?>">
									<?php foreach ( $form_2 as $r ) : ?>
										<span class="aps-h2h-form-dot aps-h2h-form-dot--<?php echo esc_attr( strtolower( $r ) ); ?>"><?php echo esc_html( $r ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( $total > 0 ) : ?>
						<div class="aps-h2h-stats">
							<div class="aps-h2h-stats-row">
								<span class="aps-h2h-stats-label"><?php esc_html_e( 'Vitorias', 'api-sportmonks' ); ?> (<?php echo esc_html( $team_1_name ); ?>)</span>
								<span class="aps-h2h-stats-value"><?php echo esc_html( $wins_1 ); ?></span>
							</div>
							<div class="aps-h2h-stats-row">
								<span class="aps-h2h-stats-label"><?php esc_html_e( 'Empates', 'api-sportmonks' ); ?></span>
								<span class="aps-h2h-stats-value"><?php echo esc_html( $draws ); ?></span>
							</div>
							<div class="aps-h2h-stats-row">
								<span class="aps-h2h-stats-label"><?php esc_html_e( 'Vitorias', 'api-sportmonks' ); ?> (<?php echo esc_html( $team_2_name ); ?>)</span>
								<span class="aps-h2h-stats-value"><?php echo esc_html( $wins_2 ); ?></span>
							</div>
							<div class="aps-h2h-stats-row aps-h2h-stats-row--goals">
								<span class="aps-h2h-stats-label"><?php esc_html_e( 'Golos', 'api-sportmonks' ); ?></span>
								<span class="aps-h2h-stats-value"><?php echo esc_html( $goals_1 . ' - ' . $goals_2 ); ?></span>
							</div>
						</div>
					<?php endif; ?>

					<div class="aps-h2h-results">
						<h4 class="aps-h2h-results-title"><?php esc_html_e( 'Resultados anteriores', 'api-sportmonks' ); ?></h4>
						<ul class="aps-h2h-list" data-page-size="<?php echo esc_attr( $per_page ); ?>">
							<?php
							$finished_list = array_values( $finished_fixtures );
							foreach ( $finished_list as $idx => $fixture ) :
								$home = $this->get_team_by_location( $fixture['participants'] ?? array(), 'home' );
								$away = $this->get_team_by_location( $fixture['participants'] ?? array(), 'away' );
								$score = $this->get_current_score( $fixture );
								$league_name = $fixture['league']['name'] ?? ( $fixture['league_name'] ?? '' );
								$date_display = ! empty( $fixture['starting_at'] ) ? gmdate( 'd/m/Y', strtotime( $fixture['starting_at'] ) ) : '';
								$is_first_page = $idx < $per_page;
							?>
								<li class="aps-h2h-item <?php echo $is_first_page ? '' : ' aps-h2h-item--hidden'; ?>" data-index="<?php echo esc_attr( $idx ); ?>">
									<div class="aps-h2h-item-meta"><?php echo esc_html( $league_name ); ?></div>
									<div class="aps-h2h-item-row">
										<div class="aps-h2h-item-team">
											<?php if ( ! empty( $home['image_path'] ) ) : ?>
												<img src="<?php echo esc_url( $home['image_path'] ); ?>" alt="<?php echo esc_attr( $home['name'] ?? '' ); ?>" loading="lazy" />
											<?php endif; ?>
											<span><?php echo esc_html( $home['name'] ?? '' ); ?></span>
										</div>
										<div class="aps-h2h-item-score"><?php echo esc_html( $score ); ?></div>
										<div class="aps-h2h-item-team">
											<?php if ( ! empty( $away['image_path'] ) ) : ?>
												<img src="<?php echo esc_url( $away['image_path'] ); ?>" alt="<?php echo esc_attr( $away['name'] ?? '' ); ?>" loading="lazy" />
											<?php endif; ?>
											<span><?php echo esc_html( $away['name'] ?? '' ); ?></span>
										</div>
									</div>
									<div class="aps-h2h-item-date"><?php echo esc_html( $date_display ); ?></div>
								</li>
							<?php endforeach; ?>
						</ul>
						<?php if ( $show_load_more ) : ?>
							<div class="aps-h2h-pagination">
								<button type="button" class="aps-h2h-load-more"><?php esc_html_e( 'Ver mais', 'api-sportmonks' ); ?></button>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Process team API response for recent form component (results, xG, key stats).
	 *
	 * @param array $team_data Raw team data from API (with latest fixtures if included).
	 * @param int   $team_id   Team ID for which we compute form.
	 * @return array{team_name: string, team_logo: string, form: array, wins: int, draws: int, losses: int, fixtures: array, xg_for: array, xg_against: array, key_stats: array}
	 */
	private function process_team_recent_form_data( $team_data, $team_id ) {
		$team_name = $team_data['name'] ?? __( 'Equipa', 'api-sportmonks' );
		$team_logo = $team_data['image_path'] ?? '';

		$latest = $team_data['latest'] ?? array();
		if ( isset( $latest['data'] ) && is_array( $latest['data'] ) ) {
			$latest = $latest['data'];
		}
		if ( ! is_array( $latest ) ) {
			$latest = array();
		}

		// Only finished fixtures; sort most recent first, then take up to 10.
		$finished = array();
		foreach ( $latest as $fixture ) {
			if ( $this->get_fixture_status_key( $fixture ) !== 'finished' ) {
				continue;
			}
			$finished[] = $fixture;
		}
		usort( $finished, function ( $a, $b ) {
			$ta = isset( $a['starting_at'] ) ? strtotime( $a['starting_at'] ) : 0;
			$tb = isset( $b['starting_at'] ) ? strtotime( $b['starting_at'] ) : 0;
			return $tb <=> $ta;
		} );
		$finished = array_slice( $finished, 0, 10 );

		$form = array();
		$wins = 0;
		$draws = 0;
		$losses = 0;
		$xg_for = array();
		$xg_against = array();
		$key_stats = array();

		foreach ( $finished as $fixture ) {
			$participants = $fixture['participants'] ?? array();
			$home = $this->get_team_by_location( $participants, 'home' );
			$away = $this->get_team_by_location( $participants, 'away' );
			$home_id = (int) ( $home['id'] ?? 0 );
			$away_id = (int) ( $away['id'] ?? 0 );
			$score = $this->get_current_score( $fixture );
			if ( $score === '' ) {
				continue;
			}
			$parts = array_map( 'intval', explode( '-', $score, 2 ) );
			$home_goals = $parts[0] ?? 0;
			$away_goals = $parts[1] ?? 0;

			$our_goals = 0;
			$opp_goals = 0;
			if ( $team_id === $home_id ) {
				$our_goals = $home_goals;
				$opp_goals = $away_goals;
			} elseif ( $team_id === $away_id ) {
				$our_goals = $away_goals;
				$opp_goals = $home_goals;
			} else {
				continue;
			}

			if ( $our_goals > $opp_goals ) {
				$form[] = 'W';
				$wins++;
			} elseif ( $our_goals < $opp_goals ) {
				$form[] = 'L';
				$losses++;
			} else {
				$form[] = 'D';
				$draws++;
			}

			$xgf = $fixture['xgfixture'] ?? array();
			if ( ! empty( $xgf['data'] ) && is_array( $xgf['data'] ) ) {
				$xgf = $xgf['data'];
			}
			if ( is_array( $xgf ) ) {
				foreach ( $xgf as $xg_row ) {
					$pid = (int) ( $xg_row['participant_id'] ?? 0 );
					$xg_val = (float) ( $xg_row['expected_goals'] ?? $xg_row['xg'] ?? 0 );
					if ( $pid === $team_id ) {
						$xg_for[] = $xg_val;
					} else {
						$xg_against[] = $xg_val;
					}
				}
			}

			$stats = $fixture['statistics'] ?? array();
			if ( isset( $stats['data'] ) && is_array( $stats['data'] ) ) {
				$stats = $stats['data'];
			}
			if ( is_array( $stats ) && empty( $key_stats ) ) {
				foreach ( $stats as $stat_row ) {
					$pid = (int) ( $stat_row['participant_id'] ?? 0 );
					if ( $pid !== $team_id ) {
						continue;
					}
					$type = $stat_row['type'] ?? array();
					$code = is_array( $type ) ? ( $type['code'] ?? $type['short_name'] ?? '' ) : (string) $type;
					$val = $stat_row['value'] ?? $stat_row['data']['value'] ?? null;
					if ( $code && $val !== null ) {
						$key_stats[ $code ] = $val;
					}
				}
			}
		}

		$form = array_slice( array_reverse( $form ), 0, 5 );
		$form = array_reverse( $form );

		return array(
			'team_name'   => $team_name,
			'team_logo'   => $team_logo,
			'team_id'     => $team_id,
			'form'        => $form,
			'wins'        => $wins,
			'draws'       => $draws,
			'losses'      => $losses,
			'fixtures'    => $finished,
			'xg_for'      => $xg_for,
			'xg_against'  => $xg_against,
			'key_stats'   => $key_stats,
		);
	}

	/**
	 * Render Team Recent Form component (results, xG trends, key stats).
	 *
	 * @param array $data Processed data from process_team_recent_form_data.
	 * @return string
	 */
	private function render_team_recent_form( $data ) {
		wp_enqueue_style( 'aps-components-frontend' );
		wp_enqueue_script( 'aps-components-frontend' );

		$team_name = $data['team_name'] ?? __( 'Equipa', 'api-sportmonks' );
		$team_logo = $data['team_logo'] ?? '';
		$form = $data['form'] ?? array();
		$wins = (int) ( $data['wins'] ?? 0 );
		$draws = (int) ( $data['draws'] ?? 0 );
		$losses = (int) ( $data['losses'] ?? 0 );
		$xg_for = $data['xg_for'] ?? array();
		$xg_against = $data['xg_against'] ?? array();
		$key_stats = $data['key_stats'] ?? array();
		$total = $wins + $draws + $losses;

		ob_start();
		?>
		<div class="aps-component aps-team-recent-form aps-premium-card aps-premium-card--recent-form" data-component="team-recent-form">
			<div class="aps-premium-pattern aps-premium-pattern--recent-form" aria-hidden="true">
				<svg viewBox="0 0 120 120">
					<rect x="12" y="12" width="96" height="96" rx="18" fill="currentColor" />
					<circle cx="36" cy="36" r="10" fill="#fff" opacity="0.35" />
					<circle cx="84" cy="84" r="8" fill="#fff" opacity="0.35" />
				</svg>
			</div>

			<div class="aps-premium-header">
				<div class="aps-premium-icon aps-premium-icon--recent-form">
					<svg viewBox="0 0 24 24" aria-hidden="true">
						<path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z" fill="currentColor" />
					</svg>
				</div>
				<div class="aps-premium-title">
					<h3><?php esc_html_e( 'Última Forma da Equipa', 'api-sportmonks' ); ?></h3>
					<p><?php esc_html_e( 'Resultados recentes, xG e estatísticas para analisar o momento.', 'api-sportmonks' ); ?></p>
					<div class="aps-premium-count aps-premium-count--recent-form">
						<?php if ( ! empty( $team_logo ) ) : ?>
							<img src="<?php echo esc_url( $team_logo ); ?>" alt="" class="aps-team-recent-form-logo" loading="lazy" />
						<?php endif; ?>
						<span><?php echo esc_html( $team_name ); ?></span>
					</div>
				</div>
			</div>

			<div class="aps-premium-content">
				<?php if ( $total === 0 ) : ?>
					<p class="aps-team-recent-form-empty"><?php esc_html_e( 'Sem jogos recentes para esta equipa.', 'api-sportmonks' ); ?></p>
				<?php else : ?>
					<div class="aps-results-summary">
						<div class="aps-result-item aps-result-item--wins">
							<span class="aps-result-value"><?php echo esc_html( (string) $wins ); ?></span>
							<small><?php esc_html_e( 'Vitórias', 'api-sportmonks' ); ?></small>
						</div>
						<div class="aps-result-item aps-result-item--draws">
							<span class="aps-result-value"><?php echo esc_html( (string) $draws ); ?></span>
							<small><?php esc_html_e( 'Empates', 'api-sportmonks' ); ?></small>
						</div>
						<div class="aps-result-item aps-result-item--losses">
							<span class="aps-result-value"><?php echo esc_html( (string) $losses ); ?></span>
							<small><?php esc_html_e( 'Derrotas', 'api-sportmonks' ); ?></small>
						</div>
					</div>

					<?php if ( ! empty( $form ) ) : ?>
						<div class="aps-form-row">
							<span class="aps-form-label"><?php esc_html_e( 'Forma (últimos jogos)', 'api-sportmonks' ); ?></span>
							<div class="aps-form-dots" aria-label="<?php esc_attr_e( 'Forma recente', 'api-sportmonks' ); ?>">
								<?php foreach ( $form as $r ) : ?>
									<span class="aps-form-dot aps-form-dot--<?php echo esc_attr( strtolower( $r ) ); ?>"><?php echo esc_html( $r ); ?></span>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $xg_for ) || ! empty( $xg_against ) ) : ?>
						<div class="aps-xg-section">
							<h4 class="aps-xg-title"><?php esc_html_e( 'xG (Expected Goals)', 'api-sportmonks' ); ?></h4>
							<div class="aps-xg-row">
								<div class="aps-xg-item">
									<span class="aps-xg-label"><?php esc_html_e( 'xG a favor', 'api-sportmonks' ); ?></span>
									<span class="aps-xg-value"><?php echo esc_html( number_format( array_sum( $xg_for ) / max( 1, count( $xg_for ) ), 2 ) ); ?></span>
								</div>
								<div class="aps-xg-item">
									<span class="aps-xg-label"><?php esc_html_e( 'xG contra', 'api-sportmonks' ); ?></span>
									<span class="aps-xg-value"><?php echo esc_html( number_format( array_sum( $xg_against ) / max( 1, count( $xg_against ) ), 2 ) ); ?></span>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $key_stats ) ) : ?>
						<div class="aps-key-stats">
							<h4 class="aps-key-stats-title"><?php esc_html_e( 'Estatísticas recentes', 'api-sportmonks' ); ?></h4>
							<div class="aps-key-stats-grid">
								<?php
								$stat_labels = array(
									'shots_total' => __( 'Remates', 'api-sportmonks' ),
									'possession' => __( 'Posse', 'api-sportmonks' ),
									'passes_total' => __( 'Passes', 'api-sportmonks' ),
									'corners' => __( 'Cantos', 'api-sportmonks' ),
								);
								foreach ( $key_stats as $code => $val ) :
									$label = $stat_labels[ $code ] ?? $code;
									?>
									<div class="aps-key-stat-item">
										<span class="aps-key-stat-label"><?php echo esc_html( $label ); ?></span>
										<span class="aps-key-stat-value"><?php echo esc_html( is_numeric( $val ) ? number_format( (float) $val, 1 ) : (string) $val ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Process fixture data for events timeline (sort events by period and minute).
	 *
	 * @param array $fixture Raw fixture from API (participants, events, league, state, scores).
	 * @return array{fixture_name: string, league_name: string, venue_name: string, state: array, score: string, home: array, away: array, events: array}
	 */
	private function process_events_timeline_data( $fixture ) {
		$home = $this->get_team_by_location( $fixture['participants'] ?? array(), 'home' );
		$away = $this->get_team_by_location( $fixture['participants'] ?? array(), 'away' );
		$home_id = (int) ( $home['id'] ?? 0 );
		$away_id = (int) ( $away['id'] ?? 0 );

		$events = $fixture['events'] ?? array();
		if ( isset( $events['data'] ) && is_array( $events['data'] ) ) {
			$events = $events['data'];
		}
		if ( ! is_array( $events ) ) {
			$events = array();
		}

		usort( $events, function ( $a, $b ) {
			$period_a = $a['period'] ?? array();
			$period_b = $b['period'] ?? array();
			$type_id_a = (int) ( is_array( $period_a ) ? ( $period_a['type_id'] ?? 0 ) : 0 );
			$type_id_b = (int) ( is_array( $period_b ) ? ( $period_b['type_id'] ?? 0 ) : 0 );
			if ( $type_id_a !== $type_id_b ) {
				return $type_id_a <=> $type_id_b;
			}
			$min_a = (int) ( $a['minute'] ?? 0 );
			$min_b = (int) ( $b['minute'] ?? 0 );
			if ( $min_a !== $min_b ) {
				return $min_a <=> $min_b;
			}
			$ex_a = (int) ( $a['extra_minute'] ?? 0 );
			$ex_b = (int) ( $b['extra_minute'] ?? 0 );
			if ( $ex_a !== $ex_b ) {
				return $ex_a <=> $ex_b;
			}
			return ( (int) ( $a['sort_order'] ?? 0 ) ) <=> ( (int) ( $b['sort_order'] ?? 0 ) );
		} );

		foreach ( $events as $i => $event ) {
			$pid = (int) ( $event['participant_id'] ?? 0 );
			$events[ $i ]['_side'] = ( $pid === $home_id ) ? 'home' : ( ( $pid === $away_id ) ? 'away' : '' );
		}

		return array(
			'fixture_name' => $fixture['name'] ?? '',
			'league_name'  => isset( $fixture['league']['name'] ) ? $fixture['league']['name'] : ( $fixture['league_name'] ?? '' ),
			'venue_name'  => isset( $fixture['venue']['name'] ) ? $fixture['venue']['name'] : ( $fixture['venue_name'] ?? '' ),
			'state'       => $fixture['state'] ?? array(),
			'score'       => $this->get_current_score( $fixture ),
			'home'        => $home,
			'away'        => $away,
			'events'      => $events,
		);
	}

	/**
	 * Render Events Timeline component (goals, cards, substitutions, VAR).
	 *
	 * @param array $data Processed data from process_events_timeline_data.
	 * @return string
	 */
	private function render_events_timeline( $data ) {
		wp_enqueue_style( 'aps-components-frontend' );
		wp_enqueue_script( 'aps-components-frontend' );

		$fixture_name = $data['fixture_name'] ?? __( 'Jogo', 'api-sportmonks' );
		$league_name  = $data['league_name'] ?? '';
		$score        = $data['score'] ?? '';
		$state        = $data['state'] ?? array();
		$state_name   = is_array( $state ) ? ( $state['name'] ?? $state['short_name'] ?? '' ) : (string) $state;
		$home         = $data['home'] ?? array();
		$away         = $data['away'] ?? array();
		$events       = $data['events'] ?? array();

		ob_start();
		?>
		<div class="aps-component aps-events-timeline aps-premium-card aps-premium-card--timeline" data-component="events-timeline">
			<div class="aps-premium-pattern aps-premium-pattern--timeline" aria-hidden="true">
				<svg viewBox="0 0 120 120">
					<path d="M60 8 L100 56 L60 104 L20 56 Z" fill="currentColor" />
					<circle cx="60" cy="56" r="20" fill="#fff" opacity="0.35" />
				</svg>
			</div>

			<div class="aps-premium-header">
				<div class="aps-premium-icon aps-premium-icon--timeline">
					<svg viewBox="0 0 24 24" aria-hidden="true">
						<path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor" />
					</svg>
				</div>
				<div class="aps-premium-title">
					<h3><?php esc_html_e( 'Cronologia do Jogo', 'api-sportmonks' ); ?></h3>
					<p><?php esc_html_e( 'Golos, substituições, cartões e decisões VAR.', 'api-sportmonks' ); ?></p>
					<div class="aps-premium-count aps-premium-count--timeline">
						<span><?php echo esc_html( count( $events ) ); ?></span>
						<small><?php echo esc_html( _n( 'evento', 'eventos', count( $events ), 'api-sportmonks' ) ); ?></small>
					</div>
				</div>
			</div>

			<div class="aps-premium-content">
				<div class="aps-timeline-fixture-header">
					<?php if ( ! empty( $home['image_path'] ) || ! empty( $away['image_path'] ) ) : ?>
						<div class="aps-timeline-teams">
							<div class="aps-timeline-team">
								<?php if ( ! empty( $home['image_path'] ) ) : ?>
									<img src="<?php echo esc_url( $home['image_path'] ); ?>" alt="<?php echo esc_attr( $home['name'] ?? '' ); ?>" loading="lazy" />
								<?php endif; ?>
								<span><?php echo esc_html( $home['name'] ?? '' ); ?></span>
							</div>
							<div class="aps-timeline-score">
								<?php if ( $score ) : ?>
									<span class="aps-timeline-score-value"><?php echo esc_html( $score ); ?></span>
								<?php endif; ?>
								<?php if ( $state_name ) : ?>
									<span class="aps-timeline-state"><?php echo esc_html( $state_name ); ?></span>
								<?php endif; ?>
							</div>
							<div class="aps-timeline-team">
								<?php if ( ! empty( $away['image_path'] ) ) : ?>
									<img src="<?php echo esc_url( $away['image_path'] ); ?>" alt="<?php echo esc_attr( $away['name'] ?? '' ); ?>" loading="lazy" />
								<?php endif; ?>
								<span><?php echo esc_html( $away['name'] ?? '' ); ?></span>
							</div>
						</div>
					<?php else : ?>
						<div class="aps-timeline-fixture-name"><?php echo esc_html( $fixture_name ); ?></div>
						<?php if ( $league_name ) : ?>
							<div class="aps-timeline-league"><?php echo esc_html( $league_name ); ?></div>
						<?php endif; ?>
						<?php if ( $score || $state_name ) : ?>
							<div class="aps-timeline-meta">
								<?php if ( $score ) : ?><span><?php echo esc_html( $score ); ?></span><?php endif; ?>
								<?php if ( $state_name ) : ?><span><?php echo esc_html( $state_name ); ?></span><?php endif; ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<?php if ( empty( $events ) ) : ?>
					<p class="aps-timeline-empty"><?php esc_html_e( 'Ainda não há eventos para este jogo.', 'api-sportmonks' ); ?></p>
				<?php else : ?>
					<ul class="aps-timeline-list" role="list">
						<?php foreach ( $events as $event ) : ?>
							<?php
							$type     = $event['type'] ?? array();
							$code     = is_array( $type ) ? ( $type['code'] ?? $type['name'] ?? '' ) : (string) $type;
							$type_name = is_array( $type ) ? ( $type['name'] ?? $code ) : $code;
							$side     = $event['_side'] ?? '';
							$minute   = (int) ( $event['minute'] ?? 0 );
							$extra    = isset( $event['extra_minute'] ) ? (int) $event['extra_minute'] : null;
							$time_str = $minute ? ( $minute . "'" . ( $extra !== null && $extra > 0 ? '+' . $extra : '' ) ) : '';
							$player_name = $event['player_name'] ?? '';
							$player = $event['player'] ?? array();
							if ( empty( $player_name ) && is_array( $player ) ) {
								$player_name = $player['display_name'] ?? $player['name'] ?? '';
							}
							$related_name = $event['related_player_name'] ?? '';
							$result  = $event['result'] ?? '';
							$info    = $event['info'] ?? '';
							$addition = $event['addition'] ?? '';
							$icon_class = 'aps-timeline-icon--' . strtolower( preg_replace( '/[^a-z0-9]/', '-', $code ) );
							?>
							<li class="aps-timeline-item aps-timeline-item--<?php echo esc_attr( $side ); ?>" data-event-type="<?php echo esc_attr( $code ); ?>">
								<div class="aps-timeline-time" aria-hidden="true"><?php echo esc_html( $time_str ); ?></div>
								<div class="aps-timeline-marker">
									<span class="aps-timeline-icon <?php echo esc_attr( $icon_class ); ?>" title="<?php echo esc_attr( $type_name ); ?>"></span>
								</div>
								<div class="aps-timeline-content">
									<?php if ( $result ) : ?>
										<span class="aps-timeline-result"><?php echo esc_html( $result ); ?></span>
									<?php endif; ?>
									<span class="aps-timeline-player"><?php echo esc_html( $player_name ?: __( '—', 'api-sportmonks' ) ); ?></span>
									<?php if ( $related_name && ( $code === 'substitution' || strpos( strtolower( $type_name ), 'subst' ) !== false ) ) : ?>
										<span class="aps-timeline-arrow" aria-hidden="true">→</span>
										<span class="aps-timeline-related"><?php echo esc_html( $related_name ); ?></span>
									<?php endif; ?>
									<?php if ( $addition ) : ?>
										<span class="aps-timeline-addition"><?php echo esc_html( $addition ); ?></span>
									<?php endif; ?>
									<?php if ( $info ) : ?>
										<span class="aps-timeline-info"><?php echo esc_html( $info ); ?></span>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Group sidelined entries by home/away participant.
	 *
	 * @param array $fixture Fixture data with participants and sidelined.
	 * @return array{home: array, away: array}
	 */
	private function group_sidelined_by_teams( $fixture ) {
		$home = $this->get_team_by_location( $fixture['participants'] ?? array(), 'home' );
		$away = $this->get_team_by_location( $fixture['participants'] ?? array(), 'away' );
		$home_id = (int) ( $home['id'] ?? 0 );
		$away_id = (int) ( $away['id'] ?? 0 );
		$sidelined = $fixture['sidelined'] ?? array();
		$home_sidelined = array();
		$away_sidelined = array();
		foreach ( $sidelined as $entry ) {
			$participant_id = (int) ( $entry['participant_id'] ?? 0 );
			if ( $participant_id === $home_id ) {
				$home_sidelined[] = $entry;
			} elseif ( $participant_id === $away_id ) {
				$away_sidelined[] = $entry;
			}
		}
		return array(
			'home' => array( 'team' => $home, 'sidelined' => $home_sidelined ),
			'away' => array( 'team' => $away, 'sidelined' => $away_sidelined ),
		);
	}

	/**
	 * Render Injuries & Suspensions component.
	 *
	 * @param array $data Fixture data (participants, sidelined, league, state, etc.).
	 * @return string
	 */
	private function render_injuries_suspensions( $data ) {
		wp_enqueue_style( 'aps-components-frontend' );
		wp_enqueue_script( 'aps-components-frontend' );

		$grouped = $this->group_sidelined_by_teams( $data );
		$home_team = $grouped['home']['team'];
		$away_team = $grouped['away']['team'];
		$home_sidelined = $grouped['home']['sidelined'];
		$away_sidelined = $grouped['away']['sidelined'];

		ob_start();
		?>
		<div class="aps-component aps-injuries-suspensions aps-premium-card aps-premium-card--sidelined" data-component="injuries-suspensions">
			<div class="aps-premium-pattern aps-premium-pattern--sidelined" aria-hidden="true">
				<svg viewBox="0 0 120 120">
					<path d="M60 8 L100 60 L60 112 L20 60 Z" fill="currentColor" />
					<circle cx="60" cy="60" r="24" fill="#fff" opacity="0.25" />
				</svg>
			</div>

			<div class="aps-premium-content aps-sidelined-content">
				<div class="aps-sidelined-divider">
					<span class="aps-sidelined-divider-text"><?php esc_html_e( 'Indisponiveis', 'api-sportmonks' ); ?></span>
				</div>
				<div class="aps-sidelined-columns">
					<div class="aps-sidelined-col">
						<div class="aps-sidelined-col-header">
							<?php if ( ! empty( $home_team['image_path'] ) ) : ?>
								<img src="<?php echo esc_url( $home_team['image_path'] ); ?>" alt="" class="aps-sidelined-col-logo" loading="lazy" />
							<?php endif; ?>
							<span class="aps-sidelined-col-count"><?php echo esc_html( count( $home_sidelined ) ); ?> <?php echo esc_html( _n( 'jogador', 'jogadores', count( $home_sidelined ), 'api-sportmonks' ) ); ?></span>
						</div>
						<ul class="aps-sidelined-list">
							<?php foreach ( $home_sidelined as $entry ) : ?>
								<?php
								$sideline = $entry['sideline'] ?? array();
								$player = $sideline['player'] ?? array();
								$type = $sideline['type'] ?? array();
								$type_name = $type['name'] ?? __( 'Lesao', 'api-sportmonks' );
								$display_name = $player['display_name'] ?? $player['name'] ?? '';
								$image_path = $player['image_path'] ?? '';
								$end_date = $sideline['end_date'] ?? null;
								?>
								<li class="aps-sidelined-card">
									<?php if ( $image_path ) : ?><img src="<?php echo esc_url( $image_path ); ?>" alt="<?php echo esc_attr( $display_name ); ?>" class="aps-sidelined-card-img" loading="lazy" /><?php endif; ?>
									<div class="aps-sidelined-card-body">
										<span class="aps-sidelined-card-name"><?php echo esc_html( $display_name ); ?></span>
										<span class="aps-sidelined-card-type"><?php echo esc_html( $type_name ); ?></span>
										<?php if ( $end_date ) : ?>
											<span class="aps-sidelined-card-return"><?php echo esc_html( sprintf( __( 'Regresso previsto: %s', 'api-sportmonks' ), gmdate( 'd/m/Y', strtotime( $end_date ) ) ) ); ?></span>
										<?php endif; ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<div class="aps-sidelined-col">
						<div class="aps-sidelined-col-header">
							<?php if ( ! empty( $away_team['image_path'] ) ) : ?>
								<img src="<?php echo esc_url( $away_team['image_path'] ); ?>" alt="" class="aps-sidelined-col-logo" loading="lazy" />
							<?php endif; ?>
							<span class="aps-sidelined-col-count"><?php echo esc_html( count( $away_sidelined ) ); ?> <?php echo esc_html( _n( 'jogador', 'jogadores', count( $away_sidelined ), 'api-sportmonks' ) ); ?></span>
						</div>
						<ul class="aps-sidelined-list">
							<?php foreach ( $away_sidelined as $entry ) : ?>
								<?php
								$sideline = $entry['sideline'] ?? array();
								$player = $sideline['player'] ?? array();
								$type = $sideline['type'] ?? array();
								$type_name = $type['name'] ?? __( 'Lesao', 'api-sportmonks' );
								$display_name = $player['display_name'] ?? $player['name'] ?? '';
								$image_path = $player['image_path'] ?? '';
								$end_date = $sideline['end_date'] ?? null;
								?>
								<li class="aps-sidelined-card">
									<?php if ( $image_path ) : ?><img src="<?php echo esc_url( $image_path ); ?>" alt="<?php echo esc_attr( $display_name ); ?>" class="aps-sidelined-card-img" loading="lazy" /><?php endif; ?>
									<div class="aps-sidelined-card-body">
										<span class="aps-sidelined-card-name"><?php echo esc_html( $display_name ); ?></span>
										<span class="aps-sidelined-card-type"><?php echo esc_html( $type_name ); ?></span>
										<?php if ( $end_date ) : ?>
											<span class="aps-sidelined-card-return"><?php echo esc_html( sprintf( __( 'Regresso previsto: %s', 'api-sportmonks' ), gmdate( 'd/m/Y', strtotime( $end_date ) ) ) ); ?></span>
										<?php endif; ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render competition standings.
	 *
	 * @param array $data Response data.
	 * @return string
	 */
	private function render_competition_standings( $data ) {
		wp_enqueue_style( 'aps-components-frontend' );
		wp_enqueue_script( 'aps-components-frontend' );

		$rows = $this->extract_standings_rows( $data );
		$seasons = $data['seasons'] ?? array();
		$season_id = absint( $data['season_id'] ?? 0 );
		$league_id = absint( $data['league_id'] ?? 0 );
		$fixture_id = absint( $data['fixture_id'] ?? 0 );

		ob_start();
		?>
		<div
			class="aps-component aps-competition-standings aps-premium-card"
			data-component="competition-standings"
			data-league-id="<?php echo esc_attr( $league_id ); ?>"
			data-season-id="<?php echo esc_attr( $season_id ); ?>"
			data-fixture-id="<?php echo esc_attr( $fixture_id ); ?>"
		>
			<div class="aps-premium-pattern" aria-hidden="true">
				<svg viewBox="0 0 120 120">
					<circle cx="60" cy="60" r="48" fill="currentColor" />
					<circle cx="88" cy="32" r="10" fill="#fff" opacity="0.35" />
					<circle cx="30" cy="90" r="8" fill="#fff" opacity="0.35" />
				</svg>
			</div>

			<div class="aps-premium-header">
				<div class="aps-premium-icon">
					<svg viewBox="0 0 24 24" aria-hidden="true">
						<path d="M4 5h16v2H4V5zm0 4h16v2H4V9zm0 4h10v2H4v-2zm0 4h7v2H4v-2z" fill="currentColor" />
					</svg>
				</div>
				<div class="aps-premium-title">
					<h3><?php esc_html_e( 'Classificacao', 'api-sportmonks' ); ?></h3>
					<p><?php esc_html_e( 'Tabela atual da competicao e historico recente.', 'api-sportmonks' ); ?></p>
					<div class="aps-premium-count">
						<span><?php echo esc_html( count( $rows ) ); ?></span>
						<small><?php esc_html_e( 'equipas', 'api-sportmonks' ); ?></small>
					</div>
				</div>
				<div class="aps-standings-filter">
					<label for="aps-standings-season-<?php echo esc_attr( $league_id ?: $fixture_id ); ?>">
						<?php esc_html_e( 'Epoca', 'api-sportmonks' ); ?>
					</label>
					<select id="aps-standings-season-<?php echo esc_attr( $league_id ?: $fixture_id ); ?>" class="aps-standings-season-select">
						<?php if ( empty( $seasons ) ) : ?>
							<option value="<?php echo esc_attr( $season_id ); ?>"><?php esc_html_e( 'Atual', 'api-sportmonks' ); ?></option>
						<?php else : ?>
							<?php foreach ( $seasons as $season ) : ?>
								<option value="<?php echo esc_attr( $season['id'] ); ?>" <?php selected( (int) $season['id'], $season_id ); ?>>
									<?php echo esc_html( $season['label'] ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>
			</div>

			<div class="aps-premium-content">
				<div class="aps-standings-table-wrapper">
					<?php echo $this->render_competition_table( $data ); ?>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render standings table HTML.
	 *
	 * @param array $data Standings payload.
	 * @return string
	 */
	private function render_competition_table( $data ) {
		$rows = $this->extract_standings_rows( $data );

		ob_start();
		if ( empty( $rows ) ) :
			?>
			<p class="aps-standings-empty"><?php esc_html_e( 'Sem classificacao disponivel.', 'api-sportmonks' ); ?></p>
			<?php
		else :
			?>
			<table class="aps-standings-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Pos', 'api-sportmonks' ); ?></th>
						<th><?php esc_html_e( 'Equipa', 'api-sportmonks' ); ?></th>
						<th><?php esc_html_e( 'J', 'api-sportmonks' ); ?></th>
						<th><?php esc_html_e( 'V', 'api-sportmonks' ); ?></th>
						<th><?php esc_html_e( 'E', 'api-sportmonks' ); ?></th>
						<th><?php esc_html_e( 'D', 'api-sportmonks' ); ?></th>
						<th><?php esc_html_e( 'GM', 'api-sportmonks' ); ?></th>
						<th><?php esc_html_e( 'GS', 'api-sportmonks' ); ?></th>
						<th><?php esc_html_e( 'DG', 'api-sportmonks' ); ?></th>
						<th><?php esc_html_e( 'Pts', 'api-sportmonks' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$participant = $row['participant'] ?? array();
						$team_name = $participant['name'] ?? ( $row['participant_name'] ?? '' );
						$team_logo = $participant['image_path'] ?? '';
						$played = $this->resolve_standings_value( $row, array( 'played', 'games_played', 'matches_played' ), array( 'played', 'games_played', 'matches_played' ), array( 129 ) );
						$won = $this->resolve_standings_value( $row, array( 'won', 'games_won', 'matches_won' ), array( 'won', 'games_won', 'matches_won', 'wins' ), array( 130 ) );
						$draw = $this->resolve_standings_value( $row, array( 'draw', 'games_drawn', 'matches_drawn' ), array( 'draw', 'games_drawn', 'matches_drawn', 'draws' ), array( 131 ) );
						$lost = $this->resolve_standings_value( $row, array( 'lost', 'games_lost', 'matches_lost' ), array( 'lost', 'games_lost', 'matches_lost', 'losses' ), array( 132 ) );
						$goals_for = $this->resolve_standings_value( $row, array( 'goals_for', 'goals_scored', 'scored' ), array( 'goals_for', 'goals_scored', 'goals', 'scored', 'goals_for_total' ), array( 133 ) );
						$goals_against = $this->resolve_standings_value( $row, array( 'goals_against', 'goals_conceded', 'conceded' ), array( 'goals_against', 'goals_conceded', 'conceded', 'goals_against_total' ), array( 134 ) );
						$goal_difference = $this->resolve_standings_value( $row, array( 'goal_difference', 'goals_diff', 'goal_diff' ), array( 'goal_difference', 'goals_diff', 'goal_diff', 'difference' ), array( 179 ) );
						$points = $this->resolve_standings_value( $row, array( 'points', 'total_points' ), array( 'points', 'total_points' ), array( 187 ) );
						?>
						<tr>
							<td><?php echo esc_html( $row['position'] ?? '' ); ?></td>
							<td>
								<span class="aps-team-cell">
									<?php if ( ! empty( $team_logo ) ) : ?>
										<img class="aps-team-logo" src="<?php echo esc_url( $team_logo ); ?>" alt="<?php echo esc_attr( $team_name ); ?>" loading="lazy" />
									<?php endif; ?>
									<span class="aps-team-name"><?php echo esc_html( $team_name ); ?></span>
								</span>
							</td>
							<td><?php echo esc_html( $played ); ?></td>
							<td><?php echo esc_html( $won ); ?></td>
							<td><?php echo esc_html( $draw ); ?></td>
							<td><?php echo esc_html( $lost ); ?></td>
							<td><?php echo esc_html( $goals_for ); ?></td>
							<td><?php echo esc_html( $goals_against ); ?></td>
							<td><?php echo esc_html( $goal_difference ); ?></td>
							<td><?php echo esc_html( $points ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endif;

		return ob_get_clean();
	}

	/**
	 * Get team by location.
	 *
	 * @param array  $participants Participants list.
	 * @param string $location Location (home|away).
	 * @return array
	 */
	private function get_team_by_location( $participants, $location ) {
		foreach ( $participants as $participant ) {
			$meta = $participant['meta'] ?? array();
			if ( ( $meta['location'] ?? '' ) === $location ) {
				return $participant;
			}
		}
		return array();
	}

	/**
	 * Get current score from fixture.
	 *
	 * @param array $fixture Fixture data.
	 * @return string
	 */
	private function get_current_score( $fixture ) {
		$scores = $fixture['scores'] ?? array();
		if ( empty( $scores ) || ! is_array( $scores ) ) {
			return '';
		}

		$home = '';
		$away = '';

		foreach ( $scores as $score ) {
			if ( ( $score['description'] ?? '' ) !== 'CURRENT' ) {
				continue;
			}
			$participant = $score['score']['participant'] ?? '';
			$goals = $score['score']['goals'] ?? '';
			if ( 'home' === $participant ) {
				$home = $goals;
			} elseif ( 'away' === $participant ) {
				$away = $goals;
			}
		}

		if ( $home === '' && $away === '' ) {
			return '';
		}

		return sprintf( '%s - %s', $home, $away );
	}

	/**
	 * Resolve team location for fixture by team id.
	 *
	 * @param array $fixture Fixture data.
	 * @param int   $team_id Team id.
	 * @return string
	 */
	private function get_team_location_for_fixture( $fixture, $team_id ) {
		if ( empty( $team_id ) ) {
			return '';
		}

		$participants = $fixture['participants'] ?? array();
		foreach ( $participants as $participant ) {
			if ( (int) ( $participant['id'] ?? 0 ) !== (int) $team_id ) {
				continue;
			}
			$meta = $participant['meta'] ?? array();
			return $meta['location'] ?? '';
		}

		return '';
	}

	/**
	 * Extract leagues from schedule fixtures.
	 *
	 * @param array $fixtures Fixtures list.
	 * @return array
	 */
	private function extract_schedule_leagues( $fixtures ) {
		$leagues = array();

		foreach ( $fixtures as $fixture ) {
			$league_id = $fixture['league_id'] ?? 0;
			if ( ! $league_id ) {
				continue;
			}
			$league_name = $fixture['league']['name'] ?? ( $fixture['league_name'] ?? '' );
			if ( empty( $league_name ) ) {
				$league_name = (string) $league_id;
			}
			$leagues[ $league_id ] = array(
				'id' => $league_id,
				'name' => $league_name,
			);
		}

		return array_values( $leagues );
	}

	/**
	 * Status options for schedule filters.
	 *
	 * @return array
	 */
	private function get_schedule_status_options() {
		return array(
			'scheduled' => __( 'Por jogar', 'api-sportmonks' ),
			'live' => __( 'Ao vivo', 'api-sportmonks' ),
			'finished' => __( 'Terminado', 'api-sportmonks' ),
		);
	}

	/**
	 * Get fixture status key.
	 *
	 * @param array $fixture Fixture data.
	 * @return string
	 */
	private function get_fixture_status_key( $fixture ) {
		$state_id = (int) ( $fixture['state_id'] ?? 0 );
		if ( ! $state_id && ! empty( $fixture['state']['id'] ) ) {
			$state_id = (int) $fixture['state']['id'];
		}
		$has_scores = ! empty( $fixture['scores'] );
		$has_result = ! empty( $fixture['result_info'] );
		$start = $fixture['starting_at'] ?? '';
		$timestamp = $start ? strtotime( $start ) : 0;
		$now = time();

		if ( $has_result ) {
			return 'finished';
		}

		if ( $has_scores && $timestamp && $timestamp <= $now ) {
			return 'live';
		}

		if ( $state_id && in_array( $state_id, array( 5, 6, 7 ), true ) ) {
			return 'finished';
		}

		return 'scheduled';
	}

	/**
	 * Get fixture status label.
	 *
	 * @param array $fixture Fixture data.
	 * @return string
	 */
	private function get_fixture_status_label( $fixture ) {
		if ( ! empty( $fixture['result_info'] ) ) {
			return (string) $fixture['result_info'];
		}

		$labels = $this->get_schedule_status_options();
		$key = $this->get_fixture_status_key( $fixture );

		return $labels[ $key ] ?? '';
	}

	/**
	 * Sort fixtures by starting date.
	 *
	 * @param array $fixtures Fixtures list.
	 * @return array
	 */
	private function sort_fixtures_by_date( $fixtures ) {
		usort(
			$fixtures,
			function ( $a, $b ) {
				$time_a = isset( $a['starting_at'] ) ? strtotime( $a['starting_at'] ) : 0;
				$time_b = isset( $b['starting_at'] ) ? strtotime( $b['starting_at'] ) : 0;
				return $time_a <=> $time_b;
			}
		);

		return $fixtures;
	}

	/**
	 * Extract team id from fixtures.
	 *
	 * @param array $fixtures Fixtures list.
	 * @return int
	 */
	private function extract_team_id_from_fixtures( $fixtures ) {
		foreach ( $fixtures as $fixture ) {
			$participants = $fixture['participants'] ?? array();
			foreach ( $participants as $participant ) {
				if ( ! empty( $participant['meta']['location'] ) ) {
					return (int) ( $participant['id'] ?? 0 );
				}
			}
		}

		return 0;
	}

	/**
	 * Format fixture time for display.
	 *
	 * @param array $fixture Fixture data.
	 * @return string
	 */
	private function format_fixture_time( $fixture ) {
		if ( empty( $fixture['starting_at'] ) ) {
			return '';
		}

		$timestamp = strtotime( $fixture['starting_at'] );
		if ( ! $timestamp ) {
			return $fixture['starting_at'];
		}

		return gmdate( 'H:i', $timestamp );
	}

	/**
	 * Extract fixtures from schedules response.
	 *
	 * @param array $data Response data.
	 * @return array
	 */
	private function extract_fixtures_from_schedule( $data ) {
		$fixtures = array();

		if ( isset( $data['fixtures'] ) && is_array( $data['fixtures'] ) ) {
			$fixtures = $data['fixtures'];
			if ( isset( $fixtures[0] ) && is_array( $fixtures[0] ) && ( isset( $fixtures[0]['fixtures'] ) || isset( $fixtures[0]['rounds'] ) ) ) {
				return $this->flatten_schedule_stages( $fixtures );
			}
			return $fixtures;
		}

		if ( ! empty( $data ) && is_array( $data ) ) {
			if (
				isset( $data[0] ) &&
				is_array( $data[0] ) &&
				isset( $data[0]['id'] ) &&
				isset( $data[0]['name'] ) &&
				! isset( $data[0]['fixtures'] ) &&
				! isset( $data[0]['rounds'] ) &&
				( isset( $data[0]['participants'] ) || isset( $data[0]['scores'] ) || isset( $data[0]['starting_at'] ) )
			) {
				return $data;
			}

			foreach ( $data as $stage ) {
				if ( ! empty( $stage['fixtures'] ) && is_array( $stage['fixtures'] ) ) {
					$fixtures = array_merge( $fixtures, $stage['fixtures'] );
				}
				if ( ! empty( $stage['rounds'] ) && is_array( $stage['rounds'] ) ) {
					foreach ( $stage['rounds'] as $round ) {
						if ( ! empty( $round['fixtures'] ) && is_array( $round['fixtures'] ) ) {
							$fixtures = array_merge( $fixtures, $round['fixtures'] );
						}
					}
				}
			}
		}

		return $fixtures;
	}

	/**
	 * Flatten stages/rounds fixtures.
	 *
	 * @param array $stages Stage list.
	 * @return array
	 */
	private function flatten_schedule_stages( $stages ) {
		$fixtures = array();

		foreach ( $stages as $stage ) {
			if ( ! empty( $stage['fixtures'] ) && is_array( $stage['fixtures'] ) ) {
				$fixtures = array_merge( $fixtures, $stage['fixtures'] );
			}
			if ( ! empty( $stage['rounds'] ) && is_array( $stage['rounds'] ) ) {
				foreach ( $stage['rounds'] as $round ) {
					if ( ! empty( $round['fixtures'] ) && is_array( $round['fixtures'] ) ) {
						$fixtures = array_merge( $fixtures, $round['fixtures'] );
					}
				}
			}
		}


		return $fixtures;
	}

	/**
	 * Filter upcoming fixtures and limit results.
	 *
	 * @param array $fixtures Fixtures list.
	 * @param int   $limit Limit count.
	 * @return array
	 */
	private function filter_upcoming_fixtures( $fixtures, $limit ) {
		$now      = time();
		$upcoming = array();

		foreach ( $fixtures as $fixture ) {
			if ( empty( $fixture['starting_at'] ) ) {
				continue;
			}
			$timestamp = strtotime( $fixture['starting_at'] );
			if ( ! $timestamp ) {
				continue;
			}
			if ( $timestamp >= $now ) {
				$fixture['_aps_ts'] = $timestamp;
				$upcoming[]         = $fixture;
			}
		}

		usort(
			$upcoming,
			function ( $a, $b ) {
				return ( $a['_aps_ts'] ?? 0 ) <=> ( $b['_aps_ts'] ?? 0 );
			}
		);

		$upcoming = array_slice( $upcoming, 0, $limit );

		foreach ( $upcoming as $index => $fixture ) {
			unset( $upcoming[ $index ]['_aps_ts'] );
		}

		return $upcoming;
	}

	/**
	 * Build display list: last N finished (past) + next M upcoming fixtures, sorted by date.
	 *
	 * @param array $fixtures Fixtures list (sorted by date asc).
	 * @param int   $limit_upcoming Max number of upcoming fixtures.
	 * @param int   $limit_finished Max number of past (finished) fixtures.
	 * @return array
	 */
	/**
	 * Split fixtures into finished (past) and upcoming, for tabs with pagination.
	 *
	 * @param array $fixtures Fixtures list (sorted by date).
	 * @param int   $limit_upcoming Max number of upcoming fixtures.
	 * @param int   $limit_finished Max number of past (finished) fixtures.
	 * @return array{finished: array, upcoming: array}
	 */
	private function get_schedule_finished_and_upcoming( $fixtures, $limit_upcoming = 20, $limit_finished = 50 ) {
		$now    = time();
		$past   = array();
		$future = array();

		foreach ( $fixtures as $fixture ) {
			if ( empty( $fixture['starting_at'] ) ) {
				continue;
			}
			$ts = strtotime( $fixture['starting_at'] );
			if ( ! $ts ) {
				continue;
			}
			$fixture['_aps_ts'] = $ts;
			if ( $ts < $now ) {
				$past[] = $fixture;
			} else {
				$future[] = $fixture;
			}
		}

		// Jogos realizados: mais recente primeiro.
		usort( $past, function ( $a, $b ) {
			return ( $b['_aps_ts'] ?? 0 ) <=> ( $a['_aps_ts'] ?? 0 );
		} );
		$past = array_slice( $past, 0, $limit_finished );
		foreach ( $past as $i => $f ) {
			unset( $past[ $i ]['_aps_ts'] );
		}

		// Jogos por realizar: próximo jogo primeiro.
		usort( $future, function ( $a, $b ) {
			return ( $a['_aps_ts'] ?? 0 ) <=> ( $b['_aps_ts'] ?? 0 );
		} );
		$future = array_slice( $future, 0, $limit_upcoming );
		foreach ( $future as $i => $f ) {
			unset( $future[ $i ]['_aps_ts'] );
		}

		return array(
			'finished' => $past,
			'upcoming' => $future,
		);
	}

	/**
	 * Extract standings rows from standings response.
	 *
	 * @param array $data Response data.
	 * @return array
	 */
	private function extract_standings_rows( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return array();
		}

		if ( isset( $data['standings'] ) && is_array( $data['standings'] ) ) {
			return $data['standings'];
		}

		if ( isset( $data[0]['standings'] ) && is_array( $data[0]['standings'] ) ) {
			$rows = array();
			foreach ( $data as $group ) {
				if ( ! empty( $group['standings'] ) && is_array( $group['standings'] ) ) {
					$rows = array_merge( $rows, $group['standings'] );
				}
			}
			return $rows;
		}

		if ( isset( $data[0] ) && is_array( $data[0] ) && isset( $data[0]['position'] ) ) {
			return $data;
		}

		return array();
	}

	/**
	 * Extract last 10 seasons for league.
	 *
	 * @param array $response League response.
	 * @return array
	 */
	private function extract_league_seasons( $response ) {
		$seasons = $response['data']['seasons'] ?? $response['data']['season'] ?? array();
		if ( isset( $seasons['data'] ) && is_array( $seasons['data'] ) ) {
			$seasons = $seasons['data'];
		}

		if ( empty( $seasons ) || ! is_array( $seasons ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $seasons as $season ) {
			if ( empty( $season['id'] ) ) {
				continue;
			}
			$normalized[] = array(
				'id'    => (int) $season['id'],
				'label' => $this->format_season_label( $season ),
				'start' => $season['starting_at'] ?? $season['start_date'] ?? '',
			);
		}

		usort(
			$normalized,
			function ( $a, $b ) {
				$time_a = $a['start'] ? strtotime( $a['start'] ) : 0;
				$time_b = $b['start'] ? strtotime( $b['start'] ) : 0;
				return $time_b <=> $time_a;
			}
		);

		$normalized = array_slice( $normalized, 0, 10 );

		foreach ( $normalized as $index => $season ) {
			if ( empty( $season['label'] ) ) {
				$normalized[ $index ]['label'] = (string) $season['id'];
			}
			unset( $normalized[ $index ]['start'] );
		}

		return $normalized;
	}

	/**
	 * Format season label.
	 *
	 * @param array $season Season data.
	 * @return string
	 */
	private function format_season_label( $season ) {
		if ( ! empty( $season['name'] ) ) {
			return $season['name'];
		}

		if ( ! empty( $season['year'] ) ) {
			return (string) $season['year'];
		}

		$start = $season['starting_at'] ?? $season['start_date'] ?? '';
		$end = $season['ending_at'] ?? $season['end_date'] ?? '';
		if ( $start && $end ) {
			$start_year = gmdate( 'Y', strtotime( $start ) );
			$end_year = gmdate( 'Y', strtotime( $end ) );
			if ( $start_year && $end_year ) {
				return $start_year . '/' . $end_year;
			}
		}

		return '';
	}

	/**
	 * Resolve standings stat value from multiple shapes.
	 *
	 * @param array $row Standings row.
	 * @param array $keys Direct keys to check.
	 * @param array $detail_aliases Detail label aliases.
	 * @return string|int
	 */
	private function resolve_standings_value( $row, $keys, $detail_aliases, $type_ids = array() ) {
		foreach ( $keys as $key ) {
			if ( isset( $row[ $key ] ) && '' !== $row[ $key ] ) {
				return $row[ $key ];
			}
		}

		$containers = array( 'overall', 'stats', 'data' );
		foreach ( $containers as $container_key ) {
			if ( isset( $row[ $container_key ] ) && is_array( $row[ $container_key ] ) ) {
				$container = $row[ $container_key ];
				if ( $this->is_assoc_array( $container ) ) {
					foreach ( $keys as $key ) {
						if ( isset( $container[ $key ] ) && '' !== $container[ $key ] ) {
							return $container[ $key ];
						}
					}
				}
			}
		}

		$details = $row['details'] ?? $row['stats'] ?? $row['overall'] ?? array();
		if ( is_array( $details ) && ! $this->is_assoc_array( $details ) ) {
			foreach ( $details as $detail ) {
				if ( ! is_array( $detail ) ) {
					continue;
				}
				if ( ! empty( $type_ids ) && isset( $detail['type_id'] ) && in_array( (int) $detail['type_id'], $type_ids, true ) ) {
					if ( isset( $detail['value'] ) && '' !== $detail['value'] ) {
						return $detail['value'];
					}
					if ( isset( $detail['data']['value'] ) && '' !== $detail['data']['value'] ) {
						return $detail['data']['value'];
					}
				}
				$label = $detail['type'] ?? $detail['name'] ?? $detail['label'] ?? $detail['description'] ?? '';
				$label = $this->normalize_standings_label( $label );
				if ( in_array( $label, $detail_aliases, true ) ) {
					if ( isset( $detail['value'] ) && '' !== $detail['value'] ) {
						return $detail['value'];
					}
					if ( isset( $detail['data']['value'] ) && '' !== $detail['data']['value'] ) {
						return $detail['data']['value'];
					}
				}
			}
		}

		return '';
	}

	/**
	 * Normalize label for standings details.
	 *
	 * @param string $label Raw label.
	 * @return string
	 */
	private function normalize_standings_label( $label ) {
		$label = strtolower( (string) $label );
		$label = preg_replace( '/[^a-z0-9]+/', '_', $label );
		return trim( $label, '_' );
	}

	/**
	 * Check if array is associative.
	 *
	 * @param array $array Input array.
	 * @return bool
	 */
	private function is_assoc_array( $array ) {
		if ( array() === $array ) {
			return false;
		}
		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}
}

if ( ! function_exists( 'aps_render_component' ) ) {
	/**
	 * Render a component.
	 *
	 * @param string $component_id Component key.
	 * @param array  $args Component args.
	 * @return string
	 */
	function aps_render_component( $component_id, $args = array() ) {
		return APS_Components::get_instance()->render_component( $component_id, $args );
	}
}
