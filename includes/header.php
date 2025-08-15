<?php
// includes/header.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Absensi Solution</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            padding-top: 70px; /* Biar konten tidak ketimpa navbar */
        }
       .navbar-brand img {
            height: 40px;
            margin-right: 10px;
            vertical-align: middle;
        }

        .navbar-dark .navbar-nav .nav-link {
            transition: color 0.2s ease-in-out;
        }
        .navbar-dark .navbar-nav .nav-link:hover {
            color: #ffc107;
        }
        .navbar-logo {
            height: 35px;
            margin-right: 8px;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
          <img src="images/logo.png" alt="Logo" class="navbar-logo" />
            HUMAN RESOURES INFORMATION SYSTEM

        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="home.php"><i class="fa-solid fa-house"></i> Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="user.php"><i class="fa-solid fa-users"></i> Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="about.php"><i class="fa-solid fa-circle-info"></i> About</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
