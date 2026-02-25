<?php
require_once dirname(__FILE__) . '/lib.php';
cms_session_start();
cms_auth_check();
cms_strip_magic_quotes();

$tarifs = data_read('tarifs.json');
if (!$tarifs) {
    $tarifs = array('forfaits' => array(), 'taux_horaires' => array());
}

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action  = isset($_POST['action'])  ? $_POST['action']  : '';
    $section = isset($_POST['section']) ? $_POST['section'] : '';
} else {
    $action  = isset($_GET['action'])  ? $_GET['action']  : '';
    $section = isset($_GET['section']) ? $_GET['section'] : '';
}

if ($action == 'save' && ($section == 'forfaits' || $section == 'taux_horaires')) {
    $ids     = isset($_POST['ids'])     ? $_POST['ids']     : array();
    $labels  = isset($_POST['labels'])  ? $_POST['labels']  : array();
    $prix    = isset($_POST['prix'])    ? $_POST['prix']    : array();
    $details = isset($_POST['details']) ? $_POST['details'] : array();
    $lines = array();
    for ($k = 0; $k < count($ids); $k++) {
        $lines[] = array(
            'id'     => $ids[$k],
            'label'  => $labels[$k],
            'prix'   => $prix[$k],
            'detail' => isset($details[$k]) ? $details[$k] : ''
        );
    }
    $tarifs[$section] = $lines;
    data_write('tarifs.json', $tarifs);
    regenerate_index($tarifs);
    $msg = 'Tarifs sauvegardes et pages mises a jour.';
    $msg_type = 'success';
}

if ($action == 'add' && ($section == 'forfaits' || $section == 'taux_horaires')) {
    $tarifs[$section][] = array('id' => 'ligne_' . time(), 'label' => 'Nouvelle prestation', 'prix' => '0 EUR', 'detail' => '');
    data_write('tarifs.json', $tarifs);
    $msg = 'Ligne ajoutee.';
    $msg_type = 'success';
}

if ($action == 'delete' && ($section == 'forfaits' || $section == 'taux_horaires')) {
    $idx = isset($_GET['idx']) ? (int)$_GET['idx'] : -1;
    if ($idx >= 0 && isset($tarifs[$section][$idx])) {
        $new_arr = array();
        for ($i = 0; $i < count($tarifs[$section]); $i++) {
            if ($i != $idx) $new_arr[] = $tarifs[$section][$i];
        }
        $tarifs[$section] = $new_arr;
        data_write('tarifs.json', $tarifs);
        regenerate_index($tarifs);
        $msg = 'Ligne supprimee.';
        $msg_type = 'success';
    }
}



function regenerate_index($tarifs) {
    $files = array(
        dirname(dirname(__FILE__)) . '/index.html',
        dirname(dirname(__FILE__)) . '/index-desktop.html'
    );
    $forfait_prix_ids = array(
        0=>'ir1oig-2', 1=>'ipo40b-2', 2=>'iitysh-2', 3=>'ist9d2-2', 4=>'i1a2hk-2'
    );
    $taux_prix_ids = array(
        0=>'i821f4-2', 1=>'iovq02-2', 2=>'iggao9-2', 3=>'ikcajj-2', 4=>'i69f9f-2'
    );
    $forfaits = isset($tarifs['forfaits'])      ? $tarifs['forfaits']      : array();
    $taux     = isset($tarifs['taux_horaires']) ? $tarifs['taux_horaires'] : array();

    for ($fi = 0; $fi < count($files); $fi++) {
        $html_file = $files[$fi];
        if (!file_exists($html_file)) continue;
        $fp = fopen($html_file, 'r');
        $html = fread($fp, filesize($html_file));
        fclose($fp);

        // Bloc forfaits complet
        $bloc = "\n";
        for ($i = 0; $i < count($forfaits); $i++) {
            $f = $forfaits[$i];
            $id_prix = isset($forfait_prix_ids[$i]) ? $forfait_prix_ids[$i] : ('fp-' . $i);
            $bloc .= '          &bull; ' . entites($f['label']) . ' : ';
            $bloc .= '<span id="' . $id_prix . '">' . entites($f['prix']) . '</span>';
            if (isset($f['detail']) && $f['detail'] != '') {
                $bloc .= "\n          <br/><span>" . entites($f['detail']) . '</span>';
            }
            $bloc .= "\n          <br/><br/>\n";
        }
        $html = preg_replace('/(<div id="ihevu7-2">).*?(<\/div>)/s', '${1}' . $bloc . '${2}', $html);

        // Bloc taux horaires complet
        $bloc2 = "\n";
        for ($i = 0; $i < count($taux); $i++) {
            $t = $taux[$i];
            $id_prix = isset($taux_prix_ids[$i]) ? $taux_prix_ids[$i] : ('tp-' . $i);
            $bloc2 .= '          &bull; ' . entites($t['label']) . ' : ';
            $bloc2 .= '<span id="' . $id_prix . '">' . entites($t['prix']) . '</span>';
            if (isset($t['detail']) && $t['detail'] != '') {
                $bloc2 .= ' <span>(' . entites($t['detail']) . ')</span>';
            }
            $bloc2 .= "\n          <br/><br/>\n";
        }
        $html = preg_replace('/(<div id="ir7zn6-2">).*?(<\/div>)/s', '${1}' . $bloc2 . '${2}', $html);

        $fp = fopen($html_file, 'w');
        fwrite($fp, $html);
        fclose($fp);
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tarifs - CMS THOTHELIER</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&amp;family=Oswald:wght@400;700&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" type="text/css" href="cms.css" />
</head>
<body>
<?php include dirname(__FILE__) . '/header.php'; ?>
<div class="cms-body">
  <h2 class="section-title">Gestion des Tarifs</h2>
  <div class="section-line"></div>

  <?php if ($msg) { echo '<div class="alert alert-' . $msg_type . '">' . $msg . '</div>'; } ?>

  <?php
  $sections = array(
      'forfaits'      => 'Forfaits courants',
      'taux_horaires' => "Taux horaires main d'oeuvre"
  );
  foreach ($sections as $section => $titre) {
      $lines = isset($tarifs[$section]) ? $tarifs[$section] : array();
  ?>
  <div class="card">
    <div class="card-title"><?php echo h($titre); ?></div>
    <form method="post" action="tarifs.php">
      <input type="hidden" name="action" value="save" />
      <input type="hidden" name="section" value="<?php echo $section; ?>" />
      <table class="table-tarifs" style="table-layout:fixed;width:100%">
        <thead>
          <tr>
            <th style="width:42%">Prestation</th>
            <th style="width:14%">Prix</th>
            <th style="width:34%">Detail (optionnel)</th>
            <th style="width:10%">Suppr.</th>
          </tr>
        </thead>
        <tbody>
          <?php for ($k = 0; $k < count($lines); $k++) { $l = $lines[$k]; ?>
          <tr>
            <td>
              <input type="hidden" name="ids[]" value="<?php echo h($l['id']); ?>" />
              <textarea name="labels[]" rows="2" style="resize:vertical;width:100%;box-sizing:border-box;min-width:0"><?php echo h($l['label']); ?></textarea>
            </td>
            <td><input type="text" name="prix[]" value="<?php echo h($l['prix']); ?>" style="width:100%;box-sizing:border-box;min-width:0" /></td>
            <td><input type="text" name="details[]" value="<?php echo h(isset($l['detail']) ? $l['detail'] : ''); ?>" style="width:100%;box-sizing:border-box;min-width:0" /></td>
            <td style="text-align:center">
              <a href="tarifs.php?action=delete&amp;section=<?php echo $section; ?>&amp;idx=<?php echo $k; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cette ligne ?')">X</a>
            </td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
      <div class="clearfix" style="margin-top:14px">
        <input type="submit" class="btn btn-primary btn-right" value="Enregistrer <?php echo h($titre); ?>" />
      </div>
    </form>
    <form method="post" action="tarifs.php" style="margin-top:8px;text-align:right">
      <input type="hidden" name="action" value="add" />
      <input type="hidden" name="section" value="<?php echo $section; ?>" />
      <input type="submit" class="btn btn-ghost" value="+ Ajouter une ligne" />
    </form>
  </div>
  <?php } ?>

</div>
</body>
</html>
