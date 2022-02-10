<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "users";

$conn = new PDO("mysql:host=$servername;dbname=$dbname",$username,$password);
if($_GET)
{
 $id = $_GET["id"];
 $sqlDelete = "delete from user_table where id = ?";
 $query = $conn->prepare($sqlDelete);
 $result = $query->execute(array($id));
 header('Location: mesajlar.html');
}
