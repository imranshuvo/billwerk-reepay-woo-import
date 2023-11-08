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


function wk_bwi_update_payment_tokens($token_data){
    global $wpdb;

    //$token = new WC_Payment_Token_CC();

    $token = new \Reepay\Checkout\Tokens\TokenReepay();

    $token->set_gateway_id( $token_data['gateway_id'] );
    $token->set_token( $token_data['token'] );
    $token->set_last4( $token_data['last4'] );
    $token->set_expiry_year( $token_data['expiry_year']);
    $token->set_expiry_month( $token_data['expiry_month'] );
    $token->set_card_type( $token_data['card_type'] );
    $token->set_user_id( $token_data['user_id'] );
    $token->set_masked_card( $token_data['masked_card'] );
    $token->save();

    if(!$token->get_id()){
        $response = array(
            'status' => 401,
            'message' => 'Token could not be created',
            'token' => $token_data['token']
        );
    }else{
        
        $response = array(
            'status' => 200,
            'message' => 'Token created successfully!',
            'token' => $token->get_id()
        );
    }
    
    return $response;
}


add_action('wp_ajax_wk_bwi_save_payment_tokens','wk_bwi_save_payment_tokens');


function wk_bwi_save_payment_tokens(){
    $limit = 10;
    $offset = isset($_POST['offset']) && $_POST['offset'] != '' ? intval($_POST['offset']) : 0;


    $args = array(
     'post_type' => 'shop_subscription',
     'posts_per_page' => $limit,
     'offset' => $offset,
     'post_status' => 'any',
     'include' => array(512970),
     /*'meta_query' => array(
         array(
             'key' => '_payment_method',
             'value' => 'reepay_checkout'
         )
     ),*/

    );

    $posts = get_posts($args);


    $response_back = array();

    if(is_array($posts) && count($posts) > 0){
        foreach($posts as $post){
            $token = array();

            $sub_id = $post->ID;
            $sub_customer_id = get_post_meta($sub_id, '_customer_user', true );

            $payment_id =  'ca_e037b5fbb034a5c036c79a1f23ab76ff';//get_post_meta($sub_id, 'reepay_token', true );

            if($payment_id != ''){
                $response = wk_bwi_get_payment($payment_id); //should be the object

                if($response['status_code'] == 200 ){

                    $card = $response['payment_method']->card;

                    $expiry_month = explode("-", $card->exp_date)[0];
                    $expiry_year = '20'.explode("-", $card->exp_date)[1];
                    $last4 = substr( $card->masked_card, -4);
                    $card_type = $card->transaction_card_type;
                    $user_id = $sub_customer_id;
                    $masked_card = $card->masked_card;


                    $token['gateway_id'] = 'reepay_checkout';
                    $token['token'] = $payment_id;
                    $token['last4'] = $last4;
                    $token['expiry_year'] = $expiry_year;
                    $token['expiry_month'] = $expiry_month;
                    $token['card_type'] = $card_type;
                    $token['user_id'] = $user_id;
                    $token['masked_card'] = $masked_card;


                    $token_saved = wk_bwi_update_payment_tokens($token);

                    $response_back['token_saved'] = $token_saved;

                    $response_back['tokendata'] = $token;

                    $response_back[] = $response['payment_method'];

                }
            }


        }
    }

    wp_send_json($response_back);

    wp_die();

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