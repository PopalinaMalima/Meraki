<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to edit your profile.";
    header("Location: login.php");
    exit();
}

$logged_in_user_id = $_SESSION['user_id'];
$message = '';

$userData = [
    'user_id' => $logged_in_user_id,
    'username' => '',
    'bio' => '',
    'avatar_blob' => null,
    'avatar_type' => null,
];

$sql_fetch_user = "SELECT username, bio, avatar_blob, avatar_type FROM users WHERE user_id = ?";
$stmt_fetch_user = $conn->prepare($sql_fetch_user);

if ($stmt_fetch_user) {
    $stmt_fetch_user->bind_param("i", $logged_in_user_id);
    $stmt_fetch_user->execute();    
    $result_fetch_user = $stmt_fetch_user->get_result();

    if ($row_fetch_user = $result_fetch_user->fetch_assoc()) {
        $userData['username'] = htmlspecialchars($row_fetch_user['username']);
        $userData['bio'] = htmlspecialchars($row_fetch_user['bio']);
        $userData['avatar_blob'] = $row_fetch_user['avatar_blob'];
        $userData['avatar_type'] = $row_fetch_user['avatar_type'];
    } else {
        $_SESSION['error_message'] = "Error: User not found.";
        header("Location: logout.php");
        exit();
    }
} else {
    $message = "Error preparing query to fetch user data: " . $conn->error;
    error_log($message);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') { //trimitere
    $new_username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
    $new_bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($new_username)) {
        $message = "Username cannot be empty.";
    } else {
        $update_query_parts = [];
        $bind_types = '';
        $bind_params = [];

        if ($new_username !== $userData['username']) {
            $sql_check_username = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
            $stmt_check_username = $conn->prepare($sql_check_username);
            if ($stmt_check_username) {
                $stmt_check_username->bind_param("si", $new_username, $logged_in_user_id);
                $stmt_check_username->execute();
                $stmt_check_username->store_result();
                if ($stmt_check_username->num_rows > 0) {
                    $message = "This username is already taken.";
                }
                $stmt_check_username->close();
            } else {
                $message = "Error checking username: " . $conn->error;
            }
        }

        if (empty($message)) {
            if ($new_username !== $userData['username']) {
                $update_query_parts[] = "username = ?";
                $bind_types .= 's';
                $bind_params[] = &$new_username;
            }

            if ($new_bio !== $userData['bio']) {
                $update_query_parts[] = "bio = ?";
                $bind_types .= 's';
                $bind_params[] = &$new_bio;
            }

            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_file_size = 5 * 1024 * 1024;

                $avatar_tmp_name = $_FILES['avatar']['tmp_name'];
                $avatar_type = mime_content_type($avatar_tmp_name);
                $avatar_size = $_FILES['avatar']['size'];

                if (!in_array($avatar_type, $allowed_types)) {
                    $message = "Avatar file type not allowed. Only JPEG, PNG, GIF are accepted.";
                } elseif ($avatar_size > $max_file_size) {
                    $message = "Avatar file size exceeds the 5MB limit.";
                } else {
                    $avatar_blob_content = file_get_contents($avatar_tmp_name);
                    if ($avatar_blob_content === false) {
                        $message = "Error reading avatar file.";
                    } else {
                        $update_query_parts[] = "avatar_blob = ?";
                        $update_query_parts[] = "avatar_type = ?";
                        $bind_types .= 'bs';
                        $bind_params[] = &$avatar_blob_content;
                        $bind_params[] = &$avatar_type;
                    }
                }
            }

            if (!empty($update_query_parts) && empty($message)) {
                $sql_update_user = "UPDATE users SET " . implode(', ', $update_query_parts) . " WHERE user_id = ?";
                $stmt_update_user = $conn->prepare($sql_update_user);

                if ($stmt_update_user) {
                    $types = $bind_types . 'i';
                    $bind_params[] = &$logged_in_user_id;

                    $stmt_update_user->bind_param($types, ...$bind_params);

                    if (isset($avatar_blob_content)) {
                        $stmt_update_user->send_long_data(array_search($avatar_blob_content, $bind_params), $avatar_blob_content);
                    }

                    if ($stmt_update_user->execute()) {
                        $message = "Profile updated successfully!";

                        if (isset($stmt_fetch_user) && $stmt_fetch_user) {
                            $stmt_fetch_user->close();
                        }
                        $stmt_fetch_user = $conn->prepare($sql_fetch_user);
                        if ($stmt_fetch_user) {
                            $stmt_fetch_user->bind_param("i", $logged_in_user_id);
                            $stmt_fetch_user->execute();
                            $result_fetch_user = $stmt_fetch_user->get_result();
                            if ($row_fetch_user = $result_fetch_user->fetch_assoc()) {
                                $userData['username'] = htmlspecialchars($row_fetch_user['username']);
                                $userData['bio'] = htmlspecialchars($row_fetch_user['bio']);
                                $userData['avatar_blob'] = $row_fetch_user['avatar_blob'];
                                $userData['avatar_type'] = $row_fetch_user['avatar_type'];
                            }
                        }
                    } else {
                        $message = "Error updating profile: " . $stmt_update_user->error;
                        error_log("Error updating profile: " . $stmt_update_user->error);
                    }
                    $stmt_update_user->close();
                } else {
                    $message = "Error preparing update query: " . $conn->error;
                    error_log("Error preparing update query: " . $conn->error);
                }
            } elseif (empty($update_query_parts) && empty($message)) {
                $message = "No changes were made.";
            }
        }
    }
}

if (isset($stmt_fetch_user) && $stmt_fetch_user) {
    $stmt_fetch_user->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Edit your profile</title>
    <link rel="stylesheet" href="./styles/edit_profile.css">
</head>

<body>
    <div class="container">
        <h1>Edit your profile</h1>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo (stripos($message, 'success') !== false) ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div>
            <?php if ($userData['avatar_blob'] && $userData['avatar_type']): ?>
                <img src="data:<?php echo $userData['avatar_type']; ?>;base64,<?php echo base64_encode($userData['avatar_blob']); ?>" alt="Avatar" class="avatar-preview" />
            <?php else: ?>
                <img src="https://placehold.co/120x120/cccccc/333333?text=No+Avatar" alt="No Avatar" class="avatar-preview" />
            <?php endif; ?>
            <p class="username-display"><?php echo $userData['username']; ?></p>
        </div>

        <form action="edit_profile.php" method="POST" enctype="multipart/form-data">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo $userData['username']; ?>" required />
            </div>

            <div>
                <label for="bio">Bio:</label>
                <textarea id="bio" name="bio" rows="4"><?php echo $userData['bio']; ?></textarea>
            </div>

            <div>
                <label for="avatar">Upload a new profile pic: (max 5MB, JPEG, PNG, GIF):</label>
                <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif" />
            </div>

            <button type="submit">Save</button>
        </form>

        <p class="back-link">
            <a href="profile.php">Back to my profile</a>
        </p>
    </div>
</body>

</html>
