<?php

// The SQL to uninstall this tool
$DATABASE_UNINSTALL = array(
);

// The SQL to create the tables if they don't exist
$DATABASE_INSTALL = array(
    array( "{$CFG->dbprefix}photo_gallery",
        "create table {$CFG->dbprefix}photo_gallery (
    photo_id    INTEGER NOT NULL AUTO_INCREMENT,
    user_id     INTEGER NOT NULL,
    description TEXT NULL,
    blob_id     INTEGER NOT NULL,
    thumb_id    INTEGER NULL,
    approved    BOOL DEFAULT 0,
    
    PRIMARY KEY(photo_id)
) ENGINE = InnoDB DEFAULT CHARSET=utf8"),
    array( "{$CFG->dbprefix}gallery_main",
        "create table {$CFG->dbprefix}gallery_main (
    link_id     INTEGER NOT NULL,
    approval    BOOL DEFAULT 0,
        
    PRIMARY KEY(link_id)
) ENGINE = InnoDB DEFAULT CHARSET=utf8")
);

$DATABASE_UPGRADE = function($oldversion) {
    global $CFG, $PDOX;

    if ( ! $PDOX->columnExists('thumb_id', "{$CFG->dbprefix}photo_gallery") ) {
        $sql= "ALTER TABLE {$CFG->dbprefix}photo_gallery ADD thumb_id INTEGER NULL";
        echo("Upgrading: ".$sql."<br/>\n");
        error_log("Upgrading: ".$sql);
        $q = $PDOX->queryDie($sql);
    }

    return 202002101610;
};