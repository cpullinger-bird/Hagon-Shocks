<?php

namespace Bird\Tools;

use WC_Product;
use Bird\Models\Ftp;

class Importer
{

    // Init obj
    public function __construct(
        Ftp $ftp
    )
    {
        $this->ftp = $ftp;

    }

    /*** FILE FUNCTIONS ***/
    // Get a list of all the Json files in the import folder
    public function getJsonFiles()
    {
        return array_diff(scandir(BMHS_IMPORT_FOLDER), ['.', '..']);
    }

    // Get the select option html from the files in the import folder
    public function getFileOptionsHtml($files = null)
    {
        if ($files > 0) {
            $optionsHtml = "<option disabled selected>Select a file</option>";
        } else {
            $optionsHtml = "<option disabled selected>You need to upload files via FTP to the <strpng>sap</strpng> folder</option>";
        }
        foreach ($files as $file) {
            $optionLabel = ucfirst(explode('.', $file)[0]);
            $optionsHtml .= "<option value='{$file}'>{$optionLabel}</option>";
        }
        return $optionsHtml;
    }

    // Pass back any errors when reading the Json file
    public function getJsonReadError()
    {

        switch (json_last_error()) {
            case JSON_ERROR_DEPTH:
                $error = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $error = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $error = 'Syntax error, malformed JSON';
                break;
        }

        if ($error) {
            return $this->throwError($error);
        }
    }

    // Throw error with HTML
    public function throwError($error)
    {
        $html = "
                <div class='bm-alert-message--error'>
                    <h3>Error Importing File</h3>
                    <p><b>Message:</b> {$error}</p>
                </div>
            ";
        return $html;
    }

    // Cache the file to read in batches later
    public function uploadAndCache($fileName, $type)
    {

        // Get file contents
        $fileContents = json_decode(preg_replace('/[[:^print:]]/', '', file_get_contents(BMHS_IMPORT_FOLDER . "/" . $fileName)));

        // Check if for products or prices
        switch ($type) {
            case("products"):
                $contents = $fileContents->Products;
                break;
            case("pricing"):
                $contents = $fileContents->PriceLists;
                break;
        }

        if ($contents === NULL) {
            $response["html"] = $this->throwError("There were no {$type} objects found on this file");
            $response["message"] = "Error reading json file";
            return $response;
        } else if (json_last_error()) {
            $response["html"] = $this->getJsonReadError();
            $response["message"] = "Error reading json file";
            return $response;
        } else {
            wp_cache_add("BMHS_products", $contents, "", 3600);
            $response["message"] = "Successfully read and cached";
            return $response;
        }
    }

    // Check cache for file if not upload file
    public function checkCache($fileName, $type)
    {
        if (wp_cache_get("BMHS_products") === false) {
            return $this->uploadAndCache($fileName, $type);
        } else {
            return "Cached";
        }
    }



    /*** PRODUCT FUNCTIONS ***/
    // Get the contents of the pass json file
    public function pluckProducts($fileName, $importType, $offset, $length = 75)
    {
        // Check to see if cached, if not this will cache
        $this->checkCache($fileName, $importType);

        // Get the all products and count
        $products = wp_cache_get("BMHS_products");
        $productCount = count($products);

        // Pluck products to be batch imported
        $pluckedProducts = array_slice($products, $offset * $length, $length, true);


        // Import Data
        $response["count"] = $productCount;
        $response["offset"] = $offset;
        $response["length"] = $length;
        $response["products"] = $pluckedProducts;

        if (count($pluckedProducts) === 0) {
            $response["message"] = "Importing Finished";
        } else {
            $response["message"] = "Importing";
        }

        return $response;
    }

    // Return a name using the properties of the product
    public function makeProductName($product){

        $name = "";

        $items = [
            $product->Description
        ];

        foreach($items as $item){
            $name .= $item;
            if($items[$i + 1]){
                $name .= " ";
            }
            $i++;
        }

        $name .= " - " . $product->ProductCode;

        return $name;


    }

    // Return a SKU using the properties of the product
    public function makeProductSKU($product){

        $sku = '';
        $i = 0;

        $items = [
            $product->ProductGroup,
            $product->Make,
            $product->Model,
            $product->Years,
            $product->CapacityCCM,
            $product->CapacityRange,
            $product->ModelNo,
            $product->Type,
            $product->Item
        ];

        foreach($items as $item){
            if($item === ''){
                return 'Failed';
            }
            $sku .= slugify($item);
            if($items[$i + 1]){
                $sku .= '-';
            }
            $i++;
        }
        return $sku;
    }

    // Search SAP image folder, download and assign if found
    public function getImage($product, $id)
    {
        $sapImgPath = "./images/";
        $sapImgDir = ($this->ftp->ftp_nlist($sapImgPath));


        $dir = wp_upload_dir()["path"] . "/"; // . "/sap-images/";


        if($product->ProductCode !== null && in_array($product->ProductCode . ".jpg", $sapImgDir)){
            $imgName = $product->ProductCode . '.jpg';
        } else if($product->PictureFieldName !== null && in_array($product->PictureFieldName, $sapImgDir)){
            $imgName = $product->PictureFieldName;
        }



        if($imgName !== null){

            $imgName = strtolower($imgName);
            $this->ftp->ftp_get($dir . $imgName, $sapImgPath . $imgName, FTP_BINARY);

            $wpFileType = wp_check_filetype($dir . $imgName, null);

            $attachment = [
                'post_mime_type' => $wpFileType['type'],  // file type
                'post_title' => sanitize_file_name($imgName),  // sanitize and use image name as file name
                'post_content' => '',  // could use the image description here as the content
                'post_status' => 'inherit'
            ];

            $attachment_id = wp_insert_attachment( $attachment, $imgName, $id );

            if ( ! is_wp_error( $attachment_id ) ) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                $attachment_data = wp_generate_attachment_metadata( $attachment_id, $imgName );
                wp_update_attachment_metadata( $attachment_id, $attachment_data );
                set_post_thumbnail( $id, $attachment_id );


            }
        } else {
            return;
        }
    }

    // Create or update a product from the passed object
    public function createOrUpdateProduct($product)
    {

        // Get the product SKU to see if it exists
        $wcProductSKU = $this->makeProductSKU($product);
        $productId = wc_get_product_id_by_sku($wcProductSKU);

        // Write SKU to product.log
        writeLineToLog($wcProductSKU, 'product');

        // If no SKU then return item as failed
        if($wcProductSKU === "Failed") {
            return "Failed";
        }

        if ($productId) {
            // If product was found, update the product
            $method = "Updated";

            // Get the woocommerce product
            $wcProduct = wc_get_product($productId);

            // Assign item code to product meta
            $this->assignProductCode($productId, $product->ProductCode);
            $this->assignAttributes($wcProduct->get_id(), $product);
        } else {
            // If no product was found, create the product
            $method = "Created";

            // Create a new WC product obj
            $wcProduct = new WC_Product();

            // Set the product name
            $wcProduct->set_name($this->makeProductName($product));

            // Set the product SKU
            $wcProduct->set_sku($wcProductSKU);

            // Get the category path (returns or creates IDs of each given cat)
            $category_ids = $this->createCategoryPath($product);

            // If there are no years for the product, return as failed
            // Years is the last stage in the category path
            if (!$category_ids["Years Value"] === 0) {
                return "Failed";
            }

            // Assign category
            $wcProductCats = [];
            $wcProductCats[] = $category_ids["Years Value"];
            $wcProduct->set_category_ids($wcProductCats);


            $wcProduct->set_short_description($product->Description);
            $wcProduct->set_description($product->Remarks);


            $wcProduct->set_manage_stock( true );
            $wcProduct->set_stock_quantity( $product->SalesVolume );
            $wcProduct->set_weight($product->SalesWeight);
            $wcProduct->set_height($product->SalesHeight);
            $wcProduct->set_length($product->SalesLength);
            $wcProduct->set_width($product->SalesWidth);


            // Save Product
            $wcProduct->save();

            // Assign the product code
            $this->assignProductCode($wcProduct->get_id(), $product->ProductCode);

            $this->getImage($product, $wcProduct->get_id());

            $this->assignAttributes($wcProduct->get_id(), $product);
            // Return the outcome
            return $method;
        }

        // Return the outcome
        return $method;
    }

    public function assignAttributes($wcProductId, $product){

        // List of all attributes
        $newAttrs = [
            "ModelNo"                   => "model-no",
            "Type"                      => "shock-type",
            "CapacityCCM"               => "capacity",
            "CapacityRange"             => "capacity-range",
            "CapacityRangeDescription"  => "capacity-description"
        ];

        // Loop through attributes
        foreach($newAttrs as $key => $attr) {
            $term = null;

            // Get term if exists
            $term = get_term_by("name", strval($product->$key), "pa_" . $attr);

            // Create term and get if doesnt exist
            if($term === false) {
                $term = wp_insert_term(strval($product->$key), "pa_" . $attr);
                $term = get_term_by("name", strval($product->$key), "pa_" . $attr);
            }

            // Apply the term to the produt
            wp_set_object_terms($wcProductId, $term->term_id, 'pa_' . $attr, true);

            // Build the term array
            $data = [
                'pa_' . $attr => [
                    'name' => 'pa_' . $attr,
                    'value' => $term->term_id,
                    'is_visible' => '1',
                    'is_taxonomy' => '1'
                ]
            ];

            // Get current product attribute data
            $_product_attributes = get_post_meta($wcProductId, '_product_attributes', TRUE);

            if(!$_product_attributes) {
                // Create if doesnt exist
                add_post_meta($wcProductId, '_product_attributes', $data);
            } else {
                // Update current data
                update_post_meta($wcProductId, '_product_attributes', array_merge($_product_attributes, $data));
            }
        }
    }

    // Search the product code and updates all item's prices
    public function updateProductPrice($priceItem): string
    {
        // If not retail price, skip
        if ($priceItem->PriceListNo !== 1) {
            return "N/a";
        }

        // Write item code to log file in case need to debug
        writeLineToLog($priceItem->ItemCode, 'prices');

        // Meta query args
        $args = [
            'meta_query' => [
                [
                    'key' => 'bmhs_product_code',
                    'value' => $priceItem->ItemCode
                ]
            ],
            'post_type' => 'product',
            'posts_per_page' => -1
        ];

        // Get prodcuts
        $products = get_posts($args);


        if($products){
            // If products found, loop through and update their prices
            foreach($products as $product){
                $wcProduct = wc_get_product( $product->ID );
                $wcProduct->set_regular_price($priceItem->ListPrice);
                $wcProduct->save();
            }
        } else {
            // If no product return failed
            return "N/a";
        }

        return "PriceSet";
    }



    /*** CATEGORY FUNCTIONS ***/
    // Returns the hierarchy path of the categories (IDs)
    public function createCategoryPath($product)
    {
        // Catagory Path
        // Step 1: Group

        if($product->ProductGroup !== NULL){
            $productGroupID = $this->findOrCreateTerm($product->ProductGroup)->term_id;
        }

        // Step 2: Make
        if($product->Make !== NULL) {
            //Step 2-1: Make Label
            $makeLabelID = $this->getTermChildrenOrCreate($productGroupID, "Make")->term_id;
            // Step 2-2: Make Value
            $makeValueID = $this->getTermChildrenOrCreate($makeLabelID, $product->Make)->term_id;
        }

        // Step 3: Model
        if($product->Model !== NULL) {
            // Step 3-1 Model Label
            $modelLabelID = $this->getTermChildrenOrCreate($makeValueID, "Model")->term_id;
            // Step 3-1 Model Value
            $modelValueID = $this->getTermChildrenOrCreate($modelLabelID, $product->Model)->term_id;
        }


        // Step 3: Years
        if($product->Years !== NULL) {
            // Step 4-1 Years Label
            $yearsLabelID = $this->getTermChildrenOrCreate($modelValueID, "Years")->term_id;

            // Step 4-2 Years Value
            $yearsValueID = $this->getTermChildrenOrCreate($yearsLabelID, $product->Years)->term_id;
        }

        $categoryIds = [
            "Group"        => ($productGroupID) ? $productGroupID : 0,
            "Make Label"   => ($makeLabelID) ? $makeLabelID : 0,
            "Make Value"   => ($makeValueID) ? $makeValueID : 0,
            "Model Label"  => ($modelLabelID) ? $modelLabelID : 0,
            "Model Value"  => ($modelValueID) ? $modelValueID : 0,
            "Years Label"  => ($yearsLabelID) ? $yearsLabelID : 0,
            "Years Value"  => ($yearsValueID) ? $yearsValueID : 0
        ];

        return $categoryIds;
    }

    // Finds or create the category term
    public function findOrCreateTerm($term, $parentTermId = null)
    {
        $existingTerm = get_term_by("slug", slugify($term), "product_cat");

        if (!$existingTerm) {
            return $this->createTerm($term, $parentTermId);
        }

        return $existingTerm;
    }

    // Get the children of a given term
    public function getTermChildrenOrCreate($parentID, $name)
    {
        $args = [
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => $parentID
        ];

        if ($name) {
            $args['name'] = $name;
        }

        $childCats = get_terms($args);

        if (!$childCats) {
            return $this->createTerm($name, $parentID);
        } else {
            return $childCats[0];
        }
    }

    // Creates the term
    public function createTerm($termName, $parentId)
    {
        $imgMap = [
            "A.J.S." => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/ajs3.png",
            "BMW" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/bmw3.png",
            "CCM" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/ccm3.png",
            "HONDA" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/honda3.png",
            "MOTO GUZZI" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/motoguzzicopy.png",
            "SUZUKI" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/suzuki3.png",
            "APRILIA" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/aprilia3.png",
            "BSA" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/bsa3.png",
            "DUCATI" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/ducati3.png",
            "INDIAN" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/indian3.png",
            "NORTON" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/norton3-.png",
            "TRIUMPH" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/triumph3.png",
            "BENELLI" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/benelli3.png",
            "CAGIVA" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/cagiva3.png",
            "HARLEY-DAVIDSON" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/harley3.png",
            "KAWASAKI" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/kawasaki3.png",
            "ROYAL ENFIELD" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/royalen3.png",
            "YAMAHA" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/yamaha3.png",
            "MORINI" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/moto-morini.jpg",
            "GILLET" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/gillet.jpg",
            "AMBASSADOR" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/ambassador3.png",
            "BULTACO" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/bultaco3.png",
            "CAN-AM" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/can-am3.png",
            "COTTON" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/cotton3.png",
            "DKW" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/DKW3.png",
            "FEATHERW'T" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/alouette-sm.png",
            "DMW" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/dmw.jpg",
            "BETA" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/beta.jpg",
            "MV AGUSTA" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/agusta.jpg",
            "ARIEL" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/ariel.jpg",
            "CCM/ARMSTRONG" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/armstrong.jpg",
            "BIMOTA" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/bimota.jpg",
            "BOND" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/bond.jpg",
            "BUELL" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/buell.jpg",
            "BUTLER" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/butler.jpg",
            "DALESMAN" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/dalesman.jpg",
            "DAYTON" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/dayton.jpg",
            "DOT" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/dot.jpg",
            "DOUGLAS" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/doug.jpg",
            "ECOMOBILE" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/ECOMOBILE.jpg",
            "EGLI" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/EGLI.jpg",
            "EXCELSIOR" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/EXCELSIOR.jpg",
            "EXPRESS" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/express.jpg",
            "FANTIC" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/fantic.jpg",
            "GILERA" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/gilera.jpg",
            "GREEVES" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/greeves.jpg",
            "HERCULESE" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/herc.jpg",
            "HESKETH" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/hesketh.jpg",
            "HOREX" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/horex.jpg",
            "HUSQVARNA" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/hus.jpg",
            "HYOSUNG" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/hyos.jpg",
            "JAMES" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/james.jpg",
            "JAWA/C.Z." => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/jawa.jpg",
            "KTM" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/ktm.jpg",
            "LAVERDA" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/laverda.jpg",
            "LEXMOTO" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/lexmoto.jpg",
            "MAICO" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/maico.jpg",
            "MARTIN" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/martin.jpg",
            "MATCHLESS" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/matchless.jpg",
            "MONARK" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/monark.jpg",
            "MOTO MORINI" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/moto-morini.jpg",
            "NOVY" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/novy.jpg",
            "NOXALL" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/noxal.jpg",
            "PANTHER" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/panther.jpg",
            "RUDGE" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/rudge.jpg",
            "SANGLAS" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/sanglas.jpg",
            "SAROLEA" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/sarolea.jpg",
            "SCOTT" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/scott.jpg",
            "SILK" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/silk.jpg",
            "SUN" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/sun.jpg",
            "SWM" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/swm.jpg",
            "TANDON" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/tandon.jpg",
            "URAL" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/ural.jpg",
            "VELOCETTE" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/velocette.jpg",
            "VICTORY" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/victory.jpg",
            "VOXAN" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/voxan.jpg",
            "WATSONIAN" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/watsonian.jpg",
            "WOOLER" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/wooler.jpg",
            "WP" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/wp.jpg",
            "ZUNDAPP" => "https://hagon-shocks.eagle.brd.ltd/wp-content/uploads/zundapp.jpg",
        ];

        $args = [
            "slug" => slugify($termName)
        ];

        if ($parentId) {
            $args["parent"] = $parentId;
        }

        $newTerm = wp_insert_term($termName, "product_cat", $args);
        $newTermObj = get_term_by("id", $newTerm["term_id"], "product_cat");

        if($termName !== "Make" && $termName !== "SHOCKS"){
            if($imgMap[$termName]) {
                $thumb_id = attachment_url_to_postid($imgMap[$termName]);
                update_term_meta($newTerm["term_id"], 'thumbnail_id', absint($thumb_id));
            }
        }

        return $newTermObj;
    }

    // Adds the product code meta to the product
    public function assignProductCode($productID, $productCode)
    {
        // Updating the Post Meta
        update_post_meta($productID, 'bmhs_product_code', $productCode);
    }



    /*** DB FUNCTIONS ***/
    // Return the import count from the DB
    public function getTotalImportCount($rowID)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . BMHS_TABLE_NAME;

        $count = $wpdb->get_var("SELECT SUM(import_count + update_count + failed_count) FROM `{$table_name}` WHERE id = {$rowID}");

        return $count;
    }

    // Create the row for the import
    public function createRecord()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . BMHS_TABLE_NAME;

        $sql = $wpdb->insert($table_name, ["ID" => Null, "import_count" => 0], ["%d"]);

        $wpdb->query($sql);

        // get the inserted record id.
        $id = $wpdb->insert_id;

        return $id;
    }

    // Increment the import method count
    public function incrementCount($id, $target)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . BMHS_TABLE_NAME;
        $col_name = $target . "_count";
        $count = $wpdb->get_var("SELECT `{$col_name}` FROM `{$table_name}` WHERE `id` = {$id}");
        $newCount = (int)$count + 1;


        $wpdb->update($table_name, ["$col_name" => $newCount], ["id" => $id], [$col_name => "%d"], ["%d"], ["%d"]);

        return $count;
    }

    // Get a col count (import_count, update_count, failed_count)
    public function getImportColCount($rowID, $col)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . BMHS_TABLE_NAME;

        $count = $wpdb->get_var("SELECT {$col} FROM `{$table_name}` WHERE id = {$rowID}");

        return $count;
    }


}


