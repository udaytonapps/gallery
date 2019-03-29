<?php
require_once "../config.php";

use \Tsugi\Util\LTI;
use \Tsugi\UI\Output;
use \Tsugi\Core\LTIX;
use \Tsugi\Blob\BlobUtil;

$p = $CFG->dbprefix;

// Sanity checks
$LAUNCH = LTIX::requireData(array(LTIX::CONTEXT, LTIX::LINK));

$settingsStmt = $PDOX->prepare("SELECT approval FROM {$p}gallery_main WHERE link_id = :linkId");
$settingsStmt->execute(array(":linkId" => $LINK->id));
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

// View
$OUTPUT->header();

$photoST = $PDOX->prepare("SELECT * FROM {$p}blob_file WHERE file_id = :fileId");
$photoST->execute(array(":fileId" => $_GET['id']));
$photo = $photoST->fetch(PDO::FETCH_ASSOC);

$galleryST = $PDOX->prepare("SELECT * FROM {$p}photo_gallery WHERE blob_id = :blobId");
$galleryST->execute(array(":blobId" => $photo['file_id']));
$gallery = $galleryST->fetch(PDO::FETCH_ASSOC);

$degrees = $gallery['degrees'];

if(isset($_GET['deg'])) {
    $degrees = $_GET['deg'];
}
?>
<style type="text/css">
    .gallery-image {
        width: 100%;
        height: auto;
        border: 1px solid #ccc;
        transform: rotate(<?=$degrees?>deg);
    }
    .editOptions {
        margin-bottom: 2%;
        margin-top: 2%;
    }
</style>

<?php

$serve = BlobUtil::getAccessUrlForBlob($photo['file_id']);

$url = 'photo-edit.php?id=' . $_GET['id'];

$isRotate = isset($_POST['isRotate']) ? 1 : 0;
$degreesSub = isset($_POST['degrees']) ? $_POST['degrees'] : 0;

if ( $_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($isRotate == 1) {

        if(isset($_GET['deg'])) {
            $degrees = 90 + $_GET['deg'];
        } else {
            $degrees = $degrees + 90;
        }
        $url2 = 'photo-edit.php?deg=' . $degrees . "&id=" . $_GET['id'];

        header('Location: ' . addSession($url2));
    } else {
        // Update Gallery Database
        $updatePhoto = $PDOX->prepare("UPDATE {$p}photo_gallery SET degrees=:degrees WHERE blob_id = :blobId");
        $updatePhoto->execute(array(
            ":degrees" => $degreesSub,
            ":blobId" => $photo['file_id']
        ));

        $_SESSION['success'] = 'Photo updated successfully.';
        header('Location: ' . addSession('index.php'));
        return;
    }
}
$OUTPUT->bodyStart();
?>
<nav class="navbar navbar-default">
    <div class="container-fluid">
        <div class="navbar-header">
            <a class="navbar-brand" href="index.php"><span class="fa fa-photo-o" aria-hidden="true"></span> Photo Gallery</a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="editOptions">
        <form method="post">
            <input class="isRotate" id="isRotate" name="isRotate" value="1" type="number" style="display: none">
            <button class="btn btn-primary" id="rotate" type="submit"><i class="fas fa-redo"></i> Rotate Image</button>
        </form>
    </div>
</div>

<div class="container-fluid">
    <img class="gallery-image" src="<?=addSession($serve)?>">
    <div class="editOptions">
        <form method="post">
            <input class="photo-description" id="photo-description" value="<?=$photo['description']?>" type="hidden">
            <input class="degrees" id="degrees" name = "degrees" value="<?=$degrees?>" type="number" style="display: none">
            <button class="btn btn-success" type="submit">Save</button>
        </form>
    </div>
</div>

<?php
$OUTPUT->flashMessages();

$OUTPUT->footerStart();

$OUTPUT->footerEnd();
