<?php

define('TITLE', 'Social App');

// server / database configuration
define('DB_USERNAME', 'ltm_30516816_rrgroup');
define('DB_PASSWORD', 'dhanbad12');
define('DB_HOST', 'sql210.ultimatefreehost.in');
define('DB_NAME', 'ltm_30516816');
define('HOST', '185.27.134.10');
define('PROFILE_HOST', 'http://185.27.134.10/socialapp-api/index.php/user/profilePhoto/');

// fcm configuration
define("FCM", "<fcm-id>");

// notification types
define('PUSH_TYPE_NOTIFICATION', 1);
define('PUSH_TYPE_REQUESTS', 2);
define('PUSH_TYPE_MESSAGE', 3);

// response/error codes
define('REQUEST_PASSED', 1);
define('REQUEST_FAILED', 2);
define('FCM_UPDATE_SUCCESSFUL', 3);
define('FCM_UPDATE_FAILED', 4);
define('USER_ALREADY_EXISTS', 5);
define('USER_INVALID', 6);
define('EMAIL_INVALID', 7);
define('UNKNOWN_ERROR', 404);
define('FAILED_MESSAGE_SEND', 8);
define('MESSAGE_SENT', 9);
define('PASSWORD_INCORRECT', 10);
define('ACCOUNT_DISABLED', 11);
define('SESSION_EXPIRED', 440);
define('EMAIL_ALREADY_EXISTS', 12);

?>
