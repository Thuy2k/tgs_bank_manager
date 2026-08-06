<?php

/**
 * Danh mục ngân hàng — danh sách + thêm / sửa / xoá.
 *
 * Sửa bằng modal chứ không phải màn riêng: bảng này nhiều dòng, người dùng sửa
 * liên tiếp vài dòng một lúc — nhảy trang qua lại mỗi lần sửa là mất chỗ đang xem.
 *
 * @package tgs_bank_manager
 */

if (!defined('ABSPATH')) {
    exit;
}

$bank_search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$bank_ready  = TGS_Bank_Repo::table_exists();
$bank_rows   = $bank_ready ? TGS_Bank_Repo::all($bank_search) : [];
$bank_fields = TGS_Bank_Repo::editable_fields();
$bank_can_edit = current_user_can(TGS_BANK_CAPABILITY);
?>

<div class="tgs-bank">

    <?php if (!$bank_ready) : ?>
        <?php // Bảng do plugin chính tạo — nói thẳng cách khắc phục, đừng để trắng màn ?>
        <div class="alert alert-warning">
            <strong>Chưa có bảng dữ liệu <code>global_bank</code>.</strong>
            Vào <em>Plugins</em> → tắt rồi bật lại <strong>TGS Shop Management</strong> để hệ thống tạo bảng, sau đó quay lại màn này.
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body py-3 d-flex flex-wrap align-items-end gap-2">
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>"
                  class="d-flex align-items-end gap-2 flex-grow-1">
                <input type="hidden" name="page" value="tgs-shop-management">
                <input type="hidden" name="view" value="bank-list">

                <div class="tgs-bank__search">
                    <label class="form-label mb-1 fw-semibold" for="bankSearch">
                        <i class="bx bx-search me-1"></i>Tìm kiếm
                    </label>
                    <input type="text" name="s" id="bankSearch" class="form-control form-control-sm"
                           value="<?php echo esc_attr($bank_search); ?>"
                           placeholder="Mã, tên ngân hàng, số TK, chủ TK...">
                </div>

                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bx bx-filter me-1"></i>Tìm
                </button>
                <?php if ($bank_search !== '') : ?>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="<?php echo esc_url(admin_url('admin.php?page=tgs-shop-management&view=bank-list')); ?>">
                        <i class="bx bx-reset me-1"></i>Bỏ lọc
                    </a>
                <?php endif; ?>
            </form>

            <?php if ($bank_can_edit) : ?>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-primary"
                       href="<?php echo esc_url(admin_url('admin.php?page=tgs-shop-management&view=bank-import')); ?>">
                        <i class="bx bx-file-import me-1"></i>Nhập từ Excel
                    </a>
                    <button type="button" class="btn btn-sm btn-primary" id="bankAdd">
                        <i class="bx bx-plus me-1"></i>Thêm ngân hàng
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <?php
        /*
         * Tiêu đề + số dòng gộp CHUNG một khối: tgs-erp-ds.js tự chèn nút
         * "Xuất Excel" vào card-header, để rời ra thì justify-content-between
         * đẩy cái ở giữa ra chính giữa thẻ. Dùng d-inline-flex chứ không phải
         * d-flex, vì hàm chèn nút dò đúng '.d-flex' để nhét vào.
         */
        ?>
        <div class="card-header py-2 d-flex align-items-center justify-content-between">
            <span class="d-inline-flex align-items-center gap-2">
                <strong>Danh mục ngân hàng</strong>
                <span class="badge bg-label-primary"><?php echo esc_html(number_format(count($bank_rows))); ?> dòng</span>
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 tgs-bank__table" id="bankTable">
                <thead>
                    <tr>
                        <?php foreach ($bank_fields as $key => $label) : ?>
                            <th class="col-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></th>
                        <?php endforeach; ?>
                        <?php if ($bank_can_edit) : ?>
                            <th class="col-act" data-ds-filter="off" style="width:96px;">Thao tác</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bank_rows)) : ?>
                        <tr>
                            <td colspan="<?php echo (int) (count($bank_fields) + ($bank_can_edit ? 1 : 0)); ?>"
                                class="text-center text-muted py-5">
                                <i class="bx bx-bank fs-1 d-block mb-2 opacity-50"></i>
                                <?php echo $bank_search !== ''
                                    ? 'Không tìm thấy ngân hàng nào khớp từ khoá.'
                                    : 'Chưa có ngân hàng nào. Bấm "Thêm ngân hàng" hoặc nhập từ Excel.'; ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($bank_rows as $r) : ?>
                            <tr data-bank='<?php echo esc_attr(wp_json_encode($r)); ?>'>
                                <td class="col-bank_code"><strong><?php echo esc_html($r['bank_code']); ?></strong></td>
                                <td class="col-bank_name"><?php echo esc_html($r['bank_name']); ?></td>
                                <td class="col-bank_address"><?php echo esc_html($r['bank_address']); ?></td>
                                <td class="col-bank_phone"><?php echo esc_html($r['bank_phone']); ?></td>
                                <td class="col-account_holder"><?php echo esc_html($r['account_holder']); ?></td>
                                <td class="col-account_number"><?php echo esc_html($r['account_number']); ?></td>
                                <td class="col-contact_person"><?php echo esc_html($r['contact_person']); ?></td>
                                <td class="col-note"><?php echo esc_html($r['note']); ?></td>
                                <td class="col-accounting_code"><?php echo esc_html($r['accounting_code']); ?></td>
                                <?php if ($bank_can_edit) : ?>
                                    <td class="col-act">
                                        <button type="button" class="btn btn-xs btn-outline-primary bank-edit" title="Sửa">
                                            <i class="bx bx-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-danger bank-del" title="Xoá">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($bank_can_edit) : ?>
    <div class="modal fade" id="bankModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bx bx-bank me-1"></i> <span id="bankModalTitle">Thêm ngân hàng</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning small d-none" id="bankAlert"></div>
                    <input type="hidden" id="bankId" value="0">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="f_bank_code">
                                Mã ngân hàng <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="f_bank_code" placeholder="VD: NHND06">
                            <div class="form-text">Tự động viết hoa. Đây là mã dùng để đối chiếu khi nhập Excel.</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold" for="f_bank_name">
                                Tên ngân hàng <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="f_bank_name">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="f_account_holder">Chủ tài khoản</label>
                            <input type="text" class="form-control" id="f_account_holder">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="f_account_number">Số tài khoản</label>
                            <input type="text" class="form-control" id="f_account_number">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="f_bank_phone">Điện thoại</label>
                            <input type="text" class="form-control" id="f_bank_phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="f_contact_person">Người liên lạc</label>
                            <input type="text" class="form-control" id="f_contact_person">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="f_accounting_code">TK kế toán</label>
                            <input type="text" class="form-control" id="f_accounting_code" placeholder="VD: 1121">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="f_bank_address">Địa chỉ</label>
                            <input type="text" class="form-control" id="f_bank_address">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="f_note">Ghi chú</label>
                            <textarea class="form-control" id="f_note" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-primary" id="bankSave">
                        <i class="bx bx-check me-1"></i> Lưu
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        jQuery(function ($) {
            var CFG = {
                ajax: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
                nonce: <?php echo wp_json_encode(wp_create_nonce(TGS_Bank_Ajax::NONCE)); ?>
            };

            // Trùng danh sách cột với repo — thêm cột chỉ phải sửa hai chỗ
            var FIELDS = <?php echo wp_json_encode(array_keys($bank_fields)); ?>;

            function modal() {
                return bootstrap.Modal.getOrCreateInstance(document.getElementById('bankModal'));
            }

            function warn(msg) {
                $('#bankAlert').removeClass('d-none').text(msg);
            }

            function fill(row) {
                $('#bankId').val(row && row.bank_id ? row.bank_id : 0);
                FIELDS.forEach(function (f) {
                    $('#f_' + f).val(row && row[f] ? row[f] : '');
                });
                $('#bankAlert').addClass('d-none').text('');
            }

            $('#bankAdd').on('click', function () {
                $('#bankModalTitle').text('Thêm ngân hàng');
                fill(null);
                modal().show();
            });

            $(document).on('click', '.bank-edit', function () {
                var row = $(this).closest('tr').data('bank') || {};
                $('#bankModalTitle').text('Sửa ngân hàng ' + (row.bank_code || ''));
                fill(row);
                modal().show();
            });

            $('#bankSave').on('click', function () {
                var payload = { action: 'tgs_bank_save', nonce: CFG.nonce, bank_id: $('#bankId').val() };
                FIELDS.forEach(function (f) { payload[f] = $('#f_' + f).val() || ''; });

                if (!payload.bank_code.trim()) { warn('Chưa nhập mã ngân hàng.'); return; }
                if (!payload.bank_name.trim()) { warn('Chưa nhập tên ngân hàng.'); return; }

                var $btn = $(this), old = $btn.html();
                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Đang lưu...');

                $.post(CFG.ajax, payload).done(function (resp) {
                    if (!resp || !resp.success) {
                        warn((resp && resp.data && resp.data.message) || 'Lưu thất bại.');
                        return;
                    }
                    window.location.reload();
                }).fail(function () {
                    warn('Không gọi được máy chủ. Thử lại giúp mình.');
                }).always(function () {
                    $btn.prop('disabled', false).html(old);
                });
            });

            $(document).on('click', '.bank-del', function () {
                var row = $(this).closest('tr').data('bank') || {};
                if (!confirm('Xoá ngân hàng "' + (row.bank_code || '') + ' — ' + (row.bank_name || '') + '"?')) {
                    return;
                }

                $.post(CFG.ajax, {
                    action: 'tgs_bank_delete',
                    nonce: CFG.nonce,
                    bank_id: row.bank_id
                }).done(function (resp) {
                    if (!resp || !resp.success) {
                        alert((resp && resp.data && resp.data.message) || 'Xoá thất bại.');
                        return;
                    }
                    window.location.reload();
                }).fail(function () {
                    alert('Không gọi được máy chủ.');
                });
            });
        });
    </script>
<?php endif; ?>
