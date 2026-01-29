<?php
session_start();
require '../config/db.php';
$user_id = $_SESSION['user_id'];
$title = $_POST['title'];
$desc = $_POST['description'];

$pdo->prepare("INSERT INTO student_issues (sender_id, title, description) VALUES (?, ?, ?)")->execute([$user_id, $title, $desc]);
header("Location: dashboard.php?msg=Issue Submitted");
?>