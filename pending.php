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

        .magnify {
            cursor: pointer;
            font-size: 20px;
        }

        .approve-btn {
            margin-left: 20px;
        }

        .nav-spacing {
            margin-top: 50px;
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
                <a class="navbar-brand" href="index.php"><span class="fa fa-photo-o" aria-hidden="true"></span> Photo
                    Gallery</a>
            </div>
        </div>
    </nav>

<?php $OUTPUT->flashMessages(); ?>

    <div class="container-fluid nav-spacing">
    <h3 class="pending-title">Photos Pending Approval <br /><small><a href="index.php"><span class="fa fa-chevron-left" aria-hidden="true"></span> Back</a></small></h3>
    <p>Click on the photo to view a larger version and use the check mark button to approve the photo or the delete
        button to delete it.</p>
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

            $fn = $photoInfo['file_name'];
            $date = $photoInfo['created_at'];

            $serve = BlobUtil::getAccessUrlForBlob($id);
            $thumb = BlobUtil::getAccessUrlForBlob($thumbId);

            if ($row["approved"] == "1") {
                continue;
            } else {
                $count++; // This photo needs approval so count it
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
                    <div style="display:flex">';
            if ($USER->instructor) {
                ?>
                <div style="flex-grow: 1">
                    <a href="photo-delete.php?id=<?= $id ?>&thumb=<?= $thumbId ?>"><span class="fa fa-trash"
                                                                                         aria-hidden="true"></span>
                        Delete Photo</a>
                    <a href="photo-approve.php?photo_id=<?php echo $row["photo_id"] ?>" class="approve-btn"><span
                                class="fa fa-check" aria-hidden="true"></span> Approve Photo</a>
                </div>
                <?php
            }
            ?>
            <ul class="pager" style="margin: 0;">
                <li><a href="#" data-dismiss="modal" onclick="gotoprev(<?= $count ?>)">Previous</a></li>
                <li><a href="#" data-dismiss="modal" onclick="gotonext(<?= $count ?>)">Next</a></li>
            </ul>
            <?php
            echo '
                    </div>
                </div>
                <div class="modal-body">';
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
        }
        echo("</div>\n");

        if ($count == 0) echo "<p><em>No photos are pending approval.</em></p>\n";

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
                current_index = links.length - 1;
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
