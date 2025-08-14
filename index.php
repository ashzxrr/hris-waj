<?php 
require __DIR__ . '/includes/header.php'; 
?>

<div class="container mt-5 pt-4">
    <div class="row justify-content-center">
        <!-- Card User -->
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="halaman-users-fix.php" class="text-decoration-none">
                <div class="card shadow-sm border-0 text-center p-4 hover-card">
                    <div class="card-body">
                        <i class="fa-solid fa-users fa-3x text-primary mb-3"></i>
                        <h5 class="card-title text-dark">Manajemen User</h5>
                        <p class="text-muted small mb-0">Lihat & kelola data pengguna</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<style>
    .hover-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .hover-card:hover {
        transform: translateY(-5px);
        box-shadow: 0px 8px 20px rgba(0,0,0,0.15);
    }
</style>

<?php 
require __DIR__ . '/includes/footer.php'; 
?>
