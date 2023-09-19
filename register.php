<?php

$REGISTER_LTI2 = array(
    "name" => "Photo Gallery", // Name of the tool
    "FontAwesome" => "fa-picture-o", // Icon for the tool
    "short_name" => "Photo Gallery",
    "description" => "Add a photo gallery for images to be uploaded and viewed.", // Tool description
    "messages" => array("launch", "launch_grade"),
    "license" => "Apache",
    "languages" => array(
        "English"
    ),
    "analytics" => array(
        "internal"
    ),
    "video" => "https://udayton.warpwire.com/w/GfsEAA/",
    "source_url" => "https://github.com/udaytonapps/gallery",
    // For now Tsugi tools delegate this to /lti/store
    "placements" => array(
        /*
        "course_navigation", "homework_submission",
        "course_home_submission", "editor_button",
        "link_selection", "migration_selection", "resource_selection",
        "tool_configuration", "user_navigation"
        */
    ),
    "screen_shots" => array(
        "images/PG-Image-View.jpg",
        "images/PG-Empty-View.jpg",
        "images/PG-Image-Upload-and-Editor.jpg",
        "images/PG-Gallery-View.jpg",
        "images/PG-Instructor-Gallery-View.jpg"
    )
);
