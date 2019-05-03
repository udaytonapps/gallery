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

if( isset($_FILES['uploaded_file']) && $_FILES['uploaded_file']['error'] == 0)
{
    $fdes = $_FILES['uploaded_file'];

    $filename = isset($fdes['name']) ? basename($fdes['name']) : false;

    // Sanity-check the file
    $safety = BlobUtil::validateUpload($fdes);
    if ( $safety !== true ) {
        $_SESSION['error'] = "Error: ".$safety;
        error_log("Upload Error: ".$safety);
        header( 'Location: '.addSession('index') ) ;
        return;
    }

    $blob_id = BlobUtil::uploadToBlob($fdes);
    if ( $blob_id === false ) {
        $_SESSION['error'] = 'Problem storing file in server: '.$filename;
        header( 'Location: '.addSession('index') ) ;
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

// If we got a post but no file...
if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
    $_SESSION['error'] = 'Please choose a photo to upload.';
    header( 'Location: '.addSession('index.php') ) ;
    return;
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
        width: 100%;
        height: 100%;
        object-fit: scale-down;
    }
    .editPhoto {
        margin-left: 2%;
    }
    .image-container {
        width: 200px;
        height: 200px;
    }
    .image-container2 {
        width: 700px;
        height: 700px;
        margin-left: 9%;
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
            <li><a href="javascript:void(0);" role="button" data-toggle="modal" data-target="#uploadModal"><span class="fa fa-plus" aria-hidden="true"></span> Add Photo</a></li>
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
    <p>Use the "Add Photo" button above to add a photo to this gallery.</p>
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
                    <h4>Photo added by '.$name["displayname"].'<br /><small>'.$formattedDate.'</small></h4>';
                    if ($USER->instructor || $USER->id == $photoInfo["user_id"]) {
                        ?>
                        <a href="photo-delete.php?id=<?=$id?>"><span class="fa fa-trash" aria-hidden="true"></span> Delete Photo</a>
                        <a href="photo-edit.php?id=<?=$id?>" class="editPhoto"><span class="fa fa-edit" aria-hidden="true"></span> Edit Photo</a>
                    <?php
                    }
                    echo '
                </div>
                <div class="modal-body">
                    <p>'.$photoInfo["description"].'</p>
                    <div class="image-container2">
                        <img class="image-large" src="'.addSession($serve).'">
                    </div>
                    <ul class="pager">
                        <li><a href="javascript:void(0);" data-dismiss="modal" onclick="gotoprev('.$count.');">Previous</a></li>
                        <li><a href="javascript:void(0);" data-dismiss="modal" onclick="gotonext('.$count.');">Next</a></li>
                    </ul>
                </div>
                </div>
            </div>
          </div>';
    $count++;
}
echo("</div>\n");

if ( $count == 0 ) echo "<p><em>No photos have been added yet.</em></p>\n";

?>
</div> <!-- Ending Container -->

<div id="uploadModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Upload a Photo<br /><small>Maximum size: <?php echo(BlobUtil::maxUpload());?>MB</small></h4>
                <h5 class="sideNote"><i>Photos can be edited after upload.</i></h5>
            </div>
            <form name="myform" enctype="multipart/form-data" method="post" action="<?php addSession('index.php');?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="uploaded_file">Upload Photo</label>
                        <input name="uploaded_file" type="file" id="uploaded_file">
                    </div>
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo(BlobUtil::maxUpload());?>000000" />
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Add Photo</button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
    </script>
<?php
$OUTPUT->footerEnd();
