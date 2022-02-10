<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "users";

$conn = new mysqli($servername, $username, $password, $dbname);

$Sil = "Sil";
$Güncelle = "Güncelle";

$sql = "SELECT * FROM user_table";
$users = $conn->query($sql);

if (isset($_POST)) {

    echo "<tr>
        <td style='height: 50px; width: 5% ;text-align: center;font-weight: bold'>Id</td>
        <td style='height: 50px; width: 12%;text-align: center;font-weight: bold'>Ad</td>
        <td style='height: 50px; width: 12%;text-align: center;font-weight: bold'>Soyad</td>
        <td style='height: 50px; width: 12%;text-align: center;font-weight: bold'>Telefon</td>
        <td style='height: 50px; width: 12%;text-align: center;font-weight: bold'>Konu</td>
        <td style='height: 50px; width: 30%;text-align: center;font-weight: bold'>Mesaj</td>
        <td style='height: 50px; width: 5% ;text-align: center;font-weight: bold'>Sil</td>
        <td style='height: 50px; width: 20%;text-align: center;font-weight: bold'>Güncelle</td>
    </tr>";

    if (mysqli_num_rows($users)) {
        foreach ($users as $row) {
            echo "
                  <tr>
                  <td id='rowID' style='height: 50px; width: 5% ;text-align: center;font-weight: bold'>" . $row['id'] . "</td>
                  <td id='rowAd' style='height: 50px; width: 12% ;text-align: center;font-weight: bold'>" . $row['ad'] . "</td>
                  <td id= 'rowSoyad' style='height: 50px; width: 12% ;text-align: center;font-weight: bold'>" . $row['soyad'] . "</td>
                  <td id= ''style='height: 50px; width: 12% ;text-align: center;font-weight: bold'>" . $row['telefon'] . "</td>
                  <form id='updateForm'>
                  <td style='height: 42px; width: 12%; text-align: left;padding-left: 4%'>
                    <select name='konuName' id='" . $row['id'] . "select' >
                        <option id='1' value='oneri'>  Öneri  </option>
                        <option id='2' value='talep'>  Talep  </option>
                        <option id='3' value='sikayet'>Şikayet</option>
                    </select>
                    <script>
                      function setSelected(selectedIndex) {
                          console.log(selectedIndex+'Selected Indexler');
                          if(selectedIndex === 0) {
                                document.getElementById('" . $row['id'] . "select').selectedIndex = 0;
                          }else if(selectedIndex === 1) {
                                document.getElementById('" . $row['id'] . "select').selectedIndex = 1;
                          }else{
                                document.getElementById('" . $row['id'] . "select').selectedIndex = 2;
                          } 
                      }
                      setSelected(" . $row['konu'] . ")
                    </script>
                  </td>
                  <td style='height: 50px; width: 20% ;text-align: center;font-weight: bold'> <textarea id='" . $row['id'] . "textArea'   class='userInput'  style='width: 90%; height: 90%;'>" . $row['mesaj'] . "</textarea> </td>
                 
                  <td style='height: 50px; width: 5% ;text-align: center;font-weight:  bold'>";
                  echo "<a href='delete.php?id=" . $row['id'] . "'>
                              <img  src='assets/delete.png' width='50px' height='50px' style='padding: 10px'> 
                        </a>
                  </td>
                  <td style=' height: 50px; width: 5%; text-align: center; font-weight:bold; '>  
                  <div  id='" . $row['id'] . "divUpdate' style='cursor: pointer'>
                       <img src='assets/update.png' width='50px' height='50px' style='padding: 10px'>
                  </div>
                  </td>
                   </form>
                  </tr>
                  <script>
                       $('#" . $row['id'] . "divUpdate').click(function ()
                       {  
                           var konuIndex  = document.getElementById('" . $row['id'] . "select').selectedIndex;
                           var meajValue = document.getElementById('" . $row['id'] . "textArea').value;
                           
                           $.ajax({
                               type: 'POST',
                               url: 'update.php',
                               dataType:'json',
                               data: {
                               mesaj:meajValue,
                               konu:konuIndex,
                               id: '" . $row['id'] . "',
                               },
                               success: function(data) {
                                  
                               },
                               error:  function (eror){
                                   console.log(JSON.stringify(error));
                               }
                           });
                           
                       }).done()
                  </script>
                  ";
        }
    } else {
        echo "<tr>Kayıt Bulunamadı</tr>";
    }

    echo $users;

} else {

    echo "<h1>Bu bir post isteği değildir</h1>";

}



