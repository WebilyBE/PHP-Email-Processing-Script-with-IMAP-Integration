<?php
// Define application name
define('app_name', 'My Application');

// SMTP Configuration
define('smtp_port', '465'); // SMTP port
define('smtp', 'example.com'); // SMTP server address

// IMAP Configuration
define('imap_mailbox', 'imap.example.com'); // IMAP server address
define('office_username', 'office@example.com'); // Office IMAP username
define('office_password', 'office_password123'); // Office IMAP password
define('general_username', 'general@example.com'); // General IMAP username
define('general_password', 'general_password456'); // General IMAP password

// Secret Key for Authentication
define('secret_key', 'your_secret_key_here');

// Set time limit and timezone
set_time_limit(0);
date_default_timezone_set('Europe/Brussels');

// Function to set IMAP username and password based on the 'q' parameter
function setImapCredentials($q) {
    switch ($q) {
        case 'office':
            return [office_username, office_password];
        default:
            return [general_username, general_password];
    }
}
?>
