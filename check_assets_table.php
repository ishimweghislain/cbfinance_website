<?php
$conn = mysqli_connect('localhost', 'root', '', 'cbfinance');
if ($conn) {
    $r = mysqli_query($conn, "DESC assets");
    while($row = mysqli_fetch_assoc($r)) {
        echo $row['Field'] . ': ' . $row['Type'] . PHP_EOL;
    }
} else {
    echo "Connection failed: " . mysqli_connect_error();
}
?>
