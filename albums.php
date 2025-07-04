<?php
session_start();
require_once 'config.php';

$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'title_asc';

$sql_albums = "
  SELECT 
    a.album_id,
    a.title,
    a.artist,
    a.cover_blob,
    a.cover_type,
    a.release_date,
    ROUND(AVG(r.rating), 2) AS average_rating
  FROM albums a
  LEFT JOIN reviews r ON a.album_id = r.album_id
";

$where_clauses = [];
$order_by_clause = "";

if (!empty($search_query)) {
    $where_clauses[] = "a.title LIKE '%" . $search_query . "%'";
}

switch ($sort_by) {
    case 'title_asc':
        $order_by_clause = "ORDER BY a.title ASC";
        break;
    case 'title_desc':
        $order_by_clause = "ORDER BY a.title DESC";
        break;
    case 'release_date_asc':
        $order_by_clause = "ORDER BY a.release_date ASC";
        break;
    case 'release_date_desc':
        $order_by_clause = "ORDER BY a.release_date DESC";
        break;
    case 'rating_asc':
        $order_by_clause = "ORDER BY average_rating ASC";
        break;
    case 'rating_desc':
        $order_by_clause = "ORDER BY average_rating DESC";
        break;
    default:
        $order_by_clause = "ORDER BY a.title ASC";
}

if (!empty($where_clauses)) {
    $sql_albums .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_albums .= " GROUP BY a.album_id " . $order_by_clause;

?>

<!DOCTYPE html>
<html lang="ro">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Albums</title>
    <link rel="stylesheet" href="./styles/albums.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <img src="img/disk2.png" alt="disk2"> <a href="home.php">MERAKI</a>
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

        <h1 class="main-title">Albums</h1>

        <div class="search-sort-container">
            <div class="search-bar">
                <form action="albums.php" method="GET">
                    <label for="search">Search by Title:</label>
                    <input type="text" id="search" name="search"
                        value="<?php echo htmlspecialchars($search_query); ?>"
                        placeholder="Enter album title"
                        list="albumSuggestions"> <datalist id="albumSuggestions"></datalist> <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
                    <button type="submit">Search</button>
                </form>
            </div>
            <div class="sort-options">
                <form action="albums.php" method="GET">
                    <label for="sort_by">Sort By:</label>
                    <select id="sort_by" name="sort_by" onchange="this.form.submit()">
                        <option value="title_asc" <?php if ($sort_by == 'title_asc') echo 'selected'; ?>>Title (A-Z)</option>
                        <option value="title_desc" <?php if ($sort_by == 'title_desc') echo 'selected'; ?>>Title (Z-A)</option>
                        <option value="release_date_asc" <?php if ($sort_by == 'release_date_asc') echo 'selected'; ?>>Release Date (Oldest First)</option>
                        <option value="release_date_desc" <?php if ($sort_by == 'release_date_desc') echo 'selected'; ?>>Release Date (Newest First)</option>
                        <option value="rating_desc" <?php if ($sort_by == 'rating_desc') echo 'selected'; ?>>Rating (Highest First)</option>
                        <option value="rating_asc" <?php if ($sort_by == 'rating_asc') echo 'selected'; ?>>Rating (Lowest First)</option>
                    </select>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                </form>
            </div>
        </div>

        <div class="album-grid">
            <?php
            $result_albums = $conn->query($sql_albums);

            if ($result_albums === false) {
                $result_albums = (object)['num_rows' => 0];
            }

            if ($result_albums->num_rows > 0) {
                while ($row_album = $result_albums->fetch_assoc()) {
                    echo '<div class="album-card">';

                    if ($row_album['cover_blob'] && $row_album['cover_type']) {
                        echo '<img src="data:' . htmlspecialchars($row_album['cover_type']) . ';base64,' . base64_encode($row_album['cover_blob']) . '" alt="' . htmlspecialchars($row_album['title']) . ' Cover">';
                    } else {
                        echo '<img src="img/default_cover.png" alt="Default Album Cover">';
                    }
                    echo '<h3>' . htmlspecialchars($row_album['title']) . '</h3>';
                    echo '<p><span style="color: lightgray; font-weight: bold;">Artist:</span> ' . htmlspecialchars($row_album['artist']) . '</p>';

                    if ($row_album['average_rating'] !== null) {
                        echo '<p><span style="color: lightgray; font-weight: bold;">Rating:</span> ' . number_format($row_album['average_rating'], 2) . ' / 5</p>';
                    } else {
                        echo '<p><span style="color: lightgray; font-weight: bold;">Rating:</span> N/A</p>';
                    }

                    echo '<a href="this_album.php?album_id=' . $row_album['album_id'] . '">See this album</a>';
                    echo '</div>';
                }
            } else {
                echo "<p style='color: white;'>No albums found matching your criteria.</p>";
            }
            $conn->close();
            ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const suggestionsDatalist = document.getElementById('albumSuggestions');
            let timeout = null;

            searchInput.addEventListener('input', function() {
                clearTimeout(timeout);

                const query = this.value.trim();

                if (query.length < 2) {
                    suggestionsDatalist.innerHTML = '';
                    return;
                }

                timeout = setTimeout(() => {
                    fetch('suggestions_albums.php?query=' + encodeURIComponent(query))
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok ' + response.statusText);
                            }
                            return response.json();
                        })
                        .then(data => {
                            suggestionsDatalist.innerHTML = '';
                            data.forEach(suggestion => {
                                const option = document.createElement('option');
                                option.value = suggestion;
                                suggestionsDatalist.appendChild(option);
                            });
                        })
                        .catch(error => {
                            console.error('Error fetching suggestions:', error);
                        });
                }, 300);
            });
        });
    </script>
</body>

</html>