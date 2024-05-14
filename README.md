## PHP-Email Processing Script with IMAP Integration
This PHP script is designed to process emails based on specific criteria, such as moving emails to different folders, automatically responding to emails, and performing certain actions depending on the content of the email.

The script utilizes IMAP to connect to an email server, setting the IMAP username and password based on the 'q' parameter. It searches for unread emails with a specific keyword and moves them to a designated folder, such as 'invoices' or 'support'.

Example links for using different functions:

1. To search for emails with a specific keyword that are moved to the 'invoices' folder:
- `index.php?q=general&imap_folder=invoices&keyword={keyword}&secretKey={secretKey}`

2. To search for general emails without specific keywords:
- `index.php?q=general&secretKey={secretKey}`

3. To delete all emails in the 'spam' folder:
- `index.php?q=general&spam=clear&secretKey={secretKey}`

To handle these links with a cron job, you need to create a cron job that periodically calls these URLs. For example, you can set up a cron job to call these links every minute using curl:

```
* * * * * curl http://example.com/index.php?q=general&imap_folder=invoices&keyword={keyword}&secretKey={secretKey}
```

Make sure to replace the placeholders {keyword} and {secretKey} with the correct values and customize the URLs to fit your own environment.
