<?php
// manage_users.php
session_start();
include 'db_connect.php';

// (Optional) check if admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Users
$result = $conn->query("SELECT id, first_name, last_name, email, status, gender FROM users");

// Count total users
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];

// Count total appointments
$totalAppointments = $conn->query("SELECT COUNT(*) as total FROM appointments")->fetch_assoc()['total'];

// Count male and female users
$genderData = $conn->query("SELECT gender, COUNT(*) as count FROM users GROUP BY gender");
$maleCount = 0;
$femaleCount = 0;
while ($row = $genderData->fetch_assoc()) {
    if (strtolower($row['gender']) === 'male') {
        $maleCount = $row['count'];
    } elseif (strtolower($row['gender']) === 'female') {
        $femaleCount = $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Users - HealthBase</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      height: 100vh;
      background-color: #f4f6f8;
    }
    /* Sidebar */
    .sidebar {
      width: 220px;
      background-color: #00695c;
      color: white;
      display: flex;
      flex-direction: column;
      padding: 20px;
      gap: 15px;
    }
    .sidebar h2 {
      font-size: 1.4rem;
      margin-bottom: 20px;
      text-align: center;
    }
    .sidebar a, .sidebar button {
      background-color: #004d40;
      border: none;
      color: white;
      padding: 10px;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none;
      text-align: center;
    }
    .sidebar a:hover {
      background-color: #00796b;
    }
    .sidebar button {
      background-color: #e53935;
    }
    .sidebar button:hover {
      background-color: #c62828;
    }
    /* Main content */
    .main {
      flex: 1;
      padding: 30px;
      overflow-y: auto;
    }
    h2 {
      color: #00695c;
      margin-bottom: 20px;
    }
    .stats {
      display: flex;
      gap: 20px;
      margin-bottom: 30px;
    }
    .stat-card {
      flex: 1;
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      text-align: center;
    }
    .stat-card h3 {
      margin: 10px 0;
      font-size: 1.2rem;
      color: #444;
    }
    .stat-card p {
      font-size: 2rem;
      font-weight: bold;
      color: #00695c;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    th, td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }
    th {
      background-color: #00695c;
      color: white;
    }
    tr:hover {
      background-color: #f1f1f1;
    }
    a.action-btn {
      padding: 6px 12px;
      border-radius: 5px;
      text-decoration: none;
      font-weight: bold;
      font-size: 0.9rem;
    }
    a.deactivate {
      background-color: #e53935;
      color: white;
    }
    a.deactivate:hover {
      background-color: #c62828;
    }
    a.activate {
      background-color: #43a047;
      color: white;
    }
    a.activate:hover {
      background-color: #2e7d32;
    }
    .chart-container {
      margin: 30px 0;
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      max-width: 500px;
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <h2>Admin Dashboard</h2>
    <a href="healthbase_dashboard.php"><i class="fas fa-home"></i> HealthBase Dashboard</a>
    <button onclick="confirmLogout()"><i class="fas fa-sign-out-alt"></i> Logout</button>
  </div>

  <!-- Main Content -->
  <div class="main">
    <h2>User Accounts</h2>

    <!-- Stats Cards -->
    <div class="stats">
      <div class="stat-card">
        <h3>Total Users</h3>
        <p><?= $totalUsers ?></p>
      </div>
      <div class="stat-card">
        <h3>Total Appointments</h3>
        <p><?= $totalAppointments ?></p>
      </div>
    </div>

    <!-- Gender Chart -->
    <div class="chart-container">
      <h3>Users by Gender</h3>
      <canvas id="genderChart"></canvas>
    </div>

    <!-- User Table -->
    <table>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
      <?php while($row = $result->fetch_assoc()) { ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></td>
          <td><?= htmlspecialchars($row['email']); ?></td>
          <td><?= ucfirst($row['status']); ?></td>
          <td>
            <?php if($row['status'] == 'active') { ?>
              <a href="toggle_user.php?id=<?= $row['id'] ?>&action=deactivate" class="action-btn deactivate">Deactivate</a>
            <?php } else { ?>
              <a href="toggle_user.php?id=<?= $row['id'] ?>&action=activate" class="action-btn activate">Activate</a>
            <?php } ?>
          </td>
        </tr>
      <?php } ?>
    </table>
  </div>

  <script>
    function confirmLogout() {
      if (confirm("Are you sure you want to log out?")) {
        window.location.href = "logout.php";
      }
    }

    // Gender Chart
    const ctx = document.getElementById('genderChart').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Male', 'Female'],
        datasets: [{
          data: [<?= $maleCount ?>, <?= $femaleCount ?>],
          backgroundColor: ['#42a5f5', '#ef5350']
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });
  </script>
</body>
</html>
