<?php
session_start();
require __DIR__ . '/includes/config.php';

// Proteksi: redirect ke login jika belum login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// sekarang boleh include header (yang berisi markup HTML)
require __DIR__ . '/includes/header.php';

// Query untuk mengambil data statistik
$stats = [
    'weekly_sales' => 0,
    'weekly_orders' => 0,
    'visitors_online' => 0
];

try {
    // Total Users/Karyawan
    $query = "SELECT COUNT(*) as total FROM users";
    $result = $mysqli->query($query);
    $total_users = $result->fetch_assoc()['total'];

    // Active Users
    $query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
    $result = $mysqli->query($query);
    $active_users = $result->fetch_assoc()['total'];

} catch(Exception $e) {
    error_log($e->getMessage());
}
?>
<style>
    .content-wrapper {
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 
            0 0 0 1px rgba(63, 63, 68, 0.05), 
            0 1px 3px 0 rgba(34, 33, 81, 0.15);
        padding: 2.5rem;
        margin-top: 40px;
        margin-bottom: 40px;
        width: 100%;
        position: relative;
        min-height: calc(100vh - 180px);
        background-image: 
            linear-gradient(rgba(255,255,255,.8) 2px, transparent 2px),
            linear-gradient(90deg, rgba(255,255,255,.8) 2px, transparent 2px),
            linear-gradient(rgba(0,0,0,.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(0,0,0,.05) 1px, transparent 1px);
        background-size: 100px 100px, 100px 100px, 20px 20px, 20px 20px;
        background-position: -2px -2px, -2px -2px, -1px -1px, -1px -1px;
    }

    .content-wrapper::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.6);
        backdrop-filter: blur(1px);
        border-radius: 8px;
        z-index: 0;
    }

    .row {
        position: relative;
        z-index: 1;
    }

    .card.hover-card {
        transition: all 0.3s ease;
        border: none;
        border-radius: 16px;
        overflow: hidden;
        background: transparent;
        position: relative;
        min-height: 120px;
    }

    /* Individual Card Colors with Bubble Effects */
    .card.hover-card:nth-of-type(2) {
        background: linear-gradient(135deg, #FF6B6B 0%, #ff8585 100%);
    }

    .card.hover-card:nth-of-type(1) {
        background: linear-gradient(135deg, #45caff 0%, #5c9dff 100%);
    }

    .card.hover-card:nth-of-type(3) {
        background: linear-gradient(135deg, #4ECDC4 0%, #45D394 100%);
    }

    /* Bubble Effects */
    .card.hover-card::before,
    .card.hover-card::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        pointer-events: none;
    }

    .card.hover-card::before {
        width: 100px;
        height: 100px;
        top: -30px;
        right: -30px;
    }

    .card.hover-card::after {
        width: 70px;
        height: 70px;
        bottom: -20px;
        left: -20px;
    }

    .hover-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }

    .card {
        padding: 1.5rem !important;
    }

    .card-body {
        padding: 0.8rem !important;
        position: relative;
        z-index: 1;
    }

    /* Value Text Styles */
    .value-text {
        font-size: 1.1rem;
        font-weight: 300;
        color: white;
        margin-bottom: 0.3rem;
        letter-spacing: -0.5px;
    }

    /* Label Text Styles */
    .label-text {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.9);
        font-weight: 500;
    }

    .card-content {
        text-align: left;
    }

    /* Responsive adjustments */
    .col-md-3 {
        max-width: 280px;
    }

    @media (max-width: 768px) {
        .col-md-3 {
            max-width: 100%;
        }
        .card {
            padding: 1.2rem !important;
        }
        .value-text {
            font-size: 1.5rem;
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

<section class="py-4">
    <div class="container">
        <div class="content-wrapper">
            <div class="row justify-content-start g-4">
                
                <!-- Card 1 -->
                <div class="col-md-3 col-sm-6 mb-4">
                    <a href="halaman-users-merge.php" class="text-decoration-none">
                    <div class="card hover-card">
                        <div class="card-body">
                            <i class="fa-solid fa-users card-icon"></i>
                            <div class="card-content">
                                <div class="value-text">Karyawan</div>
                                <div class="value-text"><?= number_format($total_users, 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                    </a>    
                </div>

                <!-- Card 2 -->
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card hover-card">
                        <div class="card-body">
                            <i class="fa-solid fa-folder fa-1x text-success"></i>
                            <div class="card-content">
                                <div class="value-text">Administrasi</div>
                                <div class="value-text">--</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card hover-card">
                        <div class="card-body">
                            <i class="fa-solid fa-gear fa-1x text-secondary"></i>
                            <div class="card-content">
                                <div class="value-text">Setting</div>
                                <div class="value-text">--</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>


<?php
require __DIR__ . '/includes/footer.php';
?>
