CREATE DATABASE sistema_rc;
USE sistema_rc;

-- Tabela de usuários
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    setor VARCHAR(100) NOT NULL,
    nivel_liberacao ENUM('SOLICITANTE', 'APROVADOR', 'COMPRAS', 'ADMIN') NOT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de requisições
CREATE TABLE requisicoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    centro_custo VARCHAR(100),
    data_necessidade DATE,
    fornecedor_sugerido VARCHAR(255),
    status ENUM('Rascunho', 'Pendente', 'Em cotação', 'Aprovada', 'Rejeitada', 'Pedido Emitido', 'Em Entrega', 'Concluída', 'Cancelada') DEFAULT 'Rascunho',
    solicitante_id INT NOT NULL,
    aprovador_id INT,
    comprador_id INT,
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitante_id) REFERENCES usuarios(id),
    FOREIGN KEY (aprovador_id) REFERENCES usuarios(id),
    FOREIGN KEY (comprador_id) REFERENCES usuarios(id)
);

-- Tabela de itens da requisição
CREATE TABLE requisicao_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisicao_id INT NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL,
    preco_unitario DECIMAL(10,2) DEFAULT 0,
    unidade_medida VARCHAR(20) DEFAULT 'UN',
    FOREIGN KEY (requisicao_id) REFERENCES requisicoes(id) ON DELETE CASCADE
);

-- Tabela de anexos
CREATE TABLE requisicao_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisicao_id INT NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(500) NOT NULL,
    tipo_arquivo VARCHAR(50),
    tamanho INT,
    upload_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requisicao_id) REFERENCES requisicoes(id) ON DELETE CASCADE
);

-- Tabela de aprovações
CREATE TABLE requisicao_aprovacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisicao_id INT NOT NULL,
    aprovador_id INT NOT NULL,
    decisao ENUM('APROVADA', 'REJEITADA', 'PENDENTE') DEFAULT 'PENDENTE',
    comentario TEXT,
    data_decisao TIMESTAMP NULL,
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requisicao_id) REFERENCES requisicoes(id) ON DELETE CASCADE,
    FOREIGN KEY (aprovador_id) REFERENCES usuarios(id)
);

-- Tabela de histórico
CREATE TABLE requisicao_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisicao_id INT NOT NULL,
    usuario_id INT NOT NULL,
    acao VARCHAR(100) NOT NULL,
    descricao TEXT,
    metadata JSON,
    data_acao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requisicao_id) REFERENCES requisicoes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Inserir dados iniciais
INSERT INTO usuarios (nome, email, username, senha, setor, nivel_liberacao) VALUES
('Ana Souza', 'ana@suzano.com', 'ana.souza', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Planejamento', 'SOLICITANTE'),
('Carlos Lima', 'carlos@suzano.com', 'carlos.lima', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrativo', 'APROVADOR'),
('Beatriz Nunes', 'beatriz@suzano.com', 'beatriz.nunes', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Compras', 'COMPRAS'),
('Admin', 'admin@suzano.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'TI', 'ADMIN');

-- Nota: A senha para todos os usuários de exemplo é "password"