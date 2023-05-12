<!-- 
    This file is for managin the auto-import behavior for the tool.
    It should be required into the index.php file.

    If you are implementing a new auto-import, you can copy this file
    into your tool an repurpose it.

    $SETTINGS_TO_COPY and copyToolData() should be used to tailor it to
    the specific tool implementation. The remainder of the code should
    be pretty generic, but of course it can be adjusted as needed based
    on the requirements of the implementation.
 -->
<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;

$LAUNCH = LTIX::requireData();

/** Specific to the tool */
$SETTINGS_TO_COPY = [];
/** Specific to the tool */
function copyToolData($prevLinkId)
{
    global $CFG, $LINK, $PDOX;
    $p = $CFG->dbprefix;
    $currentTime = new DateTime('now', new DateTimeZone($CFG->timezone));
    $currentTime = $currentTime->format("Y-m-d H:i:s");
    // Copy over db table data
    $tableName = 'gallery_main';
    $query = "INSERT INTO {$p}{$tableName} (link_id, approval)
    SELECT :linkId, approval
    FROM {$p}{$tableName}
    WHERE link_id = :previousLinkId";
    $arr = array(
        ":previousLinkId" => $prevLinkId,
        ":linkId" => $LINK->id,
    );
    $PDOX->queryDie($query, $arr);
}

/** 
 *  For most auto-imports, you won't need to change anything below
 */

/** Generic */
function getSettingsByLinkId($link_id)
{
    global $p, $PDOX;
    $row = $PDOX->rowDie(
        "SELECT settings FROM {$p}lti_link WHERE link_id = :ID",
        array(":ID" => $link_id)
    );
    if ($row === false) return array();
    $json = $row['settings'];
    if ($json === null) return array();
    try {
        $retval = json_decode($json, true);
    } catch (Exception $e) {
        $retval = array();
    }
    return $retval;
}

/** Generic */
function copySettingsFromLink($otherLinkId, $settingsList)
{
    global $LAUNCH;
    $prevSettings = getSettingsByLinkId($otherLinkId);
    foreach ($settingsList as $settingsProp) {
        // Import settings from previous link
        if (array_key_exists($settingsProp, $prevSettings)) {
            $LAUNCH->link->settingsSet($settingsProp, $prevSettings[$settingsProp]);
        }
    }
}

// Check if auto-initialization has already occurred
$autoInitialized = $LAUNCH->link->settingsGet("auto-initialized", false);
if (!$autoInitialized) {
    // If auto-initialization hasn't occurred, import all associated data for that link
    $previousIds = LTIX::getLatestHistoryIds();
    $previousIds = ['link_id' => 100];
    if ($previousIds) {
        $prevLinkId = $previousIds['link_id'];
        copyToolData($prevLinkId);
        // Copy over settings from the lti JSON
        $prevSettings = getSettingsByLinkId($prevLinkId);
        copySettingsFromLink($prevLinkId, $SETTINGS_TO_COPY);
        // Make sure to mark the settings as having been auto-initialized
        $LAUNCH->link->settingsSet("auto-initialized", true);
    }
}
