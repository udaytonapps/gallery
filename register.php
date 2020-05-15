<?php

$REGISTER_LTI2 = array(
    "name" => "Photo Gallery", // Name of the tool
    "FontAwesome" => "fa-picture-o", // Icon for the tool
    "short_name" => "Photo Gallery",
    "description" => "A simple photo gallery tool that allows everyone in a course to upload images to a shared gallery.", // Tool description
    "messages" => array("launch"),
    "privacy_level" => "public",  // anonymous, name_only, public
    "license" => "Apache",
    "languages" => array(
        "English",
    ),
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
        "store/screen-01.png",
        "store/screen-02.png",
        "store/screen-03.png",
    )
);
