<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_user_role('Admin');

header('Location: dashboard.php');
exit;
