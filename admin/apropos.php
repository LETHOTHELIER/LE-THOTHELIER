<?php
require_once dirname(__FILE__) . '/lib.php';
cms_session_start();
cms_auth_check();
cms_strip_magic_quotes();

$msg = '';
$msg_type = '';

// Traitement upload
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action == 'upload_photo') {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $tmp    = $_FILES['photo']['tmp_name'];
            $name   = $_FILES['photo']['name'];
            $ext    = strtolower(substr(strrchr($name, '.'), 1));
            $ok_ext = in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'));

            if (!$ok_ext) {
                $msg = 'Format invalide. Utilisez JPG, PNG, GIF ou WEBP.';
                $msg_type = 'error';
            } else {
                $dest     = dirname(dirname(__FILE__)) . '/assets/profil.' . $ext;
                $new_src  = '/assets/profil.' . $ext;

                // Redimensionnement GD si disponible
                $saved = false;
                if ($ext != 'svg' && function_exists('imagecreatefromjpeg')) {
                    $info = getimagesize($tmp);
                    $src_img = null;
                    if ($ext == 'jpg' || $ext == 'jpeg') $src_img = @imagecreatefromjpeg($tmp);
                    elseif ($ext == 'png') $src_img = @imagecreatefrompng($tmp);
                    elseif ($ext == 'gif') $src_img = @imagecreatefromgif($tmp);
                    if ($src_img && $info) {
                        $w = $info[0]; $h = $info[1];
                        $max = 500;
                        if ($w > $max || $h > $max) {
                            if ($w > $h) { $nh = round($h * $max / $w); $nw = $max; }
                            else         { $nw = round($w * $max / $h); $nh = $max; }
                        } else { $nw = $w; $nh = $h; }
                        $dst = imagecreatetruecolor($nw, $nh);
                        imagecopyresampled($dst, $src_img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        $dest_jpg = dirname(dirname(__FILE__)) . '/assets/profil.jpg';
                        $new_src  = '/assets/profil.jpg';
                        if (imagejpeg($dst, $dest_jpg, 90)) { $saved = true; }
                        imagedestroy($src_img); imagedestroy($dst);
                    }
                }
                if (!$saved) {
                    if (move_uploaded_file($tmp, $dest)) { $saved = true; }
                }

                if ($saved) {
                    // Mettre a jour src dans index.html et index-desktop.html
                    $files = array(
                        dirname(dirname(__FILE__)) . '/index.html',
                        dirname(dirname(__FILE__)) . '/index-desktop.html'
                    );
                    $updated = 0;
                    for ($fi = 0; $fi < count($files); $fi++) {
                        if (!file_exists($files[$fi])) continue;
                        $html = file_get_contents($files[$fi]);
                        if ($html === false) continue;
                        // Remplacer src de l'img ihuh61 - compatible PHP4
                        $html_new2 = $html;
                        $pos = strpos($html_new2, 'id="ihuh61"');
                        if ($pos !== false) {
                            $tag_start = strrpos(substr($html_new2, 0, $pos), '<img');
                            $tag_end   = strpos($html_new2, '>', $pos) + 1;
                            $old_tag   = substr($html_new2, $tag_start, $tag_end - $tag_start);
                            $new_tag   = eregi_replace('src="[^"]*"', 'src="' . $new_src . '"', $old_tag);
                            if ($new_tag) {
                                $html_new2 = substr($html_new2, 0, $tag_start) . $new_tag . substr($html_new2, $tag_end);
                            }
                        }
                        if ($html_new2 != $html) {
                            $fp = fopen($files[$fi], 'w');
                            fwrite($fp, $html_new2);
                            fclose($fp);
                            $updated++;
                        }
                    }
                    $msg = 'Photo mise a jour.' . ($updated > 0 ? ' Pages HTML regenerees (' . $updated . ').' : '');
                    $msg_type = 'success';
                } else {
                    $msg = 'Erreur lors de la sauvegarde.';
                    $msg_type = 'error';
                }
            }
        } else {
            $msg = 'Aucun fichier recu.';
            $msg_type = 'error';
        }
    }
}

// Trouver la photo actuelle
$current_photo = '/assets/profil.jpg';
$files_check = array('profil.jpg','profil.jpeg','profil.png','profil.gif','profil.webp');
for ($i = 0; $i < count($files_check); $i++) {
    if (file_exists(dirname(dirname(__FILE__)) . '/assets/' . $files_check[$i])) {
        $current_photo = '/assets/' . $files_check[$i];
        break;
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Photo A Propos - CMS THOTHELIER</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&amp;family=Oswald:wght@400;700&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" type="text/css" href="cms.css" />
</head>
<body>
<?php include dirname(__FILE__) . '/header.php'; ?>
<div class="cms-body">
  <h2 class="section-title">Photo &laquo;&nbsp;A Propos&nbsp;&raquo;</h2>
  <div class="section-line"></div>

  <?php if ($msg) { echo '<div class="alert alert-' . $msg_type . '">' . $msg . '</div>'; } ?>

  <div class="card" style="max-width:600px">
    <div class="card-title">Photo actuelle</div>
    <div style="text-align:center;margin:20px 0">
      <img src="<?php echo h($current_photo); ?>?v=<?php echo time(); ?>"
           alt="Photo A Propos actuelle"
           style="max-width:400px;max-height:400px;border-radius:140px 0 140px 0;border:2px solid #b86e44;object-fit:cover" />
    </div>
    <div style="text-align:center;color:#888;font-size:13px;margin-bottom:20px">
      Fichier actuel&nbsp;: <code><?php echo h($current_photo); ?></code>
    </div>

    <div class="card-title" style="margin-top:20px">Remplacer la photo</div>
    <form method="post" action="apropos.php" enctype="multipart/form-data" style="margin-top:14px">
      <input type="hidden" name="action" value="upload_photo" />
      <div class="form-row">
        <label>Nouvelle photo (JPG, PNG, GIF, WEBP &mdash; max 500px automatique)</label>
        <input type="file" name="photo" accept="image/*" style="margin-top:8px" />
      </div>
      <div class="clearfix" style="margin-top:16px">
        <input type="submit" class="btn btn-primary btn-right" value="Mettre a jour la photo" />
      </div>
    </form>
  </div>

  <div class="card" style="max-width:600px;margin-top:20px">
    <div class="card-title">Notes</div>
    <p style="color:#d9d9d9;font-size:14px;padding:10px">
      La photo sera automatiquement redimensionn&eacute;e &agrave; 500px maximum.<br/>
      Elle remplacera le fichier <code>profil.jpg</code> dans le dossier <code>/assets/</code>.<br/>
      Les pages <code>index.html</code> et <code>index-desktop.html</code> seront mises &agrave; jour automatiquement.
    </p>
  </div>

</div>
</body>
</html>
