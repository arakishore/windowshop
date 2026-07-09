<?php

return [

    'admin_login_max_attempts' => (int) env('ADMIN_LOGIN_MAX_ATTEMPTS', 5),

    'admin_login_decay_seconds' => (int) env('ADMIN_LOGIN_DECAY_SECONDS', 60),

];
