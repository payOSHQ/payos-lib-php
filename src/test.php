<?php
namespace PayOS;
require 'PayOS.php';

$payos = new PayOS('client_id','api_key','checksum');


var_dump($payos->getPaymentLinkInformation(10176));
