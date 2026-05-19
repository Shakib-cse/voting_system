<?php
require_once 'config/db.php';

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and read text inputs
    $name = trim($_POST['name'] ?? '');
    $username_id = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($_POST['username_id'] ?? '')));
    $age_category = trim($_POST['age_category'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validate inputs
    if (empty($name) || empty($username_id) || empty($age_category) || empty($email)) {
        $error_msg = 'Please fill out all required text fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Please enter a valid email address.';
    } elseif (!in_array($age_category, ['9-11', '12-14', '15-17'])) {
        $error_msg = 'Please select a valid age category.';
    } elseif (empty($_FILES['page_1']['name'])) {
        $error_msg = 'Page 1 image is required!';
    } else {
        // Check if Username_ID is already taken in any category-specific table
        try {
            $is_taken = false;
            foreach (['9_11', '12_14', '15_17'] as $tbl_cat) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM participants_$tbl_cat WHERE username_id = ?");
                $stmt->execute([$username_id]);
                if ($stmt->fetchColumn() > 0) {
                    $is_taken = true;
                    break;
                }
            }
            if ($is_taken) {
                $error_msg = "The Username ID '<strong>$username_id</strong>' is already taken. Please choose another one.";
            }
        } catch (PDOException $e) {
            $error_msg = 'Database error: ' . $e->getMessage();
        }

        // Proceed if no errors
        if (empty($error_msg)) {
            // Target folder for image uploads
            $upload_dir = 'uploads/images/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $uploaded_files = ['page_1' => '', 'page_2' => null, 'page_3' => null];
            $upload_ok = true;

            // Loop to handle the three potential file uploads
            foreach (['page_1', 'page_2', 'page_3'] as $page_key) {
                if (empty($_FILES[$page_key]['name'])) {
                    continue;
                }

                $file_name = $_FILES[$page_key]['name'];
                $file_tmp = $_FILES[$page_key]['tmp_name'];
                $file_size = $_FILES[$page_key]['size'];
                
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed_extensions)) {
                    $error_msg = "File type for $page_key is not allowed. Only JPG, JPEG, PNG, and GIF files are accepted.";
                    $upload_ok = false;
                    break;
                }

                if ($file_size > $max_size) {
                    $error_msg = "File size for $page_key is too large! Maximum size allowed is 5MB.";
                    $upload_ok = false;
                    break;
                }

                // Generate secure and unique filename
                $new_filename = $username_id . '_' . $page_key . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $target_filepath = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $target_filepath)) {
                    $uploaded_files[$page_key] = $target_filepath;
                } else {
                    $error_msg = "Failed to upload image file for $page_key. Please check folder permissions.";
                    $upload_ok = false;
                    break;
                }
            }

            if ($upload_ok) {
                try {
                    $tbl = "participants_" . str_replace('-', '_', $age_category);
                    $stmt = $pdo->prepare("INSERT INTO $tbl (username_id, name, email, page_1, page_2, page_3) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $username_id,
                        $name,
                        $email,
                        $uploaded_files['page_1'],
                        $uploaded_files['page_2'],
                        $uploaded_files['page_3']
                    ]);
                    $success_msg = 'Registration successful! Your comic has been submitted and is ready for voting.';
                    
                    // Clear inputs for display
                    $name = $username_id = $age_category = $email = '';
                } catch (PDOException $e) {
                    $error_msg = 'Database insertion failed: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Comic - Online Voting System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <header>
            <a href="https://www.stripplaza.nl/">
            <img src="assets/images/header/logo.png" alt="Logo" width="150" height="120" class="header-logo header-logo primary">
        </a>
            <div>
                <a href="index.php" class="btn btn-secondary">
                    <!-- Home icon -->
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    Back to Voting
                </a>
            </div>
        </header>

        <!-- SUBMISSION FORM -->
        <div class="upload-wrapper">
            <h2 class="upload-title">Register &amp; Upload Comic</h2>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success">
                    <?= $success_msg ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger">
                    <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <form action="upload.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label" for="artist-name">Full Name</label>
                    <input type="text" id="artist-name" name="name" class="form-input" placeholder="e.g. Jane Doe" required value="<?= htmlspecialchars($name ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="username-id">Username ID (URL Identifier)</label>
                    <input type="text" id="username-id" name="username_id" class="form-input" placeholder="e.g. jane_doe" required value="<?= htmlspecialchars($username_id ?? '') ?>">
                    <span style="font-size:0.8rem; color:var(--text-muted);">Lowercase letters, numbers, underscores or hyphens only. Automatically filled.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="age-category">Age Category</label>
                    <select id="age-category" name="age_category" class="form-input" required style="background:#fff; height:50px;">
                        <option value="" disabled <?= empty($age_category) ? 'selected' : '' ?>>Select age category...</option>
                        <option value="9-11" <?= ($age_category ?? '') === '9-11' ? 'selected' : '' ?>>9-11 Years</option>
                        <option value="12-14" <?= ($age_category ?? '') === '12-14' ? 'selected' : '' ?>>12-14 Years</option>
                        <option value="15-17" <?= ($age_category ?? '') === '15-17' ? 'selected' : '' ?>>15-17 Years</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="artist-email">Email Address (Private)</label>
                    <input type="email" id="artist-email" name="email" class="form-input" placeholder="e.g. jane.doe@example.com" required value="<?= htmlspecialchars($email ?? '') ?>">
                    <span style="font-size:0.8rem; color:var(--text-muted);">This is only used for validation and contacting winners. It will never be shown to the public.</span>
                </div>

                <div class="form-group" style="margin-top:30px;">
                    <label class="form-label">Upload Comic Pages (Max size 5MB each. JPG, PNG, GIF)</label>
                    
                    <div class="file-upload-grid">
                        <!-- PAGE 1 (Required) -->
                        <div class="file-upload-box">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                            <span class="file-upload-label">Page 1</span>
                            <span class="file-upload-req">Required</span>
                            <input type="file" name="page_1" accept="image/*" required>
                            
                            <!-- Preview overlay -->
                            <div class="file-preview">
                                <img src="" alt="Preview 1">
                                <button class="remove-preview" onclick="removePreview(event, this)">&times;</button>
                            </div>
                        </div>

                        <!-- PAGE 2 (Optional) -->
                        <div class="file-upload-box">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                            <span class="file-upload-label">Page 2</span>
                            <span style="font-size:0.75rem; color:var(--text-muted);">Optional</span>
                            <input type="file" name="page_2" accept="image/*">

                            <!-- Preview overlay -->
                            <div class="file-preview">
                                <img src="" alt="Preview 2">
                                <button class="remove-preview" onclick="removePreview(event, this)">&times;</button>
                            </div>
                        </div>

                        <!-- PAGE 3 (Optional) -->
                        <div class="file-upload-box">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                            <span class="file-upload-label">Page 3</span>
                            <span style="font-size:0.75rem; color:var(--text-muted);">Optional</span>
                            <input type="file" name="page_3" accept="image/*">

                            <!-- Preview overlay -->
                            <div class="file-preview">
                                <img src="" alt="Preview 3">
                                <button class="remove-preview" onclick="removePreview(event, this)">&times;</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 40px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 15px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        Submit Comic Drawing
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script to auto-fill Username ID from Name -->
    <script>
        document.getElementById('artist-name').addEventListener('input', function() {
            const usernameInput = document.getElementById('username-id');
            // Auto fill if username ID is empty or was previously auto-filled
            const slug = this.value
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9\s_\-]/g, '') // Remove non-alphanumeric except space, underscore, hyphen
                .replace(/\s+/g, '_');          // Replace spaces with underscores
            usernameInput.value = slug;
        });
    </script>
    <script src="assets/script.js"></script>
</body>
</html>
