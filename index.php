<?php
require_once "../config.php";

use Tsugi\Blob\BlobUtil;
use Tsugi\Core\LTIX;

$p = $CFG->dbprefix;

// Sanity checks
$LAUNCH = LTIX::requireData(array(LTIX::CONTEXT, LTIX::LINK));

$settingsStmt = $PDOX->prepare("SELECT approval FROM {$p}gallery_main WHERE link_id = :linkId");
$settingsStmt->execute(array(":linkId" => $LINK->id));
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

const IMAGE_HANDLERS = [
    IMAGETYPE_JPEG => [
        'load' => 'imagecreatefromjpeg',
        'save' => 'imagejpeg',
        'quality' => 100
    ],
    IMAGETYPE_PNG => [
        'load' => 'imagecreatefrompng',
        'save' => 'imagepng',
        'quality' => 0
    ],
    IMAGETYPE_GIF => [
        'load' => 'imagecreatefromgif',
        'save' => 'imagegif'
    ]
];

function createThumbnail($src, $dest, $targetWidth, $targetHeight = null)
{

    $type = exif_imagetype($src);

    if (!$type || !IMAGE_HANDLERS[$type]) {
        return null;
    }

    $image = call_user_func(IMAGE_HANDLERS[$type]['load'], $src);

    if (!$image) {
        return null;
    }
    $width = imagesx($image);
    $height = imagesy($image);

    if ($targetHeight == null) {
        $ratio = $width / $height;

        if ($width > $height) {
            $targetHeight = floor($targetWidth / $ratio);
        } else {
            $targetHeight = $targetWidth;
            $targetWidth = floor($targetWidth * $ratio);
        }
    }

    $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

    if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_PNG) {

        imagecolortransparent(
            $thumbnail,
            imagecolorallocate($thumbnail, 0, 0, 0)
        );

        if ($type == IMAGETYPE_PNG) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
        }
    }

    imagecopyresampled(
        $thumbnail,
        $image,
        0, 0, 0, 0,
        $targetWidth, $targetHeight,
        $width, $height
    );

    return $thumbnail;
}

$requireApproval = 0;
if ($settings) {
    $requireApproval = $settings["approval"];
} else {
    $newStmt = $PDOX->prepare("INSERT INTO {$p}gallery_main (link_id, approval) values (:linkId, :approval)");
    $newStmt->execute(array(":linkId" => $LINK->id, ":approval" => 0));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve'])) {
        var_dump($_POST);
        $approveBool = ($settings["approval"] == 0) ? 1 : 0;
        var_dump($approveBool);
        $editStmt = $PDOX->prepare("UPDATE {$p}gallery_main SET approval = :approval where link_id = :linkId");
        $editStmt->execute(array(
            ":approval" => $approveBool,
            ":linkId" => $LINK->id
        ));
    } else if (isset($_POST['captionText'])) {
        $editStmt = $PDOX->prepare("UPDATE {$p}photo_gallery SET description = :description where blob_id = :blobId");
        $editStmt->execute(array(
            ":description" => $_POST['captionText'],
            ":blobId" => $_POST['id']
        ));
    } else if (isset($_FILES['filepond']) && isset($_GET['id'])) {
        $fdes = $_FILES['filepond'];

        $filename = isset($fdes['name'][0]) ? basename($fdes['name'][0]) : false;

        $thumb = createThumbnail($fdes['tmp_name'], 'thumb' . $fdes['tmp_name'], 200);
        imagejpeg($thumb, '/tmp/thumb_' . $fdes['name']);

        $fbes['name'] = 'thumb_' . $fdes['name'];
        $fbes['type'] = $fdes['type'];
        $fbes['tmp_name'] = '/tmp/' . $fbes['name'];
        $fbes['error'] = 0;
        $fbes['size'] = getimagesize($fbes['tmp_name']);

        $thumbname = isset($fbes['name']) ? basename($fbes['name']) : false;

        $safety1 = BlobUtil::validateUpload($fdes);
        if ($safety1 !== true) {
            $_SESSION['error'] = "Error: " . $safety1;
            error_log("Upload Error: " . $safety1);
            header('Location: ' . addSession('index.php'));
            return;
        }

        $blob_id = BlobUtil::uploadToBlob($fdes);
        if ($blob_id === false) {
            $_SESSION['error'] = 'Problem storing file in server: ' . $filename;
            header('Location: ' . addSession('index.php'));
            return;
        }

        $safety2 = BlobUtil::validateUpload($fbes);
        if ($safety2 !== true) {
            $_SESSION['error'] = "Error: " . $safety2;
            error_log("Upload Error: " . $safety2);
            header('Location: ' . addSession('index.php'));
            return;
        }

        $thumb_id = BlobUtil::uploadToBlob($fbes);
        if ($thumb_id === false) {
            $_SESSION['error'] = 'Problem storing file in server: ' . $thumbname;
            header('Location: ' . addSession('index.php'));
            return;
        }

        $editStmt = $PDOX->prepare("UPDATE {$p}photo_gallery SET blob_id = :blobId, thumb_id = :thumbId where photo_id = :photoId");
        $editStmt->execute(array(
            ":blobId" => $blob_id,
            ":thumbId" => $thumb_id,
            ":photoId" => $_GET['id']
        ));

        $deleteId = $_GET['blob'];
        $deleteThumbId = $_GET['thumb'];

        $deleteBlobST = $PDOX->prepare("DELETE FROM {$p}blob_file where file_id = :fileId");
        $deleteBlobST->execute(array(":fileId" => $deleteId));

        $deleteBlobST = $PDOX->prepare("DELETE FROM {$p}blob_file where file_id = :fileId");
        $deleteBlobST->execute(array(":fileId" => $deleteThumbId));

        header('Location: ' . addSession('index.php'));
    } else if (isset($_FILES['filepond'])) {
        $fdes = $_FILES['filepond'];

        $filename = isset($fdes['name'][0]) ? basename($fdes['name'][0]) : false;

        $fdes['name'] = $fdes['name'][0];
        $fdes['type'] = $fdes['type'][0];
        $fdes['tmp_name'] = $fdes['tmp_name'][0];
        $fdes['error'] = $fdes['error'][0];
        $fdes['size'] = $fdes['size'][0];

        $thumb = createThumbnail($fdes['tmp_name'], 'thumb' . $fdes['tmp_name'], 200);
        imagejpeg($thumb, '/tmp/thumb_' . $fdes['name']);

        $fbes['name'] = 'thumb_' . $fdes['name'];
        $fbes['type'] = $fdes['type'];
        $fbes['tmp_name'] = '/tmp/' . $fbes['name'];
        $fbes['error'] = 0;
        $fbes['size'] = getimagesize($fbes['tmp_name']);

        $thumbname = isset($fbes['name']) ? basename($fbes['name']) : false;

        $safety1 = BlobUtil::validateUpload($fdes);
        if ($safety1 !== true) {
            $_SESSION['error'] = "Error: " . $safety1;
            error_log("Upload Error: " . $safety1);
            header('Location: ' . addSession('index.php'));
            return;
        }

        $blob_id = BlobUtil::uploadToBlob($fdes);
        if ($blob_id === false) {
            $_SESSION['error'] = 'Problem storing file in server: ' . $filename;
            header('Location: ' . addSession('index.php'));
            return;
        }

        $safety2 = BlobUtil::validateUpload($fbes);
        if ($safety2 !== true) {
            $_SESSION['error'] = "Error: " . $safety2;
            error_log("Upload Error: " . $safety2);
            header('Location: ' . addSession('index.php'));
            return;
        }

        $thumb_id = BlobUtil::uploadToBlob($fbes);
        if ($thumb_id === false) {
            $_SESSION['error'] = 'Problem storing file in server: ' . $thumbname;
            header('Location: ' . addSession('index.php'));
            return;
        }

        $description = "";

        $approved = 1;
        if ($requireApproval == 1 && !$USER->instructor) {
            $approved = 0;
        }

        // Save success so add info to gallery database
        $newStmt = $PDOX->prepare("INSERT INTO {$p}photo_gallery (user_id, description, blob_id, thumb_id, approved) values (:userId, :description, :blobId, :thumbId, :approved)");
        $newStmt->execute(array(":userId" => $USER->id, ":description" => $description, ":blobId" => $blob_id, ":thumbId" => $thumb_id, ":approved" => $approved));

        $_SESSION['success'] = 'Photo added successfully.';
        header('Location: ' . addSession('index.php'));
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
            -webkit-box-shadow: 0 5px 11px 0 rgba(0, 0, 0, .18), 0 4px 15px 0 rgba(0, 0, 0, .15);
            box-shadow: 0 5px 11px 0 rgba(0, 0, 0, .18), 0 4px 15px 0 rgba(0, 0, 0, .15);
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

        .filepond-main-label {
            text-decoration: none;
            cursor: pointer;
        }

        [class ^= filepond--drop-label] {
            cursor: pointer;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 30px;
            height: 17px;
            margin-top: 14px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #333;
            -webkit-transition: .4s;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 13px;
            width: 13px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            -webkit-transition: .4s;
            transition: .4s;
        }

        input:checked + .slider {
            background-color: #2196F3;
        }

        input:focus + .slider {
            box-shadow: 0 0 1px #2196F3;
        }

        input:checked + .slider:before {
            -webkit-transform: translateX(13px);
            -ms-transform: translateX(13px);
            transform: translateX(13px);
        }

        /* Rounded sliders */
        .slider.round {
            border-radius: 17px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        .req-approve {
            font-size: 14px;
            color: black;
            font-weight: bold;
            text-wrap: none;
            vertical-align: top;
            line-height: 50px;
        }
        .req-approve:hover {
            cursor: pointer;
        }
        .disabledbutton {
            pointer-events: none;
            opacity: 0.4;
        }
        .loader {
            border: 16px solid #f3f3f3; /* Light grey */
            border-top: 16px solid #3498db; /* Blue */
            border-radius: 50%;
            width: 120px;
            height: 120px;
            animation: spin 2s linear infinite;
            margin-left: 43%;
            margin-top: 35%;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loader-cont {
            height: 100%;
            width: 100%;
            align-items: center;
            justify-content: center;
            vertical-align: center;
            justify-items: center;
        }
        .pager li a {
            color: black;
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
while ($photo = $allPhotos->fetch(PDO::FETCH_ASSOC)) {
    $approvedStmt = $PDOX->prepare("SELECT user_id, approved FROM {$p}photo_gallery WHERE blob_id = :blobId AND approved = :approved");
    $approvedStmt->execute(array(":blobId" => $photo["file_id"], ":approved" => 0));
    $approveInfo = $approvedStmt->fetch(PDO::FETCH_ASSOC);

    if ($approveInfo) {
        if ($approveInfo["approved"] == "0") {
            $countPending++;
        }
        if ($approveInfo["user_id"] == $USER->id) {
            $currentUserPendingCount++;
        }
    }
}
?>

    <nav class="navbar navbar-default">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xs-9 col-sm-9 col-md-9 col-lg-9">
                    <div class="navbar-header">
                        <a class="navbar-brand" href="index.php"><span class="fa fa-photo-o" aria-hidden="true"></span> Photo
                            Gallery</a>
                    </div>
                    <ul class="nav navbar-nav">
                        <?php
                        if ($requireApproval && $USER->instructor) {
                            echo '<li><a href="pending.php"><span class="badge pull-right" style="margin-left:4px;">' . $countPending . '</span>Pending Photos</a></li>';
                        }
                        ?>
                    </ul>
                </div>
                <?php
                if($USER->instructor) {
                    if($requireApproval) {
                        ?>
                        <div class="col-xs-3 col-sm-3 col-md-3 col-lg-3 text-right"">
                            <label for="approve-toggle" class="req-approve">Moderate student uploads</label>
                            <label class="switch">
                                <input type="checkbox" id="approve-toggle" checked>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="col-xs-3 col-sm-3 col-md-3 col-lg-3 text-right">
                    <span>
                        <label for="approve-toggle" class="req-approve">Moderate student uploads</label>
                        <label class="switch">
                            <input type="checkbox" id="approve-toggle">
                            <span class="slider round"></span>
                        </label>
                    </span>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
    </nav>

<?php $OUTPUT->flashMessages(); ?>
    <a id="loader-toggle" href="#" role="button" data-toggle="modal" data-target="#loader-modal" hidden></a>
<?php
echo('<div id="loader-modal" class="modal" role="img">
            <button type="button" class="close" id="loader-modal-close" data-dismiss="modal" hidden></button>
            <div class="loader-cont">
                <div class="loader" id="loader"></div>
            </div>
        
    </div>')
?>
    <div class="container-fluid" id="main-gallery">
    <div class="photo-box">
        <input type="file" class="filepond" name="filepond[]" multiple data-max-file-size="6MB">
    </div>
    <?php
    if ($requireApproval && $currentUserPendingCount > 0) {
        echo '<p class="alert alert-danger">You have <strong>' . $currentUserPendingCount . '</strong> submitted photo(s) pending approval.</p>';
    }
    ?>
    <div id="gallery">

        <?php
        $count = 0;
        $infostmt = $PDOX->prepare("SELECT * FROM {$p}photo_gallery ORDER BY photo_id desc");
        $infostmt->execute();
        $photoList = $infostmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($photoList as $row) {
            $id = $row['blob_id'];
            $thumbId = isset($row['thumb_id']) ? $row['thumb_id'] : $row['blob_id'];
            $photoInfostmt = $PDOX->prepare("SELECT file_name, created_at FROM {$p}blob_file WHERE file_id = :fileId AND link_id = :LI");
            $photoInfostmt->execute(array(":fileId" => $id, ":LI" => $LINK->id));
            $photoInfo = $photoInfostmt->fetch(PDO::FETCH_ASSOC);
            if ($photoInfo) {
                $fn = $photoInfo['file_name'];
                $date = $photoInfo['created_at'];

                $serve = BlobUtil::getAccessUrlForBlob($id);
                $thumb = BlobUtil::getAccessUrlForBlob($thumbId);

                if ($requireApproval && $row["approved"] == "0") {
                    continue;
                }

                $photoDate = new DateTime($date);
                $formattedDate = $photoDate->format("m-d-y") . " at " . $photoDate->format("h:i A");

                $namestmt = $PDOX->prepare("SELECT displayname FROM {$p}lti_user WHERE user_id = :user_id;");
                $namestmt->execute(array(":user_id" => $row["user_id"]));
                $name = $namestmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <div class="gallery-column">
                    <a href="#" role="button" data-toggle="modal" data-target="#image<?= $id ?>" class="image-link">
                        <div class="image-container">
                            <img class="gallery-image" src="<?= addSession($thumb) ?>">
                        </div>
                    </a>
                </div>
                <?php
                echo '
          <div id="image' . $id . '" class="modal" role="dialog">
            <div class="modal-dialog modal-lg">
                <!-- Modal content-->
                <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4>Photo added by ' . $name["displayname"] . '<br /><small>' . $formattedDate . '</small></h4>
                    <div style="display:flex">
                    <div style="flex-grow: 1">';
                if ($USER->instructor || $USER->id == $row["user_id"]) {
                    ?>
                    <a href="photo-delete.php?id=<?= $id ?>&thumb=<?= $thumbId ?>"><span class="fa fa-trash"
                                                                                         aria-hidden="true"></span>
                        Delete Photo</a>
                    <a class="editPhoto"
                       onclick="editPhoto('<?php echo addSession($serve) ?>', '<?php echo $row['photo_id'] ?>', '<?php echo $id ?>', '<?php echo $thumbId ?>')"><span
                                class="fa fa-edit" aria-hidden="true"></span> Edit Photo</a>
                    <?php
                }
                ?>
                </div>
                <ul class="pager" style="margin: 0;">
                    <li><a href="#" data-dismiss="modal" onclick="gotoprev(<?= $count ?>)">Previous</a></li>
                    <li><a href="#" data-dismiss="modal" onclick="gotonext(<?= $count ?>)">Next</a></li>
                </ul>
                <?php
                echo '
                    </div> <!-- End flex -->
                </div> <!-- End modal header -->
                <div class="modal-body">';
                if ($row["description"] != NULL) {
                    if ($USER->instructor || $USER->id == $row["user_id"]) {
                        ?>
                        <a onclick="editCaption(<?php echo $id ?>)" class="addCaption"
                           id="editCaption<?php echo $id ?>"><span class="fa fa-edit"></span> Edit Caption</a>
                        <?php
                    }
                    ?>
                    <p id="editCaption0<?php echo $id ?>"><?= $row["description"] ?></p>
                    <form id="captionEdit<?php echo $id ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <div class="container caption">
                            <div class="row" id="captionEdit1<?php echo $id ?>" hidden>
                                <h4>Edit Photo Caption</h4>
                            </div>
                            <div class="row" id="captionEdit2<?php echo $id ?>" hidden>
                                <textarea class="form-control captionText" name="captionText"
                                          id="captionEdit4<?php echo $id ?>"><?= $row["description"] ?></textarea>
                            </div>
                            <div class="row" id="captionEdit3<?php echo $id ?>" hidden>
                                <button class="btn btn-primary save" onclick="submitEdit(<?php echo $id ?>)" id="save">
                                    Save
                                </button>
                                <a class="addCaption" onclick="cancelEditCaption(<?php echo $id ?>)">Cancel</a>
                            </div>
                        </div>
                    </form>
                    <?php
                } else if ($USER->instructor || $USER->id == $row["user_id"]) {
                    ?>
                    <a onclick="editCaption(<?php echo $id ?>)" class="addCaption" id="editCaption<?php echo $id ?>"
                       hidden><span class="fa fa-edit"></span> Edit Caption</a>
                    <p id="editCaption0<?php echo $id ?>" hidden></p>
                    <a onclick="addCaption(<?php echo $id ?>)" class="addCaption" id="addCaption<?php echo $id ?>"><span
                                class="fa fa-plus"></span> Add Photo Caption</a>
                    <form id="captionAdd<?php echo $id ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <div class="container caption">
                            <div class="row" id="captionCont1<?php echo $id ?>" hidden>
                                <h4>Add Photo Caption</h4>
                            </div>
                            <div class="row" id="captionCont2<?php echo $id ?>" hidden>
                                <textarea class="form-control captionText" id="captionText<?php echo $id ?>"
                                          name="captionText"></textarea>
                            </div>
                            <div class="row" id="captionCont3<?php echo $id ?>" hidden>
                                <button class="btn btn-primary save" onclick="submitAdd(<?php echo $id ?>)" id="save">
                                    Save
                                </button>
                                <a class="addCaption" onclick="cancelAddCaption(<?php echo $id ?>)">Cancel</a>
                            </div>
                        </div>
                    </form>
                    <form id="captionEdit<?php echo $id ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <div class="container caption">
                            <div class="row" id="captionEdit1<?php echo $id ?>" hidden>
                                <h4>Edit Photo Caption</h4>
                            </div>
                            <div class="row" id="captionEdit2<?php echo $id ?>" hidden>
                                <textarea class="form-control captionText" name="captionText"
                                          id="captionEdit4<?php echo $id ?>"><?= $row["description"] ?></textarea>
                            </div>
                            <div class="row" id="captionEdit3<?php echo $id ?>" hidden>
                                <button class="btn btn-primary save" onclick="submitEdit(<?php echo $id ?>)" id="save">
                                    Save
                                </button>
                                <a class="addCaption" onclick="cancelEditCaption(<?php echo $id ?>)">Cancel</a>
                            </div>
                        </div>
                    </form>
                    <?php
                }
                ?>
                <div class="image-container2">
                    <a class="magnify" onclick="window.open('<?= addSession($serve) ?>', '_blank');"><img
                                class="image-large" src="<?= addSession($serve) ?>"></a>
                </div>
                <?php
                echo '</div>
                </div>
            </div>
          </div>';
                $count++; // Only count photos in this gallery
            }
        }


        echo("</div>\n");

        if ($count == 0) echo "<p><em>No photos have been added yet.</em></p>\n";

        ?>
    </div> <!-- Ending Container -->

    <?php
    $OUTPUT->footerStart();
    ?>
    <script type="text/javascript">
        $("#approve-toggle").change(function() {
            $.ajax({
                type: 'POST',
                url: 'index.php?PHPSESSID=<?php echo session_id() ?>',
                data: {
                    approve: 0
                }
            }).then(() => {
                location.reload();
            });
        });

        function gotoprev(current_index) {
            let links = document.getElementsByClassName("image-link");
            current_index--;
            if (current_index < 0) {
                current_index = links.length - 1;
            }
            links[current_index].click();
        }

        function gotonext(current_index) {
            let links = document.getElementsByClassName("image-link");
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
            console.log(document.getElementById('editCaption0' + id).innerHTML);
            if (document.getElementById('captionEdit4' + id).value === "") {
                document.getElementById('captionEdit4' + id).innerHTML = document.getElementById('editCaption0' + id).innerHTML;
            }
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

        function submitAdd(id) {
            $('#captionAdd' + id).submit(function () {
                let post_data = $('#captionAdd' + id).serialize();
                document.getElementById('editCaption0' + id).innerHTML = document.getElementById('captionText' + id).value;
                document.getElementById('editCaption0' + id).style.display = "block";
                document.getElementById('addCaption' + id).style.display = "none";
                document.getElementById('captionCont1' + id).style.display = "none";
                document.getElementById('captionCont2' + id).style.display = "none";
                document.getElementById('captionCont3' + id).style.display = "none";
                document.getElementById('editCaption' + id).style.display = "block";
                $.ajax({
                    type: 'POST',
                    url: 'index.php',
                    data: post_data
                });
                return false;
            });
        }

        function submitEdit(id) {
            $('#captionEdit' + id).submit(function () {
                let post_data = $('#captionEdit' + id).serialize();
                document.getElementById('editCaption0' + id).innerHTML = document.getElementById('captionEdit4' + id).value;
                document.getElementById('editCaption0' + id).style.display = "block";
                document.getElementById('captionEdit1' + id).style.display = "none";
                document.getElementById('captionEdit2' + id).style.display = "none";
                document.getElementById('captionEdit3' + id).style.display = "none";
                document.getElementById('editCaption' + id).style.display = "block";
                $.ajax({
                    type: 'POST',
                    url: 'index.php',
                    data: post_data
                });
                return false;
            });
        }

        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        function editPhoto(src, id, blob, thumb) {
            let img = new Image();
            img.src = src;
            const pond = FilePond.create({
                imageEditInstantEdit: true,
                instantUpload: true,
                imageEditEditor: Doka.create({
                    cropResizeScrollRectOnly: true,
                    outputStripImageHead: false,
                    labelButtonConfirm: 'Save',
                    oncancel: () => {
                        $("#main-gallery").removeClass("disabledbutton");
                        $("#loader-modal-close").click();
            }
                }),
                server: {
                    url: 'index.php?PHPSESSID=<?php echo session_id() ?>&id=' + id + '&blob=' + blob + "&thumb=" + thumb
                }
            });
            pond.addFile(src);
            pond.onaddfilestart = (files) => {
                sleep(2000).then(() => {
                    $("#main-gallery").addClass("disabledbutton");
                    $("#loader-toggle").click();
                });
            };
            pond.onprocessfile = (files) => {
                window.location.href = 'index.php?PHPSESSID=<?php echo session_id() ?>';
            }
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
            labelStatusProcessingImage: 'Processing image…',
            labelButtonConfirm: 'Save'
        });

        let pond = FilePond.create(document.querySelector('.filepond'), {
            acceptedFileTypes: ['image/*'],
            allowMultiple: true,
            maxParallelUploads: 20,
            maxFiles: 20,
            labelIdle: '<span class="filepond-main-label">Drag & Drop Your Photos or Click to Browse</span>',
            imageEditEditor: Doka.create(),
            imageEditInstantEdit: true,
            server: {
                url: 'index.php?PHPSESSID=<?php echo session_id() ?>'
            },
            onprocessfile: (files) => {
                isLoadingCheck();
            }
        });

        function isLoadingCheck() {
            let isLoading1 = pond.getFiles().filter(x => x.status !== 5).length !== 0;
            if (!isLoading1) {
                window.location.href = 'index.php?PHPSESSID=<?php echo session_id() ?>';
            }
        }
    </script>
<?php
$OUTPUT->footerEnd();
