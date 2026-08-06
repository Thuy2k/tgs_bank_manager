<?php

/**
 * AJAX cho danh mục ngân hàng: lưu, xoá, import Excel.
 *
 * @package tgs_bank_manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class TGS_Bank_Ajax
{
    const NONCE = 'tgs_bank_nonce';

    public static function init()
    {
        add_action('wp_ajax_tgs_bank_save', [__CLASS__, 'save']);
        add_action('wp_ajax_tgs_bank_delete', [__CLASS__, 'delete']);
        add_action('wp_ajax_tgs_bank_import', [__CLASS__, 'import']);

        add_action('wp_ajax_tgs_pm_save', [__CLASS__, 'pm_save']);
        add_action('wp_ajax_tgs_pm_delete', [__CLASS__, 'pm_delete']);
    }

    /**
     * Ghi dữ liệu thì phải có quyền, khác với việc chỉ xem danh sách.
     */
    private static function guard()
    {
        if (!current_user_can(TGS_BANK_CAPABILITY)) {
            wp_send_json_error(['message' => 'Bạn không có quyền thao tác.'], 403);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, self::NONCE)) {
            wp_send_json_error(['message' => 'Phiên làm việc đã hết hạn. Tải lại trang rồi thử lại.'], 403);
        }
    }

    public static function save()
    {
        self::guard();

        $bank_id = isset($_POST['bank_id']) ? (int) $_POST['bank_id'] : 0;
        $data    = [];

        foreach (array_keys(TGS_Bank_Repo::editable_fields()) as $f) {
            if (isset($_POST[$f])) {
                $data[$f] = wp_unslash($_POST[$f]);   // sanitize nằm trong repo
            }
        }

        $res = TGS_Bank_Repo::save($data, $bank_id);
        if (!$res['ok']) {
            wp_send_json_error(['message' => $res['message']]);
        }

        wp_send_json_success($res);
    }

    public static function delete()
    {
        self::guard();

        $bank_id = isset($_POST['bank_id']) ? (int) $_POST['bank_id'] : 0;
        if (!TGS_Bank_Repo::delete($bank_id)) {
            wp_send_json_error(['message' => 'Xoá thất bại.']);
        }

        wp_send_json_success(['message' => 'Đã xoá.']);
    }

    // ── Hình thức thanh toán ────────────────────────────────────────────────

    public static function pm_save()
    {
        self::guard();

        $pm_id = isset($_POST['pm_id']) ? (int) $_POST['pm_id'] : 0;
        $data  = [];

        foreach (array_keys(TGS_Payment_Method_Repo::text_fields()) as $f) {
            if (isset($_POST[$f])) {
                $data[$f] = wp_unslash($_POST[$f]);
            }
        }

        /*
         * Ô tích không được gửi lên khi bỏ tích, nên phải gán 0 rõ ràng cho MỌI
         * cột bool — chỉ đọc những cái có mặt thì bỏ tích sẽ không bao giờ lưu được.
         */
        foreach (array_keys(TGS_Payment_Method_Repo::bool_fields()) as $f) {
            $data[$f] = !empty($_POST[$f]) ? 1 : 0;
        }

        $res = TGS_Payment_Method_Repo::save($data, $pm_id);
        if (!$res['ok']) {
            wp_send_json_error(['message' => $res['message']]);
        }

        wp_send_json_success($res);
    }

    public static function pm_delete()
    {
        self::guard();

        $pm_id = isset($_POST['pm_id']) ? (int) $_POST['pm_id'] : 0;
        if (!TGS_Payment_Method_Repo::delete($pm_id)) {
            wp_send_json_error(['message' => 'Xoá thất bại.']);
        }

        wp_send_json_success(['message' => 'Đã xoá.']);
    }

    /**
     * Nhập một LÔ dòng từ Excel.
     *
     * Trình duyệt đọc file rồi gửi lên từng lô — file vài nghìn dòng gửi một
     * phát sẽ đụng giới hạn max_input_vars / thời gian chạy, mà người dùng lại
     * không thấy tiến độ gì.
     */
    public static function import()
    {
        self::guard();

        $raw  = isset($_POST['rows']) ? wp_unslash($_POST['rows']) : '';
        $rows = is_string($raw) ? json_decode($raw, true) : (array) $raw;

        if (!is_array($rows) || empty($rows)) {
            wp_send_json_error(['message' => 'Lô dữ liệu rỗng.']);
        }

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rows as $r) {
            if (!is_array($r)) {
                $result['skipped']++;
                continue;
            }

            $outcome = TGS_Bank_Repo::import_row($r);
            $result[$outcome]++;

            /*
             * Ghi lại dòng bị bỏ qua kèm số dòng trong file, để người dùng mở
             * Excel sửa đúng chỗ thay vì phải tự dò.
             */
            if ($outcome === 'skipped' && !empty($r['__row'])) {
                $result['errors'][] = [
                    'row'  => (int) $r['__row'],
                    'code' => (string) ($r['bank_code'] ?? ''),
                ];
            }
        }

        wp_send_json_success($result);
    }
}
