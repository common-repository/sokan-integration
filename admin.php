<?php
global $wpdb;

$db                  = new Skng_Sokan_db( $wpdb );
$api_url_select      = $db->webServiceUrls();
$customer_identities = $db->customerIdentities();
$sync_modes          = $db->syncModes();

if ( isset( $_GET['item'] ) ) {

	$item = sanitize_text_field( $_GET['item'] );
	if ( $item == 'reset_all' ) {
		$db->resetSyncDate();
	} elseif ( $item == 'logout' ) {
		update_option( SKNG_PLUGIN_NAME . '_token', '' );
	}
}

if ( isset( $_POST ) ) {

	if ( isset( $_POST[ SKNG_PLUGIN_NAME . '_username' ] ) ) {

		$url = esc_url( sanitize_text_field( $_POST['api_url'] ) );
		if ( ! empty( $url ) and in_array( $url, $api_url_select ) ) {
			update_option( SKNG_PLUGIN_NAME . '_api_url', $url );
		}

		$api = new Skng_Sokan_api();

		$body = [
			'username' => sanitize_text_field( $_POST[ SKNG_PLUGIN_NAME . '_username' ] ),
			'password' => sanitize_text_field( $_POST[ SKNG_PLUGIN_NAME . '_password' ] )
		];

		$result = $api->apiRequest( json_encode( $body ), 'token' );

		if ( $api->isSuccess( $result ) ) {
			$response = json_decode( $result['result'], true );
			update_option( SKNG_PLUGIN_NAME . '_token', $response['token'] );
		} else {
			$login_error = 'نام کاربری یا رمز عبور اشتباه است';
		}
	}

	if ( isset( $_POST['sale_status_select'] ) ) {

		$complete_status   = sanitize_text_field( $_POST['sale_status_select'] ?? '' );
		$refunded_status   = sanitize_text_field( $_POST['refunded_status_select'] ?? '' );
		$api_limitation    = sanitize_text_field( $_POST[ SKNG_PLUGIN_NAME . '_api_limitation' ] ?? 50 );
		$customer_identity = sanitize_text_field( $_POST['customer_identity_select'] ?? 'id' );
		$sync_mode         = sanitize_text_field( $_POST['sync_mode_select'] ?? 'sync' );

		if ( ! empty( $complete_status ) and ! empty( $refunded_status ) ) {
			$option = explode( ",", $complete_status );
			if ( in_array( $option[1], wc_get_order_statuses() ) ) {
				update_option( SKNG_PLUGIN_NAME . '_sale_status', $option[0] );
				update_option( SKNG_PLUGIN_NAME . '_refunded_status', $refunded_status );
			}
		}

		if ( $value = filter_var( $api_limitation, FILTER_SANITIZE_NUMBER_INT ) and $api_limitation > 20 ) {
			update_option( SKNG_PLUGIN_NAME . '_api_limitation', $value );
		}

		if ( in_array( $customer_identity, $customer_identities ) ) {
			update_option( SKNG_PLUGIN_NAME . '_customer_identity', $customer_identity );
		}

		if ( in_array( $sync_mode, $sync_modes ) ) {
			update_option( SKNG_PLUGIN_NAME . '_sync_mode', $sync_mode );
		}
	}


}

$token             = get_option( SKNG_PLUGIN_NAME . '_token' );
$api_url           = get_option( SKNG_PLUGIN_NAME . '_api_url' );
$sale_status       = get_option( SKNG_PLUGIN_NAME . '_sale_status' );
$refunded_status   = get_option( SKNG_PLUGIN_NAME . '_refunded_status' );
$api_limitation    = get_option( SKNG_PLUGIN_NAME . '_api_limitation' );
$customer_identity = get_option( SKNG_PLUGIN_NAME . '_customer_identity' );
$sync_mode         = get_option( SKNG_PLUGIN_NAME . '_sync_mode' );
$last_date         = 'همگام سازی نشده';

if ( ! empty( $date = get_option( SKNG_PLUGIN_NAME . '_sync_date' ) ) ) {

	$last_date = round( ( time() - strtotime( $date ) ) / ( 60 * 60 * 24 ) );

	if ( $last_date < 1 ) {
		$last_date = "امروز" . " ( " . $date . " )";
	} else {
		$last_date = $last_date . " روز قبل " . " ( " . $date . " )";
	}
}

?>

<div class="wrap">

    <a href="https://sokan.tech" target="_blank">
        <img class="mt-12" src="<?php echo plugin_dir_url( __FILE__ ) . 'assets/images/sokan-logo.png'; ?>" alt="logo">
    </a>
    <hr/>
    <div id="skng_login" class="mt-12 mb-12">
        <div style="display: <?php echo empty( $token ) ? esc_html( 'unset' ) : esc_html( 'none' ); ?>">
            <h2>ورود به حساب کاربری سکان</h2>
            <form method="post" action="admin.php?page=sokan_integration">

                <label>پنل:</label>
                <select name="api_url">
					<?php foreach ( $api_url_select as $key => $value ) { ?>
                        <option <?php echo esc_html( $api_url ) == esc_html( $value ) ? 'selected' : '' ?>
                                value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $key ); ?>
                        </option>
					<?php } ?>
                </select>
                <br/>
                <br>

                <input type="text" class="regular-text" name="<?php echo esc_attr( SKNG_PLUGIN_NAME . "_username" ) ?>"
                       placeholder="نام کاربری"/>
                <br/>
                <br>
                <input type="text" class="regular-text" name="<?php echo esc_attr( SKNG_PLUGIN_NAME . "_password" ) ?>"
                       placeholder="رمز عبور"/>

                <p class="error-text"> <?php echo $login_error ?? ""; ?></p>

				<?php submit_button( "ورود" ); ?>

                <p>
                    حساب کاربری ندارید ؟<a href="https://sokan.tech/request-demo/" target="_blank"> درخواست دمو </a>
                </p>
            </form>
        </div>
        <div style="display: <?php echo empty( $token ) ? esc_html( 'none' ) : esc_html( 'unset' ); ?>">

            <h2>فروشگاه به پلتفرم <a href="<?php echo esc_html( str_replace( 'api-', '', $api_url ) ) ?>"
                                     target="_blank">سکان</a>
                متصل شد!</h2>

            <ul class="mt-8">
                <li>با خروج از حساب کاربری همگام سازی داده های فروشگاه با سکان متوقف خواهد شد.</li>
                <li>با ورود دوباره همگام سازی داده ها از سر گرفته خواهد شد.</li>
            </ul>

            <a
                    href="admin.php?page=sokan_integration&item=logout"
                    class="button-primary mt-8 mb-12"
                    style="background-color:#ce1e1e">خروج از حساب کاربری
            </a>

        </div>
    </div>
    <hr/>
    <div style="display: <?php echo empty( $token ) ? esc_html( 'none' ) : esc_html( 'unset' ); ?>">

        <div id="skng_setting" class="mt-12">
            <h2>تنظیمات همگام سازی</h2>
            <form method="post" action="admin.php?page=sokan_integration">
                <label>وضعیت سفارش های تکمیل شده : </label>
                <select name="sale_status_select">
					<?php foreach ( wc_get_order_statuses() as $key => $value ) { ?>
                        <option <?php echo esc_html( $sale_status ) == esc_html( $key ) ? 'selected' : '' ?>
                                value="<?php echo esc_attr( "$key,$value" ); ?>"><?php echo esc_html( $value ); ?>
                        </option>
					<?php } ?>
                </select>
                <br/>
                <br/>
                <label>وضعیت سفارش های مرجوع شده : </label>
                <select name="refunded_status_select">
					<?php foreach ( wc_get_order_statuses() as $key => $value ) { ?>
                        <option <?php echo esc_html( $refunded_status ) == esc_html( $key ) ? 'selected' : '' ?>
                                value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?>
                        </option>
					<?php } ?>
                </select>
                <br/>
                <br/>
                <label>کلید یکتای شناسایی مشتریان: </label>
                <select name="customer_identity_select">
					<?php foreach ( $customer_identities as $key => $value ) { ?>
                        <option <?php echo esc_html( $customer_identity ) == esc_html( $value ) ? 'selected' : '' ?>
                                value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $key ); ?>
                        </option>
					<?php } ?>
                </select>
                <br/>
                <br/>
                <label>نوع همگام سازی داده ها: </label>
                <select name="sync_mode_select">
					<?php foreach ( $sync_modes as $key => $value ) { ?>
                        <option <?php echo esc_html( $sync_mode ) == esc_html( $value ) ? 'selected' : '' ?>
                                value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $key ); ?>
                        </option>
					<?php } ?>
                </select>
                <span>در صورت استفاده از پلاگین های کش ممکن است گزینه همگام سازی در پس زمینه با اختلال همراه شود</span>

                <br/>
                <br/>
                <label>تعداد ارسال داده در هر درخواست : </label>
                <input type="number" style="width: 135px;"
                       name="<?php echo esc_attr( SKNG_PLUGIN_NAME . "_api_limitation" ) ?>"
                       value="<?php echo esc_attr( $api_limitation ) ?>"
                       placeholder="تعداد فاکتور"/>

                <span>در صورت خطای Time Out این عدد را کاهش دهید</span>

				<?php submit_button( "ذخیره تنظیمات" ); ?>
            </form>
        </div>

        <hr/>

        <div id="skng_sync">
            <h2>همگام سازی داده ها</h2>
            <h3 id="title"> تاریخ آخرین سفارش همگام سازی شده : <strong
                        id="sync-date"><?php echo esc_html( $last_date ) ?></strong></h3>
            <h4 id="sync-date"></h4>
            <ul class="mt-8">
                <li>2- عملیات بر اساس تاریخ سفارشات ثبت شده می باشد و <strong>تاریخ نمایش داده شده تاریخ آخرین فاکتور
                        تکمیل شده در فروشگاه شماست</strong>.
                </li>
                <li>1- بعد از شروع تا اتمام عملیات و نمایش <strong>"پایان همگام سازی"</strong> این صفحه را باز نگه دارید
                    در غیر اینصورت عملیات متوقف و همگام سازی چند ساعت بعد به صورت خودکار انجام خواهد گرفت.
                </li>
                <li>3- بسته به تعداد سفارشات ثبت شده در فروشگاه شما عملیات اولیه همگام سازی ممکن است 10 دقیقه یا بیشتر
                    طول بکشد.
                </li>
                <li>4- بعد از همگام سازی اولیه و بروزرسانی شدن تاریخ نمایش داده شده بالا ، به صورت
                    <strong>خودکار</strong> داده های جدید یا بروزرسانی شده در پس زمینه با <strong>سکان</strong> همگام
                    سازی خواهد شد.
                </li>
            </ul>
            <br>
            <div id="message"></div>
            <a id="panel"
               href="<?php echo esc_html( str_replace( 'api-', '', $api_url ) ) ?>"
               target="_blank"
               class="button-primary mt-8"
               style="display: none">مشاهده پنل سکان
            </a>
            <div id="loader" class="loader mt-12"></div>
            <br/>
            <div class="mb-12">
                <button id="ajaxsync" class="button-primary">شروع عملیات</button>
                <button id="stopajaxsync" class="button-primary" style="background-color:#ce1e1e ; display: none">توقف
                    عملیات
                </button>
            </div>
            <br/>
            <br/>

        </div>

        <hr/>

        <div id="skng_reset_log" class="mt-8">
            <h2>بازنشانی تاریخ همگام سازی</h2>
            <ul class="mt-8 mb-12 ">
                <li>1- پاک کردن آخرین تاریخ همگام سازی و خطاهای ثبت شده.</li>
                <li>2- تاریخ همگام سازی به حالت پیشفرض <strong>"همگام سازی نشده"</strong> تنظیم و عملیات همگام سازی از
                    <strong>اولین فاکتور</strong> ثبت شده شروع خواهد شد.
                </li>
                <li>3- <strong>این عمل داده های موجود در پنل سکان را پاک نخواهد کرد و صرفا داده های قدیمی دوباره ارسال
                        خواهند شد</strong>.
                </li>
            </ul>
            <br/>
            <a href="admin.php?page=sokan_integration&item=reset_all" class="button-primary"
               style="background-color:#ce1e1e">بازنشانی</a>
        </div>

    </div>
    <script>
        let sync_locked = true;

        const data = {
            'action': 'skng_sokan_sync',
            'item': 'sync'
        };

        function sync_data() {
            if (!sync_locked) {
                jQuery.post('admin-ajax.php', data, function (response) {
                    let res = JSON.parse(response)
                    if (res['stop']) {
                        jQuery('#message').html('<h2>' + res['message'] + '<h2/>');
                        jQuery('#loader').fadeOut(200);
                        jQuery('#panel').show();
                        jQuery('#ajaxsync').hide();
                        jQuery('#stopajaxsync').hide();
                        sync_locked = true;
                    } else {
                        if (!sync_locked) {
                            jQuery('#message')
                                .append('<br>')
                                .append(res['message'])
                                .append("   ===>  ")
                                .append(res['state']);
                            jQuery('#sync-date').html(res['date']);
                        }
                        sync_data();
                    }
                }).fail(function (response) {
                    alert('<textarea>' + JSON.stringify(response) + '</textarea>');
                    jQuery('#loader').fadeOut(200);
                });
            }
        }

        jQuery(document).ready(function () {

            jQuery('#ajaxsync').click(function () {
                sync_locked = false;
                jQuery('#message').html("در حال همگام سازی داده ها لطفا صبر کنید ...").show();
                jQuery('#loader').fadeIn(200);
                jQuery(this).hide();
                jQuery('#stopajaxsync').show();
                sync_data();
            });

            jQuery('#stopajaxsync').click(function () {
                sync_locked = true;
                jQuery(this).hide();
                jQuery('#message').html("برای شروع استخراج داده و همگام سازی  روی دکمه شروع عملیات کلیک کنید").show();
                jQuery('#loader').fadeOut(200);
                jQuery('#ajaxsync').show();
            });

            const data = {
                'action': 'skng_sokan_sync',
                'item': 'custom_code'
            };
            jQuery.post('admin-ajax.php', data, function (response) {
            })

        });

    </script>
</div>
<style>

    .mt-12 {
        margin-top: 12px;
    }

    .mt-8 {
        margin-top: 8px;
    }

    .mb-12 {
        margin-bottom: 12px;
    }

    .error-text {
        color: red;
    }

    .loader {
        display: inline-block;
        border: 2px solid #f3f3f3; /* Light grey */
        border-top: 2px solid #3498db; /* Blue */
        border-radius: 50%;
        width: 20px;
        height: 20px;
        animation: spin 1s linear infinite;
        display: none;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }

</style>

