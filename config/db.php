<?php

$host = "localhost";
$user = "root";
$password = "Vendetta7080";
$dbname = "mailroom_system";

$conn = new mysqli($host,$user,$password,$dbname);

if($conn->connect_error){
    die("Connection Failed: " . $conn->connect_error);
}
?>