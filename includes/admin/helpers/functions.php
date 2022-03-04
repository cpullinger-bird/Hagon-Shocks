<?php

// Dump and die
function dd($item){
    echo "<pre style='background-color: lightgrey; padding: 20px'>";
    var_dump($item);
    echo "</pre>";
    die();
}

// Slugify a string
function slugify($text, string $divider = '-')
{
    // replace non letter or digits by divider
    $text = preg_replace('~[^\pL\d]+~u', $divider, $text);

    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    // trim
    $text = trim($text, $divider);

    // remove duplicate divider
    $text = preg_replace('~-+~', $divider, $text);

    // lowercase
    $text = strtolower($text);

    if (empty($text)) {
        return 'n-a';
    }

    return $text;
}


// Write line to log for debug
function writeLineToLog($msg, $fileName){
    // path of the log file where errors need to be logged
    $log_file = "/home/hagonshockseagle/public_html/sap/logs/" . $fileName . ".log";

    // logging error message to given log file
    error_log($msg . " \n ", 3, $log_file);
}