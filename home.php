<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/home.css">
    <title>MERAKI</title>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <img src="img/disk2.png" alt="disk2">
                <a href="home.php">MERAKI</a>
            </div>
            <div class="navigation">
                <button><a href="artists.php" style="text-decoration: none; color: inherit;">Artists</a></button>
                <button><a href="albums.php" style="text-decoration: none; color: inherit;">Albums</a></button>
            </div>
            <div class="header-right">
                <?php
                session_start();
                require_once 'config.php';

                if (isset($_SESSION['user_id'])) {
                    $user_id = $_SESSION['user_id'];

                    $stmt = $conn->prepare("SELECT username, avatar_blob, avatar_type FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($row = $result->fetch_assoc()) {
                        echo '<span class="welcome">' . htmlspecialchars($row['username']) . '</span>';
                        $_SESSION['username'] = $row['username'];

                        echo '<a href="profile.php?id=' . $_SESSION['user_id'] . '" class="avatar-link">';

                        if ($row['avatar_blob'] && $row['avatar_type']) {
                            echo '<div class="avatar"><img src="data:' . htmlspecialchars($row['avatar_type']) . ';base64,' . base64_encode($row['avatar_blob']) . '" alt="Avatar"></div>';
                        } else {
                            echo '<div class="avatar"><img src="img/default-avatar.jpg" alt="Default Avatar"></div>';
                        }
                        echo '</a>';
                    } else {
                        echo '<span class="welcome">Welcome!</span>';
                        echo '<div class="avatar"><img src="img/default-avatar.jpg" alt="Default Avatar"></div>';
                    }

                    $stmt->close();
                } else {
                    echo '<span class="welcome">Welcome!</span>';
                    echo '<div class="avatar"><img src="img/default-avatar.jpg" alt="Default Avatar"></div>';
                }

                $conn->close();
                ?>
            </div>
        </div>
        <section class="home">
            <div class="hero">
                <div class="hero-left">
                    <h1 class="hero-motto">Where Every Album Finds Its Audience</h1>
                    <button><a href="albums.php" style="text-decoration: none; color: inherit;">See our album collection</a></button>
                </div>
                <img class="hero-albums" src="./img/albums.webp" alt="albums hero collage" width="300px" />
            </div>
            <div class="about">
                <h2 class="about-title">About</h2>
                <div class="motto">
                    <h3 class="motto-pronounciation">[may-rah-kee] • Greek</h3>
                    <p class="motto-def">(adj.) when you do something with soul, creativity or love; putting a piece of yourself into what you do.</p>
                </div>
                <div class="about-content">
                    <p class="about-content">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
                    <p class="about-content">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
                    <p class="about-content">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
                </div>
            </div>
        </section>
    </div>
    <footer>
        <p style="margin-bottom: 0.5rem;">&copy; 2025 Meraki. Popa Malina. All rights reserved.</p>
    </footer>

</body>

</html>