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
    $m = isset($config['mentions']) ? $config['mentions'] : array();
    $m['telephone']           = trim(isset($_POST['telephone'])           ? $_POST['telephone']           : '');
    $m['email']               = trim(isset($_POST['email'])               ? $_POST['email']               : '');
    $m['hebergeur_nom']       = trim(isset($_POST['hebergeur_nom'])       ? $_POST['hebergeur_nom']       : '');
    $m['hebergeur_adresse']   = trim(isset($_POST['hebergeur_adresse'])   ? $_POST['hebergeur_adresse']   : '');
    $m['hebergeur_cp_ville']  = trim(isset($_POST['hebergeur_cp_ville'])  ? $_POST['hebergeur_cp_ville']  : '');
    $m['hebergeur_telephone'] = trim(isset($_POST['hebergeur_telephone']) ? $_POST['hebergeur_telephone'] : '');
    $m['hebergeur_site']      = trim(isset($_POST['hebergeur_site'])      ? $_POST['hebergeur_site']      : '');
    $m['hebergeur_site_affiche'] = trim(isset($_POST['hebergeur_site_affiche']) ? $_POST['hebergeur_site_affiche'] : '');
    $config['mentions'] = $m;
    data_write('config.json', $config);
    regenerate_mentions($config);
    $msg = 'Mentions legales mises a jour.'; $msg_type = 'success';
    $config = data_read('config.json');
}

function regenerate_mentions($config) {
    $html_file = dirname(dirname(__FILE__)) . '/mentions-lgales.html';
    if (!file_exists($html_file)) return;
    $fp   = fopen($html_file, 'r');
    $html = fread($fp, filesize($html_file));
    fclose($fp);

    $m = $config['mentions'];

    // Tel atelier (ligne brute dans le bloc editeur)
    $html = preg_replace('/07\s?\d{2}\s?\d{2}\s?\d{2}\s?\d{2}/', htmlspecialchars($m['telephone']), $html);

    // Emails
    $email = htmlspecialchars($m['email']);
    $html = preg_replace('/href="mailto:[^"]*" id="imanij"/', 'href="mailto:' . $email . '" id="imanij"', $html);
    $html = preg_replace('/(<a href="mailto:[^"]*" id="imanij">)[^<]*(<\/a>)/', '${1}' . $email . '${2}', $html);
    $html = preg_replace('/href="mailto:[^"]*" id="i52epo"/', 'href="mailto:' . $email . '" id="i52epo"', $html);
    $html = preg_replace('/href="mailto:[^"]*" id="icgvs5"/', 'href="mailto:' . $email . '" id="icgvs5"', $html);
    // Texte email inline
    $html = preg_replace('/(<a href="mailto:[^"]*" id="icgvs5">)[^<]*(<\/a>)/', '${1}' . $email . '${2}', $html);

    // Hebergeur nom
    $html = str_replace('IONOS SE', htmlspecialchars($m['hebergeur_nom']), $html);
    // Hebergeur adresse
    $html = str_replace('7 place de la Gare', htmlspecialchars($m['hebergeur_adresse']), $html);
    // Hebergeur CP ville
    $html = preg_replace('/57200 Sarreguemines[^<]*/', htmlspecialchars($m['hebergeur_cp_ville']), $html);
    // Hebergeur tel
    $html = str_replace('09 70 80 89 11', htmlspecialchars($m['hebergeur_telephone']), $html);
    // Hebergeur site URL et texte
    $html = preg_replace('/href="https?:\/\/www\.ionos\.fr"/', 'href="' . htmlspecialchars($m['hebergeur_site']) . '"', $html);
    $html = str_replace('www.ionos.fr', htmlspecialchars($m['hebergeur_site_affiche']), $html);

    $fp = fopen($html_file, 'w');
    fwrite($fp, $html);
    fclose($fp);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mentions legales - CMS THOTHELIER</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&amp;family=Oswald:wght@400;700&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" type="text/css" href="cms.css" />
</head>
<body>
<?php include dirname(__FILE__) . '/header.php'; ?>
<div class="cms-body">
  <h2 class="section-title">Mentions Legales</h2>
  <div class="section-line"></div>

  <?php if ($msg) { echo '<div class="alert alert-' . $msg_type . '">' . $msg . '</div>'; } ?>

  <?php $m = isset($config['mentions']) ? $config['mentions'] : array(); ?>
  <form method="post" action="mentions.php">
    <div class="card">
      <div class="card-title">Coordonnees de l'atelier</div>
      <div class="form-row">
        <div class="form-half">
          <label>Telephone</label>
          <input type="tel" name="telephone" value="<?php echo h(isset($m['telephone']) ? $m['telephone'] : ''); ?>" />
        </div>
        <div class="form-half">
          <label>Email</label>
          <input type="email" name="email" value="<?php echo h(isset($m['email']) ? $m['email'] : ''); ?>" />
        </div>
      </div>
      <div class="clearfix"></div>
    </div>

    <div class="card">
      <div class="card-title">Hebergeur du site</div>
      <div class="form-row">
        <div class="form-half">
          <label>Societe</label>
          <input type="text" name="hebergeur_nom" value="<?php echo h(isset($m['hebergeur_nom']) ? $m['hebergeur_nom'] : ''); ?>" />
        </div>
        <div class="form-half">
          <label>Adresse</label>
          <input type="text" name="hebergeur_adresse" value="<?php echo h(isset($m['hebergeur_adresse']) ? $m['hebergeur_adresse'] : ''); ?>" />
        </div>
      </div>
      <div class="form-row">
        <div class="form-half">
          <label>Code postal + Ville</label>
          <input type="text" name="hebergeur_cp_ville" value="<?php echo h(isset($m['hebergeur_cp_ville']) ? $m['hebergeur_cp_ville'] : ''); ?>" />
        </div>
        <div class="form-half">
          <label>Telephone hebergeur</label>
          <input type="tel" name="hebergeur_telephone" value="<?php echo h(isset($m['hebergeur_telephone']) ? $m['hebergeur_telephone'] : ''); ?>" />
        </div>
      </div>
      <div class="form-row">
        <div class="form-half">
          <label>URL du site hebergeur (avec https://)</label>
          <input type="text" name="hebergeur_site" value="<?php echo h(isset($m['hebergeur_site']) ? $m['hebergeur_site'] : ''); ?>" />
        </div>
        <div class="form-half">
          <label>Texte affiche du lien (ex: www.ionos.fr)</label>
          <input type="text" name="hebergeur_site_affiche" value="<?php echo h(isset($m['hebergeur_site_affiche']) ? $m['hebergeur_site_affiche'] : ''); ?>" />
        </div>
      </div>
      <div class="clearfix"></div>
    </div>

    <div style="text-align:right">
      <input type="submit" class="btn btn-primary" value="Enregistrer les mentions legales" style="padding:12px 28px" />
    </div>
  </form>

</div>
</body>
</html>
