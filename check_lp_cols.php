<?php
require 'config/database.php';
$c=getConnection();
$r=$c->query('DESCRIBE loan_instalments');
while($row=$r->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
