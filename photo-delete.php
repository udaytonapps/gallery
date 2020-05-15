<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;

// Sanity checks
$LAUNCH = LTIX::requireData();

$id = $_REQUEST['id'];
$thumb_id = $_REQUEST['thumb'];
if ( strlen($id) < 1 ) {
    die("File not found");
}

$p = $CFG->dbprefix;
$stmt = $PDOX->prepare("SELECT file_name, path FROM {$p}blob_file
            WHERE file_id = :ID AND context_id = :CID");
$stmt->execute(array(":ID" => $id, ":CID" => $CONTEXT->id));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ( $row === false ) {
    die("File not found");
}

$fn = $row['file_name'];
$path = $row['path'];

if ( isset($_POST["doDelete"]) ) {
    $stmt = $PDOX->prepare("DELETE FROM {$p}blob_file
            WHERE file_id = :ID AND context_id = :CID");
    $stmt->execute(array(":ID" => $id, ":CID" => $CONTEXT->id));

    $stmt = $PDOX->prepare("DELETE FROM {$p}blob_file
            WHERE file_id = :ID AND context_id = :CID");
    $stmt->execute(array(":ID" => $thumb_id, ":CID" => $CONTEXT->id));

    $infostmt = $PDOX->prepare("DELETE FROM {$p}photo_gallery where blob_id = :blobId");
    $infostmt->execute(array(":blobId" => $id));

    $_SESSION['success'] = 'Photo deleted';
    header( 'Location: '.addSession('index.php') ) ;
    return;
}

// Switch to view / controller
$OUTPUT->header();
$OUTPUT->flashMessages();

echo '<div class="container">
        <h4 class="alert alert-danger">Are you sure you want to delete: ' .htmlent_utf8($fn). "? This cannot be undone.</h4>\n";
?>
    <form name=myform enctype="multipart/form-data" method="post">
        <input type=hidden name="id" value="<?php echo $_REQUEST['id']; ?>">
        <p>
            <input class="btn btn-danger" type=submit name=doDelete value="Delete">
            <input class="btn btn-default" type=submit name=doCancel onclick="location='<?php echo(addSession('index.php'));?>'; return false;" value="Cancel">
        </p>
    </form>
    </div>
<?php

$OUTPUT->footer();
