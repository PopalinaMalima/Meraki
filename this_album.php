<?php
session_start();
require_once 'config.php';

$albumData = [
    'album_id' => null,
    'title' => "",
    'artist' => "",
    'release_date' => "",
    'release_year' => "",
    'cover_blob' => null,
    'cover_type' => null,
];

$trackCount = 0;
$averageRating = null;
$totalRatings = 0;
$userRating = 0;
$existingReviewText = '';
$tracks = [];
$reviews = [];
$albumFound = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'submit_review') {
    $current_album_id_post = filter_input(INPUT_POST, 'album_id', FILTER_SANITIZE_NUMBER_INT);

    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = "You must be logged in to submit a review.";
    } else {
        $album_id_submit = $current_album_id_post;
        $rating = filter_input(INPUT_POST, 'rating', FILTER_SANITIZE_NUMBER_INT);
        $review_text = filter_input(INPUT_POST, 'review_text', FILTER_SANITIZE_STRING);
        $user_id = $_SESSION['user_id'];

        if ($album_id_submit && $rating >= 1 && $rating <= 5) {
            $sql_check = "SELECT COUNT(*) FROM reviews WHERE album_id = ? AND user_id = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("ii", $album_id_submit, $user_id);
            $stmt_check->execute();
            $stmt_check->bind_result($review_count);
            $stmt_check->fetch();
            $stmt_check->close();

            $is_first_review = ($review_count == 0);

            if ($is_first_review) {
                $sql_insert = "INSERT INTO reviews (album_id, user_id, rating, review_text, created_at) VALUES (?, ?, ?, ?, NOW())";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("iiis", $album_id_submit, $user_id, $rating, $review_text);

                if ($stmt_insert->execute()) {
                    $_SESSION['success_message'] = "Review submitted successfully!";
                    $_SESSION['first_review'] = true;
                } else {
                    $_SESSION['error_message'] = "Error submitting review: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } else {
                $sql_update = "UPDATE reviews SET rating = ?, review_text = ?, created_at = NOW() WHERE album_id = ? AND user_id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("isii", $rating, $review_text, $album_id_submit, $user_id);

                if ($stmt_update->execute()) {
                    $_SESSION['success_message'] = "Review updated successfully!";
                } else {
                    $_SESSION['error_message'] = "Error updating review: " . $stmt_update->error;
                }
                $stmt_update->close();
            }
        } else {
            $_SESSION['error_message'] = "Invalid rating or album input.";
        }
    }
    header("Location: this_album.php?album_id=" . $current_album_id_post);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_review') {
    $current_album_id_post = filter_input(INPUT_POST, 'album_id', FILTER_SANITIZE_NUMBER_INT);

    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = "You must be logged in to delete a review.";
    } else {
        $album_id_delete = $current_album_id_post;
        $user_id_to_delete = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
        $logged_in_user_id = $_SESSION['user_id'];

        if ($user_id_to_delete != $logged_in_user_id) {
            $_SESSION['error_message'] = "You are not authorized to delete this review.";
        } elseif ($album_id_delete && $user_id_to_delete) {
            $sql_delete = "DELETE FROM reviews WHERE album_id = ? AND user_id = ?";
            $stmt_delete = $conn->prepare($sql_delete);

            if ($stmt_delete) {
                $stmt_delete->bind_param("ii", $album_id_delete, $user_id_to_delete);

                if ($stmt_delete->execute()) {
                    if ($stmt_delete->affected_rows > 0) {
                        $_SESSION['success_message'] = "Review deleted successfully!";
                    } else {
                        $_SESSION['error_message'] = "Review not found or already deleted.";
                    }
                } else {
                    $_SESSION['error_message'] = "Error deleting review: " . $stmt_delete->error;
                }
                $stmt_delete->close();
            } else {
                $_SESSION['error_message'] = "Database query preparation failed.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid album or user ID provided for deletion.";
        }
    }
    header("Location: this_album.php?album_id=" . $current_album_id_post);
    exit();
}

if (isset($_GET['album_id'])) {
    $album_id = $_GET['album_id'];

    $sql_album = "SELECT album_id, title, artist, cover_blob, cover_type, release_date FROM albums WHERE album_id = ?";
    $stmt_album = $conn->prepare($sql_album);

    if ($stmt_album) {
        $stmt_album->bind_param("i", $album_id);
        $stmt_album->execute();
        $result_album = $stmt_album->get_result();

        if ($row_album = $result_album->fetch_assoc()) {
            $albumFound = true;
            $albumData['album_id'] = htmlspecialchars($row_album['album_id']);
            $albumData['title'] = htmlspecialchars($row_album['title']);
            $albumData['artist'] = htmlspecialchars($row_album['artist']);
            $albumData['release_date'] = htmlspecialchars($row_album['release_date']);
            $albumData['cover_type'] = $row_album['cover_type'];
            $albumData['cover_blob'] = $row_album['cover_blob'];

            if (!empty($row_album['release_date'])) {
                $albumData['release_year'] = date('Y', strtotime($row_album['release_date']));
            }

            $sql_tracks = "SELECT track_number, title, duration FROM tracks WHERE album_id = ? ORDER BY track_number ASC";
            $stmt_tracks = $conn->prepare($sql_tracks);
            if ($stmt_tracks) {
                $stmt_tracks->bind_param("i", $album_id);
                $stmt_tracks->execute();
                $result_tracks = $stmt_tracks->get_result();
                while ($row_track = $result_tracks->fetch_assoc()) {
                    $tracks[] = [
                        'number' => htmlspecialchars($row_track['track_number']),
                        'title' => htmlspecialchars($row_track['title']),
                        'duration' => substr($row_track['duration'], 0, 5)
                    ];
                }
                $stmt_tracks->close();
            }

            $sql_ratings_summary = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM reviews WHERE album_id = ?";
            $stmt_ratings_summary = $conn->prepare($sql_ratings_summary);
            if ($stmt_ratings_summary) {
                $stmt_ratings_summary->bind_param("i", $album_id);
                $stmt_ratings_summary->execute();
                $result_ratings_summary = $stmt_ratings_summary->get_result();
                if ($row_ratings_summary = $result_ratings_summary->fetch_assoc()) {
                    $averageRating = $row_ratings_summary['avg_rating'];
                    $totalRatings = $row_ratings_summary['total_ratings'];
                }
                $stmt_ratings_summary->close();
            }

            if (isset($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
                $sql_user_review = "SELECT rating, review_text FROM reviews WHERE album_id = ? AND user_id = ?";
                $stmt_user_review = $conn->prepare($sql_user_review);
                if ($stmt_user_review) {
                    $stmt_user_review->bind_param("ii", $album_id, $user_id);
                    $stmt_user_review->execute();
                    $result_user_review = $stmt_user_review->get_result();
                    if ($row_user_review = $result_user_review->fetch_assoc()) {
                        $userRating = $row_user_review['rating'];
                        $existingReviewText = htmlspecialchars($row_user_review['review_text']);
                    }
                    $stmt_user_review->close();
                }
            }

            $sql_reviews = "SELECT r.rating, r.review_text, r.user_id, u.username, u.avatar_blob, u.avatar_type
                            FROM reviews r
                            JOIN users u ON r.user_id = u.user_id
                            WHERE r.album_id = ?
                            ORDER BY r.created_at DESC";
            $stmt_reviews = $conn->prepare($sql_reviews);
            if ($stmt_reviews) {
                $stmt_reviews->bind_param("i", $album_id);
                $stmt_reviews->execute();
                $result_reviews = $stmt_reviews->get_result();
                while ($row_review = $result_reviews->fetch_assoc()) {
                    $reviews[] = [
                        'user_id' => htmlspecialchars($row_review['user_id']),
                        'username' => htmlspecialchars($row_review['username']),
                        'avatar_blob' => $row_review['avatar_blob'],
                        'avatar_type' => $row_review['avatar_type'],
                        'rating' => htmlspecialchars($row_review['rating']),
                        'review_text' => htmlspecialchars($row_review['review_text'])
                    ];
                }
                $stmt_reviews->close();
            }

            if (isset($_SESSION['first_review']) && $_SESSION['first_review'] === true && isset($_SESSION['user_id'])) {
                $current_user_id = $_SESSION['user_id'];
                $user_review_index = -1;

                foreach ($reviews as $index => $review) {
                    if ($review['user_id'] == $current_user_id) {
                        $user_review_index = $index;
                        break;
                    }
                }

                if ($user_review_index !== -1) {
                    $user_specific_review = $reviews[$user_review_index];
                    array_splice($reviews, $user_review_index, 1);
                    array_unshift($reviews, $user_specific_review);
                }
                unset($_SESSION['first_review']);
            }
        }
        $stmt_album->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Album: <?php echo $albumData['title'] ?: "Not Found"; ?></title>
    <link rel="stylesheet" href="./styles/album.css">
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
                if (isset($_SESSION['user_id'])) {
                    $user_id = $_SESSION['user_id'];
                    $stmt_header_user = $conn->prepare("SELECT username, avatar_blob, avatar_type FROM users WHERE user_id = ?");
                    if ($stmt_header_user) {
                        $stmt_header_user->bind_param("i", $user_id);
                        $stmt_header_user->execute();
                        $result_header_user = $stmt_header_user->get_result();

                        if ($row_header_user = $result_header_user->fetch_assoc()) {
                            echo '<span class="welcome">' . htmlspecialchars($row_header_user['username']) . '</span>';
                            $_SESSION['username'] = $row_header_user['username'];

                            echo '<a href="profile.php?id=' . $_SESSION['user_id'] . '" class="avatar-link">';

                            if ($row_header_user['avatar_blob'] && $row_header_user['avatar_type']) {
                                echo '<div class="avatar"><img src="data:' . htmlspecialchars($row_header_user['avatar_type']) . ';base64,' . base64_encode($row_header_user['avatar_blob']) . '" alt="Avatar"></div>';
                            } else {
                                echo '<div class="avatar"><img src="img/default-avatar.jpg" alt="Default Avatar"></div>';
                            }
                            echo '</a>';
                        } else {
                            echo '<span class="welcome">Welcome!</span>';
                            echo '<div class="avatar"><img src="img/default-avatar.jpg" alt="Default Avatar"></div>';
                        }
                        $stmt_header_user->close();
                    } else {
                        echo '<span class="welcome">Welcome!</span>';
                        echo '<div class="avatar"><img src="img/default-avatar.jpg" alt="Default Avatar"></div>';
                    }
                } else {
                    echo '<span class="welcome">Welcome!</span>';
                    echo '<div class="avatar"><img src="img/default-avatar.jpg" alt="Default Avatar"></div>';
                }
                ?>
            </div>
        </div>

        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="message success">' . $_SESSION['success_message'] . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="message error">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <h1 class="main-title">Album Details</h1>

        <?php if ($albumFound): ?>
            <div class="album-details">
                <div class="album-presentation">
                    <?php
                    if ($albumData['cover_blob'] && $albumData['cover_type']) {
                        echo '<img src="data:' . htmlspecialchars($albumData['cover_type']) . ';base64,' . base64_encode($albumData['cover_blob']) . '" alt="' . $albumData['title'] . ' Cover" class="album-cover">';
                    } else {
                        echo '<img src="./img/default-album.png" alt="Default Album Cover" class="album-cover">';
                    }
                    ?>
                    <div class="album-info">
                        <h2><?php echo $albumData['title']; ?></h2>
                        <p>Artist: <span style="color: cyan; text-decoration: none; font-weight: bold;"><?php echo $albumData['artist']; ?></span></p>
                        <p>Release Year: <span><?php echo $albumData['release_year']; ?></span></p>
                    </div>

                    <?php
                    $canEdit = isset($_SESSION['username']) && $_SESSION['username'] === $albumData['artist'];
                    if ($canEdit): ?>
                        <form action="update_album.php" method="GET" style="margin-top: 15px;">
                            <input type="hidden" name="album_id" value="<?php echo $albumData['album_id']; ?>">
                            <button type="submit" class="edit-button">Edit album</button>
                        </form>
                    <?php endif; ?>

                </div>
                <div class="tracks-list">
                    <h3>Tracks</h3>
                    <?php if (!empty($tracks)): ?>
                        <ul>
                            <?php foreach ($tracks as $track): ?>
                                <li>
                                    <span class="track-title"><?php echo $track['title']; ?></span>
                                    <span class="track-duration"><?php echo $track['duration']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color: #ccc;">No tracks found for this album.</p>
                    <?php endif; ?>
                </div>

                <div class="ratings-reviews-section">
                    <h3>Ratings and Reviews</h3>
                    <div class="average-rating">
                        Average Rating:
                        <?php if ($averageRating !== null): ?>
                            <strong><?php echo number_format($averageRating, 1); ?> / 5</strong> (<?php echo $totalRatings; ?> ratings)
                        <?php else: ?>
                            No ratings yet.
                        <?php endif; ?>
                    </div>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="user-rating-form">
                            <h4>Your Review:</h4>
                            <form method="POST">
                                <input type="hidden" name="action" value="submit_review">
                                <input type="hidden" name="album_id" value="<?php echo $albumData['album_id']; ?>">
                                <div class="stars">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" <?php echo ($userRating == $i) ? 'checked' : ''; ?> required>
                                        <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> stars">&#9733;</label>
                                    <?php endfor; ?>
                                </div>
                                <textarea name="review_text" placeholder="Write your review here..."><?php echo $existingReviewText; ?></textarea>
                                <button type="submit">Submit review</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <p style="color: #ccc;">Please <a href="login.php" style="color: #630000; text-decoration: none;">log in</a> to rate and review this album.</p>
                    <?php endif; ?>

                    <div class="all-reviews">
                        <h4>All Reviews:</h4>
                        <?php if (!empty($reviews)): ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-header">
                                        <div class="reviewer-avatar">
                                            <?php if ($review['avatar_blob'] && $review['avatar_type']): ?>
                                                <img src="data:<?php echo $review['avatar_type']; ?>;base64,<?php echo base64_encode($review['avatar_blob']); ?>" alt="User Avatar">
                                            <?php else: ?>
                                                <img src="img/default-avatar.jpg" alt="Default Avatar">
                                            <?php endif; ?>
                                        </div>
                                        <span class="reviewer-username"><?php echo $review['username']; ?></span>
                                        <span class="review-rating">
                                            <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                                                &#9733;
                                            <?php endfor; ?>
                                            <?php for ($i = $review['rating']; $i < 5; $i++): ?>
                                                &#9734;
                                            <?php endfor; ?>
                                        </span>

                                        <?php
                                        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $review['user_id']) {
                                            echo '<form method="POST" onsubmit="return confirm(\'Are you sure?\');" style="display:inline; margin-left: auto;">';
                                            echo '<input type="hidden" name="action" value="delete_review">';
                                            echo '<input type="hidden" name="album_id" value="' . htmlspecialchars($albumData['album_id']) . '">';
                                            echo '<input type="hidden" name="user_id" value="' . htmlspecialchars($review['user_id']) . '">';
                                            echo '<button type="submit" class="delete-btn">Delete</button>';
                                            echo '</form>';
                                        }
                                        ?>
                                    </div>
                                    <p class="review-text"><?php echo $review['review_text']; ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #ccc;">No reviews yet. Be the first to leave a review!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p class="main-title" style="font-size: 2em; color: #ff6666;">Album not found.</p>
        <?php endif; ?>
    </div>
    <?php
    if (isset($conn) && $conn) {
        $conn->close();
    }
    ?>
</body>

</html>