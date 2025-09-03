<?php
// src/Core/Auth.php
namespace Ironhaven\Core;

class Auth {
    private static $instance = null;
    private $db;
    private $currentUser = null;

    private function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->db = Database::getInstance();
        $this->checkSession();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

private function checkSession() {
    if (isset($_SESSION['user_id'])) {
        $user = $this->db->fetch(
            "SELECT * FROM players WHERE id = ?",
            [$_SESSION['user_id']]
        );
        if ($user) {
            // Utente valido trovato, imposta l'utente corrente
            $this->currentUser = $user;
        } else {
            // Nessun utente corrispondente: rimuovi l'ID dalla sessione
            unset($_SESSION['user_id']);
            $this->currentUser = null;
        }
    }
}

    public function login($username, $password) {
        $user = $this->db->fetch(
            "SELECT * FROM players WHERE username = ?",
            [$username]
        );

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $this->currentUser = $user;

            // Aggiorna ultimo accesso
            $this->db->update(
                'players',
                ['last_login' => date('Y-m-d H:i:s')],
                'id = ?',
                [$user['id']]
            );

            return true;
        }

        return false;
    }

    public function register($username, $email, $password) {
        // Verifica se utente esiste già
        $existingUser = $this->db->fetch(
            "SELECT * FROM players WHERE username = ? OR email = ?",
            [$username, $email]
        );

        if ($existingUser) {
            return false;
        }

        // Crea nuovo utente
        $userId = $this->db->insert('players', [
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'level' => 1,
            'experience' => 0,
            'fame' => 0
        ]);

        if ($userId) {
            $_SESSION['user_id'] = $userId;
            $this->currentUser = $this->db->fetch(
                "SELECT * FROM players WHERE id = ?",
                [$userId]
            );
            return true;
        }

        return false;
    }

    public function logout() {
        unset($_SESSION['user_id']);
        $this->currentUser = null;
        session_destroy();
    }

    public function isLoggedIn() {
        return $this->currentUser !== null;
    }

    public function getCurrentUser() {
        return $this->currentUser;
    }

    public function getUserId() {
        return $this->currentUser ? $this->currentUser['id'] : null;
    }
}
