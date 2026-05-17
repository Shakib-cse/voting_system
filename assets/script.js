document.addEventListener('DOMContentLoaded', () => {
    // --- STATIC SELECTORS ---
    const voteModal = document.getElementById('vote-modal');
    const comicModal = document.getElementById('comic-modal');
    
    // --- VOTE MODAL LOGIC ---
    let activeUsernameId = '';
    const voteForm = document.getElementById('vote-form');
    const voterEmailInput = document.getElementById('voter-email');
    const voteModalTitle = document.getElementById('vote-modal-title');
    const voteFeedback = document.getElementById('vote-feedback');
    
    // Globals for comic viewer
    let currentComicPages = [];
    let currentComicIndex = 0;
    const comicImgElement = document.getElementById('comic-viewer-img');
    const prevBtn = document.getElementById('comic-prev');
    const nextBtn = document.getElementById('comic-next');
    const pageIndicator = document.getElementById('page-indicator');

    // Make functions globally accessible
    window.openVoteModal = function(usernameId, name) {
        activeUsernameId = usernameId;
        voteModalTitle.innerText = `Cast Vote for ${decodeURIComponent(name)}`;
        voterEmailInput.value = '';
        voteFeedback.className = 'form-group';
        voteFeedback.innerHTML = '';
        voteFeedback.style.display = 'none';
        
        voteModal.classList.add('active');
        voterEmailInput.focus();
    };

    window.closeVoteModal = function() {
        voteModal.classList.remove('active');
        activeUsernameId = '';
    };

    // AJAX form submission
    if (voteForm) {
        voteForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const email = voterEmailInput.value.trim();
            if (!email) return;

            // Show loading state
            const submitBtn = voteForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Casting your vote...';

            fetch('vote.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `username_id=${encodeURIComponent(activeUsernameId)}&voter_email=${encodeURIComponent(email)}`
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;

                voteFeedback.style.display = 'block';
                if (data.status === 'success') {
                    voteFeedback.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                    voterEmailInput.value = '';
                    
                    // Close modal after success
                    setTimeout(() => {
                        closeVoteModal();
                    }, 2000);
                } else {
                    voteFeedback.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                voteFeedback.style.display = 'block';
                voteFeedback.innerHTML = `<div class="alert alert-danger">An unexpected error occurred. Please try again.</div>`;
                console.error(err);
            });
        });
    }

    // --- COMIC VIEWER MODAL LOGIC ---
    window.openComicViewer = function(pagesJsonStr) {
        try {
            currentComicPages = JSON.parse(decodeURIComponent(pagesJsonStr)).filter(p => p !== null && p !== '');
            if (currentComicPages.length === 0) return;
            
            currentComicIndex = 0;
            updateComicViewer();
            comicModal.classList.add('active');
        } catch (e) {
            console.error('Error parsing comic pages', e);
        }
    };

    window.closeComicViewer = function() {
        comicModal.classList.remove('active');
    };

    window.prevComicPage = function() {
        if (currentComicIndex > 0) {
            currentComicIndex--;
            updateComicViewer();
        }
    };

    window.nextComicPage = function() {
        if (currentComicIndex < currentComicPages.length - 1) {
            currentComicIndex++;
            updateComicViewer();
        }
    };

    function updateComicViewer() {
        if (currentComicPages.length === 0) return;
        
        comicImgElement.src = currentComicPages[currentComicIndex];
        pageIndicator.innerText = `Page ${currentComicIndex + 1} of ${currentComicPages.length}`;
        
        // Disable/enable controls
        if (currentComicIndex === 0) {
            prevBtn.style.opacity = '0.4';
            prevBtn.style.pointerEvents = 'none';
        } else {
            prevBtn.style.opacity = '1';
            prevBtn.style.pointerEvents = 'all';
        }

        if (currentComicIndex === currentComicPages.length - 1) {
            nextBtn.style.opacity = '0.4';
            nextBtn.style.pointerEvents = 'none';
        } else {
            nextBtn.style.opacity = '1';
            nextBtn.style.pointerEvents = 'all';
        }
    }

    // --- FILE UPLOAD PREVIEWS ---
    const fileInputs = document.querySelectorAll('.file-upload-box input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = this.files[0];
            const uploadBox = this.closest('.file-upload-box');
            const previewEl = uploadBox.querySelector('.file-preview');
            const previewImg = previewEl.querySelector('img');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewEl.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    });

    window.removePreview = function(event, btn) {
        event.preventDefault();
        event.stopPropagation();
        const uploadBox = btn.closest('.file-upload-box');
        const fileInput = uploadBox.querySelector('input[type="file"]');
        const previewEl = uploadBox.querySelector('.file-preview');
        const previewImg = previewEl.querySelector('img');
        
        fileInput.value = '';
        previewImg.src = '';
        previewEl.style.display = 'none';
    };

    // Close modals on clicking overlay background
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('active');
            }
        });
    });

    // Keyboard support for modals
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeVoteModal();
            closeComicViewer();
        } else if (e.key === 'ArrowRight' && comicModal.classList.contains('active')) {
            nextComicPage();
        } else if (e.key === 'ArrowLeft' && comicModal.classList.contains('active')) {
            prevComicPage();
        }
    });
});
