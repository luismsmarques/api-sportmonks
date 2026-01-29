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
		add_action( 'wp_ajax_aps_manual_sync_range', array( $this, 'ajax_manual_sync_range' ) );
		add_action( 'wp_ajax_aps_refresh_match', array( $this, 'ajax_refresh_match' ) );
		add_action( 'load-edit.php', array( $this, 'maybe_handle_bulk_refresh' ) );
	}
	
	/**
	 * Sync fixtures for all configured teams
	 *
	 * @return array Results
	 */
	public function sync_teams_fixtures() {
		$start_time = microtime( true );
		update_option( 'aps_smonks_last_sync_started', current_time( 'mysql' ) );
		$teams = get_option( 'aps_smonks_teams', array() );
		$sync_squads = (bool) get_option( 'aps_smonks_sync_squads', true );
		$sync_injuries = (bool) get_option( 'aps_smonks_sync_injuries', true );
		$sync_transfers = (bool) get_option( 'aps_smonks_sync_transfers', true );
		$results = array(
			'success'   => 0,
			'error'     => 0,
			'updated'   => 0,
			'created'   => 0,
			'trashed'   => 0,
			'squads'    => 0,
			'injuries'  => 0,
			'transfers' => 0,
		);
		$metrics = array(
			'fixtures_total'   => 0,
			'fixtures_created' => 0,
			'fixtures_updated' => 0,
			'fixtures_errors'  => 0,
			'fixture_modes'    => array(),
		);
		
		if ( empty( $teams ) ) {
			APS_Error_Logger::get_instance()->log(
				'SYNC_ERROR',
				'No teams configured for synchronization',
				'NO_TEAMS'
			);
			update_option( 'aps_smonks_last_sync', current_time( 'mysql' ) );
			update_option( 'aps_smonks_last_sync_results', $results );
			return $results;
		}
		
		$api_client = APS_API_Client::get_instance();
		$taxonomy_manager = APS_Taxonomy_Manager::get_instance();
		
		foreach ( $teams as $team ) {
			if ( empty( $team['team_id'] ) ) {
				continue;
			}
			
			try {
				$team_id = absint( $team['team_id'] );
				$last_id_key = 'aps_smonks_last_fixture_id_' . $team_id;
				$snapshot_key = 'aps_smonks_last_fixture_snapshot_' . $team_id;
				$last_id = absint( get_option( $last_id_key, 0 ) );
				$last_snapshot = get_option( $snapshot_key, '' );
				$snapshot_due = empty( $last_snapshot ) || ( time() - strtotime( $last_snapshot ) > DAY_IN_SECONDS );
				$mode = ( $last_id > 0 && ! $snapshot_due ) ? 'incremental' : 'snapshot';
				$metrics['fixture_modes'][ $team_id ] = $mode;

				$params = array( 'per_page' => 1000 );
				if ( 'incremental' === $mode ) {
					$params['filters'] = array( 'idAfter:' . $last_id );
				}

				// Get fixtures for team (include league so competition can be associated)
				$response = $api_client->get_fixtures( $team_id, $params, array( 'league' ) );
				
				if ( is_wp_error( $response ) ) {
					$results['error']++;
					$metrics['fixtures_errors']++;
					continue;
				}
				
				// Process fixtures
				if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
					$metrics['fixtures_total'] += count( $response['data'] );

					foreach ( $response['data'] as $fixture ) {
						$sync_result = $this->sync_single_match( $fixture, $team, $taxonomy_manager );
						
						if ( $sync_result['success'] ) {
							$results['success']++;
							if ( $sync_result['created'] ) {
								$results['created']++;
								$metrics['fixtures_created']++;
							} else {
								$results['updated']++;
								$metrics['fixtures_updated']++;
							}
						} else {
							$results['error']++;
							$metrics['fixtures_errors']++;
						}
					}

					$max_id = $this->get_max_id_from_records( $response['data'], 'id' );
					if ( $max_id > $last_id ) {
						update_option( $last_id_key, $max_id );
					}
					if ( 'snapshot' === $mode ) {
						update_option( $snapshot_key, current_time( 'mysql' ) );
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

		// Sync deleted fixtures (API filter=deleted) so DB stays in sync when Sportmonks removes fixtures
		if ( (bool) get_option( 'aps_smonks_sync_deleted', true ) ) {
			$trashed = $this->sync_deleted_fixtures( $api_client );
			$results['trashed'] = $trashed;
		}

		update_option( 'aps_smonks_last_sync', current_time( 'mysql' ) );
		update_option( 'aps_smonks_last_sync_results', $results );
		$metrics['duration_seconds'] = round( microtime( true ) - $start_time, 2 );
		update_option( 'aps_smonks_last_sync_metrics', $metrics );
		return $results;
	}

	/**
	 * Sync deleted fixtures: fetch fixtures removed from API (filters=deleted) and trash matching posts.
	 * See Sportmonks guide "How to keep your database in SYNC".
	 *
	 * @param APS_API_Client $api_client API client
	 * @return int Number of posts trashed
	 */
	private function sync_deleted_fixtures( $api_client ) {
		$days = absint( get_option( 'aps_smonks_sync_deleted_days', 90 ) );
		$days = $days > 0 ? min( $days, 365 ) : 90;
		$start = strtotime( "-{$days} days", time() );
		$end = time();
		$trashed = 0;

		for ( $ts = $start; $ts <= $end; $ts += DAY_IN_SECONDS ) {
			$date = gmdate( 'Y-m-d', $ts );
			$response = $api_client->get_fixtures_by_date(
				$date,
				array( 'filters' => array( 'deleted' ) ),
				array( 'state' ),
				false
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$data = $response['data'] ?? $response;
			if ( ! is_array( $data ) ) {
				continue;
			}
			// API may return single fixture or list; ensure we iterate
			if ( isset( $data['id'] ) ) {
				$data = array( $data );
			}

			foreach ( $data as $fixture ) {
				$match_id = isset( $fixture['id'] ) ? absint( $fixture['id'] ) : 0;
				if ( ! $match_id ) {
					continue;
				}

				$post = $this->find_post_by_match_id( $match_id );
				if ( $post && $post->post_status !== 'trash' ) {
					wp_trash_post( $post->ID );
					$trashed++;
				}
			}
		}

		return $trashed;
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
			$error_data = $response->get_error_data();
			if ( is_array( $error_data ) && (int) ( $error_data['status'] ?? 0 ) === 403 ) {
				update_option( 'aps_smonks_sync_squads', 0 );
				APS_Error_Logger::get_instance()->log(
					'SYNC_ERROR',
					'Squad sync disabled due to 403 access error',
					'SYNC_DISABLED',
					array( 'team_id' => $team_id )
				);
			}
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
			$error_data = $response->get_error_data();
			if ( is_array( $error_data ) && (int) ( $error_data['status'] ?? 0 ) === 403 ) {
				update_option( 'aps_smonks_sync_transfers', 0 );
				APS_Error_Logger::get_instance()->log(
					'SYNC_ERROR',
					'Transfers sync disabled due to 403 access error',
					'SYNC_DISABLED',
					array( 'team_id' => $team_id )
				);
			}
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
	 * Get max ID from API records
	 *
	 * @param array  $records Records
	 * @param string $key ID key
	 * @return int
	 */
	private function get_max_id_from_records( $records, $key ) {
		$max_id = 0;
		foreach ( $records as $record ) {
			$id = isset( $record[ $key ] ) ? absint( $record[ $key ] ) : 0;
			if ( $id > $max_id ) {
				$max_id = $id;
			}
		}
		return $max_id;
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
		// #region agent log
		@file_put_contents(
			'/Users/LuisMarques_1/Local Sites/super-portistas/app/public/wp-content/plugins/api-sportmonks/.cursor/debug.log',
			wp_json_encode(
				array(
					'sessionId' => 'debug-session',
					'runId' => 'pre-fix',
					'hypothesisId' => 'H2',
					'location' => 'class-sync-manager.php:extract_match_data',
					'message' => 'fixture keys and participants',
					'data' => array(
						'fixture_id' => $fixture_data['id'] ?? 0,
						'has_participants' => isset( $fixture_data['participants'] ) && is_array( $fixture_data['participants'] ),
						'participants_count' => isset( $fixture_data['participants'] ) && is_array( $fixture_data['participants'] ) ? count( $fixture_data['participants'] ) : 0,
						'participants_meta_positions' => isset( $fixture_data['participants'] ) && is_array( $fixture_data['participants'] )
							? array_values(
								array_filter(
									array_map(
										function ( $p ) {
											return $p['meta']['position'] ?? null;
										},
										$fixture_data['participants']
									)
								)
							)
							: array(),
						'fixture_keys' => array_keys( is_array( $fixture_data ) ? $fixture_data : array() ),
					),
					'timestamp' => round( microtime( true ) * 1000 ),
				)
			) . PHP_EOL,
			FILE_APPEND
		);
		// #endregion

		$league_obj = $fixture_data['league'] ?? array();
		$data = array(
			'match_id'        => $fixture_data['id'] ?? 0,
			'team_home_id'    => 0,
			'team_away_id'    => 0,
			'team_home_name'  => '',
			'team_away_name'  => '',
			'team_home_logo'  => '',
			'team_away_logo'  => '',
			'league_id'       => $fixture_data['league_id'] ?? ( is_array( $league_obj ) ? ( $league_obj['id'] ?? 0 ) : 0 ),
			'league_name'     => is_array( $league_obj ) ? ( $league_obj['name'] ?? '' ) : '',
			'venue_id'        => 0,
			'venue_name'      => '',
			'match_date'      => $fixture_data['starting_at'] ?? '',
			'match_status'    => $fixture_data['state']['name'] ?? ( $fixture_data['state']['short_name'] ?? 'NS' ),
			'score_home'      => '',
			'score_away'      => '',
		);
		
		// Extract participants (teams)
		if ( isset( $fixture_data['participants'] ) && is_array( $fixture_data['participants'] ) ) {
			// #region agent log
			@file_put_contents(
				'/Users/LuisMarques_1/Local Sites/super-portistas/app/public/wp-content/plugins/api-sportmonks/.cursor/debug.log',
				wp_json_encode(
					array(
						'sessionId' => 'debug-session',
						'runId' => 'pre-fix',
						'hypothesisId' => 'H5',
						'location' => 'class-sync-manager.php:extract_match_data',
						'message' => 'participants meta sample',
						'data' => array(
							'match_id' => $fixture_data['id'] ?? 0,
							'participants_sample' => array_slice(
								array_map(
									function ( $p ) {
										return array(
											'id' => $p['id'] ?? 0,
											'name' => $p['name'] ?? '',
											'meta' => $p['meta'] ?? array(),
										);
									},
									$fixture_data['participants']
								),
								0,
								2
							),
						),
						'timestamp' => round( microtime( true ) * 1000 ),
					)
				) . PHP_EOL,
				FILE_APPEND
			);
			// #endregion

			foreach ( $fixture_data['participants'] as $participant ) {
				$meta = $participant['meta'] ?? array();
				$position = $meta['position'] ?? '';
				$location = $meta['location'] ?? '';
				$image_path = isset( $participant['image_path'] ) ? esc_url_raw( $participant['image_path'] ) : '';

				if ( $location === 'home' || $position === 'home' ) {
					$data['team_home_id']   = $participant['id'] ?? 0;
					$data['team_home_name'] = $participant['name'] ?? '';
					$data['team_home_logo'] = $image_path;
				} elseif ( $location === 'away' || $position === 'away' ) {
					$data['team_away_id']   = $participant['id'] ?? 0;
					$data['team_away_name'] = $participant['name'] ?? '';
					$data['team_away_logo'] = $image_path;
				}
			}
		}

		if ( ! empty( $data['league_id'] ) && empty( $data['league_name'] ) && is_array( $league_obj ) && ! empty( $league_obj['name'] ) ) {
			$data['league_name'] = $league_obj['name'];
		}

		if ( empty( $data['team_home_id'] ) || empty( $data['team_away_id'] ) ) {
			// #region agent log
			@file_put_contents(
				'/Users/LuisMarques_1/Local Sites/super-portistas/app/public/wp-content/plugins/api-sportmonks/.cursor/debug.log',
				wp_json_encode(
					array(
						'sessionId' => 'debug-session',
						'runId' => 'pre-fix',
						'hypothesisId' => 'H3',
						'location' => 'class-sync-manager.php:extract_match_data',
						'message' => 'missing home/away after parsing',
						'data' => array(
							'match_id' => $data['match_id'],
							'home_id' => $data['team_home_id'],
							'away_id' => $data['team_away_id'],
							'home_name' => $data['team_home_name'],
							'away_name' => $data['team_away_name'],
						),
						'timestamp' => round( microtime( true ) * 1000 ),
					)
				) . PHP_EOL,
				FILE_APPEND
			);
			// #endregion

			APS_Error_Logger::get_instance()->log(
				'SYNC_ERROR',
				'Fixture participants missing home/away positions',
				'PARTICIPANTS_MISSING',
				array( 'fixture_id' => $data['match_id'] )
			);
		}
		
		// #region agent log
		@file_put_contents(
			'/Users/LuisMarques_1/Local Sites/super-portistas/app/public/wp-content/plugins/api-sportmonks/.cursor/debug.log',
			wp_json_encode(
				array(
					'sessionId' => 'debug-session',
					'runId' => 'post-fix',
					'hypothesisId' => 'H6',
					'location' => 'class-sync-manager.php:extract_match_data',
					'message' => 'parsed home/away after location mapping',
					'data' => array(
						'match_id' => $data['match_id'],
						'home_id' => $data['team_home_id'],
						'away_id' => $data['team_away_id'],
						'home_name' => $data['team_home_name'],
						'away_name' => $data['team_away_name'],
						'participants_count' => isset( $fixture_data['participants'] ) && is_array( $fixture_data['participants'] )
							? count( $fixture_data['participants'] )
							: 0,
					),
					'timestamp' => round( microtime( true ) * 1000 ),
				)
			) . PHP_EOL,
			FILE_APPEND
		);
		// #endregion
		
		// Extract scores (API v3: description=CURRENT, score.goals, score.participant; legacy: meta.type=ft)
		if ( isset( $fixture_data['scores'] ) && is_array( $fixture_data['scores'] ) ) {
			foreach ( $fixture_data['scores'] as $score ) {
				$description = $score['description'] ?? '';
				$score_payload = $score['score'] ?? array();
				$meta = $score['meta'] ?? array();
				$score_type = $meta['type'] ?? '';

				if ( $description === 'CURRENT' && is_array( $score_payload ) ) {
					$goals = $score_payload['goals'] ?? $score_payload['score'] ?? '';
					$participant_side = $score_payload['participant'] ?? '';
					if ( $participant_side === 'home' ) {
						$data['score_home'] = (string) $goals;
					} elseif ( $participant_side === 'away' ) {
						$data['score_away'] = (string) $goals;
					}
				} elseif ( $score_type === 'ft' ) {
					$participant_id = $score['participant_id'] ?? 0;
					$score_value = is_array( $score_payload ) ? ( $score_payload['goals'] ?? $score_payload['score'] ?? $score['score'] ?? '' ) : ( $score['score'] ?? '' );
					if ( (int) $participant_id === (int) $data['team_home_id'] ) {
						$data['score_home'] = (string) $score_value;
					} elseif ( (int) $participant_id === (int) $data['team_away_id'] ) {
						$data['score_away'] = (string) $score_value;
					}
				}
			}
		}

		// Extract venue
		if ( isset( $fixture_data['venue'] ) && is_array( $fixture_data['venue'] ) ) {
			$data['venue_id'] = $fixture_data['venue']['id'] ?? 0;
			$data['venue_name'] = $fixture_data['venue']['name'] ?? '';
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
		if ( empty( $match_data['team_home_name'] ) || empty( $match_data['team_away_name'] ) ) {
			// #region agent log
			@file_put_contents(
				'/Users/LuisMarques_1/Local Sites/super-portistas/app/public/wp-content/plugins/api-sportmonks/.cursor/debug.log',
				wp_json_encode(
					array(
						'sessionId' => 'debug-session',
						'runId' => 'pre-fix',
						'hypothesisId' => 'H4',
						'location' => 'class-sync-manager.php:create_match_post',
						'message' => 'missing team names when creating post',
						'data' => array(
							'match_id' => $match_data['match_id'] ?? 0,
							'home_name' => $match_data['team_home_name'] ?? '',
							'away_name' => $match_data['team_away_name'] ?? '',
						),
						'timestamp' => round( microtime( true ) * 1000 ),
					)
				) . PHP_EOL,
				FILE_APPEND
			);
			// #endregion

			APS_Error_Logger::get_instance()->log(
				'SYNC_ERROR',
				'Cannot create match post without team names',
				'MISSING_TEAM_NAMES',
				array( 'match_id' => $match_data['match_id'] )
			);
			return false;
		}

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
		update_post_meta( $post_id, '_aps_team_home_logo', $match_data['team_home_logo'] ?? '' );
		update_post_meta( $post_id, '_aps_team_away_logo', $match_data['team_away_logo'] ?? '' );
		update_post_meta( $post_id, '_aps_league_id', $match_data['league_id'] );
		update_post_meta( $post_id, '_aps_venue_id', $match_data['venue_id'] );
		update_post_meta( $post_id, '_aps_venue_name', $match_data['venue_name'] );
		update_post_meta( $post_id, '_aps_match_date', $match_data['match_date'] );
		update_post_meta( $post_id, '_aps_match_status', $match_data['match_status'] );
		update_post_meta( $post_id, '_aps_score_home', $match_data['score_home'] );
		update_post_meta( $post_id, '_aps_score_away', $match_data['score_away'] );
		update_post_meta( $post_id, '_aps_last_sync', current_time( 'mysql' ) );
		
		// Update title
		if ( empty( $match_data['team_home_name'] ) || empty( $match_data['team_away_name'] ) ) {
			APS_Error_Logger::get_instance()->log(
				'SYNC_ERROR',
				'Cannot update match title without team names',
				'MISSING_TEAM_NAMES',
				array( 'match_id' => $match_data['match_id'], 'post_id' => $post_id )
			);
		} else {
			wp_update_post( array(
				'ID'         => $post_id,
				'post_title' => sprintf(
					'%s vs %s',
					$match_data['team_home_name'],
					$match_data['team_away_name']
				),
			) );
		}
		
		// Associate taxonomies
		$taxonomy_terms = array();
		
		// Associate configured team with category
		if ( ! empty( $team_config['team_id'] ) ) {
			$mapped_term_id = $taxonomy_manager->get_term_id_by_team_id( absint( $team_config['team_id'] ) );
			if ( $mapped_term_id ) {
				$taxonomy_terms['category'][] = $mapped_term_id;
			}
		}
		
		// Associate league with competition (create term if missing)
		if ( ! empty( $match_data['league_id'] ) ) {
			$competition_term_id = $taxonomy_manager->get_term_id_by_league_id( $match_data['league_id'] );
			if ( ! $competition_term_id && ! empty( $match_data['league_name'] ) ) {
				$term = wp_insert_term(
					sanitize_text_field( $match_data['league_name'] ),
					'aps_competicao'
				);
				if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
					update_term_meta( $term['term_id'], 'aps_league_id', (int) $match_data['league_id'] );
					$taxonomy_manager->update_league_mapping( (int) $match_data['league_id'], $term['term_id'] );
					$competition_term_id = $term['term_id'];
				}
			}
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
	 * Update a published match post from API (refresh data).
	 *
	 * @param int $post_id Post ID of aps_jogo.
	 * @return array{success: bool, message: string}
	 */
	public function update_published_match( $post_id ) {
		$result = array( 'success' => false, 'message' => '' );

		if ( get_post_type( $post_id ) !== 'aps_jogo' ) {
			$result['message'] = __( 'Post não é um jogo.', 'api-sportmonks' );
			return $result;
		}

		$match_id = get_post_meta( $post_id, '_aps_match_id', true );
		if ( ! $match_id ) {
			$result['message'] = __( 'Jogo sem Match ID.', 'api-sportmonks' );
			return $result;
		}

		$api_client = APS_API_Client::get_instance();
		$taxonomy_manager = APS_Taxonomy_Manager::get_instance();

		$response = $api_client->request(
			"fixtures/{$match_id}",
			array(),
			array( 'participants', 'scores', 'state', 'league' ),
			false
		);

		if ( is_wp_error( $response ) ) {
			$result['message'] = $response->get_error_message();
			return $result;
		}

		$fixture = $response['data'] ?? $response;
		if ( ! is_array( $fixture ) ) {
			$result['message'] = __( 'Resposta da API inválida.', 'api-sportmonks' );
			return $result;
		}

		$match_data = $this->extract_match_data( $fixture );
		$this->update_match_post( $post_id, $match_data, array(), $taxonomy_manager );

		$result['success'] = true;
		$result['message'] = __( 'Jogo atualizado da API.', 'api-sportmonks' );
		return $result;
	}

	/**
	 * AJAX: Refresh single match from API
	 */
	public function ajax_refresh_match() {
		check_ajax_referer( 'aps_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'api-sportmonks' ) ) );
		}

		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'ID do post inválido.', 'api-sportmonks' ) ) );
		}

		$result = $this->update_published_match( $post_id );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		}

		wp_send_json_error( array( 'message' => $result['message'] ) );
	}

	/**
	 * Handle bulk action "Atualizar da API" on Jogos list
	 */
	public function maybe_handle_bulk_refresh() {
		global $typenow;

		if ( $typenow !== 'aps_jogo' ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';
		if ( $action === '-1' ) {
			$action = isset( $_REQUEST['action2'] ) ? sanitize_text_field( $_REQUEST['action2'] ) : '';
		}

		if ( $action !== 'aps_refresh_match' ) {
			return;
		}

		$post_ids = isset( $_REQUEST['post'] ) && is_array( $_REQUEST['post'] ) ? array_map( 'absint', $_REQUEST['post'] ) : array();
		if ( empty( $post_ids ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Permissão negada.', 'api-sportmonks' ) );
		}

		$updated = 0;
		$errors = 0;
		foreach ( $post_ids as $post_id ) {
			$r = $this->update_published_match( $post_id );
			if ( $r['success'] ) {
				$updated++;
			} else {
				$errors++;
			}
		}

		$redirect = add_query_arg(
			array(
				'post_type'       => 'aps_jogo',
				'aps_refreshed'   => $updated,
				'aps_refresh_err' => $errors,
			),
			admin_url( 'edit.php' )
		);
		wp_safe_redirect( remove_query_arg( array( 'action', 'action2', 'post' ), $redirect ) );
		exit;
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
		
		$msg = sprintf(
			__( 'Sincronização concluída: %d sucesso, %d erros, %d criados, %d atualizados', 'api-sportmonks' ),
			$results['success'],
			$results['error'],
			$results['created'],
			$results['updated']
		);
		if ( ! empty( $results['trashed'] ) ) {
			$msg .= ' ' . sprintf( _n( '%d jogo removido (API).', '%d jogos removidos (API).', $results['trashed'], 'api-sportmonks' ), $results['trashed'] );
		}
		wp_send_json_success( array(
			'message' => $msg,
			'results' => $results,
		) );
	}

	/**
	 * AJAX: Manual sync for date range
	 */
	public function ajax_manual_sync_range() {
		check_ajax_referer( 'aps_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'api-sportmonks' ) ) );
		}

		$date_from = sanitize_text_field( $_POST['date_from'] ?? '' );
		$date_to = sanitize_text_field( $_POST['date_to'] ?? '' );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			wp_send_json_error( array( 'message' => __( 'Datas inválidas.', 'api-sportmonks' ) ) );
		}

		update_option( 'aps_smonks_last_sync_started', current_time( 'mysql' ) );

		$teams = get_option( 'aps_smonks_teams', array() );
		$results = array(
			'success' => 0,
			'error'   => 0,
			'updated' => 0,
			'created' => 0,
		);

		$api_client = APS_API_Client::get_instance();
		$taxonomy_manager = APS_Taxonomy_Manager::get_instance();

		foreach ( $teams as $team ) {
			$team_id = absint( $team['team_id'] ?? 0 );
			if ( ! $team_id ) {
				continue;
			}

			$response = $api_client->request(
				"fixtures/between/{$date_from}/{$date_to}/{$team_id}",
				array( 'per_page' => 1000 ),
				array( 'participants', 'scores', 'state', 'league' ),
				false
			);

			if ( is_wp_error( $response ) ) {
				$results['error']++;
				continue;
			}

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
		}

		update_option( 'aps_smonks_last_sync', current_time( 'mysql' ) );
		update_option( 'aps_smonks_last_sync_results', $results );

		wp_send_json_success( array(
			'message' => sprintf(
				__( 'Sincronização por datas concluída: %d sucesso, %d erros, %d criados, %d atualizados', 'api-sportmonks' ),
				$results['success'],
				$results['error'],
				$results['created'],
				$results['updated']
			),
			'results' => $results,
		) );
	}
}

