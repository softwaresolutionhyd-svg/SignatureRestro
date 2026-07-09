<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fixed LAN address for mobile / tablet on same WiFi
    |--------------------------------------------------------------------------
    |
    | PC par scripts/set-cafe-lan-ip.ps1 chalao taake yeh IP assign ho.
    |
    */

    'server_ip' => env('LAN_SERVER_IP', '192.168.3.50'),

    'server_url' => rtrim((string) env('LAN_SERVER_URL', 'http://192.168.3.50'), '/'),

];
