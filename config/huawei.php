<?php

return [
    'onu_user' => env('HUAWEI_ONU_SSH_USER', 'root'),
    'onu_password' => env('HUAWEI_ONU_SSH_PASSWORD', 'admin'),
    'ssh_port' => (int) env('HUAWEI_ONU_SSH_PORT', 22),
    'telnet_port' => (int) env('HUAWEI_ONU_TELNET_PORT', 23),
    'timeout' => (int) env('HUAWEI_ONU_TIMEOUT', 20),
    'web_port' => (int) env('HUAWEI_ONU_WEB_PORT', 80),
    'web_timeout' => (int) env('HUAWEI_ONU_WEB_TIMEOUT', 15),
    'web_detect_timeout' => (int) env('HUAWEI_ONU_WEB_DETECT_TIMEOUT', 3),
    // Superadmin web: puede cambiar SSID, clave y reiniciar.
    'web_user' => env('HUAWEI_ONU_WEB_USER', 'telecomadmin'),
    'web_password' => env('HUAWEI_ONU_WEB_PASSWORD', 'admintelecom'),
];
