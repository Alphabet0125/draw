// PDF Editor Functions
var pdfDoc = null;
var currentPage = 1;
var totalPages = 0;

// เปิด PDF Editor
async function openPDFEditor(fileId, fileName) {
    currentFileId = fileId;
    currentFileName = fileName;
    currentPage = 1;
    
    const modal = document.getElementById('drawingModal');
    document.getElementById('currentFileName').textContent = fileName;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    const canvasInfo = document.getElementById('canvasInfo');
    if (canvasInfo) {
        canvasInfo.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังโหลด PDF...<br><small>กรุณารอสักครู่</small>';
    }
    
    history = [];
    historyStep = -1;
    
    setTimeout(() => {
        loadPDFDocument(fileId);
    }, 100);
}

// โหลด PDF Document
async function loadPDFDocument(fileId) {
    try {
        const pdfUrl = `preview_pdf.php?id=${fileId}&t=${new Date().getTime()}`;
        
        console.log('Loading PDF from:', pdfUrl);
        
        const loadingTask = pdfjsLib.getDocument({
            url: pdfUrl,
            withCredentials: false,
            isEvalSupported: false
        });
        
        loadingTask.onProgress = function(progress) {
            if (progress.total > 0) {
                const percent = Math.round((progress.loaded / progress.total) * 100);
                const canvasInfo = document.getElementById('canvasInfo');
                if (canvasInfo) {
                    canvasInfo.innerHTML = `
                        <i class="fas fa-spinner fa-spin"></i> กำลังโหลด PDF...<br>
                        <div style="margin-top: 10px; background: var(--bg-color); border-radius: 5px; overflow: hidden; height: 20px;">
                            <div style="width: ${percent}%; height: 100%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); transition: width 0.3s;"></div>
                        </div>
                        <small>${percent}%</small>
                    `;
                }
            }
        };
        
        pdfDoc = await loadingTask.promise;
        totalPages = pdfDoc.numPages;
        
        console.log(`✅ PDF loaded successfully: ${totalPages} pages`);
        
        await renderPDFPage(currentPage);
        
        if (totalPages > 1) {
            addPageNavigation();
        }
        
    } catch (error) {
        console.error('Error loading PDF:', error);
        
        let errorMessage = 'ไม่สามารถโหลด PDF ได้';
        
        if (error.name === 'InvalidPDFException') {
            errorMessage = 'ไฟล์ PDF ไม่ถูกต้องหรือเสียหาย';
        } else if (error.name === 'MissingPDFException') {
            errorMessage = 'ไม่พบไฟล์ PDF';
        } else if (error.name === 'UnexpectedResponseException') {
            errorMessage = 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง';
        } else if (error.message) {
            errorMessage += ': ' + error.message;
        }
        
        alert('❌ ' + errorMessage);
        
        const pageNav = document.getElementById('pdfPageNav');
        if (pageNav) {
            pageNav.remove();
        }
        
        closeDrawingModal();
    }
}

// Render PDF Page - เพิ่ม 150px รอบๆ
async function renderPDFPage(pageNum) {
    try {
        console.log(`Rendering PDF page ${pageNum}/${totalPages}...`);
        
        const page = await pdfDoc.getPage(pageNum);
        const viewport = page.getViewport({ scale: 1.5 });
        
        // ขนาดจริงของ PDF
        originalImageWidth = viewport.width;
        originalImageHeight = viewport.height;
        
        // เพิ่มพื้นที่รอบๆ 150px
        const PADDING = 150;
        
        imageOffsetX = PADDING;
        imageOffsetY = PADDING;
        
        const canvasWidth = originalImageWidth + (PADDING * 2);
        const canvasHeight = originalImageHeight + (PADDING * 2);
        
        canvas = document.getElementById('drawingCanvas');
        ctx = canvas.getContext('2d');
        
        if (!tempCanvas) {
            tempCanvas = document.createElement('canvas');
            tempCtx = tempCanvas.getContext('2d');
        }
        
        canvas.width = canvasWidth;
        canvas.height = canvasHeight;
        tempCanvas.width = canvasWidth;
        tempCanvas.height = canvasHeight;
        
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // สร้าง Canvas ชั่วคราวสำหรับ render PDF
        const tempPDFCanvas = document.createElement('canvas');
        const tempPDFContext = tempPDFCanvas.getContext('2d');
        tempPDFCanvas.width = originalImageWidth;
        tempPDFCanvas.height = originalImageHeight;
        
        const renderContext = {
            canvasContext: tempPDFContext,
            viewport: viewport
        };
        
        console.log('Rendering PDF to canvas...');
        await page.render(renderContext).promise;
        console.log('PDF rendered successfully');
        
        // วาด PDF ลงบน main canvas (ตรงกลาง)
        ctx.drawImage(tempPDFCanvas, imageOffsetX, imageOffsetY);
        
        // เก็บรูป PDF เป็น originalImage
        originalImage = new Image();
        originalImage.src = tempPDFCanvas.toDataURL();
        originalImage.width = originalImageWidth;
        originalImage.height = originalImageHeight;
        
        updatePDFInfo(originalImageWidth, originalImageHeight, canvasWidth, canvasHeight, pageNum);
        
        if (history.length === 0) {
            if (typeof saveState === 'function') saveState();
            if (typeof initializeEventListeners === 'function') initializeEventListeners();
            if (typeof initializeToolbar === 'function') initializeToolbar();
        } else {
            if (typeof saveState === 'function') saveState();
        }
        
        console.log(`✅ PDF Page ${pageNum}/${totalPages}: ${Math.round(originalImageWidth)}x${Math.round(originalImageHeight)}px | Canvas: ${canvasWidth}x${canvasHeight}px`);
        
    } catch (error) {
        console.error('Error rendering PDF page:', error);
        alert('❌ ไม่สามารถแสดง PDF หน้าที่ ' + pageNum + ' ได้\n\n' + error.message);
    }
}

// อัปเดตข้อมูล PDF
function updatePDFInfo(pdfW, pdfH, canvasW, canvasH, pageNum) {
    const canvasInfo = document.getElementById('canvasInfo');
    if (canvasInfo) {
        let pageInfo = '';
        if (totalPages > 1) {
            pageInfo = `<strong style="color: var(--primary-color);">📄 หน้า ${pageNum}/${totalPages}</strong><br>`;
        } else {
            pageInfo = `<strong style="color: var(--primary-color);">📄 PDF (1 หน้า)</strong><br>`;
        }
        
        canvasInfo.innerHTML = `
            <i class="fas fa-file-pdf" style="font-size: 1.5rem; color: #e74c3c;"></i><br>
            ${pageInfo}
            <strong>ขนาด PDF:</strong><br>
            ${Math.round(pdfW)} × ${Math.round(pdfH)}px<br>
            <strong>Canvas รวม:</strong><br>
            ${canvasW} × ${canvasH}px<br>
            <strong style="color: var(--primary-color);">พื้นที่วาด: +150px รอบๆ</strong><br>
            <span style="font-size: 0.75rem; color: var(--text-secondary);">
                <i class="fas fa-info-circle"></i> บันทึก = ขนาดจริง
            </span>
        `;
    }
}

// เพิ่มปุ่มเปลี่ยนหน้า PDF
function addPageNavigation() {
    if (document.getElementById('pdfPageNav')) {
        return;
    }
    
    const canvasInfoSection = document.querySelector('.toolbar-section:has(#canvasInfo)');
    if (!canvasInfoSection) return;
    
    const pageNavSection = document.createElement('div');
    pageNavSection.className = 'toolbar-section';
    pageNavSection.id = 'pdfPageNav';
    pageNavSection.innerHTML = `
        <h4><i class="fas fa-file-alt"></i> เปลี่ยนหน้า PDF</h4>
        <div class="page-nav-buttons">
            <button class="page-nav-btn" id="prevPageBtn" onclick="changePDFPage(-1)" title="หน้าก่อนหน้า">
                <i class="fas fa-chevron-left"></i> ก่อนหน้า
            </button>
            <div class="page-number" id="pageNumber">
                <span id="currentPageNum">1</span> / <span id="totalPageNum">${totalPages}</span>
            </div>
            <button class="page-nav-btn" id="nextPageBtn" onclick="changePDFPage(1)" title="หน้าถัดไป">
                ถัดไป <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    `;
    
    canvasInfoSection.parentNode.insertBefore(pageNavSection, canvasInfoSection.nextSibling);
    
    updatePageButtons();
}

// เปลี่ยนหน้า PDF
async function changePDFPage(direction) {
    const newPage = currentPage + direction;
    
    if (newPage < 1 || newPage > totalPages) {
        return;
    }
    
    if (history.length > 1) {
        if (!confirm('⚠️ การเปลี่ยนหน้าจะทำให้การวาดที่ยังไม่ได้บันทึกหายไป\n\nคุณต้องการดำเนินการต่อหรือไม่?')) {
            return;
        }
    }
    
    currentPage = newPage;
    
    history = [];
    historyStep = -1;
    
    await renderPDFPage(currentPage);
    
    updatePageButtons();
}

// อัปเดตสถานะปุ่มเปลี่ยนหน้า
function updatePageButtons() {
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    const currentPageNum = document.getElementById('currentPageNum');
    
    if (prevBtn) {
        prevBtn.disabled = currentPage <= 1;
        prevBtn.style.opacity = currentPage <= 1 ? '0.5' : '1';
    }
    
    if (nextBtn) {
        nextBtn.disabled = currentPage >= totalPages;
        nextBtn.style.opacity = currentPage >= totalPages ? '0.5' : '1';
    }
    
    if (currentPageNum) {
        currentPageNum.textContent = currentPage;
    }
}