<?php

namespace Bird\Models;

use Bird\Tools\Importer;
use Bird\Tools\Ajaxhandler;

Class Admin
{

    // Init obj
    public function __construct(
        Importer $importer
    )
    {
        $this->importer = $importer;
    }


    // Enqueue the assets for the admin area
    public function setAdminAssets()
    {
        add_action("admin_enqueue_scripts", function ($hook) {

            if ($hook == "toplevel_page_hs-json-importer") {
                wp_enqueue_script("bm-loader", plugins_url("/bm-json-product-importer/public/js/loader.js", ['jQuery']));
            }
        });
    }

    // Set all pages for the admin view
    public function setMenuPages()
    {
        add_action('admin_menu', function () {
            add_menu_page("HS Json Importer", "HS Json Importer", 'manage_options', 'hs-json-importer', [$this, 'getDashboard'], 'dashicons-drumstick', '70');
            add_submenu_page(null, "HS Json Import Stats", "HS Json Import Stats", 'manage_options', 'hs-json-importer/import-stats', [$this, 'getImportPage']);
        });
    }

    // Get the main admin page
    public function getDashboard()
    {

        // TODO:
        // 1) Add in verification for user capabilities
        // 2) Nonce for the import form

        $options = $this->importer->getFileOptionsHtml($this->importer->getJsonFiles());

        $html = "
            <div class='bmhs-admin-area' style='margin: 35px 15px 2px !important;'>
                <h1>Hagon Shocks JSON Product Importer</h1>
                
                <div class='bmhs-admin-area__content'>
                    <p>After uploading files to the <strong>sap</strong> folder, use the form below to import the products</p>
                    
                    <form id='bmhs-import-form' action='' method='post'>
                        <select name='bmhs-selected-file' required>{$options}</select>
                        <select name='bmhs-import-type' required>
                            <option value=''>Select import type</option>
                            <option value='products'>Products</option>
                            <option value='pricing'>Pricing</option>
                        </select>
                        <input class='submit button-primary' name='bmhs-submit' type='submit'/>
                    </form>
                </div>
            </div>
        ";

        echo $html;
    }
}


/* Example import Object
object(stdClass)#15514 (24) {
  ["ProductCode"]=>
  string(8) "25503CC2"
  ["Description"]=>
  string(13) "VT125C SHADOW"
  ["ProductGroup"]=>
  string(6) "SHOCKS"
  ["ShippingType"]=>
  int(1)
  ["SalesVolume"]=>
  int(12331)
  ["PictureFieldName"]=>
  string(12) "25503CC2.jpg"
  ["ModelNo"]=>
  string(3) "778"
  ["Type"]=>
  string(1) "T"
  ["Make"]=>
  string(5) "HONDA"
  ["CapacityCCM"]=>
  int(125)
  ["CapacityRange"]=>
  int(1)
  ["CapacityRangeDescription"]=>
  string(11) "0 - 250 ccm"
  ["Model"]=>
  string(13) "VT125C SHADOW"
  ["YTo"]=>
  int(2009)
  ["YFrom"]=>
  int(1998)
  ["Years"]=>
  string(11) "1998 - 2009"
  ["Item"]=>
  string(8) "25503CC2"
  ["OperationDate"]=>
  NULL
  ["SentToWebDB"]=>
  NULL
}
ProductCode      => SKU?
Description      => Product Name
ProductGroup     => Category
ShippingType     =>
SalesLength      => Product Length
SalesWidth       => Product Width
SalesHeight      => Product Height
SalesVolume      => Inventory QTY
SalesWeight      => Product Weight
PictureFieldName => Img name
Remarks          => Description



"Make": "HONDA",
"Model": "VT125C SHADOW",
"Years": "1998 - 2009",

ProductGroup =>> Make =>> Model =>> Years =>> Product?


----- Attributes ------

ModelNo": "778",
"Type": "T",
"CapacityCCM": 125,
"CapacityRange": 1,
"CapacityRangeDescription": "0 - 250 ccm"

----- Unknown ------

"YTo": 2009,
"YFrom": 1998,
"Item": "25503CC2",
"OperationDate": null,
"SentToWebDB": null
*/