<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;

// Sanity checks
$LAUNCH = LTIX::requireData();

$p = $CFG->dbprefix;

if ( isset($_GET["photo_id"]) ) {
    $stmt = $PDOX->prepare("UPDATE {$p}photo_gallery SET approved = :approved WHERE photo_id = :photoId");
    $stmt->execute(array(":approved" => 1, ":photoId" => $_GET["photo_id"]));

    $_SESSION['success'] = 'Photo approved';
}
header( 'Location: '.addSession('pending.php') ) ;
return;