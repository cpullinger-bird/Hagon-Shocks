<?php

namespace Bird\Tools;

require_once('includes/admin/models/AdminArea.php');
require_once('includes/admin/models/Importer.php');
require_once('includes/admin/models/AjaxHandler.php');;
require_once('includes/admin/models/Ftp.php');;
require_once('includes/admin/helpers/functions.php');
require_once('includes/admin/helpers/hooks.php');


use Bird\Models\Ftp;
use Bird\Tools\AjaxHandler;
use Bird\Models\Admin;

//use Bird\Hooks\AjaxHandler;
class Bootstraps {

    public function __construct(){
        $this->ftp       = new Ftp();
        $this->importer  = new Importer($this->ftp);
        $this->adminArea = new Admin($this->importer);
        $this->hooks     = new AjaxHandler();

        $this->adminArea->setMenuPages();
        $this->adminArea->setAdminAssets();
    }
}