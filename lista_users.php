<?php
require_once 'config.php';

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_sql = "DELETE FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        echo "<div class='message success'>User deleted successfully!</div>";
    } else {
        echo "<div class='message error'>Error deleting: " . $stmt->error . "</div>";
    }

    $stmt->close();
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$sql = "SELECT user_id, username, email, is_admin, is_artist FROM users";
if ($filter == 'users') {
    $sql .= " WHERE is_artist = 0 AND is_admin = 0";
} elseif ($filter == 'artists') {
    $sql .= " WHERE is_artist = 1";
} elseif ($filter == 'admins') {
    $sql .= " WHERE is_admin = 1";
}
$sql .= " ORDER BY user_id ASC";

$result = $conn->query($sql);

if (!$result) {
    die("Error querying the database: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Users and Artists List</title>
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
            margin: 50px auto;
            background-color: #121314;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
            border: 1px solid cyan;
        }

        h2 {
            font-size: 45px;
            text-align: center;
            color: #ffffff;
            margin-bottom: 0;
        }

        .controls {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .create-user-button,
        .create-artist-button,
        .create-admin-button {
            padding: 0.5rem 1rem;
            background-color: #fff;
            color: #000000;
            text-decoration: none;
            border-radius: 7px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: none;
            cursor: pointer;
            display: inline-block;
        }

        .create-user-button:hover,
        .create-artist-button:hover,
        .create-admin-button:hover {
            background-color: cyan;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-5px);
            color: #000000;
        }

        .filter-dropdown {
            padding: 10px 15px;
            font-size: 16px;
            border-radius: 8px;
            border: 1px solid #ddd;
            background-color: #fff;
            color: #333;
            transition: all 0.3s ease;
            width: 180px;
            box-sizing: border-box;
        }

        .filter-dropdown:focus {
            border-color: #000000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            outline: none;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            background-color: #ffffff;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background-color: rgb(255, 255, 255);
            color: rgb(0, 0, 0);
            font-weight: bold;
        }

        .table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .table tbody tr:hover {
            background-color: #e0e0e0;
        }

        .actions {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            margin: 0 auto;
        }

        .btn-danger {
            background-color: cyan;
            color: black;
            border: 1px solid black;
            text-decoration: none;
        }

        .btn-danger:hover {
            background-color: black;
            color: white;
        }

        .btn-warning {
            background-color: rgb(0, 0, 0);
            color: white;
        }

        .btn-warning:hover {
            background-color: rgb(0, 0, 0);
            color: white;
        }

        .message {
            margin-top: 20px;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 16px;
            color: rgb(0, 0, 0);
            background-color: rgb(255, 255, 255);
            border: 1px solid rgb(0, 0, 0);
        }

        .message.error {
            color: #ff4f4f;
            background-color: #ffe2e2;
            border: 1px solid #ff6b6b;
        }

        .back-button {
            background-color: cyan;
            border-radius: 7px;
            text-align: center;
            text-decoration: none;
            color: black;
            padding: 0.5rem 1.5rem;
            cursor: pointer;
            margin-left: 5px;
        }

        .back-button:hover {
            background-color: skyblue;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>
            Users and Artists
        </h2>
        <div class="controls">
            <a href="register.php" class="create-user-button">Create User/Artist</a>
            <a class="back-button" href="admin_panel.html">Go back</a>

            <select class="filter-dropdown" onchange="window.location.href='?filter=' + this.value">
                <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All</option>
                <option value="users" <?php echo $filter == 'users' ? 'selected' : ''; ?>>Users</option>
                <option value="artists" <?php echo $filter == 'artists' ? 'selected' : ''; ?>>Artists</option>
            </select>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Email</th>

                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>

                        <td class="actions">
                            <a href="?delete_id=<?php echo $row['user_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
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