<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/ftp_replacement.php';

require_login();

$user = current_user();
if ((string)($user['role_code'] ?? '') === 'journalist') {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$errors = [];
$uploaded = [];
$isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
$effectiveMaxBytes = ftp_replacement_effective_upload_limit();
$allowedExtensions = implode(', ', array_map(static fn(string $ext): string => strtoupper($ext), ftp_replacement_allowed_extensions()));
$storage = null;

try {
    $storage = ftp_replacement_user_storage($user);
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

if (is_post() && $storage !== null) {
    $files = $_FILES['photos'] ?? null;
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMaxBytes = ftp_replacement_ini_bytes((string)ini_get('post_max_size'));

    if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
        $errors[] = 'Upload je větší než serverový limit post_max_size '
            . ftp_replacement_format_bytes($postMaxBytes)
            . '. Aktuální požadavek má přibližně '
            . ftp_replacement_format_bytes($contentLength)
            . '.';
    } elseif (!is_array($files) || empty($files['name'])) {
        $errors[] = 'Vyber alespoň jeden soubor.';
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
                $uploaded[] = ftp_replacement_store_completed_file($user ?? [], $file);
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
                'filename' => (string)$item['filename'],
                'ftp_user' => (string)$item['ftp_user'],
                'size' => ftp_replacement_format_bytes((int)$item['size']),
            ],
            $uploaded
        ),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel ftp-replacement-panel">
    <div class="page-head">
        <h1>Náhrada FTP uploadu</h1>
        <a href="/photos-status.php" class="button button-muted">Zpět na přehled</a>
    </div>

    <div class="ftp-replacement-intro">
        <div>
            <strong>Zdrojové fotky pro zpracování</strong>
            <p>
                Soubory se po dokončení uploadu uloží do tvého FTP účtu a následně je standardně zpracuje watcher.
            </p>
        </div>
        <?php if ($storage !== null): ?>
            <span class="badge badge-info">FTP účet <?= h((string)$storage['ftp_user']) ?></span>
        <?php endif; ?>
        <?php if ($effectiveMaxBytes !== PHP_INT_MAX): ?>
            <span class="badge badge-info">serverový limit <?= h(ftp_replacement_format_bytes($effectiveMaxBytes)) ?></span>
        <?php endif; ?>
    </div>

    <div id="ftp-upload-messages">
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
                <div class="upload-result ftp-upload-result-ok">
                    <?= h((string)$item['filename']) ?>
                    uloženo do FTP účtu <?= h((string)$item['ftp_user']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>

    <div class="card ftp-replacement-card">
        <form method="post" enctype="multipart/form-data" class="form ftp-replacement-form" id="ftp-replacement-form">
            <label for="photos">Zdrojové fotky</label>

            <label class="upload-dropzone ftp-replacement-dropzone" id="ftp-replacement-dropzone" for="photos">
                <span class="upload-dropzone-title">Sem přetáhni zdrojové fotky</span>
                <span class="upload-dropzone-subtitle">nebo klikni a vyber soubory z telefonu či počítače</span>
                <span class="upload-dropzone-files" id="ftp-replacement-file-summary">Zatím není vybraný žádný soubor</span>
            </label>

            <input
                class="upload-file-input"
                type="file"
                name="photos[]"
                id="photos"
                accept=".cr2,.cr3,.nef,.nrw,.arw,.sr2,.srf,.raf,.rw2,.orf,.dng,.pef,.iiq,.3fr,.jpg,.jpeg,image/jpeg"
                multiple
                required
            >

            <p class="table-subtext">
                Podporované formáty: <?= h($allowedExtensions) ?>.
                Fotky se v přehledu objeví až po zpracování watcherem.
            </p>

            <div class="upload-progress" id="ftp-replacement-progress" hidden>
                <div class="upload-progress-head">
                    <span id="ftp-replacement-progress-label">Nahrávám...</span>
                    <strong>
                        <span id="ftp-replacement-progress-file-count">0/0</span>
                        <span id="ftp-replacement-progress-percent">0 %</span>
                    </strong>
                </div>
                <div class="upload-progress-track ftp-replacement-progress-track">
                    <div class="upload-progress-bar ftp-replacement-progress-bar" id="ftp-replacement-progress-bar"></div>
                </div>
            </div>

            <button type="submit" <?= $storage === null ? 'disabled' : '' ?>>Nahrát zdrojové fotky</button>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const maxFileBytes = <?= $effectiveMaxBytes === PHP_INT_MAX ? '0' : (int)$effectiveMaxBytes ?>;
    const form = document.getElementById('ftp-replacement-form');
    const fileInput = document.getElementById('photos');
    const dropzone = document.getElementById('ftp-replacement-dropzone');
    const fileSummary = document.getElementById('ftp-replacement-file-summary');
    const progress = document.getElementById('ftp-replacement-progress');
    const progressBar = document.getElementById('ftp-replacement-progress-bar');
    const progressFileCount = document.getElementById('ftp-replacement-progress-file-count');
    const progressPercent = document.getElementById('ftp-replacement-progress-percent');
    const progressLabel = document.getElementById('ftp-replacement-progress-label');
    const messages = document.getElementById('ftp-upload-messages');

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

    function setProgress(percent, label, counterText) {
        const clean = Math.max(0, Math.min(100, Math.round(percent)));
        progress.hidden = false;
        progressBar.style.width = clean + '%';
        progressFileCount.textContent = counterText || '0/0';
        progressPercent.textContent = clean + ' %';
        progressLabel.textContent = label;
    }

    function renderResults(data) {
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
                html += '<div class="upload-result ftp-upload-result-ok">';
                html += escapeHtml(item.filename) + ' uloženo do FTP účtu ' + escapeHtml(item.ftp_user || '');
                if (item.size) {
                    html += ' (' + escapeHtml(item.size) + ')';
                }
                html += '</div>';
            });
            html += '</div>';
        }

        messages.innerHTML = html;
    }

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
                    setProgress(overallProgress, 'Nahrávám ' + file.name + '...', counterText);
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
        const submitButton = form.querySelector('button[type="submit"]');
        const results = {
            ok: true,
            errors: [],
            uploaded: []
        };

        messages.innerHTML = '';
        setProgress(0, 'Připravuji upload...', '0/' + files.length);
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

            setProgress(100, 'Předávám watcheru...', files.length + '/' + files.length);
            renderResults(results);
            progressLabel.textContent = results.ok ? 'Předáno ke zpracování' : 'Dokončeno s chybou';

            if (submitButton) {
                submitButton.disabled = false;
            }

            if (results.ok) {
                form.reset();
                updateFileSummary();
            }
        })();
    });

    updateFileSummary();
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
