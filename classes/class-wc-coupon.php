<?php
/**
 * WooCommerce coupons
 *
 * The WooCommerce coupons class gets coupon data from storage and checks coupon validity
 *
 * @class 		WC_Coupon
 * @package		WooCommerce
 * @category	Class
 * @author		WooThemes
 */
class WC_Coupon {

	/** @public string Coupon code. */
	public $code;

	/** @public int Coupon ID. */
	public $id;

	/** @public string Type of discount. */
	public $type;

	/** @public string Type of discount (alias). */
	public $discount_type;

	/** @public string Coupon amount. */
	public $amount;

	/** @public string "Yes" if for individual use. */
	public $individual_use;

	/** @public array Array of product IDs. */
	public $product_ids;

	/** @public int Coupon usage limit. */
	public $usage_limit;

	/** @public int Coupon usage count. */
	public $usage_count;

	/** @public string Expirey date. */
	public $expiry_date;

	/** @public string "yes" if applied before tax. */
	public $apply_before_tax;

	/** @public string "yes" if coupon grants free shipping. */
	public $free_shipping;

	/** @public array Array of category ids. */
	public $product_categories;

	/** @public array Array of category ids. */
	public $exclude_product_categories;

	/** @public string Minimum cart amount. */
	public $minimum_amount;

	/** @public string Coupon owner's email. */
	public $customer_email;

	/** @public array Post meta. */
	public $coupon_custom_fields;

	/** @public string How much the coupon is worth. */
	public $coupon_amount;

	/** @public string Error message. */
	public $error_message;

	/**
	 * Coupon constructor. Loads coupon data.
	 *
	 * @access public
	 * @param mixed $code code of the coupon to load
	 * @return void
	 */
	public function __construct( $code ) {
		global $wpdb;

		$this->code 	= apply_filters( 'woocommerce_coupon_code', $code );

		// Coupon data lets developers create coupons through code
		$coupon_data 	= apply_filters( 'woocommerce_get_shop_coupon_data', false, $code );

        if ( $coupon_data ) {

            $this->id 							= absint( $coupon_data['id'] );
            $this->type 						= esc_html( $coupon_data['type'] );
            $this->amount 						= esc_html( $coupon_data['amount'] );
            $this->individual_use 				= esc_html( $coupon_data['individual_use'] );
            $this->product_ids 					= is_array( $coupon_data['product_ids'] ) ? $coupon_data['product_ids'] : array();
            $this->exclude_product_ids 			= is_array( $coupon_data['exclude_product_ids'] ) ? $coupon_data['exclude_product_ids'] : array();
            $this->usage_limit 					= absint( $coupon_data['usage_limit'] );
            $this->usage_count 					= absint( $coupon_data['usage_count'] );
            $this->expiry_date 					= esc_html( $coupon_data['expiry_date'] );
            $this->apply_before_tax 			= esc_html( $coupon_data['apply_before_tax'] );
            $this->free_shipping 				= esc_html( $coupon_data['free_shipping'] );
            $this->product_categories 			= is_array( $coupon_data['product_categories'] ) ? $coupon_data['product_categories'] : array();
            $this->exclude_product_categories 	= is_array( $coupon_data['exclude_product_categories'] ) ? $coupon_data['exclude_product_categories'] : array();
            $this->minimum_amount 				= esc_html( $coupon_data['minimum_amount'] );
            $this->customer_email 				= esc_html( $coupon_data['customer_email'] );


        } else {

            $coupon_id 	= $wpdb->get_var( $wpdb->prepare( apply_filters( 'woocommerce_coupon_code_query', "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'shop_coupon'" ), $this->code ) );

            if ( ! $coupon_id )
            	return;

			$coupon             = get_post( $coupon_id );
			$coupon->post_title = apply_filters( 'woocommerce_coupon_code', $coupon->post_title );

            if ( empty( $coupon ) || $coupon->post_status !== 'publish' || $this->code !== $coupon->post_title )
            	return;

            $this->id                   = $coupon->ID;
            $this->coupon_custom_fields = get_post_meta( $this->id );

            $load_data = array(
            	'discount_type'					=> 'fixed_cart',
            	'coupon_amount'					=> 0,
            	'individual_use'				=> 'no',
            	'product_ids'					=> '',
            	'exclude_product_ids'			=> '',
            	'usage_limit'					=> '',
            	'usage_count'					=> '',
            	'expiry_date'					=> '',
            	'apply_before_tax'				=> 'yes',
            	'free_shipping'					=> 'no',
            	'product_categories'			=> array(),
            	'exclude_product_categories'	=> array(),
            	'minimum_amount'				=> '',
            	'customer_email'				=> array()
            );

            foreach ( $load_data as $key => $default )
            	$this->$key = isset( $this->coupon_custom_fields[ $key ][0] ) && $this->coupon_custom_fields[ $key ][0] !== '' ? $this->coupon_custom_fields[ $key ][0] : $default;

            // Alias
            $this->type 						= $this->discount_type;
            $this->amount 						= $this->coupon_amount;

            // Formatting
            $this->product_ids 					= array_filter( array_map( 'trim', explode( ',', $this->product_ids ) ) );
            $this->exclude_product_ids 			= array_filter( array_map( 'trim', explode( ',', $this->exclude_product_ids ) ) );
 			$this->expiry_date 					= $this->expiry_date ? strtotime( $this->expiry_date ) : '';
            $this->product_categories 			= array_filter( array_map( 'trim', (array) maybe_unserialize( $this->product_categories ) ) );
       		$this->exclude_product_categories 	= array_filter( array_map( 'trim', (array) maybe_unserialize( $this->exclude_product_categories ) ) );
			$this->customer_email 				= array_filter( array_map( 'trim', array_map( 'strtolower', (array) maybe_unserialize( $this->customer_email ) ) ) );
        }
	}


	/**
	 * Check if coupon needs applying before tax.
	 *
	 * @access public
	 * @return bool
	 */
	public function apply_before_tax() {
		return $this->apply_before_tax == 'yes' ? true : false;
	}


	/**
	 * Check if a coupon enables free shipping.
	 *
	 * @access public
	 * @return void
	 */
	public function enable_free_shipping() {
		return $this->free_shipping == 'yes' ? true : false;
	}


	/**
	 * Increase usage count fo current coupon.
	 *
	 * @access public
	 * @return void
	 */
	public function inc_usage_count() {
		$this->usage_count++;
		update_post_meta( $this->id, 'usage_count', $this->usage_count );
	}


	/**
	 * Decrease usage count fo current coupon.
	 *
	 * @access public
	 * @return void
	 */
	public function dcr_usage_count() {
		$this->usage_count--;
		update_post_meta( $this->id, 'usage_count', $this->usage_count );
	}

	/**
	 * Returns the error_message string
	 *
	 * @access public
	 * @return string
	 */
	public function get_error_message() {
		return $this->error_message;
	}

	/**
	 * is_valid function.
	 *
	 * Check if a coupon is valid. Return a reason code if invaid. Reason codes:
	 *
	 * @access public
	 * @return bool|WP_Error validity or a WP_Error if not valid
	 */
	public function is_valid() {
		global $woocommerce;

		if ( $this->id ) {

			$valid = true;
			$error = false;

			// Usage Limit
			if ( $this->usage_limit > 0 ) {
				if ( $this->usage_count >= $this->usage_limit ) {
					$valid = false;
					$error = __( 'Coupon usage limit has been reached.', 'woocommerce' );
				}
			}

			// Expired
			if ( $this->expiry_date ) {
				if ( current_time( 'timestamp' ) > $this->expiry_date ) {
					$valid = false;
					$error = __( 'This coupon has expired.', 'woocommerce' );
				}
			}

			// Minimum spend
			if ( $this->minimum_amount > 0 ) {
				if ( $this->minimum_amount > $woocommerce->cart->subtotal ) {
					$valid = false;
					$error = sprintf( __( 'The minimum spend for this coupon is %s.', 'woocommerce' ), woocommerce_price( $this->minimum_amount ) );
				}
			}

			// Product ids - If a product included is found in the cart then its valid
			if ( sizeof( $this->product_ids ) > 0 ) {
				$valid_for_cart = false;
				if ( sizeof( $woocommerce->cart->get_cart() ) > 0 ) {
					foreach( $woocommerce->cart->get_cart() as $cart_item_key => $cart_item ) {

						if ( in_array( $cart_item['product_id'], $this->product_ids ) || in_array( $cart_item['variation_id'], $this->product_ids ) || in_array( $cart_item['data']->get_parent(), $this->product_ids ) )
							$valid_for_cart = true;
					}
				}
				if ( ! $valid_for_cart ) {
					$valid = false;
					$error = __( 'Sorry, this coupon is not applicable to your cart contents.', 'woocommerce' );
				}
			}

			// Category ids - If a product included is found in the cart then its valid
			if ( sizeof( $this->product_categories ) > 0 ) {
				$valid_for_cart = false;
				if ( sizeof( $woocommerce->cart->get_cart() ) > 0 ) {
					foreach( $woocommerce->cart->get_cart() as $cart_item_key => $cart_item ) {

						$product_cats = wp_get_post_terms($cart_item['product_id'], 'product_cat', array("fields" => "ids"));

						if ( sizeof( array_intersect( $product_cats, $this->product_categories ) ) > 0 )
							$valid_for_cart = true;
					}
				}
				if ( ! $valid_for_cart ) {
					$valid = false;
					$error = __( 'Sorry, this coupon is not applicable to your cart contents.', 'woocommerce' );
				}
			}

			// Cart discounts cannot be added if non-eligble product is found in cart
			if ( $this->type != 'fixed_product' && $this->type != 'percent_product' ) {

				// Exclude Products
				if ( sizeof( $this->exclude_product_ids ) > 0 ) {
					$valid_for_cart = true;
					if ( sizeof( $woocommerce->cart->get_cart() ) > 0 ) {
						foreach( $woocommerce->cart->get_cart() as $cart_item_key => $cart_item ) {
							if ( in_array( $cart_item['product_id'], $this->exclude_product_ids ) || in_array( $cart_item['variation_id'], $this->exclude_product_ids ) || in_array( $cart_item['data']->get_parent(), $this->exclude_product_ids ) ) {
								$valid_for_cart = false;
							}
						}
					}
					if ( ! $valid_for_cart ) {
						$valid = false;
						$error = __( 'Sorry, this coupon is not applicable to your cart contents.', 'woocommerce' );
					}
				}

				// Exclude Categories
				if ( sizeof( $this->exclude_product_categories ) > 0 ) {
					$valid_for_cart = true;
					if ( sizeof( $woocommerce->cart->get_cart() ) > 0 ) {
						foreach( $woocommerce->cart->get_cart() as $cart_item_key => $cart_item ) {

							$product_cats = wp_get_post_terms( $cart_item['product_id'], 'product_cat', array( "fields" => "ids" ) );

							if ( sizeof( array_intersect( $product_cats, $this->exclude_product_categories ) ) > 0 )
								$valid_for_cart = false;
						}
					}
					if ( ! $valid_for_cart ) {
						$valid = false;
						$error = __( 'Sorry, this coupon is not applicable to your cart contents.', 'woocommerce' );
					}
				}
			}

			$valid = apply_filters( 'woocommerce_coupon_is_valid', $valid, $this );

			if ( $valid )
				return true;

		} else {
			$error = __( 'Invalid coupon', 'woocommerce' );
		}

		$this->error_message = apply_filters( 'woocommerce_coupon_error', $error, $this );
		return false;
	}
}