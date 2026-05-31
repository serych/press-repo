<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/photos.php';
require_once __DIR__ . '/inc/published_photos.php';

require_login();

if (!has_permission('published_photos.upload')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$event = photos_get_current_event();
$user = current_user();
$errors = [];
$uploaded = [];
$isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

function upload_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float)$value;

    return match ($unit) {
        'g' => (int)($number * 1024 * 1024 * 1024),
        'm' => (int)($number * 1024 * 1024),
        'k' => (int)($number * 1024),
        default => (int)$number,
    };
}

function upload_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / 1024 / 1024, 0, ',', ' ') . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', ' ') . ' kB';
    }

    return $bytes . ' B';
}

$uploadMaxBytes = upload_ini_bytes((string)ini_get('upload_max_filesize'));
$postMaxBytes = upload_ini_bytes((string)ini_get('post_max_size'));
$effectiveMaxBytes = min(
    $uploadMaxBytes > 0 ? $uploadMaxBytes : PHP_INT_MAX,
    $postMaxBytes > 0 ? $postMaxBytes : PHP_INT_MAX
);

if (!$event) {
    $errors[] = 'Není vybraný aktivní event.';
}

if (is_post() && $event) {
    $files = $_FILES['photos'] ?? null;
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);

    if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
        $errors[] = 'Upload je větší než serverový limit post_max_size '
            . upload_format_bytes($postMaxBytes)
            . '. Aktuální požadavek má přibližně '
            . upload_format_bytes($contentLength)
            . '.';
    } elseif (!is_array($files) || empty($files['name'])) {
        $errors[] = 'Vyber alespoň jeden JPG soubor.';
    } else {
        $count = is_array($files['name']) ? count($files['name']) : 0;

        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
                && (string)($file['name'] ?? '') === ''
            ) {
                continue;
            }

            try {
                $uploaded[] = published_photos_store_upload($event, $user, $file);
            } catch (Throwable $e) {
                $errors[] = (string)$file['name'] . ': ' . $e->getMessage();
            }
        }

        if (!$uploaded && !$errors) {
            $errors[] = 'Nebyl nahrán žádný soubor.';
        }
    }
}

if (is_post() && $isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => $errors === [],
        'errors' => $errors,
        'uploaded' => array_map(
            static fn(array $item): array => [
                'id' => (int)$item['id'],
                'filename' => (string)$item['filename'],
                'paired' => !empty($item['source_photo']),
                'source_filename' => !empty($item['source_photo'])
                    ? (string)$item['source_photo']['filename']
                    : null,
            ],
            $uploaded
        ),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Nahrát hotové fotografie</h1>
        <a href="/photos.php" class="button">Zpět na fotografie</a>
    </div>

    <?php if ($event): ?>
        <p class="table-subtext">
            Event:
            <strong><?= h((string)$event['title']) ?></strong>
            <span class="badge badge-info"><?= h((string)$event['slug']) ?></span>
            <?php if ($effectiveMaxBytes !== PHP_INT_MAX): ?>
                <span class="badge badge-info">limit <?= h(upload_format_bytes($effectiveMaxBytes)) ?> / soubor</span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <div id="upload-messages">
    <?php if ($errors): ?>
        <div class="alert-error upload-result-box">
            <?php foreach ($errors as $error): ?>
                <div><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($uploaded): ?>
        <div class="upload-result-box">
            <?php foreach ($uploaded as $item): ?>
                <div class="upload-result <?= !empty($item['source_photo']) ? 'upload-result-paired' : 'upload-result-unpaired' ?>">
                    <?= h((string)$item['filename']) ?>
                    <?php if (!empty($item['source_photo'])): ?>
                        spárováno s <?= h((string)$item['source_photo']['filename']) ?>
                    <?php else: ?>
                        uloženo bez automatického spárování
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>

    <div class="card">
        <form method="post" enctype="multipart/form-data" class="form published-upload-form" id="published-upload-form">
            <label for="photos">JPG soubory</label>

            <label class="upload-dropzone" id="upload-dropzone" for="photos">
                <span class="upload-dropzone-title">Sem přetáhněte fotky</span>
                <span class="upload-dropzone-subtitle">nebo klikněte a vyberte JPG soubory</span>
                <span class="upload-dropzone-files" id="upload-file-summary">Zatím není vybraný žádný soubor</span>
            </label>

            <input class="upload-file-input" type="file" name="photos[]" id="photos" accept=".jpg,.jpeg,image/jpeg" multiple required>
            <?php if ($effectiveMaxBytes !== PHP_INT_MAX): ?>
                <p class="table-subtext">Aktuální serverový limit je <?= h(upload_format_bytes($effectiveMaxBytes)) ?> na jeden soubor.</p>
            <?php endif; ?>

            <div class="upload-progress" id="upload-progress" hidden>
                <div class="upload-progress-head">
                    <span id="upload-progress-label">Nahrávám...</span>
                    <strong>
                        <span id="upload-progress-file-count">0/0</span>
                        <span id="upload-progress-percent">0 %</span>
                    </strong>
                </div>
                <div class="upload-progress-track">
                    <div class="upload-progress-bar" id="upload-progress-bar"></div>
                </div>
            </div>

            <button type="submit">Nahrát hotové fotografie</button>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const maxFileBytes = <?= $effectiveMaxBytes === PHP_INT_MAX ? '0' : (int)$effectiveMaxBytes ?>;
    const form = document.getElementById('published-upload-form');
    const fileInput = document.getElementById('photos');
    const dropzone = document.getElementById('upload-dropzone');
    const fileSummary = document.getElementById('upload-file-summary');
    const progress = document.getElementById('upload-progress');
    const progressBar = document.getElementById('upload-progress-bar');
    const progressFileCount = document.getElementById('upload-progress-file-count');
    const progressPercent = document.getElementById('upload-progress-percent');
    const progressLabel = document.getElementById('upload-progress-label');
    const messages = document.getElementById('upload-messages');
    const submitButton = form ? form.querySelector('button[type="submit"]') : null;
    let uploadInProgress = false;
    let clearMessagesTimer = null;

    if (!form || !fileInput || !dropzone || !fileSummary || !progress || !progressBar || !progressFileCount || !progressPercent || !progressLabel || !messages) {
        return;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function updateFileSummary() {
        const count = fileInput.files ? fileInput.files.length : 0;
        if (count === 0) {
            fileSummary.textContent = 'Zatím není vybraný žádný soubor';
        } else if (count === 1) {
            fileSummary.textContent = fileInput.files[0].name;
        } else {
            fileSummary.textContent = count + ' souborů vybráno';
        }

        if (submitButton) {
            submitButton.disabled = count === 0;
        }

        form.classList.toggle('has-files', count > 0);
    }

    function scheduleResultCleanup() {
        if (clearMessagesTimer !== null) {
            window.clearTimeout(clearMessagesTimer);
        }

        clearMessagesTimer = window.setTimeout(function () {
            messages.innerHTML = '';
            clearMessagesTimer = null;
        }, 3000);
    }

    function setProgress(percent, label, counterText) {
        const clean = Math.max(0, Math.min(100, Math.round(percent)));
        progress.hidden = false;
        progressBar.style.width = clean + '%';
        progressFileCount.textContent = counterText || '0/0';
        progressPercent.textContent = clean + ' %';
        progressLabel.textContent = label;
    }

    function resetProgress() {
        progress.hidden = true;
        progressBar.style.width = '0%';
        progressFileCount.textContent = '0/0';
        progressPercent.textContent = '0 %';
        progressLabel.textContent = 'Nahrávám...';
    }

    function formatBytes(bytes) {
        const value = Number(bytes || 0);
        if (value >= 1024 * 1024 * 1024) {
            return (value / 1024 / 1024 / 1024).toFixed(1).replace('.', ',') + ' GB';
        }
        if (value >= 1024 * 1024) {
            return Math.round(value / 1024 / 1024) + ' MB';
        }
        if (value >= 1024) {
            return Math.round(value / 1024) + ' kB';
        }
        return value + ' B';
    }

    function renderResults(data) {
        if (clearMessagesTimer !== null) {
            window.clearTimeout(clearMessagesTimer);
            clearMessagesTimer = null;
        }

        let html = '';

        if (Array.isArray(data.errors) && data.errors.length > 0) {
            html += '<div class="alert-error upload-result-box">';
            data.errors.forEach(function (error) {
                html += '<div>' + escapeHtml(error) + '</div>';
            });
            html += '</div>';
        }

        if (Array.isArray(data.uploaded) && data.uploaded.length > 0) {
            html += '<div class="upload-result-box">';
            data.uploaded.forEach(function (item) {
                const cls = item.paired ? 'upload-result-paired' : 'upload-result-unpaired';
                html += '<div class="upload-result ' + cls + '">';
                html += escapeHtml(item.filename) + ' ';
                if (item.paired) {
                    html += 'spárováno s ' + escapeHtml(item.source_filename || '');
                } else {
                    html += 'uloženo bez automatického spárování';
                }
                html += '</div>';
            });
            html += '</div>';
        }

        messages.innerHTML = html;
    }

    window.addEventListener('beforeunload', function (event) {
        if (!uploadInProgress) {
            return;
        }

        event.preventDefault();
        event.returnValue = 'Chcete stránku opustit a tím přerušit upload?';
    });

    function uploadSingleFile(file, fileIndex, totalFiles) {
        return new Promise(function (resolve) {
            if (maxFileBytes > 0 && file.size > maxFileBytes) {
                resolve({
                    ok: false,
                    errors: [
                        file.name + ': Soubor je větší než serverový limit ' + formatBytes(maxFileBytes) + '.'
                    ],
                    uploaded: []
                });
                return;
            }

            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            const counterText = (fileIndex + 1) + '/' + totalFiles;

            formData.append('photos[]', file, file.name);

            xhr.upload.addEventListener('progress', function (event) {
                if (event.lengthComputable) {
                    const fileProgress = event.loaded / event.total;
                    const overallProgress = ((fileIndex + fileProgress) / totalFiles) * 100;
                    const label = fileProgress >= 0.999
                        ? 'Tvořím náhledy a páruji ' + file.name + '...'
                        : 'Nahrávám ' + file.name + '...';
                    setProgress(overallProgress, label, counterText);
                } else {
                    setProgress((fileIndex / totalFiles) * 100, 'Nahrávám ' + file.name + '...', counterText);
                }
            });

            xhr.addEventListener('load', function () {
                try {
                    const data = JSON.parse(xhr.responseText || '{}');
                    resolve({
                        ok: xhr.status >= 200 && xhr.status < 300 && data.ok === true,
                        errors: Array.isArray(data.errors) ? data.errors : [],
                        uploaded: Array.isArray(data.uploaded) ? data.uploaded : []
                    });
                } catch (e) {
                    resolve({
                        ok: false,
                        errors: [file.name + ': Server vrátil nečitelnou odpověď.'],
                        uploaded: []
                    });
                }
            });

            xhr.addEventListener('error', function () {
                resolve({
                    ok: false,
                    errors: [file.name + ': Upload se nepodařilo dokončit.'],
                    uploaded: []
                });
            });

            xhr.open('POST', form.action || window.location.href);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        });
    }

    fileInput.addEventListener('change', updateFileSummary);

    ['dragenter', 'dragover'].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (event) {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (event) {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');
        });
    });

    dropzone.addEventListener('drop', function (event) {
        if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length > 0) {
            fileInput.files = event.dataTransfer.files;
            updateFileSummary();
        }
    });

    form.addEventListener('submit', function (event) {
        if (!fileInput.files || fileInput.files.length === 0) {
            return;
        }

        event.preventDefault();

        const files = Array.from(fileInput.files);
        const results = {
            ok: true,
            errors: [],
            uploaded: []
        };

        messages.innerHTML = '';
        setProgress(0, 'Připravuji upload...', '0/' + files.length);
        form.classList.add('is-uploading');
        uploadInProgress = true;
        if (submitButton) {
            submitButton.disabled = true;
        }

        (async function () {
            for (let i = 0; i < files.length; i++) {
                const result = await uploadSingleFile(files[i], i, files.length);

                if (!result.ok || result.errors.length > 0) {
                    results.ok = false;
                }

                results.errors = results.errors.concat(result.errors);
                results.uploaded = results.uploaded.concat(result.uploaded);
                renderResults(results);
            }

            setProgress(100, 'Tvořím náhledy a páruji fotky...', files.length + '/' + files.length);
            progressFileCount.textContent = files.length + '/' + files.length;
            renderResults(results);
            progressLabel.textContent = results.ok ? 'Hotovo' : 'Dokončeno s chybou';

            if (submitButton) {
                submitButton.disabled = false;
            }

            if (results.ok) {
                form.reset();
                updateFileSummary();
            }

            form.classList.remove('is-uploading');
            uploadInProgress = false;
            resetProgress();

            if (results.ok) {
                scheduleResultCleanup();
            }
        })();
    });

    updateFileSummary();
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
