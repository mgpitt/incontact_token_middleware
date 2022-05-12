<?php

require __DIR__ . '/vendor/autoload.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: X-Requested-With,content-type,X-Inbenta-Token');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
    die;
}

use App\Incontact;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $app = new Incontact($_ENV);
    $response = $app->getAccessKey();
} catch (Exception $e) {
    $response = (object) ["message" => $e->getMessage(), "error_code" => 400];
}

if (isset($response->error_code)) {
    http_response_code($response->error_code);
    unset($response->error_code);
}

echo json_encode($response);
