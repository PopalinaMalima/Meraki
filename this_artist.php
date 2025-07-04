<?php
session_start();
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artist Details</title>
    <link rel="stylesheet" href="styles/artist.css">
</head>

<body>
    <?php
    if (isset($_GET['artist_id'])) {
        $artist_id = $_GET['artist_id'];
        $sql_artist = "SELECT user_id, username, avatar_blob, avatar_type, bio, is_artist FROM users WHERE user_id = ?";
        $stmt_artist = $conn->prepare($sql_artist);
        $stmt_artist->bind_param("i", $artist_id);
        $stmt_artist->execute();
        $result_artist = $stmt_artist->get_result();
        if ($row_artist = $result_artist->fetch_assoc()) {
    ?>
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
                        ?>
                    </div>
                </div>
                <div class="artist-details">

                    <?php
                    if ($row_artist['avatar_blob'] && $row_artist['avatar_type']) {
                        echo '<div class="artist-details-left">
                    <img src="data:' . htmlspecialchars($row_artist['avatar_type']) . ';base64,' . base64_encode($row_artist['avatar_blob']) . '" alt="' . htmlspecialchars($row_artist['username']) . '">';
                    } else {
                        echo '<div class="artist-details-left"><img src="img/default_artist.png" alt="Default Artist">';
                    }
                    echo '<h1>' . htmlspecialchars($row_artist['username']) . '</h1></div><div class="artist-details-right"> <h2>Biography</h2>';
                    echo '<p>' . htmlspecialchars($row_artist['bio']) . '</p>';
                    $sql_album_count = "SELECT COUNT(*) as album_count FROM albums WHERE artist = ?";
                    $stmt_album_count = $conn->prepare($sql_album_count);
                    $stmt_album_count->bind_param("s", $row_artist['username']);
                    $stmt_album_count->execute();
                    $result_album_count = $stmt_album_count->get_result();
                    $row_album_count = $result_album_count->fetch_assoc();
                    echo '<p class="album-count">Number of Albums: ' . htmlspecialchars($row_album_count['album_count']) . '</p></div>';
                    $stmt_album_count->close();
                    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $row_artist['user_id'] && $row_artist['is_artist'] == 1) {
                        echo '<div class="edit-buttons">';
                        echo '<a href="edit_profile.php?artist_id=' . $row_artist['user_id'] . '">Edit Profile</a>';
                        echo '<a href="create2_album.php">Add Album</a>';
                        echo '</div>';
                    }
                    ?>
                </div>
                <div class="artist-albums">
                    <h2>Albums</h2>
                    <?php
                    $sql_albums = "SELECT album_id, title, artist, cover_blob, cover_type FROM albums WHERE artist = ?";
                    $stmt_albums = $conn->prepare($sql_albums);
                    $stmt_albums->bind_param("s", $row_artist['username']);
                    $stmt_albums->execute();
                    $result_albums = $stmt_albums->get_result();
                    if ($result_albums->num_rows > 0) {
                        echo '<div class="album-grid">';
                        while ($row_album = $result_albums->fetch_assoc()) {
                            echo '<div class="album-card">';
                            if ($row_album['cover_blob'] && $row_album['cover_type']) {
                                echo '<img src="data:' . htmlspecialchars($row_album['cover_type']) . ';base64,' . base64_encode($row_album['cover_blob']) . '" alt="' . htmlspecialchars($row_album['title']) . '">';
                            } else {
                                echo '<img src="img/default_cover.png" alt="Default Cover">';
                            }
                            echo '<h3>' . htmlspecialchars($row_album['title']) . '</h3>';
                            echo '<p>Artist: ' . htmlspecialchars($row_album['artist']) . '</p>';
                            echo '<a href="this_album.php?album_id=' . $row_album['album_id'] . '" style="margin-top: 10px; display: inline-block; text-decoration: none; color: black; padding:8px 16px; border-radius: 5px; background-color: cyan;">View Album</a>';
                            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $row_artist['user_id'] && $row_artist['is_artist'] == 1) {
                                echo '<a href="update_album.php?album_id=' . $row_album['album_id'] . '" style="margin-top: 10px; display: inline-block; text-decoration: none; color: #c2bebe;">Edit Album</a>';
                            }
                            echo '</div>';
                        }
                        echo '</div>';
                    } else {
                        echo "<p>No albums found for this artist.</p>";
                    }
                    $stmt_albums->close();
                    ?>
                </div>
            </div>
    <?php
        } else {
            echo "<p>Artist not found.</p>";
        }
        $stmt_artist->close();
    } else {
        echo "<p>Invalid artist ID.</p>";
    }
    $conn->close();
    ?>
</body>

</html>