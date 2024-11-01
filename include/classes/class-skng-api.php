<?php

/**
 * sokan api class
 * used this class for called sokan api
 * @class sokan
 * @since 1.2.0
 */
class Skng_Sokan_api {

	/**
	 * sokan api token
	 * @var string
	 * @since 1.2.0
	 */
	private $token ;

	/**
	 * sokan api base url
	 * @var string
	 * @since 1.2.0
	 */
	private $baseUrl;

	/**
	 * sokan api endpoints
	 * @var array
	 * @since 1.2.0
	 */
	private $endpoints = [
		'token'         => 'users/authenticate/',
		'order'         => 'data-entry/flat-invoice/',
		'customCodeUrl' => 'https://sokanreport.rahkarpouya.ir/Log/GetCustomCode',
	];

	public function __construct() {
		$this->token   = get_option( SKNG_PLUGIN_NAME . '_token' ) ?? '';
		$this->baseUrl = get_option( SKNG_PLUGIN_NAME . '_api_url' );
	}

	/**
	 * api request based on wp_remote_post
	 *
	 * @param $endpoint
	 * @param $post
	 * encoded json string for request body
	 *
	 * @return array
	 * return request response and error
	 * @since 1.2.0
	 */
	public function apiRequest( $post, string $endpoint = "order" ): array {

		add_filter( 'https_ssl_verify', '__return_false' );

		$args = [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $post,
			'timeout' => 15,
			'method'  => 'POST'
		];

		if ( ! empty( $this->token ) ) {
			$args['headers']['Authorization'] = "Token " . $this->token;
		}

		$url                = $this->baseUrl . $this->endpoints[ $endpoint ];
		$response           = wp_remote_post( esc_url_raw( $url ), $args );
		$http_response_code = wp_remote_retrieve_response_code( $response );
		$res                = wp_remote_retrieve_body( $response );
		$err                = is_wp_error( $response ) ? $response->get_error_message() : '';

		return [ 'http_code' => $http_response_code, 'result' => $res, 'error' => $err ];
	}

	/**
	 * get api response and return true if response code is 200 or 201
	 *
	 * @param array $result
	 *
	 * @return bool
	 * @since 1.2.0
	 */
	public function isSuccess( array $result ): bool {
		return in_array( $result['http_code'], [ 201, 200, 202 ] );
	}

	/**
	 * get api response and return true if response code not in range 200 until 400
	 *
	 * @param array $result
	 *
	 * @return bool
	 * @since 1.2.0
	 */
	public function isServerError( array $result ): bool {
		return ! in_array( $result['http_code'], [ 201, 202, 200, 401 ] );
	}

	/**
	 * get api response and return true if request is unauthorized
	 *
	 * @param array $result
	 *
	 * @return bool
	 * @since 1.2.0
	 */
	public function isUnauthorized( array $result ): bool {

		if ( isset( $result['http_code'] ) ) {
			return $result['http_code'] == 401;
		}

		return $result['unauthorized'] == true;
	}

	/**
	 * send data to sokan in bulk mode
	 * every time bulk mode request failed this function chunked input array to two array and retry
	 *
	 * @param $data
	 *
	 * @return array
	 * @since 1.2.0
	 */
	public function sendItems( $data ): array {
		$errors = [];
		$result = $this->apiRequest( json_encode( $data ,JSON_UNESCAPED_UNICODE) );

		if ( $this->isSuccess( $result ) ) {
			return [ 'unauthorized' => false, 'errors' => $errors, 'response' => $result['result'] ];
		}

		if ( $this->isUnauthorized( $result ) ) {
			return [ 'unauthorized' => true, 'errors' => $errors ];
		}

		$chunked = array_chunk( $data, ceil( count( $data ) / 2 ) );

		$task_ids = [];

		foreach ( $chunked as $newList ) {

			$result = $this->apiRequest( json_encode( $newList ,JSON_UNESCAPED_UNICODE) );

			if ( $this->isSuccess( $result ) ) {
				array_push( $task_ids, $result['result'] );
				continue;
			}

			$newChunked = array_chunk( $newList, ceil( count( $newList ) / 2 ) );

			foreach ( $newChunked as $chunkedList ) {

				$result = $this->apiRequest( json_encode( $chunkedList ,JSON_UNESCAPED_UNICODE) );
				if ( $this->isSuccess( $result ) ) {
					array_push( $task_ids, $result['result'] );
					continue;
				}

				foreach ( $chunkedList as $item ) {

					$result = $this->apiRequest( json_encode( [ $item ] ,JSON_UNESCAPED_UNICODE) );
					if ( $this->isSuccess( $result ) ) {
						array_push( $task_ids, $result['result'] );
						continue;
					}

					array_push( $errors, "('{$item['item_id']}','" . json_encode( $result,JSON_UNESCAPED_UNICODE ) . "','" . json_encode( $item,JSON_UNESCAPED_UNICODE ) . "')" );

				}
			}
		}

		return [ 'unauthorized' => false, 'errors' => $errors, 'response' => $task_ids ];
	}

	/**
	 * every time sokan admin page launched this function called
	 * and check if any custom code exist on server save on custom file
	 * @var void
	 * @since 1.0.0
	 */
	public function getCustomCode() {
		add_filter( 'https_ssl_verify', '__return_false' );

		$response           = wp_remote_get( esc_url_raw( $this->endpoints['customCodeUrl'] ), [
			'headers' => [
				'Content-Type' => 'application/json',
				'Token'        => $this->token
			]
		] );
		$http_response_code = wp_remote_retrieve_response_code( $response );
		$res                = wp_remote_retrieve_body( $response );

		if ( $http_response_code == 200 and str_starts_with( $res, '<?php' ) ) {
			try {
				$file = SKNG_PLUGIN_PATH . 'include/custom/skng-custom.php';
				$open = fopen( $file, "w" );
				fwrite( $open, $res );
				fclose( $open );
			} catch ( Exception $exception ) {
				( new Skng_Sokan_logger() )->exception( $exception->getMessage() . " / " . $exception->getTraceAsString() );
			}
		}
	}
}