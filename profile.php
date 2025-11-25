<?php
session_start();
require 'db_config.php'; // Include the database connection script

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

// Fetch user data from the profiles table
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.username, p.email, p.name, p.address, p.profile_picture FROM users u JOIN profiles p ON u.user_id = p.user_id WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user) {
    // Display user information
    $username = htmlspecialchars($user['username']);
    $name = htmlspecialchars($user['name']);
    $email = htmlspecialchars($user['email']);
    $address = htmlspecialchars($user['address']);
    $profile_picture = htmlspecialchars($user['profile_picture']);
} else {
    $username = $name = $email = $address = $profile_picture = '';
    echo "<div class='error'>User not found.</div>";
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    // Validate inputs
    if (empty($name) || empty($email) || empty($address)) {
        echo "<div class='error'>All fields are required.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<div class='error'>Invalid email format.</div>";
    } else {
        // Update user profile information
        $stmt = $pdo->prepare("UPDATE profiles SET name = ?, email = ?, address = ? WHERE user_id = ?");
        $stmt->execute([$name, $email, $address, $user_id]);
        echo "<div class='success'>Profile updated successfully!</div>";
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if (password_verify($current_password, $user_data['password'])) {
        if ($new_password === $confirm_password && strlen($new_password) >= 8) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            echo "<div class='success'>Password changed successfully!</div>";
        } else {
            echo "<div class='error'>New passwords do not match or are too short (minimum 8 characters).</div>";
        }
    } else {
        echo "<div class='error'>Current password is incorrect.</div>";
    }
}

// Handle profile picture upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = $_FILES['profile_picture']['type'];
    $file_tmp = $_FILES['profile_picture']['tmp_name'];
    $file_name = basename($_FILES['profile_picture']['name']);
    $upload_dir = 'uploads/profile_pictures/'; // Ensure this directory is writable
    // Validate file type
    if (in_array($file_type, $allowed_types)) {
        $target_file = $upload_dir . uniqid() . '-' . $file_name;

        if (move_uploaded_file($file_tmp, $target_file)) {
            // Update the database with the new profile picture path
            $stmt = $pdo->prepare("UPDATE profiles SET profile_picture = ? WHERE user_id = ?");
            $stmt->execute([$target_file, $user_id]);
            echo "<div class='success'>Profile picture uploaded successfully!</div>";
        } else {
            echo "<div class='error'>Error uploading the file.</div>";
        }
    } else {
        echo "<div class='error'>Invalid file type. Only JPG, PNG, and GIF files are allowed.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link rel="stylesheet" href="styles.css"> <!-- Include your CSS file -->
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        color: #333;
        margin: 0;
        padding: 0;
    }

    header {
        background: #007BFF;
        color: white;
        padding: 10px 0;
        text-align: center;
    }

    main {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    h1,
    h2 {
        color: #007BFF;
    }

    form {
        margin-bottom: 20px;
    }

    label {
        display: block;
        margin: 10px 0 5px;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }

    input[type="file"] {
        margin: 10px 0;
    }

    button {
        background: #007BFF;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        transition: 0.3s;
    }

    button:hover {
        background: #0056b3;
    }

    .success {
        color: green;
        margin: 10px 0;
    }

    .error {
        color: red;
        margin: 10px 0;
    }

    footer {
        text-align: center;
        padding: 10px 0;
        background: #f4f4f4;
        margin-top: 20px;
        border-top: 1px solid #ccc;
    }
    </style>
</head>

<body>
    <header>
        <h1>User Profile</h1>
    </header>
    <main>
        <section>
            <h2>Profile Information</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <label for="username">Username:</label>
                <input type="text" name="username" value="<?php echo $username ?>" disabled>

                <label for="name">Name:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" required>

                <label for="email">Email:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>

                <label for="address">Address:</label>
                <textarea name="address" required><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></textarea>

                <label for="profile_picture">Profile Picture:</label>
                <input type="file" name="profile_picture" accept="image/*">

                <button type="submit" name="update_profile">Update Profile</button>
            </form>
        </section>

        <section>
            <h2>Change Password</h2>
            <form method="POST" action="">
                <label for="current_password">Current Password:</label>
                <input type="password" name="current_password" required>

                <label for="new_password">New Password:</label>
                <input type="password" name="new_password" required>

                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" name="confirm_password" required>

                <button type="submit" name="change_password">Change Password</button>
            </form>
        </section>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Your Company Name. All rights reserved.</p>
    </footer>
</body>

</html>
