<?php
/**
 * API Client Class
 *
 * Handles all API requests to Sportmonks API
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_API_Client {
	
	/**
	 * API Base URL
	 */
	const API_BASE_URL = 'https://api.sportmonks.com/v3/football';
	
	/**
	 * Cache expiration time (seconds) — default; ver get_cache_ttl() para
	 * TTL por família de endpoint.
	 */
	const CACHE_EXPIRATION = 300; // 5 minutes

	/**
	 * Transient name do circuit breaker de rate limit (HTTP 429)
	 */
	const RATE_LIMIT_TRANSIENT = 'aps_api_rate_limited';

	/**
	 * Option com o mapa de quota por entidade (remaining/resets_at),
	 * alimentado pelo rate_limit devolvido em cada resposta da API.
	 */
	const ENTITY_QUOTA_OPTION = 'aps_api_entity_quota';
	
	/**
	 * Instance
	 *
	 * @var APS_API_Client
	 */
	private static $instance = null;
	
	/**
	 * API Token
	 *
	 * @var string
	 */
	private $api_token = '';
	
	/**
	 * Get instance
	 *
	 * @return APS_API_Client
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
		$this->api_token = get_option( 'aps_smonks_api_token', '' );
	}
	
	/**
	 * Set API token
	 *
	 * @param string $token API token
	 */
	public function set_api_token( $token ) {
		$this->api_token = $token;
	}

	/**
	 * TTL de cache por família de endpoint (em vez de 300 s para tudo).
	 *
	 * @param string $endpoint Endpoint relativo.
	 * @return int Segundos.
	 */
	public function get_cache_ttl( $endpoint ) {
		$endpoint = ltrim( (string) $endpoint, '/' );
		$ttl = self::CACHE_EXPIRATION; // fixtures e afins: 5 min.
		if ( 0 === strpos( $endpoint, 'livescores' ) ) {
			$ttl = 15;
		} elseif ( 0 === strpos( $endpoint, 'standings' ) || 0 === strpos( $endpoint, 'topscorers' ) ) {
			$ttl = HOUR_IN_SECONDS;
		} elseif ( 0 === strpos( $endpoint, 'squads' ) || 0 === strpos( $endpoint, 'players' ) || 0 === strpos( $endpoint, 'transfers' ) ) {
			$ttl = 6 * HOUR_IN_SECONDS;
		} elseif ( 0 === strpos( $endpoint, 'teams' ) || 0 === strpos( $endpoint, 'leagues' ) || 0 === strpos( $endpoint, 'seasons' ) || 0 === strpos( $endpoint, 'venues' ) || 0 === strpos( $endpoint, 'coaches' ) || 0 === strpos( $endpoint, 'referees' ) ) {
			$ttl = 12 * HOUR_IN_SECONDS;
		}

		/**
		 * @param int    $ttl      TTL em segundos.
		 * @param string $endpoint Endpoint relativo.
		 */
		return (int) apply_filters( 'aps_api_cache_ttl', $ttl, $endpoint );
	}

	/**
	 * Entidade Sportmonks (balde de quota) de um endpoint.
	 *
	 * @param string $endpoint Endpoint relativo.
	 * @return string Nome da entidade ('' quando desconhecida).
	 */
	public static function entity_for_endpoint( $endpoint ) {
		$endpoint = ltrim( (string) $endpoint, '/' );
		$map = array(
			'livescores' => 'Fixture', // /livescores/* conta no balde Fixture.
			'fixtures'   => 'Fixture',
			'standings'  => 'Standing',
			'topscorers' => 'Topscorer',
			'squads'     => 'Squad',
			'players'    => 'Player',
			'teams'      => 'Team',
			'leagues'    => 'League',
			'seasons'    => 'Season',
		);
		foreach ( $map as $prefix => $entity ) {
			if ( 0 === strpos( $endpoint, $prefix ) ) {
				return $entity;
			}
		}
		return '';
	}

	/**
	 * Há quota suficiente no balde da entidade para gastar mais um pedido?
	 *
	 * Um Fixture esgotado não bloqueia trabalho noutro balde, e um balde
	 * quase vazio guarda uma reserva para o essencial (live tick).
	 *
	 * @param string $entity  Entidade ('' = sem gate).
	 * @param int    $reserve Pedidos a preservar no balde.
	 * @return bool
	 */
	public function can_make_request( $entity, $reserve = null ) {
		if ( '' === (string) $entity ) {
			return true;
		}
		if ( null === $reserve ) {
			/**
			 * @param int    $reserve Pedidos guardados como reserva por entidade.
			 * @param string $entity  Entidade em causa.
			 */
			$reserve = (int) apply_filters( 'aps_api_quota_reserve', 10, $entity );
		}

		$quota = get_option( self::ENTITY_QUOTA_OPTION, array() );
		if ( ! is_array( $quota ) || empty( $quota[ $entity ] ) ) {
			return true; // sem informação ainda — deixa passar e aprende da resposta.
		}

		$row = $quota[ $entity ];
		if ( ! empty( $row['resets_at'] ) && time() >= (int) $row['resets_at'] ) {
			return true; // janela renovada.
		}

		return (int) ( $row['remaining'] ?? 0 ) > (int) $reserve;
	}

	/**
	 * Regista o rate_limit devolvido pela API no mapa por entidade.
	 *
	 * @param array $rate_limit Bloco rate_limit da resposta.
	 */
	private function record_rate_limit( $rate_limit ) {
		if ( empty( $rate_limit ) || ! is_array( $rate_limit ) ) {
			return;
		}
		$entity = (string) ( $rate_limit['requested_entity'] ?? '' );
		if ( '' === $entity ) {
			return;
		}
		$quota = get_option( self::ENTITY_QUOTA_OPTION, array() );
		if ( ! is_array( $quota ) ) {
			$quota = array();
		}
		$quota[ $entity ] = array(
			'remaining' => (int) ( $rate_limit['remaining'] ?? 0 ),
			'resets_at' => time() + (int) ( $rate_limit['resets_in_seconds'] ?? 0 ),
		);
		update_option( self::ENTITY_QUOTA_OPTION, $quota, false );
	}
	
	/**
	 * Make API request
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $params Query parameters
	 * @param array  $includes Includes array
	 * @param bool   $use_cache Use cache
	 * @return array|WP_Error Response data or WP_Error
	 */
	public function request( $endpoint, $params = array(), $includes = array(), $use_cache = true ) {
		if ( empty( $this->api_token ) ) {
			APS_Error_Logger::get_instance()->log(
				'API_ERROR',
				'API token is not set',
				'NO_TOKEN',
				array( 'endpoint' => $endpoint )
			);
			return new WP_Error( 'no_token', __( 'API token is not configured.', 'api-sportmonks' ) );
		}
		
		// Build URL
		$url = self::API_BASE_URL . '/' . ltrim( $endpoint, '/' );
		
		// Add API token
		$params['api_token'] = $this->api_token;

		// Normalize params
		if ( isset( $params['filters'] ) && is_array( $params['filters'] ) ) {
			$params['filters'] = implode( ';', $params['filters'] );
		}

		if ( isset( $params['select'] ) && is_array( $params['select'] ) ) {
			$params['select'] = implode( ',', $params['select'] );
		}
		
		// Add includes if provided
		if ( ! empty( $includes ) ) {
			$params['include'] = implode( ';', $includes );
		}
		
		// Check cache
		$cache_key = 'aps_api_' . md5( $url . serialize( $params ) );
		
		if ( $use_cache ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}

			// Cache negativo no cliente: um endpoint que acabou de falhar não
			// volta a ser martelado a cada render (1 min em erros, 10 min em 403).
			if ( get_transient( $cache_key . '_neg' ) ) {
				return new WP_Error(
					'aps_negative_cache',
					__( 'Sportmonks API recently failed for this request; backing off.', 'api-sportmonks' ),
					array( 'status' => 503 )
				);
			}
		}

		// Circuit breaker: se a API devolveu 429 ha pouco, falhar rapido
		// sem gastar mais quota (so quando nao ha resposta em cache).
		if ( get_transient( self::RATE_LIMIT_TRANSIENT ) ) {
			return new WP_Error(
				'rate_limited',
				__( 'Sportmonks API rate limit reached. Please try again shortly.', 'api-sportmonks' ),
				array( 'status' => 429 )
			);
		}

		// Gate de quota por entidade: com o balde desta entidade na reserva,
		// não gastar — outros baldes continuam livres.
		$entity = self::entity_for_endpoint( $endpoint );
		if ( ! $this->can_make_request( $entity ) ) {
			return new WP_Error(
				'quota_reserved',
				sprintf( __( 'Sportmonks quota for entity %s is at reserve level; request skipped.', 'api-sportmonks' ), $entity ),
				array( 'status' => 429 )
			);
		}

		// Build query string
		$query_string = http_build_query( $params );
		$full_url = $url . '?' . $query_string;

		// Prepare safe logging values (mask token)
		$safe_params = $params;
		if ( isset( $safe_params['api_token'] ) ) {
			$safe_params['api_token'] = '***';
		}
		$safe_url = $full_url;
		if ( ! empty( $this->api_token ) ) {
			$safe_url = str_replace( $this->api_token, '***', $safe_url );
		}

		// Make request. Em contexto cron, 1 retry para falhas transitorias
		// (rede, 5xx). NUNCA repetir um 429 — cada retry gastava mais quota
		// da conta inteira; arma-se logo o circuit breaker.
		$max_attempts = wp_doing_cron() ? 2 : 1;
		$attempt = 0;

		do {
			$attempt++;

			$response = wp_remote_get( $full_url, array(
				'timeout' => 30,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
			) );

			$is_network_error = is_wp_error( $response );
			$status_code = $is_network_error ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$is_transient_failure = $is_network_error || $status_code >= 500;

			if ( $is_transient_failure && $attempt < $max_attempts ) {
				sleep( 2 );
				continue;
			}

			break;
		} while ( true );

		// Check for errors
		if ( is_wp_error( $response ) ) {
			APS_Error_Logger::get_instance()->log(
				'API_ERROR',
				$response->get_error_message(),
				$response->get_error_code(),
				array( 'endpoint' => $endpoint, 'url' => $safe_url ),
				'',
				array( 'url' => $safe_url, 'params' => $safe_params )
			);
			set_transient( $cache_key . '_neg', 1, MINUTE_IN_SECONDS );
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		// Rate limit: registar, armar o circuit breaker e devolver erro proprio.
		if ( 429 === $status_code ) {
			$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
			if ( $retry_after < 1 || $retry_after > 300 ) {
				$retry_after = 60;
			}
			set_transient( self::RATE_LIMIT_TRANSIENT, time(), $retry_after );

			$error_message = __( 'Sportmonks API rate limit reached (HTTP 429).', 'api-sportmonks' );
			APS_Error_Logger::get_instance()->log(
				'API_ERROR',
				$error_message,
				'RATE_LIMITED',
				array( 'endpoint' => $endpoint, 'url' => $safe_url, 'retry_after' => $retry_after ),
				'',
				array( 'url' => $safe_url, 'params' => $safe_params, 'response' => $body )
			);
			return new WP_Error( 'rate_limited', $error_message, array( 'status' => 429 ) );
		}

		// Check HTTP status
		if ( $status_code !== 200 ) {
			$error_message = sprintf( __( 'API request failed with status code %d', 'api-sportmonks' ), $status_code );
			APS_Error_Logger::get_instance()->log(
				'API_ERROR',
				$error_message,
				(string) $status_code,
				array( 'endpoint' => $endpoint, 'url' => $safe_url ),
				'',
				array( 'url' => $safe_url, 'params' => $safe_params, 'response' => $body )
			);
			// 403 = add-on/permissão em falta: não vai mudar no próximo minuto.
			set_transient( $cache_key . '_neg', 1, 403 === $status_code ? 10 * MINUTE_IN_SECONDS : MINUTE_IN_SECONDS );
			return new WP_Error( 'api_error', $error_message, array( 'status' => $status_code ) );
		}
		
		// Decode JSON
		$data = json_decode( $body, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_message = sprintf( __( 'Failed to decode JSON response: %s', 'api-sportmonks' ), json_last_error_msg() );
			APS_Error_Logger::get_instance()->log(
				'API_ERROR',
				$error_message,
				'JSON_DECODE_ERROR',
				array( 'endpoint' => $endpoint ),
				'',
				array( 'response' => $body )
			);
			return new WP_Error( 'json_error', $error_message );
		}
		
		// Check for API errors in response
		if ( isset( $data['error'] ) ) {
			APS_Error_Logger::get_instance()->log(
				'API_ERROR',
				$data['error']['message'] ?? 'Unknown API error',
				$data['error']['code'] ?? '',
				array( 'endpoint' => $endpoint ),
				'',
				array( 'response' => $data )
			);
			return new WP_Error( 'api_error', $data['error']['message'] ?? 'Unknown API error' );
		}
		
		// Regista a quota devolvida (remaining/resets por entidade).
		$this->record_rate_limit( $data['rate_limit'] ?? array() );

		// Cache successful response (TTL por família de endpoint)
		if ( $use_cache ) {
			set_transient( $cache_key, $data, $this->get_cache_ttl( $endpoint ) );
		}

		return $data;
	}

	/**
	 * Pedido paginado por has_more/page — a v3 limita per_page a 50 e
	 * truncava silenciosamente pedidos com per_page maior.
	 *
	 * @param string $endpoint  Endpoint relativo.
	 * @param array  $params    Query parameters.
	 * @param array  $includes  Includes.
	 * @param bool   $use_cache Use cache.
	 * @param int    $max_pages Limite de páginas (backstop de quota).
	 * @return array|WP_Error Resposta com 'data' agregada de todas as páginas.
	 */
	public function request_all_pages( $endpoint, $params = array(), $includes = array(), $use_cache = true, $max_pages = 10 ) {
		$params['per_page'] = 50; // máximo real da v3.
		$page      = 1;
		$all_rows  = array();
		$last_resp = null;
		$has_more  = false;

		do {
			$params['page'] = $page;
			$response = $this->request( $endpoint, $params, $includes, $use_cache );

			if ( is_wp_error( $response ) ) {
				if ( 1 === $page ) {
					return $response;
				}
				// Falha a meio: devolve o que há, mas nunca em silêncio.
				APS_Error_Logger::get_instance()->log(
					'API_WARNING',
					sprintf( 'Paginated request failed at page %d of %s; returning partial data.', $page, $endpoint ),
					'PAGINATION_PARTIAL',
					array( 'endpoint' => $endpoint, 'page' => $page )
				);
				break;
			}

			$last_resp = $response;
			$rows      = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
			$all_rows  = array_merge( $all_rows, $rows );
			$has_more  = ! empty( $response['pagination']['has_more'] );
			$page++;
		} while ( $has_more && $page <= $max_pages );

		if ( $has_more ) {
			APS_Error_Logger::get_instance()->log(
				'API_WARNING',
				sprintf( 'Paginated request truncated at %d pages for %s (has_more=true).', $max_pages, $endpoint ),
				'PAGINATION_TRUNCATED',
				array( 'endpoint' => $endpoint, 'max_pages' => $max_pages )
			);
		}

		if ( null === $last_resp ) {
			return array( 'data' => $all_rows );
		}

		$last_resp['data'] = $all_rows;
		return $last_resp;
	}
	
	/**
	 * Get team data
	 *
	 * @param int   $team_id Team ID
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_team( $team_id, $includes = array(), $use_cache = true ) {
		return $this->request( "teams/{$team_id}", array(), $includes, $use_cache );
	}
	
	/**
	 * Get team fixtures
	 *
	 * @param int   $team_id Team ID
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_fixtures( $team_id, $params = array(), $includes = array(), $use_cache = true ) {
		$default_includes = array( 'participants', 'scores', 'state' );
		$includes = array_merge( $default_includes, $includes );

		$date_range = $this->get_team_active_season_dates( $team_id );
		$start_date = $date_range['start'] ?? gmdate( 'Y-m-d', strtotime( '-90 days' ) );
		$end_date = $date_range['end'] ?? gmdate( 'Y-m-d', strtotime( '+90 days' ) );

		// Paginado: a v3 limita per_page a 50 — o antigo per_page=1000 era
		// truncado em silêncio e épocas completas ficavam sem jogos.
		return $this->request_all_pages( "fixtures/between/{$start_date}/{$end_date}/{$team_id}", $params, $includes, $use_cache );
	}
	
	/**
	 * Get match details
	 *
	 * @param int   $match_id Match ID
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_match( $match_id, $params = array(), $includes = array(), $use_cache = true ) {
		$default_includes = array(
			'participants',
			'scores',
			'state',
			'periods', // Sportmonks returns current match minute here (minutes, seconds, ticking, time_added)
			'events',
			'statistics',
		);
		$includes = array_merge( $default_includes, $includes );
		
		return $this->request( "fixtures/{$match_id}", $params, $includes, $use_cache );
	}
	
	/**
	 * Get league data
	 *
	 * @param int   $league_id League ID
	 * @param array $includes Includes array
	 * @return array|WP_Error
	 */
	public function get_league( $league_id, $includes = array(), $use_cache = true ) {
		return $this->request( "leagues/{$league_id}", array(), $includes, $use_cache );
	}
	
	/**
	 * Get league standings
	 *
	 * @param int   $league_id League ID
	 * @param array $includes Includes array
	 * @return array|WP_Error
	 */
	public function get_league_standings( $league_id, $includes = array(), $use_cache = true ) {
		$default_includes = array( 'participant' );
		$includes = array_merge( $default_includes, $includes );
		
		return $this->request( "standings/seasons/latest/leagues/{$league_id}", array(), $includes, $use_cache );
	}
	
	/**
	 * Get league top scorers
	 *
	 * @param int   $league_id League ID
	 * @param array $params Query parameters
	 * @return array|WP_Error
	 */
	public function get_league_top_scorers( $league_id, $params = array(), $use_cache = true ) {
		return $this->request( "topscorers/seasons/latest/leagues/{$league_id}", $params, array(), $use_cache );
	}
	
	/**
	 * Get head to head data (API v3: fixtures/head-to-head)
	 *
	 * @param int   $team1_id Team 1 ID
	 * @param int   $team2_id Team 2 ID
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_head_to_head( $team1_id, $team2_id, $includes = array(), $use_cache = true ) {
		if ( empty( $includes ) ) {
			$includes = array( 'participants', 'scores', 'state' );
		}
		return $this->request( "fixtures/head-to-head/{$team1_id}/{$team2_id}", array(), $includes, $use_cache );
	}

	/**
	 * Get fixtures by date
	 *
	 * @param string $date Date (YYYY-MM-DD)
	 * @param array  $params Query parameters
	 * @param array  $includes Includes array
	 * @param bool   $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_fixtures_by_date( $date, $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( "fixtures/date/{$date}", $params, $includes, $use_cache );
	}

	/**
	 * Get fixtures by date range
	 *
	 * @param string $from Date (YYYY-MM-DD)
	 * @param string $to Date (YYYY-MM-DD)
	 * @param array  $params Query parameters
	 * @param array  $includes Includes array
	 * @param bool   $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_fixtures_by_date_range( $from, $to, $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( "fixtures/between/{$from}/{$to}", $params, $includes, $use_cache );
	}

	/**
	 * Get team squad
	 *
	 * @param int   $team_id Team ID
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_team_squad( $team_id, $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( "squads/teams/{$team_id}", $params, $includes, $use_cache );
	}

	/**
	 * Get injuries
	 *
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_injuries( $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( 'injuries', $params, $includes, $use_cache );
	}

	/**
	 * Get team sidelined (injuries) via team include
	 *
	 * @param int  $team_id Team ID
	 * @param bool $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_team_sidelined( $team_id, $use_cache = true ) {
		return $this->get_team( $team_id, array( 'sidelined' ), $use_cache );
	}

	/**
	 * Get team players via include
	 *
	 * @param int  $team_id Team ID
	 * @param bool $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_team_players( $team_id, $use_cache = true ) {
		return $this->get_team( $team_id, array( 'players' ), $use_cache );
	}

	/**
	 * Get player with statistics
	 *
	 * @param int  $player_id Player ID
	 * @param bool $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_player_with_stats( $player_id, $use_cache = true ) {
		return $this->get_player( $player_id, array( 'statistics' ), $use_cache );
	}

	/**
	 * Resolve active season date range for team
	 *
	 * @param int $team_id Team ID
	 * @return array
	 */
	private function get_team_active_season_dates( $team_id ) {
		$response = $this->get_team( $team_id, array( 'activeSeasons' ), true );
		if ( is_wp_error( $response ) ) {
			return array();
		}

		$seasons = $response['data']['active_seasons'] ?? $response['data']['activeSeasons'] ?? array();
		if ( empty( $seasons ) || ! is_array( $seasons ) ) {
			return array();
		}

		$season = $seasons[0];
		$start = $season['starting_at'] ?? $season['start_date'] ?? '';
		$end = $season['ending_at'] ?? $season['end_date'] ?? '';

		if ( empty( $start ) || empty( $end ) ) {
			return array();
		}

		return array(
			'start' => gmdate( 'Y-m-d', strtotime( $start ) ),
			'end'   => gmdate( 'Y-m-d', strtotime( $end ) ),
		);
	}

	/**
	 * Get transfers
	 *
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_transfers( $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( 'transfers', $params, $includes, $use_cache );
	}

	/**
	 * Get transfers by team
	 *
	 * @param int   $team_id Team ID
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_transfers_by_team( $team_id, $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( "transfers/teams/{$team_id}", $params, $includes, $use_cache );
	}

	/**
	 * Get player by ID
	 *
	 * @param int   $player_id Player ID
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_player( $player_id, $includes = array(), $use_cache = true ) {
		return $this->request( "players/{$player_id}", array(), $includes, $use_cache );
	}

	/**
	 * Search players by name
	 *
	 * @param string $name Player name
	 * @param array  $params Query parameters
	 * @param array  $includes Includes array
	 * @param bool   $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function search_players( $name, $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( "players/search/{$name}", $params, $includes, $use_cache );
	}

	/**
	 * Search teams by name
	 *
	 * @param string $name Team name
	 * @param array  $params Query parameters
	 * @param array  $includes Includes array
	 * @param bool   $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function search_teams( $name, $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( "teams/search/{$name}", $params, $includes, $use_cache );
	}
	
	/**
	 * Clear cache for specific endpoint
	 *
	 * @param string $endpoint Endpoint
	 * @param array  $params Parameters
	 */
	public function clear_cache( $endpoint, $params = array() ) {
		$url = self::API_BASE_URL . '/' . ltrim( $endpoint, '/' );
		$params['api_token'] = $this->api_token;
		$cache_key = 'aps_api_' . md5( $url . serialize( $params ) );
		delete_transient( $cache_key );
	}
	
	/**
	 * Clear all API cache
	 */
	public function clear_all_cache() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aps_api_%' OR option_name LIKE '_transient_timeout_aps_api_%' OR option_name LIKE '_transient_aps_standings_ajax_%' OR option_name LIKE '_transient_timeout_aps_standings_ajax_%'" );
	}
}

