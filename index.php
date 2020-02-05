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

$requireApproval = 0;
if ($settings) {
    $requireApproval = $settings["approval"];
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['captionText'])) {
        $editStmt = $PDOX->prepare("UPDATE {$p}photo_gallery SET description = :description where blob_id = :blobId");
        $editStmt->execute(array(
            ":description" => $_POST['captionText'],
            ":blobId" => $_POST['id']
        ));

        header('Location: ' . addSession('index.php'));
    }
    else if(isset($_FILES['filepond']) && isset($_GET['id'])) {
        $fdes = $_FILES['filepond'];

        $filename = isset($fdes['name'][0]) ? basename($fdes['name'][0]) : false;

        $data = "";

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

        $editStmt = $PDOX->prepare("UPDATE {$p}photo_gallery SET blob_id = :blobId where photo_id = :photoId");
        $editStmt->execute(array(
            ":blobId" => $blob_id,
            ":photoId" => $_GET['id']
        ));

        $deleteId = $_GET['blob'];

        $deleteBlobST = $PDOX->prepare("DELETE FROM {$p}blob_file where file_id = :fileId");
        $deleteBlobST->execute(array(":fileId" => $deleteId));

        header('Location: ' . addSession('index.php'));
    } else if(isset($_FILES['filepond'])) {
        $fdes = $_FILES['filepond'];

        $filename = isset($fdes['name'][0]) ? basename($fdes['name'][0]) : false;

        $fdes['name'] = $fdes['name'][0];
        $fdes['type'] = $fdes['type'][0];
        $fdes['tmp_name'] = $fdes['tmp_name'][0];
        $fdes['error'] = $fdes['error'][0];
        $fdes['size'] = $fdes['size'][0];

        $data = "";

        $safety = BlobUtil::validateUpload($fdes);
        if ( $safety !== true ) {
            $_SESSION['error'] = "Error: ".$safety;
            error_log("Upload Error: ".$safety);
            header( 'Location: '.addSession('index.php') ) ;
            return;
        }

        $blob_id = BlobUtil::uploadToBlob($fdes);
        if ( $blob_id === false ) {
            $_SESSION['error'] = 'Problem storing file in server: '.$filename;
            header( 'Location: '.addSession('index.php') ) ;
            return;
        }

        $description = "";

        $approved = 1;
        if ($requireApproval == 1 && !$USER->instructor) {
            $approved = 0;
        }

        // Save success so add info to gallery database
        $newStmt = $PDOX->prepare("INSERT INTO {$p}photo_gallery (user_id, description, blob_id, approved) values (:userId, :description, :blobId, :approved)");
        $newStmt->execute(array(":userId" => $USER->id, ":description" => $description, ":blobId" => $blob_id, ":approved" => $approved));

        $_SESSION['success'] = 'Photo added successfully.';
        $url = 'photo-edit.php?id=' . $blob_id;
        header( 'Location: '.addSession($url) ) ;
        return;
    }
}

// View
$OUTPUT->header();
?>
<style type="text/css">
    #gallery {
        padding: .5vw;
        display: -ms-flexbox;
        -ms-flex-wrap: wrap;
        -ms-flex-direction: column;
        -webkit-flex-flow: row wrap;
        flex-flow: row wrap;
        display: -webkit-box;
        display: flex;
    }
    .gallery-column {
        -webkit-box-flex: inherit;
        -ms-flex: auto;
        width: 200px;
        margin: .5vw;
    }
    .gallery-image {
        width: 100%;
        height: 100%;
        object-fit: scale-down;
        vertical-align: bottom;
    }
    .gallery-image:hover {
        -webkit-box-shadow: 0 5px 11px 0 rgba(0,0,0,.18), 0 4px 15px 0 rgba(0,0,0,.15);
        box-shadow: 0 5px 11px 0 rgba(0,0,0,.18), 0 4px 15px 0 rgba(0,0,0,.15);
    }
    .image-large {
        max-height: 500px;
        max-width: 100%;
        width: auto;
        height: auto;
    }
    .editPhoto {
        margin-left: 2%;
        cursor: pointer;
    }
    .addCaption {
        cursor: pointer;
    }
    .caption {
        margin-bottom: 10px;
        width: 100%;
        max-width: 100%;
    }
    .captionText {
        margin-bottom: 10px;
        max-width: 100%;
    }
    .image-container {
        width: 200px;
        height: 200px;
    }
    .image-container2 {
        text-align: center;
    }
    .photo-box {
        margin-top: 50px;
    }
    .magnify {
        cursor: pointer;
        font-size: 20px;
    }
    .doka--root {
        max-height: 800px;
    }
</style>
<?php
$OUTPUT->bodyStart();

// TODO: Make this a method in BlobUtil
$allPhotos = $PDOX->prepare("SELECT file_id, file_name, created_at FROM {$p}blob_file
        WHERE link_id = :LI");
$allPhotos->execute(array(":LI" => $LINK->id));

$sortedPhotos = $PDOX->prepare("SELECT file_id, file_name, created_at FROM {$p}blob_file
        WHERE link_id = :LI ORDER BY created_at desc");
$sortedPhotos->execute(array(":LI" => $LINK->id));

$countPending = 0;
$currentUserPendingCount = 0;
while ( $photo = $allPhotos->fetch(PDO::FETCH_ASSOC) ) {
    $approvedStmt = $PDOX->prepare("SELECT user_id, approved FROM {$p}photo_gallery WHERE blob_id = :blobId AND approved = :approved");
    $approvedStmt->execute(array(":blobId" => $photo["file_id"], ":approved" => 0));
    $approveInfo = $approvedStmt->fetch(PDO::FETCH_ASSOC);

    if ($approveInfo["approved"] == "0") {
        $countPending++;
    }
    if ($approveInfo["user_id"] == $USER->id) {
        $currentUserPendingCount++;
    }
}
?>

<nav class="navbar navbar-default">
    <div class="container-fluid">
        <div class="navbar-header">
            <a class="navbar-brand" href="index.php"><span class="fa fa-photo-o" aria-hidden="true"></span> Photo Gallery</a>
        </div>
        <ul class="nav navbar-nav">
            <?php
            if ($requireApproval && $USER->instructor) {
                echo '<li><a href="pending.php">Pending Photos <span class="label label-danger">'.$countPending.'</span></a></li>';
            }
            ?>
        </ul>
    </div>
</nav>

<?php $OUTPUT->flashMessages(); ?>

<div class="container-fluid">
    <div class="photo-box">
        <input type="file" class="filepond" name="filepond[]" multiple data-max-file-size="6MB">
    </div>
    <?php
    if ($requireApproval && $currentUserPendingCount > 0) {
        echo '<p class="alert alert-danger">You have <strong>'.$currentUserPendingCount.'</strong> submitted photo(s) pending approval.</p>';
    }
    ?>
    <div id="gallery">

<?php
$count = 0;
while ( $row = $sortedPhotos->fetch(PDO::FETCH_ASSOC) ) {
    $id = $row['file_id'];
    $fn = $row['file_name'];
    $date = $row['created_at'];

    $serve = BlobUtil::getAccessUrlForBlob($id);
    $infostmt = $PDOX->prepare("SELECT * FROM {$p}photo_gallery WHERE blob_id = :blobId");
    $infostmt->execute(array(":blobId" => $id));
    $photoInfo = $infostmt->fetch(PDO::FETCH_ASSOC);

    if ($requireApproval && $photoInfo["approved"] == "0") {
        continue;
    }

    $photoDate = new DateTime($date);
    $formattedDate = $photoDate->format("m-d-y") . " at " . $photoDate->format("h:i A");

    $namestmt = $PDOX->prepare("SELECT displayname FROM {$p}lti_user WHERE user_id = :user_id;");
    $namestmt->execute(array(":user_id" => $photoInfo["user_id"]));
    $name = $namestmt->fetch(PDO::FETCH_ASSOC);

    echo '<div class="gallery-column">
            <a href="javascript:void(0);" role="button" data-toggle="modal" data-target="#image'.$id.'" class="image-link">
            <div class="image-container">
                <img class="gallery-image" src="'.addSession($serve).'">
            </div>
                
            </a>
          </div>
          <div id="image'.$id.'" class="modal fade" role="dialog">
            <div class="modal-dialog modal-lg">
                <!-- Modal content-->
                <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4>Photo added by '.$name["displayname"].'<br /><small>'.$formattedDate.'</small></h4>
                    <div style="display:flex">';
                    if ($USER->instructor || $USER->id == $photoInfo["user_id"]) {
                        ?>
                        <div style="flex-grow: 1">
                        <a href="photo-delete.php?id=<?=$id?>"><span class="fa fa-trash" aria-hidden="true"></span> Delete Photo</a>
                        <a class="editPhoto" onclick="editPhoto('<?php echo addSession($serve) ?>', '<?php echo $photoInfo['photo_id'] ?>', '<?php echo $id ?>')"><span class="fa fa-edit" aria-hidden="true"></span> Edit Photo</a>
                        </div>
                    <?php
                    }
                    echo '
                    <ul class="pager" style="margin: 0;">
                        <li><a href="javascript:void(0);" data-dismiss="modal" onclick="gotoprev('.$count.');">Previous</a></li>
                        <li><a href="javascript:void(0);" data-dismiss="modal" onclick="gotonext('.$count.');">Next</a></li>
                    </ul>
                    </div>
                </div>
                <div class="modal-body">';
                    if($photoInfo["description"] != NULL) {
                        if ($USER->instructor || $USER->id == $photoInfo["user_id"]) {
                            ?>
                            <a onclick="editCaption(<?php echo $id ?>)" class="addCaption" id="editCaption<?php echo $id ?>"><span class="fa fa-edit"></span> Edit Caption</a>
                            <?php
                        }
                        ?>
                        <p id="editCaption0<?php echo $id ?>"><?=$photoInfo["description"]?></p>
                        <form method="post" id="caption">
                            <input type="hidden" name="id" value="<?=$id?>">
                            <div class="container caption">
                                <div class="row" id="captionEdit1<?php echo $id ?>" hidden>
                                    <h4>Edit Photo Caption</h4>
                                </div>
                                <div class="row" id="captionEdit2<?php echo $id ?>" hidden>
                                    <textarea class="form-control captionText" name="captionText"><?=$photoInfo["description"]?></textarea>
                                </div>
                                <div class="row" id="captionEdit3<?php echo $id ?>" hidden>
                                    <button class="btn btn-primary save" id="save">Save</button>
                                    <a class="addCaption" onclick="cancelEditCaption(<?php echo $id ?>)">Cancel</a>
                                </div>
                            </div>
                        </form>
                        <?php
                    } else if($USER->instructor || $USER->id == $photoInfo["user_id"]) {
                        ?>
                        <a onclick="addCaption(<?php echo $id ?>)" class="addCaption" id="addCaption<?php echo $id ?>"><span class="fa fa-plus"></span> Add Photo Caption</a>
                        <form method="post" id="caption">
                            <input type="hidden" name="id" value="<?=$id?>">
                            <div class="container caption">
                                <div class="row" id="captionCont1<?php echo $id ?>" hidden>
                                    <h4>Add Photo Caption</h4>
                                </div>
                                <div class="row" id="captionCont2<?php echo $id ?>" hidden>
                                    <textarea class="form-control captionText" name="captionText"></textarea>
                                </div>
                                <div class="row" id="captionCont3<?php echo $id ?>" hidden>
                                    <button class="btn btn-primary save" id="save">Save</button>
                                    <a class="addCaption" onclick="cancelAddCaption(<?php echo $id ?>)">Cancel</a>
                                </div>
                            </div>
                        </form>
                        <?php
                    }
                    ?>
                    <div class="image-container2">
                        <a class="magnify" onclick="window.open('<?= addSession($serve) ?>', '_blank');"><img class="image-large" src="<?=addSession($serve)?>"></a>
                    </div>
                    <?php
                echo '</div>
                </div>
            </div>
          </div>';
    $count++;
}
echo("</div>\n");

if ( $count == 0 ) echo "<p><em>No photos have been added yet.</em></p>\n";

?>
</div> <!-- Ending Container -->

<?php
$OUTPUT->footerStart();
?>
    <script type="text/javascript">
        function gotoprev(current_index) {
            var links = document.getElementsByClassName("image-link");
            current_index--;
            if (current_index < 0) {
                current_index = links.length -1;
            }
            links[current_index].click();
        }
        function gotonext(current_index) {
            var links = document.getElementsByClassName("image-link");
            current_index++;
            if (current_index >= links.length) {
                current_index = 0;
            }
            links[current_index].click();
        }

        function addCaption(id) {
            document.getElementById('addCaption' + id).style.display = "none";
            document.getElementById('captionCont1' + id).style.display = "block";
            document.getElementById('captionCont2' + id).style.display = "block";
            document.getElementById('captionCont3' + id).style.display = "block";
        }

        function cancelAddCaption(id) {
            document.getElementById('addCaption' + id).style.display = "block";
            document.getElementById('captionCont1' + id).style.display = "none";
            document.getElementById('captionCont2' + id).style.display = "none";
            document.getElementById('captionCont3' + id).style.display = "none";
        }

        function editCaption(id) {
            document.getElementById('editCaption' + id).style.display = "none";
            document.getElementById('editCaption0' + id).style.display = "none";
            document.getElementById('captionEdit1' + id).style.display = "block";
            document.getElementById('captionEdit2' + id).style.display = "block";
            document.getElementById('captionEdit3' + id).style.display = "block";
        }

        function cancelEditCaption(id) {
            document.getElementById('editCaption' + id).style.display = "block";
            document.getElementById('editCaption0' + id).style.display = "block";
            document.getElementById('captionEdit1' + id).style.display = "none";
            document.getElementById('captionEdit2' + id).style.display = "none";
            document.getElementById('captionEdit3' + id).style.display = "none";
        }

        function editPhoto(src, id, blob) {
            let img = new Image();
            img.src = src;
            const pond = FilePond.create({
                imageEditInstantEdit: true,
                instantUpload: true,
                imageEditEditor: Doka.create({
                    cropResizeScrollRectOnly: true,
                    outputStripImageHead: false
                }),
                server: {
                    url: 'index.php?PHPSESSID=<?php echo session_id() ?>&id=' + id + '&blob=' + blob
                }
            });
            pond.addFile(src);
            pond.onprocessfile = (files) => { window.location.href='index.php?PHPSESSID=<?php echo session_id() ?>'; }
        }

        FilePond.registerPlugin(
            FilePondPluginFileEncode,
            FilePondPluginFileValidateSize,
            FilePondPluginFileValidateType,
            FilePondPluginImageExifOrientation,
            FilePondPluginImageEdit,
            FilePondPluginImageCrop,
            FilePondPluginImageResize,
            FilePondPluginImageTransform
        );

        Doka.setOptions({
            labelStatusAwaitingImage: 'Waiting for image…',
            labelStatusLoadImageError: 'Error loading image…',
            labelStatusLoadingImage: 'Loading image…',
            labelStatusProcessingImage: 'Processing image…'
        });

        let pond = FilePond.create( document.querySelector('.filepond'), {
            acceptedFileTypes: ['image/*'],
            allowMultiple: true,
            maxParallelUploads: 20,
            maxFiles: 20,
            imageEditEditor: Doka.create(),
            server: {
                url: 'index.php?PHPSESSID=<?php echo session_id() ?>'
            },
            onaddfilestart: (files) => { isMultipleFiles(); },
            onprocessfile: (files) => { isLoadingCheck(); }
        });

        function isMultipleFiles() {
            let isMultiple = pond.getFiles().length > 1;
            console.log(isMultiple);
            pond.setOptions({
                imageEditInstantEdit: !isMultiple
            })
        }

        function isLoadingCheck() {
            let isLoading1 = pond.getFiles().filter(x=>x.status !== 5).length !== 0;
            if(!isLoading1) {
                window.location.href='index.php?PHPSESSID=<?php echo session_id() ?>';
            }
        }
    </script>
<?php
$OUTPUT->footerEnd();
