<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;
use \Tsugi\Blob\BlobUtil;

$p = $CFG->dbprefix;

// Sanity checks
$LAUNCH = LTIX::requireData(array(LTIX::CONTEXT, LTIX::LINK));

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
            -webkit-box-flex: auto;
            -ms-flex: auto;
            flex: auto;
            width: 200px;
            margin: .5vw;
        }
        .gallery-image {
            width: 100%;
            height: auto;
            border: 1px solid #ccc;
        }
        .gallery-image:hover {
            -webkit-box-shadow: 0 5px 11px 0 rgba(0,0,0,.18), 0 4px 15px 0 rgba(0,0,0,.15);
            box-shadow: 0 5px 11px 0 rgba(0,0,0,.18), 0 4px 15px 0 rgba(0,0,0,.15);
            border: 1px solid #999;
        }
        .image-large {
            width: 100%;
            height: auto;
        }
        .pending-title {
            margin-top: 0;
        }
    </style>
<?php
$OUTPUT->bodyStart();

$sortedPhotos = $PDOX->prepare("SELECT file_id, file_name, created_at FROM {$p}blob_file
        WHERE link_id = :LI ORDER BY created_at asc");
$sortedPhotos->execute(array(":LI" => $LINK->id));

?>

    <nav class="navbar navbar-default">
        <div class="container-fluid">
            <div class="navbar-header">
                <a class="navbar-brand" href="index.php"><span class="fa fa-photo-o" aria-hidden="true"></span> Photo Gallery</a>
            </div>
        </div>
    </nav>

<?php $OUTPUT->flashMessages(); ?>

    <div class="container-fluid">
    <h3 class="pending-title">Photos Pending Approval</h3>
    <p>Click on the photo to view a larger version and use the check mark button to approve the photo or the delete button to delete it.</p>
    <div id="gallery">

        <?php
        $count = 0;
        while ( $row = $sortedPhotos->fetch(PDO::FETCH_ASSOC) ) {
            $id = $row['file_id'];
            $fn = $row['file_name'];
            $date = $row['created_at'];

            $serve = BlobUtil::getAccessUrlForBlob($id);

            $infostmt = $PDOX->prepare("SELECT photo_id, user_id, description, approved FROM {$p}photo_gallery WHERE blob_id = :blobId");
            $infostmt->execute(array(":blobId" => $id));
            $photoInfo = $infostmt->fetch(PDO::FETCH_ASSOC);

            if ($photoInfo["approved"] == "1") {
                continue;
            }

            $photoDate = new DateTime($date);
            $formattedDate = $photoDate->format("m-d-y") . " at " . $photoDate->format("h:i A");

            $namestmt = $PDOX->prepare("SELECT displayname FROM {$p}lti_user WHERE user_id = :user_id;");
            $namestmt->execute(array(":user_id" => $photoInfo["user_id"]));
            $name = $namestmt->fetch(PDO::FETCH_ASSOC);

            echo '<div class="gallery-column">
            <a href="javascript:void(0);" role="button" data-toggle="modal" data-target="#image'.$id.'" class="image-link">
                <img class="gallery-image" src="'.addSession($serve).'">
            </a>
          </div>
          <div id="image'.$id.'" class="modal fade" role="dialog">
            <div class="modal-dialog modal-lg">
                <!-- Modal content-->
                <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4>Photo added by '.$name["displayname"].'<br /><small>'.$formattedDate.'</small></h4>
                    <a href="photo-approve.php?photo_id='.$photoInfo["photo_id"].'" class="btn btn-success pull-right"><span class="fa fa-check" aria-hidden="true"></span> Approve Photo</a>';
            if ($USER->instructor || $USER->id == $photoInfo["user_id"]) {
                echo '<a href="photo-delete.php?id='.$id.'"><span class="fa fa-trash" aria-hidden="true"></span> Delete Photo</a>';
            }
            echo '
                </div>
                <div class="modal-body">
                    <p>'.$photoInfo["description"].'</p>
                    <img class="image-large" src="'.addSession($serve).'">
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

        if ( $count == 0 ) echo "<p><em>No photos are pending approval.</em></p>\n";

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
    </script>
<?php
$OUTPUT->footerEnd();
