<?php


if($_POST)
{
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "users";

        $conn = new mysqli($servername, $username, $password, $dbname);

        $id = $_POST['id'];
        $konu = $_POST['konu'];
        $mesaj = $_POST['mesaj'];
        echo $_POST['id'] ;

        if ($conn->connect_error) {
            die("Connection failed:" . $conn->connect_error);
        }else{
            $updateSQLText = "UPDATE user_table SET konu = $konu, mesaj = '$mesaj' WHERE  id = $id";
            echo  $updateSQLText;
            if ($conn->query($updateSQLText) === true) {
               ?>
                <script>alert("Başarılı")</script>
               <?php
            }else{
                ?>
                <script>alert("Başarısız")</script>
                <?php
            }
            header('Location: mesajlar.html');
        }

} else {

    echo "Başarısız bir istek";
    header('Location: mesajlar.html');

}


?>