<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    die("Access denied. Please log in.");
}
$current_user = $_SESSION['username'];

try {
    $pdo = new PDO("mysql:host=$db_server;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("SELECT is_artist, is_admin FROM users WHERE username = ?");
    $stmt->execute([$current_user]);
    $user_roles = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_roles) {
        die("Access denied. User not found.");
    }

    if (!$user_roles['is_artist'] && !$user_roles['is_admin']) {
        die("Access denied. You must be an artist or an admin to add an album.");
    }
} catch (PDOException $e) {
    die("Error checking user status: " . $e->getMessage());
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $release_date = $_POST['release_date'] ?? '';
    $tracklist_input = $_POST['tracklist'] ?? '';
    $cover_blob = null;
    $cover_type = '';
    $average_rating = 0;

    if (isset($_FILES['cover_blob']) && $_FILES['cover_blob']['error'] === UPLOAD_ERR_OK) {
        $file_type = strtolower(pathinfo($_FILES['cover_blob']['name'], PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($file_type, $allowed_types)) {
            $cover_blob = file_get_contents($_FILES['cover_blob']['tmp_name']);
            $cover_type = $file_type;
        } else {
            $error = "Allowed cover types are jpg, jpeg, png, gif.";
        }
    } elseif (isset($_FILES['cover_blob']) && $_FILES['cover_blob']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error = "Error uploading album cover.";
    }

    if (empty($title)) {
        $error = "Album title is required.";
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO albums (title, artist, release_date, cover_blob, cover_type, average_rating)
                                   VALUES (:title, :artist, :release_date, :cover_blob, :cover_type, :average_rating)");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':artist', $current_user);
            $stmt->bindParam(':release_date', $release_date);
            $stmt->bindParam(':cover_blob', $cover_blob, PDO::PARAM_LOB);
            $stmt->bindParam(':cover_type', $cover_type);
            $stmt->bindParam(':average_rating', $average_rating);
            $stmt->execute();
            $album_id = $pdo->lastInsertId();

            if (!empty($tracklist_input)) {
                $tracks = explode("\n", $tracklist_input);
                $track_number = 1;
                $insert_track_stmt = $pdo->prepare("INSERT INTO tracks (album_id, track_number, title, artist, duration) VALUES (?, ?, ?, ?, ?)");

                foreach ($tracks as $track_line) {
                    $track_line = trim($track_line);
                    if (!empty($track_line)) {
                        $track_title = null;
                        $track_artist = null;
                        $track_duration = null;
                        $regex = '/^(?:\d+\.\s*)?(.+?)(?:\s*-\s*([^(\n]+?))?(?:\s*\(([\d:]+)\))?$/';

                        if (preg_match($regex, $track_line, $matches)) {
                            $track_title = trim($matches[1]);
                            $track_artist = isset($matches[2]) && !empty(trim($matches[2])) ? trim($matches[2]) : null;
                            $track_duration = isset($matches[3]) && !empty(trim($matches[3])) ? trim($matches[3]) : null;

                            if ($track_duration && !preg_match('/^(\d{1,2}:)?([0-5]?\d):([0-5]?\d)$/', $track_duration)) {
                                error_log("Invalid duration format for track: " . $track_line);
                                $track_duration = null;
                            }
                        } else {
                            $track_title = $track_line;
                            error_log("Could not parse track line with regex: " . $track_line);
                        }

                        $insert_track_stmt->execute([$album_id, $track_number, $track_title, $track_artist, $track_duration]);
                        $track_number++;
                    }
                }
            }
            $pdo->commit();

            $success = "Album and tracks added successfully!";
            $_POST = [];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error adding album or tracks: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add New Album</title>
    <link rel="stylesheet" href="./styles/create_album.css">
</head>

<body>
    <h1>Add New Album</h1>
    <p><strong>Logged in as artist:</strong> <?php echo htmlspecialchars($current_user); ?></p>
    <form method="post" enctype="multipart/form-data">
        <label for="title">Album Title:</label>
        <input type="text" name="title" id="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">

        <label for="release_date">Release Date:</label>
        <input type="date" name="release_date" id="release_date" value="<?php echo htmlspecialchars($_POST['release_date'] ?? ''); ?>">

        <label for="tracklist">Tracklist (one per line, e.g., "1. Song Title (03:45)"):</label>
        <textarea name="tracklist" id="tracklist" rows="6" placeholder="1. Track 1 (03:30)&#10;2. Track 2 (04:15)"><?php echo htmlspecialchars($_POST['tracklist'] ?? ''); ?></textarea>

        <label for="cover_blob">Album Cover (jpg, jpeg, png, gif):</label>
        <input type="file" name="cover_blob" id="cover_blob" accept="image/*">

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (!empty($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <button type="submit">Add Album</button>
    </form>
</body>

</html>