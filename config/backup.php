<?php

return [
    'disk' => env('BACKUP_DISK', 'backups'),
    'giu_ban' => (int) env('BACKUP_GIU_BAN', 7),
    'mysqldump_binary' => env('DB_DUMP_BINARY', 'mysqldump'),
    'timeout' => (int) env('BACKUP_TIMEOUT', 300),
];