<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

// Responder para requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/database.php';
include_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

// Verificar método da requisição
$method = $_SERVER['REQUEST_METHOD'];

// Obter o corpo da requisição
$input = json_decode(file_get_contents("php://input"), true);

try {
    if ($method == 'POST') {
        // Verificar se o ID foi especificado
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "ID da requisição não especificado"]);
            exit();
        }
        
        // Validação básica
        if (empty($input['status']) || empty($input['usuario_id'])) {
            http_response_code(400);
            echo json_encode(["message" => "Dados incompletos"]);
            exit();
        }
        
        // Verificar se a requisição existe
        $checkQuery = "SELECT id FROM requisicoes WHERE id = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["message" => "Requisição não encontrada"]);
            exit();
        }
        
        // Atualizar o status da requisição
        $updateQuery = "UPDATE requisicoes SET status = :status WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':status', $input['status']);
        $updateStmt->bindParam(':id', $id);
        
        if ($updateStmt->execute()) {
            // Registrar no histórico
            $descricao = 'Status atualizado para: ' . $input['status'];
            
            if (!empty($input['fornecedor'])) {
                $descricao .= '. Fornecedor: ' . $input['fornecedor'];
            }
            
            if (!empty($input['numero_pedido'])) {
                $descricao .= '. Pedido: ' . $input['numero_pedido'];
            }
            
            if (!empty($input['data_entrega_estimada'])) {
                $descricao .= '. ETA: ' . $input['data_entrega_estimada'];
            }
            
            $historicoQuery = "INSERT INTO requisicao_historico 
                              (requisicao_id, usuario_id, acao, descricao) 
                              VALUES 
                              (:requisicao_id, :usuario_id, :acao, :descricao)";
            
            $historicoStmt = $db->prepare($historicoQuery);
            $historicoStmt->bindParam(':requisicao_id', $id);
            $historicoStmt->bindParam(':usuario_id', $input['usuario_id']);
            $historicoStmt->bindValue(':acao', 'Acompanhamento');
            $historicoStmt->bindParam(':descricao', $descricao);
            $historicoStmt->execute();
            
            http_response_code(200);
            echo json_encode(["message" => "Acompanhamento atualizado com sucesso"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao atualizar acompanhamento"]);
        }
    } else {
        http_response_code(405);
        echo json_encode(["message" => "Método não permitido"]);
    }
} catch (Exception $e) {
    error_log("Erro em requisicoes_acompanhamento.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Erro interno do servidor: " . $e->getMessage()]);
}
?>