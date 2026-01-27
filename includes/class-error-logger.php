<?php
/**
 * Error Logger Class
 *
 * Handles all error logging for the plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_Error_Logger {
	
	/**
	 * Table name
	 */
	const TABLE_NAME = 'aps_error_logs';
	
	/**
	 * Instance
	 *
	 * @var APS_Error_Logger
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 *
	 * @return APS_Error_Logger
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
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}
	
	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'aps-sportmonks',
			__( 'Error Log', 'api-sportmonks' ),
			__( 'Error Log', 'api-sportmonks' ),
			'manage_options',
			'aps-error-log',
			array( $this, 'render_page' )
		);
	}
	
	/**
	 * Handle admin actions
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'aps-error-log' ) {
			return;
		}
		
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Delete log
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['log_id'] ) ) {
			check_admin_referer( 'aps_delete_log_' . $_GET['log_id'] );
			$this->delete_log( absint( $_GET['log_id'] ) );
			wp_redirect( admin_url( 'admin.php?page=aps-error-log&deleted=1' ) );
			exit;
		}
		
		// Clear old logs
		if ( isset( $_POST['aps_clear_old_logs'] ) ) {
			check_admin_referer( 'aps_clear_old_logs' );
			$days = absint( $_POST['days'] ?? 30 );
			$deleted = $this->clear_old_logs( $days );
			wp_redirect( admin_url( 'admin.php?page=aps-error-log&cleared=' . $deleted ) );
			exit;
		}
		
		// Delete all logs
		if ( isset( $_POST['aps_delete_all_logs'] ) ) {
			check_admin_referer( 'aps_delete_all_logs' );
			$this->delete_all_logs();
			wp_redirect( admin_url( 'admin.php?page=aps-error-log&deleted_all=1' ) );
			exit;
		}
		
		// Export CSV
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'export' ) {
			check_admin_referer( 'aps_export_logs' );
			$this->export_csv_download();
		}
	}
	
	/**
	 * Render admin page
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Show notices
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Log removido com sucesso!', 'api-sportmonks' ) . '</p></div>';
		}
		
		if ( isset( $_GET['cleared'] ) ) {
			$count = absint( $_GET['cleared'] );
			echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( '%d logs antigos removidos!', 'api-sportmonks' ), $count ) . '</p></div>';
		}
		
		if ( isset( $_GET['deleted_all'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Todos os logs foram removidos!', 'api-sportmonks' ) . '</p></div>';
		}
		
		// Get filter values
		$error_type = sanitize_text_field( $_GET['error_type'] ?? '' );
		$date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
		$date_to = sanitize_text_field( $_GET['date_to'] ?? '' );
		$page = absint( $_GET['paged'] ?? 1 );
		$per_page = 20;
		
		// Get logs
		$args = array(
			'error_type' => $error_type,
			'date_from'  => $date_from,
			'date_to'    => $date_to,
			'per_page'   => $per_page,
			'page'       => $page,
		);
		
		$logs = $this->get_logs( $args );
		$total = $this->get_logs_count( $args );
		$total_pages = ceil( $total / $per_page );
		
		// Get unique error types
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$error_types = $wpdb->get_col( "SELECT DISTINCT error_type FROM $table_name ORDER BY error_type" );
		
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="aps-error-log-filters">
				<form method="get" action="">
					<input type="hidden" name="page" value="aps-error-log" />
					
					<select name="error_type">
						<option value=""><?php _e( 'Todos os Tipos', 'api-sportmonks' ); ?></option>
						<?php foreach ( $error_types as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $error_type, $type ); ?>>
								<?php echo esc_html( $type ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					
					<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php _e( 'Data Inicial', 'api-sportmonks' ); ?>" />
					<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php _e( 'Data Final', 'api-sportmonks' ); ?>" />
					
					<?php submit_button( __( 'Filtrar', 'api-sportmonks' ), 'secondary', '', false ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aps-error-log' ) ); ?>" class="button">
						<?php _e( 'Limpar Filtros', 'api-sportmonks' ); ?>
					</a>
				</form>
			</div>
			
			<div class="aps-error-log-actions">
				<form method="post" action="" style="display: inline;">
					<?php wp_nonce_field( 'aps_clear_old_logs' ); ?>
					<input type="number" name="days" value="30" min="1" style="width: 60px;" />
					<?php submit_button( __( 'Limpar Logs Antigos', 'api-sportmonks' ), 'secondary', 'aps_clear_old_logs', false ); ?>
				</form>
				
				<form method="post" action="" style="display: inline; margin-left: 10px;">
					<?php wp_nonce_field( 'aps_delete_all_logs' ); ?>
					<?php submit_button( __( 'Limpar Todos os Logs', 'api-sportmonks' ), 'delete', 'aps_delete_all_logs', false ); ?>
				</form>
				
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=aps-error-log&action=export' ), 'aps_export_logs' ) ); ?>" class="button">
					<?php _e( 'Exportar CSV', 'api-sportmonks' ); ?>
				</a>
			</div>
			
			<p><?php printf( __( 'Total: %d logs', 'api-sportmonks' ), $total ); ?></p>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 80px;"><?php _e( 'ID', 'api-sportmonks' ); ?></th>
						<th style="width: 150px;"><?php _e( 'Data/Hora', 'api-sportmonks' ); ?></th>
						<th style="width: 120px;"><?php _e( 'Tipo', 'api-sportmonks' ); ?></th>
						<th><?php _e( 'Mensagem', 'api-sportmonks' ); ?></th>
						<th style="width: 100px;"><?php _e( 'Código', 'api-sportmonks' ); ?></th>
						<th style="width: 100px;"><?php _e( 'Ações', 'api-sportmonks' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="6"><?php _e( 'Nenhum log encontrado.', 'api-sportmonks' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log['id'] ); ?></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['timestamp'] ) ) ); ?></td>
								<td><?php echo esc_html( $log['error_type'] ); ?></td>
								<td>
									<strong><?php echo esc_html( wp_trim_words( $log['error_message'], 20 ) ); ?></strong>
									<?php if ( ! empty( $log['context'] ) ) : ?>
										<br><small><?php _e( 'Contexto:', 'api-sportmonks' ); ?> <?php echo esc_html( wp_json_encode( $log['context'] ) ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $log['error_code'] ?: '-' ); ?></td>
								<td>
									<a href="#" class="aps-view-details" data-log-id="<?php echo esc_attr( $log['id'] ); ?>">
										<?php _e( 'Ver Detalhes', 'api-sportmonks' ); ?>
									</a> |
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=aps-error-log&action=delete&log_id=' . $log['id'] ), 'aps_delete_log_' . $log['id'] ) ); ?>" 
									   onclick="return confirm('<?php _e( 'Tem certeza?', 'api-sportmonks' ); ?>');">
										<?php _e( 'Remover', 'api-sportmonks' ); ?>
									</a>
								</td>
							</tr>
							<tr class="aps-log-details" id="aps-log-details-<?php echo esc_attr( $log['id'] ); ?>" style="display: none;">
								<td colspan="6">
									<div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
										<p><strong><?php _e( 'Mensagem Completa:', 'api-sportmonks' ); ?></strong><br>
										<?php echo esc_html( $log['error_message'] ); ?></p>
										
										<?php if ( ! empty( $log['context'] ) ) : ?>
											<p><strong><?php _e( 'Contexto:', 'api-sportmonks' ); ?></strong><br>
											<pre><?php echo esc_html( wp_json_encode( $log['context'], JSON_PRETTY_PRINT ) ); ?></pre></p>
										<?php endif; ?>
										
										<?php if ( ! empty( $log['stack_trace'] ) ) : ?>
											<p><strong><?php _e( 'Stack Trace:', 'api-sportmonks' ); ?></strong><br>
											<pre><?php echo esc_html( $log['stack_trace'] ); ?></pre></p>
										<?php endif; ?>
										
										<?php if ( ! empty( $log['request_details'] ) ) : ?>
											<p><strong><?php _e( 'Detalhes da Requisição:', 'api-sportmonks' ); ?></strong><br>
											<pre><?php echo esc_html( wp_json_encode( $log['request_details'], JSON_PRETTY_PRINT ) ); ?></pre></p>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => $total_pages,
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('.aps-view-details').on('click', function(e) {
				e.preventDefault();
				var logId = $(this).data('log-id');
				$('#aps-log-details-' + logId).toggle();
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Delete log
	 *
	 * @param int $log_id Log ID
	 * @return bool
	 */
	private function delete_log( $log_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		return $wpdb->delete( $table_name, array( 'id' => $log_id ), array( '%d' ) ) !== false;
	}
	
	/**
	 * Export CSV and download
	 */
	private function export_csv_download() {
		$error_type = sanitize_text_field( $_GET['error_type'] ?? '' );
		$date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
		$date_to = sanitize_text_field( $_GET['date_to'] ?? '' );
		
		$args = array(
			'error_type' => $error_type,
			'date_from'  => $date_from,
			'date_to'    => $date_to,
			'per_page'   => 1000,
		);
		
		$csv_content = $this->export_csv( $args );
		
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=aps-error-logs-' . date( 'Y-m-d' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		echo "\xEF\xBB\xBF"; // UTF-8 BOM
		echo $csv_content;
		exit;
	}
	
	/**
	 * Create error log table
	 */
	public static function create_table() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			error_type varchar(50) NOT NULL,
			error_message text NOT NULL,
			error_code varchar(50) DEFAULT NULL,
			context text DEFAULT NULL,
			stack_trace longtext DEFAULT NULL,
			request_details longtext DEFAULT NULL,
			PRIMARY KEY (id),
			KEY error_type (error_type),
			KEY timestamp (timestamp)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	/**
	 * Log an error
	 *
	 * @param string $error_type Error type
	 * @param string $error_message Error message
	 * @param string $error_code Error code
	 * @param array  $context Context data
	 * @param string $stack_trace Stack trace
	 * @param array  $request_details Request details
	 */
	public function log( $error_type, $error_message, $error_code = '', $context = array(), $stack_trace = '', $request_details = array() ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		
		$wpdb->insert(
			$table_name,
			array(
				'error_type'      => sanitize_text_field( $error_type ),
				'error_message'   => sanitize_textarea_field( $error_message ),
				'error_code'      => sanitize_text_field( $error_code ),
				'context'         => wp_json_encode( $context ),
				'stack_trace'     => sanitize_textarea_field( $stack_trace ),
				'request_details' => wp_json_encode( $request_details ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
	
	/**
	 * Get error logs
	 *
	 * @param array $args Query arguments
	 * @return array
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		
		$defaults = array(
			'error_type' => '',
			'date_from'  => '',
			'date_to'    => '',
			'per_page'   => 20,
			'page'       => 1,
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$where = array( '1=1' );
		$where_values = array();
		
		if ( ! empty( $args['error_type'] ) ) {
			$where[] = 'error_type = %s';
			$where_values[] = $args['error_type'];
		}
		
		if ( ! empty( $args['date_from'] ) ) {
			$where[] = 'timestamp >= %s';
			$where_values[] = $args['date_from'];
		}
		
		if ( ! empty( $args['date_to'] ) ) {
			$where[] = 'timestamp <= %s';
			$where_values[] = $args['date_to'];
		}
		
		$where_clause = implode( ' AND ', $where );
		
		$offset = ( $args['page'] - 1 ) * $args['per_page'];
		
		$query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY timestamp DESC LIMIT %d OFFSET %d";
		
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, array_merge( $where_values, array( $args['per_page'], $offset ) ) );
		} else {
			$query = $wpdb->prepare( $query, $args['per_page'], $offset );
		}
		
		$results = $wpdb->get_results( $query, ARRAY_A );
		
		// Decode JSON fields
		foreach ( $results as &$result ) {
			$result['context'] = json_decode( $result['context'], true );
			$result['request_details'] = json_decode( $result['request_details'], true );
		}
		
		return $results;
	}
	
	/**
	 * Get total count of logs
	 *
	 * @param array $args Query arguments
	 * @return int
	 */
	public function get_logs_count( $args = array() ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		
		$where = array( '1=1' );
		$where_values = array();
		
		if ( ! empty( $args['error_type'] ) ) {
			$where[] = 'error_type = %s';
			$where_values[] = $args['error_type'];
		}
		
		if ( ! empty( $args['date_from'] ) ) {
			$where[] = 'timestamp >= %s';
			$where_values[] = $args['date_from'];
		}
		
		if ( ! empty( $args['date_to'] ) ) {
			$where[] = 'timestamp <= %s';
			$where_values[] = $args['date_to'];
		}
		
		$where_clause = implode( ' AND ', $where );
		
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE $where_clause", $where_values );
		} else {
			$query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
		}
		
		return (int) $wpdb->get_var( $query );
	}
	
	/**
	 * Clear old logs
	 *
	 * @param int $days Number of days to keep
	 * @return int Number of deleted rows
	 */
	public function clear_old_logs( $days = 30 ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		
		return $wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE timestamp < %s", $date ) );
	}
	
	/**
	 * Delete all logs
	 *
	 * @return int Number of deleted rows
	 */
	public function delete_all_logs() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		
		return $wpdb->query( "DELETE FROM $table_name" );
	}
	
	/**
	 * Export logs to CSV
	 *
	 * @param array $args Query arguments
	 * @return string CSV content
	 */
	public function export_csv( $args = array() ) {
		$logs = $this->get_logs( array_merge( $args, array( 'per_page' => 1000 ) ) );
		
		$csv = array();
		$csv[] = array( 'ID', 'Timestamp', 'Error Type', 'Error Message', 'Error Code', 'Context', 'Request Details' );
		
		foreach ( $logs as $log ) {
			$csv[] = array(
				$log['id'],
				$log['timestamp'],
				$log['error_type'],
				$log['error_message'],
				$log['error_code'],
				wp_json_encode( $log['context'] ),
				wp_json_encode( $log['request_details'] ),
			);
		}
		
		$output = fopen( 'php://temp', 'r+' );
		foreach ( $csv as $row ) {
			fputcsv( $output, $row );
		}
		rewind( $output );
		$csv_content = stream_get_contents( $output );
		fclose( $output );
		
		return $csv_content;
	}
}

