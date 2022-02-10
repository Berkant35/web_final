<?php



if ($_POST) {
    try {
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "users";
        $conn = new mysqli($servername, $username, $password, $dbname);

        $ad = $_POST['ad'];
        $soyadi = $_POST['soyadi'];
        $phone = $_POST['phone'];
        $konu = $_POST['konu'];
        $mesaj = $_POST['mesaj'];


        switch ($konu) {
            case 'oneri':
                $konu = '0';
                break;
            case 'talep':
                $konu = '1';
                break;
            case 'sikayet':
                $konu = '2';
                break;
        }

        if ($conn->connect_error) {
            die("Connection failed:" . $conn->connect_error);
        }
        $insertSQL = "INSERT INTO `user_table` (`id`, `ad`, `soyad`, `telefon`, `konu`, `mesaj`) 
        VALUES (NULL, '$ad', '$soyadi',' $phone', '$konu', '$mesaj')";
        echo $insertSQL;
        if ($conn->query($insertSQL) === true) {
            ?>

            <script>
                alert('Ekleme başarılı!');
            </script>
            <?php

        } else {
            echo "başarısız";
            ?>
            <script>
                alert('Ekleme başarısız!');
            </script>
            <?php

        }

        ?>
        <?php

    } catch (e) {
        echo "Hata çıktı";
    }
    header('Location: mesajlar.html');
    $conn->close();
}
?>
