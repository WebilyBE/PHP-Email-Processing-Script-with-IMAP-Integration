<?php
// Include configuration file
include 'config.php';

// Check for valid secret key
if (isset($_GET['secretKey']) && $_GET['secretKey'] == secret_key) {
    // Set default value if 'q' parameter is not specified or empty
    $q = isset($_GET['q']) && !empty($_GET['q']) ? $_GET['q'] : 'general';

    // Set IMAP username and password based on the 'q' parameter
    list($imap_username, $imap_password) = setImapCredentials($q);

    // Set IMAP mailbox and folder
    $mailbox = '{' . imap_mailbox . ':993/imap/ssl}INBOX';
    $imap_folder = isset($_GET['imap_folder']) && !empty($_GET['imap_folder']) ? $_GET['imap_folder'] : 'general';

    // Open IMAP connection
    $inbox = imap_open($mailbox, $imap_username, $imap_password);

    // Define greeting message function
    function getGreetingMessage() {
        $hour = date('H');
        if ($hour >= 04 && $hour < 11) {
            return "Good morning";
        } elseif ($hour == 11 || ($hour > 11 && $hour <= 17)) {
            return "Good afternoon";
        } elseif ($hour > 17 && $hour <= 23) {
            return "Good evening";
        } else {
            return "Good night";
        }
    }
    
    // Retrieve mail body data from JSON file
    $mailBodyData = file_get_contents('data.json');
    $mailBodyData = json_decode($mailBodyData, true);
    $mailBodyText = '';

    // Construct the mail body text
    if (!empty($mailBodyData[$imap_folder])) {
        // Iterate through the specific folder's mail body data
        foreach ($mailBodyData[$imap_folder] as $key => $line) {
            // Append each line to the mail body text
            $mailBodyText .= $line . "\n";
        }
    } else {
        // If the specific folder's data is empty, fallback to general data
        foreach ($mailBodyData['general'] as $key => $line) {
            // Append each line to the mail body text
            $mailBodyText .= $line . "\n";
        }
    }

    // Function to check if an IMAP folder exists; if not, create it
    function checkAndCreateImapFolder($inbox, $mailbox, $imap_folder) {
        $folders = imap_getmailboxes($inbox, $mailbox, '*');
        $imapFolderExists = false;
        // Loop through existing folders to check if the desired folder already exists
        foreach ($folders as $folder) {
            if ($folder->name == $mailbox . '.' . ucfirst(strtolower($imap_folder))) {
                $imapFolderExists = true;
                break;
            }
        }
        // If the desired folder is "general" and doesn't exist, create it
        if (!$imapFolderExists && $imap_folder == "general") {
            imap_createmailbox($inbox, $mailbox . '.Webily');
            imap_createmailbox($inbox, $mailbox . '.Sent.Autoresponders');
        }
        // If the desired folder is not "general" and doesn't exist, create it
        if (!$imapFolderExists && $imap_folder !== "general") {
            imap_createmailbox($inbox, $mailbox . '.' . ucfirst(strtolower($imap_folder)));
        }
    }
    
    // Check if an IMAP folder exists; if not, create it
    checkAndCreateImapFolder($inbox, $mailbox, $imap_folder);

    // Function to check if the 'logs' directory exists, if not, create it
    function checkAndCreateLogsDirectory() {
        if (!is_dir('logs')) {
            mkdir('logs');
        }
    }

    // Check if the 'logs' directory exists, if not, create it
    checkAndCreateLogsDirectory();
    
    // Clear IMAP spam folder if $spam_folder is "clear"
    $spam_folder = isset($_GET['spam']) && !empty($_GET['spam']) ? $_GET['spam'] : 'clear';
    function clearSpamFolder($inbox, $imap_username, $imap_password, $spam_folder) {
        if ($spam_folder == "clear") {
            // Open spam folder
            $spamMailbox = '{' . imap_mailbox . ':993/imap/ssl}INBOX.Spam';
            $spamInbox = imap_open($spamMailbox, $imap_username, $imap_password);

            // Delete all emails in spam folder
            $spamEmails = imap_search($spamInbox, 'ALL');
            if ($spamEmails) {
                foreach ($spamEmails as $spamNumber) {
                    imap_delete($spamInbox, $spamNumber);
                }
            }

            // Close spam folder
            imap_expunge($spamInbox);
            imap_close($spamInbox);

            // Log the result with date in spam.txt
            $logMessage = date('Y-m-d H:i:s') . " - Spam folder cleared, " . ucfirst(strtolower($q)) . "\n";
            file_put_contents('logs/spam.log', $logMessage, FILE_APPEND);
        }
    }

    // Clear IMAP spam folder if $spam_folder is "clear"
    clearSpamFolder($inbox, $imap_username, $imap_password, $spam_folder);
    
    $blockedEmailsFile = 'logs/blocked_emails.log';

    // Function to check if blocked_emails.log doesn't exist, then create it
    function checkAndCreateBlockedEmailsFile($blockedEmailsFile) {
        if (!file_exists($blockedEmailsFile)) {
            file_put_contents($blockedEmailsFile, '');
        }
    }

    // Check if blocked_emails.log doesn't exist, then create it
    checkAndCreateBlockedEmailsFile($blockedEmailsFile);

    // Read the list of blocked emails from the file
    $blockedEmails = file($blockedEmailsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
 
    // Function to search for unread emails based on keyword
    function searchForEmails($inbox, $keyword) {
        $searchCriteria = empty($keyword) ? 'UNSEEN ALL' : 'UNSEEN SUBJECT "' . $keyword . '"';
        return imap_search($inbox, $searchCriteria);
    }

    // Search for unread emails based on keyword
    $keyword = isset($_GET['keyword']) && !empty($_GET['keyword']) ? $_GET['keyword'] : '';
    $emails = searchForEmails($inbox, $keyword);

    // If emails are found
    if ($emails) {
        // Loop through each email
        foreach ($emails as $emailNumber) {
            // Get email header
            $header = imap_headerinfo($inbox, $emailNumber);
            $overview = imap_fetch_overview($inbox, $emailNumber, 0);
            $senderAddress = $header->from[0]->mailbox . "@" . $header->from[0]->host;
            $fromAddress = $header->fromaddress;
        
            // Extract only the name from the email address
            preg_match('/([^<]*)<([^>]*)>/', $fromAddress, $matches);
            $fromName = isset($matches[1]) ? trim($matches[1]) : $fromAddress;

            // Extract sender's name
            $fromNameParts = explode(" ", $fromName);
            if (empty($fromNameParts)) {
                $fromName = "Mailer";
            } else {
                $fromName = $fromNameParts[0];
                // Remove special characters
                $fromName = preg_replace('/[^a-zA-Z0-9\s.]/', '', $fromName);
                $fromNameParts = preg_replace('/[^a-zA-Z0-9\s.]/', '', $fromNameParts);

                // Check if the name contains 3 or more words
                if (count($fromNameParts) >= 3) {
                    // Display the full name
                    $fromName = implode(" ", array_map('ucfirst', array_map('strtolower', $fromNameParts)));
                } else {
                    // Only display the first word
                    $fromName = ucfirst(strtolower($fromNameParts[0]));
                }
                // Check if name length is longer than 20 characters
                if (strlen($fromName) > 30) {
                    $fromName = "Mailer";
                }
            }

            $subject = utf8_decode($header->subject);
            imap_clearflag_full($inbox, $emailNumber, "\\Seen");

            // Check if the email has already been processed
            $messageId = $overview[0]->uid;
            $processedFolder = "logs/processed_emails";
            if (!is_dir($processedFolder)) {
                mkdir($processedFolder, 0777, true);
            }
            if (file_exists("$processedFolder/$messageId.txt")) {
                continue; // Skip to the next email if already processed
            }

            // Check if sender has sent an email this month
            $currentYear = date('Y');
            $currentMonth = date('m');
            $uniqueCode = abs(crc32($header->fromaddress));
            $senderData = file_get_contents("logs/general/$uniqueCode/$currentYear/$currentMonth/data.txt");
            if (empty($senderData)) {
                // Sender hasn't sent an email this month, save sender data
                $senderFolder = "logs/general/$uniqueCode/$currentYear/$currentMonth";
                if (!is_dir($senderFolder)) {
                    mkdir($senderFolder, 0777, true);
                }
                file_put_contents("$senderFolder/data.txt", $header->fromaddress);
            } else {
                // Move email to specific folder if it's not 'general'
                if ($imap_folder !== "general" && strpos(strtolower($subject), $keyword) !== false) {
                    // Move email to specific folder and delete it from inbox
                    imap_mail_move($inbox, $emailNumber, 'INBOX.' . ucfirst(strtolower($imap_folder)));

                    // Add message ID to the list of processed emails
                    file_put_contents("$processedFolder/$messageId.txt", "Processed");

                    // Delete the email from the inbox
                    imap_delete($inbox, $emailNumber);

                    continue; // Move to the next email
                }
                continue; // Move to the next email
            }
            
            // Check if sender is blocked
            if (in_array($senderAddress, $blockedEmails)) {
                continue; // Skip to the next email if sender is blocked
            }

            // Set new subject and message for the auto-reply
            $newSubject = 'Autoreply: ' . $subject;

            // Read the content of email.html
            $emailContent = file_get_contents('email.html');

            // Replace placeholders with actual data
            $emailContent = str_replace('{appName}', app_name, $emailContent);
            $emailContent = str_replace('{fromName}', $fromName, $emailContent);
            $emailContent = str_replace('{senderAddress}', $senderAddress, $emailContent);
            $emailContent = str_replace('{getGreetingMessage}', getGreetingMessage(), $emailContent);
            $emailContent = str_replace('{mailBodyText}', $mailBodyText, $emailContent);

            $newBody = $emailContent;
            $toAddress = app_name . ' <' . $header->to[0]->mailbox . "@" . $header->to[0]->host . '>';

            // Set email headers
            $headers = [
                "MIME-Version: 1.0",
                "Content-type: text/html; charset=UTF-8",
                'From: ' . $toAddress
            ];

            // Set current time
            $currentTime = date('D, d M Y H:i:s O');

            // Send email and move to sent folder
            mail($header->fromaddress, $newSubject, $newBody, implode("\r\n", $headers));
            $outboxEmail = implode("\r\n", [
                "From: $toAddress",
                "To: $header->fromaddress",
                "Date: $currentTime",
                "Content-Type: text/html; charset=UTF-8",
                "Subject: $newSubject",
                "",
                $newBody
            ]);
            imap_append($inbox, "{" . imap_mailbox . ":993/imap/ssl}INBOX.Sent.Autoresponders", $outboxEmail);
            imap_clearflag_full($inbox, $emailNumber, "\\Seen");

            // Sleep until the next iteration
            if (($n + 1) < count($emails)) {
                sleep(1);
            }
        }
    } else {
        echo "There are no emails to process";
    }

    // Close IMAP connection
    imap_expunge($inbox); // Mark emails to be deleted
    imap_close($inbox);
} else {
    echo "Invalid secret key";
}
?>
