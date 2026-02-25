<?php
require_once dirname(__FILE__) . '/lib.php';
cms_session_start();

if (!empty($_SESSION['cms_auth'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mdp  = isset($_POST['password']) ? $_POST['password'] : '';
    $hash = cms_get_password_hash();
    if (cms_verify_password($mdp, $hash)) {
        $_SESSION['cms_auth'] = 1;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Mot de passe incorrect.';
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CMS - Le THOTHELIER</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&amp;family=Oswald:wght@400;700&amp;display=swap" rel="stylesheet" />
  <style type="text/css">
    * { margin:0; padding:0; }
    body {
      background: #0d0d0d url('/assets/PHOTO_ACCUEIL.jpg') center center no-repeat fixed;
      background-size: cover;
      display: table; width: 100%; height: 100%;
      font-family: 'Montserrat', sans-serif;
    }
    .wrap { display: table-cell; vertical-align: middle; text-align: center; padding: 40px 20px; }
    .login-box {
      display: inline-block; text-align: left;
      background: rgba(10,10,10,0.93);
      border: 1px solid #b86e44;
      border-radius: 12px;
      padding: 48px 40px 40px;
      width: 380px;
      box-shadow: 0 0 40px rgba(184,110,68,0.25);
    }
    .login-box img { display: block; margin: 0 auto 16px auto; width: 110px; }
    h1 {
      font-family: 'Oswald', sans-serif;
      color: #b86e44; font-size: 20px; letter-spacing: 3px;
      text-transform: uppercase; text-align: center; margin-bottom: 4px;
    }
    .subtitle { color: #888; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; text-align: center; margin-bottom: 32px; }
    label { display: block; color: #d9d9d9; font-size: 12px; letter-spacing: 1px; margin-bottom: 6px; }
    input { display: block; width: 100%; padding: 12px 14px; background: #1a1a1a; border: 1px solid #333; color: #d9d9d9; font-family: 'Montserrat', sans-serif; font-size: 14px; margin-bottom: 20px; box-sizing: border-box; -moz-box-sizing: border-box; }
    .oeil-wrap { position:relative; width:100%; box-sizing:border-box; -moz-box-sizing:border-box; }
    .btn { display: block; width: 100%; padding: 13px; background: #b86e44; border: none; color: #000; font-family: 'Montserrat', sans-serif; font-size: 14px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; cursor: pointer; }
    .error { background: rgba(200,60,60,0.15); border: 1px solid #c03c3c; color: #f09090; padding: 10px 14px; font-size: 13px; margin-bottom: 18px; }
  </style>
</head>
<body>
<div class="wrap">
<div class="login-box">
  <img src="/assets/THOTHELIER-LOGO-BLC-SUR-N.png" alt="Logo THOTHELIER" />
  <h1>Le THOTHELIER</h1>
  <p class="subtitle">Administration du site</p>
  <?php if ($error) { echo '<div class="error">' . h($error) . '</div>'; } ?>
  <form method="post" action="login.php">
    <label for="password">Mot de passe</label>
    <div class="oeil-wrap">
      <input type="password" id="password" name="password" style="padding-right:42px" />
      <span id="oeil" onclick="var i=document.getElementById('password');i.type=i.type=='password'?'text':'password';document.getElementById('oeil').innerHTML=i.type=='password'?'&#128065;':'&#128064;'" style="position:absolute;right:10px;top:8px;cursor:pointer;font-size:18px;color:#888">&#128065;</span>
    </div>
    <br />
    <input type="submit" class="btn" value="Acceder au CMS" />
  </form>
</div>
</div>
</body>
</html>
