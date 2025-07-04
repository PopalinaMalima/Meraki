<?php
session_start();

require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $email_input = $_POST["email"];
    $password_input = $_POST["password"];

    $sql_check = "SELECT user_id, password, is_admin FROM users WHERE email = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $email_input);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows == 1) {
        $stmt_check->bind_result($user_id, $hashed_password, $is_admin);
        $stmt_check->fetch();

        if (password_verify($password_input, $hashed_password)) {
            $_SESSION["user_id"] = $user_id;

            if ($is_admin == 1) {
                header("Location: admin_panel.html");
            } else {
                header("Location: home.php");
            }
            exit();
        } else {
            $error_message = "Incorrect password!";
        }
    } else {
        $error_message = "Incorrect or non-existent email!";
    }

    $stmt_check->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meraki</title>
    <link rel="stylesheet" href="./styles/login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;700&family=Tinos&display=swap" rel="stylesheet">
</head>
<body>
    <div class="rotating-image-container">
        <img src="./img/disk1.png" alt="disk" class="rotating-image">
    </div>
    <div class="login-container">
        <h2>Already a user?</h2>
        <form id="loginForm" method="post" action="login.php">
            <div class="input-group">
                <input type="email" id="email" name="email" placeholder="Email" required>
            </div>
            <div class="input-group">
                <input type="password" id="password" name="password" placeholder="Password" required>
            </div>
            <div class="button-group">
                <button type="submit" id="signinBtn" class="signin-button" disabled>SIGN IN</button>
                <a href="register.php" class="signup-link"><button type="button">SIGN UP</button></a>
            </div>
            <div id="errorMessage" class="error-message">
                <?php if (isset($error_message)) echo $error_message; ?>
            </div>
        </form>
    </div>

    <script>
        const loginForm = document.getElementById('loginForm');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const signinBtn = document.getElementById('signinBtn');
        const errorMessageDiv = document.getElementById('errorMessage');

        function checkInputs() {
            if (emailInput.value.trim() !== '' && passwordInput.value.trim() !== '') {
                signinBtn.removeAttribute('disabled');
            } else {
                signinBtn.setAttribute('disabled', 'true');
            }
        }

        emailInput.addEventListener('input', checkInputs);
        passwordInput.addEventListener('input', checkInputs);

        loginForm.addEventListener('submit', function(event) {
            if (signinBtn.hasAttribute('disabled')) {
                event.preventDefault();
                errorMessageDiv.textContent = 'Please enter both email and password.';
            } else {
                errorMessageDiv.textContent = '';
            }
        });
    </script>
</body>
</html>