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

?>
    <style type="text/css">
        .gallery-image {
            width: 100%;
            height: 100%;
            object-fit: scale-down;
            vertical-align: bottom;
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
        .col-sm-1 {
            margin-bottom: 2%;
        }
        .save {
            margin-top: 2%;
        }
        .instructions-edit {
            font-family: "Lato", sans-serif;
            word-wrap: break-spaces;
            color: #555555;

        }
        .pageInstructions {
            width: 85%;
            margin-left: 8%;
            text-align: center;
        }

    </style>

<?php
//Resize unnecessarily large images so they actually fit on the page
$serve = BlobUtil::getAccessUrlForBlob($photo['file_id']);
$im = imagecreatefromstring($blob['content']);
$width = imagesx($im);
$height = imagesy($im);
$origWidth = $width;
$origHeight = $height;
if($width > 600 && $width <= 1200 && $width>$height || $height > 600 && $height <= 1200 && $height>$width) {
    $width = $width/2;
    $height = $height/2;
} else if($width > 1200 && $width <= 1800 && $width>$height || $height > 1200 && $height <= 1800 && $height>$width) {
    $width = $width/3;
    $height = $height/3;
} else if($width > 1800 && $width <= 2400 && $width>$height || $height > 1800 && $height <= 2400 && $height>$width) {
    $width = $width/4;
    $height = $height/4;
} else if($width > 2400 && $width <= 3000 && $width>$height || $height > 2400 && $height <= 3000 && $height>$width) {
    $width = $width/5;
    $height = $height/5;
} else if($width > 3000 && $width <= 3600 && $width>$height || $height > 3000 && $height <= 3600 && $height>$width) {
    $width = $width/6;
    $height = $height/6;
} else if($width > 3600 && $width <= 4200 && $width>$height || $height > 3600 && $height <= 4200 && $height>$width) {
    $width = $width/7;
    $height = $height/7;
} else if($width > 4200 && $width <= 4800 && $width>$height || $height > 4200 && $height <= 4800 && $height>$width) {
    $width = $width/8;
    $height = $height/8;
} else if($width > 4800 && $width <= 5400 && $width>$height || $height > 4800 && $height <= 5600 && $height>$width) {
    $width = $width/9;
    $height = $height/9;
} else if($width > 5400 && $width>$height || $height > 5400 && $height>$width) {
    $width = $width/10;
    $height = $height/10;
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
$OUTPUT->flashMessages();
$OUTPUT->bodyStart();
?>
    <nav class="navbar navbar-default">
        <div class="container-fluid">
            <div class="navbar-header">
                <a class="navbar-brand" href="index.php"><span class="fa fa-photo-o" aria-hidden="true"></span> Photo Gallery</a>
            </div>
        </div>
    </nav>

    <div class="pageInstructions">
        <h3 class="instructions-edit">Click the buttons to rotate the image. Drag the edges to crop the photo. Add a description in the box at the bottom of the page. When finished, click the 'save' button.</h3>
    </div>

    <div class="container-fluid">
        <div class="editOptions">
            <div class="col-sm-1">
                <button class="btn btn-primary" id="rotateLeft" data-deg="90"> Rotate Left</button>
            </div>
            <div class="col-sm-1" style="margin-left: 2%">
                <button class="btn btn-primary" id="rotateRight" data-deg="-90"> Rotate Right</button>
            </div>
        </div>
    </div>
<div class="container">
    <div id="pic"></div>
</div>

    <div class="container-fluid">
        <div class="editOptions">
            <form method="post" id="saveImage">
                <input type="hidden" id="saveResult" name="saveResult">
                <label for="photo-description">Photo Description</label>
                <textarea class="form-control" id="photo-description" name="photo-description" rows="5"><?=$gallery['description']?></textarea>
                <button class="btn btn-success save" id="save">Save</button>
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
                    boundary: { width: <?php echo $width ?>, height: <?php echo $height ?> },
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
                        boundary: { width: <?php echo $height ?>, height: <?php echo $width ?> },
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
                        boundary: { width: <?php echo $width ?>, height: <?php echo $height ?> },
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
                        boundary: { width: <?php echo $height ?>, height: <?php echo $width ?> },
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
                        boundary: { width: <?php echo $width ?>, height: <?php echo $height ?> },
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
