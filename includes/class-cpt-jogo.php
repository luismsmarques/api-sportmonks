<?php
/**
 * CPT Jogo Class
 *
 * Registers and manages the aps_jogo custom post type
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_CPT_Jogo {
	
	/**
	 * Instance
	 *
	 * @var APS_CPT_Jogo
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 *
	 * @return APS_CPT_Jogo
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
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_aps_jogo', array( $this, 'save_meta_boxes' ) );
		add_filter( 'manage_aps_jogo_posts_columns', array( $this, 'add_custom_columns' ) );
		add_action( 'manage_aps_jogo_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
		add_filter( 'manage_edit-aps_jogo_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( $this, 'render_admin_filters' ) );
		add_filter( 'parse_query', array( $this, 'apply_admin_filters' ) );
		add_filter( 'bulk_actions-edit-aps_jogo', array( $this, 'add_bulk_action_refresh' ) );
		add_action( 'admin_notices', array( $this, 'bulk_refresh_admin_notices' ) );
	}
	
	/**
	 * Register custom post type
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Jogos', 'Post Type General Name', 'api-sportmonks' ),
			'singular_name'         => _x( 'Jogo', 'Post Type Singular Name', 'api-sportmonks' ),
			'menu_name'             => __( 'Jogos', 'api-sportmonks' ),
			'name_admin_bar'        => __( 'Jogo', 'api-sportmonks' ),
			'archives'              => __( 'Arquivo de Jogos', 'api-sportmonks' ),
			'attributes'            => __( 'Atributos do Jogo', 'api-sportmonks' ),
			'parent_item_colon'     => __( 'Jogo Pai:', 'api-sportmonks' ),
			'all_items'             => __( 'Todos os Jogos', 'api-sportmonks' ),
			'add_new_item'          => __( 'Adicionar Novo Jogo', 'api-sportmonks' ),
			'add_new'               => __( 'Adicionar Novo', 'api-sportmonks' ),
			'new_item'              => __( 'Novo Jogo', 'api-sportmonks' ),
			'edit_item'             => __( 'Editar Jogo', 'api-sportmonks' ),
			'update_item'           => __( 'Atualizar Jogo', 'api-sportmonks' ),
			'view_item'             => __( 'Ver Jogo', 'api-sportmonks' ),
			'view_items'            => __( 'Ver Jogos', 'api-sportmonks' ),
			'search_items'          => __( 'Procurar Jogos', 'api-sportmonks' ),
			'not_found'             => __( 'Não encontrado', 'api-sportmonks' ),
			'not_found_in_trash'    => __( 'Não encontrado no lixo', 'api-sportmonks' ),
			'featured_image'        => __( 'Imagem em Destaque', 'api-sportmonks' ),
			'set_featured_image'    => __( 'Definir imagem em destaque', 'api-sportmonks' ),
			'remove_featured_image' => __( 'Remover imagem em destaque', 'api-sportmonks' ),
			'use_featured_image'    => __( 'Usar como imagem em destaque', 'api-sportmonks' ),
			'insert_into_item'      => __( 'Inserir no jogo', 'api-sportmonks' ),
			'uploaded_to_this_item' => __( 'Carregado para este jogo', 'api-sportmonks' ),
			'items_list'            => __( 'Lista de jogos', 'api-sportmonks' ),
			'items_list_navigation' => __( 'Navegação da lista de jogos', 'api-sportmonks' ),
			'filter_items_list'     => __( 'Filtrar lista de jogos', 'api-sportmonks' ),
		);
		
		$args = array(
			'label'                 => __( 'Jogo', 'api-sportmonks' ),
			'description'           => __( 'Jogos de futebol sincronizados da API Sportmonks', 'api-sportmonks' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'taxonomies'            => array( 'category', 'aps_competicao' ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 20,
			'menu_icon'             => 'dashicons-groups',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => true,
			'exclude_from_search'   => false,
			'publicly_queryable'    => true,
			'capability_type'       => 'post',
			'show_in_rest'          => true,
			'rewrite'               => array( 'slug' => 'jogo' ),
		);
		
		register_post_type( 'aps_jogo', $args );
	}
	
	/**
	 * Add meta boxes
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'aps_jogo_details',
			__( 'Detalhes do Jogo', 'api-sportmonks' ),
			array( $this, 'render_details_meta_box' ),
			'aps_jogo',
			'normal',
			'high'
		);
		
		add_meta_box(
			'aps_jogo_api_info',
			__( 'Informação da API', 'api-sportmonks' ),
			array( $this, 'render_api_info_meta_box' ),
			'aps_jogo',
			'side',
			'default'
		);
	}
	
	/**
	 * Render details meta box
	 *
	 * @param WP_Post $post Post object
	 */
	public function render_details_meta_box( $post ) {
		wp_nonce_field( 'aps_jogo_meta_box', 'aps_jogo_meta_box_nonce' );
		
		$match_id = get_post_meta( $post->ID, '_aps_match_id', true );
		$team_home_id = get_post_meta( $post->ID, '_aps_team_home_id', true );
		$team_away_id = get_post_meta( $post->ID, '_aps_team_away_id', true );
		$team_home_name = get_post_meta( $post->ID, '_aps_team_home_name', true );
		$team_away_name = get_post_meta( $post->ID, '_aps_team_away_name', true );
		$team_home_logo = get_post_meta( $post->ID, '_aps_team_home_logo', true );
		$team_away_logo = get_post_meta( $post->ID, '_aps_team_away_logo', true );
		$league_id = get_post_meta( $post->ID, '_aps_league_id', true );
		$venue_id = get_post_meta( $post->ID, '_aps_venue_id', true );
		$venue_name = get_post_meta( $post->ID, '_aps_venue_name', true );
		$match_date = get_post_meta( $post->ID, '_aps_match_date', true );
		$match_status = get_post_meta( $post->ID, '_aps_match_status', true );
		$score_home = get_post_meta( $post->ID, '_aps_score_home', true );
		$score_away = get_post_meta( $post->ID, '_aps_score_away', true );
		
		?>
		<table class="form-table">
			<tr>
				<th><label for="_aps_match_id"><?php _e( 'Match ID', 'api-sportmonks' ); ?></label></th>
				<td><input type="number" id="_aps_match_id" name="_aps_match_id" value="<?php echo esc_attr( $match_id ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="_aps_team_home_name"><?php _e( 'Equipa Casa', 'api-sportmonks' ); ?></label></th>
				<td><input type="text" id="_aps_team_home_name" name="_aps_team_home_name" value="<?php echo esc_attr( $team_home_name ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="_aps_team_away_name"><?php _e( 'Equipa Visitante', 'api-sportmonks' ); ?></label></th>
				<td><input type="text" id="_aps_team_away_name" name="_aps_team_away_name" value="<?php echo esc_attr( $team_away_name ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label><?php _e( 'Logo Casa', 'api-sportmonks' ); ?></label></th>
				<td>
					<?php if ( $team_home_logo ) : ?>
						<img src="<?php echo esc_url( $team_home_logo ); ?>" alt="" style="max-width: 32px; height: auto; vertical-align: middle;" />
						<input type="url" id="_aps_team_home_logo" name="_aps_team_home_logo" value="<?php echo esc_attr( $team_home_logo ); ?>" class="regular-text" style="margin-left: 8px;" placeholder="<?php esc_attr_e( 'URL do logo', 'api-sportmonks' ); ?>" />
					<?php else : ?>
						<input type="url" id="_aps_team_home_logo" name="_aps_team_home_logo" value="" class="regular-text" placeholder="<?php esc_attr_e( 'URL do logo (preenchido pelo sync)', 'api-sportmonks' ); ?>" />
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label><?php _e( 'Logo Visitante', 'api-sportmonks' ); ?></label></th>
				<td>
					<?php if ( $team_away_logo ) : ?>
						<img src="<?php echo esc_url( $team_away_logo ); ?>" alt="" style="max-width: 32px; height: auto; vertical-align: middle;" />
						<input type="url" id="_aps_team_away_logo" name="_aps_team_away_logo" value="<?php echo esc_attr( $team_away_logo ); ?>" class="regular-text" style="margin-left: 8px;" placeholder="<?php esc_attr_e( 'URL do logo', 'api-sportmonks' ); ?>" />
					<?php else : ?>
						<input type="url" id="_aps_team_away_logo" name="_aps_team_away_logo" value="" class="regular-text" placeholder="<?php esc_attr_e( 'URL do logo (preenchido pelo sync)', 'api-sportmonks' ); ?>" />
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="_aps_match_date"><?php _e( 'Data/Hora', 'api-sportmonks' ); ?></label></th>
				<td><input type="datetime-local" id="_aps_match_date" name="_aps_match_date" value="<?php echo esc_attr( $match_date ? date( 'Y-m-d\TH:i', strtotime( $match_date ) ) : '' ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="_aps_venue_name"><?php _e( 'Estádio', 'api-sportmonks' ); ?></label></th>
				<td>
					<input type="text" id="_aps_venue_name" name="_aps_venue_name" value="<?php echo esc_attr( $venue_name ); ?>" class="regular-text" />
					<input type="hidden" id="_aps_venue_id" name="_aps_venue_id" value="<?php echo esc_attr( $venue_id ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="_aps_match_status"><?php _e( 'Estado', 'api-sportmonks' ); ?></label></th>
				<td>
					<select id="_aps_match_status" name="_aps_match_status">
						<option value="NS" <?php selected( $match_status, 'NS' ); ?>><?php _e( 'Por Começar', 'api-sportmonks' ); ?></option>
						<option value="LIVE" <?php selected( $match_status, 'LIVE' ); ?>><?php _e( 'Ao Vivo', 'api-sportmonks' ); ?></option>
						<option value="HT" <?php selected( $match_status, 'HT' ); ?>><?php _e( 'Intervalo', 'api-sportmonks' ); ?></option>
						<option value="FT" <?php selected( $match_status, 'FT' ); ?>><?php _e( 'Terminado', 'api-sportmonks' ); ?></option>
						<option value="CANC" <?php selected( $match_status, 'CANC' ); ?>><?php _e( 'Cancelado', 'api-sportmonks' ); ?></option>
						<option value="POSTP" <?php selected( $match_status, 'POSTP' ); ?>><?php _e( 'Adiado', 'api-sportmonks' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label><?php _e( 'Resultado', 'api-sportmonks' ); ?></label></th>
				<td>
					<input type="number" id="_aps_score_home" name="_aps_score_home" value="<?php echo esc_attr( $score_home ); ?>" style="width: 60px;" /> 
					vs 
					<input type="number" id="_aps_score_away" name="_aps_score_away" value="<?php echo esc_attr( $score_away ); ?>" style="width: 60px;" />
				</td>
			</tr>
		</table>
		<?php
	}
	
	/**
	 * Render API info meta box
	 *
	 * @param WP_Post $post Post object
	 */
	public function render_api_info_meta_box( $post ) {
		$match_id = get_post_meta( $post->ID, '_aps_match_id', true );
		$last_sync = get_post_meta( $post->ID, '_aps_last_sync', true );
		
		?>
		<p><strong><?php _e( 'Match ID:', 'api-sportmonks' ); ?></strong> <?php echo esc_html( $match_id ?: '-' ); ?></p>
		<?php if ( $last_sync ) : ?>
			<p><strong><?php _e( 'Última Sincronização:', 'api-sportmonks' ); ?></strong><br><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_sync ) ) ); ?></p>
		<?php endif; ?>
		<?php if ( $match_id ) : ?>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=aps-data-explorer&match_id=' . $match_id ) ); ?>" class="button"><?php _e( 'Ver Dados Completos', 'api-sportmonks' ); ?></a></p>
			<p>
				<button type="button" class="button aps-refresh-match" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'aps_settings_nonce' ) ); ?>">
					<?php _e( 'Atualizar dados da API', 'api-sportmonks' ); ?>
				</button>
				<span class="aps-refresh-result" style="margin-left:8px;"></span>
			</p>
			<script>
			(function() {
				var btn = document.querySelector('.aps-refresh-match');
				if (!btn) return;
				btn.addEventListener('click', function() {
					var result = document.querySelector('.aps-refresh-result');
					result.textContent = '<?php echo esc_js( __( 'A atualizar...', 'api-sportmonks' ) ); ?>';
					var formData = new FormData();
					formData.append('action', 'aps_refresh_match');
					formData.append('nonce', btn.getAttribute('data-nonce'));
					formData.append('post_id', btn.getAttribute('data-post-id'));
					fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: formData, credentials: 'same-origin' })
						.then(function(r) { return r.json(); })
						.then(function(data) {
							result.textContent = data.success ? data.data.message : (data.data && data.data.message ? data.data.message : '<?php echo esc_js( __( 'Erro.', 'api-sportmonks' ) ); ?>');
							if (data.success) { setTimeout(function() { location.reload(); }, 800); }
						})
						.catch(function() { result.textContent = '<?php echo esc_js( __( 'Erro de rede.', 'api-sportmonks' ) ); ?>'; });
				});
			})();
			</script>
		<?php endif; ?>
		<?php
	}
	
	/**
	 * Save meta boxes
	 *
	 * @param int $post_id Post ID
	 */
	public function save_meta_boxes( $post_id ) {
		// Check nonce
		if ( ! isset( $_POST['aps_jogo_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['aps_jogo_meta_box_nonce'], 'aps_jogo_meta_box' ) ) {
			return;
		}
		
		// Check autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		
		// Save meta fields
		$fields = array(
			'_aps_match_id',
			'_aps_team_home_id',
			'_aps_team_away_id',
			'_aps_team_home_name',
			'_aps_team_away_name',
			'_aps_team_home_logo',
			'_aps_team_away_logo',
			'_aps_league_id',
			'_aps_venue_id',
			'_aps_venue_name',
			'_aps_match_date',
			'_aps_match_status',
			'_aps_score_home',
			'_aps_score_away',
		);
		
		$url_fields = array( '_aps_team_home_logo', '_aps_team_away_logo' );
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = in_array( $field, $url_fields, true ) ? esc_url_raw( wp_unslash( $_POST[ $field ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				update_post_meta( $post_id, $field, $value );
			}
		}
	}
	
	/**
	 * Add custom columns
	 *
	 * @param array $columns Columns
	 * @return array
	 */
	public function add_custom_columns( $columns ) {
		$new_columns = array();
		$new_columns['cb'] = $columns['cb'];
		$new_columns['title'] = $columns['title'];
		$new_columns['aps_teams'] = __( 'Equipas', 'api-sportmonks' );
		$new_columns['aps_result'] = __( 'Resultado', 'api-sportmonks' );
		$new_columns['aps_date'] = __( 'Data', 'api-sportmonks' );
		$new_columns['aps_status'] = __( 'Estado', 'api-sportmonks' );
		$new_columns['aps_venue'] = __( 'Estádio', 'api-sportmonks' );
		$new_columns['aps_competicao'] = __( 'Competição', 'api-sportmonks' );
		$new_columns['date'] = $columns['date'];
		
		return $new_columns;
	}
	
	/**
	 * Render custom columns
	 *
	 * @param string $column Column name
	 * @param int    $post_id Post ID
	 */
	public function render_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'aps_teams':
				$home = get_post_meta( $post_id, '_aps_team_home_name', true );
				$away = get_post_meta( $post_id, '_aps_team_away_name', true );
				echo esc_html( $home ?: '-' ) . ' vs ' . esc_html( $away ?: '-' );
				break;
				
			case 'aps_result':
				$score_home = get_post_meta( $post_id, '_aps_score_home', true );
				$score_away = get_post_meta( $post_id, '_aps_score_away', true );
				if ( $score_home !== '' && $score_away !== '' ) {
					echo esc_html( $score_home ) . ' - ' . esc_html( $score_away );
				} else {
					echo '-';
				}
				break;
				
			case 'aps_date':
				$date = get_post_meta( $post_id, '_aps_match_date', true );
				if ( $date ) {
					echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date ) ) );
				} else {
					echo '-';
				}
				break;
				
			case 'aps_status':
				$status = get_post_meta( $post_id, '_aps_match_status', true );
				$status_labels = array(
					'NS'    => __( 'Por Começar', 'api-sportmonks' ),
					'LIVE'  => __( 'Ao Vivo', 'api-sportmonks' ),
					'HT'    => __( 'Intervalo', 'api-sportmonks' ),
					'FT'    => __( 'Terminado', 'api-sportmonks' ),
					'CANC'  => __( 'Cancelado', 'api-sportmonks' ),
					'POSTP' => __( 'Adiado', 'api-sportmonks' ),
				);
				echo esc_html( isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status );
				break;
				
			case 'aps_venue':
				$venue = get_post_meta( $post_id, '_aps_venue_name', true );
				echo esc_html( $venue ?: '-' );
				break;
				
			case 'aps_competicao':
				$terms = get_the_terms( $post_id, 'aps_competicao' );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$term_names = array();
					foreach ( $terms as $term ) {
						$term_names[] = $term->name;
					}
					echo esc_html( implode( ', ', $term_names ) );
				} else {
					echo '-';
				}
				break;
		}
	}
	
	/**
	 * Make columns sortable
	 *
	 * @param array $columns Columns
	 * @return array
	 */
	public function sortable_columns( $columns ) {
		$columns['aps_date'] = '_aps_match_date';
		$columns['aps_status'] = '_aps_match_status';
		return $columns;
	}

	/**
	 * Render admin filters for CPT list
	 */
	public function render_admin_filters() {
		global $typenow;

		if ( 'aps_jogo' !== $typenow ) {
			return;
		}

		$selected_status = sanitize_text_field( $_GET['aps_status'] ?? '' );
		$selected_month = sanitize_text_field( $_GET['aps_month'] ?? '' );

		$status_options = array(
			''      => __( 'Todos os estados', 'api-sportmonks' ),
			'NS'    => __( 'Por Começar', 'api-sportmonks' ),
			'LIVE'  => __( 'Ao Vivo', 'api-sportmonks' ),
			'HT'    => __( 'Intervalo', 'api-sportmonks' ),
			'FT'    => __( 'Terminado', 'api-sportmonks' ),
			'CANC'  => __( 'Cancelado', 'api-sportmonks' ),
			'POSTP' => __( 'Adiado', 'api-sportmonks' ),
		);

		echo '<select name="aps_status">';
		foreach ( $status_options as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $selected_status, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		echo '<input type="month" name="aps_month" value="' . esc_attr( $selected_month ) . '" />';
	}

	/**
	 * Apply admin filters to query
	 *
	 * @param WP_Query $query Query instance
	 * @return WP_Query
	 */
	public function apply_admin_filters( $query ) {
		global $pagenow;

		if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
			return $query;
		}

		$post_type = $query->get( 'post_type' );
		if ( 'aps_jogo' !== $post_type ) {
			return $query;
		}

		$meta_query = $query->get( 'meta_query', array() );
		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}

		$status = sanitize_text_field( $_GET['aps_status'] ?? '' );
		if ( $status ) {
			$meta_query[] = array(
				'key'   => '_aps_match_status',
				'value' => $status,
			);
		}

		$month = sanitize_text_field( $_GET['aps_month'] ?? '' );
		if ( preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			$start = $month . '-01 00:00:00';
			$end = gmdate( 'Y-m-t 23:59:59', strtotime( $start ) );
			$meta_query[] = array(
				'key'     => '_aps_match_date',
				'value'   => array( $start, $end ),
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME',
			);
		}

		if ( ! empty( $meta_query ) ) {
			$query->set( 'meta_query', $meta_query );
		}

		return $query;
	}

	/**
	 * Add bulk action "Atualizar da API" to Jogos list
	 *
	 * @param array $actions Bulk actions
	 * @return array
	 */
	public function add_bulk_action_refresh( $actions ) {
		$actions['aps_refresh_match'] = __( 'Atualizar da API', 'api-sportmonks' );
		return $actions;
	}

	/**
	 * Admin notice after bulk refresh
	 */
	public function bulk_refresh_admin_notices() {
		global $typenow;

		if ( $typenow !== 'aps_jogo' || ! isset( $_GET['aps_refreshed'] ) ) {
			return;
		}

		$updated = absint( $_GET['aps_refreshed'] ?? 0 );
		$errors  = absint( $_GET['aps_refresh_err'] ?? 0 );

		if ( $updated > 0 ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html( sprintf( _n( '%d jogo atualizado da API.', '%d jogos atualizados da API.', $updated, 'api-sportmonks' ), $updated ) );
			echo '</p></div>';
		}
		if ( $errors > 0 ) {
			echo '<div class="notice notice-warning is-dismissible"><p>';
			echo esc_html( sprintf( _n( '%d jogo não foi possível atualizar.', '%d jogos não foi possível atualizar.', $errors, 'api-sportmonks' ), $errors ) );
			echo '</p></div>';
		}
	}
}

