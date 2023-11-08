<?php 

use GuzzleHttp\Exception\ClientException;

function wk_bwi_request($type, $url, $body = array()){
    global $client, $headers;

    try {
        $response = $client->request(
            $type,
            $url,
            [
                'headers' => $headers ,
                'json' => $body 
            ]
        );

        //status_cdoe
        $status_code = $response->getStatusCode();
        //status phrase
        $status_phrase = $response->getReasonPhrase();
        //Body
        $content = $response->getBody()->getContents(); //this is a json_encoded string\

        return array(
            'status_code' => $status_code,
            'status' => $status_phrase,
            'content' => $content, //this is json_encoded string
        );

    } catch ( ClientException $e) {

        if($e->hasResponse()){
            $response = $e->getResponse();
            $error_body = json_decode($response->getBody()->getContents());
            
            if(isset($error_body->code)){
                $status_code = $error_body->code;
            }else{
                $status_code = $error_body->http_status;
            }

            return array(
                'status_code' => $status_code,
                'message' => $error_body->error,
                'content' => $error_body
            );

        }else{

            return array(
                'status_code' => '400',
                'message' => 'Something went wrong and exception does not have a response'
            );
        }
        
    }
}

/***** Customers  ******/

//function get all the customers
function wk_bwi_get_all_customer(){
    $response = wk_bwi_request('GET','/v1/list/customer');

    if($response['status_code'] == 200 ){
        //successfull
        $customers = json_decode($response['content']);

        echo '<pre>';
        print_r($customers);
        echo '</pre>';

    }else {
        //error happened; do something
        wk_bwi_error_log($response, 'wk_bwi_get_all_customer function call');
    }
}

//get a single customer 
function wk_bwi_get_customer($customer_handle){
    $response = wk_bwi_request('GET', "/v1/customer/{$customer_handle}");

    if($response['status_code'] == 200 ){
        //successfull
        $customer = json_decode($response['content']);

        echo '<pre>';
        print_r($customer);
        echo '</pre>';
    }else{
        //error happened; do something
        wk_bwi_error_log($response, $customer_handle);
    }
}

//Create a single customer 
function wk_bwi_create_customer($customer_data = array()){
    //customer_data must be an array
    $response = wk_bwi_request('POST', '/v1/customer', $customer_data);

    if($response['status_code'] == 200 ){
        //successfull
        $customer = json_decode($response['content']);

        echo '<pre>';
        print_r($customer);
        echo '</pre>';
    }else{
        //error happened; do something
        wk_bwi_error_log($response, $customer_data);
    }

}

/******* Subscription *****/
function wk_bwi_create_subscription($plan_data){
    $response = wk_bwi_request('POST', '/v1/subscription', $plan_data);

    if($response['status_code'] == 200 ){
        //successfull
        $subscription = json_decode($response['content']);

        echo '<pre>';
        print_r($subscription);
        echo '</pre>';

    }else{
        //error happened; do something
        wk_bwi_error_log($response, $plan_data);
    }
}

/******** Payments  *******/

//get a payment method by id
function wk_bwi_get_payment($payment_id){
    $response = wk_bwi_request('GET', "/v1/payment_method/{$payment_id}");

    if($response['status_code'] == 200 ){
        //successfull
        $payment_method = json_decode($response['content']);

        // echo '<pre>';
        // print_r($payment_method);
        // echo '</pre>';

        return array(
            'status_code' => 200,
            'payment_method' => $payment_method //full response
        );

    }else{
        //error happened; do something
        // error_log 
        wk_bwi_error_log($response, $payment_id);

        return array(
            'status_code' => $response['status_code'],
            'response' => $response
        );
    }
}


//add payment method 
function wk_bwi_add_payment_method($payment_data){

    $response = wk_bwi_request('POST', '/v1/payment_method', $payment_data);

    if($response['status_code'] == 200 ){
        //successfull
        //First make sure to get the payment id 
        $payment_method = json_decode($response['content']);
        $payment_id = $payment_method->id;
        $customer_handle = $payment_method->customer;

        return array(
            'status_code' => 200,
            'token' => $payment_id,
            'customer_id' => $customer_handle,
            'payment_method' => $response['content'] //full response
        );

    }else{
        //error happened; do something
        // error_log 
        wk_bwi_error_log($response, $payment_data);

        return array(
            'status_code' => $response['status_code'],
            'response' => $response
        );
    }
    
}


//