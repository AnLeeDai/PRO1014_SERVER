<?php

class BannerController
{
    private BannerModel $bannerModel;

    public function __construct()
    {
        $this->bannerModel = new BannerModel();
    }

    public function handleDeleteBanner(): void
    {
        AuthMiddleware::isAdmin();

        $inputData = json_decode(file_get_contents("php://input"), true);
        $bannerId = isset($inputData['banner_id']) ? filter_var($inputData['banner_id'], FILTER_VALIDATE_INT) : null;

        if (!$bannerId || $bannerId <= 0) {
            Utils::respond(['success' => false, 'message' => 'ID banner không hợp lệ.'], 400);
        }

        $banner = $this->bannerModel->getBannerById($bannerId);
        if (!$banner) {
            Utils::respond(['success' => false, 'message' => 'Banner không tồn tại.'], 404);
        }

        $deleted = $this->bannerModel->deleteBanner($bannerId);
        if ($deleted) {
            Utils::respond(['success' => true, 'message' => 'Xóa banner thành công.'], 200);
        } else {
            Utils::respond(['success' => false, 'message' => 'Không thể xóa banner.'], 500);
        }
    }

    public function handleListBannersPaginated(): void
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 100]]);
        $sortBy = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'created_at';
        $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

        $result = $this->bannerModel->getBannersPaginated($page, $limit, $sortBy, $search);

        $filters = ['sort_by' => $sortBy, 'search' => $search];

        Utils::respond(Utils::buildPaginatedResponse(
            true,
            "Lấy danh sách banner thành công.",
            $result['banners'] ?? [],
            $page,
            $limit,
            $result['total'] ?? 0,
            $filters
        ), 200);
    }

    public function handleUpdateBanner(): void
    {
        AuthMiddleware::isAdmin();

        // Lấy banner_id
        $bannerId = filter_input(INPUT_POST, 'banner_id', FILTER_VALIDATE_INT);
        if (!$bannerId || $bannerId <= 0) {
            Utils::respond(['success' => false, 'message' => 'ID banner không hợp lệ.'], 400);
        }

        // Kiểm tra banner tồn tại
        $banner = $this->bannerModel->getBannerById($bannerId);
        if (!$banner) {
            Utils::respond(['success' => false, 'message' => 'Không tìm thấy banner.'], 404);
        }

        // Lấy tiêu đề banner
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS);
        if (empty($title)) {
            Utils::respond(['success' => false, 'message' => 'Thiếu tiêu đề banner.'], 400);
        }

        // Xử lý file upload nếu có (key là 'image')
        $imageUrl = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $uploadResult = Utils::uploadImage($_FILES['image'], 'banner', $title);
            if (!$uploadResult['success']) {
                Utils::respond(['success' => false, 'message' => 'Lỗi upload ảnh: ' . $uploadResult['message']], 400);
            }
            $imageUrl = $uploadResult['url'];
        }

        // Cập nhật banner: nếu không có ảnh mới thì chỉ cập nhật tiêu đề
        $updated = $this->bannerModel->updateBanner($bannerId, $title, $imageUrl);
        if ($updated) {
            $updatedBanner = $this->bannerModel->getBannerById($bannerId);
            Utils::respond([
                'success' => true,
                'message' => 'Cập nhật banner thành công.',
                'banner' => $updatedBanner
            ]);
        } else {
            Utils::respond(['success' => false, 'message' => 'Không thể cập nhật banner.'], 500);
        }
    }

    public function handleCreateBanner(): void
    {
        AuthMiddleware::isAdmin();

        // Lấy dữ liệu title
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS);
        if (empty($title)) {
            Utils::respond(['success' => false, 'message' => 'Thiếu tiêu đề banner.'], 400);
        }

        // Kiểm tra file upload cho ảnh banner
        if (!isset($_FILES['image'])) {
            Utils::respond(['success' => false, 'message' => 'Vui lòng gửi ảnh banner.'], 400);
        }


        $uploadResult = Utils::uploadImage($_FILES['image'], 'banner', $title);
        if (!$uploadResult['success']) {
            Utils::respond(['success' => false, 'message' => 'Lỗi upload ảnh: ' . $uploadResult['message']], 400);
        }

        $imageUrl = $uploadResult['url'];

        $newBannerId = $this->bannerModel->createBanner($title, $imageUrl);
        if ($newBannerId === false) {
            Utils::respond(['success' => false, 'message' => 'Tạo banner thất bại'], 500);
        }

        Utils::respond([
            'success' => true,
            'message' => 'Tạo banner thành công',
            'data' => [
                'id' => $newBannerId,
                'title' => $title,
                'image_url' => $imageUrl,
            ]
        ], 201);
    }
}
