<?php
/**
 * Theme Helpers Class
 *
 * Provides helper functions for themes to access Sportmonks data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_Theme_Helpers {
	
	/**
	 * Instance
	 *
	 * @var APS_Theme_Helpers
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 *
	 * @return APS_Theme_Helpers
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
		// Register helper functions
		$this->register_helpers();
	}
	
	/**
	 * Register helper functions
	 */
	private function register_helpers() {
		// Functions are available globally
	}
	
	/**
	 * Get match data from API
	 *
	 * @param int  $match_id Match ID
	 * @param bool $use_cache Use cache
	 * @return array|WP_Error Match data or error
	 */
	public static function get_match_data( $match_id, $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_match( $match_id, array(), array(), $use_cache );
	}
	
	/**
	 * Get match data from post
	 *
	 * @param int $post_id Post ID
	 * @return array Match data
	 */
	public static function get_match_from_post( $post_id ) {
		$match_id = get_post_meta( $post_id, '_aps_match_id', true );
		
		if ( ! $match_id ) {
			return array();
		}
		
		return array(
			'match_id'      => $match_id,
			'team_home_id' => get_post_meta( $post_id, '_aps_team_home_id', true ),
			'team_away_id' => get_post_meta( $post_id, '_aps_team_away_id', true ),
			'team_home_name' => get_post_meta( $post_id, '_aps_team_home_name', true ),
			'team_away_name' => get_post_meta( $post_id, '_aps_team_away_name', true ),
			'league_id'    => get_post_meta( $post_id, '_aps_league_id', true ),
			'match_date'   => get_post_meta( $post_id, '_aps_match_date', true ),
			'match_status' => get_post_meta( $post_id, '_aps_match_status', true ),
			'score_home'   => get_post_meta( $post_id, '_aps_score_home', true ),
			'score_away'   => get_post_meta( $post_id, '_aps_score_away', true ),
		);
	}
	
	/**
	 * Get full match details from API
	 *
	 * @param int $match_id Match ID
	 * @return array|WP_Error Full match data
	 */
	public static function get_full_match_details( $match_id ) {
		$includes = array(
			'participants',
			'scores',
			'state',
			'events',
			'lineups',
			'statistics',
			'venue',
			'referee',
			'statistics.periods',
			'events.type',
			'lineups.player',
			'lineups.position',
		);
		
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_match( $match_id, array(), $includes, true );
	}
	
	/**
	 * Get league standings
	 *
	 * @param int $league_id League ID
	 * @return array|WP_Error Standings data
	 */
	public static function get_league_standings( $league_id ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_league_standings( $league_id );
	}
	
	/**
	 * Get league top scorers
	 *
	 * @param int $league_id League ID
	 * @param int $limit Limit
	 * @return array|WP_Error Top scorers data
	 */
	public static function get_league_top_scorers( $league_id, $limit = 20 ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_league_top_scorers( $league_id, array( 'per_page' => $limit ) );
	}
	
	/**
	 * Get head to head data
	 *
	 * @param int $team1_id Team 1 ID
	 * @param int $team2_id Team 2 ID
	 * @return array|WP_Error H2H data
	 */
	public static function get_head_to_head( $team1_id, $team2_id ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_head_to_head( $team1_id, $team2_id );
	}

	/**
	 * Get fixtures for a team
	 *
	 * @param int   $team_id Team ID
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public static function get_team_fixtures( $team_id, $params = array(), $includes = array(), $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_fixtures( $team_id, $params, $includes, $use_cache );
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
	public static function get_fixtures_by_date_range( $from, $to, $params = array(), $includes = array(), $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_fixtures_by_date_range( $from, $to, $params, $includes, $use_cache );
	}

	/**
	 * Get livescores
	 *
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public static function get_livescores( $params = array(), $includes = array(), $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_livescores( $params, $includes, $use_cache );
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
	public static function get_team_squad( $team_id, $params = array(), $includes = array(), $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_team_squad( $team_id, $params, $includes, $use_cache );
	}

	/**
	 * Get injuries
	 *
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public static function get_injuries( $params = array(), $includes = array(), $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_injuries( $params, $includes, $use_cache );
	}

	/**
	 * Get team sidelined (injuries)
	 *
	 * @param int  $team_id Team ID
	 * @param bool $use_cache Use cache
	 * @return array|WP_Error
	 */
	public static function get_team_sidelined( $team_id, $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_team_sidelined( $team_id, $use_cache );
	}

	/**
	 * Get team players
	 *
	 * @param int  $team_id Team ID
	 * @param bool $use_cache Use cache
	 * @return array|WP_Error
	 */
	public static function get_team_players( $team_id, $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_team_players( $team_id, $use_cache );
	}

	/**
	 * Get player statistics
	 *
	 * @param int  $player_id Player ID
	 * @param bool $use_cache Use cache
	 * @return array|WP_Error
	 */
	public static function get_player_stats( $player_id, $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_player_with_stats( $player_id, $use_cache );
	}

	/**
	 * Get category term for a team ID
	 *
	 * @param int $team_id Team ID
	 * @return WP_Term|null
	 */
	public static function get_team_category( $team_id ) {
		$term_id = APS_Taxonomy_Manager::get_instance()->get_term_id_by_team_id( $team_id );
		if ( ! $term_id ) {
			return null;
		}

		$term = get_term( $term_id, 'category' );
		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Get mapped team categories (team_id => term_id)
	 *
	 * @return array
	 */
	public static function get_team_category_mapping() {
		return get_option( 'aps_team_mapping', array() );
	}

	/**
	 * Get transfers
	 *
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public static function get_transfers( $params = array(), $includes = array(), $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_transfers( $params, $includes, $use_cache );
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
	public static function get_transfers_by_team( $team_id, $params = array(), $includes = array(), $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_transfers_by_team( $team_id, $params, $includes, $use_cache );
	}

	/**
	 * Get cached team squad
	 *
	 * @param int $team_id Team ID
	 * @return array
	 */
	public static function get_cached_team_squad( $team_id ) {
		$cache_key = sprintf( 'aps_smonks_squad_%d', absint( $team_id ) );
		$cached = get_transient( $cache_key );
		return is_array( $cached ) ? $cached : array();
	}

	/**
	 * Get cached team injuries
	 *
	 * @param int $team_id Team ID
	 * @return array
	 */
	public static function get_cached_team_injuries( $team_id ) {
		$cache_key = sprintf( 'aps_smonks_injuries_%d', absint( $team_id ) );
		$cached = get_transient( $cache_key );
		return is_array( $cached ) ? $cached : array();
	}

	/**
	 * Get cached team transfers
	 *
	 * @param int $team_id Team ID
	 * @return array
	 */
	public static function get_cached_team_transfers( $team_id ) {
		$cache_key = sprintf( 'aps_smonks_transfers_%d', absint( $team_id ) );
		$cached = get_transient( $cache_key );
		return is_array( $cached ) ? $cached : array();
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
	public static function search_players( $name, $params = array(), $includes = array(), $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->search_players( $name, $params, $includes, $use_cache );
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
	public static function search_teams( $name, $params = array(), $includes = array(), $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->search_teams( $name, $params, $includes, $use_cache );
	}

	/**
	 * Get player data
	 *
	 * @param int   $player_id Player ID
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	public static function get_player( $player_id, $includes = array(), $use_cache = true ) {
		$api_client = APS_API_Client::get_instance();
		return $api_client->get_player( $player_id, $includes, $use_cache );
	}
	
	/**
	 * Format match status
	 *
	 * @param string $status Status code
	 * @return string Formatted status
	 */
	public static function format_match_status( $status ) {
		$statuses = array(
			'NS'    => __( 'Por Começar', 'api-sportmonks' ),
			'LIVE'  => __( 'Ao Vivo', 'api-sportmonks' ),
			'HT'    => __( 'Intervalo', 'api-sportmonks' ),
			'FT'    => __( 'Terminado', 'api-sportmonks' ),
			'CANC'  => __( 'Cancelado', 'api-sportmonks' ),
			'POSTP' => __( 'Adiado', 'api-sportmonks' ),
		);
		
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
	}
	
	/**
	 * Get match events
	 *
	 * @param array $match_data Full match data from API
	 * @return array Events array
	 */
	public static function get_match_events( $match_data ) {
		if ( ! isset( $match_data['data']['events'] ) || ! is_array( $match_data['data']['events'] ) ) {
			return array();
		}
		
		return $match_data['data']['events'];
	}
	
	/**
	 * Get match lineups
	 *
	 * @param array $match_data Full match data from API
	 * @return array Lineups array
	 */
	public static function get_match_lineups( $match_data ) {
		if ( ! isset( $match_data['data']['lineups'] ) || ! is_array( $match_data['data']['lineups'] ) ) {
			return array();
		}
		
		return $match_data['data']['lineups'];
	}
	
	/**
	 * Get match statistics
	 *
	 * @param array $match_data Full match data from API
	 * @return array Statistics array
	 */
	public static function get_match_statistics( $match_data ) {
		if ( ! isset( $match_data['data']['statistics'] ) || ! is_array( $match_data['data']['statistics'] ) ) {
			return array();
		}
		
		return $match_data['data']['statistics'];
	}
}

// Make functions available globally
if ( ! function_exists( 'aps_get_match_data' ) ) {
	/**
	 * Get match data from API
	 *
	 * @param int  $match_id Match ID
	 * @param bool $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_get_match_data( $match_id, $use_cache = true ) {
		return APS_Theme_Helpers::get_match_data( $match_id, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_team_fixtures' ) ) {
	/**
	 * Get team fixtures
	 *
	 * @param int   $team_id Team ID
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_get_team_fixtures( $team_id, $params = array(), $includes = array(), $use_cache = true ) {
		return APS_Theme_Helpers::get_team_fixtures( $team_id, $params, $includes, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_fixtures_by_date_range' ) ) {
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
	function aps_get_fixtures_by_date_range( $from, $to, $params = array(), $includes = array(), $use_cache = true ) {
		return APS_Theme_Helpers::get_fixtures_by_date_range( $from, $to, $params, $includes, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_livescores' ) ) {
	/**
	 * Get livescores
	 *
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_get_livescores( $params = array(), $includes = array(), $use_cache = true ) {
		return APS_Theme_Helpers::get_livescores( $params, $includes, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_team_squad' ) ) {
	/**
	 * Get team squad
	 *
	 * @param int   $team_id Team ID
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_get_team_squad( $team_id, $params = array(), $includes = array(), $use_cache = true ) {
		return APS_Theme_Helpers::get_team_squad( $team_id, $params, $includes, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_injuries' ) ) {
	/**
	 * Get injuries
	 *
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_get_injuries( $params = array(), $includes = array(), $use_cache = true ) {
		return APS_Theme_Helpers::get_injuries( $params, $includes, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_team_sidelined' ) ) {
	/**
	 * Get team sidelined (injuries)
	 *
	 * @param int  $team_id Team ID
	 * @param bool $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_get_team_sidelined( $team_id, $use_cache = true ) {
		return APS_Theme_Helpers::get_team_sidelined( $team_id, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_team_players' ) ) {
	/**
	 * Get team players
	 *
	 * @param int  $team_id Team ID
	 * @param bool $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_get_team_players( $team_id, $use_cache = true ) {
		return APS_Theme_Helpers::get_team_players( $team_id, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_player_stats' ) ) {
	/**
	 * Get player statistics
	 *
	 * @param int  $player_id Player ID
	 * @param bool $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_get_player_stats( $player_id, $use_cache = true ) {
		return APS_Theme_Helpers::get_player_stats( $player_id, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_team_category' ) ) {
	/**
	 * Get category term for a team ID
	 *
	 * @param int $team_id Team ID
	 * @return WP_Term|null
	 */
	function aps_get_team_category( $team_id ) {
		return APS_Theme_Helpers::get_team_category( $team_id );
	}
}

if ( ! function_exists( 'aps_get_team_category_mapping' ) ) {
	/**
	 * Get mapped team categories
	 *
	 * @return array
	 */
	function aps_get_team_category_mapping() {
		return APS_Theme_Helpers::get_team_category_mapping();
	}
}

if ( ! function_exists( 'aps_get_transfers' ) ) {
	/**
	 * Get transfers
	 *
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_get_transfers( $params = array(), $includes = array(), $use_cache = true ) {
		return APS_Theme_Helpers::get_transfers( $params, $includes, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_transfers_by_team' ) ) {
	/**
	 * Get transfers by team
	 *
	 * @param int   $team_id Team ID
	 * @param array $params Query parameters
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_get_transfers_by_team( $team_id, $params = array(), $includes = array(), $use_cache = true ) {
		return APS_Theme_Helpers::get_transfers_by_team( $team_id, $params, $includes, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_cached_team_squad' ) ) {
	/**
	 * Get cached team squad
	 *
	 * @param int $team_id Team ID
	 * @return array
	 */
	function aps_get_cached_team_squad( $team_id ) {
		return APS_Theme_Helpers::get_cached_team_squad( $team_id );
	}
}

if ( ! function_exists( 'aps_get_cached_team_injuries' ) ) {
	/**
	 * Get cached team injuries
	 *
	 * @param int $team_id Team ID
	 * @return array
	 */
	function aps_get_cached_team_injuries( $team_id ) {
		return APS_Theme_Helpers::get_cached_team_injuries( $team_id );
	}
}

if ( ! function_exists( 'aps_get_cached_team_transfers' ) ) {
	/**
	 * Get cached team transfers
	 *
	 * @param int $team_id Team ID
	 * @return array
	 */
	function aps_get_cached_team_transfers( $team_id ) {
		return APS_Theme_Helpers::get_cached_team_transfers( $team_id );
	}
}

if ( ! function_exists( 'aps_search_players' ) ) {
	/**
	 * Search players by name
	 *
	 * @param string $name Player name
	 * @param array  $params Query parameters
	 * @param array  $includes Includes array
	 * @param bool   $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_search_players( $name, $params = array(), $includes = array(), $use_cache = true ) {
		return APS_Theme_Helpers::search_players( $name, $params, $includes, $use_cache );
	}
}

if ( ! function_exists( 'aps_search_teams' ) ) {
	/**
	 * Search teams by name
	 *
	 * @param string $name Team name
	 * @param array  $params Query parameters
	 * @param array  $includes Includes array
	 * @param bool   $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_search_teams( $name, $params = array(), $includes = array(), $use_cache = true ) {
		return APS_Theme_Helpers::search_teams( $name, $params, $includes, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_player' ) ) {
	/**
	 * Get player data
	 *
	 * @param int   $player_id Player ID
	 * @param array $includes Includes array
	 * @param bool  $use_cache Use cache
	 * @return array|WP_Error
	 */
	function aps_get_player( $player_id, $includes = array(), $use_cache = true ) {
		return APS_Theme_Helpers::get_player( $player_id, $includes, $use_cache );
	}
}

if ( ! function_exists( 'aps_get_match_from_post' ) ) {
	/**
	 * Get match data from post
	 *
	 * @param int $post_id Post ID
	 * @return array
	 */
	function aps_get_match_from_post( $post_id ) {
		return APS_Theme_Helpers::get_match_from_post( $post_id );
	}
}

if ( ! function_exists( 'aps_get_full_match_details' ) ) {
	/**
	 * Get full match details from API
	 *
	 * @param int $match_id Match ID
	 * @return array|WP_Error
	 */
	function aps_get_full_match_details( $match_id ) {
		return APS_Theme_Helpers::get_full_match_details( $match_id );
	}
}

if ( ! function_exists( 'aps_get_league_standings' ) ) {
	/**
	 * Get league standings
	 *
	 * @param int $league_id League ID
	 * @return array|WP_Error
	 */
	function aps_get_league_standings( $league_id ) {
		return APS_Theme_Helpers::get_league_standings( $league_id );
	}
}

if ( ! function_exists( 'aps_get_league_top_scorers' ) ) {
	/**
	 * Get league top scorers
	 *
	 * @param int $league_id League ID
	 * @param int $limit Limit
	 * @return array|WP_Error
	 */
	function aps_get_league_top_scorers( $league_id, $limit = 20 ) {
		return APS_Theme_Helpers::get_league_top_scorers( $league_id, $limit );
	}
}

if ( ! function_exists( 'aps_get_head_to_head' ) ) {
	/**
	 * Get head to head data
	 *
	 * @param int $team1_id Team 1 ID
	 * @param int $team2_id Team 2 ID
	 * @return array|WP_Error
	 */
	function aps_get_head_to_head( $team1_id, $team2_id ) {
		return APS_Theme_Helpers::get_head_to_head( $team1_id, $team2_id );
	}
}

if ( ! function_exists( 'aps_format_match_status' ) ) {
	/**
	 * Format match status
	 *
	 * @param string $status Status code
	 * @return string
	 */
	function aps_format_match_status( $status ) {
		return APS_Theme_Helpers::format_match_status( $status );
	}
}

