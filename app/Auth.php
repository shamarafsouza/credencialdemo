<?php
declare(strict_types=1);

// app/Auth.php
final class Auth
{
    /** @var PDO */
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Realiza login do usuário
     * @return array{success:bool, message:string}
     */
    public function login(string $email, string $senha): array
    {
        try {
            // Busca usuário por e-mail
            $stmt = $this->db->prepare("
                SELECT id, nome, email, senha, tipo_usuario, ativo
                  FROM usuarios
                 WHERE email = :email
                 LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();

            if (!$usuario) {
                $this->registrarLog(null, 'login_falha', 'Email não encontrado');
                return ['success' => false, 'message' => 'Credenciais inválidas'];
            }

            if ((int)$usuario['ativo'] !== 1) {
                $this->registrarLog((int)$usuario['id'], 'login_falha', 'Usuário inativo');
                return ['success' => false, 'message' => 'Usuário inativo. Contate o administrador.'];
            }

            if (!password_verify($senha, (string)$usuario['senha'])) {
                $this->registrarLog((int)$usuario['id'], 'login_falha', 'Senha incorreta');
                return ['success' => false, 'message' => 'Credenciais inválidas'];
            }

            // Cria/renova sessão
            $_SESSION['usuario_id']   = (int)$usuario['id'];
            $_SESSION['usuario_nome'] = (string)$usuario['nome'];
            $_SESSION['usuario_email']= (string)$usuario['email'];
            $_SESSION['usuario_tipo'] = (string)$usuario['tipo_usuario'];
            $_SESSION['login_time']   = time();

            // Gera token de sessão e persiste
            $token = bin2hex(random_bytes(32));
            $_SESSION['token'] = $token;

            $this->salvarSessao((int)$usuario['id'], $token);

            $this->registrarLog((int)$usuario['id'], 'login_sucesso', 'Login realizado com sucesso');
            return ['success' => true, 'message' => 'Login realizado com sucesso'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao realizar login: ' . $e->getMessage()];
        }
    }

    /**
     * Realiza logout do usuário
     */
    public function logout(): void
    {
        if (!empty($_SESSION['usuario_id'])) {
            $this->registrarLog((int)$_SESSION['usuario_id'], 'logout', 'Logout realizado');

            if (!empty($_SESSION['token'])) {
                $stmt = $this->db->prepare("DELETE FROM sessoes WHERE token = :token");
                $stmt->execute([':token' => (string)$_SESSION['token']]);
            }
        }

        // Limpa cookie/sessão
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Verifica se a sessão atual é válida
     */
    public function verificarAutenticacao(): bool
    {
        if (empty($_SESSION['usuario_id']) || empty($_SESSION['token'])) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT id
              FROM sessoes
             WHERE token = :token
               AND usuario_id = :uid
               AND expira_em > datetime('now')
             LIMIT 1
        ");
        $stmt->execute([
            ':token' => (string)$_SESSION['token'],
            ':uid'   => (int)$_SESSION['usuario_id'],
        ]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Verifica se usuário logado é secretário
     */
    public function isSecretario(): bool
    {
        return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'secretario';
    }

    /**
     * Retorna dados básicos do usuário logado
     * @return array{id:int,nome:string,email:string,tipo:string}|null
     */
    public function getUsuarioLogado(): ?array
    {
        if (!$this->verificarAutenticacao()) {
            return null;
        }

        return [
            'id'    => (int)$_SESSION['usuario_id'],
            'nome'  => (string)$_SESSION['usuario_nome'],
            'email' => (string)$_SESSION['usuario_email'],
            'tipo'  => (string)$_SESSION['usuario_tipo'],
        ];
    }

    /**
     * Persiste uma sessão no banco (apaga anteriores do mesmo usuário)
     */
    private function salvarSessao(int $usuario_id, string $token): void
    {
        // Remove sessões antigas do mesmo usuário
        $stmt = $this->db->prepare("DELETE FROM sessoes WHERE usuario_id = :uid");
        $stmt->execute([':uid' => $usuario_id]);

        // Cria nova sessão (8 horas)
        $stmt = $this->db->prepare("
            INSERT INTO sessoes (usuario_id, token, ip_address, user_agent, expira_em, criado_em)
            VALUES (:uid, :token, :ip, :ua, datetime('now', '+8 hours'), datetime('now'))
        ");
        $stmt->execute([
            ':uid'   => $usuario_id,
            ':token' => $token,
            ':ip'    => $_SERVER['REMOTE_ADDR']     ?? '',
            ':ua'    => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    }

    /**
     * Registra log de acesso (sucesso=1 quando ação contém 'sucesso')
     */
    private function registrarLog(?int $usuario_id, string $acao, ?string $detalhes = null): void
    {
        // sucesso simples baseado no nome da ação
        $sucesso = (strpos($acao, 'sucesso') !== false) ? 1 : 0;

        $stmt = $this->db->prepare("
            INSERT INTO logs_acesso (usuario_id, acao, ip_address, sucesso, detalhes, criado_em)
            VALUES (:uid, :acao, :ip, :ok, :det, datetime('now'))
        ");
        $stmt->execute([
            ':uid' => $usuario_id,
            ':acao'=> $acao,
            ':ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
            ':ok'  => $sucesso,
            ':det' => $detalhes,
        ]);
    }

    /**
     * Remove sessões expiradas
     */
    public function limparSessoesExpiradas(): void
    {
        $stmt = $this->db->prepare("DELETE FROM sessoes WHERE expira_em <= datetime('now')");
        $stmt->execute();
    }
}

