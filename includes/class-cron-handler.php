<?php
/**
 * Cron Handler Class
 *
 * Manages WP-Cron scheduling for synchronization
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_Cron_Handler {
	
	/**
	 * Instance
	 *
	 * @var APS_Cron_Handler
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 *
	 * @return APS_Cron_Handler
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
		add_action( 'init', array( $this, 'schedule_event' ) );
		add_filter( 'cron_schedules', array( $this, 'add_custom_schedules' ) );
	}
	
	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing schedules
	 * @return array
	 */
	public function add_custom_schedules( $schedules ) {
		$schedules['15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'A cada 15 minutos', 'api-sportmonks' ),
		);
		
		$schedules['30min'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'A cada 30 minutos', 'api-sportmonks' ),
		);
		
		$schedules['2hours'] = array(
			'interval' => 2 * HOUR_IN_SECONDS,
			'display'  => __( 'A cada 2 horas', 'api-sportmonks' ),
		);
		
		$schedules['6hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'A cada 6 horas', 'api-sportmonks' ),
		);
		
		$schedules['12hours'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'A cada 12 horas', 'api-sportmonks' ),
		);
		
		return $schedules;
	}
	
	/**
	 * Schedule event
	 */
	public function schedule_event() {
		$frequency = get_option( 'aps_smonks_sync_frequency', 'hourly' );
		
		if ( ! wp_next_scheduled( 'aps_sportmonks_sync_event' ) ) {
			wp_schedule_event( time(), $frequency, 'aps_sportmonks_sync_event' );
		}
	}
	
	/**
	 * Update schedule
	 */
	public function update_schedule() {
		// Clear existing schedule
		$timestamp = wp_next_scheduled( 'aps_sportmonks_sync_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'aps_sportmonks_sync_event' );
		}
		
		// Schedule new event
		$frequency = get_option( 'aps_smonks_sync_frequency', 'hourly' );
		wp_schedule_event( time(), $frequency, 'aps_sportmonks_sync_event' );
	}
}

