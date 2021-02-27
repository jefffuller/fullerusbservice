<?php

/*
 * Pheditor
 * PHP file editor
 * Hamid Samak
 * https://github.com/hamidsamak/pheditor
 * Release under MIT license
 */

define('PASSWORD', 'c7ad44cbad762a5da0a452f9e854fdc1e0e7a52a38015f23f3eab1d80b931dd472634dfac71cd34ebc35d16ab7fb8a90c81f975113d6c7538dc69dd8de9077ec');
define('EDITABLE_FORMATS', 'txt,php,htm,html,js,css,tpl,xml,md,push');
define('LOG_FILE', __DIR__ . DIRECTORY_SEPARATOR . '.phedlog');
define('SHOW_PHP_SELF', false);
define('SHOW_HIDDEN_FILES', false);
define('ACCESS_IP', '');
define('HISTORY_PATH', __DIR__ . DIRECTORY_SEPARATOR . '.phedhistory');
define('MAX_HISTORY_FILES', 5);
define('WORD_WRAP', true);

if (empty(ACCESS_IP) === false && ACCESS_IP != $_SERVER['REMOTE_ADDR'])
    die('Your IP address is not allowed to access this page.');

if (file_exists(LOG_FILE)) {
    $log = unserialize(file_get_contents(LOG_FILE));

    if (isset($log[$_SERVER['REMOTE_ADDR']]) && $log[$_SERVER['REMOTE_ADDR']]['num'] > 3 && time() - $log[$_SERVER['REMOTE_ADDR']]['time'] < 86400)
        die('This IP address is blocked due to unsuccessful login attempts.');

    foreach ($log as $key => $value)
        if (time() - $value['time'] > 86400) {
            unset($log[$key]);

            $log_updated = true;
        }

    if (isset($log_updated))
        file_put_contents(LOG_FILE, serialize($log));
}

session_start();

if (isset($_SESSION['pheditor_admin']) === false || $_SESSION['pheditor_admin'] !== true) {
    if (isset($_POST['pheditor_password']) && empty($_POST['pheditor_password']) === false)
        if (hash('sha512', $_POST['pheditor_password']) === PASSWORD) {
            $_SESSION['pheditor_admin'] = true;

            redirect();
        } else {
            $error = 'The entry password is not correct.';

            $log = file_exists(LOG_FILE) ? unserialize(file_get_contents(LOG_FILE)) : array();

            if (isset($log[$_SERVER['REMOTE_ADDR']]) === false)
                $log[$_SERVER['REMOTE_ADDR']] = array('num' => 0, 'time' => 0);

            $log[$_SERVER['REMOTE_ADDR']]['num'] += 1;
            $log[$_SERVER['REMOTE_ADDR']]['time'] = time();

            file_put_contents(LOG_FILE, serialize($log));
        }

    die('<title>FullerUSB</title><form method="post"><div style="text-align:center"><h1><a href="/index.php" target="_blank" title="FullerUSB Service Config Editor" style="color:#444;text-decoration:none" tabindex="3">FullerUSB</a></h1>' . (isset($error) ? '<p style="color:#dd0000">' . $error . '</p>' : null) . '<input id="pheditor_password" name="pheditor_password" type="password" value="" placeholder="Password&hellip;" tabindex="1"><br><br><input type="submit" value="Login" tabindex="2"></div></form><script type="text/javascript">document.getElementById("pheditor_password").focus();</script>');
}

if (isset($_GET['logout'])) {
    unset($_SESSION['pheditor_admin']);

    redirect();
}

if (isset($_POST['action'])) {
    if (isset($_POST['file']) && empty($_POST['file']) === false) {
        $formats = explode(',', EDITABLE_FORMATS);

        if (($position = strrpos($_POST['file'], '.')) !== false)
            $extension = substr($_POST['file'], $position + 1);
        else
            $extension = null;

        if (empty($extension) === false && in_array(strtolower($extension), $formats) !== true)
            die('INVALID_EDITABLE_FORMAT');

        if (strpos($_POST['file'], '../') !== false || strpos($_POST['file'], '..\'') !== false)
            die('INVALID_FILE_PATH');
    }

    switch ($_POST['action']) {
        case 'open':
            if (isset($_POST['file']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $_POST['file']))
                echo br2nl(highlight_string(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $_POST['file']), true));
            break;

        case 'save':
            $file = __DIR__ . DIRECTORY_SEPARATOR . $_POST['file'];

            if (isset($_POST['file']) && isset($_POST['data']) && (file_exists($file) === false || is_writable($file))) {
                file_to_history($file);

                file_put_contents($file, $_POST['data']);
                echo br2nl(highlight_string(file_get_contents($file), true));
            }
            break;

        case 'reload':
            echo files(__DIR__);
            break;

        case 'password':
            if (isset($_POST['password']) && empty($_POST['password']) === false) {
                $contents = file(__FILE__);

                foreach ($contents as $key => $line)
                    if (strpos($line, 'define(\'PASSWORD\'') !== false) {
                        $contents[$key] = "define('PASSWORD', '" . hash('sha512', $_POST['password']) . "');\n";

                        break;
                    }

                file_put_contents(__FILE__, implode($contents));

                echo 'Password changed successfully.';
            }
            break;

        case 'delete':
            if (isset($_POST['file']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $_POST['file'])) {
                file_to_history($_POST['file']);

                unlink(__DIR__ . DIRECTORY_SEPARATOR . $_POST['file']);
            }
            break;
    }

    exit;
}

function files($dir, $display = 'block') {
    $formats = explode(',', EDITABLE_FORMATS);

    $data = '<ul class="files list-group" style="display:' . $display . '">';
    $files = array_slice(scandir($dir), 2);

    asort($files);

    foreach ($files as $key => $file) {
        if ((SHOW_PHP_SELF === false && $dir . DIRECTORY_SEPARATOR . $file == __FILE__) || (SHOW_HIDDEN_FILES === false && substr($file, 0, 1) === '.'))
            continue;

        $writable = is_writable($dir . DIRECTORY_SEPARATOR . $file) ? 'writable' : 'non-writable';

        if (is_dir($dir . DIRECTORY_SEPARATOR . $file))
            $data .= '<li class="dir ' . $writable . ' list-group-item"><a href="javascript:void(0);" onclick="return expandDir(this);" data-dir="' . str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $dir . DIRECTORY_SEPARATOR . $file) . '">' . $file . '</a>' . files($dir . DIRECTORY_SEPARATOR . $file, 'none') . '</li>';
        else {
            $is_editable = strpos($file, '.') === false || in_array(substr($file, strrpos($file, '.') + 1), $formats);

            $data .= '<li class="file ' . $writable . ' ' . ($is_editable ? 'editable' : null) . ' list-group-item">';

            if ($is_editable === true) {
                $file_path = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $dir . DIRECTORY_SEPARATOR . $file);

                $data .= '<a href="#' . $file_path . '" onclick="return openFile(this);" data-file="' . $file_path . '">';
            }

            $data .= $file;

            if ($is_editable)
                $data .= '</a>';

            if ($writable === 'writable')
                $data .= ' <a href="javascript:void(0);" class="btn btn-sm btn-danger visible-on-hover float-right" onclick="return deleteFile(this);">Delete</a>';

            $data .= '</li>';
        }
    }
    
    $data .= '</ul>';

    return $data;
}

function br2nl($string) {
    $string = str_replace(array("\r\n", "\r", "\n"), '', $string);
    $string = str_replace('<br />', "\n", $string);

    return $string;
}

function redirect($address = null) {
    if (empty($address))
        $address = $_SERVER['PHP_SELF'];

    header('Location: ' . $address);
    exit;
}

function file_to_history($file) {
    if (is_numeric(MAX_HISTORY_FILES) && MAX_HISTORY_FILES > 0) {
        $file_dir = dirname($file);
        $file_name = basename($file);
        $file_history_dir = HISTORY_PATH . DIRECTORY_SEPARATOR . str_replace(__DIR__, '', $file_dir);

        foreach ([HISTORY_PATH, $file_history_dir] as $dir)
            if (file_exists($dir) === false || is_dir($dir) === false)
                mkdir($dir);

        $history_files = scandir($file_history_dir);

        foreach ($history_files as $key => $history_file)
            if (in_array($history_file, ['.', '..', '.DS_Store']))
                unset($history_files[$key]);

        $history_files = array_values($history_files);

        if (count($history_files) >= MAX_HISTORY_FILES)
            foreach ($history_files as $key => $history_file)
                if ($key < 1) {
                    unlink($file_history_dir . DIRECTORY_SEPARATOR . $history_file);
                    unset($history_files[$key]);
                } else
                    rename($file_history_dir . DIRECTORY_SEPARATOR . $history_file, $file_history_dir . DIRECTORY_SEPARATOR . $file_name . '.' . ($key - 1));

        copy($file, $file_history_dir . DIRECTORY_SEPARATOR . $file_name . '.' . count($history_files));
    }
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FullerUSB Service Config Editor</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
<style type="text/css">
h1, h1 a, h1 a:hover {
    margin: 0;
    padding: 0;
    color: #444;
    cursor: default;
    text-decoration: none;
}

.visible-on-hover {
    visibility: hidden;
}

li.file:hover .visible-on-hover {
    visibility: visible;
}
    
#editor {
    overflow: auto;
    white-space: pre<?php if (WORD_WRAP === true) print '-wrap'; ?>;
    padding: 5px 10px;
}
</style>
<script type="text/javascript">
var expandedDirs = [];

function id(id) {
    return document.getElementById(id);
}

function expandDir(element) {
    var ul = element.nextSibling;
    var dir = element.getAttribute("data-dir");
    
    if (ul.style.display == "none") {
        ul.style.display = "block";

        expandedDirs.push(dir);
    } else {
        ul.style.display = "none";

        for (var i in expandedDirs)
            if (expandedDirs[i] == dir)
                expandedDirs.splice(i, 1);
    }

    document.cookie = "phedExpDirs=" + expandedDirs.join("|");
}

function openFile(element) {
    var editor = id("editor");
    var file = element.getAttribute("data-file");

    editor.setAttribute("contenteditable", "false");

    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            editor.innerHTML = xhttp.responseText;
            editor.setAttribute("data-file", file);
            editor.setAttribute("contenteditable", element.parentNode.className.indexOf("non-writable") < 0);

            id("save").setAttribute("disabled", "");
            id("close").removeAttribute("disabled");

            id("status").innerHTML = file;
        }
    }
    xhttp.open("POST", "<?=$_SERVER['PHP_SELF']?>", true);
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=open&file=" + encodeURIComponent(file));
}

function saveFile() {
    var newFile;
    var editor = id("editor");
    var file = editor.getAttribute("data-file");

    editor.setAttribute("contenteditable", "false");
    editor.innerHTML = editor.innerHTML.replace(/<div>/gi, "").replace(/<\/div>/gi, "<br>").replace(/<br(\s*)\/*>/ig, "\n").replace(/&nbsp;/ig, " ");

    if (file.length < 1) {
        newFile = true;
        file = prompt("Please enter file name with full path", "new-file.php");
    } else
        newFile = false;

    if (file != null && file.length > 0) {
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (xhttp.readyState == 4 && xhttp.status == 200) {
                var save = id("save");

                editor.setAttribute("contenteditable", "true");
                editor.innerHTML = xhttp.responseText;

                save.setAttribute("disabled", "");
                reloadFiles();

                if (newFile == true) {
                    id("status").innerHTML = file;
                    editor.setAttribute("data-file", file);
                }
            }
        }
        xhttp.open("POST", "<?=$_SERVER['PHP_SELF']?>", true);
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhttp.send("action=save&file=" + encodeURIComponent(file) + "&data=" + encodeURIComponent(editor.textContent));
    } else {
        editor.setAttribute("contenteditable", "true");
        editor.focus();
    }
}

function reloadFiles() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            id("sidebar").innerHTML = xhttp.responseText;

            var dirs = id("sidebar").getElementsByTagName("a");

            for (var i = 0; i < dirs.length; i++)
                if (dirs[i].hasAttribute("data-dir") && dirs[i].getAttribute("data-dir"))
                    for (var j in expandedDirs)
                        if (dirs[i].getAttribute("data-dir") == expandedDirs[j]) {
                            dirs[i].click();

                            break;
                        }
        }
    }
    xhttp.open("POST", "<?=$_SERVER['PHP_SELF']?>", true);
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=reload");
}

function closeFile() {
    var save = id("save");
    var editor = id("editor");

    if (save.hasAttribute("disabled") == false && confirm("Discard changes?") == false)
        return false;

    editor.innerHTML = "";
    editor.setAttribute("data-file", "");
    editor.setAttribute("contenteditable", "true");

    save.setAttribute("disabled", "");
    id("close").setAttribute("disabled", "");

    id("status").innerHTML = "";
    window.location.hash = "";
}

function editorChange(event) {
    if (event.ctrlKey == false)
        id("save").removeAttribute("disabled");
}

function editorFocus(event) {
    var editor = id("editor");

    editor.innerHTML = escapeHtml(editor.textContent);
}

function changePassword() {
    var password = prompt("Please enter new password:");

    if (password != null && password.length > 0) {
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (xhttp.readyState == 4 && xhttp.status == 200)
                alert(xhttp.responseText);
        }
        xhttp.open("POST", "<?=$_SERVER['PHP_SELF']?>", true);
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhttp.send("action=password&password=" + password);
    }
}

function deleteFile(element) {
    if (confirm("Are you sure to delete this file?") != true)
        return false;

    var file = element.previousSibling.previousSibling.getAttribute("data-file");

    if (file != null && file.length > 0) {
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (xhttp.readyState == 4 && xhttp.status == 200) {
                reloadFiles();
            }
        }
        xhttp.open("POST", "<?=$_SERVER['PHP_SELF']?>", true);
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhttp.send("action=delete&file=" + encodeURIComponent(file));
    }
}

function escapeHtml(string) {
    var map = {"&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#039;" };

    return string.replace(/[&<>""]/g, function(index) { return map[index]; });
}

window.onload = function() {
    id("save").setAttribute("disabled", "");
    id("close").setAttribute("disabled", "");

    var dirs = id("sidebar").getElementsByTagName("a");
    var cookie = document.cookie.split(";");
    for (var i in cookie)
        if (cookie[i].indexOf("phedExpDirs=") > -1) {
            expandedDirs = cookie[i].substring(cookie[i].indexOf("=") + 1).split("|");

            break;
        }

    for (var i = 0; i < dirs.length; i++)
        if (dirs[i].hasAttribute("data-dir"))
            for (var j in expandedDirs)
                if (dirs[i].getAttribute("data-dir") == expandedDirs[j])
                    dirs[i].nextSibling.style.display = "block";

    if (window.location.hash.length > 1) {
        var hash = window.location.hash;
        var files = id("sidebar").getElementsByTagName("a");

        for (i in files)
            if (typeof files[i] == "object" && files[i].hasAttribute("data-file") && files[i].getAttribute("data-file") == hash.substring(1)) {
                files[i].click();

                break;
            }
    }

    window.onresize();
}

document.onkeydown = function(event) {
    if (event.ctrlKey == true)
        if (event.keyCode == 83) {
            event.preventDefault();

            id("save").focus();
            id("save").click();
        } else if (event.keyCode == 87) {
            event.preventDefault();

            id("close").click();
        }
}

window.onresize = function(){
    var editor = id("editor");

    if (window.innerWidth >= 720) {
        var height = window.innerHeight - editor.getBoundingClientRect().top - 20;
        editor.style.height = height + "px";
    } else
        editor.style.height = "";
};
</script>
</head>
<body>

<div class="container-fluid">

    <div class="row p-3">
        <div class="col-md-3">
            <h1><a href="/index.php">FullerUSB</a></h1>
        </div>
        <div class="col-md-9">
            <div class="float-left">
                <span id="status"></span>
                <button id="save" onclick="return saveFile();" class="btn btn-sm btn-success" disabled>Save</button>
                <button id="close" onclick="return closeFile();" class="btn btn-sm btn-danger" disabled>Close</button>
            </div>

            <div class="float-right">
                <a href="javascript:void(0);" onclick="return changePassword();" class="btn btn-sm btn-primary">Password</a> &nbsp; <a href="<?=$_SERVER['PHP_SELF']?>?logout=1" class="btn btn-sm btn-warning">Logout</a>
            </div>
        </div>
    </div>

    <div class="row p-3">
        <div class="col-md-3">
            <div id="sidebar"><?=files(__DIR__)?></div>
        </div>

        <div class="col-md-9">
            <div class="card">
                <div class="card-block">
                    <div id="editor" data-file="" contenteditable="true" onkeydown="return editorChange(event);" onfocus="return editorFocus(event);"></div>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>

