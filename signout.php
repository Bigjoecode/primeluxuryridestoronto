<?php
/** Customer sign-out. */
require_once __DIR__ . '/includes/customer.php';

customer_logout();
header('Location: index.php');
exit;
