<?php
require_once dirname(__FILE__) . '/lib.php';
cms_session_start();
cms_auth_check();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CMS - Le THOTHELIER</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&amp;family=Oswald:wght@400;700&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" type="text/css" href="cms.css" />
  <link rel="icon" href="/assets/favicon.png" type="image/png" />
</head>
<body>
<?php include dirname(__FILE__) . '/header.php'; ?>
<div class="cms-body">
  <h2 class="section-title">Tableau de bord</h2>
  <div class="section-line"></div>

  <div class="dash-grid">
    <a href="tarifs.php" class="dash-card">
      <div class="icon">&#128182;</div>
      <h3>Tarifs</h3>
      <p>Modifier les prix, ajouter ou supprimer des lignes</p>
    </a>
    <a href="galerie.php" class="dash-card">
      <div class="icon">&#128444;</div>
      <h3>Galerie</h3>
      <p>Gerer les galeries photos</p>
    </a>
    <a href="contact.php" class="dash-card">
      <div class="icon">&#128222;</div>
      <h3>Contact</h3>
      <p>Tel, email, reseaux sociaux</p>
    </a>
    <a href="mentions.php" class="dash-card">
      <div class="icon">&#9878;</div>
      <h3>Mentions legales</h3>
      <p>Coordonnees et hebergeur</p>
    </a>
    <a href="mdp.php" class="dash-card">
      <div class="icon">&#128273;</div>
      <h3>Mot de passe</h3>
      <p>Changer le mot de passe CMS</p>
    </a>
  </div>
  <div class="clearfix"></div>

  <div class="info-box">
    <p>
      Bienvenue dans l'administration de <strong>Le THOTHELIER</strong>.<br />
      Toutes les modifications sont sauvegardees dans <code>_data/</code> et regenerent automatiquement les pages HTML du site.<br /><br />
      Hebergement test : <strong>ftpperso.free.fr</strong> (PHP 4.4) &mdash;
      Hebergement final : <strong>Netlify</strong> (deploiement via GitHub).
    </p>
  </div>
</div>
</body>
</html>
