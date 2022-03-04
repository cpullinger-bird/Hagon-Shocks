<?php
//
//namespace Bird\Tools;
//
//use Bird\Tools\Importer;
//
//class AjaxHandler {
//
//    public function initImport($data){
//
//        // Init Importer
//        $importer = new Importer();
//        $count =  $importer->getJsonProductCount($data["file"]);
//
//        // Push DB row ID and the loading html to array to be passed back0
//        $data["row_id"] = $importer->createRecord();
//        $data["html"] = $this->getLoadingHtml($count, 0);
//
//        // Pass data back
//        return $data;
//    }
//
//
//    public function importBatch($data, $offset){
//
//        // Init Importer
//        $importer = new Importer();
//        $fileContents = $importer->getJsonFileContents($data["file"], $offset);
//        dd($fileContents);
//        foreach ($fileContents["products"] as $product){
//
//            set_time_limit(0);
//            dd($product);
//            $method = $importer->createOrUpdateProduct($product);
//
//            if($method === "Created"){
//                $importer->incrementCount($data["rowId"], "import");
//            } elseif($method === "Updated") {
//                $importer->incrementCount($data["rowId"], "update");
//            } elseif($method === "Failed") {
//            }
//        }
//
//        $response = [
//            "file"        => $data["file"],
//            "offset"      => $fileContents["offset"],
//            "message"     => $fileContents["message"],
//            "importCount" => $importer->getLastRow(),
//            "rowId"       => $data["rowId"]
//        ];
//
//        return $response;
//    }
//
//    public function getLoadingHtml($count, $current){
//        $loadingHtml = "
//
//            <style>
//                #myProgress {
//                  width: 100%;
//                  background-color: grey;
//                }
//
//                #myBar {
//                  width: 1%;
//                  height: 30px;
//                  background-color: green;
//                  transition: all 1s ;
//                }
//            </style>
//
//
//            <div class='bmhs-loader'>
//                <h3>Importing <span class='count'>{$count}</span> Products</h3>
//
//                <div id='myProgress'>
//                    <div id='myBar'></div>
//                </div>
//            </div>
//        ";
//
//        return $loadingHtml;
//    }
//
//
//
//
//
//
//}
//
//
