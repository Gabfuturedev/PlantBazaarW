<?php
include 'conn.php';
session_start();

// Start output buffering
ob_start();

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    header('Location: ../index.php');
    exit;
}

// Retrieve the user's data from the database
$email = $_SESSION['email'];
$query = "SELECT id, firstname, lastname, phonenumber, address, password, proflePicture FROM users WHERE email = '$email'";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    $userId = $user['id'];
    $firstname = $user['firstname'];
    $lastname = $user['lastname'];
    $phonenumber = $user['phonenumber'];
    $address = $user['address'];
    $password = $user['password'];
    $profilePicture = $user['proflePicture'];
} else {
    echo 'Error retrieving user data';
    exit;
}

$updated = false;

// Handle form submission
if (isset($_POST['submit'])) {
    $newFirstname = $_POST['firstname'];
    $newLastname = $_POST['lastname'];
    $newPhoneNumber = $_POST['phonenumber'];
    $newAddress = $_POST['address'];
    $newpassword = $_POST['password'];
    $hashed_password = password_hash($newpassword, PASSWORD_DEFAULT);

    $newprofilePicture = $profilePicture; // Default to current picture

    // Handle profile picture upload
    if (isset($_FILES['profile']) && $_FILES['profile']['error'] == 0) {
        $targetDir = "ProfilePictures/";
        $fileName = basename($_FILES['profile']['name']);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        // Allow only certain file formats
        $allowedTypes = array('jpg', 'jpeg', 'png', 'gif');
        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['profile']['tmp_name'], $targetFilePath)) {
                $newprofilePicture = $fileName;
            } else {
                echo "Error uploading profile picture.";
            }
        } else {
            echo "Only JPG, JPEG, PNG, and GIF files are allowed.";
        }
    }

    // Check if any data has changed
    if ($newFirstname != $firstname || $newLastname != $lastname || $newPhoneNumber != $phonenumber || $newAddress != $address || $hashed_password != $password || $newprofilePicture != $profilePicture) {
        // Update the user's data in the database
        $query = "UPDATE users SET firstname = ?, lastname = ?, phonenumber = ?, address = ?, password = ?, proflePicture = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssi", $newFirstname, $newLastname, $newPhoneNumber, $newAddress, $hashed_password, $newprofilePicture, $userId);

        if ($stmt->execute()) {
            // Set updated flag to true
            $updated = true;
        } else {
            echo "Error updating profile: " . $stmt->error;
        }
        $stmt->close();
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="editprofile.css">

    <!-- SweetAlert Library -->
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
</head>
<body>
<?php include 'nav.php'; ?>
   <div class="container">
   <form  id="profileForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
   <img id="profileImage" class="profile" src="ProfilePictures/<?php echo $profilePicture; ?>" alt="Profile Picture">
        <label for="profile">Profile Picture:</label>
        <input type="file" id="profile" name="profile"><br><br>
        <label for="firstname">Firstname:</label>
        <input type="text" id="firstname" name="firstname" value="<?php echo $firstname; ?>"><br><br>
        <label for="lastname">Lastname:</label>
        <input type="text" id="lastname" name="lastname" value="<?php echo $lastname; ?>"><br><br>
        <label for="phonenumber">Phone Number:</label>
        <input type="text" id="phonenumber" name="phonenumber" value="<?php echo $phonenumber; ?>"><br><br>
        <label for="address">Address:</label>
        <input type="text" id="address" name="address" value="<?php echo $address; ?>"><br><br>
        <label for="address">Password:</label>
        <input type="password" id="password" name="password" placeholder="Enter Password"><br><br>
        <input type="submit" name="submit" value="Update Profile">
        
    </form>
   </div>

    <!-- Check if the profile was updated, then trigger SweetAlert -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <?php if ($updated): ?>
        <script>
            swal({
                title: "Profile Updated!",
                text: "Your profile has been updated successfully",
                icon: "success",
                button: "Ok",
            }).then(function() {
                window.location.href = "editprofile.php";
            });
          

    $(document).ready(function () {
        $('#profileForm').on('submit', function (e) {
            e.preventDefault(); // Prevent default form submission

            var formData = new FormData(this);

            $.ajax({
                url: '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    // Check if the upload was successful
                    if (response.includes("success")) {
                        const imageUrl = 'ProfilePictures/' + response.split('|')[1] + '?' + new Date().getTime();
                        $('#profileImage').attr('src', imageUrl);
                        swal({
                            title: "Profile Updated!",
                            text: "Your profile has been updated successfully",
                            icon: "success",
                            button: "Ok",
                        });
                    } else {
                        swal("Error", response, "error");
                    }
                },
                error: function () {
                    swal("Error", "Error uploading profile picture.", "error");
                }
            });
        });
    });


        </script>
    <?php endif; ?>
</body>
</html>
