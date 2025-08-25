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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Inter:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@500&display=swap" rel="stylesheet">
<style>
    body {
        padding-top: 55px;
        background: #f8fafc;
        font-family: 'Poppins', sans-serif;
    }

    .navbar {
        background: rgba(92, 157, 255, 0.95);
        backdrop-filter: blur(8px);
        padding: 6px 0;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .navbar-brand {
        font-family: 'Inter', sans-serif;
        font-weight: 700;
        font-size: 1.2rem;
        letter-spacing: 0.5px;
        color: #fff !important;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
    }

    .navbar-brand:hover {
        transform: scale(1.05);
        color: #ffd700 !important;
    }

    .navbar-logo {
        height: 26px;
        width: auto;
        transition: transform 0.3s ease
    }
    .navbar-brand:hover .navbar-logo {
        transform: rotate(5deg);
    }

    .navbar-nav {
        margin-left: auto;
    }

    .navbar-nav .nav-link {
        position: relative;
        color: rgba(255, 255, 255, 0.9) !important;
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: 0.3px;
        margin: 0 12px;
        padding: 4px 0;
        transition: all 0.2s ease;
    }

    .navbar-nav .nav-link::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -1px;
        width: 0;
        height: 1px;
        background: #fff;
        transition: width 0.25s ease;
    }

    .navbar-nav .nav-link:hover,
    .navbar-nav .nav-link.active {
        color: #fff !important;
    }

    .navbar-nav .nav-link:hover::after,
    .navbar-nav .nav-link.active::after {
        width: 100%;
    }

    .navbar-toggler {
        padding: 4px 8px;
        border: none;
    }

    .navbar-toggler:focus {
        box-shadow: none;
    }

    .nav-link i {
        font-size: 0.8rem;
    }

    @media (max-width: 991px) {
        .navbar {
            padding: 8px 0;
            background: rgba(37, 99, 235, 0.98);
        }

        .navbar-brand {
            font-size: 1.1rem;
        }

        .navbar-nav .nav-link {
            margin: 6px 0;
            font-size: 0.7rem;
            padding: 6px 0;
        }
        
        .navbar-collapse {
            padding: 10px 0;
        }
    }
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="home.php  ">
            <img src="images/logo.png" alt="Logo" class="navbar-logo">
            HRIS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Right Navigation -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="home.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="halaman-users-merge.php">Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#"><i class="fas fa-search"></i></a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
