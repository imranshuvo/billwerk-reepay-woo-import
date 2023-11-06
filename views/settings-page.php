<script src="https://cdn.tailwindcss.com"></script>

<div class="wrap" id="">
    <div class="bg-white p-4">
        <h1>Billwerk+ settings</h1>
    </div>

    <!-- Api key input field -->
    <div class="mt-10 bg-white p-4">
        <div class="text-green"></div>
        <div class="text-red"></div>
        <label class="block">
            <span class="block text-sm font-medium text-slate-700 pb-2">API Key</span>
            <input type="text" id="wk_billwerk_import_api_key" name="wk_billwerk_import_api_key" class="peer ..." value="<?php echo wk_bwi_get_api_key(); ?>" >
        </label>
        <label class="block mt-2">
            <span class="block text-sm font-medium text-slate-700 pb-2">CSV file name</span>
            <!-- CSV file needs to be on the upload dir directly -->
            <input type="text" id="wk_billwerk_import_file_name" name="wk_billwerk_import_file_name" class="peer ..." value="<?php echo wk_bwi_get_import_file_name(); ?>">
            <div class="block w-auto border mt-4"><?php echo wk_bwi_get_csv_file_url()['url']; ?></div>
        </label>
        <a id="option_submit" href="#" class="btn bg-orange-500 text-white p-3 mt-4 inline-block">Submit</a>
    </div>
    <?php var_dump(wk_bwi_get_csv_file_url()['path']); ?>
    <?php $file_url = file_exists(wk_bwi_get_csv_file_url()['path']) ?? ''; ?>
    <?php if($file_url != ''): ?>
    <div class="mt-10 bg-white p-4">
        <div class="text-green-import text-green-900 font-bold m-2"></div>
        <div class="text-red-import text-red-900 font-bold m-2"></div>

        <div id="wk_bwi_start_import" class="bg-green-900 text-white font-bold inline-block cursor-pointer p-6">Start Customer and Payment Import</div>


        <?php  

        $file_url = wk_bwi_get_csv_file_url()['path'];

        $handle = fopen($file_url, 'r');

        $not_found = 0;

        $row = 1;

        if($handle !== FALSE) {
            while(($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $num = count($data);
                for ($c=0; $c < $num; $c++) {
                    //print_r($data[$c]);
                    if(gettype($data[$c]) == 'string'){
                        $old_data = explode(";", $data[$c]);
                        echo '<pre>';
                        print_r($old_data);
                        echo '</pre>';

                        $pensopay_transaction_id = $old_data[0];
                        $sub_id_ref = explode("-", $old_data[1])[0];
                        $reepay_payment_token = $old_data[2];

                        $sub_order = get_post($sub_id_ref);

                        $data_to_push = array();

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
                                'vat' => 'vatnumber',
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


                            echo '<pre>';
                            print_r($data_to_push);
                            echo '</pre>';
                        }else {
                            $not_found++;
                        }
                        
                    }
                }

                //$row control 
                $row++;
            }
            fclose($handle);
        }

        echo $not_found;
        ?>
    </div>
    <?php endif; ?>
</div>


<script>

    jQuery(document).ready(function($){

        //Import ajax
        $('#wk_bwi_start_import').on('click', function(e){
            $.ajax({
                type: 'post',
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                data: {
                    action: 'wk_bwi_process_import',
                },
                beforeSend: function(){
                    console.log('beforeSend');
                },
                success: function(response){
                    console.log(response);
                }
            })
        });


        //This to save the api key and file name
        $('#option_submit').on('click', function(e){
            e.preventDefault();
            api_key = $('#wk_billwerk_import_api_key').val();
            file_url = $('#wk_billwerk_import_file_name').val();


            $.ajax({
                type: 'post',
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                data: {
                    action: "wk_bwi_save_options",
                    api_key: api_key,
                    file_url: file_url
                },
                beforeSend: function(){

                },
                success: function(response){
                    if(response.status == 200 ){
                        $('.text-green').text('Saved!');
                        location.reload();
                    }else{
                        $('.text-red').text('Error!');
                    }
                }
            });
        });
    });
</script>