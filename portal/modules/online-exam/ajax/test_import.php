<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_FILES['word_file'] = [
    'tmp_name' => 'C:/xampp/htdocs/GCA-Production/sample word file/OES Question Bank.docx',
    'name' => 'OES Question Bank.docx',
    'error' => UPLOAD_ERR_OK,
    'size' => filesize('C:/xampp/htdocs/GCA-Production/sample word file/OES Question Bank.docx')
];

require_once 'C:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

if (!function_exists('hasAnyRole')) {
    function hasAnyRole($roles) { return true; }
}

include 'C:/xampp/htdocs/GCA-Production/portal/modules/online-exam/ajax/import-word-server.php';
