<?php
declare(strict_types=1);

// use sempre init.php (PDO) + Auth.php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/Auth.php';

// sessão
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// garante estrutura + abre conexão PDO
bootstrap();
$pdo  = db();
$auth = new Auth($pdo);

// de vez em quando, limpa sessões expiradas
if (rand(1, 100) === 1) {
    $auth->limparSessoesExpiradas();
}

// se não estiver autenticado -> vai para login
if (!$auth->verificarAutenticacao()) {
    header('Location: /login.php');
    exit;
}

// deixa disponível para as páginas protegidas
$usuarioLogado = $auth->getUsuarioLogado();

/**
 * Exige perfil de secretário nas páginas/ações que precisarem.
 * Ex.: chame requireSecretario() logo após incluir este arquivo.
 */
function requireSecretario(): void {
    global $auth;
    if (!$auth->isSecretario()) {
        http_response_code(403);
        exit('Acesso negado. Apenas secretários podem acessar esta página.');
    }
}
