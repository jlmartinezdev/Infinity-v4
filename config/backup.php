<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ruta al ejecutable mysqldump
    |--------------------------------------------------------------------------
    |
    | En Windows con XAMPP suele ser: C:\xampp\mysql\bin\mysqldump.exe
    | Si está vacío, se usa "mysqldump" del PATH.
    |
    */

    'mysqldump_path' => env('MYSQLDUMP_PATH', ''),

];
