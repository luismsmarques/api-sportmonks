<?php
/**
 * Live Tick — um único pedido /livescores/inplay por minuto que abastece
 * a post meta dos jogos ao vivo.
 *
 * Motivação (auditoria de rate limit, jul 2026): a quota Sportmonks é por
 * CONTA e por entidade; o polling ao vivo por visitante custava
 * 120 pedidos Fixture/hora POR visitante. Com o tick, o custo fica fixo
 * (≤60/h, só durante a janela de jogo) e o front lê a meta — zero pedidos
 * à API por visitante.
 *
 * Garantias:
 * - Gate local sem API: só corre se houver jogos na janela (estado live
 *   guardado OU kickoff nas últimas 4 h / próximos 30 min).
 * - Reserva de quota: não corre com o circuit breaker de 429 armado e é
 *   desligável por filtro (aps_live_tick_enabled).
 * - Uma chamada por tick: o payload do inplay é reutilizado para todos os
 *   jogos — nunca uma chamada por jogo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_Live_Tick {

	const EVENT       = 'aps_live_tick_event';
	const SCHEDULE    = 'aps_minutely';
	const SYNCED_META = '_aps_live_synced_at';

	/**
	 * @var APS_Live_Tick
	 */
	private static $instance = null;

	/**
	 * @return APS_Live_Tick
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_schedule' ) );
		add_action( 'init', array( $this, 'schedule_event' ) );
		add_action( self::EVENT, array( $this, 'run' ) );
	}

	/**
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_schedule( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'A cada minuto (live tick)', 'api-sportmonks' ),
		);
		return $schedules;
	}

	public function schedule_event() {
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_event( time(), self::SCHEDULE, self::EVENT );
		}
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::EVENT );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::EVENT );
		}
	}

	/**
	 * Um tick: 0 ou 1 pedido à API, nunca mais.
	 */
	public function run() {
		/**
		 * @param bool $enabled Desligar o live tick sem mexer no cron.
		 */
		if ( ! apply_filters( 'aps_live_tick_enabled', true ) ) {
			return;
		}

		// Gate local (sem API): há jogos na janela de live?
		$window = $this->get_matches_in_live_window();
		if ( empty( $window ) ) {
			return;
		}

		// Reserva de quota: com o breaker de 429 armado, não gastar nada.
		if ( get_transient( APS_API_Client::RATE_LIMIT_TRANSIENT ) ) {
			return;
		}

		$client   = APS_API_Client::get_instance();
		$response = $client->request(
			'livescores/inplay',
			array( 'per_page' => 50 ),
			array( 'state', 'scores', 'periods' ),
			false // dados ao vivo: o cache de 5 min do cliente não serve aqui.
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$fixtures = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
		if ( empty( $fixtures ) ) {
			return;
		}

		// Reutiliza o payload para todos os jogos locais da janela.
		foreach ( $fixtures as $fixture ) {
			$fixture_id = isset( $fixture['id'] ) ? (int) $fixture['id'] : 0;
			if ( ! $fixture_id || ! isset( $window[ $fixture_id ] ) ) {
				continue;
			}
			$this->write_live_meta( (int) $window[ $fixture_id ], $fixture );
		}
	}

	/**
	 * Jogos locais na janela de live, sem tocar na API.
	 *
	 * @return array<int, int> Mapa fixture_id (API) => post_id.
	 */
	private function get_matches_in_live_window() {
		$now_utc = gmdate( 'Y-m-d H:i:s' );
		// _aps_match_date é o starting_at da Sportmonks (UTC, Y-m-d H:i:s) —
		// comparação lexicográfica funciona.
		$from = gmdate( 'Y-m-d H:i:s', time() - 4 * HOUR_IN_SECONDS );
		$to   = gmdate( 'Y-m-d H:i:s', time() + 30 * MINUTE_IN_SECONDS );

		$live_codes = array( 'LIVE', '1H', 'HT', '2H', 'ET', 'BT', 'P', 'INPLAY_1ST_HALF', 'INPLAY_2ND_HALF', 'INPLAY_ET', 'INPLAY_PENALTIES', 'EXTRA_TIME_BREAK', 'PEN_BREAK' );

		$query = new WP_Query(
			array(
				'post_type'      => 'aps_jogo',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_aps_match_status',
						'value'   => $live_codes,
						'compare' => 'IN',
					),
					array(
						'key'     => '_aps_match_date',
						'value'   => array( $from, $to ),
						'compare' => 'BETWEEN',
						'type'    => 'CHAR',
					),
				),
			)
		);

		$map = array();
		foreach ( $query->posts as $post_id ) {
			$api_id = (int) get_post_meta( $post_id, '_aps_match_id', true );
			if ( $api_id ) {
				$map[ $api_id ] = (int) $post_id;
			}
		}

		return $map;
	}

	/**
	 * Escreve resultado, minuto e estado na meta canónica do jogo — a mesma
	 * que sp_get_match_data() (core) já lê como fallback.
	 *
	 * @param int   $post_id Post do jogo.
	 * @param array $fixture Linha do payload livescores/inplay.
	 */
	private function write_live_meta( $post_id, $fixture ) {
		// Estado.
		$state = isset( $fixture['state'] ) && is_array( $fixture['state'] ) ? $fixture['state'] : array();
		$code  = $state['short_code'] ?? $state['state'] ?? $state['name'] ?? '';
		if ( $code ) {
			update_post_meta( $post_id, '_aps_match_status', (string) $code );
		}

		// Resultado atual (scores CURRENT, participant home/away).
		if ( ! empty( $fixture['scores'] ) && is_array( $fixture['scores'] ) ) {
			foreach ( $fixture['scores'] as $score ) {
				if ( ( $score['description'] ?? '' ) !== 'CURRENT' ) {
					continue;
				}
				$payload = isset( $score['score'] ) && is_array( $score['score'] ) ? $score['score'] : array();
				$goals   = $payload['goals'] ?? null;
				$side    = $payload['participant'] ?? '';
				if ( null === $goals ) {
					continue;
				}
				if ( 'home' === $side ) {
					update_post_meta( $post_id, '_aps_score_home', (int) $goals );
				} elseif ( 'away' === $side ) {
					update_post_meta( $post_id, '_aps_score_away', (int) $goals );
				}
			}
		}

		// Minuto (período a contar).
		if ( ! empty( $fixture['periods'] ) && is_array( $fixture['periods'] ) ) {
			foreach ( $fixture['periods'] as $period ) {
				if ( empty( $period['ticking'] ) ) {
					continue;
				}
				$mins  = isset( $period['minutes'] ) ? (int) $period['minutes'] : 0;
				$added = isset( $period['time_added'] ) ? (int) $period['time_added'] : 0;
				if ( $mins > 0 || $added > 0 ) {
					update_post_meta( $post_id, '_aps_elapsed', $added > 0 ? $mins . '+' . $added : (string) $mins );
				}
				break;
			}
		}

		// Carimbo de frescura — é isto que autoriza o core a servir da meta.
		update_post_meta( $post_id, self::SYNCED_META, time() );
	}
}
