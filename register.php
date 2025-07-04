<?php
require_once 'config.php';

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $username_input = trim($_POST["username"] ?? '');
    $password_input = $_POST["password"] ?? '';
    $confirm_password_input = $_POST["confirm_password"] ?? '';
    $email_input = trim($_POST["email"] ?? '');
    $role_input = $_POST["role"] ?? 'user';

    if ($password_input !== $confirm_password_input) {
        $error_message = "Passwords do not match!";
    } elseif (!preg_match('/[A-Z]/', $password_input)) {
        $error_message = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/\d/', $password_input)) {
        $error_message = "Password must contain at least one digit.";
    } elseif (!preg_match('/[^a-zA-Z\d]/', $password_input)) {
        $error_message = "Password must contain at least one special character.";
    } elseif (strlen($password_input) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        $sql_check = "SELECT username FROM users WHERE username = ? OR email = ?";
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param("ss", $username_input, $email_input);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $error_message = "This username or email is already registered!";
            } else {
                $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);
                $is_artist = ($role_input === "artist") ? 1 : 0;

                $sql_insert = "INSERT INTO users (username, password, email, is_artist) VALUES (?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                if ($stmt_insert) {
                    $stmt_insert->bind_param("sssi", $username_input, $hashed_password, $email_input, $is_artist);
                    if ($stmt_insert->execute()) {
                        $success_message = "Account created successfully! You can <a href='login.php'>log in</a> now.";
                    } else {
                        $error_message = "Error creating account: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                } else {
                    $error_message = "Error preparing insert query: " . $conn->error;
                }
            }
            $stmt_check->close();
        } else {
            $error_message = "Error preparing check query: " . $conn->error;
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Meraki</title>
    <link rel="stylesheet" href="./styles/register.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;700&family=Tinos&display=swap" rel="stylesheet">
</head>

<body>
    <div class="rotating-image-container">
        <img src="img/disk1.png" alt="disk" class="rotating-image">
    </div>
    <div class="login-container">
        <h2>Create an account</h2>

        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <p class="success-message"><?php echo $success_message; ?></p>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="input-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>
            <div class="input-group">
                <label for="role">I am a:</label>
                <select id="role" name="role">
                    <option value="user" <?php echo (isset($_POST['role']) && $_POST['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                    <option value="artist" <?php echo (isset($_POST['role']) && $_POST['role'] == 'artist') ? 'selected' : ''; ?>>Artist</option>
                </select>
            </div>
            <div class="input-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            <div class="input-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="input-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div class="button-group">
                <button type="submit">Create Account</button>
            </div>
            <p style="margin-top: 10px; text-align: center;"><a href="login.php" style="color: #c2bebe;">Back to login</a></p>
        </form>
    </div>
</body>

</html>