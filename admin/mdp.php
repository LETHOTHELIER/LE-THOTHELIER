<?php
require_once dirname(__FILE__) . '/lib.php';
cms_session_start();
cms_auth_check();
cms_strip_magic_quotes();

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $actuel    = isset($_POST['actuel'])    ? $_POST['actuel']    : '';
    $nouveau   = isset($_POST['nouveau'])   ? $_POST['nouveau']   : '';
    $confirmer = isset($_POST['confirmer']) ? $_POST['confirmer'] : '';

    $hash = cms_get_password_hash();
    if (!cms_verify_password($actuel, $hash)) {
        $msg = 'Mot de passe actuel incorrect.'; $msg_type = 'error';
    } elseif (strlen($nouveau) < 8) {
        $msg = 'Le nouveau mot de passe doit faire au moins 8 caracteres.'; $msg_type = 'error';
    } elseif ($nouveau != $confirmer) {
        $msg = 'Les deux mots de passe ne correspondent pas.'; $msg_type = 'error';
    } else {
        $new_hash = cms_hash_password($nouveau);
        cms_save_password_hash($new_hash);
        $msg = 'Mot de passe modifie avec succes.'; $msg_type = 'success';
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mot de passe - CMS THOTHELIER</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&amp;family=Oswald:wght@400;700&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" type="text/css" href="cms.css" />
</head>
<body>
<?php include dirname(__FILE__) . '/header.php'; ?>
<div class="cms-body">
  <h2 class="section-title">Modifier le mot de passe</h2>
  <div class="section-line"></div>

  <?php if ($msg) { echo '<div class="alert alert-' . $msg_type . '">' . $msg . '</div>'; } ?>

  <div class="card" style="max-width:460px">
    <div class="card-title">Nouveau mot de passe</div>
    <form method="post" action="mdp.php">
      <div style="margin-bottom:13px">
        <label>Mot de passe actuel</label>
        <input type="password" name="actuel" />
      </div>
      <div style="margin-bottom:13px">
        <label>Nouveau mot de passe (8 caracteres minimum)</label>
        <input type="password" name="nouveau" />
      </div>
      <div style="margin-bottom:18px">
        <label>Confirmer le nouveau mot de passe</label>
        <input type="password" name="confirmer" />
      </div>
      <input type="submit" class="btn btn-primary" value="Changer le mot de passe" style="width:100%" />
    </form>
    <p style="color:#555;font-size:11px;margin-top:14px;text-align:center">
      Mot de passe par defaut (premier acces) : <strong style="color:#b86e44">thothelier2024</strong>
    </p>
  </div>

</div>
</body>
</html>
