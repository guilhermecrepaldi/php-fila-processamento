CREATE DATABASE fila_process;
USE fila_process;
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(100) NOT NULL,
    payload JSON,
    prioridade INT DEFAULT 2,
    status VARCHAR(20) DEFAULT 'pendente',
    tentativas INT DEFAULT 0,
    resultado TEXT,
    processado_em DATETIME NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_prioridade (prioridade, criado_em)
);
