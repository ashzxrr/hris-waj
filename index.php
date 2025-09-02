<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/includes/config.php';

$error = '';
$username = '';

if (isset($_SESSION['user_id'])) {
    header('Location: router.php?page=dash');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password harus diisi!';
    } else {
        $stmt = $mysqli->prepare("SELECT id, username, password, level, status FROM users_login WHERE username = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (isset($user['status']) && strtolower($user['status']) !== 'active') {
                    $error = 'Akun tidak aktif. Hubungi administrator.';
                } else {
                    $hashed = $user['password'];

                    if ((function_exists('password_verify') && password_verify($password, $hashed)) || $password === $hashed) {
                        $_SESSION['user_id'] = (int) $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['level'] = $user['level'];
                        $_SESSION['login_time'] = time();

                        header('Location: router.php?page=dash');
                        exit();
                    } else {
                        $error = 'Username atau password salah.';
                    }
                }
            } else {
                $error = 'Username tidak ditemukan.';
            }

            $stmt->close();
        } else {
            $error = 'Terjadi kesalahan database.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Absensi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
        }

        .form-control {
            padding: 0.75rem;
            font-size: 0.95rem;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            font-weight: 500;
            margin-top: 1rem;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #5a6fd6 0%, #6a438f 100%);
        }

        .alert {
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .demo-accounts {
            margin-top: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 0.85rem;
        }

        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.98);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
        }

        .loading-animation {
            width: 80px;
            height: 80px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite, scale 0.5s ease-in-out;
        }

        .loading-text {
            margin-top: 20px;
            font-size: 1.1rem;
            color: #667eea;
            font-weight: 500;
            letter-spacing: 0.5px;
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @keyframes scale {
            0% {
                transform: scale(0.5);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <!-- Add Loading Screen HTML -->
    <div class="loading-screen">
        <div class="loading-animation"></div>
        <div class="loading-text">Logging you in...</div>
    </div>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Login</h1>
            <p class="text-muted">Sistem Manajemen Absensi</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username"
                    value="<?= htmlspecialchars($username) ?>" required autocomplete="username">
                <div class="invalid-feedback">Harap masukkan username</div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required
                    autocomplete="current-password">
                <div class="invalid-feedback">Harap masukkan password</div>
            </div>

            <button type="submit" class="btn btn-primary btn-login">
                <i class="fas fa-sign-in-alt"></i> Masuk
            </button>
        </form>

        <div class="demo-accounts">
            <h6 class="text-muted mb-2">Demo Accounts:</h6>
            <p class="mb-1"><strong>Admin:</strong> admin / admin123</p>
            <p class="mb-0"><strong>Manager:</strong> manager / manager123</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation')
            const loadingScreen = document.querySelector('.loading-screen')

            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    } else {
                        // Show loading screen only if form is valid
                        loadingScreen.style.display = 'flex'

                        // Add some artificial delay for better UX (optional)
                        setTimeout(() => {
                            form.submit()
                        }, 800)
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>

</html>