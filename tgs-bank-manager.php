<?php

/**
 * Plugin Name: TGS Bank Manager — Danh mục ngân hàng
 * Description: Quản lý danh mục ngân hàng / tài khoản ngân hàng dùng chung toàn multisite. Thêm, sửa, xoá và nhập từ Excel.
 * Version: 1.0.0
 * Author: TGS
 *
 * ── Ghi chú thiết kế ────────────────────────────────────────────────────────
 *
 * Plugin này KHÔNG tạo bảng. Bảng wp_global_bank do tgs_shop_management tạo
 * (database/class-tgs-database.php) — nơi tập trung toàn bộ schema. Ở đây chỉ
 * có giao diện và nghiệp vụ, kích hoạt/gỡ plugin này không đụng gì tới dữ liệu.
 *
 * Bảng chưa có thì màn hình báo rõ "kích hoạt lại plugin chính", chứ không ném
 * lỗi SQL vào mặt người dùng.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TGS_BANK_VERSION', '1.0.0');
define('TGS_BANK_DIR', plugin_dir_path(__FILE__));
define('TGS_BANK_URL', plugin_dir_url(__FILE__));

/*
 * Quyền tối thiểu để THÊM/SỬA/XOÁ. Danh mục ngân hàng là dữ liệu kế toán, sai
 * số tài khoản là chuyển tiền nhầm chỗ — nên mặc định siết ở manage_options.
 * Việc XEM thì đi theo phân quyền menu của tgs_permission như các màn khác.
 */
define('TGS_BANK_CAPABILITY', 'manage_options');

require_once TGS_BANK_DIR . 'includes/class-tgs-bank-repo.php';
require_once TGS_BANK_DIR . 'includes/class-tgs-payment-method-repo.php';
require_once TGS_BANK_DIR . 'includes/class-tgs-bank-ajax.php';

class TGS_Bank_Plugin
{
    const VIEW_LIST   = 'bank-list';
    const VIEW_IMPORT = 'bank-import';
    const VIEW_PM     = 'payment-method-list';

    private static function views()
    {
        return [
            self::VIEW_LIST   => ['Danh mục ngân hàng', 'bank-list.php'],
            self::VIEW_IMPORT => ['Nhập ngân hàng từ Excel', 'bank-import.php'],
            self::VIEW_PM     => ['Danh mục hình thức thanh toán', 'payment-method-list.php'],
        ];
    }

    public static function init()
    {
        // 21/10/2026: BỎ menu "Cấu hình → Tài chính" + các trang Ngân hàng / Hình thức thanh toán / Nhập ngân hàng từ Excel
        // (danh mục lấy theo "Đồng bộ từ phần mềm kế toán"). Giữ lớp dữ liệu + AJAX vì plugin khác còn dùng (phiếu chi…).
        TGS_Bank_Ajax::init();
    }

    public static function register_routes($routes)
    {
        foreach (self::views() as $view => $meta) {
            $routes[$view] = [$meta[0], TGS_BANK_DIR . 'admin-views/' . $meta[1]];
        }

        return $routes;
    }

    /**
     * Chèn vào menu "Cấu hình" — đúng chỗ phần mềm cũ đặt mục "Ngân Hàng".
     *
     * Thêm hẳn một section mới thay vì nhét vào section có sẵn, để sau này các
     * mục còn lại của khối đó (Tiền tệ, Hình thức thanh toán…) có chỗ về chung.
     */
    public static function add_nav_items($workflow_nav, $current_view = '')
    {
        if (!isset($workflow_nav['config']['sections'])) {
            return $workflow_nav;   // menu Cấu hình chưa có thì thôi, không tự dựng
        }

        $workflow_nav['config']['sections'][] = [
            'heading' => 'Tài chính',
            'icon'    => 'bx bx-bank',
            'items'   => [
                ['view' => self::VIEW_LIST, 'label' => 'Ngân hàng', 'icon' => 'bx bx-bank'],
                ['view' => self::VIEW_PM, 'label' => 'Hình thức thanh toán', 'icon' => 'bx bx-credit-card'],
                ['view' => self::VIEW_IMPORT, 'label' => 'Nhập ngân hàng từ Excel', 'icon' => 'bx bx-file-import'],
            ],
        ];

        return $workflow_nav;
    }

    public static function enqueue_assets($hook)
    {
        if ($hook !== 'toplevel_page_tgs-shop-management') {
            return;
        }

        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : '';
        if (!array_key_exists($view, self::views())) {
            return;
        }

        wp_enqueue_style(
            'tgs-bank',
            TGS_BANK_URL . 'assets/css/bank.css',
            [],
            TGS_BANK_VERSION . '.' . @filemtime(TGS_BANK_DIR . 'assets/css/bank.css')
        );

        /*
         * SheetJS chỉ cần ở màn import. Nạp ở màn danh sách là tải thừa ~900KB
         * cho việc không dùng đến.
         *
         * Dùng lại đúng bản đã có trong plugin chính để không có hai phiên bản
         * thư viện chạy song song trong cùng hệ thống.
         */
        if ($view === self::VIEW_IMPORT) {
            wp_enqueue_script(
                'tgs-bank-xlsx',
                'https://cdn.jsdelivr.net/npm/xlsx@0.20.3/dist/xlsx.full.min.js',
                [],
                '0.20.3',
                true
            );
        }
    }
}

TGS_Bank_Plugin::init();
