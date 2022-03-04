<?php

namespace Bird\Tools;

use Bird\Tools\Importer;
use Bird\Models\Ftp;
class AjaxHandler {

    // Start the import
    public function initImport($data){

        // Init Importer
        $importer = new Importer(new Ftp());

        $importType = $data["type"];

        $isCached = $importer->uploadAndCache($data["file"], $importType);

        $count =  count(wp_cache_get( "BMHS_products" ));

        // Push DB row ID and the loading html to array to be passed back
        $data["row_id"]  = $importer->createRecord();
        $data["message"] = $isCached["message"];

        // If read and cached, pass html to frontend
        if($data["message"]  === "Successfully read and cached"){
            $data["html"] = $this->getLoadingHtml($count, 0);
        } else {
            $data["html"] = $isCached["html"];
        }

        // Pass data back
        return $data;
    }

    public function importBatch($data, $offset){

        // Init Importer
        $importer = new Importer(new Ftp());

        $isCached = $importer->checkCache($data["file"], $data["type"]);

        $fileContents = $importer->pluckProducts($data["file"], $data["type"], $offset);

        if($data["type"] === "products"){
            // Move this to its own function
            foreach ($fileContents["products"] as $product){
                set_time_limit(0);

//                $sku = $importer->makeProductSKU($product);
                try {
                    $method = $importer->createOrUpdateProduct($product);

                    if($method === "Created"){
                        $importer->incrementCount($data["rowId"], "import");
                    } elseif($method === "Updated") {
                        $importer->incrementCount($data["rowId"], "update");
                    } else {
                        writeLineToLog($sku, 'error');
                        $importer->incrementCount($data["rowId"], "failed");
                    }
                } catch (Exception $e){
                    writeLineToLog($e, 'error');
                    writeLineToLog($sku, 'error');
                }
            }

        } elseif ($data["type"] === "pricing"){
            foreach ($fileContents["products"] as $product){

                set_time_limit(0);

                $sku = $importer->makeProductSKU($product);
                $method = $importer->updateProductPrice($product);

                if($method === "PriceSet"){
                    $importer->incrementCount($data["rowId"], "update");
                } elseif($method === "N/a") {
                    writeLineToLog($sku, 'error');
                    $importer->incrementCount($data["rowId"], "failed");
                }
            }

        } else {
            dd($data);
        }




        $response = [
            "file"        => $data["file"],
            "offset"      => $fileContents["offset"],
            "message"     => $fileContents["message"],
            "isCached"    => $isCached,
            "importCount" => $importer->getTotalImportCount($data["rowId"]),
            "rowId"       => $data["rowId"],
            "type"        => $data["type"]
        ];

        return $response;
    }

    public function getLoadingHtml($count, $current){
        $loadingHtml = "

            <style>
                #myProgress {
                  width: 100%;
                  background-color: grey;
                  margin-bottom: 15px;
                }
                
                #myBar {
                  width: 1%;
                  height: 30px;
                  background-color: green;
                  transition: all 1s ;
                }
            </style>


            <div class='bmhs-loader'>
                <h3>Importing <span class='count'>{$count}</span> Products</h3>
                
                <div id='myProgress'>
                    <div id='myBar'></div>
                </div>
            </div>
        ";

        return $loadingHtml;
    }


    public function getFinalCount($rowId, $status, $type)
    {
        $importer = new Importer(new Ftp());

        $updated = ucfirst($importer->getImportColCount($rowId,'update_count'));
        $created = ucfirst($importer->getImportColCount($rowId,'import_count'));
        $failed = ucfirst($importer->getImportColCount($rowId,'failed_count'));

        $type = ucfirst($type);

        $html = "
            <div class='bm-final-score'>
                <h3>{$type} Import Status -- {$status}</h3>
                <p><b>Updated: </b> {$updated}</p>
                <p><b>Created: </b> {$created}</p>
                <p><b>Failed: </b> {$failed}</p>
            </div>
        ";

        return $html;
    }
}


