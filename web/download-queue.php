<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/db.php';

require_login();

$user = current_user();
$userId = (int)$user['id'];
$pdo = db();

$jobId = (int)($_GET['job'] ?? 0);

?>
<html>
<head>
<meta charset="utf-8">
<title>Bulk download</title>
</head>
<body>

<h2>Bulk download</h2>

<div id="status">Initializing…</div>

<button onclick="start()">Start</button>
<button onclick="next()">Next</button>

<script>

let job = <?= $jobId ?>;
let running = false;

async function start() {
    running = true;
    next();
}

async function next() {

    if (!running) return;

    let r = await fetch('/api/download-job-status.php?job='+job);
    let s = await r.json();

    document.getElementById('status').innerText =
        "Downloaded "+s.downloaded+" / "+s.total;

    if (!s.next_item) {
        running = false;
        return;
    }

    let iframe = document.createElement('iframe');
    iframe.style.display='none';

    iframe.src =
        '/download-item.php?job='+job+
        '&item='+s.next_item;

    document.body.appendChild(iframe);

    setTimeout(next, 1500);
}

</script>

</body>
</html>