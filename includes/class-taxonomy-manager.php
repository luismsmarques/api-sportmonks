<?php
/**
 * Taxonomy Manager Class
 *
 * Manages taxonomy mappings and custom taxonomy registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_Taxonomy_Manager {
	
	/**
	 * Instance
	 *
	 * @var APS_Taxonomy_Manager
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 *
	 * @return APS_Taxonomy_Manager
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
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}
	
	/**
	 * Register custom taxonomy for competitions
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'              => _x( 'Competições', 'taxonomy general name', 'api-sportmonks' ),
			'singular_name'     => _x( 'Competição', 'taxonomy singular name', 'api-sportmonks' ),
			'search_items'      => __( 'Procurar Competições', 'api-sportmonks' ),
			'all_items'         => __( 'Todas as Competições', 'api-sportmonks' ),
			'parent_item'       => null,
			'parent_item_colon' => null,
			'edit_item'         => __( 'Editar Competição', 'api-sportmonks' ),
			'update_item'       => __( 'Atualizar Competição', 'api-sportmonks' ),
			'add_new_item'      => __( 'Adicionar Nova Competição', 'api-sportmonks' ),
			'new_item_name'     => __( 'Nome da Nova Competição', 'api-sportmonks' ),
			'menu_name'         => __( 'Competições', 'api-sportmonks' ),
		);
		
		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'competicao' ),
			'show_in_rest'      => true,
		);
		
		register_taxonomy( 'aps_competicao', array( 'aps_jogo' ), $args );
		
		// Add meta field to store Sportmonks league ID
		add_action( 'aps_competicao_add_form_fields', array( $this, 'add_competition_meta_fields' ) );
		add_action( 'aps_competicao_edit_form_fields', array( $this, 'edit_competition_meta_fields' ) );
		add_action( 'created_aps_competicao', array( $this, 'save_competition_meta_fields' ) );
		add_action( 'edited_aps_competicao', array( $this, 'save_competition_meta_fields' ) );
		
		// Add meta field to category for Sportmonks team ID
		add_action( 'category_add_form_fields', array( $this, 'add_category_meta_fields' ) );
		add_action( 'category_edit_form_fields', array( $this, 'edit_category_meta_fields' ) );
		add_action( 'created_category', array( $this, 'save_category_meta_fields' ) );
		add_action( 'edited_category', array( $this, 'save_category_meta_fields' ) );
	}
	
	/**
	 * Add meta fields to competition add form
	 */
	public function add_competition_meta_fields() {
		?>
		<div class="form-field">
			<label for="aps_league_id"><?php _e( 'Sportmonks League ID', 'api-sportmonks' ); ?></label>
			<input type="number" name="aps_league_id" id="aps_league_id" value="" />
			<p class="description"><?php _e( 'ID da liga na API Sportmonks', 'api-sportmonks' ); ?></p>
		</div>
		<?php
	}
	
	/**
	 * Add meta fields to competition edit form
	 *
	 * @param WP_Term $term Term object
	 */
	public function edit_competition_meta_fields( $term ) {
		$league_id = get_term_meta( $term->term_id, 'aps_league_id', true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="aps_league_id"><?php _e( 'Sportmonks League ID', 'api-sportmonks' ); ?></label>
			</th>
			<td>
				<input type="number" name="aps_league_id" id="aps_league_id" value="<?php echo esc_attr( $league_id ); ?>" />
				<p class="description"><?php _e( 'ID da liga na API Sportmonks', 'api-sportmonks' ); ?></p>
			</td>
		</tr>
		<?php
	}
	
	/**
	 * Save competition meta fields
	 *
	 * @param int $term_id Term ID
	 */
	public function save_competition_meta_fields( $term_id ) {
		if ( isset( $_POST['aps_league_id'] ) ) {
			$league_id = absint( $_POST['aps_league_id'] );
			update_term_meta( $term_id, 'aps_league_id', $league_id );
			
			// Update mapping
			$this->update_league_mapping( $league_id, $term_id );
		}
	}
	
	/**
	 * Add meta fields to category add form
	 */
	public function add_category_meta_fields() {
		?>
		<div class="form-field">
			<label for="aps_team_id"><?php _e( 'Sportmonks Team ID', 'api-sportmonks' ); ?></label>
			<input type="number" name="aps_team_id" id="aps_team_id" value="" />
			<p class="description"><?php _e( 'ID da equipa na API Sportmonks', 'api-sportmonks' ); ?></p>
		</div>
		<?php
	}
	
	/**
	 * Add meta fields to category edit form
	 *
	 * @param WP_Term $term Term object
	 */
	public function edit_category_meta_fields( $term ) {
		$team_id = get_term_meta( $term->term_id, 'aps_team_id', true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="aps_team_id"><?php _e( 'Sportmonks Team ID', 'api-sportmonks' ); ?></label>
			</th>
			<td>
				<input type="number" name="aps_team_id" id="aps_team_id" value="<?php echo esc_attr( $team_id ); ?>" />
				<p class="description"><?php _e( 'ID da equipa na API Sportmonks', 'api-sportmonks' ); ?></p>
			</td>
		</tr>
		<?php
	}
	
	/**
	 * Save category meta fields
	 *
	 * @param int $term_id Term ID
	 */
	public function save_category_meta_fields( $term_id ) {
		if ( isset( $_POST['aps_team_id'] ) ) {
			$team_id = absint( $_POST['aps_team_id'] );
			update_term_meta( $term_id, 'aps_team_id', $team_id );
			
			// Update mapping
			$this->update_team_mapping( $team_id, $term_id );
		}
	}
	
	/**
	 * Get term ID from team ID (Sportmonks)
	 *
	 * @param int $team_id Team ID from Sportmonks
	 * @return int|false Term ID or false if not found
	 */
	public function get_term_id_by_team_id( $team_id ) {
		$mapping = get_option( 'aps_team_mapping', array() );
		return isset( $mapping[ $team_id ] ) ? (int) $mapping[ $team_id ] : false;
	}
	
	/**
	 * Get term ID from league ID (Sportmonks)
	 *
	 * @param int $league_id League ID from Sportmonks
	 * @return int|false Term ID or false if not found
	 */
	public function get_term_id_by_league_id( $league_id ) {
		$mapping = get_option( 'aps_league_mapping', array() );
		return isset( $mapping[ $league_id ] ) ? (int) $mapping[ $league_id ] : false;
	}
	
	/**
	 * Update team mapping
	 *
	 * @param int $team_id Team ID from Sportmonks
	 * @param int $term_id Category term ID
	 */
	public function update_team_mapping( $team_id, $term_id ) {
		$mapping = get_option( 'aps_team_mapping', array() );
		$mapping[ $team_id ] = $term_id;
		update_option( 'aps_team_mapping', $mapping );
	}
	
	/**
	 * Update league mapping
	 *
	 * @param int $league_id League ID from Sportmonks
	 * @param int $term_id Competition term ID
	 */
	public function update_league_mapping( $league_id, $term_id ) {
		$mapping = get_option( 'aps_league_mapping', array() );
		$mapping[ $league_id ] = $term_id;
		update_option( 'aps_league_mapping', $mapping );
	}
	
	/**
	 * Get team ID from term ID
	 *
	 * @param int $term_id Category term ID
	 * @return int|false Team ID or false if not found
	 */
	public function get_team_id_by_term_id( $term_id ) {
		$team_id = get_term_meta( $term_id, 'aps_team_id', true );
		return $team_id ? (int) $team_id : false;
	}
	
	/**
	 * Get league ID from term ID
	 *
	 * @param int $term_id Competition term ID
	 * @return int|false League ID or false if not found
	 */
	public function get_league_id_by_term_id( $term_id ) {
		$league_id = get_term_meta( $term_id, 'aps_league_id', true );
		return $league_id ? (int) $league_id : false;
	}
}

