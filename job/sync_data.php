<?php

defined('ABSPATH') or die('No access!');

global $wpdb;

$logger = new Skng_Sokan_logger();
$isSokanAjaxCall = $sokanAjax ?? true;

try {

    $logger->ping();

    $db = new Skng_Sokan_db($wpdb);
    $api = new Skng_Sokan_api();
    $orders_items = $db->getAllOrders();

    $order_query = $orders_items['order'];
    $last_sync_date = $orders_items['date'];
    $orders = apply_filters("skng_get_all_orders_filter", $order_query);
    if (count($orders) > 0) {


        $result = $api->sendItems($orders);

        if ($api->isUnauthorized($result)) {
            update_option(SKNG_PLUGIN_NAME . "_token", '');
            $logger->exception("حساب کاربری فروشگاه غیرفعال و کاربر به صفحه ورود به حساب کاربری منتقل شد");
            if ($isSokanAjaxCall) {
                echo json_encode([
                    'stop' => true,
                    'count' => '0',
                    'message' => "حساب کاربری شما مسدود شده است لطفا با پشتیبانی سکان تماس بگیرید!",
                    'date' => (string)$last_sync_date
                ],JSON_UNESCAPED_UNICODE);
                exit();
            }
        }

        $logger->synced($result['errors'], count($orders), json_encode($result['response'] ?? [],JSON_UNESCAPED_UNICODE));

        if (count($result['errors']) > 0) {
            $db->saveErrors($result['errors']);
        }

        update_option(SKNG_PLUGIN_NAME . "_sync_date", (string)$last_sync_date);

        $count = (string)count($orders);

        if ($isSokanAjaxCall) {
            echo json_encode([
                'stop' => false,
                'message' => "در حال ارسال فاکتور های ثبت شده بعد از تاریخ : $last_sync_date",
                'state' => "تعداد $count سطر فاکتور همگام سازی شد",
                'date' => (string)$last_sync_date
            ],JSON_UNESCAPED_UNICODE);
        }

    } else {

        $logger->emptyData();
        if ($isSokanAjaxCall) {
            echo json_encode([
                'stop' => true,
                'message' => "داده جدیدی یافت نشد و تمامی داده های فروش تا تاریخ نمایش داده شده همگام سازی شد.",
                'state' => 'پایان همگام سازی',
                'date' => (string)$last_sync_date
            ],JSON_UNESCAPED_UNICODE);
        }
    }

} catch (Exception $exception) {
    $logger->exception($exception->getMessage() . " / " . $exception->getTraceAsString());
    if ($isSokanAjaxCall) {
        echo json_encode([
            'stop' => true,
            'message' => $exception->getMessage(),
            'state' => 'خطایی در همگام سازی رخ داد لطفا صفحه را رفرش و دوباره تلاش کنید',
            'date' => "خطا در دریافت اطلاعات"
        ],JSON_UNESCAPED_UNICODE);
    }
}
