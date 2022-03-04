<?php

use Bird\Tools\AjaxHandler;
use Bird\Tools\Importer;


/*------ Init Import ------------------------*/
function bmhsInitImport() {
    $handler = new AjaxHandler();

    $response = $handler->initImport($_POST);
    echo json_encode($response);
    wp_die();
}


add_action( "wp_ajax_bmhs-init-import", "bmhsInitImport" );
add_action( "wp_ajax_nopriv_bmhs-init-import", "bmhsInitImport" );


/*------ Start Import ------------------------*/
function bmhsStartImport(){
    $handler = new AjaxHandler();
    $response = $handler->importBatch($_POST, $_POST["offset"]);

    echo json_encode($response);
    wp_die();
}


add_action( "wp_ajax_bmhs-start-import", "bmhsStartImport" );
add_action( "wp_ajax_nopriv_bmhs-start-import", "bmhsStartImport" );

/*------ Check Import -----------------------*/
function bmhsCheckImport() {

    $importer = new Importer();

    $response = $importer->getLastRow();


    echo json_encode($response);
    wp_die();
}

add_action( "wp_ajax_bmhs-check-import", "bmhsCheckImport" );
add_action( "wp_ajax_nopriv_check-import", "bmhsCheckImport" );


