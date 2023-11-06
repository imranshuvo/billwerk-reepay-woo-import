<?php 
/*
* Plugin Name: Billwerk Woo Import
* Description: This plugin helps importing Woocommerce quickpay payment service data to Billwerk/reepay system
* Version: 1.0
* Requires PHP: 7.2
* Author: webkonsulenterne, Imran khan
* Author URI: https://webkonsulenterne.dk
* License: GPL v2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain: billwerk-woo-import
* Domain Path: /languages
*/
require __DIR__.'/bootstrap.php';

//Add the submenu to woocommerce
add_action('admin_menu','wk_billwerk_add_setting_page');

function wk_billwerk_add_setting_page(){
    add_submenu_page(
        'woocommerce',
        __('Billwerk/Reepay','billwerk-woo-import'),
        __('Billwerk/Reepay','billwerk-woo-import'),
        'manage_options',
        'wk-billwerk-woo-import',
        'wk_billwerk_woo_import_settings_page_callback',
    );
}

function wk_billwerk_woo_import_settings_page_callback(){
    $customer_to_create = [
        'email' => 'test23@test.com',
        'address' => "Some test address",
        'address2' => "address 2",
        'city' => 'LA',
        'country' => "US",
        'phone' => '555-555-555',
        'company' => 'Test company',
        'vat' => 'vatnumber',
        'test' => true,
        'first_name' => 'Imran',
        'last_name' => 'Khan',
        'postal_code' => '1216',
        'generate_handle' => true,
    ];

    //wk_bwi_get_all_customer();
    //wk_bwi_get_customer('cust-0009');
    // wk_bwi_add_payment_method(array(
    //     "source" => 'ct_ca7b393ebb0ab1b59ea075b55adae344', //source token from billwerk csv
    //     "customer_handle" => 'cust-0001'
    // ));
    //wk_bwi_create_customer($customer_to_create);

    // wk_bwi_create_subscription(array(
    //     'customer' => 'cust-0006', //only this or create_customer can be sent
    //     'create_customer' => $customer_to_create,
    //     'plan' => 'tester-123',
    //     'test' => true,
    //     //'source' => '', //this should be the source token from the billwerk csv
    //     'start_date' => date("Y-m-d"), //this should be when the subscription starts
    //     //'signup_method' => 'source' //for the existing customers, relevant for our case since we're importing
    //     'signup_method' => 'link',
    //     'generate_handle' => true
    // ));

    require __DIR__.'/views/settings-page.php';

}


