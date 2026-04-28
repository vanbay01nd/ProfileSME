/**
 * quote.js – VPBank SME Portal
 * Chức năng:
 *   1. Đọc & giải mã URL params
 *   2. Tính toán lịch trả nợ hàng tháng (2 giai đoạn lãi suất)
 *   3. Render bảng amortization
 *   4. Xuất PDF (html2pdf.js) và Excel (SheetJS)
 *
 * CÔNG THỨC TÍNH LÃI (Dư nợ giảm dần – Equal Principal):
 *   - Gốc hàng tháng = Tổng dư nợ / Tổng số kỳ (cố định)
 *   - Lãi tháng i    = Dư nợ đầu kỳ i × (Lãi suất năm / 12)
 *   - Tổng trả       = Gốc + Lãi
 */

'use strict';

/* ============================================================
   TIỆN ÍCH FORMAT
   ============================================================ */

/** Format số tiền VNĐ */
function fmtVND(n) {
  if (!isFinite(n)) return '0 ₫';
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency', currency: 'VND', minimumFractionDigits: 0
  }).format(Math.round(n));
}

/** Format số tiền ngắn gọn (triệu/tỷ) */
function fmtShort(n) {
  if (n >= 1e9) return (n / 1e9).toLocaleString('vi-VN', { maximumFractionDigits: 2 }) + ' Tỷ';
  if (n >= 1e6) return (n / 1e6).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' Triệu';
  return fmtVND(n);
}

/** Format phần trăm */
function fmtPct(r) { return r.toFixed(2) + '%'; }

/** Format tháng thành "Tháng M/YYYY" tính từ hôm nay */
function fmtMonth(offset) {
  const d = new Date();
  d.setMonth(d.getMonth() + offset);
  return `T${d.getMonth() + 1}/${d.getFullYear()}`;
}

/* ============================================================
   ĐỌC URL PARAMS & GIẢI MÃ
   ============================================================ */
function parseQuoteParams() {
  const p = new URLSearchParams(window.location.search);
  try {
    return {
      name:    decodeURIComponent(atob(p.get('n') || '')),
      amount:  parseFloat(p.get('a')),
      rate1:   parseFloat(p.get('r1')),   // % / năm
      period1: parseInt(p.get('p1')),      // số tháng ưu đãi
      rate2:   parseFloat(p.get('r2')),   // % / năm sau ưu đãi
      total:   parseInt(p.get('t')),       // tổng tháng
      note:    p.get('nt') ? decodeURIComponent(atob(p.get('nt'))) : '',
      date:    p.get('d') || new Date().toLocaleDateString('vi-VN')
    };
  } catch {
    return null;
  }
}

/* ============================================================
   TÍNH TOÁN LỊCH TRẢ NỢ
   Logic phức hợp (2 giai đoạn lãi suất):
   ──────────────────────────────────────────────────────────
   Phương pháp: Dư nợ giảm dần (Equal Principal Repayment)
   ▸ Gốc hàng tháng  = Tổng vay ÷ Tổng số kỳ  (bất biến mọi kỳ)
   ▸ Lãi kỳ i        = Dư nợ đầu kỳ × (Lãi suất%/năm ÷ 12 ÷ 100)
   ▸ Tổng trả kỳ i   = Gốc + Lãi kỳ i
   ▸ Dư nợ cuối kỳ i = Dư nợ đầu kỳ - Gốc
   ──────────────────────────────────────────────────────────
   Lưu ý: Nếu muốn đổi sang phương pháp Annuity (trả đều),
   hãy thay công thức gốc_monthly = dư_nợ_đầu × r / (1-(1+r)^-n)
   ============================================================ */
function calcAmortization(amount, rate1, period1, rate2, total) {
  const rows = [];

  // Gốc hàng tháng (KHÔNG đổi suốt thời hạn vay - Equal Principal)
  const monthlyPrincipal = amount / total;

  let balance = amount;         // Dư nợ đầu kỳ tích lũy
  let totalPayment  = 0;        // Tổng tiền đã trả (gốc + lãi)
  let totalInterest = 0;        // Tổng lãi đã trả

  for (let month = 1; month <= total; month++) {

    // ── Xác định lãi suất áp dụng cho kỳ này ──────────────
    // Kỳ 1 đến period1  → dùng rate1 (ưu đãi)
    // Kỳ period1+1 trở đi → dùng rate2 (sau ưu đãi)
    const annualRate  = month <= period1 ? rate1 : rate2;
    const monthlyRate = annualRate / 100 / 12;    // Lãi suất tháng (thập phân)

    // ── Tính toán kỳ hiện tại ──────────────────────────────
    const openingBalance = balance;               // Dư nợ đầu kỳ
    const interest        = openingBalance * monthlyRate;  // Số tiền lãi
    const principal       = monthlyPrincipal;     // Số tiền gốc (cố định)
    const totalMonthly    = principal + interest; // Tổng trả trong tháng
    const closingBalance  = Math.max(0, openingBalance - principal); // Dư nợ cuối kỳ

    // ── Đánh dấu tháng chuyển lãi suất (để highlight) ─────
    const isRateChange = month === period1 + 1;

    totalPayment  += totalMonthly;
    totalInterest += interest;
    balance        = closingBalance;

    rows.push({
      month,
      label:          fmtMonth(month - 1),       // Nhãn "Tháng M/YY"
      openingBalance,
      principal,
      interest,
      totalMonthly,
      closingBalance,
      annualRate,
      isRateChange
    });
  }

  return { rows, totalPayment, totalInterest, monthlyPrincipal };
}

/* ============================================================
   RENDER GIAO DIỆN
   ============================================================ */
function renderQuote(params) {
  const { name, amount, rate1, period1, rate2, total, note, date } = params;

  // ── Header Info ──────────────────────────────────────────
  document.getElementById('quote-company-name').textContent = name;
  document.getElementById('quote-greeting').textContent =
    `Báo giá khoản vay vốn dành cho ${name} từ Chuyên viên Nguyễn Văn Bẩy – VPBank`;

  document.getElementById('q-display-amount').textContent  = fmtShort(amount);
  document.getElementById('q-display-rate1').textContent   = fmtPct(rate1) + '/năm';
  document.getElementById('q-display-period1').textContent = `${period1} tháng đầu`;
  document.getElementById('q-display-rate2').textContent   = fmtPct(rate2) + '/năm';
  document.getElementById('q-display-total').textContent   = `${total} tháng`;

  // ── Ngày tạo ─────────────────────────────────────────────
  document.querySelector('#q-created-date span').textContent = date;

  // ── Note (nếu có) ─────────────────────────────────────────
  if (note) {
    document.getElementById('q-note-section').classList.remove('hidden');
    document.getElementById('q-note-text').textContent = note;
  }

  // ── Tính toán ─────────────────────────────────────────────
  const { rows, totalPayment, totalInterest, monthlyPrincipal } = calcAmortization(
    amount, rate1, period1, rate2, total
  );

  // Lãi tháng đầu (kỳ ưu đãi)
  const firstMonthInterest = rows.length ? rows[0].interest : 0;

  // ── Summary Stats ─────────────────────────────────────────
  document.getElementById('q-monthly-principal').textContent = fmtShort(monthlyPrincipal);
  document.getElementById('q-interest-promo').textContent    = fmtShort(firstMonthInterest);
  document.getElementById('q-total-payment').textContent     = fmtShort(totalPayment);
  document.getElementById('q-total-interest').textContent    = fmtShort(totalInterest);

  // ── Render bảng trả nợ ────────────────────────────────────
  const tbody  = document.getElementById('amortization-body');
  const tfoot  = document.getElementById('amortization-footer');
  if (!tbody) return;

  tbody.innerHTML = '';

  rows.forEach(row => {
    const tr = document.createElement('tr');
    if (row.isRateChange) tr.className = 'rate-change-row';

    tr.innerHTML = `
      <td>${row.month}</td>
      <td>${row.label}${row.isRateChange ? ' ⚡' : ''}</td>
      <td>${fmtVND(row.openingBalance)}</td>
      <td>${fmtVND(row.principal)}</td>
      <td class="${row.isRateChange ? '' : 'text-red-500'}">${fmtVND(row.interest)}</td>
      <td class="font-semibold text-vpgreen-DEFAULT">${fmtVND(row.totalMonthly)}</td>
      <td>${fmtVND(row.closingBalance)}</td>
      <td class="${row.annualRate === rate1 ? 'text-vpgreen-bright' : 'text-vpgold-dark'} font-semibold">${fmtPct(row.annualRate)}</td>
    `;
    tbody.appendChild(tr);
  });

  // ── Footer tổng ───────────────────────────────────────────
  tfoot.innerHTML = `
    <tr>
      <td colspan="3" class="text-vpgreen-DEFAULT font-bold">TỔNG CỘNG</td>
      <td>${fmtVND(amount)}</td>
      <td>${fmtVND(totalInterest)}</td>
      <td class="text-vpgreen-DEFAULT">${fmtVND(totalPayment)}</td>
      <td>–</td>
      <td>–</td>
    </tr>
  `;

  // Cập nhật title
  document.title = `Báo Giá Vay Vốn – ${name} | VPBank SME`;
}

/* ============================================================
   XUẤT PDF (html2pdf.js)
   ============================================================ */
function initExportPDF(params) {
  const btn = document.getElementById('export-pdf-btn');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner spin-icon"></i> Đang tạo PDF...';

    const element = document.getElementById('quote-document');
    const opt = {
      margin:      [10, 10, 10, 10],
      filename:    `BaoGia_VPBank_${(params.name || 'SME').replace(/\s+/g, '_')}_${new Date().getTime()}.pdf`,
      image:       { type: 'jpeg', quality: 0.95 },
      html2canvas: { scale: 2, useCORS: true, letterRendering: true },
      jsPDF:       { unit: 'mm', format: 'a4', orientation: 'landscape' },
      pagebreak:   { mode: 'avoid-all', before: '.page-break' }
    };

    try {
      await html2pdf().from(element).set(opt).save();
    } catch (err) {
      alert('Lỗi xuất PDF: ' + err.message);
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  });
}

/* ============================================================
   XUẤT EXCEL (SheetJS)
   Logic: Đọc lại mảng rows từ calcAmortization → tạo worksheet
   ============================================================ */
function initExportExcel(params) {
  const btn = document.getElementById('export-excel-btn');
  if (!btn || typeof XLSX === 'undefined') return;

  btn.addEventListener('click', () => {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner spin-icon"></i> Đang tạo Excel...';

    try {
      const { amount, rate1, period1, rate2, total, name, date } = params;
      const { rows, totalPayment, totalInterest, monthlyPrincipal } = calcAmortization(
        amount, rate1, period1, rate2, total
      );

      // ── Sheet 1: Thông tin khoản vay ──────────────────────
      const infoData = [
        ['THÔNG TIN KHOẢN VAY – VPBank SME Portal'],
        [],
        ['Doanh nghiệp', name],
        ['Chuyên viên', 'Nguyễn Văn Bẩy – VPBank'],
        ['SĐT', '0876.680.001'],
        ['Email', 'Baynv@vpbank.com.vn'],
        ['Ngày tạo báo giá', date],
        [],
        ['Số tiền vay (VNĐ)', amount],
        ['Lãi suất ưu đãi (%/năm)', rate1],
        ['Thời gian ưu đãi (tháng)', period1],
        ['Lãi suất sau ưu đãi (%/năm)', rate2],
        ['Tổng thời gian vay (tháng)', total],
        [],
        ['Gốc hàng tháng (VNĐ)', Math.round(monthlyPrincipal)],
        ['Tổng tiền trả (VNĐ)', Math.round(totalPayment)],
        ['Tổng lãi phải trả (VNĐ)', Math.round(totalInterest)],
      ];

      // ── Sheet 2: Lịch trả nợ chi tiết ────────────────────
      const headers = [
        'Kỳ', 'Tháng',
        'Dư nợ đầu kỳ (VNĐ)',
        'Gốc phải trả (VNĐ)',
        'Lãi phải trả (VNĐ)',
        'Tổng trả (VNĐ)',
        'Dư nợ cuối kỳ (VNĐ)',
        'Lãi suất %/năm'
      ];

      const scheduleData = [headers];
      rows.forEach(r => {
        scheduleData.push([
          r.month,
          r.label,
          Math.round(r.openingBalance),
          Math.round(r.principal),
          Math.round(r.interest),
          Math.round(r.totalMonthly),
          Math.round(r.closingBalance),
          r.annualRate
        ]);
      });

      // Dòng tổng
      scheduleData.push([
        'TỔNG', '',
        '',
        Math.round(amount),
        Math.round(totalInterest),
        Math.round(totalPayment),
        '',
        ''
      ]);

      // ── Tạo workbook ──────────────────────────────────────
      const wb = XLSX.utils.book_new();

      const wsInfo = XLSX.utils.aoa_to_sheet(infoData);
      wsInfo['!cols'] = [{ wch: 35 }, { wch: 30 }];
      XLSX.utils.book_append_sheet(wb, wsInfo, 'Thông Tin Khoản Vay');

      const wsSchedule = XLSX.utils.aoa_to_sheet(scheduleData);
      wsSchedule['!cols'] = [
        { wch: 5 }, { wch: 12 },
        { wch: 22 }, { wch: 22 }, { wch: 20 },
        { wch: 22 }, { wch: 22 }, { wch: 14 }
      ];
      XLSX.utils.book_append_sheet(wb, wsSchedule, 'Lịch Trả Nợ');

      // ── Xuất file ─────────────────────────────────────────
      const filename = `LichTraNo_VPBank_${(name || 'SME').replace(/\s+/g, '_')}_${Date.now()}.xlsx`;
      XLSX.writeFile(wb, filename);

    } catch (err) {
      alert('Lỗi xuất Excel: ' + err.message);
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  });
}

/* ============================================================
   PRINT BUTTON
   ============================================================ */
function initPrintBtn() {
  // Không có nút in riêng ở quote.html,
  // Người dùng có thể dùng Ctrl+P → in từ browser
}

/* ============================================================
   MAIN INIT
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  const params = parseQuoteParams();

  const docEl   = document.getElementById('quote-document');
  const errorEl = document.getElementById('quote-error');

  // Validate params cơ bản
  const isValid = (
    params &&
    params.name &&
    params.amount > 0 &&
    params.rate1 > 0 &&
    params.period1 > 0 &&
    params.rate2 > 0 &&
    params.total > params.period1
  );

  if (!isValid) {
    // Nếu URL trống (demo) → dùng dữ liệu mẫu
    const hasAnyParam = new URLSearchParams(window.location.search).has('a');
    if (hasAnyParam) {
      // Có params nhưng invalid → hiển thị lỗi
      docEl?.classList.add('hidden');
      errorEl?.classList.remove('hidden');
      return;
    }

    // Không có params → load demo
    const demoParams = {
      name:    'Công ty TNHH Thương mại XYZ',
      amount:  5000000000,
      rate1:   6.5,
      period1: 6,
      rate2:   9.5,
      total:   60,
      note:    'Không phạt trả nợ trước hạn sau 12 tháng. Miễn phí thẩm định hồ sơ.',
      date:    new Date().toLocaleDateString('vi-VN')
    };
    renderQuote(demoParams);
    initExportPDF(demoParams);
    initExportExcel(demoParams);
    return;
  }

  renderQuote(params);
  initExportPDF(params);
  initExportExcel(params);
});
