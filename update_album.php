<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    die("Access denied. Please log in.");
}
$current_user = $_SESSION['username'];

try {
    $pdo = new PDO("mysql:host=$db_server;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $stmt = $pdo->prepare("SELECT is_artist, is_admin FROM users WHERE username = ?");
    $stmt->execute([$current_user]);
    $user_roles = $stmt->fetch();

    if (!$user_roles) {
        die("Access denied. User not found.");
    }

    if (!$user_roles['is_artist'] && !$user_roles['is_admin']) {
        die("Access denied. You must be an artist or an admin to update an album.");
    }

} catch (PDOException $e) {
    die("Error checking user status: " . $e->getMessage());
}

$album_id = null;
$album_data = [];
$tracks_data = [];
$error_message = '';
$success_message = '';
$tracklist_input_val = '';

if (isset($_GET['album_id'])) {
    $album_id = intval($_GET['album_id']);

    try {
        $stmt_album = $pdo->prepare("SELECT title, artist, release_date, cover_blob, cover_type FROM albums WHERE album_id = ?");
        $stmt_album->execute([$album_id]);
        $album_data = $stmt_album->fetch();

        if (!$album_data) {
            die("Album not found!");
        }

        if ($album_data['artist'] !== $current_user && !$user_roles['is_admin']) {
            die("Access denied. You can only edit albums you created, unless you are an admin.");
        }

        $stmt_tracks = $pdo->prepare("SELECT track_number, title, artist, duration FROM tracks WHERE album_id = ? ORDER BY track_number ASC");
        $stmt_tracks->execute([$album_id]);
        $tracks_data = $stmt_tracks->fetchAll();

        $temp_track_lines = [];
        foreach ($tracks_data as $track) {
            $line = $track['track_number'] . '. ' . htmlspecialchars($track['title']);
            if (!empty($track['artist'])) {
                $line .= ' - ' . htmlspecialchars($track['artist']);
            }
            if (!empty($track['duration'])) {
                $line .= ' (' . htmlspecialchars($track['duration']) . ')';
            }
            $temp_track_lines[] = $line;
        }
        $tracklist_input_val = implode("\n", $temp_track_lines);


    } catch (PDOException $e) {
        die("Error fetching album data: " . $e->getMessage());
    }
} else {
    die("Album ID is required!");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title_input = $_POST["title"] ?? '';
    $artist_input = $_POST["artist"] ?? '';
    $release_date_input = $_POST["release_date"] ?? '';
    $tracklist_input = $_POST['tracklist'] ?? '';

    $new_cover_blob = null;
    $new_cover_type = null;

    if (isset($_FILES["cover_blob"]) && $_FILES["cover_blob"]["error"] == UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES["cover_blob"]["type"];
        $file_size = $_FILES["cover_blob"]["size"];

        if (in_array($file_type, $allowed_types) && $file_size <= 2 * 1024 * 1024) { // Max 2MB
            $new_cover_blob = file_get_contents($_FILES["cover_blob"]["tmp_name"]);
            $new_cover_type = $_FILES['cover_blob']['type'];
        } else {
            $error_message = "Invalid file type or size (max 2MB allowed)!";
        }
    } elseif (isset($_FILES["cover_blob"]) && $_FILES["cover_blob"]["error"] !== UPLOAD_ERR_NO_FILE) {
        $error_message = "Error uploading cover image: " . $_FILES["cover_blob"]["error"];
    }

    if (empty($title_input) || empty($artist_input)) {
        $error_message = "Title and Album Artist are required!";
    }

    if (empty($error_message)) {
        try {
            $pdo->beginTransaction();

            $sql_update_album = "UPDATE albums SET title=?, artist=?, release_date=?";
            $params = [$title_input, $artist_input, $release_date_input];
            $types = "ssi";
            if ($new_cover_blob !== null && $new_cover_type !== null) {
                $sql_update_album .= ", cover_blob=?, cover_type=?";
                $params[] = $new_cover_blob;
                $params[] = $new_cover_type;
            }
            $sql_update_album .= " WHERE album_id=?";
            $params[] = $album_id;

            $stmt_update_album = $pdo->prepare($sql_update_album);
            $stmt_update_album->execute($params);

            $stmt_delete_tracks = $pdo->prepare("DELETE FROM tracks WHERE album_id = ?");
            $stmt_delete_tracks->execute([$album_id]);

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
            $success_message = "Album and tracks updated successfully!";

            $stmt_album = $pdo->prepare("SELECT title, artist, release_date, cover_blob, cover_type FROM albums WHERE album_id = ?");
            $stmt_album->execute([$album_id]);
            $album_data = $stmt_album->fetch();

            $stmt_tracks = $pdo->prepare("SELECT track_number, title, artist, duration FROM tracks WHERE album_id = ? ORDER BY track_number ASC");
            $stmt_tracks->execute([$album_id]);
            $tracks_data = $stmt_tracks->fetchAll();

            $temp_track_lines = [];
            foreach ($tracks_data as $track) {
                $line = $track['track_number'] . '. ' . htmlspecialchars($track['title']);
                if (!empty($track['artist'])) {
                    $line .= ' - ' . htmlspecialchars($track['artist']);
                }
                if (!empty($track['duration'])) {
                    $line .= ' (' . htmlspecialchars($track['duration']) . ')';
                }
                $temp_track_lines[] = $line;
            }
            $tracklist_input_val = implode("\n", $temp_track_lines);


        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Error updating album or tracks: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Album - Meraki</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;700&family=Tinos&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'NunitoSans', sans-serif, Arial, sans-serif;
            background-color: #101010;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #fff;
        }
        .login-container {
            width: fit-content;
            margin: 0 auto;
            padding: 25px 30px;
            border-radius: 10px;
            background-color: #151819;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.5);
            text-align: center;
            color: #e0e0e0;
        }

        .login-container h2 {
            margin-bottom: 25px;
            color: white;
            /* cyan */
            font-weight: 700;
            font-size: 2rem;
            letter-spacing: 1px;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color:white;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
        }

        .input-group input[type="text"],
        .input-group input[type="date"],
        .input-group input[type="file"],
        .input-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            background-color: #141819;
            color: #e0e0e0;
            font-size: 1rem;
            font-weight: 400;
            transition: border-color 0.3s ease;
        }

        .input-group input[type="text"]:focus,
        .input-group input[type="date"]:focus,
        .input-group input[type="file"]:focus,
        .input-group textarea:focus {
            border-color: #00cccc;
            outline: none;
        }

        .input-group textarea {
            resize: vertical;
        }

        .input-group small {
            margin-top: 4px;
            color: #666;
            font-size: 0.8rem;
        }

        .button-group {
            margin-top: 25px;
        }

        .button-group button {
            width: 100%;
            padding: 12px 0;
            background: linear-gradient(90deg, #00ffff, #00cccc);
            border: none;
            border-radius: 8px;
            color: #0c0c0c;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .button-group button:hover {
            background: linear-gradient(90deg, #00cccc, #00ffff);
        }

        .error-message {
            color: #ff4c4c;
            margin-top: 15px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .success-message {
            color: #4cff4c;
            margin-top: 15px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        a {
            color: #00ffff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h2>Update Album</h2>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?album_id=<?php echo $album_id; ?>" enctype="multipart/form-data">
            <div class="input-group">
                <label for="title">Title:</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($album_data['title'] ?? ''); ?>" required>
            </div>
            <div class="input-group">
                <label for="artist">Artist:</label>
                <input type="text" id="artist" name="artist" value="<?php echo htmlspecialchars($album_data['artist'] ?? ''); ?>" required>
            </div>
            <div class="input-group">
                <label for="release_date">Release Date:</label>
                <input type="date" id="release_date" name="release_date" value="<?php echo htmlspecialchars($album_data['release_date'] ?? ''); ?>">
            </div>
            <div class="input-group">
                <label for="tracklist">Tracklist (one per line, e.g., "1. Song Title (03:30)"):</label>
                <textarea name="tracklist" id="tracklist" rows="10" placeholder="1. Track One - (03:30)&#10;2. Another Track (04:15)"><?php echo htmlspecialchars($tracklist_input_val); ?></textarea>
            </div>
            <div class="input-group">
                <label for="cover_blob">Cover Image:</label>
                <input type="file" id="cover_blob" name="cover_blob" accept="image/*">
                <small>Allowed types: jpg, png, gif. Max size: 2MB. Leave blank to keep current cover.</small>
                <?php if (!empty($album_data['cover_blob']) && !empty($album_data['cover_type'])): ?>
                    <div style="margin-top: 10px;">
                        <img src="data:<?php echo htmlspecialchars($album_data['cover_type']); ?>;base64,<?php echo base64_encode($album_data['cover_blob']); ?>" alt="Current Cover" style="max-width: 100px; height: auto;">
                        <p>Current Cover</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="button-group">
                <button type="submit">Update Album</button>
            </div>
            <p style="margin-top: 10px; text-align: center;"><a href="albums.php">Back to Albums</a></p>
        </form>
        <?php if (isset($error_message) && !empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <?php if (isset($success_message) && !empty($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>
    </div>
</body>

</html>
