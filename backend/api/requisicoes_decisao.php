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
        if (empty($input['decisao']) || empty($input['usuario_id'])) {
            http_response_code(400);
            echo json_encode(["message" => "Dados incompletos"]);
            exit();
        }
        
        // Verificar se a requisição existe
        $checkQuery = "SELECT id, status FROM requisicoes WHERE id = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["message" => "Requisição não encontrada"]);
            exit();
        }
        
        $requisicao = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Registrar a decisão na tabela de aprovações
        $query = "INSERT INTO requisicao_aprovacoes 
                  (requisicao_id, aprovador_id, decisao, comentario, data_decisao) 
                  VALUES 
                  (:requisicao_id, :aprovador_id, :decisao, :comentario, NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':requisicao_id', $id);
        $stmt->bindParam(':aprovador_id', $input['usuario_id']);
        $stmt->bindParam(':decisao', $input['decisao']);
        $stmt->bindParam(':comentario', $input['comentario']);
        
        if ($stmt->execute()) {
            // Atualizar o status da requisição
            $novoStatus = ($input['decisao'] == 'APROVADA') ? 'Aprovada' : 'Rejeitada';
            
            // Se for aprovação, atribuir automaticamente a um comprador
            if ($input['decisao'] == 'APROVADA') {
                // Buscar um usuário com perfil de COMPRAS
                $compradorQuery = "SELECT id FROM usuarios WHERE nivel_liberacao = 'COMPRAS' AND ativo = 1 LIMIT 1";
                $compradorStmt = $db->prepare($compradorQuery);
                $compradorStmt->execute();
                
                $comprador_id = null;
                if ($compradorStmt->rowCount() > 0) {
                    $comprador = $compradorStmt->fetch(PDO::FETCH_ASSOC);
                    $comprador_id = $comprador['id'];
                }
                
                $updateQuery = "UPDATE requisicoes SET status = :status, comprador_id = :comprador_id WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':status', $novoStatus);
                $updateStmt->bindParam(':comprador_id', $comprador_id);
                $updateStmt->bindParam(':id', $id);
            } else {
                $updateQuery = "UPDATE requisicoes SET status = :status WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':status', $novoStatus);
                $updateStmt->bindParam(':id', $id);
            }
            
            if ($updateStmt->execute()) {
                // Registrar no histórico
                $acao = ($input['decisao'] == 'APROVADA') ? 'Aprovação' : 'Rejeição';
                $descricao = $acao . '. Comentário: ' . ($input['comentario'] || 'Nenhum comentário');
                
                $historicoQuery = "INSERT INTO requisicao_historico 
                                  (requisicao_id, usuario_id, acao, descricao) 
                                  VALUES 
                                  (:requisicao_id, :usuario_id, :acao, :descricao)";
                
                $historicoStmt = $db->prepare($historicoQuery);
                $historicoStmt->bindParam(':requisicao_id', $id);
                $historicoStmt->bindParam(':usuario_id', $input['usuario_id']);
                $historicoStmt->bindValue(':acao', $acao);
                $historicoStmt->bindParam(':descricao', $descricao);
                $historicoStmt->execute();
                
                http_response_code(200);
                echo json_encode(["message" => "Decisão registrada com sucesso"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Erro ao atualizar status da requisição"]);
            }
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao registrar decisão"]);
        }
    } else {
        http_response_code(405);
        echo json_encode(["message" => "Método não permitido"]);
    }
} catch (Exception $e) {
    error_log("Erro em requisicoes_decisao.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Erro interno do servidor: " . $e->getMessage()]);
}
?>