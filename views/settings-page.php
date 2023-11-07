<?php  
    use League\Csv\Reader;
    use League\Csv\Statement;
?>

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

    <?php $file_url = file_exists(wk_bwi_get_csv_file_url()['path']) ?? ''; ?>
    <?php if($file_url != ''): ?>
    <div class="mt-10 bg-white p-4">
        <div class="text-green-import text-green-900 font-bold m-2"></div>
        <div class="text-red-import text-red-900 font-bold m-2"></div>


        <div id="wk_bwi_start_import_pre_run" class="bg-green-900 text-white font-bold inline-block cursor-pointer p-6 mr-5" style="display: none;">Test Run</div>

        <div id="wk_bwi_start_import" class="bg-green-900 text-white font-bold inline-block cursor-pointer p-6" style="display: none;">Start Customer and Payment Import</div>
        
    </div>
    <?php endif; ?>
</div>


<script>

    jQuery(document).ready(function($){

        //Import ajax pre run 
        $('#wk_bwi_start_import_pre_run').on('click', function(){
            $.ajax({
                type: 'post',
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                data: {
                    action: 'wk_bwi_process_import_pre_run',
                },
                beforeSend: function(){
                    console.log('beforeSend pre run ');
                },
                success: function(response){
                    console.log(response);
                }
            })
        });

        //Import ajax
        $('#wk_bwi_start_import').on('click', function(e){
            let totalQueries = 702; //This is where you changed the query number for now
            let batchSize = 10; //Adjust batch size as needed

            //Calculate the number of batches 
            let numBatches = Math.ceil(totalQueries/batchSize);

            // Process each batch sequantially
            let currentBatch = 1;

            function processBatch(){
                if(currentBatch <= numBatches){
                    let offset = (currentBatch - 1 ) * batchSize;

                    $.ajax({
                        type: 'post',
                        url: "<?php echo admin_url('admin-ajax.php'); ?>",
                        data: {
                            action: 'wk_bwi_process_import',
                            offset: offset
                        },
                        beforeSend: function(){
                            console.log('beforeSend currentBatch ', + currentBatch + ' and offset ' + offset );
                        },
                        success: function(response){
                            console.log(response);

                            currentBatch++;
                            processBatch();

                        }
                    })
                }
            }

            //Start processing the first batch
            processBatch();
            
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