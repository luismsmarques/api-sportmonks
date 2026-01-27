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
	 * Cache expiration time (seconds)
	 */
	const CACHE_EXPIRATION = 300; // 5 minutes
	
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
		}
		
		// Build query string
		$query_string = http_build_query( $params );
		$full_url = $url . '?' . $query_string;
		
		// Make request
		$response = wp_remote_get( $full_url, array(
			'timeout' => 30,
			'headers' => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			),
		) );
		
		// Check for errors
		if ( is_wp_error( $response ) ) {
			APS_Error_Logger::get_instance()->log(
				'API_ERROR',
				$response->get_error_message(),
				$response->get_error_code(),
				array( 'endpoint' => $endpoint, 'url' => $full_url ),
				'',
				array( 'url' => $full_url, 'params' => $params )
			);
			return $response;
		}
		
		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		
		// Check HTTP status
		if ( $status_code !== 200 ) {
			$error_message = sprintf( __( 'API request failed with status code %d', 'api-sportmonks' ), $status_code );
			APS_Error_Logger::get_instance()->log(
				'API_ERROR',
				$error_message,
				(string) $status_code,
				array( 'endpoint' => $endpoint, 'url' => $full_url ),
				'',
				array( 'url' => $full_url, 'params' => $params, 'response' => $body )
			);
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
		
		// Cache successful response
		if ( $use_cache ) {
			set_transient( $cache_key, $data, self::CACHE_EXPIRATION );
		}
		
		return $data;
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
		
		return $this->request( "fixtures/between/{$start_date}/{$end_date}/{$team_id}", $params, $includes, $use_cache );
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
			'events',
			'lineups',
			'statistics',
			'venue',
			'referee',
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
	 * Get head to head data
	 *
	 * @param int $team1_id Team 1 ID
	 * @param int $team2_id Team 2 ID
	 * @return array|WP_Error
	 */
	public function get_head_to_head( $team1_id, $team2_id, $use_cache = true ) {
		return $this->request( "teams/{$team1_id}/h2h/{$team2_id}", array(), array( 'fixtures' ), $use_cache );
	}

	/**
	 * Get all fixtures (with filters)
	 *
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_all_fixtures( $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( 'fixtures', $params, $includes, $use_cache );
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
	 * Get latest updated fixtures
	 *
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_latest_updated_fixtures( $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( 'fixtures/latest', $params, $includes, $use_cache );
	}

	/**
	 * Get livescores
	 *
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_livescores( $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( 'livescores', $params, $includes, $use_cache );
	}

	/**
	 * Get inplay livescores
	 *
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_inplay_livescores( $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( 'livescores/inplay', $params, $includes, $use_cache );
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
	 * Get extended team squad
	 *
	 * @param int   $team_id Team ID
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public function get_extended_team_squad( $team_id, $params = array(), $includes = array(), $use_cache = true ) {
		return $this->request( "squads/teams/{$team_id}/extended", $params, $includes, $use_cache );
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
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aps_api_%' OR option_name LIKE '_transient_timeout_aps_api_%'" );
	}
}

