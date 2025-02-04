<?php
// Worker de processamento
echo "[WORKER] Iniciado em ".date("d/m/Y H:i:s")."\n";
$pdo = new PDO("mysql:host=localhost;dbname=fila_process;charset=utf8","root","",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

while (true) {
    $pdo->beginTransaction();
    $stmt = $pdo->query("SELECT * FROM jobs WHERE status='pendente' ORDER BY prioridade DESC, criado_em ASC LIMIT 1 FOR UPDATE");
    $job = $stmt->fetch();
    
    if ($job) {
        $pdo->prepare("UPDATE jobs SET status='processando', processado_em=NOW() WHERE id=?")->execute([$job["id"]]);
        $pdo->commit();
        
        echo "[JOB {$job["id"]}] Processando: {$job["tipo"]}... ";
        try {
            // Simula processamento
            sleep(1);
            $payload = json_decode($job["payload"], true);
            $resultado = "Processado com sucesso: " . ($payload["dado"] ?? $job["tipo"]);
            
            $pdo->prepare("UPDATE jobs SET status='concluido', resultado=?, processado_em=NOW() WHERE id=?")
                ->execute([$resultado, $job["id"]]);
            echo "OK\n";
        } catch (Exception $e) {
            $tentativas = $job["tentativas"] + 1;
            if ($tentativas >= 3) {
                $pdo->prepare("UPDATE jobs SET status='erro', tentativas=?, resultado=? WHERE id=?")
                    ->execute([$tentativas, "Falhou apos $tentativas tentativas", $job["id"]]);
            } else {
                $pdo->prepare("UPDATE jobs SET status='pendente', tentativas=? WHERE id=?")
                    ->execute([$tentativas, $job["id"]]);
            }
            echo "ERRO: {$e->getMessage()}\n";
        }
    } else {
        $pdo->commit();
        echo "[WORKER] Aguardando jobs...\n";
        sleep(5);
    }
}
