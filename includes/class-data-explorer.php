<?php
/**
 * Data Explorer Class
 *
 * Provides interface for exploring API data directly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_Data_Explorer {
	
	/**
	 * Instance
	 *
	 * @var APS_Data_Explorer
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 *
	 * @return APS_Data_Explorer
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
		add_action( 'wp_ajax_aps_fetch_api_data', array( $this, 'ajax_fetch_api_data' ) );
		add_action( 'wp_ajax_aps_fetch_team_bundle', array( $this, 'ajax_fetch_team_bundle' ) );
		add_action( 'wp_ajax_aps_fetch_widget_calendar', array( $this, 'ajax_fetch_widget_calendar' ) );
		add_action( 'wp_ajax_aps_fetch_widget_next_fixture', array( $this, 'ajax_fetch_widget_next_fixture' ) );
		add_action( 'wp_ajax_aps_fetch_widget_results', array( $this, 'ajax_fetch_widget_results' ) );
		add_action( 'wp_ajax_aps_fetch_widget_squad', array( $this, 'ajax_fetch_widget_squad' ) );
		add_action( 'wp_ajax_aps_fetch_widget_player', array( $this, 'ajax_fetch_widget_player' ) );
		add_action( 'wp_ajax_aps_fetch_widget_injuries', array( $this, 'ajax_fetch_widget_injuries' ) );
		add_action( 'wp_ajax_aps_fetch_widget_match_center', array( $this, 'ajax_fetch_widget_match_center' ) );
		add_action( 'wp_ajax_aps_fetch_widget_standings', array( $this, 'ajax_fetch_widget_standings' ) );
		add_action( 'wp_ajax_aps_fetch_widget_topscorers', array( $this, 'ajax_fetch_widget_topscorers' ) );
		add_action( 'wp_ajax_aps_fetch_widget_h2h', array( $this, 'ajax_fetch_widget_h2h' ) );
	}
	
	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'aps-sportmonks',
			__( 'Data Explorer', 'api-sportmonks' ),
			__( 'Data Explorer', 'api-sportmonks' ),
			'manage_options',
			'aps-data-explorer',
			array( $this, 'render_page' )
		);
	}
	
	/**
	 * Enqueue scripts
	 *
	 * @param string $hook Current admin page
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'sportmonks_page_aps-data-explorer' !== $hook ) {
			return;
		}
		
		wp_enqueue_script( 'aps-data-explorer', APS_SMONKS_PLUGIN_URL . 'assets/js/data-explorer.js', array( 'jquery' ), APS_SMONKS_VERSION, true );
		wp_enqueue_style( 'aps-data-explorer', APS_SMONKS_PLUGIN_URL . 'assets/css/data-explorer.css', array(), APS_SMONKS_VERSION );
		
		wp_localize_script( 'aps-data-explorer', 'apsDataExplorer', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'aps_data_explorer_nonce' ),
		) );
	}
	
	/**
	 * Render page
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$match_id = isset( $_GET['match_id'] ) ? absint( $_GET['match_id'] ) : 0;
		$teams = get_option( 'aps_smonks_teams', array() );
		
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="aps-data-explorer">
				<div class="aps-explorer-form">
					<h2><?php _e( 'Equipas Configuradas', 'api-sportmonks' ); ?></h2>
					<?php if ( empty( $teams ) ) : ?>
						<p><?php _e( 'Ainda nao existem equipas configuradas.', 'api-sportmonks' ); ?></p>
					<?php else : ?>
						<div class="aps-team-bundle">
							<label for="aps-team-bundle-select"><?php _e( 'Selecionar equipa', 'api-sportmonks' ); ?></label>
							<select id="aps-team-bundle-select" class="regular-text">
								<?php foreach ( $teams as $team ) : ?>
									<option value="<?php echo esc_attr( $team['team_id'] ); ?>">
										<?php echo esc_html( $team['team_name'] . ' (#' . $team['team_id'] . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<button type="button" id="aps-team-bundle-fetch" class="button button-primary">
								<?php _e( 'Carregar dados completos', 'api-sportmonks' ); ?>
							</button>
						</div>
						<div id="aps-team-bundle-response" class="aps-team-bundle-response" style="display: none;">
							<h3><?php _e( 'Dados da Equipa', 'api-sportmonks' ); ?></h3>
							<div class="aps-bundle-section" data-section="team">
								<h4><?php _e( 'Equipa', 'api-sportmonks' ); ?></h4>
								<pre></pre>
								<button type="button" class="button aps-copy-section"><?php _e( 'Copiar', 'api-sportmonks' ); ?></button>
							</div>
							<div class="aps-bundle-section" data-section="fixtures">
								<h4><?php _e( 'Fixtures', 'api-sportmonks' ); ?></h4>
								<pre></pre>
								<button type="button" class="button aps-copy-section"><?php _e( 'Copiar', 'api-sportmonks' ); ?></button>
							</div>
							<div class="aps-bundle-section" data-section="squad">
								<h4><?php _e( 'Plantel', 'api-sportmonks' ); ?></h4>
								<pre></pre>
								<button type="button" class="button aps-copy-section"><?php _e( 'Copiar', 'api-sportmonks' ); ?></button>
							</div>
							<div class="aps-bundle-section" data-section="injuries">
								<h4><?php _e( 'Lesoes', 'api-sportmonks' ); ?></h4>
								<pre></pre>
								<button type="button" class="button aps-copy-section"><?php _e( 'Copiar', 'api-sportmonks' ); ?></button>
							</div>
						</div>
					<?php endif; ?>
				</div>
				<div class="aps-explorer-form aps-widget-panel">
					<h2><?php _e( 'Painel de Widgets', 'api-sportmonks' ); ?></h2>
					<div class="aps-widget-grid">
						<div class="aps-widget-card" data-widget="calendar">
							<h3><?php _e( 'Calendario & Resultados', 'api-sportmonks' ); ?></h3>
							<label><?php _e( 'Team ID', 'api-sportmonks' ); ?></label>
							<input type="number" class="aps-widget-team-id" />
							<label><?php _e( 'Data Inicio', 'api-sportmonks' ); ?></label>
							<input type="date" class="aps-widget-start" />
							<label><?php _e( 'Data Fim', 'api-sportmonks' ); ?></label>
							<input type="date" class="aps-widget-end" />
							<button type="button" class="button aps-fetch-widget" data-action="aps_fetch_widget_calendar"><?php _e( 'Carregar', 'api-sportmonks' ); ?></button>
							<pre class="aps-widget-response"></pre>
						</div>
						<div class="aps-widget-card" data-widget="next-fixture">
							<h3><?php _e( 'Proximo Jogo', 'api-sportmonks' ); ?></h3>
							<label><?php _e( 'Team ID', 'api-sportmonks' ); ?></label>
							<input type="number" class="aps-widget-team-id" />
							<button type="button" class="button aps-fetch-widget" data-action="aps_fetch_widget_next_fixture"><?php _e( 'Carregar', 'api-sportmonks' ); ?></button>
							<pre class="aps-widget-response"></pre>
						</div>
						<div class="aps-widget-card" data-widget="results">
							<h3><?php _e( 'Resultados Historicos', 'api-sportmonks' ); ?></h3>
							<label><?php _e( 'Team ID', 'api-sportmonks' ); ?></label>
							<input type="number" class="aps-widget-team-id" />
							<label><?php _e( 'Data Inicio', 'api-sportmonks' ); ?></label>
							<input type="date" class="aps-widget-start" />
							<label><?php _e( 'Data Fim', 'api-sportmonks' ); ?></label>
							<input type="date" class="aps-widget-end" />
							<button type="button" class="button aps-fetch-widget" data-action="aps_fetch_widget_results"><?php _e( 'Carregar', 'api-sportmonks' ); ?></button>
							<pre class="aps-widget-response"></pre>
						</div>
						<div class="aps-widget-card" data-widget="squad">
							<h3><?php _e( 'Plantel', 'api-sportmonks' ); ?></h3>
							<label><?php _e( 'Team ID', 'api-sportmonks' ); ?></label>
							<input type="number" class="aps-widget-team-id" />
							<button type="button" class="button aps-fetch-widget" data-action="aps_fetch_widget_squad"><?php _e( 'Carregar', 'api-sportmonks' ); ?></button>
							<pre class="aps-widget-response"></pre>
						</div>
						<div class="aps-widget-card" data-widget="player">
							<h3><?php _e( 'Perfil do Jogador', 'api-sportmonks' ); ?></h3>
							<label><?php _e( 'Player ID', 'api-sportmonks' ); ?></label>
							<input type="number" class="aps-widget-player-id" />
							<button type="button" class="button aps-fetch-widget" data-action="aps_fetch_widget_player"><?php _e( 'Carregar', 'api-sportmonks' ); ?></button>
							<pre class="aps-widget-response"></pre>
						</div>
						<div class="aps-widget-card" data-widget="injuries">
							<h3><?php _e( 'Boletim Clinico', 'api-sportmonks' ); ?></h3>
							<label><?php _e( 'Team ID', 'api-sportmonks' ); ?></label>
							<input type="number" class="aps-widget-team-id" />
							<button type="button" class="button aps-fetch-widget" data-action="aps_fetch_widget_injuries"><?php _e( 'Carregar', 'api-sportmonks' ); ?></button>
							<pre class="aps-widget-response"></pre>
						</div>
						<div class="aps-widget-card" data-widget="match-center">
							<h3><?php _e( 'Match Center', 'api-sportmonks' ); ?></h3>
							<label><?php _e( 'Fixture ID', 'api-sportmonks' ); ?></label>
							<input type="number" class="aps-widget-fixture-id" />
							<button type="button" class="button aps-fetch-widget" data-action="aps_fetch_widget_match_center"><?php _e( 'Carregar', 'api-sportmonks' ); ?></button>
							<pre class="aps-widget-response"></pre>
						</div>
						<div class="aps-widget-card" data-widget="standings">
							<h3><?php _e( 'Classificacoes', 'api-sportmonks' ); ?></h3>
							<label><?php _e( 'League ID', 'api-sportmonks' ); ?></label>
							<input type="number" class="aps-widget-league-id" />
							<button type="button" class="button aps-fetch-widget" data-action="aps_fetch_widget_standings"><?php _e( 'Carregar', 'api-sportmonks' ); ?></button>
							<pre class="aps-widget-response"></pre>
						</div>
						<div class="aps-widget-card" data-widget="topscorers">
							<h3><?php _e( 'Topscorers', 'api-sportmonks' ); ?></h3>
							<label><?php _e( 'League ID', 'api-sportmonks' ); ?></label>
							<input type="number" class="aps-widget-league-id" />
							<button type="button" class="button aps-fetch-widget" data-action="aps_fetch_widget_topscorers"><?php _e( 'Carregar', 'api-sportmonks' ); ?></button>
							<pre class="aps-widget-response"></pre>
						</div>
						<div class="aps-widget-card" data-widget="h2h">
							<h3><?php _e( 'H2H', 'api-sportmonks' ); ?></h3>
							<label><?php _e( 'Team 1 ID', 'api-sportmonks' ); ?></label>
							<input type="number" class="aps-widget-team-id" />
							<label><?php _e( 'Team 2 ID', 'api-sportmonks' ); ?></label>
							<input type="number" class="aps-widget-team-id-2" />
							<button type="button" class="button aps-fetch-widget" data-action="aps_fetch_widget_h2h"><?php _e( 'Carregar', 'api-sportmonks' ); ?></button>
							<pre class="aps-widget-response"></pre>
						</div>
					</div>
				</div>

				<div class="aps-explorer-form">
					<h2><?php _e( 'Explorar API', 'api-sportmonks' ); ?></h2>
					
					<form id="aps-api-form">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="aps-endpoint"><?php _e( 'Endpoint', 'api-sportmonks' ); ?></label>
								</th>
								<td>
									<select id="aps-endpoint" name="endpoint" class="regular-text">
										<option value=""><?php _e( '-- Selecionar --', 'api-sportmonks' ); ?></option>
										<optgroup label="<?php _e( 'Equipas', 'api-sportmonks' ); ?>">
											<option value="teams/{id}"><?php _e( 'Dados da Equipa', 'api-sportmonks' ); ?></option>
											<option value="teams/{id}/fixtures"><?php _e( 'Jogos da Equipa', 'api-sportmonks' ); ?></option>
										</optgroup>
										<optgroup label="<?php _e( 'Jogos', 'api-sportmonks' ); ?>">
											<option value="fixtures/{id}" <?php selected( $match_id > 0 ); ?>><?php _e( 'Detalhes do Jogo', 'api-sportmonks' ); ?></option>
										</optgroup>
										<optgroup label="<?php _e( 'Ligas', 'api-sportmonks' ); ?>">
											<option value="leagues/{id}"><?php _e( 'Dados da Liga', 'api-sportmonks' ); ?></option>
											<option value="standings/seasons/latest/leagues/{id}"><?php _e( 'Classificação', 'api-sportmonks' ); ?></option>
											<option value="topscorers/seasons/latest/leagues/{id}"><?php _e( 'Top Marcadores', 'api-sportmonks' ); ?></option>
										</optgroup>
										<optgroup label="<?php _e( 'Head to Head', 'api-sportmonks' ); ?>">
											<option value="teams/{id}/h2h/{id2}"><?php _e( 'Histórico entre Equipas', 'api-sportmonks' ); ?></option>
										</optgroup>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="aps-resource-id"><?php _e( 'ID do Recurso', 'api-sportmonks' ); ?></label>
								</th>
								<td>
									<input type="number" id="aps-resource-id" name="resource_id" value="<?php echo esc_attr( $match_id ); ?>" class="regular-text" />
									<p class="description"><?php _e( 'ID da equipa, jogo ou liga', 'api-sportmonks' ); ?></p>
								</td>
							</tr>
							<tr id="aps-resource-id2-row" style="display: none;">
								<th scope="row">
									<label for="aps-resource-id2"><?php _e( 'ID do Recurso 2', 'api-sportmonks' ); ?></label>
								</th>
								<td>
									<input type="number" id="aps-resource-id2" name="resource_id2" value="" class="regular-text" />
									<p class="description"><?php _e( 'Segundo ID (para H2H)', 'api-sportmonks' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="aps-includes"><?php _e( 'Includes', 'api-sportmonks' ); ?></label>
								</th>
								<td>
									<input type="text" id="aps-includes" name="includes" value="" class="regular-text" />
									<p class="description"><?php _e( 'Separar múltiplos includes com ponto e vírgula (;)', 'api-sportmonks' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="aps-filters"><?php _e( 'Filtros', 'api-sportmonks' ); ?></label>
								</th>
								<td>
									<input type="text" id="aps-filters" name="filters" value="" class="regular-text" />
									<p class="description"><?php _e( 'Ex: filters=fixtures.state:NS', 'api-sportmonks' ); ?></p>
								</td>
							</tr>
						</table>
						
						<p class="submit">
							<button type="submit" class="button button-primary"><?php _e( 'Obter Dados', 'api-sportmonks' ); ?></button>
							<button type="button" id="aps-copy-response" class="button" style="display: none;"><?php _e( 'Copiar Resposta', 'api-sportmonks' ); ?></button>
						</p>
					</form>
				</div>
				
				<div class="aps-explorer-response" id="aps-api-response" style="display: none;">
					<h2><?php _e( 'Resposta da API', 'api-sportmonks' ); ?></h2>
					<pre id="aps-response-content"></pre>
				</div>
			</div>
		</div>
		<?php
	}
	
	/**
	 * AJAX: Fetch API data
	 */
	public function ajax_fetch_api_data() {
		check_ajax_referer( 'aps_data_explorer_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'api-sportmonks' ) ) );
		}
		
		$endpoint = sanitize_text_field( $_POST['endpoint'] ?? '' );
		$resource_id = absint( $_POST['resource_id'] ?? 0 );
		$resource_id2 = absint( $_POST['resource_id2'] ?? 0 );
		$includes = sanitize_text_field( $_POST['includes'] ?? '' );
		$filters = sanitize_text_field( $_POST['filters'] ?? '' );
		
		if ( empty( $endpoint ) || empty( $resource_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Endpoint e ID são obrigatórios.', 'api-sportmonks' ) ) );
		}
		
		// Replace placeholders
		$endpoint = str_replace( '{id}', $resource_id, $endpoint );
		$endpoint = str_replace( '{id2}', $resource_id2, $endpoint );
		
		// Parse includes
		$includes_array = array();
		if ( ! empty( $includes ) ) {
			$includes_array = array_map( 'trim', explode( ';', $includes ) );
		}
		
		// Parse filters
		$params = array();
		if ( ! empty( $filters ) ) {
			parse_str( $filters, $params );
		}
		
		// Make API request
		$api_client = APS_API_Client::get_instance();
		$response = $api_client->request( $endpoint, $params, $includes_array, false );
		
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array(
				'message' => $response->get_error_message(),
				'code'    => $response->get_error_code(),
			) );
		}
		
		wp_send_json_success( array(
			'data'   => $response,
			'json'   => wp_json_encode( $response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
		) );
	}

	/**
	 * AJAX: Fetch team bundle data
	 */
	public function ajax_fetch_team_bundle() {
		check_ajax_referer( 'aps_data_explorer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'api-sportmonks' ) ) );
		}

		$team_id = absint( $_POST['team_id'] ?? 0 );
		if ( empty( $team_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Team ID nao fornecido.', 'api-sportmonks' ) ) );
		}

		$api_client = APS_API_Client::get_instance();

		$team = $api_client->get_team( $team_id );
		$fixtures = $api_client->get_fixtures( $team_id, array(), array(), false );
		$squad = $api_client->get_team_players( $team_id, false );
		$injuries = $api_client->get_team_sidelined( $team_id, false );
		$transfers = $api_client->get_transfers_by_team( $team_id, array(), array(), false );

		$payload = array(
			'team'      => $team,
			'fixtures'  => $fixtures,
			'squad'     => $squad,
			'injuries'  => $injuries,
			'transfers' => $transfers,
		);

		wp_send_json_success( array(
			'data' => $payload,
			'json' => array(
				'team'      => $this->format_response( $team, __( 'Nao incluido no plano.', 'api-sportmonks' ) ),
				'fixtures'  => $this->format_response( $fixtures, __( 'Nao incluido no plano.', 'api-sportmonks' ) ),
				'squad'     => $this->format_response( $squad, __( 'Nao incluido no plano.', 'api-sportmonks' ) ),
				'injuries'  => $this->format_response( $injuries, __( 'Nao incluido no plano.', 'api-sportmonks' ) ),
				'transfers' => $this->format_response( $transfers, __( 'Nao incluido no plano.', 'api-sportmonks' ) ),
			),
		) );
	}

	/**
	 * Format response for display (handles 403)
	 *
	 * @param mixed  $response API response
	 * @param string $fallback_message Fallback message
	 * @return string
	 */
	private function format_response( $response, $fallback_message ) {
		if ( is_wp_error( $response ) ) {
			$error_data = $response->get_error_data( 'api_error' );
			if ( empty( $error_data ) ) {
				$error_data = $response->get_error_data();
			}
			$status = is_array( $error_data ) ? ( $error_data['status'] ?? 0 ) : 0;
			if ( 403 === (int) $status ) {
				return $fallback_message;
			}
			return $response->get_error_message();
		}

		return wp_json_encode( $response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * AJAX: Calendar widget
	 */
	public function ajax_fetch_widget_calendar() {
		$this->handle_widget_request( function( $payload ) {
			$team_id = absint( $payload['team_id'] ?? 0 );
			$start = sanitize_text_field( $payload['start'] ?? '' );
			$end = sanitize_text_field( $payload['end'] ?? '' );

			if ( ! $team_id || ! $start || ! $end ) {
				return new WP_Error( 'missing_params', __( 'Team ID e datas sao obrigatorios.', 'api-sportmonks' ) );
			}

			$api_client = APS_API_Client::get_instance();
			return $api_client->request( "fixtures/between/{$start}/{$end}/{$team_id}", array(), array( 'participants', 'scores', 'state' ), false );
		} );
	}

	/**
	 * AJAX: Next fixture widget
	 */
	public function ajax_fetch_widget_next_fixture() {
		$this->handle_widget_request( function( $payload ) {
			$team_id = absint( $payload['team_id'] ?? 0 );
			if ( ! $team_id ) {
				return new WP_Error( 'missing_params', __( 'Team ID e obrigatorio.', 'api-sportmonks' ) );
			}

			$start = gmdate( 'Y-m-d' );
			$end = gmdate( 'Y-m-d', strtotime( '+90 days' ) );

			$api_client = APS_API_Client::get_instance();
			$response = $api_client->request( "fixtures/between/{$start}/{$end}/{$team_id}", array( 'order' => 'asc', 'per_page' => 1 ), array( 'participants', 'scores', 'state', 'venue' ), false );
			return $response;
		} );
	}

	/**
	 * AJAX: Results widget
	 */
	public function ajax_fetch_widget_results() {
		$this->handle_widget_request( function( $payload ) {
			$team_id = absint( $payload['team_id'] ?? 0 );
			$start = sanitize_text_field( $payload['start'] ?? '' );
			$end = sanitize_text_field( $payload['end'] ?? '' );

			if ( ! $team_id || ! $start || ! $end ) {
				return new WP_Error( 'missing_params', __( 'Team ID e datas sao obrigatorios.', 'api-sportmonks' ) );
			}

			$api_client = APS_API_Client::get_instance();
			return $api_client->request( "fixtures/between/{$start}/{$end}/{$team_id}", array( 'order' => 'desc' ), array( 'participants', 'scores', 'state' ), false );
		} );
	}

	/**
	 * AJAX: Squad widget
	 */
	public function ajax_fetch_widget_squad() {
		$this->handle_widget_request( function( $payload ) {
			$team_id = absint( $payload['team_id'] ?? 0 );
			if ( ! $team_id ) {
				return new WP_Error( 'missing_params', __( 'Team ID e obrigatorio.', 'api-sportmonks' ) );
			}

			$api_client = APS_API_Client::get_instance();
			return $api_client->get_team_players( $team_id, false );
		} );
	}

	/**
	 * AJAX: Player profile widget
	 */
	public function ajax_fetch_widget_player() {
		$this->handle_widget_request( function( $payload ) {
			$player_id = absint( $payload['player_id'] ?? 0 );
			if ( ! $player_id ) {
				return new WP_Error( 'missing_params', __( 'Player ID e obrigatorio.', 'api-sportmonks' ) );
			}

			$api_client = APS_API_Client::get_instance();
			return $api_client->get_player( $player_id, array( 'statistics' ), false );
		} );
	}

	/**
	 * AJAX: Injuries widget
	 */
	public function ajax_fetch_widget_injuries() {
		$this->handle_widget_request( function( $payload ) {
			$team_id = absint( $payload['team_id'] ?? 0 );
			if ( ! $team_id ) {
				return new WP_Error( 'missing_params', __( 'Team ID e obrigatorio.', 'api-sportmonks' ) );
			}

			$api_client = APS_API_Client::get_instance();
			return $api_client->get_team_sidelined( $team_id, false );
		} );
	}

	/**
	 * AJAX: Match center widget
	 */
	public function ajax_fetch_widget_match_center() {
		$this->handle_widget_request( function( $payload ) {
			$fixture_id = absint( $payload['fixture_id'] ?? 0 );
			if ( ! $fixture_id ) {
				return new WP_Error( 'missing_params', __( 'Fixture ID e obrigatorio.', 'api-sportmonks' ) );
			}

			$api_client = APS_API_Client::get_instance();
			return $api_client->get_match( $fixture_id, array(), array( 'events', 'statistics', 'participants', 'scores', 'state' ), false );
		} );
	}

	/**
	 * AJAX: Standings widget
	 */
	public function ajax_fetch_widget_standings() {
		$this->handle_widget_request( function( $payload ) {
			$league_id = absint( $payload['league_id'] ?? 0 );
			if ( ! $league_id ) {
				return new WP_Error( 'missing_params', __( 'League ID e obrigatorio.', 'api-sportmonks' ) );
			}

			$api_client = APS_API_Client::get_instance();
			return $api_client->get_league_standings( $league_id, array(), false );
		} );
	}

	/**
	 * AJAX: Topscorers widget
	 */
	public function ajax_fetch_widget_topscorers() {
		$this->handle_widget_request( function( $payload ) {
			$league_id = absint( $payload['league_id'] ?? 0 );
			if ( ! $league_id ) {
				return new WP_Error( 'missing_params', __( 'League ID e obrigatorio.', 'api-sportmonks' ) );
			}

			$api_client = APS_API_Client::get_instance();
			return $api_client->get_league_top_scorers( $league_id, array(), false );
		} );
	}

	/**
	 * AJAX: H2H widget
	 */
	public function ajax_fetch_widget_h2h() {
		$this->handle_widget_request( function( $payload ) {
			$team1 = absint( $payload['team_id'] ?? 0 );
			$team2 = absint( $payload['team_id2'] ?? 0 );
			if ( ! $team1 || ! $team2 ) {
				return new WP_Error( 'missing_params', __( 'IDs das equipas sao obrigatorios.', 'api-sportmonks' ) );
			}

			$api_client = APS_API_Client::get_instance();
			return $api_client->get_head_to_head( $team1, $team2, false );
		} );
	}

	/**
	 * Widget request helper
	 *
	 * @param callable $callback Callback
	 */
	private function handle_widget_request( $callback ) {
		check_ajax_referer( 'aps_data_explorer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'api-sportmonks' ) ) );
		}

		$response = call_user_func( $callback, $_POST );
		$message = __( 'Nao incluido no plano.', 'api-sportmonks' );

		wp_send_json_success( array(
			'json' => $this->format_response( $response, $message ),
		) );
	}
}

