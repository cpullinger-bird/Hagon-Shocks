<?php

use Bird\Tools\AjaxHandler;


/*------ Init Import ------------------------*/
function bmhsInitImport() {
    $handler = new AjaxHandler();

    $response = $handler->initImport($_POST);

    echo json_encode($response);
    wp_die();
}


add_action( "wp_ajax_bmhs-init-import", "bmhsInitImport" );
add_action( "wp_ajax_nopriv_bmhs-init-import", "bmhsInitImport" );


/*------ Batch Import ------------------------*/
function bmhsBatchImport(){
    $handler = new AjaxHandler();

    $response = $handler->importBatch($_POST, $_POST["offset"]);

    echo json_encode($response);
    wp_die();
}


add_action( "wp_ajax_bmhs-batch-import", "bmhsBatchImport" );
add_action( "wp_ajax_nopriv_bmhs-batch-import", "bmhsBatchImport" );



/*------ Final Stats --------------------------*/
function bmhsFinalStats(){
    $handler = new AjaxHandler();

    $rowId = $_POST["rowId"];
    $status = $_POST["status"];
    $type = $_POST["type"];

    $response = $handler->getFinalCount($rowId, $status, $type);

    echo json_encode($response);
    wp_die();
}

add_action( "wp_ajax_bmhs-get-final-stats", "bmhsFinalStats" );
add_action( "wp_ajax_nopriv_bmhs-get-final-stats", "bmhsFinalStats" );
