document.addEventListener("DOMContentLoaded", function() {
    const tabs = document.querySelectorAll('.seup-tab');
    const tabPanes = document.querySelectorAll('.seup-tab-pane');

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const target = this.dataset.tab;
            if (!target) return;

            tabs.forEach(t => t.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));
            this.classList.add('active');

            const pane = document.getElementById(target);
            if (pane) pane.classList.add('active');

            if (target === 'prepregled' && typeof openOmotPreview === 'function') {
                openOmotPreview();
            }
        });
    });

    const dodajAktBtn = document.getElementById('dodajAktBtn');
    const dodajPrilogBtn = document.getElementById('dodajPrilogBtn');
    const sortirajNedodjeljenoBtn = document.getElementById('sortirajNedodjeljenoBtn');

    if (dodajAktBtn) {
        dodajAktBtn.addEventListener('click', function() {
            aktUploadModal.classList.add('show');
        });
    }

    if (dodajPrilogBtn) {
        dodajPrilogBtn.addEventListener('click', function() {
            prilogUploadModal.classList.add('show');
        });
    }

    if (sortirajNedodjeljenoBtn) {
        sortirajNedodjeljenoBtn.addEventListener('click', function() {
        });
    }

    const aktUploadModal = document.getElementById('aktUploadModal');
    const aktUploadForm = document.getElementById('aktUploadForm');
    const uploadAktBtn = document.getElementById('uploadAktBtn');
    const aktFileInput = document.getElementById('aktFile');

    function openAktModal() {
        aktUploadModal.classList.add('show');
    }

    function closeAktModal() {
        aktUploadModal.classList.remove('show');
        aktUploadForm.reset();
    }

    function uploadAkt() {
        const file = aktFileInput.files[0];
        if (!file) {
            showMessage('Molimo odaberite datoteku', 'error');
            return;
        }

        if (file.size > 50 * 1024 * 1024) {
            showMessage('Datoteka je prevelika (maksimalno 50MB)', 'error');
            return;
        }

        uploadAktBtn.classList.add('seup-loading');
        const progressDiv = document.getElementById('uploadProgress');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');

        progressDiv.style.display = 'block';
        progressText.textContent = 'Uploading akt...';

        const formData = new FormData(aktUploadForm);

        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                progressFill.style.width = percentComplete + '%';
                progressText.textContent = `Uploading... ${Math.round(percentComplete)}%`;
            }
        });

        xhr.addEventListener('load', function() {
            try {
                console.log('HTTP Status:', xhr.status);
                console.log('Server response:', xhr.responseText);
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    showMessage(response.message, 'success');
                    closeAktModal();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage('Greška pri uploadu: ' + response.error, 'error');
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', xhr.responseText);
                showMessage('Greška pri obradi odgovora: ' + xhr.responseText.substring(0, 200), 'error');
            }
        });

        xhr.addEventListener('error', function() {
            showMessage('Greška pri uploadu datoteke', 'error');
        });

        xhr.addEventListener('loadend', function() {
            uploadAktBtn.classList.remove('seup-loading');
            progressDiv.style.display = 'none';
            progressFill.style.width = '0%';
        });

        xhr.open('POST', '', true);
        xhr.send(formData);
    }

    document.getElementById('closeAktModal').addEventListener('click', closeAktModal);
    document.getElementById('cancelAktBtn').addEventListener('click', closeAktModal);
    document.getElementById('uploadAktBtn').addEventListener('click', uploadAkt);

    aktUploadModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeAktModal();
        }
    });

    const prilogUploadModal = document.getElementById('prilogUploadModal');
    const prilogUploadForm = document.getElementById('prilogUploadForm');
    const uploadPrilogBtn = document.getElementById('uploadPrilogBtn');
    const prilogFileInput = document.getElementById('prilogFile');
    const aktSelect = document.getElementById('aktSelect');

    function openPrilogModal() {
        prilogUploadModal.classList.add('show');
    }

    function closePrilogModal() {
        prilogUploadModal.classList.remove('show');
        if (prilogUploadForm) {
            prilogUploadForm.reset();
        }
    }

    function uploadPrilog() {
        if (!prilogUploadForm) {
            showMessage('Nema dostupnih akata za dodavanje priloga', 'error');
            return;
        }

        const file = prilogFileInput.files[0];
        const selectedAkt = aktSelect.value;

        if (!selectedAkt) {
            showMessage('Molimo odaberite akt', 'error');
            return;
        }

        if (!file) {
            showMessage('Molimo odaberite datoteku', 'error');
            return;
        }

        if (file.size > 50 * 1024 * 1024) {
            showMessage('Datoteka je prevelika (maksimalno 50MB)', 'error');
            return;
        }

        uploadPrilogBtn.classList.add('seup-loading');
        const progressDiv = document.getElementById('uploadProgress');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');

        progressDiv.style.display = 'block';
        progressText.textContent = 'Uploading prilog...';

        const formData = new FormData(prilogUploadForm);

        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                progressFill.style.width = percentComplete + '%';
                progressText.textContent = `Uploading... ${Math.round(percentComplete)}%`;
            }
        });

        xhr.addEventListener('load', function() {
            try {
                console.log('HTTP Status (prilog):', xhr.status);
                console.log('Server response (prilog):', xhr.responseText);
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    showMessage(response.message, 'success');
                    closePrilogModal();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage('Greška pri uploadu: ' + response.error, 'error');
                }
            } catch (e) {
                console.error('JSON parse error (prilog):', e);
                console.error('Response text:', xhr.responseText);
                showMessage('Greška pri obradi odgovora: ' + xhr.responseText.substring(0, 200), 'error');
            }
        });

        xhr.addEventListener('error', function() {
            showMessage('Greška pri uploadu datoteke', 'error');
        });

        xhr.addEventListener('loadend', function() {
            uploadPrilogBtn.classList.remove('seup-loading');
            progressDiv.style.display = 'none';
            progressFill.style.width = '0%';
        });

        xhr.open('POST', '', true);
        xhr.send(formData);
    }

    if (document.getElementById('closePrilogModal')) {
        document.getElementById('closePrilogModal').addEventListener('click', closePrilogModal);
    }
    if (document.getElementById('cancelPrilogBtn')) {
        document.getElementById('cancelPrilogBtn').addEventListener('click', closePrilogModal);
    }
    if (document.getElementById('uploadPrilogBtn')) {
        document.getElementById('uploadPrilogBtn').addEventListener('click', uploadPrilog);
    }
    if (document.getElementById('dodajAktPrviBtn')) {
        document.getElementById('dodajAktPrviBtn').addEventListener('click', function() {
            closePrilogModal();
            dodajAktBtn.click();
        });
    }

    if (prilogUploadModal) {
        prilogUploadModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closePrilogModal();
            }
        });
    }

    const generateOmotBtn = document.getElementById('generateOmotBtn');
    const previewOmotBtn = document.getElementById('previewOmotBtn');
    const printOmotBtn = document.getElementById('printOmotBtn');

    if (generateOmotBtn) {
        generateOmotBtn.addEventListener('click', function() {
            this.classList.add('seup-loading');

            const formData = new FormData();
            formData.append('action', 'generate_omot');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showMessage('Greška pri generiranju omota: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showMessage('Došlo je do greške pri generiranju omota', 'error');
            })
            .finally(() => {
                this.classList.remove('seup-loading');
            });
        });
    }

    if (previewOmotBtn) {
        previewOmotBtn.addEventListener('click', function() {
            openOmotPreview();
        });
    }

    if (printOmotBtn) {
        printOmotBtn.addEventListener('click', function() {
            openPrintInstructionsModal();
        });
    }

    function openPrintInstructionsModal() {
        const modal = document.getElementById('printInstructionsModal');
        modal.classList.add('show');
    }

    function closePrintInstructionsModal() {
        const modal = document.getElementById('printInstructionsModal');
        modal.classList.remove('show');
    }

    function confirmPrint() {
        closePrintInstructionsModal();

        showMessage('Priprema omot za ispis...', 'success', 2000);

        setTimeout(() => {
            window.print();
        }, 500);
    }

    document.getElementById('closePrintModal').addEventListener('click', closePrintInstructionsModal);
    document.getElementById('cancelPrintBtn').addEventListener('click', closePrintInstructionsModal);
    document.getElementById('confirmPrintBtn').addEventListener('click', confirmPrint);

    document.getElementById('printInstructionsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePrintInstructionsModal();
        }
    });

    function openOmotPreview() {
        const modal = document.getElementById('omotPreviewModal');
        const content = document.getElementById('omotPreviewContent');

        modal.classList.add('show');
        content.innerHTML = '<div class="seup-loading-message"><i class="fas fa-spinner fa-spin"></i> Učitavam prepregled...</div>';

        const formData = new FormData();
        formData.append('action', 'preview_omot');

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = data.preview_html;
            } else {
                content.innerHTML = '<div class="seup-alert seup-alert-error">Greška pri učitavanju prepregleda: ' + data.error + '</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="seup-alert seup-alert-error">Došlo je do greške pri učitavanju prepregleda</div>';
        });
    }

    function closeOmotPreview() {
        document.getElementById('omotPreviewModal').classList.remove('show');
    }

    document.getElementById('closeOmotModal').addEventListener('click', closeOmotPreview);
    document.getElementById('closePreviewBtn').addEventListener('click', closeOmotPreview);

    document.getElementById('generateFromPreviewBtn').addEventListener('click', function() {
        closeOmotPreview();
        generateOmotBtn.click();
    });

    document.getElementById('omotPreviewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeOmotPreview();
        }
    });

    let currentDeleteData = null;

    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-document-btn')) {
            const btn = e.target.closest('.delete-document-btn');
            const filename = btn.dataset.filename;
            const filepath = btn.dataset.filepath;

            currentDeleteData = { filename, filepath };

            document.getElementById('deleteDocName').textContent = filename;

            document.getElementById('deleteDocModal').classList.add('show');
        }
    });

    function closeDeleteModal() {
        document.getElementById('deleteDocModal').classList.remove('show');
        currentDeleteData = null;
    }

    function confirmDelete() {
        if (!currentDeleteData) return;

        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.classList.add('seup-loading');

        const formData = new FormData();
        formData.append('action', 'delete_document');
        formData.append('filename', currentDeleteData.filename);
        formData.append('filepath', currentDeleteData.filepath);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                closeDeleteModal();
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showMessage('Greška pri brisanju: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showMessage('Došlo je do greške pri brisanju dokumenta', 'error');
        })
        .finally(() => {
            confirmBtn.classList.remove('seup-loading');
        });
    }

    document.getElementById('closeDeleteModal').addEventListener('click', closeDeleteModal);
    document.getElementById('cancelDeleteBtn').addEventListener('click', closeDeleteModal);
    document.getElementById('confirmDeleteBtn').addEventListener('click', confirmDelete);

    document.getElementById('deleteDocModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });

    window.showMessage = function(message, type = 'success', duration = 5000) {
        let messageEl = document.querySelector('.seup-message-toast');
        if (!messageEl) {
            messageEl = document.createElement('div');
            messageEl.className = 'seup-message-toast';
            document.body.appendChild(messageEl);
        }

        messageEl.className = `seup-message-toast seup-message-${type} show`;
        messageEl.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
        `;

        setTimeout(() => {
            messageEl.classList.remove('show');
        }, duration);
    };
});
