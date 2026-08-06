<?php

/**
 * Truy xuất danh mục hình thức thanh toán — chỗ duy nhất chạm wp_global_payment_method.
 *
 * @package tgs_bank_manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class TGS_Payment_Method_Repo
{
    public static function table()
    {
        global $wpdb;
        return $wpdb->base_prefix . 'global_payment_method';
    }

    public static function table_exists()
    {
        global $wpdb;
        $t = self::table();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t;
    }

    /** Cột chữ / số người dùng nhập được */
    public static function text_fields()
    {
        return [
            'pm_name'       => 'Tên',
            'note'          => 'Ghi chú',
            'discount_rate' => 'Tỉ lệ chiết khấu',
            'payment_unit'  => 'Đơn vị TT',
            'bank_label'    => 'Ngân hàng',
        ];
    }

    /** Cột bật/tắt — tách riêng vì phải ép về 0/1, không nhận chuỗi rỗng */
    public static function bool_fields()
    {
        return [
            'via_bank'      => 'HTTT qua ngân hàng',
            'via_qrcode'    => 'Thanh toán qua QRCode',
            'is_default'    => 'Mặc định',
            'show_shortcut' => 'Hiển thị ShortCut',
        ];
    }

    public static function all($search = '')
    {
        global $wpdb;
        if (!self::table_exists()) {
            return [];
        }

        $t   = self::table();
        $sql = "SELECT * FROM {$t} WHERE is_deleted = 0";

        $search = trim((string) $search);
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= $wpdb->prepare(
                ' AND (pm_name LIKE %s OR note LIKE %s OR bank_label LIKE %s)',
                $like,
                $like,
                $like
            );
        }

        // sort_order trước để giữ thứ tự đã sắp, cùng hạng thì theo tên
        $sql .= ' ORDER BY sort_order ASC, pm_name ASC';

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function find_by_name($name)
    {
        global $wpdb;
        $t = self::table();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$t} WHERE pm_name = %s", trim((string) $name)),
            ARRAY_A
        );
    }

    /**
     * @return array{ok:bool, message:string, pm_id:int}
     */
    public static function save($data, $pm_id = 0)
    {
        global $wpdb;

        if (!self::table_exists()) {
            return ['ok' => false, 'message' => 'Chưa có bảng dữ liệu. Kích hoạt lại plugin TGS Shop Management để tạo bảng.', 'pm_id' => 0];
        }

        $pm_id = (int) $pm_id;
        $row   = [];

        foreach (array_keys(self::text_fields()) as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            if ($f === 'note') {
                $row[$f] = sanitize_textarea_field((string) $data[$f]);
            } elseif ($f === 'discount_rate') {
                $row[$f] = (float) $data[$f];
            } else {
                $row[$f] = sanitize_text_field((string) $data[$f]);
            }
        }

        foreach (array_keys(self::bool_fields()) as $f) {
            if (array_key_exists($f, $data)) {
                $row[$f] = !empty($data[$f]) ? 1 : 0;
            }
        }

        if (isset($row['pm_name'])) {
            $row['pm_name'] = trim($row['pm_name']);
        }

        if ($pm_id === 0 && ($row['pm_name'] ?? '') === '') {
            return ['ok' => false, 'message' => 'Tên hình thức thanh toán không được để trống.', 'pm_id' => 0];
        }

        // Chặn trùng tên ở đây để người dùng nhận câu tiếng Việt, không phải lỗi SQL
        if (isset($row['pm_name'])) {
            $dup = self::find_by_name($row['pm_name']);
            if ($dup && (int) $dup['pm_id'] !== $pm_id) {
                return ['ok' => false, 'message' => 'Đã có hình thức thanh toán tên "' . $row['pm_name'] . '".', 'pm_id' => 0];
            }
        }

        $now = current_time('mysql');
        $uid = (int) get_current_user_id() ?: null;

        if ($pm_id > 0) {
            $row['updated_at'] = $now;
            $row['updated_by'] = $uid;

            if ($wpdb->update(self::table(), $row, ['pm_id' => $pm_id]) === false) {
                return ['ok' => false, 'message' => 'Cập nhật thất bại.', 'pm_id' => 0];
            }
            self::enforce_single_default($row, $pm_id);
            return ['ok' => true, 'message' => 'Đã cập nhật.', 'pm_id' => $pm_id];
        }

        $row['pm_status']  = 1;
        $row['is_deleted'] = 0;
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        $row['created_by'] = $uid;
        $row['sort_order'] = (int) $wpdb->get_var('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM ' . self::table());

        if ($wpdb->insert(self::table(), $row) === false) {
            return ['ok' => false, 'message' => 'Thêm mới thất bại.', 'pm_id' => 0];
        }

        $new_id = (int) $wpdb->insert_id;
        self::enforce_single_default($row, $new_id);

        return ['ok' => true, 'message' => 'Đã thêm.', 'pm_id' => $new_id];
    }

    /**
     * "Mặc định" chỉ được có MỘT.
     *
     * Bật cho dòng này thì tự tắt các dòng khác. Để hai dòng cùng mặc định thì
     * màn bán hàng chọn cái nào là tuỳ thứ tự truy vấn — sai âm thầm, rất khó
     * lần ra khi có người thắc mắc "sao nó cứ nhảy vào công nợ".
     */
    private static function enforce_single_default($row, $pm_id)
    {
        global $wpdb;

        if (!isset($row['is_default']) || (int) $row['is_default'] !== 1) {
            return;
        }

        $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::table() . ' SET is_default = 0 WHERE pm_id <> %d',
            (int) $pm_id
        ));
    }

    /** Xoá mềm — hình thức thanh toán đã dùng ở phiếu cũ, xoá cứng là mất dấu vết */
    public static function delete($pm_id)
    {
        global $wpdb;

        $pm_id = (int) $pm_id;
        if ($pm_id <= 0) {
            return false;
        }

        return false !== $wpdb->update(
            self::table(),
            [
                'is_deleted' => 1,
                'deleted_at' => current_time('mysql'),
                'updated_by' => (int) get_current_user_id() ?: null,
            ],
            ['pm_id' => $pm_id]
        );
    }
}
