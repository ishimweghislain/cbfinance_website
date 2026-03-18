<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
$conn = getConnection();
$res = $conn->query("SELECT customer_code, customer_name, customer_id FROM customers WHERE customer_code = 'C0060' OR customer_code = 'C100000'");
while($r = $res->fetch_assoc()) print_r($r);
$lres = $conn->query("SELECT loan_number FROM loan_portfolio WHERE customer_id IN (SELECT customer_id FROM customers WHERE customer_code IN ('C0060', 'C100000'))");
while($l = $lres->fetch_assoc()) print_r($l);
