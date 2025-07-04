<?php
session_start();
require_once 'config.php';

$album_id = null;
$album_title = '';
$artist_name = '';
$cover_blob = null;
$cover_type = null;
$existing_rating = '';
$existing_review = '';
$error = '';
$success = '';

if (isset($_GET['album_id'])) {
    $album_id = $_GET['album_id'];

    $stmt_album = $conn->prepare("SELECT title, artist, cover_blob, cover_type FROM albums WHERE album_id = ?");
    if ($stmt_album) {
        $stmt_album->bind_param("i", $album_id);
        $stmt_album->execute();
        $result_album = $stmt_album->get_result();
        if ($row_album = $result_album->fetch_assoc()) {
            $album_title = htmlspecialchars($row_album['title']);
            $artist_name = htmlspecialchars($row_album['artist']);
            $cover_blob = $row_album['cover_blob'];
            $cover_type = $row_album['cover_type'];
        } else {
            $error = "Album not found.";
        }
        $stmt_album->close();
    } else {
        $error = "Error preparing album query: " . $conn->error;
    }
} else {
    $error = "Album ID not provided.";
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($album_id && $user_id) {
    $stmt_existing = $conn->prepare("SELECT rating, review_text FROM reviews WHERE album_id = ? AND user_id = ?");
    if ($stmt_existing) {
        $stmt_existing->bind_param("ii", $album_id, $user_id);
        $stmt_existing->execute();
        $result_existing = $stmt_existing->get_result();
        if ($row_existing = $result_existing->fetch_assoc()) {
            $existing_rating = htmlspecialchars($row_existing['rating']);
            $existing_review = htmlspecialchars($row_existing['review_text']);
        }
        $stmt_existing->close();
    } else {
        $error = "Error preparing existing review query: " . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $album_id && $user_id) {
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $review_text = trim($_POST['review_text']);

    if ($rating === false || $rating < 1 || $rating > 5) {
        $error = "Rating must be a number between 1 and 5.";
    } else {
        try {
            $stmt_check = $conn->prepare("SELECT review_id FROM reviews WHERE album_id = ? AND user_id = ?");
            $stmt_check->bind_param("ii", $album_id, $user_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $stmt_update = $conn->prepare("UPDATE reviews SET rating = ?, review_text = ? WHERE album_id = ? AND user_id = ?");
                $stmt_update->bind_param("isii", $rating, $review_text, $album_id, $user_id);
                if ($stmt_update->execute()) {
                    $success = "Review updated successfully!";
                } else {
                    $error = "Error updating review: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $stmt_insert = $conn->prepare("INSERT INTO reviews (album_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)");
                $stmt_insert->bind_param("iiis", $album_id, $user_id, $rating, $review_text);
                if ($stmt_insert->execute()) {
                    $success = "Review added successfully!";
                } else {
                    $error = "Error adding review: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            }
            $stmt_check->close();

            header("Location: this_album.php?album_id=" . $album_id);
            exit();

        } catch (Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Album</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Source Sans 3', sans-serif;
            background-color: #000000;
            color: #fff;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            text-align: center;
        }

        .container {
            width: 90%;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
            background-color: #630000;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #ffffff;
            margin-bottom: 20px;
        }

        .album-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
            color: #333;
        }

        .album-info img {
            width: 100px;
            height: 100px;
            border-radius: 5px;
            margin-right: 15px;
        }

        .album-info div {
            text-align: left;
        }

        .album-info h2 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .album-info p {
            margin: 0;
            color: #666;
        }

        form {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            text-align: left;
            color: #333;
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .rating-options {
            margin-bottom: 20px;
        }

        .rating-options input[type="radio"] {
            margin-right: 5px;
        }

        .rating-options label {
            display: inline-block;
            margin-right: 15px;
            font-weight: normal;
        }

        textarea {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: vertical;
            min-height: 80px;
        }

        button[type="submit"] {
            background-color: #000000;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        button[type="submit"]:hover {
            background-color: #333;
        }

        .error {
            color: red;
            font-size: 0.9em;
            background-color: #ffe2e2;
            border: 1px solid #ff6b6b;
            padding: 10px;
            border-radius: 5px;
        }

        .success {
            color: #006400;
            margin-top: 15px;
            font-size: 0.9em;
            background-color: #e6f4e5;
            border: 1px solid #006400;
            padding: 10px;
            border-radius: 5px;
        }

        @media (max-width: 576px) {
            .album-info {
                flex-direction: column;
                text-align: center;
            }
            .album-info img {
                margin-right: 0;
                margin-bottom: 10px;
            }
            .album-info div {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Rate Album</h1>

        <?php if ($album_id && !$error): ?>
            <div class="album-info">
                <?php if ($cover_blob && $cover_type): ?>
                    <img src="data:<?php echo $cover_type; ?>;base64,<?php echo base64_encode($cover_blob); ?>" alt="Album Cover">
                <?php else: ?>
                    <img src="img/default_cover.png" alt="Default Cover">
                <?php endif; ?>
                <div>
                    <h2><?php echo $album_title; ?></h2>
                    <p>Artist: <?php echo $artist_name; ?></p>
                </div>
            </div>

            <form method="post">
                <label for="rating">Rating:</label>
                <div class="rating-options">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <input type="radio" id="rating<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>"
                            <?php echo ($existing_rating == $i) ? 'checked' : ''; ?> required>
                        <label for="rating<?php echo $i; ?>"><?php echo $i; ?></label>
                    <?php endfor; ?>
                </div>

                <label for="review_text">Review (optional):</label>
                <textarea id="review_text" name="review_text" placeholder="Write your review here..."><?php echo $existing_review; ?></textarea>

                <button type="submit">Submit Review</button>

                <?php if ($error): ?>
                    <p class="error"><?php echo $error; ?></p>
                <?php endif; ?>
                <?php if ($success): ?>
                    <p class="success"><?php echo $success; ?></p>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <a href="this_album.php?album_id=<?php echo $album_id; ?>">Back to Album Details</a>
    </div>
</body>
</html>