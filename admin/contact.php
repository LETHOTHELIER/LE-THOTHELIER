<?php
require_once dirname(__FILE__) . '/lib.php';
cms_session_start();
cms_auth_check();
cms_strip_magic_quotes();

$config = data_read('config.json');
if (!$config) $config = array('contact' => array(), 'mentions' => array(), 'reseaux_sociaux' => array());

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action == 'save_contact') {
        $config['contact']['telephone'] = trim(isset($_POST['telephone']) ? $_POST['telephone'] : '');
        $config['contact']['email']     = trim(isset($_POST['email'])     ? $_POST['email']     : '');
        data_write('config.json', $config);
        update_all_pages($config);
        $msg = 'Coordonnees mises a jour sur toutes les pages.'; $msg_type = 'success';
    }

    if ($action == 'save_social') {
        $idx = isset($_POST['idx']) ? (int)$_POST['idx'] : -1;
        if ($idx >= 0 && isset($config['reseaux_sociaux'][$idx])) {
            $config['reseaux_sociaux'][$idx]['url'] = trim(isset($_POST['url']) ? $_POST['url'] : '');
            data_write('config.json', $config);
            update_contact_page($config);
            $msg = 'URL mise a jour.'; $msg_type = 'success';
        }
    }

    if ($action == 'delete_social') {
        $idx = isset($_POST['idx']) ? (int)$_POST['idx'] : -1;
        if ($idx >= 0) {
            $new_rs = array();
            for ($i = 0; $i < count($config['reseaux_sociaux']); $i++) {
                if ($i != $idx) $new_rs[] = $config['reseaux_sociaux'][$i];
            }
            $config['reseaux_sociaux'] = $new_rs;
            data_write('config.json', $config);
            update_contact_page($config);
            update_footer_social($config);
            $msg = 'Reseau supprime.'; $msg_type = 'success';
        }
    }

    if ($action == 'add_social') {
        $nom = trim(isset($_POST['nom']) ? $_POST['nom'] : '');
        $url = trim(isset($_POST['url_new']) ? $_POST['url_new'] : '');
        $icone_path = '';
        if (isset($_FILES['icone']) && $_FILES['icone']['error'] == 0) {
            $ext  = strtolower(substr($_FILES['icone']['name'], strrpos($_FILES['icone']['name'], '.') + 1));
            $ok   = array('png','jpg','jpeg','svg','gif');
            $ok_ext = false;
            for ($i = 0; $i < count($ok); $i++) { if ($ok[$i] == $ext) { $ok_ext = true; break; } }
            if ($ok_ext) {
                $tmp  = $_FILES['icone']['tmp_name'];
                $saved = false;
                if ($ext != 'svg' && function_exists('imagecreatefromjpeg')) {
                    $src_img = null;
                    if ($ext == 'jpg' || $ext == 'jpeg') $src_img = @imagecreatefromjpeg($tmp);
                    elseif ($ext == 'png') $src_img = @imagecreatefrompng($tmp);
                    elseif ($ext == 'gif') $src_img = @imagecreatefromgif($tmp);
                    if ($src_img) {
                        $info = getimagesize($tmp);
                        $dst  = imagecreatetruecolor(75, 75);
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                        $trans = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                        imagefill($dst, 0, 0, $trans);
                        imagecopyresampled($dst, $src_img, 0, 0, 0, 0, 75, 75, $info[0], $info[1]);
                        $fn_png = 'logo_' . strtolower(preg_replace('/[^a-z0-9]/', '', strtolower($nom))) . '.png';
                        $dest   = dirname(dirname(__FILE__)) . '/assets/' . $fn_png;
                        if (imagepng($dst, $dest)) { $icone_path = '/assets/' . $fn_png; $saved = true; }
                        imagedestroy($src_img); imagedestroy($dst);
                    }
                }
                if (!$saved) {
                    $fn2  = 'logo_' . strtolower(preg_replace('/[^a-z0-9]/', '', strtolower($nom))) . '.' . $ext;
                    $dest = dirname(dirname(__FILE__)) . '/assets/' . $fn2;
                    if (move_uploaded_file($tmp, $dest)) $icone_path = '/assets/' . $fn2;
                }
            }
        }
        if ($nom && $url) {
            $config['reseaux_sociaux'][] = array('nom' => $nom, 'url' => $url, 'icone' => $icone_path);
            data_write('config.json', $config);
            update_contact_page($config);
            update_footer_social($config);
            $msg = 'Reseau "' . $nom . '" ajoute.'; $msg_type = 'success';
        } else { $msg = 'Nom et URL requis.'; $msg_type = 'error'; }
    }

    $config = data_read('config.json');
}

function update_html_file($filepath, $config) {
    if (!file_exists($filepath)) return;
    $fp   = fopen($filepath, 'r');
    $html = fread($fp, filesize($filepath));
    fclose($fp);

    $tel   = htmlspecialchars($config['contact']['telephone']);
    $email = htmlspecialchars($config['contact']['email']);
    $tel_digits = preg_replace('/[^0-9]/', '', $config['contact']['telephone']);

    // Footer tel text
    $html = preg_replace(
        '/(<a href="tel:[^"]*" class="footer-link">)[^<]*(<\/a>)/',
        '${1}' . $tel . '${2}', $html
    );
    // Footer tel href
    $html = preg_replace(
        '/href="tel:\+?33?[0-9]*" class="footer-link"/',
        'href="tel:+33' . ltrim($tel_digits, '0') . '" class="footer-link"', $html
    );
    // Footer email text
    $html = preg_replace(
        '/(<a href="mailto:[^"]*" class="footer-link">)[^<]*(<\/a>)/',
        '${1}' . $email . '${2}', $html
    );
    // Footer email href
    $html = preg_replace(
        '/href="mailto:[^"]*" class="footer-link"/',
        'href="mailto:' . $email . '" class="footer-link"', $html
    );

    $fp = fopen($filepath, 'w');
    fwrite($fp, $html);
    fclose($fp);
}

function update_contact_page($config) {
    $filepath = dirname(dirname(__FILE__)) . '/contact.html';
    if (!file_exists($filepath)) return;
    $fp   = fopen($filepath, 'r');
    $html = fread($fp, filesize($filepath));
    fclose($fp);

    $email = htmlspecialchars($config['contact']['email']);
    $tel   = htmlspecialchars($config['contact']['telephone']);
    $tel_digits = preg_replace('/[^0-9]/', '', $config['contact']['telephone']);

    // Email dans la page contact
    $html = preg_replace('/href="mailto:[^"]*" id="iic6n1-2"/', 'href="mailto:' . $email . '" id="iic6n1-2"', $html);
    $html = preg_replace('/(<a href="mailto:[^"]*" id="iic6n1-2">)[^<]*(<\/a>)/', '${1}' . $email . '${2}', $html);

    // Tel dans la page contact (texte brut entre balises)
    $html = preg_replace('/(<a[^>]*href="tel:[^"]*"[^>]*>)[^<]*(<\/a>)/', '${1}' . $tel . '${2}', $html);

    // Regenerer les liens reseaux sociaux (section contact)
    $social_html = "\n";
    $rs = isset($config['reseaux_sociaux']) ? $config['reseaux_sociaux'] : array();
    for ($i = 0; $i < count($rs); $i++) {
        $r = $rs[$i];
        $social_html .= '          <a href="' . htmlspecialchars($r['url']) . '" target="_blank" rel="noopener noreferrer">' . "\n";
        $social_html .= '            <img alt="' . htmlspecialchars($r['nom']) . '" src="' . htmlspecialchars($r['icone']) . '" style="width:75px;height:75px;object-fit:contain;margin:0 12px" />' . "\n";
        $social_html .= "          </a>\n";
    }
    // On cherche le div qui contient les liens sociaux dans contact.html (entre id="iviusm" parent)
    // Approche : remplacer les <a> qui contiennent des img de logo social
    // Cibler div#ii8af3 (bloc icones dans contact.html)
    $tag_start = strpos($html, 'id="ii8af3"');
    if ($tag_start !== false) {
        $tag_end   = strpos($html, '>', $tag_start);
        $close_div = strpos($html, '</div>', $tag_end);
        if ($close_div !== false) {
            $html = substr($html, 0, $tag_end + 1) . $social_html . substr($html, $close_div);
        }
    } else {
        // Fallback: remplacer liens avec images
        $html = preg_replace(
            '/(<a href="https?:\/\/[^"]*"[^>]*>\s*<img[^>]*\/?>\s*<\/a>\s*){1,}/s',
            $social_html,
            $html,
            1
        );
    }

    $fp = fopen($filepath, 'w');
    fwrite($fp, $html);
    fclose($fp);
}

function update_footer_social($config) {
    // Met a jour div#footer-social-icons dans toutes les pages HTML
    $root  = dirname(dirname(__FILE__)) . '/';
    $pages = array('index.html', 'index-desktop.html', 'galerie.html', 'contact.html', 'mentions-lgales.html');
    $rs = isset($config['reseaux_sociaux']) ? $config['reseaux_sociaux'] : array();
    // Construire le HTML des icones
    $footer_html = "\n";
    for ($i = 0; $i < count($rs); $i++) {
        $r = $rs[$i];
        $footer_html .= '          <a href="' . htmlspecialchars($r['url']) . '" target="_blank" rel="noopener noreferrer" class="social-link" aria-label="' . htmlspecialchars($r['nom']) . '">' . "\n";
        $footer_html .= '            <img src="' . htmlspecialchars($r['icone']) . '" alt="' . htmlspecialchars($r['nom']) . '" class="social-icon"/>' . "\n";
        $footer_html .= "          </a>\n";
    }
    for ($pi = 0; $pi < count($pages); $pi++) {
        $filepath = $root . $pages[$pi];
        if (!file_exists($filepath)) continue;
        $fp   = fopen($filepath, 'r');
        $html = fread($fp, filesize($filepath));
        fclose($fp);
        // Cibler div#footer-social-icons
        $tag_start = strpos($html, 'id="footer-social-icons"');
        if ($tag_start === false) {
            // Fallback: cibler div.footer-social
            $tag_start = strpos($html, 'class="footer-social"');
            if ($tag_start === false) continue;
        }
        $tag_end   = strpos($html, '>', $tag_start);
        $close_div = strpos($html, '</div>', $tag_end);
        if ($close_div === false) continue;
        $html = substr($html, 0, $tag_end + 1) . $footer_html . substr($html, $close_div);
        $fp = fopen($filepath, 'w');
        fwrite($fp, $html);
        fclose($fp);
    }
}

function update_all_pages($config) {
    $root  = dirname(dirname(__FILE__)) . '/';
    $pages = array('index.html', 'galerie.html', 'contact.html', 'mentions-lgales.html');
    for ($i = 0; $i < count($pages); $i++) {
        update_html_file($root . $pages[$i], $config);
    }
    update_contact_page($config);
    update_footer_social($config);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contact - CMS THOTHELIER</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&amp;family=Oswald:wght@400;700&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" type="text/css" href="cms.css" />
</head>
<body>
<?php include dirname(__FILE__) . '/header.php'; ?>
<div class="cms-body">
  <h2 class="section-title">Page Contact</h2>
  <div class="section-line"></div>

  <?php if ($msg) { echo '<div class="alert alert-' . $msg_type . '">' . $msg . '</div>'; } ?>

  <div class="card">
    <div class="card-title">Coordonnees</div>
    <form method="post" action="contact.php">
      <input type="hidden" name="action" value="save_contact" />
      <div class="form-row">
        <div class="form-half">
          <label>Telephone</label>
          <input type="tel" name="telephone" value="<?php echo h(isset($config['contact']['telephone']) ? $config['contact']['telephone'] : ''); ?>" />
        </div>
        <div class="form-half">
          <label>Email</label>
          <input type="email" name="email" value="<?php echo h(isset($config['contact']['email']) ? $config['contact']['email'] : ''); ?>" />
        </div>
      </div>
      <div class="clearfix"></div>
      <div style="text-align:right;margin-top:8px">
        <input type="submit" class="btn btn-primary" value="Enregistrer (met a jour toutes les pages)" />
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-title">Reseaux sociaux existants</div>
    <?php
    $rs = isset($config['reseaux_sociaux']) ? $config['reseaux_sociaux'] : array();
    for ($i = 0; $i < count($rs); $i++) {
        $r = $rs[$i]; ?>
    <div class="social-row clearfix">
      <?php if ($r['icone']) { echo '<img src="' . h($r['icone']) . '" alt="' . h($r['nom']) . '" />'; } ?>
      <span class="social-name"><?php echo h($r['nom']); ?></span>
      <form method="post" action="contact.php" style="display:inline">
        <input type="hidden" name="action" value="save_social" />
        <input type="hidden" name="idx" value="<?php echo $i; ?>" />
        <input type="text" name="url" value="<?php echo h($r['url']); ?>" style="width:300px;display:inline-block" />
        &nbsp;<input type="submit" class="btn btn-primary btn-sm" value="Sauvegarder" />
      </form>
      &nbsp;
      <form method="post" action="contact.php" style="display:inline" onsubmit="return confirm('Supprimer ce reseau ?')">
        <input type="hidden" name="action" value="delete_social" />
        <input type="hidden" name="idx" value="<?php echo $i; ?>" />
        <input type="submit" class="btn btn-danger btn-sm" value="Supprimer" />
      </form>
    </div>
    <?php } ?>
  </div>

  <div class="card">
    <div class="card-title">Ajouter un reseau social</div>
    <form method="post" action="contact.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_social" />
      <div class="form-row">
        <div class="form-half">
          <label>Nom du reseau</label>
          <input type="text" name="nom" placeholder="ex: TikTok" />
        </div>
        <div class="form-half">
          <label>URL du profil</label>
          <input type="url" name="url_new" placeholder="https://..." />
        </div>
      </div>
      <div class="clearfix"></div>
      <div style="margin-bottom:14px">
        <label>Icone (PNG, SVG, JPG - max 2 Mo)</label>
        <input type="file" name="icone" accept="image/*" style="color:#d9d9d9;margin-top:5px" />
      </div>
      <div style="text-align:right">
        <input type="submit" class="btn btn-primary" value="Ajouter ce reseau" />
      </div>
    </form>
  </div>

</div>
</body>
</html>
