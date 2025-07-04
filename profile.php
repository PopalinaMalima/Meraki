<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$userData = [
    'user_id' => null,
    'username' => '',
    'email' => '',
    'bio' => '',
    'is_admin' => false,
    'is_artist' => false,
    'avatar_blob' => null,
    'avatar_type' => null,
];
$userFound = false;

$loggedInUserData = null;
$logged_in_user_id = null;
$loggedInUserIsAdmin = false;
$loggedInUserIsArtist = false;

if (isset($_SESSION['user_id'])) {
    $logged_in_user_id = $_SESSION['user_id'];
    $sql_header_user = "SELECT username, avatar_blob, avatar_type, is_admin, is_artist FROM users WHERE user_id = ?";
    $stmt_header_user = $conn->prepare($sql_header_user);

    if ($stmt_header_user) {
        $stmt_header_user->bind_param("i", $logged_in_user_id);
        $stmt_header_user->execute();
        $result_header_user = $stmt_header_user->get_result();

        if ($row_header_user = $result_header_user->fetch_assoc()) {
            $loggedInUserData = $row_header_user;
            $loggedInUserIsAdmin = (bool)$row_header_user['is_admin'];
            $loggedInUserIsArtist = (bool)$row_header_user['is_artist'];
        }
        $stmt_header_user->close();
    } else {
        error_log("Failed to prepare header user statement: " . $conn->error);
    }
}

if (isset($_GET['id'])) {
    $profile_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

    if ($profile_id) {
        $sql_user = "SELECT user_id, username, email, bio, is_admin, is_artist, avatar_blob, avatar_type FROM users WHERE user_id = ?";
        $stmt_user = $conn->prepare($sql_user);

        if ($stmt_user) {
            $stmt_user->bind_param("i", $profile_id);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();

            if ($row_user = $result_user->fetch_assoc()) {
                $userFound = true;
                $userData['user_id'] = htmlspecialchars($row_user['user_id']);
                $userData['username'] = htmlspecialchars($row_user['username']);
                $userData['email'] = htmlspecialchars($row_user['email']);
                $userData['bio'] = htmlspecialchars($row_user['bio']);
                $userData['is_admin'] = (bool)$row_user['is_admin'];
                $userData['is_artist'] = (bool)$row_user['is_artist'];
                $userData['avatar_blob'] = $row_user['avatar_blob'];
                $userData['avatar_type'] = $row_user['avatar_type'];
            }
            $stmt_user->close();
        } else {
            error_log("Failed to prepare profile user statement: " . $conn->error);
        }
    }
}

if (!$userFound) {
    if ($logged_in_user_id) {
        header("Location: profile.php?id=" . $logged_in_user_id);
        exit();
    } else {
        $_SESSION['error_message'] = "You must be logged in to access this page.";
        header("Location: login.php");
        exit();
    }
}

$reviews = [];
if ($userFound) {
    $sql_reviews = "SELECT r.rating, r.review_text AS comment, r.created_at, r.album_id,
                            u.username AS reviewer_username, u.avatar_blob AS reviewer_avatar_blob, u.avatar_type AS reviewer_avatar_type,
                            a.title AS album_title, a.cover_blob AS album_cover_blob, a.cover_type AS album_cover_type
                    FROM reviews r
                    JOIN users u ON r.user_id = u.user_id
                    LEFT JOIN albums a ON r.album_id = a.album_id
                    WHERE r.user_id = ?
                    ORDER BY r.created_at DESC";
    $stmt_reviews = $conn->prepare($sql_reviews);

    if ($stmt_reviews) {
        $stmt_reviews->bind_param("i", $userData['user_id']);
        $stmt_reviews->execute();
        $result_reviews = $stmt_reviews->get_result();

        while ($row_review = $result_reviews->fetch_assoc()) {
            $reviews[] = $row_review;
        }
        $stmt_reviews->close();
    } else {
        error_log("Failed to prepare reviews statement: " . $conn->error);
    }
}

$albums = [];
if ($userFound && $userData['is_artist']) {
    $sql_albums = "SELECT a.album_id, a.title, a.release_date, a.cover_blob, a.cover_type
                   FROM albums a
                   JOIN users u ON a.artist = u.username
                   WHERE u.user_id = ?
                   ORDER BY release_date DESC, title ASC";
    $stmt_albums = $conn->prepare($sql_albums);

    if ($stmt_albums) {
        $stmt_albums->bind_param("i", $userData['user_id']);
        $stmt_albums->execute();
        $result_albums = $stmt_albums->get_result();

        while ($row_album = $result_albums->fetch_assoc()) {
            $albums[] = $row_album;
        }
        $stmt_albums->close();
    } else {
        error_log("Failed to prepare albums statement: " . $conn->error);
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile <?php echo $userData['username'] ?: "User"; ?></title>
    <link rel="stylesheet" href="./styles/profile.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <img src="img/disk2.png" alt="disk2">
                <a href="home.php">MERAKI</a>
            </div>
            <nav class="navbar">
                <a href="home.php">Home</a>
                <a href="artists.php">Artists</a>
                <a href="albums.php">Albums</a>
                <?php if ($loggedInUserIsAdmin) : ?>
                    <a href="admin_panel.html">Admin Panel</a>
                <?php endif; ?>
                <?php if ($loggedInUserData) : ?>
                    <a href="logout.php">Logout</a>
                <?php endif; ?>
            </nav>
            <div class="header-right">
                <?php if ($loggedInUserData) : ?>
                    <span class="welcome"><?php echo htmlspecialchars($loggedInUserData['username']); ?></span>
                    <a href="profile.php?id=<?php echo $logged_in_user_id; ?>" class="avatar-link">
                        <?php if ($loggedInUserData['avatar_blob'] && $loggedInUserData['avatar_type']) : ?>
                            <div class="avatar"><img src="data:<?php echo htmlspecialchars($loggedInUserData['avatar_type']); ?>;base64,<?php echo base64_encode($loggedInUserData['avatar_blob']); ?>" alt="Avatar"></div>
                        <?php else : ?>
                            <div class="avatar"><img src="./img/default-avatar.jpg" alt="Default Avatar"></div>
                        <?php endif; ?>
                    </a>
                <?php else : ?>
                    <a href="login.php" class="button">Login</a>
                    <a href="register.php" class="button">Register</a>
                <?php endif; ?>
            </div>
        </div>

        <h1 class="main-title">User Profile</h1>

        <div class="profile-container">
            <?php
            if ($userData['avatar_blob'] && $userData['avatar_type']) {
                echo '<img src="data:' . htmlspecialchars($userData['avatar_type']) . ';base64,' . base64_encode($userData['avatar_blob']) . '" alt="Profile Avatar" class="profile-avatar">';
            } else {
                echo '<img src="./img/default-avatar.jpg" alt="Default Profile Avatar" class="profile-avatar">';
            }
            ?>
            <div class="profile-info">
                <h2><?php echo $userData['username']; ?></h2>
                <p><strong>Email:</strong> <?php echo $userData['email']; ?></p>
                <p><strong>User ID:</strong> <?php echo $userData['user_id']; ?></p>
                <p><strong>Status:</strong>
                    <?php
                    $roles = [];
                    if ($userData['is_admin']) {
                        $roles[] = 'Admin';
                    }
                    if ($userData['is_artist']) {
                        $roles[] = 'Artist';
                    }
                    if (empty($roles)) {
                        echo 'Standard User';
                    } else {
                        echo implode(', ', $roles);
                    }
                    ?>
                </p>

                <div class="bio-section">
                    <p><?php echo !empty($userData['bio']) ? nl2br($userData['bio']) : 'No bio'; ?></p>
                </div>

            </div>

            <div class="profile-actions">
                <?php if ($logged_in_user_id && $logged_in_user_id == $userData['user_id']) : ?>
                    <a href="edit_profile.php" class="button">Edit your profile</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($userData['is_artist']) : ?>
            <div class="albums-section">
                <h3>Albums by <?php echo $userData['username']; ?></h3>
                <?php if (!empty($albums)) : ?>
                    <div class="album-grid">
                        <?php foreach ($albums as $album) : ?>
                            <a href="this_album.php?album_id=<?php echo htmlspecialchars($album['album_id']); ?>" class="album-item">
                                <?php if ($album['cover_blob'] && $album['cover_type']) : ?>
                                    <img src="data:<?php echo htmlspecialchars($album['cover_type']); ?>;base64,<?php echo base64_encode($album['cover_blob']); ?>" alt="Album Cover">
                                <?php else : ?>
                                    <img src="./img/default-album.png" alt="Default Album Cover">
                                <?php endif; ?>
                                <h4><?php echo htmlspecialchars($album['title']); ?></h4>
                                <p><?php echo htmlspecialchars($album['release_date']); ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="no-albums">No albums found for this artist.</p>
                <?php endif; ?>
                <?php if ($logged_in_user_id && $logged_in_user_id == $userData['user_id']) : ?>
                    <div class="no-albums-actions">
                        <a href="create_album.php" class="button">Add a new album</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="reviews-section">
            <h3>Reviews by <?php echo $userData['username']; ?></h3>
            <?php if (!empty($reviews)) : ?>
                <?php foreach ($reviews as $review) : ?>
                    <div class="review-item">
                        <?php
                        if ($review['reviewer_avatar_blob'] && $review['reviewer_avatar_type']) {
                            echo '<img src="data:' . htmlspecialchars($review['reviewer_avatar_type']) . ';base64,' . base64_encode($review['reviewer_avatar_blob']) . '" alt="Reviewer Avatar" class="reviewer-avatar">';
                        } else {
                            echo '<img src="./img/default-avatar.jpg" alt="Default Reviewer Avatar" class="reviewer-avatar">';
                        }
                        ?>
                        <div class="review-content">
                            <div class="review-header">
                                <span class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_username']); ?></span>
                                <span class="review-date"><?php echo date('F j, Y, g:i a', strtotime($review['created_at'])); ?></span>
                            </div>
                            <div class="review-rating">
                                <?php
                                for ($i = 1; $i <= 5; $i++) : ?>
                                    <?php echo ($i <= $review['rating']) ? '&#9733;' : '&#9734;'; ?>
                                <?php endfor; ?>
                            </div>
                            <div class="review-comment">
                                <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            </div>
                            <?php
                            if ($review['album_title'] && $review['album_id']) : ?>
                                <div class="review-album-display">
                                    Review for:
                                    <a href="this_album.php?album_id=<?php echo $review['album_id']; ?>">
                                        <?php if ($review['album_cover_blob'] && $review['album_cover_type']) : ?>
                                            <img src="data:<?php echo htmlspecialchars($review['album_cover_type']); ?>;base64,<?php echo base64_encode($review['album_cover_blob']); ?>" alt="Album Cover" class="review-album-cover">
                                        <?php else : ?>
                                            <img src="./img/default-album.png" alt="Default Album Cover" class="review-album-cover">
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($review['album_title']); ?></span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p class="no-reviews">Add your first review!</p>
                <div class="no-reviews-actions">
                    <a href="albums.php" class="button">Albums</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>