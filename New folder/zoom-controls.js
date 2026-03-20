// Zoom Controls
var currentZoom = 100;
var isPanning = false;
var startPanX = 0;
var startPanY = 0;
var scrollLeft = 0;
var scrollTop = 0;

// Initialize Zoom Controls
function initializeZoomControls() {
    console.log('🔍 Initializing zoom controls...');
    
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const zoomResetBtn = document.getElementById('zoomResetBtn');
    const zoomFitBtn = document.getElementById('zoomFitBtn');
    const zoomSlider = document.getElementById('zoomSlider');
    const canvasArea = document.querySelector('.canvas-area');
    
    if (!zoomInBtn || !zoomOutBtn || !zoomSlider) {
        console.warn('⚠️ Zoom controls not found');
        return;
    }
    
    // Zoom In Button
    zoomInBtn.addEventListener('click', function() {
        setZoom(currentZoom + 25);
    });
    
    // Zoom Out Button
    zoomOutBtn.addEventListener('click', function() {
        setZoom(currentZoom - 25);
    });
    
    // Reset Button (100%)
    zoomResetBtn.addEventListener('click', function() {
        setZoom(100);
    });
    
    // Fit to Screen Button
    zoomFitBtn.addEventListener('click', function() {
        fitToScreen();
    });
    
    // Zoom Slider
    zoomSlider.addEventListener('input', function() {
        setZoom(parseInt(this.value));
    });
    
    // Mouse Wheel Zoom
    if (canvasArea) {
        canvasArea.addEventListener('wheel', function(e) {
            if (e.ctrlKey) {
                e.preventDefault();
                const delta = e.deltaY > 0 ? -25 : 25;
                setZoom(currentZoom + delta);
            }
        }, { passive: false });
        
        // Pan/Drag functionality
        canvasArea.addEventListener('mousedown', startPan);
        canvasArea.addEventListener('mousemove', doPan);
        canvasArea.addEventListener('mouseup', endPan);
        canvasArea.addEventListener('mouseleave', endPan);
    }
    
    // Keyboard Shortcuts
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('drawingModal').style.display === 'block') {
            // Ctrl + Plus
            if (e.ctrlKey && (e.key === '+' || e.key === '=')) {
                e.preventDefault();
                setZoom(currentZoom + 25);
            }
            // Ctrl + Minus
            if (e.ctrlKey && e.key === '-') {
                e.preventDefault();
                setZoom(currentZoom - 25);
            }
            // Ctrl + 0 (Reset)
            if (e.ctrlKey && e.key === '0') {
                e.preventDefault();
                setZoom(100);
            }
        }
    });
    
    console.log('✅ Zoom controls initialized');
}

// Set Zoom Level
function setZoom(zoomLevel) {
    // จำกัดระหว่าง 25% - 300%
    zoomLevel = Math.max(25, Math.min(300, zoomLevel));
    currentZoom = zoomLevel;
    
    // อัปเดต UI
    updateZoomUI();
    
    // ใช้ CSS Transform เพื่อซูม
    applyZoom();
    
    console.log(`🔍 Zoom set to ${currentZoom}%`);
}

// Update Zoom UI
function updateZoomUI() {
    const zoomDisplay = document.getElementById('zoomDisplay');
    const zoomSlider = document.getElementById('zoomSlider');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    
    if (zoomDisplay) {
        zoomDisplay.textContent = currentZoom + '%';
    }
    
    if (zoomSlider) {
        zoomSlider.value = currentZoom;
    }
    
    // Disable buttons ตามขอบเขต
    if (zoomInBtn) {
        zoomInBtn.disabled = currentZoom >= 300;
    }
    
    if (zoomOutBtn) {
        zoomOutBtn.disabled = currentZoom <= 25;
    }
}

// Apply Zoom using CSS Transform
function applyZoom() {
    const canvasWrapper = document.querySelector('.canvas-wrapper');
    const canvasArea = document.querySelector('.canvas-area');
    
    if (canvasWrapper) {
        const scale = currentZoom / 100;
        canvasWrapper.style.transform = `scale(${scale})`;
        
        // เพิ่ม class สำหรับ cursor
        if (currentZoom > 100 && canvasArea) {
            canvasArea.classList.add('zoomed');
        } else if (canvasArea) {
            canvasArea.classList.remove('zoomed');
        }
    }
}

// Fit to Screen
function fitToScreen() {
    const canvasArea = document.querySelector('.canvas-area');
    const canvasWrapper = document.querySelector('.canvas-wrapper');
    
    if (!canvasArea || !canvasWrapper) return;
    
    const areaWidth = canvasArea.clientWidth - 60;
    const areaHeight = canvasArea.clientHeight - 110;
    
    const wrapperWidth = canvasWrapper.offsetWidth;
    const wrapperHeight = canvasWrapper.offsetHeight;
    
    const scaleX = areaWidth / wrapperWidth;
    const scaleY = areaHeight / wrapperHeight;
    
    const scale = Math.min(scaleX, scaleY, 1) * 100;
    
    setZoom(Math.round(scale / 25) * 25); // Round to nearest 25%
    
    console.log(`📐 Fit to screen: ${Math.round(scale)}%`);
}

// Pan/Drag Functions
function startPan(e) {
    const canvasArea = document.querySelector('.canvas-area');
    
    // ใช้ได้เฉพาะเมื่อซูมมากกว่า 100%
    if (currentZoom <= 100) return;
    
    isPanning = true;
    startPanX = e.clientX;
    startPanY = e.clientY;
    scrollLeft = canvasArea.scrollLeft;
    scrollTop = canvasArea.scrollTop;
    
    canvasArea.classList.add('dragging');
}

function doPan(e) {
    if (!isPanning) return;
    
    e.preventDefault();
    
    const canvasArea = document.querySelector('.canvas-area');
    const deltaX = e.clientX - startPanX;
    const deltaY = e.clientY - startPanY;
    
    canvasArea.scrollLeft = scrollLeft - deltaX;
    canvasArea.scrollTop = scrollTop - deltaY;
}

function endPan() {
    isPanning = false;
    const canvasArea = document.querySelector('.canvas-area');
    canvasArea.classList.remove('dragging');
}

// Reset Zoom (เรียกใช้เมื่อปิด modal)
function resetZoom() {
    currentZoom = 100;
    updateZoomUI();
    applyZoom();
}

// Export functions
window.initializeZoomControls = initializeZoomControls;
window.setZoom = setZoom;
window.resetZoom = resetZoom;