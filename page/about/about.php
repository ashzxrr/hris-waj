<?php
require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/header.php';
?>

<style>
    .about-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .about-header {
        text-align: center;
        margin-bottom: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 60px 20px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .about-header h1 {
        font-size: 2.5rem;
        margin-bottom: 15px;
        font-weight: 300;
    }

    .about-header p {
        font-size: 1.2rem;
        opacity: 0.9;
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.6;
    }

    .about-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 30px;
        margin-bottom: 40px;
    }

    .about-card {
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid #f0f0f0;
    }

    .about-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.12);
    }

    .card-icon {
        font-size: 2.5rem;
        margin-bottom: 20px;
        display: block;
        text-align: center;
    }

    .about-card h3 {
        color: #333;
        font-size: 1.4rem;
        margin-bottom: 15px;
        text-align: center;
        font-weight: 600;
    }

    .about-card p {
        color: #666;
        line-height: 1.6;
        text-align: center;
        margin-bottom: 0;
    }

    .features-section {
        background: #f8f9fa;
        padding: 50px 30px;
        border-radius: 15px;
        margin-bottom: 40px;
    }

    .features-title {
        text-align: center;
        color: #333;
        font-size: 2rem;
        margin-bottom: 30px;
        font-weight: 600;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .feature-item {
        background: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        transition: transform 0.2s ease;
    }

    .feature-item:hover {
        transform: scale(1.02);
    }

    .feature-icon {
        font-size: 2rem;
        margin-bottom: 10px;
        color: #667eea;
    }

    .feature-item h4 {
        color: #333;
        margin-bottom: 8px;
        font-size: 1.1rem;
    }

    .feature-item p {
        color: #666;
        font-size: 0.9rem;
        margin: 0;
    }

    .team-section {
        text-align: center;
        margin-bottom: 40px;
    }

    .team-title {
        color: #333;
        font-size: 2rem;
        margin-bottom: 30px;
        font-weight: 600;
    }

    .team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 30px;
        max-width: 800px;
        margin: 0 auto;
    }

    .team-member {
        background: white;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        transition: transform 0.3s ease;
    }

    .team-member:hover {
        transform: translateY(-3px);
    }

    .member-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        margin: 0 auto 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        font-weight: bold;
    }

    .member-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }

    .member-role {
        color: #666;
        font-size: 0.9rem;
    }

    .contact-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px 30px;
        border-radius: 15px;
        text-align: center;
    }

    .contact-title {
        font-size: 1.8rem;
        margin-bottom: 20px;
        font-weight: 600;
    }

    .contact-info {
        display: flex;
        justify-content: center;
        gap: 40px;
        flex-wrap: wrap;
        margin-top: 20px;
    }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .contact-item i {
        font-size: 1.2rem;
        opacity: 0.9;
    }

    @media (max-width: 768px) {
        .about-header h1 {
            font-size: 2rem;
        }
        
        .about-grid {
            grid-template-columns: 1fr;
        }
        
        .contact-info {
            flex-direction: column;
            gap: 15px;
        }
        
        .features-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="about-container">
    <!-- Header Section -->
    <div class="about-header">
        <h1>Tentang HRIS</h1>
        <p>Sistem Informasi Manajemen Sumber Daya Manusia yang modern, efisien, dan user-friendly untuk memudahkan pengelolaan karyawan di perusahaan Anda</p>
    </div>

    <!-- Main Info Cards -->
    <div class="about-grid">
        <div class="about-card">
            <div class="card-icon">🎯</div>
            <h3>Visi Kami</h3>
            <p>Menjadi solusi terdepan dalam sistem manajemen HR yang mengintegrasikan teknologi modern dengan kebutuhan bisnis untuk menciptakan lingkungan kerja yang produktif dan efisien.</p>
        </div>

        <div class="about-card">
            <div class="card-icon">🚀</div>
            <h3>Misi Kami</h3>
            <p>Menyediakan platform HRIS yang mudah digunakan, aman, dan dapat diandalkan untuk membantu perusahaan mengelola sumber daya manusia dengan lebih baik dan efektif.</p>
        </div>

        <div class="about-card">
            <div class="card-icon">⭐</div>
            <h3>Nilai Kami</h3>
            <p>Inovasi, Integritas, dan Kepuasan Pengguna. Kami berkomitmen untuk terus berinovasi dan memberikan layanan terbaik kepada setiap pengguna sistem kami.</p>
        </div>
    </div>

    <!-- Features Section -->
    <div class="features-section">
        <h2 class="features-title">Fitur Unggulan</h2>
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">👥</div>
                <h4>Manajemen Karyawan</h4>
                <p>Kelola data karyawan secara lengkap dan terintegrasi</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📊</div>
                <h4>Dashboard Analytics</h4>
                <p>Visualisasi data HR yang informatif dan real-time</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">⏰</div>
                <h4>Sistem Absensi</h4>
                <p>Monitoring kehadiran karyawan dengan teknologi modern</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💰</div>
                <h4>Penggajian</h4>
                <p>Sistem penggajian otomatis dan akurat</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📱</div>
                <h4>Mobile Friendly</h4>
                <p>Akses dari berbagai perangkat kapan saja</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🔒</div>
                <h4>Keamanan Tinggi</h4>
                <p>Proteksi data dengan standar keamanan terbaik</p>
            </div>
        </div>
    </div>

    <!-- Team Section -->
    <div class="team-section">
        <h2 class="team-title">Tim Pengembang</h2>
        <div class="team-grid">
            <div class="team-member">
                <div class="member-avatar">SH</div>
                <div class="member-name">Shohazar</div>
                <div class="member-role">Lead Developer</div>
            </div>
            <div class="team-member">
                <div class="member-avatar">HR</div>
                <div class="member-name">HR Team</div>
                <div class="member-role">Business Analyst</div>
            </div>
            <div class="team-member">
                <div class="member-avatar">UI</div>
                <div class="member-name">UI/UX Team</div>
                <div class="member-role">Designer</div>
            </div>
            <div class="team-member">
                <div class="member-avatar">QA</div>
                <div class="member-name">Quality Team</div>
                <div class="member-role">Quality Assurance</div>
            </div>
        </div>
    </div>

    <!-- Contact Section -->
    <div class="contact-section">
        <h2 class="contact-title">Hubungi Kami</h2>
        <p>Kami siap membantu Anda mengoptimalkan sistem HR perusahaan</p>
        <div class="contact-info">
            <div class="contact-item">
                <i>📧</i>
                <span>info@hris-company.com</span>
            </div>
            <div class="contact-item">
                <i>📞</i>
                <span>+62 812-3456-7890</span>
            </div>
            <div class="contact-item">
                <i>📍</i>
                <span>Jakarta, Indonesia</span>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>