<?php
/**
 * Shortcodes Class
 *
 * Renders frontend widgets for Sportmonks data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_Shortcodes {
	/**
	 * Instance
	 *
	 * @var APS_Shortcodes
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return APS_Shortcodes
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		$this->register_shortcodes();
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'aps-widgets', APS_SMONKS_PLUGIN_URL . 'assets/css/widgets.css', array(), APS_SMONKS_VERSION );
		wp_enqueue_script( 'aps-widgets', APS_SMONKS_PLUGIN_URL . 'assets/js/widgets.js', array(), APS_SMONKS_VERSION, true );
	}

	/**
	 * Register shortcodes
	 */
	private function register_shortcodes() {
		add_shortcode( 'aps_calendar', array( $this, 'render_calendar' ) );
		add_shortcode( 'aps_next_game', array( $this, 'render_next_game' ) );
		add_shortcode( 'aps_results', array( $this, 'render_results' ) );
		add_shortcode( 'aps_match_center', array( $this, 'render_match_center' ) );
		add_shortcode( 'aps_standings', array( $this, 'render_standings' ) );
		add_shortcode( 'aps_topscorers', array( $this, 'render_topscorers' ) );
		add_shortcode( 'aps_h2h', array( $this, 'render_h2h' ) );
		add_shortcode( 'aps_team_squad', array( $this, 'render_team_squad' ) );
		add_shortcode( 'aps_injuries', array( $this, 'render_injuries' ) );
		add_shortcode( 'aps_transfers', array( $this, 'render_transfers' ) );
		add_shortcode( 'aps_player_profile', array( $this, 'render_player_profile' ) );
	}

	/**
	 * Render calendar shortcode
	 */
	public function render_calendar( $atts ) {
		$atts = shortcode_atts(
			array(
				'team_id' => '',
				'from'    => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
				'to'      => gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
			),
			$atts
		);

		$data = array();
		if ( $atts['team_id'] ) {
			$params = array(
				'filters' => 'between:' . $atts['from'] . ',' . $atts['to'],
			);
			$response = APS_Theme_Helpers::get_team_fixtures( absint( $atts['team_id'] ), $params );
			$data = $response['data'] ?? array();
		} else {
			$response = APS_Theme_Helpers::get_fixtures_by_date_range( $atts['from'], $atts['to'] );
			$data = $response['data'] ?? array();
		}

		ob_start();
		?>
		<div class="aps-widget aps-calendar">
			<h3><?php esc_html_e( 'Calendario', 'api-sportmonks' ); ?></h3>
			<?php if ( empty( $data ) ) : ?>
				<p><?php esc_html_e( 'Sem jogos para o periodo selecionado.', 'api-sportmonks' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $data as $fixture ) : ?>
						<li>
							<span class="aps-date"><?php echo esc_html( $fixture['starting_at'] ?? '' ); ?></span>
							<span class="aps-fixture-name"><?php echo esc_html( $fixture['name'] ?? '' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render next game shortcode
	 */
	public function render_next_game( $atts ) {
		$atts = shortcode_atts(
			array(
				'team_id' => '',
			),
			$atts
		);

		if ( empty( $atts['team_id'] ) ) {
			return '<p>' . esc_html__( 'Team ID nao fornecido.', 'api-sportmonks' ) . '</p>';
		}

		$params = array(
			'filters' => 'from:' . gmdate( 'Y-m-d' ),
		);
		$response = APS_Theme_Helpers::get_team_fixtures( absint( $atts['team_id'] ), $params );
		$fixtures = $response['data'] ?? array();
		$next = ! empty( $fixtures ) ? $fixtures[0] : array();

		ob_start();
		?>
		<div class="aps-widget aps-next-game" data-start="<?php echo esc_attr( $next['starting_at'] ?? '' ); ?>">
			<h3><?php esc_html_e( 'Proximo Jogo', 'api-sportmonks' ); ?></h3>
			<?php if ( empty( $next ) ) : ?>
				<p><?php esc_html_e( 'Sem jogos futuros.', 'api-sportmonks' ); ?></p>
			<?php else : ?>
				<p class="aps-fixture-name"><?php echo esc_html( $next['name'] ?? '' ); ?></p>
				<p class="aps-fixture-date"><?php echo esc_html( $next['starting_at'] ?? '' ); ?></p>
				<div class="aps-countdown" aria-live="polite"></div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render results shortcode
	 */
	public function render_results( $atts ) {
		$atts = shortcode_atts(
			array(
				'team_id' => '',
				'from'    => gmdate( 'Y-m-d', strtotime( '-90 days' ) ),
				'to'      => gmdate( 'Y-m-d' ),
			),
			$atts
		);

		if ( empty( $atts['team_id'] ) ) {
			return '<p>' . esc_html__( 'Team ID nao fornecido.', 'api-sportmonks' ) . '</p>';
		}

		$params = array(
			'filters' => 'between:' . $atts['from'] . ',' . $atts['to'],
		);
		$response = APS_Theme_Helpers::get_team_fixtures( absint( $atts['team_id'] ), $params );
		$data = $response['data'] ?? array();

		ob_start();
		?>
		<div class="aps-widget aps-results">
			<h3><?php esc_html_e( 'Resultados', 'api-sportmonks' ); ?></h3>
			<?php if ( empty( $data ) ) : ?>
				<p><?php esc_html_e( 'Sem resultados para o periodo selecionado.', 'api-sportmonks' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $data as $fixture ) : ?>
						<li>
							<span class="aps-date"><?php echo esc_html( $fixture['starting_at'] ?? '' ); ?></span>
							<span class="aps-fixture-name"><?php echo esc_html( $fixture['name'] ?? '' ); ?></span>
							<span class="aps-fixture-score"><?php echo esc_html( $this->format_score( $fixture ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render match center shortcode
	 */
	public function render_match_center( $atts ) {
		$atts = shortcode_atts(
			array(
				'match_id' => '',
			),
			$atts
		);

		if ( empty( $atts['match_id'] ) ) {
			return '<p>' . esc_html__( 'Match ID nao fornecido.', 'api-sportmonks' ) . '</p>';
		}

		$response = APS_Theme_Helpers::get_full_match_details( absint( $atts['match_id'] ) );
		if ( is_wp_error( $response ) ) {
			return '<p>' . esc_html__( 'Erro ao carregar match center.', 'api-sportmonks' ) . '</p>';
		}

		$data = $response['data'] ?? array();
		$events = $data['events'] ?? array();
		$stats = $data['statistics'] ?? array();

		ob_start();
		?>
		<div class="aps-widget aps-match-center">
			<h3><?php esc_html_e( 'Match Center', 'api-sportmonks' ); ?></h3>
			<p class="aps-fixture-name"><?php echo esc_html( $data['name'] ?? '' ); ?></p>
			<div class="aps-match-events">
				<h4><?php esc_html_e( 'Eventos', 'api-sportmonks' ); ?></h4>
				<?php if ( empty( $events ) ) : ?>
					<p><?php esc_html_e( 'Sem eventos disponiveis.', 'api-sportmonks' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( $events as $event ) : ?>
							<li>
								<span class="aps-minute"><?php echo esc_html( $event['minute'] ?? '' ); ?>'</span>
								<span class="aps-event"><?php echo esc_html( $event['player_name'] ?? '' ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<div class="aps-match-stats">
				<h4><?php esc_html_e( 'Estatisticas', 'api-sportmonks' ); ?></h4>
				<?php if ( empty( $stats ) ) : ?>
					<p><?php esc_html_e( 'Sem estatisticas disponiveis.', 'api-sportmonks' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( $stats as $stat ) : ?>
							<li>
								<span class="aps-stat-type"><?php echo esc_html( $stat['type_id'] ?? '' ); ?></span>
								<span class="aps-stat-value"><?php echo esc_html( $stat['data']['value'] ?? '' ); ?></span>
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
	 * Render standings shortcode
	 */
	public function render_standings( $atts ) {
		$atts = shortcode_atts(
			array(
				'league_id' => '',
			),
			$atts
		);

		if ( empty( $atts['league_id'] ) ) {
			return '<p>' . esc_html__( 'League ID nao fornecido.', 'api-sportmonks' ) . '</p>';
		}

		$response = APS_Theme_Helpers::get_league_standings( absint( $atts['league_id'] ) );
		$data = $response['data'] ?? array();

		ob_start();
		?>
		<div class="aps-widget aps-standings">
			<h3><?php esc_html_e( 'Classificacao', 'api-sportmonks' ); ?></h3>
			<?php if ( empty( $data ) ) : ?>
				<p><?php esc_html_e( 'Sem dados de classificacao.', 'api-sportmonks' ); ?></p>
			<?php else : ?>
				<table>
					<thead>
						<tr>
							<th>#</th>
							<th><?php esc_html_e( 'Equipa', 'api-sportmonks' ); ?></th>
							<th><?php esc_html_e( 'Pts', 'api-sportmonks' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['position'] ?? '' ); ?></td>
								<td><?php echo esc_html( $row['participant']['name'] ?? '' ); ?></td>
								<td><?php echo esc_html( $row['points'] ?? '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render topscorers shortcode
	 */
	public function render_topscorers( $atts ) {
		$atts = shortcode_atts(
			array(
				'league_id' => '',
				'limit'     => 10,
			),
			$atts
		);

		if ( empty( $atts['league_id'] ) ) {
			return '<p>' . esc_html__( 'League ID nao fornecido.', 'api-sportmonks' ) . '</p>';
		}

		$response = APS_Theme_Helpers::get_league_top_scorers( absint( $atts['league_id'] ), absint( $atts['limit'] ) );
		$data = $response['data'] ?? array();

		ob_start();
		?>
		<div class="aps-widget aps-topscorers">
			<h3><?php esc_html_e( 'Melhores Marcadores', 'api-sportmonks' ); ?></h3>
			<?php if ( empty( $data ) ) : ?>
				<p><?php esc_html_e( 'Sem dados de marcadores.', 'api-sportmonks' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $data as $row ) : ?>
						<li>
							<?php echo esc_html( $row['participant']['name'] ?? '' ); ?>
							<span class="aps-goals"><?php echo esc_html( $row['goals'] ?? '' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render H2H shortcode
	 */
	public function render_h2h( $atts ) {
		$atts = shortcode_atts(
			array(
				'team1_id' => '',
				'team2_id' => '',
			),
			$atts
		);

		if ( empty( $atts['team1_id'] ) || empty( $atts['team2_id'] ) ) {
			return '<p>' . esc_html__( 'IDs de equipas nao fornecidos.', 'api-sportmonks' ) . '</p>';
		}

		$response = APS_Theme_Helpers::get_head_to_head( absint( $atts['team1_id'] ), absint( $atts['team2_id'] ) );
		$data = $response['data']['fixtures'] ?? array();

		ob_start();
		?>
		<div class="aps-widget aps-h2h">
			<h3><?php esc_html_e( 'Confronto Direto', 'api-sportmonks' ); ?></h3>
			<?php if ( empty( $data ) ) : ?>
				<p><?php esc_html_e( 'Sem historico disponivel.', 'api-sportmonks' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $data as $fixture ) : ?>
						<li>
							<span class="aps-date"><?php echo esc_html( $fixture['starting_at'] ?? '' ); ?></span>
							<span class="aps-fixture-name"><?php echo esc_html( $fixture['name'] ?? '' ); ?></span>
							<span class="aps-fixture-score"><?php echo esc_html( $this->format_score( $fixture ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render team squad shortcode
	 */
	public function render_team_squad( $atts ) {
		$atts = shortcode_atts(
			array(
				'team_id'   => '',
				'use_cache' => 1,
			),
			$atts
		);

		if ( empty( $atts['team_id'] ) ) {
			return '<p>' . esc_html__( 'Team ID nao fornecido.', 'api-sportmonks' ) . '</p>';
		}

		$team_id = absint( $atts['team_id'] );
		$data = array();
		if ( (int) $atts['use_cache'] === 1 ) {
			$data = APS_Theme_Helpers::get_cached_team_squad( $team_id );
		}

		if ( empty( $data ) ) {
			$response = APS_Theme_Helpers::get_team_players( $team_id, false );
			$data = $response['data']['players'] ?? array();
		} else {
			$data = $data['data']['players'] ?? $data['data'] ?? array();
		}

		ob_start();
		?>
		<div class="aps-widget aps-team-squad">
			<h3><?php esc_html_e( 'Plantel', 'api-sportmonks' ); ?></h3>
			<?php if ( empty( $data ) ) : ?>
				<p><?php esc_html_e( 'Sem dados de plantel.', 'api-sportmonks' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $data as $player ) : ?>
						<li><?php echo esc_html( $player['display_name'] ?? $player['name'] ?? $player['player_name'] ?? '' ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render injuries shortcode
	 */
	public function render_injuries( $atts ) {
		$atts = shortcode_atts(
			array(
				'team_id'   => '',
				'use_cache' => 1,
			),
			$atts
		);

		if ( empty( $atts['team_id'] ) ) {
			return '<p>' . esc_html__( 'Team ID nao fornecido.', 'api-sportmonks' ) . '</p>';
		}

		$team_id = absint( $atts['team_id'] );
		$data = array();
		if ( (int) $atts['use_cache'] === 1 ) {
			$data = APS_Theme_Helpers::get_cached_team_injuries( $team_id );
		}

		if ( empty( $data ) ) {
			$response = APS_Theme_Helpers::get_team_sidelined( $team_id, false );
			$data = $response['data']['sidelined'] ?? $response['data']['sidelineds'] ?? array();
		} else {
			$data = $data['data']['sidelined'] ?? $data['data']['sidelineds'] ?? $data['data'] ?? array();
		}

		ob_start();
		?>
		<div class="aps-widget aps-injuries">
			<h3><?php esc_html_e( 'Boletim Clinico', 'api-sportmonks' ); ?></h3>
			<?php if ( empty( $data ) ) : ?>
				<p><?php esc_html_e( 'Sem lesoes ativas.', 'api-sportmonks' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $data as $injury ) : ?>
						<li>
							<?php echo esc_html( $injury['player']['name'] ?? $injury['player_name'] ?? '' ); ?>
							<span class="aps-injury-info"><?php echo esc_html( $injury['category'] ?? $injury['description'] ?? '' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render transfers shortcode
	 */
	public function render_transfers( $atts ) {
		$atts = shortcode_atts(
			array(
				'team_id'   => '',
				'use_cache' => 1,
			),
			$atts
		);

		if ( empty( $atts['team_id'] ) ) {
			return '<p>' . esc_html__( 'Team ID nao fornecido.', 'api-sportmonks' ) . '</p>';
		}

		$team_id = absint( $atts['team_id'] );
		$data = array();
		if ( (int) $atts['use_cache'] === 1 ) {
			$data = APS_Theme_Helpers::get_cached_team_transfers( $team_id );
		}

		if ( empty( $data ) ) {
			$response = APS_Theme_Helpers::get_transfers_by_team( $team_id );
			$data = $response['data'] ?? array();
		}

		ob_start();
		?>
		<div class="aps-widget aps-transfers">
			<h3><?php esc_html_e( 'Transferencias', 'api-sportmonks' ); ?></h3>
			<?php if ( empty( $data ) ) : ?>
				<p><?php esc_html_e( 'Sem transferencias recentes.', 'api-sportmonks' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $data as $transfer ) : ?>
						<li>
							<span><?php echo esc_html( $transfer['player_name'] ?? '' ); ?></span>
							<span class="aps-transfer-info"><?php echo esc_html( $transfer['type'] ?? '' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render player profile shortcode
	 */
	public function render_player_profile( $atts ) {
		$atts = shortcode_atts(
			array(
				'player_id' => '',
			),
			$atts
		);

		if ( empty( $atts['player_id'] ) ) {
			return '<p>' . esc_html__( 'Player ID nao fornecido.', 'api-sportmonks' ) . '</p>';
		}

		$api_client = APS_API_Client::get_instance();
		$response = $api_client->get_player( absint( $atts['player_id'] ) );
		$data = $response['data'] ?? array();

		ob_start();
		?>
		<div class="aps-widget aps-player-profile">
			<h3><?php esc_html_e( 'Perfil do Jogador', 'api-sportmonks' ); ?></h3>
			<?php if ( empty( $data ) ) : ?>
				<p><?php esc_html_e( 'Sem dados do jogador.', 'api-sportmonks' ); ?></p>
			<?php else : ?>
				<p class="aps-player-name"><?php echo esc_html( $data['name'] ?? '' ); ?></p>
				<?php if ( ! empty( $data['image_path'] ) ) : ?>
					<img src="<?php echo esc_url( $data['image_path'] ); ?>" alt="<?php echo esc_attr( $data['name'] ?? '' ); ?>" />
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Format score from fixture data
	 *
	 * @param array $fixture Fixture data
	 * @return string
	 */
	private function format_score( $fixture ) {
		if ( empty( $fixture['scores'] ) || ! is_array( $fixture['scores'] ) ) {
			return '';
		}

		$home = '';
		$away = '';

		foreach ( $fixture['scores'] as $score ) {
			$meta = $score['meta'] ?? array();
			if ( ( $meta['type'] ?? '' ) !== 'ft' ) {
				continue;
			}

			if ( ( $meta['location'] ?? '' ) === 'home' ) {
				$home = $score['score'] ?? '';
			} elseif ( ( $meta['location'] ?? '' ) === 'away' ) {
				$away = $score['score'] ?? '';
			}
		}

		if ( $home === '' && $away === '' ) {
			return '';
		}

		return sprintf( '%s - %s', $home, $away );
	}
}

