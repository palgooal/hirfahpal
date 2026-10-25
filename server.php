<?php

/*
 * Development router for "php artisan serve" (picked up from the project root by
 * ServeCommand). A copy of vendor/laravel/framework/src/Illuminate/Foundation/
 * resources/server.php with one change: the exact path /vendor goes to Laravel
 * (the vendor.entry route) instead of the public/vendor/ asset folder, whose
 * files are still served as static files.
 */

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if ($uri !== '/' && rtrim($uri, '/') !== '/vendor' && file_exists($publicPath.$uri)) {
    return false;
}

$formattedDateTime = date('D M j H:i:s Y');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
