<?php

/**
 * class for communicate with db
 * @class sokan db
 * @since 1.2.0
 */
class Skng_Sokan_db {

	/**
	 * @var Wpdb
	 * @since 1.1.0
	 */
	private $db;

	/**
	 * array of filter for extract data from woocommerce tables
	 * @var array
	 * @since 1.2.0
	 */
	private $filters;

	/**
	 * array of customer and region key name for search in woocommerce tables
	 * @var array
	 * @since 1.2.0
	 */
	private $order_attr = [
		'user_id'    => '_customer_user',
		'first_name' => '_billing_first_name',
		'last_name'  => '_billing_last_name',
		'city'       => '_billing_city',
		'state'      => '_billing_state',
		'country'    => '_billing_country',
		'phone'      => '_billing_phone',
		'email'      => '_billing_email',
	];

	/**
	 * array of region Code and name for replacing
	 * @var array
	 * @since 1.2.0
	 */
	private $states = array(
		'KHZ' => 'خوزستان',
		'THR' => 'تهران',
		'ILM' => 'ایلام',
		'BHR' => 'بوشهر',
		'ADL' => 'اردبیل',
		'ESF' => 'اصفهان',
		'YZD' => 'یزد',
		'KRH' => 'کرمانشاه',
		'KRN' => 'کرمان',
		'HDN' => 'همدان',
		'GZN' => 'قزوین',
		'ZJN' => 'زنجان',
		'LRS' => 'لرستان',
		'ABZ' => 'البرز',
		'EAZ' => 'آذربایجان شرقی',
		'WAZ' => 'آذربایجان غربی',
		'CHB' => 'چهارمحال و بختیاری',
		'SKH' => 'خراسان جنوبی',
		'RKH' => 'خراسان رضوی',
		'NKH' => 'خراسان شمالی',
		'SMN' => 'سمنان',
		'FRS' => 'فارس',
		'QHM' => 'قم',
		'KRD' => 'کردستان',
		'KBD' => 'کهگیلویه و بویراحمد',
		'GLS' => 'گلستان',
		'GIL' => 'گیلان',
		'MZN' => 'مازندران',
		'MKZ' => 'مرکزی',
		'HRZ' => 'هرمزگان',
		'SBN' => 'سیستان و بلوچستان',
	);

	public function __construct( $wpdp ) {
		$this->db         = $wpdp;
		$this->filters    = [
			'log_table'         => "{$wpdp->prefix}" . SKNG_PLUGIN_NAME . "_logs",
			'update_date'       => get_option( SKNG_PLUGIN_NAME . "_sync_date" ),
			'complete_status'   => get_option( SKNG_PLUGIN_NAME . '_sale_status' ),
			'refunded_status'   => get_option( SKNG_PLUGIN_NAME . '_refunded_status' ),
			'api_limitation'    => get_option( SKNG_PLUGIN_NAME . '_api_limitation' ),
			'customer_identity' => get_option( SKNG_PLUGIN_NAME . '_customer_identity' )
		];
		$this->order_attr = apply_filters( "skng_set_orders_attr", $this->order_attr );
	}

	/**
	 * return all available sokan api urls
	 * @return array
	 * @since 1.2.0
	 */
	public function webServiceUrls(): array {
		return [
			'ارسال به نسخه آزمایشی' => "https://api-lab.sokan.tech/",
			'ارسال به نسخه دمو'     => "https://api-demo.sokan.tech/",
			'ارسال به نسخه اپ'      => "https://api-app.sokan.tech/",
		];
	}

	/**
	 * return all available customer identity value
	 * @return array
	 * @since 1.4.0
	 */
	public function customerIdentities(): array {
		return [
			'شماره تلفن مشتریان'         => "phone",
			'کد کاربری مشتریان'          => "id",
			'نام و نام خانوادگی مشتریان' => "name",
		];
	}

	/**
	 * return all available sync mode
	 * @return array
	 * @since 1.4.0
	 */
	public function syncModes(): array {
		return [
			'در پس زمینه (30 ثانیه بعد از تغییر وضعیت سفارش)' => "async",
			'همزمان با تغییر وضعیت سفارش'                     => "sync",
		];
	}

	/**
	 * reset last sync date and clear log table
	 * @return void
	 * @since 1.2.0
	 */
	public function resetSyncDate() {
		$logTable = $this->filters['log_table'];
		update_option( SKNG_PLUGIN_NAME . '_sync_date', '' );
		$this->db->query( "TRUNCATE TABLE $logTable" );
	}

	/**
	 * return all products and their brands and categories list
	 *
	 * @param $ids
	 *
	 * @return array
	 * @since 1.3.0
	 */
	public function getProducts( $ids ): array {
		$data = $this->db->get_results(
			"
           SELECT ID as id , post_title  , 
           {$this->db->prefix}terms.term_id , 
           {$this->db->prefix}terms.name , 
           {$this->db->prefix}term_taxonomy.taxonomy,
           {$this->db->prefix}term_taxonomy.parent
           FROM `{$this->db->prefix}posts` 
           JOIN `{$this->db->prefix}term_relationships` 
           ON `{$this->db->prefix}posts`.ID = `{$this->db->prefix}term_relationships`.object_id
           JOIN `{$this->db->prefix}term_taxonomy`
           ON `{$this->db->prefix}term_relationships`.term_taxonomy_id = `{$this->db->prefix}term_taxonomy`.term_taxonomy_id
           LEFT JOIN {$this->db->prefix}terms ON {$this->db->prefix}terms.term_id = {$this->db->prefix}term_taxonomy.term_id
           WHERE `{$this->db->prefix}posts`.post_type IN ( 'product','product_variation' )
           AND ( `{$this->db->prefix}term_taxonomy`.taxonomy LIKE '%brand%'
           OR `{$this->db->prefix}term_taxonomy`.taxonomy LIKE '%product_cat%'
           OR `{$this->db->prefix}term_taxonomy`.taxonomy LIKE '%weight%'
           OR `{$this->db->prefix}term_taxonomy`.taxonomy LIKE '%وزن%'
           OR `{$this->db->prefix}term_taxonomy`.taxonomy LIKE '%برند%'
           )
           AND ID IN (" . implode( ',', $ids ) . ")
           ORDER BY {$this->db->prefix}term_taxonomy.parent DESC
            "
			, ARRAY_A
		);

		$categories = $this->db->get_results(
			"
           SELECT * FROM `{$this->db->prefix}term_taxonomy` 
           JOIN `{$this->db->prefix}terms` 
           ON `{$this->db->prefix}term_taxonomy`.term_id = `{$this->db->prefix}terms`.term_id     
           WHERE `{$this->db->prefix}term_taxonomy`.taxonomy = 'product_cat'  
            ", ARRAY_A
		);

		$products       = [];
		$categories_ids = array_column( $categories, 'term_id' );
		$data           = apply_filters( "skng_before_validate_product_attr", $data );
		foreach ( $data as $item ) {
			$product_id                      = $item['id'];
			$products[ $product_id ]['name'] = $item['post_title'];
			if ( $item['taxonomy'] == 'product_cat' ) {
				if ( isset( $products[ $product_id ]['categories'] ) ) continue;

				$parent = $item['parent'];
				$cats   = [ $item['name'] ];
				for ( $x = 0; $x <= 8; $x ++ ) {
					$key = array_search( $parent, $categories_ids );
                    if ($key === false) break;
                    array_push( $cats, $categories[ $key ]['name'] );
                    $parent = $categories[ $key ]['parent'];
				}
				$products[ $product_id ]['categories'] = array_reverse( $cats );
			}
			if ( strpos( $item['taxonomy'], "brand" ) != false or strpos( $item['taxonomy'], "برند" ) != false ) {
				$products[ $product_id ]['brand'] = $item['name'];
				continue;
			}
			if ( strpos( $item['taxonomy'], "weight" ) != false or strpos( $item['taxonomy'], "وزن" ) != false ) {
				$products[ $product_id ]['weight'] = $item['name'];
			}
		}

		return apply_filters( "skng_get_all_product_filter", $products );
	}


	/**
	 * return all deleted product names
	 *
	 * @param $ids
	 *
	 * @return array
	 * @since 1.5.0
	 */
	public function getDeletedProducts( $ids ): array {
		$ids      = implode( "','", $ids );
		$products = $this->db->get_results(
			"SELECT * FROM  `{$this->db->prefix}woocommerce_order_items`
                   WHERE  `{$this->db->prefix}woocommerce_order_items`.order_item_id 
                   IN ('$ids')",
			ARRAY_A
		);
		$result   = [];
		foreach ( $products as $product ) {
			$result[ $product['order_item_id'] ] = $product['order_item_name'];
		}

		return $result;
	}

	/**
	 * return all order based on filters
	 * @return array invoices
	 * return list of invoices
	 * @since 1.1.0
	 */
	public function getAllOrders(): array {
		$date              = $this->filters['update_date'];
		$ref_status        = $this->filters['refunded_status'];
		$customer_identity = $this->filters['customer_identity'];

		if ( count( $ids = $this->getAvailableOrderIds() ) == 0 ) {
			return [ 'order' => [], 'date' => $date ];
		}

		$all_invoices = $this->getOrderData( $ids );
		$new_orders   = [];
		$product_ids  = [];

		foreach ( $all_invoices as $order_key => $value ) {

			$date           = $value['modified_date'] ?? $date;
			$order_date     = $value['post_date'] ?? $date;
			$first_name     = $value[ $this->order_attr['first_name'] ] ?? "مشتری";
			$last_name      = $value[ $this->order_attr['last_name'] ] ?? "نامشخص";
			$customer_name  = trim( trim( $first_name, " " ) . " " . trim( $last_name, " " ), " " );
			$customer_phone = $this->convertToEnNumbers( $value[ $this->order_attr['phone'] ] ?? "" );

			if ( preg_match( '/^(?:98|\+98|0098|0|\+980|00980)?9[0-9]{9}/', $customer_phone, $_phone, PREG_OFFSET_CAPTURE ) ) {
				$_phone         = $_phone[0][0];
				$_phone         = str_replace( "+980", '+98', $_phone );
				$_phone         = str_replace( "00980", '+98', $_phone );
				$customer_phone = preg_replace( '/^(?:98|\+98|0098|0)/', "0", $_phone );
			}

			$customer_id = $value[ $this->order_attr['user_id'] ] ?? ( empty( $customer_phone ) ? $customer_name : $customer_phone );

			if ( $customer_identity == 'phone' and ! empty( $customer_phone ) ) {
				$customer_id = trim( str_replace( "0", "", $customer_phone ), " " );
			} elseif ( $customer_identity == 'name' and $customer_name != "مشتری نامشخص" ) {
				$customer_id = $customer_name;
			} elseif ( $customer_id == 0 ) {
				$customer_id = $customer_name != "مشتری نامشخص" ? $customer_name : $order_key;
			}

			$state = $value[ $this->order_attr['state'] ] ?? "نامشخص";
			$city  = $value[ $this->order_attr['city'] ] ?? "نامشخص";

			if ( ! isset( $value['items'] ) and isset( $value['_order_total'] ) ) {
				$value['items'][ $value['_order_total'] ]['_fee_amount'] = $value['_order_total'];
			} elseif ( ! isset( $value['items'] ) ) {
				continue;
			}

			foreach ( $value['items'] as $item_key => $item ) {

				$qty          = $item['_qty'] ?? 1;
				$qty          = abs( $qty );
				$discount     = 0;
				$unit_price   = 0;
				$total        = 0;
				$product_id   = 'محصول نامشخص';
				$product_type = 'SERVICE';

				if ( isset( $item['_line_subtotal'] ) ) {

					if ( $item['_line_subtotal'] > 0 ) {
						$unit_price = $item['_line_subtotal'] / $qty;
					} else {
						$unit_price = $item['_line_total'] / $qty;
					}
					if ( $item['_line_total'] != 0 ) {
						$total = $item['_line_total'] / $qty;
					}
					$discount     = ( $unit_price - $total ) * $qty;
					$product_type = 'PRODUCT';

					if ( ! empty( $item['_product_id'] ) ) {
						$product_id                 = $item['_product_id'];
						$product_ids[ $product_id ] = $product_id;
					}
				} else if ( isset( $item['cost'] ) ) {

					$unit_price = (int) $item['cost'];
					$product_id = 'هزینه ارسال';

				} else if ( isset( $item['_fee_amount'] ) ) {
					$unit_price = (int) $item['_fee_amount'];
				}

				$weight = $item['pa_weight'] ?? 0;

                $filteredState = $this->getRegionName( $state );
                $filteredCity = trim( $city, " " );

				$order = [
					'item_id'       => (string) $item_key,
					'invoice_id'    => (string) $order_key,
					'date'          => (string) $order_date,
					'unit_price'    => abs( (int) $unit_price ),
					'type'          => "SALES",
					'quantity'      => (int) $qty,
					'discount'      => (int) $discount,
					'weight'        => (int) $weight,
					'customer_id'   => $customer_id,
					'customer_name' => $customer_name,
					'region0'       => "ایران",
					'region1'       => preg_match('~[0-9]+~', $filteredState) == true ? "نامشخص" : $filteredState,
					'region2'       => preg_match('~[0-9]+~', $filteredCity) == true ? "نامشخص" : $filteredCity,
					'sku_id'        => $product_id,
					'product_name'  => $product_id,
					'product_type'  => $product_type
				];

				if ( preg_match( '/^(?:98|\+98|0098|0)?9[0-9]{9}$/', $customer_phone ) ) {
					$order['customer_phone_number'] = $customer_phone;
				}

				array_push( $new_orders, $order );

				if ( $value['status'] == $ref_status ) {
					$refund_order             = $order;
					$refund_order['item_id']  = $refund_order['item_id'] . "_refunded";
					$refund_order['quantity'] = - $refund_order['quantity'];
					$refund_order['discount'] = - $refund_order['discount'];
					$refund_order['type']     = "RETURN";
					array_push( $new_orders, $refund_order );
				}
			}
		}

		$all_products = $this->getProducts( $product_ids );
		$order_ids    = [];
		foreach ( $new_orders as $key => $order_item ) {

			if ( isset( $all_products[ $order_item['sku_id'] ] ) ) {

				$product = $all_products[ $order_item['sku_id'] ];
                $name = trim($product['name']," ");
				$new_orders[ $key ]['product_name'] = is_numeric($name) == true ? "محصول نامشخص" : $name;

				if ( isset( $product['brand'] ) ) {
					$new_orders[ $key ]['product_brand0'] = $product['brand'];
				}
				if ( isset( $product['categories'] ) ) {
					foreach ( $product['categories'] as $cat_key => $name ) {
						$new_orders[ $key ][ 'product_category' . $cat_key ] = $name;
					}
				}
				if ( $order_item['weight'] == 0 and isset( $product['weight'] ) ) {
					$new_orders[ $key ]['weight'] = (int) $product['weight'];
				}
			} elseif ( $order_item['sku_id'] == 'محصول نامشخص' and $order_item['sku_id'] != 'هزینه ارسال' ) {
				$order_ids[ $key ] = strval( $order_item['item_id'] );
			}
		}

		if ( count( $order_ids ) > 0 ) {
			$deletedProduct = $this->getDeletedProducts( $order_ids );
			foreach ( $deletedProduct as $id => $name ) {
				$key = array_search( $id, array_column( $new_orders, 'item_id' ) );
				if ( $key >= 0 ) {
                    $name = trim($name," ");
					$new_orders[ $key ]['sku_id']            = $name;
					$new_orders[ $key ]['product_name']      = is_numeric($name) == true ? "محصول نامشخص" : $name;;
					$new_orders[ $key ]['product_category0'] = 'محصولات حذف شده';
				}
			}
		}

		$orders_for_sync = array_merge( $this->getErrorLogs(), $new_orders );

		return [ 'order' => $orders_for_sync, 'date' => $date ];
	}

	/**
	 * Each time a data is not synchronized with the sokan, it is logged
	 * @return void
	 * @since 1.1.0
	 */
	public function saveErrors( $errors ) {
		$logTable = $this->filters['log_table'];
		$this->db->query( " INSERT ignore INTO `$logTable`(`entity_id`,`error`,`payload`) VALUES " . implode( ',', $errors ) . ";" );
	}

	/**
	 * get all error logs saved on db for sync again
	 * @return array
	 * @since 1.1.0
	 */
	private function getErrorLogs(): array {
		$logTable = $this->filters['log_table'];
		$result   = [];
		$res      = $this->db->get_results( " SELECT * FROM `$logTable` ORDER BY `date`", ARRAY_A );

		if ( count( $res ) == 0 ) {
			return $result;
		}

		foreach ( $res as $log ) {
			if ( ! empty( $log['payload'] ) ) {
				array_push( $result, json_decode( $log['payload'], true ) );
			}
		}
		$this->db->query( "TRUNCATE TABLE $logTable" );

		return $result;
	}

	/**
	 * get list of order ids for sync based on date and status filters
	 * @return array
	 * @since 1.2.0
	 */
	private function getAvailableOrderIds(): array {

		$date           = $this->filters['update_date'];
		$com_status     = $this->filters['complete_status'];
		$ref_status     = $this->filters['refunded_status'];
		$api_limitation = $this->filters['api_limitation'];

		$ids = $this->db->get_results(
			"SELECT `{$this->db->prefix}posts`.ID as id FROM `{$this->db->prefix}posts`
                   WHERE `{$this->db->prefix}posts`.post_status IN ('$com_status' , '$ref_status') 
                   AND `{$this->db->prefix}posts`.post_modified > '$date'
                   GROUP BY `{$this->db->prefix}posts`.ID
                   ORDER BY `{$this->db->prefix}posts`.post_modified LIMIT  $api_limitation ",
			ARRAY_A
		);

		return apply_filters( "skng_get_all_order_ids_filter", array_column( $ids, 'id' ) );
	}

	/**
	 * get order data of given id list
	 *
	 * @param $ids
	 *
	 * @return array
	 * @since 1.2.0
	 */
	private function getOrderData( $ids ): array {

		$attr_copy = $this->order_attr;
		array_push( $attr_copy, '_order_total' );
		$attr = implode( "','", $attr_copy );

		$all = $this->db->get_results( "
         (SELECT `{$this->db->prefix}woocommerce_order_items`.order_item_id as item_id ,
         `{$this->db->prefix}woocommerce_order_items`.order_id as order_id,
         `{$this->db->prefix}woocommerce_order_itemmeta`.meta_key as meta_key ,
         `{$this->db->prefix}woocommerce_order_itemmeta`.meta_value AS meta_value,
         `{$this->db->prefix}posts`.post_status as order_status,
         `{$this->db->prefix}posts`.post_modified as modified_date,
         `{$this->db->prefix}posts`.post_date as post_date
          FROM `{$this->db->prefix}woocommerce_order_items` 
          JOIN `{$this->db->prefix}woocommerce_order_itemmeta` 
          ON `{$this->db->prefix}woocommerce_order_itemmeta`.order_item_id = `{$this->db->prefix}woocommerce_order_items`.order_item_id
          JOIN `{$this->db->prefix}posts` ON `{$this->db->prefix}posts`.ID = `{$this->db->prefix}woocommerce_order_items`.order_id
          WHERE `{$this->db->prefix}posts`.ID IN (" . implode( ',', $ids ) . ")
          AND (
          `{$this->db->prefix}woocommerce_order_itemmeta`.meta_key 
          IN ('_product_id' , '_qty','_line_subtotal' , '_line_total' , 'cost' , '_fee_amount' ,'_order_total')
          OR `{$this->db->prefix}woocommerce_order_itemmeta`.meta_key LIKE '%weight%'
          OR `{$this->db->prefix}woocommerce_order_itemmeta`.meta_key LIKE '%وزن%'
          )) 
          UNION 
          (SELECT `{$this->db->prefix}postmeta`.meta_id as item_id,
          `{$this->db->prefix}posts`.ID as order_id,
          `{$this->db->prefix}postmeta`.meta_key as meta_key,
          `{$this->db->prefix}postmeta`.meta_value as meta_value ,
          `{$this->db->prefix}posts`.post_status as post_status,
          `{$this->db->prefix}posts`.post_modified as modified_date,
          `{$this->db->prefix}posts`.post_date as post_date
          FROM `{$this->db->prefix}postmeta` 
          JOIN `{$this->db->prefix}posts` ON `{$this->db->prefix}posts`.ID = `{$this->db->prefix}postmeta`.post_id
          WHERE `{$this->db->prefix}postmeta`.meta_key IN ('$attr')
          AND `{$this->db->prefix}posts`.ID IN (" . implode( ',', $ids ) . "))
          ", ARRAY_A );

		$all_invoices = [];
		foreach ( $all as $value ) {

			if ( in_array( $value['meta_key'], $this->order_attr ) ) {
				if ( ! ctype_space( $value['meta_value'] ) and ! empty( $value['meta_value'] ) ) {
					$all_invoices[ $value['order_id'] ][ $value['meta_key'] ] = $value['meta_value'];
				}
				continue;
			}

			$all_invoices[ $value['order_id'] ]['modified_date'] = $value['modified_date'];
			$all_invoices[ $value['order_id'] ]['post_date']     = $value['post_date'];
			$all_invoices[ $value['order_id'] ]['status']        = $value['order_status'];

			if ( $value['meta_key'] == '_order_total' ) {
				$all_invoices[ $value['order_id'] ]['_order_total'] = $value['meta_value'];
				continue;
			}

			$all_invoices[ $value['order_id'] ]['items'][ $value['item_id'] ][ $value['meta_key'] ] = $value['meta_value'];

		}

		return apply_filters( "skng_get_all_order_data_filter", $all_invoices );
	}

	/**
	 * return persian name of given state key
	 *
	 * @param $state
	 *
	 * @return string
	 * @since 1.2.0
	 */
	private function getRegionName( $state ): string {
        $state = str_replace("استان", "", $state);
        $state = str_replace("شهرستان", "", $state);
        $state = str_replace("مقدس", "", $state);
        $state = str_replace(",", "", $state);
        $state = str_replace(".", "", $state);
        $state = str_replace("،", "", $state);

		if ( isset( $this->states[ $state ] ) ) {
			return trim( $this->states[ $state ], " " );
		}

		return trim( $state, " " );
	}

	private function convertToEnNumbers( $string ) {
		if ( empty( $string ) ) {
			return "";
		}
		$string  = str_replace( ' ', '', $string );
		$persian = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
		$english = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ];
		$string  = str_replace( $persian, $english, $string );

		return filter_var( $string, FILTER_SANITIZE_NUMBER_INT );
	}

}