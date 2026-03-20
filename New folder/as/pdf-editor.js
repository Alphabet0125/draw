// PDF Editor Functions
// ใช้ global variables จาก drawing-editor.js
var pdfDoc = null;
var currentPage = 1;
var totalPages = 0;

// ตัวแปร Padding สำหรับ PDF (เพิ่มใหม่)
var pdfPaddingSize = 50;

// เปิด PDF Editor
async function openPDFEditor(fileId, fileName) {
    // ตั้งค่า global variables
    currentFileId = fileId;
    currentFileName = fileName;
    currentPage = 1;
    
    const modal = document.getElementById('drawingModal');
    document.getElementById('currentFileName').textContent = fileName;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // ซ่อนปุ่มเพิ่มพื้นที่สำหรับ PDF (เพิ่มใหม่)
    const paddingSection = document.getElementById('paddingControlSection');
    if (paddingSection) paddingSection.style.display = 'none';
    
    // แสดง loading
    const canvasInfo = document.getElementById('canvasInfo');
    if (canvasInfo) {
        canvasInfo.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังโหลด PDF...<br><small>กรุณารอสักค���ู่</small>';
    }
    
    // ล้าง history
    history = [];
    historyStep = -1;
    
    setTimeout(() => {
        loadPDFDocument(fileId);
    }, 100);
}

// โหลด PDF Document (ไม่แก้ไข)
async function loadPDFDocument(fileId) {
    try {
        const pdfUrl = `preview_pdf.php?id=${fileId}&t=${new Date().getTime()}`;
        
        console.log('📄 Loading PDF from:', pdfUrl);
        
        const testResponse = await fetch(pdfUrl);
        console.log('📊 Response Status:', testResponse.status);
        console.log('📊 Response Type:', testResponse.headers.get('content-type'));
        
        if (!testResponse.ok) {
            const errorText = await testResponse.text();
            console.error('❌ Server Error:', errorText);
            throw new Error('Server Error: ' + errorText);
        }
        
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
        console.error('❌ Error loading PDF:', error);
        
        let errorMessage = 'ไม่สามารถโหลด PDF ได้';
        
        if (error.name === 'InvalidPDFException') {
            errorMessage = 'ไฟล์ PDF ไม่ถูกต้องหรือเสียหาย';
        } else if (error.name === 'MissingPDFException') {
            errorMessage = 'ไม่พบไฟล์ PDF';
        } else if (error.name === 'UnexpectedResponseException') {
            errorMessage = 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง';
        } else if (error.message) {
            errorMessage = error.message;
        }
        
        alert('❌ ' + errorMessage + '\n\nกรุณาตรวจสอบ:\n1. ไฟล์เป็น PDF จริง\n2. ไฟล์ไม่เสียหาย\n3. มีไฟล์ preview_pdf.php\n\nดู Console (F12) สำหรับข้อมูลเพิ่มเติม');
        
        const pageNav = document.getElementById('pdfPageNav');
        if (pageNav) {
            pageNav.remove();
        }
        
        closeDrawingModal();
    }
}

// Render PDF Page (แก้ไขให้ใช้ pdfPaddingSize)
async function renderPDFPage(pageNum) {
    try {
        console.log(`🎨 Rendering PDF page ${pageNum}/${totalPages}...`);
        
        const page = await pdfDoc.getPage(pageNum);
        const viewport = page.getViewport({ scale: 1.5 });
        
        // เก็บขนาดต้นฉบับ
        originalImageWidth = viewport.width;
        originalImageHeight = viewport.height;
        
        // ใช้ pdfPaddingSize แทนค่าคงที่ (แก้ไขตรงนี้)
        const PADDING = pdfPaddingSize;
        
        // คำนวณ offset เพื่อวาดรูปตรงกลาง
        imageOffsetX = PADDING;
        imageOffsetY = PADDING;
        
        // ตั้งค่าขนาด canvas = ขนาดจริง + padding รอบๆ
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
        
        console.log('🖼️ Rendering PDF to canvas...');
        await page.render(renderContext).promise;
        console.log('✅ PDF rendered successfully');
        
        // วาด PDF ลงบน main canvas (ตรงกลาง)
        ctx.drawImage(tempPDFCanvas, imageOffsetX, imageOffsetY);
        
        // เก็บรูป PDF เ���็น originalImage
        originalImage = new Image();
        originalImage.src = tempPDFCanvas.toDataURL();
        originalImage.width = originalImageWidth;
        originalImage.height = originalImageHeight;
        
        // อัปเดตข้อมูล
        updatePDFInfo(originalImageWidth, originalImageHeight, canvasWidth, canvasHeight, pageNum);
        
        console.log('🎨 Initializing drawing tools...');
        
        // บังคับ initialize event listeners ใหม่ทุกครั้ง
        const newCanvas = canvas.cloneNode(true);
        canvas.parentNode.replaceChild(newCanvas, canvas);
        canvas = document.getElementById('drawingCanvas');
        ctx = canvas.getContext('2d');
        
        canvas.width = canvasWidth;
        canvas.height = canvasHeight;
        ctx.drawImage(tempPDFCanvas, imageOffsetX, imageOffsetY);
        
        if (typeof saveState === 'function') {
            saveState();
        }
        
        if (typeof initializeEventListeners === 'function') {
            initializeEventListeners();
            console.log('✅ Event listeners initialized');
        }
        
        if (history.length === 1) {
            if (typeof initializeToolbar === 'function') {
                initializeToolbar();
                console.log('✅ Toolbar initialized');
            }
        }
        
        console.log(`✅ PDF Page ${pageNum}/${totalPages} ready: ${Math.round(originalImageWidth)}x${Math.round(originalImageHeight)}px`);
        console.log(`🎨 Canvas: ${canvasWidth}x${canvasHeight}px | Padding: ${PADDING}px`);
        console.log('🖌️ All drawing tools are ready!');
        
    } catch (error) {
        console.error('❌ Error rendering PDF page:', error);
        alert('❌ ไม่สามารถแสดง PDF หน้าที่ ' + pageNum + ' ได้\n\n' + error.message);
    }
}

// ฟังก์ชันเพิ่มพื้นที่รอบๆ สำหรับ PDF (เพิ่มใหม่)
function increasePadding() {
    if (!originalImage) {
        alert('❌ ไม่พบ PDF ต้นฉบับ');
        return;
    }
    
    // เช็คว่าเป็น PDF หรือไม่
    if (pdfDoc) {
        // เพิ่ม padding สำหรับ PDF
        pdfPaddingSize += 100;
        console.log(`📏 PDF: Increasing padding to ${pdfPaddingSize}px...`);
        redrawPDFWithPadding();
    } else {
        // เพิ่ม padding สำหรับรูปภาพ (ใช้ฟังก์ชันจาก drawing-editor.js)
        paddingSize += 100;
        console.log(`📏 Image: Increasing padding to ${paddingSize}px...`);
        redrawWithPadding();
    }
}

// ฟังก์ชันวาด PDF ใหม่ตาม padding ใหม่ (เพิ่มใหม่)
function redrawPDFWithPadding() {
    if (!originalImage) return;
    
    // คำนวณ padding เก่า
    const oldPadding = pdfPaddingSize - 100;
    
    // บันทึก canvas ปัจจุบัน
    const oldCanvas = document.createElement('canvas');
    const oldCtx = oldCanvas.getContext('2d');
    oldCanvas.width = canvas.width;
    oldCanvas.height = canvas.height;
    oldCtx.drawImage(canvas, 0, 0);
    
    // คำนวณขนาดและตำแหน่งใหม่
    imageOffsetX = pdfPaddingSize;
    imageOffsetY = pdfPaddingSize;
    
    const newCanvasWidth = originalImageWidth + (pdfPaddingSize * 2);
    const newCanvasHeight = originalImageHeight + (pdfPaddingSize * 2);
    
    // ปรับขนาด canvas ใหม่
    canvas.width = newCanvasWidth;
    canvas.height = newCanvasHeight;
    tempCanvas.width = newCanvasWidth;
    tempCanvas.height = newCanvasHeight;
    
    // ล้าง canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // คำนวณค่าเลื่อน offset (PDF จะอยู่ตรงกลางเสมอ)
    const offsetDiff = pdfPaddingSize - oldPadding;
    
    // วาด canvas เดิมที่ตำแหน่งใหม่ (เลื่อนตาม offset)
    ctx.drawImage(oldCanvas, offsetDiff, offsetDiff);
    
    // บันทึก state ใหม่
    if (typeof saveState === 'function') {
        saveState();
    }
    
    // อัปเดตข้อมูล
    updatePDFInfo(originalImageWidth, originalImageHeight, newCanvasWidth, newCanvasHeight, currentPage);
    
    // อัปเดตปุ่ม
    updatePaddingButton();
    
    console.log(`✅ PDF Padding increased to ${pdfPaddingSize}px | Canvas: ${newCanvasWidth}x${newCanvasHeight}px`);
    console.log(`🎯 PDF centered at (${imageOffsetX}, ${imageOffsetY})`);
}

// อัปเดตข้อมูล PDF (แก้ไขให้แสดงข้อมูล padding)
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
            <strong>พื้นที่รอบๆ:</strong><br>
            <span style="color: var(--primary-color); font-size: 1.2rem;">${pdfPaddingSize}px</span><br>
            <span style="font-size: 0.75rem; color: var(--text-secondary);">
                <i class="fas fa-info-circle"></i> พื้นที่วาด ${pdfPaddingSize}px รอบๆ
            </span>
        `;
    }
}

// อัปเดตปุ่มเพิ่มพื้นที่ (เพิ่มใหม่)
function updatePaddingButton() {
    const paddingSizeDisplay = document.getElementById('paddingSizeDisplay');
    if (paddingSizeDisplay) {
        // เช็คว่าเป็น PDF หรือรูปภาพ
        if (pdfDoc) {
            paddingSizeDisplay.textContent = `${pdfPaddingSize}px`;
        } else {
            paddingSizeDisplay.textContent = `${paddingSize}px`;
        }
    }
}

// เพิ่มปุ่มเปลี่ยนหน้า PDF (ไม่แก้ไข)
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

// เปลี่ยนหน้า PDF (ไม่แก้ไข)
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
    
    console.log(`📄 Changing to page ${currentPage}...`);
    
    await renderPDFPage(currentPage);
    
    updatePageButtons();
}

// อัปเดตสถานะปุ่มเปลี่ยนหน้า (ไม่แก้ไข)
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