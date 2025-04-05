<?php

class UserController
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function handleListUsers(): void
    {
        $adminData = AuthMiddleware::isAdmin();

        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
            'options' => ['default' => 1, 'min_range' => 1],
        ]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, [
            'options' => ['default' => 10, 'min_range' => 1, 'max_range' => 100],
        ]);
        $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

        $result = $this->userModel->getUsersPaginated($page, $limit, $search, false);

        $filters = [
            'search' => $search
        ];

        Utils::respond(
            Utils::buildPaginatedResponse(
                true,
                "Lấy danh sách người dùng thành công.",
                $result['users'] ?? [],
                $page,
                $limit,
                $result['total'] ?? 0,
                $filters
            ),
            200
        );
    }
}
