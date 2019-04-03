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
<link rel="stylesheet" href="node_modules/croppie/croppie.css">
<script src="node_modules/croppie/croppie.js"></script>
<style type="text/css">
    .gallery-image {
        width: 100%;
        height: 100%;
        object-fit: scale-down;
        vertical-align: bottom;
        transform: rotate(<?=$degrees?>deg);
    }
    .image-container {
        width: 1000px;
        height: 1000px;
        margin-left: 4%;
    }
    .editOptions {
        margin-bottom: 2%;
        margin-top: 2%;
    }
    .save {
        margin-top: 2%;
    }
</style>

<?php

$serve = BlobUtil::getAccessUrlForBlob($photo['file_id']);

$url = 'photo-edit.php?id=' . $_GET['id'];

$isRotate = isset($_POST['isRotate']) ? 1 : 0;
$degreesSub = isset($_POST['degrees']) ? $_POST['degrees'] : 0;
$description = isset($_POST['photo-description']) ? $_POST['photo-description'] : $gallery['description'];
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
        $updatePhoto = $PDOX->prepare("UPDATE {$p}photo_gallery SET degrees=:degrees, description=:description WHERE blob_id = :blobId");
        $updatePhoto->execute(array(
            ":degrees" => $degreesSub,
            ":description" => $description,
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
    <div class="image-container">
        <img class="gallery-image" src="<?=addSession($serve)?>">
    </div>
    <script>
        var url = "<?php echo addSession($serve) ?>";
        var elem = document.getElementById('gallery-image');
        var crop = new Croppie(elem, {
            viewport: { width: 100, height: 100 },
            boundary: { width: 300, height: 300 },
            showZoomer: false,
            enableResize: true,
            enableOrientation: true,
            mouseWheelZoom: 'ctrl'
        });
        crop.bind({
            url: url,
        });
        //on button click
        crop.result('blob').then(function(blob) {

        });
    </script>
    <div class="editOptions">
        <form method="post">
            <label for="photo-description">Photo Description</label>
            <textarea class="form-control" id="photo-description" name="photo-description" rows="5"><?=$gallery['description']?></textarea>
            <input class="degrees" id="degrees" name="degrees" value="<?=$degrees?>" type="number" style="display: none">
            <button class="btn btn-success save" type="submit">Save</button>
        </form>
    </div>
</div>

<?php
$OUTPUT->flashMessages();

$OUTPUT->footerStart();

$OUTPUT->footerEnd();
