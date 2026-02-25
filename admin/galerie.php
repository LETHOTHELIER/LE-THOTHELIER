<?php
require_once dirname(__FILE__) . '/lib.php';
cms_session_start();
cms_auth_check();
cms_strip_magic_quotes();

// Redimensionnement GD - max 1920px, qualite JPEG 85
// Gere JPEG, PNG, GIF, WEBP
// Corrige l'orientation EXIF automatiquement
function resize_image($src_path, $ext, $dest_path) {
    $max_w = 1920;
    $max_h = 1920;
    $quality = 85;

    $info = getimagesize($src_path);
    if (!$info) return false;
    $orig_w = $info[0];
    $orig_h = $info[1];

    // Charger l'image source selon le format
    $src_img = null;
    $ext_low = strtolower($ext);
    if ($ext_low == 'jpg' || $ext_low == 'jpeg') {
        $src_img = imagecreatefromjpeg($src_path);
    } elseif ($ext_low == 'png') {
        $src_img = imagecreatefrompng($src_path);
    } elseif ($ext_low == 'gif') {
        $src_img = imagecreatefromgif($src_path);
    } elseif ($ext_low == 'webp' && function_exists('imagecreatefromwebp')) {
        $src_img = imagecreatefromwebp($src_path);
    }
    if (!$src_img) return false;

    // Correction orientation EXIF (photos portrait depuis smartphone)
    if (function_exists('exif_read_data') && ($ext_low == 'jpg' || $ext_low == 'jpeg')) {
        $exif = @exif_read_data($src_path);
        if ($exif && isset($exif['Orientation'])) {
            $orient = $exif['Orientation'];
            if ($orient == 3) {
                $src_img = imagerotate($src_img, 180, 0);
            } elseif ($orient == 6) {
                $src_img = imagerotate($src_img, -90, 0);
                $tmp = $orig_w; $orig_w = $orig_h; $orig_h = $tmp;
            } elseif ($orient == 8) {
                $src_img = imagerotate($src_img, 90, 0);
                $tmp = $orig_w; $orig_w = $orig_h; $orig_h = $tmp;
            }
        }
    }

    // Calculer nouvelles dimensions en conservant le ratio
    if ($orig_w <= $max_w && $orig_h <= $max_h) {
        // Pas besoin de redimensionner, juste convertir en JPEG
        $new_w = $orig_w;
        $new_h = $orig_h;
    } else {
        $ratio_w = $max_w / $orig_w;
        $ratio_h = $max_h / $orig_h;
        $ratio   = min($ratio_w, $ratio_h);
        $new_w   = (int)($orig_w * $ratio);
        $new_h   = (int)($orig_h * $ratio);
    }

    // Creer image destination
    $dst_img = imagecreatetruecolor($new_w, $new_h);

    // Fond blanc pour les PNG transparents
    $white = imagecolorallocate($dst_img, 255, 255, 255);
    imagefill($dst_img, 0, 0, $white);

    // Redimensionner
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);

    // Sauvegarder en JPEG
    $ok = imagejpeg($dst_img, $dest_path, $quality);

    imagedestroy($src_img);
    imagedestroy($dst_img);

    return $ok;
}

function regenerate_galerie($galeries) {
    $html_file = dirname(dirname(__FILE__)) . '/galerie.html';
    if (!file_exists($html_file)) return;
    $fp = fopen($html_file, 'r');
    $html = fread($fp, filesize($html_file));
    fclose($fp);
    $js = "  var GALLERY_DATA = [\n";
    for ($gi = 0; $gi < count($galeries); $gi++) {
        $g = $galeries[$gi];
        $visible = $g['visible'] ? 'true' : 'false';
        $label = addslashes($g['label']);
        $js .= "    {\n      id: \"" . $g['id'] . "\",\n      label: \"" . $label . "\",\n      visible: " . $visible . ",\n      photos: [\n";
        $photos = isset($g['photos']) ? $g['photos'] : array();
        for ($pi = 0; $pi < count($photos); $pi++) {
            $p = $photos[$pi];
            $alt = isset($p['alt']) ? addslashes($p['alt']) : '';
            $js .= "        { src: \"" . addslashes($p['src']) . "\", alt: \"" . $alt . "\" },\n";
        }
        $js .= "      ]\n    },\n";
    }
    $js .= "  ];";
    $html = preg_replace('/var GALLERY_DATA = \[.*?\];/s', $js, $html);
    $fp = fopen($html_file, 'w');
    fwrite($fp, $html);
    fclose($fp);
}

$galeries = data_read('galeries.json');
if (!$galeries) $galeries = array();

// ============================================================
// SYNCHRO AUTOMATIQUE: disque <-> JSON
// Ajoute les photos presentes sur disque mais absentes du JSON
// Supprime du JSON les photos dont le fichier n'existe plus
// S'execute a chaque chargement du CMS
// ============================================================
$synchro_changed = false;
for ($gi = 0; $gi < count($galeries); $gi++) {
    $gal_id = $galeries[$gi]['id'];
    $dir = dirname(dirname(__FILE__)) . '/photos/' . $gal_id . '/';
    if (!is_dir($dir)) continue;

    // Lister les fichiers image sur disque
    $disk_files = array();
    $exts_ok = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    $handle = opendir($dir);
    while (($fn = readdir($handle)) !== false) {
        if ($fn == '.' || $fn == '..') continue;
        $ext = strtolower(substr(strrchr($fn, '.'), 1)); // extension sans point
        if (in_array($ext, $exts_ok)) {
            $disk_files[] = '/photos/' . $gal_id . '/' . $fn;
        }
    }
    closedir($handle);
    sort($disk_files);

    // Index des src actuellement dans le JSON
    $json_srcs = array();
    $photos = isset($galeries[$gi]['photos']) ? $galeries[$gi]['photos'] : array();
    for ($pi = 0; $pi < count($photos); $pi++) {
        $json_srcs[] = $photos[$pi]['src'];
    }

    // Ajouter les nouveaux fichiers (presents sur disque, absents du JSON)
    for ($di = 0; $di < count($disk_files); $di++) {
        $src = $disk_files[$di];
        $found = false;
        for ($pi = 0; $pi < count($json_srcs); $pi++) {
            if ($json_srcs[$pi] == $src) { $found = true; break; }
        }
        if (!$found) {
            $fn = basename($src);
            $alt = preg_replace('/\.[^.]+$/', '', $fn); // nom sans extension
            $galeries[$gi]['photos'][] = array('src' => $src, 'alt' => $alt);
            $synchro_changed = true;
        }
    }

    // Note: on ne supprime PAS du JSON les fichiers absents du disque ici
    // car file_exists() peut retourner false sur Free.fr juste apres un upload (cache NFS)
    // La suppression se fait uniquement via le bouton "Supprimer" du CMS
}
if ($synchro_changed) {
    data_write('galeries.json', $galeries);
    regenerate_galerie($galeries);
}
// ============================================================

// Force synchro manuelle
if (isset($_GET['action']) && $_GET['action'] == 'force_sync') {
    $force_changed = false;
    for ($gi = 0; $gi < count($galeries); $gi++) {
        $gal_id = $galeries[$gi]['id'];
        $dir = dirname(dirname(__FILE__)) . '/photos/' . $gal_id . '/';
        if (!is_dir($dir)) continue;
        $disk_files = array();
        $exts_ok = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        $handle = opendir($dir);
        while (($fn = readdir($handle)) !== false) {
            if ($fn == '.' || $fn == '..') continue;
            $ext = strtolower(substr(strrchr($fn, '.'), 1));
            if (in_array($ext, $exts_ok)) {
                $disk_files[] = '/photos/' . $gal_id . '/' . $fn;
            }
        }
        closedir($handle);
        sort($disk_files);
        $json_srcs = array();
        $photos = isset($galeries[$gi]['photos']) ? $galeries[$gi]['photos'] : array();
        for ($pi = 0; $pi < count($photos); $pi++) {
            $json_srcs[] = $photos[$pi]['src'];
        }
        for ($di = 0; $di < count($disk_files); $di++) {
            $src = $disk_files[$di];
            $found = false;
            for ($pi = 0; $pi < count($json_srcs); $pi++) {
                if ($json_srcs[$pi] == $src) { $found = true; break; }
            }
            if (!$found) {
                $fn = basename($src);
                $alt = preg_replace('/\.[^.]+$/', '', $fn);
                $galeries[$gi]['photos'][] = array('src' => $src, 'alt' => $alt);
                $force_changed = true;
            }
        }
    }
    data_write('galeries.json', $galeries);
    regenerate_galerie($galeries);
    $galeries = data_read('galeries.json');
    if (!$galeries) $galeries = array();
    $msg = 'Synchronisation forcee : galerie.html regeneree (' . ($force_changed ? 'nouvelles photos trouvees' : 'deja a jour') . ').';
    $msg_type = 'success';
}

$msg = '';
$msg_type = '';
$gal_active = isset($_GET['gal']) ? $_GET['gal'] : '';
if (!$gal_active && count($galeries) > 0) $gal_active = $galeries[0]['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
} else {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
}

// Upload photo en base64 (une par une, formulaire classique)
if ($action == 'upload_b64') {
    $gal_id  = isset($_POST['gal_id'])    ? $_POST['gal_id']    : '';
    $b64     = isset($_POST['photo_b64']) ? $_POST['photo_b64'] : '';
    $nom_ori = isset($_POST['photo_nom']) ? $_POST['photo_nom'] : 'photo';
    $reste   = isset($_POST['reste'])     ? (int)$_POST['reste'] : 0;

    $dest_dir = dirname(dirname(__FILE__)) . '/photos/' . $gal_id . '/';
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755);

    $ok = false;
    if ($b64 != '') {
        // Supprimer l'entete data:image/jpeg;base64,
        $pos = strpos($b64, ',');
        if ($pos !== false) $b64 = substr($b64, $pos + 1);
        $data = base64_decode($b64);
        if ($data) {
            // Nommer avec copyright si galerie deja renommee
            $use_copyright = false;
            $next_num = 1;
            for ($gi2 = 0; $gi2 < count($galeries); $gi2++) {
                if ($galeries[$gi2]['id'] != $gal_id) continue;
                $ex = isset($galeries[$gi2]['photos']) ? $galeries[$gi2]['photos'] : array();
                $next_num = count($ex) + 1;
                for ($px = 0; $px < count($ex); $px++) {
                    if (strpos(basename($ex[$px]['src']), 'copyright_thothelier') === 0) {
                        $use_copyright = true; break;
                    }
                }
            }
            if ($use_copyright) {
                $filename = 'copyright_thothelier_' . $gal_id . '_' . str_pad($next_num, 3, '0', STR_PAD_LEFT) . '.jpg';
            } else {
                $filename = 'photo_' . time() . '_' . rand(100,999) . '.jpg';
            }
            $dest_path = $dest_dir . $filename;
            $fp = fopen($dest_path, 'w');
            if ($fp) {
                fwrite($fp, $data);
                fclose($fp);
                $src = '/photos/' . $gal_id . '/' . $filename;
                $alt = substr($nom_ori, 0, strrpos($nom_ori, '.'));
                if (!$alt) $alt = $nom_ori;
                for ($gi = 0; $gi < count($galeries); $gi++) {
                    if ($galeries[$gi]['id'] == $gal_id) {
                        $galeries[$gi]['photos'][] = array('src' => $src, 'alt' => $alt);
                    }
                }
                data_write('galeries.json', $galeries);
                regenerate_galerie($galeries);
                $ok = true;
            }
        }
    }
    // S'il reste des photos a envoyer, on revient avec ?reste=N
    // Sinon on affiche le message final
    if ($reste > 0) {
        header('Location: galerie.php?gal=' . urlencode($gal_id) . '&reste=' . $reste);
        exit;
    }
    $msg = $ok ? 'Photo ajoutee.' : 'Erreur lors de l\'ajout.';
    $msg_type = $ok ? 'success' : 'error';
    $gal_active = $gal_id;
}

// Sauvegarder les noms (alt) des photos
if ($action == 'save_alts') {
    $gal_id   = isset($_POST['gal_id']) ? $_POST['gal_id'] : '';
    $srcs_str = isset($_POST['alts_srcs']) ? $_POST['alts_srcs'] : '';
    $alts_str = isset($_POST['alts_vals']) ? $_POST['alts_vals'] : '';
    if ($gal_id && $srcs_str) {
        $srcs = explode('|||', $srcs_str);
        $alts = explode('|||', $alts_str);
        for ($gi = 0; $gi < count($galeries); $gi++) {
            if ($galeries[$gi]['id'] != $gal_id) continue;
            for ($pi = 0; $pi < count($galeries[$gi]['photos']); $pi++) {
                $src = $galeries[$gi]['photos'][$pi]['src'];
                for ($si = 0; $si < count($srcs); $si++) {
                    if ($srcs[$si] != $src) continue;
                    $new_alt = isset($alts[$si]) ? trim($alts[$si]) : '';
                    $galeries[$gi]['photos'][$pi]['alt'] = $new_alt;
                    // Renommer aussi le fichier physique si alt non vide
                    if ($new_alt != '') {
                        $old_path = dirname(dirname(__FILE__)) . '/' . ltrim($src, '/');
                        if (file_exists($old_path)) {
                            $ext      = strtolower(substr(strrchr(basename($src), '.'), 1));
                            // Nettoyer le nom: espaces->underscores, suppr accents, caracteres speciaux
                            $clean    = preg_replace('/[^a-z0-9_-]/i', '_', $new_alt);
                            $clean    = preg_replace('/_+/', '_', trim($clean, '_'));
                            $clean    = strtolower($clean);
                            if ($clean == '') $clean = 'photo_' . ($pi + 1);
                            $new_name = $clean . '.' . $ext;
                            $dir      = dirname($old_path) . '/';
                            $new_path = $dir . $new_name;
                            // Eviter collision
                            $suffix = 0;
                            $base_clean = $clean;
                            while (file_exists($new_path) && $new_path != $old_path) {
                                $suffix++;
                                $new_name = $base_clean . '_' . $suffix . '.' . $ext;
                                $new_path = $dir . $new_name;
                            }
                            if ($new_path != $old_path && @copy($old_path, $new_path)) {
                                @unlink($old_path);
                                $new_src = '/photos/' . $gal_id . '/' . $new_name;
                                $galeries[$gi]['photos'][$pi]['src'] = $new_src;
                            }
                        }
                    }
                    break;
                }
            }
        }
        data_write('galeries.json', $galeries);
        regenerate_galerie($galeries);
        $msg = 'Noms sauvegardes et fichiers renommes.';
        $msg_type = 'success';
        $gal_active = $gal_id;
    }
}

// Renommer toutes les photos d'une galerie avec copyright
if ($action == 'rename_all') {
    $gal_id = isset($_POST['gal_id']) ? $_POST['gal_id'] : '';
    $rename_errors = 0;
    for ($gi = 0; $gi < count($galeries); $gi++) {
        if ($galeries[$gi]['id'] != $gal_id) continue;
        $photos = isset($galeries[$gi]['photos']) ? $galeries[$gi]['photos'] : array();
        $dir    = dirname(dirname(__FILE__)) . '/photos/' . $gal_id . '/';
        $prefix = 'copyright_thothelier_' . $gal_id . '_';
        // PASSE 1: renommer tout en _tmp_N pour eviter les collisions
        $tmp_paths = array();
        for ($pi = 0; $pi < count($photos); $pi++) {
            $src = $photos[$pi]['src'];
            $old_path = dirname(dirname(__FILE__)) . '/' . ltrim($src, '/');
            if (!file_exists($old_path)) { $tmp_paths[$pi] = null; continue; }
            $tmp_name = '_rename_tmp_' . $pi . '_' . time() . '.jpg';
            $tmp_path = $dir . $tmp_name;
            if (@copy($old_path, $tmp_path)) {
                @unlink($old_path);
                $tmp_paths[$pi] = $tmp_path;
            } else {
                $tmp_paths[$pi] = $old_path; // copie echouee, garder original
                $rename_errors++;
            }
        }
        // PASSE 2: renommer les _tmp en noms finaux
        $compteur  = 1;
        $photos_new = array();
        for ($pi = 0; $pi < count($photos); $pi++) {
            if ($tmp_paths[$pi] === null) { $photos_new[] = $photos[$pi]; continue; }
            $new_name = $prefix . str_pad($compteur, 3, '0', STR_PAD_LEFT) . '.jpg';
            $new_path = $dir . $new_name;
            $tmp_path = $tmp_paths[$pi];
            if ($tmp_path != $new_path) {
                if (@copy($tmp_path, $new_path)) {
                    @unlink($tmp_path);
                } else {
                    // echec: garder le fichier tmp et mettre a jour le src
                    $new_name = basename($tmp_path);
                    $new_path = $tmp_path;
                    $rename_errors++;
                }
            }
            $new_src = '/photos/' . $gal_id . '/' . $new_name;
            // Mettre a jour l'alt avec le nouveau nom de fichier lisible
            $new_alt = str_replace('_', ' ', preg_replace('/\.[^.]+$/', '', $new_name)); // nom lisible sans extension
            $photos_new[] = array('src' => $new_src, 'alt' => $new_alt);
            $compteur++;
        }
        $galeries[$gi]['photos'] = $photos_new;
    }
    data_write('galeries.json', $galeries);
    regenerate_galerie($galeries);
    // Recharger depuis le JSON pour que le CMS affiche les nouveaux noms
    $galeries = data_read('galeries.json');
    if (!$galeries) $galeries = array();
    if ($rename_errors > 0) {
        $msg = 'Renommage partiel (' . $rename_errors . ' erreur(s)). Verifiez les permissions du dossier photos.';
        $msg_type = 'error';
    } else {
        $msg = 'Photos renommees avec copyright.';
        $msg_type = 'success';
    }
    $gal_active = $gal_id;
}

// Renumerotation sans trous apres suppression
function renumeroter($galeries, $gal_id) {
    for ($gi = 0; $gi < count($galeries); $gi++) {
        if ($galeries[$gi]['id'] != $gal_id) continue;
        $photos = isset($galeries[$gi]['photos']) ? $galeries[$gi]['photos'] : array();
        $prefix = 'copyright_thothelier_' . $gal_id . '_';
        // Verifier si les photos utilisent deja le format copyright
        $has_copyright = false;
        for ($pi = 0; $pi < count($photos); $pi++) {
            if (strpos(basename($photos[$pi]['src']), 'copyright_thothelier') === 0) {
                $has_copyright = true; break;
            }
        }
        if (!$has_copyright) return $galeries; // pas encore renommees, ne pas toucher
        // Renumeroter pour combler les trous
        $compteur = 1;
        $photos_new = array();
        for ($pi = 0; $pi < count($photos); $pi++) {
            $src = $photos[$pi]['src'];
            $old_path = dirname(dirname(__FILE__)) . '/' . ltrim($src, '/');
            if (!file_exists($old_path)) continue;
            $new_name = $prefix . str_pad($compteur, 3, '0', STR_PAD_LEFT) . '.jpg';
            $new_path = dirname(dirname(__FILE__)) . '/photos/' . $gal_id . '/' . $new_name;
            if ($old_path != $new_path) rename($old_path, $new_path);
            $new_src = '/photos/' . $gal_id . '/' . $new_name;
            // Mettre a jour l'alt avec le nouveau nom de fichier lisible
            $new_alt = str_replace('_', ' ', preg_replace('/\.[^.]+$/', '', $new_name)); // nom lisible sans extension
            $photos_new[] = array('src' => $new_src, 'alt' => $new_alt);
            $compteur++;
        }
        $galeries[$gi]['photos'] = $photos_new;
    }
    return $galeries;
}

// Reordonner les photos (ordre base sur les src, pas les index)
if ($action == 'reorder') {
    $gal_id    = isset($_POST['gal_id'])  ? $_POST['gal_id']  : '';
    $ordre_str = isset($_POST['ordre'])   ? $_POST['ordre']   : '';
    if ($gal_id && $ordre_str) {
        // ordre_str = liste de src separes par ||| (evite les pb avec les virgules dans les noms)
        $srcs_ordre = explode('|||', $ordre_str);
        for ($gi = 0; $gi < count($galeries); $gi++) {
            if ($galeries[$gi]['id'] == $gal_id) {
                $photos_old = isset($galeries[$gi]['photos']) ? $galeries[$gi]['photos'] : array();
                // Indexer les photos par src pour retrouver facilement
                $by_src = array();
                for ($pi = 0; $pi < count($photos_old); $pi++) {
                    $by_src[$photos_old[$pi]['src']] = $photos_old[$pi];
                }
                $photos_new = array();
                for ($oi = 0; $oi < count($srcs_ordre); $oi++) {
                    $src = $srcs_ordre[$oi];
                    if (isset($by_src[$src])) $photos_new[] = $by_src[$src];
                }
                $galeries[$gi]['photos'] = $photos_new;
            }
        }
        data_write('galeries.json', $galeries);
        regenerate_galerie($galeries);
        $msg = 'Ordre sauvegarde.';
        $msg_type = 'success';
        $gal_active = $gal_id;
    }
}

// Deplacer photos vers une autre galerie
if ($action == 'move_photos') {
    $gal_src  = isset($_POST['gal_id'])   ? $_POST['gal_id']   : '';
    $gal_dest = isset($_POST['gal_dest']) ? $_POST['gal_dest'] : '';
    $srcs_str = isset($_POST['srcs'])     ? $_POST['srcs']     : '';
    if ($gal_src && $srcs_str) {
        // Suppression directe si destination = __delete__
        if ($gal_dest == '__delete__') {
            $srcs_del = explode('|||', $srcs_str);
            $idx_src = -1;
            for ($gi = 0; $gi < count($galeries); $gi++) {
                if ($galeries[$gi]['id'] == $gal_src) { $idx_src = $gi; break; }
            }
            if ($idx_src >= 0) {
                $photos_ok = array();
                $photos = isset($galeries[$idx_src]['photos']) ? $galeries[$idx_src]['photos'] : array();
                for ($pi = 0; $pi < count($photos); $pi++) {
                    $src = $photos[$pi]['src'];
                    $a_suppr = false;
                    for ($si = 0; $si < count($srcs_del); $si++) {
                        if ($srcs_del[$si] == $src) { $a_suppr = true; break; }
                    }
                    if ($a_suppr) {
                        $fpath = dirname(dirname(__FILE__)) . '/' . ltrim($src, '/');
                        if (file_exists($fpath)) @unlink($fpath);
                    } else {
                        $photos_ok[] = $photos[$pi];
                    }
                }
                $galeries[$idx_src]['photos'] = $photos_ok;
                data_write('galeries.json', $galeries);
                regenerate_galerie($galeries);
                $galeries = data_read('galeries.json');
                if (!$galeries) $galeries = array();
                $msg = count($srcs_del) . ' photo(s) supprimee(s).';
                $msg_type = 'success';
            }
        } else if ($gal_dest && $gal_src != $gal_dest) {
        $srcs_move = explode('|||', $srcs_str);
        $idx_src  = -1;
        $idx_dest = -1;
        for ($gi = 0; $gi < count($galeries); $gi++) {
            if ($galeries[$gi]['id'] == $gal_src)  $idx_src  = $gi;
            if ($galeries[$gi]['id'] == $gal_dest) $idx_dest = $gi;
        }
        if ($idx_src >= 0 && $idx_dest >= 0) {
            $photos_gardees = array();
            $photos_src = isset($galeries[$idx_src]['photos']) ? $galeries[$idx_src]['photos'] : array();
            for ($pi = 0; $pi < count($photos_src); $pi++) {
                $src = $photos_src[$pi]['src'];
                $a_deplacer = false;
                for ($si = 0; $si < count($srcs_move); $si++) {
                    if ($srcs_move[$si] == $src) { $a_deplacer = true; break; }
                }
                if ($a_deplacer) {
                    // Deplacer le fichier physiquement
                    $old_path = dirname(dirname(__FILE__)) . '/' . ltrim($src, '/');
                    $new_name = basename($src);
                    $new_dest_dir = dirname(dirname(__FILE__)) . '/photos/' . $gal_dest . '/';
                    if (!is_dir($new_dest_dir)) mkdir($new_dest_dir, 0755);
                    $new_path = $new_dest_dir . $new_name;
                    // Si fichier du meme nom existe deja dans dest, renommer
                    if (file_exists($new_path)) $new_path = $new_dest_dir . 'mv_' . time() . '_' . $new_name;
                    if (rename($old_path, $new_path)) {
                        $new_src = '/photos/' . $gal_dest . '/' . basename($new_path);
                        $galeries[$idx_dest]['photos'][] = array('src' => $new_src, 'alt' => $photos_src[$pi]['alt']);
                    } else {
                        $photos_gardees[] = $photos_src[$pi]; // echec rename, on garde
                    }
                } else {
                    $photos_gardees[] = $photos_src[$pi];
                }
            }
            $galeries[$idx_src]['photos'] = $photos_gardees;
            data_write('galeries.json', $galeries);
            regenerate_galerie($galeries);
            $galeries = data_read('galeries.json');
            if (!$galeries) $galeries = array();
            $nb = count($srcs_move) - (count($photos_gardees) - (count($photos_src) - count($srcs_move)));
            $msg = $nb . ' photo(s) deplacee(s) vers "' . $galeries[$idx_dest]['label'] . '".';
            $msg_type = 'success';
        }
        } // fin else deplacer
    }
    $gal_active = $gal_src;
}

// Supprimer photo
if ($action == 'delete_photo') {
    $gal_id = isset($_GET['gal_id']) ? $_GET['gal_id'] : '';
    $src    = isset($_GET['src'])    ? urldecode($_GET['src']) : '';
    $filepath = dirname(dirname(__FILE__)) . '/' . ltrim($src, '/');
    if (file_exists($filepath)) unlink($filepath);
    for ($gi = 0; $gi < count($galeries); $gi++) {
        if ($galeries[$gi]['id'] == $gal_id) {
            $new_photos = array();
            for ($pi = 0; $pi < count($galeries[$gi]['photos']); $pi++) {
                if ($galeries[$gi]['photos'][$pi]['src'] != $src) $new_photos[] = $galeries[$gi]['photos'][$pi];
            }
            $galeries[$gi]['photos'] = $new_photos;
        }
    }
    data_write('galeries.json', $galeries);
    regenerate_galerie($galeries);
    $msg = 'Photo supprimee.'; $msg_type = 'success';
    $gal_active = $gal_id;
}

// Ajouter galerie
if ($action == 'add_galerie') {
    $new_id    = preg_replace('/[^a-z0-9_]/', '', strtolower(isset($_POST['new_id']) ? $_POST['new_id'] : ''));
    $new_label = trim(isset($_POST['new_label']) ? $_POST['new_label'] : '');
    if ($new_id && $new_label) {
        $exists = false;
        for ($gi = 0; $gi < count($galeries); $gi++) { if ($galeries[$gi]['id'] == $new_id) $exists = true; }
        if (!$exists) {
            $galeries[] = array('id' => $new_id, 'label' => $new_label, 'visible' => true, 'photos' => array());
            $new_dir = dirname(dirname(__FILE__)) . '/photos/' . $new_id . '/';
            if (!is_dir($new_dir)) mkdir($new_dir, 0755);
            data_write('galeries.json', $galeries);
            regenerate_galerie($galeries);
            $msg = 'Galerie "' . $new_label . '" creee.'; $msg_type = 'success';
            $gal_active = $new_id;
        } else { $msg = 'Cet identifiant existe deja.'; $msg_type = 'error'; }
    } else { $msg = 'Identifiant et titre requis.'; $msg_type = 'error'; }
}

// Supprimer galerie
if ($action == 'delete_galerie') {
    $gal_id = isset($_GET['gal_id']) ? $_GET['gal_id'] : '';
    $dir = dirname(dirname(__FILE__)) . '/photos/' . $gal_id . '/';
    if (is_dir($dir)) {
        $files = glob($dir . '*');
        if ($files) { for ($fi = 0; $fi < count($files); $fi++) unlink($files[$fi]); }
        rmdir($dir);
    }
    $new_gal = array();
    for ($gi = 0; $gi < count($galeries); $gi++) { if ($galeries[$gi]['id'] != $gal_id) $new_gal[] = $galeries[$gi]; }
    $galeries = $new_gal;
    data_write('galeries.json', $galeries);
    regenerate_galerie($galeries);
    $msg = 'Galerie supprimee.'; $msg_type = 'success';
    $gal_active = count($galeries) > 0 ? $galeries[0]['id'] : '';
}

// Toggle visibilite
if ($action == 'toggle_visible') {
    $gal_id = isset($_GET['gal_id']) ? $_GET['gal_id'] : '';
    for ($gi = 0; $gi < count($galeries); $gi++) {
        if ($galeries[$gi]['id'] == $gal_id) $galeries[$gi]['visible'] = !$galeries[$gi]['visible'];
    }
    data_write('galeries.json', $galeries);
    regenerate_galerie($galeries);
    $msg = 'Visibilite modifiee.'; $msg_type = 'success';
    $gal_active = $gal_id;
}


// Trouver galerie courante
$gal_courante = null;
for ($gi = 0; $gi < count($galeries); $gi++) {
    if ($galeries[$gi]['id'] == $gal_active) { $gal_courante = $galeries[$gi]; break; }
}
if (!$gal_courante && count($galeries) > 0) { $gal_courante = $galeries[0]; $gal_active = $gal_courante['id']; }


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Galerie - CMS THOTHELIER</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&amp;family=Oswald:wght@400;700&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" type="text/css" href="cms.css" />
</head>
<body>
<?php include dirname(__FILE__) . '/header.php'; ?>
<div class="cms-body">
  <h2 class="section-title">Gestion de la Galerie</h2>
  <div class="section-line"></div>

  <?php if ($msg) { echo '<div class="alert alert-' . $msg_type . '">' . $msg . '</div>'; } ?>

  <div class="gal-tabs">
    <?php for ($gi = 0; $gi < count($galeries); $gi++) {
        $g = $galeries[$gi];
        $active = ($g['id'] == $gal_active) ? ' active' : '';
        $nb = isset($g['photos']) ? count($g['photos']) : 0;
        $vis = $g['visible'] ? '' : ' (masquee)';
        echo '<a href="galerie.php?gal=' . h($g['id']) . '" class="gal-tab' . $active . '">' . h($g['label']) . $vis . ' (' . $nb . ')</a>';
    } ?>
  </div>

  <?php if ($gal_courante) {
      $photos = isset($gal_courante['photos']) ? $gal_courante['photos'] : array();
  ?>
  <div class="card">
    <div class="card-title">
      <?php echo h($gal_courante['label']); ?> &mdash; <?php echo count($photos); ?> photo(s)
      &nbsp;
      <a href="galerie.php?gal=<?php echo h($gal_active); ?>&amp;action=toggle_visible&amp;gal_id=<?php echo h($gal_courante['id']); ?>" class="btn btn-ghost btn-sm"><?php echo $gal_courante['visible'] ? 'Visible (cliquer pour masquer)' : 'Masquee (cliquer pour afficher)'; ?></a>
      &nbsp;
      <a href="galerie.php?action=delete_galerie&amp;gal_id=<?php echo h($gal_courante['id']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cette galerie et toutes ses photos ?')">Supprimer la galerie</a>
      <?php if (count($photos) > 0) { ?>
      &nbsp;
      <form method="post" action="galerie.php?gal=<?php echo h($gal_active); ?>" style="display:inline">
        <input type="hidden" name="action"  value="rename_all" />
        <input type="hidden" name="gal_id"  value="<?php echo h($gal_courante['id']); ?>" />
        <input type="submit" class="btn btn-ghost btn-sm" value="<?php echo chr(169); ?> Renommer toutes" onclick="return confirm('Renommer toutes les photos avec le copyright thothelier ?')" />
      </form>
      <?php } ?>
    </div>

    <!-- iframe cible : absorbe les soumissions sans recharger la page principale -->
    <iframe id="upload-target" name="upload-target" style="display:none"></iframe>

    <!-- Formulaire cible vers l'iframe -->
    <form id="frm-upload" method="post" action="galerie.php?gal=<?php echo h($gal_active); ?>" target="upload-target">
      <input type="hidden" name="action"    value="upload_b64" />
      <input type="hidden" name="gal_id"    value="<?php echo h($gal_courante['id']); ?>" />
      <input type="hidden" name="photo_b64" id="photo_b64" value="" />
      <input type="hidden" name="photo_nom" id="photo_nom" value="" />
    </form>

    <div class="upload-zone" id="drop-zone" onclick="document.getElementById('file-input').click()">
      <input type="file" id="file-input" accept="image/*" multiple="multiple" style="display:none" onchange="charger_fichiers(this.files)" />
      Cliquez ici ou <strong style="color:#b86e44">glissez vos photos</strong> dans cette zone<br />
      <span style="color:#888;font-size:11px">Toutes tailles acceptees - redimensionnement automatique.</span><br />
      <span id="file-lbl" style="color:#b86e44;font-size:11px;margin-top:6px;display:block"></span>
    </div>

    <div id="progress-zone" style="display:none;margin-top:10px;padding:10px;background:#1a1a1a;border:1px solid #2a2a2a;color:#888;font-size:12px">
      <span id="progress-txt">Preparation...</span>
      <div style="height:6px;background:#2a2a2a;margin-top:6px"><div id="progress-bar" style="height:6px;background:#b86e44;width:0%"></div></div>
    </div>

    <div style="text-align:right;margin-top:10px">
      <input type="button" id="btn-upload" class="btn btn-primary" value="Uploader les photos" onclick="lancer_upload()" disabled="disabled" />
    </div>

    <script type="text/javascript">
    var MAX_DIM = 1200;
    var QUALITE = 0.82;
    var fichiers_ok = [];
    var b64_cache   = [];

    window.onload = function() {
        var zone = document.getElementById('drop-zone');
        if (!zone) return;
        zone.ondragover = function(e) {
            e.preventDefault(); e.stopPropagation();
            zone.style.borderColor = '#b86e44';
            zone.style.background  = 'rgba(184,110,68,0.08)';
            return false;
        };
        zone.ondragleave = function(e) {
            e.preventDefault();
            zone.style.borderColor = '';
            zone.style.background  = '';
            return false;
        };
        zone.ondrop = function(e) {
            e.preventDefault(); e.stopPropagation();
            zone.style.borderColor = '';
            zone.style.background  = '';
            var files = e.dataTransfer ? e.dataTransfer.files : null;
            if (files && files.length > 0) charger_fichiers(files);
            return false;
        };

        // Quand l'iframe a charge = PHP a fini de sauvegarder -> envoyer la suivante
        var iframe = document.getElementById('upload-target');
        iframe.onload = function() {
            if (b64_cache.length == 0) return; // pas d'upload en cours
            idx_courant++;
            if (idx_courant >= b64_cache.length) {
                // Tout envoye
                document.getElementById('progress-bar').style.width = '100%';
                document.getElementById('progress-txt').innerHTML = 'Termine ! ' + b64_cache.length + ' photo(s) ajoutee(s).';
                b64_cache = []; // vider pour ne plus reagir
                // Recharger la page pour afficher les nouvelles photos
                setTimeout(function() {
                    window.location.href = 'galerie.php?gal=<?php echo h($gal_active); ?>';
                }, 1000);
            } else {
                envoyer_une(idx_courant);
            }
        };
    };

    var idx_courant = 0;

    function charger_fichiers(files) {
        fichiers_ok = [];
        b64_cache   = [];
        var rejetes = 0;
        for (var i = 0; i < files.length; i++) {
            var t = files[i].type.toLowerCase();
            if (t == 'image/jpeg' || t == 'image/png' || t == 'image/gif' || t == 'image/webp') {
                fichiers_ok.push(files[i]);
            } else { rejetes++; }
        }
        var txt = fichiers_ok.length + ' photo(s) prete(s) a envoyer';
        if (rejetes > 0) txt += ' (' + rejetes + ' ignoree(s))';
        document.getElementById('file-lbl').innerHTML = txt;
        document.getElementById('btn-upload').disabled = (fichiers_ok.length == 0);
    }

    function lancer_upload() {
        if (fichiers_ok.length == 0) return;
        document.getElementById('btn-upload').disabled = true;
        document.getElementById('progress-zone').style.display = 'block';
        b64_cache   = [];
        idx_courant = 0;
        precalculer(0);
    }

    function precalculer(idx) {
        if (idx >= fichiers_ok.length) {
            envoyer_une(0);
            return;
        }
        var pct = Math.round(idx / fichiers_ok.length * 50);
        document.getElementById('progress-bar').style.width = pct + '%';
        document.getElementById('progress-txt').innerHTML = 'Preparation ' + (idx+1) + '/' + fichiers_ok.length;

        var file = fichiers_ok[idx];
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = new Image();
            img.onload = function() {
                var w = img.width, h = img.height;
                if (w > MAX_DIM || h > MAX_DIM) {
                    if (w > h) { h = Math.round(h * MAX_DIM / w); w = MAX_DIM; }
                    else       { w = Math.round(w * MAX_DIM / h); h = MAX_DIM; }
                }
                var canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                var ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);
                b64_cache[idx] = canvas.toDataURL('image/jpeg', QUALITE);
                precalculer(idx + 1);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    function marquer_modifie() {
        var btn = document.getElementById('btn-save-alts');
        if (btn) {
            btn.disabled = false;
            btn.style.background = '#b86e44';
            btn.style.color = '#000';
        }
    }

    // Selection automatique du texte entier au focus sur les champs nom photo
    document.addEventListener('focusin', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('photo-alt-input')) {
            e.target.select();
        }
    });
    // Empecher le draggable parent de capter les interactions souris sur les inputs
    document.addEventListener('mousedown', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('photo-alt-input')) {
            e.stopPropagation();
        }
    }, true);

    function sauvegarder_un_alt(inp) {
        var src = inp.getAttribute('data-src');
        var val = inp.value;
        var btn = inp.nextElementSibling;
        var old_bg = btn.style.background;
        btn.style.background = '#888';
        btn.disabled = true;

        // Mettre a jour le champ hidden du formulaire global pour ce src
        // ET soumettre directement via une requete iframe
        var form = document.createElement('form');
        form.method = 'post';
        form.action = 'galerie.php?gal=' + encodeURIComponent(document.getElementById('gal-id-hidden').value);
        form.target = 'upload-target';
        form.style.display = 'none';

        function addH(n,v){ var i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;form.appendChild(i); }
        addH('action', 'save_alts');
        addH('gal_id', document.getElementById('gal-id-hidden').value);
        addH('alts_srcs', src);
        addH('alts_vals', val);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        // Feedback visuel
        setTimeout(function(){
            btn.style.background = '#3a7a3a';
            btn.style.color = '#fff';
            btn.innerHTML = '&#10003;';
            btn.disabled = false;
            setTimeout(function(){
                btn.style.background = old_bg;
                btn.style.color = '#d9d9d9';
                btn.innerHTML = '&#10003;';
            }, 2000);
        }, 500);
    }

    function sauvegarder_alts() {
        var inputs = document.getElementsByClassName('photo-alt-input');
        var srcs = [], vals = [];
        for (var i = 0; i < inputs.length; i++) {
            srcs.push(inputs[i].getAttribute('data-src'));
            vals.push(inputs[i].value);
        }
        document.getElementById('alts-srcs').value = srcs.join('|||');
        document.getElementById('alts-vals').value = vals.join('|||');
        document.getElementById('frm-alts').submit();
    }

    function envoyer_une(idx) {
        idx_courant = idx;
        var pct = 50 + Math.round(idx / b64_cache.length * 50);
        document.getElementById('progress-bar').style.width = pct + '%';
        document.getElementById('progress-txt').innerHTML = 'Envoi ' + (idx+1) + '/' + b64_cache.length + ' : ' + fichiers_ok[idx].name;
        document.getElementById('photo_b64').value = b64_cache[idx];
        document.getElementById('photo_nom').value = fichiers_ok[idx].name;
        document.getElementById('frm-upload').submit();
    }
    </script>

    <?php if (count($photos) > 0) { ?>
    <p style="color:#555;font-size:11px;margin-top:16px;margin-bottom:6px">
      Glissez les vignettes pour changer l'ordre. Cochez-en plusieurs pour les deplacer ou supprimer.
    </p>

    <!-- Iframe + formulaire reorder silencieux -->
    <iframe id="reorder-target" name="reorder-target" style="display:none"></iframe>
    <form id="frm-reorder" method="post" action="galerie.php?gal=<?php echo h($gal_active); ?>" target="reorder-target">
      <input type="hidden" name="action" value="reorder" />
      <input type="hidden" name="gal_id" value="<?php echo h($gal_courante['id']); ?>" />
      <input type="hidden" name="ordre"  id="input-ordre" value="" />
    </form>

    <!-- Formulaire deplacement -->
    <form id="frm-move" method="post" action="galerie.php?gal=<?php echo h($gal_active); ?>">
      <input type="hidden" name="action"  value="move_photos" />
      <input type="hidden" name="gal_id"  value="<?php echo h($gal_courante['id']); ?>" />
      <input type="hidden" name="srcs"    id="move-srcs" value="" />
      <input type="hidden" name="gal_dest" id="move-dest" value="" />
    </form>

    <!-- Barre d'actions sur selection (masquee par defaut) -->
    <div id="barre-selection" style="display:none;margin-bottom:10px;padding:8px 12px;background:#1a1a1a;border:1px solid #2a2a2a;border-radius:3px">
      <span id="nb-selectionnes" style="color:#b86e44;font-weight:bold;margin-right:12px">0 selectionnee(s)</span>
      <?php if (count($galeries) > 1) { ?>
      Deplacer vers :
      <select id="select-dest" style="background:#222;color:#d9d9d9;border:1px solid #333;padding:4px 8px;margin:0 8px">
        <option value="">-- choisir --</option>
        <?php for ($gi = 0; $gi < count($galeries); $gi++) {
            $g = $galeries[$gi];
            if ($g['id'] == $gal_active) continue;
            echo '<option value="' . h($g['id']) . '">' . h($g['label']) . '</option>';
        } ?>
        <option value="__delete__" style="color:#e04444;font-weight:bold">&#128465; Supprimer les photos</option>
      </select>
      <input type="button" class="btn btn-ghost btn-sm" value="Deplacer" onclick="deplacer_selection()" />
      &nbsp;
      <?php } ?>
      <input type="button" class="btn btn-ghost btn-sm" value="Tout cocher" onclick="tout_cocher()" />
      &nbsp;
      <input type="button" class="btn btn-danger btn-sm" value="Tout decocher" onclick="tout_decocher()" />
      &nbsp; &nbsp;
      <a href="galerie.php?gal=<?php echo h($gal_active); ?>&action=force_sync" class="btn btn-warning btn-sm" style="text-decoration:none;display:inline-block;padding:4px 10px;background:#c87941;color:#fff;border-radius:4px;font-size:12px">&#8635; Forcer synchro</a>
    </div>

    <div class="photo-grid" id="photo-grid" style="margin-top:6px">
      <?php for ($pi = 0; $pi < count($photos); $pi++) {
          $p = $photos[$pi];
          $del_url = 'galerie.php?gal=' . h($gal_active) . '&action=delete_photo&gal_id=' . h($gal_courante['id']) . '&src=' . urlencode($p['src']);
      ?>
      <div class="photo-item" draggable="true" data-src="<?php echo h($p['src']); ?>">
        <img src="<?php echo h($p['src']); ?>" alt="<?php echo h(isset($p['alt']) ? $p['alt'] : ''); ?>" onclick="toggle_select(this.parentNode)" style="cursor:pointer" />
        <a href="<?php echo $del_url; ?>" class="photo-del" onclick="return confirm('Supprimer cette photo ?')">x</a>
        <div class="photo-check" style="display:none">&#10003;</div>
        <div style="display:flex;margin-top:4px;gap:3px" onmousedown="event.stopPropagation();event.preventDefault()" ondragstart="return false">
          <input type="text" class="photo-alt-input"
               data-src="<?php echo h($p['src']); ?>"
               value="<?php echo h(isset($p['alt']) ? $p['alt'] : ''); ?>"
               placeholder="Nom de la photo"
               ondragstart="return false"
               onmousedown="event.stopPropagation()"
               onclick="event.stopPropagation()"
               onfocus="this.select()"
               onchange="marquer_modifie()"
               style="flex:1;min-width:0;padding:3px 6px;background:#222;color:#d9d9d9;border:1px solid #555;border-radius:3px 0 0 3px;font-size:12px;cursor:text"
               />
          <button type="button"
               onmousedown="event.stopPropagation()"
               onclick="event.stopPropagation();sauvegarder_un_alt(this.previousElementSibling)"
               title="Valider ce nom"
               style="padding:3px 7px;background:#444;color:#d9d9d9;border:1px solid #555;border-left:none;border-radius:0 3px 3px 0;cursor:pointer;font-size:12px;flex-shrink:0">&#10003;</button>
        </div>
      </div>
      <?php } ?>
    </div>

    <script type="text/javascript">
    // -- Selection de photos --
    function toggle_select(item) {
        // Ne pas selectionner si on est en train de draguer
        if (item.getAttribute('data-dragging') == '1') return;
        var chk = item.getElementsByClassName('photo-check')[0];
        var sel = item.getAttribute('data-selected') == '1';
        if (sel) {
            item.setAttribute('data-selected', '0');
            item.style.outline = '';
            chk.style.display  = 'none';
        } else {
            item.setAttribute('data-selected', '1');
            item.style.outline = '3px solid #b86e44';
            chk.style.display  = 'block';
        }
        maj_barre();
    }

    function maj_barre() {
        var all = document.getElementById('photo-grid').getElementsByClassName('photo-item');
        var nb  = 0;
        for (var i = 0; i < all.length; i++) if (all[i].getAttribute('data-selected') == '1') nb++;
        var barre = document.getElementById('barre-selection');
        if (nb > 0) {
            barre.style.display = 'block';
            document.getElementById('nb-selectionnes').innerHTML = nb + ' photo(s) selectionnee(s)';
        } else {
            barre.style.display = 'none';
        }
    }

    function tout_cocher() {
        var all = document.getElementById('photo-grid').getElementsByClassName('photo-item');
        for (var i = 0; i < all.length; i++) {
            all[i].setAttribute('data-selected', '1');
            all[i].style.outline = '3px solid #b86e44';
            var chk = all[i].getElementsByClassName('photo-check')[0];
            if (chk) chk.style.display = 'block';
        }
        maj_barre();
    }

    function tout_decocher() {
        var all = document.getElementById('photo-grid').getElementsByClassName('photo-item');
        for (var i = 0; i < all.length; i++) {
            all[i].setAttribute('data-selected', '0');
            all[i].style.outline = '';
            var chk = all[i].getElementsByClassName('photo-check')[0];
            if (chk) chk.style.display = 'none';
        }
        maj_barre();
    }

    function deplacer_selection() {
        var dest = document.getElementById('select-dest').value;
        if (!dest) { alert('Choisissez une galerie de destination ou "Supprimer".'); return; }
        if (dest === '__delete__') {
            var n = document.querySelectorAll('.photo-item[data-selected="1"]').length;
            if (!confirm('Supprimer ' + n + ' photo(s) definitivement ?')) return;
        }
        var all  = document.getElementById('photo-grid').getElementsByClassName('photo-item');
        var srcs = [];
        for (var i = 0; i < all.length; i++) {
            if (all[i].getAttribute('data-selected') == '1') srcs.push(all[i].getAttribute('data-src'));
        }
        if (srcs.length == 0) return;
        if (!confirm('Deplacer ' + srcs.length + ' photo(s) vers cette galerie ?')) return;
        document.getElementById('move-srcs').value = srcs.join('|||');
        document.getElementById('move-dest').value = dest;
        document.getElementById('frm-move').submit();
    }

    // -- Drag & drop reorder --
    (function() {
        var grid     = document.getElementById('photo-grid');
        var items    = grid.getElementsByClassName('photo-item');
        var dragged  = null;
        var save_timer = null;

        // Appliquer les listeners a tous les items
        function init_drag() {
            var all = grid.getElementsByClassName('photo-item');
            for (var i = 0; i < all.length; i++) {
                all[i].ondragstart  = on_dragstart;
                all[i].ondragover   = on_dragover;
                all[i].ondragleave  = on_dragleave;
                all[i].ondrop       = on_drop;
                all[i].ondragend    = on_dragend;
            }
        }

        function on_dragstart(e) {
            dragged = this;
            this.setAttribute('data-dragging', '1');
            this.style.opacity = '0.4';
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', '');
        }
        function on_dragend(e) {
            var self = this;
            this.style.opacity = '';
            // Remettre l'outline de selection si la photo etait selectionnee
            var all = grid.getElementsByClassName('photo-item');
            for (var i = 0; i < all.length; i++) {
                if (all[i].getAttribute('data-selected') != '1') all[i].style.outline = '';
            }
            setTimeout(function() { self.setAttribute('data-dragging', '0'); }, 50);
        }
        function on_dragover(e) {
            e.preventDefault();
            if (this !== dragged) this.style.outline = '2px solid #b86e44';
            return false;
        }
        function on_dragleave(e) {
            this.style.outline = '';
        }
        function on_drop(e) {
            e.preventDefault(); e.stopPropagation();
            this.style.outline = '';
            if (this === dragged) return false;

            // Inserer dragged avant ou apres this selon la position
            var all_before = grid.getElementsByClassName('photo-item');
            var pos_dragged = -1, pos_target = -1;
            for (var i = 0; i < all_before.length; i++) {
                if (all_before[i] === dragged) pos_dragged = i;
                if (all_before[i] === this)    pos_target  = i;
            }
            if (pos_dragged < pos_target) {
                grid.insertBefore(dragged, this.nextSibling);
            } else {
                grid.insertBefore(dragged, this);
            }

            // Sauvegarder avec un delai pour eviter les soumissions multiples
            if (save_timer) clearTimeout(save_timer);
            save_timer = setTimeout(sauvegarder_ordre, 600);
            return false;
        }

        function sauvegarder_ordre() {
            var all = grid.getElementsByClassName('photo-item');
            var srcs = [];
            for (var i = 0; i < all.length; i++) srcs.push(all[i].getAttribute('data-src'));
            document.getElementById('input-ordre').value = srcs.join('|||');
            document.getElementById('frm-reorder').submit();
        }

        init_drag();
    })();
    </script>

    <?php if (count($photos) > 0) { ?>
    <input type="hidden" id="gal-id-hidden" value="<?php echo h($gal_active); ?>" />
      <form id="frm-alts" method="post" action="galerie.php?gal=<?php echo h($gal_active); ?>">
      <input type="hidden" name="action"    value="save_alts" />
      <input type="hidden" name="gal_id"    value="<?php echo h($gal_courante['id']); ?>" />
      <input type="hidden" name="alts_srcs" id="alts-srcs" value="" />
      <input type="hidden" name="alts_vals" id="alts-vals" value="" />
      <div style="text-align:right;margin-top:10px">
        <input type="button" id="btn-save-alts" class="btn btn-ghost" value="Sauvegarder les noms" onclick="sauvegarder_alts()" disabled="disabled" />
      </div>
    </form>
    <?php } ?>

    <?php } else { ?>
    <p style="color:#555;text-align:center;margin-top:20px;padding-bottom:10px">Aucune photo dans cette galerie.</p>
    <?php } ?>
  </div>
  <?php } ?>

  <div class="card">
    <div class="card-title">Creer une nouvelle galerie</div>
    <form method="post" action="galerie.php">
      <input type="hidden" name="action" value="add_galerie" />
      <div class="form-row">
        <div class="form-half">
          <label>Identifiant (minuscules, sans espaces)</label>
          <input type="text" name="new_id" placeholder="ex: custom_builds" />
        </div>
        <div class="form-half">
          <label>Titre affiche sur le site</label>
          <input type="text" name="new_label" placeholder="ex: Custom Builds" />
        </div>
      </div>
      <div class="clearfix"></div>
      <div style="text-align:right;margin-top:10px">
        <input type="submit" class="btn btn-primary" value="Creer la galerie" />
      </div>
    </form>
  </div>

</div>
</body>
</html>
