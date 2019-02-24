<?php

namespace App\Domain\User;

use Odan\Slim\Session\Session;
use RuntimeException;

/**
 * Authentication and authorisation.
 */
class Auth
{
    /**
     * Session.
     *
     * @var Session
     */
    private $session;

    /**
     * @var AuthRepository
     */
    private $authRepository;

    /**
     * Constructor.
     *
     * @param Session $session Storage
     * @param AuthRepository $authRepository The repository
     */
    public function __construct(Session $session, AuthRepository $authRepository)
    {
        $this->session = $session;
        $this->authRepository = $authRepository;
    }

    /**
     * Returns true if and only if an identity is available from storage.
     *
     * @return bool status
     */
    public function hasIdentity(): bool
    {
        return !empty($this->session->get('user'));
    }

    /**
     * Clears the identity from persistent storage.
     *
     * @return void
     */
    public function clearIdentity(): void
    {
        $this->session->remove('user');

        // Clears all session data and regenerates session ID
        if ($this->session->isStarted()) {
            //$this->session->regenerateId();
            $this->session->destroy();
        }
    }

    /**
     * Get user Id.
     *
     * @return int User Id
     */
    public function getUserId(): int
    {
        $result = $this->getUser()->getId();

        if (empty($result)) {
            throw new RuntimeException(__('Invalid or empty User-ID'));
        }

        return $result;
    }

    /**
     * Returns the identity from storage or null if no identity is available.
     *
     * @return User The logged-in user
     */
    public function getUser(): User
    {
        $user = $this->session->get('user');
        if (!$user) {
            throw new RuntimeException('No identity available');
        }

        return $user;
    }

    /**
     * Performs an authentication attempt.
     *
     * @param string $username username
     * @param string $password password
     *
     * @return User|null the user or null
     */
    public function authenticate(string $username, string $password): ?User
    {
        $userRow = $this->authRepository->findUserByUsername($username);

        if (!$userRow) {
            return null;
        }

        $user = User::fromArray($userRow);

        if (!$this->verifyPassword($password, $user->getPassword() ?: '')) {
            return null;
        }

        $this->startUserSession($user);

        return $user;
    }

    /**
     * Returns true if password and hash is valid.
     *
     * @param string $password password
     * @param string $hash stored hash
     *
     * @return bool Success
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Init user session.
     *
     * @param User $user the user
     *
     * @return void
     */
    protected function startUserSession(User $user): void
    {
        // Clear session data
        $this->session->destroy();
        $this->session->start();

        // Create new session id
        $this->session->regenerateId();

        // Store user settings in session
        $this->setIdentity($user);
    }

    /**
     * Set the identity into storage or null if no identity is available.
     *
     * @param User $user the user
     *
     * @return void
     */
    public function setIdentity(User $user): void
    {
        $this->session->set('user', $user);
    }

    /**
     * Returns secure password hash.
     *
     * @param string $password password
     *
     * @return string
     */
    public function createPassword(string $password): string
    {
        return password_hash($password, 1) ?: '';
    }

    /**
     * Check user permission.
     *
     * @param string|array $role (e.g. 'ROLE_ADMIN' or 'ROLE_USER')
     * or array('ROLE_ADMIN', 'ROLE_USER')
     *
     * @return bool Status
     */
    public function hasRole($role): bool
    {
        // Current user role
        $userRole = $this->getUser()->getRole();

        // Full access for admin
        if ($userRole === UserRole::ROLE_ADMIN) {
            return true;
        }
        if ($role === $userRole) {
            return true;
        }
        if (is_array($role) && in_array($userRole, $role, true)) {
            return true;
        }

        return false;
    }
}
