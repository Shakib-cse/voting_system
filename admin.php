<?php
session_start();
if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    header('Location: admin/dashboard.php');
} else {
    header('Location: admin/login.php');
}
exit;
?>
