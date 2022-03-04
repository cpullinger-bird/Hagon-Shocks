jQuery(function ($){
    let count = 0
    var closeAjax = true;



    $("bm-cancel-request").click(function(){
        closeAjax = true;
    });



    // Need to init import, return loading HTML and then start the import
    $("[name='bmhs-submit']").click(function(e){
        closeAjax = false;
        count = 0
        // Prevent the submit
        e.preventDefault();
        closeAllErrors();
        $('.bmhs-loader').empty();


        // Variables
        let fileSelect = jQuery('#bmhs-import-form select[name="bmhs-selected-file"]').val();
        let importType = jQuery('#bmhs-import-form select[name="bmhs-import-type"]').val();
        let importData = [];


        // Send a request to init the import in the DB
        $.ajax({
            type:"POST",
            // dataType:"json",
            url: "admin-ajax.php",
            data: {
                action: 'bmhs-init-import',
                file: fileSelect,
                type: importType
            },
            success: function(response) {

                // On success parse the data, load the loading html and start the batch imports
                importData = JSON.parse(response);

                if(importData["message"] === "Successfully read and cached"){

                    console.log(closeAjax, 'test');
                    if(closeAjax === false) {
                        jQuery(importData["html"]).insertBefore('form');
                        batchImport(importData["file"], importData["row_id"], 0, importType)
                    }
                } else{
                    throwErrorMessage(importData["message"]);
                }

                console.log('init request', importData);
            },error: function(){
                console.log('throwErrorMessage');
            }
        });
    })

    // Pass the file name, DB row ID and the offset
    function batchImport(file, rowId, offset, importType){

        return  $.ajax({
            type:"POST",
            url: "admin-ajax.php",
            data: {
                action: 'bmhs-batch-import',
                file: file,
                rowId: rowId,
                offset: offset,
                type: importType
            },success: function(response) {
                let importData = JSON.parse(response);
                console.log(importData);
                let totalFound = $(".count").text();
                let percentComplete = (importData["importCount"] / totalFound) * 100
                console.log(percentComplete);
                $("#myBar").css("width", percentComplete + "%");
                console.log(closeAjax, 'test');
                if(closeAjax === false) {
                    if(importData["message"] === "Importing"){
                        batchImport(importData["file"], importData["rowId"], parseInt(importData["offset"]) + 1, importType );
                    } else if (importData["message"] === "Error Importing" || importData["message"] === "Error reading json file") {
                        getFinalHtml( importData["rowId"], "Failed", importType);
                    } else if (importData["message"] === "Importing Finished") {
                        getFinalHtml( importData["rowId"], "Successful", importType);
                    }
                } else {
                    window.alert("Request has been cancelled")
                }
            },error: function(){
                throwErrorMessage("There was an error while importing the file. If this continues, contact Bird at support@birdmarketing.co.uk")
            }
        });
    }

    function closeAllErrors(){
        $('.bmhs-admin-area__error-msg').remove();
    }

    function throwErrorMessage(message){

        var html = "<div class='bmhs-admin-area__error-msg'>" + message + "<span>X</span></div>";

        $(html).insertAfter('.bmhs-admin-area__content');
        console.log(message);

    }


    function getFinalHtml(rowId, status, importType){
        $.ajax({
            type: "POST",
            url: "admin-ajax.php",
            data: {
                action: 'bmhs-get-final-stats',
                rowId: rowId,
                status: status,
                type: importType
            },
            success: function (response) {
                console.log(status);

                var html = JSON.parse(response);

                console.log(html);
                $('.bmhs-admin-area__content').empty()
                $('.bmhs-admin-area__content').append(html);

            }
        });
    }
});
