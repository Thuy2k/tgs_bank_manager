<?php

/**
 * Danh mục hình thức thanh toán — danh sách + thêm / sửa / xoá.
 *
 * Dữ liệu mồi 18 dòng do tgs_shop_management nạp một lần lúc tạo bảng, chép
 * theo màn tương ứng của phần mềm cũ. Từ đó trở đi người dùng tự quản.
 *
 * @package tgs_bank_manager
 */

if (!defined('ABSPATH')) {
    exit;
}

$pm_search   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$pm_ready    = TGS_Payment_Method_Repo::table_exists();
$pm_rows     = $pm_ready ? TGS_Payment_Method_Repo::all($pm_search) : [];
$pm_can_edit = current_user_can(TGS_BANK_CAPABILITY);

// Gợi ý chọn ngân hàng từ danh mục đã có — đỡ gõ tay sai chính tả
$pm_banks = TGS_Bank_Repo::table_exists() ? TGS_Bank_Repo::all() : [];

/** Ô tích chỉ để xem — dùng chung cho cả 4 cột bật/tắt */
function tgs_pm_check($on)
{
    return !empty($on)
        ? '<i class="bx bx-check-square text-primary fs-5"></i>'
        : '<i class="bx bx-square text-muted opacity-50 fs-5"></i>';
}
?>

<div class="tgs-bank">

    <?php if (!$pm_ready) : ?>
        <div class="alert alert-warning">
            <strong>Chưa có bảng dữ liệu <code>global_payment_method</code>.</strong>
            Vào <em>Plugins</em> → tắt rồi bật lại <strong>TGS Shop Management</strong> để tạo bảng và nạp dữ liệu mẫu.
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body py-3 d-flex flex-wrap align-items-end gap-2">
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>"
                  class="d-flex align-items-end gap-2 flex-grow-1">
                <input type="hidden" name="page" value="tgs-shop-management">
                <input type="hidden" name="view" value="payment-method-list">

                <div class="tgs-bank__search">
                    <label class="form-label mb-1 fw-semibold" for="pmSearch">
                        <i class="bx bx-search me-1"></i>Tìm kiếm
                    </label>
                    <input type="text" name="s" id="pmSearch" class="form-control form-control-sm"
                           value="<?php echo esc_attr($pm_search); ?>"
                           placeholder="Tên, ghi chú, ngân hàng...">
                </div>

                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bx bx-filter me-1"></i>Tìm
                </button>
                <?php if ($pm_search !== '') : ?>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="<?php echo esc_url(admin_url('admin.php?page=tgs-shop-management&view=payment-method-list')); ?>">
                        <i class="bx bx-reset me-1"></i>Bỏ lọc
                    </a>
                <?php endif; ?>
            </form>

            <?php if ($pm_can_edit) : ?>
                <button type="button" class="btn btn-sm btn-primary" id="pmAdd">
                    <i class="bx bx-plus me-1"></i>Thêm hình thức
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <?php // Gộp tiêu đề + badge để nút "Xuất Excel" (do design-system chèn) bám phải ?>
        <div class="card-header py-2 d-flex align-items-center justify-content-between">
            <span class="d-inline-flex align-items-center gap-2">
                <strong>Danh mục hình thức thanh toán</strong>
                <span class="badge bg-label-primary"><?php echo esc_html(number_format(count($pm_rows))); ?> dòng</span>
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 tgs-bank__table" id="pmTable">
                <thead>
                    <tr>
                        <th class="col-pm_name">Tên</th>
                        <th class="text-center col-flag">HTTT qua ngân hàng</th>
                        <th class="text-center col-flag">Thanh toán qua QRCode</th>
                        <th class="col-note">Ghi chú</th>
                        <th class="text-end col-num">Tỉ lệ chiết khấu</th>
                        <th class="text-center col-flag">Mặc định</th>
                        <th class="col-unit">Đơn vị TT</th>
                        <th class="col-bank">Ngân hàng</th>
                        <th class="text-center col-flag">Hiển thị ShortCut</th>
                        <?php if ($pm_can_edit) : ?>
                            <th class="col-act" data-ds-filter="off" style="width:96px;">Thao tác</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pm_rows)) : ?>
                        <tr>
                            <td colspan="<?php echo $pm_can_edit ? 10 : 9; ?>" class="text-center text-muted py-5">
                                <i class="bx bx-credit-card fs-1 d-block mb-2 opacity-50"></i>
                                <?php echo $pm_search !== ''
                                    ? 'Không tìm thấy hình thức thanh toán nào khớp từ khoá.'
                                    : 'Chưa có dữ liệu.'; ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($pm_rows as $r) : ?>
                            <tr data-pm='<?php echo esc_attr(wp_json_encode($r)); ?>'>
                                <td class="col-pm_name"><strong><?php echo esc_html($r['pm_name']); ?></strong></td>
                                <td class="text-center"><?php echo tgs_pm_check($r['via_bank']); ?></td>
                                <td class="text-center"><?php echo tgs_pm_check($r['via_qrcode']); ?></td>
                                <td class="col-note"><?php echo esc_html($r['note']); ?></td>
                                <td class="text-end">
                                    <?php echo ((float) $r['discount_rate']) != 0
                                        ? esc_html(rtrim(rtrim(number_format((float) $r['discount_rate'], 3), '0'), '.') . '%')
                                        : ''; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($r['is_default'])) : ?>
                                        <span class="badge bg-label-success">Mặc định</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-unit"><?php echo esc_html($r['payment_unit']); ?></td>
                                <td class="col-bank"><?php echo esc_html($r['bank_label']); ?></td>
                                <td class="text-center"><?php echo tgs_pm_check($r['show_shortcut']); ?></td>
                                <?php if ($pm_can_edit) : ?>
                                    <td class="col-act">
                                        <button type="button" class="btn btn-xs btn-outline-primary pm-edit" title="Sửa">
                                            <i class="bx bx-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-danger pm-del" title="Xoá">
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

<?php if ($pm_can_edit) : ?>
    <div class="modal fade" id="pmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bx bx-credit-card me-1"></i> <span id="pmModalTitle">Thêm hình thức thanh toán</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning small d-none" id="pmAlert"></div>
                    <input type="hidden" id="pmId" value="0">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="f_pm_name">
                                Tên <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="f_pm_name" placeholder="VD: QR Agri CT">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="f_discount_rate">Tỉ lệ chiết khấu (%)</label>
                            <input type="number" step="0.001" min="0" class="form-control" id="f_discount_rate" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="f_payment_unit">Đơn vị TT</label>
                            <input type="text" class="form-control" id="f_payment_unit">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold" for="f_bank_label">Ngân hàng</label>
                            <?php // list cho phép vừa chọn vừa gõ tay — dữ liệu mồi có tên chưa khớp danh mục ?>
                            <input type="text" class="form-control" id="f_bank_label" list="pmBankList"
                                   placeholder="Chọn từ danh mục hoặc gõ tay">
                            <datalist id="pmBankList">
                                <?php foreach ($pm_banks as $b) : ?>
                                    <option value="<?php echo esc_attr($b['bank_name']); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="f_note">Ghi chú</label>
                            <textarea class="form-control" id="f_note" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="f_via_bank">
                                        <label class="form-check-label" for="f_via_bank">HTTT qua ngân hàng</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="f_via_qrcode">
                                        <label class="form-check-label" for="f_via_qrcode">Thanh toán qua QRCode</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="f_show_shortcut">
                                        <label class="form-check-label" for="f_show_shortcut">Hiển thị ShortCut</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="f_is_default">
                                        <label class="form-check-label" for="f_is_default">Mặc định</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-text">
                                Bật <strong>Mặc định</strong> ở đây sẽ tự tắt ở hình thức đang mặc định — chỉ được một cái.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-primary" id="pmSave">
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

            var TEXTS = <?php echo wp_json_encode(array_keys(TGS_Payment_Method_Repo::text_fields())); ?>;
            var BOOLS = <?php echo wp_json_encode(array_keys(TGS_Payment_Method_Repo::bool_fields())); ?>;

            function modal() {
                return bootstrap.Modal.getOrCreateInstance(document.getElementById('pmModal'));
            }

            function warn(msg) { $('#pmAlert').removeClass('d-none').text(msg); }

            function fill(row) {
                $('#pmId').val(row && row.pm_id ? row.pm_id : 0);
                TEXTS.forEach(function (f) {
                    $('#f_' + f).val(row && row[f] != null ? row[f] : (f === 'discount_rate' ? 0 : ''));
                });
                BOOLS.forEach(function (f) {
                    $('#f_' + f).prop('checked', !!(row && Number(row[f])));
                });
                $('#pmAlert').addClass('d-none').text('');
            }

            $('#pmAdd').on('click', function () {
                $('#pmModalTitle').text('Thêm hình thức thanh toán');
                fill(null);
                modal().show();
            });

            $(document).on('click', '.pm-edit', function () {
                var row = $(this).closest('tr').data('pm') || {};
                $('#pmModalTitle').text('Sửa: ' + (row.pm_name || ''));
                fill(row);
                modal().show();
            });

            $('#pmSave').on('click', function () {
                var payload = { action: 'tgs_pm_save', nonce: CFG.nonce, pm_id: $('#pmId').val() };
                TEXTS.forEach(function (f) { payload[f] = $('#f_' + f).val() || ''; });
                BOOLS.forEach(function (f) { payload[f] = $('#f_' + f).is(':checked') ? 1 : 0; });

                if (!payload.pm_name.trim()) { warn('Chưa nhập tên hình thức thanh toán.'); return; }

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

            $(document).on('click', '.pm-del', function () {
                var row = $(this).closest('tr').data('pm') || {};
                if (!confirm('Xoá hình thức thanh toán "' + (row.pm_name || '') + '"?')) { return; }

                $.post(CFG.ajax, {
                    action: 'tgs_pm_delete',
                    nonce: CFG.nonce,
                    pm_id: row.pm_id
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
