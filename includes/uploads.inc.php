<?php
/**
 * GDRCD — Helper per upload di immagini lato server.
 *
 * Fornisce:
 *  - gdrcd_validate_upload(): validazione di una voce $_FILES (errore, MIME, estensione, dimensione, getimagesize)
 *  - gdrcd_save_image():       move + ridimensionamento opzionale via GD
 *  - gdrcd_resize_image():     helper GD per ridimensionamento preservando aspect ratio
 *  - gdrcd_safe_filename():    sanitizzazione nome file (path traversal, unicode, lunghezza)
 *
 * Note:
 *  - Usa estensione GD (già disponibile in Dockerfile, vedi includes/required.php).
 *  - Il controllo dei permessi (SUPERUSER/MODERATOR/proprietario) NON è in carico
 *    a questa libreria: deve essere effettuato dall'handler chiamante.
 *  - In caso di errore le funzioni ritornano [false, '<messaggio errore>'] e il
 *    chiamante è libero di mostrare l'errore tramite il sistema di alert esistente.
 */

if (!defined('GDRCD_UPLOAD_DEFAULT_MAX_SIZE')) {
    /** Max upload size di default in byte (2 MB). */
    define('GDRCD_UPLOAD_DEFAULT_MAX_SIZE', 2 * 1024 * 1024);
}
if (!defined('GDRCD_UPLOAD_DEFAULT_MAX_W')) {
    define('GDRCD_UPLOAD_DEFAULT_MAX_W', 512);
}
if (!defined('GDRCD_UPLOAD_DEFAULT_MAX_H')) {
    define('GDRCD_UPLOAD_DEFAULT_MAX_H', 512);
}
if (!defined('GDRCD_UPLOAD_HARD_MAX_DIM')) {
    /** Limite hard sulle dimensioni dell'immagine sorgente per evitare decompression bombs. */
    define('GDRCD_UPLOAD_HARD_MAX_DIM', 8000);
}

/**
 * Mappa MIME image → estensione/i accettate.
 * @return array<string, string[]>
 */
function gdrcd_upload_allowed_mimes(): array
{
    return [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/gif'  => ['gif'],
        'image/webp' => ['webp'],
    ];
}

/**
 * Sanitizza un nome file: rimuove separatori di path, normalizza, abbassa, sostituisce
 * spazi con `_`, mantiene solo `[a-z0-9._-]`, comprime separatori multipli, tronca a 100 char.
 * Non determina l'estensione: il chiamante può forzarla via gdrcd_save_image() options.
 */
function gdrcd_safe_filename(string $name): string
{
    // Rimuovi qualsiasi componente di path
    $name = basename(str_replace(['\\', "\0"], ['/', ''], $name));

    // Normalizza unicode in ASCII quando possibile
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($converted !== false && $converted !== '') {
            $name = $converted;
        }
    }

    $name = strtolower($name);
    $name = str_replace(' ', '_', $name);
    // Mantieni solo caratteri sicuri
    $name = preg_replace('/[^a-z0-9._-]/', '', $name) ?? '';
    // Compatta sequenze ripetute di separatori
    $name = preg_replace('/[._-]{2,}/', '_', $name) ?? '';
    // Niente leading dot (no file nascosti)
    $name = ltrim($name, '.');

    if ($name === '') {
        $name = 'file_' . bin2hex(random_bytes(4));
    }

    if (strlen($name) > 100) {
        // Conserva estensione se presente
        $dot = strrpos($name, '.');
        if ($dot !== false && $dot > 0 && (strlen($name) - $dot) <= 8) {
            $ext  = substr($name, $dot);
            $stem = substr($name, 0, $dot);
            $name = substr($stem, 0, 100 - strlen($ext)) . $ext;
        } else {
            $name = substr($name, 0, 100);
        }
    }

    return $name;
}

/**
 * Valida una voce di $_FILES (immagine).
 *
 * Controlli effettuati:
 *  - Presenza struttura attesa e codice errore PHP
 *  - MIME via finfo (allow list image/jpeg|png|gif|webp)
 *  - Coerenza estensione ↔ MIME
 *  - Dimensione massima (default 2 MB, override via $opts['max_size'])
 *  - getimagesize() con dimensioni "ragionevoli" (≤ GDRCD_UPLOAD_HARD_MAX_DIM)
 *
 * @param array $fileEntry una voce di $_FILES (es. $_FILES['avatar'])
 * @param array $opts      ['max_size'=>int, 'allowed_mimes'=>array<mime,exts[]>]
 * @return array{0:bool,1:string} [$ok, $errorMessage|'']
 */
function gdrcd_validate_upload(array $fileEntry, array $opts = []): array
{
    $max_size = isset($opts['max_size']) ? (int)$opts['max_size'] : GDRCD_UPLOAD_DEFAULT_MAX_SIZE;
    $allowed  = isset($opts['allowed_mimes']) && is_array($opts['allowed_mimes'])
        ? $opts['allowed_mimes']
        : gdrcd_upload_allowed_mimes();

    // Struttura minima
    foreach (['tmp_name', 'name', 'size', 'error'] as $k) {
        if (!array_key_exists($k, $fileEntry)) {
            return [false, 'Upload non valido (struttura $_FILES incompleta).'];
        }
    }

    // Codice errore PHP
    $err = (int)$fileEntry['error'];
    if ($err !== UPLOAD_ERR_OK) {
        $map = [
            UPLOAD_ERR_INI_SIZE   => 'File troppo grande (limite server upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'File troppo grande (limite form).',
            UPLOAD_ERR_PARTIAL    => 'Upload interrotto: file ricevuto solo parzialmente.',
            UPLOAD_ERR_NO_FILE    => 'Nessun file selezionato.',
            UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea mancante sul server.',
            UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file su disco.',
            UPLOAD_ERR_EXTENSION  => 'Upload bloccato da un\'estensione PHP.',
        ];
        return [false, $map[$err] ?? ('Errore upload (codice ' . $err . ').')];
    }

    if (empty($fileEntry['tmp_name']) || !is_uploaded_file($fileEntry['tmp_name'])) {
        return [false, 'File temporaneo non riconosciuto come upload HTTP.'];
    }

    if ((int)$fileEntry['size'] <= 0) {
        return [false, 'File vuoto.'];
    }
    if ((int)$fileEntry['size'] > $max_size) {
        return [false, 'File troppo grande (max ' . (int)floor($max_size / 1024) . ' KB).'];
    }

    // MIME via finfo
    if (!function_exists('finfo_open')) {
        return [false, 'Estensione fileinfo non disponibile sul server.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return [false, 'Impossibile inizializzare finfo.'];
    }
    $mime = (string)finfo_file($finfo, $fileEntry['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        return [false, 'Tipo file non consentito (' . htmlspecialchars($mime) . ').'];
    }

    // Estensione coerente
    $ext = strtolower(pathinfo((string)$fileEntry['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed[$mime], true)) {
        return [false, 'Estensione "' . htmlspecialchars($ext) . '" incompatibile con il tipo file rilevato (' . htmlspecialchars($mime) . ').'];
    }

    // getimagesize per confermare che sia davvero un'immagine + dimensioni ragionevoli
    $info = @getimagesize($fileEntry['tmp_name']);
    if ($info === false || empty($info[0]) || empty($info[1])) {
        return [false, 'Il file non risulta essere un\'immagine valida.'];
    }
    if ($info[0] > GDRCD_UPLOAD_HARD_MAX_DIM || $info[1] > GDRCD_UPLOAD_HARD_MAX_DIM) {
        return [false, 'Dimensioni immagine eccessive (max ' . GDRCD_UPLOAD_HARD_MAX_DIM . 'px per lato).'];
    }

    return [true, ''];
}

/**
 * Ridimensiona un'immagine preservando l'aspect ratio.
 * Se l'immagine sorgente è già più piccola dei limiti, viene comunque copiata/re-encodata
 * in modo da normalizzare l'output (rimuove metadata sospetti).
 *
 * @param string $srcPath  percorso file sorgente leggibile (es. tmp_name)
 * @param string $destPath percorso file destinazione
 * @param int    $maxW     larghezza massima
 * @param int    $maxH     altezza massima
 * @param int    $quality  qualità jpeg/webp (1-100), default 85
 * @return bool true se ok, false in caso di errore
 */
function gdrcd_resize_image(string $srcPath, string $destPath, int $maxW, int $maxH, int $quality = 85): bool
{
    if (!is_readable($srcPath)) {
        return false;
    }
    $info = @getimagesize($srcPath);
    if ($info === false) {
        return false;
    }
    [$w, $h] = $info;
    $type = $info[2];

    $src = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $src = @imagecreatefromjpeg($srcPath);
            break;
        case IMAGETYPE_PNG:
            $src = @imagecreatefrompng($srcPath);
            break;
        case IMAGETYPE_GIF:
            $src = @imagecreatefromgif($srcPath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $src = @imagecreatefromwebp($srcPath);
            }
            break;
        default:
            return false;
    }
    if (!$src) {
        return false;
    }

    // Calcola scala preservando aspect ratio (no upscale)
    $ratio = min($maxW / $w, $maxH / $h, 1.0);
    $newW  = max(1, (int)round($w * $ratio));
    $newH  = max(1, (int)round($h * $ratio));

    $dst = imagecreatetruecolor($newW, $newH);
    if (!$dst) {
        imagedestroy($src);
        return false;
    }

    // Preserva trasparenza per PNG/GIF/WEBP
    if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    }

    if (!imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h)) {
        imagedestroy($src);
        imagedestroy($dst);
        return false;
    }

    $ok = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $ok = imagejpeg($dst, $destPath, max(1, min(100, $quality)));
            break;
        case IMAGETYPE_PNG:
            // PNG quality va da 0 (no compression) a 9; mappa quality 1-100 su 9-0
            $pngLevel = (int)round((100 - max(1, min(100, $quality))) / 100 * 9);
            $ok = imagepng($dst, $destPath, $pngLevel);
            break;
        case IMAGETYPE_GIF:
            $ok = imagegif($dst, $destPath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                $ok = imagewebp($dst, $destPath, max(1, min(100, $quality)));
            }
            break;
    }

    imagedestroy($src);
    imagedestroy($dst);
    return (bool)$ok;
}

/**
 * Salva un'immagine caricata: valida, sanitizza nome, ridimensiona/re-encoda se serve.
 *
 * $opts:
 *   - max_width    int  (default 512)
 *   - max_height   int  (default 512)
 *   - quality      int  (default 85, usato per jpeg/webp)
 *   - target_ext   string opzionale ('jpg'|'png'|'gif'|'webp'): forza re-encode a tale formato
 *   - max_size     int  byte (default 2 MB)
 *   - overwrite    bool (default false): se false e file esiste, fa fallire
 *
 * Effetti:
 *   - Crea la $destDir se non esiste (mkdir ricorsivo, 0755).
 *   - Su validazione fallita NON tocca la destinazione.
 *
 * @return array{0:bool,1:string} [$ok, $finalPath|$errorMessage]
 */
function gdrcd_save_image(array $fileEntry, string $destDir, string $filename, array $opts = []): array
{
    $maxW    = (int)($opts['max_width']  ?? GDRCD_UPLOAD_DEFAULT_MAX_W);
    $maxH    = (int)($opts['max_height'] ?? GDRCD_UPLOAD_DEFAULT_MAX_H);
    $quality = (int)($opts['quality']    ?? 85);
    $force   = isset($opts['target_ext']) ? strtolower((string)$opts['target_ext']) : '';
    $overwrite = (bool)($opts['overwrite'] ?? false);

    [$ok, $err] = gdrcd_validate_upload($fileEntry, [
        'max_size'      => (int)($opts['max_size'] ?? GDRCD_UPLOAD_DEFAULT_MAX_SIZE),
        'allowed_mimes' => $opts['allowed_mimes'] ?? gdrcd_upload_allowed_mimes(),
    ]);
    if (!$ok) {
        return [false, $err];
    }

    // Crea destDir se mancante
    if (!is_dir($destDir)) {
        if (!@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return [false, 'Impossibile creare la cartella di destinazione.'];
        }
    }
    if (!is_writable($destDir)) {
        return [false, 'Cartella di destinazione non scrivibile.'];
    }

    // Determina estensione finale
    $info = @getimagesize($fileEntry['tmp_name']);
    $srcExtFromMime = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    $srcExt = $info && isset($srcExtFromMime[$info[2]]) ? $srcExtFromMime[$info[2]] : 'jpg';
    $finalExt = $force !== '' ? $force : $srcExt;
    $validExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($finalExt, $validExts, true)) {
        return [false, 'Estensione di destinazione non supportata: ' . htmlspecialchars($finalExt)];
    }

    // Sanitizza nome (senza estensione) e ricompone
    $stem = $filename;
    $dot  = strrpos($stem, '.');
    if ($dot !== false) {
        $stem = substr($stem, 0, $dot);
    }
    $stem = gdrcd_safe_filename($stem);
    $finalName = $stem . '.' . $finalExt;
    $finalPath = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $finalName;

    if (!$overwrite && file_exists($finalPath)) {
        return [false, 'Esiste già un file con lo stesso nome.'];
    }

    // Se forziamo un re-encode (target_ext diverso dal sorgente), useremo resize anche se
    // l'immagine è già abbastanza piccola, così l'output è normalizzato.
    $needResize = ($info && ($info[0] > $maxW || $info[1] > $maxH))
                || ($force !== '' && $force !== $srcExt);

    if ($needResize) {
        // Se l'estensione finale è diversa dal sorgente, dobbiamo decodificare nel formato
        // sorgente e ricodificare nel target. gdrcd_resize_image() mantiene il formato
        // sorgente, quindi gestiamo il caso "target diverso" qui sotto a mano.
        if ($force !== '' && $force !== $srcExt) {
            $tmpResized = $fileEntry['tmp_name']; // partiamo dal tmp originale
            // Carica nel formato sorgente
            $loaders = [
                'jpg' => 'imagecreatefromjpeg', 'jpeg' => 'imagecreatefromjpeg',
                'png' => 'imagecreatefrompng',  'gif'  => 'imagecreatefromgif',
                'webp'=> 'imagecreatefromwebp',
            ];
            $loader = $loaders[$srcExt] ?? null;
            if (!$loader || !function_exists($loader)) {
                return [false, 'Caricamento sorgente non supportato.'];
            }
            $src = @$loader($tmpResized);
            if (!$src) {
                return [false, 'Impossibile decodificare l\'immagine sorgente.'];
            }
            [$w, $h] = $info;
            $ratio = min($maxW / $w, $maxH / $h, 1.0);
            $newW  = max(1, (int)round($w * $ratio));
            $newH  = max(1, (int)round($h * $ratio));
            $dst = imagecreatetruecolor($newW, $newH);
            if (in_array($finalExt, ['png', 'gif', 'webp'], true)) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $tcol = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefilledrectangle($dst, 0, 0, $newW, $newH, $tcol);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

            $okSave = false;
            switch ($finalExt) {
                case 'jpg':
                case 'jpeg':
                    $okSave = imagejpeg($dst, $finalPath, max(1, min(100, $quality)));
                    break;
                case 'png':
                    $okSave = imagepng($dst, $finalPath, (int)round((100 - max(1, min(100, $quality))) / 100 * 9));
                    break;
                case 'gif':
                    $okSave = imagegif($dst, $finalPath);
                    break;
                case 'webp':
                    if (function_exists('imagewebp')) {
                        $okSave = imagewebp($dst, $finalPath, max(1, min(100, $quality)));
                    }
                    break;
            }
            imagedestroy($src);
            imagedestroy($dst);
            if (!$okSave) {
                return [false, 'Impossibile salvare l\'immagine ridimensionata.'];
            }
        } else {
            // Resize standard nel formato sorgente
            if (!gdrcd_resize_image($fileEntry['tmp_name'], $finalPath, $maxW, $maxH, $quality)) {
                return [false, 'Errore durante il ridimensionamento dell\'immagine.'];
            }
        }
    } else {
        // Nessun resize/re-encode necessario: usa move_uploaded_file
        if (!@move_uploaded_file($fileEntry['tmp_name'], $finalPath)) {
            return [false, 'Impossibile spostare il file caricato.'];
        }
    }

    // Permessi di lettura standard
    @chmod($finalPath, 0644);

    return [true, $finalPath];
}
