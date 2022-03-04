<?php

namespace Bird\Models;


class Ftp{
    public $conn;

    private $ip   = "212.139.91.86";
    private $user = "bird.marketing";
    private $pass = "GoldenFish418?";

    public function __construct(){
        $this->conn = ftp_connect($this->ip);
        $this->ftp_login($this->user, $this->pass);
    }

    public function __call($func,$a){
        if(strstr($func,'ftp_') !== false && function_exists($func)){
            array_unshift($a,$this->conn);
            return call_user_func_array($func,$a);
        }else{
            // replace with your own error handler.
            die("$func is not a valid FTP function");
        }
    }
}


