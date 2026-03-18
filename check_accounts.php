<?php
$conn = mysqli_connect('localhost', 'root', '', 'cbfinance');
if ($conn) {
    $r = mysqli_query($conn, "SELECT account_code, account_name FROM chart_of_accounts WHERE account_code IN ('4101','4201','4202','4204','4205')");
    while($row = mysqli_fetch_assoc($r)) {
        echo $row['account_code'] . ': ' . $row['account_name'] . PHP_EOL;
    }
} else {
    echo "Connection failed: " . mysqli_connect_error();
}
?>
