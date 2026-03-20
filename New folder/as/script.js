// Theme Toggle Functionality
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

// โหลด theme ที่บันทึกไว้
const currentTheme = localStorage.getItem('theme') || 'light';
html.setAttribute('data-theme', currentTheme);
updateThemeIcon(currentTheme);

themeToggle.addEventListener('click', () => {
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeIcon(newTheme);
});

function updateThemeIcon(theme) {
    const icon = themeToggle.querySelector('i');
    if (theme === 'dark') {
        icon.classList.remove('fa-moon');
        icon.classList.add('fa-sun');
    } else {
        icon.classList.remove('fa-sun');
        icon.classList.add('fa-moon');
    }
}

// Drag & Drop Functionality
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileToUpload');
const selectedFile = document.getElementById('selectedFile');

if (dropZone) {
    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    // Highlight drop zone when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });

    // Handle dropped files
    dropZone.addEventListener('drop', handleDrop, false);

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight(e) {
        dropZone.classList.add('drag-over');
    }

    function unhighlight(e) {
        dropZone.classList.remove('drag-over');
    }

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        handleFiles(files);
    }
}

// File Input Display Name
if (fileInput && selectedFile) {
    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });
}

function handleFiles(files) {
    if (files.length > 0) {
        const file = files[0];
        const size = (file.size / 1024).toFixed(2);
        selectedFile.innerHTML = `
            <i class="fas fa-check-circle"></i>
            <strong>${file.name}</strong> (${size} KB)
        `;
        selectedFile.classList.add('show');
    }
}

// Form Validation & Progress
const uploadForm = document.getElementById('uploadForm');
const uploadProgress = document.getElementById('uploadProgress');

if (uploadForm) {
    uploadForm.addEventListener('submit', (e) => {
        const file = fileInput.files[0];
        
        if (!file) {
            e.preventDefault();
            alert('กรุณาเลือกไฟล์ที่ต้องการอัปโหลด');
            return false;
        }
        
        // ตรวจสอบขนาดไฟล์ (50MB)
        const maxSize = 50 * 1024 * 1024;
        if (file.size > maxSize) {
            e.preventDefault();
            alert('ไฟล์มีขนาดใหญ่เกินไป! (สูงสุด 50MB)');
            return false;
        }
        
        // แสดง loading
        const submitBtn = uploadForm.querySelector('.btn-upload');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังอัปโหลด...';
        submitBtn.disabled = true;
        
        // แสดง progress bar (จำลอง)
        if (uploadProgress) {
            uploadProgress.style.display = 'block';
            simulateProgress();
        }
    });
}

function simulateProgress() {
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    let progress = 0;
    
    const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 90) progress = 90;
        
        progressFill.style.width = progress + '%';
        progressText.textContent = Math.round(progress) + '%';
        
        if (progress >= 90) {
            clearInterval(interval);
        }
    }, 200);
}

// Smooth Scroll
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});