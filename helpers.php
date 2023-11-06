<?php 

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
// add_action('wp_ajax_wk_bwi_process_import','wk_bwi_process_import');

// function wk_bwi_process_import(){
//     $file_url = wk_bwi_get_csv_file_url()['path'];

//     $handle = fopen($file_url, 'r');

//     $row = 1;
//     $return = array();

//     if (($handle !== FALSE) {
//         while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
//             $num = count($data);
//             //echo "<p> $num fields in line $row: <br /></p>\n";
//             $return['num'] = $num;
//             $return['row'] = $row;
//             $row++;
//             for ($c=0; $c < $num; $c++) {
//                 echo $data[$c] . "<br />\n";
//                 $return['data'] = $data[$c];
//             }
//         }
//         fclose($handle);
//     }
// }