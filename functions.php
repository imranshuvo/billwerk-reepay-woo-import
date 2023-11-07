<?php 
use League\Csv\Reader;
use League\Csv\Statement;

//Get the stored api key from database if exists
function wk_bwi_get_api_key(){
    return get_option('wk_billwerk_import_api_key') ?? '';
}

//Get the stored csv import url from database if exist
function wk_bwi_get_import_file_name(){
    return get_option('wk_billwerk_import_file_name') ?? '';
}

function wk_bwi_get_csv_file_url(){
    $upload_dir = wp_upload_dir();

    return array(
        'path' => $upload_dir['basedir'].'/'.wk_bwi_get_import_file_name(),
        'url' => $upload_dir['baseurl'].'/'.wk_bwi_get_import_file_name()

    );
}

//Error logging 
function wk_bwi_error_log($object, $data){
    error_log('*******ERROR********');
    error_log(print_r($object, true));
    error_log('Request data => ');
    error_log(print_r($data, true ));
    error_log("*******END ERROR******");
}

function wk_bwi_success_log($object, $data){
    error_log('*******Success********');
    error_log(print_r($object, true));
    error_log('Request data => ');
    error_log(print_r($data, true ));
    error_log("*******END Success******");
}


function wk_bwi_update_payment_method_meta($post_id, $meta_value){

    $payment_method_meta_current = get_post_meta($post_id, '_payment_method_meta', true );

    if($payment_method_meta_current == '' || $payment_method_meta_current == null ){
        $payment_method_meta_current = array();
        $unserialized_data = $payment_method_meta_current;
    }else {
        $unserialized_data = maybe_unserialize($payment_method_meta_current);
    }
    $unserialized_data['reepay_checkout']['post_meta']['_reepay_token'] = $meta_value;

    update_post_meta($post_id,'_payment_method_meta', $unserialized_data);
    
}


//save options
add_action('wp_ajax_wk_bwi_save_options','wk_bwi_save_options');

function wk_bwi_save_options(){
    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    $file_url = isset($_POST['file_url']) ? sanitize_text_field($_POST['file_url']) : '';

    if($api_key != ''){
        update_option('wk_billwerk_import_api_key', $api_key);
    }
    if($file_url != ''){
        update_option('wk_billwerk_import_file_name', $file_url);
    }
    
    wp_send_json(array(
        'status' => 200,
        'message' => 'Saved'
    ));

    wp_die();
}



//Import 
add_action('wp_ajax_wk_bwi_process_import','wk_bwi_process_import');

function wk_bwi_process_import(){
    $limit = 10;
    $offset = isset($_POST['offset']) && $_POST['offset'] != '' ? intval($_POST['offset']) : 0;

    $not_pushed_data = array();
    $pushed_data = array();
    $response_from_api = array();


    $file_url = wk_bwi_get_csv_file_url()['path'];
    $csv = Reader::createFromPath($file_url, 'r');
    $stmt = Statement::create()
        ->offset($offset)
        ->limit($limit);
    $records = $stmt->process($csv);


    foreach ($records as $record) {
        //do something here
        $data_to_push = array();

        $old_data = explode(";", $record[0]);

        $pensopay_transaction_id = $old_data[0];
        $sub_id_ref = explode("-", $old_data[1])[0];
        $reepay_payment_token = $old_data[2];

        $sub_order = get_post($sub_id_ref);


        $data_to_push['pensopay_transaction_id'] = $pensopay_transaction_id;
        $data_to_push['sub_id_ref'] = $sub_id_ref;
        $data_to_push['reepay_payment_token'] = $reepay_payment_token;

        if(is_object($sub_order)){
            $customer = [
                'email' => get_post_meta($sub_id_ref, '_billing_email', true ),
                'address' => get_post_meta($sub_id_ref, '_billing_address_1', true ),
                'address2' => get_post_meta($sub_id_ref, '_billing_address_2', true ),
                'city' => get_post_meta($sub_id_ref, '_billing_city', true ),
                'country' => get_post_meta($sub_id_ref, '_billing_country', true ),
                'phone' => get_post_meta($sub_id_ref, '_billing_phone', true ),
                'company' => get_post_meta($sub_id_ref, '_billing_company', true ),
                'vat' => '',
                'first_name' => get_post_meta($sub_id_ref, '_billing_first_name', true ),
                'last_name' => get_post_meta($sub_id_ref, '_billing_last_name', true ),
                'postal_code' => get_post_meta($sub_id_ref, '_billing_postcode', true ),
            ];

            $data_to_push['source'] = $reepay_payment_token;

            //check if the customer is already on billwerk
            if(get_post_meta($sub_id_ref,'_reepay_customer_id', true ) != ''){
               $data_to_push['customer_handle'] = get_post_meta($sub_id_ref,'_reepay_customer_id', true );
            }else{
                $data_to_push['customer'] = $customer;
                $data_to_push['customer']['generate_handle'] = true;
            }

            $pushed_data[] = $data_to_push;

            //so, now we have the data ready for the payment
            $api_response = wk_bwi_add_payment_method($data_to_push);
            $response_from_api[] = $api_response;

            if($api_response['status_code'] == 200){
                update_post_meta($sub_id_ref,'_payment_method','reepay_checkout');
                update_post_meta($sub_id_ref, '_reepay_token', $api_response['token']);
                update_post_meta($sub_id_ref, 'reepay_token', $api_response['token']);
                update_post_meta($sub_id_ref,'_reepay_customer_id', $api_response['customer_id']);
                update_post_meta($sub_id_ref,'_reepay_customer', $api_response['customer_id']);

                wk_bwi_update_payment_method_meta($sub_id_ref, $api_response['token']);

                wk_bwi_success_log($api_response,$data_to_push );
            }else {
                wk_bwi_error_log($api_response, $data_to_push);
            }

        }else {
            $not_pushed_data[] = $data_to_push;
        }

    }

    $data_to_pull = array(
        'api_response' => $response_from_api,
        'pushed_data' => $pushed_data,
        'data_not_pushed' => $not_pushed_data,
    );

    wp_send_json($data_to_pull);
    wp_die();
}






//This is for testing purpose, just push 1 or 2 data 
add_action('wp_ajax_wk_bwi_process_import_pre_run','wk_bwi_process_import_pre_run');

function wk_bwi_process_import_pre_run(){
    $limit = 1000;
    $offset = 0;

    $not_pushed_data = array();
    $pushed_data = array();
    $response_from_api = array();


    $file_url = wk_bwi_get_csv_file_url()['path'];
    $csv = Reader::createFromPath($file_url, 'r');
    $stmt = Statement::create()
        ->offset($offset)
        ->limit($limit);
    $records = $stmt->process($csv);


    foreach ($records as $record) {
        //do something here
        $data_to_push = array();

        $old_data = explode(";", $record[0]);

        $pensopay_transaction_id = $old_data[0];
        $sub_id_ref = explode("-", $old_data[1])[0];
        $reepay_payment_token = $old_data[2];

        $sub_order = get_post($sub_id_ref);


        $data_to_push['pensopay_transaction_id'] = $pensopay_transaction_id;
        $data_to_push['sub_id_ref'] = $sub_id_ref;
        $data_to_push['reepay_payment_token'] = $reepay_payment_token;

        if(is_object($sub_order)){
            $customer = [
                'email' => get_post_meta($sub_id_ref, '_billing_email', true ),
                'address' => get_post_meta($sub_id_ref, '_billing_address_1', true ),
                'address2' => get_post_meta($sub_id_ref, '_billing_address_2', true ),
                'city' => get_post_meta($sub_id_ref, '_billing_city', true ),
                'country' => get_post_meta($sub_id_ref, '_billing_country', true ),
                'phone' => get_post_meta($sub_id_ref, '_billing_phone', true ),
                'company' => get_post_meta($sub_id_ref, '_billing_company', true ),
                'vat' => '',
                'first_name' => get_post_meta($sub_id_ref, '_billing_first_name', true ),
                'last_name' => get_post_meta($sub_id_ref, '_billing_last_name', true ),
                'postal_code' => get_post_meta($sub_id_ref, '_billing_postcode', true ),
            ];

            $data_to_push['source'] = $reepay_payment_token;

            //check if the customer is already on billwerk
            if(get_post_meta($sub_id_ref,'_reepay_customer_id', true ) != ''){
               $data_to_push['customer_handle'] = get_post_meta($sub_id_ref,'_reepay_customer_id', true );
            }else{
                $data_to_push['customer'] = $customer;
                $data_to_push['customer']['generate_handle'] = true;
            }

            $pushed_data[] = $data_to_push;

            //so, now we have the data ready for the payment
            // $api_response = wk_bwi_add_payment_method($data_to_push);
            // $response_from_api[] = $api_response;

            // if($api_response['status_code'] == 200){
            //     update_post_meta($sub_id_ref,'_payment_method','reepay_checkout');
            //     update_post_meta($sub_id_ref, '_reepay_token', $api_response['token']);
            //     update_post_meta($sub_id_ref, 'reepay_token', $api_response['token']);
            //     update_post_meta($sub_id_ref,'_reepay_customer_id', $api_response['customer_id']);
            //     update_post_meta($sub_id_ref,'_reepay_customer', $api_response['customer_id']);

            //     wk_bwi_success_log($api_response,$data_to_push );
            // }else {
            //     wk_bwi_error_log($api_response, $data_to_push);
            // }

        }else {
            $not_pushed_data[] = $data_to_push;
        }

    }

    $data_to_pull = array(
        'api_response' => $response_from_api,
        'pushed_data' => $pushed_data,
        'data_not_pushed' => $not_pushed_data,
    );

    wp_send_json($data_to_pull);
    wp_die();
}