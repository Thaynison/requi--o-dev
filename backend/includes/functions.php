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

class Functions {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function login($username, $password) {
        $query = "SELECT id, nome, email, username, senha, setor, nivel_liberacao 
                  FROM usuarios 
                  WHERE username = :username AND ativo = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($password, $row['senha'])) {
                // Remover a senha antes de retornar
                unset($row['senha']);
                return $row;
            }
        }
        return false;
    }
    
    public function getUsuarios($filtros = array()) {
        $query = "SELECT id, nome, email, username, setor, nivel_liberacao 
                  FROM usuarios 
                  WHERE ativo = 1";
        
        if(isset($filtros['nivel']) && !empty($filtros['nivel'])) {
            $query .= " AND nivel_liberacao = :nivel";
        }
        
        $stmt = $this->conn->prepare($query);
        
        if(isset($filtros['nivel']) && !empty($filtros['nivel'])) {
            $stmt->bindParam(':nivel', $filtros['nivel']);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRequisicaoPorId($id) {
        $query = "SELECT r.*, 
                        u_sol.nome as solicitante_nome,
                        u_apr.nome as aprovador_nome,
                        u_comp.nome as comprador_nome
                FROM requisicoes r 
                LEFT JOIN usuarios u_sol ON r.solicitante_id = u_sol.id 
                LEFT JOIN usuarios u_apr ON r.aprovador_id = u_apr.id 
                LEFT JOIN usuarios u_comp ON r.comprador_id = u_comp.id 
                WHERE r.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $requisicao = $stmt->fetch(PDO::FETCH_ASSOC);
            $requisicao['itens'] = $this->getItensRequisicao($id);
            return $requisicao;
        }
        
        return null;
    }
    
    public function getRequisicoes($filtros = array()) {
        $query = "SELECT r.*, 
                         u_sol.nome as solicitante_nome,
                         u_apr.nome as aprovador_nome,
                         u_comp.nome as comprador_nome
                  FROM requisicoes r 
                  LEFT JOIN usuarios u_sol ON r.solicitante_id = u_sol.id 
                  LEFT JOIN usuarios u_apr ON r.aprovador_id = u_apr.id 
                  LEFT JOIN usuarios u_comp ON r.comprador_id = u_comp.id 
                  WHERE 1=1";
        
        if(isset($filtros['status']) && !empty($filtros['status'])) {
            $query .= " AND r.status = :status";
        }
        
        if(isset($filtros['search']) && !empty($filtros['search'])) {
            $query .= " AND (r.titulo LIKE :search OR r.descricao LIKE :search OR u_sol.nome LIKE :search)";
        }
        
        if(isset($filtros['user_id']) && !empty($filtros['user_id'])) {
            $query .= " AND (r.solicitante_id = :user_id OR r.aprovador_id = :user_id OR r.comprador_id = :user_id)";
        }
        
        $query .= " ORDER BY r.criada_em DESC";
        
        $stmt = $this->conn->prepare($query);
        
        if(isset($filtros['status']) && !empty($filtros['status'])) {
            $stmt->bindParam(':status', $filtros['status']);
        }
        
        if(isset($filtros['search']) && !empty($filtros['search'])) {
            $search = "%" . $filtros['search'] . "%";
            $stmt->bindParam(':search', $search);
        }
        
        if(isset($filtros['user_id']) && !empty($filtros['user_id'])) {
            $stmt->bindParam(':user_id', $filtros['user_id']);
        }
        
        $stmt->execute();
        
        $requisicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar itens para cada requisição
        foreach($requisicoes as &$requisicao) {
            $requisicao['itens'] = $this->getItensRequisicao($requisicao['id']);
        }
        
        return $requisicoes;
    }
    
    public function getItensRequisicao($requisicao_id) {
        $query = "SELECT descricao, quantidade, preco_unitario, unidade_medida 
                  FROM requisicao_itens 
                  WHERE requisicao_id = :requisicao_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':requisicao_id', $requisicao_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function criarRequisicao($dados) {
        // Gerar código da requisição
        $codigo = $this->gerarCodigoRequisicao();
        
        $query = "INSERT INTO requisicoes 
                (codigo, titulo, descricao, centro_custo, data_necessidade, 
                fornecedor_sugerido, status, solicitante_id, aprovador_id) 
                VALUES 
                (:codigo, :titulo, :descricao, :centro_custo, :data_necessidade, 
                :fornecedor_sugerido, :status, :solicitante_id, :aprovador_id)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':titulo', $dados['titulo']);
        $stmt->bindParam(':descricao', $dados['descricao']);
        $stmt->bindParam(':centro_custo', $dados['centro_custo']);
        $stmt->bindParam(':data_necessidade', $dados['data_necessidade']);
        $stmt->bindParam(':fornecedor_sugerido', $dados['fornecedor_sugerido']);
        $stmt->bindParam(':status', $dados['status']);
        $stmt->bindParam(':solicitante_id', $dados['solicitante_id']);
        $stmt->bindParam(':aprovador_id', $dados['aprovador_id']);
        
        if($stmt->execute()) {
            $requisicao_id = $this->conn->lastInsertId();
            
            // Inserir itens
            if(isset($dados['itens']) && is_array($dados['itens'])) {
                $this->salvarItensRequisicao($requisicao_id, $dados['itens']);
            }
            
            // Registrar no histórico
            $this->registrarHistorico($requisicao_id, $dados['solicitante_id'], 'Criação', 'Requisição criada');
            
            return $requisicao_id;
        }
        
        return false;
    }
    
    public function atualizarRequisicao($id, $dados) {
        $query = "UPDATE requisicoes SET 
                  titulo = :titulo, descricao = :descricao, centro_custo = :centro_custo, 
                  data_necessidade = :data_necessidade, fornecedor_sugerido = :fornecedor_sugerido, 
                  aprovador_id = :aprovador_id, status = :status, atualizada_em = NOW() 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':titulo', $dados['titulo']);
        $stmt->bindParam(':descricao', $dados['descricao']);
        $stmt->bindParam(':centro_custo', $dados['centro_custo']);
        $stmt->bindParam(':data_necessidade', $dados['data_necessidade']);
        $stmt->bindParam(':fornecedor_sugerido', $dados['fornecedor_sugerido']);
        $stmt->bindParam(':aprovador_id', $dados['aprovador_id']);
        $stmt->bindParam(':status', $dados['status']);
        $stmt->bindParam(':id', $id);
        
        if($stmt->execute()) {
            // Atualizar itens (remover os antigos e inserir os novos)
            $this->removerItensRequisicao($id);
            
            if(isset($dados['itens']) && is_array($dados['itens'])) {
                $this->salvarItensRequisicao($id, $dados['itens']);
            }
            
            // Registrar no histórico
            $this->registrarHistorico($id, $dados['user_id'], 'Atualização', 'Requisição atualizada');
            
            return true;
        }
        
        return false;
    }
    
    private function salvarItensRequisicao($requisicao_id, $itens) {
        foreach($itens as $item) {
            $query = "INSERT INTO requisicao_itens 
                      (requisicao_id, descricao, quantidade, preco_unitario, unidade_medida) 
                      VALUES 
                      (:requisicao_id, :descricao, :quantidade, :preco_unitario, :unidade_medida)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':requisicao_id', $requisicao_id);
            $stmt->bindParam(':descricao', $item['descricao']);
            $stmt->bindParam(':quantidade', $item['quantidade']);
            $stmt->bindParam(':preco_unitario', $item['preco_unitario']);
            $stmt->bindParam(':unidade_medida', $item['unidade_medida']);
            $stmt->execute();
        }
    }
    
    private function removerItensRequisicao($requisicao_id) {
        $query = "DELETE FROM requisicao_itens WHERE requisicao_id = :requisicao_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':requisicao_id', $requisicao_id);
        $stmt->execute();
    }
    
    private function gerarCodigoRequisicao() {
        // Buscar o último código
        $query = "SELECT codigo FROM requisicoes ORDER BY id DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);
            $numero = intval(substr($ultimo['codigo'], 3)) + 1;
            return 'RC-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
        }
        
        return 'RC-0001';
    }
    
    private function registrarHistorico($requisicao_id, $usuario_id, $acao, $descricao) {
        $query = "INSERT INTO requisicao_historico 
                  (requisicao_id, usuario_id, acao, descricao) 
                  VALUES 
                  (:requisicao_id, :usuario_id, :acao, :descricao)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':requisicao_id', $requisicao_id);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':acao', $acao);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->execute();
    }
}
?>