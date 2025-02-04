<?php
$pdo = new PDO("mysql:host=localhost;dbname=fila_process;charset=utf8","root","",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$action = $_GET["action"] ?? "dashboard";

if ($action === "enfileirar" && $_SERVER["REQUEST_METHOD"]==="POST") {
    $stmt = $pdo->prepare("INSERT INTO jobs (tipo, payload, prioridade) VALUES (?, ?, ?)");
    $stmt->execute([$_POST["tipo"], json_encode(["dado"=>$_POST["payload"]]), (int)$_POST["prioridade"]]);
    header("Location: index.php"); exit;
}
if ($action === "retry" && isset($_GET["id"])) {
    $pdo->prepare("UPDATE jobs SET status='pendente', tentativas=0 WHERE id=?")->execute([$_GET["id"]]);
    header("Location: index.php"); exit;
}
if ($action === "limpar") {
    $pdo->query("DELETE FROM jobs WHERE status='concluido'");
    header("Location: index.php"); exit;
}

$jobs = $pdo->query("SELECT * FROM jobs ORDER BY 
    CASE WHEN status='pendente' THEN 0 WHEN status='processando' THEN 1 WHEN status='erro' THEN 2 ELSE 3 END,
    prioridade DESC, criado_em DESC")->fetchAll();

$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN status='processando' THEN 1 ELSE 0 END) as processando,
    SUM(CASE WHEN status='concluido' THEN 1 ELSE 0 END) as concluidos,
    SUM(CASE WHEN status='erro' THEN 1 ELSE 0 END) as erros
    FROM jobs")->fetch();
?>
<!DOCTYPE html><html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fila de Processamento</title>
<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Arial;background:#f8f9fa;color:#333}
header{background:#1a1a2e;color:white;padding:15px 30px;display:flex;justify-content:space-between;align-items:center}
header h1{font-size:1.3em}.container{max-width:1000px;margin:20px auto;padding:0 20px}
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:20px}
.stat{background:white;padding:15px;border-radius:8px;text-align:center;box-shadow:0 1px 2px rgba(0,0,0,0.05)}
.stat h3{font-size:1.8em}.stat p{font-size:0.85em;color:#999}
.topo{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
.btn{display:inline-block;padding:8px 15px;border-radius:4px;text-decoration:none;font-size:0.9em;color:white;margin-right:5px}
.btn-primary{background:#4361ee}.btn-success{background:#2ec4b6}.btn-danger{background:#e71d36}
.btn-warning{background:#ff9f1c}.btn-sm{padding:4px 10px;font-size:0.8em}
table{width:100%;border-collapse:collapse;background:white;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
th,td{padding:10px;text-align:left;border-bottom:1px solid #eee;font-size:0.95em}
th{background:#f8f9fa;font-weight:600}.badge{padding:3px 8px;border-radius:10px;font-size:0.8em;color:white}
.badge-pendente{background:#4361ee}.badge-processando{background:#ff9f1c}
.badge-concluido{background:#2ec4b6}.badge-erro{background:#e71d36}
.prioridade{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:5px}
.p-alta{background:#e71d36}.p-media{background:#ff9f1c}.p-baixa{background:#2ec4b6}
form{background:white;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.08);display:flex;gap:10px;align-items:end;flex-wrap:wrap}
form input,form select{padding:8px;border:1px solid #ddd;border-radius:4px}
form input[type=text]{flex:2;min-width:200px}form select{flex:1;min-width:120px}
form button{background:#4361ee;color:white;border:none;padding:8px 20px;border-radius:4px;cursor:pointer}
.payload{font-size:0.85em;color:#666;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tentativas{font-size:0.85em;color:#999}
footer{text-align:center;padding:20px;color:#999;font-size:0.9em}
</style></head>
<body>
<header><h1>Fila de Processamento</h1></header>
<div class="container">
<div class="stats"><div class="stat"><h3><?=$stats["total"]?></h3><p>Total</p></div>
<div class="stat"><h3><?=$stats["pendentes"]?></h3><p>Pendentes</p></div>
<div class="stat"><h3><?=$stats["processando"]?></h3><p>Processando</p></div>
<div class="stat"><h3><?=$stats["concluidos"]?></h3><p>Concluidos</p></div>
<div class="stat"><h3><?=$stats["erros"]?></h3><p>Erros</p></div></div>

<h2>Enfileirar Job</h2>
<form method="POST" action="?action=enfileirar">
<input type="text" name="payload" placeholder="Dados do job" required>
<select name="tipo" required><option value="email">Email</option><option value="relatorio">Relatorio</option><option value="notificacao">Notificacao</option><option value="processamento">Processamento</option></select>
<select name="prioridade" required><option value="3">Alta</option><option value="2" selected>Media</option><option value="1">Baixa</option></select>
<button type="submit">Enfileirar</button></form>

<div class="topo"><h2>Jobs</h2>
<div><a href="?action=limpar" class="btn btn-danger btn-sm">Limpar concluidos</a>
<a href="worker.php" class="btn btn-primary btn-sm" target="_blank">Worker</a></div></div>

<table><tr><th>#</th><th>Tipo</th><th>Prioridade</th><th>Payload</th><th>Status</th><th>Tentativas</th><th>Criado</th><th>Acao</th></tr>
<?php foreach($jobs as $j):?><tr>
<td><?=$j["id"]?></td><td><?=$j["tipo"]?></td>
<td><span class="prioridade p-<?=$j["prioridade"]>=3?"alta":($j["prioridade"]>=2?"media":"baixa")?>"></span><?=$j["prioridade"]>=3?"Alta":($j["prioridade"]>=2?"Media":"Baixa")?></td>
<td class="payload"><?=htmlspecialchars($j["payload"])?></td>
<td><span class="badge badge-<?=$j["status"]?>"><?=$j["status"]?></span></td>
<td class="tentativas"><?=$j["tentativas"]?></td>
<td><?=date("d/m/Y H:i",strtotime($j["criado_em"]))?></td>
<td><?php if($j["status"]==="erro"):?><a href="?action=retry&id=<?=$j["id"]?>" class="btn btn-warning btn-sm">Retry</a><?php endif;?></td>
</tr><?php endforeach;?></table>
<footer>Fila de Processamento v1.0 - Worker: php workers/worker.php</footer></div></body></html>
