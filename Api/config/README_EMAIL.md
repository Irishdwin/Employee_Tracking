# Email setup (Gmail) – verification code to applicant

To send the 4-digit verification code to the applicant’s Gmail:

## 1. Install Composer (one-time)

- Download: https://getcomposer.org/download/
- Run the Windows installer and follow the steps.
- Open a **new** Command Prompt or PowerShell.

## 2. Install PHPMailer (one-time)

In the project root (folder that contains `composer.json`):

```bash
cd c:\xampp\htdocs\Employee_Tracking
composer install
```

This creates a `vendor` folder with PHPMailer.

## 3. Gmail App Password

Gmail no longer allows your normal password for apps. Use an **App Password**:

1. Go to https://myaccount.google.com/
2. **Security** → **2-Step Verification** (turn it on if needed).
3. **Security** → **App passwords**.
4. Choose **Mail** and **Other (Custom name)** → e.g. “Onazul”.
5. Copy the **16-character password** (no spaces).

## 4. Configure this project

Edit:

**`Api/config/mail_config.php`**

Set:

- `smtp_user` = your Gmail address (e.g. `yourcompany@gmail.com`)
- `smtp_pass` = the 16-character App Password
- `from_email` = same Gmail address
- `from_name` = e.g. `Onazul Workforce Suite`

Save the file. The “Send code” button will then send the 4-digit code to the applicant’s Gmail.
