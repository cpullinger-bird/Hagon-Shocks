<?php
//
//namespace Bird\Tools;
//
//use WC_Product;
//
//class Importer {
//
//    // Get a list of all the Json files in the import folder
//    public function getJsonFiles(){
//        $scannedFiles = array_diff(scandir(BMHS_IMPORT_FOLDER), ['.', '..']);
//        return $scannedFiles;
//    }
//
//    // Get the select option html from the files in the import folder
//    public function getFileOptionsHtml($files = null){
//        if($files > 0 ){
//            $optionsHtml = "<option>Select a file</option>";
//        } else {
//            $optionsHtml = "<option>You need to upload files via FTP to the <strpng>sap</strpng> folder</option>";
//        }
//        foreach($files as $file){
//            $optionLabel = ucfirst(explode('.', $file)[0]);
//            $optionsHtml .= "<option value='{$file}'>{$optionLabel}</option>";
//        }
//        return $optionsHtml;
//    }
//
//    // PAss back any errors when reading the Json file
//    public function getJsonReadError(){
//
//        switch(json_last_error())
//        {
//            case JSON_ERROR_DEPTH:
//                $error = 'Maximum stack depth exceeded';
//                break;
//            case JSON_ERROR_CTRL_CHAR:
//                $error =  'Unexpected control character found';
//                break;
//            case JSON_ERROR_SYNTAX:
//                $error = 'Syntax error, malformed JSON';
//                break;
//        }
//
//        if($error){
//            $html = "
//                <div class='bm-final-score'>
//                    <h1>Hagon Shocks JSON Product Importer</h1>
//                    <h3>Error Importing File</h3>
//                    <p><b>Message:</b> {$error}</p>
//                </div>
//            ";
//            return $html;
//
//        }
//    }
//
//
//    public function getJsonProductCount($fileName){
//        return count(json_decode(file_get_contents(BMHS_IMPORT_FOLDER . "/" . $fileName))->Products);
//    }
//
//    // Get the contents of the pass json file
//    public function getJsonFileContents($fileName, $offset, $length=150){
//
//        $products = json_decode(file_get_contents(BMHS_IMPORT_FOLDER . "/" . $fileName))->Products;
//        $productCount = count($products);
//        $pluckedProducts = array_slice($products, $offset, $length);
//
//
//        // Report if there is an error with the document
//        if(json_last_error()){
//            return $response["message"] = $this->getJsonReadError();
//            die;
//        }
//
//        if(count($pluckedProducts) !== $length){
//            return "Importing Finished";
//        }
//
//        $response = [
//            "message"   => "Importing",
//            "count"     => $productCount,
//            "offset"    => $offset,
//            "length"    => $length,
//            "products"  => $pluckedProducts,
//        ];
//
//        return $response;
//    }
//
//    // Create a product from the passed object
//    public function createOrUpdateProduct($product){
//        try {
//
//            /** Get name and SKU to search if product exists **/
//            $wcProductName = $product->Description . ' ' . $product->CapacityCCM . '-' . $product->CapacityRange . ' ' . $product->ProductCode;
//
//            $wcProductSKU = slugify($wcProductName);
//
//            $productId = wc_get_product_id_by_sku($wcProductSKU);
//
//            if($productId){
//                // Update the product
//                $wcProduct = wc_get_product($productId);
//                $method = "Updated";
//            } else {
//                // Create the product
//                $wcProduct = new WC_Product();
//                $method = "Created";
//            }
//
//            /** Name **/
//            $wcProduct->set_name($wcProductName);
//
//            /** SKU **/
//            $wcProduct->set_sku($wcProductSKU);
//
//
//
//
//            /** Category
//             * ProductGroup => Make =>> Model =>> Years =>> Product?
//             */
//            $category_ids = $this->createCategoryPath($product);
//            $wcProductCats = [];
//            $wcProductCats[] = $category_ids['Year'];
//            $wcProduct->set_category_ids($wcProductCats);
//
//            // Save Product
//            $wcProduct->save();
//
//            return $method;
//
//        } catch (Exception $e){
//            var_dump($product);
//            return $method = "Failed";
//        }
//    }
//
//    public function getLastRow(){
//        global $wpdb;
//
//        $table_name = $wpdb->prefix . BMHS_TABLE_NAME;
//
//        $count = (int) $wpdb->get_var("SELECT `import_count` FROM `{$table_name}` ORDER BY id DESC LIMIT 1");
//
//        return $count;
//    }
//
//    public function createRecord(){
//
//        global $wpdb;
//
//        $table_name = $wpdb->prefix . BMHS_TABLE_NAME;
//
//        $sql = $wpdb->insert($table_name, ["ID" => Null, "import_count" => 0], ["%d"]);
//
//        $wpdb->query($sql);
//
//        // get the inserted record id.
//        $id = $wpdb->insert_id;
//
//        return $id;
//    }
//
//    public function incrementCount($id, $target){
//        global $wpdb;
//
//        $table_name = $wpdb->prefix . BMHS_TABLE_NAME;
//        $col_name = $target."_count";
//        $count = $wpdb->get_var("SELECT `{$col_name}` FROM `{$table_name}` WHERE `id` = {$id}");
//        $newCount = (int) $count + 1 ;
//
//
//        $wpdb->update( $table_name, ["$col_name" => $newCount], ["id" => $id] , [$col_name => "%d"], ["%d"], ["%d"]);
//
//        return $count;
//    }
//
//    public function createCategoryPath($product){
//
//        /** Category
//         * ProductGroup => MAKE CATEGOPRY => Make =>> Model =>> Years =>> Product?
//         */
//
//        // Product Group
//        $productGroupID       = $this->findOrCreateTerm($product->ProductGroup)->term_id;
//        $productGroupChildren = $this->getTermChildrenOrCreate($productGroupID, "Make");
//
//        // Default Make Category
//        $standardMakeID       = $productGroupChildren->term_id;
//        $standardMakeChildren = $this->getTermChildrenOrCreate($standardMakeID, $product->Make);
//
//        // Actual Make Category
//        $actualMakeId = $standardMakeChildren->term_id;
//        $actualMakeChildren = $this->getTermChildrenOrCreate($actualMakeId, $product->Model);
//
//        // Model Category
//        $modelId = $actualMakeChildren->term_id;
//        $modelChildren = $this->getTermChildrenOrCreate($modelId, $product->Years);
//
//        // Years
//        $yearsId = $modelChildren->term_id;
//
//
//        $categoryIds = [
//            'Group'        => $productGroupID,
//            'Make'         => $standardMakeID,
//            'Actual Make'  => $actualMakeId,
//            'Model'        => $modelId,
//            'Year'         => $yearsId
//        ];
//
//        return $categoryIds;
//
//    }
//
//    public function getProductTermId($term){
//        $termID = get_term_by( "slug", slugify($term), "product_cat")->term_id;
//        return $termID;
//    }
//
//    public function findOrCreateTerm($term, $parentTermId = null){
//
//        $existingTerm = get_term_by( "slug", slugify($term), "product_cat");
//
//        if(!$existingTerm){
//            return $this->createTerm($term, $parentTermId);
//        }
//
//        return $existingTerm;
//    }
//
//    // Get the children of a given term
//    public function getTermChildrenOrCreate($parentID, $name){
//
//        $args = [
//            'taxonomy' => 'product_cat',
//            'hide_empty' => false,
//            'parent' => $parentID
//        ];
//
//
//        if($name) {
//            $args['name'] = $name;
//        }
//
//        $childCats = get_terms($args);
//
//
//
////
////        if($name === 'A.J.S'){
////            $args = [
////                'taxonomy' => 'product_cat',
////                'hide_empty' => false,
////                'parent' => $parentID
////            ];
////
////            $childCats = get_terms($args);
////        }
////
//        if(!$childCats){
//            return $this->createTerm($name, $parentID);
//        } else {
//            return $childCats[0];
//        }
//
//    }
//
//    public function createTerm($termName, $parentId){
//
//        $args = [
//            "slug" => slugify($termName)
//        ];
//
//        if($parentId){
//            $args["parent"] = $parentId;
//        }
//
//        $newTerm = wp_insert_term($termName, "product_cat", $args);
//        $newTermObj = get_term_by( "id", $newTerm["term_id"], "product_cat");
//        return $newTermObj;
//    }
//
//
//
//}
