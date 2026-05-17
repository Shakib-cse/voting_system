<?php
require_once 'config/db.php';

$token = trim($_GET['token'] ?? '');
$status = 'error';
$message = 'Invalid confirmation token! The verification link is incorrect or has expired.';
$voter_name = '';
$participant_name = '';
$age_category = '';

if (!empty($token)) {
    try {
        // 1. Check if token exists in the database
        $stmt = $pdo->prepare("SELECT * FROM votes WHERE confirmation_token = ?");
        $stmt->execute([$token]);
        $vote = $stmt->fetch();

        if ($vote) {
            $voter_name = htmlspecialchars($vote->voter_name);
            $age_category = htmlspecialchars($vote->age_category);
            
            // Resolve participant name
            $tbl = "participants_" . str_replace('-', '_', $vote->age_category);
            $p_stmt = $pdo->prepare("SELECT name FROM $tbl WHERE username_id = ?");
            $p_stmt->execute([$vote->username_id]);
            $p_res = $p_stmt->fetch();
            $participant_name = $p_res ? htmlspecialchars($p_res->name) : 'the finalist';

            if ($vote->is_confirmed == 1) {
                $status = 'already_confirmed';
                $message = "Hey $voter_name, your vote for <strong>$participant_name</strong> has already been confirmed and counted previously!";
            } else {
                // 2. Confirm the vote in the database
                $update = $pdo->prepare("UPDATE votes SET is_confirmed = 1 WHERE confirmation_token = ?");
                $update->execute([$token]);
                
                $status = 'success';
                $message = "Hooray $voter_name! Your vote for <strong>$participant_name</strong> (Category: $age_category years) has been successfully confirmed and officially recorded!";
            }
        }
    } catch (PDOException $e) {
        $message = 'A database error occurred: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Confirmation - NK Strip Tekenwedstrijd</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .success-wrapper {
            max-width: 650px;
            margin: 60px auto;
            background: #ffffff;
            border: 3px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0px 10px 0px var(--border-color);
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .success-icon-container {
            width: 80px;
            height: 80px;
            margin: 0 auto 25px auto;
            border-radius: 50%;
            border: 3px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0px 4px 0px var(--border-color);
        }

        .status-success { background-color: #ffd60a; }
        .status-already { background-color: #52c41a; }
        .status-error { background-color: #ff4d4f; }

        .success-title {
            font-family: var(--font-heading);
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--text-main);
            text-transform: uppercase;
            margin-bottom: 20px;
            text-shadow: 2px 2px 0px rgba(0,0,0,0.05);
        }

        .success-text {
            font-size: 1.2rem;
            line-height: 1.8rem;
            color: var(--text-muted);
            margin-bottom: 35px;
            font-weight: 500;
        }

        .badge-confirmed {
            display: inline-block;
            background-color: #52c41a;
            color: #ffffff;
            border: 2px solid var(--border-color);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
            margin-bottom: 25px;
            box-shadow: 0px 3px 0px var(--border-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <header>
            <div class="logo-container">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="transform: rotate(-5deg); filter: drop-shadow(2px 3px 0px #1a1a1a);">
                    <circle cx="12" cy="12" r="10" fill="#ffeb3b" stroke="#1a1a1a" stroke-width="2.5"/>
                    <path d="M12 5L14.24 9.55L19.24 10.27L15.62 13.8L16.48 18.8L12 16.4L7.52 18.8L8.38 13.8L4.76 10.27L9.76 9.55L12 5Z" fill="#ff5722" stroke="#1a1a1a" stroke-width="1.5"/>
                </svg>
                <div>
                    <h1 class="logo-text"><a href="index.php">NK Strip</a></h1>
                    <span class="logo-sub">Tekenwedstrijd</span>
                </div>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary">
                    Go to Gallery
                </a>
            </div>
        </header>

        <!-- STATUS DISPLAY CARD -->
        <div class="success-wrapper">
            <?php if ($status === 'success'): ?>
                <div class="success-icon-container status-success">
                    <!-- Checkmark SVG -->
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1a1a1a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <h2 class="success-title">Vote Confirmed!</h2>
                <div class="badge-confirmed">OFFICIALLY COUNTED</div>
                <p class="success-text"><?= $message ?></p>

            <?php elseif ($status === 'already_confirmed'): ?>
                <div class="success-icon-container status-already">
                    <!-- Shield check SVG -->
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                </div>
                <h2 class="success-title">Already Confirmed!</h2>
                <div class="badge-confirmed" style="background:#096dd9;">ALREADY RECORDED</div>
                <p class="success-text"><?= $message ?></p>

            <?php else: ?>
                <div class="success-icon-container status-error">
                    <!-- X icon SVG -->
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </div>
                <h2 class="success-title" style="color:#d32f2f;">Verification Failed</h2>
                <p class="success-text" style="color:#cf1322; background:#fff1f0; padding:15px; border:2px solid #ffa39e; border-radius:12px; font-weight:600;"><?= $message ?></p>
            <?php endif; ?>

            <div style="display:flex; justify-content:center; gap:20px;">
                <a href="index.php" class="btn btn-primary" style="background:var(--text-main); font-weight:700;">
                    Return to Voting Page
                </a>
                <a href="leaderboard.php" class="btn btn-secondary" style="border-color:var(--accent-color); color:var(--accent-color); font-weight:700;">
                    View Live Rankings
                </a>
            </div>
        </div>

        <!-- FOOTER -->
        <footer>
            <p>&copy; <?= date('Y') ?> NK Strip Tekenwedstrijd. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
