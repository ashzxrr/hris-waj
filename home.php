<?php
require __DIR__ . '/includes/header.php';
?>

<style>
    .hover-card {
        transition: all 0.3s ease;
    }
    .hover-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }
</style>

<section class="py-5" style="margin-top: 60px;">
    <div class="container">
        <div class="row justify-content-start">

            <!-- Card Manajemen User -->
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

            <!-- Card Administrasi -->
            <div class="col-md-3 col-sm-6 mb-4">
                <a href="#" class="text-decoration-none">
                    <div class="card shadow-sm border-0 text-center p-4 hover-card">
                        <div class="card-body">
                            <i class="fa-solid fa-folder fa-3x text-success mb-3"></i>
                            <h5 class="card-title text-dark">Administrasi</h5>
                            <p class="text-muted small mb-0">Kelola dokumen & arsip</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Card Setting -->
            <div class="col-md-3 col-sm-6 mb-4">
                <a href="#" class="text-decoration-none">
                    <div class="card shadow-sm border-0 text-center p-4 hover-card">
                        <div class="card-body">
                            <i class="fa-solid fa-gear fa-3x text-secondary mb-3"></i>
                            <h5 class="card-title text-dark">Setting</h5>
                            <p class="text-muted small mb-0">Pengaturan sistem & preferensi</p>
                        </div>
                    </div>
                </a>
            </div>

        </div>
    </div>
</section>

<?php
require __DIR__ . '/includes/footer.php';
?>
