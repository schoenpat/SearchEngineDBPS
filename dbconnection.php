<?php

function get_connection()
{
    $db_string = 'mysql:host=localhost;dbname=dhbw_crawler';
    $db_user = '';
    $db_password = '';
    return new PDO($db_string, $db_user, $db_password);
}
