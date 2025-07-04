<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli($db_server, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = intval($_SESSION['user_id']);

$check_admin_sql = "SELECT is_admin FROM users WHERE user_id = ?";
$stmt = $conn->prepare($check_admin_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();


if ($result->num_rows === 0) {
    die("User not found.");
}

$row = $result->fetch_assoc();

if (!$row['is_admin']) {
    die("Access denied. You must be an admin to view this page.");
}

$stmt->close();


if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_sql = "DELETE FROM albums WHERE album_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        echo "<div class='message success'>Album deleted successfully!</div>";
    } else {
        echo "<div class='message error'>Error deleting: " . $stmt->error . "</div>";
    }

    $stmt->close();
}

$sql = "SELECT album_id, title, artist, release_date FROM albums ORDER BY album_id ASC";
$result = $conn->query($sql);

if (!$result) {
    die("Error querying the database: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Albums List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Source Sans 3', sans-serif;
            background-color: #000000;
            margin: 0;
            padding: 0;
        }

        .container {
            width: fit-content;
            margin: 0 auto;
            margin-top: 1em;
            background-color: #121213;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
            border: 1px solid cyan;
        }

        h2 {
            font-size: 2.5rem;
            text-align: center;
            color: #ffffff;
            margin-bottom: 0.5em;
        }

        .create-album-button {
            padding: 0.75rem 1.5rem;
            background-color: #fff;
            color: #000000;
            text-decoration: none;
            border-radius: 7px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: none;
            cursor: pointer;
            margin-top: 1.25rem;
            display: inline-block;
        }

        .create-album-button:hover {
            background-color: cyan;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-5px);
            color: black;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            background-color: #ffffff;
            margin-bottom: 2rem;
            max-height: 400px;
            overflow-y: auto;
            display: block;
        }

        .table th,
        .table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            .table th {
                position: sticky;
                top: 0;
                background-color: rgb(255, 255, 255);
                color: rgb(0, 0, 0);
                font-weight: bold;
                z-index: 1;
            }
        }

        .table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .table tbody tr:hover {
            background-color: #e0e0e0;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }

        .btn {
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            margin: 0 auto;
            text-decoration: none;
        }

        .btn-danger {
            background-color: cyan;
            color: black;
        }

        .btn-danger:hover {
            background-color: skyblue;
            color: white;
        }

        .btn-warning {
            background-color: rgb(0, 0, 0);
            color: white;
        }

        .btn-warning:hover {
            background-color: rgb(70, 70, 70);
            color: white;
        }

        .message {
            margin-top: 1.25rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            color: rgb(0, 0, 0);
            background-color: rgb(255, 255, 255);
            border: 1px solid rgb(0, 0, 0);
            text-align: center;
        }

        .message.error {
            color: #ff4f4f;
            background-color: #ffe2e2;
            border: 1px solid #ff6b6b;
        }

        .message.success {
            color: #006400;
            background-color: #e6f4e5;
            border: 1px solid #006400;
        }

        .back-button {
            background-color: cyan;
            border-radius: 7px;
            text-align: center;
            text-decoration: none;
            color: black;
            width: 100vw;
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            margin-left: 5px;
        }

        .back-button:hover {
            background-color: skyblue;
        }

        @media (max-width: 768px) {
            .container {
                width: 95%;
                padding: 30px;
            }

            .table {
                margin-top: 2rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
                font-size: 0.9rem;
            }

            h2 {
                font-size: 2rem;
            }

            .create-album-button {
                font-size: 0.9rem;
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>
            Albums list
        </h2>
        <a href="create_album.php" class="create-album-button">Create album</a>
        <a class="back-button" href="admin_panel.html">Go back</a>
        <table class="table">
            <thead>
                <tr>
                    <th>Album ID</th>
                    <th>Title</th>
                    <th>Artist</th>
                    <th>Release Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['album_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['artist']); ?></td>
                        <td><?php echo htmlspecialchars($row['release_date']); ?></td>
                        <td class="actions">
                            <a href="update_album.php?album_id=<?php echo $row['album_id']; ?>" class="btn btn-warning btn-sm">Update</a>
                            <a href="?delete_id=<?php echo $row['album_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this album?')">Delete</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</body>

</html>

<?php
$conn->close();
?>