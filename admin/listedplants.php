<?php
session_start();
include '../conn.php'; // Include your connection file

// Check if the admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: adminlogin.php');
    exit();
}

// Fetch the total number of users
$query = "SELECT COUNT(id) AS total_users FROM users";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$totalUsers = $row['total_users']; // Get the total number of users

// Fetch the total number of sellers
$querySellers = "SELECT COUNT(seller_id) AS total_sellers FROM sellers";
$resultSellers = mysqli_query($conn, $querySellers);
$rowSellers = mysqli_fetch_assoc($resultSellers);
$totalSellers = $rowSellers['total_sellers']; // Get the total number of sellers

// Fetch the total number of pending seller applicants
$queryPendingApplicants = "SELECT COUNT(applicantID) AS total_pending_applicants FROM seller_applicant WHERE status = 'pending'";
$resultPendingApplicants = mysqli_query($conn, $queryPendingApplicants);
$rowPendingApplicants = mysqli_fetch_assoc($resultPendingApplicants);
$totalPendingApplicants = $rowPendingApplicants['total_pending_applicants']; // Get the total number of pending applicants

// Fetch the total number of reported users
$queryReportedUsers = "SELECT COUNT(DISTINCT reported_user) AS total_reported_users FROM reports WHERE status = 'pending'";
$resultReportedUsers = mysqli_query($conn, $queryReportedUsers);
$rowReportedUsers = mysqli_fetch_assoc($resultReportedUsers);
$totalReportedUsers = $rowReportedUsers['total_reported_users']; // Get the total number of reported users

// Handle approve/reject actions for reports
if (isset($_POST['action_report'])) {
    $reportId = $_POST['reportID'];

    if ($_POST['action_report'] === 'approve') {
        // Approve: Archive the user before deleting
        $archiveUserQuery = "INSERT INTO reported_user_archive (user_id, firstname, lastname, email)
                              SELECT u.id, u.firstname, u.lastname, u.email 
                              FROM users u
                              JOIN reports r ON u.id = r.user_id
                              WHERE r.report_id = ?";
        $stmt = mysqli_prepare($conn, $archiveUserQuery);
        mysqli_stmt_bind_param($stmt, 'i', $reportId);
        mysqli_stmt_execute($stmt);

        // Delete the user account
        $deleteUserQuery = "DELETE FROM users WHERE id = (SELECT user_id FROM reports WHERE report_id = ?)";
        $stmt = mysqli_prepare($conn, $deleteUserQuery);
        mysqli_stmt_bind_param($stmt, 'i', $reportId);
        mysqli_stmt_execute($stmt);

        // Send email notification to the user about account deletion
        // First, get the user's email for the notification
        $emailQuery = "SELECT u.email FROM users u JOIN reports r ON u.id = r.user_id WHERE r.report_id = ?";
        $stmt = mysqli_prepare($conn, $emailQuery);
        mysqli_stmt_bind_param($stmt, 'i', $reportId);
        mysqli_stmt_execute($stmt);
        $resultEmail = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($resultEmail);
        $userEmail = $user['email'];

        // Prepare the email content
        $subject = "Account Deletion Notification";
        $message = "Dear user, your account has been deleted due to violation of our terms of service. If you have any questions, please contact support.";
        $headers = "From: support@plantbazaar.com"; // Change to your support email

        // Send email
        mail($userEmail, $subject, $message, $headers);

        // Update report status to approved
        $updateReport = "UPDATE reports SET status = 'approved' WHERE report_id = ?";
        $stmt = mysqli_prepare($conn, $updateReport);
        mysqli_stmt_bind_param($stmt, 'i', $reportId);
        mysqli_stmt_execute($stmt);

        echo "<script>
                Swal.fire('Success!', 'Report Approved and User Deleted!', 'success')
                    .then(() => location.reload());
              </script>";
    } elseif ($_POST['action_report'] === 'reject') {
        // Reject the report: Just delete the report entry from the reports table
        $deleteReport = "DELETE FROM reports WHERE reported_user_id = ?";
        $stmt = mysqli_prepare($conn, $deleteReport);
        mysqli_stmt_bind_param($stmt, 'i', $reportId);
        mysqli_stmt_execute($stmt);

        echo "<script>
                Swal.fire('Rejected', 'Report has been removed.', 'info')
                    .then(() => location.reload());
              </script>";
    }
}

// Fetch reports
$queryReports = "SELECT r.reported_user, r.id, u.firstname, u.lastname, u.email, r.report_reason, r.proof_img_1, r.proof_img_2, r.proof_img_3, r.proof_img_4, r.proof_img_5, r.proof_img_6
                 FROM reports r
                 JOIN users u ON r.id = u.id
                 WHERE r.status = 'pending'";
$resultReports = mysqli_query($conn, $queryReports);

// Fetch the total number of users, sellers, applicants, and reports (already fetched in previous steps)


// Fetch listed plants with listing_status = 1
$queryPlants = "SELECT plantname, img1, img2, img3, plantSize, plantcategories, details, region, province, city, barangay, street, price FROM product WHERE listing_status = 1";
$resultPlants = mysqli_query($conn, $queryPlants);

// Fetch the total number of listed plants
$totalListedPlantsQuery = "SELECT COUNT(*) AS total_listed_plants FROM product WHERE listing_status = 1";
$resultTotalListedPlants = mysqli_query($conn, $totalListedPlantsQuery);
$rowTotalListedPlants = mysqli_fetch_assoc($resultTotalListedPlants);
$totalListedPlants = $rowTotalListedPlants['total_listed_plants']; // Get the total number of listed plants

// Fetch listed plants with listing_status = 2 (sold plants)
$querySoldPlants = "SELECT plantname, img1, img2, img3, plantSize, plantcategories, details, region, province, city, barangay, street, price FROM product WHERE listing_status = 2";
$resultSoldPlants = mysqli_query($conn, $querySoldPlants);

// Fetch the total number of sold plants
$totalSoldPlantsQuery = "SELECT COUNT(*) AS total_sold_plants FROM product WHERE listing_status = 2";
$resultTotalSoldPlants = mysqli_query($conn, $totalSoldPlantsQuery);
$rowTotalSoldPlants = mysqli_fetch_assoc($resultTotalSoldPlants);
$totalSoldPlants = $rowTotalSoldPlants['total_sold_plants']; // Get the total number of sold plants
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* CSS (same as the previous one you provided) */
        body {
            font-family: 'Arial', sans-serif;
            background-image: url('https://www.transparenttextures.com/patterns/leaf.png');
            background-color: #e0f7fa;
            margin: 0;
            padding: 0;
            color: #333;
        }
        nav {
            background-color: #4C8C4A;
            color: white;
            padding: 10px;
            position: fixed;
            height: 100%;
            width: 200px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
        }
        nav h2 {
            color: white;
            margin: 0;
            padding: 10px 0;
            font-family: 'Georgia', serif;
        }
        nav ul {
            list-style-type: none;
            padding: 0;
        }
        nav li {
            margin: 10px 0;
        }
        nav li a {
            color: white;
            text-decoration: none;
            padding: 10px;
            display: block;
            border-radius: 5px;
            transition: background 0.3s;
        }
        nav li a:hover {
            background-color: limegreen;
        }
        .container {
            margin-left: 220px;
            padding: 20px;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-family: 'Georgia', serif;
        }
        .summary {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
        }
        .summary-box {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 30%;
            text-align: center;
            margin: 0 10px;
            margin-bottom: 20px;
        }
        .summary-box h2 {
            color: #4C8C4A;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #4C8C4A;
            color: white;
        }

        /* Modal CSS */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgb(0, 0, 0); /* Fallback color */
            background-color: rgba(0, 0, 0, 0.9); /* Black w/ opacity */
        }
        .modal-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
        }
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
        }
        .close:hover,
        .close:focus {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <nav>
        <h2>PlantBazaar</h2>
        <ul>
            <li><a class="active" href="admindashboard.php">Users</a></li>
            <li><a href="adminsellerinfo.php">Sellers</a></li>
            <li><a href="sellerapplicant.php">Seller Applicants</a></li>
            <li><a href="listedplants.php">Listed Plants</a></li>
            <li><a href="soldplants.php">Sold Plants</a></li>
            <li><a href="adminreports.php">Reports</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="header">
            <h1>Admin Dashboard</h1>

        </div>

        <div class="summary">
            <div class="summary-box">
                <h2>Total Users</h2>
                <p><strong><?php echo $totalUsers; ?></strong></p>
            </div>
            <div class="summary-box">
                <h2>Total Sellers</h2>
                <p><strong><?php echo $totalSellers; ?></strong></p>
            </div>
            <div class="summary-box">
                <h2>Total Applicants</h2>
                <p><strong><?php echo $totalPendingApplicants; ?></strong></p>
            </div>
        </div>

        <div class="summary">
            <div class="summary-box">
                <h2>Total Listed Plants</h2>
                <p><strong><?php echo $totalListedPlants ?></strong></p>
            </div>
            <div class="summary-box">
                <h2>Total Sold Plants</h2>
                <p><strong><?php echo $totalSoldPlants ?></strong></p>
            </div>
            <div class="summary-box">
                <h2>Total Reports</h2>
                <p><strong><?php ; ?></strong></p>
            </div>
        </div>

        <h2>Listed Plants</h2>
<?php 
$query = "
    SELECT p.*, u.email AS seller_email
    FROM product p
    INNER JOIN sellers s ON p.added_by = s.seller_id
    INNER JOIN users u ON s.user_id = u.id
    WHERE p.listing_status = 1

    
";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) > 0) {
    echo '<table>';
    echo '<tr>';
    echo '<th>Plant Name</th>';
    echo '<th>Category</th>';
    echo '<th>Size</th>';
    echo '<th>Price</th>';
    echo '<th>Region</th>';
    echo '<th>Province</th>';
    echo '<th>City</th>';
    echo '<th>Barangay</th>';
    echo '<th>Street</th>';
    echo '<th>Action</th>';
    echo '</tr>';
    while ($row = mysqli_fetch_assoc($result)) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['plantname']) . '</td>';
        echo '<td>' . htmlspecialchars($row['plantcategories']) . '</td>';
        echo '<td>' . htmlspecialchars($row['plantSize']) . '</td>';
        echo '<td>' . htmlspecialchars($row['price']) . '</td>';
        echo '<td>' . htmlspecialchars($row['region']) . '</td>';
        echo '<td>' . htmlspecialchars($row['province']) . '</td>';
        echo '<td>' . htmlspecialchars($row['city']) . '</td>';
        echo '<td>' . htmlspecialchars($row['barangay']) . '</td>';
        echo '<td>' . htmlspecialchars($row['street']) . '</td>';
        echo '<td><button onclick="viewMoreInfo(\'' . htmlspecialchars(json_encode($row)) . '\')">View More</button></td>';
        echo '</tr>';
    }
    echo '</table>';
} else {    
    echo '<p>No listed plants found.</p>';
}
?>
</div>

<script>
   function viewMoreInfo(plantData) {
    const plant = JSON.parse(plantData);
    const address = `${plant.region}, ${plant.province}, ${plant.city}, ${plant.barangay}, ${plant.street}`;
    
    // Initialize details as an empty string
    let details = `
        <h2>${plant.plantname}</h2>
        <p><strong>Size:</strong> ${plant.plantSize}</p>
        <p><strong>Category:</strong> ${plant.plantcategories}</p>
        <p><strong>Details:</strong> ${plant.details}</p>
        <p><strong>Price:</strong> $${plant.price}</p>
        <p><strong>Address:</strong> ${address}</p>
    `;

    // Loop through the images
    for (let i = 0; i <= 2; i++) { // Assuming you want to display img1, img2, img3 (indices 0, 1, 2)
        if (plant[`img${i + 1}`]) { // Check if the image exists
            details += `
                <img src="../Products/${plant.seller_email}/${plant[`img${i + 1}`]}" alt="${plant.plantname}" style="width:100px; margin-right: 10px;">
            `;
        }
    }

    // Show the SweetAlert modal
    Swal.fire({
        title: plant.plantname,
        html: details,
        showCloseButton: true,
        showCancelButton: false,
        focusConfirm: false,
    });
}

</script>

</html>

