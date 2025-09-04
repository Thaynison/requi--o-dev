<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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

// Obter o corpo da requisição para POST/PUT
$input = [];
if ($method === 'POST' || $method === 'PUT') {
    $input = json_decode(file_get_contents("php://input"), true);
}

try {
    switch ($method) {
        case 'GET':
            // Se foi especificado um ID, buscar requisição específica
            if (isset($_GET['id'])) {
                $id = $_GET['id'];
                
                // Buscar requisição específica por ID
                $query = "SELECT r.*, 
                                 u_sol.nome as solicitante_nome,
                                 u_apr.nome as aprovador_nome,
                                 u_comp.nome as comprador_nome
                          FROM requisicoes r 
                          LEFT JOIN usuarios u_sol ON r.solicitante_id = u_sol.id 
                          LEFT JOIN usuarios u_apr ON r.aprovador_id = u_apr.id 
                          LEFT JOIN usuarios u_comp ON r.comprador_id = u_comp.id 
                          WHERE r.id = :id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $requisicao = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Buscar itens da requisição
                    $itensQuery = "SELECT descricao, quantidade, preco_unitario, unidade_medida 
                                  FROM requisicao_itens 
                                  WHERE requisicao_id = :requisicao_id";
                    $itensStmt = $db->prepare($itensQuery);
                    $itensStmt->bindParam(':requisicao_id', $id);
                    $itensStmt->execute();
                    $requisicao['itens'] = $itensStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Buscar histórico
                    $historicoQuery = "SELECT rh.*, u.nome as usuario_nome 
                                      FROM requisicao_historico rh 
                                      LEFT JOIN usuarios u ON rh.usuario_id = u.id 
                                      WHERE rh.requisicao_id = :requisicao_id 
                                      ORDER BY rh.data_acao DESC";
                    $historicoStmt = $db->prepare($historicoQuery);
                    $historicoStmt->bindParam(':requisicao_id', $id);
                    $historicoStmt->execute();
                    $requisicao['historico'] = $historicoStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode($requisicao);
                } else {
                    http_response_code(404);
                    echo json_encode(["message" => "Requisição não encontrada"]);
                }
            } else {
                // Buscar todas as requisições com filtros
                $filters = [
                    'status' => $_GET['status'] ?? null,
                    'search' => $_GET['search'] ?? null,
                    'user_id' => $_GET['user_id'] ?? null
                ];
                
                // Construir query base
                $query = "SELECT r.*, 
                                 u_sol.nome as solicitante_nome,
                                 u_apr.nome as aprovador_nome,
                                 u_comp.nome as comprador_nome
                          FROM requisicoes r 
                          LEFT JOIN usuarios u_sol ON r.solicitante_id = u_sol.id 
                          LEFT JOIN usuarios u_apr ON r.aprovador_id = u_apr.id 
                          LEFT JOIN usuarios u_comp ON r.comprador_id = u_comp.id 
                          WHERE 1=1";
                
                $params = [];
                
                // Aplicar filtros
                if (!empty($filters['status'])) {
                    $query .= " AND r.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                if (!empty($filters['search'])) {
                    $query .= " AND (r.titulo LIKE :search OR r.descricao LIKE :search OR u_sol.nome LIKE :search)";
                    $params[':search'] = '%' . $filters['search'] . '%';
                }
                
                if (!empty($filters['user_id'])) {
                    $query .= " AND (r.solicitante_id = :user_id OR r.aprovador_id = :user_id OR r.comprador_id = :user_id)";
                    $params[':user_id'] = $filters['user_id'];
                }
                
                $query .= " ORDER BY r.criada_em DESC";
                
                $stmt = $db->prepare($query);
                
                // Bind dos parâmetros
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                
                $stmt->execute();
                $requisicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Buscar itens para cada requisição
                foreach ($requisicoes as &$requisicao) {
                    $itensQuery = "SELECT descricao, quantidade, preco_unitario, unidade_medida 
                                  FROM requisicao_itens 
                                  WHERE requisicao_id = :requisicao_id";
                    $itensStmt = $db->prepare($itensQuery);
                    $itensStmt->bindParam(':requisicao_id', $requisicao['id']);
                    $itensStmt->execute();
                    $requisicao['itens'] = $itensStmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                echo json_encode($requisicoes);
            }
            break;
            
        case 'POST':
            // Criar nova requisição
            error_log("Dados recebidos para criação: " . print_r($input, true));
            
            // Validação básica
            $requiredFields = ['titulo', 'solicitante_id', 'aprovador_id'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        "message" => "Campo obrigatório faltando: " . $field,
                        "required" => $requiredFields,
                        "received" => $input
                    ]);
                    exit();
                }
            }
            
            // Gerar código da requisição
            $codigo = "RC-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $query = "INSERT INTO requisicoes 
                      (codigo, titulo, descricao, centro_custo, data_necessidade, 
                       fornecedor_sugerido, status, solicitante_id, aprovador_id) 
                      VALUES 
                      (:codigo, :titulo, :descricao, :centro_custo, :data_necessidade, 
                       :fornecedor_sugerido, :status, :solicitante_id, :aprovador_id)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':codigo', $codigo);
            $stmt->bindParam(':titulo', $input['titulo']);
            $stmt->bindParam(':descricao', $input['descricao']);
            $stmt->bindParam(':centro_custo', $input['centro_custo']);
            $stmt->bindParam(':data_necessidade', $input['data_necessidade']);
            $stmt->bindParam(':fornecedor_sugerido', $input['fornecedor_sugerido']);
            $stmt->bindParam(':status', $input['status']);
            $stmt->bindParam(':solicitante_id', $input['solicitante_id']);
            $stmt->bindParam(':aprovador_id', $input['aprovador_id']);
            
            if ($stmt->execute()) {
                $requisicao_id = $db->lastInsertId();
                
                // Inserir itens
                if (!empty($input['itens']) && is_array($input['itens'])) {
                    foreach ($input['itens'] as $item) {
                        $itemQuery = "INSERT INTO requisicao_itens 
                                     (requisicao_id, descricao, quantidade, preco_unitario, unidade_medida) 
                                     VALUES 
                                     (:requisicao_id, :descricao, :quantidade, :preco_unitario, :unidade_medida)";
                        
                        $itemStmt = $db->prepare($itemQuery);
                        $itemStmt->bindParam(':requisicao_id', $requisicao_id);
                        $itemStmt->bindParam(':descricao', $item['descricao']);
                        $itemStmt->bindParam(':quantidade', $item['quantidade']);
                        $itemStmt->bindParam(':preco_unitario', $item['preco_unitario']);
                        $itemStmt->bindParam(':unidade_medida', $item['unidade_medida']);
                        $itemStmt->execute();
                    }
                }
                
                // Registrar no histórico
                $historicoQuery = "INSERT INTO requisicao_historico 
                                  (requisicao_id, usuario_id, acao, descricao) 
                                  VALUES 
                                  (:requisicao_id, :usuario_id, :acao, :descricao)";
                
                $historicoStmt = $db->prepare($historicoQuery);
                $historicoStmt->bindParam(':requisicao_id', $requisicao_id);
                $historicoStmt->bindParam(':usuario_id', $input['solicitante_id']);
                $historicoStmt->bindValue(':acao', 'Criação');
                $historicoStmt->bindValue(':descricao', 'Requisição criada');
                $historicoStmt->execute();
                
                http_response_code(201);
                echo json_encode([
                    "message" => "Requisição criada com sucesso",
                    "id" => $requisicao_id,
                    "codigo" => $codigo
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Erro ao criar requisição"]);
            }
            break;
            
        case 'PUT':
            // Atualizar requisição existente
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(["message" => "ID da requisição não especificado"]);
                break;
            }
            
            error_log("Dados recebidos para atualização: " . print_r($input, true));
            
            // Verificar se a requisição existe
            $checkQuery = "SELECT id FROM requisicoes WHERE id = :id";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["message" => "Requisição não encontrada"]);
                break;
            }
            
            // Atualizar requisição
            $query = "UPDATE requisicoes SET 
                      titulo = :titulo, 
                      descricao = :descricao, 
                      centro_custo = :centro_custo, 
                      data_necessidade = :data_necessidade, 
                      fornecedor_sugerido = :fornecedor_sugerido, 
                      aprovador_id = :aprovador_id, 
                      status = :status, 
                      atualizada_em = NOW() 
                      WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':titulo', $input['titulo']);
            $stmt->bindParam(':descricao', $input['descricao']);
            $stmt->bindParam(':centro_custo', $input['centro_custo']);
            $stmt->bindParam(':data_necessidade', $input['data_necessidade']);
            $stmt->bindParam(':fornecedor_sugerido', $input['fornecedor_sugerido']);
            $stmt->bindParam(':aprovador_id', $input['aprovador_id']);
            $stmt->bindParam(':status', $input['status']);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                // Remover itens antigos
                $deleteItensQuery = "DELETE FROM requisicao_itens WHERE requisicao_id = :requisicao_id";
                $deleteItensStmt = $db->prepare($deleteItensQuery);
                $deleteItensStmt->bindParam(':requisicao_id', $id);
                $deleteItensStmt->execute();
                
                // Inserir novos itens
                if (!empty($input['itens']) && is_array($input['itens'])) {
                    foreach ($input['itens'] as $item) {
                        $itemQuery = "INSERT INTO requisicao_itens 
                                     (requisicao_id, descricao, quantidade, preco_unitario, unidade_medida) 
                                     VALUES 
                                     (:requisicao_id, :descricao, :quantidade, :preco_unitario, :unidade_medida)";
                        
                        $itemStmt = $db->prepare($itemQuery);
                        $itemStmt->bindParam(':requisicao_id', $id);
                        $itemStmt->bindParam(':descricao', $item['descricao']);
                        $itemStmt->bindParam(':quantidade', $item['quantidade']);
                        $itemStmt->bindParam(':preco_unitario', $item['preco_unitario']);
                        $itemStmt->bindParam(':unidade_medida', $item['unidade_medida']);
                        $itemStmt->execute();
                    }
                }
                
                // Registrar no histórico
                $historicoQuery = "INSERT INTO requisicao_historico 
                                  (requisicao_id, usuario_id, acao, descricao) 
                                  VALUES 
                                  (:requisicao_id, :usuario_id, :acao, :descricao)";
                
                $historicoStmt = $db->prepare($historicoQuery);
                $historicoStmt->bindParam(':requisicao_id', $id);
                $historicoStmt->bindParam(':usuario_id', $input['user_id']);
                $historicoStmt->bindValue(':acao', 'Atualização');
                $historicoStmt->bindValue(':descricao', 'Requisição atualizada');
                $historicoStmt->execute();
                
                http_response_code(200);
                echo json_encode(["message" => "Requisição atualizada com sucesso"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Erro ao atualizar requisição"]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(["message" => "Método não permitido"]);
            break;
    }
} catch (Exception $e) {
    error_log("Erro em requisicoes.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Erro interno do servidor: " . $e->getMessage()]);
}
?>