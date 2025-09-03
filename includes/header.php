<?php
// includes/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
    <link rel="stylesheet" href="/assets/css/styles.css">
<style>
    body {
        padding-top: 55px;
        background: #f8fafc;
        font-family: 'Poppins', sans-serif;
    }

    .navbar {
        background: linear-gradient(135deg, #45caff 0%, #5c9dff 100%);
        backdrop-filter: blur(8px);
        padding: 4px 0;
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
        margin: 0 10px;
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
    /* Logout Button Styles */
    .btn-logout {
        background: linear-gradient(135deg, #FF6B6B 0%, #ff8585 100%);
        color: white !important;
        padding: 6px 16px !important; /* Reduced padding */
        border-radius: 20px; /* Slightly reduced radius */
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 0.75rem; /* Smaller font size */
        letter-spacing: 0.3px;
        margin-left: 12px; /* Adjusted spacing */
        box-shadow: 0 4px 15px rgba(255, 71, 87, 0.2);
    }

    .btn-logout:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 71, 87, 0.3);
    }

    .btn-logout::before,
    .btn-logout::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.15);
        width: 20px;
        height: 20px;
        transition: all 0.5s ease;
    }

    .btn-logout::before {
        top: -10px;
        left: -10px;
    }

    .btn-logout::after {
        bottom: -10px;
        right: -10px;
    }

    .btn-logout:hover::before {
        transform: scale(4);
    }

    .btn-logout:hover::after {
        transform: scale(4);
    }

    .btn-logout i {
        margin-right: 4px; /* Reduced icon spacing */
        font-size: 0.8rem; /* Smaller icon */
    }
    @media (max-width: 991px) {
        .btn-logout {
            margin: 8px 0;
            width: 100%;
            text-align: center;
            font-size: 0.75rem;
            padding: 8px 16px !important;
        }
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
            margin: 4px 0;
            font-size: 0.7rem;
            padding: 4px 0;
        }
        
        .navbar-collapse {
            padding: 10px 0;
        }
    }
    .page-loader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: #ffffff;
        z-index: 9999;
        opacity: 1;
        transition: opacity 0.5s ease-out;
    }
    
    .page-loader.fade-out {
        opacity: 0;
    }
</style>    
</head>
<body>
 <div class="page-loader"></div>
<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="?page=dash">
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
                    <a class="nav-link" href="?page=dash">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?page=users">Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?page=payroll">Payroll</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?page=">About</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a href="router.php?page=logout" class="nav-link btn-logout" 
                       onclick="return confirm('Apakah Anda yakin ingin keluar?')">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
<script>
    // Add this at the end of body
    document.addEventListener('DOMContentLoaded', function() {
        const loader = document.querySelector('.page-loader');
        if (loader) {
            loader.classList.add('fade-out');
            setTimeout(() => {
                loader.style.display = 'none';
            }, 500);
        }
    });
    </script>