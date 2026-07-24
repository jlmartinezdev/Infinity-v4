<?php

return [
    'ssh_user' => env('UBNT_SSH_USER', 'ubnt'),
    'ssh_password' => env('UBNT_SSH_PASSWORD', 'mastercontrol'),
    'ssh_port' => (int) env('UBNT_SSH_PORT', 22),
    'connect_timeout' => (int) env('UBNT_SSH_TIMEOUT', 15),
    'command' => env('UBNT_WSTALIST_COMMAND', 'wstalist'),
    'dhcp_leases_command' => env('UBNT_DHCP_LEASES_COMMAND', 'cat /tmp/dhcpd.leases'),
];
