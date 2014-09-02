<?php

/**
 * Client Model
 * 
 *
 * @package Sprout_Invoices
 * @subpackage Client
 */
class SI_Client extends SI_Post_Type {
	const USER_ROLE = 'sa_client';
	const POST_TYPE = 'sa_client';
	const REWRITE_SLUG = 'sprout-client';
	private static $instances = array();

	private static $meta_keys = array(
		'address' => '_address',
		'currency' => '_currency',
		'associated_users' => '_associated_users',
		'website' => '_website',
	); // A list of meta keys this class cares about. Try to keep them in alphabetical order.


	public static function init() {
		// register Client post type
		$post_type_args = array(
			'public' => FALSE,
			'has_archive' => FALSE,
			'show_ui' => TRUE,
			'show_in_menu' => 'edit.php?post_type='.SI_Invoice::POST_TYPE,
			'rewrite' => array(
				'slug' => self::REWRITE_SLUG,
				'with_front' => FALSE,
			),
			'supports' => array( '' )
		);
		self::register_post_type( self::POST_TYPE, 'Client', 'Clients', $post_type_args );

		// Add the role.
		add_action( 'si_plugin_activation_hook',  array( __CLASS__, 'client_role' ), 10, 0 );
	}


	public static function client_role() {
		add_role( self::USER_ROLE, self::__('Client'), array( 'read' => true, 'level_0' => true ) );
	}

	public function estimate_submenu() {
		add_submenu_page( 'edit.php?post_type='.SI_Estimate::POST_TYPE, 'Clients', 'Clients', 'edit_posts', 'edit.php?post_type='.self::POST_TYPE );
	}

	protected function __construct( $id ) {
		parent::__construct( $id );
	}

	/**
	 *
	 *
	 * @static
	 * @param int     $id
	 * @return Sprout_Invoices_Client
	 */
	public static function get_instance( $id = 0 ) {
		if ( !$id )
			return NULL;
		
		if ( !isset( self::$instances[$id] ) || !self::$instances[$id] instanceof self )
			self::$instances[$id] = new self( $id );

		if ( !isset( self::$instances[$id]->post->post_type ) )
			return NULL;
		
		if ( self::$instances[$id]->post->post_type != self::POST_TYPE )
			return NULL;
		
		return self::$instances[$id];
	}

	/**
	 * Create a client
	 * @param  array $args 
	 * @return int       
	 */
	public static function new_client( $args ) {
		$defaults = array(
			'company_name' => sprintf( self::__('New Client: %s'), date( get_option( 'date_format' ).' @ '.get_option( 'time_format' ), current_time( 'timestamp' ) ) ),
			'website' => '',
			'address' => array(),
			'currency' => 'USD',
			'user_id' => 0
		);
		$parsed_args = wp_parse_args( $args, $defaults );
		extract( $parsed_args );

		$id = wp_insert_post( array(
			'post_status' => 'publish',
			'post_type' => self::POST_TYPE,
			'post_title' => $company_name
		) );
		if ( is_wp_error( $id ) ) {
			return 0;
		}

		$client = self::get_instance( $id );
		$client->set_address( $address );
		$client->set_currency( $currency );
		$client->set_website( $website );

		if ( $user_id ) {	
			$client->add_associated_user( $user_id );	
		}

		do_action( 'sa_new_client', $client, $args );
		return $id;
	}

	///////////
	// Meta //
	///////////


	public function get_address() {
		return $this->get_post_meta( self::$meta_keys['address'] );
	}

	public function set_address( $address ) {
		return $this->save_post_meta( array( self::$meta_keys['address'] => $address ) );
	}

	/**
	 * Get the associated users with this client
	 * @return array 
	 */
	public function get_associated_users() {
		$users = $this->get_post_meta( self::$meta_keys['associated_users'], FALSE );
		if ( !is_array( $users ) ) {
			$users = array();
		}
		return array_filter($users);
	}

	/**
	 * Save the associated users with this client
	 * @param array $users
	 */
	public function set_associated_users( $users = array() ) {
		$this->save_post_meta( array(
				self::$meta_keys['associated_users'] => $users,
			) );
		return $users;
	}

	/**
	 * Clear out the associated users
	 * @param array $users
	 */
	public function clear_associated_users() {
		$this->delete_post_meta( array(
				self::$meta_keys['associated_users'] => ''
			) );
	}

	/**
	 * Add single user to associated array
	 * @param integer $user_id 
	 */
	public function add_associated_user( $user_id = 0 ) {
		if ( $user_id && !$this->is_user_associated( $user_id ) ) {
			$this->add_post_meta( array(
					self::$meta_keys['associated_users'] => $user_id
				) );
		}
	}

	public function is_user_associated( $user_id ) {
		$associated_users = $this->get_associated_users();
		if ( empty( $associated_users ) ) return;
		return in_array( $user_id, $associated_users );
	}

	public function get_currency() {
		return $this->get_post_meta( self::$meta_keys['currency'] );
	}

	public function set_currency( $currency ) {
		return $this->save_post_meta( array( self::$meta_keys['currency'] => $currency ) );
	}

	public function get_website() {
		return $this->get_post_meta( self::$meta_keys['website'] );
	}

	public function set_website( $website ) {
		return $this->save_post_meta( array( self::$meta_keys['website'] => $website ) );
	}

	//////////////
	// Utility //
	//////////////


	public function get_invoices() {
		$invoices = self::find_by_meta( SI_Invoice::POST_TYPE, array( '_client_id' => $this->get_id() ) );
		return $invoices;
	}

	public function get_estimates() {
		$estimates = self::find_by_meta( SI_Estimate::POST_TYPE, array( '_client_id' => $this->get_id() ) );
		return $estimates;
	}

	/**
	 * Get the clients that are associated with the user
	 * @param  integer $user_id 
	 * @return array           
	 */
	public static function get_clients_by_user( $user_id = 0 ) {
		$clients = self::find_by_meta( self::POST_TYPE, array( self::$meta_keys['associated_users'] => $user_id ) );
		return $clients;
	}

	public static function get_payments() {
		$payments = array();
		$invoices = $this->get_invoices();
		foreach ( $invoices as $invoice_id ) {
			$invoice = SI_Invoice::get_instance( $invoice_id );
			$payments = array_merge( $payments, $invoice->get_payments() );
		}
		return $payments;
	}

	/**
	 * Get all payments from this client.
	 * @param  integer $client_id 
	 * @return              
	 */
	public static function get_payments_by_client( $client_id = 0 ) {
		$client = self::get_instance( $client_id );
		$payments = $client->get_payments();
		return $payments;
	}

	public function get_history( $type = '' ) {
		// FUTURE v1.1 query for estimates and invoices too
		return SI_Record::get_records_by_association( $this->ID );
	}

}