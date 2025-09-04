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

try {
    // Verificar se foi solicitado filtrar por nível
    $nivel = $_GET['nivel'] ?? null;
    
    // Buscar usuários do banco de dados
    $filtros = [];
    if ($nivel) {
        $filtros['nivel'] = $nivel;
    }
    
    $usuarios = $functions->getUsuarios($filtros);
    
    http_response_code(200);
    echo json_encode($usuarios);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Erro interno do servidor: " . $e->getMessage()]);
}
?>