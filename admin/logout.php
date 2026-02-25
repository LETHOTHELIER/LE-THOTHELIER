<?php
require_once dirname(__FILE__) . '/lib.php';
cms_session_start();
session_destroy();
header('Location: login.php');
exit;
?>
