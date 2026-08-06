<?php

/**
 * Nhập danh mục ngân hàng từ Excel.
 *
 * Theo đúng lối của màn "Nhập sản phẩm từ Excel": trình duyệt đọc file bằng
 * SheetJS, xem trước, rồi gửi lên máy chủ theo từng lô kèm thanh tiến độ.
 *
 * Đọc ở trình duyệt có hai cái lợi: không phải tải file lên máy chủ (khỏi vướng
 * giới hạn upload), và người dùng thấy ngay dữ liệu có đọc đúng cột không TRƯỚC
 * khi ghi bất cứ thứ gì vào cơ sở dữ liệu.
 *
 * @package tgs_bank_manager
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can(TGS_BANK_CAPABILITY)) {
    echo '<div class="alert alert-danger">Bạn không có quyền nhập dữ liệu ngân hàng.</div>';
    return;
}

$bank_ready  = TGS_Bank_Repo::table_exists();
$bank_fields = TGS_Bank_Repo::editable_fields();
?>

<div class="tgs-bank tgs-bank-import">

    <?php if (!$bank_ready) : ?>
        <div class="alert alert-warning">
            <strong>Chưa có bảng dữ liệu <code>global_bank</code>.</strong>
            Vào <em>Plugins</em> → tắt rồi bật lại <strong>TGS Shop Management</strong> để tạo bảng.
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header py-2">
            <strong><i class="bx bx-spreadsheet me-1"></i>Định dạng file Excel</strong>
        </div>
        <div class="card-body py-3">
            <p class="mb-2 small">
                Dòng đầu tiên là <strong>tiêu đề cột</strong> (hệ thống bỏ qua).
                Từ dòng 2 trở đi là dữ liệu, theo đúng thứ tự cột dưới đây —
                khớp với thứ tự khi xuất từ phần mềm cũ:
            </p>

            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered mb-0 tgs-bank__fmt" data-ds-no-export data-ds-no-filter>
                    <thead>
                        <tr>
                            <?php $i = 'A'; ?>
                            <?php foreach ($bank_fields as $label) : ?>
                                <th class="text-center"><?php echo esc_html($i++); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ($bank_fields as $key => $label) : ?>
                                <td class="small">
                                    <?php echo esc_html($label); ?>
                                    <?php if ($key === 'bank_code') : ?>
                                        <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="small mb-0 ps-3">
                <li><strong>Mã ngân hàng</strong> là bắt buộc — dòng thiếu mã sẽ bị bỏ qua.</li>
                <li>Mã đã tồn tại thì <strong>cập nhật</strong> dòng cũ, chưa có thì <strong>thêm mới</strong>.</li>
                <li><strong>Ô trống không ghi đè</strong> dữ liệu đang có — nhập file thiếu cột vẫn an toàn.</li>
                <li>Dòng cũ không nằm trong file <strong>vẫn được giữ nguyên</strong>, không bị xoá.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header py-2">
            <strong><i class="bx bx-upload me-1"></i>Chọn file</strong>
        </div>
        <div class="card-body py-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="bankFile">File Excel (.xlsx, .xls)</label>
                    <input type="file" class="form-control" id="bankFile" accept=".xlsx,.xls">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="bankSheet">Sheet</label>
                    <select class="form-select" id="bankSheet" disabled>
                        <option value="">— Chọn file trước —</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100" id="bankRun" disabled>
                        <i class="bx bx-play me-1"></i>Bắt đầu
                    </button>
                </div>
            </div>

            <div class="progress mt-3 d-none" id="bankProgWrap" style="height:20px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="bankProg" style="width:0%">0%</div>
            </div>

            <div class="alert alert-info small mt-3 d-none" id="bankResult"></div>
        </div>
    </div>

    <div class="card d-none" id="bankPreviewCard">
        <div class="card-header py-2 d-flex align-items-center justify-content-between">
            <span class="d-inline-flex align-items-center gap-2">
                <strong>Xem trước</strong>
                <span class="badge bg-label-primary" id="bankPreviewCount">0 dòng</span>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="bankPreview" data-ds-no-export>
                <thead>
                    <tr>
                        <th style="width:60px;">Dòng</th>
                        <?php foreach ($bank_fields as $label) : ?>
                            <th><?php echo esc_html($label); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
    jQuery(function ($) {
        var CFG = {
            ajax: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            nonce: <?php echo wp_json_encode(wp_create_nonce(TGS_Bank_Ajax::NONCE)); ?>,
            listUrl: <?php echo wp_json_encode(admin_url('admin.php?page=tgs-shop-management&view=bank-list')); ?>
        };

        /* Thứ tự này CHÍNH LÀ thứ tự cột trong file Excel — đổi ở đây là đổi
           luôn cách đọc file, nên phải khớp bảng "Định dạng file" phía trên */
        var FIELDS = <?php echo wp_json_encode(array_keys($bank_fields)); ?>;

        var BATCH = 100;   // đủ nhỏ để không đụng giới hạn POST, đủ lớn để không chậm
        var workbook = null;
        var rows = [];

        function esc(s) { return $('<i>').text(s == null ? '' : String(s)).html(); }

        function cell(row, i) {
            var v = row[i];
            if (v === undefined || v === null) return '';
            return String(v).trim();
        }

        function loadSheet(name) {
            var sheet = workbook.Sheets[name];
            var raw = XLSX.utils.sheet_to_json(sheet, { header: 1 });

            rows = [];
            // Bỏ dòng 1 (tiêu đề); số dòng thật bắt đầu từ 2 để báo lỗi đúng chỗ
            raw.slice(1).forEach(function (r, idx) {
                var o = { __row: idx + 2 };
                FIELDS.forEach(function (f, i) { o[f] = cell(r, i); });

                // Dòng trống hoàn toàn thì bỏ, đừng tính vào tổng cho rối
                var hasAny = FIELDS.some(function (f) { return o[f] !== ''; });
                if (hasAny) { rows.push(o); }
            });

            renderPreview();
            $('#bankRun').prop('disabled', rows.length === 0);
        }

        function renderPreview() {
            var html = rows.slice(0, 50).map(function (o) {
                return '<tr><td class="text-muted">' + o.__row + '</td>'
                    + FIELDS.map(function (f) {
                        var v = esc(o[f]);
                        return '<td>' + (f === 'bank_code' ? '<strong>' + v + '</strong>' : v) + '</td>';
                    }).join('')
                    + '</tr>';
            }).join('');

            $('#bankPreview tbody').html(html);
            $('#bankPreviewCount').text(rows.length + ' dòng'
                + (rows.length > 50 ? ' (xem trước 50 dòng đầu)' : ''));
            $('#bankPreviewCard').toggleClass('d-none', rows.length === 0);
        }

        $('#bankFile').on('change', function () {
            var file = this.files && this.files[0];
            $('#bankResult').addClass('d-none').text('');
            rows = [];
            renderPreview();

            if (!file) {
                $('#bankSheet').prop('disabled', true).html('<option value="">— Chọn file trước —</option>');
                $('#bankRun').prop('disabled', true);
                return;
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                try {
                    workbook = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
                } catch (err) {
                    alert('Không đọc được file Excel: ' + err.message);
                    return;
                }

                var names = workbook.SheetNames || [];
                $('#bankSheet').prop('disabled', false)
                    .html(names.map(function (n) {
                        return '<option value="' + esc(n) + '">' + esc(n) + '</option>';
                    }).join(''));

                // Một sheet thì khỏi bắt chọn, nạp luôn
                if (names.length) { loadSheet(names[0]); }
            };
            reader.readAsArrayBuffer(file);
        });

        $('#bankSheet').on('change', function () {
            if (this.value) { loadSheet(this.value); }
        });

        $('#bankRun').on('click', function () {
            if (!rows.length) { return; }

            var $btn = $(this), old = $btn.html();
            var total = rows.length, done = 0;
            var sum = { created: 0, updated: 0, skipped: 0, errors: [] };

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            $('#bankProgWrap').removeClass('d-none');
            $('#bankResult').addClass('d-none').removeClass('alert-danger alert-success').addClass('alert-info');

            function step(start) {
                if (start >= total) { finish(); return; }

                var chunk = rows.slice(start, start + BATCH);

                $.post(CFG.ajax, {
                    action: 'tgs_bank_import',
                    nonce: CFG.nonce,
                    rows: JSON.stringify(chunk)
                }).done(function (resp) {
                    if (!resp || !resp.success) {
                        fail((resp && resp.data && resp.data.message) || 'Máy chủ trả về lỗi.');
                        return;
                    }
                    var d = resp.data || {};
                    sum.created += d.created || 0;
                    sum.updated += d.updated || 0;
                    sum.skipped += d.skipped || 0;
                    if (d.errors && d.errors.length) { sum.errors = sum.errors.concat(d.errors); }

                    done += chunk.length;
                    var pct = Math.round(done / total * 100);
                    $('#bankProg').css('width', pct + '%').text(pct + '%');

                    step(start + BATCH);
                }).fail(function () {
                    fail('Mất kết nối tới máy chủ.');
                });
            }

            function fail(msg) {
                $btn.prop('disabled', false).html(old);
                $('#bankResult').removeClass('d-none alert-info').addClass('alert-danger')
                    .html('<strong>Dừng ở dòng thứ ' + (done + 1) + '.</strong> ' + esc(msg)
                        + '<br>Các dòng đã ghi trước đó vẫn được giữ — chạy lại file này là an toàn, dòng cũ sẽ được cập nhật chứ không nhân đôi.');
            }

            function finish() {
                $btn.prop('disabled', false).html(old);

                var html = '<strong>Xong.</strong> Thêm mới <strong>' + sum.created
                    + '</strong> · Cập nhật <strong>' + sum.updated
                    + '</strong> · Bỏ qua <strong>' + sum.skipped + '</strong>.';

                if (sum.errors.length) {
                    html += '<br><span class="text-danger">Dòng bị bỏ qua (thiếu mã hoặc không có gì để ghi): '
                        + sum.errors.slice(0, 20).map(function (e) { return e.row; }).join(', ')
                        + (sum.errors.length > 20 ? '…' : '') + '</span>';
                }

                html += '<br><a href="' + CFG.listUrl + '" class="btn btn-sm btn-primary mt-2">'
                    + '<i class="bx bx-list-ul me-1"></i>Xem danh mục ngân hàng</a>';

                $('#bankResult').removeClass('d-none alert-info').addClass('alert-success').html(html);
            }

            step(0);
        });
    });
</script>
