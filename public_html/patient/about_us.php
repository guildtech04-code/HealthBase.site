<?php
require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['user']);
require '../config/db_connect.php';

// Get user info for sidebar
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result()->fetch_assoc();

$sidebar_user_data = [
    'username' => htmlspecialchars($user_result['username']),
    'email' => htmlspecialchars($user_result['email']),
    'role' => htmlspecialchars($user_result['role'])
];

// Get all active doctors
$doctors_query = $conn->query("
    SELECT id, first_name, last_name, specialization 
    FROM users 
    WHERE role='doctor' AND status='active' 
    ORDER BY specialization, last_name, first_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="../assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/patient_dashboard.css">
    <style>
        .about-us-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .about-hero {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 60px 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
        }
        
        .about-hero h1 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .about-hero p {
            font-size: 18px;
            opacity: 0.95;
            line-height: 1.6;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #3b82f6;
        }
        
        .hospital-info {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        .hospital-info p {
            color: #64748b;
            line-height: 1.8;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .accreditations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .accreditation-item {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .accreditation-item:hover {
            border-color: #3b82f6;
            transform: translateY(-5px);
        }
        
        .accreditation-item i {
            font-size: 40px;
            color: #3b82f6;
            margin-bottom: 10px;
        }
        
        .accreditation-item h4 {
            margin: 10px 0 5px 0;
            font-size: 16px;
            color: #1e293b;
            font-weight: 600;
        }
        
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }
        
        .doctor-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .doctor-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            border-color: #3b82f6;
        }
        
        .doctor-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .doctor-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: 700;
        }
        
        .doctor-info h3 {
            margin: 0 0 5px 0;
            font-size: 20px;
            color: #1e293b;
            font-weight: 700;
        }
        
        .doctor-info p {
            margin: 0;
            color: #3b82f6;
            font-weight: 600;
            font-size: 14px;
        }
        
        .doctor-details {
            margin-top: 15px;
        }
        
        .doctor-details p {
            color: #64748b;
            line-height: 1.6;
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .doctor-details i {
            color: #3b82f6;
            width: 20px;
        }
        
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }
        
        .value-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .value-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
        .value-card i {
            font-size: 40px;
            color: #3b82f6;
            margin-bottom: 15px;
        }
        
        .value-card h4 {
            margin: 0 0 10px 0;
            color: #1e293b;
            font-weight: 700;
            font-size: 20px;
        }
        
        .value-card p {
            color: #64748b;
            line-height: 1.6;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .about-hero h1 {
                font-size: 32px;
            }
            
            .about-hero p {
                font-size: 16px;
            }
            
            .doctors-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="patient-dashboard-page">
    <?php include 'includes/patient_sidebar.php'; ?>

    <div class="patient-main-content">
        <header class="patient-header">
            <div class="patient-header-left">
                <h1 class="patient-welcome">About HealthBase</h1>
                <p class="patient-subtitle">Learn about our medical center and healthcare providers</p>
            </div>
        </header>

        <div class="patient-dashboard-content">
            <div class="about-us-container">
                <!-- Hero Section -->
                <div class="about-hero">
                    <h1><i class="fas fa-hospital"></i> HealthBase</h1>
                    <p>A premier healthcare facility committed to delivering quality, compassionate, and personalized medical care</p>
                </div>

                <!-- Hospital Information -->
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-building"></i>
                        About Our Medical Center
                    </h2>
                    <div class="hospital-info">
                        <p>
                            HealthBase is a leading healthcare facility located in the heart of Makati's central business district. 
                            For over five decades, we have been committed to valuing lives and providing premium and personalized healthcare services 
                            that make us the premier hospital in the region.
                        </p>
                        <p>
                            With a 600-bed capacity, HealthBase delivers quality and compassionate services through our highly skilled, competent, 
                            and board-certified physicians, nurses, allied healthcare professionals, and management staff. Our medical center is 
                            equipped with modern facilities and state-of-the-art medical equipment and technology.
                        </p>
                        <p>
                            We are committed to delivering the best practices in healthcare, maintaining stringent and uncompromising safety standards, 
                            and providing excellent patient care through every interaction—from admission to discharge.
                        </p>
                    </div>
                </div>

                <!-- Accreditations -->
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-certificate"></i>
                        Accreditations & Certifications
                    </h2>
                    <div class="accreditations-grid">
                        <div class="accreditation-item">
                            <i class="fas fa-award"></i>
                            <h4>JCI Accredited</h4>
                            <p style="font-size: 13px; color: #64748b; margin: 0;">Joint Commission International</p>
                        </div>
                        <div class="accreditation-item">
                            <i class="fas fa-heart"></i>
                            <h4>Center of Excellence</h4>
                            <p style="font-size: 13px; color: #64748b; margin: 0;">DOJ & PhilHealth</p>
                        </div>
                        <div class="accreditation-item">
                            <i class="fas fa-baby"></i>
                            <h4>Mother-Baby Friendly</h4>
                            <p style="font-size: 13px; color: #64748b; margin: 0;">WHO & UNICEF</p>
                        </div>
                        <div class="accreditation-item">
                            <i class="fas fa-shield-alt"></i>
                            <h4>Patient Safety</h4>
                            <p style="font-size: 13px; color: #64748b; margin: 0;">Highest Standards</p>
                        </div>
                    </div>
                </div>

                <!-- Our Doctors -->
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-user-md"></i>
                        Our Healthcare Team
                    </h2>
                    <div class="doctors-grid">
                        <?php while ($doctor = $doctors_query->fetch_assoc()): 
                            $fullName = $doctor['first_name'] . ' ' . $doctor['last_name'];
                            $initials = strtoupper(substr($doctor['first_name'], 0, 1) . substr($doctor['last_name'], 0, 1));
                        ?>
                            <div class="doctor-card">
                                <div class="doctor-header">
                                    <div class="doctor-avatar"><?= $initials ?></div>
                                    <div class="doctor-info">
                                        <h3><?= htmlspecialchars($fullName) ?></h3>
                                        <p><?= htmlspecialchars($doctor['specialization']) ?></p>
                                    </div>
                                </div>
                                <div class="doctor-details">
                                    <?php if ($doctor['specialization'] === 'Orthopaedic Surgery'): ?>
                                        <p><i class="fas fa-graduation-cap"></i> <strong>Residency:</strong> Philippine Orthopedic Center</p>
                                        <p><i class="fas fa-certificate"></i> <strong>Fellowship:</strong> Hyogo College of Medicine, Japan & Toronto Hospital, University of Toronto</p>
                                        <p><i class="fas fa-award"></i> <strong>Board:</strong> Philippine Board of Orthopaedics</p>
                                    <?php elseif ($doctor['specialization'] === 'Dermatology'): ?>
                                        <p><i class="fas fa-graduation-cap"></i> <strong>Residency:</strong> Makati Medical Center</p>
                                        <p><i class="fas fa-certificate"></i> <strong>Fellowship:</strong> University of Miami Hospital</p>
                                        <p><i class="fas fa-award"></i> <strong>Board:</strong> Philippine Board of Dermatology</p>
                                    <?php elseif ($doctor['specialization'] === 'Gastroenterology'): ?>
                                        <p><i class="fas fa-graduation-cap"></i> <strong>Residency:</strong> Internal Medicine - Makati Medical Center</p>
                                        <p><i class="fas fa-certificate"></i> <strong>Fellowship:</strong> Gastroenterology - Training Program</p>
                                        <p><i class="fas fa-award"></i> <strong>Board:</strong> Philippine Board of Internal Medicine - Gastroenterology</p>
                                    <?php else: ?>
                                        <p><i class="fas fa-user-md"></i> <strong>Specialization:</strong> <?= htmlspecialchars($doctor['specialization']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Our Values -->
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-heart"></i>
                        Our Core Values
                    </h2>
                    <div class="values-grid">
                        <div class="value-card">
                            <i class="fas fa-user-md"></i>
                            <h4>Excellence in Care</h4>
                            <p>We maintain the highest standards in medical practice, continuously improving our services through innovation and the latest medical advancements.</p>
                        </div>
                        <div class="value-card">
                            <i class="fas fa-heart"></i>
                            <h4>Compassionate Service</h4>
                            <p>Every patient is treated with dignity, respect, and understanding. We provide personalized care that considers each patient's unique needs.</p>
                        </div>
                        <div class="value-card">
                            <i class="fas fa-shield-alt"></i>
                            <h4>Patient Safety</h4>
                            <p>Safety is our top priority. We maintain stringent safety protocols and continuously work to improve our systems to protect our patients.</p>
                        </div>
                        <div class="value-card">
                            <i class="fas fa-users"></i>
                            <h4>Team Collaboration</h4>
                            <p>Our multidisciplinary team works together seamlessly to ensure the best possible outcomes for our patients.</p>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-phone-alt"></i>
                        Contact Us
                    </h2>
                    <div class="hospital-info">
                        <p><i class="fas fa-phone" style="color: #3b82f6;"></i> <strong>Emergency Hotline:</strong> +632 8888 8999</p>
                        <p><i class="fas fa-envelope" style="color: #3b82f6;"></i> <strong>Email:</strong> info@healthbase.com.ph</p>
                        <p><i class="fas fa-map-marker-alt" style="color: #3b82f6;"></i> <strong>Address:</strong> Makati City, Philippines</p>
                        <p><i class="fas fa-clock" style="color: #3b82f6;"></i> <strong>24/7 OnCall Service:</strong> Available anytime for your healthcare needs</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/patient_sidebar.js"></script>
</body>
</html>

