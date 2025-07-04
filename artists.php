<?php
session_start();

require_once 'config.php';

$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sql_artists = "SELECT user_id, username, avatar_blob, avatar_type FROM users WHERE is_artist = 1";
$where_clauses = [];

if (!empty($search_query)) {
    $where_clauses[] = "username LIKE '%" . $search_query . "%'";
}

if (!empty($where_clauses)) {
    $sql_artists .= " AND " . implode(" AND ", $where_clauses);
}

$sql_artists .= " ORDER BY username ASC";

?>

<!DOCTYPE html>
<html lang="ro">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artists</title>
    <link rel="stylesheet" href="./styles/artists.css">
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
                    echo '<div class="avatar"><img src="img/default-avatar.jpg" alt="Avatar implicit"></div>';
                }
                ?>
            </div>
        </div>

        <h3 class="main-title">These are our artists</h3>
        <div class="search-sort-container">
            <div class="search-bar">
                <form action="artists.php" method="GET">
                    <label for="search">Search by Artist Name:</label>
                    <input type="text" id="search" name="search"
                        value="<?php echo htmlspecialchars($search_query); ?>"
                        placeholder="Enter artist name"
                        list="artistSuggestions"> <datalist id="artistSuggestions"></datalist> <button type="submit">Search</button>
                </form>
            </div>
        </div>

        <div class="artist-grid">
            <?php
            $result_artists = $conn->query($sql_artists);

            if ($result_artists === false) {
                echo "<p style='color: red;'>Error: " . htmlspecialchars($conn->error) . "</p>";
            } elseif ($result_artists->num_rows > 0) {
                while ($row_artist = $result_artists->fetch_assoc()) {
                    echo '<div class="artist-card">';
                    if ($row_artist['avatar_blob'] && $row_artist['avatar_type']) {
                        echo '<img src="data:' . htmlspecialchars($row_artist['avatar_type']) . ';base64,' . base64_encode($row_artist['avatar_blob']) . '" alt="' . htmlspecialchars($row_artist['username']) . '">';
                    }

                    echo '<p>' . htmlspecialchars($row_artist['username']) . '</p   >';
                    echo '<a href="this_artist.php?artist_id=' . $row_artist['user_id'] . '">See this artist</a>';
                    echo '</div>';
                }
            } else {
                echo "<p style='color: white;'>No artists found matching your criteria.</p>";
            }
            $conn->close();
            ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const suggestionsDatalist = document.getElementById('artistSuggestions');
            let timeout = null;

            searchInput.addEventListener('input', function() {
                clearTimeout(timeout);

                const query = this.value.trim();

                if (query.length < 2) {
                    suggestionsDatalist.innerHTML = '';
                    return;
                }

                timeout = setTimeout(() => {
                    fetch('suggestions_artists.php?query=' + encodeURIComponent(query))
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
                            console.error('Error fetching artist suggestions:', error);
                        });
                }, 300);
            });
        });
    </script>
</body>

</html>