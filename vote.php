<?php
header('Content-Type: application/json');
require_once 'config/db.php';
require_once 'config/mail.php';

// Load PHPMailer
require_once 'vendor/PHPMailer/src/Exception.php';
require_once 'vendor/PHPMailer/src/PHPMailer.php';
require_once 'vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$username_id = trim($_POST['username_id'] ?? '');
$voter_name = trim($_POST['voter_name'] ?? '');
$voter_email = trim($_POST['voter_email'] ?? '');
$age_category = trim($_POST['age_category'] ?? '');

// 1. Basic validation
if (empty($username_id) || empty($voter_email) || empty($voter_name)) {
    echo json_encode([
        'status' => 'error',
        'message' => "Please provide your name, email address, and select a participant. (Debug: username_id='$username_id', voter_name='$voter_name', voter_email='$voter_email')"
    ]);
    exit;
}

if (!filter_var($voter_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please enter a valid email address.'
    ]);
    exit;
}

try {
    // 2. Identify the participant and their age category across separate tables
    $participant = null;
    if (empty($age_category)) {
        foreach (['9-11', '12-14', '15-17'] as $cat) {
            $tbl = "participants_" . str_replace('-', '_', $cat);
            $stmt = $pdo->prepare("SELECT * FROM $tbl WHERE username_id = ?");
            $stmt->execute([$username_id]);
            $res = $stmt->fetch();
            if ($res) {
                $participant = $res;
                $age_category = $cat;
                break;
            }
        }
    } else {
        $tbl = "participants_" . str_replace('-', '_', $age_category);
        $stmt = $pdo->prepare("SELECT * FROM $tbl WHERE username_id = ?");
        $stmt->execute([$username_id]);
        $participant = $stmt->fetch();
    }

    if (!$participant) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Selected participant does not exist.'
        ]);
        exit;
    }

    // 3. Verify voter has not already voted on this specific item
    // "E-mail address can be used for other ID-items, but only once per ID-item."
    $stmt = $pdo->prepare("SELECT * FROM votes WHERE voter_email = ? AND username_id = ?");
    $stmt->execute([$voter_email, $username_id]);
    $existing_vote = $stmt->fetch();

    if ($existing_vote) {
        if ($existing_vote->is_confirmed == 1) {
            echo json_encode([
                'status' => 'error',
                'message' => 'This mails adres already voted on this item.'
            ]);
            exit;
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'A confirmation email was already sent to this address for this item. Please check your email to confirm.'
            ]);
            exit;
        }
    }

    // 4. Capture Voter IP
    $voter_ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $voter_ip = trim($ips[0]);
    }

    // 5. Generate email confirmation token
    $token = bin2hex(random_bytes(32));

    // 6. Insert vote into database as pending (is_confirmed = 0)
    $stmt = $pdo->prepare("INSERT INTO votes (username_id, age_category, voter_name, voter_email, voter_ip, is_confirmed, confirmation_token) VALUES (?, ?, ?, ?, ?, 0, ?)");
    $stmt->execute([$username_id, $age_category, $voter_name, $voter_email, $voter_ip, $token]);

    // 7. Dynamic confirmation link
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . $host . dirname($_SERVER['PHP_SELF']) . '/';
    $confirmation_link = $base_url . "vote-succes.php?token=" . $token;

    // 8. Construct beautiful Comic-style HTML Email
    $subject = "Confirm Your Vote - NK Strip Tekenwedstrijd";
    $to = $voter_email;
    
    $message = "
    <html>
    <head>
        <title>Confirm Your Vote</title>
        <style>
            body { font-family: 'Comic Sans MS', sans-serif; background-color: #f0f2f5; color: #1a1a1a; margin: 0; padding: 20px; }
            .card { background-color: #ffffff; border: 3px solid #1a1a1a; border-radius: 12px; max-width: 600px; margin: 0 auto; padding: 30px; box-shadow: 0px 8px 0px #1a1a1a; }
            .header { text-align: center; border-bottom: 3px solid #1a1a1a; padding-bottom: 15px; margin-bottom: 20px; }
            .logo { font-size: 2rem; font-weight: 900; text-transform: uppercase; color: #ff3b30; text-shadow: 2px 2px 0px #1a1a1a; }
            .btn { display: inline-block; background-color: #ffd60a; color: #1a1a1a; text-decoration: none; padding: 12px 30px; font-weight: bold; font-size: 1.1rem; border: 3px solid #1a1a1a; border-radius: 8px; box-shadow: 0px 4px 0px #1a1a1a; margin: 20px 0; }
            .btn:hover { background-color: #ffc300; }
            .footer { text-align: center; font-size: 0.8rem; color: #8e8e93; border-top: 2px solid #e5e5ea; padding-top: 15px; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class='card'>
            <div class='header'>
                <div class='logo'>NK Strip Tekenwedstrijd</div>
            </div>
            <h2>Hallo $voter_name,</h2>
            <p>Bedankt voor het uitbrengen van je stem op <strong>" . htmlspecialchars($participant->name) . "</strong> in de categorie <strong>" . htmlspecialchars($age_category) . " jaar</strong>!</p>
            <p>Om je stem definitief te bevestigen en mee te tellen in de live tussenstand, klik je op de onderstaande link:</p>
            <div style='text-align: center;'>
                <a href='$confirmation_link' class='btn'>Bevestig Mijn Stem &rarr;</a>
            </div>
            <p style='font-size: 0.9rem; color: #555;'>Mocht de knop niet werken, kopieer en plak dan de volgende link in je browser:</p>
            <p style='font-family: monospace; font-size: 0.85rem; word-break: break-all; background-color: #f8f9fa; padding: 10px; border: 1px dashed #ccc;'><a href='$confirmation_link'>$confirmation_link</a></p>
            <div class='footer'>
                &copy; " . date('Y') . " NK Strip Tekenwedstrijd. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";

    // 9. Send email using PHPMailer
    $mail = new PHPMailer(true);
    $mail_success = false;
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $voter_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        $mail_success = true;
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");
    }

    // 10. For local XAMPP testing, write details to a local text file so they can easily click it!
    $log_entry = "=====================================================================\n";
    $log_entry .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $log_entry .= "To: $voter_email ($voter_name)\n";
    $log_entry .= "Subject: Confirm your vote for " . $participant->name . " ($age_category)\n";
    $log_entry .= "Verification Link: $confirmation_link\n";
    $log_entry .= "=====================================================================\n\n";
    
    file_put_contents('sent_emails.txt', $log_entry, FILE_APPEND);

    echo json_encode([
        'status' => 'success',
        'message' => 'Thank you! We have sent a confirmation link to your email. Please click the link inside to confirm and validate your vote. ' . 
                     '(For offline local testing, we have also saved the verification link to \'sent_emails.txt\' inside the project folder!)'
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'A database error occurred: ' . $e->getMessage()
    ]);
    exit;
}
?>
