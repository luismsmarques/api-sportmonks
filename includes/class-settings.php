<?php
/**
 * Settings Class
 *
 * Handles plugin settings page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_Settings {
	
	/**
	 * Instance
	 *
	 * @var APS_Settings
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 *
	 * @return APS_Settings
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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_aps_test_api_token', array( $this, 'ajax_test_api_token' ) );
		add_action( 'wp_ajax_aps_add_team', array( $this, 'ajax_add_team' ) );
		add_action( 'wp_ajax_aps_remove_team', array( $this, 'ajax_remove_team' ) );
		add_action( 'wp_ajax_aps_search_team', array( $this, 'ajax_search_team' ) );
	}
	
	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'API Sportmonks', 'api-sportmonks' ),
			__( 'Sportmonks', 'api-sportmonks' ),
			'manage_options',
			'aps-sportmonks',
			array( $this, 'render_settings_page' ),
			'dashicons-groups',
			30
		);
		
		add_submenu_page(
			'aps-sportmonks',
			__( 'Configurações', 'api-sportmonks' ),
			__( 'Configurações', 'api-sportmonks' ),
			'manage_options',
			'aps-sportmonks',
			array( $this, 'render_settings_page' )
		);
	}
	
	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'aps_smonks_settings', 'aps_smonks_api_token', array(
			'sanitize_callback' => 'sanitize_text_field',
		) );
		
		register_setting( 'aps_smonks_settings', 'aps_smonks_sync_frequency', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'hourly',
		) );
		
		register_setting( 'aps_smonks_settings', 'aps_smonks_teams', array(
			'sanitize_callback' => array( $this, 'sanitize_teams' ),
			'default'           => array(),
		) );

		register_setting( 'aps_smonks_settings', 'aps_smonks_sync_squads', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default'           => 1,
		) );

		register_setting( 'aps_smonks_settings', 'aps_smonks_sync_injuries', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default'           => 1,
		) );

		register_setting( 'aps_smonks_settings', 'aps_smonks_sync_transfers', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default'           => 1,
		) );

		register_setting( 'aps_smonks_settings', 'aps_smonks_cache_ttl_squads', array(
			'sanitize_callback' => 'absint',
			'default'           => 21600,
		) );

		register_setting( 'aps_smonks_settings', 'aps_smonks_cache_ttl_injuries', array(
			'sanitize_callback' => 'absint',
			'default'           => 1800,
		) );

		register_setting( 'aps_smonks_settings', 'aps_smonks_cache_ttl_transfers', array(
			'sanitize_callback' => 'absint',
			'default'           => 21600,
		) );
	}
	
	/**
	 * Sanitize teams array
	 *
	 * @param array $teams Teams array
	 * @return array
	 */
	public function sanitize_teams( $teams ) {
		if ( ! is_array( $teams ) ) {
			return array();
		}
		
		$sanitized = array();
		foreach ( $teams as $team ) {
			if ( isset( $team['team_id'] ) && isset( $team['team_name'] ) ) {
				$sanitized[] = array(
					'team_id'   => absint( $team['team_id'] ),
					'team_name' => sanitize_text_field( $team['team_name'] ),
				);
			}
		}
		
		return $sanitized;
	}

	/**
	 * Sanitize checkbox
	 *
	 * @param mixed $value Value
	 * @return int
	 */
	public function sanitize_checkbox( $value ) {
		return empty( $value ) ? 0 : 1;
	}
	
	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_aps-sportmonks' !== $hook ) {
			return;
		}
		
		wp_enqueue_script( 'aps-settings', APS_SMONKS_PLUGIN_URL . 'assets/js/settings.js', array( 'jquery' ), APS_SMONKS_VERSION, true );
		wp_enqueue_style( 'aps-settings', APS_SMONKS_PLUGIN_URL . 'assets/css/settings.css', array(), APS_SMONKS_VERSION );
		
		wp_localize_script( 'aps-settings', 'apsSettings', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'aps_settings_nonce' ),
			'i18n'    => array(
				'confirmRemove' => __( 'Tem certeza que deseja remover esta equipa?', 'api-sportmonks' ),
				'testing'       => __( 'A testar...', 'api-sportmonks' ),
				'success'       => __( 'Sucesso!', 'api-sportmonks' ),
				'error'         => __( 'Erro:', 'api-sportmonks' ),
				'tokenRequired' => __( 'Token nao fornecido.', 'api-sportmonks' ),
				'requestFailed' => __( 'Pedido falhou.', 'api-sportmonks' ),
				'testToken'     => __( 'Testar Token', 'api-sportmonks' ),
				'searching'     => __( 'A pesquisar...', 'api-sportmonks' ),
				'search'        => __( 'Pesquisar', 'api-sportmonks' ),
				'noResults'     => __( 'Sem resultados.', 'api-sportmonks' ),
				'addTeam'       => __( 'Adicionar Equipa', 'api-sportmonks' ),
			),
		) );
	}
	
	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Handle form submission
		if ( isset( $_POST['aps_save_settings'] ) && check_admin_referer( 'aps_save_settings' ) ) {
			update_option( 'aps_smonks_api_token', sanitize_text_field( $_POST['aps_smonks_api_token'] ?? '' ) );
			update_option( 'aps_smonks_sync_frequency', sanitize_text_field( $_POST['aps_smonks_sync_frequency'] ?? 'hourly' ) );
			update_option( 'aps_smonks_sync_squads', $this->sanitize_checkbox( $_POST['aps_smonks_sync_squads'] ?? 0 ) );
			update_option( 'aps_smonks_sync_injuries', $this->sanitize_checkbox( $_POST['aps_smonks_sync_injuries'] ?? 0 ) );
			update_option( 'aps_smonks_sync_transfers', $this->sanitize_checkbox( $_POST['aps_smonks_sync_transfers'] ?? 0 ) );
			update_option( 'aps_smonks_cache_ttl_squads', absint( $_POST['aps_smonks_cache_ttl_squads'] ?? 21600 ) );
			update_option( 'aps_smonks_cache_ttl_injuries', absint( $_POST['aps_smonks_cache_ttl_injuries'] ?? 1800 ) );
			update_option( 'aps_smonks_cache_ttl_transfers', absint( $_POST['aps_smonks_cache_ttl_transfers'] ?? 21600 ) );
			
			// Process teams
			if ( isset( $_POST['teams'] ) && is_array( $_POST['teams'] ) ) {
				$teams = $this->sanitize_teams( $_POST['teams'] );
				update_option( 'aps_smonks_teams', $teams );

			foreach ( $_POST['teams'] as $index => $team_post ) {
				if ( ! isset( $teams[ $index ] ) ) {
					continue;
				}

				$team_id = $teams[ $index ]['team_id'];
				$category_id = absint( $team_post['category_id'] ?? 0 );

				if ( $team_id && $category_id ) {
					update_term_meta( $category_id, 'aps_team_id', $team_id );
					APS_Taxonomy_Manager::get_instance()->update_team_mapping( $team_id, $category_id );
				}
			}
			}
			
			// Update API client token
			APS_API_Client::get_instance()->set_api_token( get_option( 'aps_smonks_api_token' ) );
			
			// Update cron schedule
			APS_Cron_Handler::get_instance()->update_schedule();
			
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Configurações guardadas!', 'api-sportmonks' ) . '</p></div>';
		}
		
		$api_token = get_option( 'aps_smonks_api_token', '' );
		$sync_frequency = get_option( 'aps_smonks_sync_frequency', 'hourly' );
		$teams = get_option( 'aps_smonks_teams', array() );
		$sync_squads = (int) get_option( 'aps_smonks_sync_squads', 1 );
		$sync_injuries = (int) get_option( 'aps_smonks_sync_injuries', 1 );
		$sync_transfers = (int) get_option( 'aps_smonks_sync_transfers', 1 );
		$ttl_squads = (int) get_option( 'aps_smonks_cache_ttl_squads', 21600 );
		$ttl_injuries = (int) get_option( 'aps_smonks_cache_ttl_injuries', 1800 );
		$ttl_transfers = (int) get_option( 'aps_smonks_cache_ttl_transfers', 21600 );
		$categories = get_terms( array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
		) );
		
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'aps_save_settings' ); ?>
				
				<h2><?php _e( 'Configuração da API', 'api-sportmonks' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="aps_smonks_api_token"><?php _e( 'API Token', 'api-sportmonks' ); ?></label>
						</th>
						<td>
							<input type="text" id="aps_smonks_api_token" name="aps_smonks_api_token" value="<?php echo esc_attr( $api_token ); ?>" class="regular-text" />
							<button type="button" id="aps-test-token" class="button"><?php _e( 'Testar Token', 'api-sportmonks' ); ?></button>
							<p class="description"><?php _e( 'Obtenha o seu token em', 'api-sportmonks' ); ?> <a href="https://my.sportmonks.com/" target="_blank">My.Sportmonks</a></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="aps_smonks_sync_frequency"><?php _e( 'Frequência de Sincronização', 'api-sportmonks' ); ?></label>
						</th>
						<td>
							<select id="aps_smonks_sync_frequency" name="aps_smonks_sync_frequency">
								<option value="15min" <?php selected( $sync_frequency, '15min' ); ?>><?php _e( 'A cada 15 minutos', 'api-sportmonks' ); ?></option>
								<option value="30min" <?php selected( $sync_frequency, '30min' ); ?>><?php _e( 'A cada 30 minutos', 'api-sportmonks' ); ?></option>
								<option value="hourly" <?php selected( $sync_frequency, 'hourly' ); ?>><?php _e( 'A cada hora', 'api-sportmonks' ); ?></option>
								<option value="2hours" <?php selected( $sync_frequency, '2hours' ); ?>><?php _e( 'A cada 2 horas', 'api-sportmonks' ); ?></option>
								<option value="6hours" <?php selected( $sync_frequency, '6hours' ); ?>><?php _e( 'A cada 6 horas', 'api-sportmonks' ); ?></option>
								<option value="12hours" <?php selected( $sync_frequency, '12hours' ); ?>><?php _e( 'A cada 12 horas', 'api-sportmonks' ); ?></option>
								<option value="daily" <?php selected( $sync_frequency, 'daily' ); ?>><?php _e( 'Diariamente', 'api-sportmonks' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php _e( 'Sincronizacao Avancada', 'api-sportmonks' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php _e( 'Sincronizar Plantel', 'api-sportmonks' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="aps_smonks_sync_squads" value="1" <?php checked( $sync_squads, 1 ); ?> />
								<?php _e( 'Ativo', 'api-sportmonks' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Sincronizar Lesoes', 'api-sportmonks' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="aps_smonks_sync_injuries" value="1" <?php checked( $sync_injuries, 1 ); ?> />
								<?php _e( 'Ativo', 'api-sportmonks' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Sincronizar Transferencias', 'api-sportmonks' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="aps_smonks_sync_transfers" value="1" <?php checked( $sync_transfers, 1 ); ?> />
								<?php _e( 'Ativo', 'api-sportmonks' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php _e( 'Cache (TTL em segundos)', 'api-sportmonks' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php _e( 'Plantel', 'api-sportmonks' ); ?></th>
						<td><input type="number" name="aps_smonks_cache_ttl_squads" value="<?php echo esc_attr( $ttl_squads ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Lesoes', 'api-sportmonks' ); ?></th>
						<td><input type="number" name="aps_smonks_cache_ttl_injuries" value="<?php echo esc_attr( $ttl_injuries ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Transferencias', 'api-sportmonks' ); ?></th>
						<td><input type="number" name="aps_smonks_cache_ttl_transfers" value="<?php echo esc_attr( $ttl_transfers ); ?>" class="small-text" /></td>
					</tr>
				</table>
				
				<h2><?php _e( 'Equipas', 'api-sportmonks' ); ?></h2>
				<div class="aps-team-search">
					<label for="aps-team-search"><?php _e( 'Pesquisar equipa por nome', 'api-sportmonks' ); ?></label>
					<div class="aps-team-search-controls">
						<input type="text" id="aps-team-search" class="regular-text" placeholder="<?php esc_attr_e( 'Ex: FC Porto', 'api-sportmonks' ); ?>" />
						<button type="button" id="aps-team-search-button" class="button"><?php _e( 'Pesquisar', 'api-sportmonks' ); ?></button>
					</div>
					<div id="aps-team-search-results" class="aps-team-search-results"></div>
				</div>
				<div id="aps-teams-list">
					<?php foreach ( $teams as $index => $team ) : ?>
						<div class="aps-team-item" data-index="<?php echo esc_attr( $index ); ?>">
							<table class="form-table">
								<tr>
									<th><?php _e( 'Team ID', 'api-sportmonks' ); ?></th>
									<td>
										<input type="number" name="teams[<?php echo esc_attr( $index ); ?>][team_id]" value="<?php echo esc_attr( $team['team_id'] ); ?>" class="small-text" />
									</td>
								</tr>
								<tr>
									<th><?php _e( 'Nome da Equipa', 'api-sportmonks' ); ?></th>
									<td>
										<input type="text" name="teams[<?php echo esc_attr( $index ); ?>][team_name]" value="<?php echo esc_attr( $team['team_name'] ); ?>" class="regular-text" />
									</td>
								</tr>
								<tr>
									<th><?php _e( 'Categoria WordPress', 'api-sportmonks' ); ?></th>
									<td>
										<select name="teams[<?php echo esc_attr( $index ); ?>][category_id]" class="aps-category-select">
											<option value=""><?php _e( '-- Selecionar --', 'api-sportmonks' ); ?></option>
											<?php foreach ( $categories as $cat ) :
												$current_team_id = get_term_meta( $cat->term_id, 'aps_team_id', true );
												$selected = ( (string) $current_team_id === (string) $team['team_id'] ) ? 'selected' : '';
											?>
												<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo $selected; ?>>
													<?php echo esc_html( $cat->name ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th></th>
									<td>
										<button type="button" class="button button-link-delete aps-remove-team"><?php _e( 'Remover Equipa', 'api-sportmonks' ); ?></button>
									</td>
								</tr>
							</table>
							<hr>
						</div>
					<?php endforeach; ?>
				</div>
				
				<p>
					<button type="button" id="aps-add-team" class="button"><?php _e( '+ Adicionar Equipa', 'api-sportmonks' ); ?></button>
					<button type="button" id="aps-manual-sync" class="button button-secondary" style="margin-left: 10px;">
						<?php _e( 'Sincronizar Agora', 'api-sportmonks' ); ?>
					</button>
					<span id="aps-sync-status" style="margin-left: 10px;"></span>
				</p>
				
				<?php submit_button( __( 'Guardar Configurações', 'api-sportmonks' ), 'primary', 'aps_save_settings' ); ?>
			</form>
		</div>
		
		<script type="text/html" id="aps-team-template">
			<div class="aps-team-item" data-index="{{index}}">
				<table class="form-table">
					<tr>
						<th><?php _e( 'Team ID', 'api-sportmonks' ); ?></th>
						<td><input type="number" name="teams[{{index}}][team_id]" value="" class="small-text" /></td>
					</tr>
					<tr>
						<th><?php _e( 'Nome da Equipa', 'api-sportmonks' ); ?></th>
						<td><input type="text" name="teams[{{index}}][team_name]" value="" class="regular-text" /></td>
					</tr>
					<tr>
						<th><?php _e( 'Categoria WordPress', 'api-sportmonks' ); ?></th>
						<td>
							<select name="teams[{{index}}][category_id]" class="aps-category-select">
								<option value=""><?php _e( '-- Selecionar --', 'api-sportmonks' ); ?></option>
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th></th>
						<td>
							<button type="button" class="button button-link-delete aps-remove-team"><?php _e( 'Remover Equipa', 'api-sportmonks' ); ?></button>
						</td>
					</tr>
				</table>
				<hr>
			</div>
		</script>
		<?php
	}
	
	/**
	 * AJAX: Test API token
	 */
	public function ajax_test_api_token() {
		check_ajax_referer( 'aps_settings_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'api-sportmonks' ) ) );
		}
		
		$token = sanitize_text_field( $_POST['token'] ?? '' );
		
		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Token não fornecido.', 'api-sportmonks' ) ) );
		}
		
		$client = APS_API_Client::get_instance();
		$client->set_api_token( $token );
		
		// Test with a simple endpoint
		$response = $client->request( 'leagues', array( 'per_page' => 1 ), array(), false );
		
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}
		
		wp_send_json_success( array( 'message' => __( 'Token válido!', 'api-sportmonks' ) ) );
	}

	/**
	 * AJAX: Search team by name
	 */
	public function ajax_search_team() {
		check_ajax_referer( 'aps_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'api-sportmonks' ) ) );
		}

		$query = sanitize_text_field( $_POST['query'] ?? '' );
		if ( empty( $query ) ) {
			wp_send_json_error( array( 'message' => __( 'Nome da equipa nao fornecido.', 'api-sportmonks' ) ) );
		}

		$client = APS_API_Client::get_instance();
		$response = $client->search_teams( $query, array( 'per_page' => 10 ) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$teams = array();
		foreach ( $response['data'] ?? array() as $team ) {
			$teams[] = array(
				'id'         => $team['id'] ?? 0,
				'name'       => $team['name'] ?? '',
				'image_path' => $team['image_path'] ?? '',
			);
		}

		wp_send_json_success( array( 'teams' => $teams ) );
	}
	
	/**
	 * AJAX: Add team
	 */
	public function ajax_add_team() {
		check_ajax_referer( 'aps_settings_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		
		$teams = get_option( 'aps_smonks_teams', array() );
		$teams[] = array(
			'team_id'   => 0,
			'team_name' => '',
			'league_id' => 0,
		);
		update_option( 'aps_smonks_teams', $teams );
		
		wp_send_json_success( array( 'index' => count( $teams ) - 1 ) );
	}
	
	/**
	 * AJAX: Remove team
	 */
	public function ajax_remove_team() {
		check_ajax_referer( 'aps_settings_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		
		$index = absint( $_POST['index'] ?? 0 );
		$teams = get_option( 'aps_smonks_teams', array() );
		
		if ( isset( $teams[ $index ] ) ) {
			unset( $teams[ $index ] );
			$teams = array_values( $teams ); // Reindex
			update_option( 'aps_smonks_teams', $teams );
			wp_send_json_success();
		}
		
		wp_send_json_error();
	}
	
}

