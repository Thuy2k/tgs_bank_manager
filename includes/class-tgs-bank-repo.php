<?php

/**
 * Truy xuất dữ liệu ngân hàng — chỗ DUY NHẤT chạm vào bảng wp_global_bank.
 *
 * Mọi nơi khác (ajax, view) đều đi qua đây, để quy tắc "xoá mềm", chuẩn hoá mã
 * và danh sách cột chỉ nằm ở một chỗ.
 *
 * @package tgs_bank_manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class TGS_Bank_Repo
{
    /**
     * Bảng dùng base_prefix — một bảng duy nhất toàn multisite.
     *
     * Gọi qua hàm chứ không đặt hằng số: hằng số bị đóng băng lúc nạp plugin nên
     * KHÔNG đổi theo switch_to_blog(). base_prefix thì không đổi theo site, nhưng
     * giữ nếp gọi động để sau này copy sang bảng theo site không bị bẫy.
     */
    public static function table()
    {
        global $wpdb;
        return $wpdb->base_prefix . 'global_bank';
    }

    /** Bảng đã được tạo chưa — plugin chính mới là nơi tạo bảng */
    public static function table_exists()
    {
        global $wpdb;
        $t = self::table();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t;
    }

    /** Các cột người dùng nhập được — dùng chung cho cả form lẫn import Excel */
    public static function editable_fields()
    {
        return [
            'bank_code'       => 'Mã ngân hàng',
            'bank_name'       => 'Tên ngân hàng',
            'bank_address'    => 'Địa chỉ',
            'bank_phone'      => 'Điện thoại',
            'account_holder'  => 'Chủ TK',
            'account_number'  => 'Số TK',
            'contact_person'  => 'Người liên lạc',
            'note'            => 'Ghi chú',
            'accounting_code' => 'TK kế toán',
        ];
    }

    /**
     * Chuẩn hoá mã ngân hàng: bỏ khoảng trắng thừa, viết hoa.
     *
     * Mã là khoá nghiệp vụ, "nhnd06" và "NHND06" phải là một. Không chuẩn hoá
     * thì import lần hai sẽ đẻ thêm dòng trùng thay vì cập nhật dòng cũ.
     */
    public static function normalize_code($code)
    {
        return strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $code)));
    }

    /**
     * Danh sách ngân hàng còn hiệu lực.
     *
     * @param string $search Tìm theo mã / tên / số TK / chủ TK
     */
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
                ' AND (bank_code LIKE %s OR bank_name LIKE %s OR account_number LIKE %s OR account_holder LIKE %s)',
                $like,
                $like,
                $like,
                $like
            );
        }

        $sql .= ' ORDER BY bank_code ASC';

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function find($bank_id)
    {
        global $wpdb;
        $t = self::table();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$t} WHERE bank_id = %d", (int) $bank_id),
            ARRAY_A
        );
    }

    public static function find_by_code($code)
    {
        global $wpdb;
        $t = self::table();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$t} WHERE bank_code = %s", self::normalize_code($code)),
            ARRAY_A
        );
    }

    /**
     * Thêm mới hoặc cập nhật theo bank_id.
     *
     * @return array{ok:bool, message:string, bank_id:int}
     */
    public static function save($data, $bank_id = 0)
    {
        global $wpdb;

        if (!self::table_exists()) {
            return ['ok' => false, 'message' => 'Chưa có bảng dữ liệu. Kích hoạt lại plugin TGS Shop Management để tạo bảng.', 'bank_id' => 0];
        }

        $bank_id = (int) $bank_id;
        $row     = [];

        foreach (array_keys(self::editable_fields()) as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            $row[$f] = ($f === 'bank_address' || $f === 'note')
                ? sanitize_textarea_field((string) $data[$f])
                : sanitize_text_field((string) $data[$f]);
        }

        if (isset($row['bank_code'])) {
            $row['bank_code'] = self::normalize_code($row['bank_code']);
        }

        if ($bank_id === 0 && ($row['bank_code'] ?? '') === '') {
            return ['ok' => false, 'message' => 'Mã ngân hàng không được để trống.', 'bank_id' => 0];
        }
        if (($row['bank_name'] ?? '') === '' && $bank_id === 0) {
            return ['ok' => false, 'message' => 'Tên ngân hàng không được để trống.', 'bank_id' => 0];
        }

        /*
         * Mã trùng phải chặn ở đây chứ không phó mặc cho UNIQUE KEY: để DB ném
         * lỗi thì người dùng nhận về một câu lỗi SQL không đọc được.
         */
        if (isset($row['bank_code'])) {
            $dup = self::find_by_code($row['bank_code']);
            if ($dup && (int) $dup['bank_id'] !== $bank_id) {
                return [
                    'ok'      => false,
                    'message' => 'Mã "' . $row['bank_code'] . '" đã có ở ngân hàng: ' . $dup['bank_name'],
                    'bank_id' => 0,
                ];
            }
        }

        $now = current_time('mysql');
        $uid = (int) get_current_user_id() ?: null;

        if ($bank_id > 0) {
            $row['updated_at'] = $now;
            $row['updated_by'] = $uid;
            $ok = $wpdb->update(self::table(), $row, ['bank_id' => $bank_id]);

            if ($ok === false) {
                return ['ok' => false, 'message' => 'Cập nhật thất bại.', 'bank_id' => 0];
            }
            return ['ok' => true, 'message' => 'Đã cập nhật.', 'bank_id' => $bank_id];
        }

        $row['bank_status'] = 1;
        $row['is_deleted']  = 0;
        $row['created_at']  = $now;
        $row['updated_at']  = $now;
        $row['created_by']  = $uid;

        $ok = $wpdb->insert(self::table(), $row);
        if ($ok === false) {
            return ['ok' => false, 'message' => 'Thêm mới thất bại.', 'bank_id' => 0];
        }

        return ['ok' => true, 'message' => 'Đã thêm.', 'bank_id' => (int) $wpdb->insert_id];
    }

    /**
     * Xoá MỀM.
     *
     * Số tài khoản có thể đã bị tham chiếu ở phiếu thu/chi cũ; xoá cứng là mất
     * luôn dấu vết. Dòng đã xoá biến khỏi danh sách nhưng vẫn tra ngược được.
     */
    public static function delete($bank_id)
    {
        global $wpdb;

        $bank_id = (int) $bank_id;
        if ($bank_id <= 0) {
            return false;
        }

        return false !== $wpdb->update(
            self::table(),
            [
                'is_deleted' => 1,
                'deleted_at' => current_time('mysql'),
                'updated_by' => (int) get_current_user_id() ?: null,
            ],
            ['bank_id' => $bank_id]
        );
    }

    /**
     * Nhập một dòng từ Excel: có mã thì CẬP NHẬT, chưa có thì THÊM.
     *
     * Cố ý không xoá dòng cũ không có trong file — người dùng thường xuất một
     * phần dữ liệu rồi import, xoá theo file là mất sạch phần còn lại.
     *
     * @return string 'created' | 'updated' | 'skipped'
     */
    public static function import_row($data)
    {
        $code = self::normalize_code($data['bank_code'] ?? '');
        if ($code === '') {
            return 'skipped';
        }

        $existing = self::find_by_code($code);

        if ($existing) {
            /*
             * Ô trống trong Excel KHÔNG ghi đè dữ liệu đang có. File xuất ra
             * thường thiếu vài cột; nếu ghi đè thì một lần import là mất hết
             * địa chỉ, ghi chú đã nhập tay trước đó.
             */
            $patch = [];
            foreach (self::editable_fields() as $f => $_) {
                if ($f === 'bank_code') {
                    continue;
                }
                if (isset($data[$f]) && trim((string) $data[$f]) !== '') {
                    $patch[$f] = $data[$f];
                }
            }

            if (empty($patch)) {
                return 'skipped';
            }

            $res = self::save($patch, (int) $existing['bank_id']);
            return $res['ok'] ? 'updated' : 'skipped';
        }

        $data['bank_code'] = $code;
        if (trim((string) ($data['bank_name'] ?? '')) === '') {
            $data['bank_name'] = $code;   // thà lấy mã làm tên còn hơn bỏ mất dòng
        }

        $res = self::save($data, 0);
        return $res['ok'] ? 'created' : 'skipped';
    }
}
