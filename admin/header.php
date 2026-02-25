<?php
$current_page = basename($_SERVER['PHP_SELF']);
function nav_active($page, $current) {
    return ($page == $current) ? ' class="active"' : '';
}
?>
<div class="cms-header">
  <div class="brand">
    <img src="/assets/THOTHELIER-LOGO-BLC-SUR-N.png" alt="Logo" />
    <div>
      <span class="brand-name">Le THOTHELIER</span>
      <span class="brand-sub">Administration</span>
    </div>
  </div>
  <div class="cms-nav">
    <a href="index.php"<?php echo nav_active('index.php', $current_page); ?>>Accueil</a>
    <a href="tarifs.php"<?php echo nav_active('tarifs.php', $current_page); ?>>Tarifs</a>
    <a href="apropos.php"<?php echo nav_active('apropos.php', $current_page); ?>>A Propos</a>
    <a href="galerie.php"<?php echo nav_active('galerie.php', $current_page); ?>>Galerie</a>
    <a href="contact.php"<?php echo nav_active('contact.php', $current_page); ?>>Contact</a>
    <a href="mentions.php"<?php echo nav_active('mentions.php', $current_page); ?>>Mentions</a>
    <a href="mdp.php"<?php echo nav_active('mdp.php', $current_page); ?>>Mot de passe</a>
    <a href="logout.php" class="logout">Deconnexion</a>
  </div>
</div>
