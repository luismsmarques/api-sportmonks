<?php
/**
 * Sync Manager Class
 *
 * Handles synchronization of match data from Sportmonks API
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_Sync_Manager {
	
	/**
	 * Instance
	 *
	 * @var APS_Sync_Manager
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 *
	 * @return APS_Sync_Manager
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
		add_action( 'aps_sportmonks_sync_event', array( $this, 'sync_teams_fixtures' ) );
		add_action( 'wp_ajax_aps_manual_sync', array( $this, 'ajax_manual_sync' ) );
	}
	
	/**
	 * Sync fixtures for all configured teams
	 *
	 * @return array Results
	 */
	public function sync_teams_fixtures() {
		$teams = get_option( 'aps_smonks_teams', array() );
		$sync_squads = (bool) get_option( 'aps_smonks_sync_squads', true );
		$sync_injuries = (bool) get_option( 'aps_smonks_sync_injuries', true );
		$sync_transfers = (bool) get_option( 'aps_smonks_sync_transfers', true );
		$results = array(
			'success' => 0,
			'error'   => 0,
			'updated' => 0,
			'created' => 0,
			'squads'  => 0,
			'injuries' => 0,
			'transfers' => 0,
		);
		
		if ( empty( $teams ) ) {
			APS_Error_Logger::get_instance()->log(
				'SYNC_ERROR',
				'No teams configured for synchronization',
				'NO_TEAMS'
			);
			return $results;
		}
		
		$api_client = APS_API_Client::get_instance();
		$taxonomy_manager = APS_Taxonomy_Manager::get_instance();
		
		foreach ( $teams as $team ) {
			if ( empty( $team['team_id'] ) ) {
				continue;
			}
			
			try {
				// Get fixtures for team
				$response = $api_client->get_fixtures( $team['team_id'], array(), array() );
				
				if ( is_wp_error( $response ) ) {
					$results['error']++;
					continue;
				}
				
				// Process fixtures
				if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
					foreach ( $response['data'] as $fixture ) {
						$sync_result = $this->sync_single_match( $fixture, $team, $taxonomy_manager );
						
						if ( $sync_result['success'] ) {
							$results['success']++;
							if ( $sync_result['created'] ) {
								$results['created']++;
							} else {
								$results['updated']++;
							}
						} else {
							$results['error']++;
						}
					}
				}

				// Sync squad cache
				if ( $sync_squads ) {
					$squad_result = $this->sync_team_squad_cache( $api_client, $team['team_id'] );
					if ( $squad_result ) {
						$results['squads']++;
					}
				}

				// Sync injuries cache
				if ( $sync_injuries ) {
					$injury_result = $this->sync_team_injuries_cache( $api_client, $team['team_id'] );
					if ( $injury_result ) {
						$results['injuries']++;
					}
				}

				// Sync transfers cache
				if ( $sync_transfers ) {
					$transfer_result = $this->sync_team_transfers_cache( $api_client, $team['team_id'] );
					if ( $transfer_result ) {
						$results['transfers']++;
					}
				}
			} catch ( Exception $e ) {
				APS_Error_Logger::get_instance()->log(
					'SYNC_ERROR',
					$e->getMessage(),
					'EXCEPTION',
					array( 'team_id' => $team['team_id'] ),
					$e->getTraceAsString()
				);
				$results['error']++;
			}
		}
		
		return $results;
	}

	/**
	 * Sync team squad cache
	 *
	 * @param APS_API_Client $api_client API client
	 * @param int            $team_id Team ID
	 * @return bool
	 */
	private function sync_team_squad_cache( $api_client, $team_id ) {
		$ttl = absint( get_option( 'aps_smonks_cache_ttl_squads', 21600 ) );
		$cache_key = $this->get_cache_key( 'squad', $team_id );

		$response = $api_client->get_team_squad( $team_id, array(), array(), false );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		set_transient( $cache_key, $response, $ttl );
		return true;
	}

	/**
	 * Sync team injuries cache
	 *
	 * @param APS_API_Client $api_client API client
	 * @param int            $team_id Team ID
	 * @return bool
	 */
	private function sync_team_injuries_cache( $api_client, $team_id ) {
		$ttl = absint( get_option( 'aps_smonks_cache_ttl_injuries', 1800 ) );
		$cache_key = $this->get_cache_key( 'injuries', $team_id );

		$response = $api_client->get_team_sidelined( $team_id, false );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		set_transient( $cache_key, $response, $ttl );
		return true;
	}

	/**
	 * Sync team transfers cache
	 *
	 * @param APS_API_Client $api_client API client
	 * @param int            $team_id Team ID
	 * @return bool
	 */
	private function sync_team_transfers_cache( $api_client, $team_id ) {
		$ttl = absint( get_option( 'aps_smonks_cache_ttl_transfers', 21600 ) );
		$cache_key = $this->get_cache_key( 'transfers', $team_id );

		$response = $api_client->get_transfers_by_team( $team_id, array(), array(), false );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		set_transient( $cache_key, $response, $ttl );
		return true;
	}

	/**
	 * Build cache key
	 *
	 * @param string $type Cache type
	 * @param int    $team_id Team ID
	 * @return string
	 */
	private function get_cache_key( $type, $team_id ) {
		return sprintf( 'aps_smonks_%s_%d', sanitize_key( $type ), absint( $team_id ) );
	}
	
	/**
	 * Sync single match
	 *
	 * @param array                $fixture_data Fixture data from API
	 * @param array                $team_config Team configuration
	 * @param APS_Taxonomy_Manager $taxonomy_manager Taxonomy manager instance
	 * @return array Result
	 */
	public function sync_single_match( $fixture_data, $team_config = array(), $taxonomy_manager = null ) {
		if ( null === $taxonomy_manager ) {
			$taxonomy_manager = APS_Taxonomy_Manager::get_instance();
		}
		
		$result = array(
			'success' => false,
			'created' => false,
			'post_id' => 0,
		);
		
		if ( ! isset( $fixture_data['id'] ) ) {
			return $result;
		}
		
		$match_id = absint( $fixture_data['id'] );
		
		// Extract match data
		$match_data = $this->extract_match_data( $fixture_data );
		
		// Find existing post by match ID
		$existing_post = $this->find_post_by_match_id( $match_id );
		
		if ( $existing_post ) {
			// Update existing post
			$post_id = $existing_post->ID;
			$this->update_match_post( $post_id, $match_data, $team_config, $taxonomy_manager );
			$result['success'] = true;
			$result['created'] = false;
			$result['post_id'] = $post_id;
		} else {
			// Create new post
			$post_id = $this->create_match_post( $match_data, $team_config, $taxonomy_manager );
			if ( $post_id ) {
				$result['success'] = true;
				$result['created'] = true;
				$result['post_id'] = $post_id;
			}
		}
		
		return $result;
	}
	
	/**
	 * Extract match data from API response
	 *
	 * @param array $fixture_data Fixture data
	 * @return array Extracted data
	 */
	private function extract_match_data( $fixture_data ) {
		$data = array(
			'match_id'      => $fixture_data['id'] ?? 0,
			'team_home_id' => 0,
			'team_away_id' => 0,
			'team_home_name' => '',
			'team_away_name' => '',
			'league_id'    => $fixture_data['league_id'] ?? 0,
			'match_date'   => $fixture_data['starting_at'] ?? '',
			'match_status' => $fixture_data['state']['name'] ?? 'NS',
			'score_home'   => '',
			'score_away'   => '',
		);
		
		// Extract participants (teams)
		if ( isset( $fixture_data['participants'] ) && is_array( $fixture_data['participants'] ) ) {
			foreach ( $fixture_data['participants'] as $participant ) {
				$meta = $participant['meta'] ?? array();
				$position = $meta['position'] ?? '';
				
				if ( $position === 'home' ) {
					$data['team_home_id'] = $participant['id'] ?? 0;
					$data['team_home_name'] = $participant['name'] ?? '';
				} elseif ( $position === 'away' ) {
					$data['team_away_id'] = $participant['id'] ?? 0;
					$data['team_away_name'] = $participant['name'] ?? '';
				}
			}
		}
		
		// Extract scores
		if ( isset( $fixture_data['scores'] ) && is_array( $fixture_data['scores'] ) ) {
			foreach ( $fixture_data['scores'] as $score ) {
				$meta = $score['meta'] ?? array();
				$score_type = $meta['type'] ?? '';
				
				if ( $score_type === 'ft' ) {
					$participant_id = $score['participant_id'] ?? 0;
					$score_value = $score['score'] ?? 0;
					
					if ( $participant_id == $data['team_home_id'] ) {
						$data['score_home'] = $score_value;
					} elseif ( $participant_id == $data['team_away_id'] ) {
						$data['score_away'] = $score_value;
					}
				}
			}
		}
		
		return $data;
	}
	
	/**
	 * Find post by match ID
	 *
	 * @param int $match_id Match ID
	 * @return WP_Post|null
	 */
	private function find_post_by_match_id( $match_id ) {
		$posts = get_posts( array(
			'post_type'  => 'aps_jogo',
			'meta_key'   => '_aps_match_id',
			'meta_value' => $match_id,
			'posts_per_page' => 1,
		) );
		
		return ! empty( $posts ) ? $posts[0] : null;
	}
	
	/**
	 * Create match post
	 *
	 * @param array                $match_data Match data
	 * @param array                $team_config Team configuration
	 * @param APS_Taxonomy_Manager $taxonomy_manager Taxonomy manager
	 * @return int|false Post ID or false
	 */
	private function create_match_post( $match_data, $team_config, $taxonomy_manager ) {
		$title = sprintf(
			'%s vs %s',
			$match_data['team_home_name'],
			$match_data['team_away_name']
		);
		
		$post_data = array(
			'post_title'   => $title,
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => 'aps_jogo',
		);
		
		$post_id = wp_insert_post( $post_data );
		
		if ( is_wp_error( $post_id ) ) {
			APS_Error_Logger::get_instance()->log(
				'SYNC_ERROR',
				$post_id->get_error_message(),
				'WP_INSERT_POST_ERROR',
				array( 'match_id' => $match_data['match_id'] )
			);
			return false;
		}
		
		$this->update_match_post( $post_id, $match_data, $team_config, $taxonomy_manager );
		
		return $post_id;
	}
	
	/**
	 * Update match post
	 *
	 * @param int                  $post_id Post ID
	 * @param array                $match_data Match data
	 * @param array                $team_config Team configuration
	 * @param APS_Taxonomy_Manager $taxonomy_manager Taxonomy manager
	 */
	private function update_match_post( $post_id, $match_data, $team_config, $taxonomy_manager ) {
		// Update meta fields
		update_post_meta( $post_id, '_aps_match_id', $match_data['match_id'] );
		update_post_meta( $post_id, '_aps_team_home_id', $match_data['team_home_id'] );
		update_post_meta( $post_id, '_aps_team_away_id', $match_data['team_away_id'] );
		update_post_meta( $post_id, '_aps_team_home_name', $match_data['team_home_name'] );
		update_post_meta( $post_id, '_aps_team_away_name', $match_data['team_away_name'] );
		update_post_meta( $post_id, '_aps_league_id', $match_data['league_id'] );
		update_post_meta( $post_id, '_aps_match_date', $match_data['match_date'] );
		update_post_meta( $post_id, '_aps_match_status', $match_data['match_status'] );
		update_post_meta( $post_id, '_aps_score_home', $match_data['score_home'] );
		update_post_meta( $post_id, '_aps_score_away', $match_data['score_away'] );
		update_post_meta( $post_id, '_aps_last_sync', current_time( 'mysql' ) );
		
		// Update title
		wp_update_post( array(
			'ID'         => $post_id,
			'post_title' => sprintf(
				'%s vs %s',
				$match_data['team_home_name'],
				$match_data['team_away_name']
			),
		) );
		
		// Associate taxonomies
		$taxonomy_terms = array();
		
		// Associate teams with categories
		if ( ! empty( $match_data['team_home_id'] ) ) {
			$home_term_id = $taxonomy_manager->get_term_id_by_team_id( $match_data['team_home_id'] );
			if ( $home_term_id ) {
				$taxonomy_terms['category'][] = $home_term_id;
			}
		}
		
		if ( ! empty( $match_data['team_away_id'] ) ) {
			$away_term_id = $taxonomy_manager->get_term_id_by_team_id( $match_data['team_away_id'] );
			if ( $away_term_id ) {
				$taxonomy_terms['category'][] = $away_term_id;
			}
		}
		
		// Associate league with competition
		if ( ! empty( $match_data['league_id'] ) ) {
			$competition_term_id = $taxonomy_manager->get_term_id_by_league_id( $match_data['league_id'] );
			if ( $competition_term_id ) {
				$taxonomy_terms['aps_competicao'][] = $competition_term_id;
			}
		}
		
		// Set taxonomy terms
		foreach ( $taxonomy_terms as $taxonomy => $term_ids ) {
			wp_set_object_terms( $post_id, array_unique( $term_ids ), $taxonomy );
		}
	}
	
	/**
	 * AJAX: Manual sync
	 */
	public function ajax_manual_sync() {
		check_ajax_referer( 'aps_settings_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'api-sportmonks' ) ) );
		}
		
		$results = $this->sync_teams_fixtures();
		
		wp_send_json_success( array(
			'message' => sprintf(
				__( 'Sincronização concluída: %d sucesso, %d erros, %d criados, %d atualizados', 'api-sportmonks' ),
				$results['success'],
				$results['error'],
				$results['created'],
				$results['updated']
			),
			'results' => $results,
		) );
	}
}

