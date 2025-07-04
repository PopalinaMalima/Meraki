<?php
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['titlu'];
    $artist = $_POST['artist'];
    $release_date = $_POST['data_lansare'];

    if (isset($_FILES['coperta']) && $_FILES['coperta']['error'] == 0) {
        $cover_name = $_FILES['coperta']['name'];
        $cover_type = $_FILES['coperta']['type'];
        $cover_tmp_name = $_FILES['coperta']['tmp_name'];
        $cover_size = $_FILES['coperta']['size'];

        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        if (!in_array($cover_type, $allowed_types)) {
            die("Invalid file type. Please upload a JPEG, PNG, or GIF.");
        }

        if ($cover_size > 5 * 1024 * 1024) {
            die("File is too large. Please upload a file smaller than 5MB.");
        }

        $cover_content = file_get_contents($cover_tmp_name);
        $cover_content = mysqli_real_escape_string($conn, $cover_content);
    } else {
        die("Please upload a cover image for the album.");
    }

    $sql_album = "INSERT INTO albums (title, artist, release_date, cover_blob, cover_type) VALUES (?, ?, ?, ?, ?)";
    $stmt_album = $conn->prepare($sql_album);
    $stmt_album->bind_param("sssss", $title, $artist, $release_date, $cover_content, $cover_type);

    if ($stmt_album->execute()) {
        $album_id = $conn->insert_id;

        if (isset($_POST['melodii']) && is_array($_POST['melodii'])) {
            $tracks = $_POST['melodii'];
            foreach ($tracks as $index => $track_title) {
                if (!empty($track_title)) {
                    $track_number = $index + 1;
                    $sql_track = "INSERT INTO tracks (album_id, title, track_number) VALUES (?, ?, ?)";
                    $stmt_track = $conn->prepare($sql_track);
                    $stmt_track->bind_param("isi", $album_id, $track_title, $track_number);
                    $stmt_track->execute();
                    $stmt_track->close();
                }
            }
        }

        $stmt_album->close();
        $conn->close();

        echo "<script>
            alert('Album successfully added!');
            window.location.href = 'albums.php';
        </script>";
        exit();
    } else {
        echo "Error adding album: " . $stmt_album->error;
    }
} else {
    echo "Invalid request method.";
}
?>