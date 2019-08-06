<?php
require_once "../config.php";

use \Tsugi\Util\LTI;
use \Tsugi\UI\Output;
use \Tsugi\Core\LTIX;
use \Tsugi\Blob\BlobUtil;

$p = $CFG->dbprefix;

// Sometimes, if the maxUpload_SIZE is exceeded, it deletes all of $_POST
// Thus losing our session :(
if ( $_SERVER['REQUEST_METHOD'] == 'POST' && count($_POST) == 0 ) {
    die('Error: Maximum size of '.BlobUtil::maxUpload().'MB exceeded.');
}

// Sanity checks
$LAUNCH = LTIX::requireData(array(LTIX::CONTEXT, LTIX::LINK));

$settingsStmt = $PDOX->prepare("SELECT approval FROM {$p}gallery_main WHERE link_id = :linkId");
$settingsStmt->execute(array(":linkId" => $LINK->id));
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

$requireApproval = 0;
if ($settings) {
    $requireApproval = $settings["approval"];
}

// Other times, we see an error indication on bad upload that does not delete all the $_POST
if( isset($_FILES['uploaded_file']) && $_FILES['uploaded_file']['error'] == 1) {
    $_SESSION['error'] = 'Error: Maximum size of '.BlobUtil::maxUpload().'MB exceeded.';
    header( 'Location: '.addSession('index.php') ) ;
    return;
}

// View
$OUTPUT->header();

$photoST = $PDOX->prepare("SELECT * FROM {$p}blob_file WHERE file_id = :fileId");
$photoST->execute(array(":fileId" => $_GET['id']));
$photo = $photoST->fetch(PDO::FETCH_ASSOC);

$galleryST = $PDOX->prepare("SELECT * FROM {$p}photo_gallery WHERE blob_id = :blobId");
$galleryST->execute(array(":blobId" => $photo['file_id']));
$gallery = $galleryST->fetch(PDO::FETCH_ASSOC);

$blobST = $PDOX->prepare("SELECT * FROM {$p}blob_blob WHERE blob_id = :blobId");
$blobST->execute(array(":blobId" => $photo['blob_id']));
$blob = $blobST->fetch(PDO::FETCH_ASSOC);

//Resize unnecessarily large images so they actually fit on the page
$serve = BlobUtil::getAccessUrlForBlob($photo['file_id']);
$im = imagecreatefromstring($blob['content']);
$width = imagesx($im);
$height = imagesy($im);
$origWidth = $width;
$origHeight = $height;
$hratio = $height/$width;
$wratio = $width/$height;
if ($width > 970) {
    $width = 970;
    $height = 970 * $hratio;
}
if ($height > 970) {
    $height = 970;
    $width = $width * $wratio;
}
$zoom = 0;
if($width>$height) {
    $zoom = $width/$origWidth;
} else {
    $zoom = $height/$origHeight;
}


if ( $_SERVER['REQUEST_METHOD'] == 'POST') {
    $description = isset($_POST['photo-description']) ? $_POST['photo-description'] : $gallery['description'];
    if (isset($_POST['saveResult'])) {
        if(isset($_POST['saveResult'])) {
            $data = $_POST['saveResult'];
            list($type, $data) = explode(';', $data);
            list(, $data) = explode(',', $data);
            $data = base64_decode($data);
            $size = strlen($data);
            $temp_file = tempnam(sys_get_temp_dir(), 'newImage');
            file_put_contents($temp_file, $data);
            $fileArray = array("name" => $photo['file_name'], "type" => "image/jpeg", "tmp_name" => $temp_file, "error" => 0, "size" => $size);

            $fdes = $fileArray;
            $filename = $photo['file_name'];

            // Sanity-check the file
            $safety = BlobUtil::validateUpload($fdes);
            if ($safety !== true) {
                $_SESSION['error'] = "Error: " . $safety;
                error_log("Upload Error: " . $safety);
                header('Location: ' . addSession('index.php'));
                return;
            }


            $blob_id = BlobUtil::uploadToBlob($fdes);
            if ($blob_id === false) {
                $_SESSION['error'] = 'Problem storing file in server: ' . $filename;
                header('Location: ' . addSession('index.php'));
                return;
            }

            $approved = 1;
            if ($requireApproval == 1 && !$USER->instructor) {
                $approved = 0;
            }

            // Save success so add info to gallery database
            $newStmt = $PDOX->prepare("INSERT INTO {$p}photo_gallery (user_id, description, blob_id, approved) values (:userId, :description, :blobId, :approved)");
            $newStmt->execute(array(":userId" => $gallery['user_id'], ":description" => $description, ":blobId" => $blob_id, ":approved" => $approved));

            $deleteId = $photo['file_id'];

            $deletePhotoST = $PDOX->prepare("DELETE FROM {$p}photo_gallery where blob_id = :blobId");
            $deletePhotoST->execute(array(":blobId" => $deleteId));

            $deleteBlobST = $PDOX->prepare("DELETE FROM {$p}blob_file where file_id = :fileId");
            $deleteBlobST->execute(array(":fileId" => $deleteId));

            $newImgST = $PDOX->prepare("SELECT * FROM {$p}blob_file where file_id = :fileId");
            $newImgST->execute(array(":fileId" => $blob_id));
            $newImg = $newImgST->fetch(PDO::FETCH_ASSOC);

            header('Location: ' . addSession('index.php'));
        }
    }
}
$OUTPUT->bodyStart();
$OUTPUT->flashMessages();
?>
    <div class="pageInstructions">
        <h3>Edit Photo</h3>
        <p class="instructions-edit">Click the buttons to rotate the image. Drag the edges to crop the photo. Add a description in the box at the bottom of the page. When finished, click the 'save' button.</p>
    </div>

    <div id="pic"></div>

    <div class="editOptions">
        <p>
            <button role="button" class="btn btn-primary" id="rotateLeft" data-deg="90"> Rotate Left</button>
            <button role="button" class="btn btn-primary" id="rotateRight" data-deg="-90"> Rotate Right</button>
        </p>
        <form method="post" id="saveImage" class="form">
            <input type="hidden" id="saveResult" name="saveResult">
            <div class="form-group">
                <label for="photo-description">Photo Description</label>
                <textarea class="form-control" id="photo-description" name="photo-description" rows="5"><?=$gallery['description']?></textarea>
            </div>
            <p>
                <button class="btn btn-success save" id="save">Save</button> <a href="index.php">Cancel Editing</a>
            </p>
        </form>
    </div>
    </div>
<?php


$OUTPUT->footerStart();
?>
    <link rel="stylesheet" href="node_modules/croppie/croppie.css"/>
    <script src="node_modules/croppie/croppie.js"></script>
    <script src="jquery/src/jquery.js"></script>
    <script>
        var orientation = 1;

        function croppieOrientRotateLeft(or) {

            switch (or) {
                case 1: or = 8; break;
                case 2: or = 5; break;
                case 3: or = 6; break;
                case 4: or = 7; break;
                case 5: or = 4; break;
                case 6: or = 1; break;
                case 7: or = 2; break;
                case 8: or = 3; break;
            }

            return or;
        }

        function croppieOrientRotateRight(or) {

            switch (or) {
                case 1: or = 6; break;
                case 2: or = 7; break;
                case 3: or = 8; break;
                case 4: or = 5; break;
                case 5: or = 2; break;
                case 6: or = 3; break;
                case 7: or = 4; break;
                case 8: or = 1; break;
            }

            return or;

        }
        $( document ).ready(function() {
            //Initialize Croppie
            if(orientation === 1) {
                var crop = $('#pic').croppie({
                    viewport: { width: <?php echo $width ?>, height: <?php echo $height ?> },
                    boundary: { width: <?php echo $width+10 ?>, height: <?php echo $height+10 ?> },
                    enforceBoundary: false,
                    showZoomer: false,
                    enableResize: true,
                    enableOrientation: true
                });
                crop.croppie('bind', {
                    url: '<?php echo addSession($serve) ?>',
                    orientation: 1
                });
            }
            $('#rotateLeft').on('click', function (ev) {
                $('#pic').croppie('destroy');
                orientation = croppieOrientRotateLeft(orientation);
                if(orientation === 8 || orientation === 6) {
                    var crop = $('#pic').croppie({
                        viewport: { width: <?php echo $height ?>, height: <?php echo $width ?> },
                        boundary: { width: <?php echo $height+10 ?>, height: <?php echo $width+10 ?> },
                        enforceBoundary: false,
                        enableResize: true,
                        showZoomer: false,
                        enableOrientation: true
                    });
                    crop.croppie('bind', {
                        url: '<?php echo addSession($serve) ?>',
                        orientation: orientation,
                        zoom: <?php echo $zoom ?>
                    });
                } else {
                    var crop = $('#pic').croppie({
                        viewport: { width: <?php echo $width ?>, height: <?php echo $height ?> },
                        boundary: { width: <?php echo $width+10 ?>, height: <?php echo $height+10 ?> },
                        enforceBoundary: false,
                        showZoomer: false,
                        enableResize: true,
                        enableOrientation: true
                    });
                    crop.croppie('bind', {
                        url: '<?php echo addSession($serve) ?>',
                        orientation: orientation
                    });
                }
                crop.croppie('rotate', parseInt($(this).data('deg')));
            });
            $('#rotateRight').on('click', function (ev) {
                $('#pic').croppie('destroy');
                orientation = croppieOrientRotateRight(orientation);
                if(orientation === 8 || orientation === 6) {
                    var crop = $('#pic').croppie({
                        viewport: { width: <?php echo $height ?>, height: <?php echo $width ?> },
                        boundary: { width: <?php echo $height+10 ?>, height: <?php echo $width+10 ?> },
                        enforceBoundary: false,
                        showZoomer: false,
                        enableResize: true,
                        enableOrientation: true,
                    });
                    crop.croppie('bind', {
                        url: '<?php echo addSession($serve) ?>',
                        orientation: orientation,
                        zoom: <?php echo $zoom ?>
                    });
                } else {
                    var crop = $('#pic').croppie({
                        viewport: { width: <?php echo $width ?>, height: <?php echo $height ?> },
                        boundary: { width: <?php echo $width+10 ?>, height: <?php echo $height+10 ?> },
                        enforceBoundary: false,
                        showZoomer: false,
                        enableResize: true,
                        enableOrientation: true
                    });
                    crop.croppie('bind', {
                        url: '<?php echo addSession($serve) ?>',
                        orientation: orientation
                    });
                }

                crop.croppie('rotate', parseInt($(this).data('deg')));
            });
            $('#save').on('click', function (ev) {
                crop.croppie('bind', {
                    url: '<?php echo addSession($serve) ?>'
                });
                crop.croppie('result', {
                    type: 'canvas',
                    size: 'viewport'
                }).then(function (resp) {
                    $('#saveResult').val(resp);
                    $('#saveImage').submit();
                });
            });
        });
    </script>
<?php
$OUTPUT->footerEnd();
