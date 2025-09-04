<?php
// Habilitar CORS
header("Access-Control-Allow-Origin: http://127.0.0.1:5500");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/database.php';
include_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

// Obter dados do POST
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->username) && !empty($data->password)) {
    $username = $data->username;
    $password = $data->password;
    
    $user = $functions->login($username, $password);
    
    if ($user) {
        http_response_code(200);
        echo json_encode(array(
            "message" => "Login realizado com sucesso.",
            "user" => $user
        ));
    } else {
        http_response_code(401);
        echo json_encode(array("message" => "Usuário ou senha inválidos."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Dados incompletos."));
}
?>